<script setup lang="ts">
import GameIcon from '@/components/GameIcon.vue';
import { NodeViewWrapper } from '@tiptap/vue-3';

defineProps<{
    node: {
        attrs: {
            iconType: string;
        };
    };
    selected: boolean;
}>();
</script>

<template>
    <NodeViewWrapper as="span" class="inline">
        <span :class="['inline-flex items-center', selected ? 'rounded ring-2 ring-primary' : '']">
            <GameIcon :type="node.attrs.iconType" class-name="h-5 inline-block align-text-bottom" />
        </span>
    </NodeViewWrapper>
</template>
