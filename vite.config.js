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
        // CAMBIADO A TRUE: Ahora sí permite que el CSS conviva en el input de arriba
        cssCodeSplit: true, 
        rollupOptions: {
            output: {
                manualChunks: undefined
            }
        }
    }
});