<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import CharacterView from '@/components/CharacterView.vue';
import { useFactionColor } from '@/composables/useFactionColor';

const props = defineProps({
    character: {
        type: [Object, Array],
        required: false,
        default() {
            return null;
        },
    },
    miniature: {
        type: [Object, Array],
        required: false,
        default() {
            return null;
        },
    },
});

const factionColor = props.character?.faction ? useFactionColor(props.character.faction) : '';
</script>

<template>
    <Head :title="character.display_name" />
    <div class="w-full h-full relative">
        <div
            v-if="factionColor"
            class="pointer-events-none absolute inset-x-0 top-0 h-64 opacity-[0.07] dark:opacity-[0.12]"
            :style="{ background: `radial-gradient(ellipse at top, hsl(var(--${factionColor})) 0%, transparent 70%)` }"
        />
        <div class="animate-fade-in-up">
            <CharacterView :character="props.character" :miniature="props.miniature" />
        </div>
    </div>
</template>
