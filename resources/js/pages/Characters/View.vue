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
</script>

<template>
    <Head :title="character.display_name" />
    <div class="w-full h-full">
        <CharacterView :character="props.character" :miniature="props.miniature" />
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
