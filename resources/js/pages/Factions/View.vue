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
import { Button } from '@/components/ui/button';
import {SharedData} from "@/types";
import SearchResultsCard from "@/components/SearchResultsCard.vue";
import { cleanObject } from "@/composables/CleanObject";
import {Select, SelectContent, SelectItem, SelectTrigger, SelectValue} from "@/components/ui/select";
const page = usePage<SharedData>();

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
    }
});

const filterParams = ref({
    keyword: null,
    station: null,
});

const clear = () => {
    filterParams.value.keyword = null;
    filterParams.value.station = null;
    filter();
}

const filter = () => {
    router.get(
        route(route().current(), route().params.factionEnum),
        cleanObject(filterParams.value),
        {
            replace: true,
            preserveState: true,
            preserveScroll: true,
        }
    )
};
const urlParams = new URLSearchParams(window.location.search);
onMounted(() => {
    filterParams.value.keyword = urlParams.get("keyword");
    filterParams.value.station = urlParams.get("station");
});

</script>

<template>
    <Head :title="faction.name" />
    <div class="w-full h-full">
        <div class="flex w-full bg-secondary">
            <div class="container mx-auto items-center">
                <div class="flex justify-between">
                    <div class="py-4 flex">
                        <div><img :src='props.faction.logo' class="w-20 h-20" :alt="props.faction.name" /></div>
                        <div class="flex justify-between md:block">
                            <div class="p-2 font-bold text-xl">{{ faction.name }}</div>
                            <div class="px-2 py-0 md:py-2 md:flex text-sm">
                                <div class="md:border-r-2 md:border-r-primary md:pr-2">{{ props.statistics.characters }} Characters</div>
                                <div class="md:pl-2 md:border-r-2 md:border-r-primary md:pr-2">{{ props.statistics.miniatures }} Miniatures</div>
                                <div class="md:pl-2">{{ props.statistics.keywords }} Keywords</div>
                            </div>
                        </div>
                    </div>
                    <div class="my-auto md:flex hidden">
                        <div class="mx-1">
                            <Select v-model="filterParams.keyword">
                                <SelectTrigger class="border-2 border-primary rounded">
                                    <SelectValue placeholder="Select Keyword" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="keyword in props.keywords" :value="keyword.slug" :key="keyword.slug">
                                        {{ keyword.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div><div class="mx-1">
                            <Select v-model="filterParams.station">
                                <SelectTrigger class="border-2 border-primary rounded">
                                    <SelectValue placeholder="Select Station" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="station in props.stations" :value="station.value" :key="station.value">
                                        {{ station.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div><div class="mx-1">
                            <Button class="bg-secondary text-primary border-primary border-2 rounded mx-1" @click="filter">
                                Filter
                            </Button>
                            <Button class="bg-secondary text-primary border-primary border-2 rounded mx-1" @click="clear">
                                Clear
                            </Button>
                        </div>

                    </div>
                </div>
            </div>
        </div>
        <div class="container mx-auto items-center mt-8">
<!--            <div class="inline-flex items-center justify-center mt-16 w-full">-->
<!--                <hr class="bg-primary w-full h-0.5 my-8 border-0 rounded-sm">-->
<!--                <div class="absolute px-4 -translate-x-1/2 left-1/2">-->
<!--                    <img :src='props.faction.logo' class="w-40 h-40" :alt="props.faction.name" />-->
<!--                </div>-->
<!--            </div>-->
            <div class="grid grid-cols-1 mx-2 md:mx-0 md:grid-cols-4 md:gap-2">
                <div v-for="character in props.characters" class="mb-2 md:mb-0">
                    <Link :href="route('characters.view', {'character': character.slug, 'miniature': character.standard_miniatures[0].id, 'slug': character.standard_miniatures[0].slug})">
                        <img :src='"/storage/" + character.standard_miniatures[0].front_image' :alt="character.standard_miniatures[0].display_name" class="rounded-lg">
                    </Link>
                </div>
            </div>
        </div>
    </div>
</template>
