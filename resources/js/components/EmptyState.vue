<script setup lang="ts">
import { SearchX } from 'lucide-vue-next';

interface Props {
    title?: string;
    description?: string;
}

withDefaults(defineProps<Props>(), {
    title: 'No results found',
    description: 'Try adjusting your search or filter criteria.',
});
</script>

<template>
    <div class="flex flex-col items-center justify-center py-12 text-muted-foreground">
        <SearchX class="mb-3 size-10 opacity-50" />
        <p class="text-sm font-medium">{{ title }}</p>
        <p class="mt-1 text-xs">{{ description }}</p>
        <slot />
    </div>
</template>
