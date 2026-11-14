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
- Not yet: a link from the game screen, scenario terrain (the board uses
  the demo table), a layout editor, enforcement of climb and jump costs.
