# Tournament Tracker

Run a Malifaux tournament: registration/RSVP, Swiss rounds + pairings, scoring,
standings. Integrates with the [Game Tracker](game-tracker.md) — a tournament
match can spawn/track a live Game.

## Access
Any signed-in user can create and run a tournament (creation is throttled to 20/hour). There is no `create_tournaments`
permission or `tournament_organizer` role any more. Per-event control is the creator + `tournament_organizers` pivot
(`TournamentPolicy::manage`); `manage_tournaments` stays a super-admin "manage any" permission. Draft events are hidden from
the public list (they stay reachable by link for RSVPs).

## Routes (`routes/web.php`, `tournaments` prefix)
- `Tournament\TournamentController` — index/show/create/store/update, organizer dashboard.
- `Tournament\TournamentRoundController` — generate pairings, advance rounds.
- `Tournament\TournamentGameController` — per-pairing result/score; links a `TournamentGame` to a `Game`.
- `Tournament\TournamentPlayerController` — add/drop players, standings.
- `Tournament\TournamentRsvpController` — public RSVP / sign-up.
- `Tournament\TournamentOrganizerController` — TO management.
- `Tournament\TournamentUserSearchController` — typeahead for adding registered users.
- `Tournament\BroadcastsTournamentUpdates` (trait) — shared Reverb broadcast helper.

## Models
`Tournament`, `TournamentRound`, `TournamentGame` (bridges a round pairing to a
`Game` via `game_id`), `TournamentPlayer`, `TournamentRsvp`.

## Frontend
`pages/Tournaments/*`. Realtime: `composables/useTournamentChannel.ts` subscribes
`tournament.{uuid}`; the `TournamentUpdated` event carries a reason string
(e.g. `tracker_abandoned`, `tracker_score`). Include the Echo socket id so
`broadcast(...)->toOthers()` skips the originator (see `composables/useTournament.ts`).

## UI pieces
- `components/Tournament/MyMatchCard.vue` — a player's own pairing (table, opponent, scenario, live-game link) at the top of `Tournaments/View.vue`.
- `components/Tournament/RoundClock.vue` — countdown from `round.started_at` + `tournament.round_time_limit` (client-only tick).
- `components/Tournament/TournamentCard.vue` — Index card; Index groups Happening now / Upcoming / Past.
- Pairings print sheet: `View.vue` "Print pairings" + `[data-print-area]` / `[data-no-print]` rules in `app.css`.
- Create form can prefill from an event you organize: `/tournaments/create?copy={uuid}`.
- Meta pickers in `Manage.vue` use reka-ui `Combobox`: bind the search text on `ComboboxInput` (the root has no `searchTerm`),
  and use `<ComboboxList inline>` inside a Dialog (the portalled list otherwise closes the radix Dialog and steals focus).

## Services (`app/Services/`)
The real logic lives in services, not the controllers:
- `TournamentPairingService` — Swiss `regeneratePairings()` (per round, not upfront). Round 1 avoids same-meta pairings, with a swap pass (`separateSameMeta`) to fix clashes the greedy pass causes.
- `TournamentStandingsService` — standings math.
- `TournamentStateMachine` — round/tournament status transitions.
- `TournamentTrackerGameFactory` — spawns the linked `Game` for a pairing.

## Conventions / Gotchas
- `TournamentGame::game()` → `belongsTo(Game, 'game_id')`. Pairings regenerate
  during round Setup (reverting InProgress games first), not mid-round.
- Game-tracker hybrid: abandoning/scoring a linked `Game` broadcasts a
  `TournamentUpdated` so the TO's view updates live (see `GameController::abandon`).
- Admin overrides: `Admin\TournamentOverrideAdminController` (`admin/tournaments`).

## Tests
`tests/Feature/Tournament/` — incl. `PairingServiceTest`, `StandingsServiceTest`,
`StateMachineTest`, `RoundLifecycleTest`, `RsvpTest`, `DropPlayerTest`,
`ScenarioPropagationTest`, `PublicViewPageTest`.

## Rulesets (game-agnostic tournaments)
Tournaments run under a **ruleset** (`tournaments.ruleset`, config in `config/tournaments.php`, plan in
[docs/plans/tournament-rulesets.md](../plans/tournament-rulesets.md)). A ruleset is data: scoring parameters
(`App\Tournaments\ScoringConfig` / `ScoringEngine`), ordered tiebreakers, score fields, scenario fields, planning defaults.
Standings, score entry, the state machine and the Create/Manage/View pages read the ruleset; nothing special-cases a game.
Adding a game = adding a config entry. Rulesets today: `malifaux_gaining_grounds` (default) and `tos_fields_of_glory`.
TOS rounds pick an Operation + Deployment from `tos_operations` / `tos_deployments` (stored in `tournament_rounds.scenario`).

## Player-facing features
- **Self-reporting** (`tournaments.allow_self_report`, off by default): a linked player submits their game's score
  (`TournamentGameReportController`), the opponent confirms (official, via `TournamentScoreRecorder`) or disputes (the TO decides). Pending
  reports never count toward standings; the TO's score entry always supersedes them and the dialog pre-fills from the proposal.
  UI: `components/Tournament/MyMatchCard.vue`.
- **Check-in** (`tournament_players.checked_in_at`): players check themselves in on the public page, the TO from Manage (single or all);
  pairing asks for confirmation when some active players haven't arrived.
- **Display mode** (`/tournaments/{uuid}/display`, `pages/Tournaments/Display.vue`, no app chrome): current round's pairings + scenario + clock,
  rotating with standings, live via the tournament channel. Shares its payload with the view page (`TournamentController::viewProps`).
- **Agreed results**: `result = agreed`, scored like a played game.

## Manage page structure
`pages/Tournaments/Manage.vue` (~880 lines) is the shell: header, next-step card, announcement dialog, the standings / RSVP / settings tabs, and the score and
finalize dialogs. Its tabs live in `components/Tournament/`: `ManagePlayersTab.vue`, `ManageRoundsTab.vue` (round lifecycle, scenario editor, manual pairing,
click-to-swap pairing fixes, forfeits, the card drawer), `ScoreEntryDialog.vue` and `TournamentSettingsForm.vue`. A tab component's root is its own `TabsContent`, so
its script stays mounted when another tab is showing; the page reaches in through `defineExpose` (the next-step guide calls `createRound`, `generatePairings`,
`startRound`, `endRound`, `editScenario`, and the `*BlockReason` helpers on `roundsTab`). `composables/useTournamentNextStep.ts` decides what the guide says.
Shared TS types (players, games, rounds, `isScoredResult`) live in `types/tournament.ts`.
Template-level identifier mistakes aren't caught by the build — there is no vue-tsc here — so after moving code around, compile the templates with
`@vue/compiler-sfc` and look for `_ctx.<name>` accesses that aren't bindings, and run a TypeScript "Cannot find name" (TS2304) pass over the `<script setup>` block.
A row menu (`DropdownMenu`) that opens a dialog must be `:modal="false"`, or closing the dialog leaves `body { pointer-events: none }` and the page frozen.

## Page payloads
`App\Tournaments\Payloads\TournamentPagePayloads` builds the Inertia props: `forView()` (public; redacts report notes, crew build ids, tracker settings and meta for
people who aren't in the game or organizing), `forManage()` (organizer; heavy reference data deferred), plus the ruleset / allegiance descriptors.
`TournamentController` authorizes and renders. Pairing fixes: `TournamentGameController::swapPlayers` / `swapTables` (round setup only, round-locked).
Announcements: `TournamentAnnouncementController` (`tournaments.announcement`). The browser pass for all of this is `scripts/ui-audit/tournaments/run.sh` (see [ui-audit.md](../ui-audit.md)).

## Team events
`tournaments.team_size` > 1 makes each competitor a team: a `tournament_players` row (named by `display_name`) plus `tournament_player_members` for the people on it.
Everything that pairs/scores/ranks works on the competitor row unchanged; anything about *people* (declaration, linked accounts, notifications, check-in, self-report)
goes through the members — see `TournamentPlayer::forUser()` / `linkedUserIds()`. UI: `ManagePlayersTab` (team mode) + `TeamForm`.
