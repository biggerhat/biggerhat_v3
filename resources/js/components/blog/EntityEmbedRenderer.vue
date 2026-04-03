<script setup lang="ts">
import AbilityCard from '@/components/AbilityCard.vue';
import ActionCard from '@/components/ActionCard.vue';
import CharacterCardView from '@/components/CharacterCardView.vue';
import CrewListDisplay from '@/components/CrewListDisplay.vue';
import FactionLogo from '@/components/FactionLogo.vue';
import GameIcon from '@/components/GameIcon.vue';
import GameText from '@/components/GameText.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import UpgradeFlipCard from '@/components/UpgradeFlipCard.vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import { Loader2, Package, Swords, Users } from 'lucide-vue-next';
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
        scheme: 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200',
        strategy: 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-200',
        token: 'bg-cyan-100 text-cyan-800 dark:bg-cyan-900 dark:text-cyan-200',
        marker: 'bg-pink-100 text-pink-800 dark:bg-pink-900 dark:text-pink-200',
        package: 'bg-lime-100 text-lime-800 dark:bg-lime-900 dark:text-lime-200',
        trigger: 'bg-rose-100 text-rose-800 dark:bg-rose-900 dark:text-rose-200',
        crew: 'bg-violet-100 text-violet-800 dark:bg-violet-900 dark:text-violet-200',
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
        scheme: 'Scheme',
        strategy: 'Strategy',
        token: 'Token',
        marker: 'Marker',
        package: 'Package',
        trigger: 'Trigger',
        crew: 'Crew',
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
                <div class="w-full max-w-72">
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
                <div class="w-full max-w-72">
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

            <!-- Scheme / Strategy with image -->
            <div v-else-if="(entityType === 'scheme' || entityType === 'strategy') && entityData.image" class="flex justify-center">
                <div class="w-full max-w-72">
                    <img :src="entityData.image as string" :alt="(entityData.name ?? displayName) as string" class="w-full rounded-lg" loading="lazy" decoding="async" />
                    <div class="mt-2 text-center">
                        <Button v-if="entityData.link" size="sm" @click="navigateToEntity">View Details</Button>
                    </div>
                </div>
            </div>

            <!-- Token -->
            <div
                v-else-if="entityType === 'token'"
                class="cursor-pointer rounded-lg border bg-card p-4 transition-colors hover:bg-accent/50"
                @click="navigateToEntity"
            >
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <Badge class="border-0 text-xs" :class="typeColor" variant="outline">Token</Badge>
                        <span class="text-lg font-semibold">{{ entityData.name ?? displayName }}</span>
                    </div>
                    <Button v-if="entityData.link" size="sm" variant="outline" @click.stop="navigateToEntity">View Token</Button>
                </div>
                <p v-if="entityData.description" class="mt-2 text-sm leading-relaxed text-muted-foreground">
                    <GameText :text="entityData.description as string" icon-class="h-4 inline-block align-text-bottom" />
                </p>
            </div>

            <!-- Marker -->
            <div
                v-else-if="entityType === 'marker'"
                class="cursor-pointer rounded-lg border bg-card p-4 transition-colors hover:bg-accent/50"
                @click="navigateToEntity"
            >
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <Badge class="border-0 text-xs" :class="typeColor" variant="outline">Marker</Badge>
                        <span class="text-lg font-semibold">{{ entityData.name ?? displayName }}</span>
                        <span v-if="entityData.base" class="text-sm text-muted-foreground">{{ entityData.base }}mm</span>
                    </div>
                    <Button v-if="entityData.link" size="sm" variant="outline" @click.stop="navigateToEntity">View Marker</Button>
                </div>
                <p v-if="entityData.description" class="mt-2 text-sm leading-relaxed text-muted-foreground">
                    <GameText :text="entityData.description as string" icon-class="h-4 inline-block align-text-bottom" />
                </p>
            </div>

            <!-- Trigger -->
            <div
                v-else-if="entityType === 'trigger'"
                class="cursor-pointer rounded-lg border bg-card p-4 transition-colors hover:bg-accent/50"
                @click="navigateToEntity"
            >
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <GameIcon v-if="entityData.suits" :type="entityData.suits as string" class-name="h-5 inline-block" />
                        <GameIcon v-for="n in (entityData.stone_cost as number) ?? 0" :key="n" type="soulstone" class-name="h-5 inline-block" />
                        <span class="text-lg font-semibold">{{ entityData.name ?? displayName }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span v-if="entityData.actions_count" class="flex items-center gap-1 text-sm text-muted-foreground">
                            <Swords class="size-3.5" />
                            {{ entityData.actions_count }}
                        </span>
                        <Button v-if="entityData.link" size="sm" variant="outline" @click.stop="navigateToEntity">View Trigger</Button>
                    </div>
                </div>
                <p v-if="entityData.description" class="mt-2 text-sm leading-relaxed text-muted-foreground">
                    <GameText :text="entityData.description as string" icon-class="h-4 inline-block align-text-bottom" />
                </p>
            </div>

            <!-- Package -->
            <div
                v-else-if="entityType === 'package'"
                class="cursor-pointer rounded-lg border bg-card p-4 transition-colors hover:bg-accent/50"
                @click="navigateToEntity"
            >
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div v-if="entityData.front_image" class="size-16 shrink-0 overflow-hidden rounded-md">
                            <img :src="'/storage/' + entityData.front_image" :alt="(entityData.name ?? displayName) as string" class="h-full w-full object-cover" loading="lazy" decoding="async" />
                        </div>
                        <Package v-else class="size-8 shrink-0 text-muted-foreground" />
                        <div>
                            <div class="text-lg font-semibold">{{ entityData.name ?? displayName }}</div>
                            <div class="flex flex-wrap items-center gap-3 text-sm text-muted-foreground">
                                <span v-if="entityData.characters_count">{{ entityData.characters_count }} Characters</span>
                                <span v-if="entityData.miniatures_count">{{ entityData.miniatures_count }} Miniatures</span>
                            </div>
                        </div>
                    </div>
                    <Button v-if="entityData.link" size="sm" variant="outline" @click.stop="navigateToEntity">View Package</Button>
                </div>
            </div>

            <!-- Crew -->
            <div v-else-if="entityType === 'crew'" class="rounded-lg border bg-card">
                <div class="flex items-start gap-3 border-b p-3">
                    <FactionLogo v-if="entityData.faction" :faction="entityData.faction as string" class-name="size-7 shrink-0 mt-0.5" />
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-semibold">{{ entityData.name ?? displayName }}</p>
                        <div class="mt-1 flex flex-wrap items-center gap-1">
                            <Badge v-if="entityData.faction_label" variant="outline" class="text-[10px]">{{ entityData.faction_label }}</Badge>
                            <Badge variant="secondary" class="text-[10px]">{{ entityData.encounter_size }}ss</Badge>
                            <Badge variant="secondary" class="text-[10px]">Pool: {{ entityData.soulstone_pool }}ss</Badge>
                            <Badge variant="secondary" class="text-[10px]">{{ entityData.member_count }} models</Badge>
                        </div>
                        <div v-if="entityData.user_name" class="mt-1 text-[11px] text-muted-foreground">by {{ entityData.user_name }}</div>
                    </div>
                    <Button v-if="entityData.link" size="sm" variant="outline" class="shrink-0" @click="navigateToEntity">View Crew</Button>
                </div>
                <div class="p-2">
                    <CrewListDisplay
                        :members="(entityData.members as any[]) ?? []"
                        :crew-upgrades="(entityData.crew_upgrades as any[]) ?? []"
                        compact
                    />
                </div>
            </div>

            <!-- Keyword -->
            <div
                v-else-if="entityType === 'keyword'"
                class="cursor-pointer rounded-lg border bg-card p-4 transition-colors hover:bg-accent/50"
                @click="navigateToEntity"
            >
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <Badge class="border-0 bg-green-100 text-xs text-green-800 dark:bg-green-900 dark:text-green-200" variant="outline">Keyword</Badge>
                        <span class="text-lg font-semibold">{{ entityData.name ?? displayName }}</span>
                    </div>
                    <Button v-if="entityData.link" size="sm" variant="outline" @click.stop="navigateToEntity">View Keyword</Button>
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
                        <img :src="f.logo" :alt="f.name" class="size-4" loading="lazy" decoding="async" />
                        {{ f.name }}
                    </Badge>
                </div>
            </div>

            <!-- Faction -->
            <div
                v-else-if="entityType === 'faction'"
                class="cursor-pointer rounded-lg border bg-card p-4 transition-colors hover:bg-accent/50"
                @click="navigateToEntity"
            >
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <img v-if="entityData.logo" :src="entityData.logo as string" :alt="entityData.name as string" class="size-10" loading="lazy" decoding="async" />
                        <div>
                            <div class="text-lg font-semibold">{{ entityData.name ?? displayName }}</div>
                            <div class="flex flex-wrap items-center gap-3 text-sm text-muted-foreground">
                                <span v-if="entityData.masters_count">{{ entityData.masters_count }} {{ entityData.masters_count === 1 ? 'Master' : 'Masters' }}</span>
                                <span v-if="entityData.characters_count">{{ entityData.characters_count }} {{ entityData.characters_count === 1 ? 'Model' : 'Models' }}</span>
                                <span v-if="entityData.keywords_count">{{ entityData.keywords_count }} {{ entityData.keywords_count === 1 ? 'Keyword' : 'Keywords' }}</span>
                            </div>
                        </div>
                    </div>
                    <Button v-if="entityData.link" size="sm" variant="outline" @click.stop="navigateToEntity">View Faction</Button>
                </div>
            </div>

            <!-- Fallback -->
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
