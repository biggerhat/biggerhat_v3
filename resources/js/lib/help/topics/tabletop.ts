/**
 * Help for the Tabletop (the sandbox at /tools/tabletop and a game's board). About how it works, in the words on its screens.
 * Terrain trait wording follows the Comprehensive Rules Guide (p.56-57) because the editor shows it as a tooltip.
 * When a tabletop feature is added or changes, change its entry here (docs/features/help.md).
 */
import { SURFACES_PER_PIECE, SURFACE_MAX_TOP, type TerrainTrait } from '../../tabletop/geometry.ts';
import { TERRAIN_PRESETS } from '../../tabletop/terrainPresets.ts';
import type { HelpTopic } from '../types.ts';

/** What each terrain trait means, for the trait checkboxes' tooltips and the terrain help. */
export const TRAIT_HELP: Record<TerrainTrait, string> = {
    blocking: 'Cannot be seen through, so it can block sight lines. Models within 2" of it get cover if a sight line crosses it.',
    climbable: 'Models cannot move through it but can move across its top and climb its sides with a Walk.',
    impassable: 'Models and markers cannot move through it, and nothing can be made or placed overlapping it.',
    concealing: 'A model whose sight line passes through it has concealment. Most fog banks are concealing.',
    dense: 'Sight can be drawn into or out of it but not through it. Most woods are dense.',
    hazardous: 'A model that moves through it, touches it or acts in it suffers its effect, at most once per activation.',
    destructible: 'Can be targeted with the Slam action to remove it from play.',
    severe: 'Moving through it costs twice as much movement.',
    ramp: 'Stairs or a ramp: a model goes up or down it without climbing or falling, so the height change costs no movement.',
};

const tabletop: HelpTopic = {
    slug: 'tabletop',
    title: 'Tabletop',
    summary: 'A 36 by 36 inch table for placing models and terrain, measuring, checking sight lines and keeping a game on the board.',
    route: 'tools.tabletop',
    sections: [
        {
            id: 'start',
            title: 'Getting started',
            intro: 'The tabletop keeps track of bases, terrain and distances so you do not have to. It is a sandbox on its own, and the board of a game.',
            items: [
                {
                    title: 'Two tabs',
                    body: 'Three tabs sit at the top of the side column. Models is for the models and markers on the table: moving, measuring and their health. Terrain is for laying out the table. Table is for the table itself: saving and loading it, its surface, deployment zones and what is drawn on it. Switch with a click, or with the left and right arrow keys while a tab has focus. The question mark beside the tabs opens this help.',
                },
                {
                    title: 'Looking around',
                    body: 'Zoom with the buttons in the bar above the board, the mouse wheel or a pinch, and drag empty table to move around. Zoom is only a view: nothing on the table changes.',
                },
                {
                    title: 'The sandbox',
                    body: 'Open Tabletop from the Play menu. Add a character to put its model on the table at its real base size, with its Sz, Sp and health. The sandbox keeps your table on this device. Use Save table (on the Table tab) to keep one in My tables and share it by link; Load table brings one back.',
                },
                {
                    title: 'The board of a game',
                    body: 'Open it from a game, or from Tabletop in the Play menu. The crew from the game is on the table. Drag your own models; the other crew is locked and only its player can move it. Either player can lay out the terrain. Everything is saved with the game, and anyone watching an observable game sees it update, read only.',
                },
                {
                    title: 'The model list',
                    body: "Each model has the same row as the game tracker: health, activation, tokens and its card (tap the name). Choose a row, or tap the model, and the Options button opens what is specific to the table, in groups: Check (sight guidelines, your scheme's model), Movement (what it ignores, and going under or on top of terrain), Fate (a flip for the model), and Notes and auras, with Mark killed and Remove together at the end. Add characters, markers and terrain with the Add button above the list. With a large crew, turn on Compact model list (Table tab, under Surface and zones) for one line per model: its name, health, Options and a menu with Tokens, Upgrades and Replace. The choice is kept on this device.",
                    image: {
                        src: 'tabletop/model-options.webp',
                        alt: "A model's options panel: its size and position, a Sight guidelines button, a Mark killed button, the Ignores terrain, Fate flip and Notes sections, and Add aura.",
                        callouts: ['Sight guidelines.', 'What the model ignores.', 'Mark killed.'],
                    },
                },
            ],
        },
        {
            id: 'moving',
            title: 'Moving models',
            intro: 'Drag a model to move it. A line follows it, and the note under the table says how far it went and what it crossed.',
            items: [
                {
                    title: 'Walk, Charge, Jump, Flight, Place',
                    body: 'Choose how the model moves with the buttons in the bar above the board (next to Ruler and Step). Walk spends movement and is stopped by terrain. Charge is a straight line up to its Sp, once per activation and not while engaged. Jump crosses a gap. Flight is a Walk that ignores terrain height. Place puts a model down within a range with no movement. Hover a button for its short description. For Place, its range and Whole base are in the note that opens under the tabs. Pressing Ruler or Step does not rearrange the toolbar: choosing a move button simply goes back to moving models.',
                },
                {
                    title: 'Step',
                    body: 'Turn on Step, then grab the same model again to carry on from where you dropped it: 2" one way and 5" another is 7". Without Step, a new drag starts a fresh measure.',
                },
                {
                    title: 'What the note tells you',
                    body: 'The note reports the distance against the Sp, heights climbed, severe or hazardous terrain crossed, and whether the model is engaged. A model that ends overlapping another base or impassable terrain gets a red outline, and you can move it to the nearest legal spot.',
                },
                {
                    title: 'Models that are not on the table yet',
                    body: 'A summoned model shows "Place on the table" in its row. Say which of your models summoned it and how far it may go ("Summoned by" and "within" inches): a dashed line shows where it may go, a tap farther away is pulled in to it, and a spot that is not legal is refused with the reason. Leave it on "anywhere" if the effect has no limit. Then tap the table. A replaced model asks to be set down touching where the original stood (see On the board of a game).',
                },
                {
                    title: 'Locked models',
                    body: "In a game you can only move your own crew. The other player's models are drawn where they are and cannot be dragged.",
                },
            ],
        },
        {
            id: 'measuring',
            title: 'Measuring and sight',
            intro: 'Everything is measured edge to edge on the bases.',
            items: [
                {
                    title: 'Ruler',
                    body: 'Choose Ruler, then press on a model (or a spot on the table) and drag to another. The readout gives the horizontal distance edge to edge and the difference in height.',
                },
                {
                    title: 'Sight guidelines',
                    body: "In a model's Options, turn on Sight guidelines. Lines leave each side of its base in the direction you drag; the board says whether line of sight is clear or blocked, whether the target has cover or concealment, and why. While they are on, the Sight guidelines note under the tabs has an Aim button for aiming them again.",
                },
                {
                    title: 'Auras and markers',
                    body: 'A model can carry an aura of concealing, dense, hazardous or severe terrain; the area is drawn on the table and counts for sight and movement. Markers are added from the marker list, and a marker lists the models near it.',
                },
                {
                    title: 'Table lines and zones',
                    body: 'Show table lines draws the centre axes and the middle of each 18 inch quarter. Choosing a deployment draws its zones. Show terrain heights turns the "Ht 3" labels on the terrain on or off (these switches, and the surface and deployment, are on the Table tab under Surface and zones).',
                },
            ],
        },
        {
            id: 'terrain',
            title: 'Building terrain',
            intro: 'Pieces are made of levels. Each level has an outline and a height, which is what movement, sight and cover use.',
            items: [
                {
                    title: 'Start from a preset',
                    body: `Pick a piece in the list and press Add. The presets are: ${TERRAIN_PRESETS.map((p) => p.label).join('; ')}.`,
                    image: {
                        src: 'tabletop/terrain-editor.webp',
                        alt: 'The Terrain tab: the table summary, a list of preset pieces with an Add button, Draw an outline, and for a chosen piece its name, turn buttons, background, building, rules card and traits.',
                        callouts: [
                            'The list of presets, and Add.',
                            'Draw an outline: make your own shape.',
                            'Turn the chosen piece 90 degrees or 15 degrees either way.',
                            'Traits: how the piece behaves.',
                        ],
                    },
                },
                {
                    title: 'Draw your own outline',
                    body: 'Press Draw outline, then tap the board to put down corners (they snap to half inches). Tap the first corner again, or press Finish, to close the shape. Undo corner takes the last one back and Cancel drops the shape.',
                },
                {
                    title: 'Move, turn and reshape',
                    body: 'Drag a piece to move it. Select it, then drag a corner dot to reshape it, or drag the dot in the middle of an edge to add a corner. Turn 90 degrees, or 15 degrees either way, rotates every level together. Duplicate copies a piece, Delete removes it, and Undo takes back your last change. On a phone the terrain editor opens as a sheet over the lower part of the screen, with the whole board still in view above it, so you see each change as you make it; press Done to close it.',
                },
                {
                    title: 'Levels and heights',
                    body: `A piece can have up to ${SURFACES_PER_PIECE} levels, each with its own outline and height from 0 to ${SURFACE_MAX_TOP} inches. A tower is a low level with a smaller, higher one on top. Add a level in the piece's Levels list; a level's handles show when you open it. A model stands at the height of the highest level that holds at least half its base.`,
                },
                {
                    title: 'Traits',
                    body: `Traits say how a piece behaves; hover one in the editor for its description. ${Object.entries(TRAIT_HELP)
                        .map(([t, text]) => `${t === 'ramp' ? 'Stairs / ramp' : t[0].toUpperCase() + t.slice(1)}: ${text}`)
                        .join(' ')}`,
                },
                {
                    title: 'Stairs and ramps',
                    body: 'The Stairs and Ramp presets, or the "stairs / ramp" trait on any piece, let a model change height without paying to climb or suffering a fall. A move straight up the side of a platform still counts the climb.',
                },
                {
                    title: 'Bridges and archways',
                    body: 'Turn on "Archway beneath" for a level to make a bridge: models that fit under it can stand and walk beneath, and sight passes under it. Set the archway height to say how tall a model can be.',
                },
                {
                    title: 'Buildings you can enter',
                    body: 'Turn on the building option for a piece and add doors and windows to its walls. A model inside stands on the floor and is seen only through those openings. Drag the dots on the walls to slide them, or use Show inside to look in.',
                },
                {
                    title: 'Special terrain',
                    body: 'For special terrain, paste its rules card text into the piece and say how many may be on the table. It is shown with the piece so everyone sees what it does.',
                },
                {
                    title: 'Table check',
                    body: 'The Terrain tab counts how much of the table is covered (aim for 45 to 65 percent) and how many climbable pieces are Ht 2 or more (aim for 3 to 8). The Table check below it follows the Gaining Grounds guidelines once a deployment is chosen.',
                },
                {
                    title: 'Saving',
                    body: 'In the sandbox, terrain is kept on this device; use Save table on the Table tab to keep it in My tables and share it. In a game, terrain is saved with the game and the other player sees changes.',
                },
            ],
        },
        {
            id: 'game',
            title: 'On the board of a game',
            intro: 'The board and the game tracker are the same game: health, activation, tokens and kills are shared.',
            items: [
                {
                    title: 'The turn bar',
                    body: 'Shows the turn, whose activation it is, and who picks initiative, with a Pass button. In tournament rounds the round clock shows too.',
                },
                {
                    title: 'Look back',
                    body: 'Use the Look back list to see the table as it stood at the end of an earlier turn: positions, markers, health and tokens, read only. Choose Now to come back.',
                },
                {
                    title: 'Reading a base',
                    body: "The base is filled with the model's faction colour, and the ring around its edge is its side. The ring just inside the edge is its health: one segment per wound (a smooth ring past 12), red when it is low. Bases of 40 mm and up also show the number. Badges show tokens, activation and engagement; turn them off with Show status badges.",
                    image: {
                        src: 'tabletop/board.webp',
                        alt: 'Part of the table with four models: two with a red base and a blue ring and two with a blue base and a red ring, each with its name, a ring of health segments inside the edge and its health number.',
                        callouts: [
                            'The ring just inside the edge is health: one segment for each wound.',
                            "The ring outside the edge is the model's side.",
                            'Badges above the base show tokens and activation.',
                            'A red health ring means health is low.',
                        ],
                    },
                },
                {
                    title: 'Killing, Remains markers and soulstones',
                    body: 'Use Mark killed in a model\'s options. The crew gets a soulstone as in the game tracker, and a prompt offers the Remains marker: "Choose the spot" and tap inside the dashed line, or "Place it for me". A model that replaces itself on death offers its replacements.',
                },
                {
                    title: 'Replacing a model',
                    body: 'Use Replace on a model\'s row. The new model asks to be set down touching where the original stood: tap the side it should go on, or choose "Place it for me" or "Keep it here". The prompt stays until you answer it, even if you reload the page on the same device.',
                },
                {
                    title: 'Scheme targets',
                    body: "In a model or marker's Options, choose it as the scheme's model or marker. It is saved in your scheme notes, which only you can see.",
                },
                {
                    title: 'Upgrades and injuries',
                    body: "Use Upgrades on a model's row (on a phone it is in the row's menu) to attach or remove an upgrade: it is the same list and the same saving as the play screen, with your crew's own upgrades first. In a Campaign game there is also Attach Injury. You can only change the models you control.",
                },
            ],
        },
        {
            id: 'fate',
            title: 'The Fate deck',
            intro: 'Each player has a 54 card Fate deck. In a game the server keeps it, so nobody can see a hand that is not theirs. In the sandbox it is a practice deck.',
            items: [
                {
                    title: 'Opening it',
                    body: 'Press Fate deck. On a wide screen it opens under the button; on a phone it opens as a sheet over the lower part of the screen, and the table above stays usable. Escape closes the sheet.',
                },
                {
                    title: 'Draw, discard and look',
                    body: 'Tap your deck to draw. Tap a hand card for its menu (discard, and more). Tap your discard pile to look through it. "Up to" draws to your hand size, and "Start phase" runs the discard-then-draw routine.',
                    image: {
                        src: 'tabletop/fate-deck.webp',
                        alt: "The Fate deck: the other player's strip, your deck and discard pile, the flip controls, a hand of two cards, and the Up to and Start phase buttons.",
                        callouts: ['Your deck: tap to draw.', 'Flip.', 'A card in your hand: tap for its menu.'],
                    },
                },
                {
                    title: 'Flipping',
                    body: "Set + or - for a positive or negative flip and press Flip. Choose the result card; a Red Joker lets you name its suit. Label a flip to say what it is for, or flip from a model's Options so its result is drawn beside it.",
                },
                {
                    title: 'Cheating Fate',
                    body: 'After a result is chosen, a player may cheat by replacing it with a card from their hand: tap a hand card, then Cheat. Each player gets one chance per flip; Pass skips yours.',
                },
                {
                    title: 'Opposed duels',
                    body: 'For an opposed duel, flip as the attacker and have the other player flip as the resister. Each enters their total. The table then follows the cheating order for you: the lowest total first, the resister on a tie, then the other player.',
                },
            ],
        },
    ],
    shortcuts: [
        {
            title: 'The table: models',
            note: 'Tab to the table (it shows a ring when it has focus), or click a model. Choose a model with the bracket keys.',
            shortcuts: [
                { keys: ['[', ']'], does: 'Choose the previous or next model. The note under the table says which one, and its options open.' },
                {
                    keys: ['←', '→', '↑', '↓'],
                    does: 'Move the chosen model 0.5 inch. The same movement rules, notes and saving apply as for a drag.',
                },
                { keys: ['Shift', '+ arrow'], does: 'Move it 0.1 inch.' },
                { keys: ['Alt', '+ arrow'], does: 'Move it 2 inches.' },
                { keys: ['Esc'], does: 'Let go of the chosen model.' },
                { keys: ['?'], does: 'Open this help.' },
            ],
        },
        {
            title: 'The table: terrain',
            note: 'On the Terrain tab, the same table focus applies.',
            shortcuts: [
                { keys: ['[', ']'], does: 'Choose the previous or next piece of terrain.' },
                { keys: ['←', '→', '↑', '↓'], does: 'Move the chosen piece 0.5 inch (hold Alt for 2 inches).' },
                { keys: [',', '.'], does: 'Turn the piece 15 degrees anticlockwise or clockwise.' },
                { keys: ['Delete'], does: 'Remove the piece. Undo brings it back.' },
                { keys: ['Esc'], does: 'Let go of the piece.' },
            ],
        },
        {
            title: 'Tabs, sheets and dialogs',
            shortcuts: [
                { keys: ['←', '→'], does: 'Switch between the Models and Terrain tabs while a tab has focus.' },
                { keys: ['Esc'], does: 'Close a dialog, a menu, or the Fate sheet on a phone (focus goes back to the Fate button).' },
                {
                    keys: ['Tab', 'Shift + Tab'],
                    does: 'Move between controls. Every control on the board and in the Fate deck can be reached and used from the keyboard.',
                },
            ],
        },
        {
            title: 'Mouse and touch',
            shortcuts: [
                { keys: ['Wheel'], does: 'Zoom the table. On a phone, pinch.' },
                { keys: ['Drag'], does: 'Drag a model or terrain piece to move it; drag empty table to move the view.' },
                { keys: ['Click'], does: 'Choose a model. Click empty table to let go, or to place a model that is waiting to be set down.' },
            ],
        },
    ],
};

export default tabletop;
