<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Head, Link, router } from '@inertiajs/vue3';
import { Check } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface HandCard {
    value: number;
    suit: string;
    is_joker: boolean;
}
interface AftermathData {
    id: number;
    campaign_game_id: number;
    campaign_crew_id: number;
    current_phase: number;
    // Only the entitled hand size — the player draws the actual cards from their
    // own fate deck (pg 20).
    hand_drawn: { size: number } | null;
    hand_used: HandCard[] | null;
    scrip_earned: number;
    status: string;
    crew: {
        id: number;
        share_code: string;
        name: string;
        faction: string | null;
        scrip: number;
        user_id: number;
    };
    campaign_game: {
        id: number;
        campaign_id: number;
        campaign: { id: number; name: string; status: string; current_week: number; length_weeks: number };
        base_game: { id: number; uuid: string; name: string | null; encounter_size: number } | null;
    };
}

interface KilledModelRow {
    id: number;
    campaign_crew_id: number;
    character_id: number;
    label: string | null;
    character: { id: number; display_name: string; station: string } | null;
}

interface EquipmentRow {
    id: number;
    name: string;
    br: number | null;
    cc: number;
    is_always_available: boolean;
    ttw_only: boolean;
    pool_suit_a: string | null;
    pool_suit_b: string | null;
    body: string;
}
interface InjuryPivotRow {
    pivot_id: number;
    arsenal_model_id: number;
    label: string | null;
    display_name: string;
    injury_name: string;
}

const props = defineProps<{
    aftermath: AftermathData;
    is_owner: boolean;
    killed_models: KilledModelRow[];
    // True when killed_models comes from a tracker run; false (solo / manually
    // logged) means it's the full roster and the player picks who actually died.
    kills_are_authoritative: boolean;
    // Phase-gated lazy props — server returns null on phases that don't need them.
    equipment_catalog?: EquipmentRow[] | null;
    crew_injuries?: InjuryPivotRow[] | null;
}>();

// Reactive shortcuts so the template can pass the empty default through.
const crew_injuries = computed<InjuryPivotRow[]>(() => props.crew_injuries ?? []);
const equipment_catalog = computed<EquipmentRow[]>(() => props.equipment_catalog ?? []);

const phases = [
    { n: 1, name: 'Draw Aftermath Hand' },
    { n: 2, name: 'Payday' },
    { n: 3, name: 'Barter' },
    { n: 4, name: 'Advance Leader' },
    { n: 5, name: 'Back-Alley Doctor' },
    { n: 6, name: 'Determine Injuries' },
];

const phaseStatus = (n: number): 'done' | 'current' | 'pending' => {
    if (props.aftermath.current_phase > n) return 'done';
    if (props.aftermath.current_phase === n) return 'current';
    return 'pending';
};

// ───────── Phase 1 ─────────
const handForm = ref({
    completed_without_withdrawing: true,
    schemes_completed: 0,
});

const drawHand = () => {
    router.post(route('campaigns.aftermaths.draw-hand', props.aftermath.id), handForm.value);
};

// ───────── Phase 2 ─────────
const paydayForm = ref({
    vp: 0,
    won: false,
    crew_cr: 0,
    opponent_cr: 0,
});

const submitPayday = () => {
    router.post(route('campaigns.aftermaths.payday', props.aftermath.id), paydayForm.value);
};

// ───────── Phase 3 (Barter) ─────────
const barterForm = ref({
    flip_value: 7,
    flip_suit: '',
    is_red_joker: false,
});
const barterPurchases = ref<number[]>([]);

const itemPools = (e: EquipmentRow) => [e.pool_suit_a, e.pool_suit_b].filter(Boolean).map((p) => (p as string).toLowerCase());

// Browse the whole catalog, filterable by BR number + suit (independent of the
// flip), so players can see everything that exists rather than only what the
// current flip unlocks.
const filterBr = ref<number | null>(null);
const filterSuit = ref('');
const filteredEquipment = computed(() =>
    equipment_catalog.value.filter((e) => {
        if (filterBr.value != null && !e.is_always_available && e.br !== filterBr.value) return false;
        const suit = filterSuit.value.trim().toLowerCase();
        if (suit) {
            const pools = itemPools(e);
            if (pools.length === 0) return !!e.is_always_available;
            if (!pools.includes(suit)) return false;
        }
        return true;
    }),
);

// Whether an item can actually be bought at the CURRENT flip (the server
// enforces the same rule). BR must equal the flip value exactly (pg 21); items
// keyed to a suit pool also need the flip's suit.
const isEligible = (e: EquipmentRow) => {
    if (e.ttw_only) return barterForm.value.is_red_joker;
    if (e.is_always_available) return true;
    if (e.br === null || e.br !== barterForm.value.flip_value) return false;
    const pools = itemPools(e);
    return pools.length === 0 || pools.includes(barterForm.value.flip_suit.trim().toLowerCase());
};

const barterTotalCc = computed(() =>
    barterPurchases.value.reduce((sum, id) => {
        const item = equipment_catalog.value.find((e) => e.id === id);
        return sum + (item?.cc ?? 0);
    }, 0),
);

const toggleBarter = (id: number) => {
    const i = barterPurchases.value.indexOf(id);
    if (i >= 0) barterPurchases.value.splice(i, 1);
    else barterPurchases.value.push(id);
};

const submitBarter = () => {
    router.post(route('campaigns.aftermaths.barter', props.aftermath.id), {
        ...barterForm.value,
        purchases: barterPurchases.value,
    } as Record<string, unknown>);
};

// Advance past Barter without flipping or buying anything (e.g. no scrip).
const skipBarter = () => {
    router.post(route('campaigns.aftermaths.barter', props.aftermath.id), { purchases: [], is_red_joker: false } as Record<string, unknown>);
};

// ───────── Phase 4 (Advance Leader) ─────────
const advance = () => router.post(route('campaigns.aftermaths.advance', props.aftermath.id));

interface XpBox {
    index: number;
    filled: boolean;
    tier: number | null;
}
interface XpTrackPayload {
    leader_id: number;
    leader_name: string;
    tag: string | null;
    track: XpBox[];
}
interface CatalogRow {
    id: number;
    name: string;
    flip_value?: number | null;
    body?: string;
    is_always_available?: boolean;
}
interface AdvancementCatalogs {
    attack_mod: CatalogRow[];
    tactical_mod: CatalogRow[];
    action: CatalogRow[];
    ability: CatalogRow[];
    totem: CatalogRow[];
    summoning: CatalogRow[];
    crew_card: CatalogRow[];
}

const xp_track = computed<XpTrackPayload | null>(() => (props as unknown as { xp_track?: XpTrackPayload }).xp_track ?? null);
const advancement_catalogs = computed<AdvancementCatalogs | null>(
    () => (props as unknown as { advancement_catalogs?: AdvancementCatalogs }).advancement_catalogs ?? null,
);

const xpForm = ref({
    bruiser_killed: false,
    strategist_interacted: false,
    lost: false,
});

// Mirror of CampaignRules::xpFromGame — the conditional bonuses only count for
// the Leader's actual tag, so this preview matches the server's computed total.
const totalXp = computed(() => {
    const tag = xp_track.value?.tag;
    let xp = 1; // always +1 for playing
    if (tag === 'bruiser' && xpForm.value.bruiser_killed) xp++;
    if (tag === 'strategist' && xpForm.value.strategist_interacted) xp++;
    if (xpForm.value.lost) xp++;
    return Math.min(3, xp);
});

// Preview the track with XP-earned hypothetically filling the next N empty boxes.
const xpTrackPreview = computed<XpBox[]>(() => {
    if (!xp_track.value) return [];
    let remaining = totalXp.value;
    return xp_track.value.track.map((box) => {
        if (box.filled || remaining <= 0) return box;
        remaining--;
        return { ...box, filled: true };
    });
});

const boxClasses = (box: XpBox & { _projected?: boolean }) => {
    if (box.filled) return 'border-primary bg-primary text-primary-foreground';
    if (box.tier) return 'border-muted-foreground/40';
    return 'bg-muted/30';
};

interface QueuedAdvancement {
    position_in_xp_track: number;
    box_tier: number;
    source_table: string;
    catalog_id: number | null;
    applied_to_action_index: number;
    // The fate card flipped for this advancement (pg 38-52). Null for tables
    // that don't flip (Summoning, Crew Card).
    flip_value: number | null;
}

const advancementsQueued = computed<QueuedAdvancement[]>(() => {
    if (!xp_track.value) return [];
    // Find boxes that will newly fill via this XP earn AND have a tier number.
    let remaining = totalXp.value;
    const queue: QueuedAdvancement[] = [];
    for (const box of xp_track.value.track) {
        if (box.filled) continue;
        if (remaining <= 0) break;
        remaining--;
        if (box.tier !== null) {
            queue.push({
                position_in_xp_track: box.index,
                box_tier: box.tier,
                source_table: defaultTableForTier(box.tier),
                catalog_id: null,
                applied_to_action_index: -1,
                flip_value: 13,
            });
        }
    }
    return queue;
});

// Tables that involve a fate flip when advancing. Summoning is free choice and
// Crew Card has no flip, so they skip the flip input + filter.
const FLIP_TABLES = ['attack_mod', 'tactical_mod', 'action', 'ability', 'totem'];
const tableNeedsFlip = (table: string): boolean => FLIP_TABLES.includes(table);

/**
 * Catalog rows eligible for an advancement given its flipped value: Totem needs
 * an exact match, the other flip tables allow "the value or lower" (plus
 * always-available rows); non-flip tables show everything.
 */
const eligibleCatalogRows = (adv: QueuedAdvancement): CatalogRow[] => {
    const rows = catalogRowsFor(adv.source_table);
    if (!tableNeedsFlip(adv.source_table) || adv.flip_value == null) return rows;
    if (adv.source_table === 'totem') return rows.filter((r) => r.flip_value === adv.flip_value);
    return rows.filter((r) => r.is_always_available || r.flip_value == null || (r.flip_value ?? 99) <= (adv.flip_value ?? 0));
};

function defaultTableForTier(tier: number): string {
    if (tier === 1) return 'attack_mod';
    if (tier === 2) return 'action';
    if (tier === 3) return 'totem';
    return 'crew_card';
}

const tableOptionsForTier = (tier: number) => {
    // Tier 1 → attack_mod or tactical_mod
    // Tier 2 → action or ability (and Tier 1 still allowed)
    // Tier 3 → totem or summoning (and lower tiers allowed)
    // Tier 4 → crew_card (and lower tiers allowed)
    const all = [
        { value: 'attack_mod', label: 'Tier 1 — Attack Modification' },
        { value: 'tactical_mod', label: 'Tier 1 — Tactical Modification' },
        { value: 'action', label: 'Tier 2 — Action' },
        { value: 'ability', label: 'Tier 2 — Ability' },
        { value: 'totem', label: 'Tier 3 — Totem' },
        { value: 'summoning', label: 'Tier 3 — Summoning' },
        { value: 'crew_card', label: 'Tier 4 — Crew Card effect' },
    ];
    const maxTier = tier;
    return all.filter((opt) => {
        if (opt.value === 'attack_mod' || opt.value === 'tactical_mod') return maxTier >= 1;
        if (opt.value === 'action' || opt.value === 'ability') return maxTier >= 2;
        if (opt.value === 'totem' || opt.value === 'summoning') return maxTier >= 3;
        return maxTier >= 4;
    });
};

const catalogRowsFor = (table: string): CatalogRow[] => {
    if (!advancement_catalogs.value) return [];
    return (advancement_catalogs.value as Record<string, CatalogRow[]>)[table] ?? [];
};

const submitAdvanceLeader = () => {
    router.post(route('campaigns.aftermaths.advance-leader', props.aftermath.id), {
        // Send the raw in-game facts; the server gates the bonuses by the
        // Leader's tag and computes the XP total via CampaignRules.
        bruiser_killed_non_peon: xpForm.value.bruiser_killed,
        strategist_interacted: xpForm.value.strategist_interacted,
        lost: xpForm.value.lost,
        advancements: advancementsQueued.value
            .filter((a) => a.catalog_id !== null)
            .map((a) => ({
                source_table: a.source_table,
                catalog_id: a.catalog_id,
                applied_to_action_index: a.applied_to_action_index,
                position_in_xp_track: a.position_in_xp_track,
                // Only the flip tables carry a value; the server enforces the
                // "this value or lower" (or exact, for Totem) ceiling.
                flip_value: tableNeedsFlip(a.source_table) ? a.flip_value : null,
                free_choice: null,
            })),
    } as Record<string, unknown>);
};

// ───────── Phase 5 (Doctor) ─────────
interface DoctorAttempt {
    injury_pivot_id: number;
    flip_value: number;
    suit_pool: 'pc' | 'te';
    is_red_joker: boolean;
    // Lucky Miss table result rolled after a red-joker annihilation.
    lucky_miss_flip_value: number;
    // The Lucky Miss flip was itself a joker → Doppelganger.
    lucky_miss_is_joker: boolean;
}
const doctorAttempts = ref<DoctorAttempt[]>([]);

const addDoctorAttempt = (pivotId: number) => {
    doctorAttempts.value.push({
        injury_pivot_id: pivotId,
        flip_value: 1,
        suit_pool: 'pc',
        is_red_joker: false,
        lucky_miss_flip_value: 1,
        lucky_miss_is_joker: false,
    });
};

const removeDoctorAttempt = (idx: number) => doctorAttempts.value.splice(idx, 1);

const submitDoctor = () => {
    router.post(route('campaigns.aftermaths.doctor', props.aftermath.id), {
        attempts: doctorAttempts.value,
    } as Record<string, unknown>);
};

// ───────── Phase 6 ─────────
interface InjuryFlip {
    arsenal_model_id: number;
    flip_value: number;
    suit_pool: 'pc' | 'te';
    is_red_joker: boolean;
    // Black joker = Traitor (the model defects).
    is_black_joker: boolean;
    // Lucky Miss table result rolled when the kill flip is a red joker.
    lucky_miss_flip_value: number;
    // The Lucky Miss flip was itself a joker → Doppelganger.
    lucky_miss_is_joker: boolean;
}
const injuryFlips = ref<InjuryFlip[]>([]);
const addInjuryFlip = (modelId: number) => {
    injuryFlips.value.push({
        arsenal_model_id: modelId,
        flip_value: 1,
        suit_pool: 'pc',
        is_red_joker: false,
        is_black_joker: false,
        lucky_miss_flip_value: 1,
        lucky_miss_is_joker: false,
    });
};
const removeInjuryFlip = (idx: number) => injuryFlips.value.splice(idx, 1);

const submitInjuries = () => {
    router.post(route('campaigns.aftermaths.determine-injuries', props.aftermath.id), {
        flips: injuryFlips.value,
    } as Record<string, unknown>);
};

const finalize = () => router.post(route('campaigns.aftermaths.finalize', props.aftermath.id));
</script>

<template>
    <Head :title="`Aftermath — ${aftermath.crew.name}`" />
    <div class="container mx-auto mt-6 max-w-5xl px-4 pb-16">
        <div class="mb-6 flex items-start justify-between">
            <div>
                <Link
                    :href="route('campaigns.crews.arsenal.show', [aftermath.campaign_game.campaign.id, aftermath.crew.share_code])"
                    class="text-xs uppercase tracking-wider text-muted-foreground hover:text-foreground"
                >
                    ← {{ aftermath.crew.name }}
                </Link>
                <h1 class="mt-1 text-3xl font-bold">Aftermath</h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    {{ aftermath.campaign_game.campaign.name }} • Week {{ aftermath.campaign_game.campaign.current_week }}
                    <Badge :variant="aftermath.status === 'locked' ? 'secondary' : 'default'" class="ml-1 text-[10px] uppercase">{{
                        aftermath.status
                    }}</Badge>
                </p>
            </div>
            <Link :href="route('campaigns.crews.arsenal.show', [aftermath.campaign_game.campaign.id, aftermath.crew.share_code])">
                <Button variant="outline">Back to Arsenal</Button>
            </Link>
        </div>

        <!-- Phase stepper — visual progression bar with numbered dots + connecting lines. -->
        <div class="mb-8 rounded-lg border bg-card p-4 shadow-sm">
            <ol class="flex items-start gap-1">
                <li v-for="(p, idx) in phases" :key="p.n" class="flex flex-1 items-start gap-1" :class="idx === phases.length - 1 ? '' : 'min-w-0'">
                    <div class="flex flex-1 flex-col items-center">
                        <div
                            class="flex h-8 w-8 items-center justify-center rounded-full text-xs font-bold transition"
                            :class="
                                phaseStatus(p.n) === 'current'
                                    ? 'bg-primary text-primary-foreground ring-4 ring-primary/20'
                                    : phaseStatus(p.n) === 'done'
                                      ? 'bg-primary/80 text-primary-foreground'
                                      : 'bg-muted text-muted-foreground'
                            "
                        >
                            <Check v-if="phaseStatus(p.n) === 'done'" class="h-4 w-4" />
                            <span v-else>{{ p.n }}</span>
                        </div>
                        <p
                            class="mt-1.5 hidden text-center text-[10px] uppercase tracking-wider sm:block"
                            :class="phaseStatus(p.n) === 'current' ? 'font-semibold text-foreground' : 'text-muted-foreground'"
                        >
                            {{ p.name }}
                        </p>
                    </div>
                    <div
                        v-if="idx < phases.length - 1"
                        class="mt-4 h-0.5 flex-1 transition"
                        :class="phaseStatus(phases[idx + 1].n) === 'done' || phaseStatus(p.n) === 'done' ? 'bg-primary/60' : 'bg-muted'"
                    />
                </li>
            </ol>
        </div>

        <!-- Phase 1 -->
        <Card v-if="aftermath.current_phase === 1" class="mb-4">
            <CardHeader>
                <CardTitle>Phase 1 — Draw Aftermath Hand</CardTitle>
                <p class="text-sm text-muted-foreground">
                    Tells you how many cards to draw — you draw them from your own fate deck. Hand size = 1 (no withdraw) + 1 per scheme completed
                    (cap 3, total cap 4).
                </p>
            </CardHeader>
            <CardContent class="space-y-3">
                <label class="flex items-start gap-2 text-sm">
                    <Checkbox
                        :checked="handForm.completed_without_withdrawing"
                        @update:checked="(v: boolean) => (handForm.completed_without_withdrawing = v)"
                    />
                    <span>Completed the game without strategic withdrawal</span>
                </label>
                <div>
                    <Label>Schemes completed (0–3)</Label>
                    <Input type="number" min="0" max="3" v-model.number="handForm.schemes_completed" />
                </div>
                <Button :disabled="!is_owner" @click="drawHand">Confirm — draw my hand</Button>
            </CardContent>
        </Card>

        <!-- Reminder of the hand the player should draw (past phase 1) -->
        <Card v-if="aftermath.hand_drawn" class="mb-4">
            <CardHeader><CardTitle class="text-base">Aftermath Hand</CardTitle></CardHeader>
            <CardContent>
                <p class="text-sm">
                    Draw <span class="font-semibold">{{ aftermath.hand_drawn.size }}</span> card(s) from your fate deck. Keep them in hand to cheat
                    flips throughout the Aftermath — barter, the doctor, and determining injuries.
                </p>
                <p class="mt-2 text-[10px] text-muted-foreground">Your fate deck does not reshuffle until the Aftermath ends (pg 21).</p>
            </CardContent>
        </Card>

        <!-- Phase 2 -->
        <Card v-if="aftermath.current_phase === 2" class="mb-4">
            <CardHeader>
                <CardTitle>Phase 2 — Payday</CardTitle>
                <p class="text-sm text-muted-foreground">1 scrip per 3 VP (round up) + 1 if won + (opponent CR − your CR) when lower-rated.</p>
            </CardHeader>
            <CardContent class="space-y-3">
                <div class="grid gap-3 md:grid-cols-4">
                    <div>
                        <Label>Your VP</Label>
                        <Input type="number" min="0" v-model.number="paydayForm.vp" />
                    </div>
                    <div>
                        <Label>Your CR</Label>
                        <Input type="number" v-model.number="paydayForm.crew_cr" />
                    </div>
                    <div>
                        <Label>Opp. CR</Label>
                        <Input type="number" v-model.number="paydayForm.opponent_cr" />
                    </div>
                    <div class="flex items-end">
                        <label class="flex items-center gap-2 text-sm">
                            <Checkbox :checked="paydayForm.won" @update:checked="(v: boolean) => (paydayForm.won = v)" />
                            <span>Won</span>
                        </label>
                    </div>
                </div>
                <Button :disabled="!is_owner" @click="submitPayday">Confirm &amp; advance</Button>
            </CardContent>
        </Card>

        <!-- Phase 3 — Barter -->
        <Card v-if="aftermath.current_phase === 3" class="mb-4">
            <CardHeader>
                <CardTitle>Phase 3 — Barter</CardTitle>
                <p class="text-sm text-muted-foreground">
                    One flip determines what's available to buy this Aftermath. Always-available items appear at any flip; others need BR ≤ flip. Red
                    joker enables Those Who Thirst.
                </p>
            </CardHeader>
            <CardContent class="space-y-3">
                <div class="grid gap-3 md:grid-cols-3">
                    <div>
                        <Label>Flip value (1–13)</Label>
                        <Input type="number" min="1" max="13" v-model.number="barterForm.flip_value" />
                    </div>
                    <div>
                        <Label>Suit (optional)</Label>
                        <Input v-model="barterForm.flip_suit" placeholder="ram / crow / mask / tome" />
                    </div>
                    <div class="flex items-end">
                        <label class="flex items-center gap-2 text-sm">
                            <Checkbox :checked="barterForm.is_red_joker" @update:checked="(v: boolean) => (barterForm.is_red_joker = v)" />
                            <span>Red Joker (TTW gate)</span>
                        </label>
                    </div>
                </div>

                <div class="rounded-md border p-3">
                    <div class="mb-2 flex flex-wrap items-end gap-2">
                        <p class="mr-auto text-xs font-medium uppercase text-muted-foreground">Equipment catalog</p>
                        <div>
                            <Label class="text-[10px]">Filter BR</Label>
                            <Input type="number" min="1" max="13" v-model.number="filterBr" placeholder="any" class="h-8 w-20 text-sm" />
                        </div>
                        <div>
                            <Label class="text-[10px]">Filter suit</Label>
                            <Input v-model="filterSuit" placeholder="any" class="h-8 w-28 text-sm" />
                        </div>
                    </div>
                    <ul class="max-h-64 space-y-1 overflow-y-auto pr-1">
                        <li
                            v-for="item in filteredEquipment"
                            :key="item.id"
                            class="flex items-center justify-between rounded-sm border px-2 py-1.5 text-sm"
                            :class="isEligible(item) ? '' : 'opacity-50'"
                        >
                            <div class="min-w-0 flex-1">
                                <p class="truncate font-medium">{{ item.name }}</p>
                                <p class="text-[10px] text-muted-foreground">
                                    BR {{ item.is_always_available ? 'Always' : (item.br ?? '—') }}
                                    <template v-if="item.pool_suit_a || item.pool_suit_b">
                                        of {{ [item.pool_suit_a, item.pool_suit_b].filter(Boolean).join('/') }} </template
                                    >• CC {{ item.cc }}
                                </p>
                            </div>
                            <Button
                                size="sm"
                                :variant="barterPurchases.includes(item.id) ? 'default' : 'outline'"
                                :disabled="!is_owner || (!isEligible(item) && !barterPurchases.includes(item.id))"
                                :title="isEligible(item) ? '' : 'Not available at the current flip'"
                                @click="toggleBarter(item.id)"
                            >
                                {{ barterPurchases.includes(item.id) ? '✓' : 'Buy' }}
                            </Button>
                        </li>
                        <li v-if="filteredEquipment.length === 0" class="text-[11px] text-muted-foreground">
                            No equipment matches the filter, or the catalog hasn't been seeded.
                        </li>
                    </ul>
                </div>

                <div class="flex flex-wrap items-center justify-between gap-2">
                    <span class="text-sm">
                        Total: <Badge variant="outline" class="text-[10px] tabular-nums">{{ barterTotalCc }} scrip</Badge>
                    </span>
                    <div class="flex gap-2">
                        <Button variant="outline" :disabled="!is_owner" @click="skipBarter">Skip — buy nothing</Button>
                        <Button :disabled="!is_owner || barterTotalCc > aftermath.crew.scrip" @click="submitBarter">
                            Confirm Barter &amp; advance
                        </Button>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Phase 4 — Advance Leader -->
        <Card v-if="aftermath.current_phase === 4" class="mb-4">
            <CardHeader>
                <CardTitle>Phase 4 — Advance Leader</CardTitle>
                <p class="text-sm text-muted-foreground">
                    +1 XP for playing always. +1 if your Leader is a Bruiser and killed a non-peon. +1 if your Leader is a Strategist and Interacted
                    in the enemy DZ. +1 if you lost. Max 3 per game (pg 31).
                </p>
            </CardHeader>
            <CardContent class="space-y-4">
                <!-- XP earned form -->
                <fieldset class="rounded-md border p-3 text-sm">
                    <legend class="px-1 text-xs font-medium uppercase text-muted-foreground">XP earned</legend>
                    <div class="space-y-2">
                        <label class="flex items-start gap-2">
                            <Checkbox checked disabled />
                            <span>+1 for playing the game (always)</span>
                        </label>
                        <label class="flex items-start gap-2">
                            <Checkbox :checked="xpForm.bruiser_killed" @update:checked="(v: boolean) => (xpForm.bruiser_killed = v)" />
                            <span>+1 Bruiser killed a non-peon enemy (only counts if Leader tagged Bruiser)</span>
                        </label>
                        <label class="flex items-start gap-2">
                            <Checkbox :checked="xpForm.strategist_interacted" @update:checked="(v: boolean) => (xpForm.strategist_interacted = v)" />
                            <span>+1 Strategist Interacted in enemy DZ (only counts if Leader tagged Strategist)</span>
                        </label>
                        <label class="flex items-start gap-2">
                            <Checkbox :checked="xpForm.lost" @update:checked="(v: boolean) => (xpForm.lost = v)" />
                            <span>+1 for losing the game</span>
                        </label>
                    </div>
                    <p class="mt-3 text-xs">
                        Total XP: <Badge class="tabular-nums">{{ totalXp }}</Badge>
                        <span class="ml-2 text-muted-foreground">(capped at 3)</span>
                    </p>
                </fieldset>

                <!-- XP track preview -->
                <fieldset v-if="xp_track" class="rounded-md border p-3 text-sm">
                    <legend class="px-1 text-xs font-medium uppercase text-muted-foreground">Leadership Experience track</legend>
                    <p class="mb-2 text-xs text-muted-foreground">{{ xp_track.leader_name }} — {{ xp_track.tag ?? 'no tag' }}</p>
                    <div class="grid-cols-13 grid gap-0.5">
                        <div
                            v-for="(box, i) in xpTrackPreview"
                            :key="i"
                            class="relative flex aspect-square items-center justify-center rounded-sm border text-[8px]"
                            :class="boxClasses(box)"
                            :title="box.tier ? `Tier ${box.tier}` : 'no tier'"
                        >
                            <span v-if="box.tier" class="font-bold">{{ box.tier }}</span>
                        </div>
                    </div>
                    <p class="mt-2 text-[10px] text-muted-foreground">
                        Boxes auto-fill from your XP total. Numbered boxes trigger an advancement at that tier.
                    </p>
                </fieldset>

                <!-- Advancements queued for numbered boxes -->
                <fieldset v-if="advancementsQueued.length" class="rounded-md border p-3 text-sm">
                    <legend class="px-1 text-xs font-medium uppercase text-muted-foreground">Advancements ({{ advancementsQueued.length }})</legend>
                    <div v-for="(adv, idx) in advancementsQueued" :key="idx" class="space-y-2 border-b py-2 last:border-b-0">
                        <p class="text-xs">Box {{ adv.position_in_xp_track + 1 }} — Tier {{ adv.box_tier }} advancement</p>
                        <select v-model="adv.source_table" class="h-8 w-full rounded border bg-background px-2 text-xs text-foreground">
                            <option v-for="opt in tableOptionsForTier(adv.box_tier)" :key="opt.value" :value="opt.value">
                                {{ opt.label }}
                            </option>
                        </select>
                        <label v-if="tableNeedsFlip(adv.source_table)" class="flex items-center gap-2 text-[11px] text-muted-foreground">
                            Flipped card
                            <Input type="number" min="1" max="13" v-model.number="adv.flip_value" class="h-8 w-20" />
                            <span>{{ adv.source_table === 'totem' ? '(exact match)' : '(this value or lower)' }}</span>
                        </label>
                        <select v-model="adv.catalog_id" class="h-8 w-full rounded border bg-background px-2 text-xs text-foreground">
                            <option :value="null">— pick a row —</option>
                            <option v-for="row in eligibleCatalogRows(adv)" :key="row.id" :value="row.id">
                                {{ row.name }}<span v-if="row.flip_value != null"> (flip {{ row.flip_value }})</span>
                            </option>
                        </select>
                    </div>
                </fieldset>

                <div class="flex justify-end gap-2">
                    <Button variant="ghost" :disabled="!is_owner" @click="advance">Skip phase</Button>
                    <Button :disabled="!is_owner" @click="submitAdvanceLeader">Confirm Advancements &amp; advance</Button>
                </div>
            </CardContent>
        </Card>

        <!-- Phase 5 — Back-Alley Doctor -->
        <Card v-if="aftermath.current_phase === 5" class="mb-4">
            <CardHeader>
                <CardTitle>Phase 5 — Back-Alley Doctor</CardTitle>
                <p class="text-sm text-muted-foreground">
                    Pay 1 scrip per attempt; flip on the doctor table (pg 33). The doctor keeps the scrip regardless.
                </p>
            </CardHeader>
            <CardContent class="space-y-3">
                <ul class="space-y-1">
                    <li
                        v-for="inj in crew_injuries"
                        :key="inj.pivot_id"
                        class="flex items-center justify-between rounded-md border px-2 py-1.5 text-sm"
                    >
                        <span>
                            <strong>{{ inj.display_name }}</strong>
                            <span v-if="inj.label" class="ml-1 text-[10px] text-muted-foreground">({{ inj.label }})</span>
                            — {{ inj.injury_name }}
                        </span>
                        <Button size="sm" variant="outline" :disabled="!is_owner" @click="addDoctorAttempt(inj.pivot_id)"> Attempt </Button>
                    </li>
                    <li v-if="crew_injuries.length === 0" class="text-sm text-muted-foreground">No injuries to treat.</li>
                </ul>

                <div v-if="doctorAttempts.length" class="rounded-md border p-3">
                    <p class="mb-2 text-xs font-medium uppercase text-muted-foreground">Pending Attempts ({{ doctorAttempts.length }} scrip)</p>
                    <ul class="space-y-2">
                        <li v-for="(att, idx) in doctorAttempts" :key="idx" class="flex flex-wrap items-center gap-2 text-sm">
                            <span class="flex-1 truncate">injury pivot #{{ att.injury_pivot_id }}</span>
                            <label class="flex items-center gap-1 text-[11px] text-muted-foreground">
                                <Checkbox :checked="att.is_red_joker" @update:checked="(v: boolean) => (att.is_red_joker = v)" />
                                Red Joker
                            </label>
                            <template v-if="att.is_red_joker">
                                <label class="flex items-center gap-1 text-[11px] text-muted-foreground">
                                    <Checkbox :checked="att.lucky_miss_is_joker" @update:checked="(v: boolean) => (att.lucky_miss_is_joker = v)" />
                                    Joker (Doppelganger)
                                </label>
                                <template v-if="!att.lucky_miss_is_joker">
                                    <span class="text-[11px] text-muted-foreground">Lucky Miss flip</span>
                                    <Input type="number" min="1" max="13" v-model.number="att.lucky_miss_flip_value" class="h-8 w-16" />
                                </template>
                            </template>
                            <template v-else>
                                <Input type="number" min="1" max="13" v-model.number="att.flip_value" class="h-8 w-16" />
                                <select v-model="att.suit_pool" class="h-8 rounded border bg-background px-2 text-xs text-foreground">
                                    <option value="pc">Ram/Crow</option>
                                    <option value="te">Tome/Mask</option>
                                </select>
                            </template>
                            <Button variant="ghost" size="sm" @click="removeDoctorAttempt(idx)">×</Button>
                        </li>
                    </ul>
                </div>

                <div class="flex justify-end">
                    <Button :disabled="!is_owner || doctorAttempts.length > aftermath.crew.scrip" @click="submitDoctor"> Apply &amp; advance </Button>
                </div>
            </CardContent>
        </Card>

        <!-- Phase 6 -->
        <Card v-if="aftermath.current_phase === 6" class="mb-4">
            <CardHeader>
                <CardTitle>Phase 6 — Determine Injuries</CardTitle>
                <p class="text-sm text-muted-foreground">
                    For each non-peon model killed this game, flip a card and apply the matching injury (pg 34–35). Models hitting 3+ injuries are
                    annihilated.
                </p>
            </CardHeader>
            <CardContent class="space-y-3">
                <p v-if="kills_are_authoritative" class="text-xs uppercase text-muted-foreground">Models killed this game (from the tracker)</p>
                <p v-else class="rounded-md border border-dashed bg-muted/20 p-2 text-xs text-muted-foreground">
                    No tracker data for this game — the full roster is shown below. Flip <strong>only</strong> for the non-peon models that were
                    actually killed this game (pg 34).
                </p>
                <ul class="space-y-1">
                    <li v-for="m in killed_models" :key="m.id" class="flex items-center justify-between rounded-md border p-2 text-sm">
                        <span>
                            {{ m.character?.display_name ?? '—' }}
                            <span v-if="m.label" class="ml-1 text-[10px] text-muted-foreground">({{ m.label }})</span>
                        </span>
                        <Button size="sm" variant="outline" :disabled="!is_owner" @click="addInjuryFlip(m.id)">Flip</Button>
                    </li>
                </ul>

                <div v-if="injuryFlips.length" class="rounded-md border p-3">
                    <p class="mb-2 text-xs font-medium uppercase text-muted-foreground">Pending Flips</p>
                    <ul class="space-y-2">
                        <li v-for="(f, idx) in injuryFlips" :key="idx" class="flex flex-wrap items-center gap-2 text-sm">
                            <span class="flex-1 truncate">model #{{ f.arsenal_model_id }}</span>
                            <label class="flex items-center gap-1 text-[11px] text-muted-foreground">
                                <Checkbox
                                    :checked="f.is_red_joker"
                                    @update:checked="
                                        (v: boolean) => {
                                            f.is_red_joker = v;
                                            if (v) f.is_black_joker = false;
                                        }
                                    "
                                />
                                Red Joker
                            </label>
                            <label class="flex items-center gap-1 text-[11px] text-muted-foreground">
                                <Checkbox
                                    :checked="f.is_black_joker"
                                    @update:checked="
                                        (v: boolean) => {
                                            f.is_black_joker = v;
                                            if (v) f.is_red_joker = false;
                                        }
                                    "
                                />
                                Black Joker (Traitor)
                            </label>
                            <template v-if="f.is_black_joker">
                                <span class="text-[11px] text-muted-foreground">Model defects to the opponent.</span>
                            </template>
                            <template v-else-if="f.is_red_joker">
                                <label class="flex items-center gap-1 text-[11px] text-muted-foreground">
                                    <Checkbox :checked="f.lucky_miss_is_joker" @update:checked="(v: boolean) => (f.lucky_miss_is_joker = v)" />
                                    Joker (Doppelganger)
                                </label>
                                <template v-if="!f.lucky_miss_is_joker">
                                    <span class="text-[11px] text-muted-foreground">Lucky Miss flip</span>
                                    <Input type="number" min="1" max="13" v-model.number="f.lucky_miss_flip_value" class="h-8 w-16" />
                                </template>
                            </template>
                            <template v-else>
                                <Input type="number" min="1" max="13" v-model.number="f.flip_value" class="h-8 w-16" />
                                <select v-model="f.suit_pool" class="h-8 rounded border bg-background px-2 text-xs text-foreground">
                                    <option value="pc">Ram/Crow</option>
                                    <option value="te">Tome/Mask</option>
                                </select>
                            </template>
                            <Button variant="ghost" size="sm" @click="removeInjuryFlip(idx)">×</Button>
                        </li>
                    </ul>
                </div>

                <div class="flex justify-end">
                    <Button :disabled="!is_owner" @click="submitInjuries">Apply &amp; finalize</Button>
                </div>
            </CardContent>
        </Card>

        <Card v-if="aftermath.status === 'locked'" class="mb-4 border-primary">
            <CardHeader>
                <CardTitle>Aftermath Complete</CardTitle>
                <p class="text-sm text-muted-foreground">
                    All mutations applied to the arsenal. Scrip earned this aftermath: {{ aftermath.scrip_earned }}.
                </p>
            </CardHeader>
        </Card>

        <div v-if="is_owner && aftermath.status !== 'locked'" class="mt-4 flex justify-end">
            <Button variant="ghost" size="sm" @click="finalize">Close aftermath without finishing</Button>
        </div>
    </div>
</template>

<style scoped>
.grid-cols-13 {
    grid-template-columns: repeat(13, minmax(0, 1fr));
}
</style>
