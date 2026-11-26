import { describe, expect, it } from 'vitest';
import fs from 'fs';
import path from 'path';

/**
 * Audit 496 - Parameter OPT (Issue #170):
 * Respons API belum rapi (status code, pagination, filter, query).
 * - Ambang query per endpoint: ≤10.
 * - 0 get()/all() di batch jobs (app/Jobs/CancelExpiredBookingsJob.php).
 * - whenLoaded/whenCounted tanpa lazy load di Resource.
 * - Status code matrix CRUD konsisten.
 */
describe('API Optimization, Query Efficiency, and Resource Lazy Loading Audit (#170)', () => {
    const rootDir = path.resolve(__dirname, '../../..');
    const backendDir = path.join(rootDir, 'backend');

    it('memastikan batch jobs tidak menggunakan ->get() atau ->all() dan memanfaatkan cursor streaming', () => {
        const jobPath = path.join(backendDir, 'app', 'Jobs', 'CancelExpiredBookingsJob.php');
        expect(fs.existsSync(jobPath)).toBe(true);

        const content = fs.readFileSync(jobPath, 'utf-8');

        // Audit grep test: 0 get()/all() in batch jobs
        const getOrAllMatches = content.match(/->(get|all)\s*\(/g);
        expect(getOrAllMatches).toBeNull();

        // Menggunakan cursor untuk streaming efisien O(1)
        expect(content).toContain('->cursor()');
    });

    it('memastikan BookingResource menggunakan whenLoaded closure untuk mencegah lazy loading relasi', () => {
        const resourcePath = path.join(backendDir, 'app', 'Http', 'Resources', 'BookingResource.php');
        expect(fs.existsSync(resourcePath)).toBe(true);

        const content = fs.readFileSync(resourcePath, 'utf-8');

        // whenLoaded dengan closure fn () => new RoomResource(...)
        expect(content).toContain("whenLoaded('room', fn () => new RoomResource($this->room))");
        // Tidak membungkus MissingValue secara langsung
        expect(content).not.toContain("new RoomResource($this->whenLoaded('room'))");
    });

    it('memastikan rute queue failed-jobs konsisten dengan kontrak OpenAPI dan tidak memicu rute duplikat', () => {
        const routesPath = path.join(backendDir, 'routes', 'api.php');
        expect(fs.existsSync(routesPath)).toBe(true);

        const routesContent = fs.readFileSync(routesPath, 'utf-8');

        // Endpoint queue terpusat di /queue/failed-jobs
        expect(routesContent).toContain("Route::get('/queue/failed-jobs'");
        expect(routesContent).not.toContain("Route::get('/admin/queue/failed-jobs'");
    });
});
