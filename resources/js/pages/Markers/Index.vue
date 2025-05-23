<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { h, ref } from 'vue';
import type { ColumnDef, ColumnFiltersState } from '@tanstack/vue-table';
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { valueUpdater } from '@/lib/utils'
import AdminActions from '@/components/AdminActions.vue';
import {Ban, Check} from "lucide-vue-next";
import KeywordTableLink from "@/components/KeywordTableLink.vue";

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

const columns: ColumnDef<Markers>[] = [
    {
        accessorKey: 'name',
        header: () => h('div', {}, 'Marker'),
        cell: ({ row }) => {
            return h('div', { class: 'w-auto' }, row.getValue('name'))
        },
    },{
        accessorKey: 'description',
        header: () => h('div', {}, 'Description'),
        cell: ({ row }) => {
            return h('div', {}, row.getValue('description'))
        }
    }
];

const props = defineProps<{
    markers: TData[]
}>();

const columnFilters = ref<ColumnFiltersState>([])

const table = useVueTable({
    get data() { return props.markers },
    get columns() { return columns },
    getCoreRowModel: getCoreRowModel(),
    onColumnFiltersChange: updaterOrValue => valueUpdater(updaterOrValue, columnFilters),
    getFilteredRowModel: getFilteredRowModel(),
    state: {
        get columnFilters() { return columnFilters.value },
    }
});
</script>

<template>
    <Head title="Markers" />
    <div class="w-full h-full">
        <div class="flex w-full bg-secondary">
            <div class="container mx-auto items-center">
                <div class="flex justify-between">
                    <div class="py-1 md:py-4 flex w-full">
                        <div class="flex justify-between w-full md:block" id="page-banner">
                            <div class="p-2 font-bold text-xl my-auto">Marker Directory</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="container mx-auto mt-6">
            <div class="flex items-center justify-between py-4">
                <Input class="max-w-sm" placeholder="Filter Markers"
                       :model-value="table.getColumn('name')?.getFilterValue() as string"
                       @update:model-value=" table.getColumn('name')?.setFilterValue($event)" />
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
        </div>
    </div>
</template>
