<script setup lang="ts">
import { type TextSize, useAppearance } from '@/composables/useAppearance';

const { textSize, updateTextSize } = useAppearance();

const options: { value: TextSize; label: string; sample: string }[] = [
    { value: 'normal', label: 'Default', sample: 'text-sm' },
    { value: 'large', label: 'Large', sample: 'text-base' },
    { value: 'xlarge', label: 'Extra large', sample: 'text-xl' },
];
</script>

<template>
    <div class="grid grid-cols-3 gap-2" role="group" aria-label="Text size">
        <button
            v-for="option in options"
            :key="option.value"
            type="button"
            :aria-pressed="textSize === option.value"
            :class="[
                'flex flex-col items-center gap-1 rounded-md border px-3 py-3 text-center transition-colors',
                textSize === option.value
                    ? 'border-primary bg-accent text-foreground'
                    : 'border-border text-muted-foreground hover:bg-accent/40 hover:text-foreground',
            ]"
            @click="updateTextSize(option.value)"
        >
            <span :class="[option.sample, 'font-semibold leading-none']" aria-hidden="true">Aa</span>
            <span class="text-xs">{{ option.label }}</span>
        </button>
    </div>
</template>
