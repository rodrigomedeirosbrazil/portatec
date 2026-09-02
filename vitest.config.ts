import { resolve } from 'node:path';
import { defineConfig } from 'vitest/config';

export default defineConfig({
    resolve: {
        alias: {
            '@': resolve(import.meta.dirname, './resources/js'),
        },
    },
    test: {
        environment: 'jsdom',
        setupFiles: ['./resources/js/test-setup.ts'],
        include: ['resources/js/**/*.test.ts', 'resources/js/**/*.test.tsx'],
    },
});
