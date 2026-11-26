<?php

namespace Tests\Feature;

use App\Http\Resources\BookingResource;
use App\Jobs\CancelExpiredBookingsJob;
use App\Models\Booking;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\MissingValue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Audit 496 Parameter OPT: Respons API, Query Counter, Lazy Loading, and Batch Jobs Optimization.
 *
 * Menguji kepatuhan terhadap standar:
 * 1. Batch jobs tidak menggunakan ->get() atau ->all(), melainkan ->cursor() untuk efisiensi memori O(1).
 * 2. API Resources menggunakan whenLoaded/whenCounted tanpa memicu lazy loading N+1.
 * 3. Query counter per endpoint ≤ 10 queries.
 * 4. Matriks respons status code RESTful API yang konsisten (200, 404, 422, 401, 404).
 */
class ApiOptimizationAndQueryCounterAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_batch_jobs_do_not_use_get_or_all_and_stream_records_with_cursor(): void
    {
        $jobFile = app_path('Jobs/CancelExpiredBookingsJob.php');
        $this->assertFileExists($jobFile);

        $code = file_get_contents($jobFile);
        $this->assertIsString($code);

        // Audit requirement: 0 get()/all() di batch jobs
        $this->assertDoesNotMatchRegularExpression('/->(get|all)\s*\(/i', $code, 'Batch job tidak boleh memanggil ->get() atau ->all()');
        $this->assertStringContainsString('->cursor()', $code, 'Batch job harus menggunakan ->cursor() untuk streaming data O(1)');

        // Verifikasi fungsionalitas job
        $user = User::factory()->create();
        $room = Room::factory()->create();

        $expired = Booking::factory()->create([
            'user_id' => $user->id,
            'room_id' => $room->id,
            'status' => 'pending',
            'start_time' => now()->subHours(2),
            'end_time' => now()->subHour(),
        ]);

        $job = new CancelExpiredBookingsJob;
        $count = $job->handle();

        $this->assertSame(1, $count);
        $this->assertSame('cancelled', $expired->fresh()->status);

        // Eksekusi kedua idempoten (0 efek baru)
        $secondCount = $job->handle();
        $this->assertSame(0, $secondCount);
    }

    public function test_booking_resource_uses_when_loaded_without_triggering_lazy_loading(): void
    {
        $user = User::factory()->create();
        $room = Room::factory()->create();

        $booking = Booking::factory()->create([
            'user_id' => $user->id,
            'room_id' => $room->id,
            'status' => 'pending',
            'start_time' => now()->addDay(),
            'end_time' => now()->addDay()->addHour(),
        ]);

        // 1. Ambil model tanpa eager loading relasi 'room'
        $rawBooking = Booking::find($booking->id);
        $this->assertNotNull($rawBooking);
        $rawBooking->unsetRelations();
        $this->assertFalse($rawBooking->relationLoaded('room'));

        // Pastikan tidak ada query lazy loading saat Resource di-serialize
        DB::enableQueryLog();
        $resource = new BookingResource($rawBooking);
        $serialized = $resource->toArray(Request::create('/api/v1/bookings', 'GET'));
        $queriesWhileSerializing = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertEmpty($queriesWhileSerializing, 'Resource tidak boleh memicu query lazy loading database saat relasi tidak eager-loaded');
        $this->assertInstanceOf(MissingValue::class, $serialized['room'], 'Relasi yang belum dimuat harus menghasilkan MissingValue');

        // 2. Sekarang dengan eager loading relasi 'room'
        $loadedBooking = Booking::with('room')->find($booking->id);
        $this->assertNotNull($loadedBooking);
        $this->assertTrue($loadedBooking->relationLoaded('room'));

        $loadedResource = new BookingResource($loadedBooking);
        $loadedSerialized = $loadedResource->toArray(Request::create('/api/v1/bookings', 'GET'));

        $this->assertNotInstanceOf(MissingValue::class, $loadedSerialized['room']);
    }

    public function test_query_counter_per_endpoint_is_less_than_or_equal_to_10_queries(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $rooms = Room::factory()->count(5)->create();

        foreach ($rooms as $room) {
            Booking::factory()->count(3)->create([
                'user_id' => $admin->id,
                'room_id' => $room->id,
                'status' => 'approved',
                'start_time' => now()->addHours(rand(1, 10)),
                'end_time' => now()->addHours(rand(11, 20)),
            ]);
        }

        // Endpoint 1: GET /api/v1/bookings
        DB::flushQueryLog();
        DB::enableQueryLog();
        $responseBookings = $this->actingAs($admin)->getJson('/api/v1/bookings?page=1&per_page=25');
        $queriesBookings = count(DB::getQueryLog());
        DB::disableQueryLog();

        $responseBookings->assertOk();
        $this->assertLessThanOrEqual(10, $queriesBookings, "Query count untuk GET /api/v1/bookings ({$queriesBookings}) melebihi ambang batas ≤ 10");

        // Endpoint 2: GET /api/v1/bookings/availability
        Cache::flush();
        DB::flushQueryLog();
        DB::enableQueryLog();
        $responseAvailability = $this->actingAs($admin)->getJson('/api/v1/bookings/availability?page=1&per_page=25');
        $queriesAvailability = count(DB::getQueryLog());
        DB::disableQueryLog();

        $responseAvailability->assertOk();
        $this->assertLessThanOrEqual(10, $queriesAvailability, "Query count untuk GET /api/v1/bookings/availability ({$queriesAvailability}) melebihi ambang batas ≤ 10");

        // Endpoint 3: GET /api/v1/rooms
        DB::flushQueryLog();
        DB::enableQueryLog();
        $responseRooms = $this->actingAs($admin)->getJson('/api/v1/rooms?page=1&per_page=25');
        $queriesRooms = count(DB::getQueryLog());
        DB::disableQueryLog();

        $responseRooms->assertOk();
        $this->assertLessThanOrEqual(10, $queriesRooms, "Query count untuk GET /api/v1/rooms ({$queriesRooms}) melebihi ambang batas ≤ 10");

        // Endpoint 4: GET /api/v1/users
        DB::flushQueryLog();
        DB::enableQueryLog();
        $responseUsers = $this->actingAs($admin)->getJson('/api/v1/users?page=1&per_page=25');
        $queriesUsers = count(DB::getQueryLog());
        DB::disableQueryLog();

        $responseUsers->assertOk();
        $this->assertLessThanOrEqual(10, $queriesUsers, "Query count untuk GET /api/v1/users ({$queriesUsers}) melebihi ambang batas ≤ 10");

        // Endpoint 5: GET /api/v1/queue/failed-jobs
        DB::flushQueryLog();
        DB::enableQueryLog();
        $responseFailedJobs = $this->actingAs($admin)->getJson('/api/v1/queue/failed-jobs?page=1&per_page=25');
        $queriesFailedJobs = count(DB::getQueryLog());
        DB::disableQueryLog();

        $responseFailedJobs->assertOk();
        $this->assertLessThanOrEqual(10, $queriesFailedJobs, "Query count untuk GET /api/v1/queue/failed-jobs ({$queriesFailedJobs}) melebihi ambang batas ≤ 10");
    }

    public function test_status_code_matrix_for_crud_resources(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Room::factory()->create();

        // 1. Matriks REST pada Resource Bookings:
        // GET_unauth: 401
        $this->getJson('/api/v1/bookings')->assertStatus(401);

        // GET_list: 200
        $this->actingAs($admin)->getJson('/api/v1/bookings')->assertStatus(200);

        // GET_missing: 404
        $this->actingAs($admin)->getJson('/api/v1/bookings/999999')->assertStatus(404);

        // POST_invalid: 422
        $this->actingAs($admin)->postJson('/api/v1/bookings', [])->assertStatus(422);

        // DELETE_missing: 404
        $this->actingAs($admin)->deleteJson('/api/v1/bookings/999999')->assertStatus(404);

        // 2. Matriks REST pada Resource Rooms:
        // Reset auth context untuk pengujian unauthenticated
        $this->app['auth']->forgetGuards();

        // GET_unauth: 401
        $this->getJson('/api/v1/rooms')->assertStatus(401);

        // GET_list: 200
        $this->actingAs($admin)->getJson('/api/v1/rooms')->assertStatus(200);

        // GET_missing: 404
        $this->actingAs($admin)->getJson('/api/v1/rooms/999999')->assertStatus(404);

        // POST_invalid: 422
        $this->actingAs($admin)->postJson('/api/v1/rooms', [])->assertStatus(422);

        // DELETE_missing: 404
        $this->actingAs($admin)->deleteJson('/api/v1/rooms/999999')->assertStatus(404);
    }
}
