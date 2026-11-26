<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Audit 496 Parameter OPT (Issue #169):
 * Halaman masih berat dimuat (bundle, Web Vitals, gambar).
 * Memastikan optimasi frontend bundle splitting dan analyzer script tersedia dengan target JS awal <= 200 KB gzip.
 */
class InitialBundlePerformanceAuditTest extends TestCase
{
    public function test_frontend_has_bundle_analyzer_script_configured(): void
    {
        $packageJsonPath = base_path('../frontend/package.json');
        $this->assertFileExists($packageJsonPath, 'package.json frontend wajib ada.');

        $pkg = json_decode(file_get_contents($packageJsonPath), true);
        $this->assertArrayHasKey('scripts', $pkg);
        $this->assertArrayHasKey('analyze', $pkg['scripts'], 'Skrip npm run analyze wajib tersedia.');

        $analyzerPath = base_path('../frontend/scripts/analyze-bundle.mjs');
        $this->assertFileExists($analyzerPath, 'Skrip scripts/analyze-bundle.mjs wajib ada.');

        $analyzerContent = file_get_contents($analyzerPath);
        $this->assertStringContainsString('THRESHOLD_KB = 200', $analyzerContent, 'Ambang batas JS awal harus <= 200 KB.');
    }

    public function test_frontend_components_use_code_splitting_dynamic_imports(): void
    {
        $dashboardPagePath = base_path('../frontend/src/components/DashboardPage.tsx');
        $this->assertFileExists($dashboardPagePath);

        $content = file_get_contents($dashboardPagePath);
        $this->assertStringContainsString("dynamic(() => import('./AdminDashboardPage')", $content);
        $this->assertStringContainsString("dynamic(() => import('@/components/BookingCalendar')", $content);
        $this->assertStringContainsString("dynamic(() => import('@/components/BookingModal')", $content);
    }
}
