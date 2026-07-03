# Game Tracker

Live play-tracker for a Malifaux game (and its mode variants). Tracks crew HP,
tokens, soulstones, schemes/VP, turns. Solo and 2-player (real-time) modes.

## Routes (`routes/web.php`)
- `games` prefix → `Game\GameController` — `games.index`, `games.create`, `games.store`, `games.show` (`{game:uuid}`), `games.abandon`, settings/scenario updates.
- `/{game:uuid}/setup` → `Game\GameSetupController` — `games.setup.faction`, `.master`, `.crew`, `.crew.skip`, `.scheme`, `.opponent_name`, `.swap_roles`. **Return JSON**, client then `router.reload`s.
- `/{game:uuid}/play` → `Game\GamePlayController` — turns, crew member updates (HP/tokens/kill), summon, soulstones, scheme notes, Bonanza VP + loot (draw/select/yoink).

## Models / Enums
- `Game`, `GamePlayer` (slot 1/2, `crewMembers`, `crewBuild`, `master_id`, `soulstone_pool`), `GameCrewMember` (`character()`, `attached_tokens`), `GameTurn`, `GameLog`.
- `GameStatusEnum`: Setup, FactionSelect, MasterSelect, CrewSelect, SchemeSelect, InProgress, Completed, Abandoned.
- `GameFormatEnum`: Standard, BonanzaBrawl (+ Campaign). `usesScenario()`, `defaultEncounterSize()`.
- `GameModeTypeEnum`: standard / campaign / cooperative / bonanza_brawl / custom.

## Frontend
- `pages/Games/Show.vue` — **~6,700-line** play screen. Section landmarks: `isBonanza` computed; loot-deck panel (`isBonanza && in_progress`); Summon buttons; `postSetup()` (the reload helper); `loadReferences()`; crew columns. **Grep for the feature, don't read top-to-bottom.**
- `pages/Games/Create.vue`, `Index.vue`, `constants.ts`.

## Realtime
- Reverb. `composables/useGameChannel.ts` joins `game.{uuid}`. Events: `GamePlayerJoined`, `GameStatusChanged`, `GameSetupStepCompleted`, `GameCrewMemberUpdated`, `GameTurnAdvanced`. Solo (`is_solo`) creates an inert slot-2 GamePlayer.

## Gotchas
- **SSR is enabled** — feature tests don't exercise the Node renderer, so a passing test can still 500 in prod on a serialization path.
- `postSetup()`'s `router.reload({ only: [...] })` list must include any **status-gated prop** that becomes available on a status transition (e.g. Bonanza's `loot_card_catalog`, `starting_crews`, `bonanza_crew_upgrades`) — otherwise the UI shows stale/empty until a manual refresh.
- Crew **References** come from `player.crew_build.references`; crew-skipped/Bonanza players have no crew build, so the controller synthesizes them via `CrewBuild::computeReferences()` and attaches `player.references` (client falls back to it).
- See [bonanza-loot.md](bonanza-loot.md) for the Bonanza mode specifics.
- **Campaign format, solo mode**: the opponent is a generic placeholder — `GameSetupController::submitFaction`/`submitMaster`/`submitCampaignCrew` auto-fill slot 2's faction/`master_name`/`crew_skipped` the moment slot 1 submits, so no opponent picker ever renders and setup advances on one submission per step. Crew hiring goes through `submitCampaignCrew` (character IDs from the arsenal, not a `CrewBuild`) → `copyCrewToGame`'s Campaign branch, which also inserts the leader/totem `GameCrewMember` rows from the resolved `CampaignCrew`. Equipment isn't picked at hiring time — `character_upgrades` (the in-play "attach upgrade" editor, InProgress-only) swaps the standard catalog for the crew's own active `CampaignEquipment`, grouped by catalog upgrade with `plentiful` = copies owned. **Injuries** (pg 34) are the opposite of equipment — permanent, not optional — so `copyCrewToGame` auto-attaches each hired model's/leader's/totem's `CampaignArsenalModelInjury` rows straight into `attached_upgrades` via the `injuryUpgrades()` helper; there's no separate injury picker.

## Tests
`tests/Feature/Games/*` (e.g. `BonanzaBrawlTest.php`, `GameModeTypeTest.php`).
