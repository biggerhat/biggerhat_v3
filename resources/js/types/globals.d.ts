import type Echo from 'laravel-echo';
import type { route as routeFn } from 'ziggy-js';

declare global {
    const route: typeof routeFn;

    interface Window {
        Echo: Echo;
        Pusher: any;
        // Google Analytics (gtag.js) — present once the consent-mode snippet in
        // app.blade.php has loaded. Optional because SSR / ad-blockers may omit it.
        gtag?: (...args: unknown[]) => void;
        dataLayer?: unknown[];
    }
}
