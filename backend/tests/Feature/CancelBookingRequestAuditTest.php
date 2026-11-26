<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\BookingController;
use App\Http\Requests\CancelBookingRequest;
use App\Models\Booking;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Audit 496 Parameter 14:
 * FormRequest CancelBookingRequest Wajib Mengisi Alasan Pembatalan (minimal 10 karakter).
 */
class CancelBookingRequestAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_booking_controller_cancel_method_typehints_cancel_booking_request(): void
    {
        $reflection = new ReflectionMethod(BookingController::class, 'cancel');
        $parameters = $reflection->getParameters();

        $this->assertNotEmpty($parameters);
        $firstParam = $parameters[0];

        $type = $firstParam->getType();
        $this->assertNotNull($type);
        $this->assertSame(
            CancelBookingRequest::class,
            $type->getName(),
            'BookingController::cancel must typehint CancelBookingRequest.'
        );
    }

    public function test_cancellation_fails_if_reason_is_missing(): void
    {
        $user = User::factory()->create(['role' => 'karyawan']);
        $room = Room::factory()->create(['is_active' => true]);

        $booking = Booking::factory()->create([
            'user_id' => $user->id,
            'room_id' => $room->id,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($user)->patchJson("/api/v1/bookings/{$booking->id}/cancel", []);

        $response->assertStatus(422)
            ->assertJsonPath('status', false);

        $errors = $response->json('errors') ?? [];
        $fields = array_column($errors, 'field');
        $this->assertContains('cancellation_reason', $fields);

        $booking->refresh();
        $this->assertSame('pending', $booking->status);
    }

    public function test_cancellation_fails_if_reason_is_under_10_characters(): void
    {
        $user = User::factory()->create(['role' => 'karyawan']);
        $room = Room::factory()->create(['is_active' => true]);

        $booking = Booking::factory()->create([
            'user_id' => $user->id,
            'room_id' => $room->id,
            'status' => 'pending',
        ]);

        // 7 characters (under 10)
        $response = $this->actingAs($user)->patchJson("/api/v1/bookings/{$booking->id}/cancel", [
            'cancellation_reason' => 'Batal ya',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('status', false);

        $errors = $response->json('errors') ?? [];
        $fields = array_column($errors, 'field');
        $this->assertContains('cancellation_reason', $fields);

        $booking->refresh();
        $this->assertSame('pending', $booking->status);
    }

    public function test_cancellation_succeeds_with_valid_reason_of_at_least_10_characters(): void
    {
        $user = User::factory()->create(['role' => 'karyawan']);
        $room = Room::factory()->create(['is_active' => true]);

        $booking = Booking::factory()->create([
            'user_id' => $user->id,
            'room_id' => $room->id,
            'status' => 'pending',
        ]);

        $reason = 'Jadwal rapat diundur ke minggu depan oleh direksi.';

        $response = $this->actingAs($user)->patchJson("/api/v1/bookings/{$booking->id}/cancel", [
            'cancellation_reason' => $reason,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonPath('data.cancellation_reason', $reason);

        $booking->refresh();
        $this->assertSame('cancelled', $booking->status);
        $this->assertSame($reason, $booking->cancellation_reason);

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => 'cancelled',
            'cancellation_reason' => $reason,
        ]);
    }
}
