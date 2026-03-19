<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
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
        trigger: 'bg-rose-100 text-rose-800 dark:bg-rose-900 dark:text-rose-200',
        crew: 'bg-violet-100 text-violet-800 dark:bg-violet-900 dark:text-violet-200',
    };
    return map[props.node.attrs.entityType] ?? 'bg-gray-100 text-gray-800';
});

const typeLabel = computed(() => {
    const map: Record<string, string> = {
        character: 'Character',
        keyword: 'Keyword',
        faction: 'Faction',
        upgrade: 'Upgrade',
        action: 'Action',
        ability: 'Ability',
        scheme: 'Scheme',
        strategy: 'Strategy',
        token: 'Token',
        marker: 'Marker',
        package: 'Package',
        trigger: 'Trigger',
        crew: 'Crew',
    };
    return map[props.node.attrs.entityType] ?? props.node.attrs.entityType;
});
</script>

<template>
    <NodeViewWrapper as="div" :class="['my-2', selected ? 'rounded-lg ring-2 ring-primary' : '']">
        <div class="flex items-center gap-2 rounded-lg border bg-card px-3 py-2">
            <Badge :class="['shrink-0 border-0 text-[10px]', typeColor]" variant="outline">{{ typeLabel }}</Badge>
            <span class="min-w-0 flex-1 truncate text-sm font-medium">{{ node.attrs.displayName }}</span>
        </div>
    </NodeViewWrapper>
</template>
