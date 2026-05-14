<script setup lang="ts">
import { RotateCw } from 'lucide-vue-next';
import { ref } from 'vue';

defineProps<{
    image: string;
    name: string;
}>();

// Local-only — every card flips independently. The image itself is captured
// with Side B already rotated 180°; flipping rotates the whole image so
// either side reads upright depending on which way the user prefers.
const flipped = ref(false);
</script>

<template>
    <!-- Outer wrapper holds the flip button so the button stays in the same
         screen position whether the card is rotated or not. -->
    <div class="relative">
        <div :class="['transition-transform duration-500', flipped ? 'rotate-180' : '']">
            <img :src="`/storage/${image}`" :alt="name" class="block w-full rounded-xl shadow-sm" loading="lazy" />
        </div>
        <button
            type="button"
            class="absolute right-2 top-2 inline-flex items-center justify-center rounded-full border bg-background/80 p-1.5 text-muted-foreground shadow-sm backdrop-blur transition-colors hover:bg-background hover:text-foreground"
            :title="flipped ? 'Flip card back' : 'Flip card to read Side B upright'"
            :aria-label="flipped ? 'Flip card back' : 'Flip card to read Side B upright'"
            @click="flipped = !flipped"
        >
            <RotateCw class="size-3.5" :class="flipped ? 'rotate-180' : ''" />
        </button>
    </div>
</template>
