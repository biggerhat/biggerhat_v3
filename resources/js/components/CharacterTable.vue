<script setup lang="ts">
import { h, ref } from 'vue';
import type { ColumnDef, ColumnFiltersState } from '@tanstack/vue-table';
import { valueUpdater } from '@/lib/utils'

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
import CharacterTable from "@/components/CharacterTable.vue";
import CharacterTableLink from "@/components/CharacterTableLink.vue";

const columns: ColumnDef<Miniatures>[] = [
    {
        accessorKey: 'display_name',
        header: () => h('div', {}, 'Name'),
        cell: ({ row }) => {
            const character = row.original;
            const href = route('characters.view', {character: character.slug, miniature: character.miniatures[0].id, slug: character.miniatures[0].slug });
            return h('div', { class: 'w-auto' }, h(CharacterTableLink, {character: character}))
        },
    },{
        accessorKey: 'cost',
        header: () => h('div', { class: 'text-center' }, 'Cost'),
        cell: ({ row }) => {
            return h('div', { class: 'text-center' }, row.getValue('cost'))
        },
    },{
        accessorKey: 'health',
        header: () => h('div', { class: 'text-center' }, 'Health'),
        cell: ({ row }) => {
            return h('div', { class: 'text-center' }, row.getValue('health'))
        },
    },{
        accessorKey: 'speed',
        header: () => h('div', { class: 'text-center' }, 'Speed'),
        cell: ({ row }) => {
            return h('div', { class: 'text-center' }, row.getValue('speed'))
        },
    },{
        accessorKey: 'defense',
        header: () => h('div', { class: 'text-center' }, 'Defense'),
        cell: ({ row }) => {
            return h('div', { class: 'text-center' }, row.getValue('defense'))
        },
    },{
        accessorKey: 'willpower',
        header: () => h('div', { class: 'text-center' }, 'Willpower'),
        cell: ({ row }) => {
            return h('div', { class: 'text-center' }, row.getValue('willpower'))
        },
    },{
        accessorKey: 'size',
        header: () => h('div', { class: 'text-center' }, 'Size'),
        cell: ({ row }) => {
            return h('div', { class: 'text-center' }, row.getValue('size'))
        },
    },
];

const props = defineProps<{
    characters: TData[]
}>();

const columnFilters = ref<ColumnFiltersState>([])

const table = useVueTable({
    get data() { return props.characters },
    get columns() { return columns },
    getCoreRowModel: getCoreRowModel(),
    // getPaginationRowModel: getPaginationRowModel(),
    onColumnFiltersChange: updaterOrValue => valueUpdater(updaterOrValue, columnFilters),
    getFilteredRowModel: getFilteredRowModel(),
    state: {
        get columnFilters() { return columnFilters.value },
    }
});
</script>

<template>
    <div class="mx-auto">
<!--        <div class="flex items-center justify-between py-4">-->
<!--            <Input class="max-w-sm" placeholder="Filter Miniatures"-->
<!--                   :model-value="table.getColumn('display_name')?.getFilterValue() as string"-->
<!--                   @update:model-value=" table.getColumn('display_name')?.setFilterValue($event)" />-->
<!--            <Button @click="router.get(route('admin.miniatures.create'))">-->
<!--                Create New Miniature-->
<!--            </Button>-->
<!--        </div>-->
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
</template>
