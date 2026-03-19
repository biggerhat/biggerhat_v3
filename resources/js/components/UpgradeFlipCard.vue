<script setup lang="ts">
import Button from '@/components/ui/button/Button.vue';
import { Dialog, DialogContent, DialogTitle } from '@/components/ui/dialog';
import { router } from '@inertiajs/vue3';
import { Maximize2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps<{
    frontImage: string;
    backImage?: string | null;
    altText?: string;
    upgradeSlug?: string;
    showLink?: boolean;
}>();

const flipped = ref(false);
const fullscreenOpen = ref(false);

const currentImage = computed(() => {
    if (!props.backImage || !flipped.value) {
        return '/storage/' + props.frontImage;
    }
    return '/storage/' + props.backImage;
});

const currentLabel = computed(() => {
    const name = props.altText ?? 'Card';
    const side = props.backImage && flipped.value ? ' (back)' : ' (front)';
    return name + side;
});
</script>

<template>
    <div class="text-center transition-shadow duration-300 hover:shadow-lg hover:shadow-black/20">
        <div class="relative mx-auto w-fit">
            <div @click="flipped = !flipped" class="cursor-pointer" style="perspective: 1000px">
                <div class="relative w-full" :class="{ 'card-flipped': flipped }" style="transition: transform 0.5s; transform-style: preserve-3d">
                    <div style="backface-visibility: hidden">
                        <img
                            :src="'/storage/' + frontImage"
                            :alt="(altText ?? 'Card') + ' (front)'"
                            loading="lazy"
                            decoding="async"
                            class="aspect-[550/950] h-full w-full rounded-lg object-cover"
                        />
                    </div>
                    <div v-if="backImage" class="absolute inset-0" style="backface-visibility: hidden; transform: rotateY(180deg)">
                        <img
                            :src="'/storage/' + backImage"
                            :alt="(altText ?? 'Card') + ' (back)'"
                            loading="lazy"
                            decoding="async"
                            class="aspect-[550/950] h-full w-full rounded-lg object-cover"
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

        <div class="mt-1" v-if="props.showLink && props.upgradeSlug">
            <Button @click="router.get(route('upgrades.view', { upgrade: props.upgradeSlug }))" size="sm" variant="link"> View Upgrade Page </Button>
        </div>

        <Dialog v-model:open="fullscreenOpen">
            <DialogContent class="max-h-[95dvh] max-w-[95vw] border-none bg-black/95 p-2 sm:max-w-fit sm:p-4">
                <DialogTitle class="sr-only">{{ currentLabel }}</DialogTitle>
                <div class="flex items-center justify-center">
                    <img :src="currentImage" :alt="currentLabel" loading="lazy" decoding="async" class="max-h-[90dvh] w-auto rounded-lg object-contain" />
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
