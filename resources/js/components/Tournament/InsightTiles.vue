<script setup lang="ts">
/** A row of headline numbers. `value` is shown as given; pass '–' for "not enough data". */
defineProps<{ tiles: { label: string; value: string | number; hint?: string }[] }>();
</script>

<template>
    <dl class="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-6">
        <div v-for="t in tiles" :key="t.label" class="rounded-md border bg-muted/30 p-2.5 text-center">
            <dt class="text-2xs uppercase text-muted-foreground">{{ t.label }}</dt>
            <dd class="text-lg font-bold tabular-nums">{{ t.value }}</dd>
            <p v-if="t.hint" class="text-2xs text-muted-foreground">{{ t.hint }}</p>
        </div>
    </dl>
</template>
