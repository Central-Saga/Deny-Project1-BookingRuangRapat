<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Room;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\LazyLoadingViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class QualityStandardsTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_login_can_access_me_and_logout_revokes_token(): void
    {
        $password = 'QualityPassword123!';
        $user = User::factory()->create([
            'role' => 'karyawan',
            'status' => 'active',
            'password' => Hash::make($password),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => $password,
        ])->assertOk()->assertJsonPath('user.id', $user->id);

        $token = $response->json('token');
        $this->assertIsString($token);
        $this->assertNotEmpty($token);

        $this->withToken($token)->getJson('/api/me')
            ->assertOk()->assertJsonPath('id', $user->id);

        $this->withToken($token)->postJson('/api/logout')->assertOk();
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_invalid_password_is_rejected_without_creating_token(): void
    {
        $user = User::factory()->create([
            'status' => 'active',
            'password' => Hash::make('CorrectPassword123!'),
        ]);

        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'WrongPassword123!',
        ])->assertUnauthorized()->assertJsonPath('success', false);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_admin_can_create_read_update_and_soft_delete_room(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $payload = [
            'name' => 'Quality Room 45',
            'capacity' => 12,
            'location' => 'Lantai 2',
            'description' => 'Ruangan untuk test CRUD',
            'facilities' => ['projector', 'whiteboard'],
            'is_active' => true,
        ];

        $created = $this->actingAs($admin)
            ->postJson('/api/v1/rooms', $payload)
            ->assertCreated()->assertJsonPath('data.name', 'Quality Room 45');
        $id = $created->json('data.id');
        $this->assertNotNull($id);

        $this->actingAs($admin)->getJson('/api/v1/rooms')
            ->assertOk()->assertJsonFragment(['name' => 'Quality Room 45']);

        $updated = array_merge($payload, ['name' => 'Quality Room 45 Updated', 'capacity' => 15]);
        $this->actingAs($admin)->putJson("/api/v1/rooms/{$id}", $updated)
            ->assertOk()->assertJsonPath('data.name', 'Quality Room 45 Updated');

        $this->actingAs($admin)->deleteJson("/api/v1/rooms/{$id}")->assertNoContent();
        $room = Room::withTrashed()->findOrFail($id);
        $this->assertFalse($room->is_active);
        $this->assertNotNull($room->deleted_at);
    }

    public function test_disabling_user_revokes_tokens(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $employee = User::factory()->create(['role' => 'karyawan', 'status' => 'active']);
        $employee->createToken('quality-test')->plainTextToken;
        $this->assertDatabaseCount('personal_access_tokens', 1);

        $this->actingAs($admin)->putJson("/api/v1/users/{$employee->id}", [
            'role' => 'karyawan',
            'status' => 'inactive',
        ])->assertOk()->assertJsonPath('data.status', 'inactive');

        $this->assertDatabaseHas('users', ['id' => $employee->id, 'status' => 'inactive']);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_room_deactivation_rolls_back_if_soft_delete_fails(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $room = Room::factory()->create(['is_active' => true]);

        Room::deleting(function (Room $candidate) use ($room): void {
            if ($candidate->id === $room->id) {
                throw new \RuntimeException('Simulated room deletion failure');
            }
        });

        try {
            $this->actingAs($admin)
                ->deleteJson("/api/v1/rooms/{$room->id}")
                ->assertStatus(500);
        } finally {
            Room::flushEventListeners();
        }

        $saved = Room::withTrashed()->findOrFail($room->id);
        $this->assertTrue($saved->is_active);
        $this->assertNull($saved->deleted_at);
    }

    public function test_lazy_loading_guard_is_enabled_in_testing(): void
    {
        $this->assertTrue(Model::preventsLazyLoading());
        $user = User::factory()->create();
        $room = Room::factory()->create();
        Booking::factory()->count(2)->create([
            'user_id' => $user->id,
            'room_id' => $room->id,
        ]);

        $bookings = Booking::query()->get();
        $this->assertCount(2, $bookings);
        $this->expectException(LazyLoadingViolationException::class);
        $bookings->first()->room;
    }

    public function test_booking_listing_includes_eager_loaded_room_and_user(): void
    {
        $employee = User::factory()->create(['role' => 'karyawan']);
        $room = Room::factory()->create(['is_active' => true]);
        Booking::factory()->count(4)->create([
            'user_id' => $employee->id,
            'room_id' => $room->id,
            'status' => 'pending',
        ]);

        $this->actingAs($employee)->getJson('/api/v1/bookings?per_page=25')
            ->assertOk()
            ->assertJsonCount(4, 'data')
            ->assertJsonPath('data.0.room.id', $room->id)
            ->assertJsonPath('data.0.user.id', $employee->id);
    }

    public function test_booking_list_does_not_grow_queries_per_row(): void
    {
        $employee = User::factory()->create(['role' => 'karyawan']);
        $room = Room::factory()->create(['is_active' => true]);
        Booking::factory()->count(8)->create([
            'user_id' => $employee->id,
            'room_id' => $room->id,
            'status' => 'pending',
        ]);

        DB::flushQueryLog();
        DB::enableQueryLog();
        try {
            $this->actingAs($employee)->getJson('/api/v1/bookings?per_page=25')
                ->assertOk()->assertJsonCount(8, 'data');
        } finally {
            $queries = DB::getQueryLog();
            DB::disableQueryLog();
            DB::flushQueryLog();
        }

        $selectCount = count(array_filter($queries, static function (array $query): bool {
            return preg_match('/^\s*select\b/i', $query['query']) === 1;
        }));
        $this->assertLessThanOrEqual(6, $selectCount, 'Booking list should not run one query per record.');
    }

    public function test_availability_listing_includes_eager_loaded_room(): void
    {
        $employee = User::factory()->create(['role' => 'karyawan']);
        $room = Room::factory()->create(['is_active' => true]);
        Booking::factory()->count(3)->create([
            'user_id' => $employee->id,
            'room_id' => $room->id,
            'status' => 'pending',
        ]);

        $this->actingAs($employee)->getJson('/api/v1/bookings/availability?per_page=25')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.room.id', $room->id);
    }
}
