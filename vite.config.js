import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

export default defineConfig(({ mode }) => {
    // Vite gère automatiquement NODE_ENV selon le mode (dev/build)
    // Ne pas définir NODE_ENV dans le fichier .env
    return {
        plugins: [
            laravel({
                input: [
                    'resources/css/app.css',
                    'resources/js/app.js',
                    'resources/js/admin/AppAdmin.ts',
                ],
                refresh: true,
            }),
            vue(),
        ],
    };
});
