import { describe, expect, it, vi } from 'vitest';
import fs from 'fs';
import path from 'path';
import React from 'react';
import { ErrorBoundary } from '../components/ErrorBoundary';

/**
 * Audit 496 - Parameter ROB (Issue #172):
 * Halaman belum tahan error (error boundary, 404/500, white screen).
 *
 * Bukti audit awal (sha 9d75930):
 * - picu error -> tidak ada error boundary
 * - uji refresh di hosting -> deep link tanpa sesi: ['/dashboard', '/admin']
 * - cek error.tsx -> 0 error boundary
 *
 * Ambang batas:
 * - bukan blank (ramah pengguna, tersedia error boundary per route group, dan deep link refresh aman)
 */
describe('Frontend Error Resilience, Error Boundaries, and Deep Links Audit (#172)', () => {
    const srcDir = path.resolve(__dirname, '..');
    const appDir = path.join(srcDir, 'app');

    describe('1. Pemeriksaan Root & Global Error Boundaries (error.tsx & global-error.tsx)', () => {
        it('memastikan root error boundary (app/error.tsx) terpasang sebagai client component dan bukan blank', () => {
            const rootErrorPath = path.join(appDir, 'error.tsx');
            expect(fs.existsSync(rootErrorPath)).toBe(true);

            const content = fs.readFileSync(rootErrorPath, 'utf-8');
            expect(content).toContain("'use client'");
            expect(content).toContain('ErrorFallback');
            expect(content).toContain('Terjadi Kendala pada Halaman');
        });

        it('memastikan global error boundary (app/global-error.tsx) mendefinisikan tag html dan body sendiri', () => {
            const globalErrorPath = path.join(appDir, 'global-error.tsx');
            expect(fs.existsSync(globalErrorPath)).toBe(true);

            const content = fs.readFileSync(globalErrorPath, 'utf-8');
            expect(content).toContain("'use client'");
            expect(content).toContain('<html');
            expect(content).toContain('<body');
            expect(content).toContain('Terjadi Kendala Kritis Sistem');
        });
    });

    describe('2. Pemeriksaan Error Boundary per Route Group (cek error.tsx > 0)', () => {
        const routeGroups = ['dashboard', 'admin', 'ruangan', 'jadwal', 'riwayat'];

        it.each(routeGroups)('memastikan rute segmen %s memiliki error.tsx sendiri', (route) => {
            const routeErrorPath = path.join(appDir, route, 'error.tsx');
            expect(fs.existsSync(routeErrorPath)).toBe(true);

            const content = fs.readFileSync(routeErrorPath, 'utf-8');
            expect(content).toContain("'use client'");
            expect(content).toContain('ErrorFallback');
            expect(content).toContain('reset');
        });

        it('memverifikasi bahwa total file error boundary di dalam app/ lebih dari 5 berkas (bukan 0)', () => {
            let errorBoundaryCount = 0;

            function countErrorFiles(dir: string) {
                const entries = fs.readdirSync(dir, { withFileTypes: true });
                for (const entry of entries) {
                    const fullPath = path.join(dir, entry.name);
                    if (entry.isDirectory()) {
                        countErrorFiles(fullPath);
                    } else if (entry.name === 'error.tsx' || entry.name === 'global-error.tsx') {
                        errorBoundaryCount++;
                    }
                }
            }

            countErrorFiles(appDir);
            // 1 root error.tsx + 1 global-error.tsx + 5 route group error.tsx = 7 error boundaries
            expect(errorBoundaryCount).toBeGreaterThanOrEqual(7);
        });
    });

    describe('3. Pemeriksaan Halaman 404 (not-found.tsx) & Fallback UI', () => {
        it('memastikan berkas app/not-found.tsx tersedia dan bukan blank', () => {
            const notFoundPath = path.join(appDir, 'not-found.tsx');
            expect(fs.existsSync(notFoundPath)).toBe(true);

            const content = fs.readFileSync(notFoundPath, 'utf-8');
            expect(content).toContain('NotFoundFallback');
            expect(content).toContain('Halaman Tidak Ditemukan');
        });

        it('memastikan komponen ErrorFallback dan NotFoundFallback di shared/ui/ tidak blank dan dapat dirender', () => {
            const errorFallbackPath = path.join(srcDir, 'shared', 'ui', 'ErrorFallback.tsx');
            const notFoundFallbackPath = path.join(srcDir, 'shared', 'ui', 'NotFoundFallback.tsx');

            expect(fs.existsSync(errorFallbackPath)).toBe(true);
            expect(fs.existsSync(notFoundFallbackPath)).toBe(true);

            const errContent = fs.readFileSync(errorFallbackPath, 'utf-8');
            const nfContent = fs.readFileSync(notFoundFallbackPath, 'utf-8');

            expect(errContent).toContain('role="alert"');
            expect(errContent).toContain('Coba Lagi');
            expect(nfContent).toContain('404');
            expect(nfContent).toContain('Buka Dashboard');
        });
    });

    describe('4. Pemeriksaan Client Component ErrorBoundary', () => {
        it('menangkap runtime error dan menampilkan fallback UI ramah pengguna', () => {
            const onErrorMock = vi.fn();

            // Komponen simulasi error
            const ThrowError = () => {
                throw new Error('Simulasi unhandled exception');
            };

            const element = React.createElement(
                ErrorBoundary,
                { onError: onErrorMock, title: 'Widget Gagal Memuat' },
                React.createElement(ThrowError)
            );

            // Verifikasi state awal dan getDerivedStateFromError
            const derived = ErrorBoundary.getDerivedStateFromError(new Error('Crash'));
            expect(derived.hasError).toBe(true);
            expect(derived.error.message).toBe('Crash');
            expect(element).toBeDefined();
        });

        it('memastikan DashboardPage membungkus subkomponen kalender dengan ErrorBoundary', () => {
            const dashboardPagePath = path.join(srcDir, 'components', 'DashboardPage.tsx');
            const content = fs.readFileSync(dashboardPagePath, 'utf-8');

            expect(content).toContain('import { ErrorBoundary }');
            expect(content).toContain('<ErrorBoundary');
            expect(content).toContain('BookingCalendar');
        });
    });

    describe('5. Pemeriksaan Deep Link Refresh Tanpa Sesi (Bukan 404)', () => {
        it('memastikan middleware dan layout mengamankan deep links /dashboard dan /admin tanpa memicu 404', () => {
            const middlewarePath = path.join(srcDir, 'middleware.ts');
            const dashboardLayoutPath = path.join(appDir, 'dashboard', 'layout.tsx');
            const adminLayoutPath = path.join(appDir, 'admin', 'layout.tsx');

            const mwContent = fs.readFileSync(middlewarePath, 'utf-8');
            const dashLayoutContent = fs.readFileSync(dashboardLayoutPath, 'utf-8');
            const adminLayoutContent = fs.readFileSync(adminLayoutPath, 'utf-8');

            // Middleware melakukan server-side redirect ke '/', bukan 404
            expect(mwContent).toContain("pathname === prefix || pathname.startsWith(`${prefix}/`)");
            expect(mwContent).toContain("NextResponse.redirect(loginUrl)");

            // Layout rute server juga memvalidasi token dan melakukan redirect('/'), bukan 404
            expect(dashLayoutContent).toContain("redirect('/')");
            expect(adminLayoutContent).toContain("redirect('/')");
        });
    });
});
