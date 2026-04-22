<script setup lang="ts">
import { SearchX } from 'lucide-vue-next';
import { markRaw, type Component } from 'vue';

interface Props {
    /** Icon displayed above the title. Defaults to a gentle "no results" glyph. */
    icon?: Component;
    title?: string;
    description?: string;
    /** Shrinks padding/type for inline placement inside tabs or narrow columns. */
    compact?: boolean;
}

// `withDefaults` treats a bare function default as a factory and invokes it —
// passing the lucide icon directly calls the functional component without a
// setup context and crashes ("Cannot destructure property 'slots' of undefined").
// Wrap in a factory and markRaw to keep the component identity intact.
withDefaults(defineProps<Props>(), {
    icon: () => markRaw(SearchX),
    title: 'No results found',
    description: 'Try adjusting your search or filter criteria.',
    compact: false,
});
</script>

<template>
    <div
        class="flex flex-col items-center justify-center rounded-lg border border-dashed text-center text-muted-foreground"
        :class="compact ? 'gap-1 px-4 py-6' : 'gap-2 px-6 py-12'"
    >
        <component :is="icon" :class="compact ? 'size-8 opacity-60' : 'size-12 opacity-50'" aria-hidden="true" />
        <p :class="compact ? 'text-sm font-semibold text-foreground' : 'mt-1 text-base font-semibold text-foreground'">{{ title }}</p>
        <p v-if="description" :class="compact ? 'max-w-xs text-xs' : 'max-w-sm text-sm'">{{ description }}</p>
        <div v-if="$slots.action" class="mt-3">
            <slot name="action" />
        </div>
        <slot />
    </div>
</template>
