<script setup lang="ts">
import EmptyState from '@/components/EmptyState.vue';
import PageBanner from '@/components/PageBanner.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { useConfirm } from '@/composables/useConfirm';
import { Head, Link, router } from '@inertiajs/vue3';
import { ImagePlus, Pencil, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const confirm = useConfirm();

interface ArtRow {
    id: number;
    name: string;
    faction: string | null;
    category: string | null;
    image_path: string;
    is_active: boolean;
    sort_order: number;
    in_use: number;
}

const props = defineProps<{
    arts: ArtRow[];
    factions: { value: string; name: string }[];
}>();

const search = ref('');
const factionLabel = (value: string | null) => props.factions.find((f) => f.value === value)?.name ?? 'Any faction';

const visible = computed(() => {
    const q = search.value.trim().toLowerCase();
    if (!q) return props.arts;
    return props.arts.filter((a) => [a.name, a.category ?? '', factionLabel(a.faction)].some((t) => t.toLowerCase().includes(q)));
});

const remove = async (art: ArtRow) => {
    const detail = art.in_use > 0 ? ` ${art.in_use} card(s) use it and will lose their art. Deactivating it instead keeps them as they are.` : '';
    if (!(await confirm({ title: `Delete "${art.name}"?`, message: `This also removes the image file.${detail}`, destructive: true }))) return;
    router.post(route('admin.card_arts.delete', art.id), {}, { preserveScroll: true });
};
</script>

<template>
    <Head title="Card Art Library - Admin" />
    <PageBanner title="Card Art Library">
        <template #subtitle>
            <div class="px-2 text-sm text-muted-foreground">
                Character art that players can pick for their Custom Card Creator characters and Campaign Leaders.
            </div>
        </template>
        <template #actions>
            <Link :href="route('admin.card_arts.create')">
                <Button class="gap-1.5"><ImagePlus class="size-4" />Add art</Button>
            </Link>
        </template>
    </PageBanner>

    <div class="container mx-auto space-y-4 pb-10">
        <Input v-model="search" placeholder="Search by name, category or faction…" class="max-w-sm" aria-label="Search card art" />

        <EmptyState
            v-if="arts.length === 0"
            :icon="ImagePlus"
            title="No art yet"
            description="Add art here and it becomes available in the character card editor."
        >
            <template #action>
                <Link :href="route('admin.card_arts.create')"><Button>Add the first piece of art</Button></Link>
            </template>
        </EmptyState>

        <div v-else class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
            <Card v-for="art in visible" :key="art.id" :class="{ 'opacity-60': !art.is_active }">
                <CardContent class="space-y-2 p-2">
                    <img
                        :src="'/storage/' + art.image_path"
                        :alt="art.name"
                        class="aspect-[4/3] w-full rounded-md bg-muted object-cover"
                        loading="lazy"
                    />
                    <div class="min-w-0">
                        <div class="truncate text-sm font-medium">{{ art.name }}</div>
                        <div class="flex flex-wrap gap-1 pt-1">
                            <Badge variant="outline" class="text-2xs">{{ factionLabel(art.faction) }}</Badge>
                            <Badge v-if="art.category" variant="secondary" class="text-2xs">{{ art.category }}</Badge>
                            <Badge v-if="!art.is_active" variant="destructive" class="text-2xs">Inactive</Badge>
                        </div>
                        <div class="pt-1 text-2xs text-muted-foreground">Used on {{ art.in_use }} {{ art.in_use === 1 ? 'card' : 'cards' }}</div>
                    </div>
                    <div class="flex gap-1">
                        <Link :href="route('admin.card_arts.edit', art.id)" class="flex-1">
                            <Button variant="outline" size="sm" class="w-full gap-1"><Pencil class="size-3.5" />Edit</Button>
                        </Link>
                        <Button variant="outline" size="sm" :aria-label="`Delete ${art.name}`" @click="remove(art)">
                            <Trash2 class="size-3.5" />
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>
        <p v-if="arts.length && visible.length === 0" class="text-sm text-muted-foreground">No art matches that search.</p>
    </div>
</template>
