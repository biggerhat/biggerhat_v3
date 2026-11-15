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
    movePiece,
    rotatePiece90,
    shapeBounds,
    tallClimbablePieces,
    terrainCoverage,
    type Surface,
    type TerrainPiece,
    type TerrainTrait,
} from '@/lib/tabletop/geometry';
import { TERRAIN_LAYOUTS } from '@/lib/tabletop/terrainLayouts';
import { TERRAIN_PRESETS, newPieceId } from '@/lib/tabletop/terrainPresets';
import { Copy, Layers, Minus, Plus, RotateCw, Trash2, Undo2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps<{
    pieces: TerrainPiece[];
    selectedId: string | null;
    /** Where new pieces appear: the middle of what the board is showing. */
    anchor: { x: number; y: number };
    canUndo: boolean;
}>();

const emit = defineEmits<{
    change: [pieces: TerrainPiece[]];
    select: [id: string | null];
    undo: [];
    clear: [];
    demo: [];
    layout: [pieces: TerrainPiece[]];
}>();

const presetKey = ref(TERRAIN_PRESETS[0].key);
const layoutKey = ref(TERRAIN_LAYOUTS[0].key);
const layout = computed(() => TERRAIN_LAYOUTS.find((l) => l.key === layoutKey.value) ?? TERRAIN_LAYOUTS[0]);
const selected = computed(() => props.pieces.find((p) => p.id === props.selectedId) ?? null);

// The guide's advice for a standard table (p.60): roughly 45-65% covered, three to eight standable pieces of Ht 2 or more.
const coverage = computed(() => Math.round(terrainCoverage(props.pieces) * 100));
const tall = computed(() => tallClimbablePieces(props.pieces));
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

const setTop = (piece: TerrainPiece, s: Surface, top: number) => patchSurface(piece, s.id, (x) => ({ ...x, top: clampTop(top) }));

/** Resize about the centre so the piece stays where it was put; a bigger piece is nudged back if it would cross the table edge. */
function resize(piece: TerrainPiece, s: Surface, dims: { w?: number; h?: number; r?: number }) {
    const next = piece.surfaces.map((x) => {
        if (x.id !== s.id) return x;
        const sh = x.shape;
        if (sh.kind === 'rect') {
            const w = clampSize(dims.w ?? sh.w);
            const h = clampSize(dims.h ?? sh.h);
            return { ...x, shape: { ...sh, x: sh.x + (sh.w - w) / 2, y: sh.y + (sh.h - h) / 2, w, h } };
        }
        if (sh.kind === 'circle') return { ...x, shape: { ...sh, r: clampSize(dims.r ?? sh.r) } };
        return x;
    });
    replace(movePiece({ ...piece, surfaces: next }, 0, 0));
}
const numberFrom = (e: Event) => Number((e.target as HTMLInputElement).value) || 0;
const levelLabel = (piece: TerrainPiece, s: Surface) => {
    const order = [...piece.surfaces].sort((a, b) => a.top - b.top);
    return `Level ${order.indexOf(s) + 1}`;
};
</script>

<template>
    <div class="space-y-3 text-sm" data-testid="terrain-editor">
        <div class="rounded-md border p-3" data-testid="terrain-stats">
            <div class="flex items-center justify-between">
                <span class="font-medium">Table</span>
                <span class="text-muted-foreground">{{ pieces.length }} piece{{ pieces.length === 1 ? '' : 's' }}</span>
            </div>
            <div :class="coverageOk ? 'text-foreground' : 'text-amber-500'">
                Covered: {{ coverage }}% <span class="text-muted-foreground">(the guide suggests 45 to 65%)</span>
            </div>
            <div :class="tallOk ? 'text-foreground' : 'text-amber-500'">
                Climbable Ht 2+ pieces: {{ tall }} <span class="text-muted-foreground">(the guide suggests 3 to 8)</span>
            </div>
        </div>

        <div class="flex flex-wrap items-end gap-2">
            <label class="min-w-0 flex-1">
                <span class="mb-1 block font-medium">Add a piece</span>
                <select v-model="presetKey" class="w-full rounded-md border bg-background px-2 py-1.5" data-testid="terrain-preset">
                    <option v-for="p in TERRAIN_PRESETS" :key="p.key" :value="p.key">{{ p.label }}</option>
                </select>
            </label>
            <Button size="sm" data-testid="terrain-add" @click="addPiece"><Plus class="mr-1 size-4" />Add</Button>
        </div>
        <div class="space-y-1" data-testid="terrain-layouts">
            <div class="flex items-end gap-2">
                <label class="min-w-0 flex-1 text-sm">
                    <span class="mb-1 block font-medium">Ready-made table</span>
                    <select v-model="layoutKey" class="w-full rounded-md border bg-background px-2 py-1.5" data-testid="terrain-layout">
                        <option v-for="l in TERRAIN_LAYOUTS" :key="l.key" :value="l.key">{{ l.label }}</option>
                    </select>
                </label>
                <Button size="sm" variant="outline" data-testid="terrain-layout-load" @click="emit('layout', layout.pieces())">Use it</Button>
            </div>
            <p class="text-xs text-muted-foreground">{{ layout.description }} Replaces the table; Undo brings yours back.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <Button size="sm" variant="outline" data-testid="terrain-undo" :disabled="!canUndo" @click="emit('undo')"
                ><Undo2 class="mr-1 size-4" />Undo</Button
            >
            <Button size="sm" variant="outline" data-testid="terrain-demo" @click="emit('demo')">Demo table</Button>
            <Button size="sm" variant="outline" data-testid="terrain-clear" :disabled="!pieces.length" @click="emit('clear')">Clear table</Button>
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
                <Button size="sm" variant="outline" data-testid="terrain-rotate" @click="replace(rotatePiece90(selected))"
                    ><RotateCw class="mr-1 size-4" />Turn 90°</Button
                >
                <Button size="sm" variant="outline" data-testid="terrain-duplicate" @click="duplicate(selected)"
                    ><Copy class="mr-1 size-4" />Copy</Button
                >
                <Button size="sm" variant="outline" data-testid="terrain-delete" @click="remove(selected)"
                    ><Trash2 class="mr-1 size-4" />Delete</Button
                >
            </div>

            <fieldset>
                <legend class="mb-1 font-medium">Traits</legend>
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
                <div v-for="s in selected.surfaces" :key="s.id" class="mb-2 rounded-md border p-2" data-testid="terrain-level">
                    <div class="mb-2 flex items-center justify-between">
                        <span class="font-medium">{{ levelLabel(selected, s) }}</span>
                        <Button
                            v-if="selected.surfaces.length > 1"
                            size="sm"
                            variant="ghost"
                            aria-label="Remove this level"
                            data-testid="terrain-remove-level"
                            @click="removeLevel(selected, s.id)"
                            ><Trash2 class="size-4"
                        /></Button>
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
                        <Button size="sm" variant="outline" aria-label="Raise" data-testid="terrain-ht-plus" @click="setTop(selected, s, s.top + 0.5)"
                            ><Plus class="size-4"
                        /></Button>
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
                        /></label>
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
                        /></label>
                        <span class="text-muted-foreground">inches</span>
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
                    <p v-else class="mt-2 text-muted-foreground">Custom outline. Move it by dragging; change its height here.</p>
                </div>
                <p class="text-muted-foreground">
                    A model stands at the height of the highest level that holds at least half its base. Drag the piece on the board to move it.
                </p>
            </div>
        </div>
        <p v-else class="text-muted-foreground">Tap a piece on the board to edit it, or add one above.</p>
    </div>
</template>
