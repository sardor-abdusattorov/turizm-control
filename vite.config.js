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
        // host / HMR / polling are driven by env vars so the defaults suit a
        // plain `npm run dev` while a remote/VM setup can override them
        // (VITE_HOST=0.0.0.0, VITE_USE_POLLING=true) without editing this file.
        host: process.env.VITE_HOST || 'localhost',
        hmr: {
            host: process.env.VITE_HMR_HOST || 'localhost',
        },
        watch: {
            usePolling: process.env.VITE_USE_POLLING === 'true',
            // On Windows bind-mounts Vite has to poll every file every cycle;
            // sweeping vendor/ (~19k files) and node_modules/ kills the dev
            // server. Keep the watcher to source folders only.
            ignored: [
                '**/vendor/**',
                '**/node_modules/**',
                '**/storage/**',
                '**/bootstrap/cache/**',
                '**/public/build/**',
                '**/.git/**',
                '**/database/database.sqlite',
            ],
            // Polling-only: 100ms (chokidar default) hammers the CPU on
            // bind-mounts. 1s is plenty for our edit cadence.
            interval: process.env.VITE_USE_POLLING === 'true' ? 1000 : undefined,
            binaryInterval: process.env.VITE_USE_POLLING === 'true' ? 3000 : undefined,
        },
    },
});
