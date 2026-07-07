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

interface EventRow {
    id: number;
    name: string;
    flip_value: number | null;
    is_black_joker: boolean;
    is_red_joker: boolean;
    requires_placement: boolean;
    is_one_time: boolean;
}

const flipLabel = (r: EventRow): string => {
    if (r.is_black_joker) return 'Black Joker';
    if (r.is_red_joker) return 'Red Joker';
    return String(r.flip_value ?? '—');
};

const globalSearchFilter: FilterFn<EventRow> = (row, _columnId, filterValue) => {
    return row.original.name.toLowerCase().includes((filterValue as string).toLowerCase());
};

const columns: ColumnDef<EventRow>[] = [
    {
        accessorKey: 'name',
        header: () => h('div', {}, 'Name'),
        cell: ({ row }) => h('div', { class: 'font-medium' }, row.getValue('name')),
    },
    {
        id: 'flip',
        header: () => h('div', {}, 'Flip'),
        cell: ({ row }) => h('div', { class: 'tabular-nums' }, flipLabel(row.original)),
    },
    {
        id: 'flags',
        header: () => h('div', {}, 'Flags'),
        cell: ({ row }) => {
            const badges = [];
            if (row.original.requires_placement) {
                badges.push(h(Badge, { variant: 'outline', class: 'mr-1 text-[10px]' }, () => 'Placement'));
            }
            if (row.original.is_one_time) {
                badges.push(h(Badge, { variant: 'destructive', class: 'text-[10px]' }, () => 'One-time'));
            }
            return h('div', {}, badges);
        },
    },
    {
        id: 'actions',
        enableHiding: false,
        header: () => h('div', {}, 'Actions'),
        cell: ({ row }) => {
            const item = row.original;
            return h(AdminActions, {
                name: item.name,
                editRoute: route('admin.campaign.weekly-events.edit', item.id),
                deleteRoute: route('admin.campaign.weekly-events.delete', item.id),
            });
        },
    },
];

const props = defineProps<{ items: EventRow[] }>();

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
    <Head title="Campaign Weekly Events — Admin" />
    <div class="container mx-auto mt-6 h-full px-2">
        <div class="flex items-center justify-between py-4">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Weekly Events</h1>
                <p class="text-sm text-muted-foreground">Pg 148–149. Rolled at week start when weekly events are enabled.</p>
            </div>
            <Button @click="router.get(route('admin.campaign.weekly-events.create'))">Create</Button>
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
