<script setup lang="ts">
import BlogContent from '@/components/blog/BlogContent.vue';
import CharacterCardView from '@/components/CharacterCardView.vue';
import GameIcon from '@/components/GameIcon.vue';
import PageBanner from '@/components/PageBanner.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Drawer, DrawerClose, DrawerContent, DrawerFooter, DrawerHeader, DrawerTitle } from '@/components/ui/drawer';
import { Separator } from '@/components/ui/separator';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import UpgradeFlipCard from '@/components/UpgradeFlipCard.vue';
import { type SharedData } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { ArrowLeft, Check, Copy, Loader2, Printer, Shield, ShieldAlert, Star, Swords } from 'lucide-vue-next';
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
    health: number;
    speed: number;
    defense: number;
    willpower: number;
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
    description: Record<string, unknown> | null;
    share_code: string;
    faction: string;
    master_id: number;
    encounter_size: number;
    crew_data: number[];
    is_public: boolean;
    user_id: number | null;
    user_name: string | null;
    updated_at: string;
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
const crewUpgrades = computed(() => master.value?.crew_upgrades ?? []);

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

// ─── Crew Stats ───
const crewStats = computed(() => {
    if (crew.value.length === 0) return null;
    const hirable = crew.value.filter((m) => m.hiringCategory !== 'leader' && m.hiringCategory !== 'totem');
    const nums = (arr: (number | null | undefined)[]) => arr.filter((v): v is number => typeof v === 'number' && v > 0);
    const avg = (vals: number[]) => (vals.length ? Math.round((vals.reduce((a, b) => a + b, 0) / vals.length) * 10) / 10 : null);
    return {
        models: crew.value.length,
        avgCost: avg(nums(hirable.map((m) => m.effectiveCost))),
        avgHealth: avg(nums(crew.value.map((m) => m.character.health))),
        avgSpeed: avg(nums(crew.value.map((m) => m.character.speed))),
        avgDefense: avg(nums(crew.value.map((m) => m.character.defense))),
        avgWillpower: avg(nums(crew.value.map((m) => m.character.willpower))),
    };
});

// ─── Card Preview Drawer ───
const previewDrawerOpen = ref(false);
const previewIndex = ref<number | null>(null);
const previewMember = computed(() => (previewIndex.value !== null ? (crew.value[previewIndex.value] ?? null) : null));

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
    const usedMiniatureIds = new Set(crew.value.filter((m) => m.character.id === character.id && m.miniature).map((m) => m.miniature!.id));
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
                copied_from_id: props.build.id,
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

// ─── Print to PDF ───
const printCrewPDF = () => {
    if (crew.value.length === 0) return;

    const cards: Array<{ card_type: string; id: number }> = [];

    for (const member of crew.value) {
        const mini = member.miniature ?? member.character.miniatures?.[0];
        if (mini) {
            cards.push({ card_type: 'miniature', id: mini.id });
        }

        // Insert crew upgrades right after the master
        if (member.hiringCategory === 'leader') {
            for (const upgrade of member.character.crew_upgrades ?? []) {
                cards.push({ card_type: 'upgrade', id: upgrade.id });
            }
        }
    }

    if (cards.length === 0) return;

    const options = { separate_images: false };
    window.open(route('tools.pdf.download', { cards: btoa(JSON.stringify(cards)), options: btoa(JSON.stringify(options)) }), '_blank');
};

// ─── Build crew from data ───
const rebuildCrew = () => {
    if (!master.value) return;
    crew.value = [];

    for (let i = 0; i < (master.value.count || 1); i++) {
        crew.value.push({
            character: master.value,
            miniature: getNextMiniature(master.value),
            isTotem: false,
            effectiveCost: 0,
            hiringCategory: 'leader',
        });
    }

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
                <Button variant="outline" size="sm" class="shrink-0 gap-1.5" as="a" :href="route('tools.crew_builder.index')">
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
                            <img v-if="faction" :src="faction.logo" :alt="faction.name" class="mt-0.5 size-10 shrink-0" />
                            <div class="min-w-0 flex-1">
                                <h2 class="text-xl font-bold">{{ build.name }}</h2>
                                <div class="text-sm text-muted-foreground">
                                    {{ master.display_name }}
                                    <span v-if="faction"> &middot; {{ faction.name }}</span>
                                </div>
                                <div class="mt-0.5 flex flex-wrap items-center gap-x-2 text-xs text-muted-foreground">
                                    <span v-if="build.user_name">by {{ build.user_name }}</span>
                                    <span v-if="build.updated_at"
                                        >Updated
                                        {{
                                            new Date(build.updated_at).toLocaleDateString(undefined, {
                                                year: 'numeric',
                                                month: 'short',
                                                day: 'numeric',
                                            })
                                        }}</span
                                    >
                                </div>
                            </div>
                        </div>

                        <!-- Description -->
                        <div v-if="build.description" class="mb-4">
                            <BlogContent :content="build.description" />
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
                        </div>

                        <!-- Crew Stats Panel -->
                        <div v-if="crewStats" class="mb-4 rounded-md border border-border/50 bg-accent/30 p-2">
                            <div class="flex flex-wrap items-center gap-x-4 gap-y-1.5">
                                <div class="text-center">
                                    <div class="text-[10px] font-medium uppercase tracking-wide text-muted-foreground">Models</div>
                                    <div class="text-sm font-bold leading-tight">{{ crewStats.models }}</div>
                                </div>
                                <Separator orientation="vertical" class="h-6" />
                                <div v-if="crewStats.avgCost != null" class="text-center">
                                    <div class="text-[10px] font-medium uppercase tracking-wide text-muted-foreground">Avg Cost</div>
                                    <div class="text-sm font-bold leading-tight">{{ crewStats.avgCost }}</div>
                                </div>
                                <div v-if="crewStats.avgHealth != null" class="text-center">
                                    <div class="text-[10px] font-medium uppercase tracking-wide text-muted-foreground">Avg HP</div>
                                    <div class="text-sm font-bold leading-tight">{{ crewStats.avgHealth }}</div>
                                </div>
                                <div v-if="crewStats.avgSpeed != null" class="text-center">
                                    <div class="text-[10px] font-medium uppercase tracking-wide text-muted-foreground">Avg Spd</div>
                                    <div class="text-sm font-bold leading-tight">{{ crewStats.avgSpeed }}</div>
                                </div>
                                <div v-if="crewStats.avgDefense != null" class="text-center">
                                    <div class="text-[10px] font-medium uppercase tracking-wide text-muted-foreground">Avg Def</div>
                                    <div class="text-sm font-bold leading-tight">{{ crewStats.avgDefense }}</div>
                                </div>
                                <div v-if="crewStats.avgWillpower != null" class="text-center">
                                    <div class="text-[10px] font-medium uppercase tracking-wide text-muted-foreground">Avg Wp</div>
                                    <div class="text-sm font-bold leading-tight">{{ crewStats.avgWillpower }}</div>
                                </div>
                            </div>
                        </div>

                        <!-- Crew Upgrades -->
                        <div v-if="crewUpgrades.length" class="mb-4 space-y-1">
                            <div
                                v-for="upgrade in crewUpgrades"
                                :key="upgrade.id"
                                class="flex items-center gap-1.5 rounded-md border border-border/50 bg-accent/50 px-2 py-1.5 transition-colors"
                                :class="upgrade.front_image ? 'cursor-pointer hover:bg-accent' : ''"
                                @click="openUpgradePreview(upgrade)"
                            >
                                <Star class="size-3.5 shrink-0 text-amber-500" />
                                <div class="min-w-0 flex-1">
                                    <div class="text-xs font-semibold">{{ upgrade.name }}</div>
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
                                            <TooltipProvider
                                                v-if="
                                                    member.hiringCategory === 'leader' ||
                                                    member.hiringCategory === 'totem' ||
                                                    member.hiringCategory === 'ook'
                                                "
                                            >
                                                <Tooltip>
                                                    <TooltipTrigger as-child>
                                                        <Shield v-if="member.hiringCategory === 'leader'" class="size-3.5 shrink-0 text-amber-300" />
                                                        <Swords v-if="member.hiringCategory === 'totem'" class="size-3.5 shrink-0 text-purple-300" />
                                                        <ShieldAlert v-if="member.hiringCategory === 'ook'" class="size-3.5 shrink-0 text-red-300" />
                                                    </TooltipTrigger>
                                                    <TooltipContent side="top">
                                                        <p class="text-xs">{{ categoryLabel(member.hiringCategory) }}</p>
                                                    </TooltipContent>
                                                </Tooltip>
                                            </TooltipProvider>
                                            <span class="truncate">{{ member.miniature?.display_name || member.character.display_name }}</span>
                                        </div>
                                        <div class="flex items-center gap-1.5 text-xs text-white/70">
                                            <span v-if="member.hiringCategory === 'ook'" class="flex items-center text-sm font-bold text-white"
                                                >{{ member.effectiveCost }}<GameIcon type="soulstone" class-name="ml-0.5 h-3 inline-block" />
                                                <span class="text-xs font-normal text-red-300">({{ member.character.cost }}+1)</span></span
                                            >
                                            <span v-else class="flex items-center text-sm font-bold text-white">{{ member.effectiveCost }}<GameIcon type="soulstone" class-name="ml-0.5 h-3 inline-block" /></span>
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
                            <Button v-if="isAuthenticated && !isOwner" class="gap-1.5" :disabled="isCopying || copySuccess" @click="copyToMyBuilds">
                                <Check v-if="copySuccess" class="size-4" />
                                <Loader2 v-else-if="isCopying" class="size-4 animate-spin" />
                                <Copy v-else class="size-4" />
                                {{ copySuccess ? 'Saved to My Builds' : 'Copy to My Builds' }}
                            </Button>
                            <Button v-if="isOwner" variant="outline" class="gap-1.5" as="a" :href="route('tools.crew_builder.editor')">
                                Edit in Crew Builder
                            </Button>
                            <Button v-if="!isAuthenticated" variant="outline" class="gap-1.5" as="a" :href="route('login')">
                                Log in to save this crew
                            </Button>
                            <Button variant="outline" class="gap-1.5" :disabled="crew.length === 0" @click="printCrewPDF">
                                <Printer class="size-4" />
                                PDF
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
                    <DrawerTitle class="text-center">
                        {{ previewMember.character.display_name }}
                        <template v-if="previewMember.character.cost != null">
                            <span v-if="previewMember.hiringCategory === 'ook'" class="text-yellow-400">({{ previewMember.effectiveCost }}<GameIcon type="soulstone" class-name="ml-0.5 h-3.5 inline-block" />)</span>
                            <span v-else class="text-yellow-400">({{ previewMember.effectiveCost }}<GameIcon type="soulstone" class-name="ml-0.5 h-3.5 inline-block" />)</span>
                        </template>
                    </DrawerTitle>
                    <div class="mt-1 flex items-center justify-center gap-1.5">
                        <Badge variant="secondary" class="text-[10px] capitalize">{{ previewMember.character.station }}</Badge>
                        <template v-if="previewMember.character.cost != null">
                            <Badge v-if="previewMember.hiringCategory === 'ook'" variant="secondary" class="gap-0.5 text-xs font-bold"
                                >{{ previewMember.effectiveCost }}<GameIcon type="soulstone" class-name="h-3 inline-block" />
                                <span class="font-normal opacity-70">({{ previewMember.character.cost }}+1)</span></Badge
                            >
                            <Badge v-else variant="secondary" class="gap-0.5 text-xs font-bold">{{ previewMember.effectiveCost }}<GameIcon type="soulstone" class-name="h-3 inline-block" /></Badge>
                        </template>
                        <Badge :class="categoryColorTheme(previewMember.hiringCategory)" class="px-1.5 py-0 text-[10px]">
                            {{ categoryLabel(previewMember.hiringCategory) }}
                        </Badge>
                    </div>
                </DrawerHeader>
                <div class="flex min-h-0 flex-1 flex-col px-4 pb-2">
                    <div class="flex min-h-0 flex-1 items-start justify-center [&_img]:max-h-[55dvh] [&_img]:w-auto [&_img]:object-contain">
                        <CharacterCardView
                            v-if="previewMember.miniature?.front_image"
                            :key="previewMember.miniature?.id"
                            :miniature="previewMember.miniature"
                            :show-link="true"
                            :character-slug="previewMember.character.slug"
                        />
                        <div v-else class="py-8 text-center text-sm text-muted-foreground">No card image available</div>
                    </div>
                </div>
                <DrawerFooter class="shrink-0 pt-2">
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
                <div class="flex min-h-0 flex-1 flex-col px-4 pb-2">
                    <div class="flex min-h-0 flex-1 items-start justify-center [&_img]:max-h-[55dvh] [&_img]:w-auto [&_img]:object-contain">
                        <UpgradeFlipCard
                            :front-image="upgradePreviewUpgrade.front_image!"
                            :back-image="upgradePreviewUpgrade.back_image"
                            :alt-text="upgradePreviewUpgrade.name"
                            :upgrade-slug="upgradePreviewUpgrade.slug"
                            :show-link="true"
                        />
                    </div>
                </div>
                <DrawerFooter class="shrink-0 pt-2">
                    <DrawerClose as-child>
                        <Button variant="outline">Close</Button>
                    </DrawerClose>
                </DrawerFooter>
            </div>
        </DrawerContent>
    </Drawer>
</template>
