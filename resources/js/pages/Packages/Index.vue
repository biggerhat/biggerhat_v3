<script setup lang="ts">
import EmptyState from '@/components/EmptyState.vue';
import FactionLogo from '@/components/FactionLogo.vue';
import PageBanner from '@/components/PageBanner.vue';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { useStaggeredEntry } from '@/composables/useStaggeredEntry';
import { valueUpdater } from '@/lib/utils';
import { Head, Link } from '@inertiajs/vue3';
import type { ColumnDef, ColumnFiltersState } from '@tanstack/vue-table';
import { LayoutGrid, List } from 'lucide-vue-next';
import { computed, h, ref } from 'vue';

import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';

import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';

import { FlexRender, getCoreRowModel, getFilteredRowModel, useVueTable } from '@tanstack/vue-table';

const columns: ColumnDef<any>[] = [
    {
        accessorKey: 'name',
        header: () => h('div', {}, 'Package'),
        cell: ({ row }) => {
            const pkg = row.original;
            return h(
                'a',
                {
                    href: route('packages.view', { package: pkg.slug }),
                    class: 'font-medium text-primary hover:underline',
                },
                pkg.name,
            );
        },
    },
    {
        accessorKey: 'sku',
        header: () => h('div', {}, 'SKU'),
        cell: ({ row }) => {
            return h('div', {}, row.getValue('sku') ?? '-');
        },
    },
    {
        accessorKey: 'sculpt_version_label',
        header: () => h('div', {}, 'Edition'),
        cell: ({ row }) => {
            return h('div', {}, row.getValue('sculpt_version_label'));
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
    packages: any[];
    factions: Record<string, any>;
}>();

const columnFilters = ref<ColumnFiltersState>([]);
const factionFilter = ref<string | null>(null);

const filteredByFaction = computed(() => {
    if (!factionFilter.value) return props.packages;
    return props.packages.filter((pkg) => pkg.factions?.some((f: any) => f.value === factionFilter.value));
});

const table = useVueTable({
    get data() {
        return filteredByFaction.value;
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

const filteredPackages = computed(() => table.getFilteredRowModel().rows.map((row) => row.original));
const filteredCount = computed(() => filteredPackages.value.length);
const totalCount = computed(() => props.packages.length);
const isFiltered = computed(() => filteredCount.value !== totalCount.value);

const { delays } = useStaggeredEntry(filteredCount);

const toggleFaction = (faction: string) => {
    factionFilter.value = factionFilter.value === faction ? null : faction;
};
</script>

<template>
    <Head title="Packages" />
    <div class="relative">
        <div
            class="pointer-events-none absolute inset-x-0 top-0 h-64 opacity-[0.07] dark:opacity-[0.12]"
            :style="{ background: 'radial-gradient(ellipse at top, hsl(var(--primary)) 0%, transparent 70%)' }"
        />
        <PageBanner title="Package Directory">
            <template #subtitle>
                <div class="px-2 text-sm text-muted-foreground">{{ totalCount }} Packages</div>
            </template>
        </PageBanner>
        <div class="container mx-auto mt-6 px-4">
            <div class="flex flex-wrap items-center gap-4 py-4">
                <Input
                    class="max-w-sm"
                    placeholder="Filter Packages"
                    :model-value="table.getColumn('name')?.getFilterValue() as string"
                    @update:model-value="table.getColumn('name')?.setFilterValue($event)"
                />
                <div class="flex flex-wrap gap-2">
                    <button
                        v-for="(faction, key) in factions"
                        :key="key"
                        @click="toggleFaction(faction.slug)"
                        class="rounded-full p-1 transition-all"
                        :class="factionFilter === faction.slug ? 'ring-2 ring-primary' : 'opacity-50 hover:opacity-100'"
                    >
                        <FactionLogo :faction="faction.slug" class-name="h-6 w-6" />
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
                    <div v-if="filteredPackages.length" class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <Link
                            v-for="(pkg, index) in filteredPackages"
                            :key="pkg.slug"
                            :href="route('packages.view', { package: pkg.slug })"
                            class="animate-fade-in-up opacity-0"
                            :style="delays[index]"
                        >
                            <Card class="h-full transition-all duration-300 hover:-translate-y-1 hover:shadow-xl">
                                <div v-if="pkg.front_image" class="overflow-hidden rounded-t-xl">
                                    <img :src="`/storage/${pkg.front_image}`" :alt="pkg.name" class="aspect-[3/4] w-full object-cover" />
                                </div>
                                <CardHeader class="pb-2">
                                    <CardTitle class="text-base">{{ pkg.name }}</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <div v-if="pkg.factions?.length" class="flex gap-1">
                                            <FactionLogo
                                                v-for="faction in pkg.factions"
                                                :key="faction.value"
                                                :faction="faction.value"
                                                class-name="h-5 w-5"
                                            />
                                        </div>
                                        <Badge v-if="pkg.sculpt_version_label" variant="secondary" class="text-xs">
                                            {{ pkg.sculpt_version_label }}
                                        </Badge>
                                        <span class="ml-auto text-xs text-muted-foreground">{{ pkg.characters_count }} Characters</span>
                                    </div>
                                </CardContent>
                            </Card>
                        </Link>
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
