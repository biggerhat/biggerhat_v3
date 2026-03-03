<script setup lang="ts">
import Button from '@/components/ui/button/Button.vue';
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';

const flipped = ref(false);
const flip = () => {
    flipped.value = !flipped.value;
};

const props = defineProps({
    miniature: {
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
    characterSlug: {
        type: String,
        required: false,
        default() {
            return '';
        },
    },
});
</script>

<template>
    <div class="w-full rounded-lg text-center transition-all duration-300 hover:scale-[1.03] hover:shadow-lg hover:shadow-black/20">
        <div @click="flip" class="card-flip-container mx-1 cursor-pointer" style="perspective: 1000px">
            <div
                class="card-flip-inner relative w-full"
                :class="{ 'card-flipped': flipped }"
                style="transition: transform 0.5s; transform-style: preserve-3d"
            >
                <div class="card-face" style="backface-visibility: hidden">
                    <img :src="'/storage/' + miniature.front_image" :alt="miniature.display_name" class="h-full w-full rounded-lg" />
                </div>
                <div class="card-face absolute inset-0" style="backface-visibility: hidden; transform: rotateY(180deg)">
                    <img :src="'/storage/' + miniature.back_image" :alt="miniature.display_name" class="h-full w-full rounded-lg" />
                </div>
            </div>
        </div>
        <div class="mt-1" v-if="props.showLink === true">
            <Button
                @click="
                    router.get(
                        route('characters.view', { character: props.characterSlug, miniature: props.miniature.id, slug: props.miniature.slug }),
                    )
                "
                size="sm"
                variant="link"
            >
                View Character Page
            </Button>
        </div>
    </div>
</template>

<style scoped>
.card-flipped {
    transform: rotateY(180deg);
}
</style>
