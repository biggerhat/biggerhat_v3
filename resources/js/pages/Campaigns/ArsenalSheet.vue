<script setup lang="ts">
import CardRenderer from '@/components/CardCreator/CardRenderer.vue';
import GameText from '@/components/GameText.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { useConfirm } from '@/composables/useConfirm';
import { factionBackground } from '@/composables/useFactionColor';
import { useToast } from '@/composables/useToast';
import { type SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Calendar, Copy, Swords, Tag } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

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
    crew_card_choice: { type: string; id: number; name: string } | null;
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

interface XpBox {
    index: number;
    filled: boolean;
    tier: number | null;
}
interface AdvancementTaken {
    id: number;
    position_in_xp_track: number;
    source_table: string;
    catalog_id: number | null;
    free_choice: Record<string, unknown> | null;
}
interface CatalogRow {
    id: number;
    name: string;
    flip_value?: number | null;
    is_always_available?: boolean;
}
type AdvancementCatalogs = Record<string, CatalogRow[]>;

const props = defineProps<{
    campaign: CampaignData;
    crew: CrewData;
    leader: CustomCharacterData | null;
    totem: CustomCharacterData | null;
    leader_xp_track: XpBox[] | null;
    leader_advancements: AdvancementTaken[];
    advancement_catalogs: AdvancementCatalogs | null;
    equipment: EquipmentItem[];
    campaign_rating: CampaignRating;
    view_mode: ViewMode;
}>();

const xpTrack = computed<XpBox[]>(() => props.leader_xp_track ?? []);
const xpFilled = computed(() => xpTrack.value.filter((b) => b.filled).length);

// ───────── Leadership advancements ─────────
const advancementCatalogs = computed<AdvancementCatalogs>(() => props.advancement_catalogs ?? {});
const takenByPosition = computed<Record<number, AdvancementTaken>>(() =>
    Object.fromEntries(props.leader_advancements.map((a) => [a.position_in_xp_track, a])),
);
// Earned advancement slots — filled boxes that grant an advancement (numbered).
const advancementSlots = computed(() =>
    xpTrack.value.filter((b) => b.filled && b.tier !== null).map((b) => ({ position: b.index, tier: b.tier as number })),
);

const FLIP_TABLES = ['attack_mod', 'tactical_mod', 'action', 'ability', 'totem'];
const tableNeedsFlip = (t: string) => FLIP_TABLES.includes(t);
const catalogRowsFor = (table: string): CatalogRow[] => advancementCatalogs.value[table] ?? [];

const defaultTableForTier = (tier: number): string => (tier === 1 ? 'attack_mod' : tier === 2 ? 'action' : tier === 3 ? 'totem' : 'crew_card');

const tableOptionsForTier = (tier: number) =>
    [
        { value: 'attack_mod', label: 'Tier 1 — Attack Modification', min: 1 },
        { value: 'tactical_mod', label: 'Tier 1 — Tactical Modification', min: 1 },
        { value: 'action', label: 'Tier 2 — Action', min: 2 },
        { value: 'ability', label: 'Tier 2 — Ability', min: 2 },
        { value: 'totem', label: 'Tier 3 — Totem', min: 3 },
        { value: 'summoning', label: 'Tier 3 — Summoning', min: 3 },
        { value: 'crew_card', label: 'Tier 4 — Crew Card effect', min: 4 },
    ].filter((o) => tier >= o.min);

const eligibleCatalogRows = (table: string, flip: number | null): CatalogRow[] => {
    const rows = catalogRowsFor(table);
    if (!tableNeedsFlip(table) || flip == null) return rows;
    if (table === 'totem') return rows.filter((r) => r.flip_value === flip);
    return rows.filter((r) => r.is_always_available || r.flip_value == null || (r.flip_value ?? 99) <= flip);
};

const advancementName = (a: AdvancementTaken): string =>
    catalogRowsFor(a.source_table).find((r) => r.id === a.catalog_id)?.name ?? a.source_table.replace(/_/g, ' ');

// Per-slot picker drafts. Seed reactively: Inertia reuses this component
// instance across visits (setup doesn't re-run), so a one-time loop would
// leave newly-earned slots without a draft — the picker then never renders
// and "Log" silently no-ops. Watching the slots keeps a draft for every
// current position without clobbering one the user is mid-edit on.
interface AdvDraft {
    source_table: string;
    catalog_id: number | null;
    flip_value: number | null;
}
const drafts = ref<Record<number, AdvDraft>>({});
watch(
    advancementSlots,
    (slots) => {
        for (const slot of slots) {
            if (!drafts.value[slot.position]) {
                drafts.value[slot.position] = { source_table: defaultTableForTier(slot.tier), catalog_id: null, flip_value: 13 };
            }
        }
    },
    { immediate: true },
);

const logAdvancement = (position: number) => {
    const d = drafts.value[position];
    if (!d || d.catalog_id == null) {
        toast.warning('Pick an advancement first.');
        return;
    }
    router.post(route('campaigns.crews.leader.advancements.store', [props.campaign.id, props.crew.share_code]), {
        position_in_xp_track: position,
        source_table: d.source_table,
        catalog_id: d.catalog_id,
        flip_value: tableNeedsFlip(d.source_table) ? d.flip_value : null,
    });
};

const removeAdvancement = async (a: AdvancementTaken) => {
    if (
        !(await confirmDialog({
            title: 'Remove advancement',
            message: 'Remove this advancement so you can pick a different one?',
            destructive: true,
        }))
    ) {
        return;
    }
    router.delete(route('campaigns.crews.leader.advancements.destroy', [props.campaign.id, props.crew.share_code, a.id]));
};

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
                <!-- Full editing (incl. action details) happens in the card editor —
                     the campaign builder can't change an action's stats/triggers. -->
                <Link v-if="view_mode.is_owner && leader" :href="route('tools.card_creator.edit', leader.id)">
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
                        <!-- The card sizes to its container width (aspect-ratio only),
                             so it needs an explicit max-width or it collapses. -->
                        <div v-if="leaderRendererProps" class="mx-auto w-full max-w-[360px]">
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
                        <div class="mx-auto w-full max-w-[360px]">
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
                            <p v-if="crew.crew_card_choice" class="text-xs">
                                <span class="font-semibold capitalize">{{ crew.crew_card_choice.type }}:</span> {{ crew.crew_card_choice.name }}
                            </p>
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
                        <p class="text-[10px] text-muted-foreground">
                            XP track (pg 31) — fills from logged games via the Aftermath's Advance Leader step.
                            <span v-if="xpTrack.length" class="font-medium">{{ xpFilled }} / {{ xpTrack.length }} earned.</span>
                        </p>
                    </CardHeader>
                    <CardContent>
                        <!-- Real 39-box track from the leader's xp_track; numbered boxes are advancement tiers. -->
                        <div v-if="xpTrack.length" class="grid-cols-13 grid gap-0.5">
                            <div
                                v-for="box in xpTrack"
                                :key="box.index"
                                class="relative flex aspect-square items-center justify-center rounded-sm border text-[8px]"
                                :class="box.filled ? 'border-primary bg-primary/70 text-primary-foreground' : 'bg-muted/30'"
                                :title="box.tier ? `Tier ${box.tier} advancement` : undefined"
                            >
                                <span v-if="box.tier" class="font-bold">{{ box.tier }}</span>
                            </div>
                        </div>
                        <p v-else class="text-sm text-muted-foreground">No leader yet.</p>

                        <!-- Advancement slots — one per earned (filled) numbered box. Owners
                             log what they took; everyone sees the result. -->
                        <div v-if="advancementSlots.length" class="mt-3 space-y-1.5">
                            <p class="text-[10px] font-semibold uppercase tracking-wide text-muted-foreground">Advancements</p>
                            <div v-for="slot in advancementSlots" :key="slot.position" class="rounded-md border p-2 text-xs">
                                <!-- Logged -->
                                <div v-if="takenByPosition[slot.position]" class="flex items-center justify-between gap-2">
                                    <span>
                                        <Badge variant="outline" class="text-[10px]">Tier {{ slot.tier }}</Badge>
                                        {{ advancementName(takenByPosition[slot.position]) }}
                                    </span>
                                    <Button
                                        v-if="view_mode.is_owner"
                                        size="sm"
                                        variant="ghost"
                                        @click="removeAdvancement(takenByPosition[slot.position])"
                                    >
                                        Remove
                                    </Button>
                                </div>
                                <!-- Owner picker for an empty slot -->
                                <div v-else-if="view_mode.is_owner && drafts[slot.position]" class="flex flex-wrap items-center gap-1.5">
                                    <Badge variant="outline" class="text-[10px]">Tier {{ slot.tier }}</Badge>
                                    <select
                                        v-model="drafts[slot.position].source_table"
                                        class="h-8 rounded border bg-background px-2 text-foreground"
                                        @change="drafts[slot.position].catalog_id = null"
                                    >
                                        <option v-for="opt in tableOptionsForTier(slot.tier)" :key="opt.value" :value="opt.value">
                                            {{ opt.label }}
                                        </option>
                                    </select>
                                    <Input
                                        v-if="tableNeedsFlip(drafts[slot.position].source_table)"
                                        v-model.number="drafts[slot.position].flip_value"
                                        type="number"
                                        min="1"
                                        max="13"
                                        class="h-8 w-14"
                                    />
                                    <select
                                        v-model.number="drafts[slot.position].catalog_id"
                                        class="h-8 min-w-0 flex-1 rounded border bg-background px-2 text-foreground"
                                    >
                                        <option :value="null">— pick —</option>
                                        <option
                                            v-for="row in eligibleCatalogRows(drafts[slot.position].source_table, drafts[slot.position].flip_value)"
                                            :key="row.id"
                                            :value="row.id"
                                        >
                                            {{ row.name }}
                                        </option>
                                    </select>
                                    <Button size="sm" @click="logAdvancement(slot.position)">Log</Button>
                                </div>
                                <!-- Viewer, not yet chosen -->
                                <div v-else class="text-muted-foreground">
                                    <Badge variant="outline" class="text-[10px]">Tier {{ slot.tier }}</Badge> — not chosen yet
                                </div>
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

        <!-- Advancement log — every Leadership advancement taken, from the
             Aftermath's Advance-Leader step or logged here on the sheet. -->
        <Card class="mt-6">
            <CardHeader>
                <CardTitle>Advancement Log</CardTitle>
                <p class="text-[10px] text-muted-foreground">Earned via Aftermath Phase 4 (Advance Leader) or logged per box above.</p>
            </CardHeader>
            <CardContent>
                <ul v-if="leader_advancements.length" class="space-y-1.5">
                    <li
                        v-for="a in [...leader_advancements].sort((x, y) => x.position_in_xp_track - y.position_in_xp_track)"
                        :key="a.id"
                        class="flex items-center justify-between rounded-md border p-2 text-sm"
                    >
                        <span class="flex items-center gap-2">
                            <Badge variant="outline" class="text-[10px]">Box {{ a.position_in_xp_track + 1 }}</Badge>
                            <span class="capitalize text-muted-foreground">{{ a.source_table.replace(/_/g, ' ') }}</span>
                            <span class="font-medium">{{ advancementName(a) }}</span>
                        </span>
                    </li>
                </ul>
                <p v-else class="text-sm text-muted-foreground">No advancements yet.</p>
            </CardContent>
        </Card>
    </div>
</template>

<style scoped>
.grid-cols-13 {
    grid-template-columns: repeat(13, minmax(0, 1fr));
}
</style>
