<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ref, computed, onMounted } from 'vue';
import { useStaggeredEntry } from '@/composables/useStaggeredEntry';
import { LayoutGrid, List, BookOpen, Search, X, ChevronDown } from 'lucide-vue-next';

import { cleanObject } from '@/composables/CleanObject';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import ClearableSelect from '@/components/ClearableSelect.vue';
import CustomMultiselect from '@/components/CustomMultiselect.vue';
import CharacterCardView from '@/components/CharacterCardView.vue';
import CharacterView from '@/components/CharacterView.vue';
import CharacterTable from '@/components/CharacterTable.vue';
import PageBanner from '@/components/PageBanner.vue';
import FilterPanel from '@/components/FilterPanel.vue';
import EmptyState from '@/components/EmptyState.vue';
import CardSkeleton from '@/components/CardSkeleton.vue';
import TableSkeleton from '@/components/TableSkeleton.vue';
import InertiaPagination from '@/components/InertiaPagination.vue';
import { Badge } from '@/components/ui/badge';

const booleanOptions = [
    { name: 'Yes', value: 'true' },
    { name: 'No', value: 'false' },
];

const props = defineProps({
    characters: {
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
    factions: {
        type: [Object, Array],
        required: false,
        default() {
            return {};
        },
    },
    stations: {
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
    base_sizes: {
        type: [Object, Array],
        required: false,
        default() {
            return {};
        },
    },
    keywords: {
        type: [Object, Array],
        required: false,
        default() {
            return {};
        },
    },
    characteristics: {
        type: [Object, Array],
        required: false,
        default() {
            return {};
        },
    },
    actions: {
        type: [Object, Array],
        required: false,
        default() {
            return {};
        },
    },
    abilities: {
        type: [Object, Array],
        required: false,
        default() {
            return {};
        },
    },
    sort_options: {
        type: [Object, Array],
        required: false,
        default() {
            return {};
        },
    },
    sort_types: {
        type: [Object, Array],
        required: false,
        default() {
            return {};
        },
    },
    view_options: {
        type: [Object, Array],
        required: false,
        default() {
            return {};
        },
    },
});

const selectedFactions = ref<string[]>([]);

const filterParams = ref({
    name: null as string | null,
    station: null as string | null,
    base: null as string | null,
    defense_suit: null as string | null,
    willpower_suit: null as string | null,
    cost_min: null as string | null,
    cost_max: null as string | null,
    health_min: null as string | null,
    health_max: null as string | null,
    speed_min: null as string | null,
    speed_max: null as string | null,
    defense_min: null as string | null,
    defense_max: null as string | null,
    willpower_min: null as string | null,
    willpower_max: null as string | null,
    size_min: null as string | null,
    size_max: null as string | null,
    generates_stone: null as string | null,
    is_unhirable: null as string | null,
    is_beta: null as string | null,
    keyword: null as string | null,
    characteristic: null as string | null,
    action: null as string | null,
    ability: null as string | null,
    page_view: null as string | null,
    sort: null as string | null,
    sort_type: null as string | null,
});

const filterKeys = [
    'name',
    'station',
    'base',
    'defense_suit',
    'willpower_suit',
    'cost_min',
    'cost_max',
    'health_min',
    'health_max',
    'speed_min',
    'speed_max',
    'defense_min',
    'defense_max',
    'willpower_min',
    'willpower_max',
    'size_min',
    'size_max',
    'generates_stone',
    'is_unhirable',
    'is_beta',
    'keyword',
    'characteristic',
    'action',
    'ability',
] as const;

const statFields = ['cost', 'health', 'speed', 'defense', 'willpower', 'size'] as const;

const factionNameToValue = (name: string): string | undefined => {
    const match = props.factions.find((f: { name: string; value: string }) => f.name === name);
    return match?.value;
};

const factionValueToName = (value: string): string | undefined => {
    const match = props.factions.find((f: { name: string; value: string }) => f.value === value);
    return match?.name;
};

const activeFilterCount = computed(() => {
    const paramCount = filterKeys.filter((key) => filterParams.value[key] != null && filterParams.value[key] !== '').length;
    return paramCount + (selectedFactions.value.length > 0 ? 1 : 0);
});

const filter = () => {
    const params: Record<string, string | null> = { ...filterParams.value };
    // Normalize empty strings to null so cleanObject removes them
    for (const key in params) {
        if (params[key] === '') {
            params[key] = null;
        }
    }
    // Convert faction names to comma-separated values
    if (selectedFactions.value.length > 0) {
        params.faction = selectedFactions.value.map((name) => factionNameToValue(name)).filter(Boolean).join(',');
    } else {
        params.faction = null;
    }
    // Reset to page 1 on any filter change
    params.page = null;
    router.get(route('search.view'), cleanObject(params), {
        only: ['characters', 'result_count'],
        replace: true,
        preserveState: true,
    });
};

const clear = () => {
    for (const key of filterKeys) {
        filterParams.value[key] = null;
    }
    selectedFactions.value = [];
    filterParams.value.page_view = 'images';
    filterParams.value.sort = 'name';
    filterParams.value.sort_type = 'ascending';
    filter();
};

const clearStat = (stat: string) => {
    filterParams.value[`${stat}_min` as keyof typeof filterParams.value] = null;
    filterParams.value[`${stat}_max` as keyof typeof filterParams.value] = null;
};

const hasStatValue = (stat: string) => {
    return filterParams.value[`${stat}_min` as keyof typeof filterParams.value] || filterParams.value[`${stat}_max` as keyof typeof filterParams.value];
};

const handleViewChange = (value: string) => {
    filterParams.value.page_view = value;
    filter();
};

const handleNameKeydown = (e: KeyboardEvent) => {
    if (e.key === 'Enter') {
        filter();
    }
};

const clearName = () => {
    filterParams.value.name = null;
    filter();
};

const sectionsOpen = ref({
    advanced: false,
    advancedStats: false,
    advancedBaseSuits: false,
    advancedFlags: false,
    advancedActionsAbilities: false,
    sorting: false,
});

const urlParams = new URLSearchParams(window.location.search);
onMounted(() => {
    filterParams.value.name = urlParams.get('name');
    // Restore faction multi-select from comma-separated URL param
    const factionParam = urlParams.get('faction');
    if (factionParam) {
        selectedFactions.value = factionParam
            .split(',')
            .map((v) => factionValueToName(v))
            .filter(Boolean) as string[];
    }
    filterParams.value.station = urlParams.get('station');
    filterParams.value.base = urlParams.get('base');
    filterParams.value.defense_suit = urlParams.get('defense_suit');
    filterParams.value.willpower_suit = urlParams.get('willpower_suit');
    filterParams.value.cost_min = urlParams.get('cost_min');
    filterParams.value.cost_max = urlParams.get('cost_max');
    filterParams.value.health_min = urlParams.get('health_min');
    filterParams.value.health_max = urlParams.get('health_max');
    filterParams.value.speed_min = urlParams.get('speed_min');
    filterParams.value.speed_max = urlParams.get('speed_max');
    filterParams.value.defense_min = urlParams.get('defense_min');
    filterParams.value.defense_max = urlParams.get('defense_max');
    filterParams.value.willpower_min = urlParams.get('willpower_min');
    filterParams.value.willpower_max = urlParams.get('willpower_max');
    filterParams.value.size_min = urlParams.get('size_min');
    filterParams.value.size_max = urlParams.get('size_max');
    filterParams.value.generates_stone = urlParams.get('generates_stone');
    filterParams.value.is_unhirable = urlParams.get('is_unhirable');
    filterParams.value.is_beta = urlParams.get('is_beta');
    filterParams.value.keyword = urlParams.get('keyword');
    filterParams.value.characteristic = urlParams.get('characteristic');
    filterParams.value.action = urlParams.get('action');
    filterParams.value.ability = urlParams.get('ability');
    filterParams.value.page_view = urlParams.get('page_view') ?? 'images';
    filterParams.value.sort = urlParams.get('sort') ?? 'name';
    filterParams.value.sort_type = urlParams.get('sort_type') ?? 'ascending';

    // Auto-open sections that have active filters
    const hasStats =
        filterParams.value.cost_min ||
        filterParams.value.cost_max ||
        filterParams.value.health_min ||
        filterParams.value.health_max ||
        filterParams.value.speed_min ||
        filterParams.value.speed_max ||
        filterParams.value.defense_min ||
        filterParams.value.defense_max ||
        filterParams.value.willpower_min ||
        filterParams.value.willpower_max ||
        filterParams.value.size_min ||
        filterParams.value.size_max;
    const hasBaseSuits = filterParams.value.base || filterParams.value.defense_suit || filterParams.value.willpower_suit;
    const hasFlags = filterParams.value.generates_stone || filterParams.value.is_unhirable || filterParams.value.is_beta;
    const hasActionsAbilities = filterParams.value.action || filterParams.value.ability;
    if (hasStats || hasBaseSuits || hasFlags || hasActionsAbilities) {
        sectionsOpen.value.advanced = true;
        if (hasStats) sectionsOpen.value.advancedStats = true;
        if (hasBaseSuits) sectionsOpen.value.advancedBaseSuits = true;
        if (hasFlags) sectionsOpen.value.advancedFlags = true;
        if (hasActionsAbilities) sectionsOpen.value.advancedActionsAbilities = true;
    }
    if (filterParams.value.sort !== 'name' || filterParams.value.sort_type !== 'ascending') {
        sectionsOpen.value.sorting = true;
    }
});

const characterCount = computed(() => props.characters?.data?.length ?? 0);
const { delays } = useStaggeredEntry(characterCount);

const isLoading = ref(false);
onMounted(() => {
    router.on('start', () => {
        isLoading.value = true;
    });
    router.on('finish', () => {
        isLoading.value = false;
    });
});
</script>

<template>
    <Head title="Advanced Search" />
    <div class="w-full h-full relative">
        <PageBanner title="Advanced Search" class="mb-2">
            <template #subtitle>
                <div class="px-2 py-0 md:py-2 my-auto text-xs md:text-sm text-muted-foreground md:text-foreground">
                    {{ props.result_count }} {{ props.result_count === 1 ? 'character' : 'characters' }} found
                </div>
            </template>
        </PageBanner>

        <!-- Search bar -->
        <div class="container mx-auto px-4 mb-3">
            <div class="relative">
                <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                <Input
                    v-model="filterParams.name"
                    type="text"
                    placeholder="Search by name or nickname..."
                    class="pl-10 pr-10 border-2 border-primary"
                    @keydown="handleNameKeydown"
                />
                <button
                    v-if="filterParams.name"
                    class="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                    @click="clearName"
                >
                    <X class="h-4 w-4" />
                </button>
            </div>
        </div>

        <!-- Tabs + mobile filter trigger -->
        <div class="container mx-auto flex items-center justify-between px-4 mb-2">
            <Tabs :model-value="filterParams.page_view" @update:model-value="handleViewChange">
                <TabsList>
                    <TabsTrigger value="images">
                        <LayoutGrid class="h-4 w-4" />
                        <span class="hidden sm:inline">Cards</span>
                    </TabsTrigger>
                    <TabsTrigger value="table">
                        <List class="h-4 w-4" />
                        <span class="hidden sm:inline">Table</span>
                    </TabsTrigger>
                    <TabsTrigger value="full">
                        <BookOpen class="h-4 w-4" />
                        <span class="hidden sm:inline">Full</span>
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
                            <!-- General -->
                            <div class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">General</div>
                            <div class="space-y-2">
                                <label class="text-sm font-medium">Factions</label>
                                <CustomMultiselect v-model="selectedFactions" combo-title="Select Factions" :choice-options="props.factions" />
                            </div>
                            <div class="space-y-2">
                                <label class="text-sm font-medium">Station</label>
                                <ClearableSelect
                                    v-model="filterParams.station"
                                    placeholder="Any Station"
                                    :options="props.stations"
                                    trigger-class="border-2 border-primary rounded"
                                />
                            </div>
                            <div class="space-y-2">
                                <label class="text-sm font-medium">Keyword</label>
                                <ClearableSelect
                                    v-model="filterParams.keyword"
                                    placeholder="Any Keyword"
                                    :options="props.keywords"
                                    option-value="slug"
                                    trigger-class="border-2 border-primary rounded"
                                />
                            </div>
                            <div class="space-y-2">
                                <label class="text-sm font-medium">Characteristic</label>
                                <ClearableSelect
                                    v-model="filterParams.characteristic"
                                    placeholder="Any Characteristic"
                                    :options="props.characteristics"
                                    option-value="slug"
                                    trigger-class="border-2 border-primary rounded"
                                />
                            </div>

                            <!-- Advanced -->
                            <div class="text-xs font-semibold uppercase tracking-wide text-muted-foreground pt-2 border-t">Advanced</div>

                            <!-- Stats -->
                            <Collapsible v-model:open="sectionsOpen.advancedStats">
                                <CollapsibleTrigger class="flex w-full items-center justify-between rounded-md bg-muted px-2.5 py-1.5 text-xs font-medium hover:bg-muted/80">
                                    Stats
                                    <ChevronDown class="h-3.5 w-3.5 transition-transform" :class="{ 'rotate-180': sectionsOpen.advancedStats }" />
                                </CollapsibleTrigger>
                                <CollapsibleContent class="space-y-3 pt-2 px-1">
                                    <div v-for="stat in statFields" :key="stat" class="space-y-1">
                                        <label class="text-sm font-medium capitalize">{{ stat }}</label>
                                        <div class="flex gap-2 items-center">
                                            <Input v-model="filterParams[`${stat}_min`]" type="number" placeholder="Min" class="h-8 text-xs border-2 border-primary" />
                                            <Input v-model="filterParams[`${stat}_max`]" type="number" placeholder="Max" class="h-8 text-xs border-2 border-primary" />
                                            <button
                                                class="shrink-0 rounded-sm p-0.5 text-muted-foreground hover:text-foreground"
                                                :class="hasStatValue(stat) ? 'visible' : 'invisible'"
                                                @click="clearStat(stat)"
                                            >
                                                <X class="h-3 w-3" />
                                            </button>
                                        </div>
                                    </div>
                                </CollapsibleContent>
                            </Collapsible>

                            <!-- Base & Suits -->
                            <Collapsible v-model:open="sectionsOpen.advancedBaseSuits">
                                <CollapsibleTrigger class="flex w-full items-center justify-between rounded-md bg-muted px-2.5 py-1.5 text-xs font-medium hover:bg-muted/80">
                                    Base & Suits
                                    <ChevronDown class="h-3.5 w-3.5 transition-transform" :class="{ 'rotate-180': sectionsOpen.advancedBaseSuits }" />
                                </CollapsibleTrigger>
                                <CollapsibleContent class="space-y-3 pt-2 px-1">
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium">Base Size</label>
                                        <ClearableSelect
                                            v-model="filterParams.base"
                                            placeholder="Any Base"
                                            :options="props.base_sizes"
                                            trigger-class="border-2 border-primary rounded"
                                        />
                                    </div>
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium">Defense Suit</label>
                                        <ClearableSelect
                                            v-model="filterParams.defense_suit"
                                            placeholder="Any Suit"
                                            :options="props.suits"
                                            trigger-class="border-2 border-primary rounded"
                                        />
                                    </div>
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium">Willpower Suit</label>
                                        <ClearableSelect
                                            v-model="filterParams.willpower_suit"
                                            placeholder="Any Suit"
                                            :options="props.suits"
                                            trigger-class="border-2 border-primary rounded"
                                        />
                                    </div>
                                </CollapsibleContent>
                            </Collapsible>

                            <!-- Flags -->
                            <Collapsible v-model:open="sectionsOpen.advancedFlags">
                                <CollapsibleTrigger class="flex w-full items-center justify-between rounded-md bg-muted px-2.5 py-1.5 text-xs font-medium hover:bg-muted/80">
                                    Flags
                                    <ChevronDown class="h-3.5 w-3.5 transition-transform" :class="{ 'rotate-180': sectionsOpen.advancedFlags }" />
                                </CollapsibleTrigger>
                                <CollapsibleContent class="space-y-3 pt-2 px-1">
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium">Generates Soulstone</label>
                                        <ClearableSelect
                                            v-model="filterParams.generates_stone"
                                            placeholder="Any"
                                            :options="booleanOptions"
                                            trigger-class="border-2 border-primary rounded"
                                        />
                                    </div>
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium">Unhirable</label>
                                        <ClearableSelect
                                            v-model="filterParams.is_unhirable"
                                            placeholder="Any"
                                            :options="booleanOptions"
                                            trigger-class="border-2 border-primary rounded"
                                        />
                                    </div>
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium">Beta</label>
                                        <ClearableSelect
                                            v-model="filterParams.is_beta"
                                            placeholder="Any"
                                            :options="booleanOptions"
                                            trigger-class="border-2 border-primary rounded"
                                        />
                                    </div>
                                </CollapsibleContent>
                            </Collapsible>

                            <!-- Actions & Abilities -->
                            <Collapsible v-model:open="sectionsOpen.advancedActionsAbilities">
                                <CollapsibleTrigger class="flex w-full items-center justify-between rounded-md bg-muted px-2.5 py-1.5 text-xs font-medium hover:bg-muted/80">
                                    Actions & Abilities
                                    <ChevronDown class="h-3.5 w-3.5 transition-transform" :class="{ 'rotate-180': sectionsOpen.advancedActionsAbilities }" />
                                </CollapsibleTrigger>
                                <CollapsibleContent class="space-y-3 pt-2 px-1">
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium">Action</label>
                                        <ClearableSelect
                                            v-model="filterParams.action"
                                            placeholder="Any Action"
                                            :options="props.actions"
                                            trigger-class="border-2 border-primary rounded"
                                        />
                                    </div>
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium">Ability</label>
                                        <ClearableSelect
                                            v-model="filterParams.ability"
                                            placeholder="Any Ability"
                                            :options="props.abilities"
                                            trigger-class="border-2 border-primary rounded"
                                        />
                                    </div>
                                </CollapsibleContent>
                            </Collapsible>

                            <!-- Sorting -->
                            <div class="text-xs font-semibold uppercase tracking-wide text-muted-foreground pt-2 border-t">Sorting</div>
                            <div class="space-y-2">
                                <label class="text-sm font-medium">Sort By</label>
                                <ClearableSelect
                                    v-model="filterParams.sort"
                                    placeholder="Sort Options"
                                    :options="props.sort_options"
                                    trigger-class="border-2 border-primary rounded"
                                />
                            </div>
                            <div class="space-y-2">
                                <label class="text-sm font-medium">Sort Direction</label>
                                <ClearableSelect
                                    v-model="filterParams.sort_type"
                                    placeholder="Sort Type"
                                    :options="props.sort_types"
                                    trigger-class="border-2 border-primary rounded"
                                />
                            </div>
                        </div>
                    </FilterPanel>
                </div>
            </div>
        </div>

        <!-- Main content area -->
        <div class="container mx-auto px-4">
            <div class="flex gap-6">
                <!-- Desktop sidebar filters -->
                <aside class="hidden md:block w-72 shrink-0">
                    <div class="sticky top-4 space-y-2 pr-2">
                        <!-- General -->
                        <div class="space-y-3 px-1">
                            <div class="space-y-1">
                                <label class="text-xs font-medium text-muted-foreground">Factions</label>
                                <CustomMultiselect v-model="selectedFactions" combo-title="Select Factions" :choice-options="props.factions" />
                            </div>
                            <div class="space-y-1">
                                <label class="text-xs font-medium text-muted-foreground">Station</label>
                                <ClearableSelect v-model="filterParams.station" placeholder="Any Station" :options="props.stations" />
                            </div>
                            <div class="space-y-1">
                                <label class="text-xs font-medium text-muted-foreground">Keyword</label>
                                <ClearableSelect v-model="filterParams.keyword" placeholder="Any Keyword" :options="props.keywords" option-value="slug" />
                            </div>
                            <div class="space-y-1">
                                <label class="text-xs font-medium text-muted-foreground">Characteristic</label>
                                <ClearableSelect
                                    v-model="filterParams.characteristic"
                                    placeholder="Any Characteristic"
                                    :options="props.characteristics"
                                    option-value="slug"
                                />
                            </div>
                        </div>

                        <!-- Advanced -->
                        <Collapsible v-model:open="sectionsOpen.advanced">
                            <CollapsibleTrigger class="flex w-full items-center justify-between rounded-md bg-secondary px-3 py-2 text-sm font-medium hover:bg-secondary/80">
                                Advanced
                                <ChevronDown class="h-4 w-4 transition-transform" :class="{ 'rotate-180': sectionsOpen.advanced }" />
                            </CollapsibleTrigger>
                            <CollapsibleContent class="space-y-2 pt-3 px-1">
                                <!-- Stats -->
                                <Collapsible v-model:open="sectionsOpen.advancedStats">
                                    <CollapsibleTrigger class="flex w-full items-center justify-between rounded-md bg-muted px-2.5 py-1.5 text-xs font-medium hover:bg-muted/80">
                                        Stats
                                        <ChevronDown class="h-3.5 w-3.5 transition-transform" :class="{ 'rotate-180': sectionsOpen.advancedStats }" />
                                    </CollapsibleTrigger>
                                    <CollapsibleContent class="space-y-3 pt-2 px-1">
                                        <div v-for="stat in statFields" :key="stat" class="space-y-1">
                                            <label class="text-xs font-medium text-muted-foreground capitalize">{{ stat }}</label>
                                            <div class="flex gap-2 items-center">
                                                <Input v-model="filterParams[`${stat}_min`]" type="number" placeholder="Min" class="h-8 text-xs border-2 border-primary" />
                                                <Input v-model="filterParams[`${stat}_max`]" type="number" placeholder="Max" class="h-8 text-xs border-2 border-primary" />
                                                <button
                                                    class="shrink-0 rounded-sm p-0.5 text-muted-foreground hover:text-foreground"
                                                    :class="hasStatValue(stat) ? 'visible' : 'invisible'"
                                                    @click="clearStat(stat)"
                                                >
                                                    <X class="h-3 w-3" />
                                                </button>
                                            </div>
                                        </div>
                                    </CollapsibleContent>
                                </Collapsible>

                                <!-- Base & Suits -->
                                <Collapsible v-model:open="sectionsOpen.advancedBaseSuits">
                                    <CollapsibleTrigger class="flex w-full items-center justify-between rounded-md bg-muted px-2.5 py-1.5 text-xs font-medium hover:bg-muted/80">
                                        Base & Suits
                                        <ChevronDown class="h-3.5 w-3.5 transition-transform" :class="{ 'rotate-180': sectionsOpen.advancedBaseSuits }" />
                                    </CollapsibleTrigger>
                                    <CollapsibleContent class="space-y-3 pt-2 px-1">
                                        <div class="space-y-1">
                                            <label class="text-xs font-medium text-muted-foreground">Base Size</label>
                                            <ClearableSelect v-model="filterParams.base" placeholder="Any Base" :options="props.base_sizes" />
                                        </div>
                                        <div class="space-y-1">
                                            <label class="text-xs font-medium text-muted-foreground">Defense Suit</label>
                                            <ClearableSelect v-model="filterParams.defense_suit" placeholder="Any Suit" :options="props.suits" />
                                        </div>
                                        <div class="space-y-1">
                                            <label class="text-xs font-medium text-muted-foreground">Willpower Suit</label>
                                            <ClearableSelect v-model="filterParams.willpower_suit" placeholder="Any Suit" :options="props.suits" />
                                        </div>
                                    </CollapsibleContent>
                                </Collapsible>

                                <!-- Flags -->
                                <Collapsible v-model:open="sectionsOpen.advancedFlags">
                                    <CollapsibleTrigger class="flex w-full items-center justify-between rounded-md bg-muted px-2.5 py-1.5 text-xs font-medium hover:bg-muted/80">
                                        Flags
                                        <ChevronDown class="h-3.5 w-3.5 transition-transform" :class="{ 'rotate-180': sectionsOpen.advancedFlags }" />
                                    </CollapsibleTrigger>
                                    <CollapsibleContent class="space-y-3 pt-2 px-1">
                                        <div class="space-y-1">
                                            <label class="text-xs font-medium text-muted-foreground">Generates Soulstone</label>
                                            <ClearableSelect v-model="filterParams.generates_stone" placeholder="Any" :options="booleanOptions" />
                                        </div>
                                        <div class="space-y-1">
                                            <label class="text-xs font-medium text-muted-foreground">Unhirable</label>
                                            <ClearableSelect v-model="filterParams.is_unhirable" placeholder="Any" :options="booleanOptions" />
                                        </div>
                                        <div class="space-y-1">
                                            <label class="text-xs font-medium text-muted-foreground">Beta</label>
                                            <ClearableSelect v-model="filterParams.is_beta" placeholder="Any" :options="booleanOptions" />
                                        </div>
                                    </CollapsibleContent>
                                </Collapsible>

                                <!-- Actions & Abilities -->
                                <Collapsible v-model:open="sectionsOpen.advancedActionsAbilities">
                                    <CollapsibleTrigger class="flex w-full items-center justify-between rounded-md bg-muted px-2.5 py-1.5 text-xs font-medium hover:bg-muted/80">
                                        Actions & Abilities
                                        <ChevronDown class="h-3.5 w-3.5 transition-transform" :class="{ 'rotate-180': sectionsOpen.advancedActionsAbilities }" />
                                    </CollapsibleTrigger>
                                    <CollapsibleContent class="space-y-3 pt-2 px-1">
                                        <div class="space-y-1">
                                            <label class="text-xs font-medium text-muted-foreground">Action</label>
                                            <ClearableSelect v-model="filterParams.action" placeholder="Any Action" :options="props.actions" />
                                        </div>
                                        <div class="space-y-1">
                                            <label class="text-xs font-medium text-muted-foreground">Ability</label>
                                            <ClearableSelect v-model="filterParams.ability" placeholder="Any Ability" :options="props.abilities" />
                                        </div>
                                    </CollapsibleContent>
                                </Collapsible>
                            </CollapsibleContent>
                        </Collapsible>

                        <!-- Sorting -->
                        <Collapsible v-model:open="sectionsOpen.sorting">
                            <CollapsibleTrigger class="flex w-full items-center justify-between rounded-md bg-secondary px-3 py-2 text-sm font-medium hover:bg-secondary/80">
                                Sorting
                                <ChevronDown class="h-4 w-4 transition-transform" :class="{ 'rotate-180': sectionsOpen.sorting }" />
                            </CollapsibleTrigger>
                            <CollapsibleContent class="space-y-3 pt-3 px-1">
                                <div class="space-y-1">
                                    <label class="text-xs font-medium text-muted-foreground">Sort By</label>
                                    <ClearableSelect v-model="filterParams.sort" placeholder="Sort Options" :options="props.sort_options" />
                                </div>
                                <div class="space-y-1">
                                    <label class="text-xs font-medium text-muted-foreground">Sort Direction</label>
                                    <ClearableSelect v-model="filterParams.sort_type" placeholder="Sort Type" :options="props.sort_types" />
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
                <div class="flex-1 min-w-0">
                    <div v-if="isLoading && filterParams.page_view === 'table'" class="overflow-auto">
                        <TableSkeleton :rows="8" :cols="7" />
                    </div>
                    <div v-else-if="isLoading">
                        <div class="grid grid-cols-2 gap-3 md:grid-cols-3 lg:grid-cols-4 md:gap-4">
                            <CardSkeleton v-for="n in 8" :key="`skeleton-${n}`" />
                        </div>
                    </div>
                    <div v-else-if="filterParams.page_view === 'table'" class="overflow-auto">
                        <CharacterTable :characters="props.characters.data" />
                        <InertiaPagination :paginator="props.characters" />
                    </div>
                    <div v-else-if="filterParams.page_view === 'full'">
                        <template v-if="props.characters?.data?.length">
                            <div v-for="character in props.characters.data" v-bind:key="character.slug">
                                <CharacterView :character="character" :miniature="character.standard_miniatures[0]" />
                            </div>
                        </template>
                        <EmptyState v-else />
                        <InertiaPagination :paginator="props.characters" />
                    </div>
                    <div v-else>
                        <template v-if="props.characters?.data?.length">
                            <div class="grid grid-cols-2 gap-3 md:grid-cols-3 lg:grid-cols-4 md:gap-4">
                                <div
                                    v-for="(character, index) in props.characters.data"
                                    :key="`character-${character.id}`"
                                    class="animate-fade-in-up opacity-0"
                                    :style="delays[index]"
                                >
                                    <CharacterCardView :miniature="character.standard_miniatures[0]" :character-slug="character.slug" />
                                </div>
                            </div>
                        </template>
                        <EmptyState v-else />
                        <InertiaPagination :paginator="props.characters" />
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
