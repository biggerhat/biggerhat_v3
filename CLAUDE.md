# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

BiggerHat v3 is a Wyrd Games database and tool suite covering **two sibling game systems**: **Malifaux** and **The Other Side (TOS)**. Built with Laravel 12 + Vue 3 + Inertia.js. The active game system is resolved per-request and surfaced as `currentGameSystem` on shared Inertia data; a mode switcher in the header lets users flip between games.

## Development Commands

```bash
composer dev                                 # Start all dev services (server, queue, logs, vite)
php artisan test --parallel                  # Run tests in parallel
php artisan test tests/Feature/SomeTest.php  # Single test file
php artisan test --filter="test name"        # Single test by name
composer lint                                # PHP linting (Pint)
composer stan                                # PHP static analysis (PHPStan/Larastan)
npm run lint && npm run format               # Frontend linting + formatting
composer prepush                             # Full pre-push check (IDE helpers, stan, lint, tests)
composer ide-generate                        # Regenerate IDE helper files (after model changes)
npm run build                                # Build frontend assets
```

## Tech Stack

- **Backend**: Laravel 12, PHP 8.2+, SQLite (dev) / MySQL (prod)
- **Frontend**: Vue 3 + TypeScript, Inertia.js, Tailwind CSS 3, Vite
- **Testing**: Pest PHP (in-memory SQLite)
- **Permissions**: Spatie Permission. `super_admin` role bypasses all. Admin area (`/admin`) requires `admin.any` middleware.
- **PDF**: DomPDF via `barryvdh/laravel-dompdf`. `PDFController` → Blade at `resources/views/PDF/`.
- **Routing helpers**: Ziggy. **UI primitives**: Radix Vue, Reka UI, Lucide icons.

## Architecture

Inertia.js bridges Laravel controllers and Vue pages. Controllers return `Inertia::render('PageName', $data)` → `resources/js/pages/PageName.vue`. No separate API layer for page data.

Routes: `web.php` (public Malifaux), `tos.php` (public TOS under `/tos`), `api.php` (JSON under `/api`), `admin.php` (admin CRUD under `/admin`, TOS admin at `/admin/tos/*`), `auth.php`, `settings.php`. Admin uses `AppAdminLayout` (auto-selected by `app.ts` for `Admin/*` pages) with `AppAdminSidebar`.

Frontend: `@` alias → `resources/js/`. UI primitives in `resources/js/components/ui/` (excluded from ESLint). Faction colors in Tailwind config (`arcanists`, `bayou`, `guild`, etc.). Prettier: 150-char width, 4-space tabs, single quotes, Tailwind class sorting.

Enums in `app/Enums/`. Key: `FactionEnum`, `CharacterStationEnum`, `UpgradeDomainTypeEnum`. All implement `HasDefaultEnumMethods` + `UsesEnumLabels` + `UsesEnumSelectOptions`. API responses use Laravel Resources at `app/Http/Resources/API/`.

## Code Conventions

**Mirror existing patterns first.** Grep before introducing anything new. See [`docs/terminology.md`](docs/terminology.md) for locked-down user-facing strings.

### Models

- `protected $guarded = ['id']` universally — do **not** use `$fillable`. Safety lives at the FormRequest layer.
- `casts()` method form, not `$casts` property.
- Relations as methods with explicit return types + `@property-read` docblock annotations.
- Factories at `database/factories/<Domain>/<Model>Factory.php` with semantic state methods (`->solo()`, `->organizer()`, etc.).

### Controllers + FormRequests

- Every write action takes a FormRequest. Use `$request->validated()` only — never `all()` or `only()`.
- `authorize()` in FormRequest for route-scoped ownership checks.
- Always `use` statements — never inline `\App\Models\Foo` in code.

### Migrations

- MySQL 64-char identifier limit. Pass explicit short names for long table+column combos.
- Cross-migration FK ordering: add constraints in a follow-up migration; use `dropForeignSafe` macro in `down()`.
- Composite indexes for filtered-relation patterns when the matching `hasOne()->where(...)` relationship exists.

### Concurrency

State machines need `lockForUpdate` inside `DB::transaction`. Re-check state after acquiring the lock — the early check is a UX guard, not a concurrency guard. Stale concurrent requests must redirect cleanly.

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

### Authorization layers

Feature flag → permission → policy → ownership. Feature flags return 404 (not 403) to hide pre-release features. `view_*`/`edit_*`/`delete_*` Spatie permissions gate admin routes. Policies for organizer-only mutations. Ownership traits for per-row checks.

### Inertia / Vue

- **Navigation = `<Link>`**, not `router.get`. `router.post`/`router.delete` for mutations only.
- **`useForm` is NOT the project default.** ~30 callsites vs ~300 manual `router.post` + `ref()`. Don't migrate without explicit reason.
- **No browser dialogs.** Use `useConfirm` (`@/composables/useConfirm`) and `useToast` (`@/composables/useToast`).
- Phase-gated lazy props use `fn () => ...` closures. `Inertia::defer(...)` for genuinely async-expensive data only.
- Page layout: `<PageBanner title>` with `#subtitle` and `#actions` slots. Faction tints via `factionBackground()` from `@/composables/useFactionColor`.
- TypeScript: inline page-prop interfaces in `<script setup>` — match the controller payload shape exactly. Don't share interfaces across pages unless a component is reused.

### Tests (Pest)

Every controller method gets a feature test: index permission gate, store happy path, store validation rejection, update happy + permission, delete happy + permission. Every FormRequest gets rejection tests per non-trivial rule. Factories usable in isolation. Concurrency edge tests for stateful flows.

### Verification before reporting

Run `composer prepush` before claiming work is done. Verify reviewer findings against actual code before acting.

## The Other Side (TOS)

**Before any TOS work, read [`docs/tos-conventions.md`](docs/tos-conventions.md)** — code locations, game-system resolution, data-model divergences, conventions, and per-PR test expectations. Key principle: mirror Malifaux patterns unless the TOS rulebook demands otherwise.
