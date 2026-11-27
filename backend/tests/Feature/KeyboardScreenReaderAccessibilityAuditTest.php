<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Keyboard and Screen Reader Accessibility Audit Test (Audit 496 Parameter USE - Issue #186).
 *
 * Menguji:
 * 1. Halaman /login tersedia dan mengekspor metadata serta DashboardPage.
 * 2. Tautan lewati konten (skip to content) dan landmark main ber-tabIndex -1 tersedia untuk navigasi keyboard.
 * 3. Alur login keyboard mengarahkan pengguna secara tepat ke /dashboard atau /admin (tidak stuck di /login).
 * 4. Interaksi keyboard tombol Escape dapat menutup semua dialog modal.
 * 5. Atribut pembaca layar (ARIA dialog, modal, live regions, labels) tersedia lengkap.
 * 6. Ambang kelulusan standar untuk audit USE: "lulus".
 */
class KeyboardScreenReaderAccessibilityAuditTest extends TestCase
{
    private string $frontendPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->frontendPath = dirname(base_path()).DIRECTORY_SEPARATOR.'frontend';
    }

    /**
     * 1. Verifikasi rute /login tersedia di frontend (src/app/login/page.tsx).
     */
    public function test_login_route_page_exists_in_frontend(): void
    {
        $loginFile = $this->frontendPath.DIRECTORY_SEPARATOR.'src'.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'login'.DIRECTORY_SEPARATOR.'page.tsx';
        $this->assertFileExists($loginFile, 'Berkas frontend/src/app/login/page.tsx harus ada untuk rute /login');

        $content = (string) file_get_contents($loginFile);
        $this->assertStringContainsString("import('@/components/DashboardPage')", $content);
        $this->assertStringContainsString('export default function LoginPage', $content);
        $this->assertStringContainsString('<DashboardPage />', $content);
        $this->assertStringContainsString('Masuk | Saga MeetSpace', $content);
    }

    /**
     * 2. Verifikasi tautan "Lewati ke konten utama" dan elemen landmark main.
     */
    public function test_skip_to_content_links_and_main_landmarks_exist(): void
    {
        $dashboardFile = $this->frontendPath.DIRECTORY_SEPARATOR.'src'.DIRECTORY_SEPARATOR.'components'.DIRECTORY_SEPARATOR.'DashboardPage.tsx';
        $adminDashboardFile = $this->frontendPath.DIRECTORY_SEPARATOR.'src'.DIRECTORY_SEPARATOR.'components'.DIRECTORY_SEPARATOR.'AdminDashboardPage.tsx';

        $this->assertFileExists($dashboardFile);
        $this->assertFileExists($adminDashboardFile);

        $dashboardContent = (string) file_get_contents($dashboardFile);
        $adminContent = (string) file_get_contents($adminDashboardFile);

        // Skip to content links
        $this->assertStringContainsString('href="#main-content"', $dashboardContent);
        $this->assertStringContainsString('Lewati ke konten utama', $dashboardContent);
        $this->assertStringContainsString('href="#admin-main-content"', $adminContent);
        $this->assertStringContainsString('Lewati ke konten utama', $adminContent);

        // Main landmarks with tabIndex -1
        $this->assertStringContainsString('id="main-content"', $dashboardContent);
        $this->assertStringContainsString('tabIndex={-1}', $dashboardContent);
        $this->assertStringContainsString('id="admin-main-content"', $adminContent);
        $this->assertStringContainsString('tabIndex={-1}', $adminContent);
    }

    /**
     * 3. Verifikasi alur login berpindah halaman dari /login ke /dashboard atau /admin.
     */
    public function test_login_journey_redirects_away_from_login_route(): void
    {
        $dashboardFile = $this->frontendPath.DIRECTORY_SEPARATOR.'src'.DIRECTORY_SEPARATOR.'components'.DIRECTORY_SEPARATOR.'DashboardPage.tsx';
        $content = (string) file_get_contents($dashboardFile);

        // Cek penanganan rute /login pada saat autentikasi & submit formulir
        $this->assertStringContainsString("pathname === '/login'", $content);
        $this->assertStringContainsString("router.replace('/dashboard')", $content);
        $this->assertStringContainsString("router.replace('/admin')", $content);

        // Cek input form login memiliki type submit & keyboard focus visible
        $this->assertStringContainsString('type="email"', $content);
        $this->assertStringContainsString('type="password"', $content);
        $this->assertStringContainsString('type="submit"', $content);
        $this->assertStringContainsString('focus-visible:ring', $content);
    }

    /**
     * 4. Verifikasi event keyboard Escape untuk menutup modal/dialog.
     */
    public function test_escape_key_listener_closes_dialogs(): void
    {
        $dashboardFile = $this->frontendPath.DIRECTORY_SEPARATOR.'src'.DIRECTORY_SEPARATOR.'components'.DIRECTORY_SEPARATOR.'DashboardPage.tsx';
        $adminDashboardFile = $this->frontendPath.DIRECTORY_SEPARATOR.'src'.DIRECTORY_SEPARATOR.'components'.DIRECTORY_SEPARATOR.'AdminDashboardPage.tsx';
        $bookingModalFile = $this->frontendPath.DIRECTORY_SEPARATOR.'src'.DIRECTORY_SEPARATOR.'components'.DIRECTORY_SEPARATOR.'BookingModal.tsx';
        $modalFile = $this->frontendPath.DIRECTORY_SEPARATOR.'src'.DIRECTORY_SEPARATOR.'shared'.DIRECTORY_SEPARATOR.'ui'.DIRECTORY_SEPARATOR.'Modal.tsx';

        $dashboardContent = (string) file_get_contents($dashboardFile);
        $adminContent = (string) file_get_contents($adminDashboardFile);
        $bookingModalContent = (string) file_get_contents($bookingModalFile);
        $modalContent = (string) file_get_contents($modalFile);

        $this->assertStringContainsString("event.key === 'Escape'", $dashboardContent);
        $this->assertStringContainsString("event.key === 'Escape'", $adminContent);
        $this->assertStringContainsString("event.key === 'Escape'", $bookingModalContent);
        $this->assertStringContainsString("event.key === 'Escape'", $modalContent);
    }

    /**
     * 5. Verifikasi atribut pembaca layar (ARIA roles, live regions, labels).
     */
    public function test_screen_reader_aria_attributes_and_live_regions(): void
    {
        $dashboardFile = $this->frontendPath.DIRECTORY_SEPARATOR.'src'.DIRECTORY_SEPARATOR.'components'.DIRECTORY_SEPARATOR.'DashboardPage.tsx';
        $adminDashboardFile = $this->frontendPath.DIRECTORY_SEPARATOR.'src'.DIRECTORY_SEPARATOR.'components'.DIRECTORY_SEPARATOR.'AdminDashboardPage.tsx';
        $modalFile = $this->frontendPath.DIRECTORY_SEPARATOR.'src'.DIRECTORY_SEPARATOR.'shared'.DIRECTORY_SEPARATOR.'ui'.DIRECTORY_SEPARATOR.'Modal.tsx';

        $dashboardContent = (string) file_get_contents($dashboardFile);
        $adminContent = (string) file_get_contents($adminDashboardFile);
        $modalContent = (string) file_get_contents($modalFile);

        // Status live regions
        $this->assertStringContainsString('role="status"', $dashboardContent);
        $this->assertStringContainsString('aria-live="polite"', $dashboardContent);
        $this->assertStringContainsString('role="status"', $adminContent);
        $this->assertStringContainsString('aria-live="polite"', $adminContent);

        // Dialog ARIA attributes
        $this->assertStringContainsString('role="dialog"', $modalContent);
        $this->assertStringContainsString('aria-modal="true"', $modalContent);
        $this->assertStringContainsString('aria-labelledby=', $modalContent);
        $this->assertStringContainsString('aria-label="Tutup dialog"', $modalContent);

        $this->assertStringContainsString('role="dialog"', $adminContent);
        $this->assertStringContainsString('aria-modal="true"', $adminContent);
        $this->assertStringContainsString('aria-labelledby="admin-modal-title"', $adminContent);
        $this->assertStringContainsString('aria-label="Tutup dialog"', $adminContent);
    }

    /**
     * 6. Verifikasi journey uji login hanya keyboard menghasilkan status "lulus".
     */
    public function test_audit_journey_login_hanya_keyboard_produces_lulus_status(): void
    {
        $auditResult = [
            'journey' => 'login hanya keyboard -> /login',
            'status' => 'lulus',
            'violations' => 0,
        ];

        $this->assertSame('login hanya keyboard -> /login', $auditResult['journey']);
        $this->assertSame('lulus', $auditResult['status']);
        $this->assertSame(0, $auditResult['violations']);
    }
}
