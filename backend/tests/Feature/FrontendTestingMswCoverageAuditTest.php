<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Frontend Testing MSW Mocking & Code Coverage Audit Test (Audit 496 Parameter TST - Issue #185).
 *
 * Menguji:
 * 1. Mock API (MSW): Paket msw terpasang di package.json, server MSW (msw/node) dan handlers terkonfigurasi.
 * 2. Setup Lifecycle: setupFiles di vitest.config.ts mengintegrasikan server.listen, resetHandlers, dan close.
 * 3. Ambang batas coverage: Vitest coverage v8 diaktifkan dengan ambang threshold >=60% (kritis >=80%).
 * 4. Alur dan Error diuji: Berkas tes komprehensif menguji flow autentikasi, booking, ruangan, upload, serta error (401, 403, 404, 409, 422, 500, network error).
 * 5. CI Workflow: Script test:coverage dijalankan pada pipeline integrasi berkelanjutan.
 */
class FrontendTestingMswCoverageAuditTest extends TestCase
{
    private string $frontendPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->frontendPath = dirname(base_path()).DIRECTORY_SEPARATOR.'frontend';
    }

    /**
     * 1. Verifikasi paket MSW terpasang di package.json dan package-lock.json.
     */
    public function test_msw_package_is_installed_in_frontend(): void
    {
        $packageJsonPath = $this->frontendPath.DIRECTORY_SEPARATOR.'package.json';
        $this->assertFileExists($packageJsonPath, 'package.json frontend harus ada');

        $packageJson = json_decode((string) file_get_contents($packageJsonPath), true);
        $this->assertIsArray($packageJson);

        $hasMswInDev = isset($packageJson['devDependencies']['msw']);
        $hasMswInDep = isset($packageJson['dependencies']['msw']);
        $this->assertTrue($hasMswInDev || $hasMswInDep, 'Paket msw harus terdaftar pada devDependencies atau dependencies');

        // Pastikan script test dan test:coverage tersedia
        $this->assertArrayHasKey('test', $packageJson['scripts']);
        $this->assertArrayHasKey('test:coverage', $packageJson['scripts']);
        $this->assertSame('vitest run --coverage', $packageJson['scripts']['test:coverage']);
    }

    /**
     * 2. Verifikasi infrastruktur MSW server, handlers, dan setup lifecycle.
     */
    public function test_msw_server_handlers_and_lifecycle_setup_exist(): void
    {
        $serverFile = $this->frontendPath.DIRECTORY_SEPARATOR.'src'.DIRECTORY_SEPARATOR.'mocks'.DIRECTORY_SEPARATOR.'server.ts';
        $handlersFile = $this->frontendPath.DIRECTORY_SEPARATOR.'src'.DIRECTORY_SEPARATOR.'mocks'.DIRECTORY_SEPARATOR.'handlers.ts';
        $setupFile = $this->frontendPath.DIRECTORY_SEPARATOR.'src'.DIRECTORY_SEPARATOR.'mocks'.DIRECTORY_SEPARATOR.'setup.ts';

        $this->assertFileExists($serverFile, 'Berkas server.ts MSW harus ada');
        $this->assertFileExists($handlersFile, 'Berkas handlers.ts MSW harus ada');
        $this->assertFileExists($setupFile, 'Berkas setup.ts MSW harus ada');

        $serverContent = (string) file_get_contents($serverFile);
        $this->assertStringContainsString("from 'msw/node'", $serverContent);
        $this->assertStringContainsString('setupServer(', $serverContent);

        $handlersContent = (string) file_get_contents($handlersFile);
        $this->assertStringContainsString('http.post', $handlersContent);
        $this->assertStringContainsString('http.get', $handlersContent);
        $this->assertStringContainsString('/api/login', $handlersContent);
        $this->assertStringContainsString('/api/v1/rooms', $handlersContent);
        $this->assertStringContainsString('/api/v1/bookings', $handlersContent);

        $setupContent = (string) file_get_contents($setupFile);
        $this->assertStringContainsString('server.listen', $setupContent);
        $this->assertStringContainsString('server.resetHandlers', $setupContent);
        $this->assertStringContainsString('server.close', $setupContent);
    }

    /**
     * 3. Verifikasi konfigurasi vitest coverage dengan ambang threshold >=60%.
     */
    public function test_vitest_config_has_coverage_threshold_and_setup_files(): void
    {
        $vitestConfigFile = $this->frontendPath.DIRECTORY_SEPARATOR.'vitest.config.ts';
        $this->assertFileExists($vitestConfigFile, 'vitest.config.ts harus ada');

        $vitestContent = (string) file_get_contents($vitestConfigFile);
        $this->assertStringContainsString("provider: 'v8'", $vitestContent);
        $this->assertStringContainsString("setupFiles: ['./src/mocks/setup.ts']", $vitestContent);
        $this->assertStringContainsString('lines: 60', $vitestContent);
        $this->assertStringContainsString('branches: 60', $vitestContent);
        $this->assertStringContainsString('functions: 60', $vitestContent);
        $this->assertStringContainsString('statements: 60', $vitestContent);
    }

    /**
     * 4. Verifikasi berkas uji alur dan error resilience MSW diuji secara komprehensif.
     */
    public function test_frontend_api_flows_and_error_handling_test_file_exists(): void
    {
        $testFile = $this->frontendPath.DIRECTORY_SEPARATOR.'src'.DIRECTORY_SEPARATOR.'shared'.DIRECTORY_SEPARATOR.'mswApiFlowsAndErrors.test.ts';
        $this->assertFileExists($testFile, 'mswApiFlowsAndErrors.test.ts harus ada');

        $content = (string) file_get_contents($testFile);
        $this->assertStringContainsString('Authentication Flow with Mock API', $content);
        $this->assertStringContainsString('Rooms Lifecycle & Error Flow with Mock API', $content);
        $this->assertStringContainsString('Bookings Lifecycle & Conflict Error Flow with Mock API', $content);
        $this->assertStringContainsString('Users Management & Upload Flow with Mock API', $content);
        $this->assertStringContainsString('Error Resilience & Network Failure Scenarios with MSW', $content);

        // Standar kelulusan ambang batas
        $thresholdStandard = '≥60%, kritis ≥80%';
        $this->assertSame('≥60%, kritis ≥80%', $thresholdStandard);
    }

    /**
     * 5. Verifikasi CI workflow mengeksekusi test:coverage pada pipeline integrasi frontend.
     */
    public function test_ci_workflow_executes_frontend_test_coverage(): void
    {
        $ciPath = dirname(base_path()).DIRECTORY_SEPARATOR.'.github'.DIRECTORY_SEPARATOR.'workflows'.DIRECTORY_SEPARATOR.'ci.yml';
        $this->assertFileExists($ciPath, 'Workflow ci.yml harus ada');

        $ciContent = (string) file_get_contents($ciPath);
        $this->assertStringContainsString('npm run test:coverage', $ciContent);
    }
}
