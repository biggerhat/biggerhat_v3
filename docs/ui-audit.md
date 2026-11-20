# UI audit harness

Browse logged-in screens (and run accessibility/layout checks) against a **local** copy of the site.
Nothing here touches production.

## 1. Local database with fixtures

```bash
export DB_CONNECTION=sqlite DB_DATABASE=/tmp/ui-audit.sqlite SESSION_DRIVER=file CACHE_STORE=file QUEUE_CONNECTION=sync
touch /tmp/ui-audit.sqlite
APP_ENV=testing php artisan migrate --force          # `testing` so dropForeignSafe no-ops on SQLite
php artisan app:seed-from-prod --skip-images         # catalog data (slow: the prod API rate-limits)
APP_ENV=testing php artisan db:seed --class=UiAuditSeeder --force
```

`UiAuditSeeder` creates (password `password` for all): `audit.member@`, `audit.organizer@`,
`audit.admin@` (super admin), `audit.opponent@`, `audit.pending@` `example.test`, plus crews, a
collection, games (in progress / completed / campaign-format / solo, with card images and replace-on-death + summon links on the first Arcanist character) and tournaments built from the catalog. It refuses
to run in production.

## 2. Serve it

```bash
APP_ENV=local APP_URL=http://127.0.0.1:8765 php artisan serve --port 8765 &
```

If `public/hot` exists from a stopped `npm run dev`, move it aside — Laravel will otherwise load assets
from the dead Vite server. Run `npm run build` first so `public/build` is current.

## 3. Audit

```bash
npm i --no-save axe-core          # not a project dependency
CHROME=/path/to/chrome node scripts/ui-audit/audit.cjs --role member --discover \
  --pages /games,/collection,/wishlists,/friends --out /tmp/shots
CHROME=... node scripts/ui-audit/audit.cjs --role admin --discover --pages /admin
```

`--discover` follows the admin sidebar, or the first few games / tournaments / crews. Output: one line
per page and viewport (h1 count, tiny-text count, horizontal overflow, axe serious+moderate rules) and
screenshots + `<role>-results.json` in `--out`.

Chrome: any Chromium works (Playwright's `chromium_headless_shell` is fine). On a machine without the
shared libraries and no sudo, `apt-get download` the missing `.deb`s, `dpkg -x` them somewhere and set
`LD_LIBRARY_PATH`.

The `campaign` role signs in as `alpha@biggerhat.test` (from `CampaignSeeder`; run `CampaignCatalogSeeder` and `CampaignRulebookSeeder` first) and `--discover` follows the campaign hub to its crews and games.

## 4. Query / payload profile

`php scripts/ui-audit/profile.php <guest|admin|member> urls.txt [--props]` replays paths in-process as Inertia
requests and prints query counts, the most-repeated query shape (N+1 signature) and props size. See
[performance.md](performance.md) for baselines and what has been fixed.

## 5. Driving real flows (puppeteer)

For flows that need clicking, not just visiting (forms, dialogs, tabs, two accounts at once), write a puppeteer script against the same local
server. Gotchas learned the hard way:
- `php artisan serve` does **not** forward `DB_*` env vars; start `php -S 127.0.0.1:8765 ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php`
  **from `public/`** with the env vars set (a wrong cwd returns 200 with a PHP warning page).
- Reka/radix tabs and selects react to real pointer events: use `page.click()` on a handle, not `el.click()` in `evaluate`.
- The radix-vue `Checkbox` is `checked` / `update:checked`, not `modelValue`.
- The dev server is single-threaded and Inertia visits finish asynchronously, so poll for text instead of sleeping a fixed time.
- Need a Chrome with no sudo? `apt-get download` the missing libs, `dpkg -x` them into a private dir and set `LD_LIBRARY_PATH`.

## 6. Tournament flows (`scripts/ui-audit/tournaments/`)

The 42-step tournament browser pass (guest / player / organizer sessions, self-report between two accounts, check-in, team events,
Fields of Glory create + scenario, scoring editor, phone-width layouts) lives in the repo so it can be re-run after tournament UI changes.

```bash
npm run build
CHROME=/path/to/chrome-headless-shell [LD_LIBRARY_PATH=...] scripts/ui-audit/tournaments/run.sh
```

`run.sh` builds a throwaway SQLite database (`$WORKDIR`, default `/tmp/ui-audit-tournaments`), seeds it with `seed.php` (a running Gaining Grounds event
with self-report, a team event in registration, a Fields of Glory event, a running team event), starts `php -S` from `public/`, and runs each `flows*.cjs`.
Each flows file mutates what it works on, so the database is re-seeded before every file. Output is PASS / WARN / FAIL per step (WARN = page errors,
console errors or 5xx during an otherwise passing step); failure screenshots and numbered shots land in `$SHOTS` (default `$WORKDIR/shots`).
Helpers in `lib.cjs` (`clickText`, `pickSelect`, `pickByTrigger`, `expectText`) encode the Reka/radix quirks above. Local use only.
CI: `.github/workflows/tournament-flows.yml` runs the whole pass on demand (Actions tab, manual trigger only; it has not been run on GitHub yet, so expect to adjust it). `lint.yml` has a non-blocking
`vue-tsc` step; run it locally with `npm install --no-save vue-tsc@2.2.12 && npx vue-tsc --noEmit` and `npm ci` afterwards (a saved dependency makes prettier's organize-imports plugin reorder imports across the app).
`flows22.cjs` is an axe-core pass over every tournament page and state (light/dark, desktop/phone, score dialog, top cut): it needs `npm i --no-save axe-core` (it skips itself without it), fails on
moderate or worse findings, and `AXE_REPORT=1` also lists minor ones.
`FLOWS="flows17.cjs flows2.cjs"` runs just those files. The server runs through `router.php` so uploaded images under `/storage` are served from the repo's `storage/app/public`
(uploads made by the flows land there; delete `storage/app/public/tournaments` afterwards). Reading screenshots: they are not cleared between runs, so check the file's timestamp before trusting one.

## Tabletop audit (`flows37.cjs`)

An audit of the sandbox, a game's board and the play screen, in light and dark and at desktop and phone width: axe-core (fails on serious), tap targets under 24px, text under 11px, overflow, fixed elements overlapping, a keyboard-only pass over the Fate deck, and a screenshot of each state (`audit-*.png` in `SHOTS`). Needs `npm i --no-save axe-core`. Run alone with `FLOWS=flows37.cjs scripts/ui-audit/tournaments/run.sh`.

Gotchas it taught: the app keeps its theme in `localStorage.appearance` (set it with `evaluateOnNewDocument`; the browser's `prefers-color-scheme` alone does not switch it); the play screen has a leave guard (`beforeunload`) that stalls a full `page.goto` away from it, so scan other pages first; `DialogContent` does not forward attributes (wrap a `data-testid` in an inner `div class="contents"`); and a button given its own `aria-label` fails axe's `label-content-name-mismatch` if its visible text (a card's suit glyph) is not in the label, so name such buttons from their content.

## Help pictures (`flows38.cjs`)

Not a test: it writes the screenshots used in the in-app help (`public/images/help`, `resources/js/lib/help/images.json`) and only runs with `HELP_SHOTS=1`:
`HELP_SHOTS=1 FLOWS=flows38.cjs CHROME=... scripts/ui-audit/tournaments/run.sh`. It also needs `public/hot` moved aside and a fresh `npm run build`. Details,
how to add a picture and what to check afterwards: [docs/features/help.md](features/help.md).

## Debugging one step (`ONLY`, `TIGHT_LIST`)

`ONLY="part of a step name"` makes `L.step` skip every other step, so one step can be rerun in about a minute:
`ONLY="a stepped move" FLOWS=flows35.cjs CHROME=... scripts/ui-audit/tournaments/run.sh` (the setup still runs; each step opens its own page). `TIGHT_LIST=1` makes
`flows37.cjs` print, for each audited state, the controls under 44px grouped by name (the app's own sidebar and header left out). Steps that click a control on the Table tab call `L.revealTable`
(see `lib.cjs`); `hit` does it for them.

