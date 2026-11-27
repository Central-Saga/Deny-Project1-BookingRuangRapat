import { expect, Page, test } from '@playwright/test';

interface MockUser {
    id: number;
    name: string;
    email: string;
    role: 'admin' | 'karyawan';
    status: 'active' | 'inactive';
}

interface MockRoom {
    id: number;
    name: string;
    capacity: number;
    location: string;
    facilities: string[];
    is_active: boolean;
}

const employeeUser: MockUser = {
    id: 2,
    name: 'Budi Karyawan',
    email: 'budi@perusahaan.com',
    role: 'karyawan',
    status: 'active',
};

const defaultRooms: MockRoom[] = [
    {
        id: 1,
        name: 'Ruang Nusantara',
        capacity: 12,
        location: 'Lantai 2',
        facilities: ['Proyektor', 'Whiteboard'],
        is_active: true,
    },
];

async function setupMockApi(page: Page) {
    await page.clock.setFixedTime(new Date('2026-11-02T08:30:00+07:00'));

    await page.route('**/api/**', async (route) => {
        const request = route.request();
        const url = new URL(request.url());
        const path = url.pathname;
        const method = request.method();
        const authHeader = request.headers()['authorization'] || '';

        if (path.endsWith('/api/login') && method === 'POST') {
            const body = request.postDataJSON() as { email?: string; password?: string };
            if (body.email === employeeUser.email && body.password === 'Karyawan123!') {
                return route.fulfill({
                    status: 200,
                    contentType: 'application/json',
                    body: JSON.stringify({ token: 'karyawan-token', user: employeeUser }),
                });
            }
            return route.fulfill({
                status: 401,
                contentType: 'application/json',
                body: JSON.stringify({
                    success: false,
                    status: false,
                    message: 'Email atau password salah.',
                    data: null,
                    errors: [],
                }),
            });
        }

        const isAuthorized = authHeader === 'Bearer karyawan-token';
        if (!isAuthorized) {
            return route.fulfill({
                status: 401,
                contentType: 'application/json',
                body: JSON.stringify({
                    success: false,
                    status: false,
                    message: 'Autentikasi diperlukan.',
                    data: null,
                    errors: [],
                }),
            });
        }

        if (path.endsWith('/api/me') && method === 'GET') {
            return route.fulfill({
                status: 200,
                contentType: 'application/json',
                body: JSON.stringify(employeeUser),
            });
        }

        if (path.endsWith('/api/v1/rooms') && method === 'GET') {
            return route.fulfill({
                status: 200,
                contentType: 'application/json',
                body: JSON.stringify({
                    success: true,
                    status: true,
                    message: 'Data ruangan berhasil diambil.',
                    data: defaultRooms,
                }),
            });
        }

        if (path.endsWith('/api/v1/bookings') && method === 'GET') {
            return route.fulfill({
                status: 200,
                contentType: 'application/json',
                body: JSON.stringify({
                    success: true,
                    status: true,
                    message: 'Data booking berhasil diambil.',
                    data: [],
                }),
            });
        }

        return route.fulfill({
            status: 200,
            contentType: 'application/json',
            body: JSON.stringify({ success: true, data: [] }),
        });
    });
}

test.describe('Keyboard & Screen Reader Usability Journey (Audit 496 Parameter USE - Issue #186)', () => {
    test('1. Alur keyboard-only lulus: login hanya keyboard -> /login berhasil masuk ke /dashboard', async ({ page }) => {
        await setupMockApi(page);

        // Kunjungi halaman /login
        await page.goto('/login');
        await expect(page).toHaveURL(/\/login$/);
        await expect(page.getByRole('heading', { name: 'Selamat datang kembali' })).toBeVisible();

        // Arahkan fokus ke input email dan isi menggunakan ketikan keyboard
        const emailInput = page.locator('input[type="email"]');
        await emailInput.focus();
        await page.keyboard.type('budi@perusahaan.com');

        // Pindah ke input password menggunakan tombol Tab
        await page.keyboard.press('Tab');
        await page.keyboard.type('Karyawan123!');

        // Tekan Enter untuk mengirim formulir login (keyboard submission)
        await page.keyboard.press('Enter');

        // Pengguna harus berhasil login dan dialihkan ke /dashboard (tidak terjebak di /login)
        await expect(page).toHaveURL(/\/dashboard$/);
        await expect(page.locator('#main-content')).toBeVisible();
        await expect(page.getByText('Budi Karyawan')).toBeVisible();
    });

    test('2. Dialog modal dapat ditutup dengan tombol Escape', async ({ page }) => {
        await setupMockApi(page);

        // Login terlebih dahulu menggunakan token di localStorage
        await page.addInitScript(() => {
            window.localStorage.setItem('meetspace_auth_token', 'karyawan-token');
        });

        await page.goto('/dashboard');
        await expect(page.locator('#main-content')).toBeVisible();

        // Buka modal booking dengan tombol Booking Cepat
        const quickBookingButton = page.getByRole('button', { name: 'Booking Cepat' });
        await quickBookingButton.click();

        // Verifikasi dialog modal terbuka
        const modalDialog = page.locator('[role="dialog"]');
        await expect(modalDialog).toBeVisible();

        // Tekan tombol Escape pada keyboard
        await page.keyboard.press('Escape');

        // Verifikasi dialog modal tertutup
        await expect(modalDialog).toBeHidden();
    });

    test('3. Skip-to-content link dapat diakses via keyboard dan memfokuskan konten utama', async ({ page }) => {
        await setupMockApi(page);

        await page.goto('/login');
        await expect(page.getByRole('heading', { name: 'Selamat datang kembali' })).toBeVisible();

        const skipLink = page.getByRole('link', { name: /lewati ke konten utama/i });
        await skipLink.focus();
        await expect(skipLink).toBeFocused();
        await expect(skipLink).toBeVisible();
        await expect(skipLink).toHaveAttribute('href', '#main-content');

        // Tekan Enter pada skip link dan pastikan fokus berpindah ke target #main-content
        await page.keyboard.press('Enter');
        await expect(page.locator('#main-content')).toBeFocused();
    });
});
