import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        tailwindcss(),
    ],
    // CSS images (Leaflet markers) and lazy JS chunks use relative URLs, so
    // the build works under any ASSET_URL (e.g. https://example.com/public/).
    experimental: {
        renderBuiltUrl: () => ({ relative: true }),
    },
    build: {
        rollupOptions: {
            output: {
                // MapLibre's worker is an .mjs file. Some hosts (the live Hostinger server)
                // send .mjs as text/plain, and browsers refuse to run it, so the map shows no
                // street detail and no listing pins. Publish it as .js, which every host serves
                // as JavaScript.
                assetFileNames: (asset) => ((asset.names?.[0] ?? asset.name ?? '').endsWith('.mjs') ? 'assets/[name]-[hash].js' : 'assets/[name]-[hash][extname]'),
            },
        },
    },
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
