import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import * as fs from 'fs';
import * as path from 'path';
import {
    apiClient,
    clearApiCache,
    clearToken,
    createBooking,
    createRoom,
    getApiCache,
    getBookings,
    getRooms,
    login,
    saveToken,
    setApiCache,
} from './index';

function jsonResponse(body: unknown, status = 200): Response {
    return new Response(JSON.stringify(body), {
        status,
        headers: { 'Content-Type': 'application/json' },
    });
}

describe('Frontend Centralized API Client & Data Layer (Audit 496 - Issue #164)', () => {
    beforeEach(() => {
        clearApiCache();
        vi.stubGlobal('window', {
            localStorage: {
                getItem: vi.fn(),
                setItem: vi.fn(),
                removeItem: vi.fn(),
            },
            location: {
                replace: vi.fn(),
            },
        });
    });

    afterEach(() => {
        vi.restoreAllMocks();
        vi.unstubAllGlobals();
        clearApiCache();
    });

    it('memastikan 0 pemanggilan fetch di luar modul api/', () => {
        const srcDir = path.resolve(__dirname, '..');
        const violations: string[] = [];

        function scanDir(dir: string) {
            const files = fs.readdirSync(dir, { withFileTypes: true });
            for (const file of files) {
                const fullPath = path.join(dir, file.name);
                if (file.isDirectory()) {
                    if (file.name === 'node_modules' || file.name === '.next') continue;
                    scanDir(fullPath);
                } else if (/\.(tsx?|jsx?)$/.test(file.name)) {
                    // Kecualikan modul di dalam folder api/ dan file test
                    const normalized = fullPath.replace(/\\/g, '/');
                    if (normalized.includes('/src/api/')) continue;
                    if (normalized.endsWith('.test.ts') || normalized.endsWith('.test.tsx')) continue;

                    const content = fs.readFileSync(fullPath, 'utf8');
                    // Cari pemanggilan fetch( yang bukan bagian dari nama fungsi/properti lain
                    if (/(?<![\w$.])fetch\s*\(/.test(content)) {
                        violations.push(normalized);
                    }
                }
            }
        }

        scanDir(srcDir);
        expect(violations).toEqual([]);
    });

    it('menginvalidasi cache secara otomatis setelah mutasi POST/PUT/DELETE', async () => {
        const initialRooms = [
            { id: 1, name: 'Ruang Saturnus', capacity: 10, location: 'Lt 1', is_active: true },
        ];
        const newRoom = { id: 2, name: 'Ruang Jupiter Baru', capacity: 20, location: 'Lt 2', is_active: true };

        // 1. Panggilan GET pertama
        const fetchMock = vi
            .fn()
            .mockResolvedValueOnce(jsonResponse({ data: initialRooms, meta: { current_page: 1, last_page: 1 } }))
            // 2. Panggilan POST create
            .mockResolvedValueOnce(jsonResponse({ data: newRoom }, 201))
            // 3. Panggilan GET kedua setelah mutasi
            .mockResolvedValueOnce(jsonResponse({ data: [...initialRooms, newRoom], meta: { current_page: 1, last_page: 1 } }));

        vi.stubGlobal('fetch', fetchMock);

        const rooms1 = await getRooms();
        expect(rooms1).toHaveLength(1);
        expect(rooms1[0].name).toBe('Ruang Saturnus');

        // Lakukan mutasi createRoom
        const created = await createRoom({
            name: 'Ruang Jupiter Baru',
            capacity: 20,
            location: 'Lt 2',
            is_active: true,
        });
        expect(created.name).toBe('Ruang Jupiter Baru');

        // Panggilan GET berikutnya harus mendapatkan data segar (tidak basi)
        const rooms2 = await getRooms();
        expect(rooms2).toHaveLength(2);
        expect(rooms2[1].name).toBe('Ruang Jupiter Baru');
        expect(fetchMock).toHaveBeenCalledTimes(3);
    });

    it('mencegah race condition saat navigasi beruntun dengan membatalkan request usang', async () => {
        let abortSignalCaught = false;

        const fetchMock = vi.fn().mockImplementation((_url: string, init?: RequestInit) => {
            const signal = init?.signal;
            if (signal) {
                signal.addEventListener('abort', () => {
                    abortSignalCaught = true;
                });
            }
            return new Promise((resolve) => {
                setTimeout(() => {
                    resolve(jsonResponse({ data: [{ id: 1 }], meta: { current_page: 1, last_page: 1 } }));
                }, 50);
            });
        });

        vi.stubGlobal('fetch', fetchMock);

        // Dua request berturut-turut pada endpoint yang sama dengan opsi preventRace
        const req1 = apiClient('/v1/categories', { preventRace: true, method: 'GET' }).catch((err) => err);
        const req2 = apiClient('/v1/categories', { preventRace: true, method: 'GET' });

        await Promise.all([req1, req2]);

        // Request pertama harus ter-abort
        expect(abortSignalCaught).toBe(true);
    });

    it('menghapus token dan cache saat menerima respons 401 Unauthorized', async () => {
        saveToken('token-lama');
        setApiCache('test-key', { foo: 'bar' });

        const fetchMock = vi.fn().mockResolvedValueOnce(
            jsonResponse({ message: 'Token telah kedaluwarsa' }, 401)
        );
        vi.stubGlobal('fetch', fetchMock);

        await expect(apiClient('/v1/bookings')).rejects.toThrow('Sesi login berakhir');
        expect(getApiCache('test-key')).toBeNull();
    });
});
