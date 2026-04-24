<script setup lang="ts">
import CardImage from '@/components/TOS/CardImage.vue';
import { Maximize2 } from 'lucide-vue-next';
import type { Component } from 'vue';

interface Props {
    frontImage?: string | null;
    backImage?: string | null;
    frontAlt?: string;
    backAlt?: string;
    allegianceSlug?: string | null;
    placeholderIcon?: Component;
    aspectClass?: string;
    /** When true, render only the front face (no flip behaviour, no back). */
    singleSide?: boolean;
    /** Show the fullscreen-trigger button (consumer wires fullscreen separately). */
    enableFullscreen?: boolean;
}

withDefaults(defineProps<Props>(), {
    frontImage: null,
    backImage: null,
    frontAlt: '',
    backAlt: '',
    allegianceSlug: null,
    aspectClass: 'aspect-[5/7]',
    singleSide: false,
    enableFullscreen: false,
});

/**
 * Two-way binding for the flipped state. UnitCard syncs this to its
 * Standard/Glory tab so the click-image and click-tab interactions stay in
 * sync — physical card-flip mental model.
 */
const flipped = defineModel<boolean>('flipped', { default: false });

const emit = defineEmits<{
    fullscreen: []
}>();

function toggle() {
    flipped.value = !flipped.value;
}

function handleKey(e: KeyboardEvent) {
    if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        toggle();
    }
}
</script>

<template>
    <div class="relative w-full">
        <div
            v-if="!singleSide"
            class="cursor-pointer"
            role="button"
            :tabindex="0"
            :aria-pressed="flipped"
            :aria-label="flipped ? 'Showing back; click to flip to front' : 'Showing front; click to flip to back'"
            style="perspective: 1000px"
            @click="toggle"
            @keydown="handleKey"
        >
            <div
                class="relative w-full transition-transform duration-500"
                :class="{ 'tos-flipped': flipped }"
                style="transform-style: preserve-3d"
            >
                <div class="tos-face" style="backface-visibility: hidden">
                    <CardImage
                        :src="frontImage"
                        :alt="frontAlt"
                        :allegiance-slug="allegianceSlug"
                        :placeholder-icon="placeholderIcon"
                        :aspect-class="aspectClass"
                    />
                </div>
                <div class="tos-face absolute inset-0" style="backface-visibility: hidden; transform: rotateY(180deg)">
                    <CardImage
                        :src="backImage"
                        :alt="backAlt"
                        :allegiance-slug="allegianceSlug"
                        :placeholder-icon="placeholderIcon"
                        :aspect-class="aspectClass"
                    />
                </div>
            </div>
        </div>

        <CardImage
            v-else
            :src="frontImage"
            :alt="frontAlt"
            :allegiance-slug="allegianceSlug"
            :placeholder-icon="placeholderIcon"
            :aspect-class="aspectClass"
        />

        <button
            v-if="enableFullscreen"
            type="button"
            aria-label="View fullscreen"
            class="absolute bottom-2 right-2 rounded-full bg-black/50 p-1.5 text-white/80 backdrop-blur-sm transition-all hover:bg-black/75 hover:text-white"
            @click.stop="emit('fullscreen')"
        >
            <Maximize2 class="size-3.5" />
        </button>
    </div>
</template>

<style scoped>
.tos-flipped {
    transform: rotateY(180deg);
}
</style>
