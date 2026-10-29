import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import {
    approveBooking,
    clearToken,
    createBooking,
    getBookings,
    getCurrentUser,
    getErrorMessage,
    getRooms,
    login,
    rejectBooking,
    saveToken,
} from '@/services/api';

function createMockWindow() {
    const store = new Map<string, string>();
    const replaceMock = vi.fn();

    const mockWindow = {
        localStorage: {
            getItem: vi.fn((key: string) => store.get(key) ?? null),
            setItem: vi.fn((key: string, value: string) => {
                store.set(key, value);
            }),
            removeItem: vi.fn((key: string) => {
                store.delete(key);
            }),
            clear: vi.fn(() => {
                store.clear();
            }),
        },
        location: {
            replace: replaceMock,
        },
    };

    vi.stubGlobal('window', mockWindow);
    return { store, replaceMock, mockWindow };
}

function jsonResponse(body: unknown, status = 200): Response {
    return new Response(JSON.stringify(body), {
        status,
        headers: { 'Content-Type': 'application/json' },
    });
}

describe('services/api', () => {
    beforeEach(() => {
        createMockWindow();
    });

    afterEach(() => {
        vi.restoreAllMocks();
        vi.unstubAllGlobals();
    });

    it('berhasil login, menyimpan token, dan mengambil profil pengguna dengan Bearer token', async () => {
        const mockUser = {
            id: 1,
            name: 'Budi Karyawan',
            email: 'budi@perusahaan.com',
            role: 'karyawan' as const,
            status: 'active' as const,
        };

        const fetchMock = vi
            .fn()
            .mockResolvedValueOnce(jsonResponse({ token: 'secret-jwt-token', user: mockUser }, 200))
            .mockResolvedValueOnce(jsonResponse(mockUser, 200));
        vi.stubGlobal('fetch', fetchMock);

        const loginResult = await login('budi@perusahaan.com', 'Password123!');
        expect(loginResult).toEqual({ token: 'secret-jwt-token', user: mockUser });

        saveToken(loginResult.token);
        const me = await getCurrentUser();
        expect(me).toEqual(mockUser);

        expect(fetchMock).toHaveBeenNthCalledWith(
            2,
            expect.stringMatching(/\/api\/me$/),
            expect.objectContaining({
                headers: {
                    Accept: 'application/json',
                    Authorization: 'Bearer secret-jwt-token',
                },
            })
        );
    });

    it('menangani respons 401 dengan menghapus token dan me-redirect ke halaman login (/)', async () => {
        const { store, replaceMock } = createMockWindow();
        saveToken('expired-token-123');
        expect(store.get('meetspace_auth_token')).toBe('expired-token-123');

        const fetchMock = vi.fn().mockResolvedValueOnce(
            jsonResponse(
                {
                    success: false,
                    status: false,
                    message: 'Autentikasi diperlukan.',
                    data: null,
                    errors: [],
                },
                401
            )
        );
        vi.stubGlobal('fetch', fetchMock);

        await expect(getCurrentUser()).rejects.toThrow('Sesi login berakhir. Silakan masuk kembali.');

        expect(store.has('meetspace_auth_token')).toBe(false);
        expect(replaceMock).toHaveBeenCalledTimes(1);
        expect(replaceMock).toHaveBeenCalledWith('/');
    });

    it('memformat error envelope standar (success/status/message/errors) saat validasi gagal', async () => {
        saveToken('valid-token');
        const errorEnvelope = {
            success: false,
            status: false,
            message: 'Validasi gagal.',
            data: null,
            errors: [
                { field: 'room_id', message: 'Ruangan wajib dipilih.' },
                { field: 'start_time', message: 'Jam mulai tidak valid.' },
            ],
        };

        vi.stubGlobal('fetch', vi.fn().mockResolvedValueOnce(jsonResponse(errorEnvelope, 422)));

        await expect(
            createBooking({
                room_id: 0,
                title: 'Rapat Tim',
                participant_count: 5,
                start_time: '2026-11-02T09:00:00+07:00',
                end_time: '2026-11-02T10:00:00+07:00',
            })
        ).rejects.toThrow('Validasi gagal. (Ruangan wajib dipilih., Jam mulai tidak valid.)');
    });

    it('mengekstrak pesan dari errors envelope jika message utama kosong atau memakai fallback saat body rusak', async () => {
        const onlyErrorsResponse = jsonResponse(
            {
                success: false,
                status: false,
                message: '',
                data: null,
                errors: [{ field: 'approval_notes', message: 'Alasan penolakan minimal 10 karakter.' }],
            },
            422
        );
        await expect(getErrorMessage(onlyErrorsResponse, 'Fallback gagal')).resolves.toBe(
            'Alasan penolakan minimal 10 karakter.'
        );

        const nonJsonResponse = new Response('Internal Server Error', { status: 500 });
        await expect(getErrorMessage(nonJsonResponse, 'Terjadi kesalahan server')).resolves.toBe(
            'Terjadi kesalahan server'
        );
    });

    it('mengambil seluruh halaman pagination pada getBookings dan membuka envelope data pada create/approve/reject', async () => {
        saveToken('admin-token');
        const bookingPage1 = {
            id: 10,
            room_id: 1,
            user_id: 2,
            title: 'Sprint Planning',
            participant_count: 6,
            start_time: '2026-11-02T09:00:00+07:00',
            end_time: '2026-11-02T10:00:00+07:00',
            status: 'pending' as const,
        };
        const bookingPage2 = {
            ...bookingPage1,
            id: 11,
            title: 'Design Review',
        };

        const fetchMock = vi
            .fn()
            .mockResolvedValueOnce(
                jsonResponse({
                    success: true,
                    status: true,
                    message: 'Data booking berhasil dimuat.',
                    data: [bookingPage1],
                    meta: { current_page: 1, last_page: 2, per_page: 100, total: 2 },
                    errors: [],
                })
            )
            .mockResolvedValueOnce(
                jsonResponse({
                    success: true,
                    status: true,
                    message: 'Data booking berhasil dimuat.',
                    data: [bookingPage2],
                    meta: { current_page: 2, last_page: 2, per_page: 100, total: 2 },
                    errors: [],
                })
            )
            .mockResolvedValueOnce(
                jsonResponse({
                    status: true,
                    message: 'Operasi booking berhasil.',
                    data: { ...bookingPage1, status: 'approved' },
                    errors: [],
                })
            )
            .mockResolvedValueOnce(
                jsonResponse({
                    status: true,
                    message: 'Operasi booking berhasil.',
                    data: { ...bookingPage2, status: 'rejected', approval_notes: 'Ruangan dipakai direksi' },
                    errors: [],
                })
            );
        vi.stubGlobal('fetch', fetchMock);

        const allBookings = await getBookings();
        expect(allBookings).toHaveLength(2);
        expect(allBookings.map((b) => b.title)).toEqual(['Sprint Planning', 'Design Review']);

        const approved = await approveBooking(10);
        expect(approved.status).toBe('approved');

        const rejected = await rejectBooking(11, 'Ruangan dipakai direksi');
        expect(rejected.status).toBe('rejected');
        expect(rejected.approval_notes).toBe('Ruangan dipakai direksi');

        clearToken();
    });

    it('menolak respons daftar API jika struktur atau metadata pagination tidak valid', async () => {
        saveToken('valid-token');
        const fetchMock = vi.fn().mockResolvedValueOnce(
            jsonResponse({
                success: true,
                data: [],
                meta: { current_page: 1, last_page: 500 },
            })
        );
        vi.stubGlobal('fetch', fetchMock);

        await expect(getRooms()).rejects.toThrow('Metadata pagination API tidak valid atau terlalu besar.');
    });
});

