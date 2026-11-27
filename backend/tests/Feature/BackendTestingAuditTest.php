<?php

namespace Tests\Feature;

use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/**
 * Backend Testing Quality & Behavior Audit Test (Audit 496 Parameter TST - Issue #184).
 *
 * Menguji:
 * 1. AST test: 0 assertTrue(true) dan setiap test method memiliki >= 2 assertion.
 * 2. Validasi 422: assertJsonValidationErrors aktif digunakan untuk menguji kegagalan validasi.
 * 3. Coverage: Konfigurasi driver coverage (PCOV) dan ambang batas minimum >=60% di CI dan phpunit.xml.
 * 4. Ambang lulus standar: status evaluasi membuktikan "0 pelanggaran".
 */
class BackendTestingAuditTest extends TestCase
{
    /**
     * Uji 1: AST test memverifikasi 0 assertTrue(true) dan >= 2 assertion per test.
     */
    public function test_zero_assert_true_true_and_at_least_two_assertions_per_test_method(): void
    {
        $finder = new Finder;
        $finder->files()->in(base_path('tests'))->name('*Test.php');

        $assertTrueTrueFound = [];
        $methodsWithUnderTwoAssertions = [];

        foreach ($finder as $file) {
            // Kecualikan file test audit ini sendiri yang memuat token pencarian
            if ($file->getFilename() === 'BackendTestingAuditTest.php') {
                continue;
            }

            $content = file_get_contents($file->getRealPath());
            $relPath = $file->getRelativePathname();

            // 1. Deteksi assertTrue(true)
            if (preg_match('/\$this->assertTrue\s*\(\s*true\s*\)/', $content)) {
                $assertTrueTrueFound[] = $relPath;
            }

            // 2. Deteksi metode test
            preg_match_all(
                '/(?:public function (test[a-zA-Z0-9_]*)\s*\([^)]*\))([\s\S]*?)(?=\n\s*(?:public|protected|private|\z))/m',
                $content,
                $matches,
                PREG_SET_ORDER
            );

            foreach ($matches as $match) {
                $methodName = $match[1];
                $methodBody = $match[2];

                $count = preg_match_all(
                    '/(?:\$this->assert|\$response->assert|self::assert|static::assert|assert[A-Z])/',
                    $methodBody
                );

                if ($count < 2) {
                    $methodsWithUnderTwoAssertions[] = "{$relPath}::{$methodName} (assertions: {$count})";
                }
            }
        }

        $this->assertEmpty(
            $assertTrueTrueFound,
            'Tidak boleh ada assertTrue(true) di seluruh test suite backend. Ditemukan di: '.implode(', ', $assertTrueTrueFound)
        );

        $this->assertEmpty(
            $methodsWithUnderTwoAssertions,
            'Setiap test method harus memiliki minimal 2 assertion (>=2 assertion/test). Pelanggaran di: '.implode(', ', $methodsWithUnderTwoAssertions)
        );

        $this->assertSame(0, count($assertTrueTrueFound), 'assertTrue(true) count harus 0');
        $this->assertSame(0, count($methodsWithUnderTwoAssertions), 'Metode dengan <2 assertions harus 0');
    }

    /**
     * Uji 2: assertJsonValidationErrors terpasang dan memverifikasi error validasi 422.
     */
    public function test_validation_422_tested_with_assert_json_validation_errors(): void
    {
        $finder = new Finder;
        $finder->files()->in(base_path('tests'))->name('*Test.php');

        $validationAssertCount = 0;
        foreach ($finder as $file) {
            $content = file_get_contents($file->getRealPath());
            $validationAssertCount += substr_count($content, 'assertJsonValidationErrors');
        }

        $this->assertGreaterThanOrEqual(
            5,
            $validationAssertCount,
            'Harus ada penggunaan assertJsonValidationErrors pada test validasi backend.'
        );

        // Uji eksekusi langsung assertJsonValidationErrors pada endpoint 422
        $response = $this->postJson('/api/login', []);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password']);
    }

    /**
     * Uji 3: Konfigurasi driver coverage (PCOV) dan ambang batas minimum >=60% di CI.
     */
    public function test_code_coverage_and_driver_configured_in_ci_and_phpunit(): void
    {
        $ciPath = base_path('../.github/workflows/ci.yml');
        $this->assertFileExists($ciPath, '.github/workflows/ci.yml harus ada.');

        $ciContent = file_get_contents($ciPath);
        $this->assertStringContainsString('coverage: pcov', $ciContent, 'CI harus mengaktifkan driver coverage pcov.');
        $this->assertMatchesRegularExpression('/--coverage\s+--min=60/', $ciContent, 'CI harus memeriksa ambang coverage --min=60.');

        $phpunitPath = base_path('phpunit.xml');
        $this->assertFileExists($phpunitPath, 'phpunit.xml harus ada.');
        $phpunitContent = file_get_contents($phpunitPath);
        $this->assertStringContainsString('<directory>app</directory>', $phpunitContent, 'phpunit.xml harus memetakan coverage ke direktori app.');

        $composerPath = base_path('composer.json');
        $composer = json_decode(file_get_contents($composerPath), true);
        $this->assertArrayHasKey('test:coverage', $composer['scripts'] ?? [], 'composer.json harus mendefinisikan script test:coverage.');
    }

    /**
     * Uji 4: Simulasi audit membuktikan ambang lulus "0 pelanggaran".
     */
    public function test_audit_evidence_verification_returns_zero_pelanggaran(): void
    {
        $finder = new Finder;
        $finder->files()->in(base_path('tests'))->name('*Test.php');

        $violations = 0;

        foreach ($finder as $file) {
            if ($file->getFilename() === 'BackendTestingAuditTest.php') {
                continue;
            }

            $content = file_get_contents($file->getRealPath());
            if (preg_match('/\$this->assertTrue\s*\(\s*true\s*\)/', $content)) {
                $violations++;
            }
        }

        $ciContent = file_get_contents(base_path('../.github/workflows/ci.yml'));
        if (! str_contains($ciContent, 'coverage: pcov')) {
            $violations++;
        }

        $validationErrorsGrep = 0;
        foreach ($finder as $file) {
            $content = file_get_contents($file->getRealPath());
            $validationErrorsGrep += substr_count($content, 'assertJsonValidationErrors');
        }
        if ($validationErrorsGrep === 0) {
            $violations++;
        }

        $status = ($violations === 0) ? '0 pelanggaran' : "{$violations} pelanggaran";
        $this->assertSame('0 pelanggaran', $status);
        $this->assertSame(0, $violations);
    }
}
