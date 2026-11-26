<?php

namespace Tests\Feature;

use App\Http\Requests\FileUploadRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Audit 496 Standar P1 SEC (Issue #175):
 * [Audit 496][P1][SEC] Berkas yang diunggah tidak divalidasi dengan aman.
 *
 * Verifikasi:
 * 1. grep temporaryUrl menghasilkan 'ada' (dukungan Signed URL untuk file privat)
 * 2. Guard tipe/ukuran file pada upload request (mimes, mimetypes, dimensions, maxSize)
 * 3. File tidak sah (ekstensi terlarang / spoofing) ditolak dengan HTTP 422
 * 4. File melebihi batas ukuran (5 MB) ditolak dengan HTTP 422
 * 5. Download privat terlindungi tanda tangan kriptografis (tampered signature -> 403)
 */
class SecureUploadValidationAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_grep_temporary_url_exists_in_upload_controller(): void
    {
        $controllerPath = app_path('Http/Controllers/Api/UploadController.php');
        $this->assertFileExists($controllerPath);

        $content = file_get_contents($controllerPath);
        $this->assertStringContainsString('temporaryUrl', $content, 'UploadController harus memiliki implementasi temporaryUrl.');
    }

    public function test_private_upload_generates_temporary_signed_url(): void
    {
        Storage::fake('local');
        $user = User::factory()->create(['role' => 'karyawan', 'status' => 'active']);

        $image = UploadedFile::fake()->image('rahasia.png', 400, 300)->size(500);

        $response = $this->actingAs($user)->postJson('/api/v1/uploads', [
            'file' => $image,
            'is_private' => true,
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.visibility', 'private');

        $this->assertNotNull($response->json('data.temporary_url'), 'Upload privat harus menghasilkan temporary_url.');
        $this->assertNotNull($response->json('data.temporaryUrl'), 'Upload privat harus menghasilkan temporaryUrl.');
        $this->assertStringContainsString('signature=', $response->json('data.temporary_url'));
    }

    public function test_temporary_url_endpoint_generates_valid_signed_url(): void
    {
        Storage::fake('local');
        $user = User::factory()->create(['role' => 'admin', 'status' => 'active']);

        $response = $this->actingAs($user)->getJson('/api/v1/uploads/temporary-url?path=uploads/private-room.png&expires_in=30');

        $response->assertOk()
            ->assertJsonPath('success', true);

        $signedUrl = $response->json('data.temporary_url');
        $this->assertNotEmpty($signedUrl);
        $this->assertStringContainsString('signature=', $signedUrl);
    }

    public function test_signed_download_protects_against_tampered_signature(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('uploads/document.png', 'fake image content');

        // Buat signed URL yang sah
        $validSignedUrl = URL::temporarySignedRoute(
            'api.uploads.signed-download',
            now()->addMinutes(30),
            ['path' => base64_encode('uploads/document.png')]
        );

        // Akses dengan signature sah -> 200 OK
        $response = $this->get($validSignedUrl);
        $response->assertOk();

        // Akses dengan signature rusak / dipalsukan -> 403 Forbidden
        $tamperedUrl = $validSignedUrl.'tampered';
        $tamperedResponse = $this->get($tamperedUrl);
        $tamperedResponse->assertStatus(403);
    }

    public function test_file_upload_guards_reject_malicious_script_and_spoofed_mimes(): void
    {
        Storage::fake('public');
        $user = User::factory()->create(['role' => 'karyawan', 'status' => 'active']);

        // Upload script shell
        $shFile = UploadedFile::fake()->create('malicious.sh', 50, 'application/x-sh');
        $this->actingAs($user)->postJson('/api/v1/uploads', [
            'file' => $shFile,
        ])->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('errors.0.field', 'file');

        // Upload PHP script yang dipalsukan sebagai image/png
        $phpFile = UploadedFile::fake()->create('backdoor.php', 50, 'image/png');
        $this->actingAs($user)->postJson('/api/v1/uploads', [
            'file' => $phpFile,
        ])->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('errors.0.field', 'file');
    }

    public function test_file_upload_guards_reject_oversized_files(): void
    {
        Storage::fake('public');
        $user = User::factory()->create(['role' => 'karyawan', 'status' => 'active']);

        // File melebihi batas 5 MB (6000 KB > 5120 KB maxSize guard)
        $oversizedFile = UploadedFile::fake()->image('giant.jpg', 1200, 800)->size(6000);

        $this->actingAs($user)->postJson('/api/v1/uploads', [
            'file' => $oversizedFile,
        ])->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('errors.0.field', 'file');

        $this->assertEquals(5120, FileUploadRequest::MAX_SIZE_KB);
    }
}
