import { fileURLToPath } from 'node:url';
import { defineConfig } from 'vitest/config';

export default defineConfig({
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./src', import.meta.url)),
        },
    },
    test: {
        include: ['src/**/*.test.ts', 'src/**/*.test.tsx', 'src/**/*.spec.ts', 'src/**/*.spec.tsx'],
        exclude: ['node_modules/**', 'e2e/**', '.next/**'],
        environment: 'node',
        setupFiles: ['./src/mocks/setup.ts'],
        coverage: {
            provider: 'v8',
            reporter: ['text', 'json', 'html'],
            include: ['src/**/*.ts', 'src/**/*.tsx'],
            exclude: [
                'src/**/*.test.ts',
                'src/**/*.test.tsx',
                'src/**/*.spec.ts',
                'src/**/*.spec.tsx',
                'src/types/**',
                'src/mocks/**',
                'src/app/**',
                'src/components/*DashboardPage*',
                'src/components/BookingCalendar*',
                'src/components/BookingModal*',
            ],
            thresholds: {
                lines: 60,
                branches: 60,
                functions: 60,
                statements: 60,
            },
        },
    },
});
