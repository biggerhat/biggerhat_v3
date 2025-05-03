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
import ArcanistLogo from "@/components/ArcanistLogo.vue";
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
    }
});
</script>

<template>
    <Head :title="faction.name" />
    <div class="w-full h-full">
        <div class="container mx-auto items-center">
            <div class="inline-flex items-center justify-center mt-16 w-full">
                <hr class="bg-primary w-full h-1 my-8 border-0 rounded-sm">
                <div class="absolute px-4 -translate-x-1/2 left-1/2">
                    <img src='/images/Factions/M4E-Arcanists.png' class="w-40 h-40" :alt="props.faction.name" />
                </div>
            </div>
        </div>
    </div>
</template>
