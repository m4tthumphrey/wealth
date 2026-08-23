import { defineConfig, loadEnv } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';

export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, process.cwd(), '');

    return {
        plugins: [
            laravel({
                input: ['resources/css/app.css', 'resources/js/wealth/main.js'],
                refresh: true,
            }),
            tailwindcss(),
            vue(),
        ],
        server: {
            host: '0.0.0.0',
            port: 5173,
            strictPort: true,
            hmr: {
                host: env.VITE_HMR_HOST || undefined,
            },
        },
    };
});
