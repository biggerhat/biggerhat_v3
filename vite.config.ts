import vue from '@vitejs/plugin-vue';
import autoprefixer from 'autoprefixer';
import laravel from 'laravel-vite-plugin';
import path from 'path';
import tailwindcss from 'tailwindcss';
import { resolve } from 'node:path';
import { defineConfig } from 'vite';
import { VitePWA } from 'vite-plugin-pwa';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/js/app.ts'],
            ssr: 'resources/js/ssr.ts',
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
        VitePWA({
            registerType: 'autoUpdate',
            workbox: {
                globPatterns: ['**/*.{js,css,html,ico,png,svg,woff,woff2}'],
                navigateFallback: null,
                runtimeCaching: [
                    {
                        urlPattern: /^https:\/\/fonts\.bunny\.net\/.*/i,
                        handler: 'CacheFirst',
                        options: {
                            cacheName: 'bunny-fonts',
                            expiration: { maxEntries: 10, maxAgeSeconds: 60 * 60 * 24 * 365 },
                        },
                    },
                    {
                        urlPattern: /\/storage\/.*\.(png|jpg|jpeg|webp|svg)$/i,
                        handler: 'CacheFirst',
                        options: {
                            cacheName: 'storage-images',
                            expiration: { maxEntries: 200, maxAgeSeconds: 60 * 60 * 24 * 30 },
                        },
                    },
                ],
            },
            manifest: {
                name: 'BiggerHat',
                short_name: 'BiggerHat',
                description: 'Malifaux miniatures database, crew builder, game tracker, and tournament manager',
                theme_color: '#171717',
                background_color: '#0a0a0a',
                display: 'standalone',
                start_url: '/',
                scope: '/',
                icons: [
                    { src: '/android-chrome-192x192.png', sizes: '192x192', type: 'image/png' },
                    { src: '/android-chrome-512x512.png', sizes: '512x512', type: 'image/png' },
                    { src: '/android-chrome-512x512.png', sizes: '512x512', type: 'image/png', purpose: 'maskable' },
                ],
            },
        }),
    ],
    resolve: {
        alias: {
            '@': path.resolve(__dirname, './resources/js'),
            'ziggy-js': resolve(__dirname, 'vendor/tightenco/ziggy'),
        },
    },
    css: {
        postcss: {
            plugins: [tailwindcss, autoprefixer],
        },
    },
    build: {
        sourcemap: false,
        // Target evergreen browsers (Edge 88+ / Chrome 87+ / Firefox 78+ /
        // Safari 14+) so esbuild emits less downleveled code — smaller
        // bundles for users.
        target: 'es2022',
        rollupOptions: {
            // Vendor splitting for runtime cache hit rate — frequent app
            // changes don't bust the heavy TipTap/ProseMirror/UI chunks.
            output: {
                manualChunks: (id) => {
                    if (id.includes('node_modules')) {
                        if (id.includes('@tiptap')) return 'vendor-tiptap';
                        if (id.includes('prosemirror')) return 'vendor-prosemirror';
                        if (id.includes('@tanstack')) return 'vendor-tanstack';
                        if (id.includes('radix-vue') || id.includes('reka-ui') || id.includes('vaul-vue')) return 'vendor-ui';
                        if (id.includes('lucide')) return 'vendor-icons';
                        if (id.includes('@inertiajs')) return 'vendor-inertia';
                        if (id.includes('@vueuse')) return 'vendor-vueuse';
                        if (id.includes('vue')) return 'vendor-vue';
                    }
                },
            },
        },
    }
});
