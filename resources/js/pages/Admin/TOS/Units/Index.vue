<script setup lang="ts">
import AdminActions from '@/components/AdminActions.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { valueUpdater } from '@/lib/utils';
import { Head, router } from '@inertiajs/vue3';
import { FlexRender, getCoreRowModel, getFilteredRowModel, getPaginationRowModel, useVueTable } from '@tanstack/vue-table';
import type { ColumnDef, ColumnFiltersState } from '@tanstack/vue-table';
import { h, ref, watch } from 'vue';

interface SpecialRule {
    id: number;
    slug: string;
    name: string;
}

interface Unit {
    id: number;
    slug: string;
    name: string;
    title: string | null;
    scrip: number;
    tactics: string | null;
    glory_tactics: string | null;
    sides: Array<{ id: number; side: string }>;
    allegiances: Array<{ id: number; name: string }>;
    special_unit_rules: SpecialRule[];
}

const columns: ColumnDef<Unit>[] = [
    {
        accessorKey: 'name',
        header: () => h('div', {}, 'Name'),
        cell: ({ row }) => h('div', { class: 'font-medium' }, row.getValue('name')),
    },
    {
        accessorKey: 'scrip',
        header: () => h('div', {}, 'Scrip'),
        cell: ({ row }) => h('div', { class: 'tabular-nums' }, row.getValue('scrip')),
    },
    {
        id: 'rules',
        accessorFn: (row) => row.special_unit_rules.map((r) => r.slug).join(','),
        header: () => h('div', {}, 'Special Rules'),
        cell: ({ row }) => h('div', { class: 'flex flex-wrap gap-1 text-[11px]' }, row.original.special_unit_rules.map((r) => r.name).join(', ')),
        filterFn: (row, columnId, value) => {
            if (value === 'all') return true;
            return row.original.special_unit_rules.some((r) => r.slug === value);
        },
    },
    {
        id: 'allegiances',
        accessorFn: (row) => row.allegiances.map((a) => a.name).join(', '),
        header: () => h('div', {}, 'Allegiances'),
        cell: ({ row }) => h('div', { class: 'text-[11px] text-muted-foreground' }, row.original.allegiances.map((a) => a.name).join(', ')),
    },
    {
        id: 'sides',
        header: () => h('div', {}, 'Sides'),
        cell: ({ row }) => h('div', { class: 'text-[11px] text-muted-foreground' }, `${row.original.sides.length}/2`),
    },
    {
        id: 'actions',
        enableHiding: false,
        header: () => h('div', {}, 'Actions'),
        cell: ({ row }) => {
            const u = row.original;
            return h(
                'div',
                { class: 'relative' },
                h(AdminActions, {
                    name: u.name,
                    editRoute: route('admin.tos.units.edit', u.slug),
                    deleteRoute: route('admin.tos.units.delete', u.slug),
                }),
            );
        },
    },
];

const props = defineProps<{
    units: Unit[];
    special_rules: SpecialRule[];
}>();

const columnFilters = ref<ColumnFiltersState>([]);
const ruleFilter = ref('all');

const table = useVueTable({
    get data() {
        return props.units;
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

watch(ruleFilter, (val) => {
    table.getColumn('rules')?.setFilterValue(val);
});
</script>

<template>
    <Head title="TOS Units — Admin" />
    <div class="container mx-auto mt-6 h-full px-2">
        <div class="flex items-center justify-between py-4">
            <div class="flex items-center gap-2">
                <Input
                    class="max-w-sm"
                    placeholder="Filter by name"
                    :model-value="table.getColumn('name')?.getFilterValue() as string"
                    @update:model-value="table.getColumn('name')?.setFilterValue($event)"
                />
                <Select v-model="ruleFilter">
                    <SelectTrigger class="w-[180px]">
                        <SelectValue placeholder="Special Rule" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">All Rules</SelectItem>
                        <SelectItem v-for="r in special_rules" :key="r.slug" :value="r.slug">{{ r.name }}</SelectItem>
                    </SelectContent>
                </Select>
            </div>
            <div>Total {{ table.getFilteredRowModel().rows.length }}</div>
            <Button @click="router.get(route('admin.tos.units.create'))">Create Unit</Button>
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
                    <template v-else>
                        <TableRow>
                            <TableCell :colspan="columns.length" class="h-24 text-center">No results.</TableCell>
                        </TableRow>
                    </template>
                </TableBody>
            </Table>
        </div>
        <div class="flex items-center justify-end space-x-2 py-4">
            <Button variant="outline" size="sm" :disabled="!table.getCanPreviousPage()" @click="table.previousPage()">Previous</Button>
            <Button variant="outline" size="sm" :disabled="!table.getCanNextPage()" @click="table.nextPage()">Next</Button>
        </div>
    </div>
</template>
