<script setup lang="ts">
import { computed, HTMLAttributes } from 'vue';

import GameIcon from '@/components/GameIcon.vue';

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

// Map tag names from {{tag}} to GameIcon type keys
const tagToIconType: Record<string, string> = {
    crow: 'crow',
    crows: 'crow',
    mask: 'mask',
    masks: 'mask',
    ram: 'ram',
    rams: 'ram',
    tome: 'tome',
    tomes: 'tome',
    soulstone: 'soulstone',
    soulstones: 'soulstone',
    stone: 'soulstone',
    melee: 'melee',
    missile: 'missile',
    magic: 'magic',
    pulse: 'pulse',
    positive: 'positive',
    negative: 'negative',
    fortitude: 'physical_defense',
    warding: 'magical_defense',
    unusual: 'unusual_defense',
    unusualdefense: 'unusual_defense',
    signatureaction: 'signature_action',
    '+': 'positive',
    '-': 'negative',
};

type Segment = { type: 'text'; value: string } | { type: 'icon'; iconType: string };

const segments = computed((): Segment[] => {
    if (!props.text) return [];

    let source = props.text;
    if (props.maxLength > 0 && source.length > props.maxLength) {
        source = source.substring(0, props.maxLength) + '...';
    }

    const parts: Segment[] = [];
    const regex = /\{\{(\w+|[+-])\}\}/g;
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
        <GameIcon v-else :type="segment.iconType" :class-name="iconClass" />
    </template>
</template>
