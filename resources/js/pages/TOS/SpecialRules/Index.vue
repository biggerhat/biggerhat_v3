<script setup lang="ts">
import CardSkeleton from '@/components/CardSkeleton.vue';
import EmptyState from '@/components/EmptyState.vue';
import InertiaPagination from '@/components/InertiaPagination.vue';
import ListSearchBar from '@/components/ListSearchBar.vue';
import PageBanner from '@/components/PageBanner.vue';
import TableSkeleton from '@/components/TableSkeleton.vue';
import TosText from '@/components/TosText.vue';
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
    <div class="relative">
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
                            <TableHead>Description</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="r in rules.data" :key="r.id">
                            <TableCell class="font-medium">{{ r.name }}</TableCell>
                            <TableCell class="text-xs text-muted-foreground">
                                <TosText v-if="r.description" :text="r.description" />
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>

            <div v-else-if="rules.data.length" class="grid gap-3 sm:grid-cols-2">
                <Card v-for="r in rules.data" :key="r.id">
                    <CardContent class="p-4">
                        <p class="mb-1 text-sm font-semibold">{{ r.name }}</p>
                        <p v-if="r.description" class="text-xs text-muted-foreground"><TosText :text="r.description" /></p>
                    </CardContent>
                </Card>
            </div>
            <EmptyState v-else :icon="BookOpen" title="No special rules yet" />

            <InertiaPagination v-if="!isLoading" :paginator="rules" :only="['rules', 'name_search', 'page_view']" />
        </div>
    </div>
</template>
