# Tabletop audit 2: usability, UI/UX, visual design, ease of use, grouping (2026-11)

Method: the audit screenshots of every state (sandbox, selected model, terrain tab, Fate, a game's board, the tables page; desktop and phone, light and dark)
from `flows37.cjs`, plus measurements taken with the harness at four screen sizes (phone 390x844, laptop 1366x768, desktop 1280x900, wide 1920x1080).
Everything marked **seen** is on a screenshot or measured; **inferred** is reasoning from the code and not yet confirmed on a screen. Not covered: a real device or
a real player.

## Measured

| | Phone 390x844 | Laptop 1366x768 | Desktop 1280x900 |
|---|---|---|---|
| Sandbox: first model row at (px from top) | **1037** (1.2 screens) | **603** of 768 | 603 of 900 |
| Game board: first model row at | **1236** (1.5 screens) | 705 of 768 | 741 of 900 |
| Game board: board starts at | 361 | 311 | 347 |
| Game board: how much of the board shows on load | 100% (but see 4) | **84%** | 91% |
| Keyboard stops before the first model row | **19** (sandbox), 18 (board) | same | same |
| Side column height with 6 models | 1330 (sandbox), 1784 (board) | 1092 / 1386 | 1092 / 1386 |

The model list is what people use most, and it is the last thing in a stack of table-level controls.

## Findings, most important first

Effort: S = under an hour, M = a few hours, L = a day or more.

### Grouping and layout

1. **The models are at the bottom of a stack of unrelated blocks (seen, measured).** The side column is: a Help row, the Models/Terrain tabs, Load table / Save table, the Table
   accordion, the Fate deck accordion, then Ruler / Step / "Move as", then the models. That is three different kinds of thing (the saved table, the tools, the models)
   at the same level. On a laptop only one or two model rows are visible without scrolling; on a phone the first row is more than a screen down. *Fix:* three zones. Tools
   (Ruler, Step, Move as, zoom) attach to the board. Table-level things (Load, Save, surface, deployment, lines, badges) go into one "Table" place (a third tab or a menu),
   Fate becomes its own dock/tab, Help moves to the page header. The side column then starts with the models. **L.**
2. **Terrain editing on a phone loses the board (seen).** The terrain controls are below the board, so to see the effect of an edit you scroll back up, and the bottom of the
   Terrain tab is empty space. *Fix:* keep a smaller board pinned while editing terrain, or put the editor in a bottom sheet over the lower half. **M.**
3. **The game board's header uses up the first screen (measured).** Page title, a sentence of instructions, "Back to the game", the turn bar and the Look back list come before the
   board: it starts 311 to 361px down, so on a laptop 16% of the board is below the fold on load. *Fix:* one row for title, Back and Help; fold Look back into the turn bar; show
   the instruction sentence once. **M.**
4. **On a phone the floating Fate button covers the zoom controls and the sticky tabs (seen).** The zoom buttons also sit below the board, away from it, and on the game board they
   are partly hidden by that button and the tab bar. *Fix:* put the zoom buttons inside a corner of the board, and move the Fate button so it does not sit over content. **S.**
5. **Opening the Fate deck pushes the tools and the models down the page (seen, desktop).** *Fix:* with finding 1, give it its own dock. **M (part of 1).**
6. **A bug: in the Terrain tab the "Fate deck" button looks pressed (black) with nothing open under it (seen).** The panel is kept shut on that tab but the button still shows the open state. **S.**
7. **Two "Help" buttons on the Terrain tab (seen)**, "Help and shortcuts" and the terrain editor's own. *Fix:* one, with the terrain section as the default there. **S.**
8. **The "Add to the table" card is at the very bottom of the model list (seen).** With a full table it is more than a screen down. *Fix:* an Add button in the models heading. **S.**
9. **"Table" means three things (seen):** the saved-table concept (Load/Save table, "My tables"), the Table settings accordion (surface, zones) and the Table summary card in the Terrain tab. *Fix:* name them
   "Saved tables", "Surface and zones", "Terrain summary". **S.**
10. **Two entrances to saved tables:** the "My tables and the public gallery" link above the board and Load/Save in the side column. **S** (with 1).

### Feedback

11. **The note under the board is the main feedback and is easy to miss (seen).** Distance moved, engaged, overlaps and "moved 3.2 of 5" are small centred text below the board; on a phone it
    scrolls off. *Fix:* a short on-board status chip (top or bottom of the board) that is also the live region, with the long text kept for the note. **S-M.**
12. **The alerts on a selected model are long and loud (seen).** "Engaged with Master: a Walk is half its Sp (2"), and it cannot declare missile actions, Interact or Charge" plus an overlap warning take half a
    phone screen and repeat what the red outline on the board already says. *Fix:* chips ("Engaged", "Overlaps") that expand on tap, with "Move to the nearest legal spot" on the overlap chip. **S-M.**

### Model list and options

13. **Rows are heavy (seen).** Each is 70 to 100px high; twelve models are over a thousand pixels. *Fix:* a compact row (activation, name, health) with the rest on tap, and a density switch. **M.**
14. **Icon-only buttons that look alike (seen).** Tokens and Replace sit side by side with similar glyphs and only a hover title. *Fix:* keep Tokens, move Replace into the row menu (it already is on a phone). **S.**
15. **A button that states a state, not an action: "Sight guidelines off" (seen).** Use a switch with a label ("Sight guidelines"). **S.**
16. **The Options panel is one flat list of unrelated things (seen):** sight guidelines, mark killed, what it ignores, Fate flip, notes, auras, remove. Group them (Sight, Movement, Notes and auras) and
    keep the destructive action set apart. **M.**
17. **The Ruler / Step / "Move as" box mixes two ideas (seen):** Ruler is a tool, Step is a modifier, and "Move as" is a mode, under a tiny grey label. *Fix:* a labelled "Tools" bar, with Move as as a segmented control and Step beside it. **S (with 1).**

### Board visuals

18. **The height labels ("Ht 5", "Ht 2") are large, on every piece and level, and overlap each other and the models (seen).** *Fix:* smaller, and behind a "Show heights" switch or shown on hover/selection. **S.**
19. **The grid lines compete with the bases and the textures (seen).** *Fix:* lower their contrast (and keep the half-inch grid for zoomed-in use). **S.**
20. ~~The sample models overlap each other on first load.~~ **Withdrawn:** that was the audit harness's own demo data; a fresh sandbox starts empty.
21. **No on-board hint for the gestures (inferred).** Wheel zoom, pinch, drag to pan, tap to select are only in Help; the keyboard hint appears only on focus. *Fix:* a dismissible first-visit strip. **S-M.**

### Ease of use and keyboard

22. **19 keyboard stops before the first model (measured).** *Fix:* a "Skip to models" link first in the side column, and the reorder in 1. **S.**
23. **No first-run path (inferred).** The sandbox opens with demo terrain and (in a fresh one) no models; "Add a character" is far down and nothing says what to do next. *Fix:* a three-step "Start here" strip: add a model, drag it, measure it. **M.**

### Consistent with the rest of the site (seen, no action)

Dark mode and light mode both pass the accessibility audit; faction colours and side rings read well; the help and keyboard coverage is good; targets are at least 44px except 8 to 9 secondary controls on a phone.

## Suggested order

1. **Quick wins (batch 1, about a day):** 4, 6, 7, 8, 9, 10, 15, 18, 19, 20, 22.
2. **Restructure the side column (batch 2, the big one):** 1, 5, 17, 3 (with the zones from 1, so the models come first and the tools sit with the board).
3. **Phone terrain (batch 3):** 2.
4. **Rows and options (batch 4):** 11, 12, 13, 14, 16.
5. **First run (batch 5):** 21, 23.

Each batch needs the help topics and the pictures remade (a layout change moves the buttons the pictures point at), so budget for `flows38.cjs` after batches 2 and 4.

## Progress

**Batch 1 (done, 2026-11):** 4 (zoom no longer covered by the Fate button), 6 (the Fate button no longer looks pressed on the Terrain tab), 7 (one Help),
8 (Add above the models), 9 (clearer names: "Surface and zones", "Terrain summary"), 15 (Sight guidelines is a labelled toggle), 18 (smaller height labels with a
"Show terrain heights" switch), 19 (softer grid), 22 (a "Skip to the models" link). 10 was left alone (the "My tables" link is page navigation).

**Batch 2 (done, 2026-11):** 1, 3, 5, 17.
- The side column now starts with three tabs, **Models, Terrain, Table**, and a Help icon beside them. The Table tab holds Saved tables (Load, Save) and Surface and zones.
  A watcher gets Models and Table.
- The tools (Ruler, Step, the five move kinds) and the zoom buttons are one toolbar **above** the board. Measured: with it the board still fits a 900px screen on the sandbox page.
- The readouts and help text for the tools (ruler distance, step help, sight lines) stay in the side column. **Lesson:** they must not go under the board: panels that appear when you start
  a drag make the pinned board column taller, and the board moves under your pointer. Anything that appears or changes height during a drag belongs outside the board's column.
- On the game board the header is one row (title, Back, one line of text) and the turn bar and Look back share a row; the board starts about 65px higher.
- First model row, measured before and after: 741 to about 540px on a 900px game board.

**Batch 3 (done, 2026-11):** 2. On a phone the terrain editor is a bottom sheet (`terrain-sheet`, Done button) with the board scrolled into view above it; the floating Fate button is hidden while it is up.

**Batch 4 (part, 2026-11):** 12 as smaller, tighter alerts (the wording is unchanged). **Not done:** 11 (compact rows; the row is shared with the play screen), 13/16 (group the Options panel), 14.

**Batch 5 (part, 2026-11):** 23. An empty sandbox says "Start here" with three steps and an Add a character button. **Not done:** 21 (a dismissible first-visit strip for the gestures).

Measured after batches 1 to 3 (desktop 1280x900 sandbox): the toolbar is one row above the board, the board still fits the screen, and the first model row is at about y=513 (was 603).
Phone controls under 44px: sandbox 15 and board 14 in the audit's list, most of them the 40px buttons the side column used to give (the list now includes small secondary controls and the skip link).
