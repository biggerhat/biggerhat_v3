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
import {
    Drawer,
    DrawerClose,
    DrawerContent,
    DrawerFooter,
    DrawerHeader,
    DrawerTitle,
    DrawerTrigger
} from "@/components/ui/drawer";

const page = usePage<SharedData>();

const closeRef = ref(null);
function handleClose() {
    closeRef.value?.click();
}

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
    handleClose();
    router.get(
        route(route().current(), route().params.factionEnum),
        cleanObject(filterParams.value),
        {
            only: ['characters', 'keyword_breakdown'],
            replace: true,
            preserveState: true,
        }
    );
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
});

</script>

<template>
    <Head :title="faction.name" />
    <div class="w-full h-full">
        <div class="flex w-full bg-secondary mb-2">
            <div class="container mx-auto items-center">
                <div class="flex justify-between">
                    <div class="py-1 md:py-4 flex w-full">
                        <div class="w-20 md:w-32"><img :src='props.faction.logo' class="w-16 h-16 md:w-20 md:h-20 mx-auto my-auto" :alt="props.faction.name" /></div>
                        <div class="flex justify-between w-full md:block">
                            <div class="p-2 font-bold text-xl my-auto">{{ faction.name }}</div>
                            <div class="hidden md:block px-2 py-0 md:py-2 my-auto md:flex text-sm">
                                <div class="md:border-r-2 md:border-r-primary md:pr-2">{{ props.statistics.characters }} Characters</div>
                                <div class="md:pl-2 md:border-r-2 md:border-r-primary md:pr-2">{{ props.statistics.miniatures }} Miniatures</div>
                                <div class="md:pl-2">{{ props.statistics.keywords }} Keywords</div>
                            </div>
                        </div>
                    </div>
                    <div class="my-auto">
                        <Drawer>
                            <DrawerTrigger as-child>
                                <Button class="mx-auto bg-background border-primary border-2 text-primary hover:bg-secondary mr-6">
                                    <SlidersHorizontal />
                                </Button>
                            </DrawerTrigger>
                            <DrawerContent>
                                <div class="container mx-auto w-full">
                                    <DrawerHeader class="w-full">
                                        <DrawerTitle class="mx-auto">Filter & Sort Options</DrawerTitle>
                                    </DrawerHeader>
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
                                    <DrawerFooter class="w-full">
                                        <DrawerClose as-child>
                                            <button ref="closeRef" class="hidden"></button>
                                        </DrawerClose>
                                        <div class="mx-auto">
                                            <Button class="bg-secondary text-primary border-primary border-2 rounded mx-1 hover:text-secondary" @click="filter">
                                                Search
                                            </Button>
                                            <Button class="bg-secondary text-primary border-primary border-2 rounded mx-1 hover:text-secondary" @click="clear">
                                                Clear
                                            </Button>
                                            <DrawerClose>
                                                <Button class="bg-destructive text-primary border-primary border-2 rounded mx-1 hover:text-secondary">
                                                    Close
                                                </Button>
                                            </DrawerClose>
                                        </div>
                                    </DrawerFooter>
                                </div>
                            </DrawerContent>
                        </Drawer>
                    </div>
                </div>
            </div>
        </div>
        <div v-if="currentView === 'keyword_breakdown'" class="container mx-auto items-center px-2">
            <KeywordBreakdown v-for="keyword in props.keyword_breakdown" v-bind:key="keyword.keyword.name" :keyword="keyword" />
        </div>
        <div v-else-if="currentView === 'table'" class="container mx-auto items-center overflow-auto px-2">
            <CharacterTable :characters="props.characters" />
        </div>
        <div v-else-if="currentView === 'full'" class="container mx-auto items-center px-2">
            <div v-for="character in props.characters" v-bind:key="character.slug">
                <CharacterView :character="character" :miniature="character.standard_miniatures[0]" />
            </div>
        </div>
        <div v-else class="px-2 container mx-auto items-center">
            <div class="grid grid-cols-1 mx-2 md:mx-0 md:grid-cols-4 md:gap-2 snap-y md:snap-none overflow-y-scroll md:overflow-y-auto snap-mandatory h-screen md:h-auto">
                <div v-for="character in props.characters"
                     :key="`character-${character.id}`"
                     class="mb-2 md:mb-0 md:snap-none snap-always md:snap-normal snap-start"
                >
                    <CharacterCardView :miniature="character.standard_miniatures[0]" :character-slug="character.slug" />
                </div>
            </div>
        </div>
    </div>
</template>
