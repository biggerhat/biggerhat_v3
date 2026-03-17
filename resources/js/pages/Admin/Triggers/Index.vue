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
    const suits = (row.getValue('suits') as string)?.toLowerCase() ?? '';
    const notes = (row.getValue('internal_notes') as string)?.toLowerCase() ?? '';
    return name.includes(search) || suits.includes(search) || notes.includes(search);
};

const columns: ColumnDef<Triggers>[] = [
    {
        accessorKey: 'id',
        header: () => h('div', {}, 'ID'),
        cell: ({ row }) => {
            return h('div', {}, row.getValue('id'));
        },
    },
    {
        accessorKey: 'name',
        header: () => h('div', {}, 'Trigger'),
        cell: ({ row }) => {
            return h('div', {}, row.getValue('name'));
        },
    },
    {
        accessorKey: 'suits',
        header: () => h('div', {}, 'Suits'),
        cell: ({ row }) => {
            const suits = row.getValue('suits') as string | null;
            return h('div', { class: 'text-sm text-muted-foreground' }, suits ?? '-');
        },
    },
    {
        accessorKey: 'stone_cost',
        header: () => h('div', {}, 'Stone Cost'),
        cell: ({ row }) => {
            const cost = row.getValue('stone_cost') as number;
            return h('div', {}, cost > 0 ? String(cost) : '-');
        },
    },
    {
        accessorKey: 'internal_notes',
        header: () => h('div', {}, 'Internal Notes'),
        cell: ({ row }) => {
            const notes = row.getValue('internal_notes') as string | null;
            return h('div', { class: 'text-xs text-muted-foreground max-w-xs truncate' }, notes ?? '');
        },
    },
    {
        id: 'actions',
        enableHiding: false,
        header: () => h('div', {}, 'Actions'),
        cell: ({ row }) => {
            const trigger = row.original;

            return h(
                'div',
                { class: 'relative' },
                h(AdminActions, {
                    name: trigger.name,
                    editRoute: route('admin.triggers.edit', trigger.slug),
                    deleteRoute: route('admin.triggers.delete', trigger.slug),
                }),
            );
        },
    },
];

const props = defineProps<{
    triggers: TData[];
}>();

const globalFilter = ref('');

const table = useVueTable({
    get data() {
        return props.triggers;
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
    <Head title="Triggers - Admin" />

    <div class="container mx-auto mt-6 h-full px-2">
        <div class="flex items-center justify-between py-4">
            <Input
                class="max-w-sm"
                placeholder="Filter by name or notes..."
                :model-value="globalFilter"
                @update:model-value="table.setGlobalFilter($event)"
            />
            <div>Total {{ props.triggers.length }}</div>
            <Button @click="router.get(route('admin.triggers.create'))"> Create New Trigger </Button>
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
                        <TableRow v-for="row in table.getRowModel().rows" :key="row.id" :data-state="row.getIsSelected() ? 'selected' : undefined">
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
