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

## Building a crew from a game
The Game Tracker's crew-select step links to the editor with `?from_game={uuid}` (and `&from_slot=` so a solo game's opponent crew goes to the right player). While `from_game` is present the editor shows a
sticky bar with **Use this crew in game**: it flushes the autosave, POSTs `games.setup.crew` with the build id (and slot), then visits the game. A game that has already left crew select is treated as done and the user
is just returned to it. `buildUrl()` keeps `from_game` / `from_slot` across the editor's URL syncing. Tests: `tests/Feature/Games/GameSetupCrewSubmitTest.php`.

## Gotchas
- Bump `CrewBuild::REFERENCES_VERSION` whenever the references schema changes, or
  cached builds serve stale reference data.
- `crew_data` is a plain id array — not a relation; resolve characters explicitly.

## Tests
`tests/Feature/Tools/CrewBuilderTest.php`.
