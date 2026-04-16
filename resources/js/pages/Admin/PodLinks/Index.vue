<script setup lang="ts">
import AdminActions from '@/components/AdminActions.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { valueUpdater } from '@/lib/utils';
import { Head, router } from '@inertiajs/vue3';
import type { ColumnDef, ColumnFiltersState } from '@tanstack/vue-table';
import { FlexRender, getCoreRowModel, getFilteredRowModel, getPaginationRowModel, useVueTable } from '@tanstack/vue-table';
import { h, ref } from 'vue';

interface PodLink {
    id: number;
    name: string;
    slug: string;
    source: string;
    url: string;
}

const sourceLabel = (source: string) => {
    const map: Record<string, string> = { forgefire: 'ForgeFire', wargame_vault: 'Wargame Vault' };
    return map[source] ?? source;
};

const sourceBadgeClass = (source: string) => {
    return source === 'forgefire'
        ? 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200'
        : 'bg-violet-100 text-violet-800 dark:bg-violet-900 dark:text-violet-200';
};

const columns: ColumnDef<PodLink>[] = [
    {
        accessorKey: 'name',
        header: () => h('div', {}, 'Name'),
        cell: ({ row }) => h('div', { class: 'font-medium' }, row.getValue('name')),
    },
    {
        accessorKey: 'source',
        header: () => h('div', {}, 'Source'),
        cell: ({ row }) => {
            const source = row.getValue('source') as string;
            return h(Badge, { class: ['border-0 text-xs', sourceBadgeClass(source)], variant: 'outline' }, () => sourceLabel(source));
        },
    },
    {
        accessorKey: 'url',
        header: () => h('div', {}, 'URL'),
        cell: ({ row }) => {
            const url = row.getValue('url') as string;
            return h(
                'a',
                { href: url, target: '_blank', class: 'text-xs text-primary hover:underline truncate max-w-[200px] block' },
                url.replace(/^https?:\/\//, '').slice(0, 40) + (url.length > 60 ? '...' : ''),
            );
        },
    },
    {
        id: 'actions',
        enableHiding: false,
        header: () => h('div', {}, 'Actions'),
        cell: ({ row }) => {
            const link = row.original;
            return h(
                'div',
                { class: 'relative' },
                h(AdminActions, {
                    name: link.name,
                    editRoute: route('admin.pod_links.edit', link.slug),
                    deleteRoute: route('admin.pod_links.delete', link.slug),
                }),
            );
        },
    },
];

const props = defineProps<{
    pod_links: PodLink[];
}>();

const columnFilters = ref<ColumnFiltersState>([]);

const table = useVueTable({
    get data() {
        return props.pod_links;
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
</script>

<template>
    <Head title="POD Links - Admin" />

    <div class="container mx-auto mt-6 h-full px-2">
        <div class="flex items-center justify-between py-4">
            <Input
                class="max-w-sm"
                placeholder="Filter POD Links"
                :model-value="table.getColumn('name')?.getFilterValue() as string"
                @update:model-value="table.getColumn('name')?.setFilterValue($event)"
            />
            <div>Total {{ props.pod_links.length }}</div>
            <Button @click="router.get(route('admin.pod_links.create'))">Create POD Link</Button>
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
                            <TableCell :colspan="columns.length" class="h-24 text-center">No results.</TableCell>
                        </TableRow>
                    </template>
                </TableBody>
            </Table>
        </div>
        <div class="flex items-center justify-end space-x-2 py-4">
            <Button variant="outline" size="sm" :disabled="!table.getCanPreviousPage()" @click="table.previousPage()">Previous</Button>
            <Button variant="outline" size="sm" :disabled="!table.getCanNextPage()" @click="table.nextPage()">Next</Button>
        </div>
    </div>
</template>
