<script setup lang="ts">
import TableThumbnail from '@/components/Tabletop/TableThumbnail.vue';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { DeploymentZones, TerrainPiece } from '@/lib/tabletop/geometry';
import type { SavedLayout } from '@/lib/tabletop/layouts';
import { MAX_SEED, newSeed, randomTable } from '@/lib/tabletop/randomTable';
import { sampleTerrain } from '@/lib/tabletop/sampleTable';
import { TERRAIN_LAYOUTS } from '@/lib/tabletop/terrainLayouts';
import { Link } from '@inertiajs/vue3';
import { Dices } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

/**
 * Pick a table to put on the board: the ready-made ones the tabletop ships with, and the viewer's own saved tables. Choosing one replaces the
 * terrain on the board (the board's Undo brings the old one back); a saved table also brings the surface it was set up on.
 */
const props = defineProps<{
    open: boolean;
    /** The deployment zones chosen on the board, which a random table is judged against (lanes between them, ground nobody can reach). */
    zones?: DeploymentZones | null;
    /** The viewer's saved tables. */
    layouts: SavedLayout[];
    /** The saved tables are still being fetched. */
    loading?: boolean;
    signedIn: boolean;
    tableImages?: { key: string; name: string; url: string; fit: 'tile' | 'cover' }[];
    terrainImages?: Record<string, string>;
}>();
const emit = defineEmits<{
    'update:open': [open: boolean];
    load: [table: { name: string; terrain: TerrainPiece[]; tableImage: string | null }];
}>();

const clone = <T,>(x: T): T => JSON.parse(JSON.stringify(x));
const defaults = computed(() => [
    { key: 'blank', name: 'Blank table', description: 'Nothing on it.', terrain: [] as TerrainPiece[] },
    { key: 'demo', name: 'Demo table', description: 'The sample terrain the sandbox starts with.', terrain: clone(sampleTerrain) },
    ...TERRAIN_LAYOUTS.map((l) => ({ key: l.key, name: l.label, description: l.description, terrain: l.pieces() })),
]);

/** The random table: a number picks it, so the same number is the same table. Opening the dialog rolls a new one. */
const seed = ref(newSeed());
const rolled = computed(() => (props.open ? randomTable(seed.value, props.zones) : null));
watch(
    () => props.open,
    (now) => {
        if (now) seed.value = newSeed();
    },
);
function setSeed(value: string | number | null) {
    const n = Math.round(Number(value));
    if (Number.isFinite(n) && n >= 1) seed.value = Math.min(MAX_SEED, n);
}

function pick(table: { name: string; terrain: TerrainPiece[]; tableImage: string | null }) {
    emit('load', table);
    emit('update:open', false);
}
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="max-h-[85vh] overflow-y-auto sm:max-w-3xl">
            <div data-testid="load-dialog" class="contents">
                <DialogHeader>
                    <DialogTitle>Load a table</DialogTitle>
                    <DialogDescription>It replaces the terrain on the board. Undo in the Terrain tab brings the old one back.</DialogDescription>
                </DialogHeader>

                <section aria-labelledby="load-random" class="space-y-2" data-testid="random-table">
                    <h3 id="load-random" class="text-sm font-semibold">Random table</h3>
                    <p class="text-xs text-muted-foreground">
                        Built to the table guide: about half covered, lanes at least 50mm wide, nothing walled off, and the same on both sides. Roll
                        until you like it, or type a number to get a table someone else rolled.
                    </p>
                    <div v-if="rolled" class="grid gap-3 sm:grid-cols-[minmax(0,16rem)_1fr]">
                        <TableThumbnail
                            uid="load-random"
                            :terrain="rolled.pieces"
                            :table-image="null"
                            :table-images="tableImages"
                            :terrain-images="terrainImages"
                        />
                        <div class="space-y-2">
                            <div class="flex flex-wrap items-end gap-2">
                                <label class="text-xs font-medium">
                                    Table number
                                    <Input
                                        :model-value="seed"
                                        type="number"
                                        min="1"
                                        :max="MAX_SEED"
                                        class="mt-1 w-32"
                                        data-testid="random-seed"
                                        @update:model-value="setSeed"
                                    />
                                </label>
                                <Button type="button" variant="outline" data-testid="random-roll" @click="seed = newSeed()"
                                    ><Dices class="mr-1.5 size-4" aria-hidden="true" />Roll again</Button
                                >
                            </div>
                            <p class="text-xs text-muted-foreground" data-testid="random-summary">
                                {{ rolled.pieces.length }} pieces.
                                {{
                                    rolled.notes
                                        ? `${rolled.notes} table check${rolled.notes === 1 ? '' : 's'} to look at once it is on the board.`
                                        : 'It passes every table check.'
                                }}
                            </p>
                            <Button
                                type="button"
                                data-testid="random-use"
                                @click="pick({ name: `Random table ${seed}`, terrain: clone(rolled.pieces), tableImage: null })"
                                >Use this table</Button
                            >
                        </div>
                    </div>
                </section>

                <section aria-labelledby="load-ready" class="space-y-2">
                    <h3 id="load-ready" class="text-sm font-semibold">Ready-made tables</h3>
                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                        <button
                            v-for="d in defaults"
                            :key="d.key"
                            type="button"
                            class="space-y-1 rounded-md border p-2 text-left transition-colors hover:border-primary focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
                            :data-testid="`load-default`"
                            :data-key="d.key"
                            @click="pick({ name: d.name, terrain: d.terrain, tableImage: null })"
                        >
                            <TableThumbnail
                                :uid="`load-${d.key}`"
                                :terrain="d.terrain"
                                :table-image="null"
                                :table-images="tableImages"
                                :terrain-images="terrainImages"
                            />
                            <span class="block truncate text-sm font-medium">{{ d.name }}</span>
                            <span class="line-clamp-2 block text-xs text-muted-foreground">{{ d.description }}</span>
                        </button>
                    </div>
                </section>

                <section aria-labelledby="load-mine" class="space-y-2">
                    <h3 id="load-mine" class="text-sm font-semibold">
                        My tables
                        <span class="font-normal text-muted-foreground"
                            >({{ !signedIn ? 'sign in to use yours' : loading ? 'loading…' : layouts.length }})</span
                        >
                    </h3>
                    <div v-if="signedIn && layouts.length" class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                        <button
                            v-for="l in layouts"
                            :key="l.uuid"
                            type="button"
                            class="space-y-1 rounded-md border p-2 text-left transition-colors hover:border-primary focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
                            data-testid="load-mine"
                            :data-name="l.name"
                            @click="pick({ name: l.name, terrain: clone(l.terrain), tableImage: l.table_image })"
                        >
                            <TableThumbnail
                                :uid="`load-mine-${l.uuid}`"
                                :terrain="l.terrain"
                                :table-image="l.table_image"
                                :table-images="tableImages"
                                :terrain-images="terrainImages"
                            />
                            <span class="block truncate text-sm font-medium">{{ l.name }}</span>
                            <span class="block text-xs text-muted-foreground">{{ l.pieces }} piece{{ l.pieces === 1 ? '' : 's' }}</span>
                        </button>
                    </div>
                    <p v-else-if="signedIn && loading" class="text-sm text-muted-foreground" data-testid="layouts-loading">Loading your tables…</p>
                    <p v-else-if="signedIn" class="text-sm text-muted-foreground">
                        You have no saved tables yet. Set one up and use Save table, or look through the
                        <Link :href="route('tools.tabletop.tables')" class="underline">public tables</Link>.
                    </p>
                    <p v-else class="text-sm text-muted-foreground">
                        <Link :href="route('login')" class="underline">Sign in</Link> to keep your own tables and load them here.
                    </p>
                    <p v-if="signedIn" class="text-xs text-muted-foreground">
                        Rename, share or delete tables on the <Link :href="route('tools.tabletop.tables')" class="underline">Tabletop page</Link>.
                    </p>
                </section>
            </div>
        </DialogContent>
    </Dialog>
</template>
