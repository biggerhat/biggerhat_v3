<script setup lang="ts">
import type { Paginator } from '@/types/tos';
import CardSkeleton from '@/components/CardSkeleton.vue';
import EmptyState from '@/components/EmptyState.vue';
import InertiaPagination from '@/components/InertiaPagination.vue';
import ListSearchBar from '@/components/ListSearchBar.vue';
import PageBanner from '@/components/PageBanner.vue';
import TableSkeleton from '@/components/TableSkeleton.vue';
import CardImage from '@/components/TOS/CardImage.vue';
import TosText from '@/components/TosText.vue';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useListFiltering } from '@/composables/useListFiltering';
import { Head, Link } from '@inertiajs/vue3';
import { Bot } from 'lucide-vue-next';

interface Envoy {
    id: number;
    slug: string;
    name: string;
    keyword: string | null;
    restriction: string;
    body: string | null;
    image_path: string | null;
    allegiance: { id: number; slug: string; name: string; is_syndicate: boolean };
}

const props = defineProps<{
    envoys: Paginator<Envoy>;
    name_search: string | null;
    page_view: string;
}>();

const { filterParams, activeFilterCount, filter, clear, handleNameKeydown, clearNameSearch, handleViewChange, isLoading } = useListFiltering(
    {
        name_search: props.name_search as string | null,
        page_view: props.page_view as string | null,
    },
    { routeName: 'tos.envoys.index', filterKeys: [], only: ['envoys', 'name_search', 'page_view'] },
);
</script>

<template>
    <Head title="Envoys — TOS" />
    <div class="relative">
        <PageBanner title="Envoys" class="mb-2">
            <template #subtitle>
                <div class="my-auto px-2 py-0 text-xs text-muted-foreground md:py-2 md:text-sm md:text-foreground">
                    Cross-allegiance hiring rules — Syndicates travel via Envoys
                </div>
            </template>
        </PageBanner>

        <ListSearchBar
            v-model:name-search="filterParams.name_search"
            :page-view="filterParams.page_view"
            :active-filter-count="activeFilterCount"
            placeholder="Search envoys by name..."
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

            <div v-else-if="filterParams.page_view === 'table' && envoys.data.length" class="overflow-auto rounded-md border">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Name</TableHead>
                            <TableHead>From</TableHead>
                            <TableHead>Restriction</TableHead>
                            <TableHead>Body</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="e in envoys.data" :key="e.id">
                            <TableCell class="font-medium">
                                <Link :href="route('tos.envoys.view', e.slug)" class="hover:underline">{{ e.name }}</Link>
                            </TableCell>
                            <TableCell class="text-xs">{{ e.allegiance.name }}</TableCell>
                            <TableCell class="text-xs capitalize">{{ e.restriction }}</TableCell>
                            <TableCell class="max-w-md text-xs text-muted-foreground line-clamp-2">
                                <TosText v-if="e.body" :text="e.body" />
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>

            <div v-else-if="envoys.data.length" class="grid gap-4 grid-cols-2 md:grid-cols-3 lg:grid-cols-4">
                <Link
                    v-for="e in envoys.data"
                    :key="e.id"
                    :href="route('tos.envoys.view', e.slug)"
                    class="block rounded-lg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                >
                    <Card class="h-full overflow-hidden transition-all duration-200 ease-out hover:-translate-y-0.5 hover:border-primary/30 hover:shadow-lg hover:shadow-black/10">
                        <CardImage
                            :src="e.image_path"
                            :alt="e.name"
                            :allegiance-slug="e.allegiance.slug"
                            :placeholder-icon="Bot"
                            rounded-class=""
                        />
                        <CardContent class="space-y-1.5 p-3">
                            <div class="flex items-center justify-between gap-2">
                                <span class="truncate text-sm font-semibold">{{ e.name }}</span>
                                <Badge variant="outline" class="shrink-0 text-[10px] capitalize">{{ e.restriction }}</Badge>
                            </div>
                            <p class="truncate text-[10px] text-muted-foreground">From {{ e.allegiance.name }}</p>
                            <p v-if="e.body" class="line-clamp-2 text-xs text-muted-foreground"><TosText :text="e.body" /></p>
                        </CardContent>
                    </Card>
                </Link>
            </div>
            <EmptyState v-else :icon="Bot" title="No envoys yet" />

            <InertiaPagination v-if="!isLoading" :paginator="envoys" :only="['envoys', 'name_search', 'page_view']" />
        </div>
    </div>
</template>
