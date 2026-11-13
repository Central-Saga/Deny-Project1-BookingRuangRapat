<?php

namespace Tests\Feature;

use App\Http\Requests\FileUploadRequest;
use App\Http\Requests\StoreRoomRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UploadValidationAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_upload_validation_rules_contain_mimetypes_dimensions_and_max(): void
    {
        // Backend parameter 4.8: Upload divalidasi isi (mimetypes), dimensi (dimensions), dan ukuran (max:)
        $this->assertFileExists(base_path('app/Http/Requests/StoreRoomRequest.php'));
        $this->assertFileExists(base_path('../app/Http/Requests/StoreRoomRequest.php'));
        $this->assertFileExists(base_path('app/Http/Requests/FileUploadRequest.php'));
        $this->assertFileExists(base_path('../app/Http/Requests/FileUploadRequest.php'));

        $fileRequest = new FileUploadRequest();
        $fileRules = $fileRequest->rules()['file'];

        // Backend parameter 4.8: Upload divalidasi isi (mimetypes), dimensi (dimensions), dan ukuran (max:)
        $rulesString = is_array($fileRules) ? implode('|', $fileRules) : $fileRules;
        $this->assertStringContainsString('mimetypes', $rulesString);
        $this->assertStringContainsString('dimensions', $rulesString);
        $this->assertStringContainsString('max:', $rulesString);

        $roomRequest = new StoreRoomRequest();
        $roomImageRules = $roomRequest->rules()['image'];
        $roomRulesString = is_array($roomImageRules) ? implode('|', $roomImageRules) : $roomImageRules;
        $this->assertStringContainsString('mimetypes', $roomRulesString);
        $this->assertStringContainsString('dimensions', $roomRulesString);
        $this->assertStringContainsString('max:', $roomRulesString);
    }

    public function test_valid_image_upload_succeeds(): void
    {
        Storage::fake('public');
        $user = User::factory()->create(['role' => 'karyawan', 'status' => 'active']);

        $validImage = UploadedFile::fake()->image('sample.jpg', 800, 600)->size(1024); // 1 MB, 800x600

        $response = $this->actingAs($user)->postJson('/api/v1/uploads', [
            'file' => $validImage,
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'File berhasil diunggah.');

        $storedPath = $response->json('data.path');
        Storage::disk('public')->assertExists($storedPath);
    }

    public function test_invalid_mimetypes_is_rejected(): void
    {
        Storage::fake('public');
        $user = User::factory()->create(['role' => 'karyawan', 'status' => 'active']);

        // Upload a non-allowed mimetype (e.g., text or script)
        $invalidFile = UploadedFile::fake()->create('malicious.sh', 50, 'application/x-sh');

        $response = $this->actingAs($user)->postJson('/api/v1/uploads', [
            'file' => $invalidFile,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('errors.0.field', 'file');
    }

    public function test_image_with_invalid_dimensions_is_rejected(): void
    {
        Storage::fake('public');
        $user = User::factory()->create(['role' => 'karyawan', 'status' => 'active']);

        // Image dimensions below minimum (10x10 < 100x100)
        $smallImage = UploadedFile::fake()->image('tiny.jpg', 10, 10)->size(50);

        $response = $this->actingAs($user)->postJson('/api/v1/uploads', [
            'file' => $smallImage,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('errors.0.field', 'file');
    }

    public function test_file_exceeding_max_size_is_rejected(): void
    {
        Storage::fake('public');
        $user = User::factory()->create(['role' => 'karyawan', 'status' => 'active']);

        // Image exceeds max 5MB (6000 KB > 5120 KB)
        $largeImage = UploadedFile::fake()->image('huge.jpg', 1200, 800)->size(6000);

        $response = $this->actingAs($user)->postJson('/api/v1/uploads', [
            'file' => $largeImage,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('errors.0.field', 'file');
    }

    public function test_room_creation_validates_image_mimetypes_and_dimensions(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);

        // Invalid mimetype on room image upload
        $badFile = UploadedFile::fake()->create('document.exe', 100, 'application/x-msdownload');
        $this->actingAs($admin)->postJson('/api/v1/rooms', [
            'name' => 'Room Testing',
            'capacity' => 10,
            'location' => 'Lantai 1',
            'image' => $badFile,
        ])->assertStatus(422)
          ->assertJsonPath('success', false)
          ->assertJsonPath('errors.0.field', 'image');

        // Invalid dimensions on room image upload
        $badDimensions = UploadedFile::fake()->image('tiny.png', 10, 10);
        $this->actingAs($admin)->postJson('/api/v1/rooms', [
            'name' => 'Room Testing',
            'capacity' => 10,
            'location' => 'Lantai 1',
            'image' => $badDimensions,
        ])->assertStatus(422)
          ->assertJsonPath('success', false)
          ->assertJsonPath('errors.0.field', 'image');
    }
}
