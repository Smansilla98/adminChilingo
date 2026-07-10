import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/css/diseno-canvas.css',
                'resources/css/programa-compositor.css',
                'resources/js/app.js',
                'resources/js/programa-partitura.js',
                'resources/js/programa-compositor.js',
                'resources/js/diseno-canvas.js',
            ],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
