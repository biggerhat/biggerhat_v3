<script setup lang="ts">
import { RotateCw } from 'lucide-vue-next';
import { ref } from 'vue';

defineProps<{
    image: string;
    name: string;
}>();

const flipped = ref(false);
</script>

<template>
    <!-- Flip button sits on the outer wrapper so it stays in the same
         screen position regardless of rotation. -->
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
