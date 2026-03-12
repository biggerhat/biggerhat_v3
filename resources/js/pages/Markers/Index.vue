<script setup lang="ts">
import EmptyState from '@/components/EmptyState.vue';
import GameText from '@/components/GameText.vue';
import PageBanner from '@/components/PageBanner.vue';
import { Badge } from '@/components/ui/badge';
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

interface MarkerData {
    name: string;
    slug: string;
    description: string;
    base: string | null;
}

const columns: ColumnDef<MarkerData>[] = [
    {
        accessorKey: 'name',
        header: () => h('div', {}, 'Marker'),
        cell: ({ row }) => {
            const base = row.original.base;
            const children = [row.getValue('name') as string];
            if (base) {
                children.push(` (${base}mm)`);
            }
            return h('div', { class: 'w-auto font-medium' }, children);
        },
    },
    {
        accessorKey: 'description',
        header: () => h('div', {}, 'Description'),
        cell: ({ row }) => {
            return h(GameText, { text: row.getValue('description') as string });
        },
    },
];

const props = defineProps<{
    markers: MarkerData[];
}>();

const columnFilters = ref<ColumnFiltersState>([]);
const selectedSize = ref<string | null>(null);

const availableSizes = computed(() => {
    const sizes = [...new Set(props.markers.map((m) => m.base).filter(Boolean))] as string[];
    return sizes.sort((a, b) => Number(a) - Number(b));
});

const toggleSize = (size: string) => {
    selectedSize.value = selectedSize.value === size ? null : size;
};

const sizeFilteredMarkers = computed(() => {
    if (!selectedSize.value) return props.markers;
    return props.markers.filter((m) => m.base === selectedSize.value);
});

const table = useVueTable({
    get data() {
        return sizeFilteredMarkers.value;
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

const filteredMarkers = computed(() => table.getFilteredRowModel().rows.map((row) => row.original));
const filteredCount = computed(() => filteredMarkers.value.length);
const totalCount = computed(() => props.markers.length);
const isFiltered = computed(() => filteredCount.value !== totalCount.value || selectedSize.value !== null);

const { delays } = useStaggeredEntry(filteredCount);
</script>

<template>
    <Head title="Markers" />
    <div class="relative">
        <div
            class="pointer-events-none absolute inset-x-0 top-0 h-64 opacity-[0.07] dark:opacity-[0.12]"
            :style="{ background: 'radial-gradient(ellipse at top, hsl(var(--primary)) 0%, transparent 70%)' }"
        />
        <PageBanner title="Marker Directory">
            <template #subtitle>
                <div class="px-2 text-sm text-muted-foreground">{{ totalCount }} Markers</div>
            </template>
        </PageBanner>
        <div class="container mx-auto mt-6 px-4">
            <div class="flex flex-wrap items-center gap-3 py-4">
                <Input
                    class="max-w-sm"
                    placeholder="Filter Markers"
                    :model-value="table.getColumn('name')?.getFilterValue() as string"
                    @update:model-value="table.getColumn('name')?.setFilterValue($event)"
                />
                <div class="flex items-center gap-1.5">
                    <span class="text-xs text-muted-foreground">Size:</span>
                    <button
                        v-for="size in availableSizes"
                        :key="size"
                        class="rounded-full border px-2.5 py-0.5 text-xs font-medium transition-colors"
                        :class="
                            selectedSize === size
                                ? 'border-primary bg-primary text-primary-foreground'
                                : 'border-border bg-background text-muted-foreground hover:bg-accent hover:text-accent-foreground'
                        "
                        @click="toggleSize(size)"
                    >
                        {{ size }}mm
                    </button>
                </div>
                <div v-if="isFiltered" class="ml-auto text-sm text-muted-foreground">Showing {{ filteredCount }} of {{ totalCount }}</div>
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
                    <div v-if="filteredMarkers.length" class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <Card
                            v-for="(marker, index) in filteredMarkers"
                            :key="marker.name"
                            class="animate-fade-in-up opacity-0 transition-all duration-300 hover:-translate-y-1 hover:shadow-xl"
                            :style="delays[index]"
                        >
                            <CardHeader class="pb-2">
                                <CardTitle class="flex items-center gap-2 text-base">
                                    {{ marker.name }}
                                    <Badge v-if="marker.base" variant="outline" class="text-xs font-normal">{{ marker.base }}mm</Badge>
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <p class="text-sm text-muted-foreground">
                                    <GameText :text="marker.description" />
                                </p>
                            </CardContent>
                        </Card>
                    </div>
                    <EmptyState v-else title="No markers found" description="Try adjusting your search filter." />
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
