<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card'
import { Separator } from '@/components/ui/separator';

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
</script>

<template>
    <Head :title="character.display_name" />
    <div class="w-full h-full">
        <div class="container flex flex-1 flex-col gap-4 rounded-xl p-4 mb-8 mx-auto">
            <div class="grid auto-rows-min gap-2 md:grid-cols-8">
                <div class="flex flex-col space-y-1.5 md:col-span-2">
                    <img :src="front_img_url" :alt="miniature.name" class="rounded">
                </div>
                <div class="flex flex-col space-y-1.5 md:col-span-2">
                    <img :src="back_img_url" :alt="miniature.name" class="rounded">
                </div>
                <div class="flex flex-col space-y-1.5 md:col-span-3">
                    <Card class="w-full border-t-4 border-b-4 m-0 p-0">
                        <CardHeader class="px-4 py-2 border-b-2">
                            <CardTitle class="text-xl font-normal">
                                {{ miniature.display_name }}
                            </CardTitle>
                            <CardDescription v-if="miniature.name || miniature.title" class="italic">
                                {{ character.display_name }}
                            </CardDescription>
                        </CardHeader>
                        <CardContent class="px-0 py-2">
                            <div class="px-4 py-2 m-0 border-b-2" v-if="character.keywords.length > 0">
                                <span v-for="keyword in character.keywords">{{ keyword.name }}</span>
                            </div>

                        </CardContent>
                    </Card>
                </div>
                <div class="flex flex-col space-y-1.5">

                </div>
            </div>
        </div>
        <Separator />


    </div>
</template>
