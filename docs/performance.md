# Performance notes

What was measured, what was fixed, what to configure on the server, and what is still open.
Numbers come from the local audit database (see [ui-audit.md](ui-audit.md)); treat them as relative.

## Measuring

```bash
# One path per line. Replays each as an Inertia XHR in-process; prints queries, repeated-query count
# (dup >= 5 is the N+1 signature), DB/wall time and props size. --props adds the heaviest props.
php scripts/ui-audit/profile.php guest urls.txt --props
php scripts/ui-audit/profile.php admin urls.txt
```

`php artisan route:list --json --method=GET` is a quick way to build `urls.txt`. The local database needs
catalog data **and** realistic volume for the area under test (TOS, blog, channels, packages are empty after
`app:seed-from-prod`; the seeders/factories under `database/` fill them) — an N+1 is invisible on three rows.

For browser-side checks (axe, layout, tiny text) use `scripts/ui-audit/audit.cjs`; for bundle sizes read the
`vite build` output.

## Baseline from the Nov 2026 audit

- **No N+1 anywhere** on ~85 public and ~110 admin GET routes: the most-repeated query shape runs ≤ 2 times.
  Heaviest route is `/admin` (≈ 20 queries, cached dashboard counts).
- Per-request cost that *was* paid on every page: three `Schema::hasTable` probes
  (`information_schema` on MySQL) from the shared Inertia props. Removed — see below.

## Fixed

| Area | Before | After |
|---|---|---|
| Ziggy route table in every Inertia XHR | 55 KB (guest) / 93 KB (admin) per navigation | `location` only; the table ships once in the first HTML (the client builds routes from `@routes`) |
| `auth.user` for admins | 19 KB (Spatie `permissions`/`roles` relations serialized) | 2.5 KB |
| `/admin` payload | 137 KB | 25 KB |
| Admin index tables (actions, abilities, triggers, characters, miniatures, crew/character upgrades) | 180–480 KB of full rows | only the rendered columns (`/admin/actions` 322 KB → 84 KB) |
| Admin upgrade form option lists | whole Ability/Trigger/Token/Marker rows | `id, name` |
| `/factions/{faction}` | 325 KB (every character's actions + triggers, loaded only for the suit tally) | 156 KB |
| `/command` (Cmd-K catalog, fetched at idle on every page) | 12 queries, uncached | cached 10 min server-side, `Cache-Control: public, max-age=300` |
| Echo / `pusher-js` | in the main bundle, websocket opened on **every** page view (guests included) | lazy `lib/echo.ts#loadEcho()`; only pages with a channel (logged-in notifications, tracker, tournaments, campaigns, loot admin) load it |
| Service worker | precached all 337 build files (5.1 MB) for every new visitor and on every deploy | precaches 9 shell files (168 KB); route chunks are cached on first use (`build-js`) |
| Shared-prop DB guards | 3 `Schema::hasTable` per request | try/catch around the query, zero cost on the happy path |

Rules that came out of this:

- Admin index tables load **only the columns they render** (`->select([...])`). If a trimmed model has
  `$appends` accessors (e.g. `Character::faction_color` needs `faction`), the relation must select what the
  accessor reads or the page 500s (`preventAccessingMissingAttributes`). `AdminIndexScaleTest` covers this.
- Anything that opens a socket or pulls a large library belongs behind a dynamic import, not in `app.ts`.

## Server configuration (nginx / Forge) — not in the repo

Hashed build assets and uploaded images are served with **no** `Cache-Control`/`Expires`, so repeat visits depend
on heuristic freshness and re-validate each file. Recommended:

```nginx
location /build/assets/ {            # content-hashed filenames
    expires 1y;
    add_header Cache-Control "public, immutable";
}
location ^~ /storage/ {              # card images etc. — filenames contain a UUID
    expires 30d;
    add_header Cache-Control "public";
}
location ^~ /font/   { expires 1y; add_header Cache-Control "public, immutable"; }
```

(Repeat the `X-Frame-Options`-style `add_header`s inside any location that declares its own, nginx does not
inherit them.) HTML and JSON are already gzip-encoded on prod; enabling brotli would reduce transfer further (not measured).

## Still open

- **Shared props on every logged-in visit**: 7 small queries (collection ids ×4, wishlists ×2, channels) are
  re-evaluated per navigation. They are cheap indexed lookups; if they ever matter, cache per user and bust on
  collection/wishlist writes, or move to `Inertia::optional` + a partial reload.
- **First load carries the route table twice**: once in `@routes` (114 KB, *all* routes — including the ~330
  `admin.*` names — even for guests) and once inside `data-page` (SSR needs `props.ziggy`). Trimming `@routes` to
  the `public` group for guests needs the table refreshed after login (full reload or merging the XHR prop).
- `/actions`, `/advanced`: `action_names` + `trigger_names` (≈ 53 KB) ship for filter dropdowns on every visit.
- `/factions/{faction}` returns the whole roster (no pagination) — fine at ≤ 50 characters, watch it if factions grow.
- Card images are JPEG at original size (~150 KB each); a resized/WebP variant would cut the heaviest pages
  (`/upgrades/crew` renders ~260 images) substantially.
- `public/images/TOS/Allegiances/` is empty (only `.gitkeep`), so the enum fallback logos 404 on prod until an
  admin uploads them; `AllegianceLogo.vue` degrades to a shield icon, but each page still issues the failing request.

## Tabletop (sight-guidelines, Nov 2026)

Measured with a Node benchmark on a heavy table (30 models, 40 pieces; a phone is several times slower than the figures here). Per-drag-frame work was the problem, since the board recomputes sight lines, placement problems and path cost on every pointer move:

| Work | Before | After | Change |
| --- | --- | --- | --- |
| `placementProblems` for 30 models | 15 ms | 1 ms | `supportFraction` rejects a base outside a shape's cached bounds; the disc sample pattern is built once |
| Side sight lines (`traceLine` x2) | 8.5 ms | 1.2 ms | the first blocked quarter inch is bisected, not walked |
| Walk path cost (`terrainAlongPath`) | 2.1 ms | 0.7 ms | bounds reject before `distanceToShape` |
| Table check, with zones | 205 ms | 25 ms | bounds reject on lanes, a 4.5" grid of sample points, bounds reject in the reachable-area flood |

Also: pointer moves are applied once per animation frame (`flushMove` in `TabletopBoard.vue`; `onUp` flushes the last one) because a phone reports moves faster than it draws. The saved layouts (up to 50, each with its full terrain) are an `Inertia::defer` prop on the Tabletop page and the game board, so the table appears without them; the Load dialog says "loading…" until they arrive. Open item: the board re-renders as one component on each drag frame.
