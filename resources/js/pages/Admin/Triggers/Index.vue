<script setup lang="ts">
import { sortableHeader } from '@/lib/adminTable';
import AdminActions from '@/components/AdminActions.vue';
import PageBanner from '@/components/PageBanner.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { valueUpdater } from '@/lib/utils';
import { Head, router } from '@inertiajs/vue3';
import type { ColumnDef, FilterFn } from '@tanstack/vue-table';
import { h, ref } from 'vue';

import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';

import { FlexRender, getCoreRowModel, getSortedRowModel, getFilteredRowModel, getPaginationRowModel, useVueTable } from '@tanstack/vue-table';

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
        header: sortableHeader('ID'),
        cell: ({ row }) => {
            return h('div', {}, row.getValue('id'));
        },
    },
    {
        accessorKey: 'name',
        header: sortableHeader('Trigger'),
        cell: ({ row }) => {
            return h('div', {}, row.getValue('name'));
        },
    },
    {
        accessorKey: 'suits',
        header: sortableHeader('Suits'),
        cell: ({ row }) => {
            const suits = row.getValue('suits') as string | null;
            return h('div', { class: 'text-sm text-muted-foreground' }, suits ?? '-');
        },
    },
    {
        accessorKey: 'stone_cost',
        header: sortableHeader('Stone Cost'),
        cell: ({ row }) => {
            const cost = row.getValue('stone_cost') as number;
            return h('div', {}, cost > 0 ? String(cost) : '-');
        },
    },
    {
        accessorKey: 'internal_notes',
        header: sortableHeader('Internal Notes'),
        cell: ({ row }) => {
            const notes = row.getValue('internal_notes') as string | null;
            return h('div', { class: 'text-xs text-muted-foreground max-w-xs truncate' }, notes ?? '');
        },
    },
    {
        id: 'actions',
        enableHiding: false,
        header: () => h('div', { class: 'text-right' }, 'Actions'),
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
    getSortedRowModel: getSortedRowModel(),
    getPaginationRowModel: getPaginationRowModel(),
    initialState: { pagination: { pageSize: 50 } },
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

    <PageBanner title="Triggers" class="mb-2">
        <template #actions>
            <Button class="my-auto mr-2" @click="router.get(route('admin.triggers.create'))"> Create New Trigger </Button>
        </template>
    </PageBanner>

    <div class="container mx-auto mt-6 h-full">
        <div class="flex items-center justify-between py-4">
            <Input
                class="max-w-sm"
                placeholder="Filter by name or notes..."
                :model-value="globalFilter"
                @update:model-value="table.setGlobalFilter($event)"
            />
            <div>Total {{ props.triggers.length }}</div>
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
