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
import { Separator } from '@/components/ui/separator';
import {SharedData} from "@/types";
const page = usePage<SharedData>();

const props = defineProps({
    character: {
        type: [Object, Array],
        required: false,
        default() {
            return null;
        }
    },
    miniature: {
        type: [Object, Array],
        required: false,
        default() {
            return null;
        }
    },
});

const front_img_url = "/storage/" + props.miniature.front_image;
const back_img_url = "/storage/" + props.miniature.back_image;
const combo_img_url = "/storage/" + props.miniature.combination_image;
</script>

<template>
    <Head :title="character.display_name" />
    <div class="w-full h-full">
        <div class="container flex flex-1 flex-col gap-4 rounded-xl p-4 mb-8 mx-auto">
            <div class="grid auto-rows-min gap-2 md:grid-cols-8">
                <div class="flex flex-col space-y-1.5 md:col-span-4" v-if="props.miniature.combination_image">
                    <img :src="combo_img_url" :alt="miniature.display_name" class="rounded">
                </div>
                <div class="flex flex-col space-y-1.5 md:col-span-2" v-if="!props.miniature.combination_image && props.miniature.front_image">
                    <img :src="front_img_url" :alt="miniature.name" class="rounded">
                </div>
                <div class="flex flex-col space-y-1.5 md:col-span-2" v-if="!props.miniature.combination_image && props.miniature.back_image">
                    <img :src="back_img_url" :alt="miniature.name" class="rounded">
                </div>
                <div class="flex flex-col space-y-1.5 md:col-span-2">
                    <Card class="w-full rounded-none border-none m-0 p-0">
                        <CardHeader class="px-4 py-2 border-primary border-r-2 border-b-2">
                            <CardTitle class="text-xl text-right font-normal">
                                {{ miniature.display_name }}
                            </CardTitle>
                            <CardDescription v-if="miniature.name || miniature.title" class="italic text-right">
                                {{ character.display_name }}
                            </CardDescription>
                        </CardHeader>
                        <CardContent class="px-0 border-l border-r py-0">
                            <Link :href="route('factions.view', character.faction)" :class="'bg-' + character.faction_color + ' block p-2 m-0 w-full h-full text-md border-b hover:bg-secondary'">
                                <span class="block m-0 p-0 text-xs">Faction</span>
                                {{ page['props']['factions'][character['faction']]['name'] }}
                            </Link>
                            <div class="border-primary" v-if="character.keywords.length > 0">
                                <Link :href="route('keywords.view', keyword.slug)" class="block p-2 m-0 w-full h-full border-b hover:bg-secondary text-md" v-for="keyword in character.keywords">
                                    <span class="block m-0 p-0 text-xs">Keyword</span>
                                    {{ keyword.name }}
                                </Link>
                            </div>
                        </CardContent>
                    </Card>
                </div>
                <div class="flex flex-col space-y-1.5 md:col-span-2">
                    <Card class="w-full border-none m-0 p-0 !rounded-none">
                        <CardHeader class="px-4 py-2 border-primary border-r-2 border-b-2">
                            <CardTitle class="text-xl text-right font-normal">
                                Miniature Sculpts
                            </CardTitle>
                        </CardHeader>
                        <CardContent class="px-0 border-l border-r py-0">
                            <Link :href="route('characters.view', {character: character.slug, miniature: sculpt.id, slug: sculpt.slug})" class="block p-2 m-0 w-full h-full border-b hover:bg-secondary text-md" :class="{'bg-secondary': sculpt.id === props.miniature.id }" v-for="sculpt in character.miniatures">
                                {{ sculpt.display_name }}
                            </Link>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>
        <hr class="border-dashed border-t !rounded-none !border-l-0 !border-r-0 !border-b-0 !block" />
<!--        <div class="container flex flex-1 flex-col gap-4 rounded-xl p-4 mt-8 mb-8 mx-auto">-->
<!--            <div class="grid auto-rows-min gap-8 md:grid-cols-3">-->
<!--                <div>TOOLBOX</div>-->
<!--                <div>REFERENCES</div>-->
<!--                <div>IMAGES AND DATA</div>-->
<!--            </div>-->
<!--        </div>-->


    </div>
</template>
