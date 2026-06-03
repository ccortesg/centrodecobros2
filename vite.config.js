import path from 'node:path';
import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/assets/js/app.js'],
            publicDirectory: 'public',
            buildDirectory: 'build',
            refresh: false,
        }),
        vue(),
    ],
    resolve: {
        alias: {
            '@': path.resolve(__dirname, 'resources/assets/js'),
            vue: 'vue/dist/vue.esm-bundler.js',
        },
    },
    build: {
        manifest: 'manifest.json',
        outDir: 'public/build',
        emptyOutDir: true,
        chunkSizeWarningLimit: 1024,
    },
    server: {
        host: '127.0.0.1',
        strictPort: true,
    },
});
