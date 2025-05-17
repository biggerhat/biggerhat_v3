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
import CharacterView from "@/components/CharacterView.vue";
import CharacterCardView from "@/components/CharacterCardView.vue";
import UpgradeCardView from "@/components/UpgradeCardView.vue";
const page = usePage<SharedData>();

function isMobileDevice() {
    return /Mobi|Android/i.test(navigator.userAgent);
}

const props = defineProps({
    upgrade: {
        type: [Object, Array],
        required: false,
        default() {
            return null;
        }
    },
});
</script>

<template>
    <Head :title="upgrade.name" />
    <div class="w-full h-full">
        <div class="container flex flex-1 flex-col gap-4 rounded-xl p-4 mb-8 mx-auto">
            <div class="grid auto-rows-min gap-2 md:grid-cols-8">
                <div class="flex flex-col space-y-1.5 md:col-span-4" v-if="props.upgrade.combination_image && !isMobileDevice()">
                    <img :src='"/storage/" + props.upgrade.combination_image' :alt="upgrade.name" class="rounded">
                </div>
                <div v-else class="flex flex-col space-y-1.5 md:col-span-2 md:col-start-2">
                    <UpgradeCardView :upgrade="props.upgrade" show-link="false" />
                </div>
                <div class="flex flex-col space-y-1.5 md:col-span-2">
                    <Card class="w-full rounded-none border-none m-0 p-0">
                        <CardHeader class="px-4 py-2 border-primary border-l-2 border-b-2">
                            <CardTitle class="text-lg font-normal">
                                {{ upgrade.name }}
                            </CardTitle>
                        </CardHeader>
                        <CardContent class="px-0 border-l border-r py-0">
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>
    </div>
</template>
