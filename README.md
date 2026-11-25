# BiggerHat

[![CI](https://github.com/biggerhat/biggerhat_v3/actions/workflows/ci.yml/badge.svg?branch=main)](https://github.com/biggerhat/biggerhat_v3/actions/workflows/ci.yml)

A database and tool suite for Wyrd Games' tabletop miniatures games, live at **[biggerhat.net](https://biggerhat.net)**. It covers two sibling game systems, **Malifaux** and **The Other Side**, with a switcher in the header to move between them.

## What's in it

| Area | What it does |
|---|---|
| **Database** | Browse characters, abilities, actions, upgrades, keywords, packages and more, for both games, with search and an admin area to maintain the data |
| **Crew Builder** | Build and share crews, with the hiring rules checked as you go |
| **Game Tracker** | Track a live game turn by turn: activations, conditions, scoring, a log, and an optional tabletop board |
| **Tournament Tracker** | Swiss pairings, standings and tiebreakers, linked to the Game Tracker |
| **Collection and Wishlist** | Record what you own per model, with a build stage and notes for each |
| **Campaign and Cooperative modes** | Multi-week campaign tools and the cooperative reference data (feature-flagged) |
| **Bonanza Brawl** | The solo loot-deck format: deck reference, tracker, printable PDF |
| **Custom Card Creator** | Homebrew cards rendered in the browser |
| **Help** | In-app guides for the larger tools, at `/help` |

Each feature has a map of its files, models and gotchas in [`docs/features/`](docs/features/).

## Tech stack

- **Backend:** Laravel 12, PHP 8.2+ (production runs 8.2), MySQL in production, SQLite for local development and tests, Redis queues with Horizon
- **Frontend:** Vue 3 and TypeScript, Inertia.js with server-side rendering, Tailwind CSS 3, Vite, Reka UI and Lucide icons
- **Testing:** Pest (the suite also runs on a real MySQL 8 in CI), Node's test runner for the browser-side logic
- **Other:** Ziggy for routes, Spatie Permission for roles, Browsershot and DomPDF for PDFs, Reverb for websockets

## Getting started

You need PHP 8.2 or newer, Composer, Node 22 and npm.

```bash
git clone https://github.com/biggerhat/biggerhat_v3.git
cd biggerhat_v3
composer install
npm install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite   # .env.example uses SQLite; to use MySQL, set the DB_* values instead
php artisan migrate
php artisan db:seed --class=RoleSeeder
php artisan db:seed --class=PermissionSeeder
composer dev                     # server, queue worker (Horizon), logs and Vite together
```

`composer dev` runs the queue through Horizon, which needs Redis. Without it, set `QUEUE_CONNECTION=sync` in `.env`.
The site is at the URL `php artisan serve` prints. Run `composer dev:ssr` instead to try server-side rendering the way production does (see [`docs/ssr.md`](docs/ssr.md)).

**Game data.** A fresh database has no characters, upgrades or schemes. `php -d memory_limit=512M artisan app:seed-from-prod --skip-images` copies them from the public API at biggerhat.net. The API is rate limited and the command waits between endpoints, so run it once, on its own, and expect it to
take a few minutes. `php artisan db:seed` does the same plus the images, and takes much longer. `app:import-wyrd-*` fill in the packages from Wyrd's store.

**An admin account.** Register on the site, then give yourself the role: `php artisan tinker --execute='App\Models\User::where("email", "you@example.com")->first()->assignRole("super_admin");'`.
It is the role that opens `/admin`, Horizon and the log viewer.

A Laravel Sail setup is also included (`docker-compose.yml`).

## Checks

CI runs these on every pull request, and you should run them before you push:

```bash
vendor/bin/pint --test        # PHP style
composer stan                 # PHPStan / Larastan
npm run format:check          # Prettier
npm run lint:check            # ESLint
npm run typecheck             # vue-tsc, compared with scripts/typecheck-baseline.json
npm run test:js               # browser-side unit tests
php artisan test --parallel   # the PHP suite
scripts/test-mysql.sh         # the PHP suite on MySQL (needs Docker, about 4 minutes)
```

`composer prepush` runs the PHP side and the JS tests. `composer lint` and `npm run format` fix files rather than check them.
What CI runs, how a merge deploys, and the Discord posts are in [`docs/ci.md`](docs/ci.md).

## Security

Do not report a vulnerability in a public issue: use **Security > Report a vulnerability** on GitHub ([`SECURITY.md`](SECURITY.md)). Never commit secrets; `.env` is ignored and `.env.example` holds names only.
Every write action goes through a FormRequest with `authorize()`, and CI runs `composer audit` and `npm audit` on each pull request.

## Contributing

`main` is protected: changes go in through a pull request, and `ci-pass` must be green. The pull request template asks for release notes (what players see; `none` for refactors), how you verified the change, and whether the in-app help was updated. If you change the Crew Builder, Game Tracker, Tabletop or Collection, update their help topic in the same change ([`docs/features/help.md`](docs/features/help.md)).

Conventions for models, controllers, migrations, Vue pages and tests are in [`CLAUDE.md`](CLAUDE.md). Wording that is fixed across the site is in [`docs/terminology.md`](docs/terminology.md). For The Other Side work, read [`docs/tos-conventions.md`](docs/tos-conventions.md) first.

## Documentation

- [`docs/features/`](docs/features/): one map per feature
- [`docs/ci.md`](docs/ci.md): CI, deploys, branch protection, Discord posts
- [`docs/ssr.md`](docs/ssr.md): what server-side rendering needs and what has broken it
- [`docs/design-system.md`](docs/design-system.md): surfaces, navigation, accessibility, type scale
- [`docs/performance.md`](docs/performance.md): profiling, measurements, caching
- [`docs/ui-audit.md`](docs/ui-audit.md): the browser harness for accessibility and layout checks

## Licence and trademarks

The code is MIT licensed, as declared in `composer.json`. Malifaux, The Other Side and their cards, art and rules are the property of Wyrd Games. This is an unofficial fan project and is not affiliated with or endorsed by Wyrd.
