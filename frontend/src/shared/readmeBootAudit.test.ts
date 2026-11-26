import { describe, expect, it } from 'vitest';
import * as fs from 'fs';
import * as path from 'path';

describe('Audit 496 - Issue #166: README Eksekusi dari Nol', () => {
    it('memastikan semua berkas README ada dan tidak boleh 0 baris', () => {
        const rootReadmePath = path.resolve(__dirname, '../../../README.md');
        const backendReadmePath = path.resolve(__dirname, '../../../backend/README.md');
        const frontendReadmePath = path.resolve(__dirname, '../../../frontend/README.md');

        expect(fs.existsSync(rootReadmePath)).toBe(true);
        expect(fs.existsSync(backendReadmePath)).toBe(true);
        expect(fs.existsSync(frontendReadmePath)).toBe(true);

        const rootContent = fs.readFileSync(rootReadmePath, 'utf8');
        const backendContent = fs.readFileSync(backendReadmePath, 'utf8');
        const frontendContent = fs.readFileSync(frontendReadmePath, 'utf8');

        expect(rootContent.trim().split('\n').length).toBeGreaterThan(50);
        expect(backendContent.trim().split('\n').length).toBeGreaterThan(15);
        expect(frontendContent.trim().split('\n').length).toBeGreaterThan(15);
    });

    it('memastikan README memiliki langkah lengkap untuk eksekusi container bersih dan native', () => {
        const rootReadmePath = path.resolve(__dirname, '../../../README.md');
        const content = fs.readFileSync(rootReadmePath, 'utf8');

        // Memastikan ada section langkah menjalankan dari nol
        expect(content).toMatch(/Cara Menjalankan dari Nol|Quick Start/);

        // Memastikan seluruh perintah esensial tercakup
        expect(content).toContain('cp .env.example .env');
        expect(content).toContain('docker compose up');
        expect(content).toContain('key:generate');
        expect(content).toContain('migrate');
        expect(content).toContain('seed');
        expect(content).toContain('http://localhost:3000');
    });

    it('memastikan akun demo dan ambang batas waktu (<= 15 menit sampai login) didokumentasikan', () => {
        const rootReadmePath = path.resolve(__dirname, '../../../README.md');
        const content = fs.readFileSync(rootReadmePath, 'utf8');

        expect(content).toContain('admin@example.com');
        expect(content).toContain('karyawan@example.com');
        expect(content).toContain('password');
        expect(content).toMatch(/15 menit|15\.0/);
    });
});
