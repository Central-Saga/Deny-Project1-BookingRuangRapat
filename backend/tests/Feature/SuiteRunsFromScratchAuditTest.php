<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Audit 496 Parameter TST (Issue #162):
 * Suite jalan dari nol — Memastikan seluruh automated test suite dapat dieksekusi dari nol (fresh environment)
 * tanpa ketergantungan pada database eksternal (menggunakan in-memory SQLite), cache, atau antrean eksternal,
 * dan menghasilkan 0 error (rc=0).
 */
class SuiteRunsFromScratchAuditTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Memastikan konfigurasi testing berjalan zero-config dengan in-memory SQLite dan driver array/sync.
     */
    public function test_suite_runs_from_scratch_with_zero_configuration_in_memory_sqlite(): void
    {
        // 1. Verifikasi koneksi database in-memory sqlite
        $this->assertSame('sqlite', config('database.default'), 'Koneksi database testing default harus sqlite.');
        $this->assertSame(':memory:', config('database.connections.sqlite.database'), 'Database sqlite harus :memory: agar terisolasi.');

        // 2. Verifikasi driver cache, antrean, session, dan mail
        $this->assertSame('array', config('cache.default'), 'Cache store testing harus array.');
        $this->assertSame('sync', config('queue.default'), 'Queue driver testing harus sync.');
        $this->assertSame('array', config('session.driver'), 'Session driver testing harus array.');
        $this->assertSame('array', config('mail.default'), 'Mail driver testing harus array.');

        // 3. Verifikasi seluruh tabel inti dibuat secara otomatis dari migrasi
        $this->assertTrue(Schema::hasTable('users'), 'Tabel users harus terbentuk saat migrasi dijalankan.');
        $this->assertTrue(Schema::hasTable('rooms'), 'Tabel rooms harus terbentuk saat migrasi dijalankan.');
        $this->assertTrue(Schema::hasTable('bookings'), 'Tabel bookings harus terbentuk saat migrasi dijalankan.');
        $this->assertTrue(Schema::hasTable('personal_access_tokens'), 'Tabel personal_access_tokens harus terbentuk.');
    }

    /**
     * Memastikan DatabaseSeeder dapat dijalankan dari nol tanpa error dan menghasilkan akun uji valid.
     */
    public function test_suite_can_seed_test_database_from_scratch_without_external_dependencies(): void
    {
        $exitCode = Artisan::call('db:seed');
        $this->assertSame(0, $exitCode, 'Artisan db:seed harus menghasilkan exit code 0.');

        // Memastikan akun bawaan admin dan karyawan terbentuk
        $this->assertDatabaseHas('users', [
            'email' => 'admin@example.com',
            'role' => 'admin',
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'karyawan@example.com',
            'role' => 'karyawan',
        ]);

        // Memastikan ruang rapat bawaan terbentuk
        $this->assertGreaterThan(0, Room::count(), 'Seeder harus mengisi data ruangan.');
    }

    /**
     * Memastikan model inti (User, Room, Booking) dapat dibuat dan berinteraksi dari nol.
     */
    public function test_all_core_models_can_be_instantiated_and_persisted_from_scratch(): void
    {
        $user = User::factory()->create([
            'role' => 'karyawan',
            'status' => 'active',
        ]);
        $this->assertNotNull($user->id);

        $room = Room::factory()->create([
            'is_active' => true,
            'capacity' => 10,
        ]);
        $this->assertNotNull($room->id);

        $booking = Booking::factory()->create([
            'room_id' => $room->id,
            'user_id' => $user->id,
            'status' => 'pending',
        ]);
        $this->assertNotNull($booking->id);

        $this->assertSame($user->id, $booking->user->id);
        $this->assertSame($room->id, $booking->room->id);
    }
}
