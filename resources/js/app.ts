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

// Send a GA4 page_view on each Inertia (SPA) navigation. The initial load is
// already counted by gtag('config') in the Blade head; without this, every
// subsequent <Link> visit (client-side pushState, no full reload) is invisible
// to GA — the main cause of pageview undercounting. Consent Mode decides whether
// the hit uses cookies, so we always send: GA downgrades to a cookieless ping
// when analytics_storage is denied. Dedupe by URL so back/forward and same-page
// reloads don't double-count, and so the first navigate back to the landing URL
// isn't counted twice with the Blade config hit.
let lastTrackedUrl = typeof window !== 'undefined' ? window.location.pathname + window.location.search : null;
router.on('navigate', (event) => {
    if (typeof window === 'undefined' || typeof window.gtag !== 'function') return;
    const url = event.detail?.page?.url;
    if (!url || url === lastTrackedUrl) return;
    lastTrackedUrl = url;
    window.gtag('event', 'page_view', {
        page_location: window.location.href,
        page_title: document.title,
        page_path: url,
    });
});

// Register PWA service worker
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/build/sw.js').catch(() => {
            // SW registration failed — app works fine without it
        });
    });
}
