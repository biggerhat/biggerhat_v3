<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { h, ref } from 'vue';
import type { ColumnDef, ColumnFiltersState } from '@tanstack/vue-table';
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { valueUpdater } from '@/lib/utils'
import AdminActions from '@/components/AdminActions.vue';
import { Check, Ban } from 'lucide-vue-next';

import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';

import {
    FlexRender,
    getCoreRowModel,
    getPaginationRowModel,
    getFilteredRowModel,
    useVueTable,
} from '@tanstack/vue-table';

const columns: ColumnDef<Upgrades>[] = [
    {
        accessorKey: 'name',
        header: () => h('div', {}, 'Upgrade'),
        cell: ({ row }) => {
            return h('div', {}, row.getValue('name'))
        },
    },{
        accessorKey: 'front_image',
        header: () => h('div', {}, 'Front Image'),
        cell: ({ row }) => {
            return h('div', {}, row.getValue('front_image') ? h(Check) : h(Ban))
        },
    },{
        accessorKey: 'back_image',
        header: () => h('div', {}, 'Back Image'),
        cell: ({ row }) => {
            return h('div', {}, row.getValue('back_image') ? h(Check) : h(Ban))
        },
    },{
        id: 'actions',
        enableHiding: false,
        header: () => h('div', {}, 'Actions'),
        cell: ({ row }) => {
            const upgrade = row.original;

            return h('div', { class: 'relative' }, h(AdminActions, { name: upgrade.name, editRoute: route('admin.upgrades.edit', upgrade.slug), deleteRoute: route('admin.upgrades.delete', upgrade.slug) }))
        },
    },
];

const props = defineProps<{
    upgrades: TData[]
}>();

const columnFilters = ref<ColumnFiltersState>([])

const table = useVueTable({
    get data() { return props.upgrades },
    get columns() { return columns },
    getCoreRowModel: getCoreRowModel(),
    getPaginationRowModel: getPaginationRowModel(),
    onColumnFiltersChange: updaterOrValue => valueUpdater(updaterOrValue, columnFilters),
    getFilteredRowModel: getFilteredRowModel(),
    state: {
        get columnFilters() { return columnFilters.value },
    }
});
</script>

<template>
    <Head title="Upgrades - Admin" />

    <div class="container mx-auto mt-6 h-full px-2">
        <div class="flex items-center justify-between py-4">
            <Input class="max-w-sm" placeholder="Filter Upgrades"
                   :model-value="table.getColumn('name')?.getFilterValue() as string"
                   @update:model-value=" table.getColumn('name')?.setFilterValue($event)" />
            <Button @click="router.get(route('admin.upgrades.create'))">
                Create New Upgrade
            </Button>
        </div>
        <div class="border rounded-md">
            <Table>
                <TableHeader>
                    <TableRow v-for="headerGroup in table.getHeaderGroups()" :key="headerGroup.id">
                        <TableHead v-for="header in headerGroup.headers" :key="header.id">
                            <FlexRender
                                v-if="!header.isPlaceholder" :render="header.column.columnDef.header"
                                :props="header.getContext()"
                            />
                        </TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <template v-if="table.getRowModel().rows?.length">
                        <TableRow
                            v-for="row in table.getRowModel().rows" :key="row.id"
                            :data-state="row.getIsSelected() ? 'selected' : undefined"
                        >
                            <TableCell v-for="cell in row.getVisibleCells()" :key="cell.id">
                                <FlexRender :render="cell.column.columnDef.cell" :props="cell.getContext()" />
                            </TableCell>
                        </TableRow>
                    </template>
                    <template v-else>
                        <TableRow>
                            <TableCell :colspan="columns.length" class="h-24 text-center">
                                No results.
                            </TableCell>
                        </TableRow>
                    </template>
                </TableBody>
            </Table>
        </div>
        <div class="flex items-center justify-end py-4 space-x-2">
            <Button
                variant="outline"
                size="sm"
                :disabled="!table.getCanPreviousPage()"
                @click="table.previousPage()"
            >
                Previous
            </Button>
            <Button
                variant="outline"
                size="sm"
                :disabled="!table.getCanNextPage()"
                @click="table.nextPage()"
            >
                Next
            </Button>
        </div>
    </div>
</template>
