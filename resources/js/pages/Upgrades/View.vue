<script setup lang="ts">
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import UpgradeCardView from '@/components/UpgradeCardView.vue';
import { isMobileDevice } from '@/composables/useMobileDevice';
import { Head } from '@inertiajs/vue3';

const props = defineProps({
    upgrade: {
        type: [Object, Array],
        required: false,
        default() {
            return null;
        },
    },
});
</script>

<template>
    <Head :title="upgrade.name" />
    <div class="h-full w-full">
        <div class="animate-fade-in-up container mx-auto mb-8 flex flex-1 flex-col gap-4 rounded-xl p-4">
            <div class="grid auto-rows-min gap-2 md:grid-cols-8">
                <div class="flex flex-col space-y-1.5 md:col-span-4" v-if="props.upgrade.combination_image && !isMobileDevice()">
                    <img :src="'/storage/' + props.upgrade.combination_image" :alt="upgrade.name" class="rounded" />
                </div>
                <div v-else class="flex flex-col space-y-1.5 md:col-span-2 md:col-start-2">
                    <UpgradeCardView :upgrade="props.upgrade" show-link="false" />
                </div>
                <div class="flex flex-col space-y-1.5 md:col-span-2">
                    <Card class="m-0 w-full rounded-none border-none p-0">
                        <CardHeader class="border-b-2 border-l-2 border-primary px-4 py-2">
                            <CardTitle class="text-lg font-normal">
                                {{ upgrade.name }}
                            </CardTitle>
                        </CardHeader>
                        <CardContent class="border-l border-r px-0 py-0"> </CardContent>
                    </Card>
                </div>
            </div>
        </div>
    </div>
</template>
