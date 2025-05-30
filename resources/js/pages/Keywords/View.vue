<script setup lang="ts">
import { Head, usePage, router } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible'
import { Button } from '@/components/ui/button';
import {SharedData} from "@/types";
import { cleanObject } from "@/composables/CleanObject";
import {Select, SelectContent, SelectItem, SelectTrigger, SelectValue} from "@/components/ui/select";
import { SlidersHorizontal } from "lucide-vue-next";
import CharacterCardView from "@/components/CharacterCardView.vue";
import CharacterView from "@/components/CharacterView.vue";
import KeywordBreakdown from "@/components/KeywordBreakdown.vue";
import CharacterTable from "@/components/CharacterTable.vue";

const page = usePage<SharedData>();

function isMobileDevice() {
    return /Mobi|Android/i.test(navigator.userAgent);
}

const props = defineProps({
    keyword: {
        type: [Object, Array],
        required: true,
        default() {
            return {};
        }
    },
    characters: {
        type: [Object, Array],
        required: false,
        default() {
            return {};
        }
    },
    station_sort: {
        type: [Object, Array],
        required: false,
        default() {
            return {};
        }
    },
    keyword_breakdown: {
        type: [Object, Array],
        required: false,
        default() {
            return {};
        }
    },
    characteristics: {
        type: [Object, Array],
        required: false,
        default() {
            return {};
        }
    },
    statistics: {
        type: [Object, Array],
        required: false,
        default() {
            return {};
        }
    },
    factions: {
        type: [Object, Array],
        required: false,
        default() {
            return {};
        }
    },
    stations: {
        type: [Object, Array],
        required: false,
        default() {
            return {};
        }
    },
    sort_options: {
        type: [Object, Array],
        required: false,
        default() {
            return {};
        }
    },
    sort_types: {
        type: [Object, Array],
        required: false,
        default() {
            return {};
        }
    },
    view_options: {
        type: [Object, Array],
        required: false,
        default() {
            return {};
        }
    }
});

const filterPanelOpen = ref(false);
const filterParams = ref({
    faction: null,
    station: null,
    characteristic: null,
    page_view: null,
    sort: null,
    sort_type: null,
});

const currentView = ref('images');

const clear = () => {
    filterParams.value.faction = null;
    filterParams.value.station = null;
    filterParams.value.characteristic = null;
    filterParams.value.page_view = 'images';
    filterParams.value.sort = 'name';
    filterParams.value.sort_type = 'ascending';
    filter();
}

const filter = () => {
    router.get(
        route(route().current(), route().params.keyword),
        cleanObject(filterParams.value),
        {
            only: ['characters', 'keyword_breakdown'],
            replace: true,
            preserveState: true,
        }
    )
    currentView.value = filterParams.value.page_view;
};
const urlParams = new URLSearchParams(window.location.search);

onMounted(() => {
    filterParams.value.faction = urlParams.get("faction");
    filterParams.value.station = urlParams.get("station");
    filterParams.value.characteristic = urlParams.get("characteristic");
    filterParams.value.page_view = urlParams.get("page_view") ?? 'images';
    currentView.value = filterParams.value.page_view;
    filterParams.value.sort = urlParams.get("sort") ?? 'name';
    filterParams.value.sort_type = urlParams.get("sort_type") ?? 'ascending';

    if (!isMobileDevice()) {
        filterPanelOpen.value = true;
    } else {
        const el = document.getElementById('page-banner');
    }
});

</script>

<template>
    <Head :title="keyword.name" />
    <div class="w-full h-full">
        <div class="flex w-full bg-secondary">
            <div class="container mx-auto items-center">
                <div class="flex justify-between">
                    <div class="py-1 md:py-4 flex w-full">
                        <div class="flex justify-between w-full md:block" id="page-banner">
                            <div class="p-2 font-bold text-xl my-auto">{{ keyword.name }}</div>
                            <div class="hidden md:block px-2 py-0 md:py-2 my-auto md:flex text-sm">
                                <div class="md:border-r-2 md:border-r-primary md:pr-2">{{ props.statistics.characters }} Characters</div>
                                <div class="md:pl-2 md:border-r-2 md:border-r-primary md:pr-2">{{ props.statistics.miniatures }} Miniatures</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="container mx-auto">
            <div class="flex items-center justify-center p-2 w-full">
                <Collapsible v-model:open="filterPanelOpen" class="w-full group/collapsible">
                    <div class="w-full flex items-center justify-center">
                        <CollapsibleTrigger>
                            <Button class="mx-auto" :class="filterPanelOpen ? 'bg-primary border-primary border-2' : 'bg-background border-primary border-2 text-primary hover:bg-secondary'"><SlidersHorizontal /> Filter Options</Button>
                        </CollapsibleTrigger>
                    </div>
                    <CollapsibleContent class="w-full">
                        <div class="w-full mx-auto flex items-center justify-center p-2">
                            <div class="my-auto md:flex w-full md:w-auto">
                                <div class="mx-0 md:mx-1 my-auto text-center">
                                    Filter by...
                                </div>
                                <div class="mx-0 md:mx-1 my-1 min-w-40">
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
                                </div><div class="mx-0 md:mx-1 my-1 min-w-40">
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
                            </div><div class="mx-0 md:mx-1 my-1 min-w-40">
                                <Select v-model="filterParams.characteristic">
                                    <SelectTrigger class="border-2 border-primary rounded">
                                        <SelectValue placeholder="Characteristic" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem v-for="characteristic in props.characteristics" :value="characteristic.slug" :key="characteristic.slug">
                                            {{ characteristic.name }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            </div>
                        </div>
                        <div class="w-full mx-auto flex items-center justify-center p-2 gap-1 md:gap-0">
                            <div class="my-auto md:flex w-full md:w-auto w-full">
                                <div class="mx-0 md:mx-1 my-auto text-center">
                                    View as...
                                </div>
                                <div class="mx-0 md:mx-1 my-1 min-w-40">
                                    <Select v-model="filterParams.page_view">
                                        <SelectTrigger class="border-2 border-primary rounded">
                                            <SelectValue placeholder="View Options" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem v-for="view in props.view_options" :value="view.value" :key="view.value">
                                                {{ view.name }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                            </div>
                            <div class="my-auto md:flex w-full md:w-auto w-full">
                                <div class="mx-0 md:mx-1 my-auto text-center">
                                    Sort by...
                                </div>
                                <div class="mx-0 md:mx-1 my-1 min-w-40">
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
                                <div class="mx-0 md:mx-1 my-1 min-w-40">
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
                        </div>
                        <div class="w-full mx-auto flex items-center justify-center p-2">
                            <div class="mx-1 my-auto">
                                <Button class="bg-secondary text-primary border-primary border-2 rounded mx-1 hover:text-secondary" @click="filter">
                                    Search
                                </Button>
                                <Button class="bg-secondary text-primary border-primary border-2 rounded mx-1 hover:text-secondary" @click="clear">
                                    Clear
                                </Button>
                            </div>
                        </div>
                    </CollapsibleContent>
                </Collapsible>
            </div>
        </div>
        <div v-if="currentView === 'keyword_breakdown'" class="container mx-auto items-center">
            <KeywordBreakdown :keyword="props.keyword_breakdown" />
        </div>
        <div v-else-if="currentView === 'table'" class="container mx-auto items-center overflow-auto">
            <CharacterTable :characters="props.characters" />
        </div>
        <div v-else-if="currentView === 'full'" class="container mx-auto items-center">
            <div v-for="character in props.characters" v-bind:key="character.slug">
                <CharacterView :character="character" :miniature="character.standard_miniatures[0]" />
            </div>
        </div>
        <div v-else class="container mx-auto items-center">
            <div class="grid grid-cols-1 mx-2 md:mx-0 md:grid-cols-4 md:gap-2 snap-y md:snap-none overflow-y-scroll md:overflow-y-auto snap-mandatory h-screen md:h-auto">
                <div v-for="character in props.characters"
                     :key="`character-${character.id}`"
                     class="mb-2 md:mb-0 md:snap-none snap-always md:snap-normal snap-start"
                >
                    <CharacterCardView :miniature="character.standard_miniatures[0]" />
                </div>
            </div>
        </div>
    </div>
</template>
