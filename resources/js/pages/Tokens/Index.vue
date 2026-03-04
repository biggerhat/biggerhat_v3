<script setup lang="ts">
import EmptyState from '@/components/EmptyState.vue';
import PageBanner from '@/components/PageBanner.vue';
import { Input } from '@/components/ui/input';
import { useStaggeredEntry } from '@/composables/useStaggeredEntry';
import { valueUpdater } from '@/lib/utils';
import { Head } from '@inertiajs/vue3';
import type { ColumnDef, ColumnFiltersState } from '@tanstack/vue-table';
import { LayoutGrid, List } from 'lucide-vue-next';
import { computed, h, ref } from 'vue';

import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';

import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';

import { FlexRender, getCoreRowModel, getFilteredRowModel, useVueTable } from '@tanstack/vue-table';

const columns: ColumnDef<Tokens>[] = [
    {
        accessorKey: 'name',
        header: () => h('div', {}, 'Token'),
        cell: ({ row }) => {
            return h('div', { class: 'w-auto' }, row.getValue('name'));
        },
    },
    {
        accessorKey: 'description',
        header: () => h('div', {}, 'Description'),
        cell: ({ row }) => {
            return h('div', {}, row.getValue('description'));
        },
    },
];

const props = defineProps<{
    tokens: TData[];
}>();

const columnFilters = ref<ColumnFiltersState>([]);

const table = useVueTable({
    get data() {
        return props.tokens;
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

const filteredTokens = computed(() => table.getFilteredRowModel().rows.map((row) => row.original));
const filteredCount = computed(() => filteredTokens.value.length);
const totalCount = computed(() => props.tokens.length);
const isFiltered = computed(() => filteredCount.value !== totalCount.value);

const { delays } = useStaggeredEntry(filteredCount);
</script>

<template>
    <Head title="Tokens" />
    <div class="relative">
        <div
            class="pointer-events-none absolute inset-x-0 top-0 h-64 opacity-[0.07] dark:opacity-[0.12]"
            :style="{ background: 'radial-gradient(ellipse at top, hsl(var(--primary)) 0%, transparent 70%)' }"
        />
        <PageBanner title="Token Directory">
            <template #subtitle>
                <div class="px-2 text-sm text-muted-foreground">{{ totalCount }} Tokens</div>
            </template>
        </PageBanner>
        <div class="container mx-auto mt-6 px-4">
            <div class="flex items-center justify-between py-4">
                <Input
                    class="max-w-sm"
                    placeholder="Filter Tokens"
                    :model-value="table.getColumn('name')?.getFilterValue() as string"
                    @update:model-value="table.getColumn('name')?.setFilterValue($event)"
                />
                <div v-if="isFiltered" class="text-sm text-muted-foreground">Showing {{ filteredCount }} of {{ totalCount }}</div>
            </div>

            <Tabs default-value="cards">
                <div class="mb-4 flex items-center justify-between">
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
                    <div v-if="filteredTokens.length" class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <Card
                            v-for="(token, index) in filteredTokens"
                            :key="token.name"
                            class="animate-fade-in-up opacity-0 transition-colors hover:bg-accent/50"
                            :style="delays[index]"
                        >
                            <CardHeader class="pb-2">
                                <CardTitle class="text-base">{{ token.name }}</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <p class="text-sm text-muted-foreground">{{ token.description }}</p>
                            </CardContent>
                        </Card>
                    </div>
                    <EmptyState v-else />
                </TabsContent>

                <TabsContent value="table">
                    <div class="rounded-md border">
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
