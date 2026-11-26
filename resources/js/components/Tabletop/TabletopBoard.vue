<script setup lang="ts">
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Button } from '@/components/ui/button';
import { useConfirm } from '@/composables/useConfirm';
import {
    AURAS_PER_MODEL,
    DEPLOYMENT_KINDS,
    TABLE_SIZE,
    auraOrigin,
    AURA_TRAITS,
    auraRadius,
    auraTerrain,
    baseRadius,
    clampToTable,
    constrainToContact,
    constrainWithin,
    contactSpots,
    deploymentZones,
    describeZone,
    edgeDistance,
    fallHurts,
    lineOfSight,
    moveCost,
    pathCost,
    placeCheck,
    placeReach,
    walkBudget,
    heightCover,
    terrainChips,
    dragShapeHandle,
    engagedSp,
    engagedWith,
    coverAt,
    insertCorner,
    insideOf as buildingOf,
    interiorShape,
    moveOpening,
    overRamp,
    openingSpan,
    wallProblem,
    wallSegments,
    movePiece,
    jumpCheck,
    nearestLegalSpot,
    pieceBounds,
    placementProblems,
    unreachableAreas,
    type UnreachableAreas,
    removeCorner,
    restingSupport,
    rotatePiece,
    shapeCentre,
    shapeHandles,
    settleUnder,
    sideSightLines,
    surfaceAt,
    whereabouts,
    terrainAlongPath,
    IGNORABLE_TRAITS,
    traceLine,
    modelsWithin,
    type Aura,
    type DeploymentKind,
    type MarkerOption,
    type ModelInfo,
    type Shape,
    type ShapeHandle,
    type TerrainPiece,
    type Token,
} from '@/lib/tabletop/geometry';
import CharacterPicker, { type CharacterOption } from '@/components/Tabletop/CharacterPicker.vue';
import MarkerPicker from '@/components/Tabletop/MarkerPicker.vue';
import GameCardFullscreenDialog from '@/components/Game/GameCardFullscreenDialog.vue';
import GameCrewMemberDrawer from '@/components/Game/GameCrewMemberDrawer.vue';
import GameTokenDialog from '@/components/Game/GameTokenDialog.vue';
import FateCardFace from '@/components/Fate/FateCardFace.vue';
import GameCrewMemberRow from '@/components/Game/GameCrewMemberRow.vue';
import ModelInfoPanel from '@/components/Tabletop/ModelInfoPanel.vue';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import TableLoadDialog from '@/components/Tabletop/TableLoadDialog.vue';
import TableSaveDialog from '@/components/Tabletop/TableSaveDialog.vue';
import TerrainEditor from '@/components/Tabletop/TerrainEditor.vue';
import type { LayoutSaveTarget, SavedLayout } from '@/lib/tabletop/layouts';
import { sampleTerrain } from '@/lib/tabletop/sampleTable';
import { shapeAttrs, shapeTag } from '@/lib/tabletop/shapeDraw';
import { newPieceId } from '@/lib/tabletop/terrainPresets';
import {
    ChevronDown,
    CircleHelp,
    Eye,
    FolderOpen,
    Lock,
    LockOpen,
    MapPin,
    Maximize,
    Minus,
    Mountain,
    Settings2,
    Plus,
    Route,
    Ruler,
    Save,
    Swords,
    Trash2,
    TriangleAlert,
    User,
    Users,
} from 'lucide-vue-next';
import { useFactionColor } from '@/composables/useFactionColor';
import HelpDialog from '@/components/Help/HelpDialog.vue';
import { GAME_ICONS } from '@/lib/gameIcons';
import { RED_JOKER, SUITS, cardFace, cardLabel, cardSuit, type FateFeed, type FateFlip } from '@/lib/fate/fate';
import { useMediaQuery } from '@vueuse/core';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, shallowReactive, shallowRef, watch } from 'vue';

/** A crew member in the shape the play screen's row reads (typed loosely, like the row itself). */
type CrewMember = {
    id: number;
    slot: number;
    is_killed: boolean;
    display_name: string;
    current_health: number | null;
    max_health: number | null;
} & Record<string, any>;
type Mode = 'move' | 'ruler' | 'sight' | 'terrain';
type Point = { x: number; y: number };

const MIN_WIDTH = 6;

const props = withDefaults(
    defineProps<{
        tokens: Token[];
        terrain?: TerrainPiece[];
        sandbox?: boolean;
        sideNames?: { a: string; b: string };
        /** In a game: each side's role, which sets its colour (attacker red, defender blue). Without roles side A is blue and side B red. */
        sideRoles?: { a?: string | null; b?: string | null };
        /** Markers that can be placed (name and base size from the markers table). Without them the picker is hidden. */
        markers?: MarkerOption[];
        /** The sides this viewer may place markers for. Each side can have MARKERS_PER_KIND of each marker on the table. The sandbox offers both. */
        placeAs?: { side: 'a' | 'b'; label: string }[];
        /** Auras players have chosen to show both sides, saved with the game. Without it, auras are private to this browser. */
        sharedAuras?: Aura[];
        /** Which way round the deployment zones sit, when the game keeps it for both players. */
        deploymentVariant?: number;
        /** Table surfaces to choose from. A built-in one repeats as a tile; an uploaded one stretches over the whole table. */
        tableImages?: { key: string; name: string; url: string; fit: 'tile' | 'cover' }[];
        /** The viewer's saved terrain layouts, and where saving the table sends it. Without a target the saved-tables panel is hidden. */
        layouts?: SavedLayout[];
        /** The saved layouts are still on their way (they load after the page). */
        layoutsLoading?: boolean;
        layoutTarget?: LayoutSaveTarget;
        signedIn?: boolean;
        /** The tab the board opens on: models (the default), or terrain for a new or edited table. */
        initialMode?: 'move' | 'terrain';
        /** The chosen surface (a key from tableImages); without one, or if it is gone, the table is grass. A game keeps it for both players. */
        tableImage?: string | null;
        /** A texture for each kind of terrain: kind => url. It repeats every TEXTURE_TILE inches across the pieces of that kind. */
        terrainImages?: Record<string, string>;
        /** Someone watching: no terrain editing, and the deployment orientation is theirs alone. */
        readOnly?: boolean;
        /** Where to search character cards to add to the table (the sandbox). Without it only blank models can be added. */
        characterSearchUrl?: string;
        /** Tokens a model can carry. Without them the panel still shows health, activation and notes. */
        tokenOptions?: { id: number; name: string }[];
        /** The Fate deck (a game's, or the sandbox's practice deck): a model can flip for itself and its result is drawn beside it. */
        fate?: FateFeed;
        /** The scheme notes of the players this viewer plays, by slot: with them a model or marker can be picked as the scheme's. */
        schemeNotes?: Record<number, { selected_model?: string | null; selected_marker?: string | null }>;
        /** A game's crew as the play screen's rows show it (health, activation, tokens, card). With it the models are listed as those rows and the tabletop options open under the chosen one. */
        crew?: CrewMember[];
        /** Models that were replaced and still need setting down in base contact with where the original stood (CRG p.48), by model id. */
        placeAnchors?: Record<string, { x: number; y: number; baseMm: number; name: string }>;
        /** The page can attach upgrades to a model (a game, for someone who plays in it): the rows offer Upgrades, and Attach Injury in a Campaign game. */
        canAttach?: boolean;
        isCampaign?: boolean;
        storageKey?: string;
        deployment?: DeploymentKind;
    }>(),
    {
        sandbox: false,
        sideNames: () => ({ a: 'Blue', b: 'Red' }),
    },
);
const emit = defineEmits<{
    moved: [token: Token];
    add: [token: Omit<Token, 'id'>];
    addMarker: [marker: { markerId: number; name: string; baseMm: number; side: 'a' | 'b'; x: number; y: number }];
    remove: [id: string];
    reset: [];
    terrainChanged: [pieces: TerrainPiece[]];
    variantChanged: [variant: number];
    tableImageChanged: [key: string | null];
    modelChange: [id: string, patch: Partial<ModelInfo>];
    replaceModel: [id: string];
    /** Mark a model killed, or bring it back. */
    killChange: [id: string, killed: boolean];
    /** Pick (or put down) a model or marker as what a scheme points at. */
    schemeTarget: [kind: 'model' | 'marker', slot: number, name: string | null];
    /** Sandbox: swap a model for another character, keeping what it has picked up. */
    replaceCharacter: [id: string, character: CharacterOption];
    auraChange: [change: { upsert?: Aura[]; remove?: string[] }];
    /** A replacement has been set down (or left where it is): the page can forget where the original stood. */
    placed: [id: string];
    /** The row's Upgrades or Attach Injury was pressed. */
    openUpgrades: [id: string];
    openInjury: [id: string];
}>();

// The table layout, copied so a piece moves smoothly while it is dragged; the parent gets the layout once it is dropped.
const cloneTerrain = (pieces: TerrainPiece[]): TerrainPiece[] => JSON.parse(JSON.stringify(pieces));
const terrain = ref<TerrainPiece[]>(cloneTerrain(props.terrain ?? sampleTerrain));
watch(
    () => props.terrain,
    (next) => {
        if (!next) return;
        const grabbed = drag;
        const held = grabbed?.kind === 'piece' || grabbed?.kind === 'handle' ? terrain.value.find((p) => p.id === grabbed.id) : undefined;
        terrain.value = cloneTerrain(next).map((p) => (held && p.id === held.id ? held : p));
    },
    { deep: true },
);
const selectedPieceId = ref<string | null>(null);
/** The level of the selected piece whose handles show on the board, and the corner of a drawn outline picked to be removed. */
const activeSurfaceId = ref<string | null>(null);
const pickedVertex = ref<number | null>(null);
/** How far the piece is being turned right now by its rotate handle, in degrees (0 when it is not). */
const turning = ref(0);
watch(selectedPieceId, () => {
    activeSurfaceId.value = null;
    pickedVertex.value = null;
});
const selectedPiece = computed(() => terrain.value.find((p) => p.id === selectedPieceId.value) ?? null);
const activeSurface = computed(() => {
    const piece = selectedPiece.value;
    if (!piece) return null;
    return piece.surfaces.find((s) => s.id === activeSurfaceId.value) ?? [...piece.surfaces].sort((a, b) => b.top - a.top)[0] ?? null;
});
/** Inches on the table that cover `px` screen pixels, so handles stay finger-sized however far the board is zoomed. */
const pxToUnits = (px: number): number => (px * view.value.w) / (svg.value?.getBoundingClientRect().width || 640);
const handles = computed<ShapeHandle[]>(() => {
    if (mode.value !== 'terrain' || outline.value || !activeSurface.value) return [];
    const openings = (selectedPiece.value?.interior?.openings ?? []).map((o, i) => ({
        id: `opening-${i}`,
        kind: 'opening' as const,
        index: i,
        x: o.x,
        y: o.y,
    }));
    return [...shapeHandles(activeSurface.value.shape), ...openings];
});
/** The dot above a selected piece that turns it, with the middle it turns about. */
const rotateGrip = computed(() => {
    const piece = selectedPiece.value;
    if (!piece || mode.value !== 'terrain' || outline.value) return null;
    const b = pieceBounds(piece);
    const centre = { x: (b.minX + b.maxX) / 2, y: (b.minY + b.maxY) / 2 };
    const reach = (b.maxY - b.minY) / 2 + pxToUnits(28);
    const a = (turning.value * Math.PI) / 180;
    return {
        centre,
        reach,
        x: Math.min(TABLE_SIZE - 0.3, Math.max(0.3, centre.x + reach * Math.sin(a))),
        y: Math.min(TABLE_SIZE - 0.3, Math.max(0.3, centre.y - reach * Math.cos(a))),
    };
});
/** Layouts before each change, newest last, so a slip of the finger can be undone. */
const undoStack = ref<TerrainPiece[][]>([]);
function commitTerrain(next: TerrainPiece[], remember = true) {
    if (remember) undoStack.value = [...undoStack.value.slice(-19), cloneTerrain(terrain.value)];
    terrain.value = cloneTerrain(next);
    pickedVertex.value = null;
    emit('terrainChanged', cloneTerrain(next));
}
/** Take the picked corner off the level that is showing its handles. */
function removePickedCorner() {
    const piece = selectedPiece.value;
    const surface = activeSurface.value;
    if (!piece || !surface || pickedVertex.value === null) return;
    const shape = removeCorner(surface.shape, pickedVertex.value);
    if (!shape) return;
    commitTerrain(
        terrain.value.map((p) =>
            p.id === piece.id ? { ...piece, surfaces: piece.surfaces.map((s) => (s.id === surface.id ? { ...s, shape } : s)) } : p,
        ),
    );
}
/** Drawing a polygon piece: the corners placed so far (snapped to half inches). The first corner closes it. */
const outline = ref<Point[] | null>(null);
const OUTLINE_MAX = 16;
const snapHalf = (n: number) => Math.min(TABLE_SIZE, Math.max(0, Math.round(n * 2) / 2));
function startOutline() {
    outline.value = [];
    selectedPieceId.value = null;
}
function cancelOutline() {
    outline.value = null;
}
function undoCorner() {
    if (outline.value) outline.value = outline.value.slice(0, -1);
}
/** A drawn outline needs three corners and a real area. */
const outlineArea = (pts: Point[]) =>
    Math.abs(pts.reduce((sum, a, i) => sum + (a.x * pts[(i + 1) % pts.length].y - pts[(i + 1) % pts.length].x * a.y), 0)) / 2;
function finishOutline() {
    const pts = outline.value;
    if (!pts || pts.length < 3 || outlineArea(pts) < 1) {
        note.value = 'An outline needs at least three corners that enclose some ground.';
        return;
    }
    const id = newPieceId();
    const piece: TerrainPiece = {
        id,
        name: 'Outline',
        traits: ['blocking', 'climbable'],
        surfaces: [{ id: `${id}-1`, top: 2, shape: { kind: 'poly', points: pts.map((q): [number, number] => [q.x, q.y]) } }],
    };
    outline.value = null;
    commitTerrain([...terrain.value, piece]);
    selectedPieceId.value = id;
}
function addCorner(p: Point) {
    if (!outline.value) return;
    const q = { x: snapHalf(p.x), y: snapHalf(p.y) };
    const first = outline.value[0];
    // Closing: a corner back on the first one finishes the shape.
    if (first && outline.value.length >= 3 && Math.hypot(q.x - first.x, q.y - first.y) < 1) return finishOutline();
    if (outline.value.length >= OUTLINE_MAX) {
        note.value = `An outline can have up to ${OUTLINE_MAX} corners.`;
        return;
    }
    if (!outline.value.some((o) => o.x === q.x && o.y === q.y)) outline.value = [...outline.value, q];
}
function undoTerrain() {
    const previous = undoStack.value.at(-1);
    if (!previous) return;
    undoStack.value = undoStack.value.slice(0, -1);
    terrain.value = cloneTerrain(previous);
    if (!terrain.value.some((p) => p.id === selectedPieceId.value)) selectedPieceId.value = null;
    emit('terrainChanged', cloneTerrain(previous));
}
const viewCentre = computed(() => ({ x: view.value.x + view.value.w / 2, y: view.value.y + view.value.w / 2 }));
// A working copy, so a drag moves smoothly; the parent gets one `moved` event when the model is dropped.
const tokens = ref<Token[]>(props.tokens.map((t) => ({ ...t })));
watch(
    () => props.tokens,
    (next) => {
        const grabbed = drag;
        const held = grabbed?.kind === 'token' ? tokens.value.find((t) => t.id === grabbed.id) : undefined;
        tokens.value = next.map((t) => (held && t.id === held.id ? { ...t, x: held.x, y: held.y } : { ...t }));
    },
    { deep: true },
);
const selectedId = ref<string | null>(null);
const mode = ref<Mode>(props.initialMode ?? 'move');
watch(mode, (m) => {
    // on a phone, bring the board into view when the editor sheet opens, so the edits are seen as they are made (a jump, not an animation)
    if (m === 'terrain' && compactScreen.value) {
        void nextTick(() => document.querySelector('[data-testid=board-bar]')?.scrollIntoView({ block: 'start' }));
    }
    // the terrain tab and the table tab are never open together
    if (m === 'terrain') showTable.value = false;
    if (m !== 'terrain') outline.value = null;
    // The ruler is a tool: when it is switched off, what it measured goes with it.
    if (m !== 'ruler') ruler.value = null;
});
/** The tabs at the top of the side column: the models, the terrain, and the table itself (its saved copies and settings). The ruler and the aim are tools of the board. */
type TabId = 'move' | 'terrain' | 'table';
const showTable = ref(false);
/** On a phone the terrain editor is a sheet over the lower part of the screen, so the board stays in view while you edit. */
const compactScreen = useMediaQuery('(max-width: 1023px)');
const terrainSheet = computed(() => compactScreen.value && mode.value === 'terrain' && !props.readOnly);
const activeTab = computed<TabId>(() => (showTable.value ? 'table' : mode.value === 'terrain' ? 'terrain' : 'move'));
/** A watcher cannot change the terrain, so has no Terrain tab. */
const tabs = computed<{ id: TabId; label: string; icon: unknown }[]>(() => [
    { id: 'move', label: 'Models', icon: Users },
    ...(props.readOnly ? [] : [{ id: 'terrain' as const, label: 'Terrain', icon: Mountain }]),
    { id: 'table', label: 'Table', icon: Settings2 },
]);
const openTab = (tab: TabId) => {
    showTable.value = tab === 'table';
    // the Models tab always returns to plain moving (it is how a tool such as the ruler or the aim is put away)
    if (tab === 'terrain') mode.value = 'terrain';
    else if (tab === 'move' || mode.value === 'terrain') mode.value = 'move';
};
/** The arrow keys move between the tabs (and wrap round), taking focus with them. */
function stepTab(by: 1 | -1) {
    const list = tabs.value;
    const i = list.findIndex((t) => t.id === activeTab.value);
    const next = list[(i + by + list.length) % list.length];
    openTab(next.id);
    void nextTick(() => document.querySelector<HTMLElement>(`[data-testid="mode-${next.id}"]`)?.focus());
}
const tabClass = (active: boolean) =>
    `flex items-center justify-center rounded-md px-3 py-1.5 text-sm font-medium transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary ${
        active ? 'bg-background text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'
    }`;
const tableSummary = computed(() =>
    [
        tableChoice.value?.name ?? 'Grass',
        deployment.value === 'none'
            ? 'no deployment'
            : (DEPLOYMENT_KINDS.find((d) => d.value === deployment.value)?.label.split(' (')[0] ?? deployment.value),
        tableLines.value ? 'lines on' : null,
    ]
        .filter(Boolean)
        .join(' · '),
);
/**
 * On a phone the side column is under the board, so what is selected can have its panel off the bottom of the screen. A chip floating above
 * the tab bar says what is selected and takes you to its panel; it shows only while that panel is out of view.
 */
const panelVisible = ref(true);
const selectionLabel = computed(() => (mode.value === 'terrain' ? (selectedPiece.value?.name ?? null) : (selected.value?.name ?? null)));
let panelObserver: IntersectionObserver | null = null;
function watchPanel() {
    panelObserver?.disconnect();
    panelObserver = null;
    const el = document.querySelector(
        '[data-testid=side-column] [data-testid=selected-panel], [data-testid=side-column] [data-testid=terrain-piece]',
    );
    if (!el || !selectionLabel.value || typeof IntersectionObserver === 'undefined') {
        panelVisible.value = true;
        return;
    }
    panelObserver = new IntersectionObserver(([entry]) => (panelVisible.value = entry.isIntersecting), { threshold: 0.05 });
    panelObserver.observe(el);
}
watch([selectedId, selectedPieceId, mode], () => nextTick(watchPanel), { flush: 'post' });
onBeforeUnmount(() => {
    panelObserver?.disconnect();
    if (moveFrame) cancelAnimationFrame(moveFrame);
    if (reachTimer) clearTimeout(reachTimer);
});
function showPanel() {
    document
        .querySelector('[data-testid=side-column] [data-testid=selected-panel], [data-testid=side-column] [data-testid=terrain-piece]')
        ?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}
/** Load table and Save table: dialogs. */
const loadOpen = ref(false);
const saveOpen = ref(false);
/** The Ruler button is a switch: on to measure, off to put the ruler away and go back to moving models. */
/** The move buttons stay on the toolbar in every mode (so it never changes height and moves the board): pressing one goes back to moving models. */
function chooseKind(kind: MoveKind) {
    moveKind.value = kind;
    mode.value = 'move';
}
function chooseStep() {
    if (mode.value !== 'move') {
        mode.value = 'move';
        return;
    }
    stepMove.value = !stepMove.value;
}
const toggleRuler = () => (mode.value = mode.value === 'ruler' ? 'move' : 'ruler');

const level = ref<number | 'all'>('all');
const deployment = ref<'none' | DeploymentKind>(props.deployment ?? 'none');
/** The table's own reference lines (the two centre axes and the middle of each quarter), shown whatever deployment is chosen. Kept on this device. */
const LINES_KEY = 'tabletop-table-lines';
const tableLines = ref(false);
onMounted(() => {
    try {
        tableLines.value = localStorage.getItem(LINES_KEY) === '1';
    } catch {
        // storage unavailable: off
    }
});
watch(tableLines, (on) => {
    try {
        localStorage.setItem(LINES_KEY, on ? '1' : '0');
    } catch {
        // ignore
    }
});
/** Status badges (token chips, the activation tick, engaged) on the bases: on by default, and can be put away when a crowded table gets busy. */
const BADGES_KEY = 'tabletop-badges';
/** The height labels on the terrain ("Ht 3"): on by default, and can be put away when they crowd the models. */
/** One line per model in the list beside the table (a long crew is otherwise a very long list). Kept on this device. */
const COMPACT_KEY = 'tabletop-compact-rows';
const compactRows = ref(false);
onMounted(() => {
    try {
        compactRows.value = localStorage.getItem(COMPACT_KEY) === '1';
    } catch {
        // storage unavailable: the full rows
    }
});
watch(compactRows, (on) => {
    try {
        localStorage.setItem(COMPACT_KEY, on ? '1' : '0');
    } catch {
        // ignore
    }
});
const HEIGHTS_KEY = 'tabletop-heights';
const showHeights = ref(true);
const showBadges = ref(true);
onMounted(() => {
    try {
        showBadges.value = localStorage.getItem(BADGES_KEY) !== '0';
        showHeights.value = localStorage.getItem(HEIGHTS_KEY) !== '0';
    } catch {
        // storage unavailable: on
    }
});
watch(showHeights, (on) => {
    try {
        localStorage.setItem(HEIGHTS_KEY, on ? '1' : '0');
    } catch {
        // ignore
    }
});
watch(showBadges, (on) => {
    try {
        localStorage.setItem(BADGES_KEY, on ? '1' : '0');
    } catch {
        // ignore
    }
});
/** The middle of each 18" quarter of the table (p.60). */
const QUARTER_CENTRES = [
    { x: TABLE_SIZE / 4, y: TABLE_SIZE / 4 },
    { x: (TABLE_SIZE * 3) / 4, y: TABLE_SIZE / 4 },
    { x: TABLE_SIZE / 4, y: (TABLE_SIZE * 3) / 4 },
    { x: (TABLE_SIZE * 3) / 4, y: (TABLE_SIZE * 3) / 4 },
];
const variant = ref(props.deploymentVariant ?? 0);
watch(
    () => props.deploymentVariant,
    (next) => {
        if (next !== undefined) variant.value = next;
    },
);
function chooseVariant(next: number) {
    variant.value = next;
    if (!props.readOnly) emit('variantChanged', next);
}
/** The table surface and terrain textures. A texture is drawn under the height tint, so the levels still read. */
const TEXTURE_TILE = 6;
const tableKey = ref<string | null>(props.tableImage ?? null);
watch(
    () => props.tableImage,
    (next) => {
        if (next !== undefined) tableKey.value = next;
    },
);
const DEFAULT_TABLE = 'grass';
const tableChoice = computed(
    () => props.tableImages?.find((t) => t.key === tableKey.value) ?? props.tableImages?.find((t) => t.key === DEFAULT_TABLE) ?? null,
);
/** Put a table on the board: its pieces, and the surface it was set up on when that surface is still on offer (undoable like any terrain change). */
function loadTable(t: { name: string; terrain: TerrainPiece[]; tableImage: string | null }) {
    commitTerrain(t.terrain);
    const key = t.tableImage;
    if (key && props.tableImages?.some((x) => x.key === key)) chooseTable(key);
    note.value = `Loaded "${t.name}".`;
}
function chooseTable(key: string) {
    tableKey.value = key;
    if (!props.readOnly) emit('tableImageChanged', key);
}
/** The textures in use on the table, one pattern each. A kind without a texture is drawn plain. */
const textures = computed(() => {
    const kinds = new Set(terrain.value.map((p) => p.kind).filter((k): k is string => !!k && !!props.terrainImages?.[k]));
    return [...kinds].map((kind) => ({ kind, url: props.terrainImages![kind] }));
});
const textureOf = (piece: TerrainPiece) => (piece.kind && props.terrainImages?.[piece.kind] ? `url(#tex-${piece.kind})` : null);
const note = ref('');
const view = ref({ x: 0, y: 0, w: TABLE_SIZE });
const svg = ref<SVGSVGElement | null>(null);
const newSide = ref<'a' | 'b'>('a');

/** Where the model being moved started, and where it is now: a dashed line with the movement it has cost, kept after the drop until the next touch. */
const trail = ref<{
    id: string;
    from: Point;
    to: Point;
    baseMm: number;
    startElevation: number;
    sp?: number;
    /** The Sp was halved because the model started engaged (CRG, Engagement). */
    halved?: boolean;
    /** A Charge cannot be declared while engaged. */
    chargeBlocked?: boolean;
    /** How it is moving: a Walk, a Walk that ignores terrain (Flight), or a place within `range` (Leap) that is not movement at all. */
    kind: MoveKind;
    /** The building it started inside, if any (a place cannot cross a wall). */
    inside: string | null;
    range: number;
    whole: boolean;
    /** The points the move has passed through so far (the start, then where each earlier leg ended); `to` is where the model is now. `spent` is what the move had cost on reaching that point. */
    path: { x: number; y: number; elev: number; spent?: number }[];
    /** Whether the model began the move beneath a bridge (so Cancel can put it back as it was). */
    startUnder?: boolean;
} | null>(null);
type MoveKind = 'walk' | 'charge' | 'jump' | 'fly' | 'place';
/** Walking spends movement and is stopped by terrain; flying walks but ignores terrain's height; placing picks the model up and puts it down within a range (p.17). */
const moveKind = ref<MoveKind>('walk');
const placeRange = ref(6);
const MOVE_KINDS: { value: MoveKind; label: string; hint: string }[] = [
    { value: 'walk', label: 'Walk', hint: 'Spends movement and is stopped by terrain' },
    {
        value: 'charge',
        label: 'Charge',
        hint: 'Once per activation, not while engaged: a straight line up to its Sp, then a melee action',
    },
    { value: 'jump', label: 'Jump', hint: 'Jump a gap: the same height or up to 1" lower, over nothing taller than where it began' },
    { value: 'fly', label: 'Flight', hint: 'A Walk that ignores terrain height' },
    { value: 'place', label: 'Place', hint: 'Puts the model down within a range, with no movement (Leap)' },
];
/** Whether the whole base must end within the range, rather than a single point of it. */
const placeWhole = ref(false);
/** Step move: grabbing the same model again carries on from where it was dropped, so legs add up (2" one way, then 5" another is 7"). */
const stepMove = ref(false);
/** What the move so far costs, and how that compares with a Walk (Sp) when the card says. */
const trailCost = computed(() => {
    const t = trail.value;
    const token = t ? tokens.value.find((x) => x.id === t.id) : undefined;
    if (!t || !token) return null;
    // a Charge is a Walk in a straight line: terrain costs the same
    const flat = t.kind !== 'walk' && t.kind !== 'charge';
    const raw = [...t.path, { x: t.to.x, y: t.to.y, elev: elevationOf(token) }].map((q) => (flat ? { ...q, elev: 0 } : q));
    // a leg over stairs or a ramp costs no climb and is no fall
    const points = flat ? raw : raw.map((q, i) => (i > 0 && overRamp(raw[i - 1], q, t.baseMm, worldTerrain.value) ? { ...q, ramp: true } : q));
    const flown = pathCost(points);
    // What a Walk meets on the way: severe terrain costs double, hazardous terrain is triggered, impassable terrain is in the way. A model that
    // ignores terrain (Flight) or is placed is not asked.
    const crossed =
        (t.kind === 'walk' || t.kind === 'charge') && !token.marker
            ? terrainAlongPath(points, t.baseMm, worldTerrain.value, token.ignores ?? [])
            : null;
    const cost = { ...flown, total: flown.total + (crossed?.severe ?? 0) };
    // A place is not movement, so it is never measured against a Walk.
    return { cost, crossed, walk: t.sp !== undefined && t.kind !== 'place' ? walkBudget(t.sp, cost) : null };
});
const trailPoints = computed<Point[]>(() => (trail.value ? [...trail.value.path.map((q) => ({ x: q.x, y: q.y })), { ...trail.value.to }] : []));
const trailLength = computed(() =>
    trailPoints.value.reduce((sum, q, i, all) => (i ? sum + Math.hypot(q.x - all[i - 1].x, q.y - all[i - 1].y) : 0), 0),
);
/** A small length on each leg once there is more than one. */
const trailLegLabels = computed(() => {
    const pts = trailPoints.value;
    if (pts.length < 3) return [];
    // A leg under an inch is too short to label without the text piling up on the model; the total still counts it.
    return pts
        .slice(1)
        .map((q, i) => ({
            x: (q.x + pts[i].x) / 2,
            y: (q.y + pts[i].y) / 2 - 0.35,
            length: Math.hypot(q.x - pts[i].x, q.y - pts[i].y),
        }))
        .filter((l) => l.length >= 1);
});
/** Line of sight: press on a model and drag to another. Each model draws its own sight lines, so both directions are checked. */
const sight = ref<{ fromId: string; toId: string | null; aim: Point | null } | null>(null);
/**
 * Sight guidelines left switched on for one model (from its panel): the side lines stay on the board, follow the model as it moves, and
 * are aimed by dragging in Sight mode. Without it they only show while a finger is down in Sight mode.
 */
const guidedId = ref<string | null>(null);
/** Where the lines first point: the nearest model on the other side, or else across the table. */
function defaultAim(t: Token): Point {
    const foes = tokens.value.filter((o) => !o.marker && !o.killed && o.id !== t.id && o.side !== t.side && o.side !== 'n');
    const near = foes.sort((a, b) => Math.hypot(a.x - t.x, a.y - t.y) - Math.hypot(b.x - t.x, b.y - t.y))[0];
    if (near) return { x: near.x, y: near.y };
    return { x: TABLE_SIZE / 2, y: t.y < TABLE_SIZE / 2 ? t.y + 8 : t.y - 8 };
}
/** Switch the guidelines off. */
function endGuide() {
    guidedId.value = null;
    sight.value = null;
    if (mode.value === 'sight') mode.value = 'move';
}
function toggleGuide(t: Token) {
    if (guidedId.value === t.id) {
        endGuide();
        return;
    }
    guidedId.value = t.id;
    const aim = defaultAim(t);
    sight.value = { fromId: t.id, toId: null, aim };
    mode.value = 'sight';
}
watch(
    () => tokens.value.some((t) => t.id === guidedId.value),
    (still) => {
        if (!still && guidedId.value) {
            guidedId.value = null;
            sight.value = null;
            if (mode.value === 'sight') mode.value = 'move';
        }
    },
);
const sightFrom = computed(() => tokens.value.find((t) => t.id === sight.value?.fromId));
const sightTo = computed(() => tokens.value.find((t) => t.id === sight.value?.toId));
const sightForward = computed(() =>
    sightFrom.value && sightTo.value ? lineOfSight(sightFrom.value, sightTo.value, tokens.value, worldTerrain.value) : null,
);
const sightBack = computed(() =>
    sightFrom.value && sightTo.value ? lineOfSight(sightTo.value, sightFrom.value, tokens.value, worldTerrain.value) : null,
);
const unknownSz = computed(() => [sightFrom.value, sightTo.value].some((t) => t && !t.marker && t.sz === undefined));
function describeSight(r: ReturnType<typeof lineOfSight>, who: string, whom: string): string {
    if (!r.clear) return `${who} cannot see ${whom}: blocked by ${r.blockedBy}.`;
    const extras = [
        r.cover === 'height' ? 'cover from height' : r.cover === 'terrain' ? 'cover from terrain' : null,
        r.concealed ? 'concealment' : null,
    ].filter(Boolean);
    return `${who} can see ${whom}${extras.length ? ' (' + extras.join(', ') + ')' : ''}.`;
}
/**
 * While aiming, one line leaves each side of the model's base straight in the direction of the aim: your finger, or the centre of the model
 * or marker it is over. Each is traced through the sight rules, so it shows what it reaches and where terrain or an enemy stops it.
 */
const sideLines = computed(() => {
    const from = sightFrom.value;
    const aim = sight.value?.aim;
    const lines = from && aim ? sideSightLines(from, aim) : null;
    if (!from || !lines) return [];
    return lines.map((l) => {
        const trace = traceLine(from, l.start, l.end, tokens.value, worldTerrain.value);
        const reached = trace.models.flatMap((m) => {
            const token = tokens.value.find((t) => t.id === m.id);
            return token ? [{ ...m, token }] : [];
        });
        return { ...l, trace, reached };
    });
});
/**
 * Whether the guidelines are aimed at something outside the part of the table in view, and if so an arrow at the edge of the view that points
 * toward it. Without one the lines just run off the screen and it looks as if nothing is there.
 */
const aimOffscreen = computed(() => {
    const aim = sight.value?.aim;
    if (!guidedId.value || !aim || !sideLines.value.length) return null;
    const v = view.value;
    const inside = aim.x >= v.x && aim.x <= v.x + v.w && aim.y >= v.y && aim.y <= v.y + v.w;
    if (inside) return null;
    // from the middle of the view toward the aim, stopped a little inside the edge
    const cx = v.x + v.w / 2;
    const cy = v.y + v.w / 2;
    const dx = aim.x - cx;
    const dy = aim.y - cy;
    const inset = pxToUnits(22);
    const half = v.w / 2 - inset;
    const k = half / Math.max(Math.abs(dx), Math.abs(dy));
    return { x: cx + dx * k, y: cy + dy * k, angle: (Math.atan2(dy, dx) * 180) / Math.PI, size: pxToUnits(11) };
});
/** Zoom and move the view so the model with the guidelines and the point they aim at are both in it. */
function showAim() {
    const from = sightFrom.value;
    const aim = sight.value?.aim;
    if (!from || !aim) return;
    const span = Math.max(Math.abs(aim.x - from.x), Math.abs(aim.y - from.y)) + 8;
    const w = Math.min(TABLE_SIZE, Math.max(MIN_WIDTH, span));
    view.value = { x: (from.x + aim.x) / 2 - w / 2, y: (from.y + aim.y) / 2 - w / 2, w };
    keepInside();
}
function describeSide(l: (typeof sideLines.value)[number]): string {
    const parts = l.reached.length
        ? 'Reaches ' + l.reached.map((m) => (m.blockedBy ? `${m.name} (hidden by ${m.blockedBy})` : `${m.name} (in sight)`)).join(', ') + '.'
        : 'Reaches no model.';
    const stop = l.trace.stopAt
        ? ` Anything past ${l.trace.blockedBy} is hidden, ${fmt(Math.hypot(l.trace.stopAt.x - l.start.x, l.trace.stopAt.y - l.start.y))}" along.`
        : '';
    return parts + stop;
}
const ruler = ref<{ from: Point; to: Point; fromToken: string | null; toToken: string | null } | null>(null);

const levels = computed(() => [0, ...new Set(terrain.value.flatMap((p) => p.surfaces.map((s) => s.top)))].sort((a, b) => a - b));
const supports = computed(() => new Map(tokens.value.map((t) => [t.id, restingSupport(t, terrain.value)])));
const elevationOf = (t: Token) => supports.value.get(t.id)?.elevation ?? 0;
/**
 * Who is engaged with whom: within 1" of an enemy, edge to edge, unless the lower model's effective Sz is below the higher model's
 * elevation. An engaged model halves its Walk and cannot declare missile actions, Interact or Charge. Shown on the base and in its options.
 */
const engagement = computed(() => {
    const out = new Map<string, Token[]>();
    for (const t of tokens.value) {
        if (t.marker) continue;
        const foes = engagedWith(t, tokens.value, elevationOf);
        if (foes.length) out.set(t.id, foes);
    }
    return out;
});
// Auras: a circle of `range` inches that starts at the outside edge of a model's base. A model casts up to three. A locked aura stays at
// the spot where it was locked while the model moves on. A view aid kept on this device.
const AURA_MIN = 0.5;
const AURA_MAX = 36;
const AURA_PRESETS = [1, 2, 3, 6];
const AURA_DASHES = ['0.5 0.35', '0.15 0.3', '1 0.3'];
/** Auras private to this browser. Shared ones come from the game (props.sharedAuras) and are changed through `auraChange`. */
const privateAuras = ref<Aura[]>([]);
const auras = computed<Aura[]>(() => [...privateAuras.value, ...(props.sharedAuras ?? []).map((a) => ({ ...a, shared: true }))]);
const auraKey = computed(() => (props.storageKey ? `tabletop-auras-${props.storageKey}` : null));
onMounted(() => {
    if (!auraKey.value) return;
    try {
        const saved = JSON.parse(localStorage.getItem(auraKey.value) ?? 'null');
        if (Array.isArray(saved)) privateAuras.value = saved;
        // first version: one aura per model, saved as { modelId: range }
        else if (saved && typeof saved === 'object') {
            privateAuras.value = Object.entries(saved).map(([ownerId, range], i) => ({
                id: `legacy${i}`,
                ownerId,
                range: Number(range),
                locked: false,
            }));
        }
    } catch {
        // storage unavailable: auras simply start empty
    }
});
watch(
    privateAuras,
    () => {
        if (!auraKey.value) return;
        try {
            localStorage.setItem(auraKey.value, JSON.stringify(privateAuras.value));
        } catch {
            // ignore
        }
    },
    { deep: true },
);
const clampAura = (n: number) => Math.min(AURA_MAX, Math.max(AURA_MIN, Math.round(n * 2) / 2));
const aurasOf = (ownerId: string) => auras.value.filter((a) => a.ownerId === ownerId);
const ownerOf = (a: Aura) => tokens.value.find((t) => t.id === a.ownerId);
/** You change a private aura freely; a shared one only when you control the model or marker that casts it. */
const canEdit = (a: Aura) => !a.shared || !ownerOf(a)?.locked;
const sharingOn = computed(() => props.sharedAuras !== undefined);
function patchAura(id: string, patch: Partial<Aura>) {
    const shared = props.sharedAuras?.find((a) => a.id === id);
    if (shared) {
        if (canEdit({ ...shared, shared: true })) emit('auraChange', { upsert: [{ ...shared, ...patch }] });
        return;
    }
    privateAuras.value = privateAuras.value.map((a) => (a.id === id ? { ...a, ...patch } : a));
}
const setRange = (id: string, range: number) => patchAura(id, { range: clampAura(range) });
function addAura(owner: Token) {
    if (aurasOf(owner.id).length >= AURAS_PER_MODEL) return;
    privateAuras.value = [
        ...privateAuras.value,
        { id: `a${Date.now().toString(36)}${auras.value.length}`, ownerId: owner.id, range: 6, locked: false },
    ];
}
/** Switch one of the terrain traits an aura counts as on or off. */
function toggleAuraTrait(aura: Aura, trait: string) {
    const now = aura.traits ?? [];
    const next = now.includes(trait) ? now.filter((x) => x !== trait) : [...now, trait];
    patchAura(aura.id, { traits: next.length ? next : undefined });
}
function toggleLock(aura: Aura, owner: Token) {
    patchAura(aura.id, aura.locked ? { locked: false, x: undefined, y: undefined } : { locked: true, x: owner.x, y: owner.y });
}
/** Show an aura to the other player (it is saved with the game), or take it back to this browser. */
function toggleShare(aura: Aura) {
    if (!canEdit(aura)) return;
    const { shared, ...plain } = aura;
    if (shared) {
        privateAuras.value = [...privateAuras.value, plain];
        emit('auraChange', { remove: [aura.id] });
    } else {
        privateAuras.value = privateAuras.value.filter((a) => a.id !== aura.id);
        emit('auraChange', { upsert: [plain] });
    }
}
function removeAura(id: string) {
    if (props.sharedAuras?.some((a) => a.id === id)) {
        if (canEdit({ ...props.sharedAuras.find((a) => a.id === id)!, shared: true })) emit('auraChange', { remove: [id] });
        return;
    }
    privateAuras.value = privateAuras.value.filter((a) => a.id !== id);
}
/** Auras drawn on the board: each with its owner, centre, radius and the models inside it. An aura whose model is gone is dropped. */
const activeAuras = computed(() =>
    auras.value.flatMap((aura) => {
        const owner = ownerOf(aura);
        if (!owner) return [];
        const origin = auraOrigin(aura, owner);
        const nth = aurasOf(owner.id).indexOf(aura);
        return [{ aura, owner, origin, nth, radius: auraRadius(owner, aura.range), inside: modelsWithin(aura, owner, tokens.value) }];
    }),
);
/** What sight, walks and placement see: the terrain, and any aura that counts as terrain. */
const worldTerrain = computed(() => {
    const extra = auraTerrain(activeAuras.value);
    return extra.length ? [...terrain.value, ...extra] : terrain.value;
});
const insideOf = (id: string) => activeAuras.value.find((a) => a.aura.id === id)?.inside ?? [];
const caught = computed(() => new Set(activeAuras.value.flatMap((a) => a.inside.map((t) => t.id))));
/** Models standing where they should not: on another base, or on impassable terrain. */
/** The ground nobody can walk to, from the real terrain (worked out once the terrain stops changing). */
const reach = shallowRef<UnreachableAreas | null>(unreachableAreas(terrain.value));
let reachTimer: ReturnType<typeof setTimeout> | null = null;
// a flood fill of the whole table: wait until the terrain stops changing (a piece being dragged changes it every frame)
watch(terrain, () => {
    if (reachTimer) clearTimeout(reachTimer);
    reachTimer = setTimeout(() => (reach.value = unreachableAreas(terrain.value)), 150);
});
const problems = computed(() => new Map(tokens.value.map((t) => [t.id, placementProblems(t, tokens.value, worldTerrain.value, reach.value)])));
// Status chips on a base: tokens grouped by name, a short label with a count, at most three chips (the rest fold into "+n"). Cached per info
// object, which keeps its identity while a model is dragged, so a drag frame does not rebuild them.
type Chip = { label: string; colour: string };
const badgeCache = new WeakMap<object, { chips: Chip[] }>();
const NO_BADGES = { chips: [] as Chip[] };
function badgesOf(info?: ModelInfo): { chips: Chip[] } {
    if (!info?.tokens.length) return NO_BADGES;
    const hit = badgeCache.get(info);
    if (hit) return hit;
    const counts = new Map<string, number>();
    for (const t of info.tokens) counts.set(t.name, (counts.get(t.name) ?? 0) + 1);
    const chips = [...counts.entries()].map(([name, n]) => {
        let h = 0;
        for (const ch of name) h = (h * 31 + ch.charCodeAt(0)) % 360;
        return { label: `${name.slice(0, 2)}${n > 1 ? n : ''}`, colour: `hsl(${h} 55% 38%)` };
    });
    const shown = chips.slice(0, 3);
    if (chips.length > 3) shown[2] = { label: `+${chips.length - 2}`, colour: 'hsl(220 10% 40%)' };
    const out = { chips: shown };
    badgeCache.set(info, out);
    return out;
}
const selected = computed(() => tokens.value.find((t) => t.id === selectedId.value) ?? null);

// The crew list: one play-screen row per living model, the tabletop options (the selected panel) opening under the chosen one.
const openCards = ref(new Set<number>());
const slotEls = shallowReactive<Record<string, HTMLElement>>({});
const setSlot = (id: number, el: unknown) => {
    if (el instanceof HTMLElement) slotEls[String(id)] = el;
    else delete slotEls[String(id)];
};
/** A game passes its crew; the sandbox's models are listed the same way, from what they kept of their card. */
const crewRows = computed<CrewMember[]>(
    () =>
        props.crew ??
        (props.sandbox
            ? tokens.value
                  .filter((t) => !t.marker)
                  .map((t) => ({
                      id: t.id as unknown as number,
                      slot: t.side === 'b' ? 2 : 1,
                      side: t.side,
                      is_killed: !!t.killed,
                      display_name: t.name,
                      faction: t.card?.faction,
                      defense: t.card?.defense,
                      willpower: t.card?.willpower,
                      speed: t.sp,
                      size: t.sz,
                      front_image: t.card?.front_image ?? null,
                      back_image: t.card?.back_image ?? null,
                      characteristics: [],
                      attached_upgrades: [],
                      attached_tokens: t.info?.tokens ?? [],
                      current_health: t.info?.health ?? null,
                      max_health: t.info?.maxHealth ?? null,
                      is_activated: !!t.info?.activated,
                      notes: t.info?.notes ?? null,
                  }))
            : []),
);
const crewSides = computed(() =>
    [1, 2]
        .map((slot) => ({
            slot,
            name: slot === 1 ? (props.sideNames?.a ?? 'Player 1') : (props.sideNames?.b ?? 'Player 2'),
            living: crewRows.value.filter((m) => m.slot === slot && !m.is_killed),
            killed: crewRows.value.filter((m) => m.slot === slot && m.is_killed),
        }))
        .filter((side) => side.living.length || side.killed.length),
);
const canEditCrew = (m: CrewMember) => !props.readOnly && !tokens.value.find((t) => t.id === String(m.id))?.locked;
const panelTarget = computed(() => (crewRows.value.length && selected.value && !selected.value.marker ? (slotEls[selected.value.id] ?? null) : null));
const toggleSelect = (m: CrewMember) => (selectedId.value = selectedId.value === String(m.id) ? null : String(m.id));
const toggleCard = (m: CrewMember) => {
    const next = new Set(openCards.value);
    if (!next.delete(m.id)) next.add(m.id);
    openCards.value = next;
};
const crewHealth = (m: CrewMember, health: number) =>
    emit('modelChange', String(m.id), { health: Math.min(m.max_health ?? health, Math.max(0, health)) });
const crewPip = (m: CrewMember, pip: number) => crewHealth(m, (m.current_health ?? 0) === pip ? pip - 1 : pip);
/** A game replaces through its page; the sandbox opens the character search and swaps the model here. */
const replacing = ref<string | null>(null);
const replaceFrom = (m: CrewMember) => {
    if (!props.sandbox) return emit('replaceModel', String(m.id));
    replacing.value = String(m.id);
    openCharacterDialog();
};
// The tracker's token dialog, for a game and for the sandbox alike: the change goes out as a patch and the page saves it.
const tokenDialog = ref(false);
const tokenMemberId = ref<string | null>(null);
const tokenSearch = ref('');
const tokenMember = computed(
    () =>
        (crewRows.value.find((m) => String(m.id) === tokenMemberId.value) ?? null) as {
            id: number;
            display_name: string;
            attached_tokens: { id: number; name: string }[];
        } | null,
);
const tokenCatalogue = computed(() => (props.tokenOptions ?? []).map((o) => ({ ...o, slug: String(o.id), description: null })));
const noReferenceTokens = new Set<number>();
function openTokens(m: CrewMember) {
    tokenMemberId.value = String(m.id);
    tokenSearch.value = '';
    tokenDialog.value = true;
}
function toggleModelToken(id: number, name: string) {
    const m = tokenMember.value;
    if (!m) return;
    const has = m.attached_tokens.some((t: { id: number }) => t.id === id);
    emit('modelChange', String(m.id), {
        tokens: has ? m.attached_tokens.filter((t: { id: number }) => t.id !== id) : [...m.attached_tokens, { id, name }],
    });
}
const removeModelToken = (id: number) => {
    const m = tokenMember.value;
    if (m) emit('modelChange', String(m.id), { tokens: m.attached_tokens.filter((t: { id: number }) => t.id !== id) });
};
// The name opens the tracker's card drawer (image, notes); a model with no card just selects.
const cardMemberId = ref<string | null>(null);
const cardDrawer = ref(false);
const cardMember = computed(() => crewRows.value.find((m) => String(m.id) === cardMemberId.value) ?? null);
const fullscreen = ref<{ src: string; backSrc: string | null; title: string | null } | null>(null);
const hasCard = (m: CrewMember) =>
    !!(m.front_image || m.custom_character?.front_image || m.custom_character?.actions?.length || m.custom_character?.abilities?.length);
function openCard(m: CrewMember) {
    if (!hasCard(m)) return toggleSelect(m);
    cardMemberId.value = String(m.id);
    cardDrawer.value = true;
}
const cardNotes = (payload: { notes: string | null }) => {
    if (cardMember.value) emit('modelChange', String(cardMember.value.id), { notes: payload.notes ?? '' });
};
const noUpgradePower = () => 0;
/** The model whose row the pointer is on: a ring on its base, so a row can be found on the table. */
const hoverId = ref<string | null>(null);
/**
 * Bring a row into view in the list it is in, by scrolling that list only. Scrolling the page (scrollIntoView does) moves the board
 * under the player's hand every time a model is chosen; a list that does not scroll on its own is left alone.
 */
function scrollWithinList(row: Element | null) {
    for (let box = row?.parentElement ?? null; row && box && box !== document.body; box = box.parentElement) {
        const overflow = getComputedStyle(box).overflowY;
        if ((overflow === 'auto' || overflow === 'scroll') && box.scrollHeight > box.clientHeight) {
            const r = row.getBoundingClientRect();
            const b = box.getBoundingClientRect();
            if (r.top < b.top) box.scrollTop -= b.top - r.top;
            else if (r.bottom > b.bottom) box.scrollTop += r.bottom - b.bottom;
            return;
        }
    }
}
// on a wide screen the table stays put, so choosing a model on it brings its row into view in the list
watch(selectedId, (id) => {
    if (!id || !crewRows.value.length || typeof window === 'undefined' || !window.matchMedia('(min-width: 1024px)').matches) return;
    void nextTick(() => scrollWithinList(document.querySelector(`[data-testid="crew-${id}"]`)));
});

// ----- a scheme's model or marker, picked on the board (the notes are private to their player) -----
const schemeSlot = (t: Token): number | null => {
    if (!props.schemeNotes || (t.side !== 'a' && t.side !== 'b')) return null;
    const slot = t.side === 'a' ? 1 : 2;
    return props.schemeNotes[slot] === undefined ? null : slot;
};
const isSchemeTarget = (t: Token, slot: number) =>
    (t.marker ? props.schemeNotes?.[slot]?.selected_marker : props.schemeNotes?.[slot]?.selected_model) === t.name;
function toggleSchemeTarget(t: Token, slot: number) {
    emit('schemeTarget', t.marker ? 'marker' : 'model', slot, isSchemeTarget(t, slot) ? null : t.name);
}

// ----- a marker's neighbours: strategies and schemes count models near a marker, and peons are ignored for them (CRG, Peons) -----
const markerRange = ref(3);
const markerNeighbours = computed(() => {
    const m = selected.value;
    if (!m?.marker) return null;
    const near = tokens.value.filter((t) => !t.marker && !t.killed && !t.peon && t.side !== 'n' && edgeDistance(m, t) <= markerRange.value + 1e-9);
    return { a: near.filter((t) => t.side === 'a'), b: near.filter((t) => t.side === 'b') };
});
const unplacedIds = computed(() => new Set(tokens.value.filter((t) => t.unplaced && !t.killed).map((t) => t.id)));

// ----- Remains (CRG, Markers and killed models): a killed model that is not a peon makes a friendly 30mm Remains marker within 1" -----
const killedSeen = new Set(tokens.value.filter((t) => t.killed).map((t) => t.id));
const remainsFor = ref<{ id: string; name: string; side: 'a' | 'b'; x: number; y: number; baseMm: number }[]>([]);
watch(
    () => tokens.value.filter((t) => t.killed && !t.marker).map((t) => t.id),
    (now) => {
        const nowSet = new Set(now);
        for (const id of [...killedSeen]) if (!nowSet.has(id)) killedSeen.delete(id); // revived: a second death asks again
        for (const id of now) {
            if (killedSeen.has(id)) continue;
            killedSeen.add(id);
            const t = tokens.value.find((x) => x.id === id);
            // you make the marker for your own crew; peons make none
            if (t && !t.locked && !t.peon && (t.side === 'a' || t.side === 'b') && !props.readOnly)
                remainsFor.value = [...remainsFor.value, { id, name: t.name, side: t.side, x: t.x, y: t.y, baseMm: t.baseMm }];
        }
    },
);
const remainsMarker = computed(() => props.markers?.find((m) => /^remains$/i.test(m.name)) ?? props.markers?.find((m) => /remains/i.test(m.name)));
const remainsArmed = ref<string | null>(null);
const skipRemains = (id: string) => {
    remainsFor.value = remainsFor.value.filter((r) => r.id !== id);
    if (remainsArmed.value === id) remainsArmed.value = null;
};
function placeRemains(r: { id: string; name: string; side: 'a' | 'b'; x: number; y: number; baseMm: number }, tap?: Point) {
    const marker = remainsMarker.value;
    if (!marker) {
        note.value = 'There is no Remains marker in the markers list.';
        return skipRemains(r.id);
    }
    if ((markersUsed.value[marker.id] ?? 0) >= MARKERS_PER_KIND) {
        note.value = `There are already ${MARKERS_PER_KIND} Remains markers on the table.`;
        return skipRemains(r.id);
    }
    // anywhere within 1" of the killed model: if no legal spot is that close, the marker is not made
    const probe: Token = {
        id: 'remains-probe',
        name: marker.name,
        side: r.side,
        marker: true,
        markerId: marker.id,
        baseMm: marker.base_mm,
        x: r.x,
        y: r.y,
    };
    if (tap) {
        // the controller chose: it must be within 1" of the killed model, and a place a marker may stand. The model is still on the table
        // (the marker is made before it is removed, CRG p.41), so a tap on its base puts the marker beside it, touching, not under it.
        let chosen = constrainWithin(tap, r, marker.base_mm, 1);
        if (Math.hypot(chosen.x - r.x, chosen.y - r.y) < baseRadius(r.baseMm) + baseRadius(marker.base_mm))
            chosen = constrainToContact(chosen, r, marker.base_mm);
        const why = whyNotHere({ ...probe, ...chosen });
        if (why) {
            note.value = `${why} Tap another place within 1" of ${r.name}.`;
            return;
        }
        skipRemains(r.id);
        emit('addMarker', { markerId: marker.id, name: marker.name, baseMm: marker.base_mm, side: r.side, x: chosen.x, y: chosen.y });
        note.value = `A Remains marker was made within 1" of ${r.name}.`;
        return;
    }
    // For the player: touching the killed model's base (it is still on the table, so the marker cannot go under it), from the side facing the
    // middle of the table; if every touching spot is unusable, the nearest legal place within 1".
    const centre = { x: TABLE_SIZE / 2, y: TABLE_SIZE / 2 };
    const touching = contactSpots(r, marker.base_mm, centre).find((c) => !whyNotHere({ ...probe, ...c }));
    const spot = touching ?? legalSpotNear(probe, 1 + baseRadius(r.baseMm) + baseRadius(marker.base_mm));
    skipRemains(r.id);
    if (!spot) {
        note.value = `${r.name}'s Remains marker could not be made within 1", so it is not made.`;
        return;
    }
    emit('addMarker', { markerId: marker.id, name: marker.name, baseMm: marker.base_mm, side: r.side, x: spot.x, y: spot.y });
    note.value = `A Remains marker was made ${touching ? 'touching' : 'beside'} ${r.name}.`;
}

// ----- Fate flips made for a model: the result is drawn beside it, and the model's options can flip -----
const SUIT_FILL: Record<string, string> = { rams: 'hsl(0 70% 42%)', masks: 'hsl(280 55% 42%)', tomes: 'hsl(215 70% 42%)', crows: 'hsl(140 55% 28%)' };
const fateSubjects = computed(() => {
    const out = new Map<string, { flip: FateFlip; cards: { card: number; joker_suit: string | null }[] }>();
    const state = props.fate?.state.value;
    if (!state) return out;
    for (const flip of state.flips) {
        if (flip.subject) out.set(flip.subject, { flip, cards: state.conflict.filter((c) => c.flip_id === flip.id) });
    }
    return out;
});
/** What to draw beside a model that has a flip open: the result once there is one, the one card shown, or how many are waiting. */
function flipBadge(id: string): { text: string; glyph: string; fill: string } | null {
    const entry = fateSubjects.value.get(id);
    if (!entry) return null;
    const pick = entry.flip.chosen ?? (entry.cards.length === 1 ? entry.cards[0] : null);
    if (!pick) return { text: `${entry.cards.length}?`, glyph: '', fill: 'hsl(220 10% 35%)' };
    const suit = cardSuit(pick.card) ?? pick.joker_suit;
    return {
        text: cardFace(pick.card),
        glyph: suit ? (GAME_ICONS[suit]?.glyph ?? '') : '',
        fill: suit ? (SUIT_FILL[suit] ?? 'hsl(220 10% 25%)') : 'hsl(220 10% 25%)',
    };
}
/** A colour for each kind of terrain chip, so the one that matters is seen at a glance: sight in purple, cover in green, danger in red or amber. */
const CHIP_TONE: Record<string, string> = {
    concealing: 'border-purple-500/50 bg-purple-500/15 text-purple-900 dark:text-purple-200',
    'cover-terrain': 'border-emerald-500/50 bg-emerald-500/15 text-emerald-900 dark:text-emerald-200',
    hazardous: 'border-red-500/50 bg-red-500/15 text-red-900 dark:text-red-200',
    severe: 'border-amber-500/50 bg-amber-500/15 text-amber-900 dark:text-amber-200',
    dense: 'border-slate-500/50 bg-slate-500/15 text-slate-900 dark:text-slate-200',
    high: 'border-sky-500/50 bg-sky-500/15 text-sky-900 dark:text-sky-200',
};
const fateMod = ref(0);
const fateSuit = ref('rams');
const slotOfToken = (t: { side: string }) => (t.side === 'b' ? 2 : 1);
const modelFlip = computed(() => (selected.value ? fateSubjects.value.get(selected.value.id) : undefined));
async function flipForModel(t: Token) {
    if (!props.fate) return;
    await props.fate.start?.();
    const r = await props.fate.act('flip', { slot: slotOfToken(t), modifier: fateMod.value, label: t.name, subject: t.id });
    if (!r.error) {
        fateMod.value = 0;
        // the flip's cards, and the hand to cheat from, are on the Fate mat: bring it into view if it is open but off screen (nothing moves if it is already visible)
        void nextTick(() => document.querySelector('[data-testid=fate-conflict-zone]')?.scrollIntoView({ block: 'nearest', behavior: 'smooth' }));
    }
}
const chooseForModel = (t: Token, flip: FateFlip, card: number) =>
    props.fate?.act('choose', { slot: slotOfToken(t), flip: flip.id, card, suit: card === RED_JOKER ? fateSuit.value : undefined });
const resolveForModel = (t: Token, flip: FateFlip) => props.fate?.act('resolve', { slot: slotOfToken(t), flip: flip.id });
const noLootCard = () => null;
const viewBox = computed(() => `${view.value.x} ${view.value.y} ${view.value.w} ${view.value.w}`);
const zones = computed(() => (deployment.value === 'none' ? null : deploymentZones(deployment.value, variant.value)));
/** The four ways to place the chosen deployment, named by where each side ends up. */
const variants = computed(() =>
    deployment.value === 'none'
        ? []
        : [0, 1, 2, 3].map((v) => {
              const z = deploymentZones(deployment.value as DeploymentKind, v);
              return { value: v, label: `${props.sideNames.a} ${describeZone(z.a)}, ${props.sideNames.b} ${describeZone(z.b)}` };
          }),
);

const RED_SIDE = 'hsl(5 70% 48%)';
const BLUE_SIDE = 'hsl(215 70% 50%)';
const sideColour = (side: 'a' | 'b' | 'n') => {
    if (side === 'n') return 'hsl(280 45% 50%)';
    const role = props.sideRoles?.[side];
    if (role === 'attacker') return RED_SIDE;
    if (role === 'defender') return BLUE_SIDE;
    return side === 'a' ? BLUE_SIDE : RED_SIDE;
};
/** Two letters for a marker ("Scheme Marker" is "SM"), one for a model. */
/**
 * What is written on a base: the first letter of the model's name; two letters where several models start alike; and a number where they
 * are the same model (two Bayou Gremlins are B1 and B2), so every base on the table can be told from the list.
 */
const tokenLabels = computed(() => {
    const out = new Map<string, string>();
    const groups = new Map<string, Token[]>();
    for (const t of tokens.value) {
        if (t.marker) continue;
        const k = t.name.slice(0, 1).toUpperCase();
        groups.set(k, [...(groups.get(k) ?? []), t]);
    }
    for (const group of groups.values()) {
        if (group.length === 1) {
            out.set(group[0].id, group[0].name.slice(0, 1));
            continue;
        }
        const pairs = new Map<string, Token[]>();
        for (const t of group) pairs.set(t.name.slice(0, 2), [...(pairs.get(t.name.slice(0, 2)) ?? []), t]);
        for (const [two, same] of pairs) {
            if (same.length === 1) out.set(same[0].id, two);
            else same.forEach((t, i) => out.set(t.id, `${t.name.slice(0, 1)}${i + 1}`));
        }
    }
    return out;
});
/** Status badges are drawn smaller on a small base, so four of them do not bury a 30 mm model. */
/** Faction colours (the CSS variables the rest of the site tints with); a model whose faction is unknown keeps its side colour. */
const FACTION_VARS = ['arcanists', 'bayou', 'explorerssociety', 'guild', 'neverborn', 'outcasts', 'resurrectionists', 'tenthunders'];
const factionOf = (t: Token): string | null => {
    if (t.marker) return null;
    const raw = t.card?.faction ?? props.crew?.find((m) => String(m.id) === t.id)?.faction ?? null;
    const key = raw ? useFactionColor(String(raw).toLowerCase()) : null;
    return key && FACTION_VARS.includes(key) ? key : null;
};
/** The base shows its faction; which side it is on is the ring around its edge. */
const baseFill = (t: Token) => {
    const f = factionOf(t);
    return f ? `hsl(var(--${f}))` : sideColour(t.side);
};
const badgeR = (t: Token) => (t.baseMm <= 30 ? 0.27 : 0.34);
/** The current health as a number inside the base, on bases big enough to hold it beside the label (40 mm and up); the ring says it on the rest. */
const healthNumber = (t: Token): number | null => (!t.marker && t.info?.maxHealth && t.baseMm >= 40 ? (t.info.health ?? t.info.maxHealth) : null);
/** Up to this many wounds the health ring is one segment each; past it the ring is smooth. */
const HEALTH_PIPS_MAX = 12;
const healthRing = (t: Token) => ({ r: baseRadius(t.baseMm) - 0.11, width: 0.16 });
const healthFraction = (t: Token) => Math.max(0, Math.min(1, (t.info?.health ?? t.info?.maxHealth ?? 0) / (t.info?.maxHealth || 1)));
/**
 * The lit part of the smooth ring (past 12 wounds): an arc clockwise from the top. It is drawn as a path, not as a dashed circle: a dash
 * of "the whole ring" on a circle with a pathLength leaves a visible gap in Chrome, so a full-health model of 13 or more looked wounded.
 * Null when there is nothing to draw; "full" is a whole circle.
 */
function smoothHealth(t: Token): { full: boolean; d: string } | null {
    const f = healthFraction(t);
    if (f <= 0) return null;
    if (f >= 1) return { full: true, d: '' };
    const { r } = healthRing(t);
    const end = -Math.PI / 2 + f * Math.PI * 2;
    return {
        full: false,
        d: `M${t.x} ${t.y - r} A${r} ${r} 0 ${f > 0.5 ? 1 : 0} 1 ${t.x + r * Math.cos(end)} ${t.y + r * Math.sin(end)}`,
    };
}
const healthColour = (t: Token) => (healthFraction(t) <= 0.34 ? 'hsl(0 85% 60%)' : 'hsl(145 65% 50%)');
/** One arc per wound, clockwise from the top; the first ones are lit (the wounds left), the last are dark (the wounds taken). */
function healthArcs(t: Token): { d: string; on: boolean }[] {
    const max = t.info?.maxHealth ?? 0;
    const now = Math.max(0, Math.min(max, t.info?.health ?? max));
    const { r } = healthRing(t);
    const span = (Math.PI * 2) / max;
    const gap = Math.min(0.16, span * 0.3);
    return Array.from({ length: max }, (_, i) => {
        const a0 = -Math.PI / 2 + i * span + gap / 2;
        const a1 = -Math.PI / 2 + (i + 1) * span - gap / 2;
        const pt = (a: number) => `${t.x + r * Math.cos(a)} ${t.y + r * Math.sin(a)}`;
        return { d: `M${pt(a0)} A${r} ${r} 0 0 1 ${pt(a1)}`, on: i < now };
    });
}
const tokenLabel = (t: Token) =>
    t.marker
        ? t.name
              .split(/\s+/)
              .slice(0, 2)
              .map((w) => w.slice(0, 1))
              .join('')
              .toUpperCase()
        : (tokenLabels.value.get(t.id) ?? t.name.slice(0, 1));

const surfaceFill = (top: number) => `hsl(var(--primary) / ${Math.min(0.1 + top * 0.09, 0.6)})`;
/** Over a texture the height tint is lighter, so the picture still reads and the levels still tell apart. */
const texturedFill = (top: number) => `hsl(var(--primary) / ${Math.min(0.03 + top * 0.035, 0.25)})`;
const dimmed = (elevation: number) => level.value !== 'all' && elevation > level.value;

const labelAt = (shape: Shape): Point => {
    if (shape.kind === 'rect' && !shape.angle) return { x: shape.x + 0.4, y: shape.y + 0.9 };
    if (shape.kind === 'rect' || shape.kind === 'ellipse') {
        const [x, y] = shapeCentre(shape);
        return { x: x - 0.8, y: y + 0.3 };
    }
    if (shape.kind === 'circle') return { x: shape.cx, y: shape.cy + 0.3 };
    const xs = shape.points.map((p) => p[0]);
    const ys = shape.points.map((p) => p[1]);
    return { x: Math.min(...xs) + 1, y: Math.min(...ys) + 1.2 };
};

// --- pointer handling: one finger drags a token or pans, two fingers pinch, wheel zooms ---

/**
 * Is a press on one of the selected piece's handles? The rotate dot and the shape handles are found by distance, so they stay easy to
 * hit with a finger; pressing one starts reshaping or turning. A press on a mid-edge dot first puts a corner there.
 */
function gripAt(p: Point): boolean {
    const piece = selectedPiece.value;
    const surface = activeSurface.value;
    if (!piece || !surface || outline.value) return false;
    const reach = pxToUnits(16);
    const near = (h: Point) => Math.hypot(h.x - p.x, h.y - p.y) <= reach;
    const grip = rotateGrip.value;
    if (grip && near(grip)) {
        drag = {
            kind: 'handle',
            id: piece.id,
            surfaceId: surface.id,
            grip: 'rotate',
            original: piece,
            before: cloneTerrain(terrain.value),
            moved: false,
            centre: grip.centre,
            bearing: (Math.atan2(p.y - grip.centre.y, p.x - grip.centre.x) * 180) / Math.PI,
        };
        return true;
    }
    const rank = (h: ShapeHandle) => (h.kind === 'opening' ? 0 : h.kind === 'mid' ? 2 : 1);
    const hit = [...handles.value]
        .filter((h) => near(h))
        .sort((a, b) => rank(a) - rank(b) || Math.hypot(a.x - p.x, a.y - p.y) - Math.hypot(b.x - p.x, b.y - p.y))[0];
    if (!hit) return false;
    const before = cloneTerrain(terrain.value);
    if (hit.kind === 'mid') {
        const shape = insertCorner(surface.shape, hit.index);
        if (!shape) return false;
        const grown = { ...piece, surfaces: piece.surfaces.map((s) => (s.id === surface.id ? { ...s, shape } : s)) };
        terrain.value = terrain.value.map((x) => (x.id === piece.id ? grown : x));
        drag = {
            kind: 'handle',
            id: piece.id,
            surfaceId: surface.id,
            grip: { kind: 'vertex', index: hit.index + 1 },
            original: grown,
            before,
            moved: true,
        };
        return true;
    }
    drag = { kind: 'handle', id: piece.id, surfaceId: surface.id, grip: { kind: hit.kind, index: hit.index }, original: piece, before, moved: false };
    return true;
}

const pointers = new Map<number, Point>();
let drag:
    | { kind: 'token'; id: string; dx: number; dy: number; startElevation: number }
    | { kind: 'piece'; id: string; from: Point; original: TerrainPiece; before: TerrainPiece[]; moved: boolean }
    | {
          kind: 'handle';
          id: string;
          surfaceId: string;
          /** Which handle: a shape's own, or the rotate dot. */
          grip: Pick<ShapeHandle, 'kind' | 'index'> | 'rotate';
          original: TerrainPiece;
          before: TerrainPiece[];
          moved: boolean;
          /** Rotate only: the middle it turns about and the bearing of the finger when it went down. */
          centre?: Point;
          bearing?: number;
      }
    | {
          kind: 'pan';
          last: Point;
          /** A press on bare table that lets go of the chosen model, unless it turns out to be a pan. */ tap?: boolean;
          travel?: number;
      }
    | { kind: 'ruler' }
    | { kind: 'sight' }
    | null = null;
let pinch: { distance: number; width: number; centre: Point } | null = null;

function toBoard(clientX: number, clientY: number): Point {
    const rect = svg.value!.getBoundingClientRect();
    return {
        x: view.value.x + ((clientX - rect.left) / rect.width) * view.value.w,
        y: view.value.y + ((clientY - rect.top) / rect.height) * view.value.w,
    };
}

function tokenAt(p: Point): Token | null {
    // Later tokens draw on top, so test from the end; small bases get a finger-sized allowance.
    for (let i = tokens.value.length - 1; i >= 0; i--) {
        const t = tokens.value[i];
        if (Math.hypot(t.x - p.x, t.y - p.y) <= Math.max(baseRadius(t.baseMm), 0.9)) return t;
    }
    return null;
}

function setZoom(width: number, anchor: Point) {
    const w = Math.min(TABLE_SIZE, Math.max(MIN_WIDTH, width));
    const k = w / view.value.w;
    view.value = { x: anchor.x - (anchor.x - view.value.x) * k, y: anchor.y - (anchor.y - view.value.y) * k, w };
    keepInside();
}
function keepInside() {
    const w = view.value.w;
    view.value = { w, x: Math.min(TABLE_SIZE - w, Math.max(0, view.value.x)), y: Math.min(TABLE_SIZE - w, Math.max(0, view.value.y)) };
}
function zoomBy(factor: number) {
    setZoom(view.value.w * factor, { x: view.value.x + view.value.w / 2, y: view.value.y + view.value.w / 2 });
}
const resetView = () => (view.value = { x: 0, y: 0, w: TABLE_SIZE });

/**
 * Where this token can stand, as close to where it is as possible: where it is if that is already legal, else the nearest legal spot
 * within `maxRadius`. nearestLegalSpot answers null both when the place is already fine and when nothing is legal, so tell them apart here.
 */
function legalSpotNear(probe: Token, maxRadius?: number): { x: number; y: number } | null {
    const bad = placementProblems(probe, tokens.value, worldTerrain.value, reach.value).some(
        (p) => p.kind === 'overlap' || p.kind === 'impassable' || p.kind === 'unreachable',
    );
    return bad ? nearestLegalSpot(probe, tokens.value, worldTerrain.value, maxRadius, undefined, reach.value) : { x: probe.x, y: probe.y };
}

/** A model the game added that has no place yet: the next tap on empty table sets it down at the nearest legal spot. */
const placingId = ref<string | null>(null);

// ----- replaced models: the new model goes into base contact with where the original stood (CRG p.48) -----
type Anchor = { x: number; y: number; baseMm: number; name: string };
/** Sandbox replacements are noted here; a game's come from the page (`placeAnchors`). */
const localAnchors = ref<Record<string, Anchor>>({});
const anchors = computed<Record<string, Anchor>>(() => ({ ...props.placeAnchors, ...localAnchors.value }));
const anchorOf = (id: string): Anchor | undefined => (tokens.value.some((t) => t.id === id && !t.killed) ? anchors.value[id] : undefined);
function finishPlacing(token: Token, spot: Point | null, note_: string) {
    if (spot) {
        Object.assign(token, spot, { unplaced: false });
        trail.value = null;
        emit('moved', { ...token });
    }
    placingId.value = null;
    const rest = { ...localAnchors.value };
    delete rest[token.id];
    localAnchors.value = rest;
    setSummoner(token.id, '');
    emit('placed', token.id);
    note.value = note_;
}
/** What is wrong with standing this token at its (probe) position, in words, or null when it is fine. */
function whyNotHere(probe: Token): string | null {
    const clamped = clampToTable(probe);
    if (Math.hypot(clamped.x - probe.x, clamped.y - probe.y) > 1e-6) return 'That is off the table.';
    const bad = placementProblems(probe, tokens.value, worldTerrain.value, reach.value).find(
        (q) => q.kind === 'overlap' || q.kind === 'impassable' || q.kind === 'unreachable',
    );
    return bad ? `${probe.name} ${bad.text}.` : null;
}
/** Into base contact with the original: at the tapped side if that is legal; with no tap, the first legal spot, from the side facing the middle of the table. */
function placeInContact(token: Token, anchor: Anchor, tap?: Point) {
    const centre = { x: TABLE_SIZE / 2, y: TABLE_SIZE / 2 };
    const spots = tap ? [constrainToContact(tap, anchor, token.baseMm)] : contactSpots(anchor, token.baseMm, centre);
    let why: string | null = null;
    for (const spot of spots) {
        why = whyNotHere({ ...token, ...spot });
        if (!why) return finishPlacing(token, spot, `${token.name} placed in base contact with where ${anchor.name} stood.`);
    }
    note.value = tap
        ? `${why ?? 'No room there.'} Tap another side of where ${anchor.name} stood.`
        : `${token.name} cannot be placed in base contact with where ${anchor.name} stood, so it is killed (CRG p.48).`;
}
// ----- summoned models: placed within a distance of the model that summoned them -----
/** A summoned model's placement rule: within `range` inches of one of its summoner's models (what the summon effect says). Not saved: it lasts until the model is set down. */
const summonRule = ref<Record<string, { from: string; range: number }>>({});
const ruleOf = (id: string) => summonRule.value[id] ?? null;
const tokenById = (id: string | number) => tokens.value.find((t) => t.id === String(id));
/** The models that could have summoned this one: its own side's models that are on the table. */
const summonersFor = (t: Token) => tokens.value.filter((x) => !x.marker && !x.killed && !x.unplaced && x.id !== t.id && x.side === t.side);
function setSummoner(id: string, from: string) {
    const rest = { ...summonRule.value };
    if (from) rest[id] = { from, range: rest[id]?.range ?? 3 };
    else delete rest[id];
    summonRule.value = rest;
}
function setSummonRange(id: string, range: number) {
    const rule = summonRule.value[id];
    if (rule && Number.isFinite(range)) summonRule.value = { ...summonRule.value, [id]: { ...rule, range: Math.min(12, Math.max(0.5, range)) } };
}
/** Held to the distance: a tap farther away is pulled in to it, and a spot that is not legal is refused with the reason. */
function placeWithinRule(token: Token, rule: { from: string; range: number }, tap: Point) {
    const from = tokens.value.find((t) => t.id === rule.from);
    if (!from) {
        setSummoner(token.id, '');
        note.value = 'The model that summoned it is not on the table, so place it anywhere.';
        return;
    }
    const spot = constrainWithin(clampToTable({ baseMm: token.baseMm, x: tap.x, y: tap.y }), from, token.baseMm, rule.range);
    const why = whyNotHere({ ...token, ...spot });
    if (why) {
        note.value = `${why} Tap another spot within ${rule.range}" of ${from.name}.`;
        return;
    }
    finishPlacing(token, spot, `${token.name} placed within ${rule.range}" of ${from.name}.`);
}
/** Whether the chosen model can be marked killed (or brought back) from its options. */
const canKill = computed(() => !!selected.value && !selected.value.marker && !selected.value.locked && (crewRows.value.length > 0 || props.sandbox));
const keepWhereItIs = (token: Token) => finishPlacing(token, null, `${token.name} stays where it is.`);
function placeAt(token: Token, p: Point) {
    if (token.locked) return;
    const anchor = anchorOf(token.id);
    if (anchor) return placeInContact(token, anchor, p);
    const rule = ruleOf(token.id);
    if (rule) return placeWithinRule(token, rule, p);
    const at = clampToTable({ baseMm: token.baseMm, x: p.x, y: p.y });
    const spot = legalSpotNear({ ...token, x: at.x, y: at.y });
    if (!spot) {
        note.value = `There is no legal spot near there for ${token.name}.`;
        return;
    }
    Object.assign(token, spot, { unplaced: false });
    trail.value = null;
    placingId.value = null;
    emit('moved', { ...token });
    note.value = `${token.name} placed.`;
}
// say so when a model needs placing: the prompt sits with its row, and this reaches a screen reader
watch(
    () => Object.keys(anchors.value).filter((id) => tokens.value.some((t) => t.id === id)),
    (now, before) => {
        const fresh = now.find((id) => !before?.includes(id));
        const t = fresh ? tokens.value.find((x) => x.id === fresh) : null;
        if (t && fresh) note.value = `${t.name} replaced ${anchors.value[fresh].name}: place it in base contact with where it stood.`;
    },
);
// ----- the keyboard: the table can be used without a pointer -----
// [ and ] choose the previous or next model, the arrows move it (0.5"; Shift 0.1"; Alt 2"), Escape lets go. A key press drives the same
// pointer code as a drag, so a keyboard move gets the same rules, notes and saving.
/** The help dialog: opened from its button, from the terrain editor, or with the ? key. */
const helpOpen = ref(false);
const helpSection = ref('start');
function openHelp(section: string) {
    helpSection.value = section;
    helpOpen.value = true;
}
const KEY_POINTER = -7;
let keyTarget: Token | null = null;
let keyHeld = false;
let keyIdle: ReturnType<typeof setTimeout> | null = null;
const toClient = (p: Point) => {
    const rect = svg.value!.getBoundingClientRect();
    return {
        clientX: rect.left + ((p.x - view.value.x) / view.value.w) * rect.width,
        clientY: rect.top + ((p.y - view.value.y) / view.value.w) * rect.width,
    };
};
const stand = (p: Point) => ({ ...toClient(p), pointerId: KEY_POINTER }) as unknown as PointerEvent;
function endKeyMove() {
    if (keyIdle) clearTimeout(keyIdle);
    keyIdle = null;
    if (!keyHeld) return;
    keyHeld = false;
    const t = tokens.value.find((x) => x.id === keyTarget?.id);
    keyTarget = null;
    onUp(stand({ x: t?.x ?? 0, y: t?.y ?? 0 }));
}
function keyMove(dx: number, dy: number) {
    const t = selected.value;
    if (!t) return void (note.value = 'Choose a model first: [ and ] step through them.');
    if (t.locked) return void (note.value = `${t.name} belongs to ${t.side === 'n' ? 'the table' : props.sideNames[t.side]}; only they can move it.`);
    if (!keyHeld) {
        keyTarget = t;
        onDown(stand({ x: t.x, y: t.y }));
        keyHeld = !!drag;
        if (!keyHeld) {
            keyTarget = null;
            return;
        }
    }
    // the moving model has the position the last key left it at
    const at = tokens.value.find((x) => x.id === t.id)!;
    onMove(stand({ x: at.x + dx, y: at.y + dy }));
    if (moveFrame) {
        cancelAnimationFrame(moveFrame);
        moveFrame = 0;
    }
    flushMove();
    if (keyIdle) clearTimeout(keyIdle);
    keyIdle = setTimeout(endKeyMove, 700);
}
function chooseNext(step: 1 | -1) {
    endKeyMove();
    const list = tokens.value.filter((t) => !t.killed);
    if (!list.length) return;
    const i = list.findIndex((t) => t.id === selectedId.value);
    const next = list[(i + step + list.length) % list.length];
    selectedId.value = next.id;
    note.value = `${next.name}${next.marker ? ' (marker)' : ''}, ${list.indexOf(next) + 1} of ${list.length}${next.locked ? ', not yours to move' : '. Arrow keys move it.'}`;
}
/** Terrain from the keyboard: [ ] choose a piece, the arrows move it, , and . turn it, Delete removes it, Escape lets go. Each change is undoable. */
function onTerrainKey(e: KeyboardEvent) {
    const id = selectedPieceId.value;
    const piece = terrain.value.find((p) => p.id === id);
    const step = e.altKey ? 2 : 0.5;
    const arrows: Record<string, [number, number]> = { ArrowLeft: [-step, 0], ArrowRight: [step, 0], ArrowUp: [0, -step], ArrowDown: [0, step] };
    const replace = (next: TerrainPiece) => commitTerrain(terrain.value.map((p) => (p.id === next.id ? next : p)));
    if (e.key === ']' || e.key === '[') {
        e.preventDefault();
        const list = terrain.value;
        if (!list.length) return void (note.value = 'No terrain on the table yet.');
        const i = list.findIndex((p) => p.id === id);
        const next = list[(i + (e.key === ']' ? 1 : -1) + list.length) % list.length];
        selectedPieceId.value = next.id;
        note.value = `${next.name}, ${list.indexOf(next) + 1} of ${list.length}. Arrows move it, comma and full stop turn it, Delete removes it.`;
    } else if (arrows[e.key] && piece) {
        e.preventDefault();
        replace(movePiece(piece, ...arrows[e.key]));
        note.value = `${piece.name} moved ${Math.abs(arrows[e.key][0] || arrows[e.key][1])}" ${e.key.replace('Arrow', '').toLowerCase()}.`;
    } else if ((e.key === ',' || e.key === '.') && piece) {
        e.preventDefault();
        const deg = e.key === '.' ? 15 : -15;
        replace(rotatePiece(piece, deg));
        note.value = `${piece.name} turned ${Math.abs(deg)} degrees ${deg > 0 ? 'clockwise' : 'anticlockwise'}.`;
    } else if ((e.key === 'Delete' || e.key === 'Backspace') && piece) {
        e.preventDefault();
        note.value = `${piece.name} removed (Undo brings it back).`;
        commitTerrain(terrain.value.filter((p) => p.id !== piece.id));
        selectedPieceId.value = null;
    } else if (e.key === 'Escape' && piece) {
        e.preventDefault();
        selectedPieceId.value = null;
    }
}
/** A leg of a move is being dragged: the ring then stays where the leg began, and once it is dropped the ring moves to where it ended. */
const legActive = ref(false);
/** The ring of what a Walk can still reach: out from the edge of the base, around where the model stands (or began the leg), and in a step move smaller by what is spent. */
const walkRing = computed(() => {
    const tr = trail.value;
    if (!tr || tr.sp === undefined) return null;
    const rim = baseRadius(tr.baseMm);
    if (!stepMove.value) return { x: tr.from.x, y: tr.from.y, r: tr.sp + rim };
    const at = legActive.value ? (tr.path.at(-1) ?? { ...tr.from, spent: 0 }) : { x: tr.to.x, y: tr.to.y, spent: trailCost.value?.cost.total ?? 0 };
    return { x: at.x, y: at.y, r: Math.max(0, tr.sp - (at.spent ?? 0)) + rim };
});
/** Step move: put the model back where it began, and forget the move. */
function cancelMove() {
    const tr = trail.value;
    if (!tr) return;
    const t = tokens.value.find((x) => x.id === tr.id);
    if (t && Math.hypot(t.x - tr.from.x, t.y - tr.from.y) >= 0.05) {
        t.x = tr.from.x;
        t.y = tr.from.y;
        if (tr.startUnder) t.under = true;
        else delete t.under;
        emit('moved', { ...t });
        note.value = `${t.name} is back where it began.`;
    }
    trail.value = null;
}
/** Step move: take back the last leg (the first leg's undo is the same as cancelling the move). */
function undoStep() {
    const tr = trail.value;
    if (!tr) return;
    if (tr.path.length < 2) return cancelMove();
    const t = tokens.value.find((x) => x.id === tr.id);
    const back = tr.path[tr.path.length - 1];
    if (t) {
        t.x = back.x;
        t.y = back.y;
        if (settleUnder(t, back.elev, terrain.value)) t.under = true;
        else delete t.under;
        emit('moved', { ...t });
    }
    trail.value = { ...tr, path: tr.path.slice(0, -1), to: { x: back.x, y: back.y } };
    note.value = 'Last step taken back.';
}
/** The space bar is held: a drag moves the view, whatever mode the board is in. */
let spaceHeld = false;
function onKey(e: KeyboardEvent) {
    if (e.ctrlKey || e.metaKey) return;
    if (e.key === ' ' && mode.value !== 'terrain') {
        e.preventDefault();
        spaceHeld = true;
        return;
    }
    if (e.key === '?') {
        e.preventDefault();
        return openHelp('shortcuts');
    }
    if (mode.value === 'terrain') return onTerrainKey(e);
    const step = e.altKey ? 2 : e.shiftKey ? 0.1 : 0.5;
    const arrows: Record<string, [number, number]> = { ArrowLeft: [-step, 0], ArrowRight: [step, 0], ArrowUp: [0, -step], ArrowDown: [0, step] };
    if (arrows[e.key]) {
        e.preventDefault();
        keyMove(...arrows[e.key]);
    } else if (e.key === ']' || e.key === '[') {
        e.preventDefault();
        chooseNext(e.key === ']' ? 1 : -1);
    } else if (e.key === 'g' || e.key === 'G') {
        // sight guidelines for the selected model, or off when they are on and nothing else is selected
        const t = selectedId.value ? tokens.value.find((x) => x.id === selectedId.value && !x.marker && !x.killed) : undefined;
        if (t) {
            e.preventDefault();
            toggleGuide(t);
        } else if (guidedId.value) {
            e.preventDefault();
            endGuide();
        }
    } else if (e.key === 'Escape' && stepMove.value && trail.value && mode.value === 'move') {
        // a step move in progress: Escape puts the model back where it began (press it again to let go of the model)
        e.preventDefault();
        endKeyMove();
        cancelMove();
    } else if (e.key === 'Escape' && selectedId.value) {
        e.preventDefault();
        endKeyMove();
        selectedId.value = null;
    } else if (e.key === 'Escape' && guidedId.value) {
        e.preventDefault();
        endGuide();
    }
}
function onKeyUp(e: KeyboardEvent) {
    if (e.key === ' ') spaceHeld = false;
    if (e.key.startsWith('Arrow')) {
        if (keyIdle) clearTimeout(keyIdle);
        keyIdle = setTimeout(endKeyMove, 350);
    }
}

function onDown(e: PointerEvent) {
    // pressing the table gives it the keyboard (its mouse-down default is cancelled, which is what would otherwise focus it), so the
    // shortcuts work after a click as the help says
    svg.value?.focus({ preventScroll: true });
    try {
        svg.value?.setPointerCapture(e.pointerId);
    } catch {
        // the keyboard's stand-in pointer is not a real one: there is nothing to capture
    }
    pointers.set(e.pointerId, { x: e.clientX, y: e.clientY });
    if (pointers.size === 2) {
        const [a, b] = [...pointers.values()];
        pinch = { distance: Math.hypot(a.x - b.x, a.y - b.y), width: view.value.w, centre: toBoard((a.x + b.x) / 2, (a.y + b.y) / 2) };
        drag = null;
        return;
    }
    // the middle or right button, or the space bar held down, always moves the view: Sight guidelines and the ruler use a plain drag for themselves
    if (e.button === 1 || e.button === 2 || spaceHeld) {
        drag = { kind: 'pan', last: { x: e.clientX, y: e.clientY } };
        return;
    }
    const p = toBoard(e.clientX, e.clientY);
    const hit = keyTarget ?? tokenAt(p);
    note.value = '';
    // a stepped move goes on when the same model is grabbed again; anything else starts afresh
    const carryOn =
        stepMove.value &&
        (moveKind.value === 'walk' || moveKind.value === 'fly') &&
        mode.value === 'move' &&
        !!hit &&
        !hit.locked &&
        trail.value?.id === hit.id;
    if (!carryOn) trail.value = null;
    if (!guidedId.value) sight.value = null;
    if (placingId.value && mode.value === 'move' && !hit) {
        const waiting = tokens.value.find((x) => x.id === placingId.value);
        if (waiting) {
            placeAt(waiting, p);
            return;
        }
    }
    if (remainsArmed.value && mode.value === 'move' && !hit) {
        const waiting = remainsFor.value.find((x) => x.id === remainsArmed.value);
        if (waiting) {
            placeRemains(waiting, p);
            return;
        }
    }
    if (mode.value === 'terrain') {
        if (outline.value) {
            addCorner(p);
            return;
        }
        const grip = gripAt(p);
        if (grip) return;
        const found = surfaceAt(terrain.value, p.x, p.y);
        if (found) {
            if (selectedPieceId.value === found.piece.id) activeSurfaceId.value = found.surface.id;
            selectedPieceId.value = found.piece.id;
            drag = { kind: 'piece', id: found.piece.id, from: p, original: found.piece, before: cloneTerrain(terrain.value), moved: false };
        } else {
            selectedPieceId.value = null;
            drag = { kind: 'pan', last: { x: e.clientX, y: e.clientY } };
        }
        return;
    }
    if (mode.value === 'sight' && guidedId.value && sight.value) {
        // the guided model keeps its lines: a press aims them at the finger, or at the model or marker pressed
        const target = hit && hit.id !== guidedId.value ? hit : null;
        sight.value = { ...sight.value, toId: target?.id ?? null, aim: target ? { x: target.x, y: target.y } : p };
        drag = { kind: 'sight' };
        return;
    }
    if (mode.value === 'ruler') {
        ruler.value = { from: hit ? { x: hit.x, y: hit.y } : p, to: hit ? { x: hit.x, y: hit.y } : p, fromToken: hit?.id ?? null, toToken: null };
        drag = { kind: 'ruler' };
    } else if (hit) {
        selectedId.value = hit.id;
        if (hit.locked) {
            note.value = `${hit.name} belongs to ${hit.side === 'n' ? 'the table' : props.sideNames[hit.side]}; only they can move it.`;
            drag = { kind: 'pan', last: { x: e.clientX, y: e.clientY } };
            return;
        }
        drag = { kind: 'token', id: hit.id, dx: hit.x - p.x, dy: hit.y - p.y, startElevation: elevationOf(hit) };
        legActive.value = true;
        const here = { x: hit.x, y: hit.y, elev: elevationOf(hit) };
        if (carryOn && trail.value) {
            const last = trail.value.path[trail.value.path.length - 1];
            const moved = !last || Math.hypot(last.x - here.x, last.y - here.y) >= 0.05;
            trail.value = {
                ...trail.value,
                path: moved ? [...trail.value.path, { ...here, spent: trailCost.value?.cost.total ?? 0 }] : trail.value.path,
                to: { x: hit.x, y: hit.y },
            };
        } else {
            trail.value = {
                id: hit.id,
                from: { x: hit.x, y: hit.y },
                to: { x: hit.x, y: hit.y },
                baseMm: hit.baseMm,
                startElevation: elevationOf(hit),
                // an engaged model's Walk is half its Sp (a jump or a place is not a Walk)
                sp: hit.marker
                    ? undefined
                    : engagement.value.get(hit.id) && hit.sp !== undefined && (moveKind.value === 'walk' || moveKind.value === 'fly')
                      ? engagedSp(hit.sp)
                      : hit.sp,
                halved:
                    !hit.marker && !!engagement.value.get(hit.id) && hit.sp !== undefined && (moveKind.value === 'walk' || moveKind.value === 'fly'),
                chargeBlocked: !hit.marker && moveKind.value === 'charge' && !!engagement.value.get(hit.id),
                kind: moveKind.value,
                inside: buildingOf(hit, terrain.value)?.id ?? null,
                range: placeRange.value,
                whole: placeWhole.value,
                path: [{ ...here, spent: 0 }],
                startUnder: !!hit.under,
            };
        }
    } else {
        // empty table: drag to move the view; a still click lets go of the model (see onUp)
        drag = { kind: 'pan', last: { x: e.clientX, y: e.clientY }, tap: true, travel: 0 };
    }
}

function onMove(e: PointerEvent) {
    if (!pointers.has(e.pointerId)) return;
    pointers.set(e.pointerId, { x: e.clientX, y: e.clientY });
    if (pinch && pointers.size === 2) {
        const [a, b] = [...pointers.values()];
        const d = Math.hypot(a.x - b.x, a.y - b.y);
        if (d > 0) setZoom(pinch.width * (pinch.distance / d), pinch.centre);
        return;
    }
    // A phone reports a move far more often than it can draw one: keep the latest and apply it once a frame.
    lastMove = { clientX: e.clientX, clientY: e.clientY };
    if (!moveFrame) {
        moveFrame = requestAnimationFrame(() => {
            moveFrame = 0;
            flushMove();
        });
    }
}
let moveFrame = 0;
let lastMove: { clientX: number; clientY: number } | null = null;
/** Apply the latest pointer position to whatever is being dragged. */
function flushMove() {
    const e = lastMove;
    lastMove = null;
    if (!e || !drag) return;
    if (drag.kind === 'token') {
        const id = drag.id;
        const t = tokens.value.find((x) => x.id === id);
        if (!t) return;
        const p = toBoard(e.clientX, e.clientY);
        let to = { x: p.x + drag.dx, y: p.y + drag.dy };
        // In a step move a Walk cannot go past what is left of it: the leg stops at the edge of the ring
        const tr = trail.value;
        if (stepMove.value && tr && tr.id === id && tr.sp !== undefined && (tr.kind === 'walk' || tr.kind === 'fly')) {
            const anchor = tr.path.at(-1) ?? { ...tr.from, spent: 0 };
            const left = Math.max(0, tr.sp - (anchor.spent ?? 0));
            const d = Math.hypot(to.x - anchor.x, to.y - anchor.y);
            if (d > left && d > 0) to = { x: anchor.x + ((to.x - anchor.x) * left) / d, y: anchor.y + ((to.y - anchor.y) * left) / d };
        }
        Object.assign(t, clampToTable({ ...t, ...to }));
        if (trail.value) trail.value = { ...trail.value, to: { x: t.x, y: t.y } };
    } else if (drag.kind === 'piece') {
        const p = toBoard(e.clientX, e.clientY);
        // Snap to half inches so a table lays out tidily.
        const snap = (n: number) => Math.round(n * 2) / 2;
        const moved = movePiece(drag.original, snap(p.x - drag.from.x), snap(p.y - drag.from.y));
        const id = drag.id;
        terrain.value = terrain.value.map((x) => (x.id === id ? moved : x));
        if (snap(p.x - drag.from.x) !== 0 || snap(p.y - drag.from.y) !== 0) drag.moved = true;
    } else if (drag.kind === 'handle') {
        const p = toBoard(e.clientX, e.clientY);
        const id = drag.id;
        let next: TerrainPiece;
        if (drag.grip === 'rotate') {
            const c = drag.centre!;
            const bearing = (Math.atan2(p.y - c.y, p.x - c.x) * 180) / Math.PI;
            let delta = bearing - (drag.bearing ?? 0);
            delta = Math.round(((((delta + 180) % 360) + 360) % 360) - 180);
            delta = Math.round(delta / 5) * 5;
            turning.value = delta;
            next = rotatePiece(drag.original, delta);
        } else {
            const grip = drag.grip;
            const surfaceId = drag.surfaceId;
            const base = drag.original.surfaces.find((s) => s.id === surfaceId);
            if (!base) return;
            if (grip.kind === 'opening') {
                // a door or window slides along the walls
                const opening = drag.original.interior?.openings[grip.index];
                if (!opening) return;
                next = moveOpening(drag.original, opening.id, p);
            } else {
                const shape = dragShapeHandle(base.shape, grip, p);
                next = movePiece({ ...drag.original, surfaces: drag.original.surfaces.map((s) => (s.id === surfaceId ? { ...s, shape } : s)) }, 0, 0);
            }
        }
        terrain.value = terrain.value.map((x) => (x.id === id ? next : x));
        drag.moved = true;
    } else if (drag.kind === 'pan') {
        const rect = svg.value!.getBoundingClientRect();
        const k = view.value.w / rect.width;
        drag.travel = (drag.travel ?? 0) + Math.hypot(e.clientX - drag.last.x, e.clientY - drag.last.y);
        view.value = { ...view.value, x: view.value.x - (e.clientX - drag.last.x) * k, y: view.value.y - (e.clientY - drag.last.y) * k };
        keepInside();
        drag.last = { x: e.clientX, y: e.clientY };
    } else if (drag.kind === 'sight') {
        const p = toBoard(e.clientX, e.clientY);
        const hit = tokenAt(p);
        if (sight.value) {
            const target = hit && hit.id !== sight.value.fromId ? hit : null;
            sight.value = { ...sight.value, toId: target?.id ?? null, aim: target ? { x: target.x, y: target.y } : p };
        }
    } else if (ruler.value) {
        const p = toBoard(e.clientX, e.clientY);
        const hit = tokenAt(p);
        ruler.value = {
            ...ruler.value,
            to: hit && hit.id !== ruler.value.fromToken ? { x: hit.x, y: hit.y } : p,
            toToken: hit && hit.id !== ruler.value.fromToken ? hit.id : null,
        };
    }
}

/** What the selected model overlaps that it could be beneath: an archway, or the roof of an enterable building. It is under (inside) it or on top of it. */
/** Terrain chips for the selected model: concealment, cover, hazardous and the like, from where its base is. */
const selectedChips = computed(() => (selected.value && !selected.value.marker ? terrainChips(selected.value, worldTerrain.value) : []));
const selectedCover = computed(() => (selected.value && !selected.value.marker ? coverAt(selected.value, terrain.value) : null));
/** The gaps in an enterable building's walls, ready to draw: where each runs and what it is. */
const openingSpans = (piece: TerrainPiece) =>
    (piece.interior?.openings ?? []).flatMap((o) => {
        const span = openingSpan(piece, o);
        return span ? [{ id: o.id, kind: o.kind, a: span[0], b: span[1] }] : [];
    });
/** Buildings whose roof is faded so the inside shows: one by one, or all of them. */
const insideShown = ref<Set<string>>(new Set());
const showAllInsides = ref(false);
const anyEnterable = computed(() => terrain.value.some((p) => p.interior));
const cutaway = (p: TerrainPiece): boolean => !!p.interior && (showAllInsides.value || insideShown.value.has(p.id));
function toggleInside(id: string) {
    const next = new Set(insideShown.value);
    if (next.has(id)) next.delete(id);
    else next.add(id);
    insideShown.value = next;
}
function setUnder(t: Token, under: boolean) {
    const token = tokens.value.find((x) => x.id === t.id);
    if (!token) return;
    if (under) token.under = true;
    else delete token.under;
    emit('moved', { ...token });
}
/** Where the selected base is on the table, in words: quarters, nearest edge and corner, and with a deployment chosen, whose half and zone it is in. */
const whereText = computed(() => {
    const t = selected.value;
    if (!t) return '';
    const w = whereabouts(t, zones.value);
    const q = w.quarters;
    const quarters = q.length === 4 ? 'All four quarters' : q.length === 2 ? `${q[0]} and ${q[1]} quarters` : `${q[0]} quarter`;
    const parts = [
        quarters.charAt(0).toUpperCase() + quarters.slice(1),
        w.edge.gap < 0.05 ? `touching the ${w.edge.side} edge` : `${fmt(w.edge.gap)}" from the ${w.edge.side} edge`,
        w.corner.gap < 6 ? `${fmt(w.corner.gap)}" from the ${w.corner.name} corner` : null,
    ];
    if (w.half) parts.push(w.half === 'line' ? 'on the centerline' : `${props.sideNames[w.half]}'s half`);
    if (w.zone) parts.push(`in ${props.sideNames[w.zone]}'s deployment zone`);
    return parts.filter(Boolean).join(' · ');
});
/** The terrain traits a model is unaffected by, switched on and off from its panel. */
function toggleIgnore(t: Token, trait: string) {
    const token = tokens.value.find((x) => x.id === t.id);
    if (!token || token.locked) return;
    const now = token.ignores ?? [];
    const next = now.includes(trait) ? now.filter((x) => x !== trait) : [...now, trait];
    if (next.length) token.ignores = next;
    else delete token.ignores;
    emit('moved', { ...token });
}
const IGNORE_HINTS: Record<string, string> = {
    blocking: 'Blocking terrain cannot block its sight lines',
    concealing: 'It ignores concealing terrain when drawing sight lines',
    dense: 'Dense terrain cannot block its sight lines',
    hazardous: 'It does not suffer hazardous terrain',
    severe: 'Severe terrain does not cost it double',
    impassable: 'It may move through impassable terrain, but not end a move on it',
};
/** A model left over another base or on impassable ground goes to the nearest legal spot (p.20). */
const illegalHere = (t: Token) =>
    (problems.value.get(t.id) ?? []).some((p) => p.kind === 'overlap' || p.kind === 'impassable' || p.kind === 'unreachable');
function moveToLegalSpot(t: Token) {
    const token = tokens.value.find((x) => x.id === t.id);
    if (!token || token.locked) return;
    const spot = nearestLegalSpot(token, tokens.value, worldTerrain.value, undefined, undefined, reach.value);
    if (!spot) {
        note.value = `There is no legal spot near ${token.name}.`;
        return;
    }
    const away = Math.hypot(spot.x - token.x, spot.y - token.y);
    Object.assign(token, spot);
    trail.value = null;
    emit('moved', { ...token });
    note.value = `${token.name} moved ${fmt(away)}" to the nearest legal spot.`;
}
/** What a jump says: how far against the Walk, and whether it was one the rules allow (p.22). */
function jumpNote(t: Token, tr: NonNullable<typeof trail.value>, startElevation: number): string {
    const c = jumpCheck({ x: tr.from.x, y: tr.from.y, baseMm: tr.baseMm }, startElevation, t, terrain.value);
    const parts = [`${t.name} jumped ${fmt(c.distance)}"`];
    if (tr.sp !== undefined) {
        const left = tr.sp - c.distance;
        parts.push(
            left >= -1e-9 ? `${fmt(left)}" left of a Walk (Sp ${tr.sp}), since a jump is movement` : `${fmt(-left)}" more than a Walk (Sp ${tr.sp})`,
        );
    }
    return c.reasons.length
        ? `${parts.join(', ')}. That is not a legal jump: ${c.reasons.join('; ')}.`
        : `${parts.join(', ')}. A legal jump, landing at elevation ${c.endElevation}.`;
}
/** A hazardous piece's name with its rules card, when it has one. */
const hazardEffect = (name: string) => {
    const rules = worldTerrain.value.find((p) => p.name === name)?.rules;
    return rules ? `${name}: ${rules}` : name;
};
/** What a place says, in words: how far it went against its range, and what it skipped (p.17). */
function placeNote(t: Token, tr: NonNullable<typeof trail.value>): string {
    const from = { x: tr.from.x, y: tr.from.y, baseMm: tr.baseMm };
    const c = placeCheck(tr.range, from, t, tr.whole);
    const parts = [
        `${t.name} placed ${fmt(c.gap)}" from where it started`,
        c.within
            ? `within the ${fmt(tr.range)}" place${tr.whole ? ' (the whole base)' : ''}`
            : `${fmt(c.over)}" beyond the ${fmt(tr.range)}" place${tr.whole ? ' (the whole base)' : ''}`,
    ];
    const now = elevationOf(t);
    if (now !== tr.startElevation) parts.push(`lands at elevation ${now}`);
    const hazards = [
        ...placementProblems({ ...t, x: from.x, y: from.y }, tokens.value, worldTerrain.value, reach.value),
        ...(problems.value.get(t.id) ?? []),
    ].some((p) => p.kind === 'hazardous');
    if (hazards) parts.push('it began or ended in hazardous terrain, which counts as having moved through it');
    if ((buildingOf(t, terrain.value)?.id ?? null) !== tr.inside)
        parts.push('a place cannot move a model into or out of a building, so it does not happen: put it back');
    return parts.join(', ') + '. Terrain and sight in between are ignored, and nothing is spent climbing or falling.';
}

function onUp(e: PointerEvent) {
    // the last move before the finger lifts must land before the drop is worked out
    if (moveFrame) {
        cancelAnimationFrame(moveFrame);
        moveFrame = 0;
    }
    flushMove();
    pointers.delete(e.pointerId);
    if (pointers.size < 2) pinch = null;
    legActive.value = false;
    if (drag?.kind === 'token') {
        const id = drag.id;
        const t = tokens.value.find((x) => x.id === id);
        if (t) {
            // Put down over a bridge: beneath it if it came from below the deck, on it if it was already that high.
            if (!t.marker) {
                if (settleUnder(t, drag.startElevation, terrain.value)) t.under = true;
                else delete t.under;
            }
            emit('moved', { ...t });
            const now = elevationOf(t);
            const cost = trailCost.value?.cost ?? pathCost([{ x: t.x, y: t.y, elev: now }]);
            const legs = cost.legs;
            const leg = moveCost(trail.value?.path.at(-1) ?? t, t, drag.startElevation, now);
            if (leg.ground < 0.05 && !stepMove.value) {
                trail.value = null;
            } else if (trail.value?.kind === 'jump') {
                note.value = jumpNote(t, trail.value, drag.startElevation);
            } else if (trail.value?.kind === 'place') {
                note.value = placeNote(t, trail.value);
            } else if (cost.ground >= 0.05) {
                const parts = [`${t.name} moved ${fmt(cost.ground)}"${legs.length > 1 ? ` (${legs.map((l) => fmt(l) + '"').join(' + ')})` : ''}`];
                if (cost.climb > 0) parts.push(`climbed ${fmt(cost.climb)}" (counts as movement): ${fmt(cost.total)}" in all`);
                const crossed = trailCost.value?.crossed;
                if (crossed && crossed.severe >= 0.05)
                    parts.push(`${fmt(crossed.severe)}" of it in severe terrain, which counts double (${fmt(cost.total)}" of movement in all)`);
                if (crossed?.hazardous.length)
                    parts.push(
                        `moved through hazardous terrain (${crossed.hazardous.map((n) => hazardEffect(n)).join(', ')}), suffered once per activation and from only one piece`,
                    );
                if (crossed?.impassable.length)
                    parts.push(`passes through impassable terrain (${crossed.impassable.join(', ')}), which a Walk cannot do`);
                if (trail.value?.kind === 'fly') parts.push('ignoring terrain height, so no climbing cost and no fall');
                else if (overRamp(trail.value?.path.at(-1) ?? t, t, t.baseMm, worldTerrain.value))
                    parts.push(
                        leg.drop > 0 ? `down the stairs or ramp to elevation ${now}, with no fall` : 'over stairs or a ramp, with no climbing cost',
                    );
                else if (fallHurts(drag.startElevation, now)) parts.push(`dropped ${fmt(leg.drop)}": 1 irreducible damage if this was a fall`);
                else if (leg.drop > 0) parts.push(`down ${fmt(leg.drop)}" to elevation ${now}`);
                const walk = trailCost.value?.walk;
                if (walk)
                    parts.push(
                        walk.within
                            ? `${fmt(walk.left)}" left of a ${trail.value?.kind === 'charge' ? 'Charge' : 'Walk'} (Sp ${trail.value?.sp}${trail.value?.halved ? ', halved: engaged' : ''})`
                            : `${fmt(-walk.left)}" more than a ${trail.value?.kind === 'charge' ? 'Charge' : 'Walk'} (Sp ${trail.value?.sp}${trail.value?.halved ? ', halved: engaged' : ''})`,
                    );
                if (trail.value?.chargeBlocked) parts.unshift('A model cannot declare Charge while engaged');
                if (trail.value?.kind === 'charge' && legs.length === 1)
                    parts.push('then it may declare a melee action that does not count against its action limit');
                note.value = parts.join(', ') + (legs.length > 1 ? '. Each leg is a straight line.' : '. Straight-line distance.');
            }
            // A walk into or out of a building has to go through a door (p.58).
            const wasInside = trail.value?.inside ?? null;
            if (trail.value && trail.value.kind !== 'place' && !t.marker && (buildingOf(t, terrain.value)?.id ?? null) !== wasInside) {
                const legs = [...trail.value.path, { x: t.x, y: t.y }];
                const walls = terrain.value.flatMap((pc) =>
                    pc.interior ? legs.slice(1).flatMap((q, i) => wallProblem(pc, legs[i], q, t.baseMm) ?? []) : [],
                );
                if (walls.length) note.value = `${t.name} ${[...new Set(walls)].join(' and ')}. ${note.value}`.trim();
            }
            const trouble = problems.value.get(t.id) ?? [];
            if (trouble.length) {
                note.value =
                    `${t.name} ${trouble.map((p) => p.text).join(' and ')}. ${trouble.some((p) => p.kind === 'overlap' || p.kind === 'impassable') ? (trail.value?.kind === 'place' ? 'A place that cannot fit does not happen: put it back.' : 'Move it to the nearest legal spot.') : ''} ${note.value}`.trim();
            }
        }
    }
    if ((drag?.kind === 'piece' || drag?.kind === 'handle') && drag.moved) {
        undoStack.value = [...undoStack.value.slice(-19), drag.before];
        emit('terrainChanged', cloneTerrain(terrain.value));
    }
    if (drag?.kind === 'handle') {
        turning.value = 0;
        if (typeof drag.grip === 'object' && drag.grip.kind === 'vertex') pickedVertex.value = drag.grip.index;
    }
    // a still click on bare table lets go of the chosen model; a drag (moving the view) does not, and neither does a click while Sight guidelines are on
    if (drag?.kind === 'pan' && drag.tap && (drag.travel ?? 0) < 4 && !guidedId.value) selectedId.value = null;
    if (pointers.size === 0) drag = null;
}

function onWheel(e: WheelEvent) {
    e.preventDefault();
    setZoom(view.value.w * (e.deltaY > 0 ? 1.12 : 1 / 1.12), toBoard(e.clientX, e.clientY));
}

const measure = computed(() => {
    const r = ruler.value;
    if (!r) return null;
    const a = tokens.value.find((t) => t.id === r.fromToken);
    const b = tokens.value.find((t) => t.id === r.toToken);
    const centres = Math.hypot(r.from.x - r.to.x, r.from.y - r.to.y);
    let inches = centres;
    if (a && b) inches = edgeDistance(a, b);
    else if (a) inches = Math.max(0, centres - baseRadius(a.baseMm));
    else if (b) inches = Math.max(0, centres - baseRadius(b.baseMm));
    const rise = a && b ? elevationOf(b) - elevationOf(a) : null;
    return { inches, rise, cover: a && b ? heightCover(elevationOf(a), elevationOf(b)) : false };
});

const characterDialog = ref(false);
watch(characterDialog, (open) => {
    if (!open) replacing.value = null;
});
const characterKey = ref(0);
function openCharacterDialog() {
    characterKey.value++;
    characterDialog.value = true;
}
/** A real card: its name, base size, Sz and Sp. New models fan out so several added in a row do not sit on top of each other. */
function addCharacter(c: CharacterOption) {
    if (replacing.value) {
        const old = tokens.value.find((t) => t.id === replacing.value);
        if (old && !old.killed) localAnchors.value = { ...localAnchors.value, [old.id]: { x: old.x, y: old.y, baseMm: old.baseMm, name: old.name } };
        emit('replaceCharacter', replacing.value, c);
        note.value = `Replaced with ${c.name}.`;
        replacing.value = null;
        characterDialog.value = false;
        return;
    }
    const nudge = (tokens.value.filter((t) => !t.marker).length % 6) * 1.2;
    const at = clampToTable({ baseMm: c.base_mm, x: view.value.x + view.value.w / 2 + nudge, y: view.value.y + view.value.w / 2 + nudge });
    emit('add', {
        name: c.name,
        side: newSide.value,
        baseMm: c.base_mm,
        sz: c.sz,
        sp: c.sp,
        peon: c.station === 'peon',
        info: { health: c.health, maxHealth: c.health, tokens: [] },
        card: {
            faction: c.faction_key,
            defense: c.df ?? undefined,
            willpower: c.wp ?? undefined,
            front_image: c.front_image,
            back_image: c.back_image,
        },
        ...at,
    });
    note.value = `${c.name} added (${c.base_mm} mm base, Sz ${c.sz}, Sp ${c.sp}, ${c.health} health). Drag it into place.`;
    characterDialog.value = false;
}
/** Markers belong to whoever placed them, and each side can have this many of each kind on the table. */
const MARKERS_PER_KIND = 5;
const markerDialog = ref(false);
/** A fresh search box each time the dialog opens. */
const pickerKey = ref(0);
const openMarkerDialog = () => {
    pickerKey.value++;
    markerDialog.value = true;
};
const markerSides = computed(
    () =>
        props.placeAs ??
        (props.sandbox
            ? [
                  { side: 'a' as const, label: props.sideNames.a },
                  { side: 'b' as const, label: props.sideNames.b },
              ]
            : []),
);
const markerSide = ref<'a' | 'b'>(markerSides.value[0]?.side ?? 'a');
/** How many of each marker the chosen side already has out, by marker id. */
const markersUsed = computed(() => {
    const used: Record<number, number> = {};
    for (const t of props.tokens)
        if (t.marker && t.side === markerSide.value && t.markerId !== undefined) used[t.markerId] = (used[t.markerId] ?? 0) + 1;
    return used;
});
const markersOut = (side: 'a' | 'b') => props.tokens.filter((t) => t.marker && t.side === side).length;
function placeMarker(m: MarkerOption) {
    if ((markersUsed.value[m.id] ?? 0) >= MARKERS_PER_KIND) return;
    // Fan new markers out a little so several added in a row do not sit exactly on top of each other.
    const nudge = (tokens.value.filter((t) => t.marker).length % 6) * 1.2;
    const at = clampToTable({ baseMm: m.base_mm, x: view.value.x + view.value.w / 2 + nudge, y: view.value.y + view.value.w / 2 + nudge });
    emit('addMarker', { markerId: m.id, name: m.name, baseMm: m.base_mm, side: markerSide.value, ...at });
    note.value = `${m.name} added (${m.base_mm} mm base). Drag it into place.`;
    markerDialog.value = false;
}
function removeSelected() {
    if (selectedId.value) emit('remove', selectedId.value);
    selectedId.value = null;
}
const confirm = useConfirm();
async function resetTable() {
    if (
        !(await confirm({
            title: 'Reset the table?',
            message: 'The models go and the terrain goes back to the demo table.',
            confirmLabel: 'Reset table',
            destructive: true,
        }))
    )
        return;
    emit('reset');
    selectedId.value = null;
    ruler.value = null;
    sight.value = null;
    guidedId.value = null;
    if (mode.value === 'sight') mode.value = 'move';
    note.value = '';
    resetView();
}
const fmt = (n: number) => (Math.round(n * 10) / 10).toString();
</script>

<template>
    <div class="grid grid-cols-[minmax(0,1fr)] gap-4 lg:grid-cols-[minmax(0,1fr)_20rem] xl:grid-cols-[minmax(0,1fr)_22rem]">
        <!-- on a wide screen the table stays in view while the side column scrolls, so the list and the options never leave it behind -->
        <div class="group/board lg:sticky lg:top-4 lg:self-start" data-testid="board-column">
            <div class="mb-2 flex flex-wrap items-center gap-2 rounded-md border p-2" role="toolbar" aria-label="Board tools" data-testid="board-bar">
                <template v-if="activeTab === 'move'">
                    <div class="contents" data-testid="move-tools">
                        <Button
                            :variant="mode === 'ruler' ? 'default' : 'outline'"
                            size="sm"
                            title="Measure edge to edge: press on a model or a spot on the table and drag to another"
                            data-testid="mode-ruler"
                            :aria-pressed="mode === 'ruler'"
                            @click="toggleRuler"
                            ><Ruler class="mr-1 size-4" />Ruler</Button
                        >
                        <Button
                            :variant="mode === 'move' && stepMove ? 'default' : 'outline'"
                            size="sm"
                            :aria-pressed="mode === 'move' && stepMove"
                            title="Step move: grab the same model again to add another leg to the move"
                            data-testid="step-toggle"
                            @click="chooseStep"
                            ><Route class="mr-1 size-4" />Step</Button
                        >

                        <div
                            role="radiogroup"
                            aria-label="How the model moves"
                            class="inline-flex rounded-md border p-0.5 max-sm:order-last max-sm:w-full max-sm:[&>button]:flex-1"
                            data-testid="move-kind"
                        >
                            <button
                                v-for="o in MOVE_KINDS"
                                :key="o.value"
                                type="button"
                                role="radio"
                                :aria-checked="mode === 'move' && moveKind === o.value"
                                :title="o.hint"
                                :class="[
                                    'rounded px-2 py-1 text-sm font-medium transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary',
                                    mode === 'move' && moveKind === o.value
                                        ? 'bg-primary text-primary-foreground'
                                        : 'text-muted-foreground hover:text-foreground',
                                ]"
                                :data-testid="`move-kind-${o.value}`"
                                @click="chooseKind(o.value)"
                            >
                                {{ o.label }}
                            </button>
                        </div>
                    </div>
                </template>
                <div class="ml-auto flex shrink-0 gap-1" data-testid="zoom-controls">
                    <Button variant="outline" size="icon" class="size-8 max-lg:size-11" aria-label="Zoom in" @click="zoomBy(0.8)"
                        ><Plus class="size-4"
                    /></Button>
                    <Button variant="outline" size="icon" class="size-8 max-lg:size-11" aria-label="Zoom out" @click="zoomBy(1.25)"
                        ><Minus class="size-4"
                    /></Button>
                    <Button variant="outline" size="icon" class="size-8 max-lg:size-11" aria-label="Fit the whole table" @click="resetView"
                        ><Maximize class="size-4"
                    /></Button>
                </div>
            </div>
            <div
                class="mx-auto aspect-square w-full max-w-[min(100%,calc(100vh-14rem))] overflow-hidden rounded-lg border bg-card"
                data-testid="tabletop-board"
            >
                <svg
                    ref="svg"
                    class="size-full touch-none select-none focus-visible:outline focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-primary"
                    :viewBox="viewBox"
                    role="application"
                    tabindex="0"
                    aria-label="Tabletop board, 36 by 36 inches. Press [ or ] to choose a model, the arrow keys to move it, Escape to let go."
                    aria-describedby="board-keys"
                    @mousedown.prevent
                    @keydown="onKey"
                    @keyup="onKeyUp"
                    @pointerdown="onDown"
                    @contextmenu.prevent
                    @blur="spaceHeld = false"
                    @pointermove="onMove"
                    @pointerup="onUp"
                    @pointercancel="onUp"
                    @wheel="onWheel"
                >
                    <defs>
                        <pattern id="arch-hatch" patternUnits="userSpaceOnUse" width="0.7" height="0.7" patternTransform="rotate(45)">
                            <line x1="0" y1="0" x2="0" y2="0.7" stroke="hsl(var(--foreground) / 0.45)" stroke-width="0.12" />
                        </pattern>
                        <pattern
                            v-if="tableChoice?.fit === 'tile'"
                            id="table-tex"
                            patternUnits="userSpaceOnUse"
                            :width="TEXTURE_TILE"
                            :height="TEXTURE_TILE"
                        >
                            <image :href="tableChoice.url" :width="TEXTURE_TILE" :height="TEXTURE_TILE" preserveAspectRatio="none" />
                        </pattern>
                        <pattern
                            v-for="t in textures"
                            :id="`tex-${t.kind}`"
                            :key="t.kind"
                            patternUnits="userSpaceOnUse"
                            :width="TEXTURE_TILE"
                            :height="TEXTURE_TILE"
                        >
                            <image :href="t.url" :width="TEXTURE_TILE" :height="TEXTURE_TILE" preserveAspectRatio="none" />
                        </pattern>
                    </defs>
                    <rect x="0" y="0" :width="TABLE_SIZE" :height="TABLE_SIZE" fill="hsl(var(--muted))" />
                    <rect
                        v-if="tableChoice?.fit === 'tile'"
                        x="0"
                        y="0"
                        :width="TABLE_SIZE"
                        :height="TABLE_SIZE"
                        fill="url(#table-tex)"
                        :data-surface="tableChoice.key"
                        data-testid="table-surface"
                    />
                    <image
                        v-else-if="tableChoice"
                        :href="tableChoice.url"
                        x="0"
                        y="0"
                        :width="TABLE_SIZE"
                        :height="TABLE_SIZE"
                        preserveAspectRatio="xMidYMid slice"
                        :data-surface="tableChoice.key"
                        data-testid="table-surface"
                    />
                    <path
                        v-for="n in 35"
                        :key="`g${n}`"
                        :d="`M${n} 0V${TABLE_SIZE}M0 ${n}H${TABLE_SIZE}`"
                        stroke="hsl(var(--border))"
                        :stroke-width="n === 18 ? 0.06 : 0.03"
                        :stroke-opacity="n === 18 ? 0.7 : 0.45"
                    />
                    <g v-if="zones" data-testid="deployment-zones" :data-kind="deployment">
                        <!-- zones are clipped to the table, which cuts the corner circles into quarters -->
                        <clipPath id="table-clip"><rect x="0" y="0" :width="TABLE_SIZE" :height="TABLE_SIZE" /></clipPath>
                        <g clip-path="url(#table-clip)">
                            <component
                                :is="shapeTag(zones.a)"
                                v-bind="shapeAttrs(zones.a)"
                                :fill="sideColour('a')"
                                fill-opacity="0.16"
                                stroke-dasharray="0.6 0.4"
                                :stroke="sideColour('a')"
                                stroke-width="0.12"
                                data-testid="zone-a"
                            />
                            <component
                                :is="shapeTag(zones.b)"
                                v-bind="shapeAttrs(zones.b)"
                                :fill="sideColour('b')"
                                fill-opacity="0.16"
                                stroke-dasharray="0.6 0.4"
                                :stroke="sideColour('b')"
                                stroke-width="0.12"
                                data-testid="zone-b"
                            />
                        </g>
                        <line
                            :x1="zones.centerline[0][0]"
                            :y1="zones.centerline[0][1]"
                            :x2="zones.centerline[1][0]"
                            :y2="zones.centerline[1][1]"
                            stroke="hsl(var(--foreground))"
                            stroke-opacity="0.75"
                            stroke-width="0.14"
                            stroke-dasharray="0.7 0.5"
                            data-testid="centerline"
                        />
                    </g>
                    <template v-for="piece in terrain" :key="piece.id">
                        <g
                            v-for="surface in [...piece.surfaces].sort((a, b) => a.top - b.top)"
                            :key="surface.id"
                            :opacity="dimmed(surface.top) ? 0.12 : cutaway(piece) ? 0.14 : 1"
                        >
                            <component
                                :is="shapeTag(surface.shape)"
                                v-if="textureOf(piece)"
                                v-bind="shapeAttrs(surface.shape)"
                                :fill="textureOf(piece)!"
                                pointer-events="none"
                                data-testid="terrain-texture"
                                :data-kind="piece.kind"
                            />
                            <component
                                :is="shapeTag(surface.shape)"
                                v-bind="shapeAttrs(surface.shape)"
                                :fill="textureOf(piece) ? texturedFill(surface.top) : surfaceFill(surface.top)"
                                :stroke="mode === 'terrain' && piece.id === selectedPieceId ? 'hsl(45 95% 55%)' : 'hsl(var(--foreground) / 0.55)'"
                                :stroke-width="mode === 'terrain' && piece.id === selectedPieceId ? 0.22 : 0.07"
                            />
                            <component
                                :is="shapeTag(surface.shape)"
                                v-if="surface.under !== undefined"
                                v-bind="shapeAttrs(surface.shape)"
                                fill="url(#arch-hatch)"
                                pointer-events="none"
                                data-testid="archway"
                            />
                            <text
                                :x="labelAt(surface.shape).x"
                                :y="labelAt(surface.shape).y"
                                v-if="surface.top > 0 && showHeights"
                                font-size="0.62"
                                fill="hsl(var(--foreground))"
                                stroke="hsl(var(--background) / 0.7)"
                                stroke-width="0.12"
                                paint-order="stroke"
                                pointer-events="none"
                                data-testid="height-label"
                            >
                                Ht {{ surface.top }}
                                <template v-if="surface.under !== undefined">· arch {{ surface.under }}</template>
                            </text>
                        </g>
                        <g
                            v-if="piece.interior"
                            pointer-events="none"
                            data-testid="building-interior"
                            :data-piece="piece.id"
                            :data-cutaway="cutaway(piece) ? 'true' : undefined"
                        >
                            <template v-if="cutaway(piece)">
                                <component
                                    :is="shapeTag(interiorShape(piece)!)"
                                    v-bind="shapeAttrs(interiorShape(piece)!)"
                                    fill="hsl(var(--foreground) / 0.1)"
                                    data-testid="building-floor"
                                />
                                <line
                                    v-for="(w, n) in wallSegments(piece)"
                                    :key="`wall-${n}`"
                                    :x1="w[0].x"
                                    :y1="w[0].y"
                                    :x2="w[1].x"
                                    :y2="w[1].y"
                                    stroke="hsl(var(--foreground))"
                                    stroke-width="0.32"
                                    stroke-linecap="round"
                                    data-testid="building-wall"
                                />
                            </template>
                            <line
                                v-for="o in openingSpans(piece)"
                                :key="o.id"
                                :x1="o.a.x"
                                :y1="o.a.y"
                                :x2="o.b.x"
                                :y2="o.b.y"
                                :stroke="o.kind === 'door' ? 'hsl(35 90% 55%)' : 'hsl(200 85% 65%)'"
                                :stroke-width="o.kind === 'door' ? 0.34 : 0.2"
                                :data-testid="`building-${o.kind}`"
                            />
                        </g>
                    </template>
                    <g v-if="tableLines" pointer-events="none" data-testid="table-lines">
                        <path
                            :d="`M${TABLE_SIZE / 2} 0V${TABLE_SIZE}M0 ${TABLE_SIZE / 2}H${TABLE_SIZE}`"
                            fill="none"
                            stroke="hsl(var(--foreground))"
                            stroke-opacity="0.55"
                            stroke-width="0.09"
                            stroke-dasharray="0.7 0.5"
                            data-testid="table-axes"
                        />
                        <g v-for="(q, i) in QUARTER_CENTRES" :key="i" data-testid="quarter-centre" :data-x="q.x" :data-y="q.y">
                            <path
                                :d="`M${q.x - 0.7} ${q.y}H${q.x + 0.7}M${q.x} ${q.y - 0.7}V${q.y + 0.7}`"
                                stroke="hsl(var(--background))"
                                stroke-width="0.3"
                                stroke-linecap="round"
                            />
                            <path
                                :d="`M${q.x - 0.7} ${q.y}H${q.x + 0.7}M${q.x} ${q.y - 0.7}V${q.y + 0.7}`"
                                stroke="hsl(var(--foreground))"
                                stroke-opacity="0.85"
                                stroke-width="0.12"
                                stroke-linecap="round"
                            />
                            <circle :cx="q.x" :cy="q.y" r="0.2" fill="hsl(var(--foreground))" />
                        </g>
                    </g>
                    <g v-if="handles.length || rotateGrip" pointer-events="none" data-testid="terrain-handles">
                        <template v-if="rotateGrip">
                            <line
                                :x1="rotateGrip.centre.x"
                                :y1="rotateGrip.centre.y"
                                :x2="rotateGrip.x"
                                :y2="rotateGrip.y"
                                stroke="hsl(45 95% 55%)"
                                :stroke-width="pxToUnits(1.5)"
                                :stroke-dasharray="`${pxToUnits(4)} ${pxToUnits(4)}`"
                            />
                            <circle
                                :cx="rotateGrip.x"
                                :cy="rotateGrip.y"
                                :r="pxToUnits(9)"
                                fill="hsl(45 95% 55%)"
                                stroke="hsl(var(--background))"
                                :stroke-width="pxToUnits(2)"
                                data-testid="handle-rotate"
                                :data-x="rotateGrip.x"
                                :data-y="rotateGrip.y"
                            />
                        </template>
                        <circle
                            v-for="h in handles"
                            :key="h.id"
                            :cx="h.x"
                            :cy="h.y"
                            :r="pxToUnits(h.kind === 'mid' ? 5 : 8)"
                            :fill="
                                h.kind === 'mid'
                                    ? 'hsl(var(--background))'
                                    : h.kind === 'vertex' && pickedVertex === h.index
                                      ? 'hsl(0 85% 60%)'
                                      : 'hsl(var(--foreground))'
                            "
                            stroke="hsl(45 95% 55%)"
                            :stroke-width="pxToUnits(2)"
                            :data-testid="`handle-${h.id}`"
                            :data-x="h.x"
                            :data-y="h.y"
                        />
                    </g>
                    <g
                        v-for="a in activeAuras"
                        :key="`aura-${a.aura.id}`"
                        pointer-events="none"
                        :data-testid="`aura-${a.owner.id}`"
                        :data-locked="a.aura.locked"
                    >
                        <circle
                            :cx="a.origin.x"
                            :cy="a.origin.y"
                            :r="a.radius"
                            :fill="sideColour(a.owner.side)"
                            fill-opacity="0.14"
                            :stroke="sideColour(a.owner.side)"
                            :stroke-width="a.aura.locked ? 0.2 : 0.12"
                            :stroke-dasharray="a.aura.locked ? undefined : AURA_DASHES[a.nth % AURA_DASHES.length]"
                        />
                        <circle
                            v-if="a.aura.shared"
                            :cx="a.origin.x"
                            :cy="a.origin.y"
                            :r="a.radius + 0.22"
                            fill="none"
                            :stroke="sideColour(a.owner.side)"
                            stroke-width="0.06"
                            data-shared="true"
                        />
                        <g v-if="a.aura.locked">
                            <circle :cx="a.origin.x" :cy="a.origin.y" r="0.22" :fill="sideColour(a.owner.side)" />
                            <circle
                                :cx="a.origin.x"
                                :cy="a.origin.y"
                                :r="baseRadius(a.owner.baseMm)"
                                fill="none"
                                :stroke="sideColour(a.owner.side)"
                                stroke-width="0.08"
                                stroke-dasharray="0.25 0.2"
                            />
                        </g>
                        <text
                            :x="a.origin.x"
                            :y="a.origin.y - a.radius - 0.3"
                            text-anchor="middle"
                            font-size="0.9"
                            font-weight="600"
                            :fill="sideColour(a.owner.side)"
                            stroke="hsl(var(--background))"
                            stroke-width="0.2"
                            paint-order="stroke"
                        >
                            {{ fmt(a.aura.range) }}"
                        </text>
                    </g>
                    <g
                        v-for="t in tokens"
                        :key="t.id"
                        :opacity="dimmed(elevationOf(t)) ? 0.25 : t.killed ? 0.35 : t.info?.activated ? 0.7 : 1"
                        :data-testid="`token-${t.id}`"
                    >
                        <circle
                            v-if="factionOf(t)"
                            :cx="t.x"
                            :cy="t.y"
                            :r="baseRadius(t.baseMm) + 0.1"
                            fill="none"
                            :stroke="sideColour(t.side)"
                            stroke-width="0.16"
                            pointer-events="none"
                            :data-testid="`side-ring-${t.id}`"
                        />
                        <circle
                            :cx="t.x"
                            :cy="t.y"
                            :r="baseRadius(t.baseMm)"
                            :fill="baseFill(t)"
                            fill-opacity="0.9"
                            :stroke="
                                t.id === selectedId
                                    ? 'hsl(var(--foreground))'
                                    : problems.get(t.id)?.some((p) => p.kind === 'overlap' || p.kind === 'impassable')
                                      ? 'hsl(0 85% 60%)'
                                      : caught.has(t.id)
                                        ? 'hsl(45 95% 55%)'
                                        : 'white'
                            "
                            :data-problem="problems.get(t.id)?.length ? 'true' : undefined"
                            :stroke-width="t.id === selectedId ? 0.16 : 0.08"
                            :stroke-dasharray="t.under ? '0.3 0.2' : t.marker && t.id !== selectedId ? '0.35 0.25' : undefined"
                            :data-under="t.under ? 'true' : undefined"
                            :data-marker="t.marker ? 'true' : undefined"
                        />
                        <g v-if="hoverId === t.id" pointer-events="none" :data-testid="`hover-ring-${t.id}`">
                            <circle
                                :cx="t.x"
                                :cy="t.y"
                                :r="baseRadius(t.baseMm) + 0.28"
                                fill="none"
                                stroke="hsl(45 95% 55%)"
                                stroke-width="0.16"
                                stroke-dasharray="0.45 0.28"
                            />
                        </g>
                        <!-- health: a ring just inside the base's edge, one segment per wound (a smooth ring past 12), so nothing sits outside the base -->
                        <g
                            v-if="t.info?.maxHealth && !t.marker"
                            pointer-events="none"
                            :data-testid="`hp-${t.id}`"
                            :data-health="`${t.info.health ?? t.info.maxHealth}/${t.info.maxHealth}`"
                        >
                            <template v-if="t.info.maxHealth <= HEALTH_PIPS_MAX">
                                <path
                                    v-for="(a, i) in healthArcs(t)"
                                    :key="i"
                                    :d="a.d"
                                    fill="none"
                                    stroke-linecap="butt"
                                    :stroke-width="healthRing(t).width"
                                    :stroke="a.on ? healthColour(t) : 'rgb(0 0 0 / 0.5)'"
                                />
                            </template>
                            <template v-else>
                                <circle
                                    :cx="t.x"
                                    :cy="t.y"
                                    :r="healthRing(t).r"
                                    fill="none"
                                    :stroke-width="healthRing(t).width"
                                    stroke="rgb(0 0 0 / 0.5)"
                                />
                                <template v-if="smoothHealth(t)">
                                    <circle
                                        v-if="smoothHealth(t)!.full"
                                        :cx="t.x"
                                        :cy="t.y"
                                        :r="healthRing(t).r"
                                        fill="none"
                                        :stroke-width="healthRing(t).width"
                                        :stroke="healthColour(t)"
                                    />
                                    <path
                                        v-else
                                        :d="smoothHealth(t)!.d"
                                        fill="none"
                                        stroke-linecap="butt"
                                        :stroke-width="healthRing(t).width"
                                        :stroke="healthColour(t)"
                                    />
                                </template>
                            </template>
                        </g>
                        <g v-if="showBadges && badgesOf(t.info).chips.length" pointer-events="none" :data-testid="`badges-${t.id}`">
                            <g v-for="(c, i) in badgesOf(t.info).chips" :key="c.label">
                                <circle
                                    :cx="t.x + (i - (badgesOf(t.info).chips.length - 1) / 2) * 0.85"
                                    :cy="t.y - baseRadius(t.baseMm) - 0.4"
                                    :r="badgeR(t) + 0.04"
                                    :fill="c.colour"
                                    stroke="white"
                                    stroke-width="0.06"
                                />
                                <text
                                    :x="t.x + (i - (badgesOf(t.info).chips.length - 1) / 2) * 0.85"
                                    :y="t.y - baseRadius(t.baseMm) - 0.4 + 0.17"
                                    text-anchor="middle"
                                    font-size="0.48"
                                    fill="white"
                                    font-weight="700"
                                >
                                    {{ c.label }}
                                </text>
                            </g>
                        </g>
                        <g v-if="showBadges && t.info?.activated" pointer-events="none" :data-testid="`activated-${t.id}`">
                            <circle
                                :cx="t.x - baseRadius(t.baseMm) * 0.8"
                                :cy="t.y - baseRadius(t.baseMm) * 0.8"
                                :r="badgeR(t)"
                                fill="hsl(var(--background))"
                                stroke="hsl(var(--foreground) / 0.7)"
                                stroke-width="0.06"
                            />
                            <path
                                :d="`M${t.x - baseRadius(t.baseMm) * 0.8 - 0.16} ${t.y - baseRadius(t.baseMm) * 0.8} l0.12 0.14 l0.22 -0.28`"
                                fill="none"
                                stroke="hsl(145 60% 35%)"
                                stroke-width="0.09"
                                stroke-linecap="round"
                            />
                        </g>
                        <g v-if="showBadges && engagement.get(t.id)" pointer-events="none" :data-testid="`engaged-${t.id}`">
                            <circle
                                :cx="t.x - baseRadius(t.baseMm) * 0.8"
                                :cy="t.y + baseRadius(t.baseMm) * 0.8"
                                :r="badgeR(t)"
                                fill="hsl(0 75% 45%)"
                                stroke="white"
                                stroke-width="0.06"
                            />
                            <path
                                :d="`M${t.x - baseRadius(t.baseMm) * 0.8 - 0.14} ${t.y + baseRadius(t.baseMm) * 0.8 - 0.14} l0.28 0.28 m0 -0.28 l-0.28 0.28`"
                                fill="none"
                                stroke="white"
                                stroke-width="0.08"
                                stroke-linecap="round"
                            />
                        </g>
                        <g v-if="flipBadge(t.id)" pointer-events="none" :data-testid="`fate-badge-${t.id}`">
                            <rect
                                :x="t.x + baseRadius(t.baseMm) + 0.2"
                                :y="t.y - 0.6"
                                width="1.9"
                                height="1.2"
                                rx="0.25"
                                :fill="flipBadge(t.id)!.fill"
                                stroke="white"
                                stroke-width="0.07"
                            />
                            <text
                                :x="t.x + baseRadius(t.baseMm) + 0.2 + (flipBadge(t.id)!.glyph ? 0.62 : 0.95)"
                                :y="t.y + 0.27"
                                text-anchor="middle"
                                font-size="0.8"
                                fill="white"
                                font-weight="700"
                            >
                                {{ flipBadge(t.id)!.text }}
                            </text>
                            <text
                                v-if="flipBadge(t.id)!.glyph"
                                :x="t.x + baseRadius(t.baseMm) + 0.2 + 1.4"
                                :y="t.y + 0.3"
                                text-anchor="middle"
                                font-size="0.85"
                                fill="white"
                                font-family="M4E-Symbols"
                            >
                                {{ flipBadge(t.id)!.glyph }}
                            </text>
                        </g>
                        <text
                            :x="t.x"
                            :y="t.y + (healthNumber(t) !== null ? -0.02 : 0.3)"
                            text-anchor="middle"
                            font-size="0.85"
                            fill="white"
                            font-weight="600"
                            stroke="rgb(0 0 0 / 0.55)"
                            stroke-width="0.14"
                            paint-order="stroke"
                            pointer-events="none"
                        >
                            {{ tokenLabel(t) }}{{ elevationOf(t) > 0 ? `+${elevationOf(t)}` : '' }}
                        </text>
                        <text
                            v-if="healthNumber(t) !== null"
                            :x="t.x"
                            :y="t.y + 0.5"
                            text-anchor="middle"
                            font-size="0.5"
                            fill="white"
                            font-weight="700"
                            stroke="rgb(0 0 0 / 0.65)"
                            stroke-width="0.12"
                            paint-order="stroke"
                            pointer-events="none"
                            :data-testid="`hp-number-${t.id}`"
                        >
                            {{ healthNumber(t) }}
                        </text>
                    </g>
                    <!-- where a replacement may go: the dashed ring is where its centre can be, base to base with where the original stood -->
                    <g
                        v-for="t in tokens.filter((x) => x.id === placingId && anchorOf(x.id))"
                        :key="`contact-${t.id}`"
                        pointer-events="none"
                        data-testid="contact-ring"
                    >
                        <circle
                            :cx="anchorOf(t.id)!.x"
                            :cy="anchorOf(t.id)!.y"
                            :r="baseRadius(anchorOf(t.id)!.baseMm)"
                            fill="hsl(45 95% 55% / 0.12)"
                            stroke="hsl(45 95% 55%)"
                            stroke-width="0.06"
                        />
                        <circle
                            :cx="anchorOf(t.id)!.x"
                            :cy="anchorOf(t.id)!.y"
                            :r="baseRadius(anchorOf(t.id)!.baseMm) + baseRadius(t.baseMm)"
                            fill="none"
                            stroke="hsl(45 95% 55%)"
                            stroke-width="0.14"
                            stroke-dasharray="0.4 0.25"
                        />
                    </g>
                    <!-- where a summoned model may go: its centre inside the dashed line, within the distance of the model that summoned it -->
                    <g
                        v-for="t in tokens.filter((x) => x.id === placingId && !anchorOf(x.id) && ruleOf(x.id) && tokenById(ruleOf(x.id)!.from))"
                        :key="`summon-${t.id}`"
                        pointer-events="none"
                        data-testid="summon-ring"
                    >
                        <circle
                            :cx="tokenById(ruleOf(t.id)!.from)!.x"
                            :cy="tokenById(ruleOf(t.id)!.from)!.y"
                            :r="baseRadius(tokenById(ruleOf(t.id)!.from)!.baseMm) + ruleOf(t.id)!.range + baseRadius(t.baseMm)"
                            fill="hsl(45 95% 55% / 0.06)"
                            stroke="hsl(45 95% 55%)"
                            stroke-width="0.14"
                            stroke-dasharray="0.4 0.25"
                        />
                    </g>
                    <!-- where a Remains marker may go: its centre inside the dashed line, within 1" of the killed model's base -->
                    <g
                        v-for="r in remainsFor.filter((x) => x.id === remainsArmed)"
                        :key="`within-${r.id}`"
                        pointer-events="none"
                        data-testid="remains-ring"
                    >
                        <circle
                            :cx="r.x"
                            :cy="r.y"
                            :r="baseRadius(r.baseMm)"
                            fill="hsl(45 95% 55% / 0.12)"
                            stroke="hsl(45 95% 55%)"
                            stroke-width="0.06"
                        />
                        <circle
                            :cx="r.x"
                            :cy="r.y"
                            :r="baseRadius(r.baseMm) + 1 + baseRadius(remainsMarker?.base_mm ?? 30)"
                            fill="hsl(45 95% 55% / 0.06)"
                            stroke="hsl(45 95% 55%)"
                            stroke-width="0.14"
                            stroke-dasharray="0.4 0.25"
                        />
                    </g>
                    <g v-if="trail && trailLength >= 0.05" pointer-events="none" data-testid="move-trail">
                        <circle
                            v-if="trail.kind === 'place'"
                            :cx="trail.from.x"
                            :cy="trail.from.y"
                            :r="placeReach(trail.range, trail.baseMm, trail.baseMm, trail.whole)"
                            fill="hsl(265 70% 60% / 0.07)"
                            stroke="hsl(265 70% 60%)"
                            stroke-width="0.08"
                            stroke-dasharray="0.3 0.3"
                            data-testid="place-reach"
                        />
                        <circle
                            v-else-if="walkRing"
                            :cx="walkRing.x"
                            :cy="walkRing.y"
                            :r="walkRing.r"
                            fill="hsl(145 65% 42% / 0.06)"
                            stroke="hsl(145 65% 42%)"
                            stroke-width="0.08"
                            stroke-dasharray="0.6 0.4"
                            data-testid="walk-reach"
                        />
                        <circle
                            :cx="trail.from.x"
                            :cy="trail.from.y"
                            :r="baseRadius(trail.baseMm)"
                            fill="none"
                            stroke="hsl(var(--foreground))"
                            stroke-width="0.07"
                            stroke-dasharray="0.2 0.2"
                            opacity="0.6"
                        />
                        <polyline
                            :points="trailPoints.map((q) => `${q.x},${q.y}`).join(' ')"
                            fill="none"
                            stroke="hsl(var(--foreground))"
                            stroke-width="0.1"
                            stroke-dasharray="0.4 0.3"
                            opacity="0.7"
                            data-testid="move-trail-path"
                        />
                        <circle
                            v-for="(q, i) in trailPoints.slice(1, -1)"
                            :key="i"
                            :cx="q.x"
                            :cy="q.y"
                            r="0.2"
                            fill="hsl(var(--foreground))"
                            opacity="0.8"
                        />
                        <text
                            v-for="(leg, i) in trailLegLabels"
                            :key="`leg-${i}`"
                            :x="leg.x"
                            :y="leg.y"
                            text-anchor="middle"
                            font-size="0.7"
                            fill="hsl(var(--foreground))"
                            stroke="hsl(var(--background))"
                            stroke-width="0.2"
                            paint-order="stroke"
                            opacity="0.85"
                            data-testid="move-trail-leg"
                        >
                            {{ fmt(leg.length) }}"
                        </text>
                        <text
                            :x="trail.to.x"
                            :y="trail.to.y - baseRadius(trail.baseMm) - 0.5"
                            text-anchor="middle"
                            font-size="0.9"
                            font-weight="600"
                            :fill="trailCost?.walk && !trailCost.walk.within ? 'hsl(0 85% 62%)' : 'hsl(var(--foreground))'"
                            stroke="hsl(var(--background))"
                            stroke-width="0.22"
                            paint-order="stroke"
                            data-testid="move-trail-label"
                        >
                            <template v-if="trail.kind === 'place'">
                                place
                                {{
                                    fmt(
                                        placeCheck(
                                            trail.range,
                                            { x: trail.from.x, y: trail.from.y, baseMm: trail.baseMm },
                                            { x: trail.to.x, y: trail.to.y, baseMm: trail.baseMm },
                                            trail.whole,
                                        ).gap,
                                    )
                                }}" of {{ fmt(trail.range) }}"
                            </template>
                            <template v-else>
                                {{ fmt(trailCost?.cost.total ?? trailLength) }}"{{ trail.sp !== undefined ? ` of ${trail.sp}"` : '' }}
                            </template>
                        </text>
                    </g>
                    <g v-if="outline && outline.length" pointer-events="none" data-testid="outline-preview">
                        <polyline
                            :points="outline.map((q) => `${q.x},${q.y}`).join(' ')"
                            fill="hsl(45 95% 55% / 0.18)"
                            stroke="hsl(45 95% 55%)"
                            stroke-width="0.15"
                            stroke-dasharray="0.4 0.3"
                        />
                        <circle
                            v-for="(q, i) in outline"
                            :key="i"
                            :cx="q.x"
                            :cy="q.y"
                            :r="i === 0 ? 0.45 : 0.28"
                            :fill="i === 0 ? 'hsl(45 95% 55%)' : 'hsl(var(--background))'"
                            stroke="hsl(45 95% 55%)"
                            stroke-width="0.1"
                        />
                    </g>
                    <!-- The two side lines already show the check when they are drawn; a third line between the centres only looked like a stray one. -->
                    <g v-if="sightForward && sightBack && !sideLines.length" pointer-events="none" data-testid="sight-line">
                        <line
                            v-if="sightBack.clear && !sightForward.clear"
                            :x1="sightBack.line.to.x"
                            :y1="sightBack.line.to.y"
                            :x2="sightBack.line.from.x"
                            :y2="sightBack.line.from.y"
                            stroke="hsl(145 65% 42%)"
                            stroke-width="0.14"
                            stroke-dasharray="0.4 0.3"
                        />
                        <line
                            :x1="sightForward.line.from.x"
                            :y1="sightForward.line.from.y"
                            :x2="sightForward.line.to.x"
                            :y2="sightForward.line.to.y"
                            :stroke="sightForward.clear ? 'hsl(145 65% 42%)' : 'hsl(0 75% 55%)'"
                            stroke-width="0.14"
                            :stroke-dasharray="sightForward.clear ? undefined : '0.4 0.3'"
                        />
                    </g>
                    <g v-if="sideLines.length" pointer-events="none" data-testid="side-lines">
                        <template v-for="l in sideLines" :key="l.side">
                            <circle
                                v-for="m in l.reached"
                                :key="`${l.side}-${m.id}`"
                                :cx="m.token.x"
                                :cy="m.token.y"
                                :r="baseRadius(m.token.baseMm) + 0.22"
                                fill="none"
                                :stroke="m.blockedBy ? 'hsl(0 75% 55%)' : 'hsl(145 65% 42%)'"
                                stroke-width="0.12"
                                :stroke-dasharray="m.blockedBy ? '0.3 0.2' : undefined"
                                :data-testid="m.blockedBy ? 'side-hidden' : 'side-seen'"
                            />
                            <line
                                :x1="l.start.x"
                                :y1="l.start.y"
                                :x2="(l.trace.stopAt ?? l.end).x"
                                :y2="(l.trace.stopAt ?? l.end).y"
                                stroke="hsl(145 65% 42%)"
                                stroke-width="0.16"
                                stroke-linecap="round"
                                :data-testid="`side-line-${l.side}`"
                            />
                            <template v-if="l.trace.stopAt">
                                <line
                                    :x1="l.trace.stopAt.x"
                                    :y1="l.trace.stopAt.y"
                                    :x2="l.end.x"
                                    :y2="l.end.y"
                                    stroke="hsl(0 75% 55%)"
                                    stroke-width="0.12"
                                    stroke-dasharray="0.4 0.3"
                                />
                                <circle :cx="l.trace.stopAt.x" :cy="l.trace.stopAt.y" r="0.22" fill="hsl(0 75% 55%)" data-testid="side-stop" />
                            </template>
                            <circle :cx="l.start.x" :cy="l.start.y" r="0.2" fill="hsl(145 65% 42%)" />
                        </template>
                        <circle v-if="sight?.aim" :cx="sight.aim.x" :cy="sight.aim.y" r="0.25" fill="hsl(var(--foreground))" />
                    </g>
                    <g
                        v-if="aimOffscreen"
                        pointer-events="none"
                        :transform="`translate(${aimOffscreen.x} ${aimOffscreen.y}) rotate(${aimOffscreen.angle})`"
                        data-testid="aim-offscreen-arrow"
                    >
                        <polygon
                            :points="`${aimOffscreen.size},0 ${-aimOffscreen.size},${aimOffscreen.size * 0.8} ${-aimOffscreen.size * 0.4},0 ${-aimOffscreen.size},${-aimOffscreen.size * 0.8}`"
                            fill="hsl(145 65% 42%)"
                            stroke="hsl(var(--background))"
                            :stroke-width="aimOffscreen.size * 0.15"
                        />
                    </g>
                    <g v-if="ruler" pointer-events="none">
                        <line
                            :x1="ruler.from.x"
                            :y1="ruler.from.y"
                            :x2="ruler.to.x"
                            :y2="ruler.to.y"
                            stroke="hsl(var(--foreground))"
                            stroke-width="0.14"
                            stroke-dasharray="0.5 0.3"
                        />
                        <circle :cx="ruler.from.x" :cy="ruler.from.y" r="0.25" fill="hsl(var(--foreground))" />
                        <circle :cx="ruler.to.x" :cy="ruler.to.y" r="0.25" fill="hsl(var(--foreground))" />
                        <text
                            v-if="measure"
                            :x="(ruler.from.x + ruler.to.x) / 2 + 0.4"
                            :y="(ruler.from.y + ruler.to.y) / 2 - 0.4"
                            font-size="1.1"
                            font-weight="700"
                            fill="hsl(var(--foreground))"
                            stroke="hsl(var(--background))"
                            stroke-width="0.25"
                            paint-order="stroke"
                        >
                            {{ fmt(measure.inches) }}"
                        </text>
                    </g>
                </svg>
            </div>
            <p id="board-keys" class="mt-1 hidden text-center text-xs text-muted-foreground group-focus-within/board:block" data-testid="board-keys">
                Keyboard: [ and ] choose a model, the arrow keys move it 0.5" (Shift 0.1", Alt 2"), Esc lets go. On the Terrain tab the same keys move
                a piece, and , . turn it.
            </p>
            <div class="mt-2 flex items-start gap-2">
                <p class="min-h-5 flex-1 text-center text-sm" role="status" data-testid="tabletop-note">{{ note }}</p>
            </div>
        </div>

        <div
            class="space-y-4 max-lg:[&_button]:min-h-11 max-lg:[&_input:not([type=checkbox])]:min-h-11 max-lg:[&_select]:min-h-11"
            data-testid="side-column"
        >
            <a
                v-if="crewRows.length && activeTab === 'move'"
                href="#tabletop-models"
                class="sr-only rounded-md bg-background px-3 py-2 text-sm font-medium focus:not-sr-only focus:ring-2 focus:ring-primary"
                data-testid="skip-to-models"
                @click.prevent="($event.target as HTMLElement).ownerDocument.getElementById('tabletop-models')?.focus()"
                >Skip to the models</a
            >
            <!-- What to work on: the models, the terrain, or the table itself (its saved copies and its settings) -->
            <div class="flex items-center gap-1 max-lg:sticky max-lg:top-1 max-lg:z-20" data-testid="tab-row">
                <div
                    role="tablist"
                    aria-label="What to work on"
                    :class="['grid flex-1 gap-1 rounded-lg bg-muted p-1 max-lg:shadow-md', readOnly ? 'grid-cols-2' : 'grid-cols-3']"
                    data-testid="mode-tabs"
                    @keydown.right.prevent="stepTab(1)"
                    @keydown.left.prevent="stepTab(-1)"
                >
                    <button
                        v-for="t in tabs"
                        :key="t.id"
                        type="button"
                        role="tab"
                        :aria-selected="activeTab === t.id"
                        :tabindex="activeTab === t.id ? 0 : -1"
                        :class="tabClass(activeTab === t.id)"
                        :data-testid="`mode-${t.id === 'move' ? 'move' : t.id}`"
                        @click="openTab(t.id)"
                    >
                        <component :is="t.icon" class="mr-1.5 size-4 max-sm:hidden" />{{ t.label }}
                    </button>
                </div>
                <Button
                    size="icon"
                    variant="ghost"
                    class="size-10 shrink-0"
                    aria-label="Help and shortcuts"
                    title="Help and shortcuts"
                    data-testid="tabletop-help-open"
                    @click="openHelp(mode === 'terrain' ? 'terrain' : 'start')"
                    ><CircleHelp class="size-5"
                /></Button>
            </div>
            <div
                v-for="r in remainsFor"
                :key="`remains-${r.id}`"
                class="space-y-1.5 rounded-md border border-amber-500/50 bg-amber-500/10 p-2 text-sm"
                role="alert"
                data-testid="remains-prompt"
            >
                <p>
                    {{ r.name }} was killed. Its controller makes a friendly 30mm Remains marker anywhere within 1" of it, before it is removed, so
                    beside its base and not under it (peons do not).
                    {{ remainsArmed === r.id ? 'Tap the table inside the dashed line.' : 'Choose the spot, or let it be placed for you.' }}
                </p>
                <div class="flex flex-wrap gap-1">
                    <Button
                        size="sm"
                        :variant="remainsArmed === r.id ? 'default' : 'outline'"
                        :aria-pressed="remainsArmed === r.id"
                        data-testid="remains-choose"
                        @click="remainsArmed = remainsArmed === r.id ? null : r.id"
                        >{{ remainsArmed === r.id ? 'Cancel' : 'Choose the spot' }}</Button
                    >
                    <Button size="sm" variant="outline" data-testid="remains-place" @click="placeRemains(r)">Place it for me</Button>
                    <Button size="sm" variant="ghost" data-testid="remains-skip" @click="skipRemains(r.id)">Skip</Button>
                </div>
            </div>

            <div v-show="activeTab === 'move'" class="space-y-3" data-testid="board-tools">
                <div
                    v-if="mode === 'move' && moveKind !== 'walk'"
                    class="rounded-md border p-3 text-sm text-muted-foreground"
                    data-testid="move-kind-help"
                >
                    <template v-if="moveKind === 'jump'"
                        >Jump: drag the model across a gap. It may end at the same height or up to 1" lower, never higher, and nothing between may be
                        taller than where it began. The distance counts as movement, so it is measured against its Walk. Use Walk and Step to reach
                        the edge first.</template
                    >
                    <template v-else-if="moveKind === 'place'"
                        >Place: drag a model to where it may be put down. It is picked up, so terrain and sight in between are ignored and nothing is
                        spent climbing or falling, but it has to fit where it lands and be within the range, measured from the closest point of its
                        base. A place that cannot fit does not happen. If it begins or ends in terrain, it counts as having moved through
                        it.</template
                    >
                    <template v-else-if="moveKind === 'charge'"
                        >Charge: once per activation, and not while engaged. Move up to its Sp in a straight line (terrain costs what it does for a
                        Walk). It may then declare a melee action that does not count against its action limit.</template
                    >
                    <template v-else
                        >Flight: the model walks but ignores terrain, so climbing costs no movement and a drop does no damage. Only the ground
                        distance counts against its Walk.</template
                    >
                    <div v-if="moveKind === 'place'" class="mt-2 flex flex-wrap items-center gap-2 text-foreground" data-testid="place-controls">
                        <label class="flex items-center gap-1 text-sm max-sm:order-last"
                            >Within
                            <input
                                v-model.number="placeRange"
                                type="number"
                                min="0.5"
                                max="36"
                                step="0.5"
                                class="w-16 rounded-md border bg-background px-2 py-1.5 text-center"
                                aria-label="Place range in inches"
                                data-testid="place-range"
                            />"</label
                        >
                        <Button
                            size="sm"
                            class="max-sm:order-last"
                            :variant="placeWhole ? 'default' : 'outline'"
                            :aria-pressed="placeWhole"
                            title="Some effects need the whole base within range rather than a single point of it"
                            data-testid="place-whole"
                            @click="placeWhole = !placeWhole"
                            >Whole base</Button
                        >
                    </div>
                </div>
                <div
                    v-if="mode === 'move' && stepMove"
                    class="flex flex-wrap items-center gap-2 rounded-md border p-3 text-sm"
                    data-testid="step-help"
                >
                    <span class="w-full text-muted-foreground"
                        >Step move: drag a model, let go, then drag the same model again. The ring shows what is left of its Walk, and a leg stops at
                        its edge. Escape puts the model back where it began.</span
                    >
                    <Button v-if="trail" size="sm" variant="outline" data-testid="step-undo" @click="undoStep">Undo step</Button>
                    <Button v-if="trail" size="sm" variant="outline" data-testid="step-cancel" @click="cancelMove">Cancel move</Button>
                    <Button v-if="trail" size="sm" variant="outline" data-testid="step-reset" @click="trail = null">Start over</Button>
                </div>
                <div v-if="guidedId" class="space-y-2 rounded-md border p-3 text-sm" data-testid="guide-panel">
                    <div class="flex items-center justify-between gap-2">
                        <span class="font-medium">Sight guidelines on for {{ tokens.find((t) => t.id === guidedId)?.name }}</span>
                        <Button
                            :variant="mode === 'sight' ? 'default' : 'outline'"
                            size="sm"
                            title="Aim the sight guidelines: press on the table, or on the model or marker they should point at"
                            data-testid="mode-sight"
                            @click="mode = 'sight'"
                            ><Eye class="mr-1 size-4" />Aim</Button
                        >
                    </div>
                    <p v-if="mode === 'sight'" class="text-muted-foreground" data-testid="guide-hint">
                        Press or drag on the board to aim {{ tokens.find((t) => t.id === guidedId)?.name }}'s guidelines, or press a model or marker
                        to point at it. The Models tab goes back to moving models.
                    </p>
                    <p v-if="aimOffscreen" class="flex flex-wrap items-center gap-2 text-muted-foreground" role="status" data-testid="aim-offscreen">
                        The guidelines are aimed at something off the part of the table you can see (the arrow at the edge points to it).
                        <Button size="sm" variant="outline" data-testid="aim-show" @click="showAim">Show it</Button>
                    </p>
                    <div v-if="sideLines.length" data-testid="side-readout">
                        <div v-for="l in sideLines" :key="l.side" data-testid="side-summary">
                            <span class="font-semibold capitalize">{{ l.side }} side:</span> {{ describeSide(l) }}
                        </div>
                        <details class="mt-1 text-muted-foreground">
                            <summary class="cursor-pointer text-xs">How to read the lines</summary>
                            <p class="mt-1 text-xs">
                                Green is clear; red dashes start where terrain or an enemy at least as tall as the model stops the line. Rings show
                                which models a line reaches. Cover and concealment come from the model-to-model check below.
                            </p>
                        </details>
                    </div>
                </div>
                <div
                    v-if="sightForward && sightBack && sightFrom && sightTo"
                    class="space-y-1 rounded-md border p-3 text-sm"
                    data-testid="sight-readout"
                >
                    <div
                        :class="
                            sightForward.clear ? 'font-semibold text-green-600 dark:text-green-400' : 'font-semibold text-red-600 dark:text-red-400'
                        "
                    >
                        {{ describeSight(sightForward, sightFrom.name, sightTo.name) }}
                    </div>
                    <div class="text-muted-foreground">{{ describeSight(sightBack, sightTo.name, sightFrom.name) }}</div>
                    <div class="text-muted-foreground">
                        Effective Sz: {{ sightFrom.name }} {{ sightForward.initiator.sz + sightForward.initiator.elevation }}, {{ sightTo.name }}
                        {{ sightForward.target.sz + sightForward.target.elevation }}. Terrain heights are compared with these.
                        <span v-if="unknownSz">A model without a Sz is counted as Sz 1.</span>
                    </div>
                </div>
                <div v-if="measure" class="rounded-md border p-3 text-sm" data-testid="ruler-readout">
                    <div class="font-semibold">
                        {{ fmt(measure.inches) }}" <span class="font-normal text-muted-foreground">horizontal, edge to edge</span>
                    </div>
                    <div v-if="measure.rise !== null" class="text-muted-foreground">
                        Elevation difference {{ measure.rise > 0 ? '+' : '' }}{{ measure.rise }}"<span v-if="measure.cover"
                            >. The higher model gets cover.</span
                        >
                    </div>
                </div>
            </div>
            <div v-show="activeTab !== 'table'" data-testid="fate-wrap">
                <slot name="fate" :terrain="mode === 'terrain'" />
            </div>

            <Teleport to="body" :disabled="!terrainSheet">
                <div
                    v-if="mode === 'terrain'"
                    :class="
                        terrainSheet
                            ? 'fixed inset-x-0 bottom-14 z-40 flex max-h-[40vh] flex-col rounded-t-xl border border-b-0 bg-background shadow-[0_-8px_24px_rgba(0,0,0,0.25)]'
                            : ''
                    "
                    :data-testid="terrainSheet ? 'terrain-sheet' : undefined"
                    :role="terrainSheet ? 'region' : undefined"
                    :aria-label="terrainSheet ? 'Terrain editor' : undefined"
                    @keydown.escape.stop="terrainSheet && openTab('move')"
                >
                    <div v-if="terrainSheet" class="flex items-center justify-between gap-2 border-b px-3 py-1.5">
                        <span class="flex items-center gap-2 text-sm font-semibold"><Mountain class="size-4" />Terrain</span>
                        <Button size="sm" variant="ghost" data-testid="terrain-sheet-done" @click="openTab('move')">Done</Button>
                    </div>
                    <div :class="terrainSheet ? 'overflow-y-auto overscroll-contain p-3' : ''">
                        <TerrainEditor
                            :pieces="terrain"
                            :selected-id="selectedPieceId"
                            :anchor="viewCentre"
                            :can-undo="undoStack.length > 0"
                            @change="commitTerrain($event)"
                            @select="selectedPieceId = $event"
                            @undo="undoTerrain"
                            @clear="commitTerrain([])"
                            :terrain-images="terrainImages"
                            :zones="zones"
                            :inside-shown="!!selectedPiece && cutaway(selectedPiece)"
                            @toggle-inside="toggleInside"
                            :active-surface-id="activeSurface?.id ?? null"
                            :vertex-selected="pickedVertex !== null"
                            @select-surface="activeSurfaceId = $event"
                            @remove-corner="removePickedCorner"
                            :drawing="outline ? outline.length : null"
                            @draw="startOutline"
                            @finish-draw="finishOutline"
                            @cancel-draw="cancelOutline"
                            @undo-corner="undoCorner"
                        />
                    </div>
                </div>
            </Teleport>
            <div v-show="activeTab === 'table'" role="tabpanel" aria-label="Table" class="space-y-4" data-testid="table-panel">
                <section class="space-y-2" aria-labelledby="table-saved-h">
                    <h3 id="table-saved-h" class="text-sm font-semibold">Saved tables</h3>
                    <div v-if="layouts !== undefined || layoutTarget" class="flex flex-wrap gap-2" data-testid="table-actions">
                        <Button
                            v-if="layouts !== undefined"
                            size="sm"
                            variant="outline"
                            class="flex-1"
                            :disabled="readOnly"
                            data-testid="table-load"
                            @click="loadOpen = true"
                            ><FolderOpen class="mr-1.5 size-4" />Load table</Button
                        >
                        <Button v-if="layoutTarget" size="sm" variant="outline" class="flex-1" data-testid="table-save" @click="saveOpen = true"
                            ><Save class="mr-1.5 size-4" />Save table</Button
                        >
                    </div>
                </section>
                <section class="space-y-2 rounded-md border" aria-labelledby="table-surface-h">
                    <div class="flex items-center justify-between gap-2 p-3 pb-0 text-sm">
                        <h3 id="table-surface-h" class="font-semibold">Surface and zones</h3>
                        <span class="min-w-0 truncate text-muted-foreground" data-testid="table-summary">{{ tableSummary }}</span>
                    </div>

                    <div class="space-y-3 border-t p-3">
                        <label v-if="tableImages?.length" class="block text-sm">
                            <span class="mb-1 block font-medium">Table surface</span>
                            <select
                                :value="tableChoice?.key ?? ''"
                                class="w-full rounded-md border bg-background px-2 py-1.5"
                                :disabled="readOnly"
                                data-testid="table-select"
                                @change="chooseTable(($event.target as HTMLSelectElement).value)"
                            >
                                <option v-for="t in tableImages" :key="t.key" :value="t.key">{{ t.name }}</option>
                            </select>
                        </label>
                        <label v-if="levels.length > 2" class="block text-sm">
                            <span class="mb-1 block font-medium">Level shown</span>
                            <select v-model="level" class="w-full rounded-md border bg-background px-2 py-1.5" data-testid="level-select">
                                <option value="all">All levels</option>
                                <option v-for="l in levels" :key="l" :value="l">{{ l === 0 ? 'Table only' : `Up to Ht ${l}` }}</option>
                            </select>
                        </label>
                        <div class="space-y-2">
                            <label class="block text-sm">
                                <span class="mb-1 block font-medium">Deployment</span>
                                <select
                                    v-model="deployment"
                                    class="w-full rounded-md border bg-background px-2 py-1.5"
                                    data-testid="deployment-select"
                                    @change="deploymentVariant === undefined && (variant = 0)"
                                >
                                    <option value="none">None</option>
                                    <option v-for="d in DEPLOYMENT_KINDS" :key="d.value" :value="d.value">{{ d.label }}</option>
                                </select>
                            </label>
                            <label
                                class="flex min-h-10 cursor-pointer items-center gap-2 text-sm"
                                title="The two centre axes and the middle of each 18-inch quarter"
                            >
                                <input v-model="tableLines" type="checkbox" class="size-6 shrink-0" data-testid="table-lines-toggle" />
                                <span>Show table lines <span class="text-muted-foreground">(centre axes and quarter centres)</span></span>
                            </label>
                            <label
                                class="flex min-h-10 cursor-pointer items-center gap-2 text-sm"
                                title="Token chips, the activation tick and the engaged mark on each base"
                            >
                                <input v-model="showBadges" type="checkbox" class="size-6 shrink-0" data-testid="badges-toggle" />
                                <span>Show status badges <span class="text-muted-foreground">(tokens, activated, engaged)</span></span>
                            </label>
                            <label class="flex min-h-10 cursor-pointer items-center gap-2 text-sm" title="The 'Ht 3' labels on the terrain">
                                <input v-model="showHeights" type="checkbox" class="size-6 shrink-0" data-testid="heights-toggle" />
                                <span>Show terrain heights <span class="text-muted-foreground">(Ht 1, Ht 2...)</span></span>
                            </label>
                            <label
                                class="flex min-h-10 cursor-pointer items-center gap-2 text-sm"
                                title="One line for each model in the list beside the table"
                            >
                                <input v-model="compactRows" type="checkbox" class="size-6 shrink-0" data-testid="compact-toggle" />
                                <span>Compact model list <span class="text-muted-foreground">(one line each)</span></span>
                            </label>
                            <label v-if="variants.length" class="block text-sm">
                                <span class="mb-1 block font-medium">Which side</span>
                                <select
                                    :value="variant"
                                    class="w-full rounded-md border bg-background px-2 py-1.5"
                                    data-testid="deployment-variant"
                                    @change="chooseVariant(Number(($event.target as HTMLSelectElement).value))"
                                >
                                    <option v-for="v in variants" :key="v.value" :value="v.value">{{ v.label }}</option>
                                </select>
                            </label>
                        </div>

                        <Button
                            v-if="anyEnterable"
                            :variant="showAllInsides ? 'default' : 'outline'"
                            size="sm"
                            :aria-pressed="showAllInsides"
                            title="Fade the roofs of enterable buildings so the inside shows"
                            data-testid="show-insides"
                            @click="showAllInsides = !showAllInsides"
                            >Show insides of buildings</Button
                        >
                        <Button v-if="sandbox" size="sm" variant="ghost" class="text-muted-foreground" data-testid="reset-table" @click="resetTable"
                            >Reset table</Button
                        >
                    </div>
                </section>
            </div>

            <div
                v-if="activeTab === 'move' && (sandbox || (markers && markerSides.length))"
                class="space-y-2 rounded-md border p-2"
                data-testid="add-panel"
            >
                <div class="flex items-center justify-between gap-2">
                    <div class="text-sm font-medium">Add to the table</div>
                    <div class="flex items-center gap-2">
                        <select
                            v-if="sandbox"
                            v-model="newSide"
                            class="rounded-md border bg-background px-2 py-1.5 text-sm"
                            aria-label="Side for the models you add"
                            title="Which side the next model you add is on"
                            data-testid="new-side"
                        >
                            <option value="a">{{ sideNames.a }}</option>
                            <option value="b">{{ sideNames.b }}</option>
                        </select>
                        <DropdownMenu :modal="false">
                            <DropdownMenuTrigger as-child>
                                <Button size="sm" data-testid="add-menu"><Plus class="mr-1 size-4" />Add</Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end">
                                <DropdownMenuItem v-if="sandbox && characterSearchUrl" data-testid="character-open" @select="openCharacterDialog"
                                    ><User class="mr-2 size-4" />Character</DropdownMenuItem
                                >
                                <DropdownMenuItem v-if="markers && markerSides.length" data-testid="marker-open" @select="openMarkerDialog"
                                    ><MapPin class="mr-2 size-4" />Marker</DropdownMenuItem
                                >
                                <DropdownMenuItem v-if="sandbox" data-testid="add-terrain" @select="mode = 'terrain'"
                                    ><Mountain class="mr-2 size-4" />Terrain piece</DropdownMenuItem
                                >
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                </div>
                <div
                    v-if="sandbox && !tokens.some((t) => !t.marker)"
                    class="space-y-2 rounded-md border border-dashed p-3 text-sm"
                    data-testid="empty-table"
                >
                    <p class="font-medium">Start here</p>
                    <ol class="list-decimal space-y-1 pl-5 text-muted-foreground">
                        <li>Add a character: its model goes on the table at its real base size, with its Sz, Sp and health.</li>
                        <li>Drag it. The note under the board says how far it went and what it crossed.</li>
                        <li>Press Ruler, then drag from one model to another to measure.</li>
                    </ol>
                    <div class="flex flex-wrap gap-2">
                        <Button v-if="characterSearchUrl" size="sm" data-testid="empty-add" @click="openCharacterDialog"
                            ><User class="mr-1.5 size-4" />Add a character</Button
                        >
                        <Button size="sm" variant="ghost" data-testid="empty-help" @click="openHelp('start')">How the table works</Button>
                    </div>
                </div>
                <div v-if="markers && markerSides.some((s) => markersOut(s.side) > 0)" class="flex flex-wrap items-center gap-2 text-sm">
                    <span class="text-muted-foreground" data-testid="marker-count"
                        >{{ markerSides.map((s) => `${markerSides.length > 1 ? `${s.label}: ` : ''}${markersOut(s.side)}`).join(', ') }} on the
                        table</span
                    >
                </div>
            </div>
            <div
                v-if="crewRows.length && activeTab === 'move'"
                id="tabletop-models"
                tabindex="-1"
                class="space-y-3 focus:outline-none"
                data-testid="crew-list"
            >
                <template v-for="side in crewSides" :key="side.slot">
                    <div class="space-y-1">
                        <div class="text-xs font-medium text-muted-foreground">{{ side.name }}</div>
                        <div
                            v-for="m in side.living"
                            :key="m.id"
                            class="rounded-md"
                            @pointerenter="hoverId = String(m.id)"
                            @pointerleave="hoverId = null"
                            :class="selectedId === String(m.id) ? 'ring-2 ring-primary' : ''"
                            :data-testid="`crew-${m.id}`"
                        >
                            <GameCrewMemberRow
                                :member="m"
                                :editable="canEditCrew(m)"
                                :card-open="openCards.has(m.id)"
                                :is-campaign="!!isCampaign"
                                :allow-attach="!!canAttach"
                                :compact="compactRows"
                                board-mode
                                :upgrade-power-max="noUpgradePower"
                                :loot-card-by-id="noLootCard"
                                :loot-card-suit-icon="noLootCard"
                                @toggle-activated="emit('modelChange', String(m.id), { activated: !m.is_activated })"
                                @health-delta="(d: number) => crewHealth(m, (m.current_health ?? 0) + d)"
                                @health-pip="(pip: number) => crewPip(m, pip)"
                                @remove-token="
                                    (id: number) => emit('modelChange', String(m.id), { tokens: m.attached_tokens.filter((t: any) => t.id !== id) })
                                "
                                @open-tokens="openTokens(m)"
                                @replace="replaceFrom(m)"
                                @open-upgrades="emit('openUpgrades', String(m.id))"
                                @open-injury="emit('openInjury', String(m.id))"
                                @toggle-card="toggleCard(m)"
                                :options-open="selectedId === String(m.id)"
                                @toggle-options="toggleSelect(m)"
                                @preview="openCard(m)"
                            />
                            <div
                                v-if="(unplacedIds.has(String(m.id)) || anchorOf(String(m.id))) && canEditCrew(m)"
                                class="mt-1 flex flex-wrap items-center gap-2 rounded-md border border-amber-500/50 bg-amber-500/10 p-1.5 text-xs"
                                :role="anchorOf(String(m.id)) ? 'alert' : undefined"
                                data-testid="unplaced-bar"
                            >
                                <span class="min-w-0 flex-1">{{
                                    anchorOf(String(m.id))
                                        ? placingId === String(m.id)
                                            ? `Tap the table: it goes touching where ${anchorOf(String(m.id))!.name} stood.`
                                            : `Replaced ${anchorOf(String(m.id))!.name}: place it in base contact with where that model stood.`
                                        : placingId === String(m.id)
                                          ? 'Tap the table where it goes.'
                                          : ruleOf(String(m.id))
                                            ? `Summoned: it goes within ${ruleOf(String(m.id))!.range}" of ${tokenById(ruleOf(String(m.id))!.from)?.name ?? 'its summoner'}.`
                                            : 'Summoned: not on the table yet.'
                                }}</span>
                                <div
                                    v-if="!anchorOf(String(m.id)) && tokenById(m.id)"
                                    class="flex w-full flex-wrap items-center gap-2"
                                    data-testid="summon-rule"
                                >
                                    <label class="flex items-center gap-1">
                                        Summoned by
                                        <select
                                            class="rounded-md border bg-background px-1.5 py-1"
                                            :value="ruleOf(String(m.id))?.from ?? ''"
                                            data-testid="summon-from"
                                            @change="setSummoner(String(m.id), ($event.target as HTMLSelectElement).value)"
                                        >
                                            <option value="">anywhere (no limit)</option>
                                            <option v-for="s in summonersFor(tokenById(m.id)!)" :key="s.id" :value="s.id">{{ s.name }}</option>
                                        </select>
                                    </label>
                                    <label v-if="ruleOf(String(m.id))" class="flex items-center gap-1">
                                        within
                                        <input
                                            type="number"
                                            min="0.5"
                                            max="12"
                                            step="0.5"
                                            class="w-16 rounded-md border bg-background px-1.5 py-1"
                                            :value="ruleOf(String(m.id))!.range"
                                            aria-label="Inches from the summoner"
                                            data-testid="summon-range"
                                            @input="setSummonRange(String(m.id), Number(($event.target as HTMLInputElement).value))"
                                        />
                                        in.
                                    </label>
                                </div>
                                <Button
                                    size="sm"
                                    :variant="placingId === String(m.id) ? 'default' : 'outline'"
                                    :aria-pressed="placingId === String(m.id)"
                                    data-testid="place-unplaced"
                                    @click="placingId = placingId === String(m.id) ? null : String(m.id)"
                                    >{{
                                        placingId === String(m.id) ? 'Cancel' : anchorOf(String(m.id)) ? 'Choose the spot' : 'Place on the table'
                                    }}</Button
                                >
                                <Button
                                    v-if="anchorOf(String(m.id))"
                                    size="sm"
                                    variant="outline"
                                    data-testid="place-contact-auto"
                                    @click="
                                        tokens.find((t) => t.id === String(m.id)) &&
                                        placeInContact(
                                            tokens.find((t) => t.id === String(m.id))!,
                                            anchorOf(String(m.id))!,
                                        )
                                    "
                                    >Place it for me</Button
                                >
                                <Button
                                    v-if="anchorOf(String(m.id)) && !unplacedIds.has(String(m.id))"
                                    size="sm"
                                    variant="ghost"
                                    data-testid="place-contact-keep"
                                    @click="tokens.find((t) => t.id === String(m.id)) && keepWhereItIs(tokens.find((t) => t.id === String(m.id))!)"
                                    >Keep it here</Button
                                >
                            </div>
                            <div :ref="(el) => setSlot(m.id, el)"></div>
                        </div>
                        <button
                            v-for="m in side.killed"
                            :key="'killed-' + m.id"
                            type="button"
                            class="flex w-full items-center rounded-md border border-border/40 bg-muted/40 px-2 py-1.5 text-left text-sm text-muted-foreground line-through"
                            @click="toggleSelect(m)"
                        >
                            {{ m.display_name }}
                        </button>
                    </div>
                </template>
            </div>

            <Teleport :to="panelTarget ?? 'body'" :disabled="!panelTarget">
                <div
                    v-if="activeTab === 'move'"
                    class="border p-3 text-sm"
                    :class="panelTarget ? '-mt-1 rounded-b-md border-t-0 bg-muted/30' : 'rounded-md'"
                    data-testid="selected-panel"
                >
                    <div>
                        <template v-if="selected">
                            <div class="font-semibold">
                                {{ selected.name }} <span class="font-normal text-muted-foreground">{{ selected.baseMm }} mm base</span>
                            </div>
                            <div class="text-muted-foreground">
                                At {{ fmt(selected.x) }}", {{ fmt(selected.y) }}". Elevation {{ elevationOf(selected)
                                }}<span v-if="selected.under && selectedCover">
                                    {{ ' ' + (selectedCover.kind === 'building' ? 'inside' : 'under') + ' ' + selectedCover.piece.name }}</span
                                ><span v-else-if="supports.get(selected.id)?.piece"> on {{ supports.get(selected.id)?.piece?.name }}</span
                                >.
                            </div>
                            <div class="text-xs text-muted-foreground" data-testid="whereabouts">{{ whereText }}</div>
                            <ul
                                v-if="selectedChips.length"
                                class="mt-1.5 flex flex-wrap gap-1"
                                aria-label="Terrain effects on this model"
                                data-testid="terrain-chips"
                            >
                                <li
                                    v-for="chip in selectedChips"
                                    :key="chip.key"
                                    :title="chip.hint"
                                    class="rounded-full border px-2 py-0.5 text-xs font-medium"
                                    :class="CHIP_TONE[chip.key] ?? 'bg-background'"
                                    :data-testid="`terrain-chip-${chip.key}`"
                                >
                                    {{ chip.label }}
                                </li>
                            </ul>
                            <div
                                v-if="engagement.get(selected.id)?.length"
                                class="mt-1.5 flex gap-1.5 rounded-md border border-red-500/40 bg-red-500/10 px-2 py-1.5 text-xs leading-snug text-red-800 dark:text-red-200"
                                role="status"
                                data-testid="engaged-note"
                            >
                                <Swords class="mt-0.5 size-3.5 shrink-0" aria-hidden="true" />
                                <p>
                                    Engaged with
                                    {{
                                        engagement
                                            .get(selected.id)!
                                            .map((o) => o.name)
                                            .join(', ')
                                    }}: a Walk is half its Sp{{ selected.sp !== undefined ? ` (${engagedSp(selected.sp)}")` : '' }}, and it cannot
                                    declare missile actions, Interact or Charge.
                                </p>
                            </div>
                            <div
                                v-if="problems.get(selected.id)?.length"
                                class="mt-1.5 flex gap-1.5 rounded-md border border-amber-500/50 bg-amber-500/10 px-2 py-1.5 text-xs leading-snug text-amber-900 dark:text-amber-200"
                                role="status"
                                data-testid="placement-warning"
                            >
                                <TriangleAlert class="mt-0.5 size-3.5 shrink-0" aria-hidden="true" />
                                <div class="space-y-1.5">
                                    <p>
                                        {{ selected.name }}
                                        {{
                                            problems
                                                .get(selected.id)!
                                                .map((p) => p.text)
                                                .join(' and ')
                                        }}.
                                    </p>
                                    <Button
                                        v-if="illegalHere(selected) && !selected.locked"
                                        size="sm"
                                        variant="outline"
                                        data-testid="model-fix-spot"
                                        @click="moveToLegalSpot(selected)"
                                        >Move to the nearest legal spot</Button
                                    >
                                </div>
                            </div>
                            <div v-if="markerNeighbours" class="mt-2 space-y-1 rounded-md border p-2 text-xs" data-testid="marker-neighbours">
                                <label class="flex items-center gap-2">
                                    Models within
                                    <input
                                        v-model.number="markerRange"
                                        type="number"
                                        min="1"
                                        max="12"
                                        step="1"
                                        class="w-14 rounded-md border bg-background px-2 py-1"
                                        aria-label="Distance in inches from the marker's base"
                                        data-testid="marker-range"
                                    />"
                                </label>
                                <p data-testid="marker-neighbours-a">
                                    <span class="font-medium">{{ sideNames.a }}:</span>
                                    {{ markerNeighbours.a.map((t) => t.name).join(', ') || 'none' }}
                                </p>
                                <p data-testid="marker-neighbours-b">
                                    <span class="font-medium">{{ sideNames.b }}:</span>
                                    {{ markerNeighbours.b.map((t) => t.name).join(', ') || 'none' }}
                                </p>
                                <p class="text-muted-foreground">
                                    Measured from the edge of the marker's base. Peons are ignored for strategies and schemes.
                                </p>
                            </div>
                            <p v-if="selected.marker && selected.strategy" class="mt-1 text-xs text-muted-foreground" data-testid="strategy-note">
                                Strategy marker: neutral to every crew. A model may move through it but not end a move on it.
                            </p>
                            <p
                                v-if="!selected.marker || (schemeSlot(selected) !== null && !selected.locked)"
                                class="mt-3 text-2xs font-semibold uppercase tracking-wide text-muted-foreground"
                            >
                                Check
                            </p>
                            <div class="mt-1.5 flex flex-wrap gap-1" data-testid="model-actions">
                                <Button
                                    v-if="!selected.marker"
                                    size="sm"
                                    :variant="guidedId === selected.id ? 'default' : 'outline'"
                                    :aria-pressed="guidedId === selected.id"
                                    title="Draw lines from each side of the base of this model, to check line of sight, cover and concealment"
                                    data-testid="model-guide"
                                    @click="toggleGuide(selected)"
                                    ><Eye class="mr-1 size-4" />Sight guidelines</Button
                                >
                                <Button
                                    v-if="schemeSlot(selected) !== null && !selected.locked"
                                    size="sm"
                                    :variant="isSchemeTarget(selected, schemeSlot(selected)!) ? 'default' : 'outline'"
                                    :aria-pressed="isSchemeTarget(selected, schemeSlot(selected)!)"
                                    data-testid="scheme-target"
                                    @click="toggleSchemeTarget(selected, schemeSlot(selected)!)"
                                    >{{ isSchemeTarget(selected, schemeSlot(selected)!) ? "This is your scheme's" : "Use as your scheme's" }}
                                    {{ selected.marker ? 'marker' : 'model' }}</Button
                                >
                            </div>
                            <p
                                v-if="!selected.marker || (selectedCover && !selected.locked)"
                                class="mt-3 text-2xs font-semibold uppercase tracking-wide text-muted-foreground"
                            >
                                Movement
                            </p>
                            <details v-if="!selected.marker" class="group mt-1.5" :open="!!selected.ignores?.length" data-testid="ignores-section">
                                <summary
                                    class="flex cursor-pointer list-none items-center justify-between text-sm text-muted-foreground [&::-webkit-details-marker]:hidden"
                                >
                                    <span
                                        >Ignores terrain
                                        <span class="text-xs">({{ selected.ignores?.length ? selected.ignores.join(', ') : 'none' }})</span></span
                                    >
                                    <ChevronDown class="size-4 transition-transform group-open:rotate-180" />
                                </summary>
                                <div class="mt-1 flex flex-wrap gap-1">
                                    <label
                                        v-for="trait in IGNORABLE_TRAITS"
                                        :key="trait"
                                        class="flex cursor-pointer items-center gap-1 rounded-md border px-2 py-1 text-xs"
                                        :class="selected.ignores?.includes(trait) ? 'border-primary bg-primary/10' : ''"
                                        :title="IGNORE_HINTS[trait]"
                                    >
                                        <input
                                            type="checkbox"
                                            class="sr-only"
                                            :checked="!!selected.ignores?.includes(trait)"
                                            :disabled="selected.locked"
                                            :data-testid="`ignore-${trait}`"
                                            @change="toggleIgnore(selected, trait)"
                                        />
                                        {{ trait }}
                                    </label>
                                </div>
                            </details>
                            <div v-if="selectedCover && !selected.locked" class="mt-2 flex flex-wrap items-center gap-1" data-testid="under-toggle">
                                <Button
                                    size="sm"
                                    :variant="selected.under ? 'default' : 'outline'"
                                    data-testid="model-under"
                                    @click="setUnder(selected, true)"
                                    >{{ selectedCover.kind === 'building' ? 'Inside' : 'Under' }} {{ selectedCover.piece.name }}</Button
                                >
                                <Button
                                    size="sm"
                                    :variant="selected.under ? 'outline' : 'default'"
                                    data-testid="model-on"
                                    @click="setUnder(selected, false)"
                                    >{{ selectedCover.kind === 'building' ? 'On the roof' : 'On top' }}</Button
                                >
                                <Button
                                    v-if="selectedCover.kind === 'building'"
                                    size="sm"
                                    :variant="cutaway(selectedCover.piece) ? 'default' : 'outline'"
                                    :aria-pressed="cutaway(selectedCover.piece)"
                                    data-testid="model-show-inside"
                                    @click="toggleInside(selectedCover.piece.id)"
                                    >Show inside</Button
                                >
                            </div>
                            <!-- a Fate flip for this model: the result shows beside it on the table -->
                            <p v-if="fate && !selected.marker" class="mt-3 text-2xs font-semibold uppercase tracking-wide text-muted-foreground">
                                Fate
                            </p>
                            <div v-if="fate && !selected.marker" class="mt-1.5 space-y-1.5 rounded-md border p-2" data-testid="model-fate">
                                <div class="space-y-1.5">
                                    <div class="flex items-center gap-1">
                                        <span class="mr-auto font-medium">Fate flip</span>
                                        <span v-if="modelFlip" class="text-xs text-muted-foreground">{{
                                            modelFlip.flip.chosen ? 'result in' : 'open'
                                        }}</span>
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            class="px-2"
                                            aria-label="One more minus"
                                            :disabled="selected.locked || fateMod <= -3"
                                            data-testid="model-fate-minus"
                                            @click="fateMod--"
                                            ><Minus class="size-4"
                                        /></Button>
                                        <span class="min-w-8 text-center font-semibold tabular-nums" data-testid="model-fate-mod">{{
                                            fateMod > 0 ? `+${fateMod}` : fateMod
                                        }}</span>
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            class="px-2"
                                            aria-label="One more plus"
                                            :disabled="selected.locked || fateMod >= 3"
                                            data-testid="model-fate-plus"
                                            @click="fateMod++"
                                            ><Plus class="size-4"
                                        /></Button>
                                        <Button
                                            size="sm"
                                            :disabled="selected.locked || fate.busy.value"
                                            data-testid="model-fate-flip"
                                            @click="flipForModel(selected)"
                                            >Flip</Button
                                        >
                                    </div>
                                    <div v-if="modelFlip" class="space-y-1" data-testid="model-fate-flip-open">
                                        <div class="flex flex-wrap gap-1">
                                            <template v-for="c in modelFlip.cards" :key="`${c.card}-${c.joker_suit}`">
                                                <button
                                                    v-if="!modelFlip.flip.chosen && modelFlip.flip.legal.includes(c.card) && !selected.locked"
                                                    type="button"
                                                    class="rounded-md focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
                                                    data-testid="model-fate-choose"
                                                    @click="chooseForModel(selected, modelFlip.flip, c.card)"
                                                >
                                                    <FateCardFace :card="c.card" small note="take as the result" />
                                                </button>
                                                <FateCardFace
                                                    v-else
                                                    :card="c.card"
                                                    :joker-suit="c.joker_suit"
                                                    small
                                                    :highlight="modelFlip.flip.chosen?.card === c.card"
                                                    :dim="!modelFlip.flip.chosen && !modelFlip.flip.legal.includes(c.card)"
                                                />
                                            </template>
                                        </div>
                                        <label
                                            v-if="!modelFlip.flip.chosen && modelFlip.flip.legal.includes(RED_JOKER)"
                                            class="flex items-center gap-2 text-xs"
                                        >
                                            A Red Joker takes the suit
                                            <select v-model="fateSuit" class="rounded-md border bg-background px-1.5 py-1">
                                                <option v-for="suit in SUITS" :key="suit" :value="suit">{{ suit }}</option>
                                            </select>
                                        </label>
                                        <p v-if="modelFlip.flip.chosen" class="text-xs" data-testid="model-fate-result">
                                            Result <span class="font-semibold">{{ cardLabel(modelFlip.flip.chosen.card) }}</span>
                                        </p>
                                        <Button
                                            v-if="!selected.locked"
                                            size="sm"
                                            variant="outline"
                                            :disabled="fate.busy.value || !modelFlip.flip.chosen"
                                            data-testid="model-fate-done"
                                            @click="resolveForModel(selected, modelFlip.flip)"
                                            >Done: discard</Button
                                        >
                                    </div>
                                </div>
                            </div>
                            <p class="mt-3 text-2xs font-semibold uppercase tracking-wide text-muted-foreground">Notes and auras</p>
                            <ModelInfoPanel
                                v-if="!selected.marker"
                                :token="selected"
                                :can-edit="!selected.locked"
                                :editable-max="sandbox"
                                :crew="crewRows.length > 0"
                                @change="emit('modelChange', selected.id, $event)"
                            />
                            <div class="mt-3 border-t pt-3" data-testid="aura-controls">
                                <div class="mb-2 flex items-center justify-between">
                                    <span class="font-medium"
                                        >Auras
                                        <span class="font-normal text-muted-foreground"
                                            >{{ aurasOf(selected.id).length }} of {{ AURAS_PER_MODEL }}</span
                                        ></span
                                    >
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        data-testid="aura-add"
                                        :disabled="aurasOf(selected.id).length >= AURAS_PER_MODEL"
                                        @click="addAura(selected)"
                                        >{{ aurasOf(selected.id).length ? 'Add another' : 'Add aura' }}</Button
                                    >
                                </div>
                                <div
                                    v-for="(aura, i) in aurasOf(selected.id)"
                                    :key="aura.id"
                                    class="mb-3 rounded-md border p-2"
                                    :data-testid="`aura-row-${i}`"
                                >
                                    <div class="flex items-center gap-1">
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            aria-label="Smaller aura"
                                            data-testid="aura-minus"
                                            :disabled="!canEdit(aura)"
                                            @click="setRange(aura.id, aura.range - 0.5)"
                                            ><Minus class="size-4"
                                        /></Button>
                                        <input
                                            type="number"
                                            inputmode="decimal"
                                            class="w-16 rounded-md border bg-background px-2 py-1.5 text-center"
                                            :min="AURA_MIN"
                                            :max="AURA_MAX"
                                            step="0.5"
                                            aria-label="Aura range in inches"
                                            data-testid="aura-range"
                                            :disabled="!canEdit(aura)"
                                            :value="aura.range"
                                            @change="setRange(aura.id, Number(($event.target as HTMLInputElement).value) || AURA_MIN)"
                                        />
                                        <span class="text-muted-foreground">"</span>
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            aria-label="Larger aura"
                                            data-testid="aura-plus"
                                            :disabled="!canEdit(aura)"
                                            @click="setRange(aura.id, aura.range + 0.5)"
                                            ><Plus class="size-4"
                                        /></Button>
                                        <Button
                                            size="sm"
                                            :variant="aura.locked ? 'default' : 'outline'"
                                            :aria-pressed="aura.locked"
                                            :aria-label="aura.locked ? 'Unlock: follow the model again' : 'Lock this aura where it is'"
                                            data-testid="aura-lock"
                                            :disabled="!canEdit(aura)"
                                            @click="toggleLock(aura, selected)"
                                        >
                                            <component :is="aura.locked ? Lock : LockOpen" class="size-4" />
                                        </Button>
                                        <Button
                                            v-if="sharingOn && !selected.locked"
                                            size="sm"
                                            :variant="aura.shared ? 'default' : 'outline'"
                                            :aria-pressed="!!aura.shared"
                                            :aria-label="
                                                aura.shared ? 'Stop showing this aura to the other player' : 'Show this aura to the other player'
                                            "
                                            data-testid="aura-share"
                                            @click="toggleShare(aura)"
                                        >
                                            <Users class="size-4" />
                                        </Button>
                                        <Button
                                            size="sm"
                                            variant="ghost"
                                            aria-label="Remove this aura"
                                            data-testid="aura-remove"
                                            :disabled="!canEdit(aura)"
                                            @click="removeAura(aura.id)"
                                            ><Trash2 class="size-4"
                                        /></Button>
                                    </div>
                                    <div class="mt-2 flex flex-wrap gap-1">
                                        <Button
                                            v-for="n in AURA_PRESETS"
                                            :key="n"
                                            size="sm"
                                            :variant="aura.range === n ? 'default' : 'outline'"
                                            :disabled="!canEdit(aura)"
                                            @click="setRange(aura.id, n)"
                                            >{{ n }}"</Button
                                        >
                                    </div>
                                    <div class="mt-2 flex flex-wrap items-center gap-1" data-testid="aura-traits">
                                        <span class="text-xs text-muted-foreground">Counts as</span>
                                        <label
                                            v-for="trait in AURA_TRAITS"
                                            :key="trait"
                                            class="flex cursor-pointer items-center rounded-md border px-2 py-0.5 text-xs"
                                            :class="aura.traits?.includes(trait) ? 'border-primary bg-primary/10' : ''"
                                            :title="`The area counts as ${trait} terrain`"
                                        >
                                            <input
                                                type="checkbox"
                                                class="sr-only"
                                                :checked="!!aura.traits?.includes(trait)"
                                                :disabled="!canEdit(aura)"
                                                :data-testid="`aura-trait-${trait}`"
                                                @change="toggleAuraTrait(aura, trait)"
                                            />
                                            {{ trait }}
                                        </label>
                                    </div>
                                    <p class="mt-2 text-muted-foreground" data-testid="aura-inside">
                                        {{ aura.shared ? (canEdit(aura) ? 'Shown to both players. ' : 'Shown to you by the other player. ') : ''
                                        }}{{
                                            aura.locked ? 'Locked at ' + fmt(aura.x ?? 0) + '", ' + fmt(aura.y ?? 0) + '". ' : 'Follows the model. '
                                        }}Starts at the edge of the base.
                                        <template v-if="insideOf(aura.id).length"
                                            >Within {{ fmt(aura.range) }}":
                                            {{
                                                insideOf(aura.id)
                                                    .map((t) => t.name)
                                                    .join(', ')
                                            }}.</template
                                        >
                                        <template v-else>No other model is within {{ fmt(aura.range) }}".</template>
                                    </p>
                                </div>
                            </div>
                            <div
                                v-if="canKill || sandbox || (selected.marker && !selected.locked)"
                                class="mt-3 flex flex-wrap items-center gap-1 border-t pt-3"
                                data-testid="model-danger"
                            >
                                <Button
                                    v-if="canKill"
                                    size="sm"
                                    variant="outline"
                                    :data-testid="selected.killed ? 'model-revive' : 'model-kill'"
                                    @click="emit('killChange', selected.id, !selected.killed)"
                                    >{{ selected.killed ? 'Bring it back' : 'Mark killed' }}</Button
                                >
                                <Button
                                    v-if="sandbox || (selected.marker && !selected.locked)"
                                    variant="ghost"
                                    size="sm"
                                    class="text-destructive hover:text-destructive"
                                    data-testid="remove-token"
                                    @click="removeSelected"
                                    ><Trash2 class="mr-1 size-4" />{{ selected.marker ? 'Remove marker' : 'Remove model' }}</Button
                                >
                            </div>
                        </template>
                        <span v-else class="text-muted-foreground"
                            >Tap a model to select it. Drag it to move. Drag the board to pan; pinch or scroll to zoom.</span
                        >
                    </div>
                </div>
            </Teleport>

            <GameCrewMemberDrawer
                :open="cardDrawer"
                :member="cardMember as any"
                :miniatures="[]"
                :can-change-sculpt="false"
                :can-edit-notes="!!cardMember && canEditCrew(cardMember)"
                @update:open="cardDrawer = $event"
                @open-fullscreen="fullscreen = { src: $event.src, backSrc: $event.backSrc, title: $event.title }"
                @notes-change="cardNotes"
            />
            <GameCardFullscreenDialog
                :open="!!fullscreen"
                :src="fullscreen?.src ?? null"
                :back-src="fullscreen?.backSrc ?? null"
                :title="fullscreen?.title ?? null"
                @update:open="fullscreen = $event ? fullscreen : null"
            />
            <GameTokenDialog
                :open="tokenDialog"
                :member="tokenMember"
                :tokens="tokenCatalogue"
                :reference-token-ids="noReferenceTokens"
                :search="tokenSearch"
                @update:open="tokenDialog = $event"
                @update:search="tokenSearch = $event"
                @toggle="toggleModelToken"
                @remove="removeModelToken"
            />
            <Dialog v-if="characterSearchUrl" v-model:open="characterDialog">
                <DialogContent class="max-w-md" data-testid="character-dialog">
                    <DialogHeader>
                        <DialogTitle>{{ replacing ? 'Replace with another character' : 'Add a character' }}</DialogTitle>
                        <DialogDescription>Puts the card on the table at its real base size, with its Sz and Sp.</DialogDescription>
                    </DialogHeader>
                    <label v-if="!replacing" class="block text-sm">
                        <span class="mb-1 block font-medium">Side</span>
                        <select v-model="newSide" class="w-full rounded-md border bg-background px-2 py-1.5" data-testid="character-side">
                            <option value="a">{{ sideNames.a }}</option>
                            <option value="b">{{ sideNames.b }}</option>
                        </select>
                    </label>
                    <CharacterPicker :key="characterKey" :url="characterSearchUrl" @add="addCharacter" />
                </DialogContent>
            </Dialog>
            <Dialog v-model:open="markerDialog">
                <DialogContent class="max-w-md" data-testid="marker-dialog">
                    <DialogHeader>
                        <DialogTitle>Add a marker</DialogTitle>
                        <DialogDescription>
                            Markers are yours: only you can move or pick them up. You can have {{ MARKERS_PER_KIND }} of each kind on the table.
                        </DialogDescription>
                    </DialogHeader>
                    <label v-if="markerSides.length > 1" class="block text-sm">
                        <span class="mb-1 block font-medium">Place for</span>
                        <select v-model="markerSide" class="w-full rounded-md border bg-background px-2 py-1.5" data-testid="marker-side">
                            <option v-for="s in markerSides" :key="s.side" :value="s.side">{{ s.label }} ({{ markersOut(s.side) }} out)</option>
                        </select>
                    </label>
                    <MarkerPicker :key="pickerKey" :markers="markers ?? []" :used="markersUsed" :limit="MARKERS_PER_KIND" @add="placeMarker" />
                </DialogContent>
            </Dialog>

            <button
                v-if="selectionLabel && !panelVisible"
                type="button"
                class="fixed bottom-20 left-1/2 z-30 max-w-[80vw] -translate-x-1/2 truncate rounded-full bg-primary px-4 py-2 text-sm font-medium text-primary-foreground shadow-lg lg:hidden"
                data-testid="selection-chip"
                @click="showPanel"
            >
                {{ selectionLabel }} · details ↓
            </button>
            <TableLoadDialog
                v-if="layouts !== undefined"
                v-model:open="loadOpen"
                :layouts="layouts"
                :loading="layoutsLoading"
                :signed-in="!!signedIn"
                :zones="zones"
                :table-images="tableImages"
                :terrain-images="terrainImages"
                @load="loadTable"
            />
            <HelpDialog v-model:open="helpOpen" topic="tabletop" :section="helpSection" />
            <TableSaveDialog
                v-if="layoutTarget"
                v-model:open="saveOpen"
                :terrain="terrain"
                :table-image="tableKey"
                :target="layoutTarget"
                :signed-in="!!signedIn"
            />
        </div>
    </div>
</template>
