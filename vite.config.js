import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.jsx'
            ],
            refresh: true,
        }),
        react(),
    ],
    build: {
        // Para evitar problemas con los estilos
        cssCodeSplit: false,
        rollupOptions: {
            output: {
                manualChunks: undefined
            }
        }
    }
});
