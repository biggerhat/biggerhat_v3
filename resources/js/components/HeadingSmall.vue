<script setup lang="ts">
interface Props {
    title: string;
    description?: string;
    /** Heading level; the first heading on a page without a banner passes `h1`. */
    as?: 'h1' | 'h2' | 'h3';
}

withDefaults(defineProps<Props>(), { as: 'h2' });
</script>

<template>
    <header>
        <component :is="as" class="mb-0.5 text-base font-medium">{{ title }}</component>
        <p v-if="description" class="text-sm text-muted-foreground">
            {{ description }}
        </p>
    </header>
</template>
