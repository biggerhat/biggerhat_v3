---
name: tos-scaffold
description: Scaffold a new The Other Side (TOS) catalog entity end-to-end — model, migration, FormRequest, admin controller, factory, admin Vue form, and the mandated feature tests — mirroring the Malifaux equivalent. Use when adding a new TOS database entity (e.g. a new card type, unit attribute table, or admin-managed catalog).
---

# TOS entity scaffolder

Generate the full, convention-correct set of files for a new TOS catalog entity. **Always read `docs/tos-conventions.md` first** and mirror the closest existing TOS entity (and its Malifaux counterpart) rather than inventing structure.

## Before generating
1. Read `docs/tos-conventions.md` (locations, divergences, conventions, per-PR test expectations).
2. Pick the closest existing TOS entity as a template (e.g. `Stratagem`, `Unit`, `Asset`) and open its model + controller + FormRequest + factory + admin form + test. Mirror it.
3. Confirm the entity's columns, relations, and whether it has an image, a slug, special-rule pivots, or two-sided data.

## Files to produce (mirror the template's exact shape)
- **Model** `app/Models/TOS/<Entity>.php` — `protected $guarded = ['id']`, `casts()` method form, `getRouteKeyName() => 'slug'` (unless id-bound like Trigger), `use GeneratesTosSlug`, relations as typed methods with `@property-read` docblocks, `newFactory()`. Add a `booted()` cascade hook if it owns pivots (test env runs SQLite with FKs disabled).
- **Migration** `database/migrations/*create_tos_<table>*` — `tos_` prefix; explicit short index/FK names (64-char MySQL limit); cross-table FKs in a follow-up migration with `dropForeignSafe` in `down()`.
- **FormRequest(s)** `app/Http/Requests/TOS/Admin/{Store,Update}<Entity>Request.php` — `authorize()` for ownership, rules per column; `prohibits`/`required_without` where the rulebook demands mutual exclusivity.
- **Admin controller** `app/Http/Controllers/TOS/Admin/<Entity>AdminController.php` — `index` selects explicit columns (no long-text in lists), create/edit pass lazy `fn () => …` select-option props, store/update use `$request->validated()` only, `view_tos_*`/`edit_tos_*`/`delete_tos_*` middleware on routes.
- **Factory** `database/factories/TOS/<Entity>Factory.php` — usable in isolation, semantic states matching the rulebook (`->commander()`, `->fireteam()`, `->neutralFor(...)`).
- **Admin Vue form** `resources/js/pages/Admin/TOS/<Entity>s/{Index,Form}.vue` — inherits `AppAdminLayout`; use `SearchableMultiselect` for pivots; `TosText`/`TosSuits` for rulebook tokens; public index uses `useListFiltering` + `ListSearchBar`.
- **Feature test** `tests/Feature/TOS/<Entity>AdminControllerTest.php` — the seven mandated cases: index permission gate, store happy, store validation rejection, update happy, update permission gate, delete happy, delete permission gate; plus relation/cascade tests and a FormRequest rejection per non-trivial rule. Model the shape on `tests/Feature/TOS/StratagemAdminControllerTest.php`.
- Wire admin routes into `routes/tos.php` (and a seeder in `database/seeders/TOS/` if it needs catalog data, registered in `DatabaseSeeder`).

## After generating
- `./vendor/bin/sail composer ide-generate` (model changed).
- Run the verify-changes skill, or at minimum: Pint, PHPStan, ESLint, build, and the new test file.
- If a public-facing API is expected, add a V1 controller + Resource per the "Public API (V1)" row in the conventions doc.
