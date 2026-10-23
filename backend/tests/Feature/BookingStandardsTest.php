<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Room;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingStandardsTest extends TestCase
{
    use RefreshDatabase;

    private function validPayload(Room $room): array
    {
        $start = Carbon::now('Asia/Makassar')
            ->nextWeekday()
            ->setTime(10, 0)
            ->setTimezone(config('app.timezone'));

        return [
            'room_id' => $room->id,
            'title' => 'Rapat Standar API',
            'participant_count' => 2,
            'start_time' => $start->toDateTimeString(),
            'end_time' => $start->copy()->addHour()->toDateTimeString(),
            'notes' => 'Pengujian Issue 42',
        ];
    }

    public function test_create_invalid_input_returns_422(): void
    {
        $user = User::factory()->create(['role' => 'karyawan']);
        $room = Room::factory()->create(['is_active' => true]);

        $payload = $this->validPayload($room);
        $payload['title'] = '';
        $payload['participant_count'] = 0;

        $this->actingAs($user)
            ->postJson('/api/v1/bookings', $payload)
            ->assertStatus(422)
            ->assertJsonPath('status', false)
            ->assertJsonPath('message', 'Validasi gagal.')
            ->assertJsonFragment(['field' => 'title']);

        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_update_invalid_input_returns_422(): void
    {
        $user = User::factory()->create(['role' => 'karyawan']);
        $room = Room::factory()->create(['is_active' => true]);

        $booking = Booking::factory()->create([
            'user_id' => $user->id,
            'status' => 'pending',
        ]);

        $payload = $this->validPayload($room);
        $payload['title'] = '';

        $this->actingAs($user)
            ->putJson("/api/v1/bookings/{$booking->id}", $payload)
            ->assertStatus(422)
            ->assertJsonPath('status', false)
            ->assertJsonFragment(['field' => 'title']);
    }

    public function test_employee_cannot_update_other_users_booking(): void
    {
        $owner = User::factory()->create(['role' => 'karyawan']);
        $employee = User::factory()->create(['role' => 'karyawan']);

        $booking = Booking::factory()->create([
            'user_id' => $owner->id,
            'status' => 'pending',
        ]);

        $this->actingAs($employee)
            ->putJson("/api/v1/bookings/{$booking->id}", [])
            ->assertStatus(403)
            ->assertJsonPath('status', false)
            ->assertJsonPath('errors', []);
    }

    public function test_employee_cannot_approve_booking(): void
    {
        $employee = User::factory()->create(['role' => 'karyawan']);

        $booking = Booking::factory()->create([
            'status' => 'pending',
        ]);

        $this->actingAs($employee)
            ->patchJson("/api/v1/bookings/{$booking->id}/approve")
            ->assertStatus(403)
            ->assertJsonPath('status', false)
            ->assertJsonPath('errors', []);
    }

    public function test_employee_cannot_delete_other_users_booking(): void
    {
        $owner = User::factory()->create(['role' => 'karyawan']);
        $employee = User::factory()->create(['role' => 'karyawan']);

        $booking = Booking::factory()->create([
            'user_id' => $owner->id,
            'status' => 'rejected',
        ]);

        $this->actingAs($employee)
            ->deleteJson("/api/v1/bookings/{$booking->id}")
            ->assertStatus(403)
            ->assertJsonPath('status', false);

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
        ]);
    }

    public function test_create_success_has_consistent_api_response(): void
    {
        $user = User::factory()->create(['role' => 'karyawan']);
        $room = Room::factory()->create([
            'capacity' => 10,
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->postJson('/api/v1/bookings', $this->validPayload($room))
            ->assertCreated()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.title', 'Rapat Standar API')
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('errors', [])
            ->assertJsonStructure([
                'status',
                'message',
                'errors',
                'data' => ['id', 'room_id', 'title', 'status'],
            ]);
    }

    public function test_update_success_has_consistent_api_response(): void
    {
        $user = User::factory()->create(['role' => 'karyawan']);

        $room = Room::factory()->create([
            'capacity' => 10,
            'is_active' => true,
        ]);

        $booking = Booking::factory()->create([
            'user_id' => $user->id,
            'status' => 'pending',
        ]);

        $payload = $this->validPayload($room);
        $payload['title'] = 'Rapat Diperbarui';

        $this->actingAs($user)
            ->putJson("/api/v1/bookings/{$booking->id}", $payload)
            ->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.title', 'Rapat Diperbarui')
            ->assertJsonPath('errors', [])
            ->assertJsonStructure([
                'status',
                'message',
                'errors',
                'data' => ['id', 'room_id', 'title', 'status'],
            ]);
    }
}
