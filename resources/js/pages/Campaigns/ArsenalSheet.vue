<script setup lang="ts">
import CardRenderer from '@/components/CardCreator/CardRenderer.vue';
import GameText from '@/components/GameText.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useConfirm } from '@/composables/useConfirm';
import { factionBackground } from '@/composables/useFactionColor';
import { useToast } from '@/composables/useToast';
import { type SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Calendar, Copy, Swords, Tag } from 'lucide-vue-next';
import { computed } from 'vue';

interface KeywordRow {
    id: number;
    name: string;
    faction: string;
}

interface CrewCardLinkedItem {
    id: number;
    name: string;
}

interface CrewCardLinkedAbility extends CrewCardLinkedItem {
    description: string | null;
}

interface CrewCardLinkedAction extends CrewCardLinkedItem {
    type: string;
    stat: number | null;
    description: string | null;
}

interface CrewCardEffectRow {
    id: number;
    name: string;
    body: string;
    actions: CrewCardLinkedAction[];
    abilities: CrewCardLinkedAbility[];
}

interface ActionData {
    name: string;
    type: string;
    category?: 'attack' | 'tactical';
    is_signature?: boolean;
    stone_cost: number;
    range: number | null;
    range_type: string | null;
    stat: number | null;
    stat_suits: string | null;
    stat_modifier: string | null;
    resisted_by: string | null;
    target_number: number | null;
    target_suits: string | null;
    damage: string | null;
    description: string | null;
    source_id: number | null;
    triggers: Array<{
        name: string;
        suits: string | null;
        stone_cost: number;
        description: string | null;
        source_id: number | null;
    }>;
}

interface AbilityData {
    name: string;
    suits: string | null;
    defensive_ability_type: string | null;
    costs_stone: boolean;
    description: string | null;
    source_id: number | null;
}

interface CustomCharacterData {
    id: number;
    name: string;
    title: string | null;
    faction: string;
    second_faction: string | null;
    station: string;
    cost: number | null;
    health: number;
    defense: number;
    defense_suit: string | null;
    willpower: number;
    willpower_suit: string | null;
    speed: number;
    size: number | null;
    base: number | string;
    keywords: Array<{ id: number | null; name: string }>;
    characteristics: string[];
    image_path: string | null;
    actions: ActionData[];
    abilities: AbilityData[];
    linked_crew_upgrades: Array<{ source_type: 'official' | 'custom'; id: number; name: string }>;
    linked_totems: Array<{ source_type: 'official' | 'custom'; id: number; name: string }>;
}

interface ArsenalRow {
    id: number;
    character_id: number;
    label: string | null;
    is_peon: boolean;
    ignored_for_limits: boolean;
    acquired_via: string;
    character: { id: number; display_name: string; cost: number | null; station: string } | null;
    injuries: string[];
    gained_characteristics: string[];
    lucky_miss: string[];
}

interface CrewData {
    id: number;
    share_code: string;
    name: string;
    faction: string | null;
    scrip: number;
    total_wins: number;
    keyword_one: KeywordRow | null;
    keyword_two: KeywordRow | null;
    crew_card_effect: CrewCardEffectRow | null;
    arsenal_models: ArsenalRow[];
}

interface CampaignData {
    id: number;
    name: string;
    status: string;
    length_weeks: number;
    current_week: number;
}

interface CampaignRating {
    value: number;
    equipment_count: number;
    advancement_count: number;
    injury_count: number;
}

interface ViewMode {
    is_member: boolean;
    is_owner: boolean;
    share_url: string;
}

interface EquipmentItem {
    id: number;
    source: string;
    name: string;
    cc: number | null;
    br: number | null;
}

const props = defineProps<{
    campaign: CampaignData;
    crew: CrewData;
    leader: CustomCharacterData | null;
    totem: CustomCharacterData | null;
    equipment: EquipmentItem[];
    campaign_rating: CampaignRating;
    view_mode: ViewMode;
}>();

const totalArsenalSs = computed(() => props.crew.arsenal_models.reduce((s, m) => s + (m.character?.cost ?? 0), 0));

// Faction-tinted hero strip — falls back to a slate gradient when faction is null.
const factionBg = computed(() => {
    const bg = factionBackground(props.crew.faction);
    return bg ? bg : 'bg-gradient-to-r from-slate-700 to-slate-500';
});

// Use the global faction_info share to render the proper display label.
const page = usePage<SharedData>();
const factionLabel = computed(() => {
    if (!props.crew.faction) return 'Faction TBD';
    return page.props.faction_info?.[props.crew.faction]?.name ?? props.crew.faction;
});

const confirmDialog = useConfirm();
const toast = useToast();

const copyShareLink = async () => {
    const url = `${window.location.origin}${props.view_mode.share_url}`;
    try {
        await navigator.clipboard.writeText(url);
        toast.success('Share link copied to clipboard.');
    } catch {
        toast.error('Could not copy', { description: url });
    }
};

const annihilateLeader = async () => {
    if (
        !(await confirmDialog({
            title: 'Annihilate Leader',
            message: 'Annihilate your Leader? First-time triggers miraculous recovery (Fate intervenes) — second-time forces Starting Anew.',
            destructive: true,
        }))
    ) {
        return;
    }
    router.post(route('campaigns.crews.leader.annihilate', [props.campaign.id, props.crew.share_code]));
};

const startingAnew = async () => {
    if (
        !(await confirmDialog({
            title: 'Start Anew',
            message: 'Start Anew? This scraps your current arsenal and grants bonus scrip based on weeks elapsed (pg 37).',
            destructive: true,
        }))
    ) {
        return;
    }
    router.post(route('campaigns.crews.starting-anew', [props.campaign.id, props.crew.share_code]));
};

// Adapter — CardRenderer expects camelCase, CustomCharacter ships snake_case.
const leaderRendererProps = computed(() => {
    if (!props.leader) return null;
    return {
        name: props.leader.name,
        title: props.leader.title,
        faction: props.leader.faction,
        secondFaction: props.leader.second_faction,
        station: props.leader.station,
        cost: props.leader.cost,
        health: props.leader.health,
        defense: props.leader.defense,
        defenseSuit: props.leader.defense_suit,
        willpower: props.leader.willpower,
        willpowerSuit: props.leader.willpower_suit,
        speed: props.leader.speed,
        size: props.leader.size,
        base: String(props.leader.base),
        keywords: props.leader.keywords,
        characteristics: props.leader.characteristics ?? [],
        characterImage: props.leader.image_path,
        actions: props.leader.actions ?? [],
        abilities: props.leader.abilities ?? [],
        linkedCrewUpgrades: props.leader.linked_crew_upgrades ?? [],
        linkedTotems: props.leader.linked_totems ?? [],
    };
});

const totemRendererProps = computed(() => {
    if (!props.totem) return null;
    return {
        name: props.totem.name,
        title: props.totem.title,
        faction: props.totem.faction,
        secondFaction: props.totem.second_faction,
        station: props.totem.station,
        cost: props.totem.cost,
        health: props.totem.health,
        defense: props.totem.defense,
        defenseSuit: props.totem.defense_suit,
        willpower: props.totem.willpower,
        willpowerSuit: props.totem.willpower_suit,
        speed: props.totem.speed,
        size: props.totem.size,
        base: String(props.totem.base),
        keywords: props.totem.keywords,
        characteristics: props.totem.characteristics ?? [],
        characterImage: props.totem.image_path,
        actions: props.totem.actions ?? [],
        abilities: props.totem.abilities ?? [],
        linkedCrewUpgrades: props.totem.linked_crew_upgrades ?? [],
        linkedTotems: props.totem.linked_totems ?? [],
    };
});
</script>

<template>
    <Head :title="`${crew.name} — Arsenal Sheet`" />

    <!-- Faction-tinted hero strip. Uses factionBackground() so e.g. an
         Arcanists crew shows a blue band; falls back to a muted gradient. -->
    <div class="relative overflow-hidden border-b" :class="factionBg">
        <div class="absolute inset-0 bg-gradient-to-r from-black/40 via-black/10 to-transparent" />
        <div class="container relative mx-auto max-w-6xl px-4 py-5">
            <Link
                v-if="view_mode.is_member"
                :href="route('campaigns.show', campaign.id)"
                class="inline-flex items-center gap-1 text-xs uppercase tracking-wider text-white/70 hover:text-white"
            >
                ← {{ campaign.name }}
            </Link>
            <p v-else class="text-xs uppercase tracking-wider text-white/70">{{ campaign.name }}</p>
            <h1 class="mt-1 text-3xl font-black text-white drop-shadow-md">{{ crew.name }}</h1>
            <p class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-white/90">
                <span class="inline-flex items-center gap-1">
                    <Calendar class="h-3.5 w-3.5" />
                    Week {{ campaign.current_week }} / {{ campaign.length_weeks }}
                </span>
                <span v-if="crew.faction" class="inline-flex items-center gap-1">
                    <Swords class="h-3.5 w-3.5" />
                    {{ factionLabel }}
                </span>
                <span v-if="crew.keyword_one || crew.keyword_two" class="inline-flex items-center gap-1">
                    <Tag class="h-3.5 w-3.5" />
                    <span v-if="crew.keyword_one">{{ crew.keyword_one.name }}</span>
                    <span v-if="crew.keyword_one && crew.keyword_two"> + </span>
                    <span v-if="crew.keyword_two">{{ crew.keyword_two.name }}</span>
                </span>
            </p>
        </div>
    </div>

    <div class="container mx-auto max-w-6xl px-4 pb-16">
        <!-- Stat tiles + action buttons -->
        <div class="mt-4 flex flex-wrap items-stretch justify-between gap-3">
            <div class="flex flex-wrap gap-2">
                <div class="rounded-md border bg-card px-4 py-2 shadow-sm">
                    <p class="text-[10px] uppercase tracking-wider text-muted-foreground">Scrip</p>
                    <p class="text-2xl font-bold tabular-nums">{{ crew.scrip }}</p>
                </div>
                <div class="rounded-md border bg-card px-4 py-2 shadow-sm">
                    <p class="text-[10px] uppercase tracking-wider text-muted-foreground">Campaign Rating</p>
                    <p
                        class="text-2xl font-bold tabular-nums"
                        :class="campaign_rating.value < 0 ? 'text-destructive' : campaign_rating.value > 0 ? 'text-primary' : ''"
                    >
                        {{ campaign_rating.value > 0 ? '+' : '' }}{{ campaign_rating.value }}
                    </p>
                </div>
                <div class="rounded-md border bg-card px-4 py-2 shadow-sm">
                    <p class="text-[10px] uppercase tracking-wider text-muted-foreground">Arsenal</p>
                    <p class="text-2xl font-bold tabular-nums">
                        {{ totalArsenalSs }} <span class="text-xs font-normal text-muted-foreground">ss</span>
                    </p>
                </div>
                <div v-if="crew.total_wins > 0" class="rounded-md border bg-card px-4 py-2 shadow-sm">
                    <p class="text-[10px] uppercase tracking-wider text-muted-foreground">Wins</p>
                    <p class="text-2xl font-bold tabular-nums">{{ crew.total_wins }}</p>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <Button size="sm" variant="outline" @click="copyShareLink"> <Copy class="mr-1 h-3 w-3" /> Share </Button>
                <Link v-if="view_mode.is_owner && leader" :href="route('campaigns.crews.leader.edit', [campaign.id, crew.share_code])">
                    <Button size="sm">Edit Leader</Button>
                </Link>
                <Button v-if="view_mode.is_owner && leader" size="sm" variant="destructive" @click="annihilateLeader"> Annihilate </Button>
                <Button v-if="view_mode.is_owner && !leader" size="sm" @click="startingAnew"> Starting Anew </Button>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            <!-- Leader card -->
            <div class="lg:col-span-2">
                <Card>
                    <CardHeader><CardTitle>Leader</CardTitle></CardHeader>
                    <CardContent>
                        <div v-if="leaderRendererProps" class="flex justify-center">
                            <CardRenderer v-bind="leaderRendererProps" />
                        </div>
                        <div v-else class="rounded-md border-2 border-dashed py-10 text-center text-sm text-muted-foreground">
                            No leader yet.
                            <Link v-if="view_mode.is_owner" :href="route('campaigns.crews.leader.edit', [campaign.id, crew.share_code])">
                                <Button size="sm" class="ml-2">Build Leader</Button>
                            </Link>
                        </div>
                    </CardContent>
                </Card>

                <Card v-if="totemRendererProps" class="mt-4">
                    <CardHeader><CardTitle>Totem</CardTitle></CardHeader>
                    <CardContent>
                        <div class="flex justify-center">
                            <CardRenderer v-bind="totemRendererProps" />
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Right column: Crew Card + XP track placeholder -->
            <div class="space-y-4">
                <Card>
                    <CardHeader><CardTitle>Crew Card</CardTitle></CardHeader>
                    <CardContent>
                        <div v-if="crew.crew_card_effect" class="space-y-2">
                            <p class="font-medium">{{ crew.crew_card_effect.name }}</p>
                            <p v-if="crew.crew_card_effect.body" class="text-xs text-muted-foreground">
                                <GameText :text="crew.crew_card_effect.body" />
                            </p>
                            <div v-if="crew.crew_card_effect.abilities.length" class="space-y-1">
                                <p class="text-[10px] font-semibold uppercase tracking-wide text-muted-foreground">Abilities</p>
                                <div v-for="ab in crew.crew_card_effect.abilities" :key="ab.id" class="text-xs">
                                    <span class="font-medium">{{ ab.name }}</span>
                                    <span v-if="ab.description" class="text-muted-foreground"> — <GameText :text="ab.description" /> </span>
                                </div>
                            </div>
                            <div v-if="crew.crew_card_effect.actions.length" class="space-y-1">
                                <p class="text-[10px] font-semibold uppercase tracking-wide text-muted-foreground">Actions</p>
                                <div v-for="ac in crew.crew_card_effect.actions" :key="ac.id" class="text-xs">
                                    <span class="font-medium">{{ ac.name }}</span>
                                    <span class="text-muted-foreground">
                                        ({{ ac.type }}<template v-if="ac.stat !== null">, {{ ac.stat }}</template
                                        >)</span
                                    >
                                    <span v-if="ac.description" class="text-muted-foreground"> — <GameText :text="ac.description" /> </span>
                                </div>
                            </div>
                        </div>
                        <p v-else class="text-sm text-muted-foreground">
                            No crew card effect picked.
                            <Link
                                v-if="view_mode.is_owner"
                                :href="route('campaigns.crews.starting-arsenal.edit', [campaign.id, crew.share_code])"
                                class="text-primary underline"
                            >
                                Pick one
                            </Link>
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Leadership Experience</CardTitle>
                        <p class="text-[10px] text-muted-foreground">XP track per pg 31 — populated by Aftermath flow (Phase 9).</p>
                    </CardHeader>
                    <CardContent>
                        <!-- 27-box track placeholder. Rows: 13 + 7 + 7 per the chart. -->
                        <div class="space-y-1">
                            <div class="grid-cols-13 grid gap-0.5">
                                <div v-for="n in 13" :key="`r1-${n}`" class="aspect-square rounded-sm border bg-muted/30"></div>
                            </div>
                            <div class="grid grid-cols-7 gap-0.5">
                                <div v-for="n in 7" :key="`r2-${n}`" class="aspect-square rounded-sm border bg-muted/30"></div>
                            </div>
                            <div class="grid grid-cols-7 gap-0.5">
                                <div v-for="n in 7" :key="`r3-${n}`" class="aspect-square rounded-sm border bg-muted/30"></div>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>

        <!-- Arsenal models -->
        <Card class="mt-6">
            <CardHeader>
                <CardTitle>Arsenal Models ({{ crew.arsenal_models.length }})</CardTitle>
            </CardHeader>
            <CardContent>
                <div v-if="crew.arsenal_models.length" class="grid gap-2 md:grid-cols-2 lg:grid-cols-3">
                    <div v-for="model in crew.arsenal_models" :key="model.id" class="rounded-md border p-3 text-sm">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0 flex-1">
                                <p class="truncate font-medium">{{ model.character?.display_name ?? '—' }}</p>
                                <p v-if="model.label" class="text-[10px] text-muted-foreground">{{ model.label }}</p>
                                <p class="text-[10px] text-muted-foreground">{{ model.character?.station }}</p>
                            </div>
                            <Badge variant="outline" class="shrink-0 text-[10px] tabular-nums">{{ model.character?.cost ?? 0 }} ss</Badge>
                        </div>
                        <div
                            v-if="
                                model.injuries.length ||
                                model.gained_characteristics.length ||
                                model.lucky_miss.length ||
                                model.acquired_via === 'doppelganger' ||
                                model.acquired_via === 'traitor'
                            "
                            class="mt-2 flex flex-wrap gap-1"
                        >
                            <Badge v-if="model.acquired_via === 'doppelganger'" variant="secondary" class="text-[10px]">Doppelganger</Badge>
                            <Badge v-if="model.acquired_via === 'traitor'" variant="secondary" class="text-[10px]">Defected</Badge>
                            <Badge v-for="inj in model.injuries" :key="`i-${inj}`" variant="destructive" class="text-[10px]">{{ inj }}</Badge>
                            <Badge v-for="ch in model.gained_characteristics" :key="`c-${ch}`" variant="outline" class="text-[10px]">{{ ch }}</Badge>
                            <Badge v-for="lm in model.lucky_miss" :key="`l-${lm}`" class="bg-green-600 text-[10px] text-white hover:bg-green-600">
                                {{ lm }}
                            </Badge>
                        </div>
                    </div>
                </div>
                <p v-else class="text-sm text-muted-foreground">
                    Empty.
                    <Link
                        v-if="view_mode.is_owner"
                        :href="route('campaigns.crews.starting-arsenal.edit', [campaign.id, crew.share_code])"
                        class="text-primary underline"
                    >
                        Pick starting arsenal
                    </Link>
                </p>
            </CardContent>
        </Card>

        <!-- Equipment — listed below the models (like the crew builder) -->
        <Card class="mt-6">
            <CardHeader>
                <CardTitle>Equipment ({{ equipment.length }})</CardTitle>
            </CardHeader>
            <CardContent>
                <div v-if="equipment.length" class="grid gap-2 md:grid-cols-2 lg:grid-cols-3">
                    <div v-for="eq in equipment" :key="eq.id" class="flex items-start justify-between gap-2 rounded-md border p-3 text-sm">
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-medium">{{ eq.name }}</p>
                            <p class="text-[10px] capitalize text-muted-foreground">{{ eq.source }}</p>
                        </div>
                        <div class="flex shrink-0 flex-col items-end gap-1">
                            <Badge v-if="eq.br != null" variant="outline" class="text-[10px] tabular-nums">BR {{ eq.br }}</Badge>
                            <Badge v-if="eq.cc != null" variant="outline" class="text-[10px] tabular-nums">{{ eq.cc }} cc</Badge>
                        </div>
                    </div>
                </div>
                <p v-else class="text-sm text-muted-foreground">No equipment yet. Earned through Aftermath Barter.</p>
            </CardContent>
        </Card>

        <!-- Advancement log + notes placeholder for Phase 9 -->
        <Card class="mt-6">
            <CardHeader>
                <CardTitle>Advancement Log</CardTitle>
                <p class="text-[10px] text-muted-foreground">Populates during Aftermath Phase 4 (Advance Leader). Phase 9 work.</p>
            </CardHeader>
            <CardContent>
                <p class="text-sm text-muted-foreground">No advancements yet.</p>
            </CardContent>
        </Card>
    </div>
</template>

<style scoped>
.grid-cols-13 {
    grid-template-columns: repeat(13, minmax(0, 1fr));
}
</style>
