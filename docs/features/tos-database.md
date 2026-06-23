# The Other Side (TOS) Database

Sibling game-system database + the **Company Builder**. Public pages live under
`/tos`. **Read [`docs/tos-conventions.md`](../tos-conventions.md) first** — this
is the quick code map.

## Routes (`routes/tos.php`, all under `tos.` name + `/tos`)
Prefixes → `TOS\Database\*Controller`: `allegiances` (+ `viewByType` for
`/tos/{earth|malifaux}`), `allegiance-cards`, `assets`, `stratagems`, `garrisons`,
`companies` (the builder), `units` (+ friendly per-rule URLs
`/commanders|titans|fireteams|squads|champions` → one `UnitController@index`
scoped by Special Unit Rule slug), `search`.
Admin: `routes/admin.php` `tos` prefix → `TOS\Admin\*Controller`.

## Models
`TOS\` namespace: `Allegiance`, `AllegianceCard`, `Asset` (+ `AssetLimit`),
`Stratagem`, `Garrison` (+ `GarrisonUnit`), `Company` (+ `CompanyUnit`), `Unit`
(+ `UnitSide`, `UnitSculpt`, `UnitSpecialRulePivot`), `SpecialUnitRule`,
`Ability`, `Action` (+ `ActionTypeLink`), `Trigger`.
- Enums: `AllegianceTypeEnum` (earth/malifaux), `GarrisonFormatEnum`
  (one_commander, one_commander_plus_10, two_commanders, theater_of_war,
  no_mans_land), `CharacterStationEnum`-equivalent station strings on units.

## Frontend
`pages/TOS/` — `Allegiances/`, `Assets/`, `Companies/` (`View.vue` is the
builder), `Garrisons/`, `Search/`. Shared components in `components/TOS/`:
`CardImage`, `FlipCard` (front/back card flip, has a `#back` slot for custom
back faces), `CompanyRosterPane`, `CompanyHiringPoolPane`, `CompanyStratagemPane`,
`CompanyCommanderPicker`, `CompanyUnitDrawer`, `UnitStatBlock`, `UnitCard`.

## Conventions / Gotchas
- **Images**: `app/Traits/TOS/HandlesTosImageUpload` (`storeTosImage` /
  `deleteTosImage`, public disk, Imagick resize to ≤1600px). Asset cards have
  `image_path` **and** `back_image_path` (disabled side); the flip uses the real
  back image or a placeholder.
- **Commanders** carry the `commander` Special Unit Rule slug (every Commander is
  also a `champion`). Exclude them from Champion lists/counts and from the
  Bonanza model-select. Filtering by `station != 'master'` in SQL drops
  NULL-station rows — guard with `whereNull OR != 'master'`.
- Company Builder (`Companies/View.vue`): responsive tabs (xl Build|Stratagems;
  mobile Roster|Hiring|Stratagems); allegiance/envoy card art folded into the
  header; format + Envoy meta line. `Asset::canAttachTo` / `AssetLimit` read each
  unit allegiance's `slug`/`name` — eager-load those columns or strict mode 500s.

## Tests
`tests/Feature/TOS/*`. Per-PR expectations are in `docs/tos-conventions.md`.
