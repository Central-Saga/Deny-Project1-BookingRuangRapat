import { fileURLToPath } from 'node:url';
import { defineConfig } from 'vitest/config';

export default defineConfig({
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./src', import.meta.url)),
        },
    },
    test: {
        include: ['src/**/*.test.ts', 'src/**/*.spec.ts'],
        exclude: ['node_modules/**', 'e2e/**', '.next/**'],
        environment: 'node',
    },
});
