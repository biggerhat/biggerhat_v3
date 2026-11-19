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

UI: `components/Fate/FateDock.vue` is the entry: a "Fate deck" button (a row in the side column on a wide screen, a floating button on a phone) that opens `FatePanel.vue`, docked under the button or, on a phone, as a non-modal bottom sheet so the table stays usable. `FatePanel.vue` lays the deck out as a mat: the other side as a thin strip, then your deck (tap to draw), the conflict, your discard pile (tap to look through it, `FatePileDialog`) and your hand along the bottom; it is fed by a feed (`useFateGame` for a game, `useLocalFate` for the sandbox's practice deck). Nothing is fetched until the dock is first opened. Mounted on `Games/Board.vue` and `Tools/Tabletop.vue` through `TabletopBoard`'s `#fate` slot, and on `Games/Show.vue` (when `tabletop_enabled` and the game is in progress; not floating there).

Also in the Fate deck: the end phase runs when the turn advances (`FateDeckService::endPhaseIfStarted`); `GET games.fate.log` and the panel's History read the action log as sentences (`App\Support\Fate\FateLog`); a flip's `subject` lets the board draw the result beside a model (`TabletopBoard` `fate` prop, the model's Fate flip control); and the other player's hand can be acted on only with consent or by the server (`random_discard`, `demand` + `prompts/{id}/respond`; a shown hand reaches the asker alone). **Throttle gotcha:** unprefixed `throttle:N,M` middleware share one counter per user across all routes, so a busy route can use up a stricter route's allowance; the Fate routes use their own bucket (`throttle:240,1,fate`).

## Turn sequence, round clock and the board's look back

- **Turn sequence** (`App\Services\Game\TurnSequence`, columns `games.initiative_slot/active_slot/last_activated_slot/passed_slot`): who has initiative (turn 1 the attacker; later the player who did NOT activate last chooses), whose activation it is (it passes to the other player when a model is marked activated, `GamePlayController::updateCrewMember`), and whether a pass is allowed (fewer models left to activate than the opponent or tied, not straight after the opponent passed). It is a guide: it never blocks marking a model activated. The state rides on the game as `game.turn_sequence` (play screen) and the `turn` prop (board); endpoints `games.play.turn.pass` and `.initiative`. `GameTurnBar` is shown inside the play screen's turn panel (`compact`) and as a strip on the board; the crew columns carry a "To activate" badge.
- **Round clock** (`App\Services\Game\RoundClock`, `GameRoundClock.vue`): for a tournament game in a running round, when the round ends and when "last turn" is called; counts against the server's time. Props `tournament_clock` (play screen) and `clock` (board).
- **End phase**: when both players have scored, each Fate discard pile goes back into its deck (`FateDeckService::endPhaseIfStarted`), and the table's board is snapshotted (`BoardHistory`: positions, markers, and each model's health, tokens and killed state) for the board's look back (`history` prop, a read-only "End of turn N" view).
- **Hand maximum** follows the tournament mode: 7, or 4 in Double Rush and Enforcer Engagement (`FateCards::handMaxFor`).

## Tabletop rules in the board (see also docs/plans/vtt-terrain-and-rules.md)

- **Engagement** (`geometry.ts` `engagedWith`): within 1" of an enemy unless the lower model's effective Sz is below the higher model's elevation; peons never. Drawn as a red mark on the base, explained in the model's options, and a Walk from an engaged start is half the Sp (not a Jump or Place).
- **Killed models**: the board can mark a model killed or bring it back (the tracker's own kill/revive endpoints). A killed non-peon offers a friendly 30mm Remains marker within 1" (nearest legal spot; none if there is no legal spot). A model that replaces itself on death is finished on the play screen.
- **Summoned models** with no saved position get a "Place on the table" bar: tap where it goes (nearest legal spot).
- **Marker neighbours**: selecting a marker lists the models within a range, peons left out (CRG: peons are ignored for strategies and schemes).
- **Scheme target**: a model or marker can be picked as the player's scheme model/marker on the board; it saves into `scheme_notes` (private to that player; the board payload carries notes only for `mine` players).
- **Observers** of an observable game hear board changes on the public `game-observe.{uuid}` channel (the events add it only when `is_observable`); a 30 s poll is only a safety net.
- **Gotcha**: `nearestLegalSpot` returns `null` both when the place is already legal and when nothing is legal; use `legalSpotNear` in `TabletopBoard` to tell them apart.
