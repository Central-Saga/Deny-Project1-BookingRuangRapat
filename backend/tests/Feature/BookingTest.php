<?php

namespace Tests\Feature;

use App\Models\Room;
use App\Models\User;
use App\Models\Booking;
use Carbon\Carbon;
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

    public function test_employee_can_only_see_own_bookings()
    {
        $employee = User::factory()->create(['role' => 'karyawan']);
        $otherUser = User::factory()->create(['role' => 'karyawan']);
        $ownBooking = Booking::factory()->create(['user_id' => $employee->id]);
        Booking::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->actingAs($employee)->getJson('/api/v1/bookings');

        $response->assertOk()
                 ->assertJsonCount(1, 'data')
                 ->assertJsonFragment(['id' => $ownBooking->id]);
    }

    public function test_admin_can_see_all_bookings()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Booking::factory()->count(2)->create();

        $response = $this->actingAs($admin)->getJson('/api/v1/bookings');

        $response->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_booking_rejects_outside_working_hours_and_weekends()
    {
        $user = User::factory()->create();
        $room = Room::factory()->create(['is_active' => true]);
        $weekend = Carbon::now()->next(Carbon::SATURDAY)->setTime(10, 0);

        $response = $this->actingAs($user)->postJson('/api/v1/bookings', [
            'room_id' => $room->id,
            'title' => 'Booking di luar jadwal',
            'start_time' => $weekend->toDateTimeString(),
            'end_time' => $weekend->copy()->addHour()->toDateTimeString(),
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('start_time');

        $beforeWorkday = Carbon::now()->next(Carbon::MONDAY)->setTime(7, 0);
        $response = $this->actingAs($user)->postJson('/api/v1/bookings', [
            'room_id' => $room->id,
            'title' => 'Booking sebelum jam kerja',
            'start_time' => $beforeWorkday->toDateTimeString(),
            'end_time' => $beforeWorkday->copy()->addHour()->toDateTimeString(),
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('start_time');
    }

    public function test_employee_cannot_cancel_another_users_booking()
    {
        $employee = User::factory()->create(['role' => 'karyawan']);
        $booking = Booking::factory()->create(['status' => 'pending']);

        $response = $this->actingAs($employee)->patchJson("/api/v1/bookings/{$booking->id}/cancel");

        $response->assertForbidden();
        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => 'pending',
        ]);
    }
}