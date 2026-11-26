<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\User;
use App\Policies\BookingPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Audit 496 Parameter 32: BookingPolicy Mencegah IDOR Pembatalan Meeting Orang Lain.
 * Karyawan hanya boleh membatalkan meeting yang dibuat oleh dirinya sendiri; Admin memiliki hak override.
 */
class BookingPolicyIdorAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_can_cancel_their_own_booking(): void
    {
        $owner = User::factory()->create(['role' => 'karyawan']);
        $booking = Booking::factory()->create([
            'user_id' => $owner->id,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($owner)
            ->patchJson("/api/v1/bookings/{$booking->id}/cancel", [
                'cancellation_reason' => 'Rapat dibatalkan oleh pemilik agenda rapat.',
            ]);

        $response->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertSame('cancelled', $booking->fresh()->status);
        $this->assertSame('Rapat dibatalkan oleh pemilik agenda rapat.', $booking->fresh()->cancellation_reason);
    }

    public function test_employee_cannot_cancel_other_users_booking_idor_prevented(): void
    {
        $owner = User::factory()->create(['role' => 'karyawan']);
        $attacker = User::factory()->create(['role' => 'karyawan']);

        $booking = Booking::factory()->create([
            'user_id' => $owner->id,
            'status' => 'pending',
        ]);

        // Attempt IDOR: Karyawan lain mencoba membatalkan booking milik owner
        $response = $this->actingAs($attacker)
            ->patchJson("/api/v1/bookings/{$booking->id}/cancel", [
                'cancellation_reason' => 'Percobaan pembatalan meeting orang lain secara IDOR.',
            ]);

        $response->assertForbidden()
            ->assertJson([
                'status' => false,
                'errors' => [],
            ]);

        // Status booking tidak boleh berubah
        $this->assertSame('pending', $booking->fresh()->status);
        $this->assertNull($booking->fresh()->cancellation_reason);
    }

    public function test_admin_has_override_permission_to_cancel_any_booking(): void
    {
        $employee = User::factory()->create(['role' => 'karyawan']);
        $admin = User::factory()->create(['role' => 'admin']);

        $booking = Booking::factory()->create([
            'user_id' => $employee->id,
            'status' => 'pending',
        ]);

        // Admin membatalkan booking karyawan (Admin override)
        $response = $this->actingAs($admin)
            ->patchJson("/api/v1/bookings/{$booking->id}/cancel", [
                'cancellation_reason' => 'Pembatalan darurat oleh administrator kantor.',
            ]);

        $response->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertSame('cancelled', $booking->fresh()->status);
        $this->assertSame('Pembatalan darurat oleh administrator kantor.', $booking->fresh()->cancellation_reason);
    }

    public function test_unauthenticated_request_cannot_cancel_booking(): void
    {
        $booking = Booking::factory()->create(['status' => 'pending']);

        $response = $this->patchJson("/api/v1/bookings/{$booking->id}/cancel", [
            'cancellation_reason' => 'Percobaan membatalkan tanpa autentikasi token.',
        ]);

        $response->assertUnauthorized()
            ->assertJson([
                'status' => false,
                'message' => 'Autentikasi diperlukan.',
            ]);

        $this->assertSame('pending', $booking->fresh()->status);
    }

    public function test_policy_cancel_direct_method_evaluation(): void
    {
        $policy = new BookingPolicy;

        $owner = User::factory()->make(['id' => 10, 'role' => 'karyawan']);
        $otherEmployee = User::factory()->make(['id' => 20, 'role' => 'karyawan']);
        $admin = User::factory()->make(['id' => 30, 'role' => 'admin']);

        $booking = Booking::factory()->make(['user_id' => 10]);

        // Owner can cancel
        $this->assertTrue($policy->cancel($owner, $booking));

        // Other employee CANNOT cancel (IDOR prevented)
        $this->assertFalse($policy->cancel($otherEmployee, $booking));

        // Admin can cancel (Admin override)
        $this->assertTrue($policy->cancel($admin, $booking));
    }
}
