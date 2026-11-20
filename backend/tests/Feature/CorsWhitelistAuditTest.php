<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Audit 496 Parameter 28: Whitelist CORS Origin pada config/cors.php.
 * Pastikan config/cors.php hanya mengizinkan origin frontend yang sah dan tidak menggunakan wildcard *.
 */
class CorsWhitelistAuditTest extends TestCase
{
    public function test_cors_config_does_not_contain_wildcard_asterisk(): void
    {
        $allowedOrigins = config('cors.allowed_origins');
        $allowedPatterns = config('cors.allowed_origins_patterns');

        $this->assertIsArray($allowedOrigins);
        $this->assertNotContains('*', $allowedOrigins, 'CORS allowed_origins must not contain wildcard asterisk *');

        if (!empty($allowedPatterns)) {
            $this->assertNotContains('.*', $allowedPatterns);
            $this->assertNotContains('*', $allowedPatterns);
        }
    }

    public function test_cors_config_has_whitelisted_frontend_origins(): void
    {
        $allowedOrigins = config('cors.allowed_origins');

        $this->assertContains('http://localhost:3000', $allowedOrigins);
        $this->assertContains('http://127.0.0.1:3000', $allowedOrigins);
    }

    public function test_preflight_options_allowed_for_whitelisted_origins(): void
    {
        $whitelistedOrigins = ['http://localhost:3000', 'http://127.0.0.1:3000'];

        foreach ($whitelistedOrigins as $origin) {
            $response = $this->withHeaders([
                'Origin' => $origin,
                'Access-Control-Request-Method' => 'GET',
                'Access-Control-Request-Headers' => 'Authorization, Content-Type',
            ])->options('/api/v1/rooms');

            $response->assertHeader('Access-Control-Allow-Origin', $origin);
        }
    }

    public function test_preflight_options_denied_for_untrusted_origins(): void
    {
        $untrustedOrigins = [
            'https://evil-attacker.com',
            'https://malicious-website.org',
            'http://localhost:8080',
            'https://untrusted.example',
        ];

        foreach ($untrustedOrigins as $origin) {
            $response = $this->withHeaders([
                'Origin' => $origin,
                'Access-Control-Request-Method' => 'POST',
            ])->options('/api/v1/bookings');

            $this->assertFalse(
                $response->headers->has('Access-Control-Allow-Origin'),
                "Origin [{$origin}] must NOT be allowed by CORS header"
            );
        }
    }
}
