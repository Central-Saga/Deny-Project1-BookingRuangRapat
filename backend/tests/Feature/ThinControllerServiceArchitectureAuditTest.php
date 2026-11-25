<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\QueueMonitoringController;
use App\Http\Controllers\AuthController;
use App\Models\Booking;
use App\Models\Room;
use App\Models\User;
use App\Services\AuthService;
use App\Services\BookingService;
use App\Services\QueueMonitoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Audit 496 Tahap 15 (Issue #163):
 * [Audit 496][P1][ARC] Logika bisnis ditumpuk di controller sampai sulit dirawat
 *
 * Target:
 * 1. Controller tipis (<= 150 baris, 0 query langsung)
 * 2. Ekstraksi logika bisnis ke Service layer (AuthService, BookingService, QueueMonitoringService)
 * 3. Cache:: digunakan pada controller/service layer
 */
class ThinControllerServiceArchitectureAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_target_controllers_are_thin_and_under_150_lines(): void
    {
        $controllers = [
            'AuthController.php' => app_path('Http/Controllers/AuthController.php'),
            'QueueMonitoringController.php' => app_path('Http/Controllers/Api/QueueMonitoringController.php'),
            'BookingController.php' => app_path('Http/Controllers/Api/BookingController.php'),
        ];

        foreach ($controllers as $name => $path) {
            $this->assertFileExists($path, "File controller {$name} harus ada.");
            $lineCount = count(file($path));
            $this->assertLessThanOrEqual(
                150,
                $lineCount,
                "Controller {$name} harus <= 150 baris (ditemukan {$lineCount} baris)."
            );
        }
    }

    public function test_target_controllers_have_zero_direct_queries(): void
    {
        $controllers = [
            'AuthController.php' => app_path('Http/Controllers/AuthController.php'),
            'QueueMonitoringController.php' => app_path('Http/Controllers/Api/QueueMonitoringController.php'),
            'BookingController.php' => app_path('Http/Controllers/Api/BookingController.php'),
        ];

        $queryPatterns = [
            '/\bDB::/',
            '/->where\(/',
            '/::where\(/',
            '/::find\(/',
            '/::create\(/',
            '/::query\(/',
            '/::select\(/',
            '/->join\(/',
        ];

        foreach ($controllers as $name => $path) {
            $content = file_get_contents($path);
            foreach ($queryPatterns as $pattern) {
                $this->assertDoesNotMatchRegularExpression($pattern, $content, "Controller {$name} tidak boleh memanggil query langsung ({$pattern}).");
            }
        }
    }

    public function test_service_layer_classes_exist_and_use_caching(): void
    {
        $this->assertTrue(class_exists(AuthService::class), 'AuthService harus terdefinisi.');
        $this->assertTrue(class_exists(BookingService::class), 'BookingService harus terdefinisi.');
        $this->assertTrue(class_exists(QueueMonitoringService::class), 'QueueMonitoringService harus terdefinisi.');

        $services = [
            app_path('Services/AuthService.php'),
            app_path('Services/BookingService.php'),
            app_path('Services/QueueMonitoringService.php'),
        ];

        $cacheFound = 0;
        foreach ($services as $servicePath) {
            $this->assertFileExists($servicePath);
            $content = file_get_contents($servicePath);
            if (str_contains($content, 'Cache::remember') || str_contains($content, 'Cache::forget') || str_contains($content, 'Cache::put')) {
                $cacheFound++;
            }
        }

        $this->assertGreaterThanOrEqual(3, $cacheFound, 'Ketiga service layer harus memanfaatkan caching.');
    }

    public function test_service_layer_integrates_correctly_with_booking_lifecycle(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $employee = User::factory()->create(['role' => 'karyawan']);
        $room = Room::factory()->create(['capacity' => 20]);

        // Test create booking through API / Service
        $response = $this->actingAs($employee)->postJson('/api/v1/bookings', [
            'room_id' => $room->id,
            'start_time' => now()->addDays(2)->format('Y-m-d 09:00:00'),
            'end_time' => now()->addDays(2)->format('Y-m-d 11:00:00'),
            'title' => 'Sprint Architecture Review',
            'agenda' => 'Sprint Architecture Review',
            'participant_count' => 10,
            'jumlah_peserta' => 10,
        ]);

        $response->assertStatus(201);
        $bookingId = $response->json('data.id');
        $this->assertNotNull($bookingId);

        // Test approve booking through admin
        $approveResponse = $this->actingAs($admin)->postJson("/api/v1/bookings/{$bookingId}/approve");
        $approveResponse->assertStatus(200);

        $booking = Booking::find($bookingId);
        $this->assertEquals('approved', $booking->status);
    }
}
