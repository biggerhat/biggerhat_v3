<script setup lang="ts">
import type { Paginator } from '@/types/tos';
import CardSkeleton from '@/components/CardSkeleton.vue';
import EmptyState from '@/components/EmptyState.vue';
import InertiaPagination from '@/components/InertiaPagination.vue';
import ListSearchBar from '@/components/ListSearchBar.vue';
import PageBanner from '@/components/PageBanner.vue';
import TableSkeleton from '@/components/TableSkeleton.vue';
import TosMarginCost from '@/components/TosMarginCost.vue';
import TosSuits from '@/components/TosSuits.vue';
import TosText from '@/components/TosText.vue';
import { Card, CardContent } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useListFiltering } from '@/composables/useListFiltering';
import { Head } from '@inertiajs/vue3';
import { Swords } from 'lucide-vue-next';

interface Trigger {
    id: number;
    name: string;
    suits: string | null;
    margin_cost: number | null;
    timing: string;
    body: string | null;
    actions: Array<{ id: number; name: string }>;
}

const props = defineProps<{
    triggers: Paginator<Trigger>;
    name_search: string | null;
    page_view: string;
}>();

const { filterParams, activeFilterCount, filter, clear, handleNameKeydown, clearNameSearch, handleViewChange, isLoading } = useListFiltering(
    {
        name_search: props.name_search as string | null,
        page_view: props.page_view as string | null,
    },
    { routeName: 'tos.triggers.index', filterKeys: [], only: ['triggers', 'name_search', 'page_view'] },
);
</script>

<template>
    <Head title="TOS Triggers" />
    <div class="relative pb-12">
        <div
            class="pointer-events-none absolute inset-x-0 top-0 h-64 opacity-[0.07] dark:opacity-[0.12]"
            :style="{ background: 'radial-gradient(ellipse at top, hsl(var(--primary)) 0%, transparent 70%)' }"
        />

        <PageBanner title="Triggers" class="mb-2">
            <template #subtitle>
                <div class="my-auto px-2 py-0 text-xs text-muted-foreground md:py-2 md:text-sm md:text-foreground">
                    Suit-driven follow-ups attached to Actions
                </div>
            </template>
        </PageBanner>

        <ListSearchBar
            v-model:name-search="filterParams.name_search"
            :page-view="filterParams.page_view"
            :active-filter-count="activeFilterCount"
            placeholder="Search triggers by name..."
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
            <div v-else-if="isLoading" class="grid gap-3 sm:grid-cols-2">
                <CardSkeleton v-for="n in 6" :key="`skeleton-${n}`" />
            </div>

            <div v-else-if="filterParams.page_view === 'table' && triggers.data.length" class="overflow-auto rounded-md border">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Name</TableHead>
                            <TableHead>Cost</TableHead>
                            <TableHead>Actions</TableHead>
                            <TableHead>Body</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="t in triggers.data" :key="t.id" class="transition-colors hover:bg-muted/40">
                            <TableCell class="font-medium">{{ t.name }}</TableCell>
                            <TableCell class="text-xs">
                                <TosSuits v-if="t.suits" :suits="t.suits" />
                                <TosMarginCost v-else-if="t.margin_cost != null" :cost="t.margin_cost" />
                                <span v-else class="text-muted-foreground">—</span>
                            </TableCell>
                            <TableCell class="text-xs text-muted-foreground">{{ t.actions.map((a) => a.name).join(', ') || '—' }}</TableCell>
                            <TableCell class="max-w-md text-xs text-muted-foreground line-clamp-2">
                                <TosText v-if="t.body" :text="t.body" />
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>

            <div v-else-if="triggers.data.length" class="grid gap-3 sm:grid-cols-2">
                <Card
                    v-for="t in triggers.data"
                    :key="t.id"
                    class="h-full transition-all duration-200 ease-out hover:-translate-y-0.5 hover:border-primary/30 hover:shadow-md"
                >
                    <CardContent class="p-4 text-sm">
                        <div class="mb-1 flex items-center justify-between gap-2">
                            <span class="font-semibold">{{ t.name }}</span>
                            <TosSuits v-if="t.suits" :suits="t.suits" />
                            <TosMarginCost v-else-if="t.margin_cost != null" :cost="t.margin_cost" />
                        </div>
                        <p v-if="t.actions.length" class="text-[11px] text-muted-foreground">
                            on {{ t.actions.map((a) => a.name).join(', ') }}
                            <span v-if="t.timing === 'immediately'" class="ml-1 italic">(Immediately)</span>
                        </p>
                        <p v-if="t.body" class="mt-2 text-xs text-muted-foreground"><TosText :text="t.body" /></p>
                    </CardContent>
                </Card>
            </div>
            <EmptyState
                v-else
                :icon="Swords"
                title="No triggers match"
                description="Triggers attach to Actions as suit-driven or margin-driven follow-ups. Try clearing your search."
            />

            <InertiaPagination v-if="!isLoading" :paginator="triggers" :only="['triggers', 'name_search', 'page_view']" />
        </div>
    </div>
</template>
