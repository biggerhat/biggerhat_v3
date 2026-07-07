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

interface AdvancementRow {
    id: number;
    name: string;
    flip_value: number | null;
    is_black_joker: boolean;
    is_red_joker: boolean;
    is_always_available: boolean;
    modifier_type: string;
    suit: string | null;
    skl_from: number | null;
    skl_from_max: number | null;
    skl_to: number | null;
    trigger_id: number | null;
}

const props = defineProps<{
    items: AdvancementRow[];
    route_prefix: string;
    display_label: string;
}>();

const valueLabel = (r: AdvancementRow): string => {
    if (r.is_black_joker && r.is_red_joker) return 'Any Joker';
    if (r.is_black_joker) return 'Black Joker';
    if (r.is_red_joker) return 'Red Joker';
    if (r.is_always_available) return 'Always';
    return r.flip_value?.toString() ?? '—';
};

const modifierLabel: Record<string, string> = {
    trigger: 'Trigger',
    skl_boost: 'Skl Boost',
    signature: 'Signature',
};

// Suit only applies to trigger rows; skl_boost rows show their qualifying
// Skl range (or exact value) here instead — the two are mutually exclusive.
const suitOrSklCell = (r: AdvancementRow): string => {
    if (r.modifier_type !== 'skl_boost') return r.suit ?? '—';
    if (r.skl_from == null) return '—';
    const range = r.skl_from_max != null && r.skl_from_max !== r.skl_from ? `${r.skl_from}–${r.skl_from_max}` : `${r.skl_from}`;

    return `Skl ${range} → ${r.skl_to ?? '?'}`;
};

const globalSearchFilter: FilterFn<AdvancementRow> = (row, _columnId, filterValue) => {
    const search = (filterValue as string).toLowerCase();
    return row.original.name.toLowerCase().includes(search);
};

const columns: ColumnDef<AdvancementRow>[] = [
    {
        id: 'value',
        header: () => h('div', {}, 'Value'),
        cell: ({ row }) => h('div', { class: 'tabular-nums' }, valueLabel(row.original)),
    },
    {
        accessorKey: 'name',
        header: () => h('div', {}, 'Name'),
        cell: ({ row }) => h('div', { class: 'font-medium' }, row.getValue('name')),
    },
    {
        accessorKey: 'modifier_type',
        header: () => h('div', {}, 'Type'),
        cell: ({ row }) => h('div', { class: 'text-xs' }, modifierLabel[row.original.modifier_type] ?? row.original.modifier_type),
        filterFn: 'equalsString',
    },
    {
        id: 'suit_or_skl',
        header: () => h('div', {}, 'Suit / Skl'),
        cell: ({ row }) => h('div', { class: 'text-xs' }, suitOrSklCell(row.original)),
    },
    {
        id: 'trigger',
        header: () => h('div', {}, 'Trigger Lookup'),
        cell: ({ row }) => {
            const triggerId = row.original.trigger_id;
            return triggerId
                ? h(Badge, { variant: 'outline', class: 'text-[10px]' }, () => `#${triggerId}`)
                : h('span', { class: 'text-xs text-muted-foreground' }, 'bespoke');
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
                editRoute: route(`${props.route_prefix}.edit`, item.id),
                deleteRoute: route(`${props.route_prefix}.delete`, item.id),
            });
        },
    },
];

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
    <Head :title="`Campaign — ${display_label} Advancement — Admin`" />
    <div class="container mx-auto mt-6 h-full px-2">
        <div class="flex items-center justify-between py-4">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">{{ display_label }} Advancements</h1>
                <p class="text-sm text-muted-foreground">Index of the Untold leader advancement table — flip &lt;= value picks an option.</p>
            </div>
            <Button @click="router.get(route(`${route_prefix}.create`))">Create</Button>
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
