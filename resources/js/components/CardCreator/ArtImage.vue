<script setup lang="ts">
import { computed } from 'vue';

/**
 * Character art filling its (relative, overflow-hidden) parent, framed by a focus point and a zoom.
 *
 * x / y are 0-100: which part of the image sits at the matching edge of the window (0 = left/top, 100 = right/
 * bottom). zoom is a percentage (100 = fill the window like `object-fit: cover`). The image is drawn in a box
 * `zoom` times the window and shifted by the same x/y percentage, then cover-positioned inside that box — so one
 * pair of numbers pans both the zoomed box and any cover overflow, and the focus point stays put as zoom changes.
 * Pure CSS (no measuring), so it renders identically in the live preview, the html-to-image export and the
 * server-side headless capture.
 */
const props = withDefaults(
    defineProps<{
        src: string;
        alt: string;
        x?: number;
        y?: number;
        zoom?: number;
    }>(),
    { x: 50, y: 50, zoom: 100 },
);

const style = computed(() => {
    const z = Math.min(300, Math.max(100, props.zoom)) / 100;
    const x = Math.min(100, Math.max(0, props.x));
    const y = Math.min(100, Math.max(0, props.y));
    return {
        position: 'absolute' as const,
        width: `${z * 100}%`,
        height: `${z * 100}%`,
        left: `${-(z - 1) * x}%`,
        top: `${-(z - 1) * y}%`,
        objectFit: 'cover' as const,
        objectPosition: `${x}% ${y}%`,
        maxWidth: 'none',
    };
});
</script>

<template>
    <img :src="src" :alt="alt" :style="style" draggable="false" />
</template>
