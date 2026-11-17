<script setup lang="ts">
import PageBanner from '@/components/PageBanner.vue';
import TabletopBoard from '@/components/Tabletop/TabletopBoard.vue';
import { Button } from '@/components/ui/button';
import { useToast } from '@/composables/useToast';
import type { RequestPayload } from '@inertiajs/core';
import type { MarkerOption, ModelInfo, TerrainPiece, Token } from '@/lib/tabletop/geometry';
import type { SharedData } from '@/types';
import type { SavedLayout } from '@/lib/tabletop/layouts';
import { sampleTerrain } from '@/lib/tabletop/sampleTable';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref, watch } from 'vue';

const props = defineProps<{
    markers: MarkerOption[];
    token_options: { id: number; name: string }[];
    table_images: { key: string; name: string; url: string; fit: 'tile' | 'cover' }[];
    terrain_images: Record<string, string>;
    /** The viewer's saved terrain layouts. */
    layouts: SavedLayout[];
    /** A layout opened by link (somebody else's shared one, or the viewer's own): shown on the table without touching the saved sandbox. */
    incoming_layout: SavedLayout | null;
    /** The incoming layout is the viewer's own, opened to change it: the banner offers to save the changes back. */
    editing: boolean;
    /** A new, empty table to set up and save, apart from the sandbox table kept on this device. */
    fresh: boolean;
}>();
const page = usePage<SharedData>();
const signedIn = computed(() => !!page.props.auth.user);
const toast = useToast();
/** While a layout opened by link is on the table nothing is written to this device, so the sandbox table kept here is not lost. */
const previewing = ref(!!props.incoming_layout || props.fresh);
const keepIncoming = () => (previewing.value = false);
/** Editing one of your own tables: changes are kept here until you save them back to it. */
const dirty = ref(false);
const saving = ref(false);
function saveChanges() {
    const layout = props.incoming_layout;
    if (!layout || saving.value) return;
    saving.value = true;
    router.put(
        route('tools.tabletop.layouts.update', layout.uuid),
        { terrain: JSON.parse(JSON.stringify(terrain.value)), table_image: tableImage.value } as unknown as RequestPayload,
        {
            preserveScroll: true,
            preserveState: true,
            only: ['layouts', 'incoming_layout'],
            onSuccess: () => {
                dirty.value = false;
                toast.success(`Saved "${layout.name}"`);
            },
            onError: (e) => {
                toast.error(Object.values(e)[0] ?? 'That did not work');
            },
            onFinish: () => (saving.value = false),
        },
    );
}
function copyIncoming() {
    const layout = props.incoming_layout;
    if (!layout) return;
    const options = {
        preserveScroll: true,
        preserveState: true,
        only: ['layouts'] as string[],
        onSuccess: () => {
            toast.success('Saved a copy to your tables');
        },
        onError: (e: Record<string, string>) => {
            toast.error(Object.values(e)[0] ?? 'That did not work');
        },
    };
    // While editing, a new table holds the changes made so far; otherwise it is a copy of the table as it was saved.
    if (props.editing)
        router.post(
            route('tools.tabletop.layouts.store'),
            {
                name: `${layout.name} (edited)`.slice(0, 80),
                terrain: JSON.parse(JSON.stringify(terrain.value)),
                table_image: tableImage.value,
            } as unknown as RequestPayload,
            options,
        );
    else router.post(route('tools.tabletop.layouts.copy', layout.uuid), {}, options);
}

// Sandbox: the demo terrain, with models you add from the character cards and markers from the database. Nothing leaves this device.
// v2: the first version kept free-standing blank models, which are gone.
const STORAGE_KEY = 'tabletop-spike-v2';
const tokens = ref<Token[]>([]);
const terrain = ref<TerrainPiece[]>(JSON.parse(JSON.stringify(sampleTerrain)));
const tableImage = ref<string | null>(null);

onMounted(() => {
    if (props.fresh) {
        // nothing of the sandbox table kept on this device comes with it
        tokens.value = [];
        terrain.value = [];
        tableImage.value = null;
        return;
    }
    try {
        const saved = JSON.parse(localStorage.getItem(STORAGE_KEY) ?? 'null');
        if (Array.isArray(saved?.tokens)) tokens.value = saved.tokens;
        if (!props.incoming_layout) {
            if (Array.isArray(saved?.terrain)) terrain.value = saved.terrain;
            if (typeof saved?.tableImage === 'string') tableImage.value = saved.tableImage;
        }
    } catch {
        // storage unavailable: the table simply starts empty
    }
    if (props.incoming_layout) {
        terrain.value = JSON.parse(JSON.stringify(props.incoming_layout.terrain));
        tableImage.value = props.incoming_layout.table_image;
    }
    // Putting the table on the board is not a change to it.
    setTimeout(() => (dirty.value = false), 0);
});
watch(
    [terrain, tableImage],
    () => {
        if (props.editing) dirty.value = true;
    },
    { deep: true, flush: 'post' },
);
watch(
    [tokens, terrain, tableImage],
    () => {
        if (previewing.value) return;
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
    terrain.value = props.fresh ? [] : JSON.parse(JSON.stringify(sampleTerrain));
};
/** A new table: name it and keep it, then go back to the list. Needs at least one piece of terrain. */
const newName = ref('');
function saveNew() {
    const name = newName.value.trim();
    if (!name || !terrain.value.length || saving.value) return;
    saving.value = true;
    router.post(
        route('tools.tabletop.layouts.store'),
        { name, terrain: JSON.parse(JSON.stringify(terrain.value)), table_image: tableImage.value } as unknown as RequestPayload,
        {
            onSuccess: () => {
                toast.success(`Saved "${name}"`);
                router.visit(route('tools.tabletop.tables'));
            },
            onError: (e) => {
                toast.error(Object.values(e)[0] ?? 'That did not work');
            },
            onFinish: () => (saving.value = false),
        },
    );
}
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
        <div v-if="incoming_layout" class="mb-3 space-y-2 rounded-md border border-amber-500/60 p-3 text-sm" data-testid="incoming-layout">
            <div class="font-medium">
                {{
                    editing
                        ? 'Editing your table'
                        : incoming_layout.mine
                          ? 'Your saved table'
                          : `A table shared by ${incoming_layout.owner ?? 'another player'}`
                }}:
                {{ incoming_layout.name }}
            </div>
            <p v-if="incoming_layout.description" class="text-muted-foreground">{{ incoming_layout.description }}</p>
            <p v-if="editing" class="text-muted-foreground" data-testid="editing-note">
                Change the terrain and surface below, then save. Your own sandbox table on this device is not touched.
                <span v-if="dirty" class="font-medium text-amber-600 dark:text-amber-400">You have unsaved changes.</span>
            </p>
            <p v-else-if="previewing" class="text-muted-foreground">
                You are looking at it. Your own sandbox table on this device has not been changed.
            </p>
            <div class="flex flex-wrap gap-2">
                <Button v-if="editing" size="sm" data-testid="editing-save" :disabled="!dirty || saving" @click="saveChanges">Save changes</Button>
                <Button v-if="editing" size="sm" variant="outline" as-child data-testid="editing-back"
                    ><Link :href="route('tools.tabletop.tables')">Back to my tables</Link></Button
                >
                <Button v-if="signedIn" size="sm" :variant="editing ? 'outline' : 'default'" data-testid="incoming-copy" @click="copyIncoming">{{
                    editing ? 'Save as a new table' : 'Save a copy to my tables'
                }}</Button>
                <Button v-else size="sm" as-child><a :href="route('login')">Sign in to save a copy</a></Button>
                <Button v-if="previewing && !editing" size="sm" variant="outline" data-testid="incoming-keep" @click="keepIncoming"
                    >Use it as my sandbox table</Button
                >
                <Button size="sm" variant="outline" data-testid="incoming-leave" @click="router.visit(route('tools.tabletop'))"
                    >Back to my own table</Button
                >
            </div>
        </div>
        <div v-if="fresh" class="mb-3 space-y-2 rounded-md border border-amber-500/60 p-3 text-sm" data-testid="new-table">
            <div class="font-medium">A new table</div>
            <p class="text-muted-foreground">
                It starts empty. Add pieces in the Terrain tab, or press Load table for a ready-made one, then name it and save it. Your own sandbox
                table on this device is not touched.
            </p>
            <form class="flex flex-wrap items-end gap-2" @submit.prevent="saveNew">
                <label class="min-w-0 flex-1">
                    <span class="mb-1 block font-medium">Name</span>
                    <input
                        v-model="newName"
                        maxlength="80"
                        placeholder="e.g. City streets"
                        class="w-full rounded-md border bg-background px-2 py-1.5"
                        data-testid="new-table-name"
                    />
                </label>
                <Button type="submit" size="sm" :disabled="!newName.trim() || !terrain.length || saving" data-testid="new-table-save"
                    >Save table</Button
                >
                <Button size="sm" variant="outline" as-child data-testid="new-table-cancel"
                    ><Link :href="route('tools.tabletop.tables')">Cancel</Link></Button
                >
            </form>
            <p v-if="!terrain.length" class="text-muted-foreground">Add at least one piece of terrain to save it.</p>
        </div>
        <p v-if="signedIn && !incoming_layout && !fresh" class="mb-2 text-sm">
            <Link :href="route('tools.tabletop.tables')" class="underline" data-testid="my-tables-link">My tables and the public gallery</Link>
        </p>
        <TabletopBoard
            :tokens="tokens"
            :terrain="terrain"
            :markers="props.markers"
            :token-options="props.token_options"
            :table-images="props.table_images"
            :layouts="layouts"
            :layout-target="fresh || editing ? undefined : { kind: 'sandbox' }"
            :initial-mode="fresh || editing ? 'terrain' : 'move'"
            :signed-in="signedIn"
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
