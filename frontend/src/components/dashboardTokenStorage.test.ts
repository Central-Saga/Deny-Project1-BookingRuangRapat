import { describe, expect, it } from 'vitest';
import * as fs from 'fs';
import * as path from 'path';

describe('Audit 496 Parameter SEC (Issue #160) - Token Storage in Frontend', () => {
    it('ensures DashboardPage.tsx does not reference localStorage directly', () => {
        const filePath = path.resolve(__dirname, 'DashboardPage.tsx');
        const content = fs.readFileSync(filePath, 'utf-8');

        // Standar Audit 496: ambang lulus standar: bukan localStorage di DashboardPage
        expect(content).not.toContain('localStorage');
        expect(content).not.toContain('window.localStorage');
    });

    it('ensures all components under src/components do not directly access token from localStorage', () => {
        const componentsDir = path.resolve(__dirname);
        const files = fs.readdirSync(componentsDir).filter((file) => file.endsWith('.tsx') || file.endsWith('.ts'));

        for (const file of files) {
            if (file.endsWith('.test.ts') || file.endsWith('.test.tsx')) continue;
            const fullPath = path.join(componentsDir, file);
            const content = fs.readFileSync(fullPath, 'utf-8');
            expect(content, `File ${file} should not contain localStorage`).not.toContain('localStorage');
        }
    });
});
