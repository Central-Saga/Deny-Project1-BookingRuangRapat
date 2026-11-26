<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Audit 496 Tahap 17 (Issue #167):
 * [Audit 496][P1][MNT] Linter dan pemeriksa tipe belum bersih (Pint, Larastan, ESLint)
 *
 * Target:
 * 1. Pint terpasang & dapat dijalankan (0 error)
 * 2. Larastan / PHPStan terpasang & terkonfigurasi via phpstan.neon (0 error)
 * 3. ESLint terpasang & terkonfigurasi di frontend (0 error, 0 warning)
 */
class LinterAndTypecheckerCleanAuditTest extends TestCase
{
    public function test_pint_and_larastan_are_configured_in_backend_composer(): void
    {
        $composerPath = base_path('composer.json');
        $this->assertFileExists($composerPath);
        $composer = json_decode(file_get_contents($composerPath), true);

        $devDeps = $composer['require-dev'] ?? [];
        $this->assertArrayHasKey('laravel/pint', $devDeps, 'Pint harus terdaftar di require-dev.');
        $this->assertArrayHasKey('larastan/larastan', $devDeps, 'Larastan harus terdaftar di require-dev.');

        $scripts = $composer['scripts'] ?? [];
        $this->assertArrayHasKey('pint', $scripts, 'Script Pint harus terdaftar di composer.json.');
        $this->assertArrayHasKey('phpstan', $scripts, 'Script PHPStan harus terdaftar di composer.json.');
    }

    public function test_phpstan_neon_configuration_exists_and_targets_app(): void
    {
        $neonPath = base_path('phpstan.neon');
        $this->assertFileExists($neonPath, 'File konfigurasi phpstan.neon harus ada di root backend.');
        $content = file_get_contents($neonPath);

        $this->assertTrue(str_contains($content, 'larastan/larastan'), 'phpstan.neon harus meng-include extension larastan.');
        $this->assertTrue(str_contains($content, 'app/'), 'phpstan.neon harus menganalisis direktori app/.');
    }

    public function test_eslint_is_configured_and_active_in_frontend(): void
    {
        $packageJsonPath = base_path('../frontend/package.json');
        $this->assertFileExists($packageJsonPath);
        $package = json_decode(file_get_contents($packageJsonPath), true);

        $devDeps = $package['devDependencies'] ?? [];
        $this->assertArrayHasKey('eslint', $devDeps, 'ESLint harus terdaftar di frontend devDependencies.');
        $this->assertArrayHasKey('eslint-config-next', $devDeps, 'eslint-config-next harus terdaftar di frontend devDependencies.');

        $eslintConfigPath = base_path('../frontend/eslint.config.mjs');
        $this->assertFileExists($eslintConfigPath, 'Konfigurasi eslint.config.mjs harus ada di direktori frontend.');
    }
}
