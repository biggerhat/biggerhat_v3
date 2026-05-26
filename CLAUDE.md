# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

BiggerHat v3 is a Wyrd Games database and tool suite covering **two sibling game systems** sharing one codebase: **Malifaux** (the original — characters, upgrades, keywords, crews, tournaments, game tracker, etc.) and **The Other Side (TOS)** (allegiances, units, stratagems, assets, etc.). The top-level game system is resolved per request and surfaced as `currentGameSystem` on the shared Inertia data; a mode switcher in the header lets users flip between games. Built with Laravel 12 + Vue 3 + Inertia.js.

## Development Commands

```bash
# Start all dev services (server, queue, logs, vite) concurrently
composer dev

# Run tests in parallel
php artisan test --parallel

# Run a single test file
php artisan test tests/Feature/SomeTest.php

# Run a single test by name
php artisan test --filter="test name here"

# PHP linting (Pint - PSR-12 style fixer)
composer lint

# PHP static analysis (PHPStan via Larastan)
composer stan

# Frontend linting + formatting
npm run lint
npm run format

# Full pre-push check (IDE helpers, stan, lint, tests)
composer prepush

# Regenerate IDE helper files (run after model changes)
composer ide-generate

# Build frontend assets
npm run build
```

## Tech Stack

- **Backend**: Laravel 12, PHP 8.2+, SQLite (dev) / MySQL (prod)
- **Frontend**: Vue 3 + TypeScript, Inertia.js, Tailwind CSS 3, Vite
- **Testing**: Pest PHP (in-memory SQLite)
- **PDF**: DomPDF via `barryvdh/laravel-dompdf`
- **Permissions**: Spatie Permission. `super_admin` role grants all permissions. Admin area (`/admin`) is accessible to any user with at least one admin permission (enforced by `EnsureHasAdminPermission` middleware, aliased as `admin.any`).
- **Routing helpers**: Ziggy (provides typed Laravel route helpers in JS)
- **UI primitives**: Radix Vue, Reka UI, Lucide icons

## Architecture

### Server-Side Rendering Flow

Inertia.js bridges Laravel controllers and Vue pages. Controllers return `Inertia::render('PageName', $data)` which renders the corresponding Vue component at `resources/js/pages/PageName.vue`. No separate API layer needed for page data — Inertia handles serialization.

### Route Organization

Routes are split across files in `routes/`:
- `web.php` — Public Malifaux pages (characters, factions, keywords, tools/pdf)
- `tos.php` — Public TOS pages under `/tos` (allegiances, units, stratagems, assets, abilities, actions, triggers, special rules, allegiance cards)
- `api.php` — JSON API endpoints under `/api` (used by external bot and PDF tool)
- `admin.php` — Admin CRUD under `/admin`, protected by `auth`, `verified`, and `admin.any` middleware. Individual routes further gate by specific `permission:*` checks. TOS admin routes live in a nested `/admin/tos/*` group inside this file. The admin area uses its own Inertia layout (`AppAdminLayout`) selected automatically by `app.ts` based on page path (`Admin/*`), and has a dedicated sidebar (`AppAdminSidebar`) with sections grouped into Game Data / Content / Community / Access / TOS — Units / TOS — Cards.
- `auth.php` — Authentication flows
- `settings.php` — User settings

### Enum-Driven Domain

The app uses PHP backed enums extensively (`app/Enums/`). Key enums:
- `FactionEnum` — 8 factions (Arcanists, Bayou, Guild, etc.) with color/logo methods
- `CharacterStationEnum` — Master, Minion, Peon (Henchman is a Characteristic, not a Station)
- `UpgradeDomainTypeEnum` — Character vs Crew upgrade distinction
- `UpgradeTypeEnum`, `UpgradeLimitationEnum`, `SuitEnum`, `BaseSizeEnum`

Enums implement `HasDefaultEnumMethods` interface and use `UsesEnumLabels` + `UsesEnumSelectOptions` traits for consistent label/select-option generation.

### Model Relationships

Core model is `Character` with relationships to:
- `Miniature` (hasMany) — sculpt variants of a character
- `Keyword`, `Marker`, `Upgrade` — via polymorphic MorphToMany

`Upgrade` model has two domains (character upgrades vs crew upgrades) distinguished by `UpgradeDomainTypeEnum`, with scopes `forCharacters()` and `forCrews()`.

Reusable relationship logic lives in `app/Traits/` (e.g., `UsesUpgrades`, `UsesMiniatures`, `UsesCharacters`).

### Admin Pattern

All admin controllers follow the same CRUD pattern: `index`, `create`, `store`, `edit`, `update`, `delete`. Each resource has a corresponding admin controller in `app/Http/Controllers/Admin/` and Vue pages in `resources/js/pages/Admin/`.

### Frontend Conventions

- Path alias: `@` maps to `resources/js/`
- UI primitives live in `resources/js/components/ui/` (excluded from ESLint)
- Faction-specific colors are defined in the Tailwind config with names like `arcanists`, `bayou`, `guild`, etc.
- Dark mode uses Tailwind's `class` strategy
- Prettier config: 150 char width, 4-space tabs, single quotes, Tailwind class sorting

### PDF Generation

`PDFController` renders Blade templates (`resources/views/PDF/`) to HTML, then converts to PDF via DomPDF. The PDF tool UI is a Vue page at `resources/js/pages/PDF/`.

### API Resources

API responses use Laravel API Resources (`app/Http/Resources/API/`) for data transformation. These serve the external bot integration and the PDF generation tool.

## Code Conventions

**When in doubt, mirror existing patterns.** Before introducing a new pattern, grep the codebase for the equivalent problem — there's almost always a precedent. Diverging from convention to chase a "best practice" trade-off is rarely worth the inconsistency cost.

**User-facing terminology** is locked down in [`docs/terminology.md`](docs/terminology.md). When a string contradicts the glossary, fix the string. When you think the glossary is wrong, edit it there first.

### Models

- `protected $guarded = ['id']` is the universal pattern (every model). Do **not** switch to `$fillable` — it would be inconsistent with 40+ existing models. Mass-assignment safety lives at the FormRequest layer (`$request->validated()`), not at the model.
- Use the `casts()` method form (not `$casts` property) so cast definitions are typed and IDE-discoverable.
- Define relations as methods with explicit return types (`BelongsTo`, `HasMany`, `HasOne`, `MorphToMany`). Always annotate `@property-read` on the model docblock.
- Factories live at `database/factories/<Domain>/<Model>Factory.php` and are referenced via `newFactory()`. Add semantic state methods (`->solo()`, `->organizer()`, `->signature()`) for self-contained test setup.

### Controllers + FormRequests

- Every write action takes a FormRequest. Use `$request->validated()` — never `$request->all()` or `$request->only()`.
- FormRequest `authorize()` runs before the controller method — put role/ownership checks there when they're route-scoped (e.g. `$this->user()->can('update', $campaign)`).
- For organizer-only mutations, prefer `$this->authorize('update', $model)` calls backed by Policies. Per-crew/per-row ownership checks live in shared traits (e.g. `App\Traits\Campaign\AuthorizesCampaignAccess`) — extract any auth pattern duplicated across 3+ controllers.
- Always use `use` statements for class references; never inline `\App\Models\Foo` in code. Fully-qualified paths only appear in static analysis annotations.

### Migrations

- MySQL caps identifier names at 64 chars. When Laravel's auto-generated `<table>_<col1>_<col2>_index` or `<table>_<col>_foreign` would exceed this (long table + long column combos), pass an explicit short name: `$table->index(['a', 'b'], 'idx_short_name')` or `$table->foreignId('col')->constrained('table', 'id', 'fk_short_name')`. Audit with the project's identifier-length script before pushing.
- For columns that need FKs but reference tables created later (cross-migration ordering), add the constraint in a follow-up migration. Use the `dropForeignSafe` blueprint macro in `down()` so SQLite test runs (which disable FKs) don't barf.
- Composite indexes on filtered-relation patterns (e.g. `(parent_id, is_active, current)`) — create them when the matching relationship exists (`hasOne()->where(...)`) so the relationship query hits an index.

### Concurrency

- Any state machine where requests advance through phases (Aftermath wizard, tournament rounds, game turns) needs **`lockForUpdate` inside a `DB::transaction`** at the start of each mutation handler. Pattern:
  ```php
  $advanced = false;
  DB::transaction(function () use ($model, &$advanced) {
      $locked = Model::lockForUpdate()->find($model->id);
      if (! $locked || $locked->status !== 'open') return;
      $locked->update([...]);
      $advanced = true;
  });
  if (! $advanced) return redirect()->back();
  ```
- Re-check the expected state (phase, status) **after** acquiring the lock — the early-return check at the top of a handler is a UX guard, not a concurrency guard.
- Stale concurrent requests should redirect cleanly, not error. Double-clicks are user-realistic.

### Authorization layers

Each feature stacks: **feature flag → permission → policy → ownership**. Example for Campaign Mode:

1. **Feature flag** (`m4e-campaign-mode` via `CampaignAccess::canUse()`) — `EnsureCampaignAccess` middleware returns 404 (not 403) to hide the feature pre-release.
2. **Permission** — `view_*`/`edit_*`/`delete_*` Spatie permissions gate admin catalog routes; `use_campaign_mode` gates the public UI. `super_admin` role bypasses everything.
3. **Policy** (`CampaignPolicy`) — organizer-only mutations.
4. **Ownership trait** (`AuthorizesCampaignAccess`) — per-crew / per-aftermath checks within an authorized campaign.

### Inertia / Vue conventions

- **Navigation = `<Link>`, not `router.get`.** Wrap the Button:
  ```vue
  <Link :href="route('campaigns.show', campaign.id)">
      <Button variant="outline">View</Button>
  </Link>
  ```
  This gives prefetch, right-click-open-in-new-tab, keyboard accessibility, and active-route handling. Use `router.post` / `router.delete` for mutations — but `router.get` for navigation is the wrong pattern.
- **`useForm` composable is NOT the project default.** ~30 callsites vs ~300 manual `router.post` + `ref()`. Don't migrate without explicit reason.
- **No browser dialogs.** `window.confirm`/`alert`/`prompt` are banned. Use `useConfirm` from `@/composables/useConfirm` (singleton Dialog) and `useToast` from `@/composables/useToast` (vue-sonner). Search results for `confirm(` in `.vue`/`.ts` files should turn up zero outside comments.
- **Phase-gated / per-tab lazy props** use `fn () => ...` closures so partial reloads only fetch the data the current phase needs:
  ```php
  'equipment_catalog' => fn () => $aftermath->current_phase === 3 ? Equipment::all() : null,
  ```
- **`Inertia::defer(...)`** is for genuinely async-expensive data (Tournament Manage stats, Collection extras) — renders a "loading…" placeholder, hydrates post-mount. See `TournamentController` for the canonical example.
- **Page layout** uses `<PageBanner title>` with `#subtitle` and `#actions` slots for the top hero. Faction-tinted strips use `factionBackground()` from `@/composables/useFactionColor`.
- **TypeScript:** define page-prop interfaces inline in the `<script setup>` — match the controller's `inertia()` payload shape exactly. Don't share interfaces across pages unless the same component is reused.

### Tests (Pest)

- Every controller method gets a feature test: index permission gate, store happy path, store validation rejection, update happy + permission, delete happy + permission.
- Every FormRequest gets at least one rejection test per non-trivial rule.
- Factories must be usable in isolation via semantic states.
- Concurrency edge tests for stateful flows: "lockAndAdvance is a no-op when the phase has already moved on", "refuses mutations once locked", "self-heals when crew is missing".
- Self-heal idempotency tests cover backfill paths (`firstOrCreate` patterns that run on every visit).

### Verification before reporting

Pre-push gate: `composer prepush` runs `composer stan` + `composer lint` + `php artisan test`. Run the full suite before claiming work is done. Reviewer agents over-claim — always verify findings against the actual code before acting.

## The Other Side (TOS)

TOS is the sibling game system living alongside Malifaux in the same codebase.

### **Guiding principle: mirror Malifaux patterns**

**When adding or changing anything on the TOS side, first check how the Malifaux side solves the equivalent problem and mirror that pattern.** This applies to controller shape, FormRequest conventions, admin form component structure, seeder style, test coverage, and UI conventions. Diverge only when the TOS rulebook demands it (e.g., two-sided unit cards, the Fireteam/Squad/Combined Arms rule pivot, Neutral hireability via type-restricted units, the separate symbol font). If you're unsure whether a pattern applies, read the Malifaux equivalent before writing TOS code — the matching file almost always exists.

### Where TOS code lives

| Concern | Location |
|---|---|
| Models | `app/Models/TOS/*` |
| Public controllers | `app/Http/Controllers/TOS/Database/*` |
| Admin controllers | `app/Http/Controllers/TOS/Admin/*` |
| FormRequests | `app/Http/Requests/TOS/Admin/*` |
| Enums | `app/Enums/TOS/*` |
| Traits | `app/Traits/TOS/*` (`GeneratesTosSlug`, `HandlesTosImageUpload`) |
| Migrations | `database/migrations/*tos_*` (tables use the `tos_` prefix) |
| Factories | `database/factories/TOS/*` |
| Seeders | `database/seeders/TOS/*` (wired in `DatabaseSeeder::run()`) |
| Public Vue pages | `resources/js/pages/TOS/*` |
| Admin Vue pages | `resources/js/pages/Admin/TOS/*` (inherits `AppAdminLayout` automatically) |
| Domain Vue components | `resources/js/components/TOS/*` (CardImage, FlipCard, UnitCard, UnitStatBlock) |
| TOS icon/text components | Top-level `resources/js/components/TosIcon.vue`, `TosText.vue`, `TosSuits.vue`, `TosMarginCost.vue`, `AllegianceLogo.vue` |
| Symbol font | `public/font/1E-TOS-Symbols.ttf`, registered as `TOS-Symbols` in `resources/css/app.css` |

### Game-system resolution

`HandleInertiaRequests::resolveGameSystem(Request)` determines the active system per-request:

1. URL first: `/tos` or `/tos/*` or `/admin/tos/*` → `tos`
2. Cookie fallback `preferred_game_system` on game-agnostic URLs (profile, settings, auth)
3. Default `malifaux`

The resolved system is exposed on the shared Inertia data as `currentGameSystem` (an object with `slug`, `label`, `home_route`, and `switch_to`). `GameSystemSwitcher.vue` in `AppSidebarHeader.vue` renders the pill control; it's currently gated on `is_super_admin` via `v-if` while TOS is pre-release. The public sidebar (`AppSidebar.vue`) is context-aware and shows TOS nav when `currentGameSystem === 'tos'`.

### Key data-model divergences

- **Two-sided Unit Cards.** Every unit has Standard + Glory sides with different AVs, abilities, actions, and triggers per side. Stored in `tos_unit_sides` (unique on `unit_id, side`), not on the unit row.
- **Allegiance & Syndicate share one table.** `tos_allegiances` with `is_syndicate` flag and a `type` (Earth/Malifaux). Cross-allegiance hires within a type fall under the Neutral pool (`Unit.restriction`) — see `Unit::hireableInto`.
- **Neutral Units.** `Unit.restriction` (nullable `AllegianceTypeEnum`) flags a unit as hireable by any Allegiance of that type. `Unit::hireableInto(Allegiance)` scope returns units with direct pivot attachment OR matching restriction. FormRequests require at least one of `allegiance_ids` or `restriction` via `required_without`.
- **Special Unit Rules are pivoted**, not a discriminator column. `tos_unit_special_rule` carries a `parameters` JSON per-rule (Fireteam size, Squad count, Combined Arms child, Adjunct size, Reserves X).
- **Combined Arms** uses `Unit.combined_arms_child_id` self-FK. Top-level unit listings must filter via `Unit::notCombinedArmsChild()`.
- **Triggers are either suit-driven OR margin-driven** — enforced by `prohibits:margin_cost` / `prohibits:suits` in the FormRequests and a Suits/Margin/None segmented control in `TriggerForm.vue`.
- **UnitSculpt** has `front_image`, `back_image`, `combination_image` (same column shape as Malifaux `Miniature`). The combo image is auto-generated from front+back via GD in `SculptAdminController::regenerateComboImage()` using `HandlesTosImageUpload::generateTosComboImage()`.
- **TOS Packages share Malifaux's `Package` model** via the polymorphic `packageables` pivot. `Package::tosUnits()` + `Unit::packages()` relations let a Starter Box list TOS contents alongside Malifaux entries.

### TOS conventions worth knowing

- **Slugs**: the `GeneratesTosSlug` trait auto-generates `slug` on `creating` from the `name` column. Most entities append `-Str::random(4)` for disambiguation (two "Attack" actions don't collide). Allegiance and SpecialUnitRule use canonical slugs (override `slugNeedsRandomSuffix(): bool` to return `false`).
- **Route-model binding**: every TOS model with a slug exposes `getRouteKeyName() => 'slug'` — admin and public routes bind by slug. Trigger is the exception (its slug is unique only within an action, so it stays id-bound).
- **Image uploads** go through `HandlesTosImageUpload` (storeTosImage / deleteTosImage / generateTosComboImage) — never inline `Storage::put` calls.
- **Permissions** follow the naming `view_tos_<entity>`, `edit_tos_<entity>`, `delete_tos_<entity>` (see `PermissionEnum` + `PermissionGroupEnum`). `super_admin` gets everything via the seeder's `syncPermissions(Permission::all())`.
- **Icons / text with rulebook tokens**: body text rendered via `TosText` tokenizes `{{crow}}`, `{{magic}}`, `{{morale}}`, `{{margin5}}`, `{{turncard}}`, etc. Use `TosSuits` for bare single-letter suit strings (`"RM"` → per-char icons) and `TosMarginCost` for the numeric margin badge.
- **Payload slimming**: admin `index()` methods select explicit column lists (no `body`/`effect`/`description`/`lore_text` in list payloads). Admin form selects use lazy `fn () => …` props for partial-reload friendliness (match `CharacterAdminController`).
- **Search bars**: every TOS public index page uses `useListFiltering` + `ListSearchBar` for URL-synced name search. The server controller reads `name_search` from the query string and applies a `LIKE %search%` filter.
- **Cascade hooks**: test env runs SQLite with FKs disabled (`.env.testing DB_FOREIGN_KEYS=false`), so production `cascadeOnDelete` FKs are mirrored by manual `booted()` hooks on `Unit`, `Ability`, `Action` to cover pivot cleanup.

### Per-PR test expectations for TOS

Every TOS admin controller must have feature tests covering: index permission gate, store happy path, store validation rejection, update happy path, update permission gate, delete happy path, delete permission gate. Every model gets a test for its relations and cascades. FormRequests get at least one rejection test per non-trivial rule. Factories must be usable in isolation via semantic states (e.g. `->commander()`, `->fireteam()`, `->neutralFor(AllegianceTypeEnum::Earth)`). Follow the shape of `tests/Feature/TOS/StratagemAdminControllerTest.php` and `tests/Feature/Tournament/*` as references.
