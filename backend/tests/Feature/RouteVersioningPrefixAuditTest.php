<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Audit 496 Parameter 7, 16:
 * Route Versioning Prefix /api/v1/ pada Seluruh Rute Booking dan Ruangan
 */
class RouteVersioningPrefixAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_booking_and_room_routes_are_prefixed_with_v1(): void
    {
        $routes = Route::getRoutes();

        $v1BookingRoutes = [];
        $unversionedBookingRoutes = [];

        foreach ($routes as $route) {
            $uri = $route->uri();

            // Rute booking atau ruangan
            if (str_contains($uri, 'bookings') || str_contains($uri, 'rooms')) {
                if (str_starts_with($uri, 'api/v1/')) {
                    $v1BookingRoutes[] = $uri;
                } else {
                    $unversionedBookingRoutes[] = $uri;
                }
            }
        }

        // Pastikan ada rute v1 yang terdaftar
        $this->assertNotEmpty($v1BookingRoutes, 'Harus ada rute bookings/rooms dengan prefix api/v1/');

        // Pastikan tidak ada rute booking/room tanpa prefix v1
        $this->assertEmpty(
            $unversionedBookingRoutes,
            'Seluruh rute booking dan rooms harus diawali prefix v1. Ditemukan tanpa prefix: '.implode(', ', $unversionedBookingRoutes)
        );
    }

    public function test_v1_rooms_endpoint_is_accessible_and_unversioned_returns_404(): void
    {
        $user = User::factory()->create(['role' => 'karyawan']);

        // Versioned endpoint /api/v1/rooms -> 200 OK
        $responseV1 = $this->actingAs($user)->getJson('/api/v1/rooms');
        $responseV1->assertOk();

        // Unversioned endpoint /api/rooms -> 404 Not Found
        $responseUnversioned = $this->actingAs($user)->getJson('/api/rooms');
        $responseUnversioned->assertNotFound();
    }

    public function test_v1_bookings_endpoint_is_accessible_and_unversioned_returns_404(): void
    {
        $user = User::factory()->create(['role' => 'karyawan']);

        // Versioned endpoint /api/v1/bookings -> 200 OK
        $responseV1 = $this->actingAs($user)->getJson('/api/v1/bookings');
        $responseV1->assertOk();

        // Unversioned endpoint /api/bookings -> 404 Not Found
        $responseUnversioned = $this->actingAs($user)->getJson('/api/bookings');
        $responseUnversioned->assertNotFound();
    }

    public function test_v1_availability_endpoint_is_accessible_with_prefix(): void
    {
        $user = User::factory()->create(['role' => 'karyawan']);

        $response = $this->actingAs($user)->getJson('/api/v1/bookings/availability');
        $response->assertOk();
        $response->assertJsonStructure(['status', 'data']);
    }
}
