<script setup lang="ts">
import AdminActions from '@/components/AdminActions.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { imageSrc } from '@/composables/useBlueprintImages';
import { valueUpdater } from '@/lib/utils';
import { Head, router } from '@inertiajs/vue3';
import type { ColumnDef, FilterFn, SortingState } from '@tanstack/vue-table';
import { computed, h, ref } from 'vue';

import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';

import { FlexRender, getCoreRowModel, getFilteredRowModel, getPaginationRowModel, getSortedRowModel, useVueTable } from '@tanstack/vue-table';

const previewImage = ref<{ src: string; name: string } | null>(null);
const showUnattachedOnly = ref(false);
const searchText = ref('');

const globalSearchFilter: FilterFn<any> = (row, _columnId, filterValue) => {
    const { search, unattachedOnly } = filterValue as { search: string; unattachedOnly: boolean };
    const term = search.toLowerCase();
    const name = (row.getValue('name') as string)?.toLowerCase() ?? '';
    const characters = (row.original.characters ?? []).map((c: any) => (c.display_name as string)?.toLowerCase() ?? '');
    const miniatures = (row.original.miniatures ?? []).map((m: any) => (m.display_name as string)?.toLowerCase() ?? '');

    const matchesSearch = !term || name.includes(term) || characters.some((n: string) => n.includes(term)) || miniatures.some((n: string) => n.includes(term));

    if (unattachedOnly) {
        return matchesSearch && row.original.characters_count === 0 && row.original.miniatures_count === 0;
    }

    return matchesSearch;
};

const columns: ColumnDef<any>[] = [
    {
        id: 'image',
        header: () => h('div', {}, ''),
        enableSorting: false,
        cell: ({ row }) => {
            const path = row.original.image_path;
            if (!path) return h('div', { class: 'h-10 w-14 rounded bg-muted' });
            return h('img', {
                src: imageSrc(path),
                alt: row.getValue('name'),
                class: 'h-10 w-14 cursor-pointer rounded object-contain transition-opacity hover:opacity-70',
                loading: 'lazy',
                onClick: () => {
                    previewImage.value = { src: imageSrc(path), name: row.getValue('name') as string };
                },
            });
        },
    },
    {
        accessorKey: 'name',
        header: () => h('div', {}, 'Blueprint'),
        cell: ({ row }) => h('div', { class: 'font-medium' }, row.getValue('name')),
    },
    {
        accessorKey: 'sculpt_version',
        header: () => h('div', {}, 'Edition'),
        cell: ({ row }) => {
            const version = row.getValue('sculpt_version') as string;
            return h('div', { class: 'text-sm text-muted-foreground capitalize' }, version?.replace(/_/g, ' ') ?? '-');
        },
    },
    {
        accessorKey: 'characters_count',
        header: () => h('div', {}, 'Characters'),
        cell: ({ row }) => {
            const count = row.getValue('characters_count') as number;
            const chars = row.original.characters ?? [];
            if (!count) return h('span', { class: 'text-sm text-muted-foreground' }, '0');
            return h(
                'div',
                { class: 'flex flex-wrap gap-1' },
                chars.map((c: any) => h('span', { class: 'inline-block rounded bg-secondary px-1.5 py-0.5 text-xs' }, c.display_name)),
            );
        },
    },
    {
        accessorKey: 'miniatures_count',
        header: () => h('div', {}, 'Miniatures'),
        cell: ({ row }) => {
            const count = row.getValue('miniatures_count') as number;
            const minis = row.original.miniatures ?? [];
            if (!count) return h('span', { class: 'text-sm text-muted-foreground' }, '0');
            return h(
                'div',
                { class: 'flex flex-wrap gap-1' },
                minis.map((m: any) => h('span', { class: 'inline-block rounded bg-secondary px-1.5 py-0.5 text-xs' }, m.display_name)),
            );
        },
    },
    {
        id: 'actions',
        enableHiding: false,
        enableSorting: false,
        header: () => h('div', {}, 'Actions'),
        cell: ({ row }) => {
            const blueprint = row.original;
            return h(
                'div',
                { class: 'relative' },
                h(AdminActions, {
                    name: blueprint.name,
                    editRoute: route('admin.blueprints.edit', blueprint.id),
                    deleteRoute: route('admin.blueprints.delete', blueprint.id),
                }),
            );
        },
    },
];

const props = defineProps<{
    blueprints: any[];
}>();

const sorting = ref<SortingState>([]);

const globalFilterValue = computed(() => ({ search: searchText.value, unattachedOnly: showUnattachedOnly.value }));

const table = useVueTable({
    get data() {
        return props.blueprints;
    },
    get columns() {
        return columns;
    },
    getCoreRowModel: getCoreRowModel(),
    getPaginationRowModel: getPaginationRowModel(),
    getFilteredRowModel: getFilteredRowModel(),
    getSortedRowModel: getSortedRowModel(),
    globalFilterFn: globalSearchFilter,
    onSortingChange: (updaterOrValue) => valueUpdater(updaterOrValue, sorting),
    state: {
        get globalFilter() {
            return globalFilterValue.value;
        },
        get sorting() {
            return sorting.value;
        },
    },
    initialState: {
        pagination: {
            pageSize: 50,
        },
    },
});
</script>

<template>
    <Head title="Blueprints - Admin" />

    <div class="container mx-auto mt-6 h-full px-2">
        <div class="flex items-center justify-between py-4">
            <div class="flex items-center gap-4">
                <Input
                    class="max-w-sm"
                    placeholder="Filter by name, character, or miniature..."
                    :model-value="searchText"
                    @update:model-value="searchText = $event"
                />
                <label class="flex cursor-pointer items-center gap-2 text-sm whitespace-nowrap">
                    <Checkbox :checked="showUnattachedOnly" @update:checked="(val: boolean) => (showUnattachedOnly = val)" />
                    Unattached only
                </label>
            </div>
            <div>Total {{ table.getFilteredRowModel().rows.length }}</div>
            <Button @click="router.get(route('admin.blueprints.create'))"> Create New Blueprint </Button>
        </div>
        <div class="rounded-md border">
            <Table>
                <TableHeader>
                    <TableRow v-for="headerGroup in table.getHeaderGroups()" :key="headerGroup.id">
                        <TableHead
                            v-for="header in headerGroup.headers"
                            :key="header.id"
                            :class="{ 'cursor-pointer select-none': header.column.getCanSort() }"
                            @click="header.column.getToggleSortingHandler()?.($event)"
                        >
                            <div class="flex items-center gap-1">
                                <FlexRender v-if="!header.isPlaceholder" :render="header.column.columnDef.header" :props="header.getContext()" />
                                <span v-if="header.column.getIsSorted() === 'asc'">↑</span>
                                <span v-else-if="header.column.getIsSorted() === 'desc'">↓</span>
                            </div>
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

    <!-- Image preview dialog -->
    <Dialog
        :open="!!previewImage"
        @update:open="
            (open: boolean) => {
                if (!open) previewImage = null;
            }
        "
    >
        <DialogContent class="max-h-[90vh] max-w-4xl overflow-y-auto">
            <DialogTitle class="text-lg font-semibold">{{ previewImage?.name }}</DialogTitle>
            <DialogDescription class="sr-only">Blueprint image preview</DialogDescription>
            <img v-if="previewImage" :src="previewImage.src" :alt="previewImage.name" class="mt-2 w-full rounded-lg border" />
        </DialogContent>
    </Dialog>
</template>
