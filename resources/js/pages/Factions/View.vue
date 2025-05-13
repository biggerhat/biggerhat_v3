<script setup lang="ts">
import { Head, usePage, router } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card'
import {
    Carousel,
    CarouselContent,
    CarouselItem,
    CarouselNext,
    CarouselPrevious,
} from '@/components/ui/carousel'
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible'
import { Button } from '@/components/ui/button';
import {SharedData} from "@/types";
import SearchResultsCard from "@/components/SearchResultsCard.vue";
import { cleanObject } from "@/composables/CleanObject";
import {Select, SelectContent, SelectItem, SelectTrigger, SelectValue} from "@/components/ui/select";
import { SlidersHorizontal, Search, ArrowDown, ArrowUp } from "lucide-vue-next";
import CharacterCardView from "@/components/CharacterCardView.vue";
import Separator from "@/components/ui/separator/Separator.vue";

const page = usePage<SharedData>();

function isMobileDevice() {
    return /Mobi|Android/i.test(navigator.userAgent);
}

const props = defineProps({
    faction: {
        type: [Object, Array],
        required: false,
        default() {
            return null;
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
    keywords: {
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
    keyword: null,
    station: null,
    characteristic: null,
    page_view: null,
    sort: null,
    sort_type: null,
});

const currentView = ref('images');

const clear = () => {
    filterParams.value.keyword = null;
    filterParams.value.station = null;
    filterParams.value.characteristic = null;
    filterParams.value.page_view = 'images';
    filterParams.value.sort = 'name';
    filterParams.value.sort_type = 'ascending';
    filter();
}

const filter = () => {
    router.get(
        route(route().current(), route().params.factionEnum),
        cleanObject(filterParams.value),
        {
            only: ['characters', 'keyword_breakdown', 'station_sort'],
            replace: true,
            preserveState: true,
        }
    )
    currentView.value = filterParams.value.page_view;
};
const urlParams = new URLSearchParams(window.location.search);
onMounted(() => {
    filterParams.value.keyword = urlParams.get("keyword");
    filterParams.value.station = urlParams.get("station");
    filterParams.value.characteristic = urlParams.get("characteristic");
    filterParams.value.page_view = urlParams.get("page_view") ?? 'images';
    currentView.value = filterParams.value.page_view;
    filterParams.value.sort = urlParams.get("sort") ?? 'name';
    filterParams.value.sort_type = urlParams.get("sort_type") ?? 'ascending';

    if (!isMobileDevice()) {
        filterPanelOpen.value = true;
    }
});

</script>

<template>
    <Head :title="faction.name" />
    <div class="w-full h-full">
        <div class="flex w-full bg-secondary">
            <div class="container mx-auto items-center">
                <div class="flex justify-between">
                    <div class="py-4 flex w-full">
                        <div class="w-32"><img :src='props.faction.logo' class="w-20 h-20" :alt="props.faction.name" /></div>
                        <div class="flex justify-between w-full md:block">
                            <div class="p-2 font-bold text-xl">{{ faction.name }}</div>
                            <div class="px-2 py-0 md:py-2 my-auto md:flex text-sm">
                                <div class="md:border-r-2 md:border-r-primary md:pr-2">{{ props.statistics.characters }} Characters</div>
                                <div class="md:pl-2 md:border-r-2 md:border-r-primary md:pr-2">{{ props.statistics.miniatures }} Miniatures</div>
                                <div class="md:pl-2">{{ props.statistics.keywords }} Keywords</div>
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
                                    <Select v-model="filterParams.keyword">
                                        <SelectTrigger class="border-2 border-primary rounded">
                                            <SelectValue placeholder="Keyword" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem v-for="keyword in props.keywords" :value="keyword.slug" :key="keyword.slug">
                                                {{ keyword.name }}
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
        <div v-if="currentView === 'keyword_breakdown'" class="container mx-auto items-center mt-8">
            <div v-for="keyword in props.keyword_breakdown" v-bind:key="keyword.keyword.name">
                <div v-if="Object.keys(keyword.characters).length > 0" class="mb-6">
                    <div class="relative flex py-5 items-center">
                        <div class="flex-grow border-t border-primary"></div>
                        <span class="flex-shrink mx-4 text-lg text-primary">{{ keyword.keyword.name }}</span>
                        <div class="flex-grow border-t border-primary"></div>
                    </div>
                    <div class="w-full lg:grid-cols-6 grid">
                        <div class="hidden lg:block grid"><CharacterCardView v-if="Object.keys(keyword.masters).length > 0" :miniature="keyword.masters[0]['standard_miniatures'][0]" /></div>
                        <div class="lg:col-span-4">
                            <div class="w-full grid lg:grid-cols-4">
                                <div v-for="character in keyword.characters" v-bind:key="character.slug">
                                    <CharacterCardView v-if="character.station !== 'master'" :miniature="character.standard_miniatures[0]" />
                                </div>
                            </div>
                        </div>
                        <div class="hidden lg:block grid"><CharacterCardView v-if="Object.keys(keyword.masters).length > 1" :miniature="keyword.masters[1]['standard_miniatures'][0]" /></div>
                    </div>
                </div>
            </div>
        </div>
        <div v-else class="container mx-auto items-center mt-8">
            <!--            <div class="inline-flex items-center justify-center mt-16 w-full">-->
            <!--                <hr class="bg-primary w-full h-0.5 my-8 border-0 rounded-sm">-->
            <!--                <div class="absolute px-4 -translate-x-1/2 left-1/2">-->
            <!--                    <img :src='props.faction.logo' class="w-40 h-40" :alt="props.faction.name" />-->
            <!--                </div>-->
            <!--            </div>-->
            <div class="grid grid-cols-1 mx-2 md:mx-0 md:grid-cols-4 md:gap-2 snap-y md:snap-none overflow-y-scroll md:overflow-y-auto snap-mandatory h-screen md:h-auto">
                <div v-for="character in props.characters" class="mb-2 md:mb-0 md:snap-none snap-always md:snap-normal snap-start">
                    <CharacterCardView :miniature="character.standard_miniatures[0]" />
                </div>
            </div>
        </div>
    </div>
</template>
