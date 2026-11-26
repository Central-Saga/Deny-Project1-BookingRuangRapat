import { describe, expect, it } from 'vitest';
import fs from 'fs';
import path from 'path';

/**
 * Audit 496 - Parameter MNT (Issue #168):
 * Tipe dan skema data frontend belum sinkron dengan API.
 * - Tidak ada as untuk memaksa respons (Ambang batas: 0 pada respons di API layer).
 * - Tidak ada duplikasi tipe backend — kontrak OpenAPI tersedia dan tersinkronisasi.
 */
describe('OpenAPI Contract & Frontend Types Synchronization Audit (#168)', () => {
    const rootDir = path.resolve(__dirname, '../../..');
    const apiDir = path.resolve(__dirname, '../api');
    const servicesDir = path.resolve(__dirname, '../services');
    const typesDir = path.resolve(__dirname, '../types');

    it('memastikan spesifikasi kontrak OpenAPI tersedia dan valid', () => {
        const rootOpenApi = path.join(rootDir, 'docs', 'openapi.yaml');
        const frontendOpenApi = path.join(rootDir, 'frontend', 'openapi.yaml');
        const openApiTypes = path.join(typesDir, 'openapi.ts');

        expect(fs.existsSync(rootOpenApi)).toBe(true);
        expect(fs.existsSync(frontendOpenApi)).toBe(true);
        expect(fs.existsSync(openApiTypes)).toBe(true);

        const yamlContent = fs.readFileSync(rootOpenApi, 'utf-8');
        expect(yamlContent).toContain('openapi: 3.1.0');
        expect(yamlContent).toContain('components:');
        expect(yamlContent).toContain('schemas:');
        expect(yamlContent).toContain('Room:');
        expect(yamlContent).toContain('User:');
        expect(yamlContent).toContain('Booking:');
    });

    it('memastikan tipe data frontend disinkronkan langsung dari kontrak OpenAPI', () => {
        const indexTypesPath = path.join(typesDir, 'index.ts');
        const typesContent = fs.readFileSync(indexTypesPath, 'utf-8');

        // Menghilangkan duplikasi manual dengan mengimpor langsung dari kontrak openapi
        expect(typesContent).toContain("from './openapi'");
        expect(typesContent).toContain("components['schemas']['User']");
        expect(typesContent).toContain("components['schemas']['Room']");
        expect(typesContent).toContain("components['schemas']['Booking']");
    });

    it('memastikan 0 pemaksaan tipe menggunakan as pada respons di seluruh api dan services layer', () => {
        const targetDirs = [apiDir, servicesDir];
        const responseCastPattern = /\)\s+as\s+[A-Za-z0-9_<>|]+/g;
        const asWordPattern = /\bas\b/g;

        const filesToScan: string[] = [];

        function collectFiles(dir: string) {
            if (!fs.existsSync(dir)) return;
            const entries = fs.readdirSync(dir, { withFileTypes: true });
            for (const entry of entries) {
                const fullPath = path.join(dir, entry.name);
                if (entry.isDirectory()) {
                    collectFiles(fullPath);
                } else if (entry.isFile() && (entry.name.endsWith('.ts') || entry.name.endsWith('.tsx'))) {
                    filesToScan.push(fullPath);
                }
            }
        }

        for (const dir of targetDirs) {
            collectFiles(dir);
        }

        expect(filesToScan.length).toBeGreaterThan(0);

        const violations: string[] = [];

        for (const filePath of filesToScan) {
            const content = fs.readFileSync(filePath, 'utf-8');
            const lines = content.split('\n');

            lines.forEach((line, index) => {
                // Abaikan import standar jika ada
                if (line.trim().startsWith('import') && line.includes('from')) {
                    return;
                }

                // Cek apakah ada 'as' cast pada respons atau deklarasi di api layer
                if (responseCastPattern.test(line) || asWordPattern.test(line)) {
                    violations.push(`${path.relative(rootDir, filePath)}:${index + 1}: ${line.trim()}`);
                }
            });
        }

        expect(violations).toEqual([]);
        expect(violations.length).toBe(0);
    });
});
