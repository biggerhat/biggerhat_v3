<script setup lang="ts">
import ArtPositioner from '@/components/CardCreator/ArtPositioner.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { ImagePlus } from 'lucide-vue-next';
import { computed, ref } from 'vue';

export interface CardArtOption {
    id: number;
    name: string;
    image_path: string;
    faction: string | null;
    category: string | null;
}

/**
 * Library-art chooser shared by the card editor and the Campaign Leader Builder: current pick with Change / Remove, a
 * searchable picker dialog, and the framing control. New art starts centred and unzoomed; re-picking the same piece
 * keeps the framing the player set. The default slot sits below the framing control (the editor's try-your-own-image).
 */
const props = withDefaults(defineProps<{ cardArts: CardArtOption[]; faction?: string | null; showPositioner?: boolean }>(), {
    faction: null,
    showPositioner: true,
});
const artId = defineModel<number | null>('artId', { required: true });
const x = defineModel<number>('x', { required: true });
const y = defineModel<number>('y', { required: true });
const zoom = defineModel<number>('zoom', { required: true });
const dx = defineModel<number>('dx', { required: true });
const dy = defineModel<number>('dy', { required: true });

const selected = computed(() => props.cardArts.find((a) => a.id === artId.value) ?? null);
const hasFaction = computed(() => !!props.faction && props.faction !== 'none');

const open = ref(false);
const search = ref('');
const category = ref<string | null>(null);
const factionOnly = ref(true);
const categories = computed(() => [...new Set(props.cardArts.map((a) => a.category).filter((c): c is string => !!c))].sort());
const visible = computed(() => {
    const q = search.value.trim().toLowerCase();
    return props.cardArts.filter((a) => {
        if (factionOnly.value && hasFaction.value && a.faction && a.faction !== props.faction) return false;
        if (category.value && a.category !== category.value) return false;
        return !q || a.name.toLowerCase().includes(q);
    });
});

const pick = (id: number | null) => {
    if (id !== artId.value) {
        x.value = 50;
        y.value = 50;
        zoom.value = 100;
        dx.value = 0;
        dy.value = 0;
    }
    artId.value = id;
    open.value = false;
};
</script>

<template>
    <div>
        <label class="mb-1.5 block text-xs text-muted-foreground">Character Art</label>
        <div v-if="selected" class="flex items-center justify-between gap-2 rounded-md border bg-muted/30 px-3 py-1.5 text-xs">
            <span class="truncate font-medium">{{ selected.name }}</span>
            <span class="flex shrink-0 gap-1">
                <Button type="button" variant="ghost" size="sm" class="h-6 px-2 text-xs" @click="open = true">Change</Button>
                <Button type="button" variant="ghost" size="sm" class="h-6 px-2 text-xs" @click="pick(null)">Remove</Button>
            </span>
        </div>
        <button
            v-else
            type="button"
            class="flex w-full items-center gap-3 rounded-lg border-2 border-dashed border-muted-foreground/25 px-4 py-3 text-left transition-colors hover:border-muted-foreground/50 hover:bg-muted/50"
            @click="open = true"
        >
            <ImagePlus class="size-5 shrink-0 text-muted-foreground" />
            <span class="text-xs font-medium">Choose art from the library</span>
            <span class="ml-auto text-xs text-muted-foreground">{{ cardArts.length }} available</span>
        </button>
        <ArtPositioner
            v-if="selected && showPositioner"
            v-model:x="x"
            v-model:y="y"
            v-model:zoom="zoom"
            v-model:dx="dx"
            v-model:dy="dy"
            :src="selected.image_path"
            :alt="selected.name"
            class="mt-2"
        />
        <slot />

        <Dialog :open="open" @update:open="(o: boolean) => (open = o)">
            <DialogContent class="max-w-3xl">
                <DialogHeader>
                    <DialogTitle>Choose character art</DialogTitle>
                    <DialogDescription>Art from the BiggerHat library. It's saved with the card and shows on the share link.</DialogDescription>
                </DialogHeader>
                <div class="flex flex-wrap items-center gap-2">
                    <Input v-model="search" placeholder="Search art…" class="h-8 max-w-[14rem]" aria-label="Search art" />
                    <label v-if="hasFaction" class="flex items-center gap-1.5 text-xs">
                        <input v-model="factionOnly" type="checkbox" />
                        Only this faction (and unrestricted art)
                    </label>
                </div>
                <div v-if="categories.length" class="flex flex-wrap gap-1.5">
                    <button
                        type="button"
                        class="rounded-full border px-2.5 py-0.5 text-xs"
                        :class="category === null ? 'border-primary bg-primary text-primary-foreground' : 'hover:bg-muted'"
                        @click="category = null"
                    >
                        All
                    </button>
                    <button
                        v-for="c in categories"
                        :key="c"
                        type="button"
                        class="rounded-full border px-2.5 py-0.5 text-xs"
                        :class="category === c ? 'border-primary bg-primary text-primary-foreground' : 'hover:bg-muted'"
                        @click="category = c"
                    >
                        {{ c }}
                    </button>
                </div>
                <div class="max-h-[55vh] overflow-y-auto">
                    <div v-if="cardArts.length === 0" class="py-10 text-center text-sm text-muted-foreground">
                        The art library is empty for now — check back soon.
                    </div>
                    <div v-else-if="visible.length === 0" class="py-10 text-center text-sm text-muted-foreground">
                        No art matches. Try clearing the filters.
                    </div>
                    <div v-else class="grid grid-cols-2 gap-2 sm:grid-cols-3 md:grid-cols-4">
                        <button
                            v-for="art in visible"
                            :key="art.id"
                            type="button"
                            class="group overflow-hidden rounded-md border text-left transition-colors hover:border-primary focus-visible:ring-2 focus-visible:ring-ring"
                            :class="artId === art.id ? 'border-primary ring-2 ring-primary' : ''"
                            :aria-pressed="artId === art.id"
                            @click="pick(art.id)"
                        >
                            <img :src="'/storage/' + art.image_path" :alt="art.name" class="aspect-[4/3] w-full object-cover" loading="lazy" />
                            <span class="block truncate px-2 py-1 text-xs">{{ art.name }}</span>
                        </button>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    </div>
</template>
