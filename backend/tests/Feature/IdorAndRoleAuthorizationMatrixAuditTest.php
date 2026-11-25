<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Audit 496 Parameter TST & SEC (Issue #161):
 * - Matriks Test Otorisasi Diuji per Role: Memastikan otorisasi diuji untuk setiap role ('karyawan' dan 'admin').
 * - Pencegahan IDOR: Data milik pengguna lain TIDAK BISA dibuka, diedit, atau dibatalkan hanya dengan mengganti ID.
 */
class IdorAndRoleAuthorizationMatrixAuditTest extends TestCase
{
    use RefreshDatabase;

    private Room $room;
    private User $karyawanA;
    private User $karyawanB;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->room = Room::factory()->create([
            'is_active' => true,
            'capacity' => 20,
        ]);

        $this->karyawanA = User::factory()->create([
            'name' => 'Karyawan A (Pemilik)',
            'email' => 'karyawan.a@example.com',
            'role' => 'karyawan',
            'status' => 'active',
        ]);

        $this->karyawanB = User::factory()->create([
            'name' => 'Karyawan B (Attacker IDOR)',
            'email' => 'karyawan.b@example.com',
            'role' => 'karyawan',
            'status' => 'active',
        ]);

        $this->admin = User::factory()->create([
            'name' => 'Admin Manajer',
            'email' => 'admin.audit@example.com',
            'role' => 'admin',
            'status' => 'active',
        ]);
    }

    private function createNextWeekdaySlot(int $dayOffset = 1, int $startHour = 9, int $endHour = 10): array
    {
        $date = Carbon::now()->addDays($dayOffset);
        while ($date->isWeekend()) {
            $date->addDay();
        }

        $start = $date->copy()->setTime($startHour, 0, 0);
        $end = $date->copy()->setTime($endHour, 0, 0);

        return [$start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s')];
    }

    /**
     * Uji IDOR: Karyawan B mencoba MEMBUKA data booking milik Karyawan A dengan mengganti ID.
     * Target: Ditolak dengan HTTP 403 Forbidden.
     */
    public function test_role_karyawan_cannot_view_other_users_booking_by_changing_id_idor_prevented(): void
    {
        [$startTime, $endTime] = $this->createNextWeekdaySlot(2, 9, 10);
        $bookingMilikKaryawanA = Booking::factory()->create([
            'room_id' => $this->room->id,
            'user_id' => $this->karyawanA->id,
            'title' => 'Rapat Rahasia Karyawan A',
            'start_time' => $startTime,
            'end_time' => $endTime,
            'status' => 'pending',
            'participant_count' => 5,
        ]);

        // Karyawan B mencoba mengakses data rapat milik Karyawan A dengan mengganti ID di URL
        $response = $this->actingAs($this->karyawanB)
            ->getJson("/api/v1/bookings/{$bookingMilikKaryawanA->id}");

        $response->assertForbidden()
            ->assertJsonPath('status', false);
    }

    /**
     * Uji IDOR: Karyawan B mencoba MENGUBAH (update) booking milik Karyawan A dengan mengganti ID.
     * Target: Ditolak dengan HTTP 403 Forbidden.
     */
    public function test_role_karyawan_cannot_update_other_users_booking_by_changing_id_idor_prevented(): void
    {
        [$startTime, $endTime] = $this->createNextWeekdaySlot(2, 10, 11);
        $bookingMilikKaryawanA = Booking::factory()->create([
            'room_id' => $this->room->id,
            'user_id' => $this->karyawanA->id,
            'title' => 'Agenda Asli Karyawan A',
            'start_time' => $startTime,
            'end_time' => $endTime,
            'status' => 'pending',
            'participant_count' => 4,
        ]);

        [$newStart, $newEnd] = $this->createNextWeekdaySlot(2, 11, 12);
        $response = $this->actingAs($this->karyawanB)
            ->putJson("/api/v1/bookings/{$bookingMilikKaryawanA->id}", [
                'room_id' => $this->room->id,
                'title' => 'Pembajakan Agenda Rapat via IDOR',
                'participant_count' => 4,
                'start_time' => $newStart,
                'end_time' => $newEnd,
            ]);

        $response->assertForbidden()
            ->assertJsonPath('status', false);

        $this->assertSame('Agenda Asli Karyawan A', $bookingMilikKaryawanA->fresh()->title);
    }

    /**
     * Uji IDOR: Karyawan B mencoba MEMBATALKAN booking milik Karyawan A dengan mengganti ID.
     * Target: Ditolak dengan HTTP 403 Forbidden.
     */
    public function test_role_karyawan_cannot_cancel_other_users_booking_by_changing_id_idor_prevented(): void
    {
        [$startTime, $endTime] = $this->createNextWeekdaySlot(3, 9, 10);
        $bookingMilikKaryawanA = Booking::factory()->create([
            'room_id' => $this->room->id,
            'user_id' => $this->karyawanA->id,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->karyawanB)
            ->patchJson("/api/v1/bookings/{$bookingMilikKaryawanA->id}/cancel", [
                'cancellation_reason' => 'Percobaan sabotase pembatalan agenda orang lain.',
            ]);

        $response->assertForbidden()
            ->assertJsonPath('status', false);

        $this->assertSame('pending', $bookingMilikKaryawanA->fresh()->status);
    }

    /**
     * Uji IDOR: Karyawan B mencoba MENGHAPUS booking milik Karyawan A dengan mengganti ID.
     * Target: Ditolak dengan HTTP 403 Forbidden.
     */
    public function test_role_karyawan_cannot_delete_other_users_booking_by_changing_id_idor_prevented(): void
    {
        $bookingCancelled = Booking::factory()->create([
            'room_id' => $this->room->id,
            'user_id' => $this->karyawanA->id,
            'status' => 'cancelled',
        ]);

        $response = $this->actingAs($this->karyawanB)
            ->deleteJson("/api/v1/bookings/{$bookingCancelled->id}");

        $response->assertForbidden();
        $this->assertDatabaseHas('bookings', ['id' => $bookingCancelled->id]);
    }

    /**
     * Otorisasi Sah: Role karyawan DAPAT MEMBUKA & MELIHAT booking miliknya sendiri.
     */
    public function test_role_karyawan_can_view_own_booking(): void
    {
        [$startTime, $endTime] = $this->createNextWeekdaySlot(2, 13, 14);
        $bookingOwn = Booking::factory()->create([
            'room_id' => $this->room->id,
            'user_id' => $this->karyawanA->id,
            'title' => 'Rapat Tim Karyawan A Sendiri',
            'start_time' => $startTime,
            'end_time' => $endTime,
            'status' => 'pending',
            'participant_count' => 6,
        ]);

        $response = $this->actingAs($this->karyawanA)
            ->getJson("/api/v1/bookings/{$bookingOwn->id}");

        $response->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.title', 'Rapat Tim Karyawan A Sendiri');
    }

    /**
     * Otorisasi Sah: Role karyawan DAPAT MEMBATALKAN booking miliknya sendiri.
     */
    public function test_role_karyawan_can_cancel_own_booking(): void
    {
        [$startTime, $endTime] = $this->createNextWeekdaySlot(2, 14, 15);
        $bookingOwn = Booking::factory()->create([
            'room_id' => $this->room->id,
            'user_id' => $this->karyawanA->id,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->karyawanA)
            ->patchJson("/api/v1/bookings/{$bookingOwn->id}/cancel", [
                'cancellation_reason' => 'Perubahan rencana dari pemilik agenda.',
            ]);

        $response->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertSame('cancelled', $bookingOwn->fresh()->status);
    }

    /**
     * Otorisasi Per Role: Role karyawan TIDAK BOLEH menyetujui (approve) atau menolak (reject) booking.
     */
    public function test_role_karyawan_cannot_approve_or_reject_any_booking(): void
    {
        $booking = Booking::factory()->create([
            'room_id' => $this->room->id,
            'user_id' => $this->karyawanA->id,
            'status' => 'pending',
        ]);

        // Karyawan mencoba menyetujui booking
        $this->actingAs($this->karyawanB)
            ->postJson("/api/v1/bookings/{$booking->id}/approve", [
                'approval_notes' => 'Mencoba approve sebagai karyawan biasa.',
            ])
            ->assertForbidden();

        // Karyawan mencoba menolak booking
        $this->actingAs($this->karyawanB)
            ->postJson("/api/v1/bookings/{$booking->id}/reject", [
                'approval_notes' => 'Alasan penolakan minimal 10 karakter.',
            ])
            ->assertForbidden();
    }

    /**
     * Otorisasi Per Role: Role karyawan TIDAK BOLEH mengakses endpoint manajemen admin (users & rooms).
     */
    public function test_role_karyawan_cannot_access_admin_management_endpoints(): void
    {
        $this->actingAs($this->karyawanA)
            ->getJson('/api/v1/users')
            ->assertForbidden();

        $this->actingAs($this->karyawanA)
            ->postJson('/api/v1/users', [
                'name' => 'User Baru',
                'email' => 'user.baru@example.com',
                'role' => 'karyawan',
                'password' => 'Password123!',
            ])
            ->assertForbidden();

        $this->actingAs($this->karyawanA)
            ->postJson('/api/v1/rooms', [
                'name' => 'Ruang Baru Ilegal',
                'capacity' => 10,
                'location' => 'Lantai 1',
            ])
            ->assertForbidden();
    }

    /**
     * Otorisasi Per Role: Role admin DAPAT MEMBUKA & MELIHAT booking siapapun dengan mengganti ID.
     */
    public function test_role_admin_can_view_any_booking_by_changing_id(): void
    {
        [$startTime, $endTime] = $this->createNextWeekdaySlot(2, 15, 16);
        $bookingKaryawan = Booking::factory()->create([
            'room_id' => $this->room->id,
            'user_id' => $this->karyawanA->id,
            'title' => 'Agenda Internal Karyawan A',
            'start_time' => $startTime,
            'end_time' => $endTime,
            'status' => 'pending',
            'participant_count' => 8,
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson("/api/v1/bookings/{$bookingKaryawan->id}");

        $response->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.title', 'Agenda Internal Karyawan A');
    }

    /**
     * Otorisasi Per Role: Role admin DAPAT MEMBATALKAN booking siapapun (Admin Override).
     */
    public function test_role_admin_can_cancel_any_booking(): void
    {
        [$startTime, $endTime] = $this->createNextWeekdaySlot(3, 10, 11);
        $bookingKaryawan = Booking::factory()->create([
            'room_id' => $this->room->id,
            'user_id' => $this->karyawanA->id,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->admin)
            ->patchJson("/api/v1/bookings/{$bookingKaryawan->id}/cancel", [
                'cancellation_reason' => 'Pembatalan darurat oleh administrator fasilitas.',
            ]);

        $response->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertSame('cancelled', $bookingKaryawan->fresh()->status);
    }

    /**
     * Otorisasi Per Role: Role admin DAPAT MENYETUJUI & MENOLAK booking.
     */
    public function test_role_admin_can_approve_and_reject_bookings(): void
    {
        $booking1 = Booking::factory()->create([
            'room_id' => $this->room->id,
            'user_id' => $this->karyawanA->id,
            'status' => 'pending',
        ]);

        $responseApprove = $this->actingAs($this->admin)
            ->postJson("/api/v1/bookings/{$booking1->id}/approve", [
                'approval_notes' => 'Disetujui untuk rapat divisi.',
            ]);

        $responseApprove->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.status', 'approved');

        $booking2 = Booking::factory()->create([
            'room_id' => $this->room->id,
            'user_id' => $this->karyawanA->id,
            'status' => 'pending',
        ]);

        $responseReject = $this->actingAs($this->admin)
            ->postJson("/api/v1/bookings/{$booking2->id}/reject", [
                'approval_notes' => 'Ruangan dialihkan untuk pemeliharaan teknis fasilitas.',
            ]);

        $responseReject->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.status', 'rejected');
    }

    /**
     * Otorisasi Per Role: Role admin DAPAT MENGELOLA user dan ruangan.
     */
    public function test_role_admin_can_manage_users_and_rooms(): void
    {
        // Admin melihat daftar pengguna
        $this->actingAs($this->admin)
            ->getJson('/api/v1/users')
            ->assertOk();

        // Admin membuat ruangan baru
        $responseRoom = $this->actingAs($this->admin)
            ->postJson('/api/v1/rooms', [
                'name' => 'Ruang Eksekutif Sakura',
                'capacity' => 15,
                'location' => 'Lantai 4',
                'facilities' => ['Video Conference', 'Smart TV'],
            ]);

        $responseRoom->assertStatus(201)
            ->assertJsonPath('data.name', 'Ruang Eksekutif Sakura');
    }
}
