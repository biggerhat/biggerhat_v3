<script setup lang="ts">
import { cn } from '@/lib/utils';
import type { HTMLAttributes } from 'vue';

interface Props {
    title: string;
    class?: HTMLAttributes['class'];
    accentColor?: string;
}

const props = defineProps<Props>();
</script>

<template>
    <div :class="cn('mb-4 flex w-full bg-secondary', props.class, accentColor ? `border-b-4 ${accentColor}` : '')">
        <div class="container mx-auto items-center">
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
