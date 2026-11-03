<script setup lang="ts">
import ArtImage from '@/components/CardCreator/ArtImage.vue';
import { Button } from '@/components/ui/button';
import { Move, RotateCcw } from 'lucide-vue-next';
import { onBeforeUnmount, ref, watch } from 'vue';

/**
 * Lets the player choose which part of the art shows in the card's art window: drag the image (or use the arrow
 * keys) to pan — freely, including past the frame edges — and the slider to zoom. The frame has the card's art-window aspect ratio, so what is visible
 * here is what the card shows.
 */
const props = defineProps<{ src: string; alt: string }>();
const x = defineModel<number>('x', { required: true });
const y = defineModel<number>('y', { required: true });
const zoom = defineModel<number>('zoom', { required: true });
const dx = defineModel<number>('dx', { default: 0 });
const dy = defineModel<number>('dy', { default: 0 });

const frame = ref<HTMLElement | null>(null);
const natural = ref<{ w: number; h: number } | null>(null);

// The natural size drives how far a pixel of dragging should move the focus point.
watch(
    () => props.src,
    (src) => {
        natural.value = null;
        const img = new Image();
        img.onload = () => (natural.value = { w: img.naturalWidth, h: img.naturalHeight });
        img.src = src.startsWith('http') || src.startsWith('/') || src.startsWith('blob:') ? src : '/storage/' + src;
    },
    { immediate: true },
);

// Total on-screen travel (px) of the image content as x or y goes 0 -> 100, for the current zoom.
const travel = () => {
    const el = frame.value;
    if (!el) return { x: 0, y: 0 };
    const fw = el.clientWidth;
    const fh = el.clientHeight;
    const z = zoom.value / 100;
    const bw = fw * z;
    const bh = fh * z;
    let overflowX = 0;
    let overflowY = 0;
    if (natural.value) {
        const scale = Math.max(bw / natural.value.w, bh / natural.value.h);
        overflowX = Math.max(0, natural.value.w * scale - bw);
        overflowY = Math.max(0, natural.value.h * scale - bh);
    }
    return { x: (z - 1) * fw + overflowX, y: (z - 1) * fh + overflowY };
};

const clamp = (v: number, lo = 0, hi = 100) => Math.min(hi, Math.max(lo, Math.round(v)));

// Position is two numbers per axis: the cover pan (x/y, 0-100, limited by how much of the art overflows the window)
// and a free offset (dx/dy, % of the window, ±100) that carries on past that limit — so the art can be pushed beyond
// the frame on either axis, including the axis where it fits exactly and can't pan at all. Both reduce to one
// on-screen shift S (px, positive = right/down) from the centred starting point; dragging adds to S and the split is
// recomputed (pan first, remainder into the offset), which keeps every drag and key press reversible.
const shiftOf = (axis: 'x' | 'y') => {
    const el = frame.value;
    if (!el) return 0;
    const t = travel()[axis];
    const dim = axis === 'x' ? el.clientWidth : el.clientHeight;
    const focus = axis === 'x' ? x.value : y.value;
    const offset = axis === 'x' ? dx.value : dy.value;
    return ((50 - focus) / 100) * t + (offset / 100) * dim;
};
const applyShift = (axis: 'x' | 'y', shift: number) => {
    const el = frame.value;
    if (!el) return;
    const t = travel()[axis];
    const dim = axis === 'x' ? el.clientWidth : el.clientHeight;
    const focus = t >= 1 ? clamp(50 - (shift / t) * 100) : 50;
    const rest = shift - ((50 - focus) / 100) * Math.max(t, 0);
    const offset = clamp((rest / dim) * 100, -100, 100);
    if (axis === 'x') {
        x.value = focus;
        dx.value = offset;
    } else {
        y.value = focus;
        dy.value = offset;
    }
};

let drag: { px: number; py: number; sx: number; sy: number } | null = null;
const onDown = (e: PointerEvent) => {
    drag = { px: e.clientX, py: e.clientY, sx: shiftOf('x'), sy: shiftOf('y') };
    (e.currentTarget as HTMLElement).setPointerCapture(e.pointerId);
};
const onMove = (e: PointerEvent) => {
    if (!drag) return;
    applyShift('x', drag.sx + (e.clientX - drag.px));
    applyShift('y', drag.sy + (e.clientY - drag.py));
};
const onUp = () => (drag = null);

const onKey = (e: KeyboardEvent) => {
    const el = frame.value;
    if (!el) return;
    const step = e.shiftKey ? 0.1 : 0.03;
    const moves: Record<string, [number, number]> = { ArrowLeft: [-1, 0], ArrowRight: [1, 0], ArrowUp: [0, -1], ArrowDown: [0, 1] };
    const m = moves[e.key];
    if (!m) return;
    e.preventDefault();
    // The art moves in the direction of the arrow.
    if (m[0]) applyShift('x', shiftOf('x') + m[0] * step * el.clientWidth);
    if (m[1]) applyShift('y', shiftOf('y') + m[1] * step * el.clientHeight);
};

const reset = () => {
    x.value = 50;
    y.value = 50;
    dx.value = 0;
    dy.value = 0;
    zoom.value = 100;
};

onBeforeUnmount(() => (drag = null));
</script>

<template>
    <div class="space-y-2">
        <div
            ref="frame"
            class="relative aspect-[550/440] w-full max-w-sm cursor-grab touch-none select-none overflow-hidden rounded-md border bg-neutral-800 outline-none ring-offset-background focus-visible:ring-2 focus-visible:ring-ring active:cursor-grabbing"
            role="application"
            tabindex="0"
            :aria-label="`Reposition ${alt}. Drag, or use the arrow keys, to choose which part of the art shows on the card.`"
            @pointerdown="onDown"
            @pointermove="onMove"
            @pointerup="onUp"
            @pointercancel="onUp"
            @keydown="onKey"
        >
            <ArtImage
                :src="src.startsWith('http') || src.startsWith('/') || src.startsWith('blob:') ? src : '/storage/' + src"
                :alt="alt"
                :x="x"
                :y="y"
                :zoom="zoom"
                :dx="dx"
                :dy="dy"
            />
            <span class="pointer-events-none absolute left-2 top-2 flex items-center gap-1 rounded bg-black/60 px-1.5 py-0.5 text-[10px] text-white">
                <Move class="size-3" /> Drag to reposition
            </span>
        </div>
        <div class="flex max-w-sm items-center gap-3">
            <label for="art-zoom" class="text-xs text-muted-foreground">Zoom</label>
            <input id="art-zoom" v-model.number="zoom" type="range" min="100" max="300" step="5" class="h-1.5 flex-1 accent-primary" />
            <span class="w-10 text-right text-xs tabular-nums text-muted-foreground">{{ (zoom / 100).toFixed(2).replace(/0$/, '') }}×</span>
            <Button type="button" variant="ghost" size="sm" class="h-7 gap-1 px-2 text-xs" @click="reset"><RotateCcw class="size-3" />Reset</Button>
        </div>
    </div>
</template>
