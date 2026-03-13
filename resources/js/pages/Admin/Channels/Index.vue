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

interface Channel {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    image: string | null;
    transmissions_count: number;
    users: Array<{ id: number; name: string }>;
}

const columns: ColumnDef<Channel>[] = [
    {
        accessorKey: 'name',
        header: () => h('div', {}, 'Channel'),
        cell: ({ row }) => {
            return h('div', {}, row.getValue('name'));
        },
    },
    {
        accessorKey: 'transmissions_count',
        header: () => h('div', {}, 'Transmissions'),
        cell: ({ row }) => {
            return h('div', {}, row.getValue('transmissions_count'));
        },
    },
    {
        id: 'owners',
        header: () => h('div', {}, 'Owners'),
        cell: ({ row }) => {
            const channel = row.original;
            return h(
                'div',
                {},
                channel.users.map((u) => u.name).join(', ') || '-',
            );
        },
    },
    {
        id: 'actions',
        enableHiding: false,
        header: () => h('div', {}, 'Actions'),
        cell: ({ row }) => {
            const channel = row.original;

            return h(
                'div',
                { class: 'relative' },
                h(AdminActions, {
                    name: channel.name,
                    editRoute: route('admin.channels.edit', channel.slug),
                    deleteRoute: route('admin.channels.delete', channel.slug),
                }),
            );
        },
    },
];

const props = defineProps<{
    channels: Channel[];
}>();

const columnFilters = ref<ColumnFiltersState>([]);

const table = useVueTable({
    get data() {
        return props.channels;
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
    <Head title="Channels - Admin" />

    <div class="container mx-auto mt-6 h-full px-2">
        <div class="flex items-center justify-between py-4">
            <Input
                class="max-w-sm"
                placeholder="Filter Channels"
                :model-value="table.getColumn('name')?.getFilterValue() as string"
                @update:model-value="table.getColumn('name')?.setFilterValue($event)"
            />
            <div>Total {{ props.channels.length }}</div>
            <Button @click="router.get(route('admin.channels.create'))"> Create New Channel </Button>
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
