# Game Tracker pre-game setup: UX review

Status: **findings 1-8 implemented 2026-11-13 except where noted** (browser flow `flows29.cjs`, 12 steps, phone width; Pest for the skip endpoint and the join redirect).
Not done from finding 8: "last played" faction hint, the scheme step's two-tap flow and small selects, and explaining roles in the lobby. The create page now remembers
format / size / season / solo per device (localStorage). Original review notes follow (2026-11-13). Method: drove the real flow in the UI-audit harness at 390x844 as two players
(create -> invite -> join -> faction -> master -> crew), full-page screenshots at each step, plus a read of `Games/Create.vue`,
`GameSetupLobby.vue` and the Faction / Master / Crew / Scheme panels. The scheme step was read but not driven (the seed has no crew
that matches a master, so the walk stopped at crew select). The websocket is off in the harness, so live updates between the two
players were not observed (they were refreshed by reloading).

Context: the player feedback that "the tracker feels clunkier than pen and paper" is mostly about _getting to the first turn_, so this
review follows the path from "New game" to "turn 1".

## Findings (ranked by effect on a player at a table)

### 1. The step you must act on is below the fold on a phone (P1)

Every setup stage renders the scenario block (deployment, strategy, scheme pool, each with a "View" button) and the players card _above_
the stepper and the active panel. At 390x844 the "Select your faction" card starts ~715px down, and "Confirm Faction" at ~1030px, so each
step needs a scroll before the first tap. The scenario is useful, but it is reference, not the task.
_Fix:_ on phones put the stepper + active panel first and fold the scenario/players into a compact summary row that expands (it is
already a `Re-roll` / `Edit` surface for the creator, so keep those reachable in the expanded state).

### 2. A player cannot skip their own crew (P1, needs a product call)

`GameSetupController::skipCrew` only exists for the _solo opponent_. Everyone else must build or pick a saved crew in the Crew Builder
before the tracker lets them past crew select; the crew step for a user with no saved crews shows "0 saved crews / No saved crews for
this faction yet" and a single path (Create New Crew, which leaves the game, now returning via `from_game`). That is the pen-and-paper
player's wall: they know their list and want VP/scheme/turn tracking only. The opponent already has "Skip, track points only" and the
scheme panel already renders "Crew not tracked", so the downstream UI copes with it.
_Fix options:_ (a) a "Track points only (skip crew)" action for the player's own slot, mirroring the opponent's; (b) a quick-add crew
(pick master + paste/type models) without the full builder. (a) is small; (b) is a feature.

### 3. Faction step uses an inline confirm; the Master step uses a floating confirm bar (P2)

Faction: tap a tile, then a "Confirm Faction" button appears below the grid (offscreen on small phones). Master: tap a card, then a
sticky bar ("Selected: X, Clear, Confirm") above the tab bar. Same job, two patterns. The faction choice has no consequence worth a
confirm step; either reuse the master step's bar, or advance on tap with an undo until the opponent confirms.

### 4. Invite: Copy + QR only, no native share, no failure handling (P2)

`GameSetupLobby.vue` offers Copy and a QR code. On a phone the usual job is "send this to my opponent" (messages / Discord), which is the
OS share sheet (`navigator.share`). `copyJoinLink` has no `try/catch`, so a blocked clipboard fails silently. The QR button is icon-only
(it has an aria-label, but no visible label next to "Copy").
_Fix:_ add a Share button when `navigator.share` exists, toast on copy failure (`useToast`).

### 5. No live-connection fallback in setup (P2)

`useGameChannel.ts` has no connection state or polling fallback (the tournament pages got both, audit item C4). On venue wifi a dropped
socket leaves "Waiting for opponent..." forever, with nothing to tell the player to refresh. A reconnecting indicator plus a slow
`router.reload({ only })` poll while disconnected would match the tournament behaviour.

### 6. Opening a game URL you are not in gives a bare 403 (P2)

`GET /games/{uuid}` for a non-participant returns the stock "403 This action is unauthorized." page. The invite link is `/join`, but people
paste the address bar URL (for example while the host is on the lobby page). For a game that is still in setup, redirecting a signed-in
non-participant to `games.join` (and a guest to login with return) would turn a dead end into a join.

### 7. Create page: the primary button is far from the top and under the tab bar (P3)

Six controls before "Create Game", which sits below the fold behind the phone tab bar. Reasonable defaults already exist, so the
common case is one tap. Make the action sticky on phones (`bottom-[var(--tabbar-h)]`, as the master bar does), and consider remembering
the last format / size / season / solo choice per user.
Code-rule note: "Back to Games" is a `Button` calling `router.get`; CLAUDE.md says navigation is `<Link>`.

### 8. Smaller things

- Crew step shows "Create New Crew" as a button _and_ a "create a new one" link in the sentence above it.
- Master cards are clickable `div`s (`<Card @click>`): no focus, no keyboard activation, no role. Use a real button or `role="button"`
  with `tabindex` and Enter/Space.
- A master card shows only a name (and a thumbnail when there is one): no faction colour, keywords or stat line, so you cannot tell
  two similarly named masters apart without leaving the page (the "View" for scenario items exists; masters have none).
- The faction grid has no "last played" hint or default; returning players pick from eight tiles every game.
- Scheme step: two taps per scheme (Select, then Confirm Scheme in a separate panel); the `h-8 text-xs` selects are small for a phone.
- Roles (Attacker / Defender) are assigned at join and shown as a small badge; there is a swap route (`games.setup.swap_roles`) but
  nothing in the lobby says roles matter or that they can be swapped.

## Suggested order

1. Finding 2 (a): skip-own-crew, because it unblocks a whole group of players. Needs a decision first: should a skipped own crew still
   count for stats / history? (the opponent's does not).
2. Finding 1: stepper-first layout on phones. Biggest daily win, presentation only.
3. Findings 3, 4, 7: consistent confirm pattern, native share, sticky create button. All small.
4. Findings 5 and 6: connection fallback and the join redirect (the second needs a policy test).
5. Finding 8 as a sweep.

## Not covered

- The in-game screen (`Games/Show.vue` play phase): out of scope here, and the biggest part of the "clunkier than pen and paper" feedback.
- The scheme step beyond reading it, Campaign / Bonanza / TOS setup variants, and the solo flow (solo skips the invite and picks both
  sides, which has its own two-step opponent panels).
- Axe results for the setup screens.
