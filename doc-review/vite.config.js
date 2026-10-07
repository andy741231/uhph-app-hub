import { fileURLToPath, URL } from 'node:url';
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

export default defineConfig(({ mode }) => ({
    plugins: [
        laravel({
            input: 'resources/js/app.js',
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
    ],
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
        },
    },
    build: {
        sourcemap: mode === 'development' ? 'inline' : false,
    },
    server: {
        host: 'localhost',
        port: 5174,
        strictPort: true,
        cors: true,
        hmr: {
            host: 'localhost',
            protocol: 'ws',
            port: 5174,
        },
        origin: 'http://localhost:5174',
    },
}));
