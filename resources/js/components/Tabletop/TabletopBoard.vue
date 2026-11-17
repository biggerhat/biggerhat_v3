<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { useConfirm } from '@/composables/useConfirm';
import {
    AURAS_PER_MODEL,
    DEPLOYMENT_KINDS,
    TABLE_SIZE,
    auraOrigin,
    auraRadius,
    baseRadius,
    clampToTable,
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
    dragShapeHandle,
    coverAt,
    insertCorner,
    insideOf as buildingOf,
    interiorShape,
    moveOpening,
    openingSpan,
    wallProblem,
    wallSegments,
    movePiece,
    pieceBounds,
    placementProblems,
    removeCorner,
    restingSupport,
    rotatePiece,
    shapeCentre,
    shapeHandles,
    settleUnder,
    sideSightLines,
    surfaceAt,
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
    Eye,
    FolderOpen,
    Lock,
    Route,
    LockOpen,
    Maximize,
    MapPin,
    Minus,
    Mountain,
    Plus,
    Ruler,
    Save,
    Trash2,
    User,
    Users,
} from 'lucide-vue-next';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';

type Mode = 'move' | 'ruler' | 'sight' | 'terrain';
type Point = { x: number; y: number };

const MIN_WIDTH = 6;

const props = withDefaults(
    defineProps<{
        tokens: Token[];
        terrain?: TerrainPiece[];
        sandbox?: boolean;
        sideNames?: { a: string; b: string };
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
    auraChange: [change: { upsert?: Aura[]; remove?: string[] }];
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
    if (m !== 'terrain') outline.value = null;
    // The ruler is a tool: when it is switched off, what it measured goes with it.
    if (m !== 'ruler') ruler.value = null;
});
/** The two tabs at the top of the side column: working with models, or with terrain. The ruler and the aim are tools of the models tab. */
const activeTab = computed<'move' | 'terrain'>(() => (mode.value === 'terrain' ? 'terrain' : 'move'));
const openTab = (tab: 'move' | 'terrain') => {
    mode.value = tab;
};
const tabClass = (active: boolean) =>
    `flex items-center justify-center rounded-md px-3 py-1.5 text-sm font-medium transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary ${
        active ? 'bg-background text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'
    }`;
/** The Table section's open state is kept on this device; its closed line says what is set. */
const TABLE_OPEN_KEY = 'tabletop-table-settings-open';
const tableOpen = ref(false);
onMounted(() => {
    try {
        tableOpen.value = localStorage.getItem(TABLE_OPEN_KEY) === '1';
    } catch {
        // storage unavailable: closed
    }
});
function onTableToggle(e: Event) {
    tableOpen.value = (e.target as HTMLDetailsElement).open;
    try {
        localStorage.setItem(TABLE_OPEN_KEY, tableOpen.value ? '1' : '0');
    } catch {
        // ignore
    }
}
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
onBeforeUnmount(() => panelObserver?.disconnect());
function showPanel() {
    document
        .querySelector('[data-testid=side-column] [data-testid=selected-panel], [data-testid=side-column] [data-testid=terrain-piece]')
        ?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}
/** Load table and Save table: dialogs. */
const loadOpen = ref(false);
const saveOpen = ref(false);
/** The Ruler button is a switch: on to measure, off to put the ruler away and go back to moving models. */
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
    /** How it is moving: a Walk, a Walk that ignores terrain (Flight), or a place within `range` (Leap) that is not movement at all. */
    kind: MoveKind;
    /** The building it started inside, if any (a place cannot cross a wall). */
    inside: string | null;
    range: number;
    whole: boolean;
    /** The points the move has passed through so far (the start, then where each earlier leg ended); `to` is where the model is now. */
    path: { x: number; y: number; elev: number }[];
} | null>(null);
type MoveKind = 'walk' | 'fly' | 'place';
/** Walking spends movement and is stopped by terrain; flying walks but ignores terrain's height; placing picks the model up and puts it down within a range (p.17). */
const moveKind = ref<MoveKind>('walk');
const placeRange = ref(6);
const MOVE_KINDS: { value: MoveKind; label: string; hint: string }[] = [
    { value: 'walk', label: 'Walk', hint: 'Spends movement and is stopped by terrain' },
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
    const flat = t.kind !== 'walk';
    const points = [...t.path, { x: t.to.x, y: t.to.y, elev: elevationOf(token) }].map((q) => (flat ? { ...q, elev: 0 } : q));
    const cost = pathCost(points);
    // A place is not movement, so it is never measured against a Walk.
    return { cost, walk: t.sp !== undefined && t.kind !== 'place' ? walkBudget(t.sp, cost) : null };
});
const trailPoints = computed<Point[]>(() => (trail.value ? [...trail.value.path.map((q) => ({ x: q.x, y: q.y })), { ...trail.value.to }] : []));
const trailLength = computed(() =>
    trailPoints.value.reduce((sum, q, i, all) => (i ? sum + Math.hypot(q.x - all[i - 1].x, q.y - all[i - 1].y) : 0), 0),
);
/** A small length on each leg once there is more than one. */
const trailLegLabels = computed(() => {
    const pts = trailPoints.value;
    if (pts.length < 3) return [];
    return pts.slice(1).map((q, i) => ({
        x: (q.x + pts[i].x) / 2,
        y: (q.y + pts[i].y) / 2 - 0.35,
        length: Math.hypot(q.x - pts[i].x, q.y - pts[i].y),
    }));
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
function toggleGuide(t: Token) {
    if (guidedId.value === t.id) {
        guidedId.value = null;
        sight.value = null;
        if (mode.value === 'sight') mode.value = 'move';
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
    sightFrom.value && sightTo.value ? lineOfSight(sightFrom.value, sightTo.value, tokens.value, terrain.value) : null,
);
const sightBack = computed(() =>
    sightFrom.value && sightTo.value ? lineOfSight(sightTo.value, sightFrom.value, tokens.value, terrain.value) : null,
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
        const trace = traceLine(from, l.start, l.end, tokens.value, terrain.value);
        const reached = trace.models.flatMap((m) => {
            const token = tokens.value.find((t) => t.id === m.id);
            return token ? [{ ...m, token }] : [];
        });
        return { ...l, trace, reached };
    });
});
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
const insideOf = (id: string) => activeAuras.value.find((a) => a.aura.id === id)?.inside ?? [];
const caught = computed(() => new Set(activeAuras.value.flatMap((a) => a.inside.map((t) => t.id))));
/** Models standing where they should not: on another base, or on impassable terrain. */
const problems = computed(() => new Map(tokens.value.map((t) => [t.id, placementProblems(t, tokens.value, terrain.value)])));
const selected = computed(() => tokens.value.find((t) => t.id === selectedId.value) ?? null);
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

const sideColour = (side: 'a' | 'b' | 'n') => (side === 'a' ? 'hsl(215 70% 50%)' : side === 'b' ? 'hsl(5 70% 48%)' : 'hsl(280 45% 50%)');
/** Two letters for a marker ("Scheme Marker" is "SM"), one for a model. */
const tokenLabel = (t: Token) =>
    t.marker
        ? t.name
              .split(/\s+/)
              .slice(0, 2)
              .map((w) => w.slice(0, 1))
              .join('')
              .toUpperCase()
        : t.name.slice(0, 1);

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
    | { kind: 'pan'; last: Point }
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

function onDown(e: PointerEvent) {
    svg.value?.setPointerCapture(e.pointerId);
    pointers.set(e.pointerId, { x: e.clientX, y: e.clientY });
    if (pointers.size === 2) {
        const [a, b] = [...pointers.values()];
        pinch = { distance: Math.hypot(a.x - b.x, a.y - b.y), width: view.value.w, centre: toBoard((a.x + b.x) / 2, (a.y + b.y) / 2) };
        drag = null;
        return;
    }
    const p = toBoard(e.clientX, e.clientY);
    const hit = tokenAt(p);
    note.value = '';
    // a stepped move goes on when the same model is grabbed again; anything else starts afresh
    const carryOn = stepMove.value && moveKind.value !== 'place' && mode.value === 'move' && !!hit && !hit.locked && trail.value?.id === hit.id;
    if (!carryOn) trail.value = null;
    if (!guidedId.value) sight.value = null;
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
        const here = { x: hit.x, y: hit.y, elev: elevationOf(hit) };
        if (carryOn && trail.value) {
            const last = trail.value.path[trail.value.path.length - 1];
            const moved = !last || Math.hypot(last.x - here.x, last.y - here.y) >= 0.05;
            trail.value = { ...trail.value, path: moved ? [...trail.value.path, here] : trail.value.path, to: { x: hit.x, y: hit.y } };
        } else {
            trail.value = {
                id: hit.id,
                from: { x: hit.x, y: hit.y },
                to: { x: hit.x, y: hit.y },
                baseMm: hit.baseMm,
                startElevation: elevationOf(hit),
                sp: hit.marker ? undefined : hit.sp,
                kind: moveKind.value,
                inside: buildingOf(hit, terrain.value)?.id ?? null,
                range: placeRange.value,
                whole: placeWhole.value,
                path: [here],
            };
        }
    } else {
        selectedId.value = null;
        drag = { kind: 'pan', last: { x: e.clientX, y: e.clientY } };
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
    if (!drag) return;
    if (drag.kind === 'token') {
        const id = drag.id;
        const t = tokens.value.find((x) => x.id === id);
        if (!t) return;
        const p = toBoard(e.clientX, e.clientY);
        Object.assign(t, clampToTable({ ...t, x: p.x + drag.dx, y: p.y + drag.dy }));
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
    const hazards = [...placementProblems({ ...t, x: from.x, y: from.y }, tokens.value, terrain.value), ...(problems.value.get(t.id) ?? [])].some(
        (p) => p.kind === 'hazardous',
    );
    if (hazards) parts.push('it began or ended in hazardous terrain, which counts as having moved through it');
    if ((buildingOf(t, terrain.value)?.id ?? null) !== tr.inside)
        parts.push('a place cannot move a model into or out of a building, so it does not happen: put it back');
    return parts.join(', ') + '. Terrain and sight in between are ignored, and nothing is spent climbing or falling.';
}

function onUp(e: PointerEvent) {
    pointers.delete(e.pointerId);
    if (pointers.size < 2) pinch = null;
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
            } else if (trail.value?.kind === 'place') {
                note.value = placeNote(t, trail.value);
            } else if (cost.ground >= 0.05) {
                const parts = [`${t.name} moved ${fmt(cost.ground)}"${legs.length > 1 ? ` (${legs.map((l) => fmt(l) + '"').join(' + ')})` : ''}`];
                if (cost.climb > 0) parts.push(`climbed ${fmt(cost.climb)}" (counts as movement): ${fmt(cost.total)}" in all`);
                if (trail.value?.kind === 'fly') parts.push('ignoring terrain height, so no climbing cost and no fall');
                else if (fallHurts(drag.startElevation, now)) parts.push(`dropped ${fmt(leg.drop)}": 1 irreducible damage if this was a fall`);
                else if (leg.drop > 0) parts.push(`down ${fmt(leg.drop)}" to elevation ${now}`);
                const walk = trailCost.value?.walk;
                if (walk)
                    parts.push(
                        walk.within
                            ? `${fmt(walk.left)}" left of a Walk (Sp ${trail.value?.sp})`
                            : `${fmt(-walk.left)}" more than a Walk (Sp ${trail.value?.sp})`,
                    );
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
const characterKey = ref(0);
function openCharacterDialog() {
    characterKey.value++;
    characterDialog.value = true;
}
/** A real card: its name, base size, Sz and Sp. New models fan out so several added in a row do not sit on top of each other. */
function addCharacter(c: CharacterOption) {
    const nudge = (tokens.value.filter((t) => !t.marker).length % 6) * 1.2;
    const at = clampToTable({ baseMm: c.base_mm, x: view.value.x + view.value.w / 2 + nudge, y: view.value.y + view.value.w / 2 + nudge });
    emit('add', {
        name: c.name,
        side: newSide.value,
        baseMm: c.base_mm,
        sz: c.sz,
        sp: c.sp,
        info: { health: c.health, maxHealth: c.health, tokens: [] },
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
    <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_20rem] xl:grid-cols-[minmax(0,1fr)_22rem]">
        <div>
            <div
                class="mx-auto aspect-square w-full max-w-[min(100%,calc(100vh-14rem))] overflow-hidden rounded-lg border bg-card"
                data-testid="tabletop-board"
            >
                <svg
                    ref="svg"
                    class="size-full touch-none select-none"
                    :viewBox="viewBox"
                    role="application"
                    aria-label="Tabletop board, 36 by 36 inches"
                    @pointerdown="onDown"
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
                                v-if="surface.top > 0"
                                :x="labelAt(surface.shape).x"
                                :y="labelAt(surface.shape).y"
                                font-size="0.9"
                                fill="hsl(var(--foreground))"
                                pointer-events="none"
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
                    <g v-for="t in tokens" :key="t.id" :opacity="dimmed(elevationOf(t)) ? 0.25 : t.killed ? 0.35 : 1" :data-testid="`token-${t.id}`">
                        <circle
                            :cx="t.x"
                            :cy="t.y"
                            :r="baseRadius(t.baseMm)"
                            :fill="sideColour(t.side)"
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
                        <g v-if="t.info?.maxHealth" pointer-events="none" :data-testid="`hp-${t.id}`">
                            <rect
                                :x="t.x - baseRadius(t.baseMm)"
                                :y="t.y + baseRadius(t.baseMm) + 0.12"
                                :width="baseRadius(t.baseMm) * 2"
                                height="0.28"
                                fill="hsl(var(--background))"
                                stroke="hsl(var(--foreground) / 0.6)"
                                stroke-width="0.05"
                            />
                            <rect
                                :x="t.x - baseRadius(t.baseMm)"
                                :y="t.y + baseRadius(t.baseMm) + 0.12"
                                :width="baseRadius(t.baseMm) * 2 * Math.min(1, (t.info.health ?? t.info.maxHealth) / t.info.maxHealth)"
                                height="0.28"
                                :fill="(t.info.health ?? t.info.maxHealth) / t.info.maxHealth <= 0.34 ? 'hsl(0 80% 55%)' : 'hsl(145 60% 42%)'"
                            />
                        </g>
                        <g v-if="t.info?.tokens.length" pointer-events="none">
                            <circle
                                :cx="t.x + baseRadius(t.baseMm) * 0.8"
                                :cy="t.y - baseRadius(t.baseMm) * 0.8"
                                r="0.42"
                                fill="hsl(190 70% 38%)"
                                stroke="white"
                                stroke-width="0.06"
                            />
                            <text
                                :x="t.x + baseRadius(t.baseMm) * 0.8"
                                :y="t.y - baseRadius(t.baseMm) * 0.8 + 0.2"
                                text-anchor="middle"
                                font-size="0.55"
                                fill="white"
                                font-weight="700"
                            >
                                {{ t.info.tokens.length }}
                            </text>
                        </g>
                        <text :x="t.x" :y="t.y + 0.3" text-anchor="middle" font-size="0.85" fill="white" font-weight="600" pointer-events="none">
                            {{ tokenLabel(t) }}{{ elevationOf(t) > 0 ? `+${elevationOf(t)}` : '' }}
                        </text>
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
                            v-else-if="trail.sp !== undefined"
                            :cx="trail.from.x"
                            :cy="trail.from.y"
                            :r="trail.sp"
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
                    <g v-if="sightForward && sightBack" pointer-events="none" data-testid="sight-line">
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
            <div class="mt-2 flex items-start gap-2">
                <p class="min-h-5 flex-1 text-center text-sm" role="status" data-testid="tabletop-note">{{ note }}</p>
                <div class="flex shrink-0 gap-1" data-testid="zoom-controls">
                    <Button variant="outline" size="icon" class="size-8" aria-label="Zoom in" @click="zoomBy(0.8)"><Plus class="size-4" /></Button>
                    <Button variant="outline" size="icon" class="size-8" aria-label="Zoom out" @click="zoomBy(1.25)"><Minus class="size-4" /></Button>
                    <Button variant="outline" size="icon" class="size-8" aria-label="Fit the whole table" @click="resetView"
                        ><Maximize class="size-4"
                    /></Button>
                </div>
            </div>
        </div>

        <div
            class="space-y-4 max-lg:[&_button]:min-h-10 max-lg:[&_input:not([type=checkbox])]:min-h-10 max-lg:[&_select]:min-h-10"
            data-testid="side-column"
        >
            <!-- What to work on: models, or terrain -->
            <div
                v-if="!readOnly"
                role="tablist"
                aria-label="What to work on"
                class="grid grid-cols-2 gap-1 rounded-lg bg-muted p-1"
                data-testid="mode-tabs"
                @keydown.right.prevent="openTab('terrain')"
                @keydown.left.prevent="openTab('move')"
            >
                <button
                    type="button"
                    role="tab"
                    :aria-selected="activeTab === 'move'"
                    :tabindex="activeTab === 'move' ? 0 : -1"
                    :class="tabClass(activeTab === 'move')"
                    data-testid="mode-move"
                    @click="openTab('move')"
                >
                    <Users class="mr-1.5 size-4" />Models
                </button>
                <button
                    type="button"
                    role="tab"
                    :aria-selected="activeTab === 'terrain'"
                    :tabindex="activeTab === 'terrain' ? 0 : -1"
                    :class="tabClass(activeTab === 'terrain')"
                    data-testid="mode-terrain"
                    @click="openTab('terrain')"
                >
                    <Mountain class="mr-1.5 size-4" />Terrain
                </button>
            </div>

            <!-- The table: load or keep one, then its settings in one place -->
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
            <details class="group rounded-md border" :open="tableOpen" data-testid="table-settings" @toggle="onTableToggle">
                <summary class="flex cursor-pointer list-none items-center justify-between gap-2 p-3 text-sm [&::-webkit-details-marker]:hidden">
                    <span class="font-semibold">Table</span>
                    <span class="min-w-0 flex-1 truncate text-right text-muted-foreground" data-testid="table-summary">{{ tableSummary }}</span>
                    <ChevronDown class="size-4 shrink-0 transition-transform group-open:rotate-180" />
                </summary>
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
                            class="flex cursor-pointer items-center gap-2 text-sm"
                            title="The two centre axes and the middle of each 18-inch quarter"
                        >
                            <input v-model="tableLines" type="checkbox" class="size-4" data-testid="table-lines-toggle" />
                            <span>Show table lines <span class="text-muted-foreground">(centre axes and quarter centres)</span></span>
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
            </details>
            <!-- Tools for working with models -->
            <div
                v-if="activeTab === 'move'"
                role="tabpanel"
                aria-label="Models"
                class="flex flex-wrap items-center gap-2 rounded-md border p-2"
                data-testid="move-tools"
            >
                <Button
                    :variant="mode === 'ruler' ? 'default' : 'outline'"
                    size="sm"
                    data-testid="mode-ruler"
                    :aria-pressed="mode === 'ruler'"
                    @click="toggleRuler"
                    ><Ruler class="mr-1 size-4" />Ruler</Button
                >
                <Button v-if="guidedId" :variant="mode === 'sight' ? 'default' : 'outline'" size="sm" data-testid="mode-sight" @click="mode = 'sight'"
                    ><Eye class="mr-1 size-4" />Aim</Button
                >
                <Button
                    v-if="mode === 'move'"
                    :variant="stepMove ? 'default' : 'outline'"
                    size="sm"
                    :aria-pressed="stepMove"
                    title="Step move: grab the same model again to add another leg to the move"
                    data-testid="step-toggle"
                    @click="stepMove = !stepMove"
                    ><Route class="mr-1 size-4" />Step</Button
                >
                <span v-if="mode === 'move'" class="text-xs text-muted-foreground" aria-hidden="true">Move as</span>
                <div
                    v-if="mode === 'move'"
                    role="radiogroup"
                    aria-label="How the model moves"
                    class="inline-flex rounded-md border p-0.5"
                    data-testid="move-kind"
                >
                    <button
                        v-for="o in MOVE_KINDS"
                        :key="o.value"
                        type="button"
                        role="radio"
                        :aria-checked="moveKind === o.value"
                        :title="o.hint"
                        :class="[
                            'rounded px-2.5 py-1 text-sm font-medium transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary',
                            moveKind === o.value ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:text-foreground',
                        ]"
                        :data-testid="`move-kind-${o.value}`"
                        @click="moveKind = o.value"
                    >
                        {{ o.label }}
                    </button>
                </div>
                <template v-if="mode === 'move' && moveKind === 'place'">
                    <label class="flex items-center gap-1 text-sm"
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
                        :variant="placeWhole ? 'default' : 'outline'"
                        :aria-pressed="placeWhole"
                        title="Some effects need the whole base within range rather than a single point of it"
                        data-testid="place-whole"
                        @click="placeWhole = !placeWhole"
                        >Whole base</Button
                    >
                </template>
            </div>

            <TerrainEditor
                v-if="mode === 'terrain'"
                :pieces="terrain"
                :selected-id="selectedPieceId"
                :anchor="viewCentre"
                :can-undo="undoStack.length > 0"
                @change="commitTerrain($event)"
                @select="selectedPieceId = $event"
                @undo="undoTerrain"
                @clear="commitTerrain([])"
                :terrain-images="terrainImages"
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

            <div
                v-if="mode === 'move' && moveKind !== 'walk'"
                class="rounded-md border p-3 text-sm text-muted-foreground"
                data-testid="move-kind-help"
            >
                <template v-if="moveKind === 'place'"
                    >Place: drag a model to where it may be put down. It is picked up, so terrain and sight in between are ignored and nothing is
                    spent climbing or falling, but it has to fit where it lands and be within the range, measured from the closest point of its base.
                    A place that cannot fit does not happen. If it begins or ends in terrain, it counts as having moved through it.</template
                >
                <template v-else
                    >Flight: the model walks but ignores terrain, so climbing costs no movement and a drop does no damage. Only the ground distance
                    counts against its Walk.</template
                >
            </div>
            <div v-if="mode === 'move' && stepMove" class="flex flex-wrap items-center gap-2 rounded-md border p-3 text-sm" data-testid="step-help">
                <span class="min-w-0 flex-1 text-muted-foreground"
                    >Step move: drag a model, let go, then drag the same model again. The legs add up. Tap empty ground to start over.</span
                >
                <Button v-if="trail" size="sm" variant="outline" data-testid="step-reset" @click="trail = null">Start over</Button>
            </div>
            <div v-if="guidedId && (mode === 'sight' || sideLines.length)" class="space-y-2 rounded-md border p-3 text-sm" data-testid="guide-panel">
                <p v-if="mode === 'sight'" class="text-muted-foreground" data-testid="guide-hint">
                    Press or drag on the board to aim {{ tokens.find((t) => t.id === guidedId)?.name }}'s guidelines, or press a model or marker to
                    point at it. The Models tab goes back to moving models.
                </p>
                <div v-if="sideLines.length" data-testid="side-readout">
                    <div v-for="l in sideLines" :key="l.side" data-testid="side-summary">
                        <span class="font-semibold capitalize">{{ l.side }} side:</span> {{ describeSide(l) }}
                    </div>
                    <details class="mt-1 text-muted-foreground">
                        <summary class="cursor-pointer text-xs">How to read the lines</summary>
                        <p class="mt-1 text-xs">
                            Green is clear; red dashes start where terrain or an enemy at least as tall as the model stops the line. Rings show which
                            models a line reaches. Cover and concealment come from the model-to-model check below.
                        </p>
                    </details>
                </div>
            </div>
            <div v-if="sightForward && sightBack && sightFrom && sightTo" class="space-y-1 rounded-md border p-3 text-sm" data-testid="sight-readout">
                <div
                    :class="sightForward.clear ? 'font-semibold text-green-600 dark:text-green-400' : 'font-semibold text-red-600 dark:text-red-400'"
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

            <div v-if="mode !== 'terrain'" class="rounded-md border p-3 text-sm" data-testid="selected-panel">
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
                    <Button
                        v-if="!selected.marker"
                        size="sm"
                        class="mt-2"
                        :variant="guidedId === selected.id ? 'default' : 'outline'"
                        :aria-pressed="guidedId === selected.id"
                        data-testid="model-guide"
                        @click="toggleGuide(selected)"
                        ><Eye class="mr-1 size-4" />Sight guidelines {{ guidedId === selected.id ? 'on' : 'off' }}</Button
                    >
                    <ModelInfoPanel
                        v-if="!selected.marker"
                        :token="selected"
                        :token-options="tokenOptions ?? []"
                        :can-edit="!selected.locked"
                        :editable-max="sandbox"
                        @change="emit('modelChange', selected.id, $event)"
                    />
                    <p v-if="problems.get(selected.id)?.length" class="mt-2 text-amber-600 dark:text-amber-400" data-testid="placement-warning">
                        {{ selected.name }}
                        {{
                            problems
                                .get(selected.id)!
                                .map((p) => p.text)
                                .join(' and ')
                        }}.
                    </p>
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
                                    :aria-label="aura.shared ? 'Stop showing this aura to the other player' : 'Show this aura to the other player'"
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
                            <p class="mt-2 text-muted-foreground" data-testid="aura-inside">
                                {{ aura.shared ? (canEdit(aura) ? 'Shown to both players. ' : 'Shown to you by the other player. ') : ''
                                }}{{ aura.locked ? 'Locked at ' + fmt(aura.x ?? 0) + '", ' + fmt(aura.y ?? 0) + '". ' : 'Follows the model. ' }}Starts
                                at the edge of the base.
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
                    <div v-if="sandbox || (selected.marker && !selected.locked)" class="mt-3 border-t pt-3">
                        <Button
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

            <div
                v-if="mode !== 'terrain' && (sandbox || (markers && markerSides.length))"
                class="space-y-2 rounded-md border p-2"
                data-testid="add-panel"
            >
                <div class="text-sm font-medium">Add to the table</div>
                <p v-if="sandbox && !tokens.some((t) => !t.marker)" class="text-sm text-muted-foreground" data-testid="empty-table">
                    The table has no models yet. Add a character to put its model on the table at its real base size, with its Sz, Sp and health.
                </p>
                <div v-if="markers && markerSides.length" class="flex flex-wrap items-center gap-2 text-sm">
                    <Button variant="outline" size="sm" data-testid="marker-open" @click="openMarkerDialog"
                        ><MapPin class="mr-1 size-4" />Add marker</Button
                    >
                    <span class="text-muted-foreground" data-testid="marker-count"
                        >{{ markerSides.map((s) => `${markerSides.length > 1 ? `${s.label}: ` : ''}${markersOut(s.side)}`).join(', ') }} on the
                        table</span
                    >
                </div>
                <div v-if="sandbox" class="flex flex-wrap items-end gap-2 text-sm">
                    <label>
                        <span class="mb-1 block font-medium">Side</span>
                        <select v-model="newSide" class="rounded-md border bg-background px-2 py-1.5">
                            <option value="a">{{ sideNames.a }}</option>
                            <option value="b">{{ sideNames.b }}</option>
                        </select>
                    </label>
                    <Button v-if="characterSearchUrl" size="sm" variant="outline" data-testid="character-open" @click="openCharacterDialog"
                        ><User class="mr-1 size-4" />Add a character</Button
                    >
                </div>
            </div>

            <Dialog v-if="characterSearchUrl" v-model:open="characterDialog">
                <DialogContent class="max-w-md" data-testid="character-dialog">
                    <DialogHeader>
                        <DialogTitle>Add a character</DialogTitle>
                        <DialogDescription>Puts the card on the table at its real base size, with its Sz and Sp.</DialogDescription>
                    </DialogHeader>
                    <label class="block text-sm">
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
                :signed-in="!!signedIn"
                :table-images="tableImages"
                :terrain-images="terrainImages"
                @load="loadTable"
            />
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
