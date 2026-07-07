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

interface AdvancementAbilityRow {
    id: number;
    flip_value: number | null;
    is_joker: boolean;
    is_always_available: boolean;
    talent_name: string;
    ability_id: number | null;
    ability: { id: number; name: string } | null;
}

const valueLabel = (r: AdvancementAbilityRow): string => {
    if (r.is_joker) return 'Any Joker';
    if (r.is_always_available) return 'Always';
    return r.flip_value?.toString() ?? '—';
};

const globalSearchFilter: FilterFn<AdvancementAbilityRow> = (row, _columnId, filterValue) => {
    const search = (filterValue as string).toLowerCase();
    const talentName = row.original.talent_name.toLowerCase();
    const abilityName = row.original.ability?.name.toLowerCase() ?? '';
    return talentName.includes(search) || abilityName.includes(search);
};

const columns: ColumnDef<AdvancementAbilityRow>[] = [
    {
        id: 'value',
        header: () => h('div', {}, 'Value'),
        cell: ({ row }) => h('div', { class: 'tabular-nums' }, valueLabel(row.original)),
    },
    {
        accessorKey: 'talent_name',
        header: () => h('div', {}, 'Talent Name'),
        cell: ({ row }) => h('div', { class: 'font-medium' }, row.getValue('talent_name')),
    },
    {
        id: 'ability',
        header: () => h('div', {}, 'Ability Lookup'),
        cell: ({ row }) => {
            const ability = row.original.ability;
            return ability
                ? h(Badge, { variant: 'outline', class: 'text-[10px]' }, () => ability.name)
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
                name: item.talent_name,
                editRoute: route('admin.campaign.advancement-ability.edit', item.id),
                deleteRoute: route('admin.campaign.advancement-ability.delete', item.id),
            });
        },
    },
];

const props = defineProps<{
    items: AdvancementAbilityRow[];
}>();

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
    <Head title="Campaign — Ability Advancement — Admin" />
    <div class="container mx-auto mt-6 h-full px-2">
        <div class="flex items-center justify-between py-4">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Ability Advancements</h1>
                <p class="text-sm text-muted-foreground">Index of the Untold leader advancement table — flip &lt;= value picks an option.</p>
            </div>
            <Button @click="router.get(route('admin.campaign.advancement-ability.create'))">Create</Button>
        </div>
        <div class="flex items-center justify-between py-2">
            <Input
                class="max-w-sm"
                placeholder="Filter by talent or ability name..."
                :model-value="globalFilter"
                @update:model-value="table.setGlobalFilter($event)"
            />
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
