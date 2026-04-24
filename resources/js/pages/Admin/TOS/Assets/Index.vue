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

interface Limit { id: number; limit_type: string }
interface Asset {
    id: number;
    slug: string;
    name: string;
    scrip_cost: number;
    allegiances: Array<{ id: number; name: string }>;
    limits: Limit[];
}

const columns: ColumnDef<Asset>[] = [
    { accessorKey: 'name', header: () => h('div', {}, 'Name'), cell: ({ row }) => h('div', { class: 'font-medium' }, row.getValue('name')) },
    { accessorKey: 'scrip_cost', header: () => h('div', {}, 'Scrip'), cell: ({ row }) => h('div', { class: 'tabular-nums' }, row.getValue('scrip_cost')) },
    { id: 'allegiances', accessorFn: (r) => r.allegiances.map((a) => a.name).join(', '), header: () => h('div', {}, 'Allegiances'), cell: ({ row }) => h('div', { class: 'text-[11px] text-muted-foreground' }, row.original.allegiances.map((a) => a.name).join(', ')) },
    { id: 'limits', header: () => h('div', {}, 'Limits'), cell: ({ row }) => h('div', { class: 'text-[11px] capitalize' }, row.original.limits.map((l) => l.limit_type).join(', ')) },
    {
        id: 'actions',
        enableHiding: false,
        header: () => h('div', {}, 'Actions'),
        cell: ({ row }) => h(AdminActions, {
            name: row.original.name,
            editRoute: route('admin.tos.assets.edit', row.original.slug),
            deleteRoute: route('admin.tos.assets.delete', row.original.slug),
        }),
    },
];

const props = defineProps<{ assets: Asset[] }>();
const columnFilters = ref<ColumnFiltersState>([]);

const table = useVueTable({
    get data() { return props.assets; },
    get columns() { return columns; },
    getCoreRowModel: getCoreRowModel(),
    getPaginationRowModel: getPaginationRowModel(),
    onColumnFiltersChange: (u) => valueUpdater(u, columnFilters),
    getFilteredRowModel: getFilteredRowModel(),
    state: { get columnFilters() { return columnFilters.value; } },
});
</script>

<template>
    <Head title="TOS Assets — Admin" />
    <div class="container mx-auto mt-6 h-full px-2">
        <div class="flex items-center justify-between py-4">
            <Input class="max-w-sm" placeholder="Filter by name" :model-value="table.getColumn('name')?.getFilterValue() as string" @update:model-value="table.getColumn('name')?.setFilterValue($event)" />
            <Button @click="router.get(route('admin.tos.assets.create'))">Create Asset</Button>
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
