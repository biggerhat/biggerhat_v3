<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ref, computed, onMounted } from 'vue';
import { useStaggeredEntry } from '@/composables/useStaggeredEntry';
import { LayoutGrid, List, BookOpen, Grid2x2 } from 'lucide-vue-next';

import { cleanObject } from '@/composables/CleanObject';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import CharacterCardView from '@/components/CharacterCardView.vue';
import CharacterView from '@/components/CharacterView.vue';
import KeywordBreakdown from '@/components/KeywordBreakdown.vue';
import CharacterTable from '@/components/CharacterTable.vue';
import PageBanner from '@/components/PageBanner.vue';
import FilterPanel from '@/components/FilterPanel.vue';
import EmptyState from '@/components/EmptyState.vue';

import CardSkeleton from '@/components/CardSkeleton.vue';
import TableSkeleton from '@/components/TableSkeleton.vue';

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
        only: ['characters', 'keyword_breakdown'],
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

const characterCount = computed(() => props.characters?.length ?? 0);
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
    <Head :title="keyword.name" />
    <div class="w-full h-full">
        <PageBanner :title="keyword.name">
            <template #subtitle>
                <div class="px-2 py-0 md:py-2 my-auto md:flex text-xs md:text-sm text-muted-foreground md:text-foreground">
                    <div>{{ props.characters?.length ?? 0 }} Characters</div>
                </div>
            </template>
        </PageBanner>
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
                    <TabsTrigger value="keyword_breakdown">
                        <Grid2x2 class="h-4 w-4" />
                        <span class="hidden sm:inline">Keywords</span>
                    </TabsTrigger>
                </TabsList>
            </Tabs>
            <FilterPanel :filter-count="activeFilterCount" @filter="filter" @clear="clear">
                <div class="grid gap-4">
                    <div class="space-y-2">
                        <label class="text-sm font-medium">Faction</label>
                        <Select v-model="filterParams.faction">
                            <SelectTrigger class="border-2 border-primary rounded">
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
                            <SelectTrigger class="border-2 border-primary rounded">
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
                            <SelectTrigger class="border-2 border-primary rounded">
                                <SelectValue placeholder="Characteristic" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="characteristic in props.characteristics"
                                    :value="characteristic.slug"
                                    :key="characteristic.slug"
                                >
                                    {{ characteristic.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <Separator />
                    <div class="space-y-2">
                        <label class="text-sm font-medium">Sort By</label>
                        <Select v-model="filterParams.sort">
                            <SelectTrigger class="border-2 border-primary rounded">
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
                            <SelectTrigger class="border-2 border-primary rounded">
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
        <div
            v-if="isLoading && (filterParams.page_view === 'table' || filterParams.page_view === 'keyword_breakdown')"
            class="container mx-auto items-center overflow-auto mt-4"
        >
            <TableSkeleton :rows="8" :cols="7" />
        </div>
        <div v-else-if="isLoading" class="container mx-auto items-center mt-4">
            <div class="grid grid-cols-2 gap-3 md:grid-cols-4 md:gap-4">
                <CardSkeleton v-for="n in 8" :key="`skeleton-${n}`" />
            </div>
        </div>
        <div v-else-if="filterParams.page_view === 'keyword_breakdown'" class="container mx-auto items-center">
            <template v-if="props.keyword_breakdown">
                <KeywordBreakdown :keyword="props.keyword_breakdown" />
            </template>
            <EmptyState v-else />
        </div>
        <div v-else-if="filterParams.page_view === 'table'" class="container mx-auto items-center overflow-auto">
            <CharacterTable :characters="props.characters" />
        </div>
        <div v-else-if="filterParams.page_view === 'full'" class="container mx-auto items-center">
            <template v-if="props.characters?.length">
                <div v-for="character in props.characters" v-bind:key="character.slug">
                    <CharacterView :character="character" :miniature="character.standard_miniatures[0]" />
                </div>
            </template>
            <EmptyState v-else />
        </div>
        <div v-else class="container mx-auto items-center">
            <template v-if="props.characters?.length">
                <div class="grid grid-cols-2 gap-3 md:grid-cols-4 md:gap-4">
                    <div
                        v-for="(character, index) in props.characters"
                        :key="`character-${character.id}`"
                        class="animate-fade-in-up opacity-0"
                        :style="delays[index]"
                    >
                        <CharacterCardView :miniature="character.standard_miniatures[0]" :character-slug="character.slug" />
                    </div>
                </div>
            </template>
            <EmptyState v-else />
        </div>
    </div>
</template>
