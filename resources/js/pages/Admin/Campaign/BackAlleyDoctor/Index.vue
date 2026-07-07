<script setup lang="ts">
import AdminActions from '@/components/AdminActions.vue';
import EmptyState from '@/components/EmptyState.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { valueUpdater } from '@/lib/utils';
import { Head, router } from '@inertiajs/vue3';
import type { ColumnDef, FilterFn } from '@tanstack/vue-table';
import { FlexRender, getCoreRowModel, getFilteredRowModel, getPaginationRowModel, useVueTable } from '@tanstack/vue-table';
import { h, ref } from 'vue';

interface DoctorRow {
    id: number;
    name: string;
    flip_value_min: number | null;
    flip_value_max: number | null;
    is_black_joker: boolean;
    is_red_joker: boolean;
    outcome_kind: string;
}

const rangeLabel = (r: DoctorRow): string => {
    if (r.is_black_joker) return 'Black Joker';
    if (r.is_red_joker) return 'Red Joker';
    if (r.flip_value_min === r.flip_value_max) return String(r.flip_value_min ?? '');
    return `${r.flip_value_min ?? ''}–${r.flip_value_max ?? ''}`;
};

const globalSearchFilter: FilterFn<DoctorRow> = (row, _columnId, filterValue) => {
    return row.original.name.toLowerCase().includes((filterValue as string).toLowerCase());
};

const columns: ColumnDef<DoctorRow>[] = [
    {
        accessorKey: 'name',
        header: () => h('div', {}, 'Name'),
        cell: ({ row }) => h('div', { class: 'font-medium' }, row.getValue('name')),
    },
    {
        id: 'range',
        header: () => h('div', {}, 'Range'),
        cell: ({ row }) => h('div', { class: 'tabular-nums' }, rangeLabel(row.original)),
    },
    {
        accessorKey: 'outcome_kind',
        header: () => h('div', {}, 'Outcome'),
        cell: ({ row }) => h(Badge, { variant: 'outline', class: 'text-[10px]' }, () => row.original.outcome_kind),
    },
    {
        id: 'actions',
        enableHiding: false,
        header: () => h('div', {}, 'Actions'),
        cell: ({ row }) => {
            const item = row.original;
            return h(AdminActions, {
                name: item.name,
                editRoute: route('admin.campaign.back-alley-doctor.edit', item.id),
                deleteRoute: route('admin.campaign.back-alley-doctor.delete', item.id),
            });
        },
    },
];

const props = defineProps<{ items: DoctorRow[] }>();

const globalFilter = ref('');

const table = useVueTable({
    get data() {
        return props.items;
    },
    get columns() {
        return columns;
    },
    getCoreRowModel: getCoreRowModel(),
    getPaginationRowModel: getPaginationRowModel(),
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
    <Head title="Campaign Back-Alley Doctor — Admin" />
    <div class="container mx-auto mt-6 h-full px-2">
        <div class="flex items-center justify-between py-4">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Back-Alley Doctor Results</h1>
                <p class="text-sm text-muted-foreground">Pg 33. Outcomes for the Phase 5 injury-removal flip.</p>
            </div>
            <Button @click="router.get(route('admin.campaign.back-alley-doctor.create'))">Create</Button>
        </div>
        <div class="flex items-center justify-between py-2">
            <Input class="max-w-sm" placeholder="Filter by name..." :model-value="globalFilter" @update:model-value="table.setGlobalFilter($event)" />
            <div class="text-sm text-muted-foreground">Total {{ table.getFilteredRowModel().rows.length }}</div>
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
                        <TableRow v-for="row in table.getRowModel().rows" :key="row.id">
                            <TableCell v-for="cell in row.getVisibleCells()" :key="cell.id">
                                <FlexRender :render="cell.column.columnDef.cell" :props="cell.getContext()" />
                            </TableCell>
                        </TableRow>
                    </template>
                    <TableRow v-else>
                        <TableCell :colspan="columns.length">
                            <EmptyState compact title="No rows yet" description="Use Create to seed from the rulebook." />
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>
        <div class="flex items-center justify-end space-x-2 py-4">
            <Button variant="outline" size="sm" :disabled="!table.getCanPreviousPage()" @click="table.previousPage()">Previous</Button>
            <Button variant="outline" size="sm" :disabled="!table.getCanNextPage()" @click="table.nextPage()">Next</Button>
        </div>
    </div>
</template>
