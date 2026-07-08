<script setup lang="ts">
import CardSkeleton from '@/components/CardSkeleton.vue';
import EmptyState from '@/components/EmptyState.vue';
import InertiaPagination from '@/components/InertiaPagination.vue';
import ListSearchBar from '@/components/ListSearchBar.vue';
import PageBanner from '@/components/PageBanner.vue';
import TableSkeleton from '@/components/TableSkeleton.vue';
import CardImage from '@/components/TOS/CardImage.vue';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useListFiltering } from '@/composables/useListFiltering';
import { CARD_HOVER } from '@/lib/cardHover';
import type { Paginator } from '@/types/tos';
import { Head, Link } from '@inertiajs/vue3';
import { Package } from 'lucide-vue-next';

interface PackageListItem {
    id: number;
    name: string;
    slug: string;
    front_image: string | null;
    combination_image: string | null;
    sku: string | null;
    msrp: number | null;
    released_at: string | null;
    tos_units_count: number;
}

interface AllegianceOption {
    id: number;
    slug: string;
    name: string;
}

const props = defineProps<{
    packages: Paginator<PackageListItem>;
    name_search: string | null;
    allegiance_filter: string | null;
    page_view: string;
    allegiances: AllegianceOption[];
}>();

const { filterParams, activeFilterCount, filter, clear, handleNameKeydown, clearNameSearch, handleViewChange, isLoading } = useListFiltering(
    {
        allegiance: props.allegiance_filter as string | null,
        name_search: props.name_search as string | null,
        page_view: props.page_view as string | null,
    },
    {
        routeName: 'tos.packages.index',
        filterKeys: ['allegiance'],
        only: ['packages', 'name_search', 'page_view'],
    },
);

function setAllegiance(slug: string | null) {
    filterParams.value.allegiance = filterParams.value.allegiance === slug ? null : slug;
    filter();
}

const formatPrice = (cents: number | null) => {
    if (!cents) return '-';
    return `$${(cents / 100).toFixed(2)}`;
};
</script>

<template>
    <Head title="TOS Packages" />
    <div class="relative">
        <div
            class="pointer-events-none absolute inset-x-0 top-0 h-64 opacity-[0.07] dark:opacity-[0.12]"
            :style="{ background: 'radial-gradient(ellipse at top, hsl(var(--primary)) 0%, transparent 70%)' }"
        />
        <PageBanner title="Packages" class="mb-2">
            <template #subtitle>
                <div class="my-auto px-2 py-0 text-xs text-muted-foreground md:py-2 md:text-sm md:text-foreground">
                    {{ packages.total }} {{ packages.total === 1 ? 'package' : 'packages' }} found
                </div>
            </template>
        </PageBanner>

        <ListSearchBar
            v-model:name-search="filterParams.name_search"
            :page-view="filterParams.page_view"
            :active-filter-count="activeFilterCount"
            placeholder="Search packages by name..."
            @update:page-view="handleViewChange"
            @name-keydown="handleNameKeydown"
            @clear-search="clearNameSearch"
            @filter="filter"
            @clear="clear"
        />

        <div class="container mx-auto sm:px-4">
            <div v-if="allegiances.length" class="mb-4 flex flex-wrap items-center gap-1">
                <span class="mr-1 text-[11px] uppercase tracking-wider text-muted-foreground">Allegiance:</span>
                <button
                    class="rounded-full border px-2 py-0.5 text-[11px] transition-colors"
                    :class="!filterParams.allegiance ? 'border-primary bg-primary/10' : 'border-transparent hover:bg-muted'"
                    @click="setAllegiance(null)"
                >
                    All
                </button>
                <button
                    v-for="a in allegiances"
                    :key="a.slug"
                    class="rounded-full border px-2 py-0.5 text-[11px] transition-colors"
                    :class="filterParams.allegiance === a.slug ? 'border-primary bg-primary/10' : 'border-transparent hover:bg-muted'"
                    @click="setAllegiance(a.slug)"
                >
                    {{ a.name }}
                </button>
            </div>

            <div v-if="isLoading && filterParams.page_view === 'table'" class="overflow-auto">
                <TableSkeleton :rows="8" :cols="4" />
            </div>
            <div v-else-if="isLoading" class="grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-4">
                <CardSkeleton v-for="n in 8" :key="`skeleton-${n}`" />
            </div>

            <div v-else-if="filterParams.page_view === 'table' && packages.data.length" class="overflow-auto rounded-md border">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Package</TableHead>
                            <TableHead>SKU</TableHead>
                            <TableHead>MSRP</TableHead>
                            <TableHead>Units</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="pkg in packages.data" :key="pkg.id" class="transition-colors hover:bg-muted/40">
                            <TableCell class="font-medium">
                                <Link :href="route('tos.packages.view', pkg.slug)" class="hover:underline">{{ pkg.name }}</Link>
                            </TableCell>
                            <TableCell class="text-xs text-muted-foreground">{{ pkg.sku ?? '-' }}</TableCell>
                            <TableCell class="text-xs">{{ formatPrice(pkg.msrp) }}</TableCell>
                            <TableCell class="text-xs">{{ pkg.tos_units_count }}</TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>

            <div v-else-if="packages.data.length" class="grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-4">
                <Link v-for="pkg in packages.data" :key="pkg.id" :href="route('tos.packages.view', pkg.slug)">
                    <Card :class="['h-full overflow-hidden', CARD_HOVER]">
                        <CardImage :src="pkg.combination_image ?? pkg.front_image" :alt="pkg.name" :placeholder-icon="Package" />
                        <CardHeader class="pb-2">
                            <CardTitle class="text-base">{{ pkg.name }}</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div class="flex flex-wrap items-center gap-2">
                                <Badge v-if="pkg.tos_units_count" variant="outline" class="text-xs">
                                    {{ pkg.tos_units_count }} {{ pkg.tos_units_count === 1 ? 'unit' : 'units' }}
                                </Badge>
                                <span class="ml-auto text-xs text-muted-foreground">{{ formatPrice(pkg.msrp) }}</span>
                            </div>
                        </CardContent>
                    </Card>
                </Link>
            </div>
            <EmptyState
                v-else
                :icon="Package"
                title="No packages yet"
                description="Try clearing filters, or check back once TOS packages have been added."
            />

            <InertiaPagination v-if="!isLoading" :paginator="packages" :only="['packages', 'name_search', 'page_view']" />
        </div>
    </div>
</template>
