import type Echo from 'laravel-echo';

let pending: Promise<Echo | null> | null = null;

/**
 * Resolves with the shared Echo instance, loading `laravel-echo` + `pusher-js` and opening the
 * Reverb socket on first use. Resolves `null` when Reverb isn't configured (or during SSR).
 *
 * Most visits (guests browsing the database) never touch a realtime channel, so `echo.js` is no
 * longer imported eagerly from app.ts — that cost every page view ~70KB of JS and a websocket.
 * Channel composables call this from `onMounted`, and must re-check that they're still mounted
 * once it resolves (the import is async).
 */
export function loadEcho(): Promise<Echo | null> {
    if (typeof window === 'undefined') return Promise.resolve(null);
    if (window.Echo) return Promise.resolve(window.Echo);

    pending ??= import('../echo').then(() => window.Echo ?? null);

    return pending;
}
