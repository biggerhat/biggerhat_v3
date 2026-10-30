import { pageTitle, resolvePage } from '@/lib/resolvePage';
import { createInertiaApp, Head, Link } from '@inertiajs/vue3';
import createServer from '@inertiajs/vue3/server';
import { renderToString } from '@vue/server-renderer';
import { createSSRApp, h, type App } from 'vue';
import { route as ziggyRoute } from 'ziggy-js';

/*
 * Inertia's Vue adapter calls `render(vueApp)` synchronously inside the outer app's render function and
 * expects a string, but `renderToString` returns a Promise. Older Vue versions tolerated the Promise being
 * pushed into the SSR buffer; Vue >= 3.5.4x stringifies it, so every page came out as "[object Promise]".
 * Hand the adapter a placeholder for that inner call, render the real app ourselves, and splice it in.
 */
const INNER_PLACEHOLDER = '<!--inertia-ssr-inner-->';

createServer(async (page) => {
    let vueApp: App | null = null;
    let inner: Promise<string> | null = null;

    const result = await createInertiaApp({
        page,
        render: ((app: App) => {
            if (app === vueApp) {
                inner = renderToString(app);

                return INNER_PLACEHOLDER;
            }

            return renderToString(app);
        }) as unknown as typeof renderToString,
        title: pageTitle,
        resolve: resolvePage,
        setup({ App, props, plugin }) {
            const app = createSSRApp({ render: () => h(App, props) });

            // Configure Ziggy for SSR...
            const ziggyConfig = {
                ...page.props.ziggy,
                location: new URL(page.props.ziggy.location),
            };
            const route = (name: string, params?: any, absolute?: boolean) => ziggyRoute(name, params, absolute, ziggyConfig);

            // Make route function available to templates and to plain modules...
            app.config.globalProperties.route = route;
            if (typeof window === 'undefined') {
                global.route = route;
            }

            app.use(plugin).component('Link', Link).component('Head', Head);
            vueApp = app;

            return app;
        },
    });

    if (result && inner) {
        result.body = result.body.replace(INNER_PLACEHOLDER, await inner);
    }

    return result;
});
