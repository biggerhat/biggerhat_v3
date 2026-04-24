<script setup lang="ts">
import TosIcon from '@/components/TosIcon.vue';
import { computed, HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

interface Props {
    // Raw TOS suit string as stored on Action.av_suits and Trigger.suits —
    // each character maps to one suit glyph (e.g. "R" → Ram, "RM" → Ram+Mask).
    suits: string | null | undefined;
    iconClass?: HTMLAttributes['class'];
}

const props = withDefaults(defineProps<Props>(), {
    iconClass: 'h-4 inline-block align-text-bottom',
});

// Canonical TOS/Malifaux single-letter suit abbreviations.
const charToType: Record<string, string> = {
    R: 'ram',
    M: 'mask',
    C: 'crow',
    T: 'tome',
};

const parts = computed(() =>
    [...(props.suits ?? '')].map((ch) => ({
        char: ch,
        type: charToType[ch.toUpperCase()] ?? null,
    })),
);
</script>

<template>
    <template v-for="(p, i) in parts" :key="i">
        <TosIcon v-if="p.type" :type="p.type" :class-name="iconClass" />
        <span v-else>{{ p.char }}</span>
    </template>
</template>
