<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Audit 496 Parameter 34: Uniform Error Response Schema.
 * Memastikan seluruh pesan error validasi dan exception diformat ke format standar:
 * { status: false, message: string, errors: array }
 */
class UniformErrorResponseSchemaAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_validation_error_returns_uniform_error_response_schema(): void
    {
        $employee = User::factory()->create(['role' => 'karyawan']);

        $response = $this->actingAs($employee)
            ->postJson('/api/v1/bookings', []);

        $response->assertStatus(422)
            ->assertJson([
                'status' => false,
            ])
            ->assertJsonStructure([
                'status',
                'message',
                'errors',
            ]);

        $this->assertFalse($response->json('status'));
        $this->assertIsString($response->json('message'));
        $this->assertIsArray($response->json('errors'));
    }

    public function test_not_found_endpoint_returns_uniform_error_response_schema(): void
    {
        $response = $this->getJson('/api/v1/non-existent-endpoint');

        $response->assertStatus(404)
            ->assertJson([
                'status' => false,
                'message' => 'Endpoint tidak ditemukan.',
                'errors' => [],
            ])
            ->assertJsonStructure([
                'status',
                'message',
                'errors',
            ]);

        $this->assertFalse($response->json('status'));
        $this->assertIsString($response->json('message'));
        $this->assertIsArray($response->json('errors'));
    }

    public function test_unauthenticated_request_returns_uniform_error_response_schema(): void
    {
        $response = $this->getJson('/api/v1/bookings');

        $response->assertStatus(401)
            ->assertJson([
                'status' => false,
                'message' => 'Autentikasi diperlukan.',
                'errors' => [],
            ])
            ->assertJsonStructure([
                'status',
                'message',
                'errors',
            ]);

        $this->assertFalse($response->json('status'));
        $this->assertIsString($response->json('message'));
        $this->assertIsArray($response->json('errors'));
    }

    public function test_forbidden_authorization_returns_uniform_error_response_schema(): void
    {
        $employee = User::factory()->create(['role' => 'karyawan']);

        $response = $this->actingAs($employee)
            ->getJson('/api/v1/users');

        $response->assertStatus(403)
            ->assertJson([
                'status' => false,
                'errors' => [],
            ])
            ->assertJsonStructure([
                'status',
                'message',
                'errors',
            ]);

        $this->assertFalse($response->json('status'));
        $this->assertIsString($response->json('message'));
        $this->assertIsArray($response->json('errors'));
    }

    public function test_method_not_allowed_returns_uniform_error_response_schema(): void
    {
        $response = $this->deleteJson('/api/v1/rooms');

        $response->assertStatus(405)
            ->assertJson([
                'status' => false,
                'message' => 'Metode HTTP tidak diizinkan.',
                'errors' => [],
            ])
            ->assertJsonStructure([
                'status',
                'message',
                'errors',
            ]);

        $this->assertFalse($response->json('status'));
        $this->assertIsString($response->json('message'));
        $this->assertIsArray($response->json('errors'));
    }

    public function test_server_error_500_returns_uniform_error_response_schema(): void
    {
        config()->set('app.debug', false);

        Route::get('/api/v1/test-server-error-envelope', function (): void {
            throw new \RuntimeException('Internal crash');
        });

        $response = $this->getJson('/api/v1/test-server-error-envelope');

        $response->assertStatus(500)
            ->assertJson([
                'status' => false,
                'message' => 'Terjadi kesalahan pada server.',
                'errors' => [],
            ])
            ->assertJsonStructure([
                'status',
                'message',
                'errors',
            ]);

        $this->assertFalse($response->json('status'));
        $this->assertIsString($response->json('message'));
        $this->assertIsArray($response->json('errors'));
    }

    public function test_model_not_found_returns_uniform_error_response_schema(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)
            ->putJson('/api/v1/users/999999', [
                'name' => 'Non Existent',
            ]);

        $response->assertStatus(404)
            ->assertJson([
                'status' => false,
                'message' => 'Data tidak ditemukan.',
                'errors' => [],
            ])
            ->assertJsonStructure([
                'status',
                'message',
                'errors',
            ]);

        $this->assertFalse($response->json('status'));
        $this->assertIsString($response->json('message'));
        $this->assertIsArray($response->json('errors'));
    }

    public function test_business_logic_conflict_abort_returns_uniform_error_response_schema(): void
    {
        $employee = User::factory()->create(['role' => 'karyawan']);
        $room = Room::factory()->create(['capacity' => 10, 'is_active' => true]);

        $start = \Illuminate\Support\Carbon::now('Asia/Makassar')
            ->nextWeekday()
            ->setTime(9, 0)
            ->setTimezone(config('app.timezone'));
        $end = (clone $start)->addHours(2);

        // Existing booking: 09:00 - 11:00
        Booking::factory()->create([
            'room_id' => $room->id,
            'start_time' => $start->toDateTimeString(),
            'end_time' => $end->toDateTimeString(),
            'status' => 'pending',
        ]);

        // Attempt overlapping booking: 10:00 - 12:00
        $overlapStart = (clone $start)->addHour();
        $overlapEnd = (clone $end)->addHour();

        $response = $this->actingAs($employee)->postJson('/api/v1/bookings', [
            'room_id' => $room->id,
            'title' => 'Overlapping Booking Attempt',
            'participant_count' => 5,
            'start_time' => $overlapStart->toDateTimeString(),
            'end_time' => $overlapEnd->toDateTimeString(),
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'status' => false,
                'message' => 'Ruangan sudah dibooking pada jadwal tersebut.',
                'errors' => [],
            ])
            ->assertJsonStructure([
                'status',
                'message',
                'errors',
            ]);

        $this->assertFalse($response->json('status'));
        $this->assertIsString($response->json('message'));
        $this->assertIsArray($response->json('errors'));
    }
}
