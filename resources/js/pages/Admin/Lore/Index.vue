<script setup lang="ts">
import AdminActions from '@/components/AdminActions.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { valueUpdater } from '@/lib/utils';
import { Head, router } from '@inertiajs/vue3';
import type { ColumnDef, FilterFn } from '@tanstack/vue-table';
import { h, ref } from 'vue';

import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';

import { FlexRender, getCoreRowModel, getFilteredRowModel, getPaginationRowModel, useVueTable } from '@tanstack/vue-table';

const globalSearchFilter: FilterFn<any> = (row, _columnId, filterValue) => {
    const search = (filterValue as string).toLowerCase();
    const name = (row.getValue('name') as string)?.toLowerCase() ?? '';
    const mediaName = (row.original.media?.name as string)?.toLowerCase() ?? '';
    return name.includes(search) || mediaName.includes(search);
};

const columns: ColumnDef<any>[] = [
    {
        accessorKey: 'id',
        header: () => h('div', {}, 'ID'),
        cell: ({ row }) => h('div', {}, row.getValue('id')),
    },
    {
        accessorKey: 'name',
        header: () => h('div', {}, 'Story Name'),
        cell: ({ row }) => h('div', {}, row.getValue('name')),
    },
    {
        id: 'media',
        header: () => h('div', {}, 'Media'),
        cell: ({ row }) => h('div', { class: 'text-sm' }, row.original.media?.name ?? '-'),
    },
    {
        id: 'media_type',
        header: () => h('div', {}, 'Type'),
        cell: ({ row }) => h('div', { class: 'text-sm capitalize' }, (row.original.media?.type as string)?.replace(/_/g, ' ') ?? '-'),
    },
    {
        id: 'characters',
        header: () => h('div', {}, 'Characters'),
        cell: ({ row }) => {
            const count = row.original.characters?.length ?? 0;
            return h('div', { class: 'text-sm text-muted-foreground' }, `${count} character${count !== 1 ? 's' : ''}`);
        },
    },
    {
        id: 'actions',
        enableHiding: false,
        header: () => h('div', {}, 'Actions'),
        cell: ({ row }) => {
            const lore = row.original;
            return h(
                'div',
                { class: 'relative' },
                h(AdminActions, {
                    name: lore.name,
                    editRoute: route('admin.lores.edit', lore.slug),
                    deleteRoute: route('admin.lores.delete', lore.slug),
                }),
            );
        },
    },
];

const props = defineProps<{
    lores: any[];
}>();

const globalFilter = ref('');

const table = useVueTable({
    get data() {
        return props.lores;
    },
    get columns() {
        return columns;
    },
    getCoreRowModel: getCoreRowModel(),
    getPaginationRowModel: getPaginationRowModel(),
    getFilteredRowModel: getFilteredRowModel(),
    globalFilterFn: globalSearchFilter,
    onGlobalFilterChange: (updaterOrValue) => valueUpdater(updaterOrValue, globalFilter),
    state: {
        get globalFilter() {
            return globalFilter.value;
        },
    },
});
</script>

<template>
    <Head title="Lore - Admin" />

    <div class="container mx-auto mt-6 h-full px-2">
        <div class="flex items-center justify-between py-4">
            <Input
                class="max-w-sm"
                placeholder="Filter by name or media..."
                :model-value="globalFilter"
                @update:model-value="table.setGlobalFilter($event)"
            />
            <div>Total {{ props.lores.length }}</div>
            <Button @click="router.get(route('admin.lores.create'))"> Create New Lore </Button>
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
