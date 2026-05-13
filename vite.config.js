import { defineConfig, loadEnv } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, process.cwd(), '');

    const devServerUrl = env.VITE_DEV_SERVER_URL || undefined; // e.g. http://192.168.1.10:5173
    const hmrHost = env.VITE_HMR_HOST || undefined; // e.g. 192.168.1.10

    return {
        server: {
            host: true,
            cors: true,
            origin: devServerUrl,
            hmr: hmrHost ? { host: hmrHost } : undefined,
        },
        plugins: [
            laravel({
                input: ['resources/js/app.js', 'resources/css/app.css'],
                refresh: true,
            }),
            vue({
                template: {
                    transformAssetUrls: {
                        base: null,
                        includeAbsolute: false,
                    },
                },
            }),
        ],
    };
});
