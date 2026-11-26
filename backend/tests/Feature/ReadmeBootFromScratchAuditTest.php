<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Audit 496 Tahap 16 (Issue #166):
 * [Audit 496][P1][JUJ] README belum cukup untuk menjalankan proyek dari nol
 *
 * Target:
 * 1. README punya langkah eksekusi dari nol: True (Langkah setup container bersih dan native)
 * 2. README tidak boleh 0 baris pada root, backend, dan frontend
 * 3. Ambang batas waktu boot dari nol: <= 15 menit sampai login
 * 4. Akun demo pengujian didokumentasikan dengan lengkap
 */
class ReadmeBootFromScratchAuditTest extends TestCase
{
    public function test_all_readme_files_exist_and_are_not_empty(): void
    {
        $readmeFiles = [
            'Root README' => base_path('../README.md'),
            'Backend README' => base_path('README.md'),
            'Frontend README' => base_path('../frontend/README.md'),
        ];

        foreach ($readmeFiles as $name => $path) {
            $this->assertFileExists($path, "File {$name} harus ada di repository.");
            $lines = file($path);
            $this->assertGreaterThan(
                10,
                count($lines),
                "File {$name} tidak boleh kosong / harus memiliki lebih dari 10 baris (ditemukan " . count($lines) . " baris)."
            );
        }
    }

    public function test_root_readme_contains_step_by_step_execution_instructions(): void
    {
        $rootReadmePath = base_path('../README.md');
        $this->assertFileExists($rootReadmePath);
        $content = file_get_contents($rootReadmePath);

        // Memastikan ada section langkah menjalankan dari nol
        $this->assertTrue(
            str_contains($content, 'Cara Menjalankan dari Nol') || str_contains($content, 'Quick Start'),
            'README harus memiliki judul/bagian "Cara Menjalankan dari Nol" atau "Quick Start".'
        );

        // Memastikan seluruh perintah esensial boot dari nol tercakup
        $requiredCommands = [
            'cp .env.example .env',
            'docker compose up',
            'key:generate',
            'migrate',
            'seed',
            'http://localhost:3000',
        ];

        foreach ($requiredCommands as $command) {
            $this->assertTrue(
                str_contains($content, $command),
                "README harus menyertakan instruksi command '{$command}'."
            );
        }
    }

    public function test_readme_documents_demo_credentials_and_time_threshold(): void
    {
        $rootReadmePath = base_path('../README.md');
        $content = file_get_contents($rootReadmePath);

        // Akun pengujian
        $this->assertTrue(str_contains($content, 'admin@example.com'), 'README harus mendokumentasikan akun admin.');
        $this->assertTrue(str_contains($content, 'karyawan@example.com'), 'README harus mendokumentasikan akun karyawan.');
        $this->assertTrue(str_contains($content, 'password'), 'README harus mendokumentasikan password demo.');

        // Ambang batas standar boot dari nol <= 15 menit
        $this->assertTrue(
            str_contains($content, '15 menit') || str_contains($content, '15.0') || str_contains($content, '15 menit sampai login'),
            'README harus mendokumentasikan standar waktu boot dari nol (<= 15 menit).'
        );
    }
}
