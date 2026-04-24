# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

BiggerHat v3 is a Wyrd Games database and tool suite covering **two sibling game systems** sharing one codebase: **Malifaux** (the original — characters, upgrades, keywords, crews, tournaments, game tracker, etc.) and **The Other Side (TOS)** (allegiances, units, envoys, stratagems, assets, etc.). The top-level game system is resolved per request and surfaced as `currentGameSystem` on the shared Inertia data; a mode switcher in the header lets users flip between games. Built with Laravel 12 + Vue 3 + Inertia.js.

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
- `tos.php` — Public TOS pages under `/tos` (allegiances, units, envoys, stratagems, assets, abilities, actions, triggers, special rules, allegiance cards)
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

## The Other Side (TOS)

TOS is the sibling game system living alongside Malifaux in the same codebase.

### **Guiding principle: mirror Malifaux patterns**

**When adding or changing anything on the TOS side, first check how the Malifaux side solves the equivalent problem and mirror that pattern.** This applies to controller shape, FormRequest conventions, admin form component structure, seeder style, test coverage, and UI conventions. Diverge only when the TOS rulebook demands it (e.g., two-sided unit cards, the Fireteam/Squad/Combined Arms rule pivot, Envoy/Neutral hireability, the separate symbol font). If you're unsure whether a pattern applies, read the Malifaux equivalent before writing TOS code — the matching file almost always exists.

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
- **Allegiance & Syndicate share one table.** `tos_allegiances` with `is_syndicate` flag and a `type` (Earth/Malifaux). Envoys plug into the cross-allegiance hiring mechanic via `Envoy.restriction`.
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
