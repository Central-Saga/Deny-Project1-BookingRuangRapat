<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

/**
 * Audit 496 Parameter 26: Token Invalidation saat Logout Pengguna.
 * Pastikan token otentikasi Sanctum dihapus permanen saat endpoint logout dipanggil
 * sehingga token mati untuk request berikutnya.
 */
class TokenInvalidationLogoutAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_logout_permanently_deletes_token_from_database(): void
    {
        $user = User::factory()->create([
            'status' => 'active',
            'password' => Hash::make('Secret123!'),
        ]);

        $loginResponse = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'Secret123!',
        ])->assertOk();

        $plainTextToken = $loginResponse->json('token');
        $this->assertNotEmpty($plainTextToken);

        // Extract token ID from token string (format: ID|token)
        [$tokenId] = explode('|', $plainTextToken, 2);

        // Verify token exists in personal_access_tokens
        $this->assertDatabaseHas('personal_access_tokens', [
            'id' => $tokenId,
            'tokenable_id' => $user->id,
        ]);

        // Access protected route successfully
        $this->withToken($plainTextToken)->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('email', $user->email);

        // Call logout
        $logoutResponse = $this->withToken($plainTextToken)->postJson('/api/v1/logout');
        $logoutResponse->assertOk()
            ->assertJson([
                'status' => true,
                'message' => 'Logout berhasil',
            ]);

        // Token must be permanently deleted from database
        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $tokenId,
        ]);
        $this->assertDatabaseCount('personal_access_tokens', 0);

        // Subsequent requests with the revoked token MUST be rejected with 401
        $this->withToken($plainTextToken)->getJson('/api/v1/me')
            ->assertUnauthorized()
            ->assertJson([
                'status' => false,
                'message' => 'Autentikasi diperlukan.',
            ]);

        $this->withToken($plainTextToken)->getJson('/api/v1/bookings')
            ->assertUnauthorized();
    }

    public function test_logout_only_invalidates_current_session_token_leaving_other_devices_active(): void
    {
        $user = User::factory()->create(['status' => 'active']);

        $tokenDevice1 = $user->createToken('laptop')->plainTextToken;
        $tokenDevice2 = $user->createToken('smartphone')->plainTextToken;

        $this->assertDatabaseCount('personal_access_tokens', 2);

        [$token1Id] = explode('|', $tokenDevice1, 2);
        [$token2Id] = explode('|', $tokenDevice2, 2);

        // Both devices can access API
        $this->withToken($tokenDevice1)->getJson('/api/v1/me')->assertOk();
        $this->withToken($tokenDevice2)->getJson('/api/v1/me')->assertOk();

        // Logout from Device 1
        $this->withToken($tokenDevice1)->postJson('/api/v1/logout')
            ->assertOk()
            ->assertJsonPath('status', true);

        // Device 1 token is permanently deleted
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $token1Id]);
        // Device 2 token is still preserved in database
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $token2Id]);

        // Device 1 cannot access API anymore
        $this->withToken($tokenDevice1)->getJson('/api/v1/me')->assertUnauthorized();

        // Device 2 can still access API normally
        $this->withToken($tokenDevice2)->getJson('/api/v1/me')->assertOk();
    }

    public function test_all_logout_endpoint_variants_invalidate_token(): void
    {
        $endpoints = ['/api/logout', '/api/v1/logout', '/api/auth/logout', '/api/v1/auth/logout'];

        foreach ($endpoints as $endpoint) {
            $user = User::factory()->create(['status' => 'active']);
            $token = $user->createToken('session')->plainTextToken;
            [$id] = explode('|', $token, 2);

            $this->assertDatabaseHas('personal_access_tokens', ['id' => $id]);

            $this->withToken($token)->postJson($endpoint)
                ->assertOk()
                ->assertJsonPath('status', true);

            $this->assertDatabaseMissing('personal_access_tokens', ['id' => $id]);
            $this->withToken($token)->getJson('/api/v1/me')->assertUnauthorized();
        }
    }

    public function test_unauthenticated_logout_request_returns_401(): void
    {
        $this->postJson('/api/v1/logout')
            ->assertUnauthorized()
            ->assertJson([
                'status' => false,
                'message' => 'Autentikasi diperlukan.',
            ]);
    }
}
