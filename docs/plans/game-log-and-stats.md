# Logging games played elsewhere, and in-depth game stats

Status: **built** (2026-11-23) on `game-log-and-stats`, from `nov23-updates`, as one branch and PR. What was built and where: [docs/features/game-tracker.md](../features/game-tracker.md) ("Logged games and game analytics"). The decisions below were settled with the maintainer: optional account linking (the other person confirms), formats are not limited to Standard (Standard, Campaign and Bonanza Brawl are all allowed as a label), logged games are private unless made public (the stats page itself is public), quick log plus optional detail, tournament games are in the analytics, solo games count by default with a way to leave them out. Differences from the plan below: the day played is `completed_at` (no separate `played_on` column) and there is no `opponent_kind` column (solo is `is_solo`; a logged game's opponent is a named or linked player); concessions are not a field (the tracker has none), the notes carry it; the work is one PR, not five.

## Goal

1. A player can **log a finished game** that was played and tracked somewhere else (paper, another app, a tournament round they only remember the result of).
   It records the same things a tracker game records, minus the setup and play flow.
2. **Analytics over every completed game**, whether it came from the Game Tracker or was logged by hand, in one place with filters.

## What exists today (read from the code)

- `games`, `game_players`, `game_turns`, `game_crew_members`. A game has strategy, deployment, scheme pool, season, encounter size, `winner_id` / `winner_slot` / `is_tie`,
  `completed_at`, `play_mode`. A player row has faction, master (`master_id` + `master_name`), `crew_build_id`, `role`, `total_points`, scheme state. A turn row has
  `strategy_points`, `scheme_points`, `scheme_id`, `scheme_action` (scored / held / discarded), `strategy_bonus_used`, `crew_snapshot`, notes. A crew member row has
  the model, cost, station, health, `is_killed`, upgrades.
- **A player with no account already exists**: solo games create slot 2 with `user_id = null` and `game_players.opponent_name`, `crew_skipped`. `winner_slot` covers
  a win by a player with no account. So a logged game can be an ordinary `Game` row. No parallel table is needed.
- `StatsController::show` (`/stats/{user:slug}`, page `Profile/Stats.vue`) already computes: win/loss/tie, VP, faction, master, scheme and keyword matchup stats,
  recent games. It reads only `status = completed` games, builds everything in PHP from loaded rows, and counts `is_solo` games as "solo".
- Campaign Mode has its own "Log game" (`CampaignGameController::storeSolo`), which writes `campaign_games`, not `games`. That stays as it is.
- Tournaments have `tournament_games` with a score breakdown and an optional tracker link.

## Design decisions

### 1. A logged game is a `Game` row with an origin

Add `games.origin` (`tracked` default, `logged`) as an enum (`GameOriginEnum`), and `games.played_on` (date, nullable; the day it was played, since `completed_at`
is when it was entered). Everything that reads "completed games" keeps working. Things that must **not** touch a logged game: the play screen, the setup flow
(`GameSetupFlow`), deployment, the board, Fate, websocket events, "abandon" and "delete after inactivity" jobs. Guard with `origin` in the routes' `can` checks and one
test that walks every `games.*` route and expects a logged game to be refused or read-only.

`is_solo` keeps its current meaning (one person playing both sides). Stats must stop using it to mean "against a person": add the explicit
`opponent_kind` the stats read: `self` / `named` / `user`.

### 2. "Same tracking options" means the same fields, entered after the fact

| Group | Fields (all optional except result) | Source today |
|---|---|---|
| Game | date played, how played (in person / online / tabletop / other), encounter size, season, format (Standard only in v1), strategy, deployment, scheme pool | `games` |
| Result | winner (me / opponent / tie), concession and turn it happened, total VP each | `games`, `game_players` |
| Each side | faction, master and title, role (attacker / defender), crew (link a saved crew build, or add models), crew upgrade, soulstone pool left | `game_players` |
| Each turn (up to the game's turns) | strategy VP, scheme VP, scheme chosen and what happened to it (scored / held / discarded), strategy bonus used, notes | `game_turns` |
| Each model | hired, killed or surviving, final health (optional), upgrades | `game_crew_members` |
| Extras | game name, game-level notes (new `games.notes`; today notes exist only on turns and models), event or venue label (new, short text), private flag (new) | `games.name`; new `notes`, `venue`, `is_private` |

Opponent: **name only in v1** (`opponent_name`), with their faction, master, scheme and model detail typed in by the same form. Linking to a real BiggerHat
account is a later option, because it needs the other person to confirm.

### 3. The form is progressive, not a wizard

One page, `Games/Log.vue`, in sections that open in order. **Quick log** is the top of the page: date, who won, VP each, my faction and master, opponent's faction and master. That is saved
and counts in stats straight away. "Add detail" sections below add the turns, schemes and crews. Detail already entered is kept if the player saves and comes back (edit
page is the same form). Phone first: each turn is a card, not a table.

Reuse, don't rebuild: the faction / master pickers and crew-build picker from `Games/Create.vue` and the setup panels, the scheme and strategy selects the tracker uses,
`GameCrewMemberRow` for the model list (read-only variant).

### 4. Analytics come from one service, not the controller

Extract the stats maths out of `StatsController` into `App\Support\Stats\` (a `PlayedGames` query scope plus small calculators that each return one block). The profile page
and the new analytics page both call it, so a number cannot differ between them. Two guards:

- Every block says **how many games it is based on**, and the UI shows rates only when n is at least 5 (otherwise "3 games"), with the interval on the headline win rate.
- Old tracker games lack data (no crew snapshot, no scheme state). Each calculator skips rows it cannot use and reports how many it skipped.

Performance: start with SQL aggregates and indexes (`games(status, completed_at)`, `game_players(user_id, game_id)`), cached per user and filter set, invalidated on game
completed, logged, edited or deleted. Do not add a denormalised facts table unless the profiler (`docs/performance.md`) shows the page is slow at a realistic game count.

## The stats ("in depth")

All filterable by date range, origin (tracked / logged / both), faction, master, encounter size, season, strategy, deployment, how played, opponent, event.

1. **Overview**: games, record, win rate with interval, average VP for and against, VP margin, concessions, streaks (current and best), games per month.
2. **Trends**: win rate and VP over time (rolling 10 games), games per month, by origin.
3. **My crews**: by faction, by master and title, by crew upgrade; best and worst, with n.
4. **Matchups**: faction vs faction and master vs master tables, plus the existing keyword matchups; head-to-head by opponent name.
5. **Strategy and deployment**: record and VP per strategy and per deployment, per encounter size and season.
6. **Schemes**: how often each is picked, scored, held and discarded; VP per scheme; first-turn vs later; scheme VP vs strategy VP split.
7. **Turn shape**: average VP per turn, the turn games are decided, win rate as attacker vs defender, win rate when scoring first.
8. **Models** (needs model data, so n shown): hired most, kill rate, survival rate, soulstone pool left, upgrades used.
9. **Game list**: sortable table of every game with origin badge, filters, and a CSV export (like the tournament analytics export, throttled).

Charts follow `docs/design-system.md` and the dataviz skill (the same palette in light and dark, tables as an alternative to every chart, no colour-only meaning).

## Phases (each its own PR, in this order)

1. **Schema and model.** `games.origin`, `played_on`, `notes`, `venue`, `is_private`, `opponent_kind`; enum, factory states (`->logged()`), observer and route guards for logged games.
   Backfill sets every existing game to `tracked`. Tests: guards (every game route against a logged game), migration up and down.
2. **Log a game.** `LoggedGameRecorder` service (one transaction, the same lock rules as other state changes), `StoreLoggedGameRequest` / `UpdateLoggedGameRequest`
   with per-rule rejection tests, controller, `Games/Log.vue`, entry points (Games index, Create Game page, profile), edit and delete (owner only, soft delete like other user content).
   Quick log first; detail sections after.
3. **Stats service.** Extract from `StatsController` with no change in output (a regression test compares old and new on a seeded set), then include logged games and
   the `origin` filter.
4. **Analytics page.** `/stats/{user}/analytics` (or a tab on the stats page), the nine blocks above, filters in the URL so a view can be shared, CSV export.
5. **Polish.** Help topic ("Logging a game" and "Reading your stats", written in the same change as the screens), `docs/features/game-tracker.md`, `docs/ui-audit.md`,
   browser flows for log and analytics at phone and desktop widths, axe, an empty state for no games and for too few.
6. **Optional later:** include tournament games; link an opponent's account with confirmation; import from a CSV; Discord `/stats`.

## Risks

- **Dirty numbers**: logging is typed by hand. Validate (VP ranges per turn, turns not above the game's max, scheme pool membership) and show a summary before saving.
- **Mixing "solo against yourself" with logged games** in stats: solved by `opponent_kind`; tracker solo games stay a separate bucket and are excluded from win rates by default (toggle).
- **Public profile**: stats are public at `/stats/{slug}` today. Logged games with opponent names must not leak names; the private flag hides a game from everyone but its owner.
- **`Games/Show.vue` is 6.7k lines**: the logged game's read-only summary is a new small page, not a mode of `Show.vue`.
- **Data volume**: fine for one player; the profiler check in phase 3 guards the heavy users.

## Open questions (defaults in bold; the plan assumes the defaults)

1. Opponent: **name only now, account link later**, or link accounts from the start?
2. Formats: **Standard only**, or also Campaign-flavoured games and Bonanza?
3. Visibility: **logged games count on the public profile, with a per-game private flag; opponent names never public**, or private by default?
4. Detail: **quick log plus optional detail**, or require turns and crews?
5. Tournament games in the analytics: **later**, or from the start?
6. Should the existing "is_solo" games (playing both sides) count in the win rate? **No, separate bucket, toggle to include.**
