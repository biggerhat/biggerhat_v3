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

interface Ability {
    id: number;
    slug: string;
    name: string;
    is_general: boolean;
    allegiance: { id: number; name: string } | null;
}

const columns: ColumnDef<Ability>[] = [
    { accessorKey: 'name', header: () => h('div', {}, 'Name'), cell: ({ row }) => h('div', { class: 'font-medium' }, row.getValue('name')) },
    { id: 'scope', header: () => h('div', {}, 'Scope'), cell: ({ row }) => h('div', { class: 'text-[11px]' }, row.original.is_general ? 'General' : (row.original.allegiance?.name ?? 'Allegiance — none')) },
    {
        id: 'actions',
        enableHiding: false,
        header: () => h('div', {}, 'Actions'),
        cell: ({ row }) => h(AdminActions, {
            name: row.original.name,
            editRoute: route('admin.tos.abilities.edit', row.original.slug),
            deleteRoute: route('admin.tos.abilities.delete', row.original.slug),
        }),
    },
];

const props = defineProps<{ abilities: Ability[] }>();
const columnFilters = ref<ColumnFiltersState>([]);

const table = useVueTable({
    get data() { return props.abilities; },
    get columns() { return columns; },
    getCoreRowModel: getCoreRowModel(),
    getPaginationRowModel: getPaginationRowModel(),
    onColumnFiltersChange: (u) => valueUpdater(u, columnFilters),
    getFilteredRowModel: getFilteredRowModel(),
    state: { get columnFilters() { return columnFilters.value; } },
});
</script>

<template>
    <Head title="TOS Abilities — Admin" />
    <div class="container mx-auto mt-6 h-full px-2">
        <div class="flex items-center justify-between py-4">
            <Input class="max-w-sm" placeholder="Filter by name" :model-value="table.getColumn('name')?.getFilterValue() as string" @update:model-value="table.getColumn('name')?.setFilterValue($event)" />
            <Button @click="router.get(route('admin.tos.abilities.create'))">Create Ability</Button>
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
