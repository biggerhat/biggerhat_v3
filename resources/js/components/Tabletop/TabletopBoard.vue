<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    BASE_SIZES_MM,
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
    heightCover,
    movePiece,
    restingSupport,
    surfaceAt,
    modelsWithin,
    type Aura,
    type DeploymentKind,
    type MarkerOption,
    type Shape,
    type TerrainPiece,
    type Token,
} from '@/lib/tabletop/geometry';
import MarkerPicker from '@/components/Tabletop/MarkerPicker.vue';
import TerrainEditor from '@/components/Tabletop/TerrainEditor.vue';
import { sampleTerrain } from '@/lib/tabletop/sampleTable';
import { Lock, LockOpen, Maximize, Minus, Mountain, Plus, Ruler, Trash2 } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';

type Mode = 'move' | 'ruler' | 'terrain';
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
    addMarker: [marker: { markerId: number; name: string; baseMm: number; x: number; y: number }];
    remove: [id: string];
    reset: [];
    terrainChanged: [pieces: TerrainPiece[]];
}>();

// The table layout, copied so a piece moves smoothly while it is dragged; the parent gets the layout once it is dropped.
const cloneTerrain = (pieces: TerrainPiece[]): TerrainPiece[] => JSON.parse(JSON.stringify(pieces));
const terrain = ref<TerrainPiece[]>(cloneTerrain(props.terrain ?? sampleTerrain));
watch(
    () => props.terrain,
    (next) => {
        if (!next) return;
        const grabbed = drag;
        const held = grabbed?.kind === 'piece' ? terrain.value.find((p) => p.id === grabbed.id) : undefined;
        terrain.value = cloneTerrain(next).map((p) => (held && p.id === held.id ? held : p));
    },
    { deep: true },
);
const selectedPieceId = ref<string | null>(null);
/** Layouts before each change, newest last, so a slip of the finger can be undone. */
const undoStack = ref<TerrainPiece[][]>([]);
function commitTerrain(next: TerrainPiece[], remember = true) {
    if (remember) undoStack.value = [...undoStack.value.slice(-19), cloneTerrain(terrain.value)];
    terrain.value = cloneTerrain(next);
    emit('terrainChanged', cloneTerrain(next));
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
const mode = ref<Mode>('move');
const level = ref<number | 'all'>('all');
const deployment = ref<'none' | DeploymentKind>(props.deployment ?? 'none');
const variant = ref(0);
const note = ref('');
const view = ref({ x: 0, y: 0, w: TABLE_SIZE });
const svg = ref<SVGSVGElement | null>(null);
const newSide = ref<'a' | 'b'>('a');
const newBase = ref<number>(30);

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
const auras = ref<Aura[]>([]);
const auraKey = computed(() => (props.storageKey ? `tabletop-auras-${props.storageKey}` : null));
onMounted(() => {
    if (!auraKey.value) return;
    try {
        const saved = JSON.parse(localStorage.getItem(auraKey.value) ?? 'null');
        if (Array.isArray(saved)) auras.value = saved;
        // first version: one aura per model, saved as { modelId: range }
        else if (saved && typeof saved === 'object') {
            auras.value = Object.entries(saved).map(([ownerId, range], i) => ({ id: `legacy${i}`, ownerId, range: Number(range), locked: false }));
        }
    } catch {
        // storage unavailable: auras simply start empty
    }
});
watch(
    auras,
    () => {
        if (!auraKey.value) return;
        try {
            localStorage.setItem(auraKey.value, JSON.stringify(auras.value));
        } catch {
            // ignore
        }
    },
    { deep: true },
);
const clampAura = (n: number) => Math.min(AURA_MAX, Math.max(AURA_MIN, Math.round(n * 2) / 2));
const aurasOf = (ownerId: string) => auras.value.filter((a) => a.ownerId === ownerId);
const patchAura = (id: string, patch: Partial<Aura>) => (auras.value = auras.value.map((a) => (a.id === id ? { ...a, ...patch } : a)));
const setRange = (id: string, range: number) => patchAura(id, { range: clampAura(range) });
function addAura(owner: Token) {
    if (aurasOf(owner.id).length >= AURAS_PER_MODEL) return;
    auras.value = [...auras.value, { id: `a${Date.now().toString(36)}${auras.value.length}`, ownerId: owner.id, range: 6, locked: false }];
}
function toggleLock(aura: Aura, owner: Token) {
    patchAura(aura.id, aura.locked ? { locked: false, x: undefined, y: undefined } : { locked: true, x: owner.x, y: owner.y });
}
const removeAura = (id: string) => (auras.value = auras.value.filter((a) => a.id !== id));
const ownerOf = (a: Aura) => tokens.value.find((t) => t.id === a.ownerId);
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
const dimmed = (elevation: number) => level.value !== 'all' && elevation > level.value;

function shapeAttrs(shape: Shape): Record<string, number | string> {
    if (shape.kind === 'rect') return { x: shape.x, y: shape.y, width: shape.w, height: shape.h };
    if (shape.kind === 'circle') return { cx: shape.cx, cy: shape.cy, r: shape.r };
    return { points: shape.points.map((p) => p.join(',')).join(' ') };
}
const shapeTag = (shape: Shape) => (shape.kind === 'rect' ? 'rect' : shape.kind === 'circle' ? 'circle' : 'polygon');
const labelAt = (shape: Shape): Point => {
    if (shape.kind === 'rect') return { x: shape.x + 0.4, y: shape.y + 0.9 };
    if (shape.kind === 'circle') return { x: shape.cx, y: shape.cy + 0.3 };
    const xs = shape.points.map((p) => p[0]);
    const ys = shape.points.map((p) => p[1]);
    return { x: Math.min(...xs) + 1, y: Math.min(...ys) + 1.2 };
};

// --- pointer handling: one finger drags a token or pans, two fingers pinch, wheel zooms ---

const pointers = new Map<number, Point>();
let drag:
    | { kind: 'token'; id: string; dx: number; dy: number; startElevation: number }
    | { kind: 'piece'; id: string; from: Point; original: TerrainPiece; before: TerrainPiece[]; moved: boolean }
    | { kind: 'pan'; last: Point }
    | { kind: 'ruler' }
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
    if (mode.value === 'terrain') {
        const found = surfaceAt(terrain.value, p.x, p.y);
        if (found) {
            selectedPieceId.value = found.piece.id;
            drag = { kind: 'piece', id: found.piece.id, from: p, original: found.piece, before: cloneTerrain(terrain.value), moved: false };
        } else {
            selectedPieceId.value = null;
            drag = { kind: 'pan', last: { x: e.clientX, y: e.clientY } };
        }
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
    } else if (drag.kind === 'piece') {
        const p = toBoard(e.clientX, e.clientY);
        // Snap to half inches so a table lays out tidily.
        const snap = (n: number) => Math.round(n * 2) / 2;
        const moved = movePiece(drag.original, snap(p.x - drag.from.x), snap(p.y - drag.from.y));
        const id = drag.id;
        terrain.value = terrain.value.map((x) => (x.id === id ? moved : x));
        if (snap(p.x - drag.from.x) !== 0 || snap(p.y - drag.from.y) !== 0) drag.moved = true;
    } else if (drag.kind === 'pan') {
        const rect = svg.value!.getBoundingClientRect();
        const k = view.value.w / rect.width;
        view.value = { ...view.value, x: view.value.x - (e.clientX - drag.last.x) * k, y: view.value.y - (e.clientY - drag.last.y) * k };
        keepInside();
        drag.last = { x: e.clientX, y: e.clientY };
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

function onUp(e: PointerEvent) {
    pointers.delete(e.pointerId);
    if (pointers.size < 2) pinch = null;
    if (drag?.kind === 'token') {
        const id = drag.id;
        const t = tokens.value.find((x) => x.id === id);
        if (t) {
            emit('moved', { ...t });
            const now = elevationOf(t);
            if (fallHurts(drag.startElevation, now))
                note.value = `${t.name} dropped from elevation ${drag.startElevation} to ${now}: 1 irreducible damage if this was a fall.`;
            else if (now !== drag.startElevation) note.value = `${t.name} is now at elevation ${now}.`;
        }
    }
    if (drag?.kind === 'piece' && drag.moved) {
        undoStack.value = [...undoStack.value.slice(-19), drag.before];
        emit('terrainChanged', cloneTerrain(terrain.value));
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

function addToken() {
    emit('add', {
        name: `${props.sideNames[newSide.value]} ${tokens.value.filter((t) => t.side === newSide.value).length + 1}`,
        side: newSide.value,
        baseMm: newBase.value,
        x: view.value.x + view.value.w / 2,
        y: view.value.y + view.value.w / 2,
    });
}
function placeMarker(m: MarkerOption) {
    // Fan new markers out a little so several added in a row do not sit exactly on top of each other.
    const nudge = (tokens.value.filter((t) => t.marker).length % 6) * 1.2;
    const at = clampToTable({ baseMm: m.base_mm, x: view.value.x + view.value.w / 2 + nudge, y: view.value.y + view.value.w / 2 + nudge });
    emit('addMarker', { markerId: m.id, name: m.name, baseMm: m.base_mm, ...at });
    note.value = `${m.name} added (${m.base_mm} mm base). Drag it into place.`;
}
function removeSelected() {
    if (selectedId.value) emit('remove', selectedId.value);
    selectedId.value = null;
}
function resetTable() {
    emit('reset');
    selectedId.value = null;
    ruler.value = null;
    note.value = '';
    resetView();
}
const fmt = (n: number) => (Math.round(n * 10) / 10).toString();
</script>

<template>
    <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_20rem]">
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
                    <rect x="0" y="0" :width="TABLE_SIZE" :height="TABLE_SIZE" fill="hsl(var(--muted))" />
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
                        <text
                            :x="TABLE_SIZE / 2"
                            :y="TABLE_SIZE / 2"
                            text-anchor="middle"
                            font-size="0.9"
                            font-weight="700"
                            letter-spacing="0.15"
                            fill="hsl(var(--foreground))"
                            fill-opacity="0.8"
                            stroke="hsl(var(--background))"
                            stroke-width="0.25"
                            paint-order="stroke"
                            :transform="`rotate(${Math.round((Math.atan2(zones.centerline[1][1] - zones.centerline[0][1], zones.centerline[1][0] - zones.centerline[0][0]) * 180) / Math.PI)} ${TABLE_SIZE / 2} ${TABLE_SIZE / 2})`"
                            dy="-0.5"
                            pointer-events="none"
                        >
                            CENTERLINE
                        </text>
                    </g>
                    <template v-for="piece in terrain" :key="piece.id">
                        <g
                            v-for="surface in [...piece.surfaces].sort((a, b) => a.top - b.top)"
                            :key="surface.id"
                            :opacity="dimmed(surface.top) ? 0.12 : 1"
                        >
                            <component
                                :is="shapeTag(surface.shape)"
                                v-bind="shapeAttrs(surface.shape)"
                                :fill="surfaceFill(surface.top)"
                                :stroke="mode === 'terrain' && piece.id === selectedPieceId ? 'hsl(45 95% 55%)' : 'hsl(var(--foreground) / 0.55)'"
                                :stroke-width="mode === 'terrain' && piece.id === selectedPieceId ? 0.22 : 0.07"
                            />
                            <text
                                :x="labelAt(surface.shape).x"
                                :y="labelAt(surface.shape).y"
                                font-size="0.9"
                                fill="hsl(var(--foreground))"
                                pointer-events="none"
                            >
                                Ht {{ surface.top }}
                            </text>
                        </g>
                    </template>
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
                            :stroke="t.id === selectedId ? 'hsl(var(--foreground))' : caught.has(t.id) ? 'hsl(45 95% 55%)' : 'white'"
                            :stroke-width="t.id === selectedId ? 0.16 : 0.08"
                            :stroke-dasharray="t.marker && t.id !== selectedId ? '0.35 0.25' : undefined"
                            :data-marker="t.marker ? 'true' : undefined"
                        />
                        <text :x="t.x" :y="t.y + 0.3" text-anchor="middle" font-size="0.85" fill="white" font-weight="600" pointer-events="none">
                            {{ tokenLabel(t) }}{{ elevationOf(t) > 0 ? `+${elevationOf(t)}` : '' }}
                        </text>
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
            <p class="mt-2 min-h-5 text-center text-sm" role="status" data-testid="tabletop-note">{{ note }}</p>
        </div>

        <div class="space-y-4">
            <div class="flex flex-wrap gap-2">
                <Button :variant="mode === 'move' ? 'default' : 'outline'" size="sm" data-testid="mode-move" @click="mode = 'move'">Move</Button>
                <Button :variant="mode === 'ruler' ? 'default' : 'outline'" size="sm" data-testid="mode-ruler" @click="mode = 'ruler'"
                    ><Ruler class="mr-1 size-4" />Ruler</Button
                >
                <Button :variant="mode === 'terrain' ? 'default' : 'outline'" size="sm" data-testid="mode-terrain" @click="mode = 'terrain'"
                    ><Mountain class="mr-1 size-4" />Terrain</Button
                >
                <Button variant="outline" size="sm" aria-label="Zoom in" @click="zoomBy(0.8)"><Plus class="size-4" /></Button>
                <Button variant="outline" size="sm" aria-label="Zoom out" @click="zoomBy(1.25)"><Minus class="size-4" /></Button>
                <Button variant="outline" size="sm" aria-label="Fit the whole table" @click="resetView"><Maximize class="size-4" /></Button>
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
                @demo="commitTerrain(sampleTerrain)"
            />

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

            <label class="block text-sm">
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
                        @change="variant = 0"
                    >
                        <option value="none">None</option>
                        <option v-for="d in DEPLOYMENT_KINDS" :key="d.value" :value="d.value">{{ d.label }}</option>
                    </select>
                </label>
                <label v-if="variants.length" class="block text-sm">
                    <span class="mb-1 block font-medium">Which side</span>
                    <select v-model.number="variant" class="w-full rounded-md border bg-background px-2 py-1.5" data-testid="deployment-variant">
                        <option v-for="v in variants" :key="v.value" :value="v.value">{{ v.label }}</option>
                    </select>
                </label>
            </div>

            <div v-if="mode !== 'terrain'" class="rounded-md border p-3 text-sm" data-testid="selected-panel">
                <template v-if="selected">
                    <div class="font-semibold">
                        {{ selected.name }} <span class="font-normal text-muted-foreground">{{ selected.baseMm }} mm base</span>
                    </div>
                    <div class="text-muted-foreground">
                        At {{ fmt(selected.x) }}", {{ fmt(selected.y) }}". Elevation {{ elevationOf(selected)
                        }}<span v-if="supports.get(selected.id)?.piece"> on {{ supports.get(selected.id)?.piece?.name }}</span
                        >.
                    </div>
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
                                    :value="aura.range"
                                    @change="setRange(aura.id, Number(($event.target as HTMLInputElement).value) || AURA_MIN)"
                                />
                                <span class="text-muted-foreground">"</span>
                                <Button
                                    size="sm"
                                    variant="outline"
                                    aria-label="Larger aura"
                                    data-testid="aura-plus"
                                    @click="setRange(aura.id, aura.range + 0.5)"
                                    ><Plus class="size-4"
                                /></Button>
                                <Button
                                    size="sm"
                                    :variant="aura.locked ? 'default' : 'outline'"
                                    :aria-pressed="aura.locked"
                                    :aria-label="aura.locked ? 'Unlock: follow the model again' : 'Lock this aura where it is'"
                                    data-testid="aura-lock"
                                    @click="toggleLock(aura, selected)"
                                >
                                    <component :is="aura.locked ? Lock : LockOpen" class="size-4" />
                                </Button>
                                <Button size="sm" variant="ghost" aria-label="Remove this aura" data-testid="aura-remove" @click="removeAura(aura.id)"
                                    ><Trash2 class="size-4"
                                /></Button>
                            </div>
                            <div class="mt-2 flex flex-wrap gap-1">
                                <Button
                                    v-for="n in AURA_PRESETS"
                                    :key="n"
                                    size="sm"
                                    :variant="aura.range === n ? 'default' : 'outline'"
                                    @click="setRange(aura.id, n)"
                                    >{{ n }}"</Button
                                >
                            </div>
                            <p class="mt-2 text-muted-foreground" data-testid="aura-inside">
                                {{ aura.locked ? 'Locked at ' + fmt(aura.x ?? 0) + '", ' + fmt(aura.y ?? 0) + '". ' : 'Follows the model. ' }}Starts
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
                    <Button
                        v-if="sandbox || selected.marker"
                        class="mt-2"
                        variant="outline"
                        size="sm"
                        data-testid="remove-token"
                        @click="removeSelected"
                        ><Trash2 class="mr-1 size-4" />{{ selected.marker ? 'Remove marker' : 'Remove model' }}</Button
                    >
                </template>
                <span v-else class="text-muted-foreground"
                    >Tap a model to select it. Drag it to move. Drag the board to pan; pinch or scroll to zoom.</span
                >
            </div>

            <MarkerPicker v-if="markers && mode !== 'terrain'" :markers="markers" @add="placeMarker" />

            <div v-if="sandbox" class="flex flex-wrap items-end gap-2 text-sm">
                <label>
                    <span class="mb-1 block font-medium">Side</span>
                    <select v-model="newSide" class="rounded-md border bg-background px-2 py-1.5">
                        <option value="a">{{ sideNames.a }}</option>
                        <option value="b">{{ sideNames.b }}</option>
                    </select>
                </label>
                <label>
                    <span class="mb-1 block font-medium">Base</span>
                    <select v-model="newBase" class="rounded-md border bg-background px-2 py-1.5">
                        <option v-for="mm in BASE_SIZES_MM" :key="mm" :value="mm">{{ mm }} mm</option>
                    </select>
                </label>
                <Button size="sm" data-testid="add-token" @click="addToken"><Plus class="mr-1 size-4" />Add model</Button>
                <Button size="sm" variant="ghost" @click="resetTable">Reset table</Button>
            </div>
        </div>
    </div>
</template>
