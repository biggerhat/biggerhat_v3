<script setup lang="ts">
import EmptyState from '@/components/EmptyState.vue';
import PageBanner from '@/components/PageBanner.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { useConfirm } from '@/composables/useConfirm';
import { Head, Link, router } from '@inertiajs/vue3';
import { ImagePlus, Pencil, Trash2 } from 'lucide-vue-next';

const confirm = useConfirm();

interface ImageRow {
    id: number;
    name: string;
    kind: 'table' | 'terrain';
    terrain_key: string | null;
    terrain_label: string | null;
    url: string;
    is_active: boolean;
    sort_order: number;
}
interface Default {
    key: string;
    name: string;
    url: string;
}

defineProps<{ images: ImageRow[]; defaults: { tables: Default[]; terrain: Default[] } }>();

const remove = async (image: ImageRow) => {
    if (!(await confirm({ title: `Delete "${image.name}"?`, message: 'This also removes the image file.', destructive: true }))) return;
    router.post(route('admin.tabletop_images.delete', image.id), {}, { preserveScroll: true });
};
</script>

<template>
    <Head title="Tabletop Images - Admin" />
    <PageBanner title="Tabletop Images">
        <template #subtitle>
            <div class="px-2 text-sm text-muted-foreground">
                Table surfaces and terrain textures for the tabletop. What you add here is offered next to the built-in ones, and a terrain texture
                replaces the built-in one for its kind of terrain.
            </div>
        </template>
        <template #actions>
            <Link :href="route('admin.tabletop_images.create')">
                <Button class="gap-1.5"><ImagePlus class="size-4" />Add image</Button>
            </Link>
        </template>
    </PageBanner>

    <div class="container mx-auto space-y-6 pb-10">
        <section class="space-y-2">
            <h2 class="text-base font-semibold">Added by you</h2>
            <EmptyState
                v-if="images.length === 0"
                :icon="ImagePlus"
                title="Nothing added yet"
                description="The tabletop is using only its built-in images, listed below."
            >
                <template #action>
                    <Link :href="route('admin.tabletop_images.create')"><Button>Add the first image</Button></Link>
                </template>
            </EmptyState>
            <div v-else class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5" data-testid="tabletop-image-list">
                <Card v-for="image in images" :key="image.id" :class="{ 'opacity-60': !image.is_active }">
                    <CardContent class="space-y-2 p-2">
                        <img :src="image.url" :alt="image.name" class="aspect-square w-full rounded-md bg-muted object-cover" loading="lazy" />
                        <div class="min-w-0">
                            <div class="truncate text-sm font-medium">{{ image.name }}</div>
                            <div class="flex flex-wrap gap-1 pt-1">
                                <Badge variant="outline" class="text-2xs">{{ image.kind === 'table' ? 'Table surface' : 'Terrain' }}</Badge>
                                <Badge v-if="image.terrain_label" variant="secondary" class="text-2xs">{{ image.terrain_label }}</Badge>
                                <Badge v-if="!image.is_active" variant="destructive" class="text-2xs">Inactive</Badge>
                            </div>
                        </div>
                        <div class="flex gap-1">
                            <Link :href="route('admin.tabletop_images.edit', image.id)" class="flex-1">
                                <Button variant="outline" size="sm" class="w-full gap-1"><Pencil class="size-3.5" />Edit</Button>
                            </Link>
                            <Button variant="outline" size="sm" :aria-label="`Delete ${image.name}`" @click="remove(image)">
                                <Trash2 class="size-3.5" />
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </section>

        <section class="space-y-2">
            <h2 class="text-base font-semibold">Built-in table surfaces</h2>
            <div class="grid grid-cols-3 gap-3 sm:grid-cols-5">
                <div v-for="d in defaults.tables" :key="d.key" class="space-y-1 text-center text-xs">
                    <img :src="d.url" :alt="d.name" class="aspect-square w-full rounded-md border object-cover" loading="lazy" />
                    {{ d.name }}
                </div>
            </div>
        </section>
        <section class="space-y-2">
            <h2 class="text-base font-semibold">Built-in terrain textures</h2>
            <div class="grid grid-cols-3 gap-3 sm:grid-cols-6">
                <div v-for="d in defaults.terrain" :key="d.key" class="space-y-1 text-center text-xs">
                    <img :src="d.url" :alt="d.name" class="aspect-square w-full rounded-md border object-cover" loading="lazy" />
                    {{ d.name }}
                </div>
            </div>
        </section>
    </div>
</template>
