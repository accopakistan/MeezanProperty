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
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
