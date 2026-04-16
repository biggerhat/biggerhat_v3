<script setup lang="ts">
import BlogContent from '@/components/blog/BlogContent.vue';
import CrewBuilderReferences from '@/components/CrewBuilderReferences.vue';
import CrewListDisplay, { type CrewMemberDisplay, type CrewUpgradeDisplay } from '@/components/CrewListDisplay.vue';
import GameIcon from '@/components/GameIcon.vue';
import PageBanner from '@/components/PageBanner.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { type SharedData } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { ArrowLeft, Check, Copy, Loader2, Printer, Star } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';

interface Keyword {
    id: number;
    name: string;
    slug: string;
}

interface HiringRules {
    alternate_leader_id?: number;
    any_faction?: boolean;
    fixed_crew_keyword?: string;
    fixed_cache?: number;
    required_characteristic?: string;
    required_count?: number;
}

interface CrewUpgrade {
    id: number;
    name: string;
    slug: string;
    front_image: string | null;
    back_image: string | null;
    keywords: Keyword[];
    hiring_rules: HiringRules | null;
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
    hiringCategory: 'leader' | 'totem' | 'in-keyword' | 'versatile' | 'ook' | 'fixed-crew' | 'required';
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
    crew_upgrade_id: number | null;
    miniature_selections: Record<string, number | number[]> | null;
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

const crewUpgradesForDisplay = computed((): CrewUpgradeDisplay[] =>
    crewUpgrades.value.map((u: CrewUpgrade) => ({
        id: u.id,
        name: u.name,
        front_image: u.front_image,
        back_image: u.back_image,
        is_active: u.id === props.build.crew_upgrade_id,
    })),
);

const crewMembersForDisplay = computed((): CrewMemberDisplay[] =>
    crew.value.map((m) => ({
        display_name: m.miniature?.display_name || m.character.display_name,
        faction: m.character.faction,
        cost: m.character.cost ?? 0,
        effective_cost: m.effectiveCost,
        category: m.hiringCategory,
        front_image: m.miniature?.front_image ?? m.character.miniatures?.[0]?.front_image ?? null,
        back_image: m.miniature?.back_image ?? m.character.miniatures?.[0]?.back_image ?? null,
    })),
);

// ─── Resolve active crew upgrade and hiring rules ───
const activeCrewUpgrade = computed((): CrewUpgrade | null => {
    if (!props.build.crew_upgrade_id || !master.value) return null;
    return master.value.crew_upgrades?.find((u) => u.id === props.build.crew_upgrade_id) ?? null;
});
const activeHiringRules = computed(() => activeCrewUpgrade.value?.hiring_rules ?? null);
const isFixedCrew = computed(() => !!activeHiringRules.value?.fixed_crew_keyword);

// ─── Stats ───
const totalSpent = computed(() => crew.value.reduce((sum, m) => sum + m.effectiveCost, 0));
const remaining = computed(() => props.build.encounter_size - totalSpent.value);
const soulstonePool = computed(() => {
    if (isFixedCrew.value && activeHiringRules.value?.fixed_cache != null) {
        return activeHiringRules.value.fixed_cache;
    }
    const r = remaining.value;
    return r > 6 ? 6 : Math.max(0, r);
});
const ookCount = computed(() => crew.value.filter((m) => m.hiringCategory === 'ook').length);

// ─── References ───
const references = ref<any>(null);
const referencesLoading = ref(false);

const fetchReferences = async () => {
    if (crew.value.length === 0) return;
    referencesLoading.value = true;
    try {
        const ids = [...new Set(crew.value.map((m) => m.character.id))];
        const params = new URLSearchParams();
        ids.forEach((id) => params.append('ids[]', String(id)));
        const res = await fetch(route('tools.crew_builder.references') + '?' + params.toString());
        if (res.ok) references.value = await res.json();
    } catch {
        // silently fail
    } finally {
        referencesLoading.value = false;
    }
};

// ─── Crew Stats ───
const crewStats = computed(() => {
    if (crew.value.length === 0) return null;
    const hirable = crew.value.filter((m) => m.hiringCategory !== 'leader' && m.hiringCategory !== 'totem');
    const nums = (arr: (number | null | undefined)[]) => arr.filter((v): v is number => typeof v === 'number' && v > 0);
    const avg = (vals: number[]) => (vals.length ? Math.round((vals.reduce((a, b) => a + b, 0) / vals.length) * 10) / 10 : null);
    const suitCounts: Record<string, number> = {};
    for (const member of crew.value) {
        for (const suit of member.character.trigger_suits ?? []) {
            suitCounts[suit] = (suitCounts[suit] ?? 0) + 1;
        }
    }

    return {
        models: crew.value.length,
        avgCost: avg(nums(hirable.map((m) => m.effectiveCost))),
        avgHealth: avg(nums(crew.value.map((m) => m.character.health))),
        avgSpeed: avg(nums(crew.value.map((m) => m.character.speed))),
        avgDefense: avg(nums(crew.value.map((m) => m.character.defense))),
        avgWillpower: avg(nums(crew.value.map((m) => m.character.willpower))),
        suitCounts,
    };
});

// ─── Miniature assignment ───
const miniatureSelections = computed(() => props.build.miniature_selections ?? {});

const getSelectedMiniature = (character: CharacterData): MiniatureData | null => {
    const miniatures = character.miniatures ?? [];
    if (miniatures.length === 0) return null;

    const sel = miniatureSelections.value[String(character.id)];
    if (sel != null) {
        const selIds = Array.isArray(sel) ? sel : [sel];
        const usedCount = crew.value.filter((m) => m.character.id === character.id && m.miniature).length;
        const targetId = selIds[usedCount] ?? selIds[selIds.length - 1];
        const found = miniatures.find((m) => m.id === targetId);
        if (found) return found;
    }

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
                crew_upgrade_id: props.build.crew_upgrade_id,
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
            miniature: getSelectedMiniature(master.value),
            isTotem: false,
            effectiveCost: 0,
            hiringCategory: 'leader',
        });
    }

    if (master.value.has_totem_id) {
        const totem = characterById.value.get(master.value.has_totem_id);
        if (totem) {
            for (let i = 0; i < (totem.count || 1); i++) {
                crew.value.push({
                    character: totem,
                    miniature: getSelectedMiniature(totem),
                    isTotem: true,
                    effectiveCost: 0,
                    hiringCategory: 'totem',
                });
            }
        }
    }

    const rules = activeHiringRules.value;

    if (rules?.fixed_crew_keyword) {
        // Fixed crew: load keyword members
        const keywordMembers = (props.characters as CharacterData[]).filter(
            (c) => c.keywords.some((k) => k.slug === rules.fixed_crew_keyword) && c.id !== master.value!.id,
        );
        for (const char of keywordMembers) {
            for (let i = 0; i < (char.count || 1); i++) {
                crew.value.push({
                    character: char,
                    miniature: getSelectedMiniature(char),
                    isTotem: false,
                    effectiveCost: char.cost ?? 0,
                    hiringCategory: 'fixed-crew',
                });
            }
        }
    } else {
        // Determine required character IDs
        const requiredCharacteristic = rules?.required_characteristic;
        const requiredIds = new Set(
            requiredCharacteristic
                ? (props.characters as CharacterData[]).filter((c) => c.characteristics.includes(requiredCharacteristic)).map((c) => c.id)
                : [],
        );

        props.build.crew_data?.forEach((charId: number) => {
            const character = characterById.value.get(charId);
            if (character) {
                if (requiredIds.has(character.id)) {
                    crew.value.push({
                        character,
                        miniature: getSelectedMiniature(character),
                        isTotem: false,
                        effectiveCost: character.cost,
                        hiringCategory: 'required',
                    });
                } else {
                    const cat = getHiringCategory(character);
                    crew.value.push({
                        character,
                        miniature: getSelectedMiniature(character),
                        isTotem: false,
                        effectiveCost: cat === 'ook' ? character.cost + 1 : character.cost,
                        hiringCategory: cat,
                    });
                }
            }
        });
    }
};

onMounted(() => {
    rebuildCrew();
    fetchReferences();
});
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

        <div :class="embed ? '' : 'container mx-auto mt-6 sm:px-4 lg:px-6'">
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

                        <!-- Fixed Crew Indicator -->
                        <div
                            v-if="isFixedCrew"
                            class="mb-4 flex items-center gap-2 rounded-md border border-cyan-500/30 bg-cyan-500/10 px-3 py-1.5 text-xs font-medium text-cyan-700 dark:text-cyan-400"
                        >
                            <Star class="size-3.5 shrink-0" />
                            Preset Crew — fixed roster from crew card
                        </div>

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
                            <div class="flex flex-wrap items-center gap-x-2 gap-y-1.5 sm:gap-x-4">
                                <div class="text-center">
                                    <div class="text-[10px] font-medium uppercase tracking-wide text-muted-foreground">Models</div>
                                    <div class="text-sm font-bold leading-tight">{{ crewStats.models }}</div>
                                </div>
                                <Separator orientation="vertical" class="hidden h-6 sm:block" />
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
                                <template v-if="Object.keys(crewStats.suitCounts).length">
                                    <Separator orientation="vertical" class="hidden h-6 sm:block" />
                                    <div
                                        v-for="suit in ['crow', 'mask', 'ram', 'tome', 'soulstone'].filter((s) => crewStats!.suitCounts[s])"
                                        :key="suit"
                                        class="text-center"
                                    >
                                        <GameIcon :type="suit" class-name="mx-auto h-4" />
                                        <div class="text-sm font-bold leading-tight">{{ crewStats.suitCounts[suit] }}</div>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <CrewListDisplay :members="crewMembersForDisplay" :crew-upgrades="crewUpgradesForDisplay" />

                        <CrewBuilderReferences :references="references" :loading="referencesLoading" />

                        <Separator class="my-4" />

                        <!-- Actions -->
                        <div class="flex flex-wrap items-center gap-2">
                            <Button v-if="isAuthenticated && !isOwner" class="gap-1.5" :disabled="isCopying || copySuccess" @click="copyToMyBuilds">
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
                                :href="route('tools.crew_builder.editor') + '?build=' + build.share_code"
                            >
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
</template>
