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

interface Rule {
    id: number;
    slug: string;
    name: string;
    description: string | null;
    sort_order: number;
}

const columns: ColumnDef<Rule>[] = [
    { accessorKey: 'name', header: () => h('div', {}, 'Name'), cell: ({ row }) => h('div', { class: 'font-medium' }, row.getValue('name')) },
    { accessorKey: 'slug', header: () => h('div', {}, 'Slug'), cell: ({ row }) => h('div', { class: 'text-[11px] text-muted-foreground' }, row.getValue('slug')) },
    {
        id: 'actions',
        enableHiding: false,
        header: () => h('div', {}, 'Actions'),
        cell: ({ row }) => h(AdminActions, {
            name: row.original.name,
            editRoute: route('admin.tos.special_rules.edit', row.original.slug),
            deleteRoute: route('admin.tos.special_rules.delete', row.original.slug),
        }),
    },
];

const props = defineProps<{ rules: Rule[] }>();
const columnFilters = ref<ColumnFiltersState>([]);

const table = useVueTable({
    get data() { return props.rules; },
    get columns() { return columns; },
    getCoreRowModel: getCoreRowModel(),
    getPaginationRowModel: getPaginationRowModel(),
    onColumnFiltersChange: (u) => valueUpdater(u, columnFilters),
    getFilteredRowModel: getFilteredRowModel(),
    state: { get columnFilters() { return columnFilters.value; } },
});
</script>

<template>
    <Head title="TOS Special Rules — Admin" />
    <div class="container mx-auto mt-6 h-full px-2">
        <div class="flex items-center justify-between py-4">
            <Input class="max-w-sm" placeholder="Filter by name" :model-value="table.getColumn('name')?.getFilterValue() as string" @update:model-value="table.getColumn('name')?.setFilterValue($event)" />
            <Button @click="router.get(route('admin.tos.special_rules.create'))">Create Rule</Button>
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
