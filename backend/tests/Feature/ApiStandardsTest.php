<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ApiStandardsTest extends TestCase
{
    use RefreshDatabase;

    public function test_invalid_booking_has_consistent_error_envelope(): void
    {
        $employee = User::factory()->create(['role' => 'karyawan']);

        $this->actingAs($employee)
            ->postJson('/api/v1/bookings', [])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('status', false)
            ->assertJsonPath('message', 'Validasi gagal.')
            ->assertJsonPath('data', null)
            ->assertJsonStructure(['errors' => [['field', 'message']]]);
    }

    public function test_nonexistent_api_endpoint_has_consistent_404(): void
    {
        $this->getJson('/api/v1/this-endpoint-does-not-exist')
            ->assertStatus(404)
            ->assertJsonPath('success', false)
            ->assertJsonPath('status', false)
            ->assertJsonPath('data', null)
            ->assertJsonPath('errors', []);
    }

    public function test_production_500_hides_internal_error_details(): void
    {
        config()->set('app.debug', false);
        Route::get('/api/_issue43_test_failure', function (): void {
            throw new \RuntimeException('PRIVATE_STACK_TRACE_TEST_MARKER');
        });

        $this->getJson('/api/_issue43_test_failure')
            ->assertStatus(500)
            ->assertJsonPath('success', false)
            ->assertJsonPath('data', null)
            ->assertJsonPath('message', 'Terjadi kesalahan pada server.')
            ->assertJsonPath('errors', [])
            ->assertDontSee('PRIVATE_STACK_TRACE_TEST_MARKER');
    }

    public function test_login_is_throttled_after_five_requests(): void
    {
        $payload = ['email' => 'no-user@example.test', 'password' => 'wrong-password'];
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.43']);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/login', $payload)->assertStatus(401);
        }

        $this->postJson('/api/login', $payload)
            ->assertStatus(429)
            ->assertJsonPath('success', false)
            ->assertJsonPath('status', false)
            ->assertJsonPath('data', null);
    }

    public function test_cors_preflight_allows_local_frontend_origin(): void
    {
        $response = $this->withHeaders([
            'Origin' => 'http://localhost:3000',
            'Access-Control-Request-Method' => 'GET',
            'Access-Control-Request-Headers' => 'Authorization',
        ])->options('/api/v1/rooms');

        $response->assertHeader('Access-Control-Allow-Origin', 'http://localhost:3000');
    }

    public function test_cors_preflight_does_not_allow_unknown_origin(): void
    {
        $response = $this->withHeaders([
            'Origin' => 'https://untrusted.example',
            'Access-Control-Request-Method' => 'GET',
        ])->options('/api/v1/rooms');

        $this->assertFalse($response->headers->has('Access-Control-Allow-Origin'));
    }

    public function test_booking_list_has_pagination_metadata(): void
    {
        $employee = User::factory()->create(['role' => 'karyawan']);
        $room = Room::factory()->create();
        Booking::factory()->count(3)->create([
            'user_id' => $employee->id,
            'room_id' => $room->id,
            'status' => 'pending',
        ]);

        $this->actingAs($employee)
            ->getJson('/api/v1/bookings?per_page=2&page=2')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.total', 3)
            ->assertJsonCount(1, 'data');
    }

    public function test_room_list_has_pagination_metadata(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Room::factory()->count(3)->create(['is_active' => true]);

        $this->actingAs($admin)
            ->getJson('/api/v1/rooms?per_page=2&page=2')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.total', 3)
            ->assertJsonCount(1, 'data');
    }

    public function test_user_list_has_pagination_metadata(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        User::factory()->count(2)->create();

        $this->actingAs($admin)
            ->getJson('/api/v1/users?per_page=2&page=2')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.total', 3)
            ->assertJsonCount(1, 'data');
    }

    public function test_availability_has_pagination_metadata(): void
    {
        $employee = User::factory()->create(['role' => 'karyawan']);
        $room = Room::factory()->create();
        Booking::factory()->count(3)->create([
            'user_id' => $employee->id,
            'room_id' => $room->id,
            'status' => 'pending',
        ]);

        $this->actingAs($employee)
            ->getJson('/api/v1/bookings/availability?per_page=2&page=2')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.total', 3)
            ->assertJsonCount(1, 'data');
    }

    public function test_per_page_above_limit_returns_422(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->getJson('/api/v1/rooms?per_page=101')
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('errors.0.field', 'per_page');
    }
}
