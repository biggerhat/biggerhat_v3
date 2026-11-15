<script setup lang="ts">
import PageBanner from '@/components/PageBanner.vue';
import TabletopBoard from '@/components/Tabletop/TabletopBoard.vue';
import type { MarkerOption, TerrainPiece, Token } from '@/lib/tabletop/geometry';
import { sampleTerrain, sampleTokens } from '@/lib/tabletop/sampleTable';
import { Head } from '@inertiajs/vue3';
import { onMounted, ref, watch } from 'vue';

const props = defineProps<{ markers: MarkerOption[] }>();

// Sandbox: the demo table with free-standing models. Nothing leaves this device.
const STORAGE_KEY = 'tabletop-spike-v1';
const tokens = ref<Token[]>(sampleTokens.map((t) => ({ ...t })));
const terrain = ref<TerrainPiece[]>(JSON.parse(JSON.stringify(sampleTerrain)));

onMounted(() => {
    try {
        const saved = JSON.parse(localStorage.getItem(STORAGE_KEY) ?? 'null');
        if (Array.isArray(saved?.tokens)) tokens.value = saved.tokens;
        if (Array.isArray(saved?.terrain)) terrain.value = saved.terrain;
    } catch {
        // storage unavailable: the table simply starts from the demo layout
    }
});
watch(
    [tokens, terrain],
    () => {
        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify({ tokens: tokens.value, terrain: terrain.value }));
        } catch {
            // ignore
        }
    },
    { deep: true },
);

const moved = (t: Token) => (tokens.value = tokens.value.map((x) => (x.id === t.id ? t : x)));
const add = (t: Omit<Token, 'id'>) => tokens.value.push({ ...t, id: `n${Date.now().toString(36)}` });
const remove = (id: string) => (tokens.value = tokens.value.filter((t) => t.id !== id));
const reset = () => {
    tokens.value = sampleTokens.map((t) => ({ ...t }));
    terrain.value = JSON.parse(JSON.stringify(sampleTerrain));
};
/** Markers go underneath the models, so a model sitting on a marker is still the one you pick up. */
const addMarker = (m: { markerId: number; name: string; baseMm: number; x: number; y: number }) =>
    tokens.value.unshift({
        id: `m${Date.now().toString(36)}`,
        name: m.name,
        side: 'n',
        baseMm: m.baseMm,
        x: m.x,
        y: m.y,
        marker: true,
        markerId: m.markerId,
    });
const terrainChanged = (pieces: TerrainPiece[]) => (terrain.value = pieces);
</script>

<template>
    <Head title="Tabletop (preview)" />
    <PageBanner title="Tabletop">
        <template #subtitle>
            <div class="px-2 text-sm text-muted-foreground">
                Preview: a 36" x 36" board with to-scale bases, terrain on several levels and a ruler. Positions stay on this device.
            </div>
        </template>
    </PageBanner>
    <div class="container mx-auto mt-4 px-2 sm:px-4 lg:px-6">
        <TabletopBoard
            :tokens="tokens"
            :terrain="terrain"
            :markers="props.markers"
            sandbox
            @add-marker="addMarker"
            @terrain-changed="terrainChanged"
            storage-key="sandbox"
            @moved="moved"
            @add="add"
            @remove="remove"
            @reset="reset"
        />
    </div>
</template>
