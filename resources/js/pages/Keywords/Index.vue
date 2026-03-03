<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { h, ref, computed } from 'vue';
import type { ColumnDef, ColumnFiltersState } from '@tanstack/vue-table';
import { Input } from '@/components/ui/input';
import { valueUpdater } from '@/lib/utils';
import { Ban, Check, LayoutGrid, List } from 'lucide-vue-next';
import PageBanner from '@/components/PageBanner.vue';
import EmptyState from '@/components/EmptyState.vue';
import KeywordTableLink from '@/components/KeywordTableLink.vue';
import { useStaggeredEntry } from '@/composables/useStaggeredEntry';

import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

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
    getFilteredRowModel,
    useVueTable,
} from '@tanstack/vue-table';

const columns: ColumnDef<Keywords>[] = [
    {
        accessorKey: 'name',
        header: () => h('div', {}, 'Keyword'),
        cell: ({ row }) => {
            const keyword = row.original;
            return h('div', { class: 'w-auto' }, h(KeywordTableLink, { keyword: keyword }));
        },
    },
    {
        accessorKey: 'has_master',
        header: () => h('div', {}, 'Master'),
        cell: ({ row }) => {
            return h(
                'div',
                {},
                row.getValue('has_master') ? h(Check, { class: 'text-green-500' }) : h(Ban, { class: 'text-red-500' }),
            );
        },
    },
    {
        accessorKey: 'characters_count',
        header: () => h('div', {}, 'Characters'),
        cell: ({ row }) => {
            return h('div', {}, row.getValue('characters_count'));
        },
    },
];

const props = defineProps<{
    keywords: TData[];
}>();

const columnFilters = ref<ColumnFiltersState>([]);

const table = useVueTable({
    get data() {
        return props.keywords;
    },
    get columns() {
        return columns;
    },
    getCoreRowModel: getCoreRowModel(),
    onColumnFiltersChange: (updaterOrValue) => valueUpdater(updaterOrValue, columnFilters),
    getFilteredRowModel: getFilteredRowModel(),
    state: {
        get columnFilters() {
            return columnFilters.value;
        },
    },
});

const filteredKeywords = computed(() => table.getFilteredRowModel().rows.map((row) => row.original));
const filteredCount = computed(() => filteredKeywords.value.length);
const totalCount = computed(() => props.keywords.length);
const isFiltered = computed(() => filteredCount.value !== totalCount.value);

const { delays } = useStaggeredEntry(filteredCount);
</script>

<template>
    <Head title="Keywords" />
    <div class="w-full h-full">
        <PageBanner title="Keyword Directory">
            <template #subtitle>
                <div class="px-2 text-sm text-muted-foreground">{{ totalCount }} Keywords</div>
            </template>
        </PageBanner>
        <div class="container mx-auto mt-6 px-4 lg:px-6">
            <div class="flex items-center justify-between py-4">
                <Input
                    class="max-w-sm"
                    placeholder="Filter Keywords"
                    :model-value="table.getColumn('name')?.getFilterValue() as string"
                    @update:model-value="table.getColumn('name')?.setFilterValue($event)"
                />
                <div v-if="isFiltered" class="text-sm text-muted-foreground">
                    Showing {{ filteredCount }} of {{ totalCount }}
                </div>
            </div>

            <Tabs default-value="cards">
                <div class="flex items-center justify-between mb-4">
                    <TabsList class="gap-1">
                        <TabsTrigger value="cards">
                            <LayoutGrid class="size-4" />
                            Cards
                        </TabsTrigger>
                        <TabsTrigger value="table">
                            <List class="size-4" />
                            Table
                        </TabsTrigger>
                    </TabsList>
                </div>

                <TabsContent value="cards">
                    <div v-if="filteredKeywords.length" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                        <Link
                            v-for="(keyword, index) in filteredKeywords"
                            :key="keyword.slug"
                            :href="route('keywords.view', { keyword: keyword.slug })"
                            class="animate-fade-in-up opacity-0"
                            :style="delays[index]"
                        >
                            <Card class="h-full transition-colors hover:bg-accent">
                                <CardHeader class="pb-2">
                                    <CardTitle class="text-base">{{ keyword.name }}</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div class="flex items-center justify-between text-sm">
                                        <span class="text-muted-foreground">{{ keyword.characters_count }} Characters</span>
                                        <span
                                            v-if="keyword.has_master"
                                            class="inline-flex items-center rounded-full bg-green-500/10 px-2 py-0.5 text-xs font-medium text-green-500"
                                        >
                                            Has Master
                                        </span>
                                        <span
                                            v-else
                                            class="inline-flex items-center rounded-full bg-muted px-2 py-0.5 text-xs font-medium text-muted-foreground"
                                        >
                                            No Master
                                        </span>
                                    </div>
                                </CardContent>
                            </Card>
                        </Link>
                    </div>
                    <EmptyState v-else />
                </TabsContent>

                <TabsContent value="table">
                    <div class="border rounded-md">
                        <Table>
                            <TableHeader>
                                <TableRow v-for="headerGroup in table.getHeaderGroups()" :key="headerGroup.id">
                                    <TableHead v-for="header in headerGroup.headers" :key="header.id">
                                        <FlexRender
                                            v-if="!header.isPlaceholder"
                                            :render="header.column.columnDef.header"
                                            :props="header.getContext()"
                                        />
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <template v-if="table.getRowModel().rows?.length">
                                    <TableRow
                                        v-for="row in table.getRowModel().rows"
                                        :key="row.id"
                                        :data-state="row.getIsSelected() ? 'selected' : undefined"
                                    >
                                        <TableCell v-for="cell in row.getVisibleCells()" :key="cell.id">
                                            <FlexRender :render="cell.column.columnDef.cell" :props="cell.getContext()" />
                                        </TableCell>
                                    </TableRow>
                                </template>
                                <template v-else>
                                    <TableRow>
                                        <TableCell :colspan="columns.length">
                                            <EmptyState />
                                        </TableCell>
                                    </TableRow>
                                </template>
                            </TableBody>
                        </Table>
                    </div>
                </TabsContent>
            </Tabs>
        </div>
    </div>
</template>
