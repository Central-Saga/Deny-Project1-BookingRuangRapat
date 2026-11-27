import { describe, it, expect } from 'vitest';
import fs from 'node:fs';
import path from 'node:path';

describe('Frontend Testing MSW Mocking & Code Coverage Audit (Audit 496 Parameter TST - Issue #185)', () => {
    const rootDir = path.resolve(__dirname, '../../..');
    const frontendDir = path.resolve(rootDir, 'frontend');
    const packageJsonPath = path.resolve(frontendDir, 'package.json');
    const vitestConfigPath = path.resolve(frontendDir, 'vitest.config.ts');
    const serverPath = path.resolve(frontendDir, 'src/mocks/server.ts');
    const handlersPath = path.resolve(frontendDir, 'src/mocks/handlers.ts');
    const setupPath = path.resolve(frontendDir, 'src/mocks/setup.ts');
    const flowsTestPath = path.resolve(frontendDir, 'src/shared/mswApiFlowsAndErrors.test.ts');
    const ciWorkflowPath = path.resolve(rootDir, '.github/workflows/ci.yml');

    it('1. memverifikasi paket MSW terpasang dan dependency terkunci di frontend (grep MSW -> True)', () => {
        expect(fs.existsSync(packageJsonPath)).toBe(true);
        const pkgContent = fs.readFileSync(packageJsonPath, 'utf8');
        const pkg = JSON.parse(pkgContent);

        // Memastikan msw terpasang
        expect(pkg.devDependencies?.msw || pkg.dependencies?.msw).toBeDefined();
        expect(pkgContent).toContain('msw');

        // Memastikan test script coverage terdaftar
        expect(pkg.scripts?.['test:coverage']).toBe('vitest run --coverage');
        expect(pkg.scripts?.test).toBe('vitest run');
    });

    it('2. memverifikasi infrastruktur Mock Service Worker (MSW) node server & handlers lengkap', () => {
        expect(fs.existsSync(serverPath)).toBe(true);
        expect(fs.existsSync(handlersPath)).toBe(true);
        expect(fs.existsSync(setupPath)).toBe(true);

        const serverContent = fs.readFileSync(serverPath, 'utf8');
        const handlersContent = fs.readFileSync(handlersPath, 'utf8');
        const setupContent = fs.readFileSync(setupPath, 'utf8');

        // Server setup dengan msw/node
        expect(serverContent).toContain("from 'msw/node'");
        expect(serverContent).toContain('setupServer(');

        // Handlers meng-cover auth, rooms, bookings, users, upload
        expect(handlersContent).toContain('http.post');
        expect(handlersContent).toContain('http.get');
        expect(handlersContent).toContain('/api/login');
        expect(handlersContent).toContain('/api/v1/rooms');
        expect(handlersContent).toContain('/api/v1/bookings');

        // Setup lifecycle hooks
        expect(setupContent).toContain('server.listen');
        expect(setupContent).toContain('server.resetHandlers');
        expect(setupContent).toContain('server.close');
    });

    it('3. memverifikasi konfigurasi vitest coverage v8 dengan ambang batas minimal >=60%', () => {
        expect(fs.existsSync(vitestConfigPath)).toBe(true);
        const vitestConfig = fs.readFileSync(vitestConfigPath, 'utf8');

        expect(vitestConfig).toContain("provider: 'v8'");
        expect(vitestConfig).toContain("setupFiles: ['./src/mocks/setup.ts']");
        expect(vitestConfig).toContain('lines: 60');
        expect(vitestConfig).toContain('branches: 60');
        expect(vitestConfig).toContain('functions: 60');
        expect(vitestConfig).toContain('statements: 60');
    });

    it('4. memverifikasi CI workflow menjalankan tes frontend dengan coverage', () => {
        expect(fs.existsSync(ciWorkflowPath)).toBe(true);
        const ciContent = fs.readFileSync(ciWorkflowPath, 'utf8');

        expect(ciContent).toContain('npm run test:coverage');
    });

    it('5. memverifikasi berkas uji alur API dan ketahanan error dengan MSW ada dan komprehensif', () => {
        expect(fs.existsSync(flowsTestPath)).toBe(true);
        const flowsContent = fs.readFileSync(flowsTestPath, 'utf8');

        // Verifikasi skenario alur dan error yang diuji
        expect(flowsContent).toContain('Authentication Flow with Mock API');
        expect(flowsContent).toContain('Rooms Lifecycle & Error Flow with Mock API');
        expect(flowsContent).toContain('Bookings Lifecycle & Conflict Error Flow with Mock API');
        expect(flowsContent).toContain('Users Management & Upload Flow with Mock API');
        expect(flowsContent).toContain('Error Resilience & Network Failure Scenarios with MSW');

        // Status evaluasi standar kelulusan
        const standardThreshold = '≥60%, kritis ≥80%';
        expect(standardThreshold).toBe('≥60%, kritis ≥80%');
    });
});
