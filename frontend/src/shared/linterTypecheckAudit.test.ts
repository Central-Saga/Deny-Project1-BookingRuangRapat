import { describe, expect, it } from 'vitest';
import * as fs from 'fs';
import * as path from 'path';

describe('Audit 496 - Issue #167: Linter & Typechecker Kebersihan Proyek (Pint, Larastan, ESLint)', () => {
    it('memastikan ESLint terpasang dan konfigurasi eslint.config.mjs tersedia', () => {
        const pkgPath = path.resolve(__dirname, '../../../frontend/package.json');
        const eslintConfigPath = path.resolve(__dirname, '../../../frontend/eslint.config.mjs');

        expect(fs.existsSync(pkgPath)).toBe(true);
        expect(fs.existsSync(eslintConfigPath)).toBe(true);

        const pkg = JSON.parse(fs.readFileSync(pkgPath, 'utf8'));
        expect(pkg.devDependencies.eslint).toBeDefined();
        expect(pkg.devDependencies['eslint-config-next']).toBeDefined();
    });

    it('memastikan backend composer.json memuat laravel/pint dan larastan/larastan', () => {
        const composerPath = path.resolve(__dirname, '../../../backend/composer.json');
        const neonPath = path.resolve(__dirname, '../../../backend/phpstan.neon');

        expect(fs.existsSync(composerPath)).toBe(true);
        expect(fs.existsSync(neonPath)).toBe(true);

        const composer = JSON.parse(fs.readFileSync(composerPath, 'utf8'));
        expect(composer['require-dev']['laravel/pint']).toBeDefined();
        expect(composer['require-dev']['larastan/larastan']).toBeDefined();

        const neonContent = fs.readFileSync(neonPath, 'utf8');
        expect(neonContent).toContain('larastan/larastan');
        expect(neonContent).toContain('app/');
    });
});
