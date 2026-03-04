<script setup lang="ts">
import { ref } from 'vue';

defineProps<{
    frontImage: string;
    backImage?: string | null;
    altText?: string;
}>();

const flipped = ref(false);
</script>

<template>
    <div class="text-center transition-shadow duration-300 hover:shadow-lg hover:shadow-black/20">
        <div @click="flipped = !flipped" class="mx-1 cursor-pointer" style="perspective: 1000px">
            <div class="relative w-full" :class="{ 'card-flipped': flipped }" style="transition: transform 0.5s; transform-style: preserve-3d">
                <div style="backface-visibility: hidden">
                    <img :src="'/storage/' + frontImage" :alt="(altText ?? 'Card') + ' (front)'" class="h-full w-full rounded-lg" />
                </div>
                <div v-if="backImage" class="absolute inset-0" style="backface-visibility: hidden; transform: rotateY(180deg)">
                    <img :src="'/storage/' + backImage" :alt="(altText ?? 'Card') + ' (back)'" class="h-full w-full rounded-lg" />
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.card-flipped {
    transform: rotateY(180deg);
}
</style>
