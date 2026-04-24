<script setup lang="ts">
import AllegianceLogo from '@/components/AllegianceLogo.vue';
import { type SharedData } from '@/types';
import { usePage } from '@inertiajs/vue3';
import type { Component } from 'vue';
import { computed } from 'vue';

interface Props {
    /** Card art path. Absolute URLs and `/`-prefixed paths used as-is; bare paths get `/storage/` prefix. */
    src?: string | null;
    alt?: string;
    /** Allegiance slug used by the placeholder background + logo when no image is set. */
    allegianceSlug?: string | null;
    /** Lucide icon shown center of the placeholder when no image AND no allegiance. */
    placeholderIcon?: Component;
    /** Aspect ratio. Defaults to TOS card proportions. */
    aspectClass?: string;
    /** Optional rounded class override. */
    roundedClass?: string;
}

const props = withDefaults(defineProps<Props>(), {
    src: null,
    alt: '',
    allegianceSlug: null,
    aspectClass: 'aspect-[5/7]',
    roundedClass: 'rounded-lg',
});

const page = usePage<SharedData>();

const resolvedSrc = computed(() => {
    if (!props.src) return null;
    if (props.src.startsWith('http') || props.src.startsWith('/')) return props.src;
    return `/storage/${props.src}`;
});

const allegianceColor = computed(() => {
    const slug = props.allegianceSlug;
    if (!slug) return null;
    return page.props.tos_allegiance_info?.[slug]?.color ?? null;
});
</script>

<template>
    <div :class="[aspectClass, roundedClass, 'relative overflow-hidden bg-muted/40']">
        <img
            v-if="resolvedSrc"
            :src="resolvedSrc"
            :alt="alt"
            loading="lazy"
            decoding="async"
            class="size-full object-cover"
        />
        <div
            v-else
            :class="[
                'flex size-full items-center justify-center',
                allegianceColor ? `bg-${allegianceColor}/20` : 'bg-primary/5',
            ]"
        >
            <AllegianceLogo
                v-if="allegianceSlug"
                :allegiance="allegianceSlug"
                class-name="size-1/3 opacity-60"
            />
            <component
                v-else-if="placeholderIcon"
                :is="placeholderIcon"
                class="size-1/4 text-muted-foreground/50"
                aria-hidden="true"
            />
        </div>
    </div>
</template>
