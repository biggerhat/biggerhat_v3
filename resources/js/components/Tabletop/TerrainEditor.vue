<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    SURFACES_PER_PIECE,
    SURFACE_MAX_TOP,
    SURFACE_MIN_TOP,
    TABLE_SIZE,
    TERRAIN_TRAITS,
    clampSize,
    clampTop,
    OPENINGS_MAX,
    addOpening,
    makeEnterable,
    makeSolid,
    movePiece,
    rotatePiece,
    setShapeAngle,
    shapeAngle,
    shapeBounds,
    tableChecks,
    tallClimbablePieces,
    terrainCoverage,
    type DeploymentZones,
    type Surface,
    type TableCheckItem,
    type TerrainPiece,
    type TerrainTrait,
} from '@/lib/tabletop/geometry';
import { TERRAIN_KIND_LABELS, TERRAIN_PRESETS, newPieceId } from '@/lib/tabletop/terrainPresets';
import { ChevronDown, Copy, Layers, PenLine, Minus, Plus, RotateCcw, RotateCw, Trash2, Undo2 } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

const props = defineProps<{
    pieces: TerrainPiece[];
    selectedId: string | null;
    /** The chosen deployment's zones, for the checks that need them. */
    zones?: DeploymentZones | null;
    /** Where new pieces appear: the middle of what the board is showing. */
    anchor: { x: number; y: number };
    canUndo: boolean;
    /** Corners placed so far while drawing an outline, or null when not drawing. */
    drawing?: number | null;
    /** The textures on offer, kind to picture (an admin may have replaced the built-in ones). */
    terrainImages?: Record<string, string>;
    /** The selected building has its roof faded so the inside shows. */
    insideShown?: boolean;
    /** The level whose handles are showing on the board. */
    activeSurfaceId?: string | null;
    /** A corner of a drawn outline is picked on the board, so it can be taken away. */
    vertexSelected?: boolean;
}>();

const emit = defineEmits<{
    change: [pieces: TerrainPiece[]];
    select: [id: string | null];
    undo: [];
    clear: [];
    draw: [];
    finishDraw: [];
    cancelDraw: [];
    undoCorner: [];
    selectSurface: [id: string];
    toggleInside: [id: string];
    removeCorner: [];
}>();

const presetKey = ref(TERRAIN_PRESETS[0].key);
/** The backgrounds a piece can be given: every texture the table has. */
const backgrounds = computed(() =>
    Object.keys(props.terrainImages ?? {})
        .map((key) => ({ key, label: TERRAIN_KIND_LABELS[key] ?? key }))
        .sort((a, b) => a.label.localeCompare(b.label)),
);
/** Enterable buildings: on or off, a door or window more, a gap's width, one fewer. */
const toggleEnterable = (piece: TerrainPiece, on: boolean) => replace(on ? makeEnterable(piece) : makeSolid(piece));
const newOpening = (piece: TerrainPiece, kind: 'door' | 'window') => {
    const next = addOpening(piece, kind);
    if (next) replace(next);
};
const setOpeningWidth = (piece: TerrainPiece, id: string, w: number) =>
    piece.interior &&
    replace({
        ...piece,
        interior: {
            ...piece.interior,
            openings: piece.interior.openings.map((o) => (o.id === id ? { ...o, w: Math.max(0.5, Math.min(6, Math.round(w * 2) / 2)) } : o)),
        },
    });
const removeOpening = (piece: TerrainPiece, id: string) =>
    piece.interior && replace({ ...piece, interior: { ...piece.interior, openings: piece.interior.openings.filter((o) => o.id !== id) } });
const setFloor = (piece: TerrainPiece, n: number) =>
    piece.interior && replace({ ...piece, interior: { ...piece.interior, floor: Math.max(0, Math.min(12, Math.round(n * 2) / 2)) } });
function setBackground(piece: TerrainPiece, key: string) {
    const next: TerrainPiece = { ...piece };
    if (key) next.kind = key;
    else delete next.kind;
    replace(next);
}
const selected = computed(() => props.pieces.find((p) => p.id === props.selectedId) ?? null);

// The guide's advice for a standard table (p.60): roughly 45-65% covered, three to eight standable pieces of Ht 2 or more.
const coverage = computed(() => Math.round(terrainCoverage(props.pieces) * 100));
const tall = computed(() => tallClimbablePieces(props.pieces));
/**
 * The Gaining Grounds table guidelines, measured on the pieces. They take a moment, so they are worked out only while the fold is open and a
 * little after the table stops changing.
 */
const checkOpen = ref(false);
const checks = ref<TableCheckItem[]>([]);
let checkTimer: ReturnType<typeof setTimeout> | null = null;
function refreshChecks() {
    if (checkTimer) clearTimeout(checkTimer);
    if (!checkOpen.value) return;
    checkTimer = setTimeout(() => (checks.value = tableChecks(props.pieces, props.zones ?? null)), 250);
}
watch([() => props.pieces, () => props.zones, checkOpen], refreshChecks);
const checkNotes = computed(() => checks.value.filter((c) => c.status === 'note').length);
const coverageOk = computed(() => coverage.value >= 45 && coverage.value <= 65);
const tallOk = computed(() => tall.value >= 3 && tall.value <= 8);

function replace(piece: TerrainPiece) {
    emit(
        'change',
        props.pieces.map((p) => (p.id === piece.id ? piece : p)),
    );
}
function patchSurface(piece: TerrainPiece, surfaceId: string, patch: (s: Surface) => Surface) {
    replace({ ...piece, surfaces: piece.surfaces.map((s) => (s.id === surfaceId ? patch(s) : s)) });
}

function addPiece() {
    const preset = TERRAIN_PRESETS.find((p) => p.key === presetKey.value);
    if (!preset) return;
    // Fan new pieces out a little so several added in a row do not sit exactly on top of each other.
    const nudge = (props.pieces.length % 5) * 1.5;
    const piece = movePiece(preset.make(newPieceId(), props.anchor.x + nudge, props.anchor.y + nudge), 0, 0);
    emit('change', [...props.pieces, piece]);
    emit('select', piece.id);
}
function duplicate(piece: TerrainPiece) {
    const id = newPieceId();
    const copy = movePiece(
        { ...piece, id, name: `${piece.name} copy`, surfaces: piece.surfaces.map((s, i) => ({ ...s, id: `${id}-${i + 1}` })) },
        1.5,
        1.5,
    );
    emit('change', [...props.pieces, copy]);
    emit('select', copy.id);
}
function remove(piece: TerrainPiece) {
    emit(
        'change',
        props.pieces.filter((p) => p.id !== piece.id),
    );
    emit('select', null);
}

function toggleTrait(piece: TerrainPiece, trait: TerrainTrait) {
    const traits = piece.traits.includes(trait) ? piece.traits.filter((t) => t !== trait) : [...piece.traits, trait];
    replace({ ...piece, traits });
}

/** A new level on top of the highest one: half its size, one inch higher. */
function addLevel(piece: TerrainPiece) {
    if (piece.surfaces.length >= SURFACES_PER_PIECE) return;
    const top = piece.surfaces.reduce((a, b) => (b.top >= a.top ? b : a));
    const b = shapeBounds(top.shape);
    const cx = (b.minX + b.maxX) / 2;
    const cy = (b.minY + b.maxY) / 2;
    const shape =
        top.shape.kind === 'circle'
            ? { kind: 'circle' as const, cx, cy, r: clampSize(top.shape.r / 2) }
            : top.shape.kind === 'ellipse'
              ? { ...top.shape, rx: clampSize(top.shape.rx / 2), ry: clampSize(top.shape.ry / 2) }
              : {
                    kind: 'rect' as const,
                    x: cx - clampSize((b.maxX - b.minX) / 4),
                    y: cy - clampSize((b.maxY - b.minY) / 4),
                    w: clampSize((b.maxX - b.minX) / 2),
                    h: clampSize((b.maxY - b.minY) / 2),
                };
    replace({ ...piece, surfaces: [...piece.surfaces, { id: `${piece.id}-${Date.now().toString(36)}`, shape, top: clampTop(top.top + 1) }] });
}
function removeLevel(piece: TerrainPiece, surfaceId: string) {
    if (piece.surfaces.length > 1) replace({ ...piece, surfaces: piece.surfaces.filter((s) => s.id !== surfaceId) });
}

/** An archway beneath a level (a bridge): switch it on, or off. The height models must fit under is kept between half an inch and the level's own height. */
function setArchway(piece: TerrainPiece, s: Surface, on: boolean) {
    patchSurface(piece, s.id, (x) => {
        const next: Surface = { ...x };
        if (on) next.under = Math.max(0.5, Math.min(2, x.top));
        else delete next.under;
        return next;
    });
}
const setUnder = (piece: TerrainPiece, s: Surface, n: number) =>
    patchSurface(piece, s.id, (x) => ({ ...x, under: Math.max(0.5, Math.min(x.top, Math.round(n * 2) / 2)) }));
const setTop = (piece: TerrainPiece, s: Surface, top: number) => patchSurface(piece, s.id, (x) => ({ ...x, top: clampTop(top) }));

/** Resize about the centre so the piece stays where it was put; a bigger piece is nudged back if it would cross the table edge. */
function resize(piece: TerrainPiece, s: Surface, dims: { w?: number; h?: number; r?: number; rx?: number; ry?: number }) {
    const next = piece.surfaces.map((x) => {
        if (x.id !== s.id) return x;
        const sh = x.shape;
        if (sh.kind === 'rect') {
            const w = clampSize(dims.w ?? sh.w);
            const h = clampSize(dims.h ?? sh.h);
            return { ...x, shape: { ...sh, x: sh.x + (sh.w - w) / 2, y: sh.y + (sh.h - h) / 2, w, h } };
        }
        if (sh.kind === 'circle') return { ...x, shape: { ...sh, r: clampSize(dims.r ?? sh.r) } };
        if (sh.kind === 'ellipse') return { ...x, shape: { ...sh, rx: clampSize(dims.rx ?? sh.rx), ry: clampSize(dims.ry ?? sh.ry) } };
        return x;
    });
    replace(movePiece({ ...piece, surfaces: next }, 0, 0));
}
/** Turn one rectangle or oval to an exact angle about its own middle. */
function setAngle(piece: TerrainPiece, s: Surface, deg: number) {
    const surfaces = piece.surfaces.map((x) => (x.id === s.id ? { ...x, shape: setShapeAngle(x.shape, Math.round(deg)) } : x));
    replace(movePiece({ ...piece, surfaces }, 0, 0));
}
const numberFrom = (e: Event) => Number((e.target as HTMLInputElement).value) || 0;
/** One line for a folded level: its height and size. */
function levelSummary(s: Surface): string {
    const sh = s.shape;
    const size =
        sh.kind === 'rect'
            ? `${sh.w}x${sh.h}`
            : sh.kind === 'circle'
              ? `radius ${sh.r}`
              : sh.kind === 'ellipse'
                ? `${sh.rx * 2}x${sh.ry * 2}`
                : `${sh.points.length} corners`;
    return `Ht ${s.top} · ${size}${s.under !== undefined ? ' · archway' : ''}`;
}
const levelLabel = (piece: TerrainPiece, s: Surface) => {
    const order = [...piece.surfaces].sort((a, b) => a.top - b.top);
    return `Level ${order.indexOf(s) + 1}`;
};
</script>

<template>
    <div class="space-y-3 text-sm" role="tabpanel" aria-label="Terrain" data-testid="terrain-editor">
        <div class="rounded-md border p-3" data-testid="terrain-stats">
            <div class="flex items-center justify-between">
                <span class="font-medium">Table</span>
                <span class="text-muted-foreground">{{ pieces.length }} piece{{ pieces.length === 1 ? '' : 's' }}</span>
            </div>
            <div :class="!pieces.length || coverageOk ? 'text-foreground' : 'text-amber-500'">
                Covered: {{ coverage }}% <span class="text-muted-foreground">(aim for 45-65%)</span>
            </div>
            <div :class="!pieces.length || tallOk ? 'text-foreground' : 'text-amber-500'">
                Climbable Ht 2+ pieces: {{ tall }} <span class="text-muted-foreground">(aim for 3-8)</span>
            </div>
        </div>

        <details class="group rounded-md border" data-testid="table-check" @toggle="checkOpen = ($event.target as HTMLDetailsElement).open">
            <summary class="flex cursor-pointer list-none items-center justify-between gap-2 p-3 [&::-webkit-details-marker]:hidden">
                <span class="font-medium">Table check</span>
                <span class="min-w-0 flex-1 truncate text-right text-muted-foreground" data-testid="table-check-summary">{{
                    !checkOpen
                        ? 'Gaining Grounds guidelines'
                        : !pieces.length
                          ? 'add some terrain'
                          : checkNotes
                            ? `${checkNotes} to look at`
                            : 'all within the guide'
                }}</span>
                <ChevronDown class="size-4 shrink-0 transition-transform group-open:rotate-180" />
            </summary>
            <ul class="space-y-2 border-t p-3 text-xs">
                <li v-if="!pieces.length" class="text-muted-foreground">Add some terrain to check the table.</li>
                <li v-for="c in checks" :key="c.id" :data-status="c.status" data-testid="table-check-item">
                    <span class="font-medium" :class="c.status === 'note' ? 'text-amber-500' : ''">{{ c.label }}</span>
                    <span class="block text-muted-foreground">{{ c.text }}</span>
                </li>
                <li v-if="pieces.length && !zones" class="text-muted-foreground">
                    Choose a deployment in the Table section to check the lanes between the zones and how much of each is reachable.
                </li>
            </ul>
        </details>

        <div class="space-y-2 rounded-md border p-3" data-testid="terrain-add-panel">
            <div class="flex flex-wrap items-end gap-2">
                <label class="min-w-0 flex-1">
                    <span class="mb-1 block font-medium">Add a piece</span>
                    <select v-model="presetKey" class="w-full rounded-md border bg-background px-2 py-1.5" data-testid="terrain-preset">
                        <option v-for="p in TERRAIN_PRESETS" :key="p.key" :value="p.key">{{ p.label }}</option>
                    </select>
                </label>
                <Button size="sm" data-testid="terrain-add" @click="addPiece"><Plus class="mr-1 size-4" />Add</Button>
            </div>
            <Button size="sm" variant="outline" data-testid="terrain-draw" :disabled="drawing !== null && drawing !== undefined" @click="emit('draw')"
                ><PenLine class="mr-1 size-4" />Draw an outline</Button
            >
            <div
                v-if="drawing !== null && drawing !== undefined"
                class="space-y-2 rounded-md border border-amber-500/60 p-3 text-sm"
                data-testid="outline-help"
            >
                <div class="font-medium">Drawing an outline</div>
                <p class="text-muted-foreground">
                    Tap the board to place corners ({{ drawing }} so far). Tap the first corner again, or press Finish, to close the shape. Corners
                    snap to half inches.
                </p>
                <div class="flex flex-wrap gap-2">
                    <Button size="sm" data-testid="outline-finish" :disabled="drawing < 3" @click="emit('finishDraw')">Finish</Button>
                    <Button size="sm" variant="outline" data-testid="outline-undo" :disabled="drawing < 1" @click="emit('undoCorner')"
                        >Undo corner</Button
                    >
                    <Button size="sm" variant="ghost" data-testid="outline-cancel" @click="emit('cancelDraw')">Cancel</Button>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                <Button size="sm" variant="outline" data-testid="terrain-undo" :disabled="!canUndo" @click="emit('undo')"
                    ><Undo2 class="mr-1 size-4" />Undo</Button
                >
                <Button size="sm" variant="outline" data-testid="terrain-clear" :disabled="!pieces.length" @click="emit('clear')">Clear table</Button>
            </div>
        </div>

        <div v-if="selected" class="space-y-3 rounded-md border p-3" data-testid="terrain-piece">
            <div class="flex items-center gap-2">
                <input
                    class="min-w-0 flex-1 rounded-md border bg-background px-2 py-1.5 font-medium"
                    maxlength="60"
                    aria-label="Piece name"
                    data-testid="terrain-name"
                    :value="selected.name"
                    @change="replace({ ...selected, name: ($event.target as HTMLInputElement).value.trim() || 'Terrain' })"
                />
            </div>
            <div class="flex flex-wrap gap-2">
                <Button size="sm" variant="outline" data-testid="terrain-rotate" @click="replace(rotatePiece(selected, 90))"
                    ><RotateCw class="mr-1 size-4" />Turn 90°</Button
                >
                <Button
                    size="sm"
                    variant="outline"
                    aria-label="Turn 15 degrees back"
                    data-testid="terrain-rotate-back"
                    @click="replace(rotatePiece(selected, -15))"
                    ><RotateCcw class="mr-1 size-4" />-15°</Button
                >
                <Button
                    size="sm"
                    variant="outline"
                    aria-label="Turn 15 degrees on"
                    data-testid="terrain-rotate-on"
                    @click="replace(rotatePiece(selected, 15))"
                    ><RotateCw class="mr-1 size-4" />+15°</Button
                >
                <Button size="sm" variant="outline" data-testid="terrain-duplicate" @click="duplicate(selected)"
                    ><Copy class="mr-1 size-4" />Copy</Button
                >
                <Button size="sm" variant="outline" data-testid="terrain-delete" @click="remove(selected)"
                    ><Trash2 class="mr-1 size-4" />Delete</Button
                >
            </div>

            <label v-if="backgrounds.length" class="block">
                <span class="mb-1 block font-medium">Background</span>
                <select
                    class="w-full rounded-md border bg-background px-2 py-1.5"
                    data-testid="terrain-background"
                    :value="selected.kind ?? ''"
                    @change="setBackground(selected, ($event.target as HTMLSelectElement).value)"
                >
                    <option value="">Plain (just the height tint)</option>
                    <option v-for="b in backgrounds" :key="b.key" :value="b.key">{{ b.label }}</option>
                </select>
            </label>

            <details class="group rounded-md border" :open="!!selected.interior" data-testid="terrain-interior">
                <summary class="flex cursor-pointer list-none items-center justify-between gap-2 p-2 [&::-webkit-details-marker]:hidden">
                    <span class="font-medium">Building</span>
                    <span class="min-w-0 flex-1 truncate text-right text-muted-foreground">{{
                        selected.interior
                            ? `enterable, ${selected.interior.openings.length} opening${selected.interior.openings.length === 1 ? '' : 's'}`
                            : 'solid'
                    }}</span>
                    <ChevronDown class="size-4 shrink-0 transition-transform group-open:rotate-180" />
                </summary>
                <div class="space-y-2 border-t p-2">
                    <label class="flex cursor-pointer items-center gap-1 font-medium" title="A model can walk in through a door and fight inside">
                        <input
                            type="checkbox"
                            :checked="!!selected.interior"
                            data-testid="terrain-enterable"
                            @change="toggleEnterable(selected, ($event.target as HTMLInputElement).checked)"
                        />
                        Can be entered
                    </label>
                    <template v-if="selected.interior">
                        <p class="text-muted-foreground">
                            The inside is the outline of its lowest level. A model inside stands on the floor and is seen only through doors and
                            windows; drag the dots on the walls to slide them.
                        </p>
                        <div class="flex flex-wrap items-center gap-2">
                            <label class="flex items-center gap-1"
                                >Floor Ht
                                <input
                                    type="number"
                                    step="0.5"
                                    min="0"
                                    max="12"
                                    class="w-16 rounded-md border bg-background px-2 py-1.5 text-center"
                                    data-testid="terrain-floor"
                                    :value="selected.interior.floor"
                                    @change="setFloor(selected, numberFrom($event))"
                            /></label>
                            <Button
                                size="sm"
                                variant="outline"
                                data-testid="terrain-add-door"
                                :disabled="selected.interior.openings.length >= OPENINGS_MAX"
                                @click="newOpening(selected, 'door')"
                                >Add door</Button
                            >
                            <Button
                                size="sm"
                                variant="outline"
                                data-testid="terrain-add-window"
                                :disabled="selected.interior.openings.length >= OPENINGS_MAX"
                                @click="newOpening(selected, 'window')"
                                >Add window</Button
                            >
                            <Button
                                size="sm"
                                :variant="insideShown ? 'default' : 'outline'"
                                :aria-pressed="insideShown"
                                data-testid="terrain-show-inside"
                                @click="emit('toggleInside', selected.id)"
                                >Show inside</Button
                            >
                        </div>
                        <div
                            v-for="o in selected.interior.openings"
                            :key="o.id"
                            class="flex flex-wrap items-center gap-2"
                            data-testid="terrain-opening"
                        >
                            <span class="w-14 capitalize">{{ o.kind }}</span>
                            <label class="flex items-center gap-1"
                                >W
                                <input
                                    type="number"
                                    step="0.5"
                                    min="0.5"
                                    max="6"
                                    class="w-16 rounded-md border bg-background px-2 py-1.5 text-center"
                                    data-testid="terrain-opening-w"
                                    :value="o.w"
                                    @change="setOpeningWidth(selected, o.id, numberFrom($event))"
                                />"</label
                            >
                            <Button
                                size="sm"
                                variant="ghost"
                                aria-label="Remove this opening"
                                data-testid="terrain-opening-remove"
                                @click="removeOpening(selected, o.id)"
                                ><Trash2 class="size-4"
                            /></Button>
                        </div>
                    </template>
                </div>
            </details>

            <details class="group rounded-md border" :open="!!selected.rules" data-testid="terrain-rules">
                <summary class="flex cursor-pointer list-none items-center justify-between gap-2 p-2 [&::-webkit-details-marker]:hidden">
                    <span class="font-medium">Rules card</span>
                    <span class="min-w-0 flex-1 truncate text-right text-muted-foreground">{{
                        selected.rules ? (selected.limit === 1 ? 'unique' : selected.limit ? `up to ${selected.limit}` : 'special terrain') : 'none'
                    }}</span>
                    <ChevronDown class="size-4 shrink-0 transition-transform group-open:rotate-180" />
                </summary>
                <div class="space-y-2 border-t p-2">
                    <textarea
                        class="w-full rounded-md border bg-background px-2 py-1.5"
                        rows="3"
                        maxlength="600"
                        aria-label="Rules card text"
                        placeholder="What this terrain does, as written on its card"
                        data-testid="terrain-rules-text"
                        :value="selected.rules ?? ''"
                        @change="replace({ ...selected, rules: ($event.target as HTMLTextAreaElement).value.trim() || undefined })"
                    ></textarea>
                    <label class="flex items-center gap-2">
                        <span>How many on the table</span>
                        <select
                            class="rounded-md border bg-background px-2 py-1"
                            data-testid="terrain-limit"
                            :value="selected.limit ?? ''"
                            @change="replace({ ...selected, limit: Number(($event.target as HTMLSelectElement).value) || undefined })"
                        >
                            <option value="">No limit</option>
                            <option value="1">Unique (1)</option>
                            <option v-for="n in [2, 3, 4, 5, 6]" :key="n" :value="n">Up to {{ n }}</option>
                        </select>
                    </label>
                </div>
            </details>

            <details open class="group rounded-md border" data-testid="terrain-traits">
                <summary class="flex cursor-pointer list-none items-center justify-between gap-2 p-2 [&::-webkit-details-marker]:hidden">
                    <span class="font-medium">Traits</span>
                    <span class="min-w-0 flex-1 truncate text-right text-muted-foreground">{{ selected.traits.join(', ') || 'none' }}</span>
                    <ChevronDown class="size-4 shrink-0 transition-transform group-open:rotate-180" />
                </summary>
                <fieldset class="border-t p-2">
                    <div class="flex flex-wrap gap-1">
                        <label
                            v-for="t in TERRAIN_TRAITS"
                            :key="t"
                            class="flex cursor-pointer items-center gap-1 rounded-md border px-2 py-1"
                            :class="selected.traits.includes(t) ? 'border-primary bg-primary/10' : ''"
                        >
                            <input
                                type="checkbox"
                                class="sr-only"
                                :checked="selected.traits.includes(t)"
                                :data-testid="`trait-${t}`"
                                @change="toggleTrait(selected, t)"
                            />
                            {{ t }}
                        </label>
                    </div>
                </fieldset>
            </details>

            <div>
                <div class="mb-1 flex items-center justify-between">
                    <span class="font-medium"
                        >Levels
                        <span class="font-normal text-muted-foreground">{{ selected.surfaces.length }} of {{ SURFACES_PER_PIECE }}</span></span
                    >
                    <Button
                        size="sm"
                        variant="outline"
                        data-testid="terrain-add-level"
                        :disabled="selected.surfaces.length >= SURFACES_PER_PIECE"
                        @click="addLevel(selected)"
                        ><Layers class="mr-1 size-4" />Add level</Button
                    >
                </div>
                <details
                    v-for="s in selected.surfaces"
                    :key="s.id"
                    class="group mb-2 rounded-md border"
                    :class="s.id === activeSurfaceId ? 'border-amber-500/70' : ''"
                    :open="selected.surfaces.length === 1 || s.id === activeSurfaceId"
                    data-testid="terrain-level"
                >
                    <summary
                        class="flex cursor-pointer list-none items-center justify-between gap-2 p-2 [&::-webkit-details-marker]:hidden"
                        title="Show this level's handles on the board"
                        data-testid="terrain-level-pick"
                        @click="emit('selectSurface', s.id)"
                    >
                        <span class="font-medium">{{ levelLabel(selected, s) }}</span>
                        <span class="min-w-0 flex-1 truncate text-right text-muted-foreground">{{ levelSummary(s) }}</span>
                        <ChevronDown class="size-4 shrink-0 transition-transform group-open:rotate-180" />
                    </summary>
                    <div class="space-y-2 border-t p-2">
                        <div v-if="selected.surfaces.length > 1" class="flex justify-end">
                            <Button
                                size="sm"
                                variant="ghost"
                                aria-label="Remove this level"
                                data-testid="terrain-remove-level"
                                @click="removeLevel(selected, s.id)"
                                ><Trash2 class="mr-1 size-4" />Remove level</Button
                            >
                        </div>
                        <div class="flex flex-wrap items-center gap-1">
                            <span class="text-muted-foreground">Ht</span>
                            <Button
                                size="sm"
                                variant="outline"
                                aria-label="Lower"
                                data-testid="terrain-ht-minus"
                                @click="setTop(selected, s, s.top - 0.5)"
                                ><Minus class="size-4"
                            /></Button>
                            <input
                                type="number"
                                inputmode="decimal"
                                class="w-16 rounded-md border bg-background px-2 py-1.5 text-center"
                                :min="SURFACE_MIN_TOP"
                                :max="SURFACE_MAX_TOP"
                                step="0.5"
                                aria-label="Height in inches"
                                data-testid="terrain-ht"
                                :value="s.top"
                                @change="setTop(selected, s, numberFrom($event))"
                            />
                            <Button
                                size="sm"
                                variant="outline"
                                aria-label="Raise"
                                data-testid="terrain-ht-plus"
                                @click="setTop(selected, s, s.top + 0.5)"
                                ><Plus class="size-4"
                            /></Button>
                        </div>
                        <div class="mt-2 flex flex-wrap items-center gap-2">
                            <label
                                class="flex cursor-pointer items-center gap-1"
                                title="A bridge: models that fit under it can stand and walk beneath, and sight passes under it"
                            >
                                <input
                                    type="checkbox"
                                    :checked="s.under !== undefined"
                                    data-testid="terrain-archway"
                                    @change="setArchway(selected, s, ($event.target as HTMLInputElement).checked)"
                                />
                                Archway beneath
                            </label>
                            <label v-if="s.under !== undefined" class="flex items-center gap-1"
                                >up to Ht
                                <input
                                    type="number"
                                    step="0.5"
                                    min="0.5"
                                    :max="s.top"
                                    class="w-16 rounded-md border bg-background px-2 py-1.5 text-center"
                                    aria-label="Archway height in inches"
                                    data-testid="terrain-under"
                                    :value="s.under"
                                    @change="setUnder(selected, s, numberFrom($event))"
                            /></label>
                        </div>
                        <div v-if="s.shape.kind === 'rect'" class="mt-2 flex flex-wrap items-center gap-2">
                            <label class="flex items-center gap-1"
                                >W
                                <input
                                    type="number"
                                    step="0.5"
                                    min="0.5"
                                    :max="TABLE_SIZE"
                                    class="w-16 rounded-md border bg-background px-2 py-1.5 text-center"
                                    data-testid="terrain-w"
                                    :value="s.shape.w"
                                    @change="resize(selected, s, { w: numberFrom($event) })"
                                />"</label
                            >
                            <label class="flex items-center gap-1"
                                >D
                                <input
                                    type="number"
                                    step="0.5"
                                    min="0.5"
                                    :max="TABLE_SIZE"
                                    class="w-16 rounded-md border bg-background px-2 py-1.5 text-center"
                                    data-testid="terrain-h"
                                    :value="s.shape.h"
                                    @change="resize(selected, s, { h: numberFrom($event) })"
                                />"</label
                            >
                            <label class="flex items-center gap-1"
                                >Turn
                                <input
                                    type="number"
                                    step="5"
                                    min="-90"
                                    max="90"
                                    class="w-16 rounded-md border bg-background px-2 py-1.5 text-center"
                                    data-testid="terrain-angle"
                                    :value="shapeAngle(s.shape)"
                                    @change="setAngle(selected, s, numberFrom($event))"
                                />°</label
                            >
                        </div>
                        <div v-else-if="s.shape.kind === 'ellipse'" class="mt-2 flex flex-wrap items-center gap-2">
                            <label class="flex items-center gap-1"
                                >Rx
                                <input
                                    type="number"
                                    step="0.5"
                                    min="0.5"
                                    :max="TABLE_SIZE"
                                    class="w-16 rounded-md border bg-background px-2 py-1.5 text-center"
                                    data-testid="terrain-rx"
                                    :value="s.shape.rx"
                                    @change="resize(selected, s, { rx: numberFrom($event) })"
                                />"</label
                            >
                            <label class="flex items-center gap-1"
                                >Ry
                                <input
                                    type="number"
                                    step="0.5"
                                    min="0.5"
                                    :max="TABLE_SIZE"
                                    class="w-16 rounded-md border bg-background px-2 py-1.5 text-center"
                                    data-testid="terrain-ry"
                                    :value="s.shape.ry"
                                    @change="resize(selected, s, { ry: numberFrom($event) })"
                                />"</label
                            >
                            <label class="flex items-center gap-1"
                                >Turn
                                <input
                                    type="number"
                                    step="5"
                                    min="-90"
                                    max="90"
                                    class="w-16 rounded-md border bg-background px-2 py-1.5 text-center"
                                    data-testid="terrain-angle"
                                    :value="shapeAngle(s.shape)"
                                    @change="setAngle(selected, s, numberFrom($event))"
                                />°</label
                            >
                        </div>
                        <div v-else-if="s.shape.kind === 'circle'" class="mt-2 flex items-center gap-2">
                            <label class="flex items-center gap-1"
                                >Radius
                                <input
                                    type="number"
                                    step="0.5"
                                    min="0.5"
                                    :max="TABLE_SIZE"
                                    class="w-16 rounded-md border bg-background px-2 py-1.5 text-center"
                                    data-testid="terrain-r"
                                    :value="s.shape.r"
                                    @change="resize(selected, s, { r: numberFrom($event) })"
                            /></label>
                            <span class="text-muted-foreground">inches</span>
                        </div>
                        <div v-else class="mt-2 space-y-1 text-muted-foreground">
                            <p>
                                Custom outline with {{ s.shape.kind === 'poly' ? s.shape.points.length : 0 }} corners. Drag a corner on the board to
                                reshape it, or drag the dot in the middle of an edge to add one.
                            </p>
                            <Button
                                v-if="s.id === activeSurfaceId"
                                size="sm"
                                variant="outline"
                                data-testid="terrain-remove-corner"
                                :disabled="!vertexSelected || (s.shape.kind === 'poly' && s.shape.points.length <= 3)"
                                @click="emit('removeCorner')"
                                ><Trash2 class="mr-1 size-4" />Remove the picked corner</Button
                            >
                        </div>
                    </div>
                </details>
                <p class="text-muted-foreground">
                    A model stands at the height of the highest level that holds at least half its base. Drag the piece on the board to move it; drag
                    the dots on its edge to reshape it and the dot above it to turn it.
                </p>
            </div>
        </div>
        <p v-else class="text-muted-foreground">Tap a piece on the board to edit it, or add one above.</p>
    </div>
</template>
