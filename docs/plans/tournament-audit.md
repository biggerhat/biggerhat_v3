# Tournament Tracker audit (2026-11-11, branch `tournament-rulesets-nov10`)

Scope: pages in `resources/js/pages/Tournaments/*`, `components/Tournament/*`, `app/Http/Controllers/Tournament/*`, `app/Services/Tournament*`, `app/Tournaments/*`.
Method: read the code, reviewed the screenshots from the 47-step browser pass (guest / player / organizer, desktop + phone), profiled the public pages
with `scripts/ui-audit/profile.php` (16 queries, 14 KB props on a small event; no N+1 signature). Findings are *verified against code* unless marked "to verify".

Severity: **P1** wrong behaviour / data exposure, **P2** clear UX or maintainability gain, **P3** polish.

## 1. Correctness and privacy

| # | Sev | Finding | Where |
|---|---|---|---|
| C1 | P1 | **Pairing ignores the ruleset's tiebreakers.** `sortByStandings()` hard-codes TP → DIFF → VP, so Fields of Glory (TP → SoS, DIFF is part of TP) pairs on the wrong order. Standings use `tiebreakerKeys()`; pairing should use the same ranked list (`StandingsRanker` output / `placing`). | `TournamentPairingService::sortByStandings` (~l.325) |
| C2 | P1 | **Public payload over-exposes.** `viewProps` serialises whole `TournamentGame` and `Game` models: `report_note`, `reported_score`, `reported_by_player_id`, `player_*_crew_build_id`, tracker `Game.settings` / `loot_state` / `creator_id`, plus `user_id` / `meta_id` on players. A dispute note between two players is world-readable. Build the payload from an API Resource / explicit arrays (also shrinks props). | `TournamentController::viewProps` ('games' => `$round->games`, `tournament` serialised whole) |
| C3 | P2 | `updateScore` checks `round->status` outside any lock, so a score save racing "End Round" can write into a completed round. CLAUDE.md's rule is lock + re-check inside the transaction (the report/confirm paths already do). | `TournamentGameController::updateScore` |
| C4 | P2 | Display mode and the public view reload on every event with no fallback: if the websocket drops (venue wifi) the TV silently goes stale. Needs a "live / reconnecting" indicator and a slow poll fallback (e.g. 30 s `router.reload({ only })`). Display also calls an unrestricted `router.reload` (all props incl. the full standings + rounds) on each event. | `useTournamentChannel.ts`, `Display.vue` |
| C5 | P3 | Header reads "Gaining Grounds 0" and "50ss Traditional" — `season_label` for a ruleset that `uses_season` shows the raw season name with an unexplained number; "50ss" is unexplained jargon (50 soulstones). Check what `PoolSeasonEnum::label()` returns for current data; spell out "50 Soulstones". (to verify with real season data — seed data may be the cause) | `Tournament::getSeasonLabelAttribute`, headers on View/Manage |
| C6 | P3 | No tests assert the public payload's *shape* (only that it renders), which is how C2 slipped in. | `PublicViewPageTest` |

**Status (branch `tournament-audit-fixes-nov11`):** C1, C2, C3, C4 and C6 are fixed with tests (pairing now orders by standings `rank`;
public payload redacts report details / crew build ids / tracker settings / player meta; score save re-checks the round under its lock;
`useTournamentChannel` exposes `connected`, polls every 30 s while down, and Display shows Live / Reconnecting). C5 was checked: "Gaining Grounds 0" is a real
season name (`PoolSeasonEnum::GainingGrounds0`), so only the "50ss" jargon remains.

**Status of the organizer pass (branch `tournament-organizer-ux-nov11`):** U1 (one Start button, in the next-step card), U2 (Manage opens on Players while setting up,
Rounds while the event runs, Standings when it is over), U3 (End Round reason shown inline), U4 (radix checkboxes, masters/titles folded away, Enter saves; the "next game"
keyboard flow is not done), U5 (faction-spine player rows), U6 (Meta folded away in the add form), U7/F1 (click-to-swap players, unpaired players and table numbers during
round setup: `POST .../rounds/{round}/swap` and `/swap-tables`, rematch warnings, touched pairings become manual) and U8 (results CSV on the Standings tab) are done.
Still open from this group: U9 (phone Rounds-tab action row).

**Status of the spectator/player pass (branch `tournament-spectator-ux-nov11`):** U10 (status chip per pairing — Paired / Playing · turn / Final — winner emphasis, "vs" instead of dashes),
U11 (2xl screens show the standings beside the pairings), U12 (display mode: bigger controls that fade when idle, per-side scores with winner emphasis, "Round N is paired — find your table",
a roster instead of a table of zeros before round 1, a scan-to-follow QR), U13 (the opponent's proposed result is a large You – Them score with the component breakdown, one tap to confirm),
F2 (organizer announcements: `tournaments.announcement` column pair, `PUT /tournaments/{t}/announcement`, banner on View and Display, live-region), F17 ("Live now" strip of in-progress tracker games on View)
and U16 for announcements (polite live region) are done. **Deploy: run the new migration.** Not browser-tested: the Live-now strip (the seed has no in-progress tracker game).

**Status of the structure pass (branch `tournament-structure-nov11`):** Q1 (the Rounds tab is `ManageRoundsTab.vue`; `Manage.vue` 1,933 -> ~880 lines; payload builders moved out of
`TournamentController` into `TournamentPagePayloads`, 783 -> ~365 lines), Q2 (resolved by that move: view and manage never shared round-building), Q3 (the tournament pages' `any`s replaced with
the shared types; what remains is the websocket plumbing) and Q6 (`isScoredResult` / `TournamentGameResult`) are done. Q5 (standings caching) was measured and deliberately skipped: the page loads every player,
round and game anyway to build its payload, so a cache keyed on their state saves almost nothing while adding an invalidation risk. Found and fixed on the way: a row menu that opened a dialog left
the page unclickable (`pointer-events: none` on `body`) after the dialog closed; the next-step guide offered "Start Round" while the server would refuse (scenario incomplete or a player unpaired) and
now says what's missing; the "games still need a result" count included byes.

**Status of the first features (branch `tournament-capacity-nov11`):** F3 (player limit, registration deadline, computed waitlist with positions shown to players and organizers) and F4 (self-withdrawal for
individual events, with organizer notification) are done. **Deploy: run the new migration** (`2026_11_16_100000_add_capacity_to_tournaments`). Not done from F3: notifying someone when they move up the waitlist.

## 2. UI / UX

### Organizer (Manage)
- **U1 Duplicate primary action.** Registration stage shows "Start Tournament" in the top bar *and* in the next-step card. Keep it in the card, drop the top-bar one (keep Back to Draft as a quiet link).
- **U2 Guide card vs status:** when the guide is sensible, the Standings tab is still the default. Default to the tab the guide points at (Players in setup, Rounds when playing).
- **U3 Rounds tab is the main working screen but dense:** per-round buttons ("Scenario", "End Round" greyed with no explanation of why). Show the blocking reason inline ("1 game still needs a score") — the state machine already produces the string.
- **U4 Score entry:** native-styled unchecked checkboxes (Agreed result, Drop after this round) don't match the radix checkbox used elsewhere; master/title pickers sit above the VP fields although most organizers only type scores. Collapse master/title behind "Add masters (optional)". Add keyboard flow (Tab through score fields, Enter to save, next game).
- **U5 Players tab rows** are fully saturated faction fills (blue / red / olive / purple) with small white text — heavy and hard to scan on a 40-player list. Use the faction spine from `docs/design-system.md` (left bar + tint) like `StandingsTable` already does. Add sort/filter (checked-in, dropped), and a roster search once > 16 players.
- **U6 Players add form** puts "Meta" before "Faction"; Meta is rarely used and has its own "+" inline create. Move it behind "More options".
- **U7 Pairing editing:** only auto-pair / randomize / add manual game. No swap-two-players or drag between tables; correcting a bad pairing means deleting games. A "swap" action on a pairing row is the common TO need.
- **U8 Export lives in Settings** ("Export results"). Surface CSV on the Standings tab and add print/PDF standings and a results summary for the end of the event.
- **U9 Phone:** Manage works at 390 px (no overflow), but the Rounds tab action row wraps into three stacked buttons; the pairing row's actions hide behind "…" with no label.

### Players and spectators (View / Display)
- **U10 Pairings show "– - –" for unplayed games** with no status; completed games show the score but not who won. Show winner emphasis, a "playing" / "reported" / "final" chip, and the tracker link only when a live game exists.
- **U11 View has a lot of dead space** on desktop (the pairing rows are a single full-width line, scores centred). Consider a two-column layout on `lg`: pairings | standings, instead of the Standings / R1 tab strip hiding one behind the other.
- **U12 Display mode:** tiny control buttons (Auto / Pairings / Standings / Exit) are unreadable at a distance and stay on screen — auto-hide after a few seconds; pairings use ~1/3 of the screen; no scores or "table finished" state; add a QR to the public page, the next-round countdown and a "Round N paired" announcement state. Standings before round 1 show a column of T-1 with all zeros — show "Round 1 starts soon" and the roster instead.
- **U13 Player's own match card:** after "Report result" the player has no view of what a confirm/dispute will do. Show the opponent's proposed score prominently with a one-tap Confirm and a short reason field for Dispute.
- **U14 Create form** is one long single-column page; fine on phone, wasteful on desktop. A stepped layout or a right-hand live summary ("what this ruleset means") would help first-time organizers. The "Game & Format" picker should be the first thing and explain each ruleset in one line.

### Cross-cutting
- **U15 Accessibility:** progress strip uses `aria-current` (good); status chips rely on colour only in the players/rounds lists; the TO's colour-filled player rows depend on faction colours for contrast (verify with axe — the harness `scripts/ui-audit/audit.cjs` supports this and has not been run on the tournament pages yet).
- **U16 Live regions:** results and round changes arrive via websocket reload with no announcement. Add a polite `aria-live` toast ("Round 2 paired").
- **U17 Terminology:** "Meta", "Encounter Size", "SoS", "Diff", "TP/VP" appear unexplained on the player-facing pages (titles exist only on table headers). Add a one-line glossary under Format & rules.

## 3. Code quality

- **Q1 Biggest files:** `Manage.vue` 1,792, `ManagePlayersTab.vue` 1,052, `View.vue` 805, `TournamentController` 750 lines. Next extractions: the Rounds tab (scenario editor + game rows) from `Manage.vue`; `viewProps` / `manage` payload builders out of the controller into `App\Tournaments\Payloads\*` (this is also the fix for C2).
- **Q2 Duplicated public-payload building:** `viewProps` and `manage` each build rounds/schemes/scenarios. One `RoundPayload` builder removes the divergence risk.
- **Q3 `any`:** 21 uses across the tournament pages (Manage.vue 10). Most are round/game callbacks; `types/tournament.ts` already has the shapes — use them (and `useTournamentNextStep` still has three `any` params for the same reason).
- **Q4 No vue-tsc:** template mistakes are only caught by my compile-and-grep trick. Adding `vue-tsc --noEmit` for `Tournaments/*` as a non-blocking CI step would catch the class of bug found in the earlier extractions.
- **Q5 Standings are recomputed per request** (public view, display, pairing, manage). Cheap today (160 games ≈ ms), but every websocket event makes every viewer recompute. Cache per `(tournament id, updated_at of latest game)` or push the computed standings in the broadcast payload.
- **Q6 Stringly-typed ruleset keys** in the frontend (`ruleset.uses_season`, etc. are flags — good — but `result` values like `'completed' | 'agreed' | 'forfeited'` are repeated in four files). One `TournamentGameResult` TS const/union.
- **Q7 Browser flows in CI:** the 47-step pass is local-only. A nightly/pre-release job (needs Chrome) would have caught the radix checkbox and banner bugs earlier. At minimum add `docs/features/tournament-tracker.md` pointers (not yet linked to `scripts/ui-audit/tournaments`).
- **Q8 Docs:** `docs/features/tournament-tracker.md` still lists "Malifaux-only" intro and services without the new `ScoringEngine` flow; update intro + link the audit script.

## 4. Feature ideas

Ranked by value for a real event, with effort.

| # | Feature | Why | Effort |
|---|---|---|---|
| F1 | **Swap / move pairings** (swap two players, move a player to another table, set tables manually) | The most common mid-event correction | S |
| F2 | **Announcements** from the TO (banner on View + Display, optional push via existing notifications) | Late starts, table changes, lunch | S |
| F3 | **Capacity, waitlist and registration close** (`max_players`, `registration_closes_at`, auto-promote from waitlist on drop) | Venues have fixed tables | M |
| F4 | **Self-drop** (player withdraws; TO confirms or auto) and **late join** with bye handling | Currently TO-only | S |
| F5 | **Table assignment** (table count + named tables / terrain sets; pair → table mapping with "avoid repeat table") | Realistic venue handling | M |
| F6 | **Results summary + shareable image/PDF** (podium, standings, best painted / sportsmanship awards) | Post-event sharing, Discord | M |
| F7 | **Side awards** (best painted, sportsmanship, favourite game) with votes | Common in the community | M |
| F8 | **Calendar integration** (`.ics`, "add to calendar" on View; Discord event post via the bot API) | Discovery, reminders | S |
| F9 | **Player history** on the profile: events, record, performance by faction, head-to-head vs. other players (Stats page already mentions tournaments) | Retention | M |
| F10 | **Crew/list submission and check** (attach a `CrewBuild` or Garrison at registration; lock at start; TO sees legality) — reuses Crew Builder | Closes the loop with the rest of the site | M |
| F11 | **Public event directory improvements**: filter by game / ruleset / date / location, "near me" | Index is grouped only by status | S |
| F12 | **Elimination / round-robin / top-cut** pairing strategies (the `pairing` hook is already left open in the plan) | Larger events and leagues | L |
| F13 | **Seasonal leagues**: series of events with cumulative points and a league table | Campaign-like long-term play; reuses rulesets | L |
| F14 | **Results import/export**: Longshanks-style CSV import, JSON backup, event copy including players | Interop (the CSV export exists; no import) | M |
| F15 | **Co-organizer permissions** (scorer-only role, read-only judge) | Large events | S |
| F16 | **Notifications**: round paired (exists), result needs confirming, you're on table N, 10 minutes left (clock) | Players leave the table area | S |
| F17 | **Live spectate**: a "Games in progress" strip linking tracker observe views with current VP | Reuses Game Tracker, already linked per pairing | S |
| F18 | **Per-round scenario packs for non-Wyrd games** (listed out of scope in the plan) | Only if a third game is actually wanted | L |

## 5. Suggested order of work

1. **Correctness / privacy (1 PR):** C1, C2 (+ shape test C6), C3, C4 indicator + fallback.
2. **Organizer UX (1 PR):** U1–U6, U8 (small, mostly template changes), then U7 swap pairings (F1).
3. **Spectator/player UX (1 PR):** U10–U13, F2 announcements, F17.
4. **Structure (1 PR):** Q1–Q3, Q5 — extract Rounds tab, payload builders, tighten types.
5. **Features, in order of value:** F3, F4, F6, F10, F9, then F5/F8/F11.
6. **Later:** F12/F13 once there's demand.

## 6. Not verified here

- axe contrast / landmark results on the tournament pages (harness exists, not run).
- MySQL behaviour of the new migrations (all browser work was SQLite).
- Large-field performance (> 64 players) — only the pairing search budget (50,000 nodes) was reasoned about, not measured.
