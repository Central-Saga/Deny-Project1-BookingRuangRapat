<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Audit 496 Parameter 21:
 * Feature Test: Filter Riwayat Booking Berdasarkan Tanggal & Ruangan.
 * Uji endpoint GET /api/v1/bookings?date=YYYY-MM-DD mengembalikan daftar jadwal yang sesuai.
 */
class BookingFilterDateAndRoomTest extends TestCase
{
    use RefreshDatabase;

    public function test_filter_bookings_by_date_returns_matching_schedule_only(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $room = Room::factory()->create(['is_active' => true]);

        // Booking on Target Date: 2026-11-25
        $bookingTarget1 = Booking::factory()->create([
            'user_id' => $admin->id,
            'room_id' => $room->id,
            'title' => 'Rapat Target 1 Tanggal 25',
            'start_time' => '2026-11-25 09:00:00',
            'end_time' => '2026-11-25 11:00:00',
            'status' => 'pending',
        ]);

        $bookingTarget2 = Booking::factory()->create([
            'user_id' => $admin->id,
            'room_id' => $room->id,
            'title' => 'Rapat Target 2 Tanggal 25',
            'start_time' => '2026-11-25 13:00:00',
            'end_time' => '2026-11-25 15:00:00',
            'status' => 'approved',
        ]);

        // Booking on Different Date: 2026-11-26
        Booking::factory()->create([
            'user_id' => $admin->id,
            'room_id' => $room->id,
            'title' => 'Rapat Tanggal 26 Lain Hari',
            'start_time' => '2026-11-26 10:00:00',
            'end_time' => '2026-11-26 12:00:00',
            'status' => 'pending',
        ]);

        // Request filter date=2026-11-25
        $response = $this->actingAs($admin)
            ->getJson('/api/v1/bookings?date=2026-11-25');

        $response->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonCount(2, 'data');

        $titles = collect($response->json('data'))->pluck('title')->all();
        $this->assertContains('Rapat Target 1 Tanggal 25', $titles);
        $this->assertContains('Rapat Target 2 Tanggal 25', $titles);
        $this->assertNotContains('Rapat Tanggal 26 Lain Hari', $titles);
    }

    public function test_filter_bookings_by_room_returns_matching_room_only(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $roomA = Room::factory()->create(['name' => 'Ruang Melati', 'is_active' => true]);
        $roomB = Room::factory()->create(['name' => 'Ruang Mawar', 'is_active' => true]);

        Booking::factory()->create([
            'user_id' => $admin->id,
            'room_id' => $roomA->id,
            'title' => 'Rapat Khusus Ruang Melati',
            'start_time' => '2026-11-25 09:00:00',
            'end_time' => '2026-11-25 11:00:00',
            'status' => 'approved',
        ]);

        Booking::factory()->create([
            'user_id' => $admin->id,
            'room_id' => $roomB->id,
            'title' => 'Rapat Ruang Mawar Lain',
            'start_time' => '2026-11-25 09:00:00',
            'end_time' => '2026-11-25 11:00:00',
            'status' => 'approved',
        ]);

        // Request filter room_id=RoomA
        $response = $this->actingAs($admin)
            ->getJson("/api/v1/bookings?room_id={$roomA->id}");

        $response->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonCount(1, 'data');

        $this->assertSame('Rapat Khusus Ruang Melati', $response->json('data.0.title'));
        $this->assertEquals($roomA->id, $response->json('data.0.room.id'));
    }

    public function test_filter_bookings_by_date_and_room_simultaneously(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $roomA = Room::factory()->create(['name' => 'Ruang Utama', 'is_active' => true]);
        $roomB = Room::factory()->create(['name' => 'Ruang Cadangan', 'is_active' => true]);

        // Target: Room A + 2026-11-25
        Booking::factory()->create([
            'user_id' => $admin->id,
            'room_id' => $roomA->id,
            'title' => 'Rapat Tepat Tanggal 25 dan Ruang Utama',
            'start_time' => '2026-11-25 10:00:00',
            'end_time' => '2026-11-25 12:00:00',
            'status' => 'pending',
        ]);

        // Room A on different date 2026-11-26
        Booking::factory()->create([
            'user_id' => $admin->id,
            'room_id' => $roomA->id,
            'title' => 'Ruang Utama Tapi Tanggal 26',
            'start_time' => '2026-11-26 10:00:00',
            'end_time' => '2026-11-26 12:00:00',
            'status' => 'pending',
        ]);

        // Room B on same date 2026-11-25
        Booking::factory()->create([
            'user_id' => $admin->id,
            'room_id' => $roomB->id,
            'title' => 'Tanggal 25 Tapi Ruang Cadangan',
            'start_time' => '2026-11-25 10:00:00',
            'end_time' => '2026-11-25 12:00:00',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($admin)
            ->getJson("/api/v1/bookings?date=2026-11-25&room_id={$roomA->id}");

        $response->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonCount(1, 'data');

        $this->assertSame('Rapat Tepat Tanggal 25 dan Ruang Utama', $response->json('data.0.title'));
    }

    public function test_filter_bookings_returns_empty_when_no_match(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $room = Room::factory()->create(['is_active' => true]);

        Booking::factory()->create([
            'user_id' => $admin->id,
            'room_id' => $room->id,
            'start_time' => '2026-11-25 10:00:00',
            'end_time' => '2026-11-25 12:00:00',
        ]);

        // Filter date with no bookings
        $response = $this->actingAs($admin)
            ->getJson('/api/v1/bookings?date=2026-12-31');

        $response->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonCount(0, 'data');
    }
}
