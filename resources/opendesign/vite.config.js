import { defineConfig } from 'vite';
import preact from '@preact/preset-vite';
import tailwindcss from '@tailwindcss/vite';

// Editor Diseño (OpenDesign). Se sirve desde /opendesign/ y Laravel lo incluye con
// @vite('src/main.tsx', 'opendesign') (resources/views/disenos/editor.blade.php).
export default defineConfig({
    plugins: [preact(), tailwindcss()],
    base: '/opendesign/',
    build: {
        outDir: '../../public/opendesign',
        emptyOutDir: true,
        manifest: 'manifest.json',
        rollupOptions: { input: 'src/main.tsx' },
    },
    resolve: {
        alias: {
            react: 'preact/compat',
            'react-dom': 'preact/compat',
            'react/jsx-runtime': 'preact/jsx-runtime',
        },
    },
});
