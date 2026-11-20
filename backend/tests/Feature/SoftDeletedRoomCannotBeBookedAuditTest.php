<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Room;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Audit 496 Parameter 21:
 * Feature Test: Soft-deleted Room Tidak Boleh Muncul di Opsi Booking.
 * Uji bahwa ruangan yang di-soft-delete tidak bisa dipilih oleh user pada form pemesanan rapat.
 */
class SoftDeletedRoomCannotBeBookedAuditTest extends TestCase
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
            'end'   => $end,
        ];
    }

    /**
     * Skenario 1:
     * Ruangan yang di-soft-delete tidak muncul pada endpoint daftar ruangan (opsi form booking).
     */
    public function test_soft_deleted_room_does_not_appear_in_room_options_for_booking(): void
    {
        $employee = User::factory()->create(['role' => 'karyawan']);

        $activeRoom = Room::factory()->create([
            'name'      => 'Ruangan Aktif Tersedia',
            'is_active' => true,
        ]);

        $softDeletedRoom = Room::factory()->create([
            'name'      => 'Ruangan Non-Aktif Terhapus',
            'is_active' => true,
        ]);

        // Lakukan soft delete pada ruangan kedua
        $softDeletedRoom->delete();
        $this->assertSoftDeleted('rooms', ['id' => $softDeletedRoom->id]);

        // Karyawan memuat daftar ruangan untuk opsi form pemesanan
        $response = $this->actingAs($employee)->getJson('/api/v1/rooms');

        $response->assertOk()
            ->assertJsonPath('status', true);

        $roomNames = collect($response->json('data'))->pluck('name')->all();
        $roomIds = collect($response->json('data'))->pluck('id')->all();

        // Ruangan aktif wajib muncul
        $this->assertContains('Ruangan Aktif Tersedia', $roomNames);
        $this->assertContains($activeRoom->id, $roomIds);

        // Ruangan yang di-soft-delete TIDAK BOLEH muncul
        $this->assertNotContains('Ruangan Non-Aktif Terhapus', $roomNames);
        $this->assertNotContains($softDeletedRoom->id, $roomIds);
    }

    /**
     * Skenario 2:
     * User mencoba memesan ruangan yang di-soft-delete wajib ditolak HTTP 422.
     */
    public function test_booking_creation_fails_when_selecting_soft_deleted_room(): void
    {
        $employee = User::factory()->create(['role' => 'karyawan']);
        $softDeletedRoom = Room::factory()->create([
            'name'      => 'Ruang Rapat Arsip',
            'capacity'  => 10,
            'is_active' => true,
        ]);

        // Soft delete room
        $softDeletedRoom->delete();
        $this->assertSoftDeleted('rooms', ['id' => $softDeletedRoom->id]);

        $slot = $this->getNextWorkingSlot(10, 2);

        $payload = [
            'room_id'           => $softDeletedRoom->id,
            'title'             => 'Pemesanan Ruangan yang Sudah Dihapus',
            'participant_count' => 4,
            'start_time'        => $slot['start']->toDateTimeString(),
            'end_time'          => $slot['end']->toDateTimeString(),
            'notes'             => 'Percobaan booking ruangan soft-deleted.',
        ];

        $response = $this->actingAs($employee)->postJson('/api/v1/bookings', $payload);

        // Harus ditolak dengan validasi 422
        $response->assertStatus(422)
            ->assertJsonPath('status', false);

        $errors = $response->json('errors') ?? [];
        $fields = array_column($errors, 'field');
        $this->assertContains('room_id', $fields, 'Field room_id harus gagal validasi karena ruangan berstatus soft-deleted.');

        // Booking tidak tersimpan di database
        $this->assertDatabaseCount('bookings', 0);
    }

    /**
     * Skenario 3:
     * Pembaruan booking untuk dialihkan ke ruangan yang di-soft-delete wajib ditolak HTTP 422.
     */
    public function test_booking_update_fails_when_switching_to_soft_deleted_room(): void
    {
        $employee = User::factory()->create(['role' => 'karyawan']);
        $activeRoom = Room::factory()->create(['is_active' => true, 'capacity' => 10]);
        $softDeletedRoom = Room::factory()->create(['is_active' => true, 'capacity' => 10]);
        $softDeletedRoom->delete();

        $slot = $this->getNextWorkingSlot(13, 2);

        $booking = Booking::factory()->create([
            'user_id'           => $employee->id,
            'room_id'           => $activeRoom->id,
            'title'             => 'Rapat Awal di Ruangan Aktif',
            'participant_count' => 5,
            'start_time'        => $slot['start']->toDateTimeString(),
            'end_time'          => $slot['end']->toDateTimeString(),
            'status'            => 'pending',
        ]);

        // Mencoba update ruangan ke soft-deleted room
        $response = $this->actingAs($employee)->putJson("/api/v1/bookings/{$booking->id}", [
            'room_id'           => $softDeletedRoom->id,
            'title'             => 'Mencoba Pindah ke Ruang Terhapus',
            'participant_count' => 5,
            'start_time'        => $slot['start']->toDateTimeString(),
            'end_time'          => $slot['end']->toDateTimeString(),
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('status', false);

        $errors = $response->json('errors') ?? [];
        $fields = array_column($errors, 'field');
        $this->assertContains('room_id', $fields);

        // Booking tetap di ruangan aktif awal
        $this->assertSame($activeRoom->id, $booking->fresh()->room_id);
    }

    /**
     * Skenario 4:
     * Admin menghapus ruangan, ruangan tersebut seketika tidak bisa dipilih lagi untuk pemesanan.
     */
    public function test_admin_deleting_room_immediately_blocks_subsequent_bookings(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $employee = User::factory()->create(['role' => 'karyawan']);

        $room = Room::factory()->create(['is_active' => true, 'capacity' => 8]);

        // Admin menghapus ruangan
        $deleteResponse = $this->actingAs($admin)->deleteJson("/api/v1/rooms/{$room->id}");
        $deleteResponse->assertNoContent();

        $this->assertSoftDeleted('rooms', ['id' => $room->id]);

        $slot = $this->getNextWorkingSlot(15, 1);

        // Karyawan mencoba booking ke ruangan yang baru saja dihapus
        $bookingResponse = $this->actingAs($employee)->postJson('/api/v1/bookings', [
            'room_id'           => $room->id,
            'title'             => 'Percobaan Booking Pasca Hapus Admin',
            'participant_count' => 3,
            'start_time'        => $slot['start']->toDateTimeString(),
            'end_time'          => $slot['end']->toDateTimeString(),
        ]);

        $bookingResponse->assertStatus(422)
            ->assertJsonPath('status', false);

        $errors = $bookingResponse->json('errors') ?? [];
        $this->assertContains('room_id', array_column($errors, 'field'));
    }
}
