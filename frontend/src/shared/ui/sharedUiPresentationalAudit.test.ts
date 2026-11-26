import { describe, expect, it } from 'vitest';
import * as fs from 'fs';
import * as path from 'path';
import { Badge, Button, Card, CardContent, CardHeader, CardTitle, Input, Modal, Spinner } from './index';

describe('Modular Frontend Architecture & Shared UI (Audit 496 - Issue #165)', () => {
    it('memastikan direktori shared/ui ada dan komponen presentasional tersedia', () => {
        const sharedUiDir = path.resolve(__dirname);
        expect(fs.existsSync(sharedUiDir)).toBe(true);

        const expectedComponents = ['Button.tsx', 'Badge.tsx', 'Card.tsx', 'Input.tsx', 'Modal.tsx', 'Spinner.tsx', 'index.ts'];
        for (const comp of expectedComponents) {
            expect(fs.existsSync(path.join(sharedUiDir, comp))).toBe(true);
        }

        expect(Button).toBeDefined();
        expect(Badge).toBeDefined();
        expect(Card).toBeDefined();
        expect(CardHeader).toBeDefined();
        expect(CardTitle).toBeDefined();
        expect(CardContent).toBeDefined();
        expect(Input).toBeDefined();
        expect(Modal).toBeDefined();
        expect(Spinner).toBeDefined();
    });

    it('memastikan 0 pemanggilan atau referensi fetch di dalam shared/ui (grep fetch di shared/ui -> 0)', () => {
        const sharedUiDir = path.resolve(__dirname);
        const files = fs.readdirSync(sharedUiDir);
        const violations: { file: string; line: number; text: string }[] = [];

        for (const file of files) {
            if (!/\.(tsx?|jsx?)$/.test(file)) continue;
            const filePath = path.join(sharedUiDir, file);
            const content = fs.readFileSync(filePath, 'utf8');
            const lines = content.split('\n');

            lines.forEach((lineText, index) => {
                if (file.includes('.test.')) return; // Jangan scan file test sendiri
                if (/\bfetch\b/i.test(lineText)) {
                    violations.push({ file, line: index + 1, text: lineText.trim() });
                }
            });
        }

        expect(violations).toEqual([]);
    });
});
