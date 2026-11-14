# Virtual tabletop for Malifaux 4th Edition: how hard, and where

Prompted by [wartable.online](https://wartable.online/) (2026-11-13). Builds on the shelved plan in the project memory
(`project_vtt_plan.md`: Konva, layers, phases). Facts about War Table come from its public page, manifest and JavaScript
bundle only; I did not sign in or play a game. Effort figures are my estimates, not measurements.

## What War Table is

- **A generic miniature-wargame tabletop, not a Malifaux tool.** The manifest calls it "online wargaming tabletop". Each game is a
  module: Warmachine, Warmachine/Hordes (legacy), Guild Ball, Judgement, Malifaux (labelled "3rd Edition (Test)"), Breachstorm,
  Bushido, Conquest: First Blood, Eldfall Chronicles, Relic Knights, and Other.
- **Stack:** Vite + React + Material UI single-page app on static hosting, with Firebase (Realtime Database, sign-in, reCAPTCHA) as the
  whole backend. Google Analytics, a Patreon link, several Discord community links. No server of its own to run.
- **What a game has:** a board with a background image (Malifaux module: 36" x 36"), models as tokens with an avatar, base shape and size,
  and up to three labelled counters (Malifaux uses one, "Health"), optional arcs, scenario/deployment elements drawn on the board, a
  two-player chess clock (60 minutes each), invite links (`/invite/<game>/<code>`) and read-only spectate links, copy-a-game, and
  "continue game".
- **The Malifaux module is a hand-typed model list.** A 150 KB chunk of names, health and avatars. That list is War Table's real
  maintenance burden, and it is stuck on 3rd Edition.
- **It disclaims affiliation:** "War Table is not affiliated with Wyrd Games."

## What this site already has

| Needed for a tabletop                             | Here today                                                                                                                                  |
| ------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------- |
| Live two-player sync, join links, spectators      | Reverb + Echo, `games.join`, `games.observe`, `is_observable`                                                                               |
| Who is in the game, whose turn, what they brought | `Game`, `GamePlayer`, `GameCrewMember` (health, tokens, upgrades, activation), `GameTurn`, `GameLog`                                        |
| Real model data per token                         | `Character.base` (mm), `Miniature` images, card art, M4E crew builder                                                                       |
| Scenario                                          | Strategy, Scheme, `DeploymentEnum` (4 deployments, **text and an image only, no vector geometry**), `Terrain` and `Marker` reference models |
| Phone-first PWA, accounts, saved history          | Yes                                                                                                                                         |
| Canvas or pan/zoom library                        | **No** (`package.json` has none)                                                                                                            |
| Token positions, board state, terrain footprints  | **No** (no `board_state`, no shapes for terrain)                                                                                            |

The big difference: War Table makes people add every model and type its health by hand. Here "Open board" can place the crews
players already built and tracked, and health and conditions stay one source of truth. That removes most of War Table's effort.

## Effort

**On this site (recommended):**

| Slice                                                                                                            | Estimate (one developer who knows the codebase)              |
| ---------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------ |
| Spike: pan/zoom board, draggable base-accurate tokens from the game's crews, ruler in inches, persisted per game | 1 to 2 weeks                                                 |
| Realtime: positions broadcast on drag end, ownership rules, spectators, reconnect                                | 1 to 2 weeks                                                 |
| Deployment zones as vector overlays for the 4 deployments, scenario markers                                      | about 1 week (needs the M4E rulebook for sizes and geometry) |
| **Playable MVP total**                                                                                           | **about 3 to 5 weeks**                                       |
| Terrain pieces (shape, height, traits), pulse/aura rings, token art, undo, line-of-sight helper, touch polish    | 4 to 8 more weeks                                            |

**As its own project:** everything above, plus accounts, hosting, a catalogue of M4E models (or an import from the data this site
already holds), crews, saved games, moderation and a way to keep the data current. Realistically several months, and it would
rebuild what this site already is. The only good reasons to split it off are branding, audience, or keeping the legal exposure
separate from this site.

## Where the difficulty is (not the rendering)

1. **Phone and tablet touch handling.** Dragging a token, panning, pinch zoom and a ruler all compete for the same gestures, and
   the audience is mostly on phones. This is the hardest UX problem.
2. **Who may move what.** Pen-and-paper play lets either player nudge anything. Needs a rule (own models only, or ask first) and a
   plain way to undo a mis-drag. Conflicting simultaneous drags need a last-writer-wins decision.
3. **Measurement accuracy.** Base size, edge-to-edge measuring and engagement range must be exact or players will not trust it.
4. **Content, not code.** Deployment geometry, terrain footprints and traits per scenario, and a handful of default terrain sets. The
   data is small but has to be authored and checked against the 4th Edition rules.
5. **Keeping the board and the tracker in step** (dead models leave the board, summoned ones enter, tokens mirror health).
6. **Rendering performance is not a concern:** 20 to 30 tokens.

## Choices worth making early

- **Plain SVG with pointer events instead of Konva.** About 30 shapes, no new dependency, accessible, easy to test with the existing
  browser harness. Konva is only worth it if the board grows (heavy terrain, fog, many effects).
- **Broadcast on drag end, not while dragging** (as the earlier plan said), and keep positions in a JSON column on the game until the
  shape of the data settles.
- **Start read-write for the owner and read-only for the opponent**, then relax.
- **Legal and art:** War Table's disclaimer is the minimum. Card art and sculpt photos are Wyrd's; check what this site is already
  allowed to show before putting art on tokens, and keep tokens as plain faction-coloured discs with the model name if unsure.

## Risks

- **Rules and board details are unverified.** I have not read the 4th Edition rulebook for table size, deployment dimensions or
  measuring rules. Confirm them (the rulebook research agent can extract them) before drawing anything to scale.
- **War Table might add 4th Edition.** Its module is labelled "(Test)" and is on 3rd. Here the edge is the integration with crews,
  tracking and tournaments, which a generic tool cannot match.
- **Scope creep toward a full rules engine** (automated line of sight, cover, charge paths). Keep it a tabletop, not a referee.

## Suggested first step

A one-week spike behind a feature flag: board plus draggable tokens for a solo game (no sync), with the ruler. If it feels
good with a thumb on a phone, build the realtime layer. If the touch handling is poor, stop before investing in the rest.
