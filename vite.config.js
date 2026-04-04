import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/admin/main.tsx'],
            refresh: true,
        }),
        react(),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
    build: {
        outDir: 'public/build',
        emptyOutDir: true,
        rolldownOptions: {
            output: {
                entryFileNames: 'assets/[name].js',
                chunkFileNames: 'assets/[name].js',
                assetFileNames: (assetInfo) => {
                    const names = Array.isArray(assetInfo.names) ? assetInfo.names : [];
                    const originalFileNames = Array.isArray(assetInfo.originalFileNames) ? assetInfo.originalFileNames : [];
                    const fileNames = [...names, ...originalFileNames];
                    const isCssAsset = fileNames.some((name) => name.toLowerCase().endsWith(".css"));

                    if (isCssAsset) {
                    return "assets/css/[name]-[hash][extname]";
                    }

                    return "assets/[name]-[hash][extname]";
                }
            },
        },
    },
});
