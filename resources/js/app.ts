import '../css/app.css';
import './echo';

import AppAdminLayout from '@/layouts/AppAdminLayout.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { createInertiaApp, Head, Link, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h, type DefineComponent } from 'vue';

import { ZiggyVue } from 'ziggy-js';
import { initializeAccent, initializeTheme } from './composables/useAppearance';

// Extend ImportMeta interface for Vite...
declare module 'vite/client' {
    interface ImportMetaEnv {
        readonly VITE_APP_NAME: string;
        [key: string]: string | boolean | undefined;
    }

    interface ImportMeta {
        readonly env: ImportMetaEnv;
        readonly glob: <T>(pattern: string) => Record<string, () => Promise<T>>;
    }
}

const appName = import.meta.env.VITE_APP_NAME || 'BiggerHat';

createInertiaApp({
    title: (title) => {
        if (!title) {
            return appName;
        }

        return `${title} - ${appName}`;
    },
    resolve: async (name) => {
        const page = await resolvePageComponent(`./pages/${name}.vue`, import.meta.glob<DefineComponent>('./pages/**/*.vue'));
        page.default.layout = page.default.layout || (name.startsWith('Admin/') ? AppAdminLayout : AppLayout);
        return page;
    },
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .component('Link', Link)
            .component('Head', Head)
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on page load...
initializeTheme();
// Apply the saved per-faction accent (no-op if user hasn't picked one).
initializeAccent();

// After every Inertia navigation, sync <meta name="csrf-token"> with the
// shared `csrf_token` prop. Without this, the meta tag stays at whatever
// was rendered on the FIRST page load, so a user who logs in mid-session
// (e.g. via a join link → login → game tracker chain) keeps sending the
// pre-login token from raw fetch() calls and gets 419'd until they
// hard-refresh. Inertia events run on every visit, including same-page
// reloads, keeping the tag aligned with the live session.
router.on('success', (event) => {
    const props = event.detail?.page?.props as { csrf_token?: string } | undefined;
    const token = props?.csrf_token;
    if (!token || typeof document === 'undefined') return;
    const meta = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]');
    if (meta && meta.content !== token) {
        meta.content = token;
    }
});

// Register PWA service worker
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/build/sw.js').catch(() => {
            // SW registration failed — app works fine without it
        });
    });
}
