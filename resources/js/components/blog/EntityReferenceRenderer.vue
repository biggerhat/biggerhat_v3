<script setup lang="ts">
import AbilityCard from '@/components/AbilityCard.vue';
import ActionCard from '@/components/ActionCard.vue';
import CharacterCardView from '@/components/CharacterCardView.vue';
import FactionLogo from '@/components/FactionLogo.vue';
import GameIcon from '@/components/GameIcon.vue';
import GameText from '@/components/GameText.vue';
import UpgradeFlipCard from '@/components/UpgradeFlipCard.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Drawer, DrawerClose, DrawerContent, DrawerFooter, DrawerHeader, DrawerTitle, DrawerTrigger } from '@/components/ui/drawer';
import { Link, router } from '@inertiajs/vue3';
import axios from 'axios';
import { Loader2, Package, Swords, Users } from 'lucide-vue-next';
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

const typeColor = computed(() => {
    const map: Record<string, string> = {
        character: 'bg-blue-100 text-blue-800 hover:bg-blue-200 dark:bg-blue-900 dark:text-blue-200 dark:hover:bg-blue-800',
        keyword: 'bg-green-100 text-green-800 hover:bg-green-200 dark:bg-green-900 dark:text-green-200 dark:hover:bg-green-800',
        faction: 'bg-purple-100 text-purple-800 hover:bg-purple-200 dark:bg-purple-900 dark:text-purple-200 dark:hover:bg-purple-800',
        upgrade: 'bg-orange-100 text-orange-800 hover:bg-orange-200 dark:bg-orange-900 dark:text-orange-200 dark:hover:bg-orange-800',
        action: 'bg-red-100 text-red-800 hover:bg-red-200 dark:bg-red-900 dark:text-red-200 dark:hover:bg-red-800',
        ability: 'bg-teal-100 text-teal-800 hover:bg-teal-200 dark:bg-teal-900 dark:text-teal-200 dark:hover:bg-teal-800',
        scheme: 'bg-amber-100 text-amber-800 hover:bg-amber-200 dark:bg-amber-900 dark:text-amber-200 dark:hover:bg-amber-800',
        strategy: 'bg-indigo-100 text-indigo-800 hover:bg-indigo-200 dark:bg-indigo-900 dark:text-indigo-200 dark:hover:bg-indigo-800',
        token: 'bg-cyan-100 text-cyan-800 hover:bg-cyan-200 dark:bg-cyan-900 dark:text-cyan-200 dark:hover:bg-cyan-800',
        marker: 'bg-pink-100 text-pink-800 hover:bg-pink-200 dark:bg-pink-900 dark:text-pink-200 dark:hover:bg-pink-800',
        package: 'bg-lime-100 text-lime-800 hover:bg-lime-200 dark:bg-lime-900 dark:text-lime-200 dark:hover:bg-lime-800',
        trigger: 'bg-rose-100 text-rose-800 hover:bg-rose-200 dark:bg-rose-900 dark:text-rose-200 dark:hover:bg-rose-800',
        crew: 'bg-violet-100 text-violet-800 hover:bg-violet-200 dark:bg-violet-900 dark:text-violet-200 dark:hover:bg-violet-800',
    };
    return map[entityType.value] ?? 'bg-gray-100 text-gray-800';
});

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
    <!-- Crew: direct link to share page -->
    <Link v-if="entityType === 'crew'" :href="route('tools.crew_builder.share', entitySlug)">
        <Badge :class="['cursor-pointer border-0 px-1.5 py-0 text-[13px]', typeColor]" variant="outline">
            {{ displayName }}
        </Badge>
    </Link>

    <!-- All other types: drawer preview -->
    <Drawer
        v-else
        @update:open="
            (open: boolean) => {
                open && loadEntityData();
            }
        "
    >
        <DrawerTrigger as-child>
            <Badge :class="['cursor-pointer border-0 px-1.5 py-0 text-[13px]', typeColor]" variant="outline">
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
                        <!-- Character -->
                        <div v-if="entityType === 'character' && (entityData.miniature as any)" class="flex justify-center">
                            <div class="w-72">
                                <CharacterCardView :miniature="entityData.miniature as any" :character-slug="entitySlug" :show-link="false" />
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
                            </div>
                        </div>

                        <!-- Scheme / Strategy with image -->
                        <div v-else-if="(entityType === 'scheme' || entityType === 'strategy') && entityData.image" class="flex justify-center">
                            <div class="w-72">
                                <img :src="entityData.image as string" :alt="(entityData.name ?? displayName) as string" class="w-full rounded-lg" />
                            </div>
                        </div>

                        <!-- Token -->
                        <div v-else-if="entityType === 'token'" class="rounded-lg border p-4">
                            <div class="flex items-center gap-3">
                                <Badge class="border-0 text-xs" :class="typeColor" variant="outline">Token</Badge>
                                <span class="font-semibold">{{ entityData.name ?? displayName }}</span>
                            </div>
                            <p v-if="entityData.description" class="mt-2 text-sm leading-relaxed text-muted-foreground">
                                <GameText :text="entityData.description as string" icon-class="h-4 inline-block align-text-bottom" />
                            </p>
                        </div>

                        <!-- Marker -->
                        <div v-else-if="entityType === 'marker'" class="rounded-lg border p-4">
                            <div class="flex items-center gap-3">
                                <Badge class="border-0 text-xs" :class="typeColor" variant="outline">Marker</Badge>
                                <span class="font-semibold">{{ entityData.name ?? displayName }}</span>
                                <span v-if="entityData.base" class="text-sm text-muted-foreground">{{ entityData.base }}mm</span>
                            </div>
                            <p v-if="entityData.description" class="mt-2 text-sm leading-relaxed text-muted-foreground">
                                <GameText :text="entityData.description as string" icon-class="h-4 inline-block align-text-bottom" />
                            </p>
                        </div>

                        <!-- Trigger -->
                        <div v-else-if="entityType === 'trigger'" class="rounded-lg border p-4">
                            <div class="flex items-center gap-2">
                                <GameIcon v-if="entityData.suits" :type="entityData.suits as string" class-name="h-5 inline-block" />
                                <GameIcon v-for="n in (entityData.stone_cost as number) ?? 0" :key="n" type="soulstone" class-name="h-5 inline-block" />
                                <span class="font-semibold">{{ entityData.name ?? displayName }}</span>
                                <span v-if="entityData.actions_count" class="ml-auto flex items-center gap-1 text-sm text-muted-foreground">
                                    <Swords class="size-3.5" />
                                    {{ entityData.actions_count }}
                                </span>
                            </div>
                            <p v-if="entityData.description" class="mt-2 text-sm leading-relaxed text-muted-foreground">
                                <GameText :text="entityData.description as string" icon-class="h-4 inline-block align-text-bottom" />
                            </p>
                        </div>

                        <!-- Package -->
                        <div v-else-if="entityType === 'package'" class="rounded-lg border p-4">
                            <div class="flex items-center gap-3">
                                <div v-if="entityData.front_image" class="size-16 shrink-0 overflow-hidden rounded-md">
                                    <img :src="'/storage/' + entityData.front_image" :alt="(entityData.name ?? displayName) as string" class="h-full w-full object-cover" loading="lazy" />
                                </div>
                                <Package v-else class="size-8 shrink-0 text-muted-foreground" />
                                <div>
                                    <div class="font-semibold">{{ entityData.name ?? displayName }}</div>
                                    <div class="flex items-center gap-3 text-sm text-muted-foreground">
                                        <span v-if="entityData.characters_count">{{ entityData.characters_count }} Characters</span>
                                        <span v-if="entityData.miniatures_count">{{ entityData.miniatures_count }} Miniatures</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Keyword -->
                        <div v-else-if="entityType === 'keyword'" class="rounded-lg border p-4">
                            <div class="flex items-center gap-3">
                                <Badge class="border-0 text-xs" :class="typeColor" variant="outline">Keyword</Badge>
                                <span class="font-semibold">{{ entityData.name ?? displayName }}</span>
                            </div>
                            <div class="mt-3 flex flex-wrap items-center gap-3 text-sm text-muted-foreground">
                                <span v-if="entityData.masters_count" class="flex items-center gap-1">
                                    <Users class="size-3.5" />
                                    {{ entityData.masters_count }} {{ entityData.masters_count === 1 ? 'Master' : 'Masters' }}
                                </span>
                                <span v-if="entityData.characters_count">
                                    {{ entityData.characters_count }} {{ entityData.characters_count === 1 ? 'Model' : 'Models' }}
                                </span>
                            </div>
                            <div v-if="(entityData.factions as any[])?.length" class="mt-2 flex flex-wrap gap-1.5">
                                <Badge v-for="f in entityData.factions as any[]" :key="f.slug" variant="secondary" class="gap-1.5">
                                    <img :src="f.logo" :alt="f.name" class="size-4" loading="lazy" />
                                    {{ f.name }}
                                </Badge>
                            </div>
                        </div>

                        <!-- Faction -->
                        <div v-else-if="entityType === 'faction'" class="rounded-lg border p-4">
                            <div class="flex items-center gap-3">
                                <img v-if="entityData.logo" :src="entityData.logo as string" :alt="entityData.name as string" class="size-10" loading="lazy" />
                                <div>
                                    <div class="font-semibold">{{ entityData.name ?? displayName }}</div>
                                    <div class="flex flex-wrap items-center gap-3 text-sm text-muted-foreground">
                                        <span v-if="entityData.masters_count">{{ entityData.masters_count }} {{ entityData.masters_count === 1 ? 'Master' : 'Masters' }}</span>
                                        <span v-if="entityData.characters_count">{{ entityData.characters_count }} {{ entityData.characters_count === 1 ? 'Model' : 'Models' }}</span>
                                        <span v-if="entityData.keywords_count">{{ entityData.keywords_count }} {{ entityData.keywords_count === 1 ? 'Keyword' : 'Keywords' }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Fallback -->
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
