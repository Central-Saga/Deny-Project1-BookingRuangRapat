<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Audit 496 Standar P1 ROB (Issue #174):
 * [Audit 496][P1][ROB] Skema database belum dijaga FK, index, dan unique yang lengkap.
 *
 * Verifikasi:
 * 1. Seluruh foreignId pada migrasi memiliki constrained() dan onDelete eksplisit (0 tanpa constrained).
 * 2. Seluruh foreign key column (bookings: user_id, room_id, approved_by) memiliki index.
 * 3. Unique index kompatibel dengan SoftDeletes (deleted_at): insert record baru setelah soft delete berhasil (0 tabrakan).
 */
class DatabaseSchemaFkIndexUniqueAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_foreign_keys_in_migrations_have_constrained_and_explicit_on_delete(): void
    {
        $migrationFiles = glob(database_path('migrations/*.php'));
        $this->assertNotEmpty($migrationFiles);

        $unconstrainedFks = [];
        foreach ($migrationFiles as $file) {
            $content = file_get_contents($file);
            // Cari pola foreignId(...) yang tidak diikuti constrained(...)
            preg_match_all('/foreignId\([\'"][a-zA-Z0-9_]+[\'"]\)(?:(?!constrained)[\s\S])*?;/', $content, $matches);
            if (! empty($matches[0])) {
                foreach ($matches[0] as $match) {
                    if (! str_contains($match, 'constrained')) {
                        $unconstrainedFks[] = basename($file).': '.trim($match);
                    }
                }
            }
        }

        $this->assertEmpty(
            $unconstrainedFks,
            'Semua foreignId pada migrasi harus memiliki constrained(): '.implode(', ', $unconstrainedFks)
        );

        // Verifikasi khusus pada 0001_01_01_000000_create_users_table.php untuk tabel sessions
        $usersMigration = file_get_contents(database_path('migrations/0001_01_01_000000_create_users_table.php'));
        $this->assertMatchesRegularExpression(
            '/foreignId\([\'"]user_id[\'"]\).*?->constrained\([\'"]users[\'"]\)/',
            $usersMigration,
            'sessions.user_id harus constrained ke users.'
        );
        $this->assertTrue(
            str_contains($usersMigration, 'cascadeOnDelete()') || str_contains($usersMigration, "onDelete('cascade')"),
            'sessions.user_id harus mendefinisikan onDelete eksplisit.'
        );
    }

    public function test_all_foreign_key_columns_have_indexes_in_database(): void
    {
        $indexes = Schema::getIndexes('bookings');
        $this->assertNotEmpty($indexes);

        $indexedColumns = [];
        foreach ($indexes as $idx) {
            foreach ($idx['columns'] as $col) {
                $indexedColumns[$col] = true;
            }
        }

        // 3 FK pada tabel bookings: room_id, user_id, approved_by
        $foreignKeyColumns = ['room_id', 'user_id', 'approved_by'];
        $missingIndexes = [];

        foreach ($foreignKeyColumns as $fkCol) {
            if (! isset($indexedColumns[$fkCol])) {
                $missingIndexes[] = "bookings|{$fkCol}";
            }
        }

        $this->assertEmpty(
            $missingIndexes,
            'Semua kolom foreign key harus memiliki index di database. Belum terindeks: '.implode(', ', $missingIndexes)
        );
    }

    public function test_rooms_unique_and_soft_deletes_compatibility_allows_insert_after_soft_delete(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        // 1. Verifikasi tabel rooms memiliki unique index dengan kolom deleted_at
        $indexes = Schema::getIndexes('rooms');
        $hasDeletedAtUnique = false;
        foreach ($indexes as $idx) {
            if (! empty($idx['unique']) && in_array('deleted_at', $idx['columns'], true)) {
                $hasDeletedAtUnique = true;
                break;
            }
        }
        $this->assertTrue($hasDeletedAtUnique, 'Tabel rooms harus memiliki unique index yang menyertakan deleted_at.');

        // 2. Buat ruangan pertama dengan nama unik
        $room1 = Room::factory()->create([
            'name' => 'Ruang Yudhistira',
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('rooms', ['id' => $room1->id, 'name' => 'Ruang Yudhistira', 'deleted_at' => null]);

        // 3. Jika ruangan masih aktif, pengajuan nama yang sama ditolak (422)
        $duplicateAttempt = $this->actingAs($admin)->postJson('/api/v1/rooms', [
            'name' => 'Ruang Yudhistira',
            'capacity' => 20,
            'location' => 'Lantai 3',
        ]);
        $duplicateAttempt->assertStatus(422);

        // 4. Soft delete ruangan pertama
        $room1->delete();
        $this->assertSoftDeleted('rooms', ['id' => $room1->id]);

        // 5. Masukkan ruangan baru dengan NAMA YANG SAMA PERSIS setelah soft delete
        // Ini harus berhasil tanpa tabrakan unique constraint (0 tabrakan)
        $room2 = Room::create([
            'name' => 'Ruang Yudhistira',
            'capacity' => 15,
            'location' => 'Lantai 2',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('rooms', ['id' => $room2->id, 'name' => 'Ruang Yudhistira', 'deleted_at' => null]);
        $this->assertNotEquals($room1->id, $room2->id);

        // 6. Pengajuan via API setelah soft delete juga berhasil 201 dengan 0 tabrakan
        $this->travel(10)->seconds();
        $room2->delete();
        $responseApi = $this->actingAs($admin)->postJson('/api/v1/rooms', [
            'name' => 'Ruang Yudhistira',
            'capacity' => 25,
            'location' => 'Lantai 4',
        ]);
        $responseApi->assertStatus(201);
    }

    public function test_bookings_unique_and_soft_deletes_compatibility_allows_insert_after_soft_delete(): void
    {
        $user = User::factory()->create();
        $room = Room::factory()->create();

        $startTime = now()->addDays(2)->setTime(10, 0, 0)->format('Y-m-d H:i:s');
        $endTime = now()->addDays(2)->setTime(12, 0, 0)->format('Y-m-d H:i:s');

        // 1. Buat booking pertama
        $booking1 = Booking::create([
            'user_id' => $user->id,
            'room_id' => $room->id,
            'title' => 'Rapat Perencanaan',
            'participant_count' => 5,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'status' => 'pending',
        ]);

        // 2. Soft delete booking pertama
        $booking1->delete();
        $this->assertSoftDeleted('bookings', ['id' => $booking1->id]);

        // 3. Buat booking baru pada slot yang sama setelah soft delete
        // Harus berhasil tanpa tabrakan unique index (0 tabrakan)
        $booking2 = Booking::create([
            'user_id' => $user->id,
            'room_id' => $room->id,
            'title' => 'Rapat Perencanaan Ulang',
            'participant_count' => 6,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'status' => 'pending',
        ]);

        $this->assertDatabaseHas('bookings', ['id' => $booking2->id, 'start_time' => $startTime]);
        $this->assertNotEquals($booking1->id, $booking2->id);
    }
}
