import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        vue(),
        laravel({
            input: [
                'resources/css/app.css',
                'resources/css/filament/admin/theme.css',
                'resources/css/filament/platform/theme.css',
                'resources/js/app.js',
                'resources/js/passkeys.js',
                'resources/css/mobile.css',
                'resources/js/mobile/main.js',
                'resources/css/ops.css',
                'resources/js/ops/main.js',
            ],
            refresh: true,
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                }),
                // Serif del encabezado del login (LOGIN-2). Autohospedada como
                // Instrument Sans, por la misma razón: la CSP no permite hojas de
                // estilo externas (fonts.bunny.net no está en style-src).
                bunny('Fraunces', {
                    weights: [500],
                }),
            ],
        }),
        tailwindcss(),
    ],
    server: {
        host: '127.0.0.1',
        cors: true,
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
