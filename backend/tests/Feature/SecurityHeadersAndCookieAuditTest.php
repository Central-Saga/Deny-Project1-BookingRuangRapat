<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SecurityHeadersAndCookieAuditTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Audit 496 Parameter SEC (Issue #160):
     * Token di httpOnly cookie & Cookie flags.
     * Login harus menyetel cookie token dengan flag httpOnly dan SameSite=Lax.
     */
    public function test_login_sets_httponly_and_samesite_cookies(): void
    {
        $user = User::factory()->create([
            'email' => 'karyawan.sec@example.com',
            'password' => Hash::make('Secret123!'),
            'status' => 'active',
            'role' => 'karyawan',
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'karyawan.sec@example.com',
            'password' => 'Secret123!',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true);

        // Verifikasi cookie meetspace_auth_token, auth_token, dan token
        $response->assertPlainCookie('meetspace_auth_token');
        $response->assertPlainCookie('auth_token');
        $response->assertPlainCookie('token');

        $cookie = $response->getCookie('meetspace_auth_token', false);
        $this->assertNotNull($cookie, 'Cookie meetspace_auth_token harus disetel');
        $this->assertTrue($cookie->isHttpOnly(), 'Cookie meetspace_auth_token harus memiliki flag httpOnly');
        $this->assertSame('lax', strtolower((string) $cookie->getSameSite()), 'Cookie SameSite harus Lax');

        // Uji juga rute v1/login
        $responseV1 = $this->postJson('/api/v1/login', [
            'email' => 'karyawan.sec@example.com',
            'password' => 'Secret123!',
        ]);

        $responseV1->assertOk()
            ->assertPlainCookie('meetspace_auth_token')
            ->assertPlainCookie('auth_token')
            ->assertPlainCookie('token');
    }

    /**
     * Audit 496 Parameter SEC (Issue #160):
     * HSTS + cookie flags: Header HSTS dan XCTO (X-Content-Type-Options: nosniff) harus ada.
     */
    public function test_security_headers_hsts_and_xcto_are_present_on_all_responses(): void
    {
        // 1. Respon web root (/)
        $webResponse = $this->get('/');
        $webResponse->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        $webResponse->assertHeader('X-Content-Type-Options', 'nosniff');
        $webResponse->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $webResponse->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');

        // 2. Respon API publik / unauthenticated error
        $apiResponse = $this->getJson('/api/v1/rooms');
        $apiResponse->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        $apiResponse->assertHeader('X-Content-Type-Options', 'nosniff');

        // 3. Respon API login
        $loginResponse = $this->postJson('/api/login', [
            'email' => 'invalid@example.com',
            'password' => 'wrong',
        ]);
        $loginResponse->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        $loginResponse->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    /**
     * Audit 496 Parameter SEC (Issue #160):
     * CookieBearerTokenMiddleware memungkinkan request otentikasi menggunakan httpOnly cookie.
     */
    public function test_cookie_bearer_token_authenticates_protected_endpoints(): void
    {
        $user = User::factory()->create([
            'email' => 'cookie.user@example.com',
            'password' => Hash::make('Secret123!'),
            'status' => 'active',
            'role' => 'karyawan',
        ]);

        $token = $user->createToken('cookie-auth-token')->plainTextToken;

        // 1. Uji dengan withCredentials + withUnencryptedCookies
        $response = $this->withCredentials()
            ->withUnencryptedCookies(['meetspace_auth_token' => $token])
            ->getJson('/api/v1/me');

        $response->assertOk()
            ->assertJsonPath('email', 'cookie.user@example.com');

        // 2. Uji dengan raw Cookie header (seperti dikirim curl / HTTP client)
        $responseHeader = $this->withHeaders(['Cookie' => "meetspace_auth_token={$token}"])
            ->getJson('/api/v1/me');

        $responseHeader->assertOk()
            ->assertJsonPath('email', 'cookie.user@example.com');

        // 3. Uji dengan cookie auth_token alternatif
        $responseAlt = $this->withCredentials()
            ->withUnencryptedCookies(['auth_token' => $token])
            ->getJson('/api/v1/me');

        $responseAlt->assertOk()
            ->assertJsonPath('email', 'cookie.user@example.com');
    }

    /**
     * Audit 496 Parameter SEC (Issue #160):
     * Logout menghapus / mematikan cookie otentikasi.
     */
    public function test_logout_clears_cookies(): void
    {
        $user = User::factory()->create([
            'status' => 'active',
            'password' => Hash::make('Secret123!'),
        ]);

        $token = $user->createToken('logout-token')->plainTextToken;

        $response = $this->withToken($token)
            ->postJson('/api/v1/logout');

        $response->assertOk();
        $response->assertCookieExpired('meetspace_auth_token');
        $response->assertCookieExpired('auth_token');
        $response->assertCookieExpired('token');
    }
}
