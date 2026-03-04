<script setup lang="ts">
import CharacterCardView from '@/components/CharacterCardView.vue';
import GameIcon from '@/components/GameIcon.vue';
import GameText from '@/components/GameText.vue';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { NodeViewWrapper } from '@tiptap/vue-3';
import axios from 'axios';
import { Loader2, Users } from 'lucide-vue-next';
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
    };
    return map[props.node.attrs.entityType] ?? props.node.attrs.entityType;
});

const formatActionType = (type: string) => {
    return type ? type.charAt(0).toUpperCase() + type.slice(1) : '';
};

const formatDefensiveType = (type: string) => {
    if (!type) return '';
    return type
        .split('_')
        .map((word: string) => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');
};

onMounted(async () => {
    try {
        const response = await axios.get(
            route('api.blog.entity-show', { type: props.node.attrs.entityType, slug: props.node.attrs.entitySlug }),
        );
        entityData.value = response.data;
    } catch (err) {
        console.error('Entity embed load failed:', err);
    } finally {
        loading.value = false;
    }
});
</script>

<template>
    <NodeViewWrapper as="div" :class="['my-4', selected ? 'ring-2 ring-primary rounded-lg' : '']">
        <div v-if="loading" class="flex items-center justify-center rounded-lg border p-4 py-8">
            <Loader2 class="h-6 w-6 animate-spin text-muted-foreground" />
        </div>
        <template v-else>
            <!-- Character: show card images -->
            <div v-if="node.attrs.entityType === 'character' && entityData" class="rounded-lg border p-4">
                <div class="mb-3 flex items-center gap-2">
                    <Badge :class="['border-0 text-xs', typeColor]" variant="outline">{{ typeLabel }}</Badge>
                    <span class="font-semibold">{{ entityData.name ?? node.attrs.displayName }}</span>
                </div>
                <div v-if="(entityData.miniatures as any[])?.length" class="flex flex-wrap justify-center gap-4">
                    <div v-for="miniature in (entityData.miniatures as any[])" :key="miniature.id" class="w-48">
                        <CharacterCardView :miniature="miniature" :character-slug="node.attrs.entitySlug" :show-link="false" />
                    </div>
                </div>
            </div>

            <!-- Action: Malifaux stat card -->
            <Card v-else-if="node.attrs.entityType === 'action' && entityData" class="overflow-hidden">
                <div class="flex items-center border-b bg-secondary px-3 py-1.5 text-xs font-semibold">
                    <span class="flex-1">{{ formatActionType(entityData.action_type as string) }} Action</span>
                    <span class="w-10 text-center text-muted-foreground">Rg</span>
                    <span class="w-10 text-center text-muted-foreground">Skl</span>
                    <span class="w-10 text-center text-muted-foreground">Rst</span>
                    <span class="w-10 text-center text-muted-foreground">TN</span>
                    <span class="w-10 text-center text-muted-foreground">Dmg</span>
                </div>
                <div class="flex items-center border-b px-3 py-2">
                    <div class="inline-flex min-w-0 flex-1 items-center gap-1">
                        <GameIcon v-if="entityData.costs_stone" type="soulstone" class-name="h-4 inline-block shrink-0" />
                        <span class="font-semibold">{{ entityData.name }}</span>
                        <span v-if="entityData.is_signature" class="ml-1 text-xs text-muted-foreground">(Sig)</span>
                    </div>
                    <span class="w-10 text-center text-sm">
                        {{ entityData.range != null ? entityData.range + '"' : '-' }}
                    </span>
                    <span class="w-10 text-center text-sm">
                        <template v-if="entityData.stat != null">
                            <span class="inline-flex items-center justify-center gap-0.5">
                                {{ entityData.stat }}
                                <GameIcon v-if="entityData.stat_suits" :type="entityData.stat_suits as string" class-name="h-3 inline-block" />
                            </span>
                        </template>
                        <template v-else>-</template>
                    </span>
                    <span class="w-10 text-center text-sm">{{ entityData.resisted_by ?? '-' }}</span>
                    <span class="w-10 text-center text-sm">
                        <template v-if="entityData.target_number != null">
                            <span class="inline-flex items-center justify-center gap-0.5">
                                {{ entityData.target_number }}
                                <GameIcon
                                    v-if="entityData.target_suits"
                                    :type="entityData.target_suits as string"
                                    class-name="h-3 inline-block"
                                />
                            </span>
                        </template>
                        <template v-else>-</template>
                    </span>
                    <span class="w-10 text-center text-sm">{{ entityData.damage ?? '-' }}</span>
                </div>
                <div v-if="entityData.description" class="px-3 py-2">
                    <p class="text-xs leading-relaxed text-muted-foreground">
                        <GameText :text="entityData.description as string" :max-length="150" icon-class="h-3.5 inline-block align-text-bottom" />
                    </p>
                </div>
                <div class="flex items-center gap-1.5 border-t px-3 py-1.5 text-xs">
                    <Users class="h-3 w-3 text-muted-foreground" />
                    <span v-if="(entityData.characters_count as number) > 0" class="text-muted-foreground">
                        {{ entityData.characters_count }} {{ (entityData.characters_count as number) === 1 ? 'character' : 'characters' }}
                    </span>
                    <span v-else class="text-muted-foreground">0 characters</span>
                </div>
            </Card>

            <!-- Ability: info card -->
            <Card v-else-if="node.attrs.entityType === 'ability' && entityData">
                <CardHeader class="pb-2">
                    <div class="flex items-start justify-between gap-2">
                        <CardTitle class="inline-flex items-center gap-1 text-base">
                            <GameIcon v-if="entityData.costs_stone" type="soulstone" class-name="h-4 inline-block shrink-0" />
                            {{ entityData.name }}
                        </CardTitle>
                        <Badge
                            v-if="entityData.defensive_ability_type"
                            variant="outline"
                            class="inline-flex shrink-0 items-center gap-1 text-xs"
                        >
                            <GameIcon :type="entityData.defensive_ability_type as string" class-name="h-3.5 inline-block" />
                            {{ formatDefensiveType(entityData.defensive_ability_type as string) }}
                        </Badge>
                    </div>
                </CardHeader>
                <CardContent>
                    <div class="space-y-1.5 text-sm">
                        <div v-if="entityData.suits" class="flex items-center justify-between">
                            <span class="text-muted-foreground">Suit</span>
                            <GameIcon :type="entityData.suits as string" class-name="h-4 inline-block" />
                        </div>
                        <div v-if="entityData.description" class="pt-1">
                            <p class="text-xs text-muted-foreground">
                                <GameText
                                    :text="entityData.description as string"
                                    :max-length="120"
                                    icon-class="h-3.5 inline-block align-text-bottom"
                                />
                            </p>
                        </div>
                        <div class="flex items-center gap-1.5 pt-2 text-xs">
                            <Users class="h-3 w-3 text-muted-foreground" />
                            <span v-if="(entityData.characters_count as number) > 0" class="text-muted-foreground">
                                {{ entityData.characters_count }}
                                {{ (entityData.characters_count as number) === 1 ? 'character' : 'characters' }}
                            </span>
                            <span v-else class="text-muted-foreground">0 characters</span>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Other types: simple info box -->
            <div v-else class="flex items-center gap-3 rounded-lg border p-4">
                <Badge :class="['border-0 text-xs', typeColor]" variant="outline">{{ typeLabel }}</Badge>
                <span class="font-semibold">{{ entityData?.name ?? node.attrs.displayName }}</span>
            </div>
        </template>
    </NodeViewWrapper>
</template>
