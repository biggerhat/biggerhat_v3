<script setup lang="ts">
import AllegianceLogo from '@/components/AllegianceLogo.vue';
import CardSkeleton from '@/components/CardSkeleton.vue';
import ClearableSelect from '@/components/ClearableSelect.vue';
import EmptyState from '@/components/EmptyState.vue';
import FilterPanel from '@/components/FilterPanel.vue';
import PageBanner from '@/components/PageBanner.vue';
import TableSkeleton from '@/components/TableSkeleton.vue';
import FlipCard from '@/components/TOS/FlipCard.vue';
import TosText from '@/components/TosText.vue';
import { Badge } from '@/components/ui/badge';
import Button from '@/components/ui/button/Button.vue';
import { Card, CardContent } from '@/components/ui/card';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { cleanObject } from '@/composables/CleanObject';
import type { TosSelectOption } from '@/types/tos';
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, ChevronDown, LayoutGrid, List, Swords } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';

interface Allegiance {
    id: number;
    slug: string;
    name: string;
    short_name: string | null;
    type: string;
    is_syndicate: boolean;
    description: string | null;
    logo_path: string | null;
    color_slug: string | null;
}

interface Sculpt {
    id: number;
    slug: string;
    name: string | null;
    front_image: string | null;
    back_image: string | null;
    combination_image: string | null;
}

interface SpecialRule {
    id: number;
    slug: string;
    name: string;
}

interface Side {
    id: number;
    side: string;
    speed: number;
    defense: number;
    willpower: number;
    armor: number;
}

interface Unit {
    id: number;
    slug: string;
    name: string;
    title: string | null;
    scrip: number;
    tactics: string | null;
    restriction: string | null;
    sculpts: Sculpt[];
    sides: Side[];
    special_unit_rules: SpecialRule[];
    allegiances: Array<{ id: number; slug: string }>;
}

interface Statistics {
    total: number;
    total_scrip: number;
    average_scrip: number | null;
    by_rule: Array<{ slug: string; name: string; count: number }>;
}

const props = defineProps<{
    allegiance: Allegiance;
    units: Unit[];
    statistics: Statistics;
    special_rule: string | null;
    page_view: string;
    sort: string;
    sort_type: string;
    special_rules: TosSelectOption[];
    sort_options: TosSelectOption[];
    sort_types: TosSelectOption[];
}>();

const filterParams = ref({
    special_rule: props.special_rule,
    page_view: props.page_view,
    sort: props.sort,
    sort_type: props.sort_type,
});

const filterKeys = ['special_rule'] as const;

const activeFilterCount = computed(() => filterKeys.filter((k) => filterParams.value[k] != null && filterParams.value[k] !== '').length);

const filter = () => {
    router.get(route('tos.allegiances.view', props.allegiance.slug), cleanObject(filterParams.value), {
        only: ['units', 'statistics', 'special_rule', 'page_view', 'sort', 'sort_type'],
        replace: true,
        preserveState: true,
        preserveScroll: true,
    });
};

const clear = () => {
    filterParams.value.special_rule = null;
    filterParams.value.page_view = 'cards';
    filterParams.value.sort = 'name';
    filterParams.value.sort_type = 'ascending';
    filter();
};

const handleViewChange = (value: string) => {
    filterParams.value.page_view = value;
    filter();
};

const sectionsOpen = ref({ filters: true, sorting: false });
type SectionKey = keyof typeof sectionsOpen.value;
const sectionKeys: SectionKey[] = ['filters', 'sorting'];
const toggleSection = (section: SectionKey) => {
    const opening = !sectionsOpen.value[section];
    for (const key of sectionKeys) sectionsOpen.value[key] = false;
    if (opening) sectionsOpen.value[section] = true;
};

const isLoading = ref(false);
onMounted(() => {
    router.on('start', () => {
        isLoading.value = true;
    });
    router.on('finish', () => {
        isLoading.value = false;
    });
    if (filterParams.value.sort !== 'name' || filterParams.value.sort_type !== 'ascending') {
        sectionsOpen.value.sorting = true;
    }
});

const ruleChips = computed(() => props.statistics.by_rule);
</script>

<template>
    <Head :title="allegiance.name" />
    <div class="relative">
        <div
            class="pointer-events-none absolute inset-x-0 top-0 h-64 opacity-[0.07] dark:opacity-[0.12]"
            :style="{ background: 'radial-gradient(ellipse at top, hsl(var(--primary)) 0%, transparent 70%)' }"
        />

        <PageBanner :title="allegiance.name" class="mb-2" :accent-color="allegiance.color_slug ? `border-${allegiance.color_slug}` : undefined">
            <template #logo>
                <div class="w-20 md:w-32">
                    <AllegianceLogo :allegiance="allegiance.slug" class-name="mx-auto my-auto h-16 w-16 md:h-20 md:w-20" />
                </div>
            </template>
            <template #subtitle>
                <div class="my-auto flex flex-wrap items-center gap-x-1 px-2 py-0 text-xs text-muted-foreground md:py-2 md:text-sm md:text-foreground">
                    <span class="capitalize">{{ allegiance.type }}</span>
                    <span class="text-muted-foreground/50">&middot;</span>
                    <span>{{ statistics.total }} {{ statistics.total === 1 ? 'unit' : 'units' }}</span>
                    <Badge v-if="allegiance.is_syndicate" variant="outline" class="ml-2 text-[10px]">Syndicate</Badge>
                </div>
            </template>
        </PageBanner>

        <!-- Stats block -->
        <div v-if="!isLoading" class="container mx-auto mb-2 sm:px-4">
            <div class="rounded-lg border bg-card p-3 sm:p-4">
                <div class="flex flex-wrap items-center gap-x-3 gap-y-2 sm:gap-x-5">
                    <div v-if="ruleChips.length" class="flex flex-wrap items-center gap-1.5">
                        <Badge v-for="r in ruleChips" :key="r.slug" variant="outline" class="text-xs">
                            {{ r.count }} {{ r.name }}{{ r.count > 1 ? 's' : '' }}
                        </Badge>
                    </div>
                    <div v-if="statistics.average_scrip != null" class="flex items-center gap-3">
                        <div class="text-center">
                            <div class="text-[10px] font-medium uppercase tracking-wide text-muted-foreground">Avg Scrip</div>
                            <div class="text-sm font-bold leading-tight tabular-nums">{{ statistics.average_scrip }}</div>
                        </div>
                        <div class="text-center">
                            <div class="text-[10px] font-medium uppercase tracking-wide text-muted-foreground">Total Scrip</div>
                            <div class="text-sm font-bold leading-tight tabular-nums">{{ statistics.total_scrip }}</div>
                        </div>
                    </div>
                </div>
                <p v-if="allegiance.description" class="mt-3 text-xs text-muted-foreground"><TosText :text="allegiance.description" /></p>
            </div>
        </div>

        <!-- Tabs + mobile filter trigger -->
        <div class="container mx-auto mb-2 flex flex-wrap items-center justify-between gap-2 sm:px-4">
            <Tabs :model-value="filterParams.page_view" @update:model-value="(v) => handleViewChange(v as string)">
                <TabsList>
                    <TabsTrigger value="cards">
                        <LayoutGrid class="h-4 w-4" />
                        <span class="hidden sm:inline">Cards</span>
                    </TabsTrigger>
                    <TabsTrigger value="table">
                        <List class="h-4 w-4" />
                        <span class="hidden sm:inline">Table</span>
                    </TabsTrigger>
                </TabsList>
            </Tabs>
            <div class="flex items-center gap-2">
                <Badge v-if="activeFilterCount > 0" variant="secondary" class="text-xs">
                    {{ activeFilterCount }} {{ activeFilterCount === 1 ? 'filter' : 'filters' }}
                </Badge>
                <div class="md:hidden">
                    <FilterPanel :filter-count="activeFilterCount" @filter="filter" @clear="clear">
                        <div class="grid gap-4">
                            <Collapsible :open="sectionsOpen.filters" @update:open="toggleSection('filters')">
                                <CollapsibleTrigger
                                    class="flex w-full items-center justify-between rounded-md bg-muted px-2.5 py-1.5 text-xs font-medium hover:bg-muted/80"
                                >
                                    Filters
                                    <ChevronDown class="h-3.5 w-3.5 transition-transform" :class="{ 'rotate-180': sectionsOpen.filters }" />
                                </CollapsibleTrigger>
                                <CollapsibleContent class="space-y-3 px-1 pt-2">
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium">Special Rule</label>
                                        <ClearableSelect
                                            v-model="filterParams.special_rule"
                                            placeholder="Any Rule"
                                            :options="special_rules"
                                            trigger-class="border-2 border-primary rounded"
                                        />
                                    </div>
                                </CollapsibleContent>
                            </Collapsible>
                            <Collapsible :open="sectionsOpen.sorting" @update:open="toggleSection('sorting')">
                                <CollapsibleTrigger
                                    class="flex w-full items-center justify-between rounded-md bg-muted px-2.5 py-1.5 text-xs font-medium hover:bg-muted/80"
                                >
                                    Sorting
                                    <ChevronDown class="h-3.5 w-3.5 transition-transform" :class="{ 'rotate-180': sectionsOpen.sorting }" />
                                </CollapsibleTrigger>
                                <CollapsibleContent class="space-y-3 px-1 pt-2">
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium">Sort By</label>
                                        <ClearableSelect
                                            v-model="filterParams.sort"
                                            placeholder="Sort By"
                                            :options="sort_options"
                                            trigger-class="border-2 border-primary rounded"
                                        />
                                    </div>
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium">Direction</label>
                                        <ClearableSelect
                                            v-model="filterParams.sort_type"
                                            placeholder="Direction"
                                            :options="sort_types"
                                            trigger-class="border-2 border-primary rounded"
                                        />
                                    </div>
                                </CollapsibleContent>
                            </Collapsible>
                        </div>
                    </FilterPanel>
                </div>
            </div>
        </div>

        <!-- Main content -->
        <div class="container mx-auto sm:px-4">
            <Link :href="route('tos.allegiances.index')" class="mb-2 inline-flex items-center gap-1 text-xs text-muted-foreground hover:text-foreground">
                <ArrowLeft class="size-3" /> All allegiances
            </Link>

            <div class="flex gap-6">
                <!-- Desktop sidebar filters -->
                <aside class="hidden w-56 shrink-0 md:block lg:w-72">
                    <div class="space-y-2 pr-2">
                        <Collapsible :open="sectionsOpen.filters" @update:open="toggleSection('filters')">
                            <CollapsibleTrigger
                                class="flex w-full items-center justify-between rounded-md bg-secondary px-3 py-2 text-sm font-medium hover:bg-secondary/80"
                            >
                                Filters
                                <ChevronDown class="h-4 w-4 transition-transform" :class="{ 'rotate-180': sectionsOpen.filters }" />
                            </CollapsibleTrigger>
                            <CollapsibleContent class="space-y-3 px-1 pt-3">
                                <div class="space-y-1">
                                    <label class="text-xs font-medium text-muted-foreground">Special Rule</label>
                                    <ClearableSelect v-model="filterParams.special_rule" placeholder="Any Rule" :options="special_rules" />
                                </div>
                            </CollapsibleContent>
                        </Collapsible>
                        <Collapsible :open="sectionsOpen.sorting" @update:open="toggleSection('sorting')">
                            <CollapsibleTrigger
                                class="flex w-full items-center justify-between rounded-md bg-secondary px-3 py-2 text-sm font-medium hover:bg-secondary/80"
                            >
                                Sorting
                                <ChevronDown class="h-4 w-4 transition-transform" :class="{ 'rotate-180': sectionsOpen.sorting }" />
                            </CollapsibleTrigger>
                            <CollapsibleContent class="space-y-3 px-1 pt-3">
                                <div class="space-y-1">
                                    <label class="text-xs font-medium text-muted-foreground">Sort By</label>
                                    <ClearableSelect v-model="filterParams.sort" placeholder="Sort By" :options="sort_options" />
                                </div>
                                <div class="space-y-1">
                                    <label class="text-xs font-medium text-muted-foreground">Direction</label>
                                    <ClearableSelect v-model="filterParams.sort_type" placeholder="Direction" :options="sort_types" />
                                </div>
                            </CollapsibleContent>
                        </Collapsible>
                        <div class="flex gap-2 pt-2">
                            <Button class="flex-1" @click="filter">Search</Button>
                            <Button variant="outline" class="flex-1" @click="clear">Clear</Button>
                        </div>
                    </div>
                </aside>

                <!-- Results -->
                <div class="min-w-0 flex-1">
                    <div v-if="isLoading && filterParams.page_view === 'table'" class="overflow-auto">
                        <TableSkeleton :rows="8" :cols="5" />
                    </div>
                    <div v-else-if="isLoading" class="grid gap-4 grid-cols-2 md:grid-cols-3 lg:grid-cols-4">
                        <CardSkeleton v-for="n in 8" :key="`skeleton-${n}`" />
                    </div>
                    <div v-else-if="filterParams.page_view === 'table' && units.length" class="overflow-auto rounded-md border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Name</TableHead>
                                    <TableHead>Title</TableHead>
                                    <TableHead>Scrip</TableHead>
                                    <TableHead>Tactics</TableHead>
                                    <TableHead>Rules</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="u in units" :key="u.id">
                                    <TableCell class="font-medium">
                                        <Link
                                            v-if="u.sculpts[0]"
                                            :href="route('tos.units.view', u.sculpts[0].slug)"
                                            class="hover:underline"
                                        >
                                            {{ u.name }}
                                        </Link>
                                        <span v-else>{{ u.name }}</span>
                                    </TableCell>
                                    <TableCell class="text-xs italic text-muted-foreground">{{ u.title ?? '—' }}</TableCell>
                                    <TableCell class="text-xs">
                                        <span
                                            v-if="u.special_unit_rules.some((r) => r.slug === 'commander')"
                                            class="tabular-nums font-medium text-emerald-700 dark:text-emerald-400"
                                        >+{{ u.scrip }}</span>
                                        <span v-else class="tabular-nums">{{ u.scrip }}</span>
                                    </TableCell>
                                    <TableCell class="text-xs tabular-nums">{{ u.tactics ?? '—' }}</TableCell>
                                    <TableCell class="text-xs">
                                        <span v-for="r in u.special_unit_rules" :key="r.id" class="mr-1">{{ r.name }}</span>
                                        <span v-if="!u.special_unit_rules.length" class="text-muted-foreground">—</span>
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>
                    <div v-else-if="units.length" class="grid gap-4 grid-cols-2 md:grid-cols-3 lg:grid-cols-4">
                        <Card
                            v-for="u in units"
                            :key="u.id"
                            class="h-full overflow-hidden transition-all duration-200 ease-out hover:-translate-y-0.5 hover:border-primary/30 hover:shadow-lg hover:shadow-black/10"
                        >
                            <FlipCard
                                :front-image="u.sculpts[0]?.front_image"
                                :back-image="u.sculpts[0]?.back_image"
                                :front-alt="`${u.name} (standard)`"
                                :back-alt="`${u.name} (glory)`"
                                :allegiance-slug="allegiance.slug"
                                :placeholder-icon="Swords"
                                :single-side="!u.sculpts[0]?.back_image"
                            />
                            <CardContent class="space-y-1.5 p-3">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="truncate text-sm font-semibold">{{ u.name }}</span>
                                    <span
                                        v-if="u.special_unit_rules.some((r) => r.slug === 'commander')"
                                        class="shrink-0 text-[11px] tabular-nums font-medium text-emerald-700 dark:text-emerald-400"
                                        title="Provides starting Scrip budget"
                                    >+{{ u.scrip }}</span>
                                    <span v-else class="shrink-0 text-[11px] tabular-nums text-muted-foreground">{{ u.scrip }}</span>
                                </div>
                                <p v-if="u.title" class="truncate text-[11px] italic text-muted-foreground">{{ u.title }}</p>
                                <div class="flex flex-wrap gap-1">
                                    <Badge v-if="u.restriction" variant="outline" class="text-[10px] capitalize">Neutral</Badge>
                                    <Badge v-for="r in u.special_unit_rules" :key="r.id" variant="outline" class="text-[10px]">{{ r.name }}</Badge>
                                </div>
                                <div v-if="u.sculpts[0]" class="pt-1">
                                    <Button
                                        size="sm"
                                        variant="link"
                                        class="h-6 px-0 text-[11px]"
                                        @click="router.get(route('tos.units.view', u.sculpts[0].slug))"
                                    >
                                        View Unit Page
                                    </Button>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                    <EmptyState v-else :icon="Swords" title="No units match" description="Try clearing the filter or attaching units to this allegiance." />
                </div>
            </div>
        </div>
    </div>
</template>
