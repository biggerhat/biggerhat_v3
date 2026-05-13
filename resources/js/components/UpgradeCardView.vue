<script setup lang="ts">
import CardFullscreenDialog from '@/components/CardFullscreenDialog.vue';
import Button from '@/components/ui/button/Button.vue';
import { router } from '@inertiajs/vue3';
import { Maximize2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const flipped = ref(false);
const fullscreenOpen = ref(false);

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

const frontImageUrl = computed(() => (props.upgrade.front_image ? '/storage/' + props.upgrade.front_image : null));
const backImageUrl = computed(() => (props.upgrade.back_image ? '/storage/' + props.upgrade.back_image : null));
</script>

<template>
    <div class="w-full rounded-lg text-center transition-shadow duration-300 hover:shadow-lg hover:shadow-black/20">
        <p class="mb-1 text-xs text-muted-foreground">{{ upgrade.name }}</p>
        <div class="relative mx-auto w-fit">
            <div
                v-if="upgrade.back_image"
                @click="flip"
                @keydown.enter="flip"
                role="button"
                tabindex="0"
                class="cursor-pointer"
                style="perspective: 1000px"
            >
                <div
                    class="card-flip-inner relative w-full"
                    :class="{ 'card-flipped': flipped }"
                    style="transition: transform 0.5s; transform-style: preserve-3d"
                >
                    <div class="card-face" style="backface-visibility: hidden">
                        <img
                            :src="'/storage/' + upgrade.front_image"
                            :alt="upgrade.name"
                            loading="lazy"
                            decoding="async"
                            class="aspect-[550/950] h-full w-full rounded-lg object-cover"
                        />
                    </div>
                    <div class="card-face absolute inset-0" style="backface-visibility: hidden; transform: rotateY(180deg)">
                        <img
                            :src="'/storage/' + upgrade.back_image"
                            :alt="upgrade.name"
                            loading="lazy"
                            decoding="async"
                            class="aspect-[550/950] h-full w-full rounded-lg object-cover"
                        />
                    </div>
                </div>
            </div>
            <div v-else>
                <img
                    :src="'/storage/' + upgrade.front_image"
                    :alt="upgrade.name"
                    loading="lazy"
                    decoding="async"
                    class="aspect-[550/950] h-full w-full rounded-lg object-cover"
                />
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
            <Button @click="router.get(route('upgrades.view', { upgrade: props.upgrade.slug }))" size="sm" variant="link"> View Upgrade Page </Button>
        </div>

        <CardFullscreenDialog
            v-model:open="fullscreenOpen"
            :src="flipped && backImageUrl ? backImageUrl : frontImageUrl"
            :back-src="flipped && backImageUrl ? frontImageUrl : backImageUrl"
            :title="upgrade.name"
        />
    </div>
</template>

<style scoped>
.card-flipped {
    transform: rotateY(180deg);
}
</style>
