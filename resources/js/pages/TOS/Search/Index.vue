<script setup lang="ts">
import CardSkeleton from '@/components/CardSkeleton.vue';
import EmptyState from '@/components/EmptyState.vue';
import FilterPanel from '@/components/FilterPanel.vue';
import InertiaPagination from '@/components/InertiaPagination.vue';
import PageBanner from '@/components/PageBanner.vue';
import TableSkeleton from '@/components/TableSkeleton.vue';
import FlipCard from '@/components/TOS/FlipCard.vue';
import SearchFilterSections from '@/components/TOS/SearchFilterSections.vue';
import UnitCard from '@/components/TOS/UnitCard.vue';
import { Badge } from '@/components/ui/badge';
import Button from '@/components/ui/button/Button.vue';
import { Card, CardContent } from '@/components/ui/card';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { cleanObject } from '@/composables/CleanObject';
import { useConfirm } from '@/composables/useConfirm';
import type { SharedData } from '@/types';
import type { Paginator, TosSelectOption } from '@/types/tos';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    Bookmark,
    BookOpen,
    Download,
    LayoutGrid,
    List,
    Newspaper,
    Package,
    ScrollText,
    Search as SearchIcon,
    Sparkles,
    Swords,
    Trash2,
} from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';

interface ResultRow {
    result_type: 'unit' | 'asset' | 'stratagem' | 'allegiance_card';
    id: number;
    slug: string;
    name: string;
    title?: string | null;
    scrip?: number;
    scrip_cost?: number;
    tactical_cost?: number;
    body?: string | null;
    effect?: string | null;
    image_path?: string | null;
    sculpts?: Array<{ id: number; slug: string; front_image: string | null; back_image: string | null; combination_image: string | null }>;
    sides?: Array<{ side: string; speed: number; defense: number; willpower: number; armor: number }>;
    allegiances?: Array<{ id: number; slug: string; name: string }>;
    allegiance?: { id: number; slug: string; name: string } | null;
    special_unit_rules?: Array<{ id: number; slug: string; name: string }>;
}

interface SavedSearch {
    id: number;
    name: string;
    query_params: Record<string, string>;
}

defineProps<{
    results: Paginator<ResultRow>;
    result_count: number;
    result_breakdown: { units: number; assets: number; stratagems: number; allegiance_cards: number };
    allegiances: TosSelectOption[];
    special_rules: TosSelectOption[];
    restriction_options: TosSelectOption[];
    action_types: TosSelectOption[];
    usage_limits: TosSelectOption[];
    actions_list: TosSelectOption[];
    abilities_list: TosSelectOption[];
    triggers_list: TosSelectOption[];
    sort_options: TosSelectOption[];
    sort_types: TosSelectOption[];
    page_view: string;
    view_options: TosSelectOption[];
    saved_searches: SavedSearch[];
}>();

const page = usePage<SharedData>();
const isLoggedIn = computed(() => !!page.props.auth?.user);

// --- Filter state ---------------------------------------------------------

type FilterParams = Record<string, string | null>;

const blankParams = (): FilterParams => ({
    name: null,
    description: null,
    allegiance: null,
    allegiance_logic: 'and',
    allegiance_exclude: null,
    restriction: null,
    special_rule: null,
    special_rule_logic: 'and',
    special_rule_exclude: null,
    scrip_min: null,
    scrip_max: null,
    tactics: null,
    glory_tactics: null,
    side: 'both',
    speed_min: null,
    speed_max: null,
    defense_min: null,
    defense_max: null,
    willpower_min: null,
    willpower_max: null,
    armor_min: null,
    armor_max: null,
    action_name: null,
    action_type: null,
    action_av_min: null,
    action_av_max: null,
    action_strength_min: null,
    action_strength_max: null,
    action_range: null,
    action_usage_limit: null,
    action_description: null,
    action_is_piercing: null,
    action_is_accurate: null,
    action_is_area: null,
    ability_name: null,
    ability_description: null,
    trigger_name: null,
    trigger_suits: null,
    trigger_description: null,
    has: null,
    sort: 'name',
    sort_type: 'ascending',
    page_view: 'cards',
});

const filterParams = ref<FilterParams>(blankParams());

const filterKeys = [
    'name', 'description', 'allegiance', 'allegiance_exclude', 'restriction',
    'special_rule', 'special_rule_exclude', 'scrip_min', 'scrip_max', 'tactics', 'glory_tactics',
    'speed_min', 'speed_max', 'defense_min', 'defense_max',
    'willpower_min', 'willpower_max', 'armor_min', 'armor_max',
    'action_name', 'action_type', 'action_av_min', 'action_av_max',
    'action_strength_min', 'action_strength_max', 'action_range',
    'action_usage_limit', 'action_description',
    'action_is_piercing', 'action_is_accurate', 'action_is_area',
    'ability_name', 'ability_description',
    'trigger_name', 'trigger_suits', 'trigger_description', 'has',
] as const;

const activeFilterCount = computed(() => filterKeys.filter((k) => {
    const v = filterParams.value[k];
    return v != null && v !== '';
}).length);

const filter = () => {
    router.get(route('tos.search'), cleanObject({ ...filterParams.value }), {
        only: ['results', 'result_count', 'result_breakdown', 'page_view'],
        replace: true,
        preserveState: true,
        preserveScroll: true,
    });
};

const clear = () => {
    filterParams.value = blankParams();
    filter();
};

const handleViewChange = (value: string) => {
    filterParams.value.page_view = value;
    filter();
};

// --- Saved searches -------------------------------------------------------

const saveOpen = ref(false);
const saveName = ref('');
const doSave = () => {
    if (!saveName.value.trim()) return;
    router.post(
        route('tos.search.save'),
        {
            name: saveName.value.trim(),
            query_params: cleanObject({ ...filterParams.value }),
        },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                saveOpen.value = false;
                saveName.value = '';
            },
        },
    );
};

const loadSavedSearch = (saved: SavedSearch) => {
    filterParams.value = { ...blankParams(), ...saved.query_params };
    filter();
};

const confirmDialog = useConfirm();
const deleteSavedSearch = async (saved: SavedSearch) => {
    if (!(await confirmDialog({
        title: 'Delete saved search',
        message: `Delete saved search "${saved.name}"?`,
        confirmLabel: 'Delete',
        destructive: true,
    }))) return;
    router.post(route('tos.search.saved.delete', saved.id), {}, { preserveScroll: true });
};

// --- Section open state ---------------------------------------------------

const sectionsOpen = ref<Record<string, boolean>>({
    identity: true,
    stats: false,
    actions: false,
    abilities: false,
    triggers: false,
    has: false,
    sorting: false,
});
const toggleSection = (section: string) => {
    const opening = !sectionsOpen.value[section];
    Object.keys(sectionsOpen.value).forEach((key) => {
        sectionsOpen.value[key] = false;
    });
    if (opening) sectionsOpen.value[section] = true;
};

// --- Has-flag chip toggles ------------------------------------------------

const hasFlags = ['commander', 'sculpts', 'combined_arms', 'abilities', 'actions', 'triggers', 'neutral'] as const;
const activeHasFlags = computed<string[]>(() => (filterParams.value.has ?? '').split(',').filter(Boolean));
const toggleHas = (flag: string) => {
    const set = new Set(activeHasFlags.value);
    if (set.has(flag)) {
        set.delete(flag);
    } else {
        set.add(flag);
    }
    const arr = Array.from(set);
    filterParams.value.has = arr.length ? arr.join(',') : null;
};

// --- Hydrate from URL -----------------------------------------------------

const isLoading = ref(false);

onMounted(() => {
    const url = new URLSearchParams(window.location.search);
    const next = blankParams();
    Object.keys(next).forEach((key) => {
        const v = url.get(key);
        if (v !== null) next[key] = v;
    });
    filterParams.value = next;

    router.on('start', () => {
        isLoading.value = true;
    });
    router.on('finish', () => {
        isLoading.value = false;
    });

    if (filterParams.value.action_name || filterParams.value.action_type || filterParams.value.action_description) {
        sectionsOpen.value.actions = true;
        sectionsOpen.value.identity = false;
    }
});

const hasActiveSearch = computed(() => activeFilterCount.value > 0);

const logicOptions: TosSelectOption[] = [
    { name: 'AND (must have all)', value: 'and' },
    { name: 'OR (any of)', value: 'or' },
];

const sideOptions: TosSelectOption[] = [
    { name: 'Both sides', value: 'both' },
    { name: 'Standard only', value: 'standard' },
    { name: 'Glory only', value: 'glory' },
];

const booleanOptions: TosSelectOption[] = [
    { name: 'Yes', value: 'true' },
    { name: 'No', value: 'false' },
];

const exportUrl = computed(() => {
    const params = new URLSearchParams(cleanObject({ ...filterParams.value }) as Record<string, string>).toString();
    return `${route('tos.search.export')}${params ? `?${params}` : ''}`;
});
</script>

<template>
    <Head title="Advanced Search — TOS" />
    <div class="relative">
        <div
            class="pointer-events-none absolute inset-x-0 top-0 h-64 opacity-[0.07] dark:opacity-[0.12]"
            :style="{ background: 'radial-gradient(ellipse at top, hsl(var(--primary)) 0%, transparent 70%)' }"
        />
        <PageBanner title="Advanced Search" class="mb-2">
            <template #subtitle>
                <div class="my-auto flex flex-wrap items-center gap-x-1 px-2 py-0 text-xs text-muted-foreground md:py-2 md:text-sm md:text-foreground">
                    <span>{{ result_breakdown.units }} units</span>
                    <span class="text-muted-foreground/50">·</span>
                    <span>{{ result_breakdown.assets }} assets</span>
                    <span class="text-muted-foreground/50">·</span>
                    <span>{{ result_breakdown.stratagems }} stratagems</span>
                    <span class="text-muted-foreground/50">·</span>
                    <span>{{ result_breakdown.allegiance_cards }} cards</span>
                </div>
            </template>
        </PageBanner>

        <div class="container mx-auto mb-2 flex flex-wrap items-center justify-between gap-2 sm:px-4">
            <Tabs :model-value="filterParams.page_view ?? 'cards'" @update:model-value="(v) => handleViewChange(v as string)">
                <TabsList>
                    <TabsTrigger value="cards"><LayoutGrid class="h-4 w-4" /><span class="hidden sm:inline">Cards</span></TabsTrigger>
                    <TabsTrigger value="table"><List class="h-4 w-4" /><span class="hidden sm:inline">Table</span></TabsTrigger>
                    <TabsTrigger value="full"><BookOpen class="h-4 w-4" /><span class="hidden sm:inline">Full</span></TabsTrigger>
                </TabsList>
            </Tabs>
            <div class="flex items-center gap-2">
                <Badge v-if="activeFilterCount > 0" variant="secondary" class="text-xs">
                    {{ activeFilterCount }} {{ activeFilterCount === 1 ? 'filter' : 'filters' }}
                </Badge>
                <Button
                    v-if="hasActiveSearch"
                    variant="outline"
                    size="sm"
                    as="a"
                    :href="exportUrl"
                    target="_blank"
                    rel="noopener"
                    class="h-7 gap-1 text-xs"
                >
                    <Download class="size-3" /><span class="hidden sm:inline">Export CSV</span>
                </Button>
                <Button v-if="isLoggedIn && hasActiveSearch" variant="outline" size="sm" class="h-7 gap-1 text-xs" @click="saveOpen = true">
                    <Bookmark class="size-3" /><span class="hidden sm:inline">Save</span>
                </Button>
                <DropdownMenu v-if="isLoggedIn && saved_searches.length">
                    <DropdownMenuTrigger as-child>
                        <Button variant="outline" size="sm" class="h-7 gap-1 text-xs">
                            <Sparkles class="size-3" /><span class="hidden sm:inline">Saved</span> ({{ saved_searches.length }})
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" class="w-72">
                        <DropdownMenuItem v-for="s in saved_searches" :key="s.id" class="flex items-center justify-between gap-2">
                            <button type="button" class="flex-1 text-left" @click="loadSavedSearch(s)">{{ s.name }}</button>
                            <button type="button" class="text-muted-foreground hover:text-rose-600" @click="deleteSavedSearch(s)">
                                <Trash2 class="size-3" />
                            </button>
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
                <div class="md:hidden">
                    <FilterPanel :filter-count="activeFilterCount" @filter="filter" @clear="clear">
                        <SearchFilterSections
                            v-model:params="filterParams"
                            v-model:sections-open="sectionsOpen"
                            :allegiances="allegiances"
                            :special-rules="special_rules"
                            :restriction-options="restriction_options"
                            :action-types="action_types"
                            :usage-limits="usage_limits"
                            :sort-options="sort_options"
                            :sort-types="sort_types"
                            :side-options="sideOptions"
                            :logic-options="logicOptions"
                            :boolean-options="booleanOptions"
                            :has-flags="hasFlags"
                            :active-has-flags="activeHasFlags"
                            @toggle="toggleSection"
                            @toggleHas="toggleHas"
                        />
                    </FilterPanel>
                </div>
            </div>
        </div>

        <div class="container mx-auto sm:px-4">
            <div class="flex gap-6">
                <aside class="hidden w-64 shrink-0 md:block lg:w-80">
                    <div class="space-y-2 pr-2">
                        <SearchFilterSections
                            v-model:params="filterParams"
                            v-model:sections-open="sectionsOpen"
                            :allegiances="allegiances"
                            :special-rules="special_rules"
                            :restriction-options="restriction_options"
                            :action-types="action_types"
                            :usage-limits="usage_limits"
                            :sort-options="sort_options"
                            :sort-types="sort_types"
                            :side-options="sideOptions"
                            :logic-options="logicOptions"
                            :boolean-options="booleanOptions"
                            :has-flags="hasFlags"
                            :active-has-flags="activeHasFlags"
                            @toggle="toggleSection"
                            @toggleHas="toggleHas"
                        />
                        <div class="flex gap-2 pt-2">
                            <Button class="flex-1" @click="filter">Search</Button>
                            <Button variant="outline" class="flex-1" @click="clear">Clear</Button>
                        </div>
                    </div>
                </aside>

                <div class="min-w-0 flex-1">
                    <div v-if="isLoading && filterParams.page_view === 'table'" class="overflow-auto">
                        <TableSkeleton :rows="8" :cols="6" />
                    </div>
                    <div v-else-if="isLoading" class="grid gap-4 grid-cols-2 md:grid-cols-3 lg:grid-cols-4">
                        <CardSkeleton v-for="n in 8" :key="`skeleton-${n}`" />
                    </div>

                    <div v-else-if="!results.data.length">
                        <EmptyState
                            :icon="SearchIcon"
                            :title="hasActiveSearch ? 'No matches' : 'Refine your search'"
                            :description="hasActiveSearch ? 'Try widening or clearing filters.' : 'Use the panel to filter by allegiance, special rules, side stats, action shape, abilities, and more.'"
                        />
                    </div>

                    <div v-else-if="filterParams.page_view === 'table'" class="overflow-auto rounded-md border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Type</TableHead>
                                    <TableHead>Name</TableHead>
                                    <TableHead>Cost</TableHead>
                                    <TableHead>Allegiances</TableHead>
                                    <TableHead>Rules</TableHead>
                                    <TableHead>Body</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="r in results.data" :key="`${r.result_type}-${r.id}`">
                                    <TableCell><Badge variant="outline" class="text-[10px] capitalize">{{ r.result_type }}</Badge></TableCell>
                                    <TableCell class="font-medium">
                                        <Link
                                            v-if="r.result_type === 'unit' && r.sculpts && r.sculpts[0]"
                                            :href="route('tos.units.view', r.sculpts[0].slug)"
                                            class="hover:underline"
                                        >{{ r.name }}{{ r.title ? `, ${r.title}` : '' }}</Link>
                                        <Link
                                            v-else-if="r.result_type === 'asset'"
                                            :href="route('tos.assets.view', r.slug)"
                                            class="hover:underline"
                                        >{{ r.name }}</Link>
                                        <Link
                                            v-else-if="r.result_type === 'stratagem'"
                                            :href="route('tos.stratagems.view', r.slug)"
                                            class="hover:underline"
                                        >{{ r.name }}</Link>
                                        <Link
                                            v-else-if="r.result_type === 'allegiance_card'"
                                            :href="route('tos.allegiance_cards.view', r.slug)"
                                            class="hover:underline"
                                        >{{ r.name }}</Link>
                                        <span v-else>{{ r.name }}</span>
                                    </TableCell>
                                    <TableCell class="text-xs tabular-nums">
                                        <template v-if="r.result_type === 'unit'">{{ r.scrip ?? '—' }}</template>
                                        <template v-else-if="r.result_type === 'asset'">{{ r.scrip_cost ?? '—' }}</template>
                                        <template v-else-if="r.result_type === 'stratagem'">{{ r.tactical_cost ?? '—' }}</template>
                                        <template v-else>—</template>
                                    </TableCell>
                                    <TableCell class="text-xs text-muted-foreground">
                                        <template v-if="r.allegiances">{{ r.allegiances.map((a) => a.name).join(', ') || '—' }}</template>
                                        <template v-else-if="r.allegiance">{{ r.allegiance.name }}</template>
                                        <span v-else class="text-muted-foreground">—</span>
                                    </TableCell>
                                    <TableCell class="text-xs">
                                        <template v-if="r.special_unit_rules">
                                            <span v-for="ru in r.special_unit_rules" :key="ru.id" class="mr-1">{{ ru.name }}</span>
                                            <span v-if="!r.special_unit_rules.length" class="text-muted-foreground">—</span>
                                        </template>
                                    </TableCell>
                                    <TableCell class="max-w-md truncate text-xs text-muted-foreground">
                                        {{ r.body ?? r.effect ?? '—' }}
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>

                    <div v-else-if="filterParams.page_view === 'full'" class="space-y-4">
                        <template v-for="r in results.data" :key="`${r.result_type}-${r.id}`">
                            <UnitCard v-if="r.result_type === 'unit'" :unit="(r as any)" :active-sculpt="(r as any).sculpts?.[0] ?? null" />
                            <Card v-else>
                                <CardContent class="space-y-1 p-4">
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="text-sm font-semibold">{{ r.name }}</span>
                                        <Badge variant="outline" class="text-[10px] capitalize">{{ r.result_type }}</Badge>
                                    </div>
                                    <p class="text-xs text-muted-foreground">{{ r.body ?? r.effect ?? '' }}</p>
                                </CardContent>
                            </Card>
                        </template>
                    </div>

                    <div v-else class="grid gap-4 grid-cols-2 md:grid-cols-3 lg:grid-cols-4">
                        <Card
                            v-for="r in results.data"
                            :key="`${r.result_type}-${r.id}`"
                            class="h-full overflow-hidden transition-all duration-200 ease-out hover:-translate-y-0.5 hover:border-primary/30 hover:shadow-lg hover:shadow-black/10"
                        >
                            <FlipCard
                                v-if="r.result_type === 'unit' && r.sculpts && r.sculpts[0]"
                                :front-image="r.sculpts[0]?.front_image"
                                :back-image="r.sculpts[0]?.back_image"
                                :front-alt="`${r.name} (standard)`"
                                :back-alt="`${r.name} (glory)`"
                                :allegiance-slug="r.allegiances?.[0]?.slug ?? null"
                                :placeholder-icon="Swords"
                                :single-side="!r.sculpts[0]?.back_image"
                            />
                            <div v-else class="flex aspect-[5/7] items-center justify-center bg-muted">
                                <Package v-if="r.result_type === 'asset'" class="size-12 text-muted-foreground/50" />
                                <Newspaper v-else-if="r.result_type === 'stratagem'" class="size-12 text-muted-foreground/50" />
                                <ScrollText v-else-if="r.result_type === 'allegiance_card'" class="size-12 text-muted-foreground/50" />
                                <Sparkles v-else class="size-12 text-muted-foreground/50" />
                            </div>
                            <CardContent class="space-y-1.5 p-3">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="truncate text-sm font-semibold">{{ r.name }}</span>
                                    <Badge variant="outline" class="shrink-0 text-[10px] capitalize">{{ r.result_type }}</Badge>
                                </div>
                                <p v-if="r.title" class="truncate text-[11px] italic text-muted-foreground">{{ r.title }}</p>
                                <p class="text-[11px] tabular-nums text-muted-foreground">
                                    <template v-if="r.result_type === 'unit'">{{ r.scrip }} Scrip</template>
                                    <template v-else-if="r.result_type === 'asset'">{{ r.scrip_cost }} Scrip</template>
                                    <template v-else-if="r.result_type === 'stratagem'">{{ r.tactical_cost }} Tactics</template>
                                    <template v-else-if="r.result_type === 'allegiance_card' && r.allegiance">{{ r.allegiance.name }}</template>
                                </p>
                                <div v-if="r.special_unit_rules?.length" class="flex flex-wrap gap-1">
                                    <Badge v-for="ru in r.special_unit_rules" :key="ru.id" variant="outline" class="text-[10px]">{{ ru.name }}</Badge>
                                </div>
                                <div class="pt-1">
                                    <Button
                                        v-if="r.result_type === 'unit' && r.sculpts && r.sculpts[0]"
                                        size="sm"
                                        variant="link"
                                        class="h-6 px-0 text-[11px]"
                                        @click="router.get(route('tos.units.view', r.sculpts![0].slug))"
                                    >View Unit</Button>
                                    <Button
                                        v-else-if="r.result_type === 'asset'"
                                        size="sm"
                                        variant="link"
                                        class="h-6 px-0 text-[11px]"
                                        @click="router.get(route('tos.assets.view', r.slug))"
                                    >View Asset</Button>
                                    <Button
                                        v-else-if="r.result_type === 'stratagem'"
                                        size="sm"
                                        variant="link"
                                        class="h-6 px-0 text-[11px]"
                                        @click="router.get(route('tos.stratagems.view', r.slug))"
                                    >View Stratagem</Button>
                                    <Button
                                        v-else-if="r.result_type === 'allegiance_card'"
                                        size="sm"
                                        variant="link"
                                        class="h-6 px-0 text-[11px]"
                                        @click="router.get(route('tos.allegiance_cards.view', r.slug))"
                                    >View Card</Button>
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    <InertiaPagination v-if="!isLoading" :paginator="results" :only="['results', 'result_count', 'result_breakdown']" />
                </div>
            </div>
        </div>

        <Dialog v-model:open="saveOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Save this search</DialogTitle>
                </DialogHeader>
                <Input v-model="saveName" placeholder="Name (e.g. King's Empire Titans)" autofocus @keydown.enter="doSave" />
                <DialogFooter class="gap-2 sm:gap-0">
                    <Button variant="ghost" @click="saveOpen = false">Cancel</Button>
                    <Button :disabled="!saveName.trim()" @click="doSave">Save</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
