<script setup lang="ts">
import AdminActions from '@/components/AdminActions.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { valueUpdater } from '@/lib/utils';
import { Head, router } from '@inertiajs/vue3';
import type { ColumnDef, ColumnFiltersState } from '@tanstack/vue-table';
import { h, ref, watch } from 'vue';

import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';

import { FlexRender, getCoreRowModel, getFilteredRowModel, getPaginationRowModel, useVueTable } from '@tanstack/vue-table';

const columns: ColumnDef<Abilities>[] = [
    {
        accessorKey: 'id',
        header: () => h('div', {}, 'ID'),
        cell: ({ row }) => {
            return h('div', {}, row.getValue('id'));
        },
    },
    {
        accessorKey: 'name',
        header: () => h('div', {}, 'Ability'),
        cell: ({ row }) => {
            return h('div', {}, row.getValue('name'));
        },
    },
    {
        accessorKey: 'suits',
        header: () => h('div', {}, 'Suits'),
        cell: ({ row }) => {
            return h('div', {}, row.getValue('suits') ?? '-');
        },
    },
    {
        accessorKey: 'defensive_ability_type',
        header: () => h('div', {}, 'Defensive Type'),
        cell: ({ row }) => {
            return h('div', {}, row.getValue('defensive_ability_type') ?? '-');
        },
    },
    {
        accessorKey: 'game_mode_type',
        header: () => h('div', {}, 'Game Mode'),
        cell: ({ row }) => {
            return h('div', { class: 'capitalize' }, row.getValue('game_mode_type') ?? 'standard');
        },
        filterFn: 'equalsString',
    },
    {
        accessorKey: 'costs_stone',
        header: () => h('div', {}, 'Costs Stone'),
        cell: ({ row }) => {
            return h('div', {}, row.getValue('costs_stone') ? 'Yes' : 'No');
        },
    },
    {
        id: 'actions',
        enableHiding: false,
        header: () => h('div', {}, 'Actions'),
        cell: ({ row }) => {
            const ability = row.original;

            return h(
                'div',
                { class: 'relative' },
                h(AdminActions, {
                    name: ability.name,
                    editRoute: route('admin.abilities.edit', ability.slug),
                    deleteRoute: route('admin.abilities.delete', ability.slug),
                }),
            );
        },
    },
];

const props = defineProps<{
    abilities: TData[];
}>();

const columnFilters = ref<ColumnFiltersState>([]);
const gameModeFilter = ref('all');

const table = useVueTable({
    get data() {
        return props.abilities;
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

watch(gameModeFilter, (val) => {
    table.getColumn('game_mode_type')?.setFilterValue(val === 'all' ? undefined : val);
});
</script>

<template>
    <Head title="Abilities - Admin" />

    <div class="container mx-auto mt-6 h-full px-2">
        <div class="flex items-center justify-between py-4">
            <div class="flex items-center gap-2">
                <Input
                    class="max-w-sm"
                    placeholder="Filter Abilities"
                    :model-value="table.getColumn('name')?.getFilterValue() as string"
                    @update:model-value="table.getColumn('name')?.setFilterValue($event)"
                />
                <Select v-model="gameModeFilter">
                    <SelectTrigger class="w-[160px]">
                        <SelectValue placeholder="Game Mode" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">All Modes</SelectItem>
                        <SelectItem value="standard">Standard</SelectItem>
                        <SelectItem value="campaign">Campaign</SelectItem>
                        <SelectItem value="cooperative">Cooperative</SelectItem>
                        <SelectItem value="custom">Custom</SelectItem>
                    </SelectContent>
                </Select>
            </div>
            <div>Total {{ table.getFilteredRowModel().rows.length }}</div>
            <Button @click="router.get(route('admin.abilities.create'))"> Create New Ability </Button>
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
