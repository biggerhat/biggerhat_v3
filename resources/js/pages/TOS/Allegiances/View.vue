<script setup lang="ts">
import AllegianceLogo from '@/components/AllegianceLogo.vue';
import CardSkeleton from '@/components/CardSkeleton.vue';
import ClearableSelect from '@/components/ClearableSelect.vue';
import EmptyState from '@/components/EmptyState.vue';
import FilterPanel from '@/components/FilterPanel.vue';
import PageBanner from '@/components/PageBanner.vue';
import TableSkeleton from '@/components/TableSkeleton.vue';
import CardImage from '@/components/TOS/CardImage.vue';
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
import { ArrowLeft, ChevronDown, LayoutGrid, List, Newspaper, Package, ScrollText, Swords } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

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

interface AssetLimit {
    id: number;
    limit_type: string;
    parameter_type: string | null;
    parameter_value: string | null;
}

interface Asset {
    id: number;
    slug: string;
    name: string;
    scrip_cost: number;
    image_path: string | null;
    allegiances: Array<{ id: number; slug: string; name: string }>;
    limits: AssetLimit[];
}

interface Stratagem {
    id: number;
    slug: string;
    name: string;
    tactical_cost: number;
    effect: string | null;
    image_path: string | null;
    allegiance_id: number | null;
    allegiance_type: string | null;
    allegiance: { id: number; slug: string; name: string; color_slug: string | null } | null;
}

interface AllegianceCardItem {
    id: number;
    slug: string;
    name: string;
    image_path: string | null;
    allegiance_id: number | null;
    allegiance: { id: number; slug: string; name: string; color_slug: string | null } | null;
}

type ResourceType = 'all' | 'units' | 'assets' | 'stratagems' | 'allegiance_cards';

const props = defineProps<{
    allegiance: Allegiance;
    units: Unit[];
    assets: Asset[];
    stratagems: Stratagem[];
    allegiance_cards: AllegianceCardItem[];
    statistics: Statistics;
    special_rule: string | null;
    page_view: string;
    resource_type: ResourceType;
    sort: string;
    sort_type: string;
    special_rules: TosSelectOption[];
    sort_options: TosSelectOption[];
    sort_types: TosSelectOption[];
}>();

const filterParams = ref({
    special_rule: props.special_rule,
    page_view: props.page_view,
    resource_type: props.resource_type,
    sort: props.sort,
    sort_type: props.sort_type,
});

const filterKeys = ['special_rule'] as const;

const activeFilterCount = computed(() => filterKeys.filter((k) => filterParams.value[k] != null && filterParams.value[k] !== '').length);

// `viewByType` (Earth/Malifaux Side) mounts the same component with a synthetic
// allegiance whose id is 0 — route back through the type endpoint so the
// controller knows to recompute the type-pool unions.
const isTypePool = computed(() => props.allegiance.id === 0);
const reloadOnly = ['units', 'assets', 'stratagems', 'allegiance_cards', 'statistics', 'special_rule', 'page_view', 'resource_type', 'sort', 'sort_type'];

const filter = () => {
    const target = isTypePool.value
        ? route('tos.allegiances.viewByType', props.allegiance.type)
        : route('tos.allegiances.view', props.allegiance.slug);
    router.get(target, cleanObject(filterParams.value), {
        only: reloadOnly,
        replace: true,
        preserveState: true,
        preserveScroll: true,
    });
};

const clear = () => {
    filterParams.value.special_rule = null;
    filterParams.value.page_view = 'cards';
    filterParams.value.resource_type = 'all';
    filterParams.value.sort = 'name';
    filterParams.value.sort_type = 'ascending';
    filter();
};

const handleViewChange = (value: string) => {
    filterParams.value.page_view = value;
    filter();
};

const handleResourceChange = (value: string) => {
    filterParams.value.resource_type = value as ResourceType;
    filter();
};

const resourceTabsList: Array<{ key: ResourceType; label: string }> = [
    { key: 'all', label: 'All' },
    { key: 'units', label: 'Units' },
    { key: 'assets', label: 'Assets' },
    { key: 'stratagems', label: 'Stratagems' },
    { key: 'allegiance_cards', label: 'Cards' },
];

const showUnits = computed(() => filterParams.value.resource_type === 'all' || filterParams.value.resource_type === 'units');
const showAssets = computed(() => filterParams.value.resource_type === 'all' || filterParams.value.resource_type === 'assets');
const showStratagems = computed(() => filterParams.value.resource_type === 'all' || filterParams.value.resource_type === 'stratagems');
const showCards = computed(() => filterParams.value.resource_type === 'all' || filterParams.value.resource_type === 'allegiance_cards');
const showUnitFilters = computed(() => filterParams.value.resource_type === 'units');
const showViewToggle = computed(() => filterParams.value.resource_type === 'units');

const stratagemScopeLabel = (s: Stratagem): string => {
    if (s.allegiance) return s.allegiance.name;
    if (s.allegiance_type) return `Any ${s.allegiance_type} allegiance`;
    return 'Universal';
};

const limitLabel = (l: AssetLimit): string => {
    const head = l.limit_type.charAt(0).toUpperCase() + l.limit_type.slice(1);
    return l.parameter_value ? `${head} (${l.parameter_value})` : head;
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
let offStart: (() => void) | null = null;
let offFinish: (() => void) | null = null;

onMounted(() => {
    offStart = router.on('start', () => {
        isLoading.value = true;
    });
    offFinish = router.on('finish', () => {
        isLoading.value = false;
    });
    if (filterParams.value.sort !== 'name' || filterParams.value.sort_type !== 'ascending') {
        sectionsOpen.value.sorting = true;
    }
});

onBeforeUnmount(() => {
    offStart?.();
    offFinish?.();
    offStart = null;
    offFinish = null;
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

        <!-- Tabs + mobile filter trigger — sticky so users can switch view/filter while scrolling -->
        <div class="sticky top-0 z-20 mb-2 border-b bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/80">
        <div class="container mx-auto flex flex-wrap items-center justify-between gap-2 py-2 sm:px-4">
            <div class="flex flex-wrap items-center gap-2">
                <Tabs :model-value="filterParams.resource_type" @update:model-value="(v) => handleResourceChange(v as string)">
                    <TabsList>
                        <TabsTrigger v-for="t in resourceTabsList" :key="t.key" :value="t.key">{{ t.label }}</TabsTrigger>
                    </TabsList>
                </Tabs>
                <Tabs
                    v-if="showViewToggle"
                    :model-value="filterParams.page_view"
                    @update:model-value="(v) => handleViewChange(v as string)"
                >
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
            </div>
            <div class="flex items-center gap-2">
                <Badge v-if="activeFilterCount > 0" variant="secondary" class="text-xs">
                    {{ activeFilterCount }} {{ activeFilterCount === 1 ? 'filter' : 'filters' }}
                </Badge>
                <div v-if="showUnitFilters || filterParams.resource_type === 'all'" class="md:hidden">
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
        </div>

        <!-- Main content -->
        <div class="container mx-auto sm:px-4">
            <Link :href="route('tos.allegiances.index')" class="mb-2 inline-flex items-center gap-1 text-xs text-muted-foreground hover:text-foreground">
                <ArrowLeft class="size-3" /> All allegiances
            </Link>

            <div class="flex gap-6">
                <!-- Desktop sidebar filters — only relevant when units are visible -->
                <aside
                    v-if="showUnitFilters || filterParams.resource_type === 'all'"
                    class="hidden w-56 shrink-0 md:block lg:w-72"
                >
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
                <div class="min-w-0 flex-1 space-y-6">
                    <!-- Loading skeletons -->
                    <template v-if="isLoading">
                        <div v-if="filterParams.page_view === 'table' && showUnits" class="overflow-auto">
                            <TableSkeleton :rows="8" :cols="5" />
                        </div>
                        <div v-else class="grid gap-4 grid-cols-2 md:grid-cols-3 lg:grid-cols-4">
                            <CardSkeleton v-for="n in 8" :key="`skeleton-${n}`" />
                        </div>
                    </template>

                    <template v-else>
                        <!-- ── Units ─────────────────────────────────────────── -->
                        <section v-if="showUnits">
                            <header v-if="filterParams.resource_type === 'all'" class="mb-3 flex items-baseline gap-2">
                                <Swords class="size-4 text-muted-foreground" aria-hidden="true" />
                                <h2 class="text-sm font-semibold uppercase tracking-wider text-muted-foreground">Units</h2>
                                <Badge variant="secondary" class="text-[10px]">{{ units.length }}</Badge>
                            </header>
                            <div v-if="filterParams.page_view === 'table' && units.length" class="overflow-auto rounded-md border">
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
                            <EmptyState
                                v-else-if="filterParams.resource_type === 'units'"
                                :icon="Swords"
                                title="No units match"
                                description="Try clearing the filter or attaching units to this allegiance."
                            />
                            <p v-else class="text-xs text-muted-foreground">No units in this pool.</p>
                        </section>

                        <!-- ── Assets ────────────────────────────────────────── -->
                        <section v-if="showAssets">
                            <header v-if="filterParams.resource_type === 'all'" class="mb-3 flex items-baseline gap-2">
                                <Package class="size-4 text-muted-foreground" aria-hidden="true" />
                                <h2 class="text-sm font-semibold uppercase tracking-wider text-muted-foreground">Assets</h2>
                                <Badge variant="secondary" class="text-[10px]">{{ assets.length }}</Badge>
                            </header>
                            <div v-if="assets.length" class="grid gap-4 grid-cols-2 md:grid-cols-3 lg:grid-cols-4">
                                <Link
                                    v-for="a in assets"
                                    :key="a.id"
                                    :href="route('tos.assets.view', a.slug)"
                                    class="block rounded-lg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                                >
                                    <Card class="h-full overflow-hidden transition-all duration-200 ease-out hover:-translate-y-0.5 hover:border-primary/30 hover:shadow-lg hover:shadow-black/10">
                                        <CardImage
                                            :src="a.image_path"
                                            :alt="a.name"
                                            :allegiance-slug="a.allegiances[0]?.slug ?? allegiance.slug"
                                            :placeholder-icon="Package"
                                            rounded-class=""
                                        />
                                        <CardContent class="space-y-1.5 p-3">
                                            <div class="flex items-center justify-between gap-2">
                                                <span class="truncate text-sm font-semibold">{{ a.name }}</span>
                                                <span class="shrink-0 text-[11px] tabular-nums text-muted-foreground">{{ a.scrip_cost }}</span>
                                            </div>
                                            <div v-if="a.limits.length" class="flex flex-wrap gap-1">
                                                <Badge
                                                    v-for="l in a.limits"
                                                    :key="l.id"
                                                    variant="outline"
                                                    class="text-[10px] capitalize"
                                                >{{ limitLabel(l) }}</Badge>
                                            </div>
                                        </CardContent>
                                    </Card>
                                </Link>
                            </div>
                            <EmptyState
                                v-else-if="filterParams.resource_type === 'assets'"
                                :icon="Package"
                                title="No assets"
                                description="No assets are attached to this allegiance yet."
                            />
                            <p v-else class="text-xs text-muted-foreground">No assets attached.</p>
                        </section>

                        <!-- ── Stratagems ────────────────────────────────────── -->
                        <section v-if="showStratagems">
                            <header v-if="filterParams.resource_type === 'all'" class="mb-3 flex items-baseline gap-2">
                                <Newspaper class="size-4 text-muted-foreground" aria-hidden="true" />
                                <h2 class="text-sm font-semibold uppercase tracking-wider text-muted-foreground">Stratagems</h2>
                                <Badge variant="secondary" class="text-[10px]">{{ stratagems.length }}</Badge>
                            </header>
                            <div v-if="stratagems.length" class="grid gap-4 grid-cols-2 md:grid-cols-3 lg:grid-cols-4">
                                <Link
                                    v-for="s in stratagems"
                                    :key="s.id"
                                    :href="route('tos.stratagems.view', s.slug)"
                                    class="block rounded-lg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                                >
                                    <Card class="h-full overflow-hidden transition-all duration-200 ease-out hover:-translate-y-0.5 hover:border-primary/30 hover:shadow-lg hover:shadow-black/10">
                                        <CardImage
                                            :src="s.image_path"
                                            :alt="s.name"
                                            :allegiance-slug="s.allegiance?.slug ?? allegiance.slug"
                                            :placeholder-icon="Newspaper"
                                            rounded-class=""
                                        />
                                        <CardContent class="space-y-1.5 p-3">
                                            <div class="flex items-center justify-between gap-2">
                                                <span class="truncate text-sm font-semibold">{{ s.name }}</span>
                                                <Badge variant="outline" class="shrink-0 text-[10px] tabular-nums">{{ s.tactical_cost }}T</Badge>
                                            </div>
                                            <p class="truncate text-[10px] capitalize text-muted-foreground">{{ stratagemScopeLabel(s) }}</p>
                                            <p v-if="s.effect" class="line-clamp-2 text-xs text-muted-foreground"><TosText :text="s.effect" /></p>
                                        </CardContent>
                                    </Card>
                                </Link>
                            </div>
                            <EmptyState
                                v-else-if="filterParams.resource_type === 'stratagems'"
                                :icon="Newspaper"
                                title="No stratagems"
                                description="No stratagems available for this allegiance yet."
                            />
                            <p v-else class="text-xs text-muted-foreground">No stratagems available.</p>
                        </section>

                        <!-- ── Allegiance Cards ──────────────────────────────── -->
                        <section v-if="showCards">
                            <header v-if="filterParams.resource_type === 'all'" class="mb-3 flex items-baseline gap-2">
                                <ScrollText class="size-4 text-muted-foreground" aria-hidden="true" />
                                <h2 class="text-sm font-semibold uppercase tracking-wider text-muted-foreground">Allegiance Cards</h2>
                                <Badge variant="secondary" class="text-[10px]">{{ allegiance_cards.length }}</Badge>
                            </header>
                            <div v-if="allegiance_cards.length" class="grid gap-4 grid-cols-2 md:grid-cols-3 lg:grid-cols-4">
                                <Link
                                    v-for="c in allegiance_cards"
                                    :key="c.id"
                                    :href="route('tos.allegiance_cards.view', c.slug)"
                                    class="block rounded-lg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                                >
                                    <Card class="h-full overflow-hidden transition-all duration-200 ease-out hover:-translate-y-0.5 hover:border-primary/30 hover:shadow-lg hover:shadow-black/10">
                                        <CardImage
                                            :src="c.image_path"
                                            :alt="c.name"
                                            :allegiance-slug="c.allegiance?.slug ?? allegiance.slug"
                                            :placeholder-icon="ScrollText"
                                            rounded-class=""
                                        />
                                        <CardContent class="space-y-1.5 p-3">
                                            <span class="block truncate text-sm font-semibold">{{ c.name }}</span>
                                            <p v-if="c.allegiance" class="truncate text-[10px] text-muted-foreground">{{ c.allegiance.name }}</p>
                                        </CardContent>
                                    </Card>
                                </Link>
                            </div>
                            <EmptyState
                                v-else-if="filterParams.resource_type === 'allegiance_cards'"
                                :icon="ScrollText"
                                title="No allegiance cards"
                                description="No allegiance cards have been created for this allegiance."
                            />
                            <p v-else class="text-xs text-muted-foreground">No allegiance cards.</p>
                        </section>
                    </template>
                </div>
            </div>
        </div>
    </div>
</template>
