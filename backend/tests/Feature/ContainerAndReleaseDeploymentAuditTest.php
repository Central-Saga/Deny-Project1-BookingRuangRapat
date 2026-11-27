<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Container & Release Deployment Audit Test (Audit 496 Parameter SUS - Issue #182).
 *
 * Menguji:
 * 1. storage:link + permission: storage:link terpasang di entrypoint dan izin direktori terkonfigurasi.
 * 2. Dockerfile multi-stage + non-root: multi-stage build (vendor/builder + runner) dan non-root user (USER www-data / nextjs).
 * 3. Migrasi sebagai job rilis (bukan on-boot): TIDAK ADA migrasi saat boot di entrypoint; migrasi diisolasi di release job.
 * 4. Uji upload: upload berkas berjalan lancar ("jalan") dan file tersimpan dengan url publik yang valid.
 */
class ContainerAndReleaseDeploymentAuditTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Uji 1: Backend Dockerfile menggunakan arsitektur multi-stage dan dijalankan oleh user non-root.
     */
    public function test_backend_dockerfile_is_multistage_and_runs_as_non_root(): void
    {
        $dockerfilePath = base_path('Dockerfile');
        $this->assertFileExists($dockerfilePath, 'backend/Dockerfile harus ada.');

        $content = file_get_contents($dockerfilePath);

        // 1. Multi-stage build (minimal 2 stage FROM)
        $fromCount = substr_count($content, 'FROM ');
        $this->assertGreaterThanOrEqual(2, $fromCount, 'Dockerfile harus menggunakan multi-stage build (minimal 2 stage).');
        $this->assertMatchesRegularExpression('/FROM\s+\S+\s+AS\s+\w+/i', $content, 'Dockerfile harus menamai build stage (misal AS vendor/builder).');

        // 2. Non-root user
        $this->assertMatchesRegularExpression('/^USER\s+www-data/m', $content, 'Dockerfile harus beralih ke user non-root (USER www-data).');

        // 3. Entrypoint terpasang
        $this->assertMatchesRegularExpression('/ENTRYPOINT\s+\[/i', $content, 'Dockerfile harus mendefinisikan ENTRYPOINT.');
    }

    /**
     * Uji 2: Frontend Dockerfile menggunakan arsitektur multi-stage dan user non-root.
     */
    public function test_frontend_dockerfile_is_multistage_and_runs_as_non_root(): void
    {
        $dockerfilePath = base_path('../frontend/Dockerfile');
        $this->assertFileExists($dockerfilePath, 'frontend/Dockerfile harus ada.');

        $content = file_get_contents($dockerfilePath);

        // 1. Multi-stage build
        $fromCount = substr_count($content, 'FROM ');
        $this->assertGreaterThanOrEqual(2, $fromCount, 'frontend/Dockerfile harus multi-stage build.');

        // 2. Non-root user
        $this->assertMatchesRegularExpression('/^USER\s+(nextjs|node)/m', $content, 'frontend/Dockerfile harus menggunakan user non-root.');
    }

    /**
     * Uji 3: Skrip entrypoint memuat perintah storage:link dan perizinan direktori.
     */
    public function test_entrypoint_script_configures_storage_link_and_safe_permissions(): void
    {
        $entrypointPaths = [
            base_path('docker/entrypoint.sh'),
            base_path('entrypoint.sh'),
        ];

        foreach ($entrypointPaths as $path) {
            $this->assertFileExists($path, "Skrip entrypoint {$path} harus ada.");
            $content = file_get_contents($path);

            // storage:link di entrypoint: True
            $this->assertStringContainsString('storage:link', $content, 'Entrypoint harus menjalankan storage:link.');

            // Penyiapan direktori storage framework
            $this->assertStringContainsString('storage/framework', $content, 'Entrypoint harus menyiapkan direktori storage/framework.');
        }
    }

    /**
     * Uji 4: Entrypoint TIDAK menjalankan migrasi saat boot (migrasi diisolasi di release job).
     */
    public function test_entrypoint_does_not_execute_database_migrations_on_boot(): void
    {
        $entrypointPath = base_path('docker/entrypoint.sh');
        $content = file_get_contents($entrypointPath);

        // TIDAK ADA migrasi saat boot
        $this->assertDoesNotMatchRegularExpression(
            '/php\s+artisan\s+migrate/i',
            $content,
            'Entrypoint TIDAK boleh mengeksekusi migrasi saat boot (mencegah race condition horizontal scaling).'
        );

        // Release job scripts terpisah harus tersedia
        $releaseScriptPath = base_path('docker/release.sh');
        $this->assertFileExists($releaseScriptPath, 'Skrip release job backend/docker/release.sh harus ada.');

        $releaseContent = file_get_contents($releaseScriptPath);
        $this->assertStringContainsString('php artisan migrate --force', $releaseContent, 'Release script harus mengeksekusi migrasi database.');

        $monorepoRelease = base_path('../scripts/release.sh');
        $this->assertFileExists($monorepoRelease, 'Skrip release monorepo scripts/release.sh harus ada.');

        // docker-compose.yml memuat service release
        $composePath = base_path('../docker-compose.yml');
        $composeContent = file_get_contents($composePath);
        $this->assertStringContainsString('release:', $composeContent, 'docker-compose.yml harus memuat service release.');
    }

    /**
     * Uji 5: Uji upload berkas berjalan lancar ("jalan") dan file tersimpan dengan link publik valid.
     */
    public function test_upload_file_runs_smoothly_and_creates_valid_link(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $user = User::factory()->create(['role' => 'karyawan']);
        Sanctum::actingAs($user);

        // Pastikan storage link berfungsi
        Artisan::call('storage:link');

        // Buat file gambar dummy valid (PNG 100x100)
        $file = UploadedFile::fake()->image('test_ruangan.png', 100, 100)->size(500);

        $response = $this->postJson('/api/v1/uploads', [
            'file' => $file,
            'is_private' => false,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('status', true)
            ->assertJsonStructure([
                'data' => [
                    'url',
                    'path',
                    'size',
                    'mime_type',
                    'visibility',
                ],
            ]);

        $uploadedPath = $response->json('data.path');
        Storage::disk('public')->assertExists($uploadedPath);
        $this->assertStringContainsString('storage/', $response->json('data.url'));
    }
}
