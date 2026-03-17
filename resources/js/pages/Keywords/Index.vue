<script setup lang="ts">
import CharacterCardView from '@/components/CharacterCardView.vue';
import EmptyState from '@/components/EmptyState.vue';
import FactionLogo from '@/components/FactionLogo.vue';
import GameIcon from '@/components/GameIcon.vue';
import KeywordTableLink from '@/components/KeywordTableLink.vue';
import PageBanner from '@/components/PageBanner.vue';
import UpgradeFlipCard from '@/components/UpgradeFlipCard.vue';
import { Button } from '@/components/ui/button';
import { Drawer, DrawerClose, DrawerContent, DrawerFooter, DrawerHeader, DrawerTitle } from '@/components/ui/drawer';
import { Input } from '@/components/ui/input';
import { useStaggeredEntry } from '@/composables/useStaggeredEntry';
import { valueUpdater } from '@/lib/utils';
import { Head, Link } from '@inertiajs/vue3';
import type { ColumnDef, ColumnFiltersState } from '@tanstack/vue-table';
import { Ban, Check, LayoutGrid, List } from 'lucide-vue-next';
import { computed, h, ref } from 'vue';

import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';

import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';

import { FlexRender, getCoreRowModel, getFilteredRowModel, useVueTable } from '@tanstack/vue-table';

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
            return h('div', {}, row.getValue('has_master') ? h(Check, { class: 'text-green-500' }) : h(Ban, { class: 'text-red-500' }));
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

const upgradeDrawerOpen = ref(false);
const selectedUpgrade = ref<any>(null);
const masterDrawerOpen = ref(false);
const selectedMaster = ref<any>(null);

function openUpgradeDrawer(upgrade: any, event: Event) {
    event.preventDefault();
    event.stopPropagation();
    selectedUpgrade.value = upgrade;
    upgradeDrawerOpen.value = true;
}

function openMasterDrawer(master: any, event: Event) {
    event.preventDefault();
    event.stopPropagation();
    selectedMaster.value = master;
    masterDrawerOpen.value = true;
}
</script>

<template>
    <Head title="Keywords" />
    <div class="relative">
        <div
            class="pointer-events-none absolute inset-x-0 top-0 h-64 opacity-[0.07] dark:opacity-[0.12]"
            :style="{ background: 'radial-gradient(ellipse at top, hsl(var(--primary)) 0%, transparent 70%)' }"
        />
        <PageBanner title="Keyword Directory">
            <template #subtitle>
                <div class="px-2 text-sm text-muted-foreground">{{ totalCount }} Keywords</div>
            </template>
        </PageBanner>
        <div class="container mx-auto mt-6 px-4">
            <div class="flex flex-col gap-2 py-4 sm:flex-row sm:items-center sm:justify-between">
                <Input
                    class="max-w-sm"
                    placeholder="Filter Keywords"
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
                    <div v-if="filteredKeywords.length" class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <Link
                            v-for="(keyword, index) in filteredKeywords"
                            :key="keyword.slug"
                            :href="route('keywords.view', { keyword: keyword.slug })"
                            class="animate-fade-in-up opacity-0"
                            :style="delays[index]"
                        >
                            <Card class="h-full overflow-hidden transition-all duration-300 hover:-translate-y-1 hover:shadow-xl">
                                <div class="flex">
                                    <div v-if="keyword.image" class="hidden w-32 shrink-0 items-center justify-center bg-muted/50 p-2 sm:flex lg:w-40">
                                        <img :src="'/storage/' + keyword.image" :alt="keyword.name" class="h-full w-full object-contain" loading="lazy" />
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <CardHeader class="pb-2">
                                            <div class="flex items-center justify-between">
                                                <CardTitle class="text-base">{{ keyword.name }}</CardTitle>
                                                <span
                                                    v-if="keyword.has_master"
                                                    class="inline-flex items-center rounded-full bg-green-500/10 px-2 py-0.5 text-xs font-medium text-green-500"
                                                >
                                                    Master
                                                </span>
                                            </div>
                                        </CardHeader>
                                        <CardContent class="space-y-2.5">
                                            <!-- Faction distribution -->
                                            <div v-if="keyword.factions?.length" class="flex flex-wrap gap-1.5">
                                                <Badge
                                                    v-for="f in keyword.factions"
                                                    :key="f.value"
                                                    variant="outline"
                                                    class="gap-1.5 border-transparent py-1 pr-2 pl-1.5 font-normal text-white"
                                                    :style="{ backgroundColor: `hsl(var(--${f.value.replace(/_/g, '')}) / 0.85)` }"
                                                >
                                                    <FactionLogo :faction="f.value" class-name="size-3.5" />
                                                    <span>{{ f.label }}</span>
                                                    <span class="text-white/70">{{ f.percent }}%</span>
                                                </Badge>
                                            </div>
                                            <!-- Masters + their crew upgrades -->
                                            <div v-if="keyword.master_summaries?.length" class="space-y-1.5">
                                                <div v-for="master in keyword.master_summaries" :key="master.slug" class="flex flex-wrap items-center gap-1.5">
                                                    <Badge
                                                        v-if="master.miniature"
                                                        variant="outline"
                                                        class="cursor-pointer border-transparent py-1 font-medium text-white hover:opacity-80"
                                                        :style="{ backgroundColor: `hsl(var(--${master.faction.replace(/_/g, '')}) / 0.85)` }"
                                                        @click.prevent.stop="openMasterDrawer(master, $event)"
                                                    >
                                                        {{ master.name }}
                                                    </Badge>
                                                    <Badge v-else variant="outline" class="py-1 font-medium">
                                                        {{ master.name }}
                                                    </Badge>
                                                    <Badge
                                                        v-for="upgrade in master.crew_upgrades"
                                                        :key="upgrade.id"
                                                        variant="secondary"
                                                        class="cursor-pointer py-1 font-normal hover:bg-accent"
                                                        @click.prevent.stop="openUpgradeDrawer(upgrade, $event)"
                                                    >
                                                        {{ upgrade.name }}
                                                    </Badge>
                                                </div>
                                            </div>
                                            <!-- Station breakdown -->
                                            <div v-if="keyword.stations?.length" class="flex flex-wrap gap-1.5">
                                                <Badge v-for="s in keyword.stations" :key="s.label" variant="secondary" class="font-normal">
                                                    {{ s.count }} {{ s.label }}{{ s.count !== 1 ? 's' : '' }}
                                                </Badge>
                                            </div>
                                            <!-- Counts -->
                                            <div class="flex items-center gap-3 text-xs">
                                                <span class="text-muted-foreground">{{ keyword.characters_count }} Characters</span>
                                                <span class="text-muted-foreground">{{ keyword.miniatures_count }} Miniatures</span>
                                                <Link
                                                    v-if="keyword.packages_count"
                                                    :href="route('packages.index', { keyword: keyword.slug })"
                                                    class="text-primary hover:underline"
                                                    @click.stop
                                                >
                                                    {{ keyword.packages_count }} Packages
                                                </Link>
                                            </div>
                                            <!-- Avg stats -->
                                            <div v-if="keyword.stats" class="grid grid-cols-3 gap-1 text-center text-xs sm:grid-cols-5">
                                                <div v-if="keyword.stats.avg_cost != null" class="rounded bg-muted/50 px-1 py-0.5">
                                                    <div class="font-medium text-foreground">{{ keyword.stats.avg_cost }}</div>
                                                    <div class="flex items-center justify-center gap-0.5 text-[10px] text-muted-foreground">
                                                        <GameIcon type="soulstone" class-name="h-2.5" />
                                                    </div>
                                                </div>
                                                <div v-if="keyword.stats.avg_health != null" class="rounded bg-muted/50 px-1 py-0.5">
                                                    <div class="font-medium text-foreground">{{ keyword.stats.avg_health }}</div>
                                                    <div class="text-[10px] text-muted-foreground">HP</div>
                                                </div>
                                                <div v-if="keyword.stats.avg_speed != null" class="rounded bg-muted/50 px-1 py-0.5">
                                                    <div class="font-medium text-foreground">{{ keyword.stats.avg_speed }}</div>
                                                    <div class="text-[10px] text-muted-foreground">SPD</div>
                                                </div>
                                                <div v-if="keyword.stats.avg_defense != null" class="rounded bg-muted/50 px-1 py-0.5">
                                                    <div class="font-medium text-foreground">{{ keyword.stats.avg_defense }}</div>
                                                    <div class="text-[10px] text-muted-foreground">DEF</div>
                                                </div>
                                                <div v-if="keyword.stats.avg_willpower != null" class="rounded bg-muted/50 px-1 py-0.5">
                                                    <div class="font-medium text-foreground">{{ keyword.stats.avg_willpower }}</div>
                                                    <div class="text-[10px] text-muted-foreground">WP</div>
                                                </div>
                                            </div>
                                        </CardContent>
                                    </div>
                                </div>
                            </Card>
                        </Link>
                    </div>
                    <EmptyState v-else />
                </TabsContent>

                <TabsContent value="table">
                    <div class="overflow-auto rounded-md border">
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

    <!-- Master Card Drawer -->
    <Drawer v-model:open="masterDrawerOpen">
        <DrawerContent>
            <div v-if="selectedMaster?.miniature" class="mx-auto w-full max-w-sm">
                <DrawerHeader class="pb-2">
                    <DrawerTitle class="text-center">{{ selectedMaster.name }}</DrawerTitle>
                </DrawerHeader>
                <div class="flex min-h-0 flex-1 flex-col px-4 pb-2">
                    <div class="flex min-h-0 flex-1 items-start justify-center [&_img]:max-h-[55dvh] [&_img]:w-auto [&_img]:object-contain">
                        <CharacterCardView :miniature="selectedMaster.miniature" :character-slug="selectedMaster.slug" :show-link="true" />
                    </div>
                </div>
                <DrawerFooter class="shrink-0 pt-2">
                    <DrawerClose as-child>
                        <Button variant="outline">Close</Button>
                    </DrawerClose>
                </DrawerFooter>
            </div>
        </DrawerContent>
    </Drawer>

    <!-- Upgrade Card Drawer -->
    <Drawer v-model:open="upgradeDrawerOpen">
        <DrawerContent>
            <div v-if="selectedUpgrade" class="mx-auto w-full max-w-sm">
                <DrawerHeader class="pb-2">
                    <DrawerTitle class="text-center">{{ selectedUpgrade.name }}</DrawerTitle>
                    <div class="mt-1 text-center text-xs text-muted-foreground">Crew Upgrade</div>
                </DrawerHeader>
                <div class="flex min-h-0 flex-1 flex-col px-4 pb-2">
                    <div class="flex min-h-0 flex-1 items-start justify-center [&_img]:max-h-[55dvh] [&_img]:w-auto [&_img]:object-contain">
                        <UpgradeFlipCard
                            :front-image="selectedUpgrade.front_image"
                            :back-image="selectedUpgrade.back_image"
                            :alt-text="selectedUpgrade.name"
                            :upgrade-slug="selectedUpgrade.slug"
                            :show-link="true"
                        />
                    </div>
                </div>
                <DrawerFooter class="shrink-0 pt-2">
                    <DrawerClose as-child>
                        <Button variant="outline">Close</Button>
                    </DrawerClose>
                </DrawerFooter>
            </div>
        </DrawerContent>
    </Drawer>
</template>
