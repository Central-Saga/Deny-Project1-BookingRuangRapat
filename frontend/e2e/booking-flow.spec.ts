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

interface MockBooking {
    id: number;
    room_id: number;
    user_id: number;
    title: string;
    participant_count: number;
    start_time: string;
    end_time: string;
    status: 'pending' | 'approved' | 'rejected' | 'cancelled';
    notes?: string;
    approval_notes?: string;
    user: MockUser;
    room: MockRoom;
}

const employeeUser: MockUser = {
    id: 2,
    name: 'Budi Karyawan',
    email: 'budi@perusahaan.com',
    role: 'karyawan',
    status: 'active',
};

const adminUser: MockUser = {
    id: 1,
    name: 'Siti Admin',
    email: 'admin@perusahaan.com',
    role: 'admin',
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
    {
        id: 2,
        name: 'Ruang Garuda',
        capacity: 8,
        location: 'Lantai 3',
        facilities: ['TV LED'],
        is_active: true,
    },
];

async function setupMockApi(page: Page, initialBookings: MockBooking[] = []) {
    await page.clock.setFixedTime(new Date('2026-11-02T08:30:00+07:00'));

    const bookings: MockBooking[] = [...initialBookings];
    let nextBookingId = bookings.length + 100;

    await page.route('**/api/**', async (route) => {
        const request = route.request();
        const url = new URL(request.url());
        const path = url.pathname;
        const method = request.method();
        const authHeader = request.headers()['authorization'] || '';

        if (path.endsWith('/api/login') && method === 'POST') {
            const body = request.postDataJSON() as { email?: string; password?: string };
            if (body.email === adminUser.email && body.password === 'Admin123!') {
                return route.fulfill({
                    status: 200,
                    contentType: 'application/json',
                    body: JSON.stringify({ token: 'admin-token', user: adminUser }),
                });
            }
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

        const isAuthorized = authHeader === 'Bearer admin-token' || authHeader === 'Bearer karyawan-token';
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
                body: JSON.stringify(authHeader === 'Bearer admin-token' ? adminUser : employeeUser),
            });
        }

        if (path.endsWith('/api/logout') && method === 'POST') {
            return route.fulfill({
                status: 200,
                contentType: 'application/json',
                body: JSON.stringify({ success: true, status: true, message: 'Logout berhasil.' }),
            });
        }

        if (path.endsWith('/api/v1/rooms') && method === 'GET') {
            return route.fulfill({
                status: 200,
                contentType: 'application/json',
                body: JSON.stringify({
                    success: true,
                    status: true,
                    data: defaultRooms,
                    meta: { current_page: 1, last_page: 1, per_page: 100, total: defaultRooms.length },
                    errors: [],
                }),
            });
        }

        if (path.endsWith('/api/v1/users') && method === 'GET') {
            return route.fulfill({
                status: 200,
                contentType: 'application/json',
                body: JSON.stringify({
                    success: true,
                    status: true,
                    data: [adminUser, employeeUser],
                    meta: { current_page: 1, last_page: 1, per_page: 100, total: 2 },
                    errors: [],
                }),
            });
        }

        if (path.endsWith('/api/v1/bookings/availability') && method === 'GET') {
            const active = bookings.filter((b) => b.status !== 'rejected' && b.status !== 'cancelled');
            return route.fulfill({
                status: 200,
                contentType: 'application/json',
                body: JSON.stringify({
                    success: true,
                    status: true,
                    data: active,
                    meta: { current_page: 1, last_page: 1, per_page: 100, total: active.length },
                    errors: [],
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
                    data: bookings,
                    meta: { current_page: 1, last_page: 1, per_page: 100, total: bookings.length },
                    errors: [],
                }),
            });
        }

        if (path.endsWith('/api/v1/bookings') && method === 'POST') {
            const payload = request.postDataJSON() as {
                room_id: number;
                title: string;
                participant_count: number;
                start_time: string;
                end_time: string;
                notes?: string;
            };
            const room = defaultRooms.find((r) => r.id === payload.room_id) || defaultRooms[0];
            const created: MockBooking = {
                id: nextBookingId++,
                room_id: room.id,
                user_id: employeeUser.id,
                title: payload.title,
                participant_count: payload.participant_count,
                start_time: payload.start_time,
                end_time: payload.end_time,
                status: 'pending',
                notes: payload.notes,
                user: employeeUser,
                room,
            };
            bookings.unshift(created);
            return route.fulfill({
                status: 201,
                contentType: 'application/json',
                body: JSON.stringify({
                    status: true,
                    message: 'Operasi booking berhasil.',
                    data: created,
                    errors: [],
                }),
            });
        }

        const approveMatch = path.match(/\/api\/v1\/bookings\/(\d+)\/approve$/);
        if (approveMatch && method === 'PATCH') {
            const id = Number(approveMatch[1]);
            const target = bookings.find((b) => b.id === id);
            if (target) {
                target.status = 'approved';
            }
            return route.fulfill({
                status: 200,
                contentType: 'application/json',
                body: JSON.stringify({
                    status: true,
                    message: 'Operasi booking berhasil.',
                    data: target,
                    errors: [],
                }),
            });
        }

        return route.fulfill({
            status: 404,
            contentType: 'application/json',
            body: JSON.stringify({
                success: false,
                status: false,
                message: 'Endpoint tidak ditemukan.',
                data: null,
                errors: [],
            }),
        });
    });

    return { bookings };
}

test.describe('Alur Inti Booking Ruang Rapat (E2E)', () => {
    test('1. Karyawan login dan membuat booking baru hingga berstatus Menunggu persetujuan', async ({ page }) => {
        await setupMockApi(page);

        await page.goto('/');
        await expect(page.getByRole('heading', { name: 'Selamat datang kembali' })).toBeVisible();

        await page.getByLabel('Email').fill(employeeUser.email);
        await page.getByLabel('Password').fill('Karyawan123!');
        await page.getByRole('button', { name: 'Masuk' }).click();

        await expect(page).toHaveURL(/\/dashboard$/);
        await expect(page.getByText('Dashboard karyawan')).toBeVisible();
        await page.getByRole('button', { name: 'Booking Cepat' }).click();

        const modal = page.getByRole('dialog');
        await expect(modal).toBeVisible();
        await modal.getByLabel('Pilih ruangan').selectOption('1');
        await modal.getByLabel('Nama agenda').fill('Rapat Evaluasi Kuartal');
        await modal.getByLabel('Jumlah peserta').fill('8');
        await modal.getByLabel('Tanggal').fill('2026-11-02');
        await modal.getByLabel('Mulai').fill('09:00');
        await modal.getByLabel('Selesai').fill('10:30');
        await modal.getByLabel(/Catatan/i).fill('Bawa laporan KPI divisi');
        await modal.getByRole('button', { name: 'Ajukan booking' }).click();

        await expect(modal).toBeHidden();

        await page.getByRole('link', { name: 'Riwayat Booking' }).click();
        await expect(page).toHaveURL(/\/riwayat$/);
        await expect(page.getByRole('heading', { name: 'Rapat Evaluasi Kuartal' })).toBeVisible();
        await expect(page.getByText('Menunggu persetujuan')).toBeVisible();
    });

    test('2. Admin login dan menyetujui permintaan booking yang pending', async ({ page }) => {
        await setupMockApi(page, [
            {
                id: 50,
                room_id: 1,
                user_id: employeeUser.id,
                title: 'Sinkronisasi Proyek Infrastruktur',
                participant_count: 6,
                start_time: '2026-11-02T09:00:00+07:00',
                end_time: '2026-11-02T10:30:00+07:00',
                status: 'pending',
                user: employeeUser,
                room: defaultRooms[0],
            },
        ]);

        await page.goto('/');
        await page.getByLabel('Email').fill(adminUser.email);
        await page.getByLabel('Password').fill('Admin123!');
        await page.getByRole('button', { name: 'Masuk' }).click();

        await expect(page).toHaveURL(/\/admin$/);
        await expect(page.getByText('DASHBOARD ADMIN')).toBeVisible();
        await page.getByRole('button', { name: 'Tinjau booking' }).click();

        await expect(page.getByRole('heading', { name: 'Sinkronisasi Proyek Infrastruktur' })).toBeVisible();
        await page.getByRole('button', { name: 'Setujui' }).click();

        await expect(page.getByText('Booking berhasil disetujui.')).toBeVisible();
        await expect(page.getByText('Tidak ada permintaan yang belum disetujui.')).toBeVisible();
    });

    test('3. Alur penuh: karyawan buat booking -> admin approve -> status Disetujui tampil di dashboard karyawan', async ({ page }) => {
        await setupMockApi(page);

        // Tahap 1: Karyawan login & buat booking
        await page.goto('/');
        await page.getByLabel('Email').fill(employeeUser.email);
        await page.getByLabel('Password').fill('Karyawan123!');
        await page.getByRole('button', { name: 'Masuk' }).click();

        await expect(page).toHaveURL(/\/dashboard$/);
        await page.getByRole('button', { name: 'Booking Cepat' }).click();
        const modal = page.getByRole('dialog');
        await modal.getByLabel('Pilih ruangan').selectOption('1');
        await modal.getByLabel('Nama agenda').fill('Rapat Koordinasi Produk');
        await modal.getByLabel('Jumlah peserta').fill('5');
        await modal.getByLabel('Tanggal').fill('2026-11-02');
        await modal.getByLabel('Mulai').fill('13:00');
        await modal.getByLabel('Selesai').fill('14:30');
        await modal.getByRole('button', { name: 'Ajukan booking' }).click();
        await expect(modal).toBeHidden();

        // Karyawan keluar
        await page.getByRole('button', { name: 'Keluar dari akun' }).click();
        await page.getByRole('button', { name: 'Ya, keluar' }).click();
        await expect(page.getByRole('heading', { name: 'Selamat datang kembali' })).toBeVisible();

        // Tahap 2: Admin login & approve booking tersebut
        await page.getByLabel('Email').fill(adminUser.email);
        await page.getByLabel('Password').fill('Admin123!');
        await page.getByRole('button', { name: 'Masuk' }).click();

        await expect(page).toHaveURL(/\/admin$/);
        await page.getByRole('button', { name: 'Tinjau booking' }).click();
        await expect(page.getByRole('heading', { name: 'Rapat Koordinasi Produk' })).toBeVisible();
        await page.getByRole('button', { name: 'Setujui' }).click();
        await expect(page.getByText('Booking berhasil disetujui.')).toBeVisible();

        // Admin keluar
        await page.getByRole('button', { name: 'Keluar' }).click();
        await page.getByRole('dialog').getByRole('button', { name: 'Keluar' }).click();
        await expect(page).toHaveURL(/\/$/);
        await expect(page.getByRole('heading', { name: 'Selamat datang kembali' })).toBeVisible();

        // Tahap 3: Karyawan masuk kembali dan melihat status Disetujui di dashboard/riwayat
        await page.getByLabel('Email').fill(employeeUser.email);
        await page.getByLabel('Password').fill('Karyawan123!');
        await page.getByRole('button', { name: 'Masuk' }).click();

        await expect(page).toHaveURL(/\/dashboard$/);
        await expect(page.getByText('Dashboard karyawan')).toBeVisible();
        await page.getByRole('link', { name: 'Riwayat Booking' }).click();
        await expect(page).toHaveURL(/\/riwayat$/);
        await expect(page.getByRole('heading', { name: 'Rapat Koordinasi Produk' })).toBeVisible();
        await expect(page.locator('#riwayat-booking span', { hasText: 'Disetujui' })).toBeVisible();
    });

    test('4. Respons 401 saat sesi berakhir menghapus token dari localStorage dan mengarahkan ke form login', async ({ page }) => {
        await setupMockApi(page);

        await page.goto('/');
        await page.getByLabel('Email').fill(employeeUser.email);
        await page.getByLabel('Password').fill('Karyawan123!');
        await page.getByRole('button', { name: 'Masuk' }).click();
        await expect(page).toHaveURL(/\/dashboard$/);

        // Simulasikan token kedaluwarsa saat pengguna hendak mengajukan booking
        await page.evaluate(() => {
            window.localStorage.setItem('meetspace_auth_token', 'expired-invalid-token');
        });

        await page.getByRole('button', { name: 'Booking Cepat' }).click();
        const modal = page.getByRole('dialog');
        await modal.getByLabel('Pilih ruangan').selectOption('1');
        await modal.getByLabel('Nama agenda').fill('Agenda Sesi Kedaluwarsa');
        await modal.getByLabel('Jumlah peserta').fill('4');
        await modal.getByLabel('Tanggal').fill('2026-11-02');
        await modal.getByLabel('Mulai').fill('10:00');
        await modal.getByLabel('Selesai').fill('11:00');
        await modal.getByRole('button', { name: 'Ajukan booking' }).click();

        await expect(page).toHaveURL(/\/$/);
        await expect(page.getByRole('heading', { name: 'Selamat datang kembali' })).toBeVisible();

        const tokenAfter401 = await page.evaluate(() => window.localStorage.getItem('meetspace_auth_token'));
        expect(tokenAfter401).toBeNull();
    });
});
