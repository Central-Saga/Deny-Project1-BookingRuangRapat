<?php

namespace Tests\Feature;

use App\Models\Room;
use App\Models\User;
use App\Models\Booking;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_fetch_rooms_list()
    {
        $user = User::factory()->create();
        Room::factory()->count(3)->create(['is_active' => true]);

        $response = $this->actingAs($user)->getJson('/api/v1/rooms');

        $response->assertStatus(200)
                 ->assertJsonCount(3, 'data');
    }

    public function test_user_can_create_booking_successfully()
    {
        $user = User::factory()->create();
        $room = Room::factory()->create(['is_active' => true]);

        $payload = [
            'room_id'    => $room->id,
            'title'      => 'Rapat Koordinasi Tim',
            'start_time' => now()->addHour()->toDateTimeString(),
            'end_time'   => now()->addHours(2)->toDateTimeString(),
            'notes'      => 'Catatan penting rapat.',
        ];

        $response = $this->actingAs($user)->postJson('/api/v1/bookings', $payload);

        $response->assertStatus(201)
                 ->assertJsonFragment(['title' => 'Rapat Koordinasi Tim']);
    }

    public function test_booking_conflict_is_prevented()
    {
        $user = User::factory()->create();
        $room = Room::factory()->create(['is_active' => true]);

        // Menggunakan waktu di masa depan agar lolos validasi 'after:now'
        $startTime = now()->addDays(5);
        $endTime = now()->addDays(5)->addHours(2);

        Booking::create([
            'user_id'    => $user->id,
            'room_id'    => $room->id,
            'title'      => 'Booking Pertama',
            'start_time' => $startTime,
            'end_time'   => $endTime,
            'status'     => 'pending',
        ]);

        $payload = [
            'room_id'    => $room->id,
            'title'      => 'Booking Bentrok',
            'start_time' => (clone $startTime)->addHour()->toDateTimeString(),
            'end_time'   => (clone $endTime)->addHour()->toDateTimeString(),
        ];

        $response = $this->actingAs($user)->postJson('/api/v1/bookings', $payload);

        $response->assertStatus(422)
                 ->assertJson([
                     'message' => 'Ruangan sudah dibooking pada jadwal tersebut.',
                 ]);
    }

    public function test_user_can_cancel_booking()
    {
        $user = User::factory()->create();
        $booking = Booking::factory()->create([
            'user_id' => $user->id,
            'status'  => 'pending',
        ]);

        $response = $this->actingAs($user)->patchJson("/api/v1/bookings/{$booking->id}/cancel");

        $response->assertStatus(200)
                 ->assertJsonFragment(['status' => 'rejected']);
    }
}