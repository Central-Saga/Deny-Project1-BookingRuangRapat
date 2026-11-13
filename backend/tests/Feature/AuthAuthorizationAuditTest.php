<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthAuthorizationAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_token_expiry_is_configured_and_not_infinite(): void
    {
        $expiration = config('sanctum.expiration');

        // Parameter 10.2: Expiration harus ada dan bukan null / abadi
        $this->assertNotNull($expiration, 'sanctum.expiration must not be null');
        $this->assertIsInt($expiration);
        $this->assertGreaterThan(0, $expiration);

        // Test token expiry behavior
        $user = User::factory()->create(['status' => 'active', 'password' => Hash::make('Secret123!')]);
        $token = $user->createToken('test-token')->plainTextToken;

        // Fresh token can access /api/me
        $this->withToken($token)->getJson('/api/me')->assertOk();

        // Advance time past expiration
        Carbon::setTestNow(now()->addMinutes($expiration + 5));
        auth()->forgetGuards();

        // Expired token should be rejected
        $this->withToken($token)->getJson('/api/me')->assertUnauthorized();

        Carbon::setTestNow(); // Reset time
    }

    public function test_logout_revokes_token_and_subsequent_request_fails(): void
    {
        $user = User::factory()->create([
            'status' => 'active',
            'password' => Hash::make('Secret123!'),
        ]);

        // Login via /api/login
        $loginResponse = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'Secret123!',
        ])->assertOk();

        $token = $loginResponse->json('token');
        $this->assertNotEmpty($token);

        // Active token works
        $this->withToken($token)->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('email', $user->email);

        // Logout revokes token
        $this->withToken($token)->postJson('/api/logout')
            ->assertOk()
            ->assertJsonPath('message', 'Logout berhasil');

        $this->assertDatabaseCount('personal_access_tokens', 0);

        // Subsequent request using revoked token MUST fail (token mati)
        $this->withToken($token)->getJson('/api/me')
            ->assertUnauthorized();

        // Also test logout on /api/v1/logout
        $tokenV1 = $user->createToken('v1-token')->plainTextToken;
        $this->withToken($tokenV1)->getJson('/api/v1/me')->assertOk();
        $this->withToken($tokenV1)->postJson('/api/v1/logout')->assertOk();
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->withToken($tokenV1)->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_change_password_revokes_all_existing_tokens(): void
    {
        $user = User::factory()->create([
            'status' => 'active',
            'password' => Hash::make('OldPassword123!'),
        ]);

        // Create 2 tokens representing multiple active sessions
        $tokenSession1 = $user->createToken('device-1')->plainTextToken;
        $tokenSession2 = $user->createToken('device-2')->plainTextToken;

        $this->assertDatabaseCount('personal_access_tokens', 2);

        // Session 1 changes password
        $response = $this->withToken($tokenSession1)->postJson('/api/v1/change-password', [
            'current_password' => 'OldPassword123!',
            'password' => 'NewSecurePassword123!',
            'password_confirmation' => 'NewSecurePassword123!',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true);

        // All existing tokens MUST be deleted (token lama mati)
        $this->assertDatabaseCount('personal_access_tokens', 0);

        // Request with old tokenSession1 MUST fail
        $this->withToken($tokenSession1)->getJson('/api/me')->assertUnauthorized();

        // Request with old tokenSession2 MUST also fail
        $this->withToken($tokenSession2)->getJson('/api/me')->assertUnauthorized();

        // Flush headers before public login requests
        $this->flushHeaders();

        // Login with old password fails
        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'OldPassword123!',
        ])->assertUnauthorized();

        // Login with new password succeeds
        $newLogin = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'NewSecurePassword123!',
        ])->assertOk();

        $newToken = $newLogin->json('token');
        $this->withToken($newToken)->getJson('/api/me')->assertOk();
    }

    public function test_change_password_validates_current_password_and_length(): void
    {
        $user = User::factory()->create([
            'status' => 'active',
            'password' => Hash::make('ValidOldPass123!'),
        ]);
        $token = $user->createToken('change-test')->plainTextToken;

        // Wrong current password
        $this->withToken($token)->postJson('/api/v1/change-password', [
            'current_password' => 'WrongOldPass123!',
            'password' => 'NewPassword123!',
        ])->assertStatus(422)
          ->assertJsonPath('success', false);

        // Password too short (< 8 chars)
        $this->withToken($token)->postJson('/api/v1/change-password', [
            'current_password' => 'ValidOldPass123!',
            'password' => 'short',
        ])->assertStatus(422)
          ->assertJsonPath('success', false);

        // Unauthenticated request (flush previous token header)
        $this->flushHeaders();
        auth()->forgetGuards();

        $this->postJson('/api/v1/change-password', [
            'current_password' => 'ValidOldPass123!',
            'password' => 'NewPassword123!',
        ])->assertUnauthorized();
    }
}
