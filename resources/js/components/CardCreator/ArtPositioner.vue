<script setup lang="ts">
import ArtImage from '@/components/CardCreator/ArtImage.vue';
import { Button } from '@/components/ui/button';
import { Move, RotateCcw } from 'lucide-vue-next';
import { onBeforeUnmount, ref, watch } from 'vue';

/**
 * Lets the player choose which part of the art shows in the card's art window: drag the image (or use the arrow
 * keys) to pan, and the slider to zoom. The frame has the card's art-window aspect ratio, so what is visible
 * here is what the card shows.
 */
const props = defineProps<{ src: string; alt: string }>();
const x = defineModel<number>('x', { required: true });
const y = defineModel<number>('y', { required: true });
const zoom = defineModel<number>('zoom', { required: true });

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

const clamp = (v: number) => Math.min(100, Math.max(0, Math.round(v)));

let drag: { px: number; py: number; x: number; y: number } | null = null;
const onDown = (e: PointerEvent) => {
    drag = { px: e.clientX, py: e.clientY, x: x.value, y: y.value };
    (e.currentTarget as HTMLElement).setPointerCapture(e.pointerId);
};
const onMove = (e: PointerEvent) => {
    if (!drag) return;
    const t = travel();
    // Dragging the picture right reveals more of its left side, i.e. the focus moves toward 0.
    if (t.x > 0) x.value = clamp(drag.x - ((e.clientX - drag.px) / t.x) * 100);
    if (t.y > 0) y.value = clamp(drag.y - ((e.clientY - drag.py) / t.y) * 100);
};
const onUp = () => (drag = null);

const onKey = (e: KeyboardEvent) => {
    const step = e.shiftKey ? 10 : 3;
    const moves: Record<string, [number, number]> = { ArrowLeft: [-step, 0], ArrowRight: [step, 0], ArrowUp: [0, -step], ArrowDown: [0, step] };
    const m = moves[e.key];
    if (!m) return;
    e.preventDefault();
    x.value = clamp(x.value + m[0]);
    y.value = clamp(y.value + m[1]);
};

const reset = () => {
    x.value = 50;
    y.value = 50;
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
