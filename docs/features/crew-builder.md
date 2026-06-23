# Crew Builder

Build, save, and share Malifaux crew lists (master + models + upgrades), with a
computed soulstone pool and a "references" payload (tokens/markers/upgrades/
summons) consumed by the [Game Tracker](game-tracker.md).

## Routes (`routes/web.php`, `crew-builder` prefix)
`CrewBuilderController` — `editor`, `browse`, `store`, `update`, `destroy`,
`details` (full build), `references` (JSON), `share` (`{shareCode}`), `quickRef`.
API mirror: `API\V1\CrewBuildController`.

## Model: `CrewBuild`
- `master_id`, `crew_data` (array of character ids), `crew_upgrade_id`,
  `references` (cached array, cast), `custom_references`, `share_code`.
- **References** are the key concept:
  - `computeReferences(array $characterIds): array` (static) → `{ version,
    markers, tokens, upgrades, characters }`, pulling each character's
    `markers`/`tokens`/`characterUpgrades` + totems (`has_totem_id`) + summon/
    replace targets.
  - `ensureReferences()` rebuilds if missing/stale (`REFERENCES_VERSION` bump
    invalidates). `refreshReferences()` recomputes from `master_id` + `crew_data`
    merged with `custom_references`.
- The Game Tracker calls `ensureReferences()` per player; for crew-build-less
  players (e.g. Bonanza) it calls `computeReferences()` directly off the fielded
  models — see [game-tracker.md](game-tracker.md).

## Frontend
Crew builder editor page + `components/CrewBuilderReferences.vue` (the
references panel, reused in the tracker). Admin crew tools:
`Admin\CrewAdminController` (`admin/crews`).

## Gotchas
- Bump `CrewBuild::REFERENCES_VERSION` whenever the references schema changes, or
  cached builds serve stale reference data.
- `crew_data` is a plain id array — not a relation; resolve characters explicitly.

## Tests
`tests/Feature/` crew-builder + references tests; `CrewBuild` model tests.
