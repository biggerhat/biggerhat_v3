<script setup lang="ts">
import { computed } from 'vue';

/**
 * A plain horizontal bar list: one row per item, bar length relative to the largest value (or `max`).
 * Built from divs so it follows the theme and reads fine to a screen reader as a list.
 */
const props = withDefaults(
    defineProps<{
        items: { label: string; value: number; note?: string | null }[];
        /** Fixes the full-bar value (for percentages pass 100); defaults to the largest item. */
        max?: number;
        unit?: string;
    }>(),
    { max: undefined, unit: '' },
);

const top = computed(() => props.max ?? Math.max(1, ...props.items.map((i) => i.value)));
const width = (v: number) => `${Math.max(v > 0 ? 3 : 0, Math.min(100, (v / top.value) * 100))}%`;
</script>

<template>
    <ul class="space-y-1.5 text-xs">
        <li
            v-for="item in items"
            :key="item.label"
            class="grid grid-cols-[minmax(0,8rem)_1fr_auto] items-center gap-2 sm:grid-cols-[minmax(0,11rem)_1fr_auto]"
        >
            <span class="truncate" :title="item.label">{{ item.label }}</span>
            <span class="h-2.5 overflow-hidden rounded-full bg-muted" aria-hidden="true">
                <span class="block h-full rounded-full bg-primary" :style="{ width: width(item.value) }" />
            </span>
            <span class="min-w-14 text-right tabular-nums"
                >{{ item.value }}{{ unit }}<span v-if="item.note" class="ml-1 text-muted-foreground">{{ item.note }}</span></span
            >
        </li>
    </ul>
</template>
