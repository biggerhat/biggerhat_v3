<script setup lang="ts">
import { NodeViewWrapper } from '@tiptap/vue-3';
import { computed } from 'vue';

const props = defineProps<{
    node: {
        attrs: {
            entityType: string;
            entityId: string | number;
            entitySlug: string;
            displayName: string;
        };
    };
    selected: boolean;
}>();

const typeAbbr = computed(() => {
    const map: Record<string, string> = {
        character: 'C',
        keyword: 'K',
        faction: 'F',
        upgrade: 'U',
        action: 'A',
        ability: 'Ab',
        scheme: 'Sc',
        strategy: 'St',
        token: 'Tk',
        marker: 'Mk',
        package: 'Pk',
    };
    return map[props.node.attrs.entityType] ?? '?';
});

const typeColor = computed(() => {
    const map: Record<string, string> = {
        character: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
        keyword: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
        faction: 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200',
        upgrade: 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200',
        action: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
        ability: 'bg-teal-100 text-teal-800 dark:bg-teal-900 dark:text-teal-200',
        scheme: 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200',
        strategy: 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-200',
        token: 'bg-cyan-100 text-cyan-800 dark:bg-cyan-900 dark:text-cyan-200',
        marker: 'bg-pink-100 text-pink-800 dark:bg-pink-900 dark:text-pink-200',
        package: 'bg-lime-100 text-lime-800 dark:bg-lime-900 dark:text-lime-200',
    };
    return map[props.node.attrs.entityType] ?? 'bg-gray-100 text-gray-800';
});
</script>

<template>
    <NodeViewWrapper as="span" class="inline">
        <span
            :class="[
                'inline-flex cursor-default items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium',
                typeColor,
                selected ? 'ring-2 ring-primary' : '',
            ]"
        >
            <span class="font-bold">{{ typeAbbr }}</span>
            <span>{{ node.attrs.displayName }}</span>
        </span>
    </NodeViewWrapper>
</template>
