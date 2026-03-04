<script setup lang="ts">
import CharacterCardView from '@/components/CharacterCardView.vue';
import GameIcon from '@/components/GameIcon.vue';
import GameText from '@/components/GameText.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Drawer, DrawerClose, DrawerContent, DrawerFooter, DrawerHeader, DrawerTitle, DrawerTrigger } from '@/components/ui/drawer';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import { Loader2, Users } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps<{
    attrs: Record<string, unknown>;
}>();

const entityType = computed(() => props.attrs.entityType as string);
const entitySlug = computed(() => props.attrs.entitySlug as string);
const displayName = computed(() => props.attrs.displayName as string);

const entityData = ref<Record<string, unknown> | null>(null);
const loading = ref(false);
const loaded = ref(false);
const upgradeFlipped = ref(false);

const typeColor = computed(() => {
    const map: Record<string, string> = {
        character: 'bg-blue-100 text-blue-800 hover:bg-blue-200 dark:bg-blue-900 dark:text-blue-200 dark:hover:bg-blue-800',
        keyword: 'bg-green-100 text-green-800 hover:bg-green-200 dark:bg-green-900 dark:text-green-200 dark:hover:bg-green-800',
        faction: 'bg-purple-100 text-purple-800 hover:bg-purple-200 dark:bg-purple-900 dark:text-purple-200 dark:hover:bg-purple-800',
        upgrade: 'bg-orange-100 text-orange-800 hover:bg-orange-200 dark:bg-orange-900 dark:text-orange-200 dark:hover:bg-orange-800',
        action: 'bg-red-100 text-red-800 hover:bg-red-200 dark:bg-red-900 dark:text-red-200 dark:hover:bg-red-800',
        ability: 'bg-teal-100 text-teal-800 hover:bg-teal-200 dark:bg-teal-900 dark:text-teal-200 dark:hover:bg-teal-800',
    };
    return map[entityType.value] ?? 'bg-gray-100 text-gray-800';
});

const formatActionType = (type: string) => {
    return type ? type.charAt(0).toUpperCase() + type.slice(1) : '';
};

const loadEntityData = async () => {
    if (loaded.value) return;
    loading.value = true;

    try {
        const response = await axios.get(route('api.blog.entity-show', { type: entityType.value, slug: entitySlug.value }));
        entityData.value = response.data;
    } catch (err) {
        console.error('Entity load failed:', err);
    }

    loading.value = false;
    loaded.value = true;
};

const navigateToEntity = () => {
    const link = entityData.value?.link as string | null;
    if (link) {
        router.get(link);
    }
};
</script>

<template>
    <Drawer @update:open="(open: boolean) => { open && loadEntityData(); if (!open) upgradeFlipped = false; }">
        <DrawerTrigger as-child>
            <Badge :class="['cursor-pointer border-0', typeColor]" variant="outline">
                {{ displayName }}
            </Badge>
        </DrawerTrigger>
        <DrawerContent>
            <div class="mx-auto w-full max-w-lg">
                <DrawerHeader>
                    <DrawerTitle>{{ entityData?.name ?? displayName }}</DrawerTitle>
                    <p class="text-sm capitalize text-muted-foreground">{{ entityType }}</p>
                </DrawerHeader>
                <div class="px-4 pb-4">
                    <div v-if="loading" class="flex items-center justify-center py-8">
                        <Loader2 class="h-6 w-6 animate-spin text-muted-foreground" />
                    </div>
                    <template v-else-if="entityData">
                        <!-- Character: single non-promotional miniature with flip -->
                        <div v-if="entityType === 'character' && (entityData.miniature as any)" class="flex justify-center">
                            <div class="w-48">
                                <CharacterCardView
                                    :miniature="entityData.miniature as any"
                                    :character-slug="entitySlug"
                                    :show-link="false"
                                />
                            </div>
                        </div>

                        <!-- Action: Malifaux stat card -->
                        <Card v-else-if="entityType === 'action'" class="overflow-hidden">
                            <div class="flex items-center border-b bg-secondary px-3 py-1.5 text-xs font-semibold">
                                <span class="flex-1">{{ formatActionType(entityData.action_type as string) }} Action</span>
                                <span class="w-10 text-center text-muted-foreground">Rg</span>
                                <span class="w-10 text-center text-muted-foreground">Stat</span>
                                <span class="w-10 text-center text-muted-foreground">Rst</span>
                                <span class="w-10 text-center text-muted-foreground">TN</span>
                                <span class="w-10 text-center text-muted-foreground">Dmg</span>
                            </div>
                            <div class="flex items-center border-b px-3 py-2">
                                <div class="inline-flex min-w-0 flex-1 items-center gap-1">
                                    <GameIcon v-if="entityData.is_signature" type="signature_action" class-name="h-4 inline-block shrink-0" />
                                    <GameIcon v-if="entityData.costs_stone" type="soulstone" class-name="h-4 inline-block shrink-0" />
                                    <span class="font-semibold">{{ entityData.name }}</span>
                                </div>
                                <span class="w-10 text-center text-sm">
                                    <span class="inline-flex items-center justify-center gap-0.5">
                                        <GameIcon v-if="entityData.range_type" :type="entityData.range_type as string" class-name="h-3 inline-block" />
                                        {{ entityData.range != null ? entityData.range + '"' : '-' }}
                                    </span>
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
                                            <GameIcon v-if="entityData.target_suits" :type="entityData.target_suits as string" class-name="h-3 inline-block" />
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
                            <div v-if="(entityData.triggers as any[])?.length" class="space-y-1 border-t px-3 py-2">
                                <div
                                    v-for="(trigger, tidx) in (entityData.triggers as any[])"
                                    :key="tidx"
                                    class="text-xs leading-relaxed text-muted-foreground"
                                >
                                    <span class="inline-flex items-center gap-0.5 font-semibold text-foreground">
                                        <GameIcon v-if="trigger.suits" :type="trigger.suits" class-name="h-3.5 inline-block" />
                                        <GameIcon v-if="trigger.costs_stone" type="soulstone" class-name="h-3.5 inline-block" />
                                        {{ trigger.name }}:
                                    </span>
                                    {{ ' ' }}
                                    <GameText
                                        v-if="trigger.description"
                                        :text="trigger.description"
                                        :max-length="120"
                                        icon-class="h-3.5 inline-block align-text-bottom"
                                    />
                                </div>
                            </div>
                            <div class="flex items-center gap-1.5 border-t px-3 py-1.5 text-xs">
                                <Users class="h-3 w-3 text-muted-foreground" />
                                <span v-if="(entityData.characters_count as number) > 0" class="text-muted-foreground">
                                    {{ entityData.characters_count }} {{ (entityData.characters_count as number) === 1 ? 'character' : 'characters' }}
                                </span>
                                <span v-else class="text-muted-foreground">0 characters</span>
                            </div>
                        </Card>

                        <!-- Ability: compact card -->
                        <Card v-else-if="entityType === 'ability'" class="overflow-hidden">
                            <div class="flex items-center gap-1.5 border-b bg-secondary px-3 py-1.5 text-xs font-semibold">
                                <span>Ability</span>
                            </div>
                            <div class="flex items-center gap-1.5 px-3 py-2">
                                <GameIcon v-if="entityData.costs_stone" type="soulstone" class-name="h-4 inline-block shrink-0" />
                                <span class="font-semibold">{{ entityData.name }}</span>
                                <span
                                    v-if="(entityData.suits && entityData.suits !== 'soulstone') || entityData.defensive_ability_type"
                                    class="inline-flex items-center gap-1 text-sm text-muted-foreground"
                                >
                                    (<GameIcon
                                        v-if="entityData.suits && entityData.suits !== 'soulstone'"
                                        :type="entityData.suits as string"
                                        class-name="h-4 inline-block"
                                    /><template v-if="entityData.defensive_ability_type"
                                        ><template v-if="entityData.suits && entityData.suits !== 'soulstone'">, </template>
                                        <GameIcon
                                            :type="entityData.defensive_ability_type as string"
                                            class-name="h-3.5 inline-block"
                                        /></template
                                    >)
                                </span>
                            </div>
                            <div v-if="entityData.description" class="px-3 pb-2">
                                <p class="text-xs leading-relaxed text-muted-foreground">
                                    <GameText
                                        :text="entityData.description as string"
                                        :max-length="150"
                                        icon-class="h-3.5 inline-block align-text-bottom"
                                    />
                                </p>
                            </div>
                            <div class="flex items-center gap-1.5 border-t px-3 py-1.5 text-xs">
                                <Users class="h-3 w-3 text-muted-foreground" />
                                <span v-if="(entityData.characters_count as number) > 0" class="text-muted-foreground">
                                    {{ entityData.characters_count }}
                                    {{ (entityData.characters_count as number) === 1 ? 'character' : 'characters' }}
                                </span>
                                <span v-else class="text-muted-foreground">0 characters</span>
                            </div>
                        </Card>

                        <!-- Upgrade: single card with flip -->
                        <div v-else-if="entityType === 'upgrade' && (entityData.front_image || entityData.back_image)" class="flex justify-center">
                            <div class="w-48 text-center transition-all duration-300 hover:scale-[1.03] hover:shadow-lg hover:shadow-black/20">
                                <div @click="upgradeFlipped = !upgradeFlipped" class="mx-1 cursor-pointer" style="perspective: 1000px">
                                    <div
                                        class="relative w-full"
                                        :class="{ 'card-flipped': upgradeFlipped }"
                                        style="transition: transform 0.5s; transform-style: preserve-3d"
                                    >
                                        <div style="backface-visibility: hidden">
                                            <img
                                                :src="'/storage/' + entityData.front_image"
                                                :alt="(entityData.name ?? displayName) + ' (front)'"
                                                class="h-full w-full rounded-lg"
                                            />
                                        </div>
                                        <div v-if="entityData.back_image" class="absolute inset-0" style="backface-visibility: hidden; transform: rotateY(180deg)">
                                            <img
                                                :src="'/storage/' + entityData.back_image"
                                                :alt="(entityData.name ?? displayName) + ' (back)'"
                                                class="h-full w-full rounded-lg"
                                            />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Other types: simple display -->
                        <div v-else class="flex items-center justify-center gap-3 rounded-lg border p-4">
                            <Badge :class="['border-0 text-xs', typeColor]" variant="outline">{{ entityType }}</Badge>
                            <span class="font-semibold">{{ entityData.name ?? displayName }}</span>
                        </div>
                    </template>
                </div>
                <DrawerFooter class="flex-row justify-center gap-2">
                    <Button v-if="entityData?.link" @click="navigateToEntity" size="sm">View Full Details</Button>
                    <DrawerClose as-child>
                        <Button variant="outline" size="sm">Close</Button>
                    </DrawerClose>
                </DrawerFooter>
            </div>
        </DrawerContent>
    </Drawer>
</template>

<style scoped>
.card-flipped {
    transform: rotateY(180deg);
}
</style>
