<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Booking;
use App\Models\Room;
use App\Models\User;
use App\Services\Contracts\BookingServiceInterface;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * Audit 496 Parameter SEC (Issue #178):
 * Log, health check, dan audit trail:
 * - Activitylog di composer dan tabel activity_log ada
 * - Health + readiness (DB, Redis, queue) pada /up dan endpoint health memiliki keys lengkap
 * - Audit trail mutasi kritikal tercatat
 * - Tahan hapus: log tidak dapat dihapus (immutable, 403 pada delete)
 */
class LoggingHealthAndAuditTrailAuditTest extends TestCase
{
    use RefreshDatabase;

    private function getFutureWeekday(): Carbon
    {
        return Carbon::now('Asia/Makassar')->nextWeekday()->setTime(10, 0)->setTimezone(config('app.timezone'));
    }

    /**
     * Parameter SEC 1: activitylog di composer: True; tabel: True.
     */
    public function test_activitylog_is_present_in_composer_and_activity_log_table_exists(): void
    {
        $composerJsonPath = base_path('composer.json');
        $this->assertFileExists($composerJsonPath);

        $composer = json_decode((string) file_get_contents($composerJsonPath), true);
        $require = $composer['require'] ?? [];

        $this->assertArrayHasKey(
            'spatie/laravel-activitylog',
            $require,
            'composer.json harus memuat dependensi spatie/laravel-activitylog.'
        );

        $this->assertTrue(
            Schema::hasTable('activity_log'),
            'Tabel activity_log harus ada dalam skema database.'
        );
    }

    /**
     * Parameter SEC 2: Health + readiness (DB, Redis, queue) — /up->200 dengan keys lengkap.
     */
    public function test_health_check_up_endpoint_returns_200_with_db_redis_and_queue_keys(): void
    {
        $endpoints = ['/up', '/health', '/api/up', '/api/health', '/api/v1/health'];

        foreach ($endpoints as $endpoint) {
            $response = $this->getJson($endpoint);

            $response->assertStatus(200);

            $json = $response->json();
            $this->assertIsArray($json);
            $this->assertNotEmpty($json, "Endpoint {$endpoint} tidak boleh mengembalikan keys kosong.");

            // Pastikan keys utama ada
            $this->assertArrayHasKey('status', $json);
            $this->assertArrayHasKey('ready', $json);
            $this->assertArrayHasKey('database', $json);
            $this->assertArrayHasKey('db', $json);
            $this->assertArrayHasKey('redis', $json);
            $this->assertArrayHasKey('queue', $json);
            $this->assertArrayHasKey('services', $json);

            $this->assertTrue($json['ready']);
            $this->assertSame('ok', $json['status']);
            $this->assertSame('ok', $json['database']);
            $this->assertSame('ok', $json['redis']);
            $this->assertSame('ok', $json['queue']);
        }
    }

    /**
     * Parameter SEC 3: Audit trail mutasi kritikal tercatat.
     */
    public function test_critical_mutations_are_recorded_in_audit_trail(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $employee = User::factory()->create(['role' => 'karyawan', 'status' => 'active']);
        $room = Room::factory()->create(['capacity' => 10, 'is_active' => true]);

        // 1. Create Room tercatat
        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'room',
        ]);

        // 2. Create Booking tercatat
        $start = $this->getFutureWeekday();
        $end = (clone $start)->addHours(2);

        $bookingService = app(BookingServiceInterface::class);
        $booking = $bookingService->createBooking($employee, [
            'room_id' => $room->id,
            'title' => 'Rapat Koordinasi Audit',
            'participant_count' => 5,
            'start_time' => $start->format('Y-m-d H:i:s'),
            'end_time' => $end->format('Y-m-d H:i:s'),
        ]);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'booking',
            'subject_id' => $booking->id,
        ]);

        // 3. Approve Booking tercatat
        $bookingService->approveBooking($booking, $admin->id);
        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'booking',
            'subject_id' => $booking->id,
            'description' => 'Booking disetujui',
        ]);

        // 4. Change Password tercatat
        $this->actingAs($employee)->postJson('/api/v1/change-password', [
            'current_password' => 'password', // Default factory password
            'password' => 'NewSecretPassword123!',
        ]);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'auth',
            'causer_id' => $employee->id,
        ]);
    }

    /**
     * Parameter SEC 4: Tahan hapus (uji hapus log).
     * Log bersifat immutable dan tidak dapat dihapus melalui model maupun endpoint API.
     */
    public function test_audit_log_is_immutable_and_cannot_be_deleted_tahan_hapus(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);

        // Buat log contoh
        activity('security_audit')
            ->causedBy($admin)
            ->log('Pencatatan mutasi keamanan');

        $log = ActivityLog::where('log_name', 'security_audit')->firstOrFail();

        // 1. Uji hapus via model delete() langsung -> harus melempar HttpException 403
        try {
            $log->delete();
            $this->fail('Penghapusan model ActivityLog harus dicegah dengan 403 Forbidden.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
            $this->assertStringContainsString('tahan hapus', $e->getMessage());
        }

        // Pastikan log masih utuh di database (tahan hapus)
        $this->assertDatabaseHas('activity_log', [
            'id' => $log->id,
        ]);

        // 2. Uji hapus via endpoint DELETE API -> harus mengembalikan 403 Forbidden
        $response = $this->actingAs($admin)->deleteJson("/api/v1/activity-log/{$log->id}");
        $response->assertStatus(403)
            ->assertJsonPath('status', false);

        // Uji flush / bulk delete endpoint -> 403 Forbidden
        $flushResponse = $this->actingAs($admin)->deleteJson('/api/v1/activity-log');
        $flushResponse->assertStatus(403)
            ->assertJsonPath('status', false);

        // Log tetap tercatat & tahan hapus
        $this->assertDatabaseHas('activity_log', [
            'id' => $log->id,
        ]);
    }
}
