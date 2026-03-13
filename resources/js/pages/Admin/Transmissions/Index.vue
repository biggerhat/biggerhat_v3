<script setup lang="ts">
import AdminActions from '@/components/AdminActions.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { valueUpdater } from '@/lib/utils';
import { Head, router } from '@inertiajs/vue3';
import type { ColumnDef, ColumnFiltersState } from '@tanstack/vue-table';
import { h, ref } from 'vue';

import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';

import { FlexRender, getCoreRowModel, getFilteredRowModel, getPaginationRowModel, useVueTable } from '@tanstack/vue-table';

interface Transmission {
    id: number;
    title: string;
    slug: string;
    url: string;
    transmission_type: string;
    content_type: string;
    channel: { id: number; name: string; slug: string };
}

const columns: ColumnDef<Transmission>[] = [
    {
        accessorKey: 'title',
        header: () => h('div', {}, 'Title'),
        cell: ({ row }) => {
            return h('div', {}, row.getValue('title'));
        },
    },
    {
        id: 'channel',
        header: () => h('div', {}, 'Channel'),
        cell: ({ row }) => {
            return h('div', {}, row.original.channel?.name ?? '-');
        },
    },
    {
        accessorKey: 'transmission_type',
        header: () => h('div', {}, 'Type'),
        cell: ({ row }) => {
            return h('div', { class: 'capitalize' }, row.getValue('transmission_type'));
        },
    },
    {
        accessorKey: 'content_type',
        header: () => h('div', {}, 'Content'),
        cell: ({ row }) => {
            return h('div', { class: 'capitalize' }, String(row.getValue('content_type')).replace(/_/g, ' '));
        },
    },
    {
        id: 'actions',
        enableHiding: false,
        header: () => h('div', {}, 'Actions'),
        cell: ({ row }) => {
            const transmission = row.original;

            return h(
                'div',
                { class: 'relative' },
                h(AdminActions, {
                    name: transmission.title,
                    editRoute: route('admin.transmissions.edit', transmission.slug),
                    deleteRoute: route('admin.transmissions.delete', transmission.slug),
                }),
            );
        },
    },
];

const props = defineProps<{
    transmissions: Transmission[];
}>();

const columnFilters = ref<ColumnFiltersState>([]);

const table = useVueTable({
    get data() {
        return props.transmissions;
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
</script>

<template>
    <Head title="Transmissions - Admin" />

    <div class="container mx-auto mt-6 h-full px-2">
        <div class="flex items-center justify-between py-4">
            <Input
                class="max-w-sm"
                placeholder="Filter Transmissions"
                :model-value="table.getColumn('title')?.getFilterValue() as string"
                @update:model-value="table.getColumn('title')?.setFilterValue($event)"
            />
            <div>Total {{ props.transmissions.length }}</div>
            <Button @click="router.get(route('admin.transmissions.create'))"> Create New Transmission </Button>
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
