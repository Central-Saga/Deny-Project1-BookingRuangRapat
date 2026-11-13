<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthorizationFillableAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorization_and_sensitive_columns_are_not_fillable(): void
    {
        $sensitiveColumns = ['role', 'is_admin', 'balance', 'tenant_id'];

        $models = [
            User::class => new User(),
            Booking::class => new Booking(),
            Room::class => new Room(),
        ];

        $fillableCount = 0;

        foreach ($models as $class => $model) {
            $fillables = $model->getFillable();

            foreach ($sensitiveColumns as $column) {
                if (in_array($column, $fillables, true)) {
                    $fillableCount++;
                }
                $this->assertNotContains(
                    $column,
                    $fillables,
                    "Kolom {$column} tidak boleh berada di \$fillable pada model {$class}."
                );
            }
        }

        // Backend parameter 2.2: cek role/is_admin/balance/tenant_id -> harus 0 fillable
        $this->assertSame(0, $fillableCount);
    }

    public function test_user_role_cannot_be_mass_assigned(): void
    {
        // Mass assignment via create() must not assign 'role'
        $user = User::create([
            'name' => 'Untrusted User',
            'email' => 'untrusted@example.com',
            'password' => 'Secret123!',
            'role' => 'admin', // Malicious attempt to elevate privileges
        ]);

        // Role should fall back to database default ('karyawan'), NOT 'admin'
        $this->assertNotSame('admin', $user->fresh()->role);
        $this->assertSame('karyawan', $user->fresh()->role);

        // Mass assignment via update() must not change 'role'
        $user->update(['role' => 'admin']);
        $this->assertSame('karyawan', $user->fresh()->role);
    }

    public function test_admin_controller_can_still_create_and_update_user_roles(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);

        // Admin stores a new user with role 'admin'
        $storeResponse = $this->actingAs($admin)->postJson('/api/v1/users', [
            'name' => 'New Staff',
            'email' => 'newstaff@example.com',
            'password' => 'StaffPass123!',
            'role' => 'admin',
            'status' => 'active',
        ])->assertCreated();

        $userId = $storeResponse->json('data.id');
        $createdUser = User::findOrFail($userId);
        $this->assertSame('admin', $createdUser->role);

        // Admin updates user role to 'karyawan'
        $this->actingAs($admin)->putJson("/api/v1/users/{$userId}", [
            'role' => 'karyawan',
            'status' => 'active',
        ])->assertOk();

        $this->assertSame('karyawan', $createdUser->fresh()->role);
    }
}
