<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    BASE_SIZES_MM,
    TABLE_SIZE,
    baseRadius,
    clampToTable,
    deploymentZones,
    edgeDistance,
    fallHurts,
    heightCover,
    restingSupport,
    type Shape,
    type Token,
} from '@/lib/tabletop/geometry';
import { sampleTerrain } from '@/lib/tabletop/sampleTable';
import { Maximize, Minus, Plus, Ruler, Trash2 } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

type Mode = 'move' | 'ruler';
type Point = { x: number; y: number };

const MIN_WIDTH = 6;

const props = withDefaults(defineProps<{ tokens: Token[]; sandbox?: boolean; sideNames?: { a: string; b: string } }>(), {
    sandbox: false,
    sideNames: () => ({ a: 'Blue', b: 'Red' }),
});
const emit = defineEmits<{
    moved: [token: Token];
    add: [token: Omit<Token, 'id'>];
    remove: [id: string];
    reset: [];
}>();

const terrain = sampleTerrain;
// A working copy, so a drag moves smoothly; the parent gets one `moved` event when the model is dropped.
const tokens = ref<Token[]>(props.tokens.map((t) => ({ ...t })));
watch(
    () => props.tokens,
    (next) => (tokens.value = next.map((t) => ({ ...t }))),
    { deep: true },
);
const selectedId = ref<string | null>(null);
const mode = ref<Mode>('move');
const level = ref<number | 'all'>('all');
const deployment = ref<'none' | 'standard' | 'corner'>('none');
const note = ref('');
const view = ref({ x: 0, y: 0, w: TABLE_SIZE });
const svg = ref<SVGSVGElement | null>(null);
const newSide = ref<'a' | 'b'>('a');
const newBase = ref<number>(30);

const ruler = ref<{ from: Point; to: Point; fromToken: string | null; toToken: string | null } | null>(null);

const levels = computed(() => [0, ...new Set(terrain.flatMap((p) => p.surfaces.map((s) => s.top)))].sort((a, b) => a - b));
const supports = computed(() => new Map(tokens.value.map((t) => [t.id, restingSupport(t, terrain)])));
const elevationOf = (t: Token) => supports.value.get(t.id)?.elevation ?? 0;
const selected = computed(() => tokens.value.find((t) => t.id === selectedId.value) ?? null);
const viewBox = computed(() => `${view.value.x} ${view.value.y} ${view.value.w} ${view.value.w}`);
const zones = computed(() => (deployment.value === 'none' ? null : deploymentZones(deployment.value)));

const sideColour = (side: 'a' | 'b') => (side === 'a' ? 'hsl(215 70% 50%)' : 'hsl(5 70% 48%)');

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
let drag: { kind: 'token'; id: string; dx: number; dy: number; startElevation: number } | { kind: 'pan'; last: Point } | { kind: 'ruler' } | null =
    null;
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
    if (mode.value === 'ruler') {
        ruler.value = { from: hit ? { x: hit.x, y: hit.y } : p, to: hit ? { x: hit.x, y: hit.y } : p, fromToken: hit?.id ?? null, toToken: null };
        drag = { kind: 'ruler' };
    } else if (hit) {
        selectedId.value = hit.id;
        if (hit.locked) {
            note.value = `${hit.name} belongs to ${props.sideNames[hit.side]}; only they can move it.`;
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
                    <g v-if="zones">
                        <component
                            :is="shapeTag(zones.a)"
                            v-bind="shapeAttrs(zones.a)"
                            :fill="sideColour('a')"
                            fill-opacity="0.12"
                            stroke-dasharray="0.6 0.4"
                            :stroke="sideColour('a')"
                            stroke-width="0.08"
                        />
                        <component
                            :is="shapeTag(zones.b)"
                            v-bind="shapeAttrs(zones.b)"
                            :fill="sideColour('b')"
                            fill-opacity="0.12"
                            stroke-dasharray="0.6 0.4"
                            :stroke="sideColour('b')"
                            stroke-width="0.08"
                        />
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
                                stroke="hsl(var(--foreground) / 0.55)"
                                stroke-width="0.07"
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
                    <g v-for="t in tokens" :key="t.id" :opacity="dimmed(elevationOf(t)) ? 0.25 : t.killed ? 0.35 : 1" :data-testid="`token-${t.id}`">
                        <circle
                            :cx="t.x"
                            :cy="t.y"
                            :r="baseRadius(t.baseMm)"
                            :fill="sideColour(t.side)"
                            fill-opacity="0.9"
                            :stroke="t.id === selectedId ? 'hsl(var(--foreground))' : 'white'"
                            :stroke-width="t.id === selectedId ? 0.16 : 0.08"
                        />
                        <text :x="t.x" :y="t.y + 0.3" text-anchor="middle" font-size="0.85" fill="white" font-weight="600" pointer-events="none">
                            {{ t.name.slice(0, 1) }}{{ elevationOf(t) > 0 ? `+${elevationOf(t)}` : '' }}
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
                <Button variant="outline" size="sm" aria-label="Zoom in" @click="zoomBy(0.8)"><Plus class="size-4" /></Button>
                <Button variant="outline" size="sm" aria-label="Zoom out" @click="zoomBy(1.25)"><Minus class="size-4" /></Button>
                <Button variant="outline" size="sm" aria-label="Fit the whole table" @click="resetView"><Maximize class="size-4" /></Button>
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

            <label class="block text-sm">
                <span class="mb-1 block font-medium">Level shown</span>
                <select v-model="level" class="w-full rounded-md border bg-background px-2 py-1.5" data-testid="level-select">
                    <option value="all">All levels</option>
                    <option v-for="l in levels" :key="l" :value="l">{{ l === 0 ? 'Table only' : `Up to Ht ${l}` }}</option>
                </select>
            </label>
            <label class="block text-sm">
                <span class="mb-1 block font-medium">Deployment zones</span>
                <select v-model="deployment" class="w-full rounded-md border bg-background px-2 py-1.5" data-testid="deployment-select">
                    <option value="none">None</option>
                    <option value="standard">Standard (8" from an edge)</option>
                    <option value="corner">Corner (12" from a corner)</option>
                </select>
            </label>

            <div class="rounded-md border p-3 text-sm" data-testid="selected-panel">
                <template v-if="selected">
                    <div class="font-semibold">
                        {{ selected.name }} <span class="font-normal text-muted-foreground">{{ selected.baseMm }} mm base</span>
                    </div>
                    <div class="text-muted-foreground">
                        At {{ fmt(selected.x) }}", {{ fmt(selected.y) }}". Elevation {{ elevationOf(selected)
                        }}<span v-if="supports.get(selected.id)?.piece"> on {{ supports.get(selected.id)?.piece?.name }}</span
                        >.
                    </div>
                    <Button v-if="sandbox" class="mt-2" variant="outline" size="sm" @click="removeSelected"
                        ><Trash2 class="mr-1 size-4" />Remove</Button
                    >
                </template>
                <span v-else class="text-muted-foreground"
                    >Tap a model to select it. Drag it to move. Drag the board to pan; pinch or scroll to zoom.</span
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
