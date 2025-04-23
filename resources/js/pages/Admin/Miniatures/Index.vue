<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { h, ref } from 'vue';
import type { ColumnDef, ColumnFiltersState } from '@tanstack/vue-table';
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { valueUpdater } from '@/lib/utils'
import AdminActions from '@/components/AdminActions.vue';

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
import {Ban, Check} from "lucide-vue-next";

const columns: ColumnDef<Miniatures>[] = [
    {
        accessorKey: 'id',
        header: () => h('div', {}, 'ID'),
        cell: ({ row }) => {
            return h('div', {}, row.getValue('id'))
        },
    },{
        accessorKey: 'display_name',
        header: () => h('div', {}, 'Miniature'),
        cell: ({ row }) => {
            return h('div', {}, row.getValue('display_name'))
        },
    },{
        accessorKey: 'character_name',
        header: () => h('div', {}, 'Character'),
        cell: ({ row }) => {
            return h('div', {}, row.getValue('character_name'))
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
            const miniature = row.original;

            return h('div', { class: 'relative' }, h(AdminActions, { name: miniature.display_name, editRoute: route('admin.miniatures.edit', miniature.slug), deleteRoute: route('admin.miniatures.delete', miniature.slug) }))
        },
    },
];

const props = defineProps<{
    miniatures: TData[]
}>();

const columnFilters = ref<ColumnFiltersState>([])

const table = useVueTable({
    get data() { return props.miniatures },
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
    <Head title="Miniatures - Admin" />

    <div class="container mx-auto mt-6">
        <div class="flex items-center justify-between py-4">
            <Input class="max-w-sm" placeholder="Filter Miniatures"
                   :model-value="table.getColumn('display_name')?.getFilterValue() as string"
                   @update:model-value=" table.getColumn('display_name')?.setFilterValue($event)" />
            <Button @click="router.get(route('admin.miniatures.create'))">
                Create New Miniature
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
