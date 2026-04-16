<script setup lang="ts">
import { fieldMap, parseSyntax, toSyntax } from '@/composables/useSearchSyntax';
import { useStaggeredEntry } from '@/composables/useStaggeredEntry';
import type { SharedData } from '@/types';
import { Head, router, usePage } from '@inertiajs/vue3';
import {
    BookOpen,
    Bookmark,
    Check,
    ChevronDown,
    ClipboardCopy,
    Download,
    HelpCircle,
    LayoutGrid,
    List,
    Search,
    Sparkles,
    Trash2,
    X,
} from 'lucide-vue-next';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';

const urlCopied = ref(false);
const copySearchUrl = () => {
    navigator.clipboard.writeText(window.location.href);
    urlCopied.value = true;
    setTimeout(() => (urlCopied.value = false), 2000);
};

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
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import UpgradeCardView from '@/components/UpgradeCardView.vue';
import UpgradeFlipCard from '@/components/UpgradeFlipCard.vue';
import { cleanObject } from '@/composables/CleanObject';

const booleanOptions = [
    { name: 'Yes', value: 'true' },
    { name: 'No', value: 'false' },
];

const props = defineProps({
    results: {
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
    game_mode_types: {
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
    triggers: {
        type: [Object, Array],
        required: false,
        default() {
            return {};
        },
    },
    tokens_list: {
        type: [Object, Array],
        required: false,
        default() {
            return {};
        },
    },
    markers_list: {
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
    saved_searches: {
        type: [Object, Array],
        required: false,
        default() {
            return [];
        },
    },
    result_breakdown: {
        type: Object,
        required: false,
        default: () => ({ characters: 0, upgrades: 0 }),
    },
});

const selectedGameModes = ref<string[]>([]);
const excludedGameModes = ref<string[]>([]);
const selectedFactions = ref<string[]>([]);
const excludedFactions = ref<string[]>([]);
const selectedKeywords = ref<string[]>([]);
const excludedKeywords = ref<string[]>([]);
const keywordLogic = ref<'and' | 'or'>('and');
const selectedCharacteristics = ref<string[]>([]);
const excludedCharacteristics = ref<string[]>([]);
const characteristicLogic = ref<'and' | 'or'>('and');
const selectedActions = ref<string[]>([]);
const actionLogic = ref<'and' | 'or'>('or');
const selectedAbilities = ref<string[]>([]);
const abilityLogic = ref<'and' | 'or'>('or');
const selectedTriggers = ref<string[]>([]);
const triggerLogic = ref<'and' | 'or'>('or');
const selectedTokens = ref<string[]>([]);
const tokenLogic = ref<'and' | 'or'>('or');
const selectedMarkers = ref<string[]>([]);
const markerLogic = ref<'and' | 'or'>('or');

// Stat comparison refs (Feature 9)
const statCompareLeft = ref<string | null>(null);
const statCompareOp = ref<string | null>(null);
const statCompareRight = ref<string | null>(null);
const statCompareOptions = [
    { name: 'Cost', value: 'cost' },
    { name: 'Health', value: 'health' },
    { name: 'Speed', value: 'speed' },
    { name: 'Defense', value: 'defense' },
    { name: 'Willpower', value: 'willpower' },
    { name: 'Size', value: 'size' },
];
const statCompareOps = [
    { name: '>', value: '>' },
    { name: '>=', value: '>=' },
    { name: '<', value: '<' },
    { name: '<=', value: '<=' },
    { name: '=', value: '=' },
];

const filterParams = ref({
    name: null as string | null,
    station: null as string | null,
    base: null as string | null,
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
    ability_name: null as string | null,
    ability_suits: null as string | null,
    ability_defensive_type: null as string | null,
    ability_costs_stone: null as string | null,
    ability_description: null as string | null,
    trigger_suits: null as string | null,
    trigger_description: null as string | null,
    description: null as string | null,
    count_min: null as string | null,
    count_max: null as string | null,
    is: null as string | null,
    has: null as string | null,
    stat_compare: null as string | null,
    page_view: null as string | null,
    sort: null as string | null,
    sort_type: null as string | null,
});

const filterKeys = [
    'name',
    'station',
    'base',
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
    'ability_name',
    'ability_suits',
    'ability_defensive_type',
    'ability_costs_stone',
    'ability_description',
    'trigger_suits',
    'trigger_description',
    'description',
    'count_min',
    'count_max',
    'is',
    'has',
    'stat_compare',
] as const;

const statFields = ['cost', 'health', 'speed', 'defense', 'willpower', 'size', 'count'] as const;

const factionNameToValue = (name: string): string | undefined => {
    const match = props.factions.find((f: { name: string; value: string }) => f.name === name);
    return match?.value;
};

const factionValueToName = (value: string): string | undefined => {
    const match = props.factions.find((f: { name: string; value: string }) => f.value === value);
    return match?.name;
};

const lookupName = (list: any[], value: string, valueKey = 'value', labelKey = 'name') =>
    list.find((o: any) => o[valueKey] === value)?.[labelKey] ?? value;

const activeFilterCount = computed(() => {
    const paramCount = filterKeys.filter((key) => filterParams.value[key] != null && filterParams.value[key] !== '').length;
    return (
        paramCount +
        (selectedGameModes.value.length > 0 ? 1 : 0) +
        (excludedGameModes.value.length > 0 ? 1 : 0) +
        (selectedFactions.value.length > 0 ? 1 : 0) +
        (excludedFactions.value.length > 0 ? 1 : 0) +
        (selectedKeywords.value.length > 0 ? 1 : 0) +
        (excludedKeywords.value.length > 0 ? 1 : 0) +
        (selectedCharacteristics.value.length > 0 ? 1 : 0) +
        (excludedCharacteristics.value.length > 0 ? 1 : 0) +
        (selectedActions.value.length > 0 ? 1 : 0) +
        (selectedAbilities.value.length > 0 ? 1 : 0) +
        (selectedTriggers.value.length > 0 ? 1 : 0) +
        (selectedTokens.value.length > 0 ? 1 : 0) +
        (selectedMarkers.value.length > 0 ? 1 : 0)
    );
});

const filter = () => {
    const params: Record<string, string | null> = { ...filterParams.value };
    // Normalize empty strings to null so cleanObject removes them
    for (const key in params) {
        if (params[key] === '') {
            params[key] = null;
        }
    }
    // Game mode types
    params.game_mode_type = selectedGameModes.value.length > 0 ? selectedGameModes.value.join(',') : null;
    params.game_mode_type_exclude = excludedGameModes.value.length > 0 ? excludedGameModes.value.join(',') : null;
    // Convert faction names to comma-separated values
    if (selectedFactions.value.length > 0) {
        params.faction = selectedFactions.value
            .map((name) => factionNameToValue(name))
            .filter(Boolean)
            .join(',');
    } else {
        params.faction = null;
    }
    // Excluded factions
    params.faction_exclude =
        excludedFactions.value.length > 0
            ? excludedFactions.value
                  .map((name) => factionNameToValue(name))
                  .filter(Boolean)
                  .join(',')
            : null;
    // Keywords with logic
    params.keyword = selectedKeywords.value.length > 0 ? selectedKeywords.value.join(',') : null;
    params.keyword_logic = selectedKeywords.value.length > 1 ? keywordLogic.value : null;
    params.keyword_exclude = excludedKeywords.value.length > 0 ? excludedKeywords.value.join(',') : null;
    // Characteristics with logic
    params.characteristic = selectedCharacteristics.value.length > 0 ? selectedCharacteristics.value.join(',') : null;
    params.characteristic_logic = selectedCharacteristics.value.length > 1 ? characteristicLogic.value : null;
    params.characteristic_exclude = excludedCharacteristics.value.length > 0 ? excludedCharacteristics.value.join(',') : null;
    // Actions with logic
    params.action = selectedActions.value.length > 0 ? selectedActions.value.join(',') : null;
    params.action_logic = selectedActions.value.length > 1 ? actionLogic.value : null;
    // Abilities with logic
    params.ability = selectedAbilities.value.length > 0 ? selectedAbilities.value.join(',') : null;
    params.ability_logic = selectedAbilities.value.length > 1 ? abilityLogic.value : null;
    // Triggers with logic
    params.trigger = selectedTriggers.value.length > 0 ? selectedTriggers.value.join(',') : null;
    params.trigger_logic = selectedTriggers.value.length > 1 ? triggerLogic.value : null;
    // Tokens with logic
    params.token = selectedTokens.value.length > 0 ? selectedTokens.value.join(',') : null;
    params.token_logic = selectedTokens.value.length > 1 ? tokenLogic.value : null;
    // Markers with logic
    params.marker = selectedMarkers.value.length > 0 ? selectedMarkers.value.join(',') : null;
    params.marker_logic = selectedMarkers.value.length > 1 ? markerLogic.value : null;
    // Is/Has filters
    if (filterParams.value.is) params.is = filterParams.value.is;
    if (filterParams.value.has) params.has = filterParams.value.has;
    // Stat comparison
    if (statCompareLeft.value && statCompareOp.value && statCompareRight.value) {
        params.stat_compare = `${statCompareLeft.value}${statCompareOp.value}${statCompareRight.value}`;
        filterParams.value.stat_compare = params.stat_compare;
    }
    // Description (cross-field text search)
    if (filterParams.value.description) {
        params.description = filterParams.value.description;
    }
    // Reset to page 1 on any filter change
    params.page = null;
    router.get(route('search.view'), cleanObject(params), {
        only: ['results', 'result_count', 'result_breakdown', 'saved_searches'],
        replace: true,
        preserveState: true,
    });
    nextTick(() => {
        const syntax = toSyntax(buildCurrentParams());
        if (syntax.trim()) saveToHistory(syntax);
    });
};

const resetFilters = () => {
    for (const key of filterKeys) {
        filterParams.value[key] = null;
    }
    selectedGameModes.value = [];
    excludedGameModes.value = [];
    selectedFactions.value = [];
    excludedFactions.value = [];
    selectedKeywords.value = [];
    excludedKeywords.value = [];
    keywordLogic.value = 'and';
    selectedCharacteristics.value = [];
    excludedCharacteristics.value = [];
    characteristicLogic.value = 'and';
    selectedActions.value = [];
    actionLogic.value = 'or';
    selectedAbilities.value = [];
    abilityLogic.value = 'or';
    selectedTriggers.value = [];
    triggerLogic.value = 'or';
    selectedTokens.value = [];
    tokenLogic.value = 'or';
    selectedMarkers.value = [];
    markerLogic.value = 'or';
    statCompareLeft.value = null;
    statCompareOp.value = null;
    statCompareRight.value = null;
    filterParams.value.page_view = 'images';
    filterParams.value.sort = 'name';
    filterParams.value.sort_type = 'ascending';
};

const clear = () => {
    resetFilters();
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

const advancedSubSections = [
    'advancedStats',
    'advancedActions',
    'advancedAbilities',
    'advancedTriggers',
    'advancedTokens',
    'advancedMarkers',
] as const;

const sectionsOpen = ref({
    exclusions: false,
    advanced: false,
    advancedStats: false,
    advancedActions: false,
    advancedAbilities: false,
    advancedTriggers: false,
    advancedTokens: false,
    advancedMarkers: false,
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

const restoreFromURL = (urlParams?: URLSearchParams) => {
    if (!urlParams) {
        urlParams = new URLSearchParams(window.location.search);
    }
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
    filterParams.value.count_min = urlParams.get('count_min');
    filterParams.value.count_max = urlParams.get('count_max');
    // Is/Has filters
    filterParams.value.is = urlParams.get('is');
    filterParams.value.has = urlParams.get('has');
    // Stat comparison
    const statCompareParam = urlParams.get('stat_compare');
    if (statCompareParam) {
        filterParams.value.stat_compare = statCompareParam;
        const scMatch = statCompareParam.match(/^(\w+)(>=|<=|>|<|=)(\w+)$/);
        if (scMatch) {
            statCompareLeft.value = scMatch[1];
            statCompareOp.value = scMatch[2];
            statCompareRight.value = scMatch[3];
        }
    }
    // Game mode types
    const gameModeParam = urlParams.get('game_mode_type');
    if (gameModeParam) selectedGameModes.value = gameModeParam.split(',').filter(Boolean);
    const gameModeExcludeParam = urlParams.get('game_mode_type_exclude');
    if (gameModeExcludeParam) excludedGameModes.value = gameModeExcludeParam.split(',').filter(Boolean);
    const keywordParam = urlParams.get('keyword');
    if (keywordParam) {
        selectedKeywords.value = keywordParam.split(',').filter(Boolean);
    }
    const characteristicParam = urlParams.get('characteristic');
    if (characteristicParam) {
        selectedCharacteristics.value = characteristicParam.split(',').filter(Boolean);
    }
    // Excluded factions
    const factionExcludeParam = urlParams.get('faction_exclude');
    if (factionExcludeParam) {
        excludedFactions.value = factionExcludeParam
            .split(',')
            .map((v) => factionValueToName(v))
            .filter(Boolean) as string[];
    }
    // Excluded keywords/characteristics
    const keywordExcludeParam = urlParams.get('keyword_exclude');
    if (keywordExcludeParam) excludedKeywords.value = keywordExcludeParam.split(',').filter(Boolean);
    const characteristicExcludeParam = urlParams.get('characteristic_exclude');
    if (characteristicExcludeParam) excludedCharacteristics.value = characteristicExcludeParam.split(',').filter(Boolean);
    // Logic toggles
    if (urlParams.get('keyword_logic') === 'or') keywordLogic.value = 'or';
    if (urlParams.get('characteristic_logic') === 'or') characteristicLogic.value = 'or';
    if (urlParams.get('action_logic') === 'and') actionLogic.value = 'and';
    if (urlParams.get('ability_logic') === 'and') abilityLogic.value = 'and';
    if (urlParams.get('trigger_logic') === 'and') triggerLogic.value = 'and';
    // Multi-select action/ability/trigger
    const actionParam = urlParams.get('action');
    if (actionParam) selectedActions.value = actionParam.split(',').filter(Boolean);
    const abilityParam = urlParams.get('ability');
    if (abilityParam) selectedAbilities.value = abilityParam.split(',').filter(Boolean);
    const triggerParam = urlParams.get('trigger');
    if (triggerParam) selectedTriggers.value = triggerParam.split(',').filter(Boolean);
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
    filterParams.value.ability_name = urlParams.get('ability_name');
    filterParams.value.ability_suits = urlParams.get('ability_suits');
    filterParams.value.ability_defensive_type = urlParams.get('ability_defensive_type');
    filterParams.value.ability_costs_stone = urlParams.get('ability_costs_stone');
    filterParams.value.ability_description = urlParams.get('ability_description');
    filterParams.value.trigger_suits = urlParams.get('trigger_suits');
    filterParams.value.trigger_description = urlParams.get('trigger_description');
    filterParams.value.description = urlParams.get('description');
    const tokenParam = urlParams.get('token');
    if (tokenParam) selectedTokens.value = tokenParam.split(',').filter(Boolean);
    if (urlParams.get('token_logic') === 'and') tokenLogic.value = 'and';
    const markerParam = urlParams.get('marker');
    if (markerParam) selectedMarkers.value = markerParam.split(',').filter(Boolean);
    if (urlParams.get('marker_logic') === 'and') markerLogic.value = 'and';
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
        filterParams.value.size_max ||
        filterParams.value.count_min ||
        filterParams.value.count_max ||
        filterParams.value.stat_compare ||
        filterParams.value.base;
    const hasActions =
        selectedActions.value.length > 0 ||
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
        selectedAbilities.value.length > 0 ||
        filterParams.value.ability_name ||
        filterParams.value.ability_suits ||
        filterParams.value.ability_defensive_type ||
        filterParams.value.ability_costs_stone ||
        filterParams.value.ability_description;
    const hasTriggers = selectedTriggers.value.length > 0 || filterParams.value.trigger_suits || filterParams.value.trigger_description;
    const hasTokens = selectedTokens.value.length > 0;
    const hasMarkers = selectedMarkers.value.length > 0;
    if (hasStats || hasActions || hasAbilities || hasTriggers || hasTokens || hasMarkers) {
        sectionsOpen.value.advanced = true;
        if (hasStats) sectionsOpen.value.advancedStats = true;
        if (hasActions) sectionsOpen.value.advancedActions = true;
        if (hasAbilities) sectionsOpen.value.advancedAbilities = true;
        if (hasTriggers) sectionsOpen.value.advancedTriggers = true;
        if (hasTokens) sectionsOpen.value.advancedTokens = true;
        if (hasMarkers) sectionsOpen.value.advancedMarkers = true;
    }
    if (excludedGameModes.value.length || excludedFactions.value.length || excludedKeywords.value.length || excludedCharacteristics.value.length) {
        sectionsOpen.value.exclusions = true;
    }
    if (filterParams.value.sort !== 'name' || filterParams.value.sort_type !== 'ascending') {
        sectionsOpen.value.sorting = true;
    }
};

onMounted(() => {
    restoreFromURL();
});

// --- Feature 9: Syntax bar ---
const syntaxInput = ref('');
const isSyncing = ref(false);
const showSyntaxHelp = ref(false);

const buildCurrentParams = (): Record<string, string> => {
    const params: Record<string, string | null> = { ...filterParams.value };
    for (const key in params) {
        if (params[key] === '') params[key] = null;
    }
    if (selectedFactions.value.length > 0) {
        params.faction = selectedFactions.value
            .map((name) => factionNameToValue(name))
            .filter(Boolean)
            .join(',');
    } else {
        params.faction = null;
    }
    params.faction_exclude =
        excludedFactions.value.length > 0
            ? excludedFactions.value
                  .map((name) => factionNameToValue(name))
                  .filter(Boolean)
                  .join(',')
            : null;
    params.game_mode_type = selectedGameModes.value.length > 0 ? selectedGameModes.value.join(',') : null;
    params.game_mode_type_exclude = excludedGameModes.value.length > 0 ? excludedGameModes.value.join(',') : null;
    params.keyword = selectedKeywords.value.length > 0 ? selectedKeywords.value.join(',') : null;
    params.keyword_logic = selectedKeywords.value.length > 1 ? keywordLogic.value : null;
    params.keyword_exclude = excludedKeywords.value.length > 0 ? excludedKeywords.value.join(',') : null;
    params.characteristic = selectedCharacteristics.value.length > 0 ? selectedCharacteristics.value.join(',') : null;
    params.characteristic_logic = selectedCharacteristics.value.length > 1 ? characteristicLogic.value : null;
    params.characteristic_exclude = excludedCharacteristics.value.length > 0 ? excludedCharacteristics.value.join(',') : null;
    params.action = selectedActions.value.length > 0 ? selectedActions.value.join(',') : null;
    params.action_logic = selectedActions.value.length > 1 ? actionLogic.value : null;
    params.ability = selectedAbilities.value.length > 0 ? selectedAbilities.value.join(',') : null;
    params.ability_logic = selectedAbilities.value.length > 1 ? abilityLogic.value : null;
    params.trigger = selectedTriggers.value.length > 0 ? selectedTriggers.value.join(',') : null;
    params.trigger_logic = selectedTriggers.value.length > 1 ? triggerLogic.value : null;
    params.token = selectedTokens.value.length > 0 ? selectedTokens.value.join(',') : null;
    params.token_logic = selectedTokens.value.length > 1 ? tokenLogic.value : null;
    params.marker = selectedMarkers.value.length > 0 ? selectedMarkers.value.join(',') : null;
    params.marker_logic = selectedMarkers.value.length > 1 ? markerLogic.value : null;
    if (filterParams.value.description) params.description = filterParams.value.description;
    if (filterParams.value.is) params.is = filterParams.value.is;
    if (filterParams.value.has) params.has = filterParams.value.has;
    if (statCompareLeft.value && statCompareOp.value && statCompareRight.value) {
        params.stat_compare = `${statCompareLeft.value}${statCompareOp.value}${statCompareRight.value}`;
    }
    // Remove defaults so syntax bar stays clean
    if (params.page_view === 'images') params.page_view = null;
    if (params.sort === 'name') params.sort = null;
    if (params.sort_type === 'ascending') params.sort_type = null;
    const cleaned: Record<string, string> = {};
    for (const [k, v] of Object.entries(params)) {
        if (v != null && v !== '') cleaned[k] = v;
    }
    return cleaned;
};

const applySyntax = () => {
    isSyncing.value = true;
    showSyntaxSuggestions.value = false;
    const { params } = parseSyntax(syntaxInput.value);
    resetFilters();
    const urlParams = new URLSearchParams();
    for (const [k, v] of Object.entries(params)) {
        urlParams.set(k, v);
    }
    restoreFromURL(urlParams);
    if (syntaxInput.value.trim()) saveToHistory(syntaxInput.value.trim());
    filter();
    isSyncing.value = false;
};

// Watch all filter-related refs to update syntax bar
watch(
    [
        filterParams,
        selectedGameModes,
        excludedGameModes,
        selectedFactions,
        excludedFactions,
        selectedKeywords,
        excludedKeywords,
        keywordLogic,
        selectedCharacteristics,
        excludedCharacteristics,
        characteristicLogic,
        selectedActions,
        actionLogic,
        selectedAbilities,
        abilityLogic,
        selectedTriggers,
        triggerLogic,
        selectedTokens,
        tokenLogic,
        selectedMarkers,
        markerLogic,
        statCompareLeft,
        statCompareOp,
        statCompareRight,
    ],
    () => {
        if (isSyncing.value) return;
        isSyncing.value = true;
        syntaxInput.value = toSyntax(buildCurrentParams());
        isSyncing.value = false;
    },
    { deep: true },
);

// --- Feature 1: Search explanation banner ---
const searchExplanation = computed(() => {
    const parts: string[] = [];
    if (selectedFactions.value.length) parts.push(selectedFactions.value.join(', '));
    if (filterParams.value.station) parts.push(lookupName(props.stations, filterParams.value.station) + 's');
    if (filterParams.value.name) parts.push(`name: "${filterParams.value.name}"`);
    if (selectedKeywords.value.length) parts.push(`keyword: ${selectedKeywords.value.join(keywordLogic.value === 'or' ? ' or ' : ' + ')}`);
    if (selectedCharacteristics.value.length)
        parts.push(`characteristic: ${selectedCharacteristics.value.join(characteristicLogic.value === 'or' ? ' or ' : ' + ')}`);
    if (filterParams.value.description) parts.push(`rules text: "${filterParams.value.description}"`);
    for (const stat of statFields) {
        const min = filterParams.value[`${stat}_min` as keyof typeof filterParams.value];
        const max = filterParams.value[`${stat}_max` as keyof typeof filterParams.value];
        if (min && max && min === max) parts.push(`${stat} = ${min}`);
        else if (min && max) parts.push(`${stat} ${min}-${max}`);
        else if (min) parts.push(`${stat} >= ${min}`);
        else if (max) parts.push(`${stat} <= ${max}`);
    }
    if (filterParams.value.base) parts.push(`base: ${lookupName(props.base_sizes, filterParams.value.base)}`);
    if (selectedActions.value.length) parts.push(`action: ${selectedActions.value.join(actionLogic.value === 'or' ? ' or ' : ' + ')}`);
    if (filterParams.value.action_name) parts.push(`action name: "${filterParams.value.action_name}"`);
    if (selectedAbilities.value.length) parts.push(`ability: ${selectedAbilities.value.join(abilityLogic.value === 'or' ? ' or ' : ' + ')}`);
    if (filterParams.value.ability_name) parts.push(`ability name: "${filterParams.value.ability_name}"`);
    if (selectedTriggers.value.length) parts.push(`trigger: ${selectedTriggers.value.join(triggerLogic.value === 'or' ? ' or ' : ' + ')}`);
    if (selectedTokens.value.length) parts.push(`token: ${selectedTokens.value.join(tokenLogic.value === 'or' ? ' or ' : ' + ')}`);
    if (selectedMarkers.value.length) parts.push(`marker: ${selectedMarkers.value.join(markerLogic.value === 'or' ? ' or ' : ' + ')}`);
    if (filterParams.value.is) parts.push(`is: ${filterParams.value.is.split(',').join(', ')}`);
    if (filterParams.value.has) parts.push(`has: ${filterParams.value.has.split(',').join(', ')}`);
    if (statCompareLeft.value && statCompareOp.value && statCompareRight.value) {
        parts.push(`${statCompareLeft.value} ${statCompareOp.value} ${statCompareRight.value}`);
    }
    if (selectedGameModes.value.length) parts.push(`mode: ${selectedGameModes.value.join(', ')}`);
    if (excludedGameModes.value.length) parts.push(`excl. modes: ${excludedGameModes.value.join(', ')}`);
    if (excludedFactions.value.length) parts.push(`excl. factions: ${excludedFactions.value.join(', ')}`);
    if (excludedKeywords.value.length) parts.push(`excl. keywords: ${excludedKeywords.value.join(', ')}`);
    if (excludedCharacteristics.value.length) parts.push(`excl. characteristics: ${excludedCharacteristics.value.join(', ')}`);
    return parts.length > 0 ? parts.join(', ') : null;
});

// --- Feature 2: Active filter chips ---
const activeFilters = computed(() => {
    const chips: Array<{ label: string; remove: () => void }> = [];
    if (filterParams.value.name) {
        chips.push({
            label: `Name: "${filterParams.value.name}"`,
            remove: () => {
                filterParams.value.name = null;
                filter();
            },
        });
    }
    for (const f of selectedFactions.value) {
        chips.push({
            label: `Faction: ${f}`,
            remove: () => {
                selectedFactions.value = selectedFactions.value.filter((v) => v !== f);
                filter();
            },
        });
    }
    if (filterParams.value.station) {
        const label = lookupName(props.stations, filterParams.value.station);
        chips.push({
            label: `Station: ${label}`,
            remove: () => {
                filterParams.value.station = null;
                filter();
            },
        });
    }
    if (selectedKeywords.value.length) {
        const logicLabel = selectedKeywords.value.length > 1 && keywordLogic.value !== 'and' ? ' (ANY)' : '';
        for (const kw of selectedKeywords.value) {
            chips.push({
                label: `Keyword: ${kw}${logicLabel}`,
                remove: () => {
                    selectedKeywords.value = selectedKeywords.value.filter((v) => v !== kw);
                    filter();
                },
            });
        }
    }
    if (selectedCharacteristics.value.length) {
        const logicLabel = selectedCharacteristics.value.length > 1 && characteristicLogic.value !== 'and' ? ' (ANY)' : '';
        for (const ch of selectedCharacteristics.value) {
            chips.push({
                label: `Characteristic: ${ch}${logicLabel}`,
                remove: () => {
                    selectedCharacteristics.value = selectedCharacteristics.value.filter((v) => v !== ch);
                    filter();
                },
            });
        }
    }
    if (filterParams.value.description) {
        chips.push({
            label: `Rules text: "${filterParams.value.description}"`,
            remove: () => {
                filterParams.value.description = null;
                filter();
            },
        });
    }
    for (const stat of statFields) {
        const min = filterParams.value[`${stat}_min` as keyof typeof filterParams.value];
        const max = filterParams.value[`${stat}_max` as keyof typeof filterParams.value];
        if (min || max) {
            let label = `${stat}: `;
            if (min && max && min === max) label += `= ${min}`;
            else if (min && max) label += `${min}-${max}`;
            else if (min) label += `>= ${min}`;
            else label += `<= ${max}`;
            chips.push({
                label,
                remove: () => {
                    clearStat(stat);
                    filter();
                },
            });
        }
    }
    if (filterParams.value.base) {
        chips.push({
            label: `Base: ${lookupName(props.base_sizes, filterParams.value.base)}`,
            remove: () => {
                filterParams.value.base = null;
                filter();
            },
        });
    }
    if (selectedActions.value.length) {
        const logicLabel = selectedActions.value.length > 1 && actionLogic.value !== 'or' ? ' (ALL)' : '';
        for (const a of selectedActions.value) {
            chips.push({
                label: `Action: ${a}${logicLabel}`,
                remove: () => {
                    selectedActions.value = selectedActions.value.filter((v) => v !== a);
                    filter();
                },
            });
        }
    }
    if (filterParams.value.action_name)
        chips.push({
            label: `Action name: "${filterParams.value.action_name}"`,
            remove: () => {
                filterParams.value.action_name = null;
                filter();
            },
        });
    if (filterParams.value.action_type)
        chips.push({
            label: `Action type: ${lookupName(props.action_types, filterParams.value.action_type)}`,
            remove: () => {
                filterParams.value.action_type = null;
                filter();
            },
        });
    if (filterParams.value.action_range_type)
        chips.push({
            label: `Range type: ${lookupName(props.action_range_types, filterParams.value.action_range_type)}`,
            remove: () => {
                filterParams.value.action_range_type = null;
                filter();
            },
        });
    if (filterParams.value.action_stat_suits)
        chips.push({
            label: `Stat suit: ${lookupName(props.suits, filterParams.value.action_stat_suits)}`,
            remove: () => {
                filterParams.value.action_stat_suits = null;
                filter();
            },
        });
    if (filterParams.value.action_stat_modifier)
        chips.push({
            label: `Stat modifier: ${lookupName(props.stat_modifiers, filterParams.value.action_stat_modifier)}`,
            remove: () => {
                filterParams.value.action_stat_modifier = null;
                filter();
            },
        });
    if (filterParams.value.action_resisted_by)
        chips.push({
            label: `Resisted by: ${lookupName(props.resistance_types, filterParams.value.action_resisted_by)}`,
            remove: () => {
                filterParams.value.action_resisted_by = null;
                filter();
            },
        });
    if (filterParams.value.action_target_suits)
        chips.push({
            label: `Target suit: ${lookupName(props.suits, filterParams.value.action_target_suits)}`,
            remove: () => {
                filterParams.value.action_target_suits = null;
                filter();
            },
        });
    if (filterParams.value.action_damage)
        chips.push({
            label: `Damage: "${filterParams.value.action_damage}"`,
            remove: () => {
                filterParams.value.action_damage = null;
                filter();
            },
        });
    if (filterParams.value.action_description)
        chips.push({
            label: `Action desc: "${filterParams.value.action_description}"`,
            remove: () => {
                filterParams.value.action_description = null;
                filter();
            },
        });
    if (filterParams.value.action_is_signature)
        chips.push({
            label: `Signature: ${filterParams.value.action_is_signature === 'true' ? 'Yes' : 'No'}`,
            remove: () => {
                filterParams.value.action_is_signature = null;
                filter();
            },
        });
    if (filterParams.value.action_costs_stone)
        chips.push({
            label: `Action costs SS: ${filterParams.value.action_costs_stone === 'true' ? 'Yes' : 'No'}`,
            remove: () => {
                filterParams.value.action_costs_stone = null;
                filter();
            },
        });
    if (selectedAbilities.value.length) {
        const logicLabel = selectedAbilities.value.length > 1 && abilityLogic.value !== 'or' ? ' (ALL)' : '';
        for (const a of selectedAbilities.value) {
            chips.push({
                label: `Ability: ${a}${logicLabel}`,
                remove: () => {
                    selectedAbilities.value = selectedAbilities.value.filter((v) => v !== a);
                    filter();
                },
            });
        }
    }
    if (filterParams.value.ability_name)
        chips.push({
            label: `Ability name: "${filterParams.value.ability_name}"`,
            remove: () => {
                filterParams.value.ability_name = null;
                filter();
            },
        });
    if (filterParams.value.ability_defensive_type)
        chips.push({
            label: `Defensive type: ${lookupName(props.defensive_ability_types, filterParams.value.ability_defensive_type)}`,
            remove: () => {
                filterParams.value.ability_defensive_type = null;
                filter();
            },
        });
    if (filterParams.value.ability_costs_stone)
        chips.push({
            label: `Ability costs SS: ${filterParams.value.ability_costs_stone === 'true' ? 'Yes' : 'No'}`,
            remove: () => {
                filterParams.value.ability_costs_stone = null;
                filter();
            },
        });
    if (filterParams.value.ability_description)
        chips.push({
            label: `Ability desc: "${filterParams.value.ability_description}"`,
            remove: () => {
                filterParams.value.ability_description = null;
                filter();
            },
        });
    if (filterParams.value.ability_suits)
        chips.push({
            label: `Ability suit: ${lookupName(props.suits, filterParams.value.ability_suits)}`,
            remove: () => {
                filterParams.value.ability_suits = null;
                filter();
            },
        });
    if (selectedTriggers.value.length) {
        const logicLabel = selectedTriggers.value.length > 1 && triggerLogic.value !== 'or' ? ' (ALL)' : '';
        for (const t of selectedTriggers.value) {
            chips.push({
                label: `Trigger: ${t}${logicLabel}`,
                remove: () => {
                    selectedTriggers.value = selectedTriggers.value.filter((v) => v !== t);
                    filter();
                },
            });
        }
    }
    if (filterParams.value.trigger_suits)
        chips.push({
            label: `Trigger suit: ${lookupName(props.suits, filterParams.value.trigger_suits)}`,
            remove: () => {
                filterParams.value.trigger_suits = null;
                filter();
            },
        });
    if (filterParams.value.trigger_description)
        chips.push({
            label: `Trigger desc: "${filterParams.value.trigger_description}"`,
            remove: () => {
                filterParams.value.trigger_description = null;
                filter();
            },
        });
    if (selectedTokens.value.length) {
        const logicLabel = selectedTokens.value.length > 1 && tokenLogic.value !== 'or' ? ' (ALL)' : '';
        for (const t of selectedTokens.value) {
            chips.push({
                label: `Token: ${t}${logicLabel}`,
                remove: () => {
                    selectedTokens.value = selectedTokens.value.filter((v) => v !== t);
                    filter();
                },
            });
        }
    }
    if (selectedMarkers.value.length) {
        const logicLabel = selectedMarkers.value.length > 1 && markerLogic.value !== 'or' ? ' (ALL)' : '';
        for (const m of selectedMarkers.value) {
            chips.push({
                label: `Marker: ${m}${logicLabel}`,
                remove: () => {
                    selectedMarkers.value = selectedMarkers.value.filter((v) => v !== m);
                    filter();
                },
            });
        }
    }
    if (filterParams.value.is) {
        for (const v of filterParams.value.is.split(',')) {
            chips.push({
                label: `Is: ${v}`,
                remove: () => {
                    filterParams.value.is =
                        filterParams.value.is
                            ?.split(',')
                            .filter((x) => x !== v)
                            .join(',') || null;
                    filter();
                },
            });
        }
    }
    if (filterParams.value.has) {
        for (const v of filterParams.value.has.split(',')) {
            chips.push({
                label: `Has: ${v}`,
                remove: () => {
                    filterParams.value.has =
                        filterParams.value.has
                            ?.split(',')
                            .filter((x) => x !== v)
                            .join(',') || null;
                    filter();
                },
            });
        }
    }
    if (statCompareLeft.value && statCompareOp.value && statCompareRight.value) {
        chips.push({
            label: `Stat: ${statCompareLeft.value} ${statCompareOp.value} ${statCompareRight.value}`,
            remove: () => {
                statCompareLeft.value = null;
                statCompareOp.value = null;
                statCompareRight.value = null;
                filterParams.value.stat_compare = null;
                filter();
            },
        });
    }
    for (const gm of selectedGameModes.value) {
        chips.push({
            label: `Mode: ${gm}`,
            remove: () => {
                selectedGameModes.value = selectedGameModes.value.filter((v) => v !== gm);
                filter();
            },
        });
    }
    for (const gm of excludedGameModes.value) {
        chips.push({
            label: `Excl. mode: ${gm}`,
            remove: () => {
                excludedGameModes.value = excludedGameModes.value.filter((v) => v !== gm);
                filter();
            },
        });
    }
    for (const f of excludedFactions.value) {
        chips.push({
            label: `Excl. faction: ${f}`,
            remove: () => {
                excludedFactions.value = excludedFactions.value.filter((v) => v !== f);
                filter();
            },
        });
    }
    for (const kw of excludedKeywords.value) {
        chips.push({
            label: `Excl. keyword: ${kw}`,
            remove: () => {
                excludedKeywords.value = excludedKeywords.value.filter((v) => v !== kw);
                filter();
            },
        });
    }
    for (const ch of excludedCharacteristics.value) {
        chips.push({
            label: `Excl. characteristic: ${ch}`,
            remove: () => {
                excludedCharacteristics.value = excludedCharacteristics.value.filter((v) => v !== ch);
                filter();
            },
        });
    }
    return chips;
});

// --- Feature 5: Section filter count badges ---
const statsFilterCount = computed(() => {
    let count = 0;
    for (const stat of statFields) {
        if (filterParams.value[`${stat}_min` as keyof typeof filterParams.value]) count++;
        if (filterParams.value[`${stat}_max` as keyof typeof filterParams.value]) count++;
    }
    if (filterParams.value.base) count++;
    return count;
});

const actionsFilterCount = computed(() => {
    let count = 0;
    if (selectedActions.value.length) count += selectedActions.value.length;
    const actionKeys = [
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
    ] as const;
    for (const key of actionKeys) {
        if (filterParams.value[key]) count++;
    }
    return count;
});

const abilitiesFilterCount = computed(() => {
    let count = 0;
    if (selectedAbilities.value.length) count += selectedAbilities.value.length;
    const abilityKeys = ['ability_name', 'ability_suits', 'ability_defensive_type', 'ability_costs_stone', 'ability_description'] as const;
    for (const key of abilityKeys) {
        if (filterParams.value[key]) count++;
    }
    return count;
});

const triggersFilterCount = computed(() => {
    let count = 0;
    if (selectedTriggers.value.length) count += selectedTriggers.value.length;
    if (filterParams.value.trigger_suits) count++;
    if (filterParams.value.trigger_description) count++;
    return count;
});

const tokensFilterCount = computed(() => selectedTokens.value.length);

const markersFilterCount = computed(() => selectedMarkers.value.length);

// --- Feature 3: Keyboard shortcut ---
const handleSlashKey = (e: KeyboardEvent) => {
    const tag = (e.target as HTMLElement)?.tagName?.toLowerCase();
    if (tag === 'input' || tag === 'textarea' || (e.target as HTMLElement)?.isContentEditable) return;
    if (e.key === '/') {
        e.preventDefault();
        const el = syntaxBarInputRef.value?.$el;
        if (el?.focus) {
            el.focus();
        } else {
            el?.querySelector('input')?.focus();
        }
    }
};

// --- Feature 7: Name autocomplete (integrated into unified search bar) ---
const nameSuggestions = ref<any[]>([]);
const showNameSuggestions = ref(false);
const nameSuggestionIndex = ref(-1);
let debounceTimer: ReturnType<typeof setTimeout> | null = null;

const isBareText = (text: string): boolean => !text.includes(':') && !/[><=]/.test(text);

const fetchNameSuggestions = () => {
    if (debounceTimer) clearTimeout(debounceTimer);
    const text = syntaxInput.value.trim();
    if (!isBareText(text) || text.length < 2) {
        nameSuggestions.value = [];
        showNameSuggestions.value = false;
        return;
    }
    debounceTimer = setTimeout(async () => {
        try {
            const res = await fetch(`/api/characters/search?q=${encodeURIComponent(text)}`);
            if (res.ok) {
                const data = await res.json();
                nameSuggestions.value = (data.data ?? data).slice(0, 8);
                showNameSuggestions.value = nameSuggestions.value.length > 0;
                nameSuggestionIndex.value = -1;
            }
        } catch {
            nameSuggestions.value = [];
            showNameSuggestions.value = false;
        }
    }, 250);
};

const selectNameSuggestion = (item: any) => {
    syntaxInput.value = item.name;
    showNameSuggestions.value = false;
    nameSuggestions.value = [];
    applySyntax();
};

// --- Feature 8: Search presets ---
const searchPresets = [
    { label: 'All Masters', params: { station: 'master' } },
    { label: 'Cheap Minions (Cost <= 5)', params: { station: 'minion', cost_max: '5' } },
    { label: 'Fast Models (Speed >= 6)', params: { speed_min: '6' } },
    { label: 'Tanky (Health >= 10)', params: { health_min: '10' } },
    { label: 'High Defense (Defense >= 6)', params: { defense_min: '6' } },
    { label: 'High Willpower (WP >= 7)', params: { willpower_min: '7' } },
];

const applyPreset = (preset: (typeof searchPresets)[number]) => {
    resetFilters();
    const urlParams = new URLSearchParams();
    for (const [k, v] of Object.entries(preset.params)) {
        urlParams.set(k, v);
    }
    restoreFromURL(urlParams);
    filter();
};

// --- Feature 10: Saved searches ---
const page = usePage<SharedData>();
const isLoggedIn = computed(() => !!page.props.auth?.user);
const showSaveDialog = ref(false);
const saveSearchName = ref('');

const saveCurrentSearch = async () => {
    if (!saveSearchName.value.trim()) return;
    const params = buildCurrentParams();
    router.post(
        route('search.save'),
        { name: saveSearchName.value.trim(), query_params: params },
        {
            preserveState: true,
            onSuccess: () => {
                showSaveDialog.value = false;
                saveSearchName.value = '';
            },
        },
    );
};

const loadSavedSearch = (savedSearch: any) => {
    resetFilters();
    const urlParams = new URLSearchParams();
    for (const [k, v] of Object.entries(savedSearch.query_params as Record<string, string>)) {
        urlParams.set(k, v);
    }
    restoreFromURL(urlParams);
    filter();
};

const deleteSavedSearch = (savedSearch: any) => {
    router.delete(route('search.delete', { saved_search: savedSearch.id }), {
        preserveState: true,
    });
};

// --- Feature 1: Rotating syntax bar placeholder ---
const syntaxPlaceholders = [
    'f:neverborn st:master kw:amalgam cost>=5',
    'health>=10 defense>=6',
    'kw:Beast speed>=6',
    'o:"heal friendly" f:guild',
    'st:minion cost<=4 f:bayou',
    '(f:arcanists OR f:neverborn) st:minion cost<=5',
    'ab:Armor health>=8',
    'is:versatile f:guild cost<=6',
];
const placeholderIndex = ref(0);
let placeholderTimer: ReturnType<typeof setInterval> | null = null;

// --- Feature 7: Search history (localStorage) ---
const SEARCH_HISTORY_KEY = 'biggerhat_search_history';
const MAX_HISTORY = 10;
const searchHistory = ref<string[]>([]);
const showHistory = ref(false);

const loadHistory = () => {
    try {
        const stored = localStorage.getItem(SEARCH_HISTORY_KEY);
        if (stored) searchHistory.value = JSON.parse(stored);
    } catch {
        searchHistory.value = [];
    }
};

const saveToHistory = (syntax: string) => {
    const trimmed = syntax.trim();
    if (!trimmed) return;
    searchHistory.value = [trimmed, ...searchHistory.value.filter((h) => h !== trimmed)].slice(0, MAX_HISTORY);
    try {
        localStorage.setItem(SEARCH_HISTORY_KEY, JSON.stringify(searchHistory.value));
    } catch {
        // Private browsing or quota exceeded
    }
};

const clearHistory = () => {
    searchHistory.value = [];
    try {
        localStorage.removeItem(SEARCH_HISTORY_KEY);
    } catch {
        // ignore
    }
};

const applyHistoryItem = (syntax: string) => {
    syntaxInput.value = syntax;
    showHistory.value = false;
    applySyntax();
};

// --- Feature 6: Clickable result attributes ---
const applyFactionFilter = (factionValue: string) => {
    const name = factionValueToName(factionValue);
    if (name && !selectedFactions.value.includes(name)) {
        selectedFactions.value.push(name);
        filter();
    }
};

const applyStationFilter = (stationValue: string) => {
    filterParams.value.station = stationValue;
    filter();
};

// --- Feature 8: CSV export ---
const exportUrl = computed(() => {
    const search = new URLSearchParams(window.location.search).toString();
    return route('search.export') + (search ? '?' + search : '');
});

// --- Feature 10: Syntax bar autocomplete ---
const syntaxFieldOptions = computed(() => {
    const map: Record<string, string[]> = {};
    map.faction = props.factions.map((f: any) => f.value);
    map.station = props.stations.map((s: any) => s.value);
    map.keyword = props.keywords.map((k: any) => k.slug ?? k.name);
    map.characteristic = props.characteristics.map((c: any) => c.slug ?? c.name);
    map.action = props.actions.map((a: any) => a.name);
    map.ability = props.abilities.map((a: any) => a.name);
    map.trigger = props.triggers.map((t: any) => t.name);
    map.token = props.tokens_list.map((t: any) => t.name);
    map.marker = props.markers_list.map((m: any) => m.name);
    map.base = props.base_sizes.map((b: any) => b.value);
    map.sort = props.sort_options.map((s: any) => s.value);
    map.sort_type = props.sort_types.map((s: any) => s.value);
    map.page_view = props.view_options.map((v: any) => v.value);
    return map;
});
const syntaxSuggestions = ref<string[]>([]);
const showSyntaxSuggestions = ref(false);
const syntaxSuggestionIndex = ref(-1);
const syntaxBarInputRef = ref<InstanceType<typeof Input> | null>(null);

const getCurrentSyntaxToken = (): { token: string; field: string | null; partial: string; start: number; end: number } | null => {
    const el = syntaxBarInputRef.value?.$el?.querySelector?.('input') ?? syntaxBarInputRef.value?.$el;
    if (!el) return null;
    const cursorPos = el.selectionStart ?? syntaxInput.value.length;
    const text = syntaxInput.value;
    let start = cursorPos;
    while (start > 0 && text[start - 1] !== ' ') start--;
    let end = cursorPos;
    while (end < text.length && text[end] !== ' ') end++;
    const token = text.slice(start, end);
    const colonIdx = token.indexOf(':');
    if (colonIdx >= 0) {
        const prefix = token.slice(0, colonIdx).replace(/^-/, '').toLowerCase();
        const resolved = fieldMap[prefix];
        if (resolved) {
            return { token, field: resolved, partial: token.slice(colonIdx + 1).replace(/^"/, ''), start, end };
        }
    }
    return { token, field: null, partial: token, start, end };
};

const updateSyntaxSuggestions = () => {
    const info = getCurrentSyntaxToken();
    if (!info || !info.field || !syntaxFieldOptions.value[info.field]) {
        syntaxSuggestions.value = [];
        showSyntaxSuggestions.value = false;
        return;
    }
    const options = syntaxFieldOptions.value[info.field];
    const partial = info.partial.toLowerCase();
    syntaxSuggestions.value = options.filter((o) => o.toLowerCase().includes(partial)).slice(0, 8);
    showSyntaxSuggestions.value = syntaxSuggestions.value.length > 0;
    syntaxSuggestionIndex.value = -1;
};

const applySyntaxSuggestion = (suggestion: string) => {
    const info = getCurrentSyntaxToken();
    if (!info) return;
    const colonIdx = info.token.indexOf(':');
    const prefix = colonIdx >= 0 ? info.token.slice(0, colonIdx + 1) : '';
    const quoted = suggestion.includes(' ') ? `"${suggestion}"` : suggestion;
    const before = syntaxInput.value.slice(0, info.start);
    const after = syntaxInput.value.slice(info.end);
    syntaxInput.value = before + prefix + quoted + after;
    showSyntaxSuggestions.value = false;
    syntaxSuggestionIndex.value = -1;
};

const handleSearchKeydown = (e: KeyboardEvent) => {
    // Name autocomplete navigation (bare text mode)
    if (showNameSuggestions.value && nameSuggestions.value.length > 0) {
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            nameSuggestionIndex.value = Math.min(nameSuggestionIndex.value + 1, nameSuggestions.value.length - 1);
            return;
        }
        if (e.key === 'ArrowUp') {
            e.preventDefault();
            nameSuggestionIndex.value = Math.max(nameSuggestionIndex.value - 1, -1);
            return;
        }
        if (e.key === 'Enter' && nameSuggestionIndex.value >= 0) {
            e.preventDefault();
            selectNameSuggestion(nameSuggestions.value[nameSuggestionIndex.value]);
            return;
        }
        if (e.key === 'Escape') {
            showNameSuggestions.value = false;
            return;
        }
    }
    // Syntax autocomplete navigation
    if (showSyntaxSuggestions.value && syntaxSuggestions.value.length > 0) {
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            syntaxSuggestionIndex.value = Math.min(syntaxSuggestionIndex.value + 1, syntaxSuggestions.value.length - 1);
            return;
        }
        if (e.key === 'ArrowUp') {
            e.preventDefault();
            syntaxSuggestionIndex.value = Math.max(syntaxSuggestionIndex.value - 1, -1);
            return;
        }
        if ((e.key === 'Enter' || e.key === 'Tab') && syntaxSuggestionIndex.value >= 0) {
            e.preventDefault();
            applySyntaxSuggestion(syntaxSuggestions.value[syntaxSuggestionIndex.value]);
            return;
        }
        if (e.key === 'Escape') {
            e.preventDefault();
            showSyntaxSuggestions.value = false;
            return;
        }
    }
    if (e.key === 'Enter') {
        e.preventDefault();
        showNameSuggestions.value = false;
        applySyntax();
    }
};

const handleSearchInput = () => {
    const text = syntaxInput.value.trim();
    if (isBareText(text)) {
        showSyntaxSuggestions.value = false;
        fetchNameSuggestions();
    } else {
        showNameSuggestions.value = false;
        nameSuggestions.value = [];
        updateSyntaxSuggestions();
    }
};

const resultCount = computed(() => props.results?.data?.length ?? 0);
const { delays } = useStaggeredEntry(resultCount);

const isLoading = ref(false);
onMounted(() => {
    router.on('start', () => {
        isLoading.value = true;
    });
    router.on('finish', () => {
        isLoading.value = false;
    });
    // Feature 3: keyboard shortcut
    document.addEventListener('keydown', handleSlashKey);
    // Initialize syntax bar
    syntaxInput.value = toSyntax(buildCurrentParams());
    // Feature 1: rotating placeholder
    placeholderTimer = setInterval(() => {
        placeholderIndex.value = (placeholderIndex.value + 1) % syntaxPlaceholders.length;
    }, 4000);
    // Feature 7: search history
    loadHistory();
});

onUnmounted(() => {
    document.removeEventListener('keydown', handleSlashKey);
    if (placeholderTimer) clearInterval(placeholderTimer);
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
                <div class="my-auto flex items-center gap-2 px-2 py-0 text-xs text-muted-foreground md:py-2 md:text-sm md:text-foreground">
                    <template v-if="props.result_breakdown.upgrades > 0">
                        {{ props.result_breakdown.characters }} {{ props.result_breakdown.characters === 1 ? 'character' : 'characters' }},
                        {{ props.result_breakdown.upgrades }} {{ props.result_breakdown.upgrades === 1 ? 'upgrade' : 'upgrades' }}
                    </template>
                    <template v-else> {{ props.result_count }} {{ props.result_count === 1 ? 'result' : 'results' }} </template>
                    <button
                        v-if="activeFilterCount > 0"
                        class="rounded-md p-1 text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                        title="Copy search URL"
                        @click="copySearchUrl"
                    >
                        <Check v-if="urlCopied" class="size-3.5 text-green-500" />
                        <ClipboardCopy v-else class="size-3.5" />
                    </button>
                    <a
                        v-if="activeFilterCount > 0 && props.result_count > 0"
                        :href="exportUrl"
                        class="rounded-md p-1 text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                        title="Export CSV"
                    >
                        <Download class="size-3.5" />
                    </a>
                </div>
            </template>
        </PageBanner>

        <!-- Unified search bar -->
        <div class="container mx-auto mb-2 sm:px-4">
            <div class="flex items-center gap-2">
                <div class="relative flex-1">
                    <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        ref="syntaxBarInputRef"
                        v-model="syntaxInput"
                        type="text"
                        :placeholder="syntaxPlaceholders[placeholderIndex]"
                        class="border-2 border-primary pl-10 pr-10"
                        @keydown="handleSearchKeydown"
                        @input="handleSearchInput"
                        @focus="
                            () => {
                                handleSearchInput();
                                if (!syntaxInput.trim()) showHistory = true;
                            }
                        "
                        @blur="
                            setTimeout(() => {
                                showSyntaxSuggestions = false;
                                showNameSuggestions = false;
                                showHistory = false;
                            }, 200)
                        "
                    />
                    <button
                        v-if="syntaxInput"
                        class="absolute right-8 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                        @click="
                            syntaxInput = '';
                            showNameSuggestions = false;
                            resetFilters();
                            filter();
                        "
                    >
                        <X class="h-4 w-4" />
                    </button>
                    <!-- Name autocomplete dropdown (bare text) -->
                    <div
                        v-if="showNameSuggestions && nameSuggestions.length"
                        class="absolute left-0 right-0 top-full z-50 mt-1 overflow-hidden rounded-md border bg-popover shadow-lg"
                    >
                        <button
                            v-for="(item, idx) in nameSuggestions"
                            :key="item.id ?? idx"
                            class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm transition-colors hover:bg-accent"
                            :class="{ 'bg-accent': idx === nameSuggestionIndex }"
                            @mousedown.prevent="selectNameSuggestion(item)"
                        >
                            <span class="truncate">{{ item.name }}</span>
                            <Badge v-if="item.faction" variant="outline" class="ml-auto shrink-0 text-[10px]">{{ item.faction }}</Badge>
                        </button>
                    </div>
                    <!-- Syntax autocomplete dropdown -->
                    <div
                        v-if="showSyntaxSuggestions && syntaxSuggestions.length"
                        class="absolute left-0 right-0 top-full z-50 mt-1 overflow-hidden rounded-md border bg-popover shadow-lg"
                    >
                        <button
                            v-for="(suggestion, idx) in syntaxSuggestions"
                            :key="idx"
                            class="flex w-full items-center px-3 py-1.5 text-left font-mono text-xs transition-colors hover:bg-accent"
                            :class="{ 'bg-accent': idx === syntaxSuggestionIndex }"
                            @mousedown.prevent="applySyntaxSuggestion(suggestion)"
                        >
                            {{ suggestion }}
                        </button>
                    </div>
                    <!-- Search history dropdown -->
                    <div
                        v-if="showHistory && !showSyntaxSuggestions && !showNameSuggestions && !syntaxInput.trim() && searchHistory.length"
                        class="absolute left-0 right-0 top-full z-50 mt-1 overflow-hidden rounded-md border bg-popover shadow-lg"
                    >
                        <div
                            class="flex items-center justify-between border-b px-3 py-1.5 text-[10px] font-semibold uppercase tracking-wide text-muted-foreground"
                        >
                            Recent searches
                            <button class="text-muted-foreground hover:text-foreground" @mousedown.prevent="clearHistory">Clear history</button>
                        </div>
                        <button
                            v-for="(item, idx) in searchHistory"
                            :key="idx"
                            class="flex w-full items-center px-3 py-1.5 text-left font-mono text-xs transition-colors hover:bg-accent"
                            @mousedown.prevent="applyHistoryItem(item)"
                        >
                            {{ item }}
                        </button>
                    </div>
                </div>
                <Button variant="ghost" size="sm" class="h-10 w-10 shrink-0 p-0" title="Syntax help" @click="showSyntaxHelp = true">
                    <HelpCircle class="h-4 w-4" />
                </Button>
            </div>
        </div>

        <!-- Syntax help dialog -->
        <Dialog v-model:open="showSyntaxHelp">
            <DialogContent class="max-h-[80vh] overflow-y-auto sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Search Syntax Reference</DialogTitle>
                </DialogHeader>
                <div class="space-y-3 text-sm">
                    <p class="text-muted-foreground">Type directly into the syntax bar and press Enter to search. Bare words search by name.</p>
                    <div class="space-y-1.5">
                        <p class="font-semibold">Fields</p>
                        <div class="grid grid-cols-2 gap-x-4 gap-y-1 text-xs">
                            <code>f:arcanists</code><span>Faction</span> <code>st:master</code><span>Station</span> <code>kw:amalgam</code
                            ><span>Keyword</span> <code>char:Living</code><span>Characteristic</span> <code>act:Obey</code><span>Action</span>
                            <code>ab:Armor</code><span>Ability</span> <code>tr:Critical</code><span>Trigger</span> <code>token:Focus</code
                            ><span>Token</span> <code>marker:Scrap</code><span>Marker</span> <code>o:"heal friendly"</code><span>Rules text</span>
                            <code>base:30mm</code><span>Base size</span>
                        </div>
                    </div>
                    <div class="space-y-1.5">
                        <p class="font-semibold">Numeric Comparisons</p>
                        <div class="grid grid-cols-2 gap-x-4 gap-y-1 text-xs">
                            <code>cost>=5</code><span>Cost at least 5</span> <code v-text="'health<=8'" /><span>Health at most 8</span>
                            <code>speed=5</code><span>Speed exactly 5</span> <code>defense>5</code><span>Defense above 5</span> <code>wp>=6</code
                            ><span>Willpower at least 6</span>
                        </div>
                    </div>
                    <div class="space-y-1.5">
                        <p class="font-semibold">Combining & Excluding</p>
                        <div class="space-y-1 text-xs">
                            <p><code>-f:guild</code> -- exclude a faction</p>
                            <p><code>-kw:Undead</code> -- exclude a keyword</p>
                            <p><code>(kw:Beast OR kw:Chimera)</code> -- match ANY keyword</p>
                        </div>
                    </div>
                    <div class="space-y-1.5">
                        <p class="font-semibold">Boolean Filters</p>
                        <div class="grid grid-cols-2 gap-x-4 gap-y-1 text-xs">
                            <code>is:versatile</code><span>Versatile models</span> <code>is:totem</code><span>Totems</span> <code>has:demise</code
                            ><span>Has Demise ability</span> <code>sf:arcanists</code><span>Second faction</span>
                        </div>
                    </div>
                    <div class="space-y-1.5">
                        <p class="font-semibold">Stat Comparison</p>
                        <div class="grid grid-cols-2 gap-x-4 gap-y-1 text-xs">
                            <code>defense>willpower</code><span>Df higher than Wp</span> <code v-text="'health>=cost'" /><span
                                >Health at least cost</span
                            >
                        </div>
                    </div>
                    <div class="space-y-1.5">
                        <p class="font-semibold">Other</p>
                        <div class="space-y-1 text-xs">
                            <p><code>count>=5</code> -- result count at least 5</p>
                            <p><code>order:cost</code> -- sort by field</p>
                            <p><code>dir:descending</code> -- sort direction</p>
                            <p><code>view:table</code> -- switch view mode</p>
                        </div>
                    </div>
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="showSyntaxHelp = false">Close</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Tabs + presets + save + mobile filter trigger -->
        <div class="container mx-auto mb-2 flex items-center justify-between sm:px-4">
            <div class="flex items-center gap-2">
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

                <!-- Search presets -->
                <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                        <Button variant="outline" size="sm" class="h-8 gap-1 text-xs">
                            <Sparkles class="h-3.5 w-3.5" />
                            <span class="hidden sm:inline">Presets</span>
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="start">
                        <DropdownMenuItem v-for="preset in searchPresets" :key="preset.label" @click="applyPreset(preset)">
                            {{ preset.label }}
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>

                <!-- Saved searches -->
                <template v-if="isLoggedIn">
                    <DropdownMenu v-if="props.saved_searches?.length">
                        <DropdownMenuTrigger as-child>
                            <Button variant="outline" size="sm" class="h-8 gap-1 text-xs">
                                <Bookmark class="h-3.5 w-3.5" />
                                <span class="hidden sm:inline">Saved</span>
                                <Badge variant="secondary" class="ml-1 px-1 py-0 text-[9px]">{{ props.saved_searches.length }}</Badge>
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="start">
                            <DropdownMenuItem
                                v-for="ss in props.saved_searches"
                                :key="ss.id"
                                class="flex items-center justify-between gap-2"
                                @click="loadSavedSearch(ss)"
                            >
                                <span class="truncate">{{ ss.name }}</span>
                                <button
                                    class="shrink-0 rounded-sm p-0.5 text-muted-foreground hover:text-destructive"
                                    @click.stop="deleteSavedSearch(ss)"
                                >
                                    <Trash2 class="h-3 w-3" />
                                </button>
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                    <Button v-if="activeFilterCount > 0" variant="outline" size="sm" class="h-8 gap-1 text-xs" @click="showSaveDialog = true">
                        <Bookmark class="h-3.5 w-3.5" />
                        <span class="hidden sm:inline">Save</span>
                    </Button>
                </template>
            </div>
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
                                <label class="text-sm font-medium">Game Mode</label>
                                <SearchableMultiselect
                                    v-model="selectedGameModes"
                                    placeholder="Standard (default)"
                                    :options="props.game_mode_types"
                                />
                            </div>
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
                                <div class="flex items-center justify-between">
                                    <label class="text-sm font-medium">Keywords</label>
                                    <button
                                        v-if="selectedKeywords.length > 1"
                                        class="rounded px-1.5 py-0.5 text-[10px] font-semibold"
                                        :class="keywordLogic === 'and' ? 'bg-primary text-primary-foreground' : 'bg-muted text-muted-foreground'"
                                        @click="keywordLogic = keywordLogic === 'and' ? 'or' : 'and'"
                                    >
                                        {{ keywordLogic === 'and' ? 'ALL' : 'ANY' }}
                                    </button>
                                </div>
                                <SearchableMultiselect
                                    v-model="selectedKeywords"
                                    placeholder="Select Keywords"
                                    :options="props.keywords"
                                    option-value="slug"
                                />
                            </div>
                            <div class="space-y-2">
                                <div class="flex items-center justify-between">
                                    <label class="text-sm font-medium">Characteristics</label>
                                    <button
                                        v-if="selectedCharacteristics.length > 1"
                                        class="rounded px-1.5 py-0.5 text-[10px] font-semibold"
                                        :class="
                                            characteristicLogic === 'and' ? 'bg-primary text-primary-foreground' : 'bg-muted text-muted-foreground'
                                        "
                                        @click="characteristicLogic = characteristicLogic === 'and' ? 'or' : 'and'"
                                    >
                                        {{ characteristicLogic === 'and' ? 'ALL' : 'ANY' }}
                                    </button>
                                </div>
                                <SearchableMultiselect
                                    v-model="selectedCharacteristics"
                                    placeholder="Select Characteristics"
                                    :options="props.characteristics"
                                    option-value="slug"
                                />
                            </div>
                            <div class="space-y-2">
                                <label class="text-sm font-medium">Rules Text</label>
                                <Input
                                    v-model="filterParams.description"
                                    type="text"
                                    placeholder="Search rules text..."
                                    class="h-8 border-2 border-primary text-xs"
                                />
                            </div>

                            <!-- Exclusions -->
                            <Collapsible v-model:open="sectionsOpen.exclusions">
                                <CollapsibleTrigger
                                    class="flex w-full items-center justify-between rounded-md bg-muted px-2.5 py-1.5 text-xs font-medium hover:bg-muted/80"
                                >
                                    Exclude
                                    <div class="flex items-center gap-1.5">
                                        <Badge
                                            v-if="
                                                excludedGameModes.length +
                                                excludedFactions.length +
                                                excludedKeywords.length +
                                                excludedCharacteristics.length
                                            "
                                            variant="secondary"
                                            class="px-1 py-0 text-[9px]"
                                        >
                                            {{
                                                excludedGameModes.length +
                                                excludedFactions.length +
                                                excludedKeywords.length +
                                                excludedCharacteristics.length
                                            }}
                                        </Badge>
                                        <ChevronDown class="h-3.5 w-3.5 transition-transform" :class="{ 'rotate-180': sectionsOpen.exclusions }" />
                                    </div>
                                </CollapsibleTrigger>
                                <CollapsibleContent class="space-y-3 px-1 pt-2">
                                    <div class="space-y-1">
                                        <label class="text-xs font-medium text-muted-foreground">Game Modes</label>
                                        <SearchableMultiselect
                                            v-model="excludedGameModes"
                                            placeholder="Exclude Game Modes"
                                            :options="props.game_mode_types"
                                        />
                                    </div>
                                    <div class="space-y-1">
                                        <label class="text-xs font-medium text-muted-foreground">Factions</label>
                                        <SearchableMultiselect
                                            v-model="excludedFactions"
                                            placeholder="Exclude Factions"
                                            :options="props.factions"
                                            option-value="name"
                                        />
                                    </div>
                                    <div class="space-y-1">
                                        <label class="text-xs font-medium text-muted-foreground">Keywords</label>
                                        <SearchableMultiselect
                                            v-model="excludedKeywords"
                                            placeholder="Exclude Keywords"
                                            :options="props.keywords"
                                            option-value="slug"
                                        />
                                    </div>
                                    <div class="space-y-1">
                                        <label class="text-xs font-medium text-muted-foreground">Characteristics</label>
                                        <SearchableMultiselect
                                            v-model="excludedCharacteristics"
                                            placeholder="Exclude Characteristics"
                                            :options="props.characteristics"
                                            option-value="slug"
                                        />
                                    </div>
                                </CollapsibleContent>
                            </Collapsible>

                            <!-- Advanced -->
                            <div class="border-t pt-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Advanced</div>

                            <!-- Stats -->
                            <Collapsible :open="sectionsOpen.advancedStats" @update:open="toggleAdvancedSection('advancedStats')">
                                <CollapsibleTrigger
                                    class="flex w-full items-center justify-between rounded-md bg-muted px-2.5 py-1.5 text-xs font-medium hover:bg-muted/80"
                                >
                                    Stats
                                    <div class="flex items-center gap-1.5">
                                        <Badge v-if="statsFilterCount" variant="secondary" class="px-1 py-0 text-[9px]">{{ statsFilterCount }}</Badge>
                                        <ChevronDown class="h-3.5 w-3.5 transition-transform" :class="{ 'rotate-180': sectionsOpen.advancedStats }" />
                                    </div>
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
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium">Base Size</label>
                                        <ClearableSelect
                                            v-model="filterParams.base"
                                            placeholder="Any Base"
                                            :options="props.base_sizes"
                                            trigger-class="border-2 border-primary rounded"
                                        />
                                    </div>
                                    <div class="space-y-1">
                                        <label class="text-sm font-medium">Stat Comparison</label>
                                        <div class="flex items-center gap-1">
                                            <ClearableSelect
                                                v-model="statCompareLeft"
                                                placeholder="Stat"
                                                :options="statCompareOptions"
                                                trigger-class="border-2 border-primary rounded"
                                            />
                                            <ClearableSelect
                                                v-model="statCompareOp"
                                                placeholder="Op"
                                                :options="statCompareOps"
                                                trigger-class="border-2 border-primary rounded"
                                            />
                                            <ClearableSelect
                                                v-model="statCompareRight"
                                                placeholder="Stat"
                                                :options="statCompareOptions"
                                                trigger-class="border-2 border-primary rounded"
                                            />
                                        </div>
                                    </div>
                                </CollapsibleContent>
                            </Collapsible>

                            <!-- Actions -->
                            <Collapsible :open="sectionsOpen.advancedActions" @update:open="toggleAdvancedSection('advancedActions')">
                                <CollapsibleTrigger
                                    class="flex w-full items-center justify-between rounded-md bg-muted px-2.5 py-1.5 text-xs font-medium hover:bg-muted/80"
                                >
                                    Actions
                                    <div class="flex items-center gap-1.5">
                                        <Badge v-if="actionsFilterCount" variant="secondary" class="px-1 py-0 text-[9px]">{{
                                            actionsFilterCount
                                        }}</Badge>
                                        <ChevronDown
                                            class="h-3.5 w-3.5 transition-transform"
                                            :class="{ 'rotate-180': sectionsOpen.advancedActions }"
                                        />
                                    </div>
                                </CollapsibleTrigger>
                                <CollapsibleContent class="space-y-3 px-1 pt-2">
                                    <div class="space-y-2">
                                        <div class="flex items-center justify-between">
                                            <label class="text-sm font-medium">Name</label>
                                            <button
                                                v-if="selectedActions.length > 1"
                                                class="rounded px-1.5 py-0.5 text-[10px] font-semibold"
                                                :class="
                                                    actionLogic === 'and' ? 'bg-primary text-primary-foreground' : 'bg-muted text-muted-foreground'
                                                "
                                                @click="actionLogic = actionLogic === 'and' ? 'or' : 'and'"
                                            >
                                                {{ actionLogic === 'and' ? 'ALL' : 'ANY' }}
                                            </button>
                                        </div>
                                        <SearchableMultiselect v-model="selectedActions" placeholder="Select Actions" :options="props.actions" />
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
                                    <div class="flex items-center gap-1.5">
                                        <Badge v-if="abilitiesFilterCount" variant="secondary" class="px-1 py-0 text-[9px]">{{
                                            abilitiesFilterCount
                                        }}</Badge>
                                        <ChevronDown
                                            class="h-3.5 w-3.5 transition-transform"
                                            :class="{ 'rotate-180': sectionsOpen.advancedAbilities }"
                                        />
                                    </div>
                                </CollapsibleTrigger>
                                <CollapsibleContent class="space-y-3 px-1 pt-2">
                                    <div class="space-y-2">
                                        <div class="flex items-center justify-between">
                                            <label class="text-sm font-medium">Name</label>
                                            <button
                                                v-if="selectedAbilities.length > 1"
                                                class="rounded px-1.5 py-0.5 text-[10px] font-semibold"
                                                :class="
                                                    abilityLogic === 'and' ? 'bg-primary text-primary-foreground' : 'bg-muted text-muted-foreground'
                                                "
                                                @click="abilityLogic = abilityLogic === 'and' ? 'or' : 'and'"
                                            >
                                                {{ abilityLogic === 'and' ? 'ALL' : 'ANY' }}
                                            </button>
                                        </div>
                                        <SearchableMultiselect
                                            v-model="selectedAbilities"
                                            placeholder="Select Abilities"
                                            :options="props.abilities"
                                        />
                                        <Input
                                            v-model="filterParams.ability_name"
                                            type="text"
                                            placeholder="or search ability name..."
                                            class="h-8 border-2 border-primary text-xs"
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

                            <!-- Triggers -->
                            <Collapsible :open="sectionsOpen.advancedTriggers" @update:open="toggleAdvancedSection('advancedTriggers')">
                                <CollapsibleTrigger
                                    class="flex w-full items-center justify-between rounded-md bg-muted px-2.5 py-1.5 text-xs font-medium hover:bg-muted/80"
                                >
                                    Triggers
                                    <div class="flex items-center gap-1.5">
                                        <Badge v-if="triggersFilterCount" variant="secondary" class="px-1 py-0 text-[9px]">{{
                                            triggersFilterCount
                                        }}</Badge>
                                        <ChevronDown
                                            class="h-3.5 w-3.5 transition-transform"
                                            :class="{ 'rotate-180': sectionsOpen.advancedTriggers }"
                                        />
                                    </div>
                                </CollapsibleTrigger>
                                <CollapsibleContent class="space-y-3 px-1 pt-2">
                                    <div class="space-y-2">
                                        <div class="flex items-center justify-between">
                                            <label class="text-sm font-medium">Trigger</label>
                                            <button
                                                v-if="selectedTriggers.length > 1"
                                                class="rounded px-1.5 py-0.5 text-[10px] font-semibold"
                                                :class="
                                                    triggerLogic === 'and' ? 'bg-primary text-primary-foreground' : 'bg-muted text-muted-foreground'
                                                "
                                                @click="triggerLogic = triggerLogic === 'and' ? 'or' : 'and'"
                                            >
                                                {{ triggerLogic === 'and' ? 'ALL' : 'ANY' }}
                                            </button>
                                        </div>
                                        <SearchableMultiselect v-model="selectedTriggers" placeholder="Select Triggers" :options="props.triggers" />
                                    </div>
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium">Suit</label>
                                        <ClearableSelect
                                            v-model="filterParams.trigger_suits"
                                            placeholder="Any Suit"
                                            :options="props.suits"
                                            trigger-class="border-2 border-primary rounded"
                                        />
                                    </div>
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium">Description</label>
                                        <Input
                                            v-model="filterParams.trigger_description"
                                            type="text"
                                            placeholder="Search description..."
                                            class="h-8 border-2 border-primary text-xs"
                                        />
                                    </div>
                                </CollapsibleContent>
                            </Collapsible>

                            <!-- Tokens -->
                            <Collapsible :open="sectionsOpen.advancedTokens" @update:open="toggleAdvancedSection('advancedTokens')">
                                <CollapsibleTrigger
                                    class="flex w-full items-center justify-between rounded-md bg-muted px-2.5 py-1.5 text-xs font-medium hover:bg-muted/80"
                                >
                                    Tokens
                                    <div class="flex items-center gap-1.5">
                                        <Badge v-if="tokensFilterCount" variant="secondary" class="px-1 py-0 text-[9px]">{{
                                            tokensFilterCount
                                        }}</Badge>
                                        <ChevronDown
                                            class="h-3.5 w-3.5 transition-transform"
                                            :class="{ 'rotate-180': sectionsOpen.advancedTokens }"
                                        />
                                    </div>
                                </CollapsibleTrigger>
                                <CollapsibleContent class="space-y-3 px-1 pt-2">
                                    <div class="space-y-2">
                                        <div class="flex items-center justify-between">
                                            <label class="text-sm font-medium">Token</label>
                                            <button
                                                v-if="selectedTokens.length > 1"
                                                class="rounded px-1.5 py-0.5 text-[10px] font-semibold"
                                                :class="
                                                    tokenLogic === 'and' ? 'bg-primary text-primary-foreground' : 'bg-muted text-muted-foreground'
                                                "
                                                @click="tokenLogic = tokenLogic === 'and' ? 'or' : 'and'"
                                            >
                                                {{ tokenLogic === 'and' ? 'ALL' : 'ANY' }}
                                            </button>
                                        </div>
                                        <SearchableMultiselect v-model="selectedTokens" placeholder="Select Tokens" :options="props.tokens_list" />
                                    </div>
                                </CollapsibleContent>
                            </Collapsible>

                            <!-- Markers -->
                            <Collapsible :open="sectionsOpen.advancedMarkers" @update:open="toggleAdvancedSection('advancedMarkers')">
                                <CollapsibleTrigger
                                    class="flex w-full items-center justify-between rounded-md bg-muted px-2.5 py-1.5 text-xs font-medium hover:bg-muted/80"
                                >
                                    Markers
                                    <div class="flex items-center gap-1.5">
                                        <Badge v-if="markersFilterCount" variant="secondary" class="px-1 py-0 text-[9px]">{{
                                            markersFilterCount
                                        }}</Badge>
                                        <ChevronDown
                                            class="h-3.5 w-3.5 transition-transform"
                                            :class="{ 'rotate-180': sectionsOpen.advancedMarkers }"
                                        />
                                    </div>
                                </CollapsibleTrigger>
                                <CollapsibleContent class="space-y-3 px-1 pt-2">
                                    <div class="space-y-2">
                                        <div class="flex items-center justify-between">
                                            <label class="text-sm font-medium">Marker</label>
                                            <button
                                                v-if="selectedMarkers.length > 1"
                                                class="rounded px-1.5 py-0.5 text-[10px] font-semibold"
                                                :class="
                                                    markerLogic === 'and' ? 'bg-primary text-primary-foreground' : 'bg-muted text-muted-foreground'
                                                "
                                                @click="markerLogic = markerLogic === 'and' ? 'or' : 'and'"
                                            >
                                                {{ markerLogic === 'and' ? 'ALL' : 'ANY' }}
                                            </button>
                                        </div>
                                        <SearchableMultiselect v-model="selectedMarkers" placeholder="Select Markers" :options="props.markers_list" />
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

        <!-- Save search dialog -->
        <Dialog v-model:open="showSaveDialog">
            <DialogContent class="sm:max-w-sm">
                <DialogHeader>
                    <DialogTitle>Save Search</DialogTitle>
                </DialogHeader>
                <div class="space-y-3">
                    <Input v-model="saveSearchName" placeholder="Enter a name for this search..." @keydown.enter="saveCurrentSearch" />
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="showSaveDialog = false">Cancel</Button>
                    <Button :disabled="!saveSearchName.trim()" @click="saveCurrentSearch">Save</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Search explanation banner -->
        <div v-if="searchExplanation" class="container mx-auto mb-2 sm:px-4">
            <div class="rounded-md bg-muted/50 px-3 py-1.5 text-xs text-muted-foreground">Showing: {{ searchExplanation }}</div>
        </div>

        <!-- Active filter chips -->
        <div v-if="activeFilters.length" class="container mx-auto mb-2 sm:px-4">
            <div class="flex flex-wrap items-center gap-1.5">
                <Badge v-for="(chip, idx) in activeFilters" :key="idx" variant="secondary" class="gap-1 pr-1 text-xs">
                    {{ chip.label }}
                    <button class="ml-0.5 rounded-full p-0.5 hover:bg-muted-foreground/20" @click="chip.remove()">
                        <X class="h-3 w-3" />
                    </button>
                </Badge>
                <Button v-if="activeFilters.length > 1" variant="ghost" size="sm" class="h-6 px-2 text-[10px]" @click="clear"> Clear all </Button>
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
                                <label class="text-xs font-medium text-muted-foreground">Game Mode</label>
                                <SearchableMultiselect
                                    v-model="selectedGameModes"
                                    placeholder="Standard (default)"
                                    :options="props.game_mode_types"
                                />
                            </div>
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
                                <div class="flex items-center justify-between">
                                    <label class="text-xs font-medium text-muted-foreground">Keywords</label>
                                    <button
                                        v-if="selectedKeywords.length > 1"
                                        class="rounded px-1.5 py-0.5 text-[10px] font-semibold"
                                        :class="keywordLogic === 'and' ? 'bg-primary text-primary-foreground' : 'bg-muted text-muted-foreground'"
                                        @click="keywordLogic = keywordLogic === 'and' ? 'or' : 'and'"
                                    >
                                        {{ keywordLogic === 'and' ? 'ALL' : 'ANY' }}
                                    </button>
                                </div>
                                <SearchableMultiselect
                                    v-model="selectedKeywords"
                                    placeholder="Select Keywords"
                                    :options="props.keywords"
                                    option-value="slug"
                                />
                            </div>
                            <div class="space-y-1">
                                <div class="flex items-center justify-between">
                                    <label class="text-xs font-medium text-muted-foreground">Characteristics</label>
                                    <button
                                        v-if="selectedCharacteristics.length > 1"
                                        class="rounded px-1.5 py-0.5 text-[10px] font-semibold"
                                        :class="
                                            characteristicLogic === 'and' ? 'bg-primary text-primary-foreground' : 'bg-muted text-muted-foreground'
                                        "
                                        @click="characteristicLogic = characteristicLogic === 'and' ? 'or' : 'and'"
                                    >
                                        {{ characteristicLogic === 'and' ? 'ALL' : 'ANY' }}
                                    </button>
                                </div>
                                <SearchableMultiselect
                                    v-model="selectedCharacteristics"
                                    placeholder="Select Characteristics"
                                    :options="props.characteristics"
                                    option-value="slug"
                                />
                            </div>
                            <div class="space-y-1">
                                <label class="text-xs font-medium text-muted-foreground">Rules Text</label>
                                <Input v-model="filterParams.description" type="text" placeholder="Search rules text..." class="h-8 text-xs" />
                            </div>
                        </div>

                        <!-- Exclusions -->
                        <Collapsible v-model:open="sectionsOpen.exclusions">
                            <CollapsibleTrigger
                                class="flex w-full items-center justify-between rounded-md bg-secondary px-3 py-2 text-sm font-medium hover:bg-secondary/80"
                            >
                                Exclude
                                <div class="flex items-center gap-1.5">
                                    <Badge
                                        v-if="
                                            excludedGameModes.length +
                                            excludedFactions.length +
                                            excludedKeywords.length +
                                            excludedCharacteristics.length
                                        "
                                        variant="secondary"
                                        class="px-1 py-0 text-[9px]"
                                    >
                                        {{
                                            excludedGameModes.length +
                                            excludedFactions.length +
                                            excludedKeywords.length +
                                            excludedCharacteristics.length
                                        }}
                                    </Badge>
                                    <ChevronDown class="h-4 w-4 transition-transform" :class="{ 'rotate-180': sectionsOpen.exclusions }" />
                                </div>
                            </CollapsibleTrigger>
                            <CollapsibleContent class="space-y-3 px-1 pt-3">
                                <div class="space-y-1">
                                    <label class="text-xs font-medium text-muted-foreground">Game Modes</label>
                                    <SearchableMultiselect
                                        v-model="excludedGameModes"
                                        placeholder="Exclude Game Modes"
                                        :options="props.game_mode_types"
                                    />
                                </div>
                                <div class="space-y-1">
                                    <label class="text-xs font-medium text-muted-foreground">Factions</label>
                                    <SearchableMultiselect
                                        v-model="excludedFactions"
                                        placeholder="Exclude Factions"
                                        :options="props.factions"
                                        option-value="name"
                                    />
                                </div>
                                <div class="space-y-1">
                                    <label class="text-xs font-medium text-muted-foreground">Keywords</label>
                                    <SearchableMultiselect
                                        v-model="excludedKeywords"
                                        placeholder="Exclude Keywords"
                                        :options="props.keywords"
                                        option-value="slug"
                                    />
                                </div>
                                <div class="space-y-1">
                                    <label class="text-xs font-medium text-muted-foreground">Characteristics</label>
                                    <SearchableMultiselect
                                        v-model="excludedCharacteristics"
                                        placeholder="Exclude Characteristics"
                                        :options="props.characteristics"
                                        option-value="slug"
                                    />
                                </div>
                            </CollapsibleContent>
                        </Collapsible>

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
                                        <div class="flex items-center gap-1.5">
                                            <Badge v-if="statsFilterCount" variant="secondary" class="px-1 py-0 text-[9px]">{{
                                                statsFilterCount
                                            }}</Badge>
                                            <ChevronDown
                                                class="h-3.5 w-3.5 transition-transform"
                                                :class="{ 'rotate-180': sectionsOpen.advancedStats }"
                                            />
                                        </div>
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
                                        <div class="space-y-1">
                                            <label class="text-xs font-medium text-muted-foreground">Base Size</label>
                                            <ClearableSelect v-model="filterParams.base" placeholder="Any Base" :options="props.base_sizes" />
                                        </div>
                                        <div class="space-y-1">
                                            <label class="text-xs font-medium text-muted-foreground">Stat Comparison</label>
                                            <div class="flex items-center gap-1">
                                                <ClearableSelect v-model="statCompareLeft" placeholder="Stat" :options="statCompareOptions" />
                                                <ClearableSelect v-model="statCompareOp" placeholder="Op" :options="statCompareOps" />
                                                <ClearableSelect v-model="statCompareRight" placeholder="Stat" :options="statCompareOptions" />
                                            </div>
                                        </div>
                                    </CollapsibleContent>
                                </Collapsible>

                                <!-- Actions -->
                                <Collapsible :open="sectionsOpen.advancedActions" @update:open="toggleAdvancedSection('advancedActions')">
                                    <CollapsibleTrigger
                                        class="flex w-full items-center justify-between rounded-md bg-muted px-2.5 py-1.5 text-xs font-medium hover:bg-muted/80"
                                    >
                                        Actions
                                        <div class="flex items-center gap-1.5">
                                            <Badge v-if="actionsFilterCount" variant="secondary" class="px-1 py-0 text-[9px]">{{
                                                actionsFilterCount
                                            }}</Badge>
                                            <ChevronDown
                                                class="h-3.5 w-3.5 transition-transform"
                                                :class="{ 'rotate-180': sectionsOpen.advancedActions }"
                                            />
                                        </div>
                                    </CollapsibleTrigger>
                                    <CollapsibleContent class="space-y-3 px-1 pt-2">
                                        <div class="space-y-1">
                                            <div class="flex items-center justify-between">
                                                <label class="text-xs font-medium text-muted-foreground">Name</label>
                                                <button
                                                    v-if="selectedActions.length > 1"
                                                    class="rounded px-1.5 py-0.5 text-[10px] font-semibold"
                                                    :class="
                                                        actionLogic === 'and'
                                                            ? 'bg-primary text-primary-foreground'
                                                            : 'bg-muted text-muted-foreground'
                                                    "
                                                    @click="actionLogic = actionLogic === 'and' ? 'or' : 'and'"
                                                >
                                                    {{ actionLogic === 'and' ? 'ALL' : 'ANY' }}
                                                </button>
                                            </div>
                                            <SearchableMultiselect v-model="selectedActions" placeholder="Select Actions" :options="props.actions" />
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
                                        <div class="flex items-center gap-1.5">
                                            <Badge v-if="abilitiesFilterCount" variant="secondary" class="px-1 py-0 text-[9px]">{{
                                                abilitiesFilterCount
                                            }}</Badge>
                                            <ChevronDown
                                                class="h-3.5 w-3.5 transition-transform"
                                                :class="{ 'rotate-180': sectionsOpen.advancedAbilities }"
                                            />
                                        </div>
                                    </CollapsibleTrigger>
                                    <CollapsibleContent class="space-y-3 px-1 pt-2">
                                        <div class="space-y-1">
                                            <div class="flex items-center justify-between">
                                                <label class="text-xs font-medium text-muted-foreground">Name</label>
                                                <button
                                                    v-if="selectedAbilities.length > 1"
                                                    class="rounded px-1.5 py-0.5 text-[10px] font-semibold"
                                                    :class="
                                                        abilityLogic === 'and'
                                                            ? 'bg-primary text-primary-foreground'
                                                            : 'bg-muted text-muted-foreground'
                                                    "
                                                    @click="abilityLogic = abilityLogic === 'and' ? 'or' : 'and'"
                                                >
                                                    {{ abilityLogic === 'and' ? 'ALL' : 'ANY' }}
                                                </button>
                                            </div>
                                            <SearchableMultiselect
                                                v-model="selectedAbilities"
                                                placeholder="Select Abilities"
                                                :options="props.abilities"
                                            />
                                            <Input
                                                v-model="filterParams.ability_name"
                                                type="text"
                                                placeholder="or search ability name..."
                                                class="h-8 text-xs"
                                            />
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
                                <!-- Triggers -->
                                <Collapsible :open="sectionsOpen.advancedTriggers" @update:open="toggleAdvancedSection('advancedTriggers')">
                                    <CollapsibleTrigger
                                        class="flex w-full items-center justify-between rounded-md bg-muted px-2.5 py-1.5 text-xs font-medium hover:bg-muted/80"
                                    >
                                        Triggers
                                        <div class="flex items-center gap-1.5">
                                            <Badge v-if="triggersFilterCount" variant="secondary" class="px-1 py-0 text-[9px]">{{
                                                triggersFilterCount
                                            }}</Badge>
                                            <ChevronDown
                                                class="h-3.5 w-3.5 transition-transform"
                                                :class="{ 'rotate-180': sectionsOpen.advancedTriggers }"
                                            />
                                        </div>
                                    </CollapsibleTrigger>
                                    <CollapsibleContent class="space-y-3 px-1 pt-2">
                                        <div class="space-y-1">
                                            <div class="flex items-center justify-between">
                                                <label class="text-xs font-medium text-muted-foreground">Trigger</label>
                                                <button
                                                    v-if="selectedTriggers.length > 1"
                                                    class="rounded px-1.5 py-0.5 text-[10px] font-semibold"
                                                    :class="
                                                        triggerLogic === 'and'
                                                            ? 'bg-primary text-primary-foreground'
                                                            : 'bg-muted text-muted-foreground'
                                                    "
                                                    @click="triggerLogic = triggerLogic === 'and' ? 'or' : 'and'"
                                                >
                                                    {{ triggerLogic === 'and' ? 'ALL' : 'ANY' }}
                                                </button>
                                            </div>
                                            <SearchableMultiselect
                                                v-model="selectedTriggers"
                                                placeholder="Select Triggers"
                                                :options="props.triggers"
                                            />
                                        </div>
                                        <div class="space-y-1">
                                            <label class="text-xs font-medium text-muted-foreground">Suit</label>
                                            <ClearableSelect v-model="filterParams.trigger_suits" placeholder="Any Suit" :options="props.suits" />
                                        </div>
                                        <div class="space-y-1">
                                            <label class="text-xs font-medium text-muted-foreground">Description</label>
                                            <Input
                                                v-model="filterParams.trigger_description"
                                                type="text"
                                                placeholder="Search description..."
                                                class="h-8 text-xs"
                                            />
                                        </div>
                                    </CollapsibleContent>
                                </Collapsible>
                                <!-- Tokens -->
                                <Collapsible :open="sectionsOpen.advancedTokens" @update:open="toggleAdvancedSection('advancedTokens')">
                                    <CollapsibleTrigger
                                        class="flex w-full items-center justify-between rounded-md bg-muted px-2.5 py-1.5 text-xs font-medium hover:bg-muted/80"
                                    >
                                        Tokens
                                        <div class="flex items-center gap-1.5">
                                            <Badge v-if="tokensFilterCount" variant="secondary" class="px-1 py-0 text-[9px]">{{
                                                tokensFilterCount
                                            }}</Badge>
                                            <ChevronDown
                                                class="h-3.5 w-3.5 transition-transform"
                                                :class="{ 'rotate-180': sectionsOpen.advancedTokens }"
                                            />
                                        </div>
                                    </CollapsibleTrigger>
                                    <CollapsibleContent class="space-y-3 px-1 pt-2">
                                        <div class="space-y-1">
                                            <div class="flex items-center justify-between">
                                                <label class="text-xs font-medium text-muted-foreground">Token</label>
                                                <button
                                                    v-if="selectedTokens.length > 1"
                                                    class="rounded px-1.5 py-0.5 text-[10px] font-semibold"
                                                    :class="
                                                        tokenLogic === 'and' ? 'bg-primary text-primary-foreground' : 'bg-muted text-muted-foreground'
                                                    "
                                                    @click="tokenLogic = tokenLogic === 'and' ? 'or' : 'and'"
                                                >
                                                    {{ tokenLogic === 'and' ? 'ALL' : 'ANY' }}
                                                </button>
                                            </div>
                                            <SearchableMultiselect
                                                v-model="selectedTokens"
                                                placeholder="Select Tokens"
                                                :options="props.tokens_list"
                                            />
                                        </div>
                                    </CollapsibleContent>
                                </Collapsible>
                                <!-- Markers -->
                                <Collapsible :open="sectionsOpen.advancedMarkers" @update:open="toggleAdvancedSection('advancedMarkers')">
                                    <CollapsibleTrigger
                                        class="flex w-full items-center justify-between rounded-md bg-muted px-2.5 py-1.5 text-xs font-medium hover:bg-muted/80"
                                    >
                                        Markers
                                        <div class="flex items-center gap-1.5">
                                            <Badge v-if="markersFilterCount" variant="secondary" class="px-1 py-0 text-[9px]">{{
                                                markersFilterCount
                                            }}</Badge>
                                            <ChevronDown
                                                class="h-3.5 w-3.5 transition-transform"
                                                :class="{ 'rotate-180': sectionsOpen.advancedMarkers }"
                                            />
                                        </div>
                                    </CollapsibleTrigger>
                                    <CollapsibleContent class="space-y-3 px-1 pt-2">
                                        <div class="space-y-1">
                                            <div class="flex items-center justify-between">
                                                <label class="text-xs font-medium text-muted-foreground">Marker</label>
                                                <button
                                                    v-if="selectedMarkers.length > 1"
                                                    class="rounded px-1.5 py-0.5 text-[10px] font-semibold"
                                                    :class="
                                                        markerLogic === 'and'
                                                            ? 'bg-primary text-primary-foreground'
                                                            : 'bg-muted text-muted-foreground'
                                                    "
                                                    @click="markerLogic = markerLogic === 'and' ? 'or' : 'and'"
                                                >
                                                    {{ markerLogic === 'and' ? 'ALL' : 'ANY' }}
                                                </button>
                                            </div>
                                            <SearchableMultiselect
                                                v-model="selectedMarkers"
                                                placeholder="Select Markers"
                                                :options="props.markers_list"
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
                        <div v-if="props.results.data?.some((r: any) => r.result_type === 'upgrade')" class="mb-6">
                            <h3 class="mb-3 text-sm font-semibold text-muted-foreground">Matching Upgrades</h3>
                            <div class="grid grid-cols-2 gap-4 md:grid-cols-4 lg:grid-cols-6">
                                <div
                                    v-for="item in props.results.data?.filter((r: any) => r.result_type === 'upgrade')"
                                    :key="`upgrade-${item.id}`"
                                    class="text-center"
                                >
                                    <p class="mb-1 text-xs text-muted-foreground">{{ item.name }}</p>
                                    <UpgradeFlipCard
                                        :front-image="item.front_image.replace('/storage/', '')"
                                        :back-image="item.back_image?.replace('/storage/', '')"
                                        :alt-text="item.name"
                                        :upgrade-slug="item.slug"
                                        :show-link="false"
                                    />
                                    <div class="mt-1">
                                        <Button @click="router.get(route('upgrades.view', { upgrade: item.slug }))" size="sm" variant="link">
                                            View Upgrade Page
                                        </Button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <CharacterTable :characters="props.results.data?.filter((r: any) => r.result_type === 'character') ?? []" />
                        <InertiaPagination :paginator="props.results" :only="['results', 'result_count']" />
                    </div>
                    <div v-else-if="filterParams.page_view === 'full'">
                        <template v-if="props.results?.data?.length">
                            <template v-for="item in props.results.data" :key="`${item.result_type}-${item.id}`">
                                <CharacterView v-if="item.result_type === 'character'" :character="item" :miniature="item.standard_miniatures[0]" />
                                <div v-else class="mx-auto mb-6 max-w-xs text-center">
                                    <p class="mb-1 text-xs text-muted-foreground">{{ item.name }}</p>
                                    <UpgradeFlipCard
                                        :front-image="item.front_image.replace('/storage/', '')"
                                        :back-image="item.back_image?.replace('/storage/', '')"
                                        :alt-text="item.name"
                                        :upgrade-slug="item.slug"
                                        :show-link="false"
                                    />
                                    <div class="mt-1">
                                        <Button @click="router.get(route('upgrades.view', { upgrade: item.slug }))" size="sm" variant="link">
                                            View Upgrade Page
                                        </Button>
                                    </div>
                                </div>
                            </template>
                        </template>
                        <EmptyState v-else>
                            <div class="mt-3 space-y-2 text-center">
                                <p
                                    v-if="
                                        excludedGameModes.length ||
                                        excludedFactions.length ||
                                        excludedKeywords.length ||
                                        excludedCharacteristics.length
                                    "
                                    class="text-xs text-muted-foreground"
                                >
                                    Try removing some exclusion filters.
                                </p>
                                <p v-if="selectedKeywords.length > 1 && keywordLogic === 'and'" class="text-xs text-muted-foreground">
                                    Try switching keywords to ANY mode instead of ALL.
                                </p>
                                <p
                                    v-if="statFields.some((s) => filterParams[`${s}_min`] || filterParams[`${s}_max`])"
                                    class="text-xs text-muted-foreground"
                                >
                                    Try widening or removing stat range filters.
                                </p>
                                <Button v-if="activeFilterCount > 0" variant="outline" size="sm" class="mt-2" @click="clear">
                                    Clear all filters
                                </Button>
                            </div>
                        </EmptyState>
                        <InertiaPagination :paginator="props.results" :only="['results', 'result_count']" />
                    </div>
                    <div v-else>
                        <div v-if="props.results?.data?.length" class="grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-4">
                            <template v-for="(item, idx) in props.results.data" :key="`${item.result_type}-${item.id}`">
                                <div v-if="item.result_type === 'upgrade'" class="animate-fade-in-up opacity-0" :style="delays[idx]">
                                    <div class="w-full rounded-lg text-center transition-shadow duration-300 hover:shadow-lg hover:shadow-black/20">
                                        <p class="mb-1 text-xs text-muted-foreground">{{ item.name }}</p>
                                        <UpgradeFlipCard
                                            :front-image="item.front_image.replace('/storage/', '')"
                                            :back-image="item.back_image?.replace('/storage/', '')"
                                            :alt-text="item.name"
                                            :upgrade-slug="item.slug"
                                            :show-link="false"
                                        />
                                        <div class="mt-1">
                                            <Button @click="router.get(route('upgrades.view', { upgrade: item.slug }))" size="sm" variant="link">
                                                View Upgrade Page
                                            </Button>
                                        </div>
                                    </div>
                                </div>
                                <template v-else>
                                    <div class="animate-fade-in-up opacity-0" :style="delays[idx]">
                                        <CharacterCardView :miniature="item.standard_miniatures[0]" :character-slug="item.slug" />
                                        <div class="mt-1 flex flex-wrap gap-1">
                                            <button
                                                v-if="item.faction"
                                                class="rounded-full bg-muted px-1.5 py-0.5 text-[10px] font-medium text-muted-foreground transition-colors hover:bg-primary hover:text-primary-foreground"
                                                @click="applyFactionFilter(item.faction)"
                                            >
                                                {{ lookupName(props.factions, item.faction) }}
                                            </button>
                                            <button
                                                v-if="item.station"
                                                class="rounded-full bg-muted px-1.5 py-0.5 text-[10px] font-medium text-muted-foreground transition-colors hover:bg-primary hover:text-primary-foreground"
                                                @click="applyStationFilter(item.station)"
                                            >
                                                {{ lookupName(props.stations, item.station) }}
                                            </button>
                                        </div>
                                    </div>
                                    <div
                                        v-for="upgrade in item.crew_upgrades ?? []"
                                        :key="'cu-' + upgrade.id"
                                        class="animate-fade-in-up opacity-0"
                                        :style="delays[idx]"
                                    >
                                        <UpgradeCardView :upgrade="upgrade" />
                                    </div>
                                </template>
                            </template>
                        </div>
                        <EmptyState v-else>
                            <div class="mt-3 space-y-2 text-center">
                                <p
                                    v-if="
                                        excludedGameModes.length ||
                                        excludedFactions.length ||
                                        excludedKeywords.length ||
                                        excludedCharacteristics.length
                                    "
                                    class="text-xs text-muted-foreground"
                                >
                                    Try removing some exclusion filters.
                                </p>
                                <p v-if="selectedKeywords.length > 1 && keywordLogic === 'and'" class="text-xs text-muted-foreground">
                                    Try switching keywords to ANY mode instead of ALL.
                                </p>
                                <p
                                    v-if="statFields.some((s) => filterParams[`${s}_min`] || filterParams[`${s}_max`])"
                                    class="text-xs text-muted-foreground"
                                >
                                    Try widening or removing stat range filters.
                                </p>
                                <Button v-if="activeFilterCount > 0" variant="outline" size="sm" class="mt-2" @click="clear">
                                    Clear all filters
                                </Button>
                            </div>
                        </EmptyState>
                        <InertiaPagination :paginator="props.results" :only="['results', 'result_count']" />
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
