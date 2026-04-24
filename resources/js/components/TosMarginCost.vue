<script setup lang="ts">
import TosIcon from '@/components/TosIcon.vue';
import { computed, HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

interface Props {
    cost: number | null | undefined;
    iconClass?: HTMLAttributes['class'];
}

const props = withDefaults(defineProps<Props>(), {
    iconClass: 'h-4 inline-block align-text-bottom',
});

// The TOS symbol font only ships margin glyphs for 3, 4, 5 — the canonical
// rulebook values. Anything else falls back to plain text.
const iconType = computed(() => {
    if (props.cost === 3 || props.cost === 4 || props.cost === 5) return `margin_${props.cost}`;
    return null;
});
</script>

<template>
    <TosIcon v-if="iconType" :type="iconType" :class-name="iconClass" />
    <span v-else-if="cost != null">[{{ cost }}M]</span>
</template>
