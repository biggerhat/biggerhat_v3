<script setup lang="ts">
import CardSkeleton from '@/components/CardSkeleton.vue';
import EmptyState from '@/components/EmptyState.vue';
import InertiaPagination from '@/components/InertiaPagination.vue';
import ListSearchBar from '@/components/ListSearchBar.vue';
import PageBanner from '@/components/PageBanner.vue';
import TableSkeleton from '@/components/TableSkeleton.vue';
import CardImage from '@/components/TOS/CardImage.vue';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useListFiltering } from '@/composables/useListFiltering';
import { CARD_HOVER } from '@/lib/cardHover';
import type { SharedData } from '@/types';
import type { Paginator } from '@/types/tos';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { BookMarked, Package, Plus } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface Limit {
    id: number;
    limit_type: string;
    parameter_type: string | null;
    parameter_value: string | null;
}

interface Asset {
    id: number;
    slug: string;
    name: string;
    scrip_cost: number;
    body: string | null;
    image_path: string | null;
    allegiances: Array<{ id: number; slug: string; name: string }>;
    limits: Limit[];
}

const props = defineProps<{
    assets: Paginator<Asset>;
    name_search: string | null;
    page_view: string;
}>();

const { filterParams, activeFilterCount, filter, clear, handleNameKeydown, clearNameSearch, handleViewChange, isLoading } = useListFiltering(
    {
        name_search: props.name_search as string | null,
        page_view: props.page_view as string | null,
    },
    { routeName: 'tos.assets.index', filterKeys: [], only: ['assets', 'name_search', 'page_view'] },
);

const limitLabel = (l: Limit): string => {
    const head = l.limit_type.charAt(0).toUpperCase() + l.limit_type.slice(1);
    if (!l.parameter_value) return head;
    return `${head} (${l.parameter_value})`;
};

// ─── Collection (Adjunct-limit Assets count as Units) ───
const page = usePage<SharedData>();
const isAuthenticated = computed(() => !!page.props.auth.user);
const isAdjunct = (a: Asset) => a.limits.some((l) => l.limit_type === 'adjunct');
const inCollection = (assetId: number) => (page.props.auth.collection_asset_ids ?? []).includes(assetId);

const addingToCollectionId = ref<number | null>(null);
const addToCollection = (assetId: number) => {
    if (inCollection(assetId)) return;

    const ids = page.props.auth.collection_asset_ids;
    const wasAbsent = !ids.includes(assetId);
    if (wasAbsent) ids.push(assetId);

    router.post(
        route('tos.collection.toggle_asset'),
        { asset_id: assetId, quantity: 1 },
        {
            // Only the shared `auth` prop actually changes here — restrict
            // the reload to it so this paginated grid's own `assets` prop
            // doesn't get refetched/re-rendered on every click.
            only: ['auth'],
            preserveScroll: true,
            preserveState: true,
            onStart: () => (addingToCollectionId.value = assetId),
            onError: () => {
                if (wasAbsent) {
                    const idx = ids.indexOf(assetId);
                    if (idx !== -1) ids.splice(idx, 1);
                }
            },
            onFinish: () => (addingToCollectionId.value = null),
        },
    );
};
</script>

<template>
    <Head title="TOS Assets" />
    <div class="relative pb-12">
        <div
            class="pointer-events-none absolute inset-x-0 top-0 h-64 opacity-[0.07] dark:opacity-[0.12]"
            :style="{ background: 'radial-gradient(ellipse at top, hsl(var(--primary)) 0%, transparent 70%)' }"
        />

        <PageBanner title="Assets" class="mb-2">
            <template #subtitle>
                <div class="my-auto px-2 py-0 text-xs text-muted-foreground md:py-2 md:text-sm md:text-foreground">
                    Equipment, mutations, and attachments bought for units during hiring
                </div>
            </template>
        </PageBanner>

        <ListSearchBar
            v-model:name-search="filterParams.name_search"
            :page-view="filterParams.page_view"
            :active-filter-count="activeFilterCount"
            placeholder="Search assets by name..."
            @update:page-view="handleViewChange"
            @name-keydown="handleNameKeydown"
            @clear-search="clearNameSearch"
            @filter="filter"
            @clear="clear"
        />

        <div class="container mx-auto sm:px-4">
            <div v-if="isLoading && filterParams.page_view === 'table'" class="overflow-auto">
                <TableSkeleton :rows="8" :cols="4" />
            </div>
            <div v-else-if="isLoading" class="grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-4">
                <CardSkeleton v-for="n in 8" :key="`skeleton-${n}`" />
            </div>

            <div v-else-if="filterParams.page_view === 'table' && assets.data.length" class="overflow-auto rounded-md border">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Name</TableHead>
                            <TableHead>Scrip</TableHead>
                            <TableHead>Limits</TableHead>
                            <TableHead>Allegiances</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="a in assets.data" :key="a.id" class="transition-colors hover:bg-muted/40">
                            <TableCell class="font-medium">
                                <Link :href="route('tos.assets.view', a.slug)" class="hover:underline">{{ a.name }}</Link>
                            </TableCell>
                            <TableCell class="text-xs tabular-nums">{{ a.scrip_cost }}</TableCell>
                            <TableCell class="text-xs capitalize">
                                <span v-for="l in a.limits" :key="l.id" class="mr-1">{{ limitLabel(l) }}</span>
                                <span v-if="!a.limits.length" class="text-muted-foreground">—</span>
                            </TableCell>
                            <TableCell class="text-xs text-muted-foreground">{{ a.allegiances.map((x) => x.name).join(', ') || '—' }}</TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>

            <div v-else-if="assets.data.length" class="grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-4">
                <Link
                    v-for="a in assets.data"
                    :key="a.id"
                    :href="route('tos.assets.view', a.slug)"
                    class="block rounded-lg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                >
                    <Card :class="['h-full overflow-hidden', CARD_HOVER]">
                        <CardImage
                            :src="a.image_path"
                            :alt="a.name"
                            :allegiance-slug="a.allegiances[0]?.slug ?? null"
                            :placeholder-icon="Package"
                            rounded-class=""
                        />
                        <CardContent class="space-y-1.5 p-3">
                            <div class="flex items-center justify-between gap-2">
                                <span class="truncate text-sm font-semibold">{{ a.name }}</span>
                                <span class="shrink-0 text-[11px] tabular-nums text-muted-foreground">{{ a.scrip_cost }}</span>
                            </div>
                            <div class="flex flex-wrap gap-1">
                                <Badge v-for="l in a.limits" :key="l.id" variant="outline" class="text-[10px] capitalize">{{ limitLabel(l) }}</Badge>
                            </div>
                            <p v-if="a.allegiances.length" class="truncate text-[10px] text-muted-foreground">
                                {{ a.allegiances.map((x) => x.name).join(', ') }}
                            </p>

                            <!-- Adjunct-limit Assets are physical swap-in models — count as Units for collection purposes -->
                            <template v-if="isAdjunct(a)">
                                <div v-if="inCollection(a.id)" class="flex items-center gap-1 pt-0.5 text-[11px]" style="color: #059669">
                                    <BookMarked class="size-3" />
                                    Collected
                                </div>
                                <button
                                    v-else-if="isAuthenticated"
                                    class="flex items-center gap-1 pt-0.5 text-[11px] text-muted-foreground transition-colors hover:text-foreground disabled:cursor-wait disabled:opacity-50"
                                    :disabled="addingToCollectionId === a.id"
                                    @click.stop.prevent="addToCollection(a.id)"
                                >
                                    <Plus class="size-3" />
                                    Add to Collection
                                </button>
                            </template>
                        </CardContent>
                    </Card>
                </Link>
            </div>
            <EmptyState
                v-else
                :icon="Package"
                title="No assets match"
                description="Assets are gear, vehicles, and constructs hired alongside Units. Try clearing your search."
            />

            <InertiaPagination v-if="!isLoading" :paginator="assets" :only="['assets', 'name_search', 'page_view']" />
        </div>
    </div>
</template>
