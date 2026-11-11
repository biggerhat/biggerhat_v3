# Tournament Rulesets — game-agnostic tournament tracking

Status: **Phases 1–5 and 6a done** on branch `tournament-rulesets-nov10` (uncommitted); Phase 6b (team events) designed, not started.
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

**4b (done, not browser-tested):** TOS Operations and Deployments are database reference data (`tos_operations`, `tos_deployments`; admin CRUD under
`/admin/tos/operations|deployments`, permissions `view/edit/delete_tos_operation|deployment`, idempotent `OperationSeeder` / `DeploymentSeeder` with the four
Fields of Glory Operations and four suit-keyed Deployments). A round's ruleset-defined scenario lives in `tournament_rounds.scenario` JSON
(`{"operation_id": 1, "deployment_id": 2}`); ruleset `scenario_fields` accepts `tos_operation` / `tos_deployment`. **Deviation from the original plan:** Malifaux keeps
its `strategy_id` / `deployment` / `scheme_pool` columns (FK + Game Tracker depend on them) instead of moving into the JSON. Ruleset `supports_tracker` stops
Fields of Glory rounds from spawning Malifaux tracker games. Manage gets Operation/Deployment pickers (autosave, reference drawer), View shows them on the round.
Known gaps: Core Rulebook Operations must be added through the admin UI; Deployment diagrams/images are not seeded.

### Phase 5 — Player declaration and tracker integration (done, not browser-tested)
Ruleset `declaration` (`faction` | `allegiance` | `none`), `requires_declaration` and `tracks_leaders` replace the old `requires_faction`.
`tournament_players` gets `allegiance_id` + `garrison_id` (and RSVPs `allegiance_id`). Fields of Glory players declare an Allegiance (fixed for the event) and may
link a Garrison by share code — public Garrisons, or the linked user's own, matching the declared Allegiance; the share link is only exposed when the Garrison is
public. The state machine checks the right column before the event starts, the Manage add/edit/RSVP flows use a shared `DeclarationFields` component, standings, the
player list, the public pairings and the CSV show the Allegiance, and the score dialog hides master/title when `tracks_leaders` is false. The tracker opt-out
(`supports_tracker`) landed with Phase 4b. Also fixed: registering a user-linked player returned a 500 (the notification left a column-restricted `tournament` on the
returned player).

### Phase 6 — Variants, custom rulesets, teams
**6a (done, not browser-tested):**
- *Variants:* `tournaments.variants` JSON (`{"alternating_leaders": true, "bans": 2}`), catalog per ruleset in `config/tournaments.php` (`variants`, optional numeric `param`).
  Gaining Grounds gets the packet's optional variants (encounter-type-covered ones like Crossroads/Team Event are not duplicated); Fields of Glory gets Close
  Objectives, Strategic Alliance and Decisive Victory. Picker on Create and Manage settings (locked once the event starts); players see them under
  "Format & rules" on the public page. Alternating Leaders also warns in the score dialog when a master (by title) was already played in an earlier round.
- *Custom scoring:* every ruleset can be customized through `scoring_config` overrides (win/tie/loss/base TP, DIFF-adds-to-TP, DIFF cap, forfeit values, tiebreaker
  order) via `ScoringEditor`; only changed keys are stored, the public page marks the event "Modified from <format>", and `ScoringSummary` explains the effective rules in
  plain language. A blank-slate `custom` ruleset (no Malifaux/TOS requirements) is the starting point for other games.
- Fix found on the way: `tiebreakerKeys()` now keeps the TO's order for an overridden tiebreaker list.

**6b (team events, not started — design only):** Fields of Glory "No Man's Land" and Gaining Grounds "Team Event"/"Double Rush" make a *team* the unit that is
paired and ranked. Recommended shape: a `tournament_teams` table (name, tournament_id) plus `tournament_players.team_id`; pairing, byes/ringers and standings key off a
"competitor" (team when the ruleset/variant is team-based, otherwise the player), with each player's own game results rolled up into the team's TP/DIFF/VP. The
`TournamentGame` sides then reference competitors rather than players. This touches pairing, standings, score entry and nearly every page, so it should be its own
reviewed PR rather than bundled with this series.

### Review fixes (done, not browser-tested)
Pairing (`TournamentPairingService::matchPlayers`): ties in the standings are now paired in random order (shuffle before the stable sort); the greedy first-fit pass
is replaced by a backtracking search that finds a rematch-free matching whenever one exists (relaxing no-rematch+no-same-meta → no-rematch → anything, closest
standings first, 50k-node budget per level). **Agreed results:** the score dialog has an "Agreed result" checkbox that stores `result = agreed` (scored exactly like
a played game, badge on the pairings, never overwritten by tracker sync). **Round clock:** rulesets carry `time_rules`; the clock labels the last turn and its tooltip
shows the format's last-turn / time-called wording.

### Player-facing follow-ups (done, not browser-tested)
Self-reporting with opponent confirmation, check-in, big-screen display mode, and the first cut of splitting `Manage.vue` (score dialog and settings form extracted;
3,349 → ~2,730 lines). Not done: extracting the player dialogs / next-step guide from Manage, and de-duplicating the mobile/desktop pairing rows in `View.vue`.

## Deliberately out of scope for now
Per-game scenario content tables for non-Wyrd games, round-robin/elimination pairing (pairing stays Swiss;
a `pairing` strategy hook is left for later), importing results from other tournament software.

## Open questions
- Team events: separate `TournamentTeam` entity, or generalize `TournamentPlayer` into "participant"?
- Do TOS Operations (Deep Recovery, Build Fortifications, …) become reference data in the DB (like Malifaux
  strategies)? Needed for Phase 4's TOS scenario descriptor.
- `GameSystemEnum` is a closed database enum; tournament game keys are deliberately free strings. Decide whether
  the two should ever be linked (e.g. for filtering the tournament list by the active game-system switcher).
