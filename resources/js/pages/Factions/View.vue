<script setup lang="ts">
import { useStaggeredEntry } from '@/composables/useStaggeredEntry';
import type { SharedData } from '@/types';
import { Head, router, usePage } from '@inertiajs/vue3';
import { BookOpen, ChevronDown, Grid2x2, LayoutGrid, Library, List, Plus } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';

import CharacterCardView from '@/components/CharacterCardView.vue';
import CharacterTable from '@/components/CharacterTable.vue';
import CharacterView from '@/components/CharacterView.vue';
import ClearableSelect from '@/components/ClearableSelect.vue';
import EmptyState from '@/components/EmptyState.vue';
import FilterPanel from '@/components/FilterPanel.vue';
import GameIcon from '@/components/GameIcon.vue';
import KeywordBreakdown from '@/components/KeywordBreakdown.vue';
import ResourcesPanel from '@/components/ResourcesPanel.vue';
import PageBanner from '@/components/PageBanner.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { cleanObject } from '@/composables/CleanObject';

import CardSkeleton from '@/components/CardSkeleton.vue';
import TableSkeleton from '@/components/TableSkeleton.vue';

const props = defineProps({
    faction: {
        type: [Object, Array],
        required: false,
        default() {
            return null;
        },
    },
    characters: {
        type: [Object, Array],
        required: false,
        default() {
            return {};
        },
    },
    station_sort: {
        type: [Object, Array],
        required: false,
        default() {
            return {};
        },
    },
    keyword_breakdown: {
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
    statistics: {
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
    stations: {
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
    resources: {
        type: Object,
        required: false,
        default: () => null,
    },
});

const filterParams = ref({
    keyword: null as string | null,
    station: null as string | null,
    characteristic: null as string | null,
    page_view: null as string | null,
    sort: null as string | null,
    sort_type: null as string | null,
});

const filterKeys = ['keyword', 'station', 'characteristic'] as const;

const activeFilterCount = computed(() => {
    return filterKeys.filter((key) => filterParams.value[key] != null).length;
});

const clear = () => {
    filterParams.value.keyword = null;
    filterParams.value.station = null;
    filterParams.value.characteristic = null;
    filterParams.value.page_view = 'images';
    filterParams.value.sort = 'station';
    filterParams.value.sort_type = 'ascending';
    filter();
};

const filter = () => {
    router.get(route(route().current(), route().params.factionEnum), cleanObject(filterParams.value), {
        only: ['characters', 'keyword_breakdown'],
        replace: true,
        preserveState: true,
        preserveScroll: true,
    });
};

const handleViewChange = (value: string) => {
    filterParams.value.page_view = value;
    filter();
};

const sectionsOpen = ref({
    filters: true,
    sorting: false,
});

type SectionKey = keyof typeof sectionsOpen.value;
const sectionKeys: SectionKey[] = ['filters', 'sorting'];

const toggleSection = (section: SectionKey) => {
    const isOpening = !sectionsOpen.value[section];
    for (const key of sectionKeys) {
        sectionsOpen.value[key] = false;
    }
    if (isOpening) {
        sectionsOpen.value[section] = true;
    }
};

onMounted(() => {
    const urlParams = new URLSearchParams(window.location.search);
    filterParams.value.keyword = urlParams.get('keyword');
    filterParams.value.station = urlParams.get('station');
    filterParams.value.characteristic = urlParams.get('characteristic');
    filterParams.value.page_view = urlParams.get('page_view') ?? 'images';
    filterParams.value.sort = urlParams.get('sort') ?? 'station';
    filterParams.value.sort_type = urlParams.get('sort_type') ?? 'ascending';

    // Auto-open sections with active filters
    const hasFilters = filterParams.value.keyword || filterParams.value.station || filterParams.value.characteristic;
    const hasSorting =
        (filterParams.value.sort && filterParams.value.sort !== 'station') ||
        (filterParams.value.sort_type && filterParams.value.sort_type !== 'ascending');
    if (hasFilters) sectionsOpen.value.filters = true;
    if (hasSorting) sectionsOpen.value.sorting = true;
});

const page = usePage<SharedData>();
const isLoggedIn = computed(() => !!page.props.auth?.user);

const collectionIds = computed(() => new Set(page.props.auth?.collection_miniature_ids ?? []));

const uncollectedCharacters = computed(() => {
    return (props.characters ?? []).filter((c: any) => !(c.standard_miniatures ?? []).some((m: any) => collectionIds.value.has(m.id)));
});

const addingAll = ref(false);
const addAllToCollection = async () => {
    const chars = uncollectedCharacters.value;
    if (!chars.length) return;
    addingAll.value = true;

    // Optimistically update shared auth data
    const ids = page.props.auth.collection_miniature_ids;
    for (const c of chars) {
        for (const m of c.standard_miniatures ?? []) {
            if (!ids.includes(m.id)) ids.push(m.id);
        }
    }

    try {
        await fetch(route('collection.add_characters'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '',
            },
            body: JSON.stringify({ character_ids: chars.map((c: any) => c.id) }),
        });
    } finally {
        addingAll.value = false;
    }
};

const characterCount = computed(() => props.characters?.length ?? 0);
const { delays } = useStaggeredEntry(characterCount);

const statItems = computed(() => {
    const s = props.statistics;
    if (!s) return [];
    return [
        { label: 'Avg Cost', value: s.avg_cost },
        { label: 'Avg HP', value: s.avg_health },
        { label: 'Avg Spd', value: s.avg_speed },
        { label: 'Avg Def', value: s.avg_defense },
        { label: 'Avg Wp', value: s.avg_willpower },
    ].filter((i: any) => i.value != null);
});

const stationCounts = computed(() => {
    const s = props.statistics;
    if (!s) return [];
    return [
        { label: 'Masters', value: s.total_masters },
        { label: 'Unique', value: s.total_unique },
        { label: 'Minions', value: s.total_minions },
        { label: 'Peons', value: s.total_peons },
    ].filter((i: any) => i.value > 0);
});

const suitOrder = ['crow', 'mask', 'ram', 'tome', 'soulstone'];
const suitStats = computed(() => {
    const counts = props.statistics?.suit_counts as Record<string, number> | undefined;
    if (!counts) return [];
    return suitOrder.filter((s) => counts[s] > 0).map((s) => ({ suit: s, count: counts[s] }));
});

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
    <Head :title="faction.name" />
    <div class="relative">
        <div
            class="pointer-events-none absolute inset-x-0 top-0 h-64 opacity-[0.07] dark:opacity-[0.12]"
            :style="{ background: `radial-gradient(ellipse at top, hsl(var(--${faction.color})) 0%, transparent 70%)` }"
        />
        <PageBanner :title="faction.name" class="mb-2" :accent-color="`border-${faction.color}`">
            <template #logo>
                <div class="w-20 md:w-32">
                    <img :src="props.faction.logo" class="mx-auto my-auto h-16 w-16 md:h-20 md:w-20" :alt="props.faction.name" />
                </div>
            </template>
            <template #subtitle>
                <div
                    class="my-auto flex flex-wrap items-center gap-x-1 px-2 py-0 text-xs text-muted-foreground md:py-2 md:text-sm md:text-foreground"
                >
                    <span>{{ props.statistics.characters }} Characters</span>
                    <span class="text-muted-foreground/50">&middot;</span>
                    <span>{{ props.statistics.miniatures }} Miniatures</span>
                    <span class="text-muted-foreground/50">&middot;</span>
                    <span>{{ props.statistics.keywords }} Keywords</span>
                </div>
            </template>
        </PageBanner>

        <!-- Stats Block -->
        <div v-if="!isLoading" class="container mx-auto mb-2 sm:px-4">
            <div class="rounded-lg border bg-card p-3 sm:p-4">
                <div class="flex flex-wrap items-center gap-x-3 gap-y-2 sm:gap-x-5">
                    <div v-if="stationCounts.length" class="flex items-center gap-1.5">
                        <Badge v-for="s in stationCounts" :key="s.label" variant="outline" class="text-xs"> {{ s.value }} {{ s.label }} </Badge>
                    </div>
                    <div v-if="statItems.length" class="flex items-center gap-3">
                        <div v-for="stat in statItems" :key="stat.label" class="text-center">
                            <div class="text-[10px] font-medium uppercase tracking-wide text-muted-foreground">{{ stat.label }}</div>
                            <div class="text-sm font-bold leading-tight">{{ stat.value }}</div>
                        </div>
                    </div>
                    <div v-if="suitStats.length" class="flex items-center gap-3">
                        <div v-for="s in suitStats" :key="s.suit" class="text-center">
                            <GameIcon :type="s.suit" class-name="mx-auto h-4" />
                            <div class="text-sm font-bold leading-tight">{{ s.count }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Resources -->
        <ResourcesPanel
            v-if="resources"
            :articles="resources.articles"
            :transmissions="resources.transmissions"
            :pod-links="resources.pod_links"
        />

        <!-- Tabs + mobile filter trigger -->
        <div class="container mx-auto mb-2 flex flex-wrap items-center justify-between gap-2 sm:px-4">
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
                    <TabsTrigger value="keyword_breakdown">
                        <Grid2x2 class="h-4 w-4" />
                        <span class="hidden sm:inline">Keywords</span>
                    </TabsTrigger>
                </TabsList>
            </Tabs>
            <div class="flex items-center gap-2">
                <Button
                    v-if="isLoggedIn && uncollectedCharacters.length > 0"
                    variant="outline"
                    size="sm"
                    class="h-7 gap-1 text-xs"
                    :disabled="addingAll"
                    @click="addAllToCollection"
                >
                    <Library class="size-3.5" />
                    <Plus class="size-3" />
                    <span class="hidden sm:inline">Add All</span> ({{ uncollectedCharacters.length }})
                </Button>
                <Badge v-if="activeFilterCount > 0" variant="secondary" class="text-xs">
                    {{ activeFilterCount }} {{ activeFilterCount === 1 ? 'filter' : 'filters' }}
                </Badge>
                <!-- Mobile-only filter trigger -->
                <div class="md:hidden">
                    <FilterPanel :filter-count="activeFilterCount" @filter="filter" @clear="clear">
                        <div class="grid gap-4">
                            <!-- Filters -->
                            <Collapsible :open="sectionsOpen.filters" @update:open="toggleSection('filters')">
                                <CollapsibleTrigger
                                    class="flex w-full items-center justify-between rounded-md bg-muted px-2.5 py-1.5 text-xs font-medium hover:bg-muted/80"
                                >
                                    Filters
                                    <ChevronDown class="h-3.5 w-3.5 transition-transform" :class="{ 'rotate-180': sectionsOpen.filters }" />
                                </CollapsibleTrigger>
                                <CollapsibleContent class="space-y-3 px-1 pt-2">
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium">Keyword</label>
                                        <ClearableSelect
                                            v-model="filterParams.keyword"
                                            placeholder="Any Keyword"
                                            :options="props.keywords"
                                            option-label="name"
                                            option-value="slug"
                                            trigger-class="border-2 border-primary rounded"
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
                                        <label class="text-sm font-medium">Characteristic</label>
                                        <ClearableSelect
                                            v-model="filterParams.characteristic"
                                            placeholder="Any Characteristic"
                                            :options="props.characteristics"
                                            option-label="name"
                                            option-value="slug"
                                            trigger-class="border-2 border-primary rounded"
                                        />
                                    </div>
                                </CollapsibleContent>
                            </Collapsible>

                            <!-- Sorting -->
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
                                            :options="props.sort_options"
                                            trigger-class="border-2 border-primary rounded"
                                        />
                                    </div>
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium">Sort Direction</label>
                                        <ClearableSelect
                                            v-model="filterParams.sort_type"
                                            placeholder="Sort Direction"
                                            :options="props.sort_types"
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
        <div class="container mx-auto sm:px-4">
            <div class="flex gap-6">
                <!-- Desktop sidebar filters -->
                <aside class="hidden w-56 shrink-0 md:block lg:w-72">
                    <div class="space-y-2 pr-2">
                        <!-- Filters -->
                        <Collapsible :open="sectionsOpen.filters" @update:open="toggleSection('filters')">
                            <CollapsibleTrigger
                                class="flex w-full items-center justify-between rounded-md bg-secondary px-3 py-2 text-sm font-medium hover:bg-secondary/80"
                            >
                                Filters
                                <ChevronDown class="h-4 w-4 transition-transform" :class="{ 'rotate-180': sectionsOpen.filters }" />
                            </CollapsibleTrigger>
                            <CollapsibleContent class="space-y-3 px-1 pt-3">
                                <div class="space-y-1">
                                    <label class="text-xs font-medium text-muted-foreground">Keyword</label>
                                    <ClearableSelect
                                        v-model="filterParams.keyword"
                                        placeholder="Any Keyword"
                                        :options="props.keywords"
                                        option-label="name"
                                        option-value="slug"
                                    />
                                </div>
                                <div class="space-y-1">
                                    <label class="text-xs font-medium text-muted-foreground">Station</label>
                                    <ClearableSelect v-model="filterParams.station" placeholder="Any Station" :options="props.stations" />
                                </div>
                                <div class="space-y-1">
                                    <label class="text-xs font-medium text-muted-foreground">Characteristic</label>
                                    <ClearableSelect
                                        v-model="filterParams.characteristic"
                                        placeholder="Any Characteristic"
                                        :options="props.characteristics"
                                        option-label="name"
                                        option-value="slug"
                                    />
                                </div>
                            </CollapsibleContent>
                        </Collapsible>

                        <!-- Sorting -->
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
                                    <ClearableSelect v-model="filterParams.sort" placeholder="Sort By" :options="props.sort_options" />
                                </div>
                                <div class="space-y-1">
                                    <label class="text-xs font-medium text-muted-foreground">Sort Direction</label>
                                    <ClearableSelect v-model="filterParams.sort_type" placeholder="Sort Direction" :options="props.sort_types" />
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
                    <div
                        v-if="isLoading && (filterParams.page_view === 'table' || filterParams.page_view === 'keyword_breakdown')"
                        class="overflow-auto"
                    >
                        <TableSkeleton :rows="8" :cols="7" />
                    </div>
                    <div v-else-if="isLoading">
                        <div class="grid grid-cols-2 gap-4 md:grid-cols-3">
                            <CardSkeleton v-for="n in 8" :key="`skeleton-${n}`" />
                        </div>
                    </div>
                    <div v-else-if="filterParams.page_view === 'keyword_breakdown'">
                        <template v-if="props.keyword_breakdown?.length">
                            <KeywordBreakdown
                                v-for="keyword in props.keyword_breakdown"
                                v-bind:key="keyword.keyword.name"
                                :keyword="keyword"
                                :statistics="keyword.statistics"
                            />
                        </template>
                        <EmptyState v-else />
                    </div>
                    <div v-else-if="filterParams.page_view === 'table'" class="overflow-auto">
                        <CharacterTable :characters="props.characters" />
                    </div>
                    <div v-else-if="filterParams.page_view === 'full'">
                        <template v-if="props.characters?.length">
                            <div v-for="character in props.characters" v-bind:key="character.slug">
                                <CharacterView :character="character" :miniature="character.standard_miniatures[0]" />
                            </div>
                        </template>
                        <EmptyState v-else />
                    </div>
                    <div v-else>
                        <template v-if="props.characters?.length">
                            <div class="grid grid-cols-2 gap-4 md:grid-cols-3">
                                <div
                                    v-for="(character, index) in props.characters"
                                    :key="`character-${character.id}`"
                                    class="animate-fade-in-up opacity-0"
                                    :style="delays[index]"
                                >
                                    <CharacterCardView
                                        :miniature="character.standard_miniatures[0]"
                                        :character-slug="character.slug"
                                        :all-miniature-ids="character.standard_miniatures.map((m: any) => m.id)"
                                    />
                                </div>
                            </div>
                        </template>
                        <EmptyState v-else />
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
