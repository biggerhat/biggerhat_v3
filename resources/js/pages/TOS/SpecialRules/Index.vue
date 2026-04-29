<script setup lang="ts">
import type { Paginator } from '@/types/tos';
import CardSkeleton from '@/components/CardSkeleton.vue';
import EmptyState from '@/components/EmptyState.vue';
import InertiaPagination from '@/components/InertiaPagination.vue';
import ListSearchBar from '@/components/ListSearchBar.vue';
import PageBanner from '@/components/PageBanner.vue';
import TableSkeleton from '@/components/TableSkeleton.vue';
import TosText from '@/components/TosText.vue';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useListFiltering } from '@/composables/useListFiltering';
import { Head } from '@inertiajs/vue3';
import { BookOpen } from 'lucide-vue-next';

interface Rule {
    id: number;
    slug: string;
    name: string;
    description: string | null;
    units_count?: number;
}

const props = defineProps<{
    rules: Paginator<Rule>;
    name_search: string | null;
    page_view: string;
}>();

const { filterParams, activeFilterCount, filter, clear, handleNameKeydown, clearNameSearch, handleViewChange, isLoading } = useListFiltering(
    {
        name_search: props.name_search as string | null,
        page_view: props.page_view as string | null,
    },
    { routeName: 'tos.special_rules.index', filterKeys: [], only: ['rules', 'name_search', 'page_view'] },
);
</script>

<template>
    <Head title="Special Unit Rules — TOS" />
    <div class="relative pb-12">
        <div
            class="pointer-events-none absolute inset-x-0 top-0 h-64 opacity-[0.07] dark:opacity-[0.12]"
            :style="{ background: 'radial-gradient(ellipse at top, hsl(var(--primary)) 0%, transparent 70%)' }"
        />

        <PageBanner title="Special Unit Rules" class="mb-2">
            <template #subtitle>
                <div class="my-auto px-2 py-0 text-xs text-muted-foreground md:py-2 md:text-sm md:text-foreground">
                    The categorical tags that define how a Unit Card behaves in play
                </div>
            </template>
        </PageBanner>

        <ListSearchBar
            v-model:name-search="filterParams.name_search"
            :page-view="filterParams.page_view"
            :active-filter-count="activeFilterCount"
            placeholder="Search rules by name..."
            @update:page-view="handleViewChange"
            @name-keydown="handleNameKeydown"
            @clear-search="clearNameSearch"
            @filter="filter"
            @clear="clear"
        />

        <div class="container mx-auto sm:px-4">
            <div v-if="isLoading && filterParams.page_view === 'table'" class="overflow-auto">
                <TableSkeleton :rows="8" :cols="2" />
            </div>
            <div v-else-if="isLoading" class="grid gap-3 sm:grid-cols-2">
                <CardSkeleton v-for="n in 6" :key="`skeleton-${n}`" />
            </div>

            <div v-else-if="filterParams.page_view === 'table' && rules.data.length" class="overflow-auto rounded-md border">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Name</TableHead>
                            <TableHead class="w-24 text-right">Units</TableHead>
                            <TableHead>Description</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="r in rules.data" :key="r.id" class="transition-colors hover:bg-muted/40">
                            <TableCell class="font-medium">{{ r.name }}</TableCell>
                            <TableCell class="text-right text-xs tabular-nums text-muted-foreground">
                                {{ r.units_count ?? 0 }}
                            </TableCell>
                            <TableCell class="text-xs text-muted-foreground">
                                <TosText v-if="r.description" :text="r.description" />
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>

            <div v-else-if="rules.data.length" class="grid gap-3 sm:grid-cols-2">
                <Card
                    v-for="r in rules.data"
                    :key="r.id"
                    class="h-full transition-all duration-200 ease-out hover:-translate-y-0.5 hover:border-primary/30 hover:shadow-md"
                >
                    <CardContent class="p-4">
                        <div class="mb-1 flex items-center justify-between gap-2">
                            <p class="text-sm font-semibold">{{ r.name }}</p>
                            <Badge v-if="r.units_count" variant="outline" class="shrink-0 text-[10px] tabular-nums">
                                {{ r.units_count }} {{ r.units_count === 1 ? 'unit' : 'units' }}
                            </Badge>
                        </div>
                        <p v-if="r.description" class="text-xs text-muted-foreground"><TosText :text="r.description" /></p>
                    </CardContent>
                </Card>
            </div>
            <EmptyState
                v-else
                :icon="BookOpen"
                title="No special rules match"
                description="Special Unit Rules tag a Unit Card with a behaviour (Commander, Fireteam, Squad, etc.). Try clearing your search."
            />

            <InertiaPagination v-if="!isLoading" :paginator="rules" :only="['rules', 'name_search', 'page_view']" />
        </div>
    </div>
</template>
