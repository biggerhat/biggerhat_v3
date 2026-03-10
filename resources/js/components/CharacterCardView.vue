<script setup lang="ts">
import Button from '@/components/ui/button/Button.vue';
import { Dialog, DialogContent, DialogTitle } from '@/components/ui/dialog';
import { router } from '@inertiajs/vue3';
import { Maximize2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const flipped = ref(false);
const fullscreenOpen = ref(false);

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

const currentImage = computed(() => {
    const path = flipped.value ? props.miniature.back_image : props.miniature.front_image;
    return '/storage/' + path;
});

const currentLabel = computed(() => {
    return props.miniature.display_name + (flipped.value ? ' (back)' : ' (front)');
});
</script>

<template>
    <div class="w-full rounded-lg text-center transition-shadow duration-300 hover:shadow-lg hover:shadow-black/20">
        <p class="mb-1 text-xs text-muted-foreground">{{ miniature.display_name }}</p>
        <div class="relative mx-auto w-fit">
            <div @click="flip" class="cursor-pointer" style="perspective: 1000px">
                <div
                    class="card-flip-inner relative w-full"
                    :class="{ 'card-flipped': flipped }"
                    style="transition: transform 0.5s; transform-style: preserve-3d"
                >
                    <div class="card-face" style="backface-visibility: hidden">
                        <img
                            :src="'/storage/' + miniature.front_image"
                            :alt="miniature.display_name"
                            loading="lazy"
                            decoding="async"
                            class="h-full w-full rounded-lg"
                        />
                    </div>
                    <div class="card-face absolute inset-0" style="backface-visibility: hidden; transform: rotateY(180deg)">
                        <img
                            :src="'/storage/' + miniature.back_image"
                            :alt="miniature.display_name"
                            loading="lazy"
                            decoding="async"
                            class="h-full w-full rounded-lg"
                        />
                    </div>
                </div>
            </div>
            <button
                @click.stop="fullscreenOpen = true"
                class="absolute bottom-3 right-2 rounded-full bg-black/40 p-1.5 text-white/70 backdrop-blur-sm transition-all hover:bg-black/70 hover:text-white"
                title="View fullscreen"
            >
                <Maximize2 class="size-3.5" />
            </button>
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

        <Dialog v-model:open="fullscreenOpen">
            <DialogContent class="fullscreen-card-dialog max-h-[95dvh] max-w-[95vw] border-none bg-black/95 p-2 sm:max-w-fit sm:p-4">
                <DialogTitle class="sr-only">{{ currentLabel }}</DialogTitle>
                <div class="flex items-center justify-center">
                    <img :src="currentImage" :alt="currentLabel" class="max-h-[90dvh] w-auto rounded-lg object-contain" />
                </div>
            </DialogContent>
        </Dialog>
    </div>
</template>

<style scoped>
.card-flipped {
    transform: rotateY(180deg);
}
</style>

<style>
.fullscreen-card-dialog > button {
    background-color: rgba(0, 0, 0, 0.6);
    backdrop-filter: blur(4px);
    color: white;
    opacity: 1;
    border-radius: 9999px;
    padding: 0.375rem;
}

.fullscreen-card-dialog > button:hover {
    background-color: rgba(0, 0, 0, 0.85);
}
</style>
