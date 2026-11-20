<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Audit 496 Parameter 29: RateLimiter Throttle pada Endpoint Pemesanan Rapat.
 * Pasang middleware rate limiter throttle:30,1 untuk mencegah spamming request pemesanan ruang rapat.
 */
class BookingRateLimiterAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_booking_routes_have_throttle_middleware(): void
    {
        $routes = Route::getRoutes();

        $postRoute = $routes->getByName('api.v1.bookings.store')
            ?: collect($routes)->first(fn ($r) => in_array('POST', $r->methods(), true) && str_contains($r->uri(), 'bookings'));

        $this->assertNotNull($postRoute, 'Route POST bookings must exist');
        $this->assertContains('throttle:30,1', $postRoute->gatherMiddleware());

        $putRoute = collect($routes)->first(fn ($r) => in_array('PUT', $r->methods(), true) && str_contains($r->uri(), 'bookings/{booking}'));
        $this->assertNotNull($putRoute, 'Route PUT bookings must exist');
        $this->assertContains('throttle:30,1', $putRoute->gatherMiddleware());

        $cancelRoute = collect($routes)->first(fn ($r) => in_array('PATCH', $r->methods(), true) && str_contains($r->uri(), 'bookings/{booking}/cancel'));
        $this->assertNotNull($cancelRoute, 'Route PATCH cancel booking must exist');
        $this->assertContains('throttle:30,1', $cancelRoute->gatherMiddleware());
    }

    public function test_booking_creation_is_throttled_after_thirty_requests(): void
    {
        $employee = User::factory()->create(['role' => 'karyawan']);
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.29']);

        // Send 30 requests (even invalid payload triggers rate limiter middleware)
        for ($i = 0; $i < 30; $i++) {
            $response = $this->actingAs($employee)
                ->postJson('/api/v1/bookings', []);
            $this->assertEquals(422, $response->status());
        }

        // 31st request must be throttled with HTTP status 429
        $response = $this->actingAs($employee)
            ->postJson('/api/v1/bookings', []);

        $response->assertStatus(429)
            ->assertJson([
                'status' => false,
                'message' => 'Terlalu banyak permintaan. Silakan coba lagi.',
                'errors' => [],
            ])
            ->assertHeader('X-RateLimit-Limit', '30');

        $this->assertFalse($response->json('status'));
        $this->assertIsArray($response->json('errors'));
    }

    public function test_booking_cancel_endpoint_is_throttled_after_thirty_requests(): void
    {
        $employee = User::factory()->create(['role' => 'karyawan']);
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.30']);

        for ($i = 0; $i < 30; $i++) {
            $response = $this->actingAs($employee)
                ->patchJson('/api/v1/bookings/1/cancel', []);
            // Either 422 or 404 depending on validation/model
            $this->assertNotEquals(429, $response->status());
        }

        // 31st request must trigger 429
        $response = $this->actingAs($employee)
            ->patchJson('/api/v1/bookings/1/cancel', []);

        $response->assertStatus(429)
            ->assertJson([
                'status' => false,
                'message' => 'Terlalu banyak permintaan. Silakan coba lagi.',
                'errors' => [],
            ]);
    }
}
