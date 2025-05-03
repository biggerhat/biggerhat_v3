<script setup lang="ts">
import {Head, usePage} from '@inertiajs/vue3';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card'
import {SharedData} from "@/types";
import SearchResultsCard from "@/components/SearchResultsCard.vue";
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
</script>

<template>
    <Head :title="faction.name" />
    <div class="w-full h-full">
        <div class="flex w-full bg-secondary">
            <div class="container mx-auto items-center">
                <div class="flex justify-between">
                    <div class="py-4 flex">
                        <div><img :src='props.faction.logo' class="w-20 h-20" :alt="props.faction.name" /></div>
                        <div>
                            <div class="p-2 font-bold text-xl">{{ faction.name }}</div>
                            <div class="p-2 flex text-sm">
                                <div class="border-r-2 border-r-primary pr-2">{{ props.statistics.characters }} Characters</div>
                                <div class="pl-2 border-r-2 border-r-primary pr-2">{{ props.statistics.miniatures }} Miniatures</div>
                                <div class="pl-2">{{ props.statistics.keywords }} Keywords</div>
                            </div>
                        </div>
                    </div>
                    <div class="my-auto md:flex hidden">
                        <div class="mx-1">
                            <Select>
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
                            <Select>
                                <SelectTrigger class="border-2 border-primary rounded">
                                    <SelectValue placeholder="Select Station" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="station in props.stations" :value="station.value" :key="station.value">
                                        {{ station.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
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
            <div class="grid grid-cols-1 md:grid-cols-4">
                <div v-for="character in props.characters">
                    <SearchResultsCard :miniature="character.standard_miniatures[0]" />
                </div>
            </div>
        </div>
    </div>
</template>
