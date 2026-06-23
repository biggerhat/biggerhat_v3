# Malifaux Database

The public browse/reference database for the Malifaux game system: Characters
(models), Abilities, Actions, Triggers, Upgrades, Keywords, Schemes, Strategies,
Markers, Tokens, Factions, Packages (product/box data). Admin CRUD lives under
`/admin`.

## Routes (`routes/web.php`)
Public prefixes → `Database\*Controller`:
`characters`, `factions`, `abilities`, `actions`, `triggers`, `upgrades`,
`keywords`, `schemes`, `strategies`, `markers`, `tokens`, `seasons`, `packages`,
`blueprints`, `collection`, `wishlists`, plus `Database\SearchController`.

## Controllers
`app/Http/Controllers/Database/` — `CharacterController`, `FactionController`,
`AbilityController`, `ActionController`, `TriggerController`, `UpgradeController`,
`KeywordController`, `SchemeController`, `StrategyController`, `MarkerController`,
`TokenController`, `SeasonController`, `PackageController`, `BlueprintController`,
`SearchController`. Admin CRUD: `app/Http/Controllers/Admin/*` (per resource).

## Models / Enums
`Character` (station, faction, second_faction, `miniatures`, `keywords`,
`characteristics`, `tokens`, `markers`, `characterUpgrades`, `summons`,
`replacesInto`, `has_totem_id`), `Ability`, `Action`, `Trigger`, `Upgrade`,
`Keyword`, `Scheme`, `Strategy`, `Marker`, `Token`, `Miniature`, `Characteristic`,
`Package`/`PackageStoreLink`, `Blueprint`, `Wishlist`/`WishlistItem`, `SavedSearch`.
- `FactionEnum`, `CharacterStationEnum` (Master, Henchman, Enforcer, Minion…),
  `PoolSeasonEnum` (scheme/strategy season pools).

## Frontend
`pages/Characters/`, `Factions/`, `Abilities/`, `Actions/`, `Triggers/`,
`Upgrades/`, `Keywords/`, `Schemes/` (under Seasons), `Markers/`, `Tokens/`,
`Packages/`, `Collection/`, `Wishlists/`, `Search/`. Icons via
`components/GameIcon.vue`; game-text token rendering via `components/GameText.vue`
(see its `tagToIconType` map — the canonical token→icon source).

## Conventions
- Faction colors are Tailwind config keys (`arcanists`, `bayou`, `guild`, …) —
  `composables/useFactionColor`.
- API mirror: `app/Http/Controllers/API/` returns Laravel Resources for the bot/VTT.

## Tests
`tests/Feature/` per resource (e.g. `CharacterControllerTest`, faction/scheme tests).
