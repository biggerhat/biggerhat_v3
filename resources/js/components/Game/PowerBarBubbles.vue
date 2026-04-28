<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
    /** Total bubbles (max value of the power bar). */
    max: number;
    /** Currently filled bubbles. */
    current: number;
    /** Disable interaction (observers, opponents in non-solo games, etc). */
    readonly?: boolean;
    /** Tighter spacing for nested rows under upgrade names. */
    compact?: boolean;
}>();

const emit = defineEmits<{
    (e: 'update', value: number): void;
}>();

const safeMax = computed(() => Math.max(0, Math.floor(props.max ?? 0)));
const safeCurrent = computed(() => Math.min(safeMax.value, Math.max(0, Math.floor(props.current ?? 0))));

// Click-to-set: tapping the Nth bubble sets current to N. Tapping the
// already-active topmost bubble decrements by one — same UX as health bubbles.
const onClick = (index: number) => {
    if (props.readonly) return;
    const nth = index + 1;
    if (nth === safeCurrent.value) {
        emit('update', nth - 1);
    } else {
        emit('update', nth);
    }
};
</script>

<template>
    <div v-if="safeMax > 0" class="flex items-center" :class="compact ? 'gap-0.5' : 'gap-1'" role="group" aria-label="Power bar">
        <button
            v-for="i in safeMax"
            :key="i"
            type="button"
            class="rounded-full border transition-colors"
            :class="[
                compact ? 'size-2.5' : 'size-3.5',
                i <= safeCurrent
                    ? 'border-amber-500 bg-amber-500'
                    : 'border-amber-500/40 bg-transparent',
                readonly ? 'cursor-default opacity-80' : 'cursor-pointer hover:border-amber-400',
            ]"
            :disabled="readonly"
            :aria-label="i <= safeCurrent ? `Power ${i} of ${safeMax} (filled)` : `Power ${i} of ${safeMax} (empty)`"
            :aria-pressed="i <= safeCurrent"
            @click.stop="onClick(i - 1)"
        />
        <span
            class="ml-1 font-mono tabular-nums text-amber-300/90"
            :class="compact ? 'text-[10px]' : 'text-xs'"
        >{{ safeCurrent }}/{{ safeMax }}</span>
    </div>
</template>
