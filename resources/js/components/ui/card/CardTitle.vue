<script setup lang="ts">
import { cn } from '@/lib/utils';
import type { HTMLAttributes } from 'vue';

const props = withDefaults(
    defineProps<{
        class?: HTMLAttributes['class'];
        /** Heading level to render; pages whose card title is the page's main heading pass `h1`. */
        as?: 'h1' | 'h2' | 'h3' | 'h4';
    }>(),
    { as: 'h3' },
);
</script>

<template>
    <component :is="props.as" :class="cn('text-2xl font-semibold leading-none tracking-tight', props.class)">
        <slot />
    </component>
</template>
