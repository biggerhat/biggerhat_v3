import '../css/app.css';
import './echo';

import AppLayout from '@/layouts/AppLayout.vue';
import { createInertiaApp, Head, Link } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h, type DefineComponent } from 'vue';

import { ZiggyVue } from 'ziggy-js';
import { initializeTheme } from './composables/useAppearance';

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

// TODO: Make an enum
const factionBackground = (factionName: string): string => {
    if (!factionName) return '';

    switch (factionName.toLowerCase()) {
        case 'explorers_society':
            return 'bg-explorerssociety';
        case 'ten_thunders':
            return 'bg-tenthunders';
        default:
            return `bg-${factionName}`;
    }
};

createInertiaApp({
    title: (title) => {
        if (!title) {
            return appName;
        }

        return `${title} - ${appName}`;
    },
    resolve: async (name) => {
        const page = await resolvePageComponent(`./pages/${name}.vue`, import.meta.glob<DefineComponent>('./pages/**/*.vue'));
        page.default.layout = page.default.layout || AppLayout;
        return page;
    },
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .component('Link', Link)
            .component('Head', Head)
            .mixin({
                methods: {
                    factionBackground: factionBackground,
                },
            })
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on page load...
initializeTheme();
