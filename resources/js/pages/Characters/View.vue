<script setup lang="ts">
import CharacterView from '@/components/CharacterView.vue';
import { useFactionColor } from '@/composables/useFactionColor';
import { Head } from '@inertiajs/vue3';

const props = defineProps({
    character: {
        type: Object,
        required: true,
    },
    miniature: {
        type: Object,
        required: true,
    },
    related_characters: {
        type: Array,
        required: false,
        default: () => [],
    },
});

const factionColor = props.character?.faction ? useFactionColor(props.character.faction) : '';
</script>

<template>
    <Head :title="character.display_name" />
    <div class="relative h-full w-full">
        <div
            v-if="factionColor"
            class="pointer-events-none absolute inset-x-0 top-0 h-64 opacity-[0.07] dark:opacity-[0.12]"
            :style="{ background: `radial-gradient(ellipse at top, hsl(var(--${factionColor})) 0%, transparent 70%)` }"
        />
        <div class="animate-fade-in-up">
            <CharacterView :character="props.character" :miniature="props.miniature" :related-characters="props.related_characters" />
        </div>
    </div>
</template>
