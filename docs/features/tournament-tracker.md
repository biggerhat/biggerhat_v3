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

## Resetting a round ("Oh crap" button)
`TournamentRoundController@reset` (`tournaments.rounds.reset`, POST) sends an **in-progress** round back to `setup`: every game's result, scores, forfeit and self-report are cleared, linked tracker `Game`s are
destroyed (`TournamentTrackerGameFactory::destroyForRound`), `started_at` / `completed_at` are nulled. Pairings, tables and the scenario are kept so the organizer can fix them and start again. Guarded by
`TournamentStateMachine::canResetRound` (in-progress only; refused once the tournament is completed) and re-checked under a row lock. Completed rounds are deliberately not resettable (their results feed the next
round's pairings). Broadcasts `round_reset`. UI: the round card's options menu on the Rounds tab. Tests: `RoundLifecycleTest`; browser flow `scripts/ui-audit/tournaments/flows26.cjs` (phone width).

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
**Instant sign-up** (`tournaments.instant_signup`, individual events): `App\Tournaments\InstantSignup` turns an RSVP into a `TournamentPlayer` (account name, declared faction/allegiance) while a seat is free and no round is in progress, under a row lock so the last seat can't be taken twice. `WaitlistPromotions::notify()` seats waiting RSVPs oldest first when a seat opens (the promoted users get the usual notice), and switching the option on seats existing RSVPs. Cancelling the RSVP removes the player unless they are already in a pairing (then they use Withdraw). The RSVP row stays so the list and waitlist order are unchanged.
`TournamentSelfDropController` (`tournaments.withdraw`): a linked player in an individual event removes their seat and RSVP before the start, or is dropped after the round they are in
(else the last finished round) once it runs; organizers get a `TournamentPlayerWithdrew` notification. Team events stay with the organizer.

When a seat frees (a self-withdrawal before the start, an organizer removing a player, or a raised limit) `WaitlistPromotions::snapshot()` is taken before the change and `notify()` after it:
whoever was on the waitlist before and is not now gets a `TournamentWaitlistPromoted` notification. A waitlisted user cancelling their own RSVP does not trigger it.

## Tables
`TournamentPairingService::assignTables()` numbers the auto-paired games from the lowest table numbers not held by a manual pairing, and keeps each player off tables they sat at in earlier
rounds where the free tables allow it (most constrained pairing first; when no clean table is left the lowest free one is used). Table names and terrain sets are not modelled.
The `TournamentRoundPaired` notification already carries the table number.

## Event details and table terrain
**Details** — `tournaments.details` is a TipTap JSON document (the long version: venue, schedule, house rules, prizes) next to the one-line `description`. The organizer edits it on the Manage page's
**Details** tab with the blog's `TipTapEditor` (`:entities="false"`: no entity link/embed buttons) and saves through `TournamentDetailsController@update` (`tournaments.details`). Organizers are ordinary users,
so `App\Tournaments\RichText::sanitize()` rebuilds the document from an allow-list before it is stored: known node and mark types only, link targets must be http(s) or mailto, images must be files under
`/storage/tournaments/` (uploaded through `tournaments.details.image`, 5 MB, throttled), the whole document at most 100 KB. The public page renders it with `BlogContent` (structured JSON, never raw HTML).
**Tables** — rulesets opt in with `supports_table_details` (Malifaux only for now; `Ruleset::$supportsTableDetails`). `tournament_tables` rows hold a name, notes (2000 chars) and a photo/layout for a table number;
a row exists only while it has something on it (`TournamentTableController@save` / `destroy`, numbers 1-200, 404 for other rulesets). The payloads carry `tables` keyed by number (`[]` when unsupported).
Players see the name and terrain on their match card and the pairing rows; spectators get a collapsible "Tables & terrain" list; display mode shows the name next to the table number.
Uploads live on the public disk under `tournaments/{id}/` and are not removed when an event is soft-deleted.

## Side awards
`tournament_awards` (name, optional note, optional `player_id`) are the organizer's side prizes. They are managed on the Manage page's **Details** tab (`AwardsEditor`, up to 12 per event) through
`TournamentAwardController` (`tournaments.awards.store|update|destroy`). Giving an award to a competitor (or changing the recipient) sends `TournamentAwardGiven` to that player, or every member of a
team; renaming or re-saving does not. The public payload (`awards`) carries only awards that have a recipient, so an unassigned one is organizer-only; the Manage payload has them all. The public
page shows `AwardsCard`, and the results PDF lists them under the podium. Deleting a player leaves the award unassigned (`nullOnDelete`).

## Scorekeepers and the scoring desk
`tournament_scorers` (a plain pivot) lists accounts an organizer lets enter results without running the event; they are separate from `organizers`, so they never receive organizer notifications or reach
organizer pages. `Tournament::canScore()` = can manage, or is a scorer, and the policy ability `score` gates **entering/correcting a score** (`ScoreTournamentGameRequest`) and **forfeits**
(`TournamentGameToggleForfeitRequest`); everything else stays on `manage`. Organizers add and remove scorekeepers from the **Scorekeepers** card on the Settings tab (`TournamentScorerController`,
`tournaments.scorers.add|remove`, they get a `TournamentScorerAdded` notification linking to the desk). The desk is `Tournaments/Score.vue` (`tournaments.score`, `forScoring()` payload: players, every round's
games, standings, ruleset, table names): a round switcher, one card per pairing with Enter/Edit score, and the same `ScoreEntryDialog` the Manage page uses, including the optional master/title pickers (the `masters` list is the same deferred prop,
`TournamentPagePayloads::mastersDeferred()`, and is only sent for rulesets that track leaders). The public event page shows a "Scoring desk" link while the event is active to anyone who `can_score`.

## Top cut (single elimination)
After the Swiss rounds an organizer can run a cut of 2, 4, 8 or 16 players (`TournamentCutService`, `TournamentCutController`, `tournaments.cut.start|advance`, the `TopCutCard` on the Rounds tab and a next-step
guide entry). `start()` seeds the best Swiss players (not dropped, disqualified or ringers) and creates the first cut round already paired in bracket order (8 => 1v8, 4v5, 2v7, 3v6), so game k of a round feeds
game floor(k/2) of the next; `advance()` pairs the winners of the finished cut round the same way. Cut rounds are ordinary `tournament_rounds` rows with `is_cut = true`, so scenarios, scoring, the tracker, self-reporting
and the scoring desk all work; `tournaments.cut_size` records the size. Rounds name themselves by stage (`TournamentRound::$label` / `$short_label`: Finals, Semifinals, Quarterfinals, "Round of 16"; "R3" / "SF" in tabs).
Rules: pairing cannot be regenerated for a cut round; a cut round starts without every player being paired; `TournamentScoreRecorder` refuses a tied score in a cut game (a forfeit's non-forfeiting player advances; if a tie
somehow exists the better seed advances); the tournament cannot be finalized until the final is played; deleting the last cut round clears `cut_size`.
**Standings:** `TournamentStandingsService::computeSwiss()` leaves cut rounds out (seeds never move), and `compute()` applies `CutPlacings` once the final has a winner: champion 1st, finalist 2nd, each earlier
round's losers share the next place (semifinal losers joint 3rd, quarterfinal losers joint 5th), everyone outside the cut ranks after. Rows get `cut_finish` ("Champion", "Finalist", "Top 4"), shown as a badge and on the PDF.
Note `Tournament::rounds()` is already ordered by `round_number`, so use `reorder()` to get the latest one.

## Accessibility notes
Live pages (`View`, `Display`) include a `TournamentLiveRegion` (visually hidden, `role="status"`, `aria-live="polite"`). `useTournamentAnnouncements` compares each render of the rounds with the previous one and
writes sentences into it: "Semifinals are paired.", "Round 2 has started.", "Result reported for table 3.", "The tournament is finished." The first render announces nothing. `ScoringSummary` ends with a **Terms** glossary
(TP, VP, DIFF, Bye, and SoS when the event ranks by it) under "Format & rules" on the public page and the Manage settings.

## Importing a finished event
`/tournaments/import` (linked from the Create page) back-fills a past event from a CSV in the export's shape: `round`, `player_one`, `player_two` and scores (`player_one_vp` / `player_two_vp`, or one
`player_one_<part>_vp` column per score component for formats that split the score, e.g. Malifaux strategy + scheme), plus optional `table`, factions, masters, `result`, `is_bye`, `is_forfeit`. `ResultsImport::parse()`
checks the file without saving (line-numbered errors, warnings, counts; at most 10 rounds, 2000 rows, 256 players, 1 MB; rounds must run 1..N without gaps; nobody paired twice in a round; scores within the format's maximum) and
`create()` builds a **completed** tournament owned by the importer with players (matched by name, case-insensitive), completed rounds and games, so standings, the results sheet and profile history work. A forfeit's
loser is taken to be player two. The page checks the file first (`tournaments.import.preview`) and only a clean file can be imported (`tournaments.import.store`); `tournaments.import.template` serves an example for the chosen format.
Not imported: allegiances (Fields of Glory), teams, accounts links (players are unlinked names), crews, scenarios.

## Discord posts
An organizer can connect an event to a Discord channel webhook (Settings tab, `DiscordCard`; `tournaments.discord` PUT, `tournaments.discord.test` POST). The URL is stored encrypted in `tournaments.discord_webhook_url`, is hidden from every
serialisation (`$hidden`), and only `discord_connected` reaches the page. `TournamentDiscord::URL_PATTERN` allows only `https://discord.com|discordapp.com/api/webhooks/<id>/<token>` (checked on save and again before every post), redirects are
not followed, `allowed_mentions` is empty (event text cannot ping anyone) and a failing Discord is logged and ignored. Posts go through the queued `PostTournamentToDiscord` job (a no-op without a webhook; `whenConnected()` skips the queue
entirely): **registration opened** (date, format, place, limit), **round started** (the pairings, `**Table N** A vs B`), **announcement** (non-empty notices) and **final results** (podium + standings and PDF links). The test button posts
synchronously so the organizer sees right away whether Discord accepted it. No bot is involved; the separate Discord bot repo is untouched.

## Leagues (series)
A `TournamentSeries` (`tournament_series`; `/tournaments/series`) groups an organizer's events under one public leaderboard. Settings: a **points table** by finishing place (`points_table`, 1st..16th; default 10-8-6-5-4-3-2-1),
**participation points** for anyone who played but placed below the table, and an optional **best N events** rule. Only the creator (or a `manage_tournaments` holder) edits or deletes it (`TournamentSeriesPolicy`); series pages are public.
An event joins from its Manage Settings tab (`SeriesCard`, `tournaments.series.assign`; it must belong to a series the user created, and team events are refused). Deleting a series un-links its events (done explicitly in the controller).
`SeriesStandings::compute()` scores every **finished, individual** event in the series from the event's final standings (so top-cut placings count, joint places share points, ringers and players with no games are skipped).
A person is one league entry across events when their players share an account; unlinked players are matched by name (case-insensitive) and a name equal to a linked player's joins that account. Rows are ranked by counted points,
then wins, then best finish (equal on all three = joint rank) and carry per-event results flagged `counts` (struck through on the page when the best-N rule drops them). The event page shows "Part of the league ..." and links back.

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

## Status vocabulary and layout conventions
`composables/useTournamentStatus.ts` is the one source for status copy and colour: `roundStatus(round)` and `gameStatus(game, round)` return `{ label, tone }` (tones: success / warning / info / danger / muted)
and `badgeTone()` / `chipTone()` turn a tone into classes (filled badge vs. outlined chip). Don't hand-roll `bg-green-100 dark:bg-green-900` ternaries. On the Rounds tab a round card has a title row (name, status,
clock, options menu holding Delete / Reset) and an action row (Scenario on the left, the single next action on the right: Auto pair, then Start round, then End round). A game row has one visible action
(Enter / Edit score) plus an overflow menu (forfeit while playing, remove while setting up). The Manage header keeps Announce and Display as buttons, Share (copy link, QR) in a menu, and the status transition as the only
filled button. The public View page orders: results, awards, announcement, description, **the viewer's own state** (RSVP / match card / crew picker), live games, then event info (details collapse once the event is running)
and the tabs. Plan and status: [docs/plans/tournament-ui-polish.md](../plans/tournament-ui-polish.md).

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
