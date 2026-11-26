<?php

namespace Tests\Feature;

use App\DTOs\ChangePasswordDTO;
use App\DTOs\CreateBookingDTO;
use App\DTOs\CreateRoomDTO;
use App\DTOs\UpdateBookingDTO;
use App\DTOs\UpdateRoomDTO;
use App\Models\Booking;
use App\Models\Room;
use App\Models\User;
use App\Services\AuthService;
use App\Services\BookingService;
use App\Services\Contracts\AuthServiceInterface;
use App\Services\Contracts\BookingServiceInterface;
use App\Services\Contracts\QueueMonitoringServiceInterface;
use App\Services\Contracts\RoomServiceInterface;
use App\Services\QueueMonitoringService;
use App\Services\RoomService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Audit 496 Standar P1 ROB (Issue #173):
 * [Audit 496][P1][ROB] Lapisan service belum memakai DTO, transaksi, dan binding.
 *
 * Verifikasi:
 * 1. 100% use case multi-tulis di service memakai DB::transaction
 * 2. Service layer memakai DTO (Data Transfer Object)
 * 3. Service interface di-bind di ServiceProvider ($bindings & $this->app->bind)
 * 4. Idempotency key untuk mutasi kritikal (POST 2x menghasilkan respons identik tanpa duplikasi data)
 */
class ServiceDtoTransactionBindingAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_service_layer_uses_dtos_for_data_transfer(): void
    {
        $this->assertTrue(class_exists(CreateBookingDTO::class), 'CreateBookingDTO harus terdefinisi.');
        $this->assertTrue(class_exists(UpdateBookingDTO::class), 'UpdateBookingDTO harus terdefinisi.');
        $this->assertTrue(class_exists(ChangePasswordDTO::class), 'ChangePasswordDTO harus terdefinisi.');
        $this->assertTrue(class_exists(CreateRoomDTO::class), 'CreateRoomDTO harus terdefinisi.');
        $this->assertTrue(class_exists(UpdateRoomDTO::class), 'UpdateRoomDTO harus terdefinisi.');

        $bookingDto = CreateBookingDTO::fromArray([
            'room_id' => 1,
            'title' => 'Rapat Koordinasi',
            'participant_count' => 8,
            'start_time' => '2026-12-01 09:00:00',
            'end_time' => '2026-12-01 11:00:00',
            'notes' => 'Catatan DTO',
        ]);

        $this->assertEquals(1, $bookingDto->roomId);
        $this->assertEquals('Rapat Koordinasi', $bookingDto->title);
        $this->assertEquals(8, $bookingDto->participantCount);
        $this->assertEquals(['room_id' => 1, 'title' => 'Rapat Koordinasi', 'participant_count' => 8, 'start_time' => '2026-12-01 09:00:00', 'end_time' => '2026-12-01 11:00:00', 'notes' => 'Catatan DTO'], $bookingDto->toArray());

        $passwordDto = ChangePasswordDTO::fromArray([
            'current_password' => 'oldpassword',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);
        $this->assertEquals('oldpassword', $passwordDto->currentPassword);
        $this->assertEquals('newpassword123', $passwordDto->newPassword);
    }

    public function test_service_providers_have_interface_bindings(): void
    {
        $appServiceProviderPath = app_path('Providers/AppServiceProvider.php');
        $this->assertFileExists($appServiceProviderPath);

        $providerContent = file_get_contents($appServiceProviderPath);
        $this->assertMatchesRegularExpression('/\$bindings\s*=\s*\[/', $providerContent, 'AppServiceProvider harus mendefinisikan properti $bindings.');
        $this->assertStringContainsString('bind(', $providerContent, 'AppServiceProvider harus memanggil $this->app->bind().');

        // Test resolving interface from service container
        $bookingService = app(BookingServiceInterface::class);
        $this->assertInstanceOf(BookingService::class, $bookingService);

        $authService = app(AuthServiceInterface::class);
        $this->assertInstanceOf(AuthService::class, $authService);

        $roomService = app(RoomServiceInterface::class);
        $this->assertInstanceOf(RoomService::class, $roomService);

        $queueService = app(QueueMonitoringServiceInterface::class);
        $this->assertInstanceOf(QueueMonitoringService::class, $queueService);
    }

    public function test_all_multi_write_service_operations_use_database_transactions(): void
    {
        $serviceFiles = [
            'BookingService.php' => app_path('Services/BookingService.php'),
            'AuthService.php' => app_path('Services/AuthService.php'),
            'RoomService.php' => app_path('Services/RoomService.php'),
        ];

        foreach ($serviceFiles as $name => $path) {
            $this->assertFileExists($path);
            $content = file_get_contents($path);
            $this->assertStringContainsString('DB::transaction', $content, "Service {$name} harus menerapkan DB::transaction untuk multi-tulis.");
        }

        // Hitung total transaksi di seluruh service layer
        $totalTransactions = 0;
        foreach ($serviceFiles as $path) {
            $totalTransactions += substr_count(file_get_contents($path), 'DB::transaction');
        }

        $this->assertGreaterThanOrEqual(5, $totalTransactions, 'Service layer harus memiliki implementasi DB::transaction pada seluruh mutasi multi-tulis.');
    }

    public function test_idempotency_key_prevents_duplicate_booking_mutations_on_repeated_posts(): void
    {
        $user = User::factory()->create(['role' => 'karyawan']);
        $room = Room::factory()->create(['capacity' => 20, 'is_active' => true]);

        $nextWeekday = now()->next('Monday')->setTime(10, 0, 0);
        $idempotencyKey = (string) Str::uuid();

        $payload = [
            'room_id' => $room->id,
            'start_time' => $nextWeekday->format('Y-m-d H:i:s'),
            'end_time' => $nextWeekday->copy()->addHours(2)->format('Y-m-d H:i:s'),
            'title' => 'Rapat Strategis Idempotency',
            'agenda' => 'Rapat Strategis Idempotency',
            'participant_count' => 10,
            'jumlah_peserta' => 10,
        ];

        // First POST
        $response1 = $this->actingAs($user)->postJson('/api/v1/bookings', $payload, [
            'Idempotency-Key' => $idempotencyKey,
        ]);
        $response1->assertStatus(201);
        $this->assertFalse($response1->headers->has('X-Idempotent-Replayed') && $response1->headers->get('X-Idempotent-Replayed') === 'true');
        $bookingId1 = $response1->json('data.id');

        // Second POST with identical Idempotency-Key (simulating POST 2x / retry)
        $response2 = $this->actingAs($user)->postJson('/api/v1/bookings', $payload, [
            'Idempotency-Key' => $idempotencyKey,
        ]);

        $response2->assertStatus(201);
        $this->assertEquals('true', $response2->headers->get('X-Idempotent-Replayed'));
        $bookingId2 = $response2->json('data.id');

        // Pastikan hanya 1 booking yang benar-benar tersimpan di database
        $this->assertEquals($bookingId1, $bookingId2);
        $this->assertEquals(1, Booking::where('room_id', $room->id)->count());
    }

    public function test_idempotency_key_handles_change_password_repeated_post(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('oldpassword123'),
        ]);

        $idempotencyKey = (string) Str::uuid();
        $payload = [
            'current_password' => 'oldpassword123',
            'password' => 'newpassword456',
            'password_confirmation' => 'newpassword456',
        ];

        // First POST
        $response1 = $this->actingAs($user)->postJson('/api/change-password', $payload, [
            'Idempotency-Key' => $idempotencyKey,
        ]);
        $response1->assertStatus(200);

        // Second POST with identical Idempotency-Key
        $response2 = $this->actingAs($user)->postJson('/api/change-password', $payload, [
            'Idempotency-Key' => $idempotencyKey,
        ]);
        $response2->assertStatus(200);
        $this->assertEquals('true', $response2->headers->get('X-Idempotent-Replayed'));
    }
}
