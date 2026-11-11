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

## Capacity, waitlist and withdrawing
`tournaments.max_players` (null = unlimited; competitors = players, or teams) and `registration_closes_at`. RSVPs are always accepted while registration is open and past the
deadline are refused (cancelling still works). The waitlist is **computed, not stored**: `Tournament::waitlistPositions()` takes RSVPs oldest first, skips users who already
have a seat, and gives everyone past `max_players - competitors` a position, so a freed seat promotes the next RSVP with no job to run (individual events only — in team events an
RSVP is a person while the limit counts teams). `TournamentPagePayloads` stamps `waitlist_position` on each RSVP. An organizer adding a (non-ringer) player past the limit gets a 422.
`TournamentSelfDropController` (`tournaments.withdraw`): a linked player in an individual event removes their seat and RSVP before the start, or is dropped after the round they are in
(else the last finished round) once it runs; organizers get a `TournamentPlayerWithdrew` notification. Team events stay with the organizer.

## Results sharing
A completed event leads with `ResultsCard.vue` on the public page: a podium (rank <= 3, joint places included, ringers excluded) and three ways to take it away.
**PDF** — `TournamentResultsController@pdf` (`tournaments.results_pdf`, public, 404 until the event is completed) renders `resources/views/PDF/TournamentResults.blade.php` with DomPDF: podium,
full standings with the ruleset's tiebreaker columns, organizer and link. **Image** — `lib/tournamentResultsImage.ts` draws a 1200x630 card on a canvas in the browser (nothing is uploaded) and the card
downloads it; **Share** uses the native share sheet (with the image where the device allows files). The browser pass captures the download and checks it is a 1200x630 PNG.

## Crews per round
Gaining Grounds players bring a different crew every round, so a crew belongs to the **pairing**, not the player: `tournament_games.player_one_crew_build_id` / `player_two_crew_build_id` (the
`require_crew_lists` setting only controls whether the organizer is nagged). Once a round is paired, a player in an individual event under a faction-declaring ruleset picks one of their own non-archived
crews for their game (`CrewListPicker` on the public page, `PUT tournaments.games.crew` -> `TournamentGameCrewController`). It must match the faction they are playing, respects Alternating Leaders (a master
can't be brought again in a later round), fills the pairing's master and title, and stays editable until the game has a result. The organizer's Manage payload carries `crew_one` / `crew_two` per game from
`App\Tournaments\CrewListCheck` — master, size, and soft flags (faction mismatch, built for a different size, no master, empty crew) — shown under each pairing on the Rounds tab; the build itself is never
serialised and the public payload hides the crew ids. Bringing a private build to a game shares it with that tournament's organizers (`CrewBuildPolicy::viewShare`). Start Round warns, without blocking, who hasn't
picked one. TOS events use the Garrison link from the declaration instead; team events don't use crews.

## Tournament history on profiles
`App\Tournaments\TournamentHistory::forUser()` builds a person's record from every **completed** event they played (as a player or a team member): placing and field size, W-L-T from the games (byes and unfinished games don't
count; a forfeit counts for the player who didn't forfeit), per-faction results from each game's faction snapshot, and the opponents met at least twice (linked to their stats pages when they have an account).
It is a closure prop `tournament_history` on `StatsController::show` (the public `/stats/{slug}` page) rendered by `TournamentHistoryCard.vue`; the card is absent for someone with no finished events.
`AchievementService::tournamentRecordForUser` (the Tournaments/Best-finish tiles and badges) is separate and still counts individual entries only.

## Finding events and calendars
`TournamentController@index` filters the public list server-side from the URL (`q` over name and location, `ruleset`, `when=upcoming|past`; unknown values are ignored), orders upcoming soonest first and
paginates 20 at a time (`public_pagination`, `filters`, `ruleset_options` props); drafts stay private. The Index page keeps the filters in the URL and debounces typing. `TournamentCalendarController`
(`tournaments.calendar`, `/tournaments/{t}/event.ics`) serves a one-event all-day iCalendar file (RFC 5545 escaping and 75-octet folding) behind the "Add to calendar" button on unfinished events.

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
