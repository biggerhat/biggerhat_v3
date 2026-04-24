<script setup lang="ts">
import AdminActions from '@/components/AdminActions.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { valueUpdater } from '@/lib/utils';
import { Head, router } from '@inertiajs/vue3';
import type { ColumnDef, ColumnFiltersState } from '@tanstack/vue-table';
import { h, ref, watch } from 'vue';

import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';

import { FlexRender, getCoreRowModel, getFilteredRowModel, getPaginationRowModel, useVueTable } from '@tanstack/vue-table';

interface Allegiance {
    id: number;
    slug: string;
    name: string;
    short_name: string | null;
    type: string;
    is_syndicate: boolean;
    sort_order: number;
}

const columns: ColumnDef<Allegiance>[] = [
    {
        accessorKey: 'name',
        header: () => h('div', {}, 'Name'),
        cell: ({ row }) => h('div', { class: 'font-medium' }, row.getValue('name')),
    },
    {
        accessorKey: 'type',
        header: () => h('div', {}, 'Type'),
        cell: ({ row }) => h('div', { class: 'capitalize' }, row.getValue('type')),
        filterFn: 'equalsString',
    },
    {
        accessorKey: 'is_syndicate',
        header: () => h('div', {}, 'Syndicate'),
        cell: ({ row }) => h('div', {}, row.getValue('is_syndicate') ? 'Yes' : 'No'),
        filterFn: (row, columnId, value) => {
            if (value === 'all') return true;
            return String(row.getValue(columnId)) === value;
        },
    },
    {
        id: 'actions',
        enableHiding: false,
        header: () => h('div', {}, 'Actions'),
        cell: ({ row }) => {
            const a = row.original;
            return h(
                'div',
                { class: 'relative' },
                h(AdminActions, {
                    name: a.name,
                    editRoute: route('admin.tos.allegiances.edit', a.slug),
                    deleteRoute: route('admin.tos.allegiances.delete', a.slug),
                }),
            );
        },
    },
];

const props = defineProps<{
    allegiances: Allegiance[];
}>();

const columnFilters = ref<ColumnFiltersState>([]);
const typeFilter = ref('all');
const syndicateFilter = ref('all');

const table = useVueTable({
    get data() {
        return props.allegiances;
    },
    get columns() {
        return columns;
    },
    getCoreRowModel: getCoreRowModel(),
    getPaginationRowModel: getPaginationRowModel(),
    onColumnFiltersChange: (updaterOrValue) => valueUpdater(updaterOrValue, columnFilters),
    getFilteredRowModel: getFilteredRowModel(),
    state: {
        get columnFilters() {
            return columnFilters.value;
        },
    },
});

watch(typeFilter, (val) => {
    table.getColumn('type')?.setFilterValue(val === 'all' ? undefined : val);
});
watch(syndicateFilter, (val) => {
    table.getColumn('is_syndicate')?.setFilterValue(val);
});
</script>

<template>
    <Head title="TOS Allegiances - Admin" />

    <div class="container mx-auto mt-6 h-full px-2">
        <div class="flex items-center justify-between py-4">
            <div class="flex items-center gap-2">
                <Input
                    class="max-w-sm"
                    placeholder="Filter by name"
                    :model-value="table.getColumn('name')?.getFilterValue() as string"
                    @update:model-value="table.getColumn('name')?.setFilterValue($event)"
                />
                <Select v-model="typeFilter">
                    <SelectTrigger class="w-[140px]">
                        <SelectValue placeholder="Type" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">All Types</SelectItem>
                        <SelectItem value="earth">Earth</SelectItem>
                        <SelectItem value="malifaux">Malifaux</SelectItem>
                    </SelectContent>
                </Select>
                <Select v-model="syndicateFilter">
                    <SelectTrigger class="w-[140px]">
                        <SelectValue placeholder="Syndicate" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">All</SelectItem>
                        <SelectItem value="false">Allegiance</SelectItem>
                        <SelectItem value="true">Syndicate</SelectItem>
                    </SelectContent>
                </Select>
            </div>
            <div>Total {{ table.getFilteredRowModel().rows.length }}</div>
            <Button @click="router.get(route('admin.tos.allegiances.create'))">Create Allegiance</Button>
        </div>
        <div class="rounded-md border">
            <Table>
                <TableHeader>
                    <TableRow v-for="headerGroup in table.getHeaderGroups()" :key="headerGroup.id">
                        <TableHead v-for="header in headerGroup.headers" :key="header.id">
                            <FlexRender v-if="!header.isPlaceholder" :render="header.column.columnDef.header" :props="header.getContext()" />
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
                            <TableCell :colspan="columns.length" class="h-24 text-center">No results.</TableCell>
                        </TableRow>
                    </template>
                </TableBody>
            </Table>
        </div>
        <div class="flex items-center justify-end space-x-2 py-4">
            <Button variant="outline" size="sm" :disabled="!table.getCanPreviousPage()" @click="table.previousPage()">Previous</Button>
            <Button variant="outline" size="sm" :disabled="!table.getCanNextPage()" @click="table.nextPage()">Next</Button>
        </div>
    </div>
</template>
