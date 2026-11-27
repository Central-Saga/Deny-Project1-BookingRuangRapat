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
 * Automated Feature Tests untuk Conflict Booking & RBAC
 * Target: Minimal 8+ test cases yang komprehensif.
 */
class BookingConflictTest extends TestCase
{
    use RefreshDatabase;

    private function getNextWorkingSlot(int $startHour = 10, int $durationHours = 2): array
    {
        // Jam kerja kantor 08:00 - 17:00 WITA (Asia/Makassar)
        $start = Carbon::now('Asia/Makassar')->nextWeekday()->setTime($startHour, 0);
        $end = (clone $start)->addHours($durationHours);

        return [
            'start' => $start->setTimezone(config('app.timezone')),
            'end' => $end->setTimezone(config('app.timezone')),
        ];
    }

    /**
     * 1. User dapat membuat booking pada slot waktu yang kosong.
     */
    public function test_user_can_create_booking_on_available_empty_slot(): void
    {
        $user = User::factory()->create(['role' => 'karyawan']);
        $room = Room::factory()->create(['is_active' => true, 'capacity' => 10]);

        $slot = $this->getNextWorkingSlot(9, 2);

        $payload = [
            'room_id' => $room->id,
            'title' => 'Rapat Perencanaan Proyek',
            'participant_count' => 5,
            'start_time' => $slot['start']->toDateTimeString(),
            'end_time' => $slot['end']->toDateTimeString(),
            'notes' => 'Membahas milestone Q4.',
        ];

        $response = $this->actingAs($user)->postJson('/api/v1/bookings', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.title', 'Rapat Perencanaan Proyek')
            ->assertJsonPath('data.room.id', $room->id)
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('bookings', [
            'room_id' => $room->id,
            'user_id' => $user->id,
            'title' => 'Rapat Perencanaan Proyek',
            'status' => 'pending',
        ]);
    }

    /**
     * 2. Sistem menolak booking yang tumpang tindih persis (exact same slot).
     */
    public function test_system_rejects_overlapping_booking_exact_same_slot(): void
    {
        $user1 = User::factory()->create(['role' => 'karyawan']);
        $user2 = User::factory()->create(['role' => 'karyawan']);
        $room = Room::factory()->create(['is_active' => true, 'capacity' => 10]);

        $slot = $this->getNextWorkingSlot(10, 2);

        Booking::create([
            'user_id' => $user1->id,
            'room_id' => $room->id,
            'title' => 'Booking Pertama',
            'participant_count' => 4,
            'start_time' => $slot['start'],
            'end_time' => $slot['end'],
            'status' => 'pending',
        ]);

        $payload = [
            'room_id' => $room->id,
            'title' => 'Booking Kedua Bentrok',
            'participant_count' => 4,
            'start_time' => $slot['start']->toDateTimeString(),
            'end_time' => $slot['end']->toDateTimeString(),
        ];

        $response = $this->actingAs($user2)->postJson('/api/v1/bookings', $payload);

        $response->assertStatus(422)
            ->assertJsonPath('status', false)
            ->assertJsonPath('message', 'Ruangan sudah dibooking pada jadwal tersebut.');
    }

    /**
     * 3. Sistem menolak booking yang tumpang tindih sebagian (mulai sebelum dan selesai di tengah jadwal eksisting).
     */
    public function test_system_rejects_overlapping_booking_partial_start_overlap(): void
    {
        $user1 = User::factory()->create(['role' => 'karyawan']);
        $user2 = User::factory()->create(['role' => 'karyawan']);
        $room = Room::factory()->create(['is_active' => true, 'capacity' => 10]);

        // Eksisting: 10:00 - 12:00
        $slot = $this->getNextWorkingSlot(10, 2);

        Booking::create([
            'user_id' => $user1->id,
            'room_id' => $room->id,
            'title' => 'Booking 10-12',
            'participant_count' => 3,
            'start_time' => $slot['start'],
            'end_time' => $slot['end'],
            'status' => 'pending',
        ]);

        // Request baru: 09:00 - 11:00 (tumpang tindih pada 10:00 - 11:00)
        $newStart = (clone $slot['start'])->subHour();
        $newEnd = (clone $slot['start'])->addHour();

        $payload = [
            'room_id' => $room->id,
            'title' => 'Booking 09-11 Bentrok',
            'participant_count' => 3,
            'start_time' => $newStart->toDateTimeString(),
            'end_time' => $newEnd->toDateTimeString(),
        ];

        $response = $this->actingAs($user2)->postJson('/api/v1/bookings', $payload);

        $response->assertStatus(422)
            ->assertJsonPath('status', false)
            ->assertJsonPath('message', 'Ruangan sudah dibooking pada jadwal tersebut.');
    }

    /**
     * 4. Sistem menolak booking yang tumpang tindih sebagian (mulai di tengah dan selesai setelah jadwal eksisting).
     */
    public function test_system_rejects_overlapping_booking_partial_end_overlap(): void
    {
        $user1 = User::factory()->create(['role' => 'karyawan']);
        $user2 = User::factory()->create(['role' => 'karyawan']);
        $room = Room::factory()->create(['is_active' => true, 'capacity' => 10]);

        // Eksisting: 10:00 - 12:00
        $slot = $this->getNextWorkingSlot(10, 2);

        Booking::create([
            'user_id' => $user1->id,
            'room_id' => $room->id,
            'title' => 'Booking 10-12',
            'participant_count' => 3,
            'start_time' => $slot['start'],
            'end_time' => $slot['end'],
            'status' => 'pending',
        ]);

        // Request baru: 11:00 - 13:00 (tumpang tindih pada 11:00 - 12:00)
        $newStart = (clone $slot['start'])->addHour();
        $newEnd = (clone $slot['end'])->addHour();

        $payload = [
            'room_id' => $room->id,
            'title' => 'Booking 11-13 Bentrok',
            'participant_count' => 3,
            'start_time' => $newStart->toDateTimeString(),
            'end_time' => $newEnd->toDateTimeString(),
        ];

        $response = $this->actingAs($user2)->postJson('/api/v1/bookings', $payload);

        $response->assertStatus(422)
            ->assertJsonPath('status', false)
            ->assertJsonPath('message', 'Ruangan sudah dibooking pada jadwal tersebut.');
    }

    /**
     * 5. Sistem menolak booking yang mencakup keseluruhan slot eksisting (enclosing overlap).
     */
    public function test_system_rejects_overlapping_booking_enclosing_existing_slot(): void
    {
        $user1 = User::factory()->create(['role' => 'karyawan']);
        $user2 = User::factory()->create(['role' => 'karyawan']);
        $room = Room::factory()->create(['is_active' => true, 'capacity' => 10]);

        // Eksisting: 11:00 - 12:00
        $slot = $this->getNextWorkingSlot(11, 1);

        Booking::create([
            'user_id' => $user1->id,
            'room_id' => $room->id,
            'title' => 'Booking 11-12',
            'participant_count' => 3,
            'start_time' => $slot['start'],
            'end_time' => $slot['end'],
            'status' => 'approved',
        ]);

        // Request baru: 10:00 - 14:00 (melingkupi seluruh slot eksisting)
        $newStart = (clone $slot['start'])->subHour();
        $newEnd = (clone $slot['end'])->addHours(2);

        $payload = [
            'room_id' => $room->id,
            'title' => 'Booking 10-14 Bentrok Penuh',
            'participant_count' => 3,
            'start_time' => $newStart->toDateTimeString(),
            'end_time' => $newEnd->toDateTimeString(),
        ];

        $response = $this->actingAs($user2)->postJson('/api/v1/bookings', $payload);

        $response->assertStatus(422)
            ->assertJsonPath('status', false)
            ->assertJsonPath('message', 'Ruangan sudah dibooking pada jadwal tersebut.');
    }

    /**
     * 6. Validasi waktu selesai (end_time) harus lebih besar daripada waktu mulai (start_time).
     */
    public function test_validation_requires_end_time_greater_than_start_time(): void
    {
        $user = User::factory()->create(['role' => 'karyawan']);
        $room = Room::factory()->create(['is_active' => true, 'capacity' => 10]);

        $slot = $this->getNextWorkingSlot(11, 2);

        // Kasus 1: end_time sama dengan start_time
        $responseEqual = $this->actingAs($user)->postJson('/api/v1/bookings', [
            'room_id' => $room->id,
            'title' => 'Waktu Sama',
            'participant_count' => 2,
            'start_time' => $slot['start']->toDateTimeString(),
            'end_time' => $slot['start']->toDateTimeString(),
        ]);

        $responseEqual->assertStatus(422)
            ->assertJsonPath('status', false)
            ->assertJsonValidationErrors(['end_time']);
        $this->assertContains('end_time', array_column($responseEqual->json('errors') ?? [], 'field'));

        // Kasus 2: end_time mendahului start_time
        $responseEarlier = $this->actingAs($user)->postJson('/api/v1/bookings', [
            'room_id' => $room->id,
            'title' => 'Waktu Mundur',
            'participant_count' => 2,
            'start_time' => $slot['end']->toDateTimeString(),
            'end_time' => $slot['start']->toDateTimeString(),
        ]);

        $responseEarlier->assertStatus(422)
            ->assertJsonPath('status', false)
            ->assertJsonValidationErrors(['end_time']);
        $this->assertContains('end_time', array_column($responseEarlier->json('errors') ?? [], 'field'));
    }

    /**
     * 7. Soft delete booking membebaskan slot jadwal ruangan.
     */
    public function test_soft_deleted_booking_frees_up_room_schedule_slot(): void
    {
        $user1 = User::factory()->create(['role' => 'karyawan']);
        $user2 = User::factory()->create(['role' => 'karyawan']);
        $room = Room::factory()->create(['is_active' => true, 'capacity' => 10]);

        $slot = $this->getNextWorkingSlot(10, 2);

        // Booking 1 dibuat
        $booking = Booking::create([
            'user_id' => $user1->id,
            'room_id' => $room->id,
            'title' => 'Booking yang Dihapus',
            'participant_count' => 3,
            'start_time' => $slot['start'],
            'end_time' => $slot['end'],
            'status' => 'pending',
        ]);

        // Lakukan soft delete pada booking 1
        $booking->delete();
        $this->assertSoftDeleted('bookings', ['id' => $booking->id]);

        // User 2 melakukan booking pada slot yang sama persis
        $payload = [
            'room_id' => $room->id,
            'title' => 'Booking Baru pada Slot Bebas',
            'participant_count' => 3,
            'start_time' => $slot['start']->toDateTimeString(),
            'end_time' => $slot['end']->toDateTimeString(),
        ];

        $response = $this->actingAs($user2)->postJson('/api/v1/bookings', $payload);

        // Harus berhasil karena booking yang di-soft-delete tidak lagi memblokir slot
        $response->assertStatus(201)
            ->assertJsonPath('data.title', 'Booking Baru pada Slot Bebas');

        $this->assertDatabaseHas('bookings', [
            'room_id' => $room->id,
            'user_id' => $user2->id,
            'title' => 'Booking Baru pada Slot Bebas',
            'deleted_at' => null,
        ]);
    }

    /**
     * 8. Booking dengan status cancelled atau rejected tidak memblokir slot ruangan.
     */
    public function test_cancelled_and_rejected_bookings_do_not_block_slot(): void
    {
        $user1 = User::factory()->create(['role' => 'karyawan']);
        $user2 = User::factory()->create(['role' => 'karyawan']);
        $room = Room::factory()->create(['is_active' => true, 'capacity' => 10]);

        $slot = $this->getNextWorkingSlot(14, 2);

        // Booking berstatus cancelled
        Booking::create([
            'user_id' => $user1->id,
            'room_id' => $room->id,
            'title' => 'Booking Batal',
            'participant_count' => 4,
            'start_time' => $slot['start'],
            'end_time' => $slot['end'],
            'status' => 'cancelled',
        ]);

        // Booking baru pada slot yang sama harus berhasil
        $response = $this->actingAs($user2)->postJson('/api/v1/bookings', [
            'room_id' => $room->id,
            'title' => 'Booking Sukses Pengganti',
            'participant_count' => 4,
            'start_time' => $slot['start']->toDateTimeString(),
            'end_time' => $slot['end']->toDateTimeString(),
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.title', 'Booking Sukses Pengganti');
        $this->assertDatabaseHas('bookings', [
            'id' => $response->json('data.id'),
            'status' => 'pending',
        ]);
    }

    /**
     * 9. User hanya bisa membatalkan booking miliknya sendiri (RBAC / Authorization).
     */
    public function test_user_can_only_cancel_their_own_booking_rbac(): void
    {
        $owner = User::factory()->create(['role' => 'karyawan']);
        $otherUser = User::factory()->create(['role' => 'karyawan']);

        $booking = Booking::factory()->create([
            'user_id' => $owner->id,
            'status' => 'pending',
        ]);

        // Karyawan lain mencoba membatalkan booking milik owner -> 403 Forbidden
        $unauthorizedResponse = $this->actingAs($otherUser)
            ->patchJson("/api/v1/bookings/{$booking->id}/cancel", [
                'cancellation_reason' => 'Mencoba batalkan milik orang lain.',
            ]);

        $unauthorizedResponse->assertForbidden();

        $booking->refresh();
        $this->assertSame('pending', $booking->status);

        // Pemilik asli membatalkan booking miliknya sendiri -> 200 OK
        $authorizedResponse = $this->actingAs($owner)
            ->patchJson("/api/v1/bookings/{$booking->id}/cancel", [
                'cancellation_reason' => 'Jadwal diundur minggu depan.',
            ]);

        $authorizedResponse->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $booking->refresh();
        $this->assertSame('cancelled', $booking->status);
        $this->assertSame('Jadwal diundur minggu depan.', $booking->cancellation_reason);
    }

    /**
     * 10. Admin memiliki wewenang untuk approve dan reject booking, sedangkan karyawan tidak (RBAC).
     */
    public function test_only_admin_can_approve_or_reject_bookings_rbac(): void
    {
        $employee = User::factory()->create(['role' => 'karyawan']);
        $admin = User::factory()->create(['role' => 'admin', 'name' => 'Admin Manajer']);

        $booking = Booking::factory()->create(['status' => 'pending']);

        // Karyawan mencoba approve -> 403
        $this->actingAs($employee)
            ->patchJson("/api/v1/bookings/{$booking->id}/approve")
            ->assertForbidden();

        // Admin approve -> 200
        $this->actingAs($admin)
            ->patchJson("/api/v1/bookings/{$booking->id}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.approved_by', $admin->id);

        $booking->refresh();
        $this->assertSame('approved', $booking->status);
    }
}
