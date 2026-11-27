<?php

namespace Tests\Feature;

use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Audit 496 Parameter SEC (Issue #177):
 * Input divalidasi lengkap lewat FormRequest:
 * - 0 validasi inline di seluruh controller
 * - Password::min terpasang pada aturan password
 * - Aturan lengkap per field
 * - Respons error konsisten tanpa bocor stack/SQL (0 stack/SQL)
 */
class FormRequestValidationAuditTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Parameter SEC 1: 0 validasi inline pada seluruh Controller.
     */
    public function test_zero_inline_validations_across_controllers(): void
    {
        $controllersPath = app_path('Http/Controllers');
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($controllersPath));

        $inlineValidationOccurrences = [];

        foreach ($files as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $content = file_get_contents($file->getRealPath());

                if (preg_match('/\$request->validate\s*\(/', $content) ||
                    preg_match('/request\(\)->validate\s*\(/', $content) ||
                    preg_match('/Validator::make\s*\(/', $content)) {
                    $inlineValidationOccurrences[] = $file->getFilename();
                }
            }
        }

        $this->assertIsArray($inlineValidationOccurrences);
        $this->assertCount(0, $inlineValidationOccurrences);
        $this->assertEmpty(
            $inlineValidationOccurrences,
            'Controller tidak boleh memiliki validasi inline ($request->validate / Validator::make). Ditemukan di: '.implode(', ', $inlineValidationOccurrences)
        );
    }

    /**
     * Parameter SEC 2: grep Password::min -> ada Password::min terpasang.
     */
    public function test_password_rules_use_password_min_rule(): void
    {
        $requestsPath = app_path('Http/Requests');
        $files = glob($requestsPath.'/*.php');
        $this->assertNotEmpty($files, 'Http/Requests harus memiliki file FormRequest.');

        $passwordMinFound = false;
        foreach ($files as $file) {
            $content = file_get_contents($file);
            if (str_contains($content, 'Password::min')) {
                $passwordMinFound = true;
                break;
            }
        }

        $this->assertTrue(
            $passwordMinFound,
            'Aturan validasi password harus menggunakan Password::min() dari Laravel.'
        );
    }

    /**
     * Parameter SEC 3: Uji error response tidak membocorkan stack trace / SQL (0 stack/SQL).
     * Endpoint POST /api/v1/admin/queue/failed-jobs kosong -> 405 / uniform error.
     */
    public function test_failed_jobs_endpoint_returns_consistent_error_without_stack_or_sql_leak(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);

        $endpoints = [
            '/api/v1/admin/queue/failed-jobs',
            '/api/v1/queue/failed-jobs',
        ];

        foreach ($endpoints as $endpoint) {
            $response = $this->actingAs($admin)->postJson($endpoint, []);

            // Harus mengembalikan 405 Method Not Allowed atau 422
            $this->assertContains($response->status(), [404, 405, 422]);

            $json = $response->json();
            $this->assertIsArray($json);
            $this->assertFalse($json['status']);
            $this->assertArrayHasKey('message', $json);
            $this->assertArrayHasKey('errors', $json);
            $this->assertArrayHasKey('success', $json);
            $this->assertArrayHasKey('data', $json);

            $responseBody = $response->getContent();
            $this->assertStringNotContainsString('SQLSTATE', $responseBody, 'Response tidak boleh membocorkan SQL');
            $this->assertStringNotContainsString('syntax error', $responseBody, 'Response tidak boleh membocorkan SQL syntax');
            $this->assertStringNotContainsString('vendor/laravel', $responseBody, 'Response tidak boleh membocorkan path framework');
            $this->assertStringNotContainsString('#0 ', $responseBody, 'Response tidak boleh membocorkan stack trace');
        }
    }

    /**
     * Parameter SEC 4: Uji 422 pada input kosong/salah tidak membocorkan stack/SQL.
     */
    public function test_422_validation_errors_are_uniform_and_leak_zero_stack_or_sql(): void
    {
        // 1. Login input kosong
        $loginResponse = $this->postJson('/api/login', []);
        $loginResponse->assertStatus(422)
            ->assertJsonPath('status', false)
            ->assertJsonStructure(['status', 'message', 'errors', 'success', 'data'])
            ->assertJsonValidationErrors(['email', 'password']);

        $body = $loginResponse->getContent();
        $this->assertStringNotContainsString('SQLSTATE', $body);
        $this->assertStringNotContainsString('#0 ', $body);

        // 2. Room creation input invalid
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $roomResponse = $this->actingAs($admin)->postJson('/api/v1/rooms', [
            'name' => '',
            'capacity' => 0,
            'location' => '',
        ]);
        $roomResponse->assertStatus(422)
            ->assertJsonPath('status', false)
            ->assertJsonValidationErrors(['name', 'capacity', 'location']);

        $roomBody = $roomResponse->getContent();
        $this->assertStringNotContainsString('SQLSTATE', $roomBody);
        $this->assertStringNotContainsString('#0 ', $roomBody);
    }

    /**
     * Parameter SEC 5: Validasi field form request untuk capacity, location, user_id, dan password.
     */
    public function test_field_rules_validation_for_capacity_location_and_user_id(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);

        // Capacity dan Location wajib pada create room
        $response = $this->actingAs($admin)->postJson('/api/v1/rooms', [
            'name' => 'Ruang Baru',
        ]);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['capacity', 'location']);
        $errors = collect($response->json('errors'))->pluck('field')->all();
        $this->assertContains('capacity', $errors, 'Field capacity harus divalidasi');
        $this->assertContains('location', $errors, 'Field location harus divalidasi');

        // Password::min(8) menolak password kurang dari 8 karakter pada create user
        $userResponse = $this->actingAs($admin)->postJson('/api/v1/users', [
            'name' => 'User Pendek',
            'email' => 'shortpass@example.com',
            'password' => '123',
            'role' => 'karyawan',
            'status' => 'active',
        ]);
        $userResponse->assertStatus(422);
        $userErrors = collect($userResponse->json('errors'))->pluck('field')->all();
        $this->assertContains('password', $userErrors);

        // Change password dengan Password::min(8)
        $user = User::factory()->create([
            'password' => Hash::make('CurrentSecret123!'),
            'role' => 'karyawan',
            'status' => 'active',
        ]);

        $changePassResponse = $this->actingAs($user)->postJson('/api/v1/change-password', [
            'current_password' => 'CurrentSecret123!',
            'password' => 'short',
        ]);
        $changePassResponse->assertStatus(422);
        $changeErrors = collect($changePassResponse->json('errors'))->pluck('field')->all();
        $this->assertContains('password', $changeErrors);
    }
}
