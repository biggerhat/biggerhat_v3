# Game Tracker pre-game setup: UX review

Status: **findings 1-8 implemented 2026-11-13 except where noted** (browser flow `flows29.cjs`, 12 steps, phone width; Pest for the skip endpoint and the join redirect).
Finding 8 is finished too: the confirmed faction is remembered per device and pre-selected next time (one tap to confirm, Clear to change), the scheme setup form scrolls into view and its selects are phone-sized (the two-tap Select + Confirm stays on purpose: a scheme is hidden information), and the lobby says roles are assigned at random. The create page now remembers
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

## Play screen review (first pass, 2026-11-13)

Driven with a seeded solo game in progress (`play_game` in the harness seed: 6 and 5 models) at 390x844. Nothing here is implemented except the first item.

**Done:** a player who skipped their crew now opens on the Game tab (VP, scheme, end of turn) instead of an empty My Crew tab (`gameplayTab` default in `Games/Show.vue`).

**The Game tab is in good shape**: turn, both VP totals, deployment, your hidden scheme with notes, the end-of-turn VP steppers and the scheme choice all fit on one scroll.

**The crew tab is where it feels slower than paper.** Each model is ~105px tall, so a six-model crew already fills the screen and an eight-model one scrolls:

1. **Row height.** Name, health pips, three icon buttons and the -/+ stepper stack in three lines. Putting the stepper on the name line and dropping the pips (the number
   `6/6` already says it) or the number (tappable pips already set health) would roughly halve the row.
2. **Duplicate health controls.** Pips (tap to set), a -/+ stepper and a numeric label all edit or show the same value.
3. **Tap targets.** The health pips are 14px squares and the activation circle is ~28px, both used constantly mid-game. The pips can keep their look and gain a bigger hit area.
4. **Three unlabelled icon buttons on every row** (upgrades, tokens, replace) repeat 6-10 times. One "more" button per row (or showing the token button only
   when the model has tokens) would remove most of the noise; the labels live only in `title`/`sr-only`, so a first-time player has to guess.
5. **Activated models only dim** (`opacity: 0.7`), which is also the repo's "no opacity on text" rule. Moving activated models to the bottom of the list, or collapsing them
   to one line, would make "who is left to activate" readable at a glance (the header already counts `6/6 remaining`).
6. **Three copies of this markup** (my crew mobile/desktop, opponent column) in the 6k-line page, so each change above is made three times. Extracting a `GameCrewMemberRow`
   component first (props: member, side, observer; emits for the dialogs) is the safe way to do 1-5 once.

**Update:** the row component is extracted (`GameCrewMemberRow.vue`, Show.vue 6,3k -> 5,7k lines; only two copies existed, not three; screenshots before/after are identical, `flows30.cjs` passes on both builds). Then items 1 (compact row: stepper on the name line, stats merged into the pips row, ~80px instead of ~105px), 3 (bigger hit areas on pips and the activation circle) and 5 (activated models sort last, `activatedLast` in `Show.vue`) were done; 4 followed: on phones Tokens is a labelled button and Upgrades / Attach injury / Replace model share a "more" menu. Item 2 stays as is on purpose (pips set health fastest, the stepper is the safe path, the number is the label between the buttons).

Suggested order: extract the row component (no visual change, covered by a browser flow that damages a model and activates one), then the compact layout and hit areas,
then the overflow menu and activated-last ordering.

## Second audit: robustness and hidden information (2026-11-14)

Scope: the server side of the first steps (create, join, faction, master, crew, scheme), what each viewer's page data reveals, and an
axe pass over every setup screen. The first review (above) was about phone usability; this one is about correctness.

**Fixed**

- **Joining was not safe against a double tap.** `GameController::join` checked "is there an open seat?" outside any lock, then reshuffled
  the first player's role, then inserted the second player. Two taps on the invite link (or two people opening it) both passed the check;
  the loser then failed on the unique `(game_id, slot)` index with a 500 _after_ having rewritten the first player's role, which could
  leave both players as Defender. It now locks the game, re-checks status, fullness and membership inside the lock, and a loser is
  sent to the game (if already in it) or back to the list with the usual message.
- **Scheme picks leaked.** During Scheme Select the other player, and any spectator, received the opponent's `current_scheme_id`, their
  private `scheme_notes` (target model, marker) and the follow-up pool derived from the pick, all in the page data. Only faction and
  master were masked. All three are now withheld for the other seat and for observers; a `scheme_ready` flag drives the "opponent has
  locked in" indicator instead. Solo games still see both sides (the one player picks both).
- **Bonanza could start with nothing to track.** `submitMaster` accepted any name and Bonanza only creates its model when the name
  matches a catalog model, so a bad name moved the game to In Progress with an empty crew. It now answers 422.
- **Game creation is atomic and throttled.** The game and its seats are made in one transaction, and creation is limited to 30 an hour
  per player.
- **Harness:** the browser seed built schemes with a random season, so whether the default season had any depended on luck and flow 29
  failed on an empty scheme pool. The seed now always creates a strategy and schemes for the default season.

**Accessibility:** flow 33 runs axe-core over the create form, lobby, faction, master, crew, scheme, the first play screen and the solo
start, dark and light, at 390px. Moderate-or-worse findings fail it. (Run `npm i --no-save axe-core` first; it skips itself without it.)
The light pass uses the real `appearance` cookie and a reload, because toggling the class on a live page produced false contrast
failures.

**Fixed in the follow-up (same day)**

- **Hidden schemes during play.** In a live two-player game the other seat's current scheme, notes, follow-up pool, unrevealed turn rows
  (held, or not yet scored or discarded) and every `next_scheme_id` are withheld from a participant's page data, and
  `current_schemes` no longer returns both sides' schemes. Scores stay public and a scored or discarded scheme is revealed. The
  opponent-intel panel is built first and handed over ready-made, because it reads the same rows. After the game, everything is shown.
- **`join` no longer changes state on a GET.** The invite link (`games.join`) now shows a "Join this game?" page with who is hosting;
  the button posts to `games.join.confirm` (throttled, same lock as before). Logged-out people still log in and come back to it.
- **Crew submission checks the faction.** A crew must match the faction the player picked (a master may also be hired by its second
  faction); Campaign games are exempt. Crew size is still not compared with the encounter, since hiring under the limit is legal.
- **`swapRoles`** has a FormRequest (creator of a solo game only) and no longer works once the game has started.
- **Stale lobbies.** `games:prune-stale-lobbies` (daily) soft-deletes two-player games nobody joined within 7 days, leaving tournament-
  tracked games alone.

**Still open (needs a decision)**

- **Observers see available schemes only (done).** A public observer of a live game gets each side's available schemes and the ones
  already scored or discarded, never what either player has selected or is holding: the current pick, notes, follow-up pool,
  unrevealed turn rows and next-turn picks are withheld for both seats, solo games included. A finished game shows everything. One
  gap remains: an opponent can still open the observe page of an observable game, but now sees no more than any other observer.
- **A bare Campaign-format game can still be created by POST.** The Create page hides the format, but `StoreGameRequest`
  accepts it, and a test plus a `copyCrewToGame` branch deliberately support a solo Campaign game with no campaign link, so I left it. If
  that path is no longer wanted, reject it in the request and delete the branch.
- **No in-browser check of the intel panel** with a scored scheme (the server tests cover its data).
