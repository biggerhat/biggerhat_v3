<script setup lang="ts">
import EmptyState from '@/components/EmptyState.vue';
import InertiaPagination from '@/components/InertiaPagination.vue';
import PageBanner from '@/components/PageBanner.vue';
import TableThumbnail from '@/components/Tabletop/TableThumbnail.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { useConfirm } from '@/composables/useConfirm';
import { useToast } from '@/composables/useToast';
import type { SavedLayout } from '@/lib/tabletop/layouts';
import type { RequestPayload } from '@inertiajs/core';
import { Head, Link, router } from '@inertiajs/vue3';
import { Copy, FlaskConical, Link2, Pencil, Play, Plus, Search, Trash2, X } from 'lucide-vue-next';
import { ref, watch } from 'vue';

interface Paginator {
    data: SavedLayout[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    first_page_url: string;
    last_page_url: string;
    next_page_url: string | null;
    prev_page_url: string | null;
    path: string;
    from: number | null;
    to: number | null;
}

const props = defineProps<{
    mine: SavedLayout[];
    gallery: Paginator;
    active_search: string | null;
    table_images: { key: string; name: string; url: string; fit: 'tile' | 'cover' }[];
    terrain_images: Record<string, string>;
}>();

const confirm = useConfirm();
const toast = useToast();
const refresh = { preserveScroll: true, preserveState: true, only: ['mine', 'gallery'] as string[] };
const failed = (errors: Record<string, string>) => {
    toast.error(Object.values(errors)[0] ?? 'That did not work');
};

const VISIBILITY: { value: SavedLayout['visibility']; label: string; hint: string }[] = [
    { value: 'private', label: 'Private', hint: 'Only you' },
    { value: 'link', label: 'Anyone with the link', hint: 'Not listed' },
    { value: 'public', label: 'Public', hint: 'Listed in the gallery below' },
];
const visibilityLabel = (v: SavedLayout['visibility']) => VISIBILITY.find((x) => x.value === v)?.label ?? v;

const setVisibility = (l: SavedLayout, visibility: string) =>
    router.put(route('tools.tabletop.layouts.update', l.uuid), { visibility } as RequestPayload, {
        ...refresh,
        onSuccess: () => {
            toast.success(
                visibility === 'private' ? 'Now private' : visibility === 'public' ? 'Now listed in the gallery' : 'Anyone with the link can see it',
            );
        },
        onError: failed,
    });

async function remove(l: SavedLayout) {
    if (
        !(await confirm({
            title: `Delete "${l.name}"?`,
            message: 'Anyone it was shared with loses it too. Copies other people saved are kept.',
            destructive: true,
        }))
    )
        return;
    router.delete(route('tools.tabletop.layouts.destroy', l.uuid), {
        ...refresh,
        onSuccess: () => {
            toast.success('Deleted');
        },
    });
}

const copy = (l: SavedLayout) =>
    router.post(
        route('tools.tabletop.layouts.copy', l.uuid),
        {},
        {
            ...refresh,
            onSuccess: () => {
                toast.success(`Saved a copy of "${l.name}" to your tables`);
            },
            onError: failed,
        },
    );

const linkOf = (l: SavedLayout) => `${window.location.origin}${route('tools.tabletop', { layout: l.uuid }, false)}`;
async function copyLink(l: SavedLayout) {
    try {
        await navigator.clipboard.writeText(linkOf(l));
        toast.success('Link copied');
    } catch {
        toast.info(linkOf(l));
    }
}

const searchText = ref(props.active_search ?? '');
let timer: ReturnType<typeof setTimeout> | null = null;
watch(searchText, (value) => {
    if (timer) clearTimeout(timer);
    timer = setTimeout(() => {
        router.get(route('tools.tabletop.tables'), value ? { search: value } : {}, {
            only: ['gallery', 'active_search'],
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    }, 300);
});

const updated = (l: SavedLayout) => (l.updated_at ? new Date(l.updated_at).toLocaleDateString() : '');
</script>

<template>
    <Head title="Tabletop (preview)" />

    <PageBanner title="Tabletop">
        <template #subtitle>
            <div class="flex flex-wrap items-center gap-3 p-2 text-sm text-muted-foreground">
                <span>Preview: set terrain up on a 36" table, keep it, share it, and play on it.</span>
                <Link :href="route('tools.tabletop')" data-testid="open-sandbox">
                    <Button size="sm" class="gap-1.5"><FlaskConical class="size-3.5" />Open the sandbox</Button>
                </Link>
            </div>
        </template>
    </PageBanner>

    <div class="container mx-auto space-y-8 px-2 pb-8 sm:px-4 lg:px-6">
        <section aria-labelledby="my-tables" data-testid="my-tables">
            <div class="mb-3 flex items-center justify-between gap-2">
                <h2 id="my-tables" class="text-lg font-semibold">
                    My tables <span class="text-sm font-normal text-muted-foreground">({{ mine.length }})</span>
                </h2>
                <Link :href="route('tools.tabletop', { new: 1 })" data-testid="new-table-link">
                    <Button size="sm" variant="outline" class="gap-1.5"><Plus class="size-3.5" />New table</Button>
                </Link>
            </div>

            <div v-if="mine.length" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <Card v-for="l in mine" :key="l.uuid" data-testid="my-table">
                    <CardContent class="space-y-3 p-3">
                        <Link :href="route('tools.tabletop', { layout: l.uuid })" class="block" :aria-label="`Open ${l.name} in the sandbox`">
                            <TableThumbnail
                                :uid="`mine-${l.uuid}`"
                                :terrain="l.terrain"
                                :table-image="l.table_image"
                                :table-images="table_images"
                                :terrain-images="terrain_images"
                            />
                        </Link>
                        <div class="min-w-0">
                            <p class="truncate font-medium" data-testid="my-table-name">{{ l.name }}</p>
                            <p v-if="l.description" class="line-clamp-2 text-xs text-muted-foreground">{{ l.description }}</p>
                            <div class="mt-1 flex flex-wrap items-center gap-1">
                                <Badge variant="secondary" class="text-2xs">{{ l.pieces }} piece{{ l.pieces === 1 ? '' : 's' }}</Badge>
                                <Badge
                                    :variant="l.visibility === 'private' ? 'outline' : 'default'"
                                    class="text-2xs"
                                    data-testid="my-table-visibility"
                                    >{{ visibilityLabel(l.visibility) }}</Badge
                                >
                                <span class="text-2xs text-muted-foreground">{{ updated(l) }}</span>
                            </div>
                        </div>
                        <div class="flex flex-wrap gap-1">
                            <Link :href="route('tools.tabletop', { layout: l.uuid, edit: 1 })">
                                <Button size="sm" data-testid="table-edit"><Pencil class="mr-1 size-4" />Edit</Button>
                            </Link>
                            <Link :href="route('tools.tabletop', { layout: l.uuid })">
                                <Button size="sm" variant="outline" data-testid="table-open"><Play class="mr-1 size-4" />Sandbox</Button>
                            </Link>
                            <Button size="sm" variant="outline" aria-label="Make a copy" data-testid="table-duplicate" @click="copy(l)"
                                ><Copy class="size-4"
                            /></Button>
                            <Button
                                v-if="l.visibility !== 'private'"
                                size="sm"
                                variant="outline"
                                aria-label="Copy the link"
                                data-testid="table-link"
                                @click="copyLink(l)"
                                ><Link2 class="size-4"
                            /></Button>
                            <Button size="sm" variant="ghost" aria-label="Delete this table" data-testid="table-delete" @click="remove(l)"
                                ><Trash2 class="size-4"
                            /></Button>
                        </div>
                        <label class="block text-xs">
                            <span class="mb-1 block text-muted-foreground">Who can see it</span>
                            <select
                                class="w-full rounded-md border bg-background px-2 py-1.5 text-sm"
                                :value="l.visibility"
                                data-testid="table-visibility"
                                @change="setVisibility(l, ($event.target as HTMLSelectElement).value)"
                            >
                                <option v-for="v in VISIBILITY" :key="v.value" :value="v.value">{{ v.label }} ({{ v.hint }})</option>
                            </select>
                        </label>
                    </CardContent>
                </Card>
            </div>
            <EmptyState
                v-else
                title="No saved tables yet"
                description="Start a new table, set it up and save it. Or save a copy of one from the gallery below."
            />
        </section>

        <section aria-labelledby="gallery" data-testid="gallery">
            <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                <h2 id="gallery" class="text-lg font-semibold">
                    Public tables <span class="text-sm font-normal text-muted-foreground">({{ gallery.total }})</span>
                </h2>
                <div class="relative w-full sm:w-72">
                    <Search class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        v-model="searchText"
                        placeholder="Search public tables..."
                        class="pl-9 pr-9"
                        aria-label="Search public tables"
                        data-testid="gallery-search"
                    />
                    <button
                        v-if="searchText"
                        aria-label="Clear search"
                        class="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                        @click="searchText = ''"
                    >
                        <X class="size-4" />
                    </button>
                </div>
            </div>

            <div v-if="gallery.data.length" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <Card v-for="l in gallery.data" :key="l.uuid" data-testid="gallery-table">
                    <CardContent class="space-y-3 p-3">
                        <Link :href="route('tools.tabletop', { layout: l.uuid })" class="block" :aria-label="`Look at ${l.name} in the sandbox`">
                            <TableThumbnail
                                :uid="`pub-${l.uuid}`"
                                :terrain="l.terrain"
                                :table-image="l.table_image"
                                :table-images="table_images"
                                :terrain-images="terrain_images"
                            />
                        </Link>
                        <div class="min-w-0">
                            <p class="truncate font-medium">{{ l.name }}</p>
                            <p v-if="l.description" class="line-clamp-2 text-xs text-muted-foreground">{{ l.description }}</p>
                            <div class="mt-1 flex flex-wrap items-center gap-1 text-2xs text-muted-foreground">
                                <Badge variant="secondary" class="text-2xs">{{ l.pieces }} piece{{ l.pieces === 1 ? '' : 's' }}</Badge>
                                <span>{{ l.mine ? 'Yours' : l.owner }}</span>
                                <span>&middot;</span>
                                <span>{{ updated(l) }}</span>
                            </div>
                        </div>
                        <div class="flex flex-wrap gap-1">
                            <Link :href="route('tools.tabletop', { layout: l.uuid })">
                                <Button size="sm" variant="outline" data-testid="gallery-open"><Play class="mr-1 size-4" />Open</Button>
                            </Link>
                            <Button v-if="!l.mine" size="sm" data-testid="gallery-copy" @click="copy(l)"
                                ><Copy class="mr-1 size-4" />Save a copy</Button
                            >
                        </div>
                    </CardContent>
                </Card>
            </div>
            <EmptyState
                v-else
                title="No public tables found"
                :description="
                    active_search ? 'Try a different search.' : 'Nobody has made a table public yet. Set yours to Public above to be the first.'
                "
            />
            <InertiaPagination :paginator="gallery" :only="['gallery']" />
        </section>
    </div>
</template>
