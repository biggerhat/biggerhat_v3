# The Other Side (TOS) Conventions

TOS is the sibling game system living alongside Malifaux in the same codebase.

## Guiding principle: mirror Malifaux patterns

**When adding or changing anything on the TOS side, first check how the Malifaux side solves the equivalent problem and mirror that pattern.** This applies to controller shape, FormRequest conventions, admin form component structure, seeder style, test coverage, and UI conventions. Diverge only when the TOS rulebook demands it (e.g., two-sided unit cards, the Fireteam/Squad/Combined Arms rule pivot, Neutral hireability via type-restricted units, the separate symbol font). If you're unsure whether a pattern applies, read the Malifaux equivalent before writing TOS code — the matching file almost always exists.

## Where TOS code lives

| Concern | Location |
|---|---|
| Models | `app/Models/TOS/*` |
| Public controllers | `app/Http/Controllers/TOS/Database/*` |
| Admin controllers | `app/Http/Controllers/TOS/Admin/*` |
| Public API (V1) | `app/Http/Controllers/API/V1/TOS/*` + `app/Http/Resources/API/V1/TOS/*`, routed under the `tos` group in `routes/api_v1.php`. Mirror the Malifaux V1 controllers: paginated `index` + `show`, slug binding, a Resource for catalog reads, and a `@tags` PHPDoc so Scramble documents it. Build endpoints (Companies/Garrisons) mirror `CrewBuildController` — public-only, fetched by `share_code`, flat paginator shape. |
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

## Game-system resolution

`HandleInertiaRequests::resolveGameSystem(Request)` determines the active system per-request:

1. URL first: `/tos` or `/tos/*` or `/admin/tos/*` → `tos`
2. Cookie fallback `preferred_game_system` on game-agnostic URLs (profile, settings, auth)
3. Default `malifaux`

The resolved system is exposed on the shared Inertia data as `currentGameSystem` (an object with `slug`, `label`, `home_route`, and `switch_to`). `GameSystemSwitcher.vue` in `AppSidebarHeader.vue` renders the pill control; it's currently gated on `is_super_admin` via `v-if` while TOS is pre-release. The public sidebar (`AppSidebar.vue`) is context-aware and shows TOS nav when `currentGameSystem === 'tos'`.

## Key data-model divergences

- **Two-sided Unit Cards.** Every unit has Standard + Glory sides with different AVs, abilities, actions, and triggers per side. Stored in `tos_unit_sides` (unique on `unit_id, side`), not on the unit row.
- **Allegiance & Syndicate share one table.** `tos_allegiances` with `is_syndicate` flag and a `type` (Earth/Malifaux). Cross-allegiance hires within a type fall under the Neutral pool (`Unit.restriction`) — see `Unit::hireableInto`.
- **Neutral Units.** `Unit.restriction` (nullable `AllegianceTypeEnum`) flags a unit as hireable by any Allegiance of that type. `Unit::hireableInto(Allegiance)` scope returns units with direct pivot attachment OR matching restriction. FormRequests require at least one of `allegiance_ids` or `restriction` via `required_without`.
- **Special Unit Rules are pivoted**, not a discriminator column. `tos_unit_special_rule` carries a `parameters` JSON per-rule (Fireteam size, Squad count, Combined Arms child, Adjunct size, Reserves X).
- **Combined Arms** uses `Unit.combined_arms_child_id` self-FK. Top-level unit listings must filter via `Unit::notCombinedArmsChild()`.
- **Triggers are either suit-driven OR margin-driven** — enforced by `prohibits:margin_cost` / `prohibits:suits` in the FormRequests and a Suits/Margin/None segmented control in `TriggerForm.vue`.
- **UnitSculpt** has `front_image`, `back_image`, `combination_image` (same column shape as Malifaux `Miniature`). The combo image is auto-generated from front+back via GD in `SculptAdminController::regenerateComboImage()` using `HandlesTosImageUpload::generateTosComboImage()`.
- **TOS Packages share Malifaux's `Package` model** via the polymorphic `packageables` pivot. `Package::tosUnits()` + `Unit::packages()` relations let a Starter Box list TOS contents alongside Malifaux entries.

## Company builder: format + Envoy (June 2026 errata)

The Company builder (`CompanyController::view`/`addUnit`/`attachAsset`/`addStratagem`, `resources/js/pages/TOS/Companies/View.vue`) is **format- and Envoy-aware**. Two concepts to keep straight:

- **`GarrisonFormatEnum` carries two distinct sets of methods.** The original `maxCommanders()` / `scripBudget()` / `stratagemCount()` describe a tournament **Garrison pool** (how much you *declare* — 2–3 commanders, 40–75 Scrip). The added `commandersFielded()` / `scripBonus()` describe how a single **Company plays** the format (the rulebook's "game size" = number of Commanders fielded; budget = sum of those Commanders' Scrip + a flat bonus). **Don't cross-wire them** — a Company reads `commandersFielded`/`scripBonus`; a Garrison reads the pool methods. Per-format commander counts come from the Fields of Glory packet (Theater of War = up to 2, No Man's Land = 1).
- **Envoy = a second Allegiance** (`Company.envoy_allegiance_id`), not a card (Envoy Cards were removed). Rules it drives:
  - **Hireable pool** = `Unit::hireableFor($primary, $envoy)` — the full Primary pool plus the Envoy's **Squad units only**. Assets match Primary ∪ Envoy. The picker chip is Direct / Envoy / Neutral.
  - **Envoy spend cap** — Envoy-sourced Units + Assets (in the Envoy Allegiance, not the Primary) are capped at **50%** of total Scrip (`Company::envoyScripSpent()` / `envoyScripCap()` / `belongsToEnvoyOnly()`).
  - **Allegiance Card tiers** — when an Allegiance is the Envoy, only its **Standard tier** applies; the **Primary tier** (`primaryAbilities`/`primaryActions`/`primaryTriggers`, the card's "Primary Only" section) is suppressed.
- **Commanders** carry the `commander` Special Unit Rule and must belong to the Primary Allegiance or a Syndicate matching the Primary's Type (`commanderAllegianceEligible()`). They're drawn from a dedicated `commander_pool` (not the hire pool), capped by `maxCommandersFielded()`.
- **Stratagem Deck** — `Company::stratagems()` (`tos_company_stratagems`), capped at `STRATAGEM_DECK_SIZE` (6) with at most `MAX_ENVOY_STRATAGEMS` (2) from the Envoy. Eligibility uses `Stratagem::availableTo($allegiance)`.

> Not yet implemented: the "a Syndicate Commander forces the Envoy to be that Syndicate" cross-field rule — eligibility + the Syndicate commander pool exist, but the auto-constraint does not.

## TOS conventions

- **Slugs**: the `GeneratesTosSlug` trait auto-generates `slug` on `creating` from the `name` column. Most entities append `-Str::random(4)` for disambiguation. Allegiance and SpecialUnitRule use canonical slugs (override `slugNeedsRandomSuffix(): bool` to return `false`).
- **Route-model binding**: every TOS model with a slug exposes `getRouteKeyName() => 'slug'`. Trigger is the exception (its slug is unique only within an action, so it stays id-bound).
- **Image uploads** go through `HandlesTosImageUpload` (storeTosImage / deleteTosImage / generateTosComboImage) — never inline `Storage::put` calls.
- **Permissions** follow the naming `view_tos_<entity>`, `edit_tos_<entity>`, `delete_tos_<entity>` (see `PermissionEnum` + `PermissionGroupEnum`). `super_admin` gets everything via the seeder's `syncPermissions(Permission::all())`.
- **Icons / text with rulebook tokens**: body text rendered via `TosText` tokenizes `{{crow}}`, `{{magic}}`, `{{morale}}`, `{{margin5}}`, `{{turncard}}`, etc. Use `TosSuits` for bare single-letter suit strings (`"RM"` → per-char icons) and `TosMarginCost` for the numeric margin badge.
- **Payload slimming**: admin `index()` methods select explicit column lists (no `body`/`effect`/`description`/`lore_text` in list payloads). Admin form selects use lazy `fn () => …` props for partial-reload friendliness (match `CharacterAdminController`).
- **Search bars**: every TOS public index page uses `useListFiltering` + `ListSearchBar` for URL-synced name search. The server controller reads `name_search` from the query string and applies a `LIKE %search%` filter.
- **Cascade hooks**: test env runs SQLite with FKs disabled (`.env.testing DB_FOREIGN_KEYS=false`), so production `cascadeOnDelete` FKs are mirrored by manual `booted()` hooks on `Unit`, `Ability`, `Action` to cover pivot cleanup.

## Per-PR test expectations

Every TOS admin controller must have feature tests covering: index permission gate, store happy path, store validation rejection, update happy path, update permission gate, delete happy path, delete permission gate. Every model gets a test for its relations and cascades. FormRequests get at least one rejection test per non-trivial rule. Factories must be usable in isolation via semantic states (e.g. `->commander()`, `->fireteam()`, `->neutralFor(AllegianceTypeEnum::Earth)`). Follow the shape of `tests/Feature/TOS/StratagemAdminControllerTest.php` and `tests/Feature/Tournament/*` as references.
