<script setup lang="ts">
import Button from '@/components/ui/button/Button.vue';
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';

const flipped = ref(false);
const flip = () => {
    flipped.value = !flipped.value;
};

const props = defineProps({
    upgrade: {
        type: [Object, Array],
        required: false,
        default() {
            return {};
        },
    },
    showLink: {
        type: [Boolean],
        required: false,
        default() {
            return true;
        },
    },
});
</script>

<template>
    <div class="w-full rounded-lg text-center transition-all duration-300 hover:scale-[1.03] hover:shadow-lg hover:shadow-black/20">
        <div v-if="upgrade.back_image" @click="flip" class="card-flip-container mx-1 cursor-pointer" style="perspective: 1000px">
            <div
                class="card-flip-inner relative w-full"
                :class="{ 'card-flipped': flipped }"
                style="transition: transform 0.5s; transform-style: preserve-3d"
            >
                <div class="card-face" style="backface-visibility: hidden">
                    <img :src="'/storage/' + upgrade.front_image" :alt="upgrade.name" class="h-full w-full rounded-lg" />
                </div>
                <div class="card-face absolute inset-0" style="backface-visibility: hidden; transform: rotateY(180deg)">
                    <img :src="'/storage/' + upgrade.back_image" :alt="upgrade.name" class="h-full w-full rounded-lg" />
                </div>
            </div>
        </div>
        <div v-else class="mx-1">
            <img :src="'/storage/' + upgrade.front_image" :alt="upgrade.name" class="h-full w-full rounded-lg" />
        </div>
        <div class="mt-1" v-if="props.showLink === true">
            <Button @click="router.get(route('upgrades.view', { upgrade: props.upgrade.slug }))" size="sm" variant="link"> View Upgrade </Button>
        </div>
    </div>
</template>

<style scoped>
.card-flipped {
    transform: rotateY(180deg);
}
</style>
