import AppAdminLayout from '@/layouts/AppAdminLayout.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import AppSettingsLayout from '@/layouts/AppSettingsLayout.vue';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { DefineComponent } from 'vue';

/**
 * Page resolution shared by the browser entry (app.ts) and the SSR entry (ssr.ts), so the server renders
 * the same layout and title the client will.
 */
export const appName = import.meta.env.VITE_APP_NAME || 'BiggerHat';

/** Pages that already include the app name (via <SeoHead>) are not suffixed twice. */
export function pageTitle(title: string): string {
    if (!title) return appName;

    return title.includes(appName) ? title : `${title} - ${appName}`;
}

export async function resolvePage(name: string) {
    const page = await resolvePageComponent(`../pages/${name}.vue`, import.meta.glob<DefineComponent>('../pages/**/*.vue'));
    // `|| (...)` would clobber a page's explicit `layout: null` (falsy, same as unset) — only fall back
    // when the page never set it at all, so a headless-capture-style page can genuinely opt out of chrome.
    if (page.default.layout === undefined) {
        page.default.layout = name.startsWith('Admin/') ? AppAdminLayout : name.startsWith('Settings/') ? AppSettingsLayout : AppLayout;
    }

    return page;
}
