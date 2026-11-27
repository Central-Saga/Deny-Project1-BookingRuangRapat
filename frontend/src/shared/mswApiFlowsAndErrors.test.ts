import { describe, it, expect, beforeEach } from 'vitest';
import { http, HttpResponse } from 'msw';
import { server } from '../mocks/server';
import {
    login,
    getCurrentUser,
    logout,
    getRooms,
    createRoom,
    updateRoom,
    deleteRoom,
    getBookings,
    getAvailability,
    createBooking,
    updateBooking,
    approveBooking,
    rejectBooking,
    cancelBooking,
    deleteBooking,
    getUsers,
    createUser,
    updateUser,
    uploadFile,
    ApiError,
    saveToken,
    clearToken,
    hasToken,
    clearApiCache,
} from '../api/index';

describe('Frontend API Flows & Error Resilience with MSW (Issue #185)', () => {
    beforeEach(() => {
        clearToken();
        clearApiCache();
    });

    describe('1. Authentication Flow with Mock API', () => {
        it('berhasil login dan menyimpan token autentikasi (happy path)', async () => {
            const res = await login('admin@example.com', 'Password123!');
            expect(res).toBeDefined();
            expect(res.token).toBe('mock-jwt-admin-token-12345');
            expect(res.user.role).toBe('admin');

            // Simpan token untuk request berikutnya
            saveToken(res.token);
            expect(hasToken()).toBe(true);

            // Fetch current user profil
            const me = await getCurrentUser();
            expect(me.name).toBe('Admin Facility');
            expect(me.role).toBe('admin');
        });

        it('menolak login dengan kredensial salah (401 Unauthenticated)', async () => {
            await expect(login('wrong@example.com', 'WrongPass!')).rejects.toThrow();
        });

        it('menolak login dengan payload kosong dan menghasilkan error validasi 422', async () => {
            try {
                await login('', '');
                expect.unreachable('Harus melempar error 422');
            } catch (err: unknown) {
                expect(err).toBeInstanceOf(ApiError);
                if (err instanceof ApiError) {
                    expect(err.status).toBe(422);
                    expect(err.fieldErrors.email).toBeDefined();
                    expect(err.fieldErrors.password).toBeDefined();
                }
            }
        });

        it('berhasil logout dan menghapus token dari sesi', async () => {
            saveToken('mock-jwt-admin-token-12345');
            expect(hasToken()).toBe(true);

            await logout();
            expect(hasToken()).toBe(false);
        });

        it('gagal fetch /me saat tidak memiliki token autentikasi (401)', async () => {
            clearToken();
            await expect(getCurrentUser()).rejects.toThrow();
        });
    });

    describe('2. Rooms Lifecycle & Error Flow with Mock API', () => {
        beforeEach(() => {
            saveToken('mock-jwt-admin-token-12345');
        });

        it('mengambil daftar seluruh ruangan rapat (getRooms)', async () => {
            const rooms = await getRooms();
            expect(rooms).toBeInstanceOf(Array);
            expect(rooms.length).toBeGreaterThanOrEqual(2);
            expect(rooms[0].name).toBe('Ruang Rinjani');
            expect(rooms[0].capacity).toBe(12);
        });

        it('berhasil membuat ruangan baru (createRoom - 201 Created)', async () => {
            const newRoom = await createRoom({
                name: 'Ruang Bromo',
                capacity: 15,
                location: 'Lantai 2',
                facilities: ['Proyektor', 'AC'],
                is_active: true,
            });

            expect(newRoom).toBeDefined();
            expect(newRoom.name).toBe('Ruang Bromo');
            expect(newRoom.capacity).toBe(15);
        });

        it('menolak pembuatan ruangan dengan kapasitas 0 atau negatif (422 Validation)', async () => {
            try {
                await createRoom({
                    name: 'Ruang Negatif',
                    capacity: 0,
                    location: 'Lantai 1',
                    is_active: true,
                });
                expect.unreachable('Harus melempar error validasi 422');
            } catch (err: unknown) {
                expect(err).toBeInstanceOf(ApiError);
                if (err instanceof ApiError) {
                    expect(err.status).toBe(422);
                    expect(err.fieldErrors.capacity).toBe('Kapasitas ruangan harus minimal 1 orang.');
                }
            }
        });

        it('berhasil memperbarui ruangan (updateRoom)', async () => {
            const updated = await updateRoom(1, {
                name: 'Ruang Rinjani VIP',
                capacity: 14,
                location: 'Lantai 2 Gedung A',
                is_active: true,
            });

            expect(updated.name).toBe('Ruang Rinjani VIP');
            expect(updated.capacity).toBe(14);
        });

        it('berhasil menonaktifkan ruangan (deleteRoom)', async () => {
            await expect(deleteRoom(1)).resolves.not.toThrow();
        });

        it('menangani 404 saat ruangan tidak ditemukan', async () => {
            await expect(updateRoom(999, {
                name: 'Ruang Hantu',
                capacity: 10,
                location: 'Void',
                is_active: true,
            })).rejects.toThrow();
        });
    });

    describe('3. Bookings Lifecycle & Conflict Error Flow with Mock API', () => {
        beforeEach(() => {
            saveToken('mock-jwt-employee-token-67890');
        });

        it('mengambil daftar ketersediaan ruangan (getAvailability)', async () => {
            const slots = await getAvailability();
            expect(slots).toBeInstanceOf(Array);
            expect(slots.length).toBeGreaterThan(0);
            expect(slots[0].room_id).toBe(1);
        });

        it('mengambil riwayat seluruh booking (getBookings)', async () => {
            const bookings = await getBookings();
            expect(bookings).toBeInstanceOf(Array);
            expect(bookings.length).toBeGreaterThan(0);
            expect(bookings[0].title).toBe('Sprint Planning Q1');
        });

        it('berhasil membuat booking baru pada jadwal kosong (201 Created)', async () => {
            const booking = await createBooking({
                room_id: 2,
                title: 'Review Sprint Bersama Klien',
                participant_count: 10,
                start_time: '2026-12-05 13:00:00',
                end_time: '2026-12-05 15:00:00',
                notes: 'Mohon siapkan proyektor 4K',
            });

            expect(booking).toBeDefined();
            expect(booking.title).toBe('Review Sprint Bersama Klien');
            expect(booking.status).toBe('pending');
        });

        it('menolak booking yang bentrok jadwal (409 Conflict)', async () => {
            try {
                await createBooking({
                    room_id: 1,
                    title: 'Bentrok Meeting',
                    participant_count: 5,
                    start_time: '2026-12-01 09:00:00',
                    end_time: '2026-12-01 11:00:00',
                });
                expect.unreachable('Harus melempar error konflik 409');
            } catch (err: unknown) {
                expect(err).toBeInstanceOf(ApiError);
                if (err instanceof ApiError) {
                    expect(err.status).toBe(409);
                    expect(err.message).toContain('sudah dibooking');
                }
            }
        });

        it('menolak booking dengan jumlah peserta melebihi kapasitas (422 Validation)', async () => {
            try {
                await createBooking({
                    room_id: 1,
                    title: 'Peserta Membludak',
                    participant_count: 150,
                    start_time: '2026-12-10 10:00:00',
                    end_time: '2026-12-10 12:00:00',
                });
                expect.unreachable('Harus melempar error 422');
            } catch (err: unknown) {
                expect(err).toBeInstanceOf(ApiError);
                if (err instanceof ApiError) {
                    expect(err.status).toBe(422);
                    expect(err.fieldErrors.participant_count).toBeDefined();
                }
            }
        });

        it('berhasil update booking (updateBooking)', async () => {
            const updated = await updateBooking(101, {
                room_id: 1,
                title: 'Sprint Planning Q1 - Extended',
                participant_count: 10,
                start_time: '2026-12-01 09:00:00',
                end_time: '2026-12-01 12:00:00',
            });
            expect(updated.title).toBe('Sprint Planning Q1 - Extended');
        });

        it('admin dapat menyetujui booking (approveBooking)', async () => {
            const approved = await approveBooking(101);
            expect(approved.status).toBe('approved');
        });

        it('admin dapat menolak booking dengan alasan (rejectBooking)', async () => {
            const rejected = await rejectBooking(101, 'Ruangan sedang dalam perbaikan AC');
            expect(rejected.status).toBe('rejected');
            expect(rejected.notes).toBe('Ruangan sedang dalam perbaikan AC');
        });

        it('user dapat membatalkan booking (cancelBooking)', async () => {
            await expect(cancelBooking(101, 'Meeting dipindah online')).resolves.not.toThrow();
        });

        it('user dapat menghapus booking (deleteBooking)', async () => {
            await expect(deleteBooking(101)).resolves.not.toThrow();
        });
    });

    describe('4. Users Management & Upload Flow with Mock API', () => {
        beforeEach(() => {
            saveToken('mock-jwt-admin-token-12345');
        });

        it('mengambil daftar seluruh pengguna (getUsers)', async () => {
            const users = await getUsers();
            expect(users).toBeInstanceOf(Array);
            expect(users.length).toBeGreaterThanOrEqual(2);
        });

        it('berhasil membuat pengguna baru (createUser)', async () => {
            const newUser = await createUser({
                name: 'Dewi Lestari',
                email: 'dewi@example.com',
                password: 'Password123!',
                role: 'karyawan',
                status: 'active',
            });
            expect(newUser.name).toBe('Dewi Lestari');
            expect(newUser.role).toBe('karyawan');
        });

        it('berhasil memperbarui status dan role pengguna (updateUser)', async () => {
            const updated = await updateUser(2, {
                role: 'admin',
                status: 'inactive',
            });
            expect(updated.role).toBe('admin');
            expect(updated.status).toBe('inactive');
        });

        it('berhasil mengunggah berkas gambar valid (uploadFile)', async () => {
            const file = new File(['mock content'], 'banner.jpg', { type: 'image/jpeg' });
            const result = await uploadFile(file);
            expect(result.url).toBe('http://localhost:8000/storage/uploads/2026/room-banner.jpg');
        });
    });

    describe('5. Error Resilience & Network Failure Scenarios with MSW', () => {
        it('menangani respons server error 500 dengan pesan fallback yang aman', async () => {
            server.use(
                http.get('*/api/v1/rooms', () => {
                    return HttpResponse.json(
                        { status: false, message: 'Internal Server Error' },
                        { status: 500 }
                    );
                })
            );

            await expect(getRooms()).rejects.toThrow();
        });

        it('menangani respons 403 Forbidden ketika hak akses ditolak', async () => {
            server.use(
                http.post('*/api/v1/rooms', () => {
                    return HttpResponse.json(
                        { status: false, message: 'Akses ditolak. Peran admin diperlukan.' },
                        { status: 403 }
                    );
                })
            );

            try {
                await createRoom({
                    name: 'Ruang Terlarang',
                    capacity: 5,
                    location: 'Lt 1',
                    is_active: true,
                });
                expect.unreachable('Harus melempar error 403');
            } catch (err: unknown) {
                expect(err).toBeInstanceOf(ApiError);
                if (err instanceof ApiError) {
                    expect(err.status).toBe(403);
                    expect(err.message).toContain('Akses ditolak');
                }
            }
        });

        it('menangani kegagalan jaringan (Network Error / connection refused)', async () => {
            server.use(
                http.get('*/api/v1/bookings', () => {
                    return HttpResponse.error();
                })
            );

            await expect(getBookings()).rejects.toThrow();
        });
    });
});
