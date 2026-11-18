<script setup lang="ts">
import type { TerrainPiece } from '@/lib/tabletop/geometry';
import { shapeAttrs, shapeTag } from '@/lib/tabletop/shapeDraw';
import { computed } from 'vue';

/**
 * A small picture of a saved table: the surface and every terrain piece, drawn flat from above like the board, with the same textures.
 * Nothing here is interactive.
 */
const props = defineProps<{
    terrain: TerrainPiece[];
    tableImage?: string | null;
    tableImages?: { key: string; name: string; url: string; fit: 'tile' | 'cover' }[];
    terrainImages?: Record<string, string>;
    /** Makes the pattern ids unique when several thumbnails share a page. */
    uid: string;
}>();

const TILE = 6;
const surface = computed(
    () => props.tableImages?.find((t) => t.key === props.tableImage) ?? props.tableImages?.find((t) => t.key === 'grass') ?? null,
);
const kinds = computed(() => [...new Set(props.terrain.map((p) => p.kind).filter((k): k is string => !!k && !!props.terrainImages?.[k]))]);
const id = (name: string) => `${props.uid}-${name}`;
const levels = computed(() => props.terrain.flatMap((piece) => piece.surfaces.map((s) => ({ piece, s }))).sort((a, b) => a.s.top - b.s.top));
/** Without a texture a piece is a flat tint, darker the higher it is, so the levels still tell apart. */
const tint = (top: number) => `hsl(var(--primary) / ${Math.min(0.15 + top * 0.1, 0.7)})`;
</script>

<template>
    <svg
        viewBox="0 0 36 36"
        class="block aspect-square w-full rounded-md"
        role="img"
        aria-label="A top-down picture of the table"
        data-testid="table-thumbnail"
    >
        <defs>
            <pattern v-if="surface?.fit === 'tile'" :id="id('table')" patternUnits="userSpaceOnUse" :width="TILE" :height="TILE">
                <image :href="surface.url" :width="TILE" :height="TILE" preserveAspectRatio="none" />
            </pattern>
            <pattern v-for="k in kinds" :id="id(k)" :key="k" patternUnits="userSpaceOnUse" :width="TILE" :height="TILE">
                <image :href="terrainImages![k]" :width="TILE" :height="TILE" preserveAspectRatio="none" />
            </pattern>
        </defs>
        <rect width="36" height="36" fill="hsl(var(--muted))" />
        <rect v-if="surface?.fit === 'tile'" width="36" height="36" :fill="`url(#${id('table')})`" />
        <image v-else-if="surface" :href="surface.url" width="36" height="36" preserveAspectRatio="none" />
        <g v-for="({ piece, s }, n) in levels" :key="`${piece.id}-${s.id}-${n}`">
            <component
                :is="shapeTag(s.shape)"
                v-if="piece.kind && terrainImages?.[piece.kind]"
                v-bind="shapeAttrs(s.shape)"
                :fill="`url(#${id(piece.kind)})`"
            />
            <component
                :is="shapeTag(s.shape)"
                v-bind="shapeAttrs(s.shape)"
                :fill="piece.kind && terrainImages?.[piece.kind] ? `hsl(var(--primary) / ${Math.min(0.03 + s.top * 0.035, 0.25)})` : tint(s.top)"
                stroke="hsl(var(--foreground) / 0.55)"
                stroke-width="0.12"
            />
        </g>
    </svg>
</template>
