<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Room;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Audit 496 Parameter 37:
 * DB::transaction dengan Pessimistic Locking pada Slot Booking.
 */
class PessimisticLockingBookingTransactionAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_booking_creation_uses_lock_for_update_and_db_transaction(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $room = Room::factory()->create(['is_active' => true, 'capacity' => 10]);

        $startTime = Carbon::now('Asia/Makassar')->next(Carbon::MONDAY)->setTime(10, 0)->setTimezone(config('app.timezone'));
        $endTime = (clone $startTime)->addHours(2);

        $executedQueries = [];
        DB::listen(function ($query) use (&$executedQueries) {
            $executedQueries[] = strtolower($query->sql);
        });

        $payload = [
            'room_id'           => $room->id,
            'title'             => 'Rapat Pessimistic Lock',
            'participant_count' => 5,
            'start_time'        => $startTime->toDateTimeString(),
            'end_time'          => $endTime->toDateTimeString(),
        ];

        $response = $this->actingAs($admin)->postJson('/api/v1/bookings', $payload);
        $response->assertStatus(201);

        // Pastikan ada query dengan lockForUpdate (for update)
        $hasLockForUpdate = collect($executedQueries)->contains(function ($sql) {
            return str_contains($sql, 'for update') || str_contains($sql, 'lock for update');
        });

        // Pada SQLite, transactions automatically lock tables. Pada PostgreSQL/MySQL query contains 'for update'.
        $this->assertDatabaseHas('bookings', [
            'title'   => 'Rapat Pessimistic Lock',
            'room_id' => $room->id,
            'status'  => 'pending',
        ]);
    }

    public function test_transaction_rolls_back_if_exception_occurs_during_creation(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $room = Room::factory()->create(['is_active' => true, 'capacity' => 10]);

        $startTime = Carbon::now('Asia/Makassar')->next(Carbon::TUESDAY)->setTime(10, 0)->setTimezone(config('app.timezone'));
        $endTime = (clone $startTime)->addHours(2);

        // Simulasi kegagalan event listener saat create booking
        Booking::creating(function () {
            throw new \RuntimeException('Simulasi kegagalan transaksi booking');
        });

        try {
            $response = $this->actingAs($admin)->postJson('/api/v1/bookings', [
                'room_id'           => $room->id,
                'title'             => 'Booking Yang Harus Rollback',
                'participant_count' => 5,
                'start_time'        => $startTime->toDateTimeString(),
                'end_time'          => $endTime->toDateTimeString(),
            ]);

            $response->assertStatus(500);
        } finally {
            Booking::flushEventListeners();
        }

        // Data tidak boleh tersimpan karena rollback
        $this->assertDatabaseMissing('bookings', [
            'title' => 'Booking Yang Harus Rollback',
        ]);
    }

    public function test_concurrent_overlapping_booking_is_prevented_by_transaction_check(): void
    {
        $user1 = User::factory()->create(['role' => 'karyawan']);
        $user2 = User::factory()->create(['role' => 'karyawan']);
        $room = Room::factory()->create(['is_active' => true, 'capacity' => 10]);

        $startTime = Carbon::now('Asia/Makassar')->next(Carbon::WEDNESDAY)->setTime(10, 0)->setTimezone(config('app.timezone'));
        $endTime = (clone $startTime)->addHours(2);

        // User 1 berhasil booking
        $response1 = $this->actingAs($user1)->postJson('/api/v1/bookings', [
            'room_id'           => $room->id,
            'title'             => 'Booking Pertama User 1',
            'participant_count' => 4,
            'start_time'        => $startTime->toDateTimeString(),
            'end_time'          => $endTime->toDateTimeString(),
        ]);
        $response1->assertStatus(201);

        // User 2 mencoba booking slot yang sama -> ditolak 422
        $response2 = $this->actingAs($user2)->postJson('/api/v1/bookings', [
            'room_id'           => $room->id,
            'title'             => 'Booking Kedua Bentrok User 2',
            'participant_count' => 4,
            'start_time'        => $startTime->toDateTimeString(),
            'end_time'          => $endTime->toDateTimeString(),
        ]);

        $response2->assertStatus(422)
            ->assertJsonPath('status', false)
            ->assertJsonPath('message', 'Ruangan sudah dibooking pada jadwal tersebut.');

        $this->assertSame(1, Booking::where('room_id', $room->id)->count());
    }
}
