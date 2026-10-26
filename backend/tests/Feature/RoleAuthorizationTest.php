<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RoleAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_users_role_column_is_used_for_authorization(): void
    {
        $this->assertFalse(class_exists(\Spatie\Permission\PermissionServiceProvider::class));
        $this->assertFalse(Schema::hasTable('roles'));
        $this->assertFalse(Schema::hasTable('permissions'));
        $this->assertTrue(Schema::hasColumn('users', 'role'));
    }

    public function test_karyawan_cannot_access_admin_endpoints(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'karyawan', 'status' => 'active']));

        $this->getJson('/api/v1/users')->assertForbidden();
        $this->postJson('/api/v1/users', [])->assertForbidden();
        $this->postJson('/api/v1/rooms', [])->assertForbidden();
    }

    public function test_admin_can_access_admin_endpoints(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'status' => 'active']));

        $this->getJson('/api/v1/users')->assertOk();
        $this->postJson('/api/v1/rooms', [])->assertStatus(422);
    }
}
