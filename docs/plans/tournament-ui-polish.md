# Tournament front end: UI/UX polish plan

Status: **steps 1-6 implemented 2026-11-12 (compiled, linted, tests green; tournament browser suite 206/206 passing at phone width, 2026-11-12, after updating two flows to the new wording/collapsed details). No before/after axe comparison was taken.** Step 6 turned out smaller than planned: the meta field was already behind a disclosure, so only the opacity-dimmed player rows were fixed. Decisions: Delete round goes in an options menu; organizers are mostly on phone/tablet. Written 2026-11-12 from a read-through of `pages/Tournaments/*` and
`components/Tournament/*` (about 9.4k lines). No browser pass yet: step 0 below is to take screenshots with the
[UI audit harness](../ui-audit.md) so each finding is confirmed visually before it is fixed.

Guiding rules: [design-system.md](../design-system.md) (surfaces, type scale, touch targets, colour tokens) and
CLAUDE.md (`<Link>` for navigation, no browser dialogs). Presentation only: no payload, route or behaviour changes
except where a finding says so.

## What the review found

### A. Buttons crammed into rows (the "forced in" feeling)

1. **Round card header** (`ManageRoundsTab.vue` ~L640-760). One wrapping row holds: round name, status badge, clock,
   strategy name, a bare delete "X", then Scenario, Auto Pair, Start Round, End Round and the new Reset Round. Up to
   seven controls on a phone, with the destructive ones (delete round, reset round) sitting beside the primary action.
2. **Manage page header** (`Manage.vue` ~L446-480). Back, Share, Display, Announce, QR, Back to Draft and the status
   transition button are all one flat row of equally weighted outline buttons. The status transition, the one
   primary action, is visually lost. "Back" is a `Button` with `router.get` (against the `<Link>` rule) and duplicates
   the breadcrumb.
3. **View page action strip** (`View.vue` ~L500-520). "Scoring desk" and "Add to calendar" each get their own full
   row of vertical space (`mb-4` wrapper per button) instead of sharing one toolbar.
4. **Per-game row** (`ManageRoundsTab.vue` ~L920-1010). Status chip, score, Enter/Edit score, a "…" menu and a bare X
   to remove the game, all trailing the names. The X (setup) and the "…" (in progress) are never shown together, but
   they are two different patterns for "secondary actions".
5. **Players tab add form** (`ManagePlayersTab.vue` ~L610-700). A four-column grid whose Meta cell contains a combobox
   plus two more icon buttons (add, clear) beside it. Dense and visually unbalanced against the Add button.
6. **Bare `<button>` elements**: 14 in `ManageRoundsTab`, 5 each in `View` and `ManagePlayersTab`, 3 in `Manage`.
   Several are icon-only (delete X, remove-game X, eye previews) and are ~20px, below the coarse-pointer touch
   target the `Button` component grows to automatically.
7. **Fixed heights override the touch-target growth**: 44 `h-7`/`h-8` classes. `Button` only grows on
   `(pointer: coarse)` when it has no explicit height, so the round-card buttons stay 28px on a phone.

### B. Visual consistency

8. **Hard-coded palette**: 144 uses of `green/amber/blue/emerald/red-NNN` classes. The round status badge builds its
   own colour ternary inline, while a separate `useTournamentStatus.statusColor()` exists for the tournament status.
   Round status, game status and tournament status are three unrelated colour systems.
9. **Status vocabulary is inconsistent**: the round badge says "Setup / Paired / In Progress / Completed", the game
   chip says "Not started / Waiting for score / Tracker abandoned", and `View.vue` re-implements the tournament
   status label in a nested ternary instead of using `useTournamentStatus`.
10. **Page chrome**: `Manage` and `View` repeat a decorative radial gradient overlay plus a metadata line in the
    banner subtitle. The Manage metadata line is a run of unseparated `span`s (date, place, size, round, season).
11. **View page is a long stack** of full-width items before the actual event content: results, series link, awards,
    announcement, description, details, tables, scoring desk, calendar, format and rules. Several are collapsed
    `<details>`, several are not, with no consistent grouping or order of importance.
12. **Round scenario summary** is `text-2xs` plain text with no labels (deployment, strategy and scheme names just
    run together), so it reads as noise rather than "this round's scenario".
13. **Pending-scores indicator** uses a spinning loader for something that is not loading; it reads as a hung request.

### C. Flow and affordances

14. **Destructive actions have no separation**: delete round, remove game, reset round and forfeit are scattered
    across the header, row and menu with different styling each time.
15. **Click-to-swap pairing** is discoverable only via a small hint line under the games. The tappable names and
    table chips give no visual hint (no hover/cursor/affordance until picked).
16. **Next-step guide vs. buttons**: `NextStepCard` and the round header both show "what to do next"; when both are
    visible the primary action appears twice with different styling.

## Plan

Ordered so each step ships on its own and the riskiest visual changes come last.

### Step 0: baseline (small)
- Run the UI audit harness for `/tournaments`, a tournament view, and Manage (Rounds, Players, Settings tabs) at phone,
  tablet and desktop widths, with an in-progress round. Save screenshots to the scratchpad and confirm or drop each
  finding above. Record the axe results for a before/after comparison.

### Step 1: one status system (A/B: 8, 9, 13)
- Extend `composables/useTournamentStatus.ts` with `roundStatus(round)` and `gameStatus(game, round)` returning
  `{ label, tone }` from semantic tokens (success / warning / info / muted), and use it in `ManageRoundsTab`,
  `View`, `Display`, `MyMatchCard` and `TournamentCard`.
- Add status tone tokens to the Badge variants (or a `StatusBadge.vue`) instead of per-site `bg-green-100 dark:bg-green-900`.
- Replace the spinner in the pending-scores line with a progress count (for example "4 of 6 scored" with a thin bar).

### Step 2: round card header and actions (A: 1, 4, 6, 7, 14)
- Split the header into two lines: **title row** (name, status badge, clock, scenario summary) and **action row**.
- One primary action per state, right-aligned: Auto Pair (setup, unpaired) -> Start Round (setup, paired) -> End Round
  (in progress). Scenario becomes a ghost button on the left of that row.
- Move Delete round and Reset Round into a single "Round options" `DropdownMenu` (`:modal="false"`, per the project
  note) with destructive styling and confirm dialogs. Reset stays discoverable but is no longer next to End Round.
- Give the game row the same pattern: Enter/Edit score as the one visible button; Remove game and Forfeit in the
  overflow menu for both setup and in-progress rounds.
- Replace the remaining bare icon `<button>`s with `Button size="icon"` and remove forced `h-7`/`h-8` where it blocks
  the coarse-pointer growth (use the Button sizes instead).

### Step 3: Manage page header (A: 2)
- Replace the "Back" button with the existing breadcrumb (derived in `lib/breadcrumbs.ts`; add a builder if Manage
  has none).
- Group the header actions: primary status transition on the right as the only filled button; Share, Display, QR in
  a "Share and display" `DropdownMenu` or a compact icon group with tooltips; Announce stays a labelled button;
  "Back to Draft" moves into a "More" menu.
- Fold the banner metadata into a consistent separated line (reuse the View page's version).

### Step 4: View page structure (B: 10, 11; A: 3)
- Group the stack: **Event** (description, details, tables), **Actions toolbar** (Scoring desk, Add to calendar,
  Share, one row), **Format and rules** (collapsed). Results, awards and announcement stay above the fold.
- Merge the two single-button rows into one toolbar.
- Use `useTournamentStatus` for the badge label.

### Step 5: scenario and pairing readability (B: 12; C: 15)
- Render the scenario summary as labelled chips (Deployment · Strategy · Schemes) at `text-xs`, not `text-2xs`.
- Add a visible affordance to the click-to-swap targets: dotted underline or a small swap icon on hover, `cursor-pointer`,
  and a one-time dismissible hint instead of a permanent footer line.

### Step 6: Players tab add form (A: 5)
- Collapse "Meta" behind an "Add details" disclosure or move add-meta and clear into the combobox itself, so the
  default form is name + faction + Add on one aligned row.

### Step 7: verify
- Re-run the harness and compare axe results, horizontal overflow and tiny-text counts with step 0.
- Existing tournament Pest tests must stay green; add a feature test only if a payload changes (none planned).
- `npm run lint`, `npm run format`, and compile the touched templates (no `vue-tsc` here; see the tournament doc's
  Manage page structure note).

## Out of scope (call out if wanted)
- The Create wizard (`Create.vue`, 546 lines) and Import page: reviewed only at a glance, no findings yet.
- `Display.vue` (the big-screen mode) and the Score desk: they share the status helpers from step 1 but no layout work.
- Any new features; this plan changes presentation and grouping only.

## Open questions
1. Is a "Round options" menu acceptable for **Delete round**, or should it keep a visible button because organizers
   use it a lot while setting up? (Plan assumes the menu.)
2. Should Reset Round also be available on a **completed** round? (Not today: results already feed the next round.)
3. Which devices do organizers actually use at events: phone, tablet or laptop? That sets how hard step 2 leans on
   the phone layout.
