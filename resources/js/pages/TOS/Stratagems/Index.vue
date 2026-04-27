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
import { Newspaper } from 'lucide-vue-next';

interface Stratagem {
    id: number;
    slug: string;
    name: string;
    tactical_cost: number;
    effect: string | null;
    image_path: string | null;
    allegiance_type: string | null;
    allegiance: { id: number; name: string; slug: string } | null;
}

const props = defineProps<{
    stratagems: Paginator<Stratagem>;
    name_search: string | null;
    page_view: string;
}>();

const { filterParams, activeFilterCount, filter, clear, handleNameKeydown, clearNameSearch, handleViewChange, isLoading } = useListFiltering(
    {
        name_search: props.name_search as string | null,
        page_view: props.page_view as string | null,
    },
    { routeName: 'tos.stratagems.index', filterKeys: [], only: ['stratagems', 'name_search', 'page_view'] },
);
</script>

<template>
    <Head title="TOS Stratagems" />
    <div class="relative">
        <PageBanner title="Stratagems" class="mb-2">
            <template #subtitle>
                <div class="my-auto px-2 py-0 text-xs text-muted-foreground md:py-2 md:text-sm md:text-foreground">
                    Playable effect cards purchased with Tactics Tokens
                </div>
            </template>
        </PageBanner>

        <ListSearchBar
            v-model:name-search="filterParams.name_search"
            :page-view="filterParams.page_view"
            :active-filter-count="activeFilterCount"
            placeholder="Search stratagems by name..."
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

            <div v-else-if="filterParams.page_view === 'table' && stratagems.data.length" class="overflow-auto rounded-md border">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Name</TableHead>
                            <TableHead>Cost</TableHead>
                            <TableHead>Scope</TableHead>
                            <TableHead>Effect</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="s in stratagems.data" :key="s.id">
                            <TableCell class="font-medium">
                                <Link :href="route('tos.stratagems.view', s.slug)" class="hover:underline">{{ s.name }}</Link>
                            </TableCell>
                            <TableCell class="text-xs tabular-nums">{{ s.tactical_cost }}T</TableCell>
                            <TableCell class="text-xs capitalize">
                                <template v-if="s.allegiance">{{ s.allegiance.name }}</template>
                                <template v-else-if="s.allegiance_type">Any {{ s.allegiance_type }}</template>
                                <template v-else>Universal</template>
                            </TableCell>
                            <TableCell class="max-w-md text-xs text-muted-foreground line-clamp-2">
                                <TosText v-if="s.effect" :text="s.effect" />
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>

            <div v-else-if="stratagems.data.length" class="grid gap-4 grid-cols-2 md:grid-cols-3 lg:grid-cols-4">
                <Link
                    v-for="s in stratagems.data"
                    :key="s.id"
                    :href="route('tos.stratagems.view', s.slug)"
                    class="block rounded-lg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                >
                    <Card class="h-full overflow-hidden transition-all duration-200 ease-out hover:-translate-y-0.5 hover:border-primary/30 hover:shadow-lg hover:shadow-black/10">
                        <CardImage
                            :src="s.image_path"
                            :alt="s.name"
                            :allegiance-slug="s.allegiance?.slug ?? null"
                            :placeholder-icon="Newspaper"
                            rounded-class=""
                        />
                        <CardContent class="space-y-1.5 p-3">
                            <div class="flex items-center justify-between gap-2">
                                <span class="truncate text-sm font-semibold">{{ s.name }}</span>
                                <Badge variant="outline" class="shrink-0 text-[10px] tabular-nums">{{ s.tactical_cost }}T</Badge>
                            </div>
                            <p class="truncate text-[10px] capitalize text-muted-foreground">
                                <template v-if="s.allegiance">{{ s.allegiance.name }}</template>
                                <template v-else-if="s.allegiance_type">Any {{ s.allegiance_type }} allegiance</template>
                                <template v-else>Universal</template>
                            </p>
                            <p v-if="s.effect" class="line-clamp-2 text-xs text-muted-foreground"><TosText :text="s.effect" /></p>
                        </CardContent>
                    </Card>
                </Link>
            </div>
            <EmptyState v-else :icon="Newspaper" title="No stratagems yet" />

            <InertiaPagination v-if="!isLoading" :paginator="stratagems" :only="['stratagems', 'name_search', 'page_view']" />
        </div>
    </div>
</template>
