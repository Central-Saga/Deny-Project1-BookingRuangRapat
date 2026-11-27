<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Room;
use App\Models\User;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Routing\Router;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Route & Middleware Security Audit Test (Audit 496 Parameter SEC - Issue #179).
 *
 * Menguji:
 * 1. TrustProxies benar: Resolusi IP asli klien dari header X-Forwarded-For.
 * 2. Nested route memakai scopeBindings(): Mencegah cross-room IDOR / akses salah relasi dengan 404.
 * 3. Urutan middleware benar: ThrottleRequests dieksekusi sebelum Authenticate untuk memitigasi brute-force / DoS.
 */
class RouteAndMiddlewareSecurityAuditTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Uji 1: Header X-Forwarded-For dipercaya dan IP asli klien terpakai.
     */
    public function test_trust_proxies_resolves_original_client_ip_from_x_forwarded_for_header(): void
    {
        $trustedIp = '203.0.113.195';

        // Endpoint health / readiness
        $response = $this->withServerVariables([
            'REMOTE_ADDR' => '10.0.0.1',
            'HTTP_X_FORWARDED_FOR' => $trustedIp,
        ])->getJson('/api/v1/health');

        $response->assertStatus(200)
            ->assertJsonPath('ip', $trustedIp)
            ->assertJsonPath('client_ip', $trustedIp);

        // Endpoint dedicated /api/v1/ip
        $responseIp = $this->withServerVariables([
            'REMOTE_ADDR' => '10.0.0.1',
            'HTTP_X_FORWARDED_FOR' => '198.51.100.42',
        ])->getJson('/api/v1/ip');

        $responseIp->assertStatus(200)
            ->assertJsonPath('ip', '198.51.100.42')
            ->assertJsonPath('client_ip', '198.51.100.42');

        // Endpoint /ip pada web routes
        $responseWebIp = $this->withServerVariables([
            'REMOTE_ADDR' => '10.0.0.1',
            'HTTP_X_FORWARDED_FOR' => '192.0.2.1',
        ])->getJson('/ip');

        $responseWebIp->assertStatus(200)
            ->assertJsonPath('ip', '192.0.2.1')
            ->assertJsonPath('client_ip', '192.0.2.1');
    }

    /**
     * Uji 2: Nested route memakai scopeBindings() dan menolak akses booking dari room yang salah (404 Not Found).
     */
    public function test_nested_route_enforces_scoped_bindings_preventing_cross_room_booking_access(): void
    {
        $user = User::factory()->create(['role' => 'karyawan']);
        Sanctum::actingAs($user);

        $roomA = Room::factory()->create(['name' => 'Ruang Sakura']);
        $roomB = Room::factory()->create(['name' => 'Ruang Melati']);

        $bookingA = Booking::factory()->create([
            'room_id' => $roomA->id,
            'user_id' => $user->id,
            'title' => 'Rapat Internal Sakura',
            'status' => 'approved',
        ]);

        // Akses booking via room pemilik yang valid -> 200 OK
        $validResponse = $this->getJson("/api/v1/rooms/{$roomA->id}/bookings/{$bookingA->id}");
        $validResponse->assertStatus(200)
            ->assertJsonPath('data.id', $bookingA->id)
            ->assertJsonPath('data.room_id', $roomA->id);

        // Akses booking via room B yang TIDAK memiliki booking tersebut -> 404 (Scoped Binding)
        $invalidResponse = $this->getJson("/api/v1/rooms/{$roomB->id}/bookings/{$bookingA->id}");
        $invalidResponse->assertStatus(404);

        // Endpoint list booking per ruangan
        $roomBookingsResponse = $this->getJson("/api/v1/rooms/{$roomA->id}/bookings");
        $roomBookingsResponse->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    /**
     * Uji 3: Periksa apakah rute nested di router memiliki flag scopeBindings aktif.
     */
    public function test_route_scope_bindings_are_enabled_on_nested_endpoints(): void
    {
        /** @var Router $router */
        $router = app('router');
        $routes = $router->getRoutes();

        $nestedV1 = null;

        foreach ($routes as $route) {
            if ($route->uri() === 'api/v1/rooms/{room}/bookings/{booking}') {
                $nestedV1 = $route;
            }
        }

        $this->assertNotNull($nestedV1, 'Rute api/v1/rooms/{room}/bookings/{booking} harus terdaftar.');
        $this->assertTrue($nestedV1->enforcesScopedBindings(), 'Rute api/v1/rooms/{room}/bookings/{booking} harus mengaktifkan scopeBindings().');
    }

    /**
     * Uji 4: Urutan middleware memastikan ThrottleRequests dieksekusi sebelum Authenticate.
     */
    public function test_middleware_priority_executes_throttle_requests_before_authenticate(): void
    {
        /** @var Router $router */
        $router = app('router');
        $priority = $router->middlewarePriority;

        $this->assertContains(ThrottleRequests::class, $priority);
        $this->assertContains(Authenticate::class, $priority);

        $throttleIndex = array_search(ThrottleRequests::class, $priority, true);
        $authIndex = array_search(Authenticate::class, $priority, true);

        $this->assertNotFalse($throttleIndex);
        $this->assertNotFalse($authIndex);
        $this->assertLessThan(
            $authIndex,
            $throttleIndex,
            'ThrottleRequests harus memiliki prioritas lebih tinggi (dieksekusi sebelum) Authenticate'
        );

        // Cek juga rute api/v1/bookings
        $route = $router->getRoutes()->getByName('api.v1.bookings.index')
            ?? $router->getRoutes()->match(request()->create('/api/v1/bookings', 'GET'));

        $this->assertNotNull($route);
        $middleware = $route->gatherMiddleware();

        $hasThrottle = false;
        $hasAuth = false;
        foreach ($middleware as $m) {
            if (str_contains($m, 'throttle') || str_contains($m, 'ThrottleRequests')) {
                $hasThrottle = true;
            }
            if (str_contains($m, 'auth') || str_contains($m, 'Authenticate')) {
                $hasAuth = true;
            }
        }

        $this->assertTrue($hasThrottle, 'Route api/v1/bookings harus memiliki throttle middleware.');
        $this->assertTrue($hasAuth, 'Route api/v1/bookings harus memiliki auth middleware.');
    }
}
