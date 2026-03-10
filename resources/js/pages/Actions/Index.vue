<script setup lang="ts">
import { useStaggeredEntry } from '@/composables/useStaggeredEntry';
import { Head, Link, router } from '@inertiajs/vue3';
import { ChevronDown, LayoutGrid, List, ScrollText, Search, Users, X } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';

import ActionCard from '@/components/ActionCard.vue';
import CardSkeleton from '@/components/CardSkeleton.vue';
import ClearableSelect from '@/components/ClearableSelect.vue';
import EmptyState from '@/components/EmptyState.vue';
import FilterPanel from '@/components/FilterPanel.vue';
import GameIcon from '@/components/GameIcon.vue';
import InertiaPagination from '@/components/InertiaPagination.vue';
import PageBanner from '@/components/PageBanner.vue';
import TableSkeleton from '@/components/TableSkeleton.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { cleanObject } from '@/composables/CleanObject';

const booleanOptions = [
    { name: 'Yes', value: 'true' },
    { name: 'No', value: 'false' },
];

const props = defineProps({
    actions: {
        type: [Object, Array],
        required: false,
        default() {
            return {};
        },
    },
    result_count: {
        type: Number,
        required: false,
        default: 0,
    },
    action_names: {
        type: [Object, Array],
        required: false,
        default() {
            return {};
        },
    },
    action_types: {
        type: [Object, Array],
        required: false,
        default() {
            return {};
        },
    },
    action_range_types: {
        type: [Object, Array],
        required: false,
        default() {
            return {};
        },
    },
    suits: {
        type: [Object, Array],
        required: false,
        default() {
            return {};
        },
    },
    stat_modifiers: {
        type: [Object, Array],
        required: false,
        default() {
            return {};
        },
    },
    resistance_types: {
        type: [Object, Array],
        required: false,
        default() {
            return {};
        },
    },
});

const filterParams = ref({
    name: null as string | null,
    name_search: null as string | null,
    type: null as string | null,
    is_signature: null as string | null,
    costs_stone: null as string | null,
    range_min: null as string | null,
    range_max: null as string | null,
    range_type: null as string | null,
    stat_min: null as string | null,
    stat_max: null as string | null,
    stat_suits: null as string | null,
    stat_modifier: null as string | null,
    resisted_by: null as string | null,
    tn_min: null as string | null,
    tn_max: null as string | null,
    target_suits: null as string | null,
    damage: null as string | null,
    description: null as string | null,
    page_view: null as string | null,
});

const filterKeys = [
    'name',
    'name_search',
    'type',
    'is_signature',
    'costs_stone',
    'range_min',
    'range_max',
    'range_type',
    'stat_min',
    'stat_max',
    'stat_suits',
    'stat_modifier',
    'resisted_by',
    'tn_min',
    'tn_max',
    'target_suits',
    'damage',
    'description',
] as const;

const activeFilterCount = computed(() => {
    return filterKeys.filter((key) => filterParams.value[key] != null && filterParams.value[key] !== '').length;
});

const filter = () => {
    const params: Record<string, string | null> = { ...filterParams.value };
    for (const key in params) {
        if (params[key] === '') {
            params[key] = null;
        }
    }
    params.page = null;
    router.get(route('actions.index'), cleanObject(params), {
        only: ['actions', 'result_count'],
        replace: true,
        preserveState: true,
    });
};

const clear = () => {
    for (const key of filterKeys) {
        filterParams.value[key] = null;
    }
    filterParams.value.page_view = 'cards';
    filter();
};

const handleNameKeydown = (e: KeyboardEvent) => {
    if (e.key === 'Enter') {
        filter();
    }
};

const clearNameSearch = () => {
    filterParams.value.name_search = null;
    filter();
};

const handleViewChange = (value: string) => {
    filterParams.value.page_view = value;
    filter();
};

const sectionsOpen = ref({
    identity: false,
    combat: false,
    targeting: false,
});

type SectionKey = keyof typeof sectionsOpen.value;
const sectionKeys: SectionKey[] = ['identity', 'combat', 'targeting'];

const toggleSection = (section: SectionKey) => {
    const isOpening = !sectionsOpen.value[section];
    for (const key of sectionKeys) {
        sectionsOpen.value[key] = false;
    }
    if (isOpening) {
        sectionsOpen.value[section] = true;
    }
};

const urlParams = new URLSearchParams(window.location.search);
onMounted(() => {
    filterParams.value.name = urlParams.get('name');
    filterParams.value.name_search = urlParams.get('name_search');
    filterParams.value.type = urlParams.get('type');
    filterParams.value.is_signature = urlParams.get('is_signature');
    filterParams.value.costs_stone = urlParams.get('costs_stone');
    filterParams.value.range_min = urlParams.get('range_min');
    filterParams.value.range_max = urlParams.get('range_max');
    filterParams.value.range_type = urlParams.get('range_type');
    filterParams.value.stat_min = urlParams.get('stat_min');
    filterParams.value.stat_max = urlParams.get('stat_max');
    filterParams.value.stat_suits = urlParams.get('stat_suits');
    filterParams.value.stat_modifier = urlParams.get('stat_modifier');
    filterParams.value.resisted_by = urlParams.get('resisted_by');
    filterParams.value.tn_min = urlParams.get('tn_min');
    filterParams.value.tn_max = urlParams.get('tn_max');
    filterParams.value.target_suits = urlParams.get('target_suits');
    filterParams.value.damage = urlParams.get('damage');
    filterParams.value.description = urlParams.get('description');
    filterParams.value.page_view = urlParams.get('page_view') ?? 'cards';

    // Auto-open sections with active filters
    const hasIdentity =
        filterParams.value.name ||
        filterParams.value.name_search ||
        filterParams.value.type ||
        filterParams.value.is_signature ||
        filterParams.value.costs_stone;
    const hasCombat =
        filterParams.value.range_min ||
        filterParams.value.range_max ||
        filterParams.value.range_type ||
        filterParams.value.stat_min ||
        filterParams.value.stat_max ||
        filterParams.value.stat_suits ||
        filterParams.value.stat_modifier ||
        filterParams.value.damage ||
        filterParams.value.description;
    const hasTargeting = filterParams.value.resisted_by || filterParams.value.tn_min || filterParams.value.tn_max || filterParams.value.target_suits;
    if (hasIdentity) sectionsOpen.value.identity = true;
    if (hasCombat) sectionsOpen.value.combat = true;
    if (hasTargeting) sectionsOpen.value.targeting = true;
});

const actionCount = computed(() => props.actions?.data?.length ?? 0);
const { delays } = useStaggeredEntry(actionCount);

const isLoading = ref(false);
onMounted(() => {
    router.on('start', () => {
        isLoading.value = true;
    });
    router.on('finish', () => {
        isLoading.value = false;
    });
});

const formatType = (type: string) => {
    return type ? type.charAt(0).toUpperCase() + type.slice(1) : '';
};

const formatRangeType = (rangeType: string) => {
    return rangeType ? rangeType.charAt(0).toUpperCase() + rangeType.slice(1) : '';
};
</script>

<template>
    <Head title="Actions" />
    <div class="relative">
        <div
            class="pointer-events-none absolute inset-x-0 top-0 h-64 opacity-[0.07] dark:opacity-[0.12]"
            :style="{ background: 'radial-gradient(ellipse at top, hsl(var(--primary)) 0%, transparent 70%)' }"
        />
        <PageBanner title="Action Directory" class="mb-2">
            <template #subtitle>
                <div class="my-auto px-2 py-0 text-xs text-muted-foreground md:py-2 md:text-sm md:text-foreground">
                    {{ props.result_count }} {{ props.result_count === 1 ? 'action' : 'actions' }} found
                </div>
            </template>
        </PageBanner>

        <!-- Search bar -->
        <div class="container mx-auto mb-3 px-4">
            <div class="relative">
                <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                <Input
                    v-model="filterParams.name_search"
                    type="text"
                    placeholder="Search actions by name..."
                    class="border-2 border-primary pl-10 pr-10"
                    @keydown="handleNameKeydown"
                />
                <button
                    v-if="filterParams.name_search"
                    class="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                    @click="clearNameSearch"
                >
                    <X class="h-4 w-4" />
                </button>
            </div>
        </div>

        <!-- Tabs + mobile filter trigger -->
        <div class="container mx-auto mb-2 flex items-center justify-between px-4">
            <Tabs :model-value="filterParams.page_view" @update:model-value="handleViewChange">
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
                <!-- Mobile-only filter trigger -->
                <div class="md:hidden">
                    <FilterPanel :filter-count="activeFilterCount" @filter="filter" @clear="clear">
                        <div class="grid gap-4">
                            <!-- Identity -->
                            <Collapsible :open="sectionsOpen.identity" @update:open="toggleSection('identity')">
                                <CollapsibleTrigger
                                    class="flex w-full items-center justify-between rounded-md bg-muted px-2.5 py-1.5 text-xs font-medium hover:bg-muted/80"
                                >
                                    Identity
                                    <ChevronDown class="h-3.5 w-3.5 transition-transform" :class="{ 'rotate-180': sectionsOpen.identity }" />
                                </CollapsibleTrigger>
                                <CollapsibleContent class="space-y-3 px-1 pt-2">
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium">Name</label>
                                        <ClearableSelect
                                            v-model="filterParams.name"
                                            placeholder="Select Action"
                                            :options="props.action_names"
                                            trigger-class="border-2 border-primary rounded"
                                        />
                                    </div>
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium">Type</label>
                                        <ClearableSelect
                                            v-model="filterParams.type"
                                            placeholder="Any Type"
                                            :options="props.action_types"
                                            trigger-class="border-2 border-primary rounded"
                                        />
                                    </div>
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium">Signature</label>
                                        <ClearableSelect
                                            v-model="filterParams.is_signature"
                                            placeholder="Any"
                                            :options="booleanOptions"
                                            trigger-class="border-2 border-primary rounded"
                                        />
                                    </div>
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium">Costs Soulstone</label>
                                        <ClearableSelect
                                            v-model="filterParams.costs_stone"
                                            placeholder="Any"
                                            :options="booleanOptions"
                                            trigger-class="border-2 border-primary rounded"
                                        />
                                    </div>
                                </CollapsibleContent>
                            </Collapsible>

                            <!-- Combat Stats -->
                            <Collapsible :open="sectionsOpen.combat" @update:open="toggleSection('combat')">
                                <CollapsibleTrigger
                                    class="flex w-full items-center justify-between rounded-md bg-muted px-2.5 py-1.5 text-xs font-medium hover:bg-muted/80"
                                >
                                    Combat Stats
                                    <ChevronDown class="h-3.5 w-3.5 transition-transform" :class="{ 'rotate-180': sectionsOpen.combat }" />
                                </CollapsibleTrigger>
                                <CollapsibleContent class="space-y-3 px-1 pt-2">
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium">Range Type</label>
                                        <ClearableSelect
                                            v-model="filterParams.range_type"
                                            placeholder="Any Range Type"
                                            :options="props.action_range_types"
                                            trigger-class="border-2 border-primary rounded"
                                        />
                                    </div>
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium">Range</label>
                                        <div class="flex items-center gap-2">
                                            <Input
                                                v-model="filterParams.range_min"
                                                type="number"
                                                placeholder="Min"
                                                class="h-8 border-2 border-primary text-xs"
                                            />
                                            <Input
                                                v-model="filterParams.range_max"
                                                type="number"
                                                placeholder="Max"
                                                class="h-8 border-2 border-primary text-xs"
                                            />
                                        </div>
                                    </div>
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium">Stat</label>
                                        <div class="flex items-center gap-2">
                                            <Input
                                                v-model="filterParams.stat_min"
                                                type="number"
                                                placeholder="Min"
                                                class="h-8 border-2 border-primary text-xs"
                                            />
                                            <Input
                                                v-model="filterParams.stat_max"
                                                type="number"
                                                placeholder="Max"
                                                class="h-8 border-2 border-primary text-xs"
                                            />
                                        </div>
                                    </div>
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium">Stat Suit</label>
                                        <ClearableSelect
                                            v-model="filterParams.stat_suits"
                                            placeholder="Any Suit"
                                            :options="props.suits"
                                            trigger-class="border-2 border-primary rounded"
                                        />
                                    </div>
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium">Stat Modifier</label>
                                        <ClearableSelect
                                            v-model="filterParams.stat_modifier"
                                            placeholder="Any Modifier"
                                            :options="props.stat_modifiers"
                                            trigger-class="border-2 border-primary rounded"
                                        />
                                    </div>
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium">Damage</label>
                                        <Input
                                            v-model="filterParams.damage"
                                            type="text"
                                            placeholder="Search damage..."
                                            class="h-8 border-2 border-primary text-xs"
                                        />
                                    </div>
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium">Description</label>
                                        <Input
                                            v-model="filterParams.description"
                                            type="text"
                                            placeholder="Search description..."
                                            class="h-8 border-2 border-primary text-xs"
                                        />
                                    </div>
                                </CollapsibleContent>
                            </Collapsible>

                            <!-- Targeting -->
                            <Collapsible :open="sectionsOpen.targeting" @update:open="toggleSection('targeting')">
                                <CollapsibleTrigger
                                    class="flex w-full items-center justify-between rounded-md bg-muted px-2.5 py-1.5 text-xs font-medium hover:bg-muted/80"
                                >
                                    Targeting
                                    <ChevronDown class="h-3.5 w-3.5 transition-transform" :class="{ 'rotate-180': sectionsOpen.targeting }" />
                                </CollapsibleTrigger>
                                <CollapsibleContent class="space-y-3 px-1 pt-2">
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium">Resisted By</label>
                                        <ClearableSelect
                                            v-model="filterParams.resisted_by"
                                            placeholder="Any"
                                            :options="props.resistance_types"
                                            trigger-class="border-2 border-primary rounded"
                                        />
                                    </div>
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium">Target Number</label>
                                        <div class="flex items-center gap-2">
                                            <Input
                                                v-model="filterParams.tn_min"
                                                type="number"
                                                placeholder="Min"
                                                class="h-8 border-2 border-primary text-xs"
                                            />
                                            <Input
                                                v-model="filterParams.tn_max"
                                                type="number"
                                                placeholder="Max"
                                                class="h-8 border-2 border-primary text-xs"
                                            />
                                        </div>
                                    </div>
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium">Target Suit</label>
                                        <ClearableSelect
                                            v-model="filterParams.target_suits"
                                            placeholder="Any Suit"
                                            :options="props.suits"
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

        <!-- Main content area -->
        <div class="container mx-auto px-4">
            <div class="flex gap-6">
                <!-- Desktop sidebar filters -->
                <aside class="hidden w-72 shrink-0 md:block">
                    <div class="space-y-2 pr-2">
                        <!-- Identity -->
                        <Collapsible :open="sectionsOpen.identity" @update:open="toggleSection('identity')">
                            <CollapsibleTrigger
                                class="flex w-full items-center justify-between rounded-md bg-secondary px-3 py-2 text-sm font-medium hover:bg-secondary/80"
                            >
                                Identity
                                <ChevronDown class="h-4 w-4 transition-transform" :class="{ 'rotate-180': sectionsOpen.identity }" />
                            </CollapsibleTrigger>
                            <CollapsibleContent class="space-y-3 px-1 pt-3">
                                <div class="space-y-1">
                                    <label class="text-xs font-medium text-muted-foreground">Name</label>
                                    <ClearableSelect v-model="filterParams.name" placeholder="Select Action" :options="props.action_names" />
                                </div>
                                <div class="space-y-1">
                                    <label class="text-xs font-medium text-muted-foreground">Type</label>
                                    <ClearableSelect v-model="filterParams.type" placeholder="Any Type" :options="props.action_types" />
                                </div>
                                <div class="space-y-1">
                                    <label class="text-xs font-medium text-muted-foreground">Signature</label>
                                    <ClearableSelect v-model="filterParams.is_signature" placeholder="Any" :options="booleanOptions" />
                                </div>
                                <div class="space-y-1">
                                    <label class="text-xs font-medium text-muted-foreground">Costs Soulstone</label>
                                    <ClearableSelect v-model="filterParams.costs_stone" placeholder="Any" :options="booleanOptions" />
                                </div>
                            </CollapsibleContent>
                        </Collapsible>

                        <!-- Combat Stats -->
                        <Collapsible :open="sectionsOpen.combat" @update:open="toggleSection('combat')">
                            <CollapsibleTrigger
                                class="flex w-full items-center justify-between rounded-md bg-secondary px-3 py-2 text-sm font-medium hover:bg-secondary/80"
                            >
                                Combat Stats
                                <ChevronDown class="h-4 w-4 transition-transform" :class="{ 'rotate-180': sectionsOpen.combat }" />
                            </CollapsibleTrigger>
                            <CollapsibleContent class="space-y-3 px-1 pt-3">
                                <div class="space-y-1">
                                    <label class="text-xs font-medium text-muted-foreground">Range Type</label>
                                    <ClearableSelect
                                        v-model="filterParams.range_type"
                                        placeholder="Any Range Type"
                                        :options="props.action_range_types"
                                    />
                                </div>
                                <div class="space-y-1">
                                    <label class="text-xs font-medium text-muted-foreground">Range</label>
                                    <div class="flex items-center gap-2">
                                        <Input
                                            v-model="filterParams.range_min"
                                            type="number"
                                            placeholder="Min"
                                            class="h-8 border-2 border-primary text-xs"
                                        />
                                        <Input
                                            v-model="filterParams.range_max"
                                            type="number"
                                            placeholder="Max"
                                            class="h-8 border-2 border-primary text-xs"
                                        />
                                    </div>
                                </div>
                                <div class="space-y-1">
                                    <label class="text-xs font-medium text-muted-foreground">Stat</label>
                                    <div class="flex items-center gap-2">
                                        <Input
                                            v-model="filterParams.stat_min"
                                            type="number"
                                            placeholder="Min"
                                            class="h-8 border-2 border-primary text-xs"
                                        />
                                        <Input
                                            v-model="filterParams.stat_max"
                                            type="number"
                                            placeholder="Max"
                                            class="h-8 border-2 border-primary text-xs"
                                        />
                                    </div>
                                </div>
                                <div class="space-y-1">
                                    <label class="text-xs font-medium text-muted-foreground">Stat Suit</label>
                                    <ClearableSelect v-model="filterParams.stat_suits" placeholder="Any Suit" :options="props.suits" />
                                </div>
                                <div class="space-y-1">
                                    <label class="text-xs font-medium text-muted-foreground">Stat Modifier</label>
                                    <ClearableSelect
                                        v-model="filterParams.stat_modifier"
                                        placeholder="Any Modifier"
                                        :options="props.stat_modifiers"
                                    />
                                </div>
                                <div class="space-y-1">
                                    <label class="text-xs font-medium text-muted-foreground">Damage</label>
                                    <Input v-model="filterParams.damage" type="text" placeholder="Search damage..." class="h-8 text-xs" />
                                </div>
                                <div class="space-y-1">
                                    <label class="text-xs font-medium text-muted-foreground">Description</label>
                                    <Input v-model="filterParams.description" type="text" placeholder="Search description..." class="h-8 text-xs" />
                                </div>
                            </CollapsibleContent>
                        </Collapsible>

                        <!-- Targeting -->
                        <Collapsible :open="sectionsOpen.targeting" @update:open="toggleSection('targeting')">
                            <CollapsibleTrigger
                                class="flex w-full items-center justify-between rounded-md bg-secondary px-3 py-2 text-sm font-medium hover:bg-secondary/80"
                            >
                                Targeting
                                <ChevronDown class="h-4 w-4 transition-transform" :class="{ 'rotate-180': sectionsOpen.targeting }" />
                            </CollapsibleTrigger>
                            <CollapsibleContent class="space-y-3 px-1 pt-3">
                                <div class="space-y-1">
                                    <label class="text-xs font-medium text-muted-foreground">Resisted By</label>
                                    <ClearableSelect v-model="filterParams.resisted_by" placeholder="Any" :options="props.resistance_types" />
                                </div>
                                <div class="space-y-1">
                                    <label class="text-xs font-medium text-muted-foreground">Target Number</label>
                                    <div class="flex items-center gap-2">
                                        <Input
                                            v-model="filterParams.tn_min"
                                            type="number"
                                            placeholder="Min"
                                            class="h-8 border-2 border-primary text-xs"
                                        />
                                        <Input
                                            v-model="filterParams.tn_max"
                                            type="number"
                                            placeholder="Max"
                                            class="h-8 border-2 border-primary text-xs"
                                        />
                                    </div>
                                </div>
                                <div class="space-y-1">
                                    <label class="text-xs font-medium text-muted-foreground">Target Suit</label>
                                    <ClearableSelect v-model="filterParams.target_suits" placeholder="Any Suit" :options="props.suits" />
                                </div>
                            </CollapsibleContent>
                        </Collapsible>

                        <!-- Action buttons -->
                        <div class="flex gap-2 pt-2">
                            <Button class="flex-1" @click="filter">Search</Button>
                            <Button variant="outline" class="flex-1" @click="clear">Clear</Button>
                        </div>
                    </div>
                </aside>

                <!-- Results area -->
                <div class="min-w-0 flex-1">
                    <div v-if="isLoading && filterParams.page_view === 'table'" class="overflow-auto">
                        <TableSkeleton :rows="8" :cols="9" />
                    </div>
                    <div v-else-if="isLoading">
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            <CardSkeleton v-for="n in 6" :key="`skeleton-${n}`" />
                        </div>
                    </div>
                    <div v-else-if="filterParams.page_view === 'table'" class="overflow-auto">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Name</TableHead>
                                    <TableHead>Type</TableHead>
                                    <TableHead>Range Type</TableHead>
                                    <TableHead>Range</TableHead>
                                    <TableHead>Stat</TableHead>
                                    <TableHead>Resisted By</TableHead>
                                    <TableHead>TN</TableHead>
                                    <TableHead>Damage</TableHead>
                                    <TableHead>Characters</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <template v-if="props.actions?.data?.length">
                                    <TableRow v-for="action in props.actions.data" :key="action.id">
                                        <TableCell class="font-medium">
                                            <span class="inline-flex items-center gap-1">
                                                <GameIcon v-if="action.costs_stone" type="soulstone" class-name="h-4 inline-block" />
                                                {{ action.name }}
                                            </span>
                                        </TableCell>
                                        <TableCell>
                                            <Badge variant="outline" class="text-xs">{{ formatType(action.type) }}</Badge>
                                        </TableCell>
                                        <TableCell>{{ formatRangeType(action.range_type) }}</TableCell>
                                        <TableCell>{{ action.range ?? '-' }}</TableCell>
                                        <TableCell>
                                            <span v-if="action.stat" class="inline-flex items-center gap-0.5">
                                                {{ action.stat }}
                                                <template v-if="action.stat_suits">
                                                    <GameIcon
                                                        v-for="suit in action.stat_suits.split(' ')"
                                                        :key="suit"
                                                        :type="suit"
                                                        class-name="h-3.5 inline-block"
                                                    />
                                                </template>
                                            </span>
                                            <span v-else>-</span>
                                        </TableCell>
                                        <TableCell>{{ action.resisted_by ?? '-' }}</TableCell>
                                        <TableCell>
                                            <span v-if="action.target_number" class="inline-flex items-center gap-0.5">
                                                {{ action.target_number }}
                                                <template v-if="action.target_suits">
                                                    <GameIcon
                                                        v-for="suit in action.target_suits.split(' ')"
                                                        :key="suit"
                                                        :type="suit"
                                                        class-name="h-3.5 inline-block"
                                                    />
                                                </template>
                                            </span>
                                            <span v-else>-</span>
                                        </TableCell>
                                        <TableCell>{{ action.damage ?? '-' }}</TableCell>
                                        <TableCell>
                                            <span class="inline-flex flex-wrap items-center gap-1">
                                                <Link
                                                    v-if="action.characters_count === 1 && action.characters?.length === 1"
                                                    :href="
                                                        route('characters.view', {
                                                            character: action.characters[0].slug,
                                                            miniature: action.characters[0].standard_miniatures?.[0]?.id,
                                                            slug: action.characters[0].standard_miniatures?.[0]?.slug ?? 'view',
                                                        })
                                                    "
                                                    class="inline-flex items-center gap-1 text-primary hover:underline"
                                                >
                                                    <Users class="h-3 w-3 shrink-0" />
                                                    {{ action.characters[0].display_name }}
                                                </Link>
                                                <Link
                                                    v-else-if="action.characters_count > 1"
                                                    :href="route('search.view', { action: action.name })"
                                                    class="inline-flex items-center gap-1 text-primary hover:underline"
                                                >
                                                    <Users class="h-3 w-3 shrink-0" />
                                                    {{ action.characters_count }}
                                                </Link>
                                                <template v-else-if="action.upgrades?.length">
                                                    <ScrollText class="h-3 w-3 shrink-0 text-muted-foreground" />
                                                    <Link
                                                        v-for="upgrade in action.upgrades"
                                                        :key="upgrade.slug"
                                                        :href="route('upgrades.view', upgrade.slug)"
                                                        class="text-primary hover:underline"
                                                    >
                                                        {{ upgrade.name }}
                                                    </Link>
                                                </template>
                                                <span v-else class="text-muted-foreground">0</span>
                                            </span>
                                        </TableCell>
                                    </TableRow>
                                </template>
                                <template v-else>
                                    <TableRow>
                                        <TableCell :colspan="9">
                                            <EmptyState />
                                        </TableCell>
                                    </TableRow>
                                </template>
                            </TableBody>
                        </Table>
                        <InertiaPagination :paginator="props.actions" :only="['actions', 'result_count']" />
                    </div>
                    <div v-else>
                        <template v-if="props.actions?.data?.length">
                            <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                                <ActionCard
                                    v-for="(action, index) in props.actions.data"
                                    :key="action.id"
                                    :action="action"
                                    class="animate-fade-in-up opacity-0 transition-all duration-300 hover:-translate-y-1 hover:shadow-xl"
                                    :style="delays[index]"
                                >
                                    <template #footer>
                                        <template v-if="action.characters_count === 1 && action.characters?.length === 1">
                                            <Link
                                                :href="
                                                    route('characters.view', {
                                                        character: action.characters[0].slug,
                                                        miniature: action.characters[0].standard_miniatures?.[0]?.id,
                                                        slug: action.characters[0].standard_miniatures?.[0]?.slug ?? 'view',
                                                    })
                                                "
                                                class="text-primary hover:underline"
                                            >
                                                {{ action.characters[0].display_name }}
                                            </Link>
                                        </template>
                                        <Link
                                            v-else-if="action.characters_count > 1"
                                            :href="route('search.view', { action: action.name })"
                                            class="text-primary hover:underline"
                                        >
                                            {{ action.characters_count }} characters
                                        </Link>
                                        <template v-else-if="action.upgrades?.length">
                                            <Link
                                                v-for="upgrade in action.upgrades"
                                                :key="upgrade.slug"
                                                :href="route('upgrades.view', upgrade.slug)"
                                                class="text-primary hover:underline"
                                            >
                                                {{ upgrade.name }}
                                            </Link>
                                        </template>
                                        <span v-else class="text-muted-foreground">0 characters</span>
                                    </template>
                                </ActionCard>
                            </div>
                        </template>
                        <EmptyState v-else />
                        <InertiaPagination :paginator="props.actions" :only="['actions', 'result_count']" />
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
