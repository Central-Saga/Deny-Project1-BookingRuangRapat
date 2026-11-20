<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Room;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Audit 496 Parameter 21, 22:
 * Feature Test: Pemesanan Ruangan Melebihi Kapasitas Wajib Ditolak.
 * Uji skenario penolakan HTTP 422 saat user memesan ruangan berkapasitas 5 orang untuk 12 peserta rapat.
 */
class RoomCapacityExceededRejectionTest extends TestCase
{
    use RefreshDatabase;

    private function getNextWorkingSlot(int $startHour = 9, int $durationHours = 2): array
    {
        $start = Carbon::now('Asia/Makassar')
            ->nextWeekday()
            ->setTime($startHour, 0)
            ->setTimezone(config('app.timezone'));

        $end = (clone $start)->addHours($durationHours);

        return [
            'start' => $start,
            'end'   => $end,
        ];
    }

    /**
     * Skenario Utama Issue #27:
     * User memesan ruangan berkapasitas 5 orang untuk 12 peserta rapat wajib ditolak HTTP 422.
     */
    public function test_booking_room_capacity_5_with_12_participants_is_rejected_with_422(): void
    {
        $employee = User::factory()->create(['role' => 'karyawan']);
        $room = Room::factory()->create([
            'name'      => 'Ruang Rapat Kecil (Meeting Room A)',
            'capacity'  => 5,
            'is_active' => true,
        ]);

        $slot = $this->getNextWorkingSlot(10, 2);

        $payload = [
            'room_id'           => $room->id,
            'title'             => 'Rapat Divisi Skala Besar',
            'participant_count' => 12, // 12 peserta melebihi kapasitas 5
            'start_time'        => $slot['start']->toDateTimeString(),
            'end_time'          => $slot['end']->toDateTimeString(),
            'notes'             => 'Agenda pembahasan strategi tahunan dengan 12 peserta.',
        ];

        $response = $this->actingAs($employee)
            ->postJson('/api/v1/bookings', $payload);

        $response->assertStatus(422)
            ->assertJsonPath('status', false)
            ->assertJsonStructure([
                'status',
                'message',
                'errors',
            ]);

        // Verifikasi error terdaftar pada field participant_count
        $errors = $response->json('errors') ?? [];
        $fields = array_column($errors, 'field');
        $this->assertContains('participant_count', $fields);

        // Verifikasi database tidak menyimpan booking yang melanggar kapasitas
        $this->assertDatabaseCount('bookings', 0);
        $this->assertDatabaseMissing('bookings', [
            'room_id'           => $room->id,
            'participant_count' => 12,
        ]);
    }

    /**
     * Skenario Pembaruan (Update):
     * Memperbarui booking menjadi 12 peserta pada ruangan berkapasitas 5 orang wajib ditolak HTTP 422.
     */
    public function test_updating_booking_to_exceed_room_capacity_5_with_12_participants_is_rejected_with_422(): void
    {
        $employee = User::factory()->create(['role' => 'karyawan']);
        $room = Room::factory()->create([
            'name'      => 'Ruang Diskusi 5 Orang',
            'capacity'  => 5,
            'is_active' => true,
        ]);

        $slot = $this->getNextWorkingSlot(13, 2);

        // Booking awal valid dengan 4 peserta
        $booking = Booking::factory()->create([
            'user_id'           => $employee->id,
            'room_id'           => $room->id,
            'title'             => 'Rapat Awal 4 Orang',
            'participant_count' => 4,
            'start_time'        => $slot['start']->toDateTimeString(),
            'end_time'          => $slot['end']->toDateTimeString(),
            'status'            => 'pending',
        ]);

        // Percobaan update menjadi 12 peserta pada ruangan kapasitas 5
        $response = $this->actingAs($employee)
            ->putJson("/api/v1/bookings/{$booking->id}", [
                'room_id'           => $room->id,
                'title'             => 'Pembaruan Menjadi 12 Orang',
                'participant_count' => 12,
                'start_time'        => $slot['start']->toDateTimeString(),
                'end_time'          => $slot['end']->toDateTimeString(),
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('status', false);

        $errors = $response->json('errors') ?? [];
        $fields = array_column($errors, 'field');
        $this->assertContains('participant_count', $fields);

        // Nilai participant_count di database harus tetap 4 (tidak berubah)
        $this->assertSame(4, $booking->fresh()->participant_count);
    }

    /**
     * Skenario Boundary:
     * Tepat 5 peserta diterima (HTTP 201), namun 6 dan 12 peserta ditolak (HTTP 422).
     */
    public function test_boundary_room_capacity_5_accepts_5_participants_but_rejects_6_and_12(): void
    {
        $employee = User::factory()->create(['role' => 'karyawan']);
        $room = Room::factory()->create([
            'capacity'  => 5,
            'is_active' => true,
        ]);

        $slotA = $this->getNextWorkingSlot(9, 1);
        $slotB = $this->getNextWorkingSlot(11, 1);
        $slotC = $this->getNextWorkingSlot(14, 1);

        // 1. Tepat 5 peserta -> Berhasil (201)
        $response5 = $this->actingAs($employee)->postJson('/api/v1/bookings', [
            'room_id'           => $room->id,
            'title'             => 'Rapat Pas 5 Orang',
            'participant_count' => 5,
            'start_time'        => $slotA['start']->toDateTimeString(),
            'end_time'          => $slotA['end']->toDateTimeString(),
        ]);
        $response5->assertStatus(201)
            ->assertJsonPath('data.participant_count', 5);

        // 2. 6 peserta (melebihi 1 orang) -> Ditolak (422)
        $response6 = $this->actingAs($employee)->postJson('/api/v1/bookings', [
            'room_id'           => $room->id,
            'title'             => 'Rapat 6 Orang (Kelebihan 1)',
            'participant_count' => 6,
            'start_time'        => $slotB['start']->toDateTimeString(),
            'end_time'          => $slotB['end']->toDateTimeString(),
        ]);
        $response6->assertStatus(422)
            ->assertJsonPath('status', false);

        // 3. 12 peserta -> Ditolak (422)
        $response12 = $this->actingAs($employee)->postJson('/api/v1/bookings', [
            'room_id'           => $room->id,
            'title'             => 'Rapat 12 Orang',
            'participant_count' => 12,
            'start_time'        => $slotC['start']->toDateTimeString(),
            'end_time'          => $slotC['end']->toDateTimeString(),
        ]);
        $response12->assertStatus(422)
            ->assertJsonPath('status', false);
    }
}
