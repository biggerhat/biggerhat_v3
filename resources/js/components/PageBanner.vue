<script setup lang="ts">
import { cn } from '@/lib/utils';
import type { HTMLAttributes } from 'vue';

interface Props {
    title: string;
    class?: HTMLAttributes['class'];
    accentColor?: string;
    /** Faction/colour token name (e.g. `bayou`) — washes the banner with a soft left-to-right tint. */
    tint?: string;
}

const props = defineProps<Props>();
</script>

<template>
    <div
        :class="cn('mb-4 flex w-full bg-secondary', props.class, accentColor ? `border-b-4 ${accentColor}` : '')"
        :style="
            tint ? { backgroundImage: `linear-gradient(90deg, hsl(var(--${tint}) / 0.42), hsl(var(--${tint}) / 0.1) 60%, transparent)` } : undefined
        "
    >
        <div class="container mx-auto items-center px-0 sm:px-2">
            <div class="flex flex-wrap items-center justify-between gap-y-1">
                <div class="flex w-full py-2 sm:w-auto md:py-4">
                    <slot name="logo" />
                    <!-- Always stack title above subtitle — subtitle content is
                         typically a descriptive sentence, not a compact badge,
                         so sitting it beside the title on narrow screens
                         squeezes both into an unreadable wrap. -->
                    <div class="flex w-full flex-col" id="page-banner">
                        <h1 class="text-balance p-2 text-lg font-bold tracking-tight sm:text-xl md:text-2xl">{{ title }}</h1>
                        <slot name="subtitle" />
                    </div>
                </div>
                <slot name="actions" />
            </div>
        </div>
    </div>
</template>
