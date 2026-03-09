<script setup lang="ts">
import CharacterCardView from '@/components/CharacterCardView.vue';
import UpgradeFlipCard from '@/components/UpgradeFlipCard.vue';
import GameIcon from '@/components/GameIcon.vue';
import PageBanner from '@/components/PageBanner.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Drawer, DrawerClose, DrawerContent, DrawerFooter, DrawerHeader, DrawerTitle } from '@/components/ui/drawer';
import { Separator } from '@/components/ui/separator';
import { type SharedData } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { ArrowLeft, Check, Copy, Loader2, Shield, ShieldAlert, Star, Swords } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';

interface Keyword {
    id: number;
    name: string;
    slug: string;
}

interface CrewUpgrade {
    id: number;
    name: string;
    slug: string;
    front_image: string | null;
    back_image: string | null;
    keywords: Keyword[];
}

interface MiniatureData {
    id: number;
    name: string;
    title: string | null;
    display_name: string;
    slug: string;
    version: number;
    front_image: string | null;
    back_image: string | null;
}

interface CharacterData {
    id: number;
    name: string;
    title: string | null;
    display_name: string;
    slug: string;
    faction: string;
    second_faction: string | null;
    station: string;
    cost: number;
    count: number;
    has_totem_id: number | null;
    keywords: Keyword[];
    characteristics: string[];
    crew_upgrades: CrewUpgrade[];
    totem_slug: string | null;
    miniatures: MiniatureData[];
}

interface Faction {
    slug: string;
    name: string;
    color: string;
    logo: string;
}

interface CrewMember {
    character: CharacterData;
    miniature: MiniatureData | null;
    isTotem: boolean;
    effectiveCost: number;
    hiringCategory: 'leader' | 'totem' | 'in-keyword' | 'versatile' | 'ook';
}

interface BuildData {
    id: number;
    name: string;
    share_code: string;
    faction: string;
    master_id: number;
    encounter_size: number;
    crew_data: number[];
    is_public: boolean;
    user_id: number | null;
    user_name: string | null;
}

const props = defineProps<{
    characters: CharacterData[];
    factions: Record<string, Faction>;
    build: BuildData;
    embed?: boolean;
}>();

const page = usePage<SharedData>();
const isAuthenticated = computed(() => !!page.props.auth.user);
const isOwner = computed(() => isAuthenticated.value && props.build.user_id === page.props.auth.user?.id);

// ─── Character lookup ───
const characterById = computed(() => {
    const map = new Map<number, CharacterData>();
    (props.characters as CharacterData[]).forEach((c) => map.set(c.id, c));
    return map;
});

// ─── Crew state ───
const master = computed(() => characterById.value.get(props.build.master_id) ?? null);
const crew = ref<CrewMember[]>([]);
const faction = computed(() => props.factions[props.build.faction] ?? null);

// ─── Keyword helpers ───
const leaderKeywordSlugs = computed(() => {
    if (!master.value) return new Set<string>();
    const slugs = new Set<string>();
    master.value.keywords.forEach((k) => slugs.add(k.slug));
    master.value.crew_upgrades?.forEach((u) => {
        u.keywords?.forEach((k) => slugs.add(k.slug));
    });
    return slugs;
});

const characterSharesKeyword = (character: CharacterData): boolean => {
    return character.keywords.some((k) => leaderKeywordSlugs.value.has(k.slug));
};

const isVersatile = (character: CharacterData): boolean => character.characteristics.includes('versatile');

const getHiringCategory = (character: CharacterData): 'in-keyword' | 'versatile' | 'ook' => {
    if (characterSharesKeyword(character)) return 'in-keyword';
    if (isVersatile(character)) return 'versatile';
    return 'ook';
};

// ─── Crew upgrade ───
const activeCrewUpgrade = computed(() => master.value?.crew_upgrades?.[0] ?? null);

// ─── Category helpers ───
const categoryLabel = (cat: string): string =>
    ({ leader: 'Leader', totem: 'Totem', 'in-keyword': 'In Keyword', versatile: 'Versatile', ook: 'Out of Keyword' })[cat] ?? cat;

const categoryColor = (cat: string): string =>
    ({
        leader: 'bg-amber-400/20 text-amber-200',
        totem: 'bg-purple-400/20 text-purple-200',
        'in-keyword': 'bg-green-400/20 text-green-200',
        versatile: 'bg-blue-400/20 text-blue-200',
        ook: 'bg-red-400/20 text-red-200',
    })[cat] ?? '';

const categoryColorTheme = (cat: string): string =>
    ({
        leader: 'bg-amber-500/10 text-amber-700 dark:text-amber-400',
        totem: 'bg-purple-500/10 text-purple-700 dark:text-purple-400',
        'in-keyword': 'bg-green-500/10 text-green-700 dark:text-green-400',
        versatile: 'bg-blue-500/10 text-blue-700 dark:text-blue-400',
        ook: 'bg-red-500/10 text-red-700 dark:text-red-400',
    })[cat] ?? '';

// ─── Stats ───
const totalSpent = computed(() => crew.value.reduce((sum, m) => sum + m.effectiveCost, 0));
const remaining = computed(() => props.build.encounter_size - totalSpent.value);
const soulstonePool = computed(() => {
    const r = remaining.value;
    return r > 6 ? 6 : Math.max(0, r);
});
const ookCount = computed(() => crew.value.filter((m) => m.hiringCategory === 'ook').length);

// ─── Card Preview Drawer ───
const previewDrawerOpen = ref(false);
const previewIndex = ref<number | null>(null);
const previewMember = computed(() => (previewIndex.value !== null ? crew.value[previewIndex.value] ?? null : null));

const openPreview = (index: number) => {
    const member = crew.value[index];
    if (!member) return;
    const mini = member.miniature ?? member.character.miniatures?.[0] ?? null;
    if (!mini?.front_image) return;
    previewIndex.value = index;
    previewDrawerOpen.value = true;
};

// ─── Upgrade Preview ───
const upgradePreviewOpen = ref(false);
const upgradePreviewUpgrade = ref<CrewUpgrade | null>(null);

const openUpgradePreview = (upgrade: CrewUpgrade) => {
    if (!upgrade.front_image) return;
    upgradePreviewUpgrade.value = upgrade;
    upgradePreviewOpen.value = true;
};

// ─── Miniature assignment ───
const getNextMiniature = (character: CharacterData): MiniatureData | null => {
    const miniatures = character.miniatures ?? [];
    if (miniatures.length === 0) return null;
    const usedMiniatureIds = new Set(
        crew.value.filter((m) => m.character.id === character.id && m.miniature).map((m) => m.miniature!.id),
    );
    return miniatures.find((m) => !usedMiniatureIds.has(m.id)) ?? miniatures[0];
};

// ─── Copy to My Builds ───
const isCopying = ref(false);
const copySuccess = ref(false);
const csrfToken = () => document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';

const copyToMyBuilds = async () => {
    if (!isAuthenticated.value || isCopying.value) return;
    isCopying.value = true;
    try {
        const response = await fetch(route('tools.crew_builder.store'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken(), Accept: 'application/json' },
            body: JSON.stringify({
                name: props.build.name,
                faction: props.build.faction,
                master_id: props.build.master_id,
                encounter_size: props.build.encounter_size,
                crew_data: props.build.crew_data,
            }),
        });
        if (response.ok) {
            copySuccess.value = true;
            setTimeout(() => (copySuccess.value = false), 3000);
        }
    } finally {
        isCopying.value = false;
    }
};

// ─── Build crew from data ───
const rebuildCrew = () => {
    if (!master.value) return;
    crew.value = [];

    crew.value.push({ character: master.value, miniature: master.value.miniatures?.[0] ?? null, isTotem: false, effectiveCost: 0, hiringCategory: 'leader' });

    if (master.value.has_totem_id) {
        const totem = characterById.value.get(master.value.has_totem_id);
        if (totem) {
            for (let i = 0; i < (totem.count || 1); i++) {
                crew.value.push({ character: totem, miniature: getNextMiniature(totem), isTotem: true, effectiveCost: 0, hiringCategory: 'totem' });
            }
        }
    }

    props.build.crew_data?.forEach((charId: number) => {
        const character = characterById.value.get(charId);
        if (character) {
            const cat = getHiringCategory(character);
            crew.value.push({
                character,
                miniature: getNextMiniature(character),
                isTotem: false,
                effectiveCost: cat === 'ook' ? character.cost + 1 : character.cost,
                hiringCategory: cat,
            });
        }
    });
};

onMounted(rebuildCrew);
</script>

<template>
    <Head :title="`${build.name} — Crew Builder`" />

    <div :class="{ 'relative pb-12': !embed }">
        <template v-if="!embed">
            <div
                class="pointer-events-none absolute inset-x-0 top-0 h-64 opacity-[0.07] dark:opacity-[0.12]"
                :style="{ background: 'radial-gradient(ellipse at top, hsl(var(--primary)) 0%, transparent 70%)' }"
            />
            <PageBanner title="Crew Builder" />
        </template>

        <div :class="embed ? '' : 'container mx-auto mt-6 px-4 lg:px-6'">
            <!-- Back button (not in embed mode) -->
            <div v-if="!embed" class="mb-4 flex items-center gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    class="shrink-0 gap-1.5"
                    as="a"
                    :href="route('tools.crew_builder.index')"
                >
                    <ArrowLeft class="size-4" />
                    <span class="hidden sm:inline">{{ isAuthenticated ? 'My Builds' : 'Build a Crew' }}</span>
                </Button>
            </div>

            <!-- Loading -->
            <div v-if="!master" class="flex items-center justify-center py-12">
                <Loader2 class="size-6 animate-spin text-muted-foreground" />
            </div>

            <!-- Crew View -->
            <div v-else class="mx-auto max-w-2xl">
                <Card>
                    <CardContent class="p-4 md:p-6">
                        <!-- Header -->
                        <div class="mb-4 flex items-start gap-3">
                            <img
                                v-if="faction"
                                :src="faction.logo"
                                :alt="faction.name"
                                class="mt-0.5 size-10 shrink-0"
                            />
                            <div class="min-w-0 flex-1">
                                <h2 class="text-xl font-bold">{{ build.name }}</h2>
                                <div class="text-sm text-muted-foreground">
                                    {{ master.display_name }}
                                    <span v-if="faction"> &middot; {{ faction.name }}</span>
                                </div>
                                <div v-if="build.user_name" class="mt-0.5 text-xs text-muted-foreground">
                                    by {{ build.user_name }}
                                </div>
                            </div>
                        </div>

                        <Separator class="mb-4" />

                        <!-- Stats -->
                        <div class="mb-4 flex flex-wrap items-center gap-x-4 gap-y-2 text-sm">
                            <div class="flex items-center gap-1">
                                <span class="text-muted-foreground">Encounter:</span>
                                <span class="font-medium">{{ build.encounter_size }}</span>
                                <GameIcon type="soulstone" class-name="h-4 inline-block" />
                            </div>
                            <div class="flex items-center gap-1">
                                <span class="text-muted-foreground">Spent:</span>
                                <span class="font-medium" :class="totalSpent > build.encounter_size ? 'text-destructive' : ''">
                                    {{ totalSpent }} / {{ build.encounter_size }}
                                </span>
                                <GameIcon type="soulstone" class-name="h-4 inline-block" />
                            </div>
                            <div class="flex items-center gap-1">
                                <span class="text-muted-foreground">Pool:</span>
                                <span class="font-medium">{{ soulstonePool }}</span>
                                <GameIcon type="soulstone" class-name="h-4 inline-block" />
                            </div>
                            <div class="flex items-center gap-1">
                                <span class="text-muted-foreground">OOK:</span>
                                <span class="font-medium" :class="ookCount >= 2 ? 'text-amber-600 dark:text-amber-400' : ''">
                                    {{ ookCount }} / 2
                                </span>
                            </div>
                            <div class="flex items-center gap-1">
                                <span class="text-muted-foreground">Models:</span>
                                <span class="font-medium">{{ crew.length }}</span>
                            </div>
                        </div>

                        <!-- Crew Upgrade -->
                        <div v-if="activeCrewUpgrade" class="mb-4">
                            <div
                                class="flex items-center gap-1.5 rounded-md border border-border/50 bg-accent/50 px-2 py-1.5 transition-colors"
                                :class="activeCrewUpgrade.front_image ? 'cursor-pointer hover:bg-accent' : ''"
                                @click="openUpgradePreview(activeCrewUpgrade)"
                            >
                                <Star class="size-3.5 shrink-0 text-amber-500" />
                                <div class="min-w-0 flex-1">
                                    <div class="text-xs font-semibold">{{ activeCrewUpgrade.name }}</div>
                                    <div class="text-[10px] text-muted-foreground">Crew Upgrade</div>
                                </div>
                            </div>
                        </div>

                        <!-- Crew List -->
                        <div class="space-y-0.5">
                            <div
                                v-for="(member, index) in crew"
                                :key="index"
                                :class="factionBackground(member.character.faction)"
                                class="cursor-pointer rounded-md border border-white/20 px-2 py-1.5 text-white transition-colors hover:brightness-110"
                                @click="openPreview(index)"
                            >
                                <div class="flex items-center justify-between">
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center gap-1.5 text-sm font-semibold">
                                            <Shield v-if="member.hiringCategory === 'leader'" class="size-3.5 shrink-0 text-amber-300" />
                                            <Swords v-if="member.hiringCategory === 'totem'" class="size-3.5 shrink-0 text-purple-300" />
                                            <ShieldAlert v-if="member.hiringCategory === 'ook'" class="size-3.5 shrink-0 text-red-300" />
                                            <span class="truncate">{{ member.miniature?.display_name || member.character.display_name }}</span>
                                        </div>
                                        <div class="flex items-center gap-1 text-xs text-white/70">
                                            <span>{{ member.effectiveCost }}ss</span>
                                            <Badge :class="categoryColor(member.hiringCategory)" class="px-1 py-0 text-[10px]">
                                                {{ categoryLabel(member.hiringCategory) }}
                                            </Badge>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <Separator class="my-4" />

                        <!-- Actions -->
                        <div class="flex flex-wrap items-center gap-2">
                            <Button
                                v-if="isAuthenticated && !isOwner"
                                class="gap-1.5"
                                :disabled="isCopying || copySuccess"
                                @click="copyToMyBuilds"
                            >
                                <Check v-if="copySuccess" class="size-4" />
                                <Loader2 v-else-if="isCopying" class="size-4 animate-spin" />
                                <Copy v-else class="size-4" />
                                {{ copySuccess ? 'Saved to My Builds' : 'Copy to My Builds' }}
                            </Button>
                            <Button
                                v-if="isOwner"
                                variant="outline"
                                class="gap-1.5"
                                as="a"
                                :href="route('tools.crew_builder.index')"
                            >
                                Edit in Crew Builder
                            </Button>
                            <Button
                                v-if="!isAuthenticated"
                                variant="outline"
                                class="gap-1.5"
                                as="a"
                                :href="route('login')"
                            >
                                Log in to save this crew
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </div>

    <!-- Card Preview Drawer -->
    <Drawer v-model:open="previewDrawerOpen">
        <DrawerContent>
            <div v-if="previewMember" class="mx-auto w-full max-w-sm">
                <DrawerHeader class="pb-2">
                    <DrawerTitle class="text-center">{{ previewMember.character.display_name }}</DrawerTitle>
                    <div class="mt-1 flex items-center justify-center gap-1.5">
                        <Badge variant="secondary" class="text-[10px] capitalize">{{ previewMember.character.station }}</Badge>
                        <Badge variant="secondary" class="text-[10px]">{{ previewMember.effectiveCost }}ss</Badge>
                        <Badge :class="categoryColorTheme(previewMember.hiringCategory)" class="px-1.5 py-0 text-[10px]">
                            {{ categoryLabel(previewMember.hiringCategory) }}
                        </Badge>
                    </div>
                </DrawerHeader>
                <div class="px-4 pb-2">
                    <CharacterCardView
                        v-if="previewMember.miniature?.front_image"
                        :key="previewMember.miniature?.id"
                        :miniature="previewMember.miniature"
                        :show-link="true"
                        :character-slug="previewMember.character.slug"
                    />
                    <div v-else class="py-8 text-center text-sm text-muted-foreground">No card image available</div>
                </div>
                <DrawerFooter class="pt-2">
                    <DrawerClose as-child>
                        <Button variant="outline">Close</Button>
                    </DrawerClose>
                </DrawerFooter>
            </div>
        </DrawerContent>
    </Drawer>

    <!-- Upgrade Preview Drawer -->
    <Drawer v-model:open="upgradePreviewOpen">
        <DrawerContent>
            <div v-if="upgradePreviewUpgrade" class="mx-auto w-full max-w-sm">
                <DrawerHeader class="pb-2">
                    <DrawerTitle class="text-center">{{ upgradePreviewUpgrade.name }}</DrawerTitle>
                    <div class="mt-1 text-center text-xs text-muted-foreground">Crew Upgrade</div>
                </DrawerHeader>
                <div class="px-4 pb-2">
                    <UpgradeFlipCard
                        :front-image="upgradePreviewUpgrade.front_image!"
                        :back-image="upgradePreviewUpgrade.back_image"
                        :alt-text="upgradePreviewUpgrade.name"
                    />
                </div>
                <DrawerFooter class="pt-2">
                    <DrawerClose as-child>
                        <Button variant="outline">Close</Button>
                    </DrawerClose>
                </DrawerFooter>
            </div>
        </DrawerContent>
    </Drawer>
</template>
