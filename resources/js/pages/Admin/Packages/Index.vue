<script setup lang="ts">
import AdminActions from '@/components/AdminActions.vue';
import PageBanner from '@/components/PageBanner.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { valueUpdater } from '@/lib/utils';
import { Head, router } from '@inertiajs/vue3';
import type { ColumnDef, FilterFn, SortingState } from '@tanstack/vue-table';
import { computed, h, ref } from 'vue';

import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';

import { FlexRender, getCoreRowModel, getFilteredRowModel, getPaginationRowModel, getSortedRowModel, useVueTable } from '@tanstack/vue-table';

const showUnattachedOnly = ref(false);
const showNeedsReviewOnly = ref(false);
const showNoMiniaturesOnly = ref(false);
const showNoKeywordsOnly = ref(false);
const categoryFilter = ref<string>('all');
const searchText = ref('');

const globalSearchFilter: FilterFn<any> = (row, _columnId, filterValue) => {
    const { search, unattachedOnly, needsReviewOnly, noMiniaturesOnly, noKeywordsOnly, category } = filterValue as {
        search: string;
        unattachedOnly: boolean;
        needsReviewOnly: boolean;
        noMiniaturesOnly: boolean;
        noKeywordsOnly: boolean;
        category: string;
    };
    const term = search.toLowerCase();
    const name = (row.getValue('name') as string)?.toLowerCase() ?? '';

    let matches = !term || name.includes(term);

    if (unattachedOnly) {
        matches = matches && row.original.characters_count === 0 && row.original.miniatures_count === 0 && row.original.tos_units_count === 0;
    }

    if (needsReviewOnly) {
        matches = matches && !!row.original.is_auto_generated;
    }

    if (noMiniaturesOnly) {
        matches = matches && row.original.miniatures_count === 0;
    }

    if (noKeywordsOnly) {
        matches = matches && row.original.keywords_count === 0;
    }

    if (category !== 'all') {
        matches = matches && row.original.category === category;
    }

    return matches;
};

const columns: ColumnDef<any>[] = [
    {
        accessorKey: 'name',
        header: () => h('div', {}, 'Name'),
        cell: ({ row }) => {
            const nodes = [h('span', {}, row.getValue('name') as string)];
            if (row.original.is_auto_generated) {
                nodes.push(
                    h(
                        Badge,
                        {
                            variant: 'outline',
                            class: 'ml-2 border-amber-500/50 text-[10px] text-amber-600 dark:text-amber-400',
                            title: 'Derived from the box-contents reference data — needs review',
                        },
                        () => 'Needs Review',
                    ),
                );
            }
            return h('div', {}, nodes);
        },
    },
    {
        accessorKey: 'game_system',
        header: () => h('div', {}, 'Game System'),
        cell: ({ row }) => {
            const gameSystem = row.getValue('game_system') as string;
            const label = gameSystem === 'both' ? 'Both' : gameSystem === 'tos' ? 'The Other Side' : 'Malifaux';
            return h(Badge, { variant: 'outline', class: 'text-[10px]' }, () => label);
        },
    },
    {
        accessorKey: 'category_label',
        header: () => h('div', {}, 'Category'),
        cell: ({ row }) => {
            const label = row.getValue('category_label') as string | null;
            return label ? h(Badge, { variant: 'outline', class: 'text-[10px]' }, () => label) : h('div', { class: 'text-muted-foreground' }, '-');
        },
    },
    {
        accessorKey: 'characters_count',
        header: () => h('div', {}, 'Characters'),
        cell: ({ row }) => {
            const count = row.getValue('characters_count') as number;
            return h('div', { class: count ? '' : 'text-muted-foreground' }, String(count));
        },
    },
    {
        accessorKey: 'miniatures_count',
        header: () => h('div', {}, 'Miniatures'),
        cell: ({ row }) => {
            const count = row.getValue('miniatures_count') as number;
            return h('div', { class: count ? '' : 'text-muted-foreground' }, String(count));
        },
    },
    {
        accessorKey: 'tos_units_count',
        header: () => h('div', {}, 'TOS Units'),
        cell: ({ row }) => {
            const count = row.getValue('tos_units_count') as number;
            return h('div', { class: count ? '' : 'text-muted-foreground' }, String(count));
        },
    },
    {
        accessorKey: 'factions',
        header: () => h('div', {}, 'Factions'),
        cell: ({ row }) => {
            const factions = row.getValue('factions') as string[] | null;
            return h('div', {}, factions?.join(', ') ?? '-');
        },
    },
    {
        accessorKey: 'keywords',
        header: () => h('div', {}, 'Keywords'),
        cell: ({ row }) => {
            const keywords = row.getValue('keywords') as string[];
            if (!keywords?.length) {
                return h('div', { class: 'text-muted-foreground' }, '-');
            }
            return h(
                'div',
                { class: 'flex flex-wrap gap-1' },
                keywords.map((k) => h(Badge, { key: k, variant: 'secondary', class: 'text-[10px]' }, () => k)),
            );
        },
    },
    {
        id: 'actions',
        enableHiding: false,
        enableSorting: false,
        header: () => h('div', {}, 'Actions'),
        cell: ({ row }) => {
            const pkg = row.original;

            return h(
                'div',
                { class: 'relative' },
                h(AdminActions, {
                    name: pkg.name,
                    editRoute: route('admin.packages.edit', pkg.slug),
                    deleteRoute: route('admin.packages.delete', pkg.slug),
                }),
            );
        },
    },
];

const props = defineProps<{
    packages: any[];
    categories: { name: string; value: string }[];
}>();

const sorting = ref<SortingState>([]);

const globalFilterValue = computed(() => ({
    search: searchText.value,
    unattachedOnly: showUnattachedOnly.value,
    needsReviewOnly: showNeedsReviewOnly.value,
    noMiniaturesOnly: showNoMiniaturesOnly.value,
    noKeywordsOnly: showNoKeywordsOnly.value,
    category: categoryFilter.value,
}));

const table = useVueTable({
    get data() {
        return props.packages;
    },
    get columns() {
        return columns;
    },
    getCoreRowModel: getCoreRowModel(),
    getPaginationRowModel: getPaginationRowModel(),
    getFilteredRowModel: getFilteredRowModel(),
    getSortedRowModel: getSortedRowModel(),
    globalFilterFn: globalSearchFilter,
    onSortingChange: (updaterOrValue) => valueUpdater(updaterOrValue, sorting),
    state: {
        get globalFilter() {
            return globalFilterValue.value;
        },
        get sorting() {
            return sorting.value;
        },
    },
});
</script>

<template>
    <Head title="Packages - Admin" />

    <PageBanner title="Packages" class="mb-2">
        <template #actions>
            <Button class="my-auto mr-2" @click="router.get(route('admin.packages.create'))"> Create New Package </Button>
        </template>
    </PageBanner>

    <div class="container mx-auto mt-6 h-full px-2">
        <div class="flex flex-wrap items-center justify-between gap-y-2 py-4">
            <div class="flex flex-wrap items-center gap-4">
                <Input class="max-w-sm" placeholder="Filter Packages" :model-value="searchText" @update:model-value="searchText = $event" />
                <Select :model-value="categoryFilter" @update:model-value="(v) => (categoryFilter = v as string)">
                    <SelectTrigger class="h-9 w-44 text-sm"><SelectValue /></SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">All categories</SelectItem>
                        <SelectItem v-for="c in categories" :key="c.value" :value="c.value">{{ c.name }}</SelectItem>
                    </SelectContent>
                </Select>
                <label class="flex cursor-pointer items-center gap-2 whitespace-nowrap text-sm">
                    <Checkbox :checked="showUnattachedOnly" @update:checked="(val: boolean) => (showUnattachedOnly = val)" />
                    Unattached only
                </label>
                <label class="flex cursor-pointer items-center gap-2 whitespace-nowrap text-sm">
                    <Checkbox :checked="showNeedsReviewOnly" @update:checked="(val: boolean) => (showNeedsReviewOnly = val)" />
                    Needs review only
                </label>
                <label class="flex cursor-pointer items-center gap-2 whitespace-nowrap text-sm">
                    <Checkbox :checked="showNoMiniaturesOnly" @update:checked="(val: boolean) => (showNoMiniaturesOnly = val)" />
                    No miniatures
                </label>
                <label class="flex cursor-pointer items-center gap-2 whitespace-nowrap text-sm">
                    <Checkbox :checked="showNoKeywordsOnly" @update:checked="(val: boolean) => (showNoKeywordsOnly = val)" />
                    No keywords
                </label>
            </div>
            <div>Total {{ table.getFilteredRowModel().rows.length }}</div>
        </div>
        <div class="rounded-md border">
            <Table>
                <TableHeader>
                    <TableRow v-for="headerGroup in table.getHeaderGroups()" :key="headerGroup.id">
                        <TableHead
                            v-for="header in headerGroup.headers"
                            :key="header.id"
                            :class="{ 'cursor-pointer select-none': header.column.getCanSort() }"
                            @click="header.column.getToggleSortingHandler()?.($event)"
                        >
                            <div class="flex items-center gap-1">
                                <FlexRender v-if="!header.isPlaceholder" :render="header.column.columnDef.header" :props="header.getContext()" />
                                <span v-if="header.column.getIsSorted() === 'asc'">↑</span>
                                <span v-else-if="header.column.getIsSorted() === 'desc'">↓</span>
                            </div>
                        </TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <template v-if="table.getRowModel().rows?.length">
                        <TableRow v-for="row in table.getRowModel().rows" :key="row.id">
                            <TableCell v-for="cell in row.getVisibleCells()" :key="cell.id">
                                <FlexRender :render="cell.column.columnDef.cell" :props="cell.getContext()" />
                            </TableCell>
                        </TableRow>
                    </template>
                    <template v-else>
                        <TableRow>
                            <TableCell :colspan="columns.length" class="h-24 text-center"> No results. </TableCell>
                        </TableRow>
                    </template>
                </TableBody>
            </Table>
        </div>
        <div class="flex items-center justify-end space-x-2 py-4">
            <Button variant="outline" size="sm" :disabled="!table.getCanPreviousPage()" @click="table.previousPage()"> Previous </Button>
            <Button variant="outline" size="sm" :disabled="!table.getCanNextPage()" @click="table.nextPage()"> Next </Button>
        </div>
    </div>
</template>
