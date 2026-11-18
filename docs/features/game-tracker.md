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
- `components/Game/GameCrewMemberRow.vue` — one living crew model on the play screen (activation, health pips + stepper, tokens, inline card, attached upgrades) for **both** sides; `editable` is the only difference. It owns no state: it emits and `Show.vue` acts. Change a crew row here, once. On phones the health stepper sits on the name line and stats share the pips row; from `sm` the stats row below carries stats, pips and stepper. On phones Tokens is a labelled button and the rarer actions (upgrades, injury, replace) are in a "more" menu. Living models are listed activated-last (`activatedLast` in `Show.vue`). Browser flow `flows30.cjs` covers it (health, pips, activation, tokens dialog, opponent side, reload).
- `pages/Games/Create.vue`, `Index.vue`, `constants.ts`.

## Realtime

- Reverb. `composables/useGameChannel.ts` joins `game.{uuid}`. Events: `GamePlayerJoined`, `GameStatusChanged`, `GameSetupStepCompleted`, `GameCrewMemberUpdated`, `GameTurnAdvanced`. Solo (`is_solo`) creates an inert slot-2 GamePlayer.

## Gotchas

- **SSR is enabled** — feature tests don't exercise the Node renderer, so a passing test can still 500 in prod on a serialization path.
- `postSetup()`'s `router.reload({ only: [...] })` list must include any **status-gated prop** that becomes available on a status transition (e.g. Bonanza's `loot_card_catalog`, `starting_crews`, `bonanza_crew_upgrades`) — otherwise the UI shows stale/empty until a manual refresh.
- Crew **References** come from `player.crew_build.references`; crew-skipped/Bonanza players have no crew build, so the controller synthesizes them via `CrewBuild::computeReferences()` and attaches `player.references` (client falls back to it).
- See [bonanza-loot.md](bonanza-loot.md) for the Bonanza mode specifics.
- **Campaign format, solo mode**: the opponent is a generic placeholder — `GameSetupController::submitFaction`/`submitMaster`/`submitCampaignCrew` auto-fill slot 2's faction/`master_name`/`crew_skipped` the moment slot 1 submits, so no opponent picker ever renders and setup advances on one submission per step. Crew hiring goes through `submitCampaignCrew` (character IDs from the arsenal, not a `CrewBuild`) → `copyCrewToGame`'s Campaign branch, which also inserts the leader/totem `GameCrewMember` rows from the resolved `CampaignCrew`. Equipment isn't picked at hiring time — `character_upgrades` (the in-play "attach upgrade" editor, InProgress-only) swaps the standard catalog for the crew's own active `CampaignEquipment`, grouped by catalog upgrade with `plentiful` = copies owned. **Injuries** (pg 34) are the opposite of equipment — permanent, not optional — so `copyCrewToGame` auto-attaches each hired model's/leader's/totem's `CampaignArsenalModelInjury` rows straight into `attached_upgrades` via the `injuryUpgrades()` helper; there's no separate injury picker.

## Pre-game setup (phone-first)

Review and rationale: [docs/plans/game-setup-ux.md](../plans/game-setup-ux.md). Conventions to keep:

- **Skip your own crew** ("track points only"): `POST games.setup.crew.skip` (`SkipCrewRequest`) marks the caller's slot `crew_skipped` and gives a real player a starting soulstone pool of `min(6, encounter size)`. A solo game names the `slot`; with no slot it skips the solo opponent (the original behaviour). `GameCrewSelectPanel` shows the button under the saved crews (it asks for a title first when the master has several), the scheme step and the play screen show "Crew not tracked". Campaign crews come from the arsenal and cannot be skipped.
- **Confirm pattern**: faction and master choices are confirmed from the same floating bar above the phone tab bar. Keep new setup steps on it.
- **Setup layout**: the step indicator sits under the header; on phones the scenario folds into a one-line summary while a `*_select` step is active (open in the lobby), and the two players share a row.
- **`GET /games/{uuid}` as a non-participant** redirects to `games.join` while a duel is still open (not full, not solo, status Setup); otherwise it stays a 403.
- **`useGameChannel`** exposes `connected` (down only after 5 s) and refreshes every 30 s while the socket is down; `Show.vue` shows a "Reconnecting" badge. Browser flow `scripts/ui-audit/tournaments/flows29.cjs` drives create -> join -> faction -> master -> skip crew -> scheme -> play at phone width.

## Tests

`tests/Feature/Games/*` (e.g. `BonanzaBrawlTest.php`, `GameModeTypeTest.php`).

## Tabletop board crew list

The sandbox (`Tools/Tabletop.vue`) builds the same list from its own tokens (`Token.card` keeps Df, Wp, faction and card image from the character search) and replaces a model locally through the character search. `Games/Board.vue` passes each model's `member` payload (`GameBoardController::models()`) to `TabletopBoard` as `crew`, which lists the same `GameCrewMemberRow` the play screen uses (`board-mode` hides the upgrade and injury controls, which stay on the play screen). The selected model's tabletop options (the `selected-panel`) are teleported into a slot under its row; with no row (a marker, the sandbox) they stay inline. Health, activation and token removal go through the board's `modelChange` to the tracker endpoint. Replace uses `GameReplaceDialog` and `games.play.crew.replace` (CRG p.48): the row is updated in place, so position, tokens, upgrades and health (capped at the new maximum) are kept. Not modelled: replacing with several models, model limits, leader/scheme retargeting.

## Fate deck (pre-release)

Plan and rules: [docs/plans/fate-deck-and-board-badges.md](../plans/fate-deck-and-board-badges.md). Each player of a game has a 54-card Fate deck kept on the server (`game_fate_cards`, one row per card, `zone` = deck / hand / conflict / discard / removed; flips are in `game_fate_flips`). `App\Services\Fate\FateDeckService` is the only thing that touches it: it shuffles with `random_int`, runs each action in a transaction under a lock on the game row, and re-checks that each named card is where the request says. **A hand is never in shared props or on a broadcast channel:** `state()` lists a hand only for players the viewer controls (their own slot, or both slots of a solo game they created); everyone else gets `hand_count`. The deck's order never leaves the service; `peek`, `search` and `arrange` tell the caller only what the effect lets it see. `GameLog` rows (`fate.*`) name face-up cards and give only counts for hidden ones. Routes are `games.fate.*` (`GameFateController`), behind `TabletopAccess` (the tabletop flag or `use_tabletop_beta`). Tests: `tests/Feature/Games/GameFateTest.php`.

UI: `components/Fate/FatePanel.vue` shows the decks, hands, flips and piles from a feed (`useFateGame` for a game, `useLocalFate` for the sandbox's practice deck); `FateSection.vue` wraps it as a shut-by-default `<details>` that starts the feed when first opened. Mounted on `Games/Board.vue` and `Tools/Tabletop.vue` through `TabletopBoard`'s `#fate` slot, and on `Games/Show.vue` (when `tabletop_enabled` and the game is in progress). After any action the controller broadcasts `GameFateChanged` (an action name and no cards) to the game and observer channels; clients refetch their own view. Browser flows: `flows35.cjs` (practice deck) and `flows36.cjs` (game deck, reload, watcher sees no hand). Note `DialogContent` does not forward attributes (fragment root): put a `data-testid` on an inner `div class="contents"`.
