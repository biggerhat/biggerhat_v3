<script setup lang="ts">
import { cn } from '@/lib/utils';
import type { HTMLAttributes } from 'vue';

withDefaults(
    defineProps<{
        /** Heading level for this label's position in the page's document outline. */
        as?: 'h2' | 'h3' | 'h4';
        class?: HTMLAttributes['class'];
    }>(),
    { as: 'h2' },
);
</script>

<template>
    <component :is="as" :class="cn('text-xs font-semibold uppercase tracking-wider text-muted-foreground', $props.class)">
        <slot />
    </component>
</template>
