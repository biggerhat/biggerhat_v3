# Tournament Rulesets — game-agnostic tournament tracking

Status: **Phases 1–3 and 4a done** on branch `tournament-rulesets-nov10` (uncommitted); Phase 4b (scenario as data) and Phases 5–6 not started.
Notes from implementation: rulesets are config-only (`config/tournaments.php`, no per-game classes); `tournaments.bye_*` columns are now nullable overrides (null = ruleset value); `ruleset` is a plain string column, unknown keys fall back to the default ruleset.

## Goal

The Tournament Tracker is Malifaux-only today. It must be able to run events for **any** game, not just
Malifaux and The Other Side (TOS). Those two are our first two *rulesets*, not special cases. Adding a third
game must be "write a ruleset definition", not "touch every service".

Source rules: `Malifaux_Gaining-Grounds-One_Rules.pdf` (Gaining Grounds One) and `Fields_Of_Glory.pdf`
(TOS Fields of Glory v3.0). Neither is checked in; both live in `/var/www`.

## Design principle: data over code

The two official formats differ in *numbers and ordering*, not in structure. So a ruleset is mostly **data**
interpreted by one generic engine, not a class per game:

| Concept | Gaining Grounds | Fields of Glory | Generic parameter |
|---|---|---|---|
| TP per game | 3 / 1 / 0 | 10 base + DIFF + 2 for a win | `base_tp`, `win_tp`, `tie_tp`, `loss_tp`, `diff_adds_to_tp` (tp = base + outcome + DIFF if enabled) |
| DIFF | uncapped | capped at ±8 | `diff_cap` (nullable) |
| Bye | 3 TP / +4 / 6 VP | 19 TP / +7 / 7 VP | `bye` {tp, diff, vp} |
| Forfeit | 0 / −11 / 0 vs 3 / +11 / 11 | 0 / −8 / 0 vs 20 / +8 / 8 | `forfeit_vp` (winner gets this VP and DIFF; loser TP = 0) |
| Standings | TP → DIFF → VP | TP → SoS | ordered `tiebreakers` list |
| Rounds by field size | 3/4/5 | 3/4/5 | `suggested_rounds` table |

A custom/TO-defined ruleset is then the same engine with TO-supplied parameters (no new code path).

Anything that is genuinely structural (scenario shape, what a player declares before the event, score
breakdown fields, tracker integration) is described by **descriptors** the frontend renders generically,
rather than hard-coded Malifaux forms.

## Core pieces

- **`Ruleset` definition** (`app/Tournaments/Rulesets/`): key (`malifaux_gaining_grounds`, `tos_fields_of_glory`,
  `custom`), label, `game_system` key (free string — *not* tied to `GameSystemEnum`, which only models the
  BiggerHat database), and the parameter groups below.
- **`RulesetRegistry`**: resolves a key to a definition; registered from `config/tournaments.php` so a new
  game is a config entry + a definition class (or just config, for pure-data rulesets).
- **`ScoringConfig`** (value object): the scoring parameters above, with per-tournament overrides layered on
  the ruleset's defaults (`tournaments.scoring_config` JSON). Resolution order: tournament override →
  legacy `bye_*` columns → ruleset default.
- **`ScoringEngine`**: pure functions `gameResult(vpA, vpB, scoring) → {tp, diff, vp}`, `bye(scoring)`,
  `forfeit(scoring, side)`. `PlayerStatsCalculator` delegates here instead of hard-coding 3/1/0 and 11.
- **Tiebreaker keys**: `StandingsRanker` takes an ordered list of keys (`tp` always first, then any of
  `sos`, `diff`, `vp`, …) instead of the two-value `TournamentTiebreakerEnum`. The enum stays as a
  back-compat mapping (`diff_vp` → [diff, vp]; `sos` → [sos, diff, vp]).

## Phases

### Phase 1 — Ruleset foundation (this PR)
- Migration: `tournaments.ruleset` (string, default `malifaux_gaining_grounds`, backfills every existing row) and
  `tournaments.scoring_config` (nullable JSON).
- `Ruleset`, `RulesetRegistry`, `ScoringConfig`, the Gaining Grounds and Fields of Glory definitions.
- `Tournament::ruleset()` / `scoringConfig()` accessors.
- No behaviour change for existing tournaments.

### Phase 2 — Data-driven scoring and standings (this PR)
- `ScoringEngine`; `PlayerStatsCalculator` and `TournamentStandingsService` use `ScoringConfig`.
- `StandingsRanker` takes ordered tiebreaker keys; legacy enum mapped.
- Tests: **parity** (Gaining Grounds output identical to today's), plus Fields of Glory worked examples from
  the PDF — a win 8–4 is `16 TP / +4 / 8 VP`; bye `19 / +7 / 7`; forfeit `0 / −8 / 0` vs `20 / +8 / 8`;
  DIFF cap; ranking by TP → SoS only.
- Validation: raise `bye_tp` max (currently 5; Fields of Glory needs 19).

### Phase 3 — Choose a ruleset (done; not browser-tested)
- Create form: ruleset picker; prefill planned rounds / round time / bye / tiebreakers from the ruleset.
- Index filtered/badged by ruleset; copy-from-event carries the ruleset.
- Settings tab shows the active scoring rules in plain language (what the engine will actually do).
- Player-count → suggested-rounds hint (both packets use 3/4/5).

### Phase 4 — Score breakdown and scenario as data

**4a (done):** `tournament_games.score_breakdown` JSON (`{"player_one": {"strategy": 3, "scheme": 2}, ...}`) replaces the four strategy/scheme VP columns
(migration backfills and is reversible, round-trip tested). Ruleset `score_fields` define the components, min/max and labels; the score request validates
`player_one_score.<key>` / `player_two_score.<key>`, the controller sums them into `player_*_vp`, the Manage score dialog and CSV export render from them.
Ruleset `scenario_fields` / `requires_faction` now drive the state machine, so a Fields of Glory event can pair and start rounds without a Malifaux
strategy/deployment/scheme pool or faction (it simply has no scenario UI yet).

**4b (not started):**
- `tournament_games`: replace `player_*_strategy_vp` / `player_*_scheme_vp` with a JSON `score_breakdown`
  (migrate existing values); ruleset declares the score fields (label, min, max). Total VP stays a real column.
- `tournament_rounds`: `strategy_id` / `deployment` / `scheme_pool` → JSON `scenario` described by a ruleset
  scenario schema (Malifaux: deployment + strategy + schemes; TOS: operation + deployment; others: free text).
- Manage/View render scenario and score entry from the descriptor.

### Phase 5 — Player declaration and tracker integration
- Replace the hard-wired "faction required" rule with a ruleset-declared check (Malifaux: faction; TOS:
  allegiance + Garrison; other games: none/free text).
- TOS: link a player's Garrison; show it on the pairing (it is public information in Fields of Glory).
- Tracker link (`TournamentTrackerGameFactory`) becomes a ruleset capability (`supports_tracker`).

### Phase 6 — Variants, teams, custom rulesets
- `variants` JSON of named optional rules (Close Objectives, Decisive Victory, Alternating Leaders, Bans …)
  surfaced to players and, where checkable, warned on at score entry.
- Team entities (Team Event / No Man's Land): a team stands as one competitor in standings.
- UI to build a custom ruleset (the same `ScoringConfig`, edited by the TO).

## Deliberately out of scope for now
Per-game scenario content tables for non-Wyrd games, round-robin/elimination pairing (pairing stays Swiss;
a `pairing` strategy hook is left for later), importing results from other tournament software.

## Open questions
- Team events: separate `TournamentTeam` entity, or generalize `TournamentPlayer` into "participant"?
- Do TOS Operations (Deep Recovery, Build Fortifications, …) become reference data in the DB (like Malifaux
  strategies)? Needed for Phase 4's TOS scenario descriptor.
- `GameSystemEnum` is a closed database enum; tournament game keys are deliberately free strings. Decide whether
  the two should ever be linked (e.g. for filtering the tournament list by the active game-system switcher).
