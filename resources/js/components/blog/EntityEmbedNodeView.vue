<script setup lang="ts">
import AbilityCard from '@/components/AbilityCard.vue';
import ActionCard from '@/components/ActionCard.vue';
import CharacterCardView from '@/components/CharacterCardView.vue';
import UpgradeFlipCard from '@/components/UpgradeFlipCard.vue';
import { Badge } from '@/components/ui/badge';
import { NodeViewWrapper } from '@tiptap/vue-3';
import axios from 'axios';
import { Loader2 } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';

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

const entityData = ref<Record<string, unknown> | null>(null);
const loading = ref(true);

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
    };
    return map[props.node.attrs.entityType] ?? props.node.attrs.entityType;
});

onMounted(async () => {
    try {
        const response = await axios.get(route('api.blog.entity-show', { type: props.node.attrs.entityType, slug: props.node.attrs.entitySlug }));
        entityData.value = response.data;
    } catch (err) {
        console.error('Entity embed load failed:', err);
    } finally {
        loading.value = false;
    }
});
</script>

<template>
    <NodeViewWrapper as="div" :class="['my-4', selected ? 'rounded-lg ring-2 ring-primary' : '']">
        <div v-if="loading" class="flex items-center justify-center rounded-lg border p-4 py-8">
            <Loader2 class="h-6 w-6 animate-spin text-muted-foreground" />
        </div>
        <template v-else>
            <!-- Character -->
            <div v-if="node.attrs.entityType === 'character' && entityData && (entityData.miniature as any)" class="flex justify-center">
                <div class="w-72">
                    <CharacterCardView :miniature="entityData.miniature as any" :character-slug="node.attrs.entitySlug" :show-link="false" />
                </div>
            </div>

            <!-- Action -->
            <ActionCard v-else-if="node.attrs.entityType === 'action' && entityData" :action="entityData as any" />

            <!-- Ability -->
            <AbilityCard v-else-if="node.attrs.entityType === 'ability' && entityData" :ability="entityData as any" />

            <!-- Upgrade -->
            <div
                v-else-if="node.attrs.entityType === 'upgrade' && entityData && (entityData.front_image || entityData.back_image)"
                class="flex justify-center"
            >
                <div class="w-72">
                    <UpgradeFlipCard
                        :front-image="entityData.front_image as string"
                        :back-image="entityData.back_image as string"
                        :alt-text="(entityData.name ?? node.attrs.displayName) as string"
                    />
                </div>
            </div>

            <!-- Scheme / Strategy with image -->
            <div
                v-else-if="(node.attrs.entityType === 'scheme' || node.attrs.entityType === 'strategy') && entityData && entityData.image"
                class="flex justify-center"
            >
                <div class="w-72">
                    <img :src="entityData.image as string" :alt="(entityData.name ?? node.attrs.displayName) as string" class="w-full rounded-lg" />
                </div>
            </div>

            <!-- Other types -->
            <div v-else class="flex items-center gap-3 rounded-lg border p-4">
                <Badge :class="['border-0 text-xs', typeColor]" variant="outline">{{ typeLabel }}</Badge>
                <span class="font-semibold">{{ entityData?.name ?? node.attrs.displayName }}</span>
            </div>
        </template>
    </NodeViewWrapper>
</template>
