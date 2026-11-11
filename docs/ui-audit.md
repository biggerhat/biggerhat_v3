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
`FLOWS="flows17.cjs flows2.cjs"` runs just those files. The server runs through `router.php` so uploaded images under `/storage` are served from the repo's `storage/app/public`
(uploads made by the flows land there; delete `storage/app/public/tournaments` afterwards). Reading screenshots: they are not cleared between runs, so check the file's timestamp before trusting one.
