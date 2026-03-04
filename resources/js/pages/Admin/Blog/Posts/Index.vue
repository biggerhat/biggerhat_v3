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

interface BlogPost {
    id: number;
    title: string;
    slug: string;
    status: string;
    author: { name: string } | null;
    category: { name: string } | null;
    published_at: string | null;
    created_at: string;
}

const statusVariant = (status: string) => {
    switch (status) {
        case 'published':
            return 'default';
        case 'draft':
            return 'secondary';
        case 'archived':
            return 'outline';
        default:
            return 'secondary';
    }
};

const columns: ColumnDef<BlogPost>[] = [
    {
        accessorKey: 'title',
        header: () => h('div', {}, 'Title'),
        cell: ({ row }) => h('div', { class: 'font-medium' }, row.getValue('title')),
    },
    {
        accessorKey: 'category',
        header: () => h('div', {}, 'Category'),
        cell: ({ row }) => {
            const cat = row.original.category;
            return h('div', {}, cat?.name ?? '—');
        },
    },
    {
        accessorKey: 'status',
        header: () => h('div', {}, 'Status'),
        cell: ({ row }) => {
            const status = row.getValue('status') as string;
            return h(Badge, { variant: statusVariant(status) }, () => status);
        },
    },
    {
        accessorKey: 'author',
        header: () => h('div', {}, 'Author'),
        cell: ({ row }) => h('div', {}, row.original.author?.name ?? '—'),
    },
    {
        accessorKey: 'created_at',
        header: () => h('div', {}, 'Date'),
        cell: ({ row }) => {
            const date = row.original.published_at ?? row.original.created_at;
            return h('div', {}, new Date(date).toLocaleDateString());
        },
    },
    {
        id: 'actions',
        enableHiding: false,
        header: () => h('div', {}, 'Actions'),
        cell: ({ row }) => {
            const post = row.original;
            return h(
                'div',
                { class: 'relative' },
                h(AdminActions, {
                    name: post.title,
                    editRoute: route('admin.blog.posts.edit', post.slug),
                    deleteRoute: route('admin.blog.posts.delete', post.slug),
                }),
            );
        },
    },
];

const props = defineProps<{
    posts: BlogPost[];
}>();

const columnFilters = ref<ColumnFiltersState>([]);

const table = useVueTable({
    get data() {
        return props.posts;
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
    <Head title="Blog Posts - Admin" />

    <div class="container mx-auto mt-6 h-full px-2">
        <div class="flex items-center justify-between py-4">
            <Input
                class="max-w-sm"
                placeholder="Filter Posts"
                :model-value="table.getColumn('title')?.getFilterValue() as string"
                @update:model-value="table.getColumn('title')?.setFilterValue($event)"
            />
            <div>Total {{ props.posts.length }}</div>
            <Button @click="router.get(route('admin.blog.posts.create'))">Create New Post</Button>
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
