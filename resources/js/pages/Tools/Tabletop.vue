<script setup lang="ts">
import PageBanner from '@/components/PageBanner.vue';
import TabletopBoard from '@/components/Tabletop/TabletopBoard.vue';
import type { MarkerOption, ModelInfo, TerrainPiece, Token } from '@/lib/tabletop/geometry';
import { sampleTerrain } from '@/lib/tabletop/sampleTable';
import { Head } from '@inertiajs/vue3';
import { onMounted, ref, watch } from 'vue';

const props = defineProps<{
    markers: MarkerOption[];
    token_options: { id: number; name: string }[];
    table_images: { key: string; name: string; url: string; fit: 'tile' | 'cover' }[];
    terrain_images: Record<string, string>;
}>();

// Sandbox: the demo terrain, with models you add from the character cards and markers from the database. Nothing leaves this device.
// v2: the first version kept free-standing blank models, which are gone.
const STORAGE_KEY = 'tabletop-spike-v2';
const tokens = ref<Token[]>([]);
const terrain = ref<TerrainPiece[]>(JSON.parse(JSON.stringify(sampleTerrain)));
const tableImage = ref<string | null>(null);

onMounted(() => {
    try {
        const saved = JSON.parse(localStorage.getItem(STORAGE_KEY) ?? 'null');
        if (Array.isArray(saved?.tokens)) tokens.value = saved.tokens;
        if (Array.isArray(saved?.terrain)) terrain.value = saved.terrain;
        if (typeof saved?.tableImage === 'string') tableImage.value = saved.tableImage;
    } catch {
        // storage unavailable: the table simply starts empty
    }
});
watch(
    [tokens, terrain, tableImage],
    () => {
        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify({ tokens: tokens.value, terrain: terrain.value, tableImage: tableImage.value }));
        } catch {
            // ignore
        }
    },
    { deep: true },
);

const moved = (t: Token) => (tokens.value = tokens.value.map((x) => (x.id === t.id ? t : x)));
const add = (t: Omit<Token, 'id'>) => tokens.value.push({ ...t, id: `n${Date.now().toString(36)}` });
/** Health, activation, tokens and notes for a model: kept with the token, on this device. */
const modelChange = (id: string, patch: Partial<ModelInfo>) =>
    (tokens.value = tokens.value.map((t) => (t.id === id ? { ...t, info: { tokens: [], ...t.info, ...patch } } : t)));
const remove = (id: string) => (tokens.value = tokens.value.filter((t) => t.id !== id));
const reset = () => {
    tokens.value = [];
    terrain.value = JSON.parse(JSON.stringify(sampleTerrain));
};
/** Markers go underneath the models, so a model sitting on a marker is still the one you pick up. */
const addMarker = (m: { markerId: number; name: string; baseMm: number; side: 'a' | 'b'; x: number; y: number }) =>
    tokens.value.unshift({
        id: `m${Date.now().toString(36)}`,
        name: m.name,
        side: m.side,
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
                Preview: a 36" x 36" board with terrain on several levels and a ruler. Add characters and markers from the database to put them on the
                table at their real base size. Everything stays on this device.
            </div>
        </template>
    </PageBanner>
    <div class="container mx-auto mt-4 px-2 sm:px-4 lg:px-6">
        <TabletopBoard
            :tokens="tokens"
            :terrain="terrain"
            :markers="props.markers"
            :token-options="props.token_options"
            :table-images="props.table_images"
            :table-image="tableImage"
            :terrain-images="props.terrain_images"
            @table-image-changed="(k) => (tableImage = k)"
            @model-change="modelChange"
            :character-search-url="route('tools.tabletop.characters')"
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
