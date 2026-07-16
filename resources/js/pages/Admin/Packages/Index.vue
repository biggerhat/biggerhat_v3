<script setup lang="ts">
import AdminActions from '@/components/AdminActions.vue';
import PageBanner from '@/components/PageBanner.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { valueUpdater } from '@/lib/utils';
import { Head, router } from '@inertiajs/vue3';
import type { ColumnDef, FilterFn, SortingState } from '@tanstack/vue-table';
import { computed, h, ref } from 'vue';

import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';

import { FlexRender, getCoreRowModel, getFilteredRowModel, getPaginationRowModel, getSortedRowModel, useVueTable } from '@tanstack/vue-table';

const showUnattachedOnly = ref(false);
const showNeedsReviewOnly = ref(false);
const searchText = ref('');

const globalSearchFilter: FilterFn<any> = (row, _columnId, filterValue) => {
    const { search, unattachedOnly, needsReviewOnly } = filterValue as { search: string; unattachedOnly: boolean; needsReviewOnly: boolean };
    const term = search.toLowerCase();
    const name = (row.getValue('name') as string)?.toLowerCase() ?? '';

    let matches = !term || name.includes(term);

    if (unattachedOnly) {
        matches = matches && row.original.characters_count === 0 && row.original.miniatures_count === 0 && row.original.tos_units_count === 0;
    }

    if (needsReviewOnly) {
        matches = matches && !!row.original.is_auto_generated;
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
        accessorKey: 'sku',
        header: () => h('div', {}, 'SKU'),
        cell: ({ row }) => {
            return h('div', {}, row.getValue('sku') ?? '-');
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
        accessorKey: 'released_at',
        header: () => h('div', {}, 'Released'),
        cell: ({ row }) => {
            return h('div', {}, row.getValue('released_at') ?? '-');
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
}>();

const sorting = ref<SortingState>([]);

const globalFilterValue = computed(() => ({
    search: searchText.value,
    unattachedOnly: showUnattachedOnly.value,
    needsReviewOnly: showNeedsReviewOnly.value,
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
        <div class="flex items-center justify-between py-4">
            <div class="flex items-center gap-4">
                <Input class="max-w-sm" placeholder="Filter Packages" :model-value="searchText" @update:model-value="searchText = $event" />
                <label class="flex cursor-pointer items-center gap-2 whitespace-nowrap text-sm">
                    <Checkbox :checked="showUnattachedOnly" @update:checked="(val: boolean) => (showUnattachedOnly = val)" />
                    Unattached only
                </label>
                <label class="flex cursor-pointer items-center gap-2 whitespace-nowrap text-sm">
                    <Checkbox :checked="showNeedsReviewOnly" @update:checked="(val: boolean) => (showNeedsReviewOnly = val)" />
                    Needs review only
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
