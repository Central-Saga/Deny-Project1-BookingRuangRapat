<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Room;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Audit 496 Parameter 21, 32:
 * Feature Test: Karyawan Biasa Tidak Boleh Membatalkan Booking Admin.
 * Uji skenario HTTP 403 Forbidden saat user karyawan biasa mencoba meng-cancel rapat pimpinan.
 */
class EmployeeCannotCancelAdminBookingTest extends TestCase
{
    use RefreshDatabase;

    private function getNextWorkingSlot(int $startHour = 10, int $durationHours = 2): array
    {
        $start = Carbon::now('Asia/Makassar')
            ->nextWeekday()
            ->setTime($startHour, 0)
            ->setTimezone(config('app.timezone'));

        $end = (clone $start)->addHours($durationHours);

        return [
            'start' => $start,
            'end' => $end,
        ];
    }

    /**
     * Skenario Utama Issue #28:
     * Karyawan biasa mencoba membatalkan booking rapat pimpinan (dibuat oleh Admin) wajib ditolak HTTP 403 Forbidden.
     */
    public function test_employee_cannot_cancel_admin_leadership_meeting_returns_403_forbidden(): void
    {
        $admin = User::factory()->create([
            'name' => 'Pimpinan Direksi / Admin',
            'role' => 'admin',
        ]);

        $employee = User::factory()->create([
            'name' => 'Karyawan Biasa',
            'role' => 'karyawan',
        ]);

        $room = Room::factory()->create(['is_active' => true, 'capacity' => 20]);
        $slot = $this->getNextWorkingSlot(10, 2);

        // Rapat pimpinan dibuat oleh admin
        $adminMeeting = Booking::factory()->create([
            'user_id' => $admin->id,
            'room_id' => $room->id,
            'title' => 'Rapat Strategis Dewan Pimpinan & Direksi',
            'participant_count' => 10,
            'start_time' => $slot['start']->toDateTimeString(),
            'end_time' => $slot['end']->toDateTimeString(),
            'status' => 'approved',
            'cancellation_reason' => null,
        ]);

        // Karyawan biasa mencoba membatalkan rapat pimpinan
        $response = $this->actingAs($employee)
            ->patchJson("/api/v1/bookings/{$adminMeeting->id}/cancel", [
                'cancellation_reason' => 'Percobaan membatalkan rapat pimpinan secara tidak sah oleh staf.',
            ]);

        // Wajib ditolak dengan status 403 Forbidden
        $response->assertStatus(403)
            ->assertJson([
                'status' => false,
                'errors' => [],
            ])
            ->assertJsonStructure([
                'status',
                'message',
                'errors',
            ]);

        // Verifikasi integritas data: status rapat pimpinan tetap 'approved' dan alasan batal tetap null
        $adminMeeting->refresh();
        $this->assertSame('approved', $adminMeeting->status);
        $this->assertNull($adminMeeting->cancellation_reason);
    }

    /**
     * Skenario:
     * Karyawan biasa mencoba membatalkan booking admin berstatus pending juga wajib ditolak HTTP 403.
     */
    public function test_employee_cannot_cancel_pending_admin_meeting_returns_403_forbidden(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $employee = User::factory()->create(['role' => 'karyawan']);

        $pendingMeeting = Booking::factory()->create([
            'user_id' => $admin->id,
            'title' => 'Rapat Evaluasi Triwulan Pimpinan (Pending)',
            'status' => 'pending',
            'cancellation_reason' => null,
        ]);

        $response = $this->actingAs($employee)
            ->patchJson("/api/v1/bookings/{$pendingMeeting->id}/cancel", [
                'cancellation_reason' => 'Karyawan mencoba membatalkan meeting pending admin.',
            ]);

        $response->assertForbidden()
            ->assertJsonPath('status', false);

        $pendingMeeting->refresh();
        $this->assertSame('pending', $pendingMeeting->status);
        $this->assertNull($pendingMeeting->cancellation_reason);
    }

    /**
     * Skenario Pembanding:
     * Admin berhak membatalkan rapat pimpinan (otorisasi valid).
     */
    public function test_admin_can_cancel_leadership_meeting_successfully(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $adminMeeting = Booking::factory()->create([
            'user_id' => $admin->id,
            'title' => 'Rapat Pimpinan yang Dibatalkan Resmi',
            'status' => 'pending',
            'cancellation_reason' => null,
        ]);

        $response = $this->actingAs($admin)
            ->patchJson("/api/v1/bookings/{$adminMeeting->id}/cancel", [
                'cancellation_reason' => 'Pembatalan resmi rapat pimpinan karena agenda mendadak dari direksi.',
            ]);

        $response->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.status', 'cancelled');

        $adminMeeting->refresh();
        $this->assertSame('cancelled', $adminMeeting->status);
        $this->assertSame('Pembatalan resmi rapat pimpinan karena agenda mendadak dari direksi.', $adminMeeting->cancellation_reason);
    }
}
