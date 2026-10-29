# Server-side rendering (Inertia SSR)

`config/inertia.php` enables SSR by default, but it only works when **two things** exist on the server;
without them Inertia silently falls back to client-side rendering (pages still work, but the HTML has no
content, titles are generic and first paint waits for JavaScript).

1. **The SSR bundle**: built with `npm run build:ssr` (`vite build && vite build --ssr`) into
   `bootstrap/ssr/ssr.js`. A plain `npm run build` does not produce it.
2. **The SSR process**: `php artisan inertia:start-ssr` (a long-running Node server on port 13714). Run it as a
   daemon (Forge → Daemons) and restart it on every deploy with `php artisan inertia:stop-ssr`
   (the daemon supervisor brings it back up on the new bundle).

Check it from the outside: `curl -s https://biggerhat.net/privacy | grep -c '<h1'` should print `1`.
With SSR off, it prints `0`.

## Things that have broken SSR here

- **`ssr.ts` placeholder.** Inertia's Vue adapter calls `render(vueApp)` synchronously and puts the result in
  `innerHTML`, but `renderToString` returns a Promise. Vue ≤ 3.5.13 tolerated that; later versions render
  `[object Promise]`. `resources/js/ssr.ts` hands the adapter a placeholder and splices in the real render.
- **Admin routes in the guest route table.** `config/ziggy.php` omits `admin.*` routes for guests. Calling
  `route('admin.…')` while rendering a public page throws during SSR (and takes the SSR process down). Pass a
  function to `AdminEditLink`'s `href` so the route is only built for users who can see the link.
- **`window` during render.** Anything touching `window`/`document` must be guarded or moved to `onMounted`;
  `composables/useCurrentUrl.ts` shows how to get the current URL on both sides.
- **Head tags.** With SSR on, `app.blade.php` skips its own `<title>`/meta defaults (the SSR head replaces them);
  they are only the fallback when SSR is unavailable.

Layouts, titles and head tags are resolved by `resources/js/lib/resolvePage.ts` for both entry points, so the
server renders the same chrome the browser will.
