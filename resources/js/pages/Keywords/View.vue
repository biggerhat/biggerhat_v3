<script setup lang="ts">
import { useStaggeredEntry } from '@/composables/useStaggeredEntry';
import type { SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { BookOpen, Grid2x2, LayoutGrid, Library, List, Plus } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';

import CharacterCardView from '@/components/CharacterCardView.vue';
import CharacterTable from '@/components/CharacterTable.vue';
import CharacterView from '@/components/CharacterView.vue';
import EmptyState from '@/components/EmptyState.vue';
import FactionLogo from '@/components/FactionLogo.vue';
import FilterPanel from '@/components/FilterPanel.vue';
import GameIcon from '@/components/GameIcon.vue';
import KeywordBreakdown from '@/components/KeywordBreakdown.vue';
import PageBanner from '@/components/PageBanner.vue';
import { Badge } from '@/components/ui/badge';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { cleanObject } from '@/composables/CleanObject';

import CardSkeleton from '@/components/CardSkeleton.vue';
import TableSkeleton from '@/components/TableSkeleton.vue';
import { Button } from '@/components/ui/button';

const props = defineProps({
    keyword: {
        type: [Object, Array],
        required: true,
        default() {
            return {};
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

const filterParams = ref({
    faction: null,
    station: null,
    characteristic: null,
    page_view: null,
    sort: null,
    sort_type: null,
});

const activeFilterCount = computed(() => {
    return [filterParams.value.faction, filterParams.value.station, filterParams.value.characteristic].filter((v) => v != null).length;
});

const clear = () => {
    filterParams.value.faction = null;
    filterParams.value.station = null;
    filterParams.value.characteristic = null;
    filterParams.value.page_view = 'images';
    filterParams.value.sort = 'name';
    filterParams.value.sort_type = 'ascending';
    filter();
};

const filter = () => {
    router.get(route(route().current(), route().params.keyword), cleanObject(filterParams.value), {
        only: ['characters', 'keyword_breakdown', 'statistics'],
        replace: true,
        preserveState: true,
    });
};

const handleViewChange = (value: string) => {
    filterParams.value.page_view = value;
    filter();
};

const urlParams = new URLSearchParams(window.location.search);

onMounted(() => {
    filterParams.value.faction = urlParams.get('faction');
    filterParams.value.station = urlParams.get('station');
    filterParams.value.characteristic = urlParams.get('characteristic');
    filterParams.value.page_view = urlParams.get('page_view') ?? 'images';
    filterParams.value.sort = urlParams.get('sort') ?? 'name';
    filterParams.value.sort_type = urlParams.get('sort_type') ?? 'ascending';
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

const hasStats = computed(() => props.statistics && Object.keys(props.statistics).length > 0);

const statItems = computed(() => {
    if (!hasStats.value) return [];
    return [
        { label: 'Avg Cost', value: props.statistics.avg_cost },
        { label: 'Avg HP', value: props.statistics.avg_health },
        { label: 'Avg Spd', value: props.statistics.avg_speed },
        { label: 'Avg Def', value: props.statistics.avg_defense },
        { label: 'Avg Wp', value: props.statistics.avg_willpower },
    ].filter((s: any) => s.value != null);
});

const stationCounts = computed(() => {
    if (!hasStats.value) return [];
    return [
        { label: 'Masters', value: props.statistics.total_masters },
        { label: 'Henchmen', value: props.statistics.total_henchmen },
        { label: 'Unique', value: props.statistics.total_unique },
        { label: 'Minions', value: props.statistics.total_minions },
        { label: 'Peons', value: props.statistics.total_peons },
    ].filter((s: any) => s.value > 0);
});

const suitOrder = ['crow', 'mask', 'ram', 'tome', 'soulstone'];
const suitStats = computed(() => {
    if (!hasStats.value || !props.statistics.suit_counts) return [];
    const counts = props.statistics.suit_counts as Record<string, number>;
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
    <Head :title="keyword.name" />
    <div class="relative">
        <div
            class="pointer-events-none absolute inset-x-0 top-0 h-64 opacity-[0.07] dark:opacity-[0.12]"
            :style="{ background: 'radial-gradient(ellipse at top, hsl(var(--primary)) 0%, transparent 70%)' }"
        />
        <PageBanner :title="keyword.name">
            <template #subtitle>
                <div class="my-auto px-2 py-0 text-xs text-muted-foreground md:flex md:py-2 md:text-sm md:text-foreground">
                    <div>{{ props.characters?.length ?? 0 }} Characters</div>
                </div>
            </template>
        </PageBanner>
        <div class="container mx-auto mb-2 flex flex-wrap items-center justify-between gap-2 px-4">
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
            </div>
            <FilterPanel :filter-count="activeFilterCount" @filter="filter" @clear="clear">
                <div class="grid gap-4">
                    <div class="space-y-2">
                        <label class="text-sm font-medium">Faction</label>
                        <Select v-model="filterParams.faction">
                            <SelectTrigger class="rounded border-2 border-primary">
                                <SelectValue placeholder="Faction" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="faction in props.factions" :value="faction.value" :key="faction.value">
                                    {{ faction.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="space-y-2">
                        <label class="text-sm font-medium">Station</label>
                        <Select v-model="filterParams.station">
                            <SelectTrigger class="rounded border-2 border-primary">
                                <SelectValue placeholder="Station" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="station in props.stations" :value="station.value" :key="station.value">
                                    {{ station.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="space-y-2">
                        <label class="text-sm font-medium">Characteristic</label>
                        <Select v-model="filterParams.characteristic">
                            <SelectTrigger class="rounded border-2 border-primary">
                                <SelectValue placeholder="Characteristic" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="characteristic in props.characteristics" :value="characteristic.slug" :key="characteristic.slug">
                                    {{ characteristic.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <Separator />
                    <div class="space-y-2">
                        <label class="text-sm font-medium">Sort By</label>
                        <Select v-model="filterParams.sort">
                            <SelectTrigger class="rounded border-2 border-primary">
                                <SelectValue placeholder="Sort Options" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="sort in props.sort_options" :value="sort.value" :key="sort.value">
                                    {{ sort.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="space-y-2">
                        <label class="text-sm font-medium">Sort Direction</label>
                        <Select v-model="filterParams.sort_type">
                            <SelectTrigger class="rounded border-2 border-primary">
                                <SelectValue placeholder="Sort Type" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="type in props.sort_types" :value="type.value" :key="type.value">
                                    {{ type.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </div>
            </FilterPanel>
        </div>
        <!-- Stats Block -->
        <div v-if="hasStats && !isLoading" class="container mx-auto mb-4 px-4">
            <div class="rounded-lg border bg-card p-3 sm:p-4">
                <div class="flex flex-wrap items-center gap-x-3 gap-y-2 sm:gap-x-5">
                    <div v-if="statistics.factions?.length" class="flex items-center gap-1.5">
                        <Link v-for="f in statistics.factions" :key="f.value" :href="route('factions.view', f.value)">
                            <Badge variant="secondary" class="cursor-pointer gap-1.5 transition-colors hover:bg-accent">
                                <FactionLogo :faction="f.value" class-name="h-4 w-4" />
                                {{ f.name }}
                            </Badge>
                        </Link>
                    </div>
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
        <div
            v-if="isLoading && (filterParams.page_view === 'table' || filterParams.page_view === 'keyword_breakdown')"
            class="container mx-auto mt-4 items-center overflow-auto px-4"
        >
            <TableSkeleton :rows="8" :cols="7" />
        </div>
        <div v-else-if="isLoading" class="container mx-auto mt-4 items-center px-4">
            <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
                <CardSkeleton v-for="n in 8" :key="`skeleton-${n}`" />
            </div>
        </div>
        <div v-else-if="filterParams.page_view === 'keyword_breakdown'" class="container mx-auto items-center px-4">
            <template v-if="props.keyword_breakdown">
                <KeywordBreakdown :keyword="props.keyword_breakdown" />
            </template>
            <EmptyState v-else />
        </div>
        <div v-else-if="filterParams.page_view === 'table'" class="container mx-auto items-center overflow-auto px-4">
            <CharacterTable :characters="props.characters" />
        </div>
        <div v-else-if="filterParams.page_view === 'full'" class="container mx-auto items-center px-4">
            <template v-if="props.characters?.length">
                <div v-for="character in props.characters" v-bind:key="character.slug">
                    <CharacterView :character="character" :miniature="character.standard_miniatures[0]" />
                </div>
            </template>
            <EmptyState v-else />
        </div>
        <div v-else class="container mx-auto items-center px-4">
            <template v-if="props.characters?.length">
                <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
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
</template>
