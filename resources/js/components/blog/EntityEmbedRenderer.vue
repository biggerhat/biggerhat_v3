<script setup lang="ts">
import AbilityCard from '@/components/AbilityCard.vue';
import ActionCard from '@/components/ActionCard.vue';
import CharacterCardView from '@/components/CharacterCardView.vue';
import UpgradeFlipCard from '@/components/UpgradeFlipCard.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import { Loader2 } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';

const props = defineProps<{
    attrs: Record<string, unknown>;
}>();

const entityType = computed(() => props.attrs.entityType as string);
const entitySlug = computed(() => props.attrs.entitySlug as string);
const displayName = computed(() => props.attrs.displayName as string);

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
    };
    return map[entityType.value] ?? 'bg-gray-100 text-gray-800';
});

const typeLabel = computed(() => {
    const map: Record<string, string> = {
        character: 'Character',
        keyword: 'Keyword',
        faction: 'Faction',
        upgrade: 'Upgrade',
        action: 'Action',
        ability: 'Ability',
    };
    return map[entityType.value] ?? entityType.value;
});

const navigateToEntity = () => {
    const link = entityData.value?.link as string | null;
    if (link) {
        router.get(link);
    }
};

onMounted(async () => {
    try {
        const response = await axios.get(route('api.blog.entity-show', { type: entityType.value, slug: entitySlug.value }));
        entityData.value = response.data;
    } catch (err) {
        console.error('Entity embed load failed:', err);
    } finally {
        loading.value = false;
    }
});
</script>

<template>
    <div class="not-prose my-6">
        <div v-if="loading" class="flex items-center justify-center rounded-lg border bg-card py-8">
            <Loader2 class="h-6 w-6 animate-spin text-muted-foreground" />
        </div>
        <template v-else-if="entityData">
            <!-- Character -->
            <div v-if="entityType === 'character' && (entityData.miniature as any)" class="flex justify-center">
                <div class="w-72">
                    <CharacterCardView :miniature="entityData.miniature as any" :character-slug="entitySlug" :show-link="false" />
                    <div class="mt-2 text-center">
                        <Button v-if="entityData.link" size="sm" @click="navigateToEntity">View Details</Button>
                    </div>
                </div>
            </div>

            <!-- Action -->
            <ActionCard v-else-if="entityType === 'action'" :action="entityData as any" />

            <!-- Ability -->
            <AbilityCard v-else-if="entityType === 'ability'" :ability="entityData as any" />

            <!-- Upgrade -->
            <div v-else-if="entityType === 'upgrade' && (entityData.front_image || entityData.back_image)" class="flex justify-center">
                <div class="w-72">
                    <UpgradeFlipCard
                        :front-image="entityData.front_image as string"
                        :back-image="entityData.back_image as string"
                        :alt-text="(entityData.name ?? displayName) as string"
                    />
                    <div class="mt-2 text-center">
                        <Button v-if="entityData.link" size="sm" @click="navigateToEntity">View Details</Button>
                    </div>
                </div>
            </div>

            <!-- Other types -->
            <div v-else class="flex items-center justify-between rounded-lg border bg-card p-4">
                <div class="flex items-center gap-3">
                    <Badge :class="['border-0 text-xs', typeColor]" variant="outline">{{ typeLabel }}</Badge>
                    <span class="font-semibold">{{ entityData.name ?? displayName }}</span>
                </div>
                <Button v-if="entityData.link" size="sm" variant="outline" @click="navigateToEntity">View Details</Button>
            </div>
        </template>
        <div v-else class="flex items-center gap-3 rounded-lg border bg-card p-4">
            <Badge :class="['border-0 text-xs', typeColor]" variant="outline">{{ typeLabel }}</Badge>
            <span class="font-semibold">{{ displayName }}</span>
        </div>
    </div>
</template>
