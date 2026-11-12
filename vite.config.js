import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js/partitura.js',
                'resources/js/sedes-mapa.js',
                'resources/css/archivo-gestion.css',
                'resources/css/archivo.css',
                'resources/css/ayuda.css',
                'resources/css/biblioteca.css',
                'resources/css/chilinga-admin.css',
                'resources/css/landing.css',
                'resources/css/programa-publico.css',
                'resources/css/programa.css',
                'resources/css/recordatorio-chatbot.css',
                'resources/js/legacy/archivo-carga.js',
                'resources/js/legacy/archivo-gestion.js',
                'resources/js/legacy/archivo.js',
                'resources/js/legacy/biblioteca-modal.js',
                'resources/js/legacy/biblioteca-share.js',
                'resources/js/legacy/hub-search.js',
                'resources/js/legacy/ito-a11y.js',
                'resources/js/legacy/ito-form-steps.js',
                'resources/js/legacy/ito-nav-progress.js',
                'resources/js/legacy/ito-shell.js',
                'resources/js/legacy/ito-tables.js',
                'resources/js/legacy/recordatorio-chatbot.js',
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
