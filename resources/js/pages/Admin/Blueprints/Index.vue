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
    const characters = (row.original.characters ?? []).map((c: any) => (c.display_name as string)?.toLowerCase() ?? '');
    return name.includes(search) || characters.some((n: string) => n.includes(search));
};

const columns: ColumnDef<any>[] = [
    {
        accessorKey: 'id',
        header: () => h('div', {}, 'ID'),
        cell: ({ row }) => h('div', {}, row.getValue('id')),
    },
    {
        accessorKey: 'name',
        header: () => h('div', {}, 'Blueprint'),
        cell: ({ row }) => h('div', {}, row.getValue('name')),
    },
    {
        accessorKey: 'sculpt_version',
        header: () => h('div', {}, 'Edition'),
        cell: ({ row }) => {
            const version = row.getValue('sculpt_version') as string;
            return h('div', { class: 'text-sm text-muted-foreground capitalize' }, version?.replace(/_/g, ' ') ?? '-');
        },
    },
    {
        id: 'characters',
        header: () => h('div', {}, 'Characters'),
        cell: ({ row }) => {
            const count = row.original.characters?.length ?? 0;
            return h('div', { class: 'text-sm text-muted-foreground' }, `${count}`);
        },
    },
    {
        id: 'miniatures',
        header: () => h('div', {}, 'Miniatures'),
        cell: ({ row }) => {
            const count = row.original.miniatures?.length ?? 0;
            return h('div', { class: 'text-sm text-muted-foreground' }, `${count}`);
        },
    },
    {
        id: 'image',
        header: () => h('div', {}, 'Image'),
        cell: ({ row }) => {
            const hasImage = !!row.original.image_path;
            return h('div', { class: 'text-sm text-muted-foreground' }, hasImage ? 'Yes' : '-');
        },
    },
    {
        id: 'actions',
        enableHiding: false,
        header: () => h('div', {}, 'Actions'),
        cell: ({ row }) => {
            const blueprint = row.original;
            return h(
                'div',
                { class: 'relative' },
                h(AdminActions, {
                    name: blueprint.name,
                    editRoute: route('admin.blueprints.edit', blueprint.slug),
                    deleteRoute: route('admin.blueprints.delete', blueprint.slug),
                }),
            );
        },
    },
];

const props = defineProps<{
    blueprints: any[];
}>();

const globalFilter = ref('');

const table = useVueTable({
    get data() {
        return props.blueprints;
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
    initialState: {
        pagination: {
            pageSize: 50,
        },
    },
});
</script>

<template>
    <Head title="Blueprints - Admin" />

    <div class="container mx-auto mt-6 h-full px-2">
        <div class="flex items-center justify-between py-4">
            <Input
                class="max-w-sm"
                placeholder="Filter by name or character..."
                :model-value="globalFilter"
                @update:model-value="table.setGlobalFilter($event)"
            />
            <div>Total {{ props.blueprints.length }}</div>
            <Button @click="router.get(route('admin.blueprints.create'))"> Create New Blueprint </Button>
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
