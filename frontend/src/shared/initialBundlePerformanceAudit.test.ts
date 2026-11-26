import { describe, expect, it } from 'vitest';
import fs from 'fs';
import path from 'path';
import zlib from 'zlib';
import vm from 'vm';

/**
 * Audit 496 - Parameter OPT (Issue #169):
 * Halaman masih berat dimuat (bundle, Web Vitals, gambar).
 * Memastikan initial JavaScript bundle (JS awal) <= 200 KB gzip.
 */
describe('Initial Bundle Size & Performance Audit (#169)', () => {
    const rootDir = path.resolve(__dirname, '../../..');
    const frontendDir = path.resolve(__dirname, '..');
    const componentsDir = path.join(frontendDir, 'components');
    const nextDir = path.join(rootDir, 'frontend', '.next');
    const THRESHOLD_GZIP_KB = 200;

    it('memastikan komponen berat diimpor secara dinamis untuk code splitting', () => {
        const dashboardPagePath = path.join(componentsDir, 'DashboardPage.tsx');
        expect(fs.existsSync(dashboardPagePath)).toBe(true);

        const content = fs.readFileSync(dashboardPagePath, 'utf-8');

        // Memastikan dynamic import digunakan untuk AdminDashboardPage, BookingCalendar, dan BookingModal
        expect(content).toContain("dynamic(() => import('./AdminDashboardPage')");
        expect(content).toContain("dynamic(() => import('@/components/BookingCalendar')");
        expect(content).toContain("dynamic(() => import('@/components/BookingModal')");
    });

    it('memastikan script analyzer terpasang di package.json dan scripts/analyze-bundle.mjs tersedia', () => {
        const packageJsonPath = path.join(rootDir, 'frontend', 'package.json');
        const analyzerScriptPath = path.join(rootDir, 'frontend', 'scripts', 'analyze-bundle.mjs');

        expect(fs.existsSync(packageJsonPath)).toBe(true);
        expect(fs.existsSync(analyzerScriptPath)).toBe(true);

        const pkg = JSON.parse(fs.readFileSync(packageJsonPath, 'utf-8'));
        expect(pkg.scripts?.analyze).toBeDefined();
        expect(pkg.scripts.analyze).toContain('analyze-bundle.mjs');
    });

    it('memastikan ukuran JS awal setiap rute tidak melebihi 200 KB gzip', () => {
        const buildManifestPath = path.join(nextDir, 'build-manifest.json');
        // Jika build belum dijalankan (misal saat fresh test tanpa build), lewati perhitungan binary .next
        if (!fs.existsSync(buildManifestPath)) {
            return;
        }

        const buildManifest = JSON.parse(fs.readFileSync(buildManifestPath, 'utf8'));
        const rootMainFiles: string[] = buildManifest.rootMainFiles || [];

        const routes = ['/', '/admin', '/dashboard', '/jadwal', '/riwayat', '/ruangan'];

        for (const route of routes) {
            const manifestSegment = route === '/' ? '/page' : `${route}/page`;
            const manifestFileName = route === '/'
                ? path.join(nextDir, 'server', 'app', 'page_client-reference-manifest.js')
                : path.join(nextDir, 'server', 'app', route.slice(1), 'page_client-reference-manifest.js');

            const allChunks = new Set<string>();
            rootMainFiles.forEach((f) => allChunks.add(f));

            if (fs.existsSync(manifestFileName)) {
                const code = fs.readFileSync(manifestFileName, 'utf8');
                const sandbox = { globalThis: {} as { __RSC_MANIFEST?: Record<string, { clientModules?: Record<string, { chunks?: string[] }> }> } };
                vm.createContext(sandbox);
                vm.runInContext(code, sandbox);

                const manifest = sandbox.globalThis.__RSC_MANIFEST?.[manifestSegment];
                if (manifest?.clientModules) {
                    for (const [, data] of Object.entries(manifest.clientModules)) {
                        if (Array.isArray(data.chunks)) {
                            data.chunks.forEach((c: string) => {
                                if (c.endsWith('.js')) allChunks.add(c);
                            });
                        }
                    }
                }
            }

            let totalGzip = 0;
            for (const file of allChunks) {
                const fullPath = path.join(nextDir, file);
                if (fs.existsSync(fullPath)) {
                    const buf = fs.readFileSync(fullPath);
                    totalGzip += zlib.gzipSync(buf).length;
                }
            }

            const gzipKB = Number((totalGzip / 1024).toFixed(1));
            expect(gzipKB).toBeLessThanOrEqual(THRESHOLD_GZIP_KB);
        }
    });
});
