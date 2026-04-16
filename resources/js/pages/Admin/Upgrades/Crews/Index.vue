<script setup lang="ts">
import AdminActions from '@/components/AdminActions.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { valueUpdater } from '@/lib/utils';
import { Head, router } from '@inertiajs/vue3';
import type { ColumnDef, ColumnFiltersState } from '@tanstack/vue-table';
import { Ban, Check } from 'lucide-vue-next';
import { h, ref, watch } from 'vue';

import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';

import { FlexRender, getCoreRowModel, getFilteredRowModel, getPaginationRowModel, useVueTable } from '@tanstack/vue-table';

const columns: ColumnDef<Upgrades>[] = [
    {
        accessorKey: 'name',
        header: () => h('div', {}, 'Crew Card'),
        cell: ({ row }) => {
            return h('div', {}, row.getValue('name'));
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
        accessorKey: 'front_image',
        header: () => h('div', {}, 'Front Image'),
        cell: ({ row }) => {
            return h('div', {}, row.getValue('front_image') ? h(Check) : h(Ban));
        },
    },
    {
        accessorKey: 'back_image',
        header: () => h('div', {}, 'Back Image'),
        cell: ({ row }) => {
            return h('div', {}, row.getValue('back_image') ? h(Check) : h(Ban));
        },
    },
    {
        id: 'characters',
        header: () => h('div', {}, 'Linked Characters'),
        cell: ({ row }) => {
            const characters = row.original.characters ?? [];
            if (characters.length === 0) return h('span', { class: 'text-muted-foreground' }, '—');
            return h(
                'div',
                { class: 'flex flex-wrap gap-1' },
                characters.map((c: { display_name: string }) => h('span', { class: 'rounded bg-muted px-1.5 py-0.5 text-xs' }, c.display_name)),
            );
        },
    },
    {
        id: 'actions',
        enableHiding: false,
        header: () => h('div', {}, 'Actions'),
        cell: ({ row }) => {
            const crew = row.original;

            return h(
                'div',
                { class: 'relative' },
                h(AdminActions, {
                    name: crew.name,
                    editRoute: route('admin.crews.edit', crew.slug),
                    deleteRoute: route('admin.crews.delete', crew.slug),
                }),
            );
        },
    },
];

const props = defineProps<{
    upgrades: TData[];
}>();

const columnFilters = ref<ColumnFiltersState>([]);
const gameModeFilter = ref('all');

const table = useVueTable({
    get data() {
        return props.upgrades;
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
    <Head title="Crew Cards - Admin" />

    <div class="container mx-auto mt-6 h-full px-2">
        <div class="flex items-center justify-between py-4">
            <div class="flex items-center gap-2">
                <Input
                    class="max-w-sm"
                    placeholder="Filter Crew Cards"
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
            <Button @click="router.get(route('admin.crews.create'))"> Create New Crew Card </Button>
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
