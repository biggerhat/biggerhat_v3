import vue from '@vitejs/plugin-vue';
import autoprefixer from 'autoprefixer';
import laravel from 'laravel-vite-plugin';
import path from 'path';
import tailwindcss from 'tailwindcss';
import { resolve } from 'node:path';
import { defineConfig } from 'vite';

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
        rollupOptions: {
            output: {
                manualChunks: (id) => {
                    if (id.includes('node_modules')) {
                        if (id.includes('@tiptap') || id.includes('prosemirror')) return 'vendor-tiptap';
                        if (id.includes('@tanstack')) return 'vendor-tanstack';
                        if (id.includes('radix-vue') || id.includes('reka-ui') || id.includes('vaul-vue')) return 'vendor-ui';
                        if (id.includes('lucide')) return 'vendor-icons';
                        if (id.includes('vue') || id.includes('@inertiajs') || id.includes('@vueuse')) return 'vendor-core';
                    }
                },
            },
        },
    }
});
