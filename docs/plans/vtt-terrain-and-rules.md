# Virtual tabletop: confirmed rules and the terrain-levels model

Source: Malifaux 4th Edition Comprehensive Rules Guide, 9.2.2026 (`/var/www/M4E_Comprehensive-Rules-Guide_9.2026.pdf`; page numbers
are the printed ones). Supersedes the "unverified rules" risk in [vtt-feasibility.md](vtt-feasibility.md).

## Confirmed facts

| Topic           | Rule                                                                                                                                                                                                                                                                                                                        | Page   |
| --------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------ |
| Table           | 3 ft x 3 ft (36" x 36"). 4 quarters of 18" x 18". Centerpoint at 18", 18". Models cannot move past a table edge.                                                                                                                                                                                                            | 12, 60 |
| Measuring       | Inches, horizontal only, from the closest point on a base to the closest point on the other base. Vertical distance is ignored. Base contact = bases touching or overlapping.                                                                                                                                               | 12     |
| Base sizes      | 30, 40 and 50 mm (per the game; the guide lists "base size" per model, and uses 30 mm in its examples). Our `Character.base` holds it. 1 in = 25.4 mm.                                                                                                                                                                      | 23     |
| Terrain share   | Roughly 45-65% of the table covered. Three to eight pieces of at least Ht 2, climbable, standable.                                                                                                                                                                                                                          | 60     |
| Elevation       | A model's elevation is the sum of the heights of the climbable terrain that supports its base. The table is height 0. Terrain under the model that does not support it is ignored (a Ht 1 box inside a house under a roof does not count). Effective Sz = Sz + elevation.                                                   | 19     |
| Cover by height | Higher by 2 or more gets cover. A model cannot target another with ranged (y) actions if its effective Sz is below the target's elevation.                                                                                                                                                                                  | 19-20  |
| Climbing        | Costs movement for vertical distance. No fall while in base contact. Ending a climb with under 50% of the base supported means a fall.                                                                                                                                                                                      | 20     |
| Scaling         | After a Walk, in base contact with climbable terrain edge: move straight up, then horizontally until 100% supported (at least 50% minimum). Free beyond Sp. Scaling down is the reverse.                                                                                                                                    | 20, 22 |
| Falling         | Under 50% base support means an immediate fall straight down. A fall of 2+ elevations deals 1 irreducible damage. If it ends overlapping a model or impassable terrain, move to the nearest legal spot.                                                                                                                     | 20     |
| Jumping         | Pick a point at the same elevation or up to 1" below, within remaining movement, at least 50% over supporting terrain. Blocked by an intervening piece whose Sz + elevation is above the jumper's. Cannot end higher than it began.                                                                                         | 23     |
| Line of sight   | Sight lines between base edges, drawn top-down. On different levels of one terrain piece, no sight line through its floors or ceiling. Blocking and dense terrain block by height comparison (see p.24).                                                                                                                    | 24     |
| Terrain traits  | blocking, climbable, impassable, concealing (-), dense, hazardous, destructible. Cover from blocking within 2".                                                                                                                                                                                                             | 55-58  |
| Standard pieces | Flat building: Ht X (rounded up), blocking, climbable. Steep building: impassable. Wall: climbable, blocking. Fence: climbable, destructible. Crates: blocking, climbable, destructible. Hill: Ht varies with position. Bridge = Walkway (height X) over an Archway (free passage below Ht X). Fog bank: concealing, dense. | 57-58  |
| Deployment      | Standard: within 8" of a table edge. Flank: within 9" of the edges of a table quarter. Corner: within 12" of a corner. Wedge: starts 12" from the middle of an edge and sweeps back to the corners. Opponent takes the mirror.                                                                                              | 61     |

## What this means for the model

**Levels are stacked climbable surfaces, not a single height per piece.** A model's elevation is the sum of what truly supports it, so
the data model is a tree of surfaces, not a flat list of pieces:

- `TerrainPiece`: shape (rectangle, circle or polygon, in inches), `traits` (set), and one or more `surfaces`.
- `Surface`: the footprint it covers, `base_elevation` (inches above the table) and `height` (the piece's Ht), `climbable`,
  `standable`. A multi-storey building has one surface per floor with its own footprint. A bridge has a walkway surface above an
  archway volume with a free-height value.
- `Token`: `x`, `y` (inches from a table corner), `surface_id` (null means the table) and the model's `base_mm`. Elevation is
  derived from `surface_id`, never stored separately, so the two cannot drift apart.
- Support rule: a token rests on the highest surface where at least 50% of its base overlaps. Under 50% on every surface it falls
  to the surface below, with the fall damage prompt when the drop is 2 or more.
- The ruler measures horizontal edge-to-edge (the rule) and shows the elevation difference separately, because the rules ignore
  vertical distance for range but need it for climbing and falling.

Footprint maths is circle against polygon overlap area. The 50% test can be sampled (a grid of points across the base) with no
geometry library.

## Authoring the content

Pieces and deployment zones are data. Deployment zones are four fixed vector shapes (rectangles, a corner quadrant, a wedge). A few
default terrain sets (a city table, a hills table) cover the common case, and a layout editor lets the player place and edit pieces.

## Decisions

- **Token art:** plain faction-coloured discs with the model name until we confirm what art we may show.
- **Terrain shapes:** rectangle, circle, polygon, with height, base elevation and traits. Nothing free-form beyond polygons.
- **Base sizes:** drawn to scale from `Character.base` (30, 40, 50 mm).

## Status (2026-11)

- Sandbox at `/tools/tabletop` and a game board at `/games/{uuid}/board`, both behind the `experiment-tabletop` flag.
- The game board loads the game's crews with their real base sizes (custom cards too, 30 mm if unknown) and saves positions to
  `games.board_state` (JSON). You move your own models; in a solo game you move both sides. Saves merge under a lock.
- Realtime: a drop broadcasts `GameBoardMoved` on the game's presence channel; the other board applies it at once, and re-syncs when the tab becomes visible again.
- Auras: select a model, "Add aura", set the range (0.5" steps, presets 1, 2, 3, 6). A model casts up to three. The lock pins an aura where it is while the model walks away; unlocking returns it to the model. The circle starts at the outside edge of the base (base radius + range) and the panel lists the models within it. Auras are a view aid stored on the device.
- Deployments: Standard, Flank, Corner and Wedge are drawn as the guide shows them (p.61), in four orientations each, with the
  centerline. The game board starts on the game's own deployment.
- Terrain editor ("Terrain" tool): pick a piece type from the guide's standard pieces or a plain block or round piece, drag it
  into place (snaps to half inches), turn it 90 degrees, resize it, copy or delete it, set its traits, and give it up to six
  levels, each with its own footprint and height. Undo, clear, or load the demo table. It shows coverage against the guide's
  45-65% and the count of climbable Ht 2+ pieces (three to eight). In a game the layout is saved to `games.board_state` and
  both players can edit it; changes reach the other player live. The sandbox keeps its layout on the device.
- Markers: "Add marker" opens a dialog that searches every row of the markers table (every word must match, any order) and lists
  each marker's base size. A marker is drawn to scale as a dashed token in its owner's colour, sits under the models, can be given
  auras and, by its owner only, dragged and picked up. The player who places a marker owns it; each player can have five of each kind on the
  table (in a solo game you place for either side). Saved with the game as {id, marker_id, owner (slot), x, y}: the name and base
  size always come from the table. Edits are merged under a lock and the other player sees them live.
- Shared auras: an aura is private to the browser (localStorage) until its owner presses "Show to the other player". Shared auras are saved
  in `board_state.auras` ({id, owner_id (crew id or marker id), range, locked, x, y}), drawn with an outer ring, live for both players, and
  only the model's or marker's owner can change or withdraw them (up to 3 per model). A marker's shared auras go when it is picked up.
- Movement readout: dragging a model draws a dashed line from where it started (with a ghost base) labelled with the straight-line
  distance; on drop the note adds climbing (costs movement, p.20) and any drop (1 damage from 2" up, p.20). Straight line only: it
  does not know the model's Wk or enforce anything.
- Deployment orientation ("Which side") is saved in `board_state.deployment_variant` (0-3), either player can set it, and the other
  sees the change live. The kind still comes from the game; the overlay on/off stays a personal view.
- Access: everyone while the `experiment-tabletop` flag is on; while it is off, only users holding `use_tabletop_beta` (and super_admin, whose
  role holds every permission). One check, `TabletopAccess::allows()`, covers the page, the board, every save and the Open board link.
  To add a tester: Admin > Roles, make a role with that permission (group Tabletop), give the role to the user. Both players need access
  to see the board: a tester's opponent without it gets a 404 and no link.
- Line of sight (Sight mode: press on a model, drag to another; `lineOfSight()` in geometry.ts, p.24-28, 56): 16 points on each base edge,
  never across a base. A line is blocked by blocking terrain or an enemy model as tall as both models; shorter than both never blocks;
  taller than only one blocks when the shorter model's base is within 2" of it. Heights are effective (Sz + elevation; terrain height =
  its top above the table). Dense terrain blocks a line that crosses it completely. Terrain either model stands on (and the part of a
  piece below them) never blocks; friendly models and markers never block; models in base contact always see each other. Cover = from
  height (2+ higher) or blocking terrain within 2" of the target that some clear line crosses; concealment = every clear line crosses
  concealing terrain the initiator is not in. Both directions are shown, because each model draws its own lines. Sz comes from the card
  (`Character.size`; custom cards may lack it and count as 1). Only climbable pieces hold a model up (p.19): a steep building or woods
  is never stood on.
  Reading choices to confirm against play: "any clear line crossing blocking terrain" gives cover (the guide's wording is "at least one
  of the initiator's sight lines"), the concealment "more than 1 inch" and in-terrain exception are not modelled, building interiors and
  windows are not modelled, and the p.19 limit on targeting with y actions across elevations is not applied.
- Ready-made tables (terrain mode > Ready-made table, `terrainLayouts.ts`): City streets, Hills and ruins, Crossroads. Each is laid out
  for half the table and turned 180 degrees for the other half so neither side has better ground; a test holds every one to the guide's
  advice (45-65% covered, 3-8 standable Ht 2+ pieces, nothing overlapping, all on the table, symmetric). Loading one replaces the
  table and can be undone. Not tied to a scenario yet: the game's scenario does not choose a layout.
- Placement check (`placementProblems()`): a model whose base overlaps another model's base, or stands on impassable terrain, gets a red
  ring, a warning in its panel and a note on drop ("move it to the nearest legal spot", p.20). Bases may touch. Markers and models out
  of play are ignored. Hazardous terrain is flagged but not treated as a problem. Nothing is blocked or moved automatically.
- Draw an outline (terrain mode): tap corners on the board (snapped to half inches, 3 to 16), tap the first corner again or press
  Finish to close it into a polygon piece (blocking, climbable, Ht 2 to start; edit traits and Ht like any piece, drag or turn it). Its size
  has no number fields, so reshape by drawing again. Undo removes it.
- Walk reach: a model with a Sp (from the card; custom cards have one) shows a green ring of Sp inches around where it started while
  dragged, the label reads "4.2\" of 5\"" (climbing counts, p.20) and goes red past a Walk, and the drop note says how much is left or how
  far over. Sp is as printed: effects that change it are not known, and a model may take more than one action to move.
- Watching: when a game is observable (the same switch as the observe page), anyone can open a read-only board at
  `/games/{uuid}/board/watch`, linked from the observe page as "Watch the board" (behind the same flag or beta permission, signed in or
  not). Every model, marker and the terrain is locked and the editor is hidden. Watchers are not on the players' live channel, so the page
  refreshes itself every 8 seconds while the tab is showing. Their auras and deployment overlay are private to them.
- Sandbox characters: "Add a character" opens a dialog that searches the standard character cards by name (every word must match;
  `GET /tools/tabletop/characters?q=`, throttled, same flag or beta permission as the page, hidden cards left out). Each result shows
  faction, base, Sz and Sp; adding it puts the model on the table at its real base size with its Sz and Sp, for the chosen side, so
  line of sight and the Walk ring work for it. There are no blank or demo models: the sandbox table starts empty and only characters and
  markers from the database can be put on it (saved in `tabletop-spike-v2`; the earlier blank models are dropped). Game boards take
  their crews from the game instead.
- Model panel (select a model): the tracker's essentials for that model, `ModelInfoPanel.vue` — activated, health (stepper and pips),
  tokens (add from the tokens table, remove) and a note. On a game board it reads and writes the game tracker's own crew member
  (`GameCrewMember` health, `is_activated`, `attached_tokens`, `notes`) through the tracker's `games.play.crew.update` endpoint, so the play
  screen and the board always agree; the board refreshes when the play screen changes a model (`GameCrewMemberUpdated`), and only the
  player who owns the model can change it (the endpoint's own policy; the game must be in progress). Models are drawn with a health bar
  and a count of tokens on them. In the sandbox the same panel is kept on the device (a card added from the search brings its health;
  a blank model can have a maximum health set). Not here: upgrades, injuries, replace, summon, kill/revive — use the play screen.
- Step move (the Step button in Move mode, `pathCost()`): with it on, grabbing the same model again carries on from where it was
  dropped, so the legs add up (2\" one way then 5\" another is 7\", each leg's climbing counted, p.20). The trail draws the whole path with
  a length on each leg and the running total against the Walk (Sp); tapping empty ground, grabbing another model or pressing Start over
  begins again. With Step off every drag is measured on its own, as before. Legs are straight lines and nothing is enforced.
- Side sight lines (Sight mode, `sideSightLines()` and `traceLine()` in `geometry.ts`): press a model and drag, and one line leaves each side of its base (the two points square to the aim) and runs straight off the base, parallel, in the direction of the finger (or of the centre of the model or marker it is over), as long as the aim is far from the model's centre. Each model a line touches gets the full sight test for the stretch up to it (blocking and dense terrain, enemies in the way, heights against that model), shown as a green ring (in sight) or a red dashed ring (hidden, with what hides it). The line is drawn green up to where terrain or an enemy at least as tall as the viewer first stops it, red dashed beyond. Dropping on a model still gives the model-to-model check with cover and concealment; the side lines report sight only. A view aid, not saved or shared. `lineOfSight()` and the side lines share `prepareSight()`. The guide's own examples (pp.25-26: Ht 3 wall, the Rooster Rider and Fire Golem line, the Ht 1 wall, the fog bank) are tests in `geometry.test.mjs`.
- Not yet: tying a table to the game's scenario, enforcement of climb and jump costs (reported against a Walk, never stopped), an interior model for buildings, numeric editing of a polygon's corners.
