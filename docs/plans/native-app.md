# Native iOS / Android app (Capacitor) — plan

Status: planned, not started. Written 2026-11-08 after a repo check.

## Approach
Wrap the live site in **Capacitor** (remote-URL mode: the shell loads the production site, so Inertia + SSR keep working).
Do NOT bundle static assets (Inertia pages come from Laravel controllers; no API layer exists) and do NOT rewrite natively.
Apple guideline 4.2 rejects thin website wrappers, so the app needs real native features (push first).

## What the repo already has
- `vite-plugin-pwa`, SW at `/build/sw.js` (registered in `resources/js/app.ts`, try/catch), `navigateFallback: null`.
- Database sessions, `same_site=lax`. No social login (no Sign in with Apple requirement). Account deletion exists (`DELETE settings/profile`).
- Reverb/Pusher broadcasting configured; `pusher-js` installed.
- ~30 files use `html-to-image` / blob downloads / `window.open` (crewShareImage, useCardJsonTransfer, useLootCardCapture, Card Creator, Crew Builder, Game summary, QR dialog).
- Stale `public/site.webmanifest` (empty name) — the real one is `/build/manifest.webmanifest`.

## Risks (priority order)
1. Apple 4.2 minimum functionality.
2. Ko-fi link on `Supporters/Index.vue` — Apple rejects external payment/donation links; hide when native.
3. Downloads/share: `<a download>`, blob URLs, `window.open` break in WKWebView → Filesystem + Share plugins.
4. `html-to-image` on WebKit (blank images, missing fonts/art, CORS on art).
5. Service worker stale caches in the shell.
6. Cookies/CSRF/login/2FA in the webview; universal + app links.
7. `target="_blank"` links → Browser plugin.
8. `Games/Show.vue` (~6.7k lines) performance on mid-range Android.
9. Wyrd IP / community-content policy for a store listing.

## Phases
0. Decisions + groundwork (2–3d): Wyrd policy, push scope, dev accounts (Apple $99/yr, Google $25), manifest cleanup + maskable icons, `useNative()` composable.
1. Shell (2–3d): Capacitor core/cli/ios/android + app, browser, share, filesystem, haptics, status-bar, splash-screen, push-notifications; `capacitor.config.ts` → prod URL with `allowNavigation`; safe-area insets; apple-app-site-association + assetlinks.json.
2. Native bridges (4–5d): `saveOrShare(blob, filename)` helper across ~30 call sites; Browser plugin for external links; hide Ko-fi when native; skip SW when native; push (FCM, APNs via FCM, `device_tokens` table + register endpoint, Laravel notification channel; triggers: tournament round/pairing, campaign turn/aftermath, game invites); haptics, share sheet, optional camera for art upload.
3. Device testing (3–4d): login/session persistence, exports, QR, full Game Tracker game, Bonanza print, rotation, dark mode, offline, web-deploy update path.
4. Submission (~1wk mostly review): listing, screenshots, privacy policy (native section: push token), Apple privacy labels, Google data-safety, TestFlight/Play internal testing, reviewer notes + demo account.

Total ≈ 2–3 weeks work + review time. Push is the largest piece.

## Open questions
- Push at launch, or wrapper + share/download first?
- Both platforms at once, or Android first (no Mac needed)?
- Mac / CI for iOS builds?

## Prep done on branch `native-app-prep-nov8` (web-only, no behaviour change)
- `resources/js/lib/native.ts` (`isNative`, `nativePlatform`, `saveBlob`, `saveUrl`, `openExternal`) + `composables/useNative.ts`. Image/PDF/JSON exports and `window.open` PDF/print links now go through it; the native branches are the only thing left to fill in.
- Ko-fi links hidden when native (footer, Hub, Supporters page).
- `viewport-fit=cover` + safe-area insets (body, phone tab bar).
- Deleted the stale `public/site.webmanifest` (the generated `/build/manifest.webmanifest` is the real one).
- Dormant push backend: `device_tokens` table, `DeviceToken` model/factory, `POST|DELETE settings/device-tokens` (JSON), tests. Nothing sends yet.

Also done (second pass):
- Server-image `<a download>` links on the Card Creator pages use `onDownloadClick` (web unchanged; native fetches + saves).
- `renderPng()` wraps `html-to-image` with a WebKit warm-up render (first paint of images is often blank in Safari/iOS); used by card export, Arsenal Sheet export and loot card capture. Art is same-origin `/storage/…`, so no CORS issue as long as the shell loads the production host (keep APP_URL = the served host).
- Push plumbing: `PushChannel` (reuses each notification's `toArray()` message + `action_url`), `PushSender` interface bound to `NullPushSender`, `users.push_notifications` switch, all 8 user-facing notifications include the channel. Dead tokens reported by a sender are deleted. At launch: write the FCM `PushSender`, bind it, add a settings toggle (shown only when native).
- `Games/Show.vue` static check: chunk 275 kB (61 kB gzip), 12 watchers, no deep watchers. A real mid-range-Android profile still needs a device.

Still to do before launch: iOS Safari/device verification of exports (needs a device), real-device Game Tracker profile, notification settings toggle UI, FCM sender, privacy-policy push-token bullet.
