<script setup lang="ts">
import { computed, HTMLAttributes } from 'vue';

import TosIcon from '@/components/TosIcon.vue';

defineOptions({
    inheritAttrs: false,
});

interface Props {
    text: string;
    iconClass?: HTMLAttributes['class'];
    maxLength?: number;
}

const props = withDefaults(defineProps<Props>(), {
    iconClass: 'h-4 inline-block align-text-bottom',
    maxLength: 0,
});

// Rulebook tokens → TosIcon type keys. Aliases reflect how the text appears
// on cards (plural suits, abbreviated margins).
const tagToIconType: Record<string, string> = {
    crow: 'crow',
    crows: 'crow',
    mask: 'mask',
    masks: 'mask',
    ram: 'ram',
    rams: 'ram',
    tome: 'tome',
    tomes: 'tome',
    melee: 'melee',
    missile: 'missile',
    pulse: 'pulse',
    aura: 'aura',
    magic: 'magic',
    morale: 'morale',
    turncard: 'turncard',
    margin3: 'margin_3',
    margin4: 'margin_4',
    margin5: 'margin_5',
    '3margin': 'margin_3',
    '4margin': 'margin_4',
    '5margin': 'margin_5',
};

type Segment = { type: 'text'; value: string } | { type: 'icon'; iconType: string };

const segments = computed((): Segment[] => {
    if (!props.text) return [];

    let source = props.text;
    if (props.maxLength > 0 && source.length > props.maxLength) {
        source = source.substring(0, props.maxLength) + '...';
    }

    const parts: Segment[] = [];
    // Match {{tag}} where tag is letters/digits (margin3, 3margin, crow, morale, …).
    const regex = /\{\{([A-Za-z0-9]+)\}\}/g;
    let lastIndex = 0;
    let match: RegExpExecArray | null;

    while ((match = regex.exec(source)) !== null) {
        if (match.index > lastIndex) {
            parts.push({ type: 'text', value: source.substring(lastIndex, match.index) });
        }

        const tag = match[1].toLowerCase();
        const iconType = tagToIconType[tag];
        if (iconType) {
            parts.push({ type: 'icon', iconType });
        } else {
            parts.push({ type: 'text', value: match[0] });
        }

        lastIndex = match.index + match[0].length;
    }

    if (lastIndex < source.length) {
        parts.push({ type: 'text', value: source.substring(lastIndex) });
    }

    return parts;
});
</script>

<template>
    <template v-for="(segment, i) in segments" :key="i">
        <template v-if="segment.type === 'text'">{{ segment.value }}</template>
        <TosIcon v-else :type="segment.iconType" :class-name="iconClass" />
    </template>
</template>
