<script setup lang="ts">
import { useStaggeredEntry } from '@/composables/useStaggeredEntry';
import { Head, router } from '@inertiajs/vue3';
import { BookOpen, ChevronDown, LayoutGrid, List, Search, X } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';

import CardSkeleton from '@/components/CardSkeleton.vue';
import CharacterCardView from '@/components/CharacterCardView.vue';
import CharacterTable from '@/components/CharacterTable.vue';
import CharacterView from '@/components/CharacterView.vue';
import ClearableSelect from '@/components/ClearableSelect.vue';
import EmptyState from '@/components/EmptyState.vue';
import FilterPanel from '@/components/FilterPanel.vue';
import InertiaPagination from '@/components/InertiaPagination.vue';
import PageBanner from '@/components/PageBanner.vue';
import SearchableMultiselect from '@/components/SearchableMultiselect.vue';
import TableSkeleton from '@/components/TableSkeleton.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { Input } from '@/components/ui/input';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { cleanObject } from '@/composables/CleanObject';

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
    defensive_ability_types: {
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
    action_name: null as string | null,
    action_type: null as string | null,
    action_is_signature: null as string | null,
    action_costs_stone: null as string | null,
    action_range_min: null as string | null,
    action_range_max: null as string | null,
    action_range_type: null as string | null,
    action_stat_min: null as string | null,
    action_stat_max: null as string | null,
    action_stat_suits: null as string | null,
    action_stat_modifier: null as string | null,
    action_resisted_by: null as string | null,
    action_tn_min: null as string | null,
    action_tn_max: null as string | null,
    action_target_suits: null as string | null,
    action_damage: null as string | null,
    action_description: null as string | null,
    ability: null as string | null,
    ability_name: null as string | null,
    ability_suits: null as string | null,
    ability_defensive_type: null as string | null,
    ability_costs_stone: null as string | null,
    ability_description: null as string | null,
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
    'action_name',
    'action_type',
    'action_is_signature',
    'action_costs_stone',
    'action_range_min',
    'action_range_max',
    'action_range_type',
    'action_stat_min',
    'action_stat_max',
    'action_stat_suits',
    'action_stat_modifier',
    'action_resisted_by',
    'action_tn_min',
    'action_tn_max',
    'action_target_suits',
    'action_damage',
    'action_description',
    'ability',
    'ability_name',
    'ability_suits',
    'ability_defensive_type',
    'ability_costs_stone',
    'ability_description',
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
        params.faction = selectedFactions.value
            .map((name) => factionNameToValue(name))
            .filter(Boolean)
            .join(',');
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
    return (
        filterParams.value[`${stat}_min` as keyof typeof filterParams.value] || filterParams.value[`${stat}_max` as keyof typeof filterParams.value]
    );
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

const advancedSubSections = ['advancedStats', 'advancedBaseSuits', 'advancedFlags', 'advancedActions', 'advancedAbilities'] as const;

const sectionsOpen = ref({
    advanced: false,
    advancedStats: false,
    advancedBaseSuits: false,
    advancedFlags: false,
    advancedActions: false,
    advancedAbilities: false,
    sorting: false,
});

const toggleAdvancedSection = (section: (typeof advancedSubSections)[number]) => {
    const isOpening = !sectionsOpen.value[section];
    for (const key of advancedSubSections) {
        sectionsOpen.value[key] = false;
    }
    if (isOpening) {
        sectionsOpen.value[section] = true;
    }
};

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
    filterParams.value.action_name = urlParams.get('action_name');
    filterParams.value.action_type = urlParams.get('action_type');
    filterParams.value.action_is_signature = urlParams.get('action_is_signature');
    filterParams.value.action_costs_stone = urlParams.get('action_costs_stone');
    filterParams.value.action_range_min = urlParams.get('action_range_min');
    filterParams.value.action_range_max = urlParams.get('action_range_max');
    filterParams.value.action_range_type = urlParams.get('action_range_type');
    filterParams.value.action_stat_min = urlParams.get('action_stat_min');
    filterParams.value.action_stat_max = urlParams.get('action_stat_max');
    filterParams.value.action_stat_suits = urlParams.get('action_stat_suits');
    filterParams.value.action_stat_modifier = urlParams.get('action_stat_modifier');
    filterParams.value.action_resisted_by = urlParams.get('action_resisted_by');
    filterParams.value.action_tn_min = urlParams.get('action_tn_min');
    filterParams.value.action_tn_max = urlParams.get('action_tn_max');
    filterParams.value.action_target_suits = urlParams.get('action_target_suits');
    filterParams.value.action_damage = urlParams.get('action_damage');
    filterParams.value.action_description = urlParams.get('action_description');
    filterParams.value.ability = urlParams.get('ability');
    filterParams.value.ability_name = urlParams.get('ability_name');
    filterParams.value.ability_suits = urlParams.get('ability_suits');
    filterParams.value.ability_defensive_type = urlParams.get('ability_defensive_type');
    filterParams.value.ability_costs_stone = urlParams.get('ability_costs_stone');
    filterParams.value.ability_description = urlParams.get('ability_description');
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
    const hasActions =
        filterParams.value.action ||
        filterParams.value.action_name ||
        filterParams.value.action_type ||
        filterParams.value.action_is_signature ||
        filterParams.value.action_costs_stone ||
        filterParams.value.action_range_min ||
        filterParams.value.action_range_max ||
        filterParams.value.action_range_type ||
        filterParams.value.action_stat_min ||
        filterParams.value.action_stat_max ||
        filterParams.value.action_stat_suits ||
        filterParams.value.action_stat_modifier ||
        filterParams.value.action_resisted_by ||
        filterParams.value.action_tn_min ||
        filterParams.value.action_tn_max ||
        filterParams.value.action_target_suits ||
        filterParams.value.action_damage ||
        filterParams.value.action_description;
    const hasAbilities =
        filterParams.value.ability ||
        filterParams.value.ability_name ||
        filterParams.value.ability_suits ||
        filterParams.value.ability_defensive_type ||
        filterParams.value.ability_costs_stone ||
        filterParams.value.ability_description;
    if (hasStats || hasBaseSuits || hasFlags || hasActions || hasAbilities) {
        sectionsOpen.value.advanced = true;
        if (hasStats) sectionsOpen.value.advancedStats = true;
        if (hasBaseSuits) sectionsOpen.value.advancedBaseSuits = true;
        if (hasFlags) sectionsOpen.value.advancedFlags = true;
        if (hasActions) sectionsOpen.value.advancedActions = true;
        if (hasAbilities) sectionsOpen.value.advancedAbilities = true;
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
    <div class="relative">
        <div
            class="pointer-events-none absolute inset-x-0 top-0 h-64 opacity-[0.07] dark:opacity-[0.12]"
            :style="{ background: 'radial-gradient(ellipse at top, hsl(var(--primary)) 0%, transparent 70%)' }"
        />
        <PageBanner title="Advanced Search" class="mb-2">
            <template #subtitle>
                <div class="my-auto px-2 py-0 text-xs text-muted-foreground md:py-2 md:text-sm md:text-foreground">
                    {{ props.result_count }} {{ props.result_count === 1 ? 'character' : 'characters' }} found
                </div>
            </template>
        </PageBanner>

        <!-- Search bar -->
        <div class="container mx-auto mb-3 sm:px-4">
            <div class="relative">
                <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                <Input
                    v-model="filterParams.name"
                    type="text"
                    placeholder="Search by name or nickname..."
                    class="border-2 border-primary pl-10 pr-10"
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
        <div class="container mx-auto mb-2 flex items-center justify-between sm:px-4">
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
                                <SearchableMultiselect
                                    v-model="selectedFactions"
                                    placeholder="Select Factions"
                                    :options="props.factions"
                                    option-value="name"
                                />
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
                            <div class="border-t pt-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Advanced</div>

                            <!-- Stats -->
                            <Collapsible :open="sectionsOpen.advancedStats" @update:open="toggleAdvancedSection('advancedStats')">
                                <CollapsibleTrigger
                                    class="flex w-full items-center justify-between rounded-md bg-muted px-2.5 py-1.5 text-xs font-medium hover:bg-muted/80"
                                >
                                    Stats
                                    <ChevronDown class="h-3.5 w-3.5 transition-transform" :class="{ 'rotate-180': sectionsOpen.advancedStats }" />
                                </CollapsibleTrigger>
                                <CollapsibleContent class="space-y-3 px-1 pt-2">
                                    <div v-for="stat in statFields" :key="stat" class="space-y-1">
                                        <label class="text-sm font-medium capitalize">{{ stat }}</label>
                                        <div class="flex items-center gap-2">
                                            <Input
                                                v-model="filterParams[`${stat}_min`]"
                                                type="number"
                                                placeholder="Min"
                                                class="h-8 border-2 border-primary text-xs"
                                            />
                                            <Input
                                                v-model="filterParams[`${stat}_max`]"
                                                type="number"
                                                placeholder="Max"
                                                class="h-8 border-2 border-primary text-xs"
                                            />
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
                            <Collapsible :open="sectionsOpen.advancedBaseSuits" @update:open="toggleAdvancedSection('advancedBaseSuits')">
                                <CollapsibleTrigger
                                    class="flex w-full items-center justify-between rounded-md bg-muted px-2.5 py-1.5 text-xs font-medium hover:bg-muted/80"
                                >
                                    Base & Suits
                                    <ChevronDown class="h-3.5 w-3.5 transition-transform" :class="{ 'rotate-180': sectionsOpen.advancedBaseSuits }" />
                                </CollapsibleTrigger>
                                <CollapsibleContent class="space-y-3 px-1 pt-2">
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
                            <Collapsible :open="sectionsOpen.advancedFlags" @update:open="toggleAdvancedSection('advancedFlags')">
                                <CollapsibleTrigger
                                    class="flex w-full items-center justify-between rounded-md bg-muted px-2.5 py-1.5 text-xs font-medium hover:bg-muted/80"
                                >
                                    Flags
                                    <ChevronDown class="h-3.5 w-3.5 transition-transform" :class="{ 'rotate-180': sectionsOpen.advancedFlags }" />
                                </CollapsibleTrigger>
                                <CollapsibleContent class="space-y-3 px-1 pt-2">
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

                            <!-- Actions -->
                            <Collapsible :open="sectionsOpen.advancedActions" @update:open="toggleAdvancedSection('advancedActions')">
                                <CollapsibleTrigger
                                    class="flex w-full items-center justify-between rounded-md bg-muted px-2.5 py-1.5 text-xs font-medium hover:bg-muted/80"
                                >
                                    Actions
                                    <ChevronDown class="h-3.5 w-3.5 transition-transform" :class="{ 'rotate-180': sectionsOpen.advancedActions }" />
                                </CollapsibleTrigger>
                                <CollapsibleContent class="space-y-3 px-1 pt-2">
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium">Name</label>
                                        <ClearableSelect
                                            v-model="filterParams.action"
                                            placeholder="Select Action"
                                            :options="props.actions"
                                            trigger-class="border-2 border-primary rounded"
                                        />
                                        <Input
                                            v-model="filterParams.action_name"
                                            type="text"
                                            placeholder="or search action name..."
                                            class="h-8 border-2 border-primary text-xs"
                                        />
                                    </div>
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium">Type</label>
                                        <ClearableSelect
                                            v-model="filterParams.action_type"
                                            placeholder="Any Type"
                                            :options="props.action_types"
                                            trigger-class="border-2 border-primary rounded"
                                        />
                                    </div>
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium">Range Type</label>
                                        <ClearableSelect
                                            v-model="filterParams.action_range_type"
                                            placeholder="Any Range Type"
                                            :options="props.action_range_types"
                                            trigger-class="border-2 border-primary rounded"
                                        />
                                    </div>
                                    <div class="space-y-1">
                                        <label class="text-sm font-medium">Range</label>
                                        <div class="flex items-center gap-2">
                                            <Input
                                                v-model="filterParams.action_range_min"
                                                type="number"
                                                placeholder="Min"
                                                class="h-8 border-2 border-primary text-xs"
                                            />
                                            <Input
                                                v-model="filterParams.action_range_max"
                                                type="number"
                                                placeholder="Max"
                                                class="h-8 border-2 border-primary text-xs"
                                            />
                                        </div>
                                    </div>
                                    <div class="space-y-1">
                                        <label class="text-sm font-medium">Stat</label>
                                        <div class="flex items-center gap-2">
                                            <Input
                                                v-model="filterParams.action_stat_min"
                                                type="number"
                                                placeholder="Min"
                                                class="h-8 border-2 border-primary text-xs"
                                            />
                                            <Input
                                                v-model="filterParams.action_stat_max"
                                                type="number"
                                                placeholder="Max"
                                                class="h-8 border-2 border-primary text-xs"
                                            />
                                        </div>
                                    </div>
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium">Stat Suit</label>
                                        <ClearableSelect
                                            v-model="filterParams.action_stat_suits"
                                            placeholder="Any Suit"
                                            :options="props.suits"
                                            trigger-class="border-2 border-primary rounded"
                                        />
                                    </div>
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium">Stat Modifier</label>
                                        <ClearableSelect
                                            v-model="filterParams.action_stat_modifier"
                                            placeholder="Any Modifier"
                                            :options="props.stat_modifiers"
                                            trigger-class="border-2 border-primary rounded"
                                        />
                                    </div>
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium">Resisted By</label>
                                        <ClearableSelect
                                            v-model="filterParams.action_resisted_by"
                                            placeholder="Any"
                                            :options="props.resistance_types"
                                            trigger-class="border-2 border-primary rounded"
                                        />
                                    </div>
                                    <div class="space-y-1">
                                        <label class="text-sm font-medium">Target Number</label>
                                        <div class="flex items-center gap-2">
                                            <Input
                                                v-model="filterParams.action_tn_min"
                                                type="number"
                                                placeholder="Min"
                                                class="h-8 border-2 border-primary text-xs"
                                            />
                                            <Input
                                                v-model="filterParams.action_tn_max"
                                                type="number"
                                                placeholder="Max"
                                                class="h-8 border-2 border-primary text-xs"
                                            />
                                        </div>
                                    </div>
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium">Target Suit</label>
                                        <ClearableSelect
                                            v-model="filterParams.action_target_suits"
                                            placeholder="Any Suit"
                                            :options="props.suits"
                                            trigger-class="border-2 border-primary rounded"
                                        />
                                    </div>
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium">Damage</label>
                                        <Input
                                            v-model="filterParams.action_damage"
                                            type="text"
                                            placeholder="Search damage..."
                                            class="h-8 border-2 border-primary text-xs"
                                        />
                                    </div>
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium">Description</label>
                                        <Input
                                            v-model="filterParams.action_description"
                                            type="text"
                                            placeholder="Search description..."
                                            class="h-8 border-2 border-primary text-xs"
                                        />
                                    </div>
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium">Signature</label>
                                        <ClearableSelect
                                            v-model="filterParams.action_is_signature"
                                            placeholder="Any"
                                            :options="booleanOptions"
                                            trigger-class="border-2 border-primary rounded"
                                        />
                                    </div>
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium">Costs Soulstone</label>
                                        <ClearableSelect
                                            v-model="filterParams.action_costs_stone"
                                            placeholder="Any"
                                            :options="booleanOptions"
                                            trigger-class="border-2 border-primary rounded"
                                        />
                                    </div>
                                </CollapsibleContent>
                            </Collapsible>

                            <!-- Abilities -->
                            <Collapsible :open="sectionsOpen.advancedAbilities" @update:open="toggleAdvancedSection('advancedAbilities')">
                                <CollapsibleTrigger
                                    class="flex w-full items-center justify-between rounded-md bg-muted px-2.5 py-1.5 text-xs font-medium hover:bg-muted/80"
                                >
                                    Abilities
                                    <ChevronDown class="h-3.5 w-3.5 transition-transform" :class="{ 'rotate-180': sectionsOpen.advancedAbilities }" />
                                </CollapsibleTrigger>
                                <CollapsibleContent class="space-y-3 px-1 pt-2">
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium">Name</label>
                                        <ClearableSelect
                                            v-model="filterParams.ability"
                                            placeholder="Select Ability"
                                            :options="props.abilities"
                                            trigger-class="border-2 border-primary rounded"
                                        />
                                        <Input
                                            v-model="filterParams.ability_name"
                                            type="text"
                                            placeholder="or search ability name..."
                                            class="h-8 border-2 border-primary text-xs"
                                        />
                                    </div>
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium">Suit</label>
                                        <ClearableSelect
                                            v-model="filterParams.ability_suits"
                                            placeholder="Any Suit"
                                            :options="props.suits"
                                            trigger-class="border-2 border-primary rounded"
                                        />
                                    </div>
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium">Defensive Type</label>
                                        <ClearableSelect
                                            v-model="filterParams.ability_defensive_type"
                                            placeholder="Any Type"
                                            :options="props.defensive_ability_types"
                                            trigger-class="border-2 border-primary rounded"
                                        />
                                    </div>
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium">Costs Soulstone</label>
                                        <ClearableSelect
                                            v-model="filterParams.ability_costs_stone"
                                            placeholder="Any"
                                            :options="booleanOptions"
                                            trigger-class="border-2 border-primary rounded"
                                        />
                                    </div>
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium">Description</label>
                                        <Input
                                            v-model="filterParams.ability_description"
                                            type="text"
                                            placeholder="Search description..."
                                            class="h-8 border-2 border-primary text-xs"
                                        />
                                    </div>
                                </CollapsibleContent>
                            </Collapsible>

                            <!-- Sorting -->
                            <div class="border-t pt-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Sorting</div>
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
        <div class="container mx-auto sm:px-4">
            <div class="flex gap-6">
                <!-- Desktop sidebar filters -->
                <aside class="hidden w-72 shrink-0 md:block">
                    <div class="space-y-2 pr-2">
                        <!-- General -->
                        <div class="space-y-3 px-1">
                            <div class="space-y-1">
                                <label class="text-xs font-medium text-muted-foreground">Factions</label>
                                <SearchableMultiselect
                                    v-model="selectedFactions"
                                    placeholder="Select Factions"
                                    :options="props.factions"
                                    option-value="name"
                                />
                            </div>
                            <div class="space-y-1">
                                <label class="text-xs font-medium text-muted-foreground">Station</label>
                                <ClearableSelect v-model="filterParams.station" placeholder="Any Station" :options="props.stations" />
                            </div>
                            <div class="space-y-1">
                                <label class="text-xs font-medium text-muted-foreground">Keyword</label>
                                <ClearableSelect
                                    v-model="filterParams.keyword"
                                    placeholder="Any Keyword"
                                    :options="props.keywords"
                                    option-value="slug"
                                />
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
                            <CollapsibleTrigger
                                class="flex w-full items-center justify-between rounded-md bg-secondary px-3 py-2 text-sm font-medium hover:bg-secondary/80"
                            >
                                Advanced
                                <ChevronDown class="h-4 w-4 transition-transform" :class="{ 'rotate-180': sectionsOpen.advanced }" />
                            </CollapsibleTrigger>
                            <CollapsibleContent class="space-y-2 px-1 pt-3">
                                <!-- Stats -->
                                <Collapsible :open="sectionsOpen.advancedStats" @update:open="toggleAdvancedSection('advancedStats')">
                                    <CollapsibleTrigger
                                        class="flex w-full items-center justify-between rounded-md bg-muted px-2.5 py-1.5 text-xs font-medium hover:bg-muted/80"
                                    >
                                        Stats
                                        <ChevronDown class="h-3.5 w-3.5 transition-transform" :class="{ 'rotate-180': sectionsOpen.advancedStats }" />
                                    </CollapsibleTrigger>
                                    <CollapsibleContent class="space-y-3 px-1 pt-2">
                                        <div v-for="stat in statFields" :key="stat" class="space-y-1">
                                            <label class="text-xs font-medium capitalize text-muted-foreground">{{ stat }}</label>
                                            <div class="flex items-center gap-2">
                                                <Input
                                                    v-model="filterParams[`${stat}_min`]"
                                                    type="number"
                                                    placeholder="Min"
                                                    class="h-8 border-2 border-primary text-xs"
                                                />
                                                <Input
                                                    v-model="filterParams[`${stat}_max`]"
                                                    type="number"
                                                    placeholder="Max"
                                                    class="h-8 border-2 border-primary text-xs"
                                                />
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
                                <Collapsible :open="sectionsOpen.advancedBaseSuits" @update:open="toggleAdvancedSection('advancedBaseSuits')">
                                    <CollapsibleTrigger
                                        class="flex w-full items-center justify-between rounded-md bg-muted px-2.5 py-1.5 text-xs font-medium hover:bg-muted/80"
                                    >
                                        Base & Suits
                                        <ChevronDown
                                            class="h-3.5 w-3.5 transition-transform"
                                            :class="{ 'rotate-180': sectionsOpen.advancedBaseSuits }"
                                        />
                                    </CollapsibleTrigger>
                                    <CollapsibleContent class="space-y-3 px-1 pt-2">
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
                                <Collapsible :open="sectionsOpen.advancedFlags" @update:open="toggleAdvancedSection('advancedFlags')">
                                    <CollapsibleTrigger
                                        class="flex w-full items-center justify-between rounded-md bg-muted px-2.5 py-1.5 text-xs font-medium hover:bg-muted/80"
                                    >
                                        Flags
                                        <ChevronDown class="h-3.5 w-3.5 transition-transform" :class="{ 'rotate-180': sectionsOpen.advancedFlags }" />
                                    </CollapsibleTrigger>
                                    <CollapsibleContent class="space-y-3 px-1 pt-2">
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

                                <!-- Actions -->
                                <Collapsible :open="sectionsOpen.advancedActions" @update:open="toggleAdvancedSection('advancedActions')">
                                    <CollapsibleTrigger
                                        class="flex w-full items-center justify-between rounded-md bg-muted px-2.5 py-1.5 text-xs font-medium hover:bg-muted/80"
                                    >
                                        Actions
                                        <ChevronDown
                                            class="h-3.5 w-3.5 transition-transform"
                                            :class="{ 'rotate-180': sectionsOpen.advancedActions }"
                                        />
                                    </CollapsibleTrigger>
                                    <CollapsibleContent class="space-y-3 px-1 pt-2">
                                        <div class="space-y-1">
                                            <label class="text-xs font-medium text-muted-foreground">Name</label>
                                            <ClearableSelect v-model="filterParams.action" placeholder="Select Action" :options="props.actions" />
                                            <Input
                                                v-model="filterParams.action_name"
                                                type="text"
                                                placeholder="or search action name..."
                                                class="h-8 text-xs"
                                            />
                                        </div>
                                        <div class="space-y-1">
                                            <label class="text-xs font-medium text-muted-foreground">Type</label>
                                            <ClearableSelect
                                                v-model="filterParams.action_type"
                                                placeholder="Any Type"
                                                :options="props.action_types"
                                            />
                                        </div>
                                        <div class="space-y-1">
                                            <label class="text-xs font-medium text-muted-foreground">Range Type</label>
                                            <ClearableSelect
                                                v-model="filterParams.action_range_type"
                                                placeholder="Any Range Type"
                                                :options="props.action_range_types"
                                            />
                                        </div>
                                        <div class="space-y-1">
                                            <label class="text-xs font-medium text-muted-foreground">Range</label>
                                            <div class="flex items-center gap-2">
                                                <Input
                                                    v-model="filterParams.action_range_min"
                                                    type="number"
                                                    placeholder="Min"
                                                    class="h-8 border-2 border-primary text-xs"
                                                />
                                                <Input
                                                    v-model="filterParams.action_range_max"
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
                                                    v-model="filterParams.action_stat_min"
                                                    type="number"
                                                    placeholder="Min"
                                                    class="h-8 border-2 border-primary text-xs"
                                                />
                                                <Input
                                                    v-model="filterParams.action_stat_max"
                                                    type="number"
                                                    placeholder="Max"
                                                    class="h-8 border-2 border-primary text-xs"
                                                />
                                            </div>
                                        </div>
                                        <div class="space-y-1">
                                            <label class="text-xs font-medium text-muted-foreground">Stat Suit</label>
                                            <ClearableSelect v-model="filterParams.action_stat_suits" placeholder="Any Suit" :options="props.suits" />
                                        </div>
                                        <div class="space-y-1">
                                            <label class="text-xs font-medium text-muted-foreground">Stat Modifier</label>
                                            <ClearableSelect
                                                v-model="filterParams.action_stat_modifier"
                                                placeholder="Any Modifier"
                                                :options="props.stat_modifiers"
                                            />
                                        </div>
                                        <div class="space-y-1">
                                            <label class="text-xs font-medium text-muted-foreground">Resisted By</label>
                                            <ClearableSelect
                                                v-model="filterParams.action_resisted_by"
                                                placeholder="Any"
                                                :options="props.resistance_types"
                                            />
                                        </div>
                                        <div class="space-y-1">
                                            <label class="text-xs font-medium text-muted-foreground">Target Number</label>
                                            <div class="flex items-center gap-2">
                                                <Input
                                                    v-model="filterParams.action_tn_min"
                                                    type="number"
                                                    placeholder="Min"
                                                    class="h-8 border-2 border-primary text-xs"
                                                />
                                                <Input
                                                    v-model="filterParams.action_tn_max"
                                                    type="number"
                                                    placeholder="Max"
                                                    class="h-8 border-2 border-primary text-xs"
                                                />
                                            </div>
                                        </div>
                                        <div class="space-y-1">
                                            <label class="text-xs font-medium text-muted-foreground">Target Suit</label>
                                            <ClearableSelect
                                                v-model="filterParams.action_target_suits"
                                                placeholder="Any Suit"
                                                :options="props.suits"
                                            />
                                        </div>
                                        <div class="space-y-1">
                                            <label class="text-xs font-medium text-muted-foreground">Damage</label>
                                            <Input
                                                v-model="filterParams.action_damage"
                                                type="text"
                                                placeholder="Search damage..."
                                                class="h-8 text-xs"
                                            />
                                        </div>
                                        <div class="space-y-1">
                                            <label class="text-xs font-medium text-muted-foreground">Description</label>
                                            <Input
                                                v-model="filterParams.action_description"
                                                type="text"
                                                placeholder="Search description..."
                                                class="h-8 text-xs"
                                            />
                                        </div>
                                        <div class="space-y-1">
                                            <label class="text-xs font-medium text-muted-foreground">Signature</label>
                                            <ClearableSelect v-model="filterParams.action_is_signature" placeholder="Any" :options="booleanOptions" />
                                        </div>
                                        <div class="space-y-1">
                                            <label class="text-xs font-medium text-muted-foreground">Costs Soulstone</label>
                                            <ClearableSelect v-model="filterParams.action_costs_stone" placeholder="Any" :options="booleanOptions" />
                                        </div>
                                    </CollapsibleContent>
                                </Collapsible>

                                <!-- Abilities -->
                                <Collapsible :open="sectionsOpen.advancedAbilities" @update:open="toggleAdvancedSection('advancedAbilities')">
                                    <CollapsibleTrigger
                                        class="flex w-full items-center justify-between rounded-md bg-muted px-2.5 py-1.5 text-xs font-medium hover:bg-muted/80"
                                    >
                                        Abilities
                                        <ChevronDown
                                            class="h-3.5 w-3.5 transition-transform"
                                            :class="{ 'rotate-180': sectionsOpen.advancedAbilities }"
                                        />
                                    </CollapsibleTrigger>
                                    <CollapsibleContent class="space-y-3 px-1 pt-2">
                                        <div class="space-y-1">
                                            <label class="text-xs font-medium text-muted-foreground">Name</label>
                                            <ClearableSelect v-model="filterParams.ability" placeholder="Select Ability" :options="props.abilities" />
                                            <Input
                                                v-model="filterParams.ability_name"
                                                type="text"
                                                placeholder="or search ability name..."
                                                class="h-8 text-xs"
                                            />
                                        </div>
                                        <div class="space-y-1">
                                            <label class="text-xs font-medium text-muted-foreground">Suit</label>
                                            <ClearableSelect v-model="filterParams.ability_suits" placeholder="Any Suit" :options="props.suits" />
                                        </div>
                                        <div class="space-y-1">
                                            <label class="text-xs font-medium text-muted-foreground">Defensive Type</label>
                                            <ClearableSelect
                                                v-model="filterParams.ability_defensive_type"
                                                placeholder="Any Type"
                                                :options="props.defensive_ability_types"
                                            />
                                        </div>
                                        <div class="space-y-1">
                                            <label class="text-xs font-medium text-muted-foreground">Costs Soulstone</label>
                                            <ClearableSelect v-model="filterParams.ability_costs_stone" placeholder="Any" :options="booleanOptions" />
                                        </div>
                                        <div class="space-y-1">
                                            <label class="text-xs font-medium text-muted-foreground">Description</label>
                                            <Input
                                                v-model="filterParams.ability_description"
                                                type="text"
                                                placeholder="Search description..."
                                                class="h-8 text-xs"
                                            />
                                        </div>
                                    </CollapsibleContent>
                                </Collapsible>
                            </CollapsibleContent>
                        </Collapsible>

                        <!-- Sorting -->
                        <Collapsible v-model:open="sectionsOpen.sorting">
                            <CollapsibleTrigger
                                class="flex w-full items-center justify-between rounded-md bg-secondary px-3 py-2 text-sm font-medium hover:bg-secondary/80"
                            >
                                Sorting
                                <ChevronDown class="h-4 w-4 transition-transform" :class="{ 'rotate-180': sectionsOpen.sorting }" />
                            </CollapsibleTrigger>
                            <CollapsibleContent class="space-y-3 px-1 pt-3">
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
                <div class="min-w-0 flex-1">
                    <div v-if="isLoading && filterParams.page_view === 'table'" class="overflow-auto">
                        <TableSkeleton :rows="8" :cols="7" />
                    </div>
                    <div v-else-if="isLoading">
                        <div class="grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-4">
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
                            <div class="grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-4">
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
