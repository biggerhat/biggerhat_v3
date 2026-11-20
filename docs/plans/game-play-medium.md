# Game Tracker: how the game is played (in person / tabletop / online / other), and setup on the tabletop

Status: **phase 1 in progress** (2026-11). The rulebook order of setup has been checked (see "What the rulebook says"); it changes step 3 below.

## What is being asked

1. When creating a game, say **how it will be played**: in person, on the tabletop app, online (not the tabletop), or other.
2. If tabletop: pick **which saved table** (a `TabletopLayout`) it is played on.
3. If tabletop: the **pre-game setup that can be done on the table** (deployment and the like) happens there, as part of the game's setup steps.
4. Abstract more of the tracker where that makes this clean.

## What exists today (read, not guessed)

- **Create**: `Games/Create.vue` -> `GameController::store`. Name, format, encounter size, season, solo. A **random** strategy, deployment and scheme pool are generated at creation (the creator can edit or re-roll). Nothing records where or how it is played.
- **Setup is a status machine**: `GameStatusEnum` Setup -> FactionSelect -> MasterSelect -> CrewSelect -> SchemeSelect -> InProgress. The transitions are hard-coded in `GameSetupController` (`submitMaster` at ~171 for Bonanza, `submitScheme` at ~540 for solo and ~554 for two players). The client reads the same names in `types/game.ts`, `useGameChannel.ts`, `Games/Index.vue`, `Campaigns/Show.vue`, `Hub/Index.vue`.
- **The tabletop is already tied to a game** (`Game\GameBoardController`, `Games/Board.vue`): terrain, positions, markers and `deployment_variant` / `table_image` live in `games.board_state`. A saved table can be loaded into a game from the board (client side, then saved), and a game's table can be saved as a layout. Deployment zones are drawn from `games.deployment` + `deployment_variant` (`geometry.ts` `deploymentZones`, `whereabouts`).
- **Tournaments** already link a `TournamentTable` to a `TabletopLayout`.
- **Gate**: the tabletop is behind `TabletopAccess` (flag `experiment-tabletop` or `use_tabletop_beta`); anything new about it must 404/hide for people without access.
- **`Games/Show.vue` is ~6.7k lines.** The setup panels are already components (`GameFactionSelectPanel`, `GameMasterSelectPanel`, `GameCrewSelectPanel`, `GameSchemeSelectPanel`, `GameSetupLobby`, `GameEditScenarioDrawer`); the stepper and the status-gated reloads (`postSetup()`) are inside the page.

## Design

### 1. Record how it is played (small, ships on its own)

- `App\Enums\GamePlayModeEnum`: `in_person`, `tabletop`, `online`, `other`. Methods: `label()`, `description()`, `usesTabletop()`. Same traits as the other enums.
- `games.play_mode` (string, **nullable**: null = games made before this, shown as nothing, never guessed) and `games.tabletop_layout_id` (nullable FK to `tabletop_layouts`, `nullOnDelete`, short explicit constraint name for MySQL).
- **A game copies the table, it does not follow it.** Choosing a saved table copies its terrain and surface into `board_state` at creation (the same shape `GameBoardController::updateTerrain` writes). Editing or deleting the layout later must not change a game in progress. `tabletop_layout_id` is kept only to say "played on <name>".
- Create form: a **"How will you play?"** select (default: the last choice on this device, as the other defaults are). Only when "On the tabletop app" is chosen, and only when `TabletopAccess::allows`, show a **Table** select: "Empty table" plus the user's saved tables (own layouts only in v1; public/link layouts can be copied to "my tables" first, which already exists).
- `StoreGameRequest`: `play_mode` in the enum; `tabletop_layout` must exist and belong to the user; both are **dropped silently server-side** for someone without tabletop access, and a layout without `tabletop` is rejected. A solo game may choose any mode.
- Show it: a badge on the lobby/header and in `Games/Index`, the public list and the hub ("Tabletop", "In person"...), and put it in the game summary/stats later. Tournament games: leave null in v1 (decide later whether a round's table with a layout implies tabletop).
- Online (not tabletop) and Other: **no behaviour**, only the label (and a free-text note field is not needed).

### 2. Abstract the setup flow (the refactor that makes 3 clean)

The thing to pull out is not the 6.7k-line page but the **status transitions and the step list**, which today are scattered `if`s.

- `App\Support\Game\GameSetupFlow` (pure PHP, unit-testable):
  - `steps(Game): list<GameSetupStep>`: the ordered steps this game has (faction, master, crew, scheme, and `deploy` only for a tabletop game), each with a key and label. Bonanza/Campaign/solo variants live here instead of in controllers.
  - `next(Game): GameStatusEnum`: the status after the current one is complete.
  - `advance(Game): bool`: does the lock + re-check + transition + broadcast in one place (the project's concurrency rule: `lockForUpdate` inside `DB::transaction`, re-check after locking). `submitMaster`, `submitScheme` and the new deploy step call this instead of writing `'status' => InProgress`.
- The step list is **sent to the client** (`setup_steps` prop) so `Games/Show.vue`'s stepper, `Games/Index`, `Hub`, `Campaigns/Show` and `useGameChannel` stop hard-coding status names; start with the stepper and the status labels. TS side: one `lib/game/setupFlow.ts` mirroring the labels/colours now repeated in three pages.
- Behaviour must not change for existing games: this step ships with the current tests green and no new status yet.

### 3. A Deployment step that happens on the tabletop

- New status `GameStatusEnum::Deployment` ("Deploying"), `isSetupPhase()` true, between CrewSelect and SchemeSelect (the book deploys before schemes are chosen), **only for `play_mode = tabletop`**. Everyone else goes CrewSelect -> SchemeSelect -> InProgress exactly as now.
- What the step is: both players are sent to the game's board (`games.board`) in a **setup mode** (`game.setup = true` in the board payload):
  - The deployment layout and the table can still be changed (the existing `board.setup` and terrain endpoints already allow it); the deployment variant picker becomes a first-class control here rather than a corner of the Table tab.
  - Each player places their own crew **inside their zone** (`whereabouts` already knows zone membership; in setup mode a model dropped outside is refused with the usual "why not here" text, and Move-as/Charge tools are hidden).
  - Each player presses **"Ready, start the game"** (`game_players.deployed_at`, new nullable timestamp). When both are ready, `GameSetupFlow::advance` moves to InProgress, turn 1, and starts the round clock/Fate end-phase logic as today. In a **solo** game one person readies both slots.
  - The Show page during Deployment shows a card "Set up the table" with a link/button to the board and who is ready (like the lobby shows who has chosen a faction). It also works as the **return path** if someone closes the board.
- Realtime: reuse `GameSetupStepCompleted` for "X is ready" and `GameStatusChanged` on the transition; the board already listens on the game channel.
- A game that is already InProgress and played on the tabletop is untouched.

### 4. Other things that move to the table (later, once 3 works)

- Choosing the **deployment** (today random at creation, editable in `GameEditScenarioDrawer`) could be picked in the deploy step with the zones previewed; keep the random roll as the default.
- Initiative / who deploys first, and alternating placement, if the rulebook sets one (see below). Not built until the rule is confirmed.
- Terrain checks from the rules (e.g. minimum terrain) as a warning, not a block.

## What the rulebook says (Comprehensive Rules Guide 9.2026, "Encounters", pp. 60-63)

Setup is steps A-K: encounter size, place and define terrain, **determine scenario**, generate schemes, **choose deployment**, faction and leader, hire crew, reveal crews, **deployment**, **choose schemes**, start of game.

- **Attacker and defender come from a Fate flip (C):** both flip the top of their shuffled deck (re-flip jokers); higher card is the attacker, ties re-flip. Then both flip again: the attacker's suit picks the strategy, the defender's the deployment type. The attacker draws the scheme pool (3 face up, D), **chooses the deployment zone (E)**, has initiative on turn 1, and orders start-of-game effects. The tracker's random strategy/deployment at creation stands in for this; on the tabletop the flips can be real (the Fate deck exists on the board).
- **Deployment (I) is not simultaneous:** the defender splits their crew into two groups (either may be empty); the attacker picks one group; the defender deploys it; the attacker deploys the whole crew in the opposite zone; the defender deploys the other group. Models go **completely inside** their zone; nothing else limits placement.
- **Schemes are chosen after deployment (J)**, secretly, from the pool of three. The tracker chooses them before play starts (SchemeSelect), so for a tabletop game the order is crew -> **Deployment** -> SchemeSelect -> InProgress.
- **Start of game (K):** both players reshuffle their full Fate deck and the opponent may cut; turn 1 starts without shuffling. There is no initiative flip.
- **Terrain (B):** a 3'x3' table, about 45-65% covered, a few (3-8) pieces of Ht 2+ that are climbable and standable. A recommendation, not a hard rule: warn, never block.
- Not in the core book: the strategy/scheme lists and the suit -> strategy/deployment tables (Gaining Grounds), and the Gaining Grounds file was not read.

## Step 3, revised from this

Deployment on the board runs the book's order: the defender **splits their crew into two groups** (a "Deploy later" toggle per model), the attacker picks the group that goes first, then the placing turns alternate (defender group 1, attacker all, defender group 2), each model only legal fully inside its owner's zone. "Whose turn to place" is a small state on the game (`board_state.deploy` or its own column) with a lock + re-check like the other state machines. A **"Start without deploying"** skips it. Solo: one person does every turn. After the second defender group, status moves to SchemeSelect (not InProgress) and the existing scheme step runs. The full Fate-deck reshuffle at start of game is already done when the tabletop starts turn 1 (check `FateDeckService`).

Side colours: **attacker is red, defender is blue** on the board (bases, deployment zones, auras). Roles already exist on `game_players.role` (`GameRoleEnum`; solo assigns them at creation, a duel when the opponent joins, `swap_roles` flips them), so the board colours by role, and falls back to today's colours (A blue, B red) in the sandbox and when no role is set yet.

## Things that could go wrong

- **Status checks elsewhere assume the old list.** 9 server and several client places match on `scheme_select` / `InProgress` (e.g. the board offers upgrades only when `InProgress`, `Games/Index` and `Hub` have label maps keyed by status). Adding `Deployment` needs each reviewed; a missing label should fall back to the raw status, not break the page.
- **Upgrades and attaching**: in the board payload `character_upgrades` is empty until InProgress. Decide whether players may attach upgrades during Deployment (probably yes, since the crew is chosen) or only after.
- **Tournament games** (`TournamentGameController`, round clock, `complete-tracker`) create and advance games too; their flow must stay on the non-tabletop path in v1.
- **SSR is enabled** and the tests do not exercise it: check the new Create form and Show states in a real build (the harness in `docs/ui-audit.md`).
- **Help**: the Game Tracker help topic (`resources/js/lib/help/topics/gameTracker.ts`) and the tabletop topic must be updated in the same change (the project rule), including new pictures if the Create page or a setup screen is shown.

## Progress

- **Phase 1 done** (2026-11): play mode, Create form, saved-table copy, badges, attacker red / defender blue. Commit `1d79b60c`.
- **Phase 2 done**: `App\Support\Game\GameSetupFlow` (`statuses`, `next`, `advance`, `startingStatus`, `steps`) owns the setup order. `GameSetupController` (six transitions), `GameController::store` and `join` use it; `advance` must be called inside the transaction holding `lockForUpdate` and re-checks the status itself. `Games/Show` gets `setup_steps` and the indicator no longer guesses `step + '_select'`. Left alone on purpose: the tournament tracker factory and `CampaignGameController`, which create games already at MasterSelect, and the label/colour maps in `Games/Index`, `Hub`, `Campaigns/Show` (their wording differs; unify when Deployment adds a status). Bonanza still shows four step dots (unchanged); `GameSetupFlow::steps` is where to make it two.

## Phases and order

| # | What | Size | Ships alone? |
|---|---|---|---|
| 0 | Rulebook check of setup order | S | **Done** |
| 1 | `GamePlayModeEnum`, columns, Create form (mode + saved table), copy layout into `board_state`, badges, tests, help | M | Yes. Useful on its own: a tabletop game opens on the chosen table |
| 2 | `GameSetupFlow` extraction + `setup_steps` prop + shared status labels (no behaviour change) | M | Yes (pure refactor, existing tests prove it) |
| 3 | `Deployment` status (between crew and schemes), the book's alternating placement order, zones enforced, Show card, realtime, solo | L | Needs 1 and 2; behind the tabletop gate |
| 4 | Table-side extras (pick deployment, initiative/alternating placement per the rulebook) | M | After 3 |
| 5 | Pull the setup stage out of `Games/Show.vue` into a component, only as far as 3 needs | M | Optional |

Tests per the project rules: a feature test per new endpoint (permission, happy path, validation), `GameSetupFlow` unit tests per format (standard, solo, Bonanza, Campaign, tabletop), a concurrency test for "both ready at once", and a harness flow (`flows39`) driving Create -> setup -> deploy -> turn 1 as two players.

## Decisions wanted

1. **Default for old games and for people without tabletop access**: null (shown as nothing) vs `in_person`. Recommended: null for old games, `in_person` as the form default.
2. **Whose saved tables**: own only in v1 (recommended) vs also shared/public layouts picked directly.
3. **Should "tabletop" games be allowed to skip the Deployment step** (play on the board but place nothing)? Recommended: yes, a "Start without deploying" button for players who set up elsewhere.
4. **Tournament rounds**: leave out of v1 (recommended) vs set tabletop automatically when the round's table has a layout.
