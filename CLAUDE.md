# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

BiggerHat v3 is a Malifaux miniatures game database and tool suite. It provides a character/upgrade browser, faction pages, PDF card generation, and a full admin CRUD interface. Built with Laravel 12 + Vue 3 + Inertia.js.

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
- **Permissions**: Spatie Permission (role: `super_admin` for admin access)
- **Routing helpers**: Ziggy (provides typed Laravel route helpers in JS)
- **UI primitives**: Radix Vue, Reka UI, Lucide icons

## Architecture

### Server-Side Rendering Flow

Inertia.js bridges Laravel controllers and Vue pages. Controllers return `Inertia::render('PageName', $data)` which renders the corresponding Vue component at `resources/js/pages/PageName.vue`. No separate API layer needed for page data — Inertia handles serialization.

### Route Organization

Routes are split across files in `routes/`:
- `web.php` — Public pages (characters, factions, keywords, tools/pdf)
- `api.php` — JSON API endpoints under `/api` (used by external bot and PDF tool)
- `admin.php` — Admin CRUD under `/admin`, protected by `auth`, `verified`, and `role:super_admin` middleware
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
