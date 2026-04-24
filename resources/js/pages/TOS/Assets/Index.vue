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
import { Head, Link } from '@inertiajs/vue3';
import { Package } from 'lucide-vue-next';

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

interface Paginator<T> {
    data: T[];
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
</script>

<template>
    <Head title="TOS Assets" />
    <div class="relative">
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
            <div v-else-if="isLoading" class="grid gap-4 grid-cols-2 md:grid-cols-3 lg:grid-cols-4">
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
                        <TableRow v-for="a in assets.data" :key="a.id">
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

            <div v-else-if="assets.data.length" class="grid gap-4 grid-cols-2 md:grid-cols-3 lg:grid-cols-4">
                <Link
                    v-for="a in assets.data"
                    :key="a.id"
                    :href="route('tos.assets.view', a.slug)"
                    class="block rounded-lg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                >
                    <Card class="h-full overflow-hidden transition-all duration-200 ease-out hover:-translate-y-0.5 hover:border-primary/30 hover:shadow-lg hover:shadow-black/10">
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
                        </CardContent>
                    </Card>
                </Link>
            </div>
            <EmptyState v-else :icon="Package" title="No assets yet" />

            <InertiaPagination v-if="!isLoading" :paginator="assets" :only="['assets', 'name_search', 'page_view']" />
        </div>
    </div>
</template>
