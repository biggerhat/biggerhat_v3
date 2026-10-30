# Tournament Tracker

Run a Malifaux tournament: registration/RSVP, Swiss rounds + pairings, scoring,
standings. Integrates with the [Game Tracker](game-tracker.md) — a tournament
match can spawn/track a live Game.

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
