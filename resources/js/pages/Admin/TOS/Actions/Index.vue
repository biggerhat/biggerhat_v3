<script setup lang="ts">
import AdminActions from '@/components/AdminActions.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { valueUpdater } from '@/lib/utils';
import { Head, router } from '@inertiajs/vue3';
import { FlexRender, getCoreRowModel, getFilteredRowModel, getPaginationRowModel, useVueTable } from '@tanstack/vue-table';
import type { ColumnDef, ColumnFiltersState } from '@tanstack/vue-table';
import { h, ref } from 'vue';

interface Action {
    id: number;
    slug: string;
    name: string;
    av: number | null;
    av_target: string | null;
    av_suits: string | null;
    range: string | null;
    strength: number | null;
    type_links: Array<{ id: number; type: string }>;
}

const columns: ColumnDef<Action>[] = [
    { accessorKey: 'name', header: () => h('div', {}, 'Name'), cell: ({ row }) => h('div', { class: 'font-medium' }, row.getValue('name')) },
    { id: 'types', header: () => h('div', {}, 'Types'), cell: ({ row }) => h('div', { class: 'text-[11px] capitalize' }, (row.original.type_links ?? []).map((l) => l.type).join(', ')) },
    { accessorKey: 'av', header: () => h('div', {}, 'AV'), cell: ({ row }) => h('div', { class: 'text-[11px]' }, row.original.av != null ? `${row.original.av}${row.original.av_suits ?? ''}${row.original.av_target ? ' v ' + row.original.av_target : ''}` : '—') },
    { accessorKey: 'range', header: () => h('div', {}, 'Range'), cell: ({ row }) => h('div', { class: 'text-[11px]' }, row.original.range ?? '—') },
    { accessorKey: 'strength', header: () => h('div', {}, 'Strength'), cell: ({ row }) => h('div', { class: 'text-[11px] tabular-nums' }, row.original.strength ?? '—') },
    {
        id: 'actions',
        enableHiding: false,
        header: () => h('div', {}, 'Actions'),
        cell: ({ row }) => h(AdminActions, {
            name: row.original.name,
            editRoute: route('admin.tos.actions.edit', row.original.slug),
            deleteRoute: route('admin.tos.actions.delete', row.original.slug),
        }),
    },
];

const props = defineProps<{ actions: Action[]; action_types: Array<{ name: string; value: string }> }>();
const columnFilters = ref<ColumnFiltersState>([]);

const table = useVueTable({
    get data() { return props.actions; },
    get columns() { return columns; },
    getCoreRowModel: getCoreRowModel(),
    getPaginationRowModel: getPaginationRowModel(),
    onColumnFiltersChange: (u) => valueUpdater(u, columnFilters),
    getFilteredRowModel: getFilteredRowModel(),
    state: { get columnFilters() { return columnFilters.value; } },
});
</script>

<template>
    <Head title="TOS Actions — Admin" />
    <div class="container mx-auto mt-6 h-full px-2">
        <div class="flex items-center justify-between py-4">
            <Input class="max-w-sm" placeholder="Filter by name" :model-value="table.getColumn('name')?.getFilterValue() as string" @update:model-value="table.getColumn('name')?.setFilterValue($event)" />
            <Button @click="router.get(route('admin.tos.actions.create'))">Create Action</Button>
        </div>
        <div class="rounded-md border">
            <Table>
                <TableHeader>
                    <TableRow v-for="hg in table.getHeaderGroups()" :key="hg.id">
                        <TableHead v-for="header in hg.headers" :key="header.id">
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
                    <template v-else><TableRow><TableCell :colspan="columns.length" class="h-24 text-center">No results.</TableCell></TableRow></template>
                </TableBody>
            </Table>
        </div>
    </div>
</template>
