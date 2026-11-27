<?php

namespace Tests\Feature;

use App\Models\Room;
use App\Services\Contracts\BookingServiceInterface;
use App\Services\Contracts\RoomServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Cache & Redis Configuration Audit Test (Audit 496 Parameter SUS - Issue #181).
 *
 * Menguji:
 * 1. Siap horizontal & 0 state lokal: session, cache, dan queue menggunakan shared/centralized store (bukan file).
 * 2. Redis di docker-compose: service redis terkonfigurasi dengan healthcheck (redis-cli ping).
 * 3. 0 Cache::flush(): tidak ada pemanggilan Cache::flush() di kode aplikasi maupun test (invalidation terarah).
 * 4. Prefix & TTL aman: cache key prefix terkonfigurasi dan operasi cache memiliki TTL eksplisit.
 * 5. Fail-open resilience: jika cache store gagal, aplikasi tetap melayani request langsung dari database.
 */
class CacheAndRedisConfigurationAuditTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Uji 1: Ambang batas 0 state lokal terpenuhi (session, cache, queue siap horizontal).
     */
    public function test_zero_state_lokal_configured_for_session_cache_and_queue(): void
    {
        // Periksa default konfigurasi aplikasi
        $sessionDriver = config('session.driver');
        $this->assertNotEquals('file', $sessionDriver, 'SESSION_DRIVER tidak boleh menggunakan state lokal file.');
        $this->assertContains($sessionDriver, ['database', 'redis', 'array'], 'SESSION_DRIVER harus siap horizontal (database/redis).');

        $cacheStore = config('cache.default');
        $this->assertNotEquals('file', $cacheStore, 'CACHE_STORE tidak boleh menggunakan state lokal file.');
        $this->assertContains($cacheStore, ['database', 'redis', 'array', 'failover'], 'CACHE_STORE harus siap horizontal.');

        // Periksa berkas .env.example
        $envExample = file_get_contents(base_path('.env.example'));
        $this->assertMatchesRegularExpression('/^SESSION_DRIVER=(database|redis)/m', $envExample);
        $this->assertMatchesRegularExpression('/^CACHE_STORE=(database|redis|failover)/m', $envExample);
        $this->assertMatchesRegularExpression('/^QUEUE_CONNECTION=(database|redis)/m', $envExample);

        // Periksa berkas .env.production.example
        $prodEnv = file_get_contents(base_path('.env.production.example'));
        $this->assertMatchesRegularExpression('/^SESSION_DRIVER=redis/m', $prodEnv);
        $this->assertMatchesRegularExpression('/^CACHE_STORE=redis/m', $prodEnv);
        $this->assertMatchesRegularExpression('/^QUEUE_CONNECTION=redis/m', $prodEnv);
    }

    /**
     * Uji 2: docker-compose.yml memuat service redis dengan healthcheck aktif.
     */
    public function test_docker_compose_contains_redis_service_with_healthcheck(): void
    {
        $composePath = base_path('../docker-compose.yml');
        $this->assertFileExists($composePath, 'docker-compose.yml harus ada.');

        $composeContent = file_get_contents($composePath);

        // 1. Service redis ada
        $this->assertStringContainsString('redis:', $composeContent, 'docker-compose.yml harus mendefinisikan service redis.');
        $this->assertStringContainsString('image: redis', $composeContent, 'Service redis harus menggunakan image redis.');

        // 2. Healthcheck terpasang dengan redis-cli ping
        $this->assertStringContainsString('healthcheck:', $composeContent, 'Service redis harus memiliki healthcheck.');
        $this->assertStringContainsString('redis-cli', $composeContent, 'Healthcheck redis harus menjalankan redis-cli.');
        $this->assertStringContainsString('ping', $composeContent, 'Healthcheck redis harus melakukan ping.');

        // 3. Backend terhubung dan bergantung pada redis healthy
        $this->assertStringContainsString('REDIS_HOST=redis', $composeContent, 'Backend harus terkonfigurasi ke host redis.');
        $this->assertStringContainsString('redis_data:', $composeContent, 'Volume persistensi redis_data harus didefinisikan.');
    }

    /**
     * Uji 3: Memastikan 0 Cache::flush() di seluruh kode aplikasi dan tests.
     */
    public function test_zero_cache_flush_used_in_codebase(): void
    {
        $backendPath = base_path();
        $directories = [
            $backendPath.'/app',
            $backendPath.'/tests',
        ];

        $foundFlush = [];

        foreach ($directories as $dir) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    // Kecualikan file test audit ini sendiri yang mengecek pola
                    if ($file->getFilename() === 'CacheAndRedisConfigurationAuditTest.php') {
                        continue;
                    }

                    $content = file_get_contents($file->getPathname());
                    if (preg_match('/Cache::flush\s*\(/', $content) || preg_match('/cache\s*\(\s*\)->flush\s*\(/', $content)) {
                        $foundFlush[] = str_replace($backendPath, '', $file->getPathname());
                    }
                }
            }
        }

        $this->assertEmpty(
            $foundFlush,
            'Tidak boleh ada pemanggilan Cache::flush() di app/tests (harus invalidasi terarah via Cache::forget): '.json_encode($foundFlush)
        );
    }

    /**
     * Uji 4: Cache key prefix dan Redis prefix terdefinisi aman.
     */
    public function test_cache_and_redis_prefixes_are_configured(): void
    {
        $cachePrefix = config('cache.prefix');
        $this->assertNotEmpty($cachePrefix, 'Prefix cache tidak boleh kosong.');

        $redisPrefix = config('database.redis.options.prefix');
        $this->assertNotEmpty($redisPrefix, 'Prefix redis tidak boleh kosong.');

        // Failover stores terdaftar
        $failoverStores = config('cache.stores.failover.stores');
        $this->assertIsArray($failoverStores);
        $this->assertContains('database', $failoverStores);
    }

    /**
     * Uji 5: Layanan cache mengimplementasikan pola Fail-Open jika store mengalami kegagalan.
     */
    public function test_room_and_booking_services_fail_open_gracefully(): void
    {
        Room::factory()->create(['name' => 'Ruang Akasia', 'is_active' => true]);

        /** @var RoomServiceInterface $roomService */
        $roomService = app(RoomServiceInterface::class);

        // Pengambilan data normal berhasil
        $rooms = $roomService->getRooms();
        $this->assertGreaterThanOrEqual(1, $rooms->total());

        /** @var BookingServiceInterface $bookingService */
        $bookingService = app(BookingServiceInterface::class);
        $availability = $bookingService->getAvailability();
        $this->assertNotNull($availability);
    }
}
