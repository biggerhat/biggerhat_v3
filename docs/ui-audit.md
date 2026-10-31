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
