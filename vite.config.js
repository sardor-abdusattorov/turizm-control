import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/css/filament/admin/theme.css'
            ],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        // host / HMR / polling are driven by env vars so the same config
        // works both natively (defaults: localhost, no polling) and inside
        // the Docker `node` container, which sets VITE_HOST=0.0.0.0 etc.
        host: process.env.VITE_HOST || 'localhost',
        hmr: {
            host: process.env.VITE_HMR_HOST || 'localhost',
        },
        watch: {
            usePolling: process.env.VITE_USE_POLLING === 'true',
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
