<script setup lang="ts">
import AbilityCard from '@/components/AbilityCard.vue';
import ActionCard from '@/components/ActionCard.vue';
import GameText from '@/components/GameText.vue';
import TriggerCard from '@/components/TriggerCard.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { Head, Link, router } from '@inertiajs/vue3';
import { Check } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

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
    character_id: number | null;
    custom_character_id: number | null;
    label: string | null;
    display_name: string;
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
    arsenal_model_id: number | null;
    custom_character_id: number | null;
    label: string | null;
    display_name: string;
    injury_name: string;
}
// Scoring captured when the game was logged, mapped to this crew's perspective.
interface Prefill {
    vp_self: number;
    vp_opponent: number;
    schemes_completed: number;
    won: boolean;
    withdrew: boolean;
    crew_cr: number;
    opponent_cr: number;
}

const props = defineProps<{
    aftermath: AftermathData;
    is_owner: boolean;
    killed_models: KilledModelRow[];
    // True when killed_models comes from a tracker run; false (solo / manually
    // logged) means it's the full roster and the player picks who actually died.
    kills_are_authoritative: boolean;
    prefill: Prefill;
    // Phase-gated props — server returns null on phases that don't need them.
    equipment_catalog?: EquipmentRow[] | null;
    crew_injuries?: InjuryPivotRow[] | null;
    xp_track?: XpTrackPayload | null;
    advancement_catalogs?: AdvancementCatalogs | null;
    doctor_results?: DoctorResultRow[] | null;
    injury_catalog?: InjuryCatalogRow[] | null;
    traitor_target_crews?: TraitorCrewRow[] | null;
    // Masters sharing a crew keyword — the Tier-4 Crew Card "borrow from" pick.
    eligible_masters?: Array<{ id: number; name: string }> | null;
    // Constrained pool for crew cards that require a token/marker/upgrade
    // choice (pg 17-18) — same pool Starting Arsenal uses.
    crew_card_choice_options?: CrewCardChoiceOptions | null;
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
// Pre-filled from the logged game; the player just confirms.
const handForm = ref({
    completed_without_withdrawing: !props.prefill.withdrew,
    schemes_completed: props.prefill.schemes_completed,
});

const drawHand = () => {
    router.post(route('campaigns.aftermaths.draw-hand', props.aftermath.id), handForm.value);
};

// ───────── Phase 2 ─────────
const paydayForm = ref({
    vp: props.prefill.vp_self,
    won: props.prefill.won,
    crew_cr: props.prefill.crew_cr,
    opponent_cr: props.prefill.opponent_cr,
});

const submitPayday = () => {
    router.post(route('campaigns.aftermaths.payday', props.aftermath.id), paydayForm.value);
};

// ───────── Phase 3 (Barter) ─────────
// The barter flip + BR/suit eligibility is resolved at the table (pg 21-30).
// Here the player just searches the catalog by name and records what they
// bought; the server charges the items' cc against scrip.
const barterPurchases = ref<number[]>([]);
const barterSearch = ref('');

const barterMatches = computed(() => {
    const q = barterSearch.value.trim().toLowerCase();
    if (!q) return [];
    return equipment_catalog.value.filter((e) => e.name.toLowerCase().includes(q) && !barterPurchases.value.includes(e.id)).slice(0, 25);
});

const purchasedItems = computed(() =>
    barterPurchases.value.map((id) => equipment_catalog.value.find((e) => e.id === id)).filter((e): e is EquipmentRow => !!e),
);

const barterTotalCc = computed(() => purchasedItems.value.reduce((sum, e) => sum + (e.cc ?? 0), 0));

const addBarterItem = (id: number) => {
    if (!barterPurchases.value.includes(id)) barterPurchases.value.push(id);
    barterSearch.value = '';
};

const removeBarterItem = (id: number) => {
    const i = barterPurchases.value.indexOf(id);
    if (i >= 0) barterPurchases.value.splice(i, 1);
};

// Buys whatever is in the list (an empty list just advances — a skipped Barter).
const submitBarter = () => {
    router.post(route('campaigns.aftermaths.barter', props.aftermath.id), {
        purchases: barterPurchases.value,
    } as Record<string, unknown>);
};

// ───────── Phase 4 (Advance Leader) ─────────
const advance = () => router.post(route('campaigns.aftermaths.advance', props.aftermath.id));

interface XpBox {
    index: number;
    filled: boolean;
    tier: number | null;
}
interface LeaderActionSummary {
    index: number;
    name: string;
    category: string;
    stat: number | string | null;
}
interface EquipmentActionSummary {
    id: number;
    name: string;
    category: string;
    stat: number | string | null;
}
interface EquipmentTargetItem {
    id: number;
    name: string;
    locked: boolean;
    actions: EquipmentActionSummary[];
}
interface XpTrackPayload {
    leader_id: number;
    leader_name: string;
    tag: string | null;
    track: XpBox[];
    leader_actions: LeaderActionSummary[];
    // Attack/Tactical Mod target (pg 31, 38-43) — Totem shares the Leader's
    // own actions[] mechanism; Equipment doesn't (see EquipmentTargetItem).
    totem_id: number | null;
    totem_name: string | null;
    totem_actions: LeaderActionSummary[];
    equipment: EquipmentTargetItem[];
}
interface CatalogRow {
    id: number;
    name: string;
    flip_value?: number | null;
    body?: string;
    description?: string | null;
    is_always_available?: boolean;
    // Action/Ability tables only — the one free-choice row per chart (pg 49/51).
    is_joker?: boolean;
    // Attack Mod/Tactical Mod tables only (pg 38-43) — both true means "Any
    // Joker"; exactly one true means that specific card only.
    is_black_joker?: boolean;
    is_red_joker?: boolean;
    modifier_type?: string;
    // Skl Boost qualifying range (pg 38-43) — see modifier_type === 'skl_boost'.
    skl_from?: number | null;
    skl_from_max?: number | null;
    skl_to?: number | null;
    // Action fields (source_table: 'action' | 'summoning')
    type?: string | null;
    stat?: number | string | null;
    stat_suits?: string | null;
    stat_modifier?: string | null;
    range?: number | string | null;
    range_type?: string | null;
    resisted_by?: string | null;
    target_number?: number | string | null;
    target_suits?: string | null;
    damage?: string | null;
    stone_cost?: number;
    is_signature?: boolean;
    triggers?: Array<{ id: number; name: string; suits: string | null; stone_cost: number; description: string | null }>;
    // Ability fields (source_table: 'ability')
    suits?: string | null;
    defensive_ability_type?: string | null;
    costs_stone?: boolean;
    // Crew Card table only (pg 17-18) — whether this borrowed effect also
    // requires picking a token/marker/upgrade type from the constrained pool.
    requires_token_choice?: boolean;
    requires_marker_choice?: boolean;
    requires_upgrade_type_choice?: boolean;
    // Crew Card table only — the master this card is actually printed on
    // (null = generic, listed directly rather than via the Master cascade).
    master_id?: number | null;
    master_name?: string | null;
}
interface ChoiceOption {
    // int for a token/marker, enum-value string for an upgrade type.
    id: number | string;
    name: string;
}
interface CrewCardChoiceOptions {
    tokens: ChoiceOption[];
    markers: ChoiceOption[];
    upgrades: ChoiceOption[];
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

const xp_track = computed<XpTrackPayload | null>(() => props.xp_track ?? null);
const advancement_catalogs = computed<AdvancementCatalogs | null>(() => props.advancement_catalogs ?? null);

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

// Box identity only — user-editable state lives in advDrafts.
interface QueuedAdvancement {
    position_in_xp_track: number;
    box_tier: number;
}

// Per-advancement editable state keyed by xp_track position. Stable across
// XP form changes — v-model on computed array items loses picks whenever
// totalXp changes and rebuilds the computed.
interface AdvDraft {
    source_table: string;
    catalog_id: number | null;
    // Attack/Tactical Mod target (pg 31, 38-43) — defaults to the Leader; the
    // crew's current Totem shares the identical actions[] mechanism, while
    // Equipment targets a granted action by its real id (see target_equipment_id).
    target_type: 'leader' | 'totem' | 'equipment';
    target_equipment_id: number | null;
    applied_to_action_index: number;
    applied_to_action_id: number | null;
    totem_name: string | null;
    totem_size: number | null;
    totem_base: string | null;
    // Any Joker (Action/Ability tables, pg 49/51): "choose any action/ability on
    // a non-totem, non-master model that shares a keyword with your leader with
    // a cost of 10 or less." Resolved via the same search LeaderBuilder uses.
    is_joker_flipped: boolean;
    // Which Joker was flipped for an Attack Mod/Tactical Mod pick (pg 38-43).
    joker_color: 'red' | 'black' | null;
    free_choice_source_id: number | null;
    free_choice_source_character_id: number | null;
    free_choice_label: string | null;
    // Crew Card table only (pg 17-18): the token/marker/upgrade-type pick a
    // borrowed effect requires, if any.
    crew_card_choice_id: number | string | null;
    // Crew Card table only: true while the player is browsing a specific
    // master's own cards (the "Choose from a Master" cascade) rather than
    // picking a generic row directly from the flat list.
    crew_card_master_mode: boolean;
}

const advancementsQueued = computed<QueuedAdvancement[]>(() => {
    if (!xp_track.value) return [];
    let remaining = totalXp.value;
    const queue: QueuedAdvancement[] = [];
    for (const box of xp_track.value.track) {
        if (box.filled) continue;
        if (remaining <= 0) break;
        remaining--;
        if (box.tier !== null) {
            queue.push({ position_in_xp_track: box.index, box_tier: box.tier });
        }
    }
    return queue;
});

const advDrafts = ref<Record<number, AdvDraft>>({});
watch(
    advancementsQueued,
    (queue) => {
        const active = new Set(queue.map((a) => a.position_in_xp_track));
        for (const pos in advDrafts.value) {
            if (!active.has(Number(pos))) delete advDrafts.value[Number(pos)];
        }
        for (const adv of queue) {
            if (!(adv.position_in_xp_track in advDrafts.value)) {
                advDrafts.value[adv.position_in_xp_track] = {
                    source_table: defaultTableForTier(adv.box_tier),
                    catalog_id: null,
                    target_type: 'leader',
                    target_equipment_id: null,
                    applied_to_action_index: -1,
                    applied_to_action_id: null,
                    totem_name: null,
                    totem_size: null,
                    totem_base: null,
                    is_joker_flipped: false,
                    joker_color: null,
                    free_choice_source_id: null,
                    free_choice_source_character_id: null,
                    free_choice_label: null,
                    crew_card_choice_id: null,
                    crew_card_master_mode: false,
                };
            }
        }
    },
    { immediate: true },
);

const onSourceTableChange = (position: number) => {
    const d = advDrafts.value[position];
    if (!d) return;
    d.catalog_id = null;
    d.totem_name = null;
    d.totem_size = null;
    d.totem_base = null;
    d.target_type = 'leader';
    d.target_equipment_id = null;
    d.applied_to_action_id = null;
    d.is_joker_flipped = false;
    d.joker_color = null;
    d.free_choice_source_id = null;
    d.free_choice_source_character_id = null;
    d.free_choice_label = null;
    d.crew_card_choice_id = null;
    d.crew_card_master_mode = false;
};

const onCatalogChange = (position: number) => {
    const d = advDrafts.value[position];
    if (!d || d.catalog_id == null) return;
    if (d.source_table === 'totem' && !d.totem_name) {
        const row = catalogRowsFor('totem').find((r) => r.id === d.catalog_id);
        d.totem_name = row?.name ?? null;
    }
    // Switching away from the Any Joker row clears a stale free pick. Crew
    // Card rows carry their own master attribution via free_choice_source_
    // character_id (set by the Master cascade below) and must keep it.
    const row = catalogRowsFor(d.source_table).find((r) => r.id === d.catalog_id);
    if (!row?.is_joker && d.source_table !== 'crew_card') {
        d.free_choice_source_id = null;
        d.free_choice_source_character_id = null;
        d.free_choice_label = null;
    }
    // A newly-picked crew card may not require the same (or any) choice.
    d.crew_card_choice_id = null;
    // A newly-picked Skl Boost row may have a different qualifying range —
    // the previously-selected action might no longer be eligible.
    d.applied_to_action_index = -1;
    d.applied_to_action_id = null;
};

// ───────── Crew Card Effect: Master cascade (pg 32, 54) ─────────
// Top-level list mixes generic (master_id === null) rows with a "Choose from
// a Master" sentinel; picking that reveals a Master select, then a second
// select scoped to that master's own rows — so the stored attribution always
// matches the actual card, instead of an unrelated free-text "borrowed from".
const CREW_CARD_FROM_MASTER = '__from_master__';

const crewCardGenericRows = computed<CatalogRow[]>(() => catalogRowsFor('crew_card').filter((r) => r.master_id == null));

const crewCardRowsForMaster = (masterId: number | null): CatalogRow[] => catalogRowsFor('crew_card').filter((r) => r.master_id === masterId);

const onCrewCardTopChange = (position: number, v: string) => {
    const d = advDrafts.value[position];
    if (!d) return;
    if (v === CREW_CARD_FROM_MASTER) {
        d.crew_card_master_mode = true;
        d.catalog_id = null;
        d.free_choice_source_character_id = null;
        d.crew_card_choice_id = null;
        return;
    }
    d.crew_card_master_mode = false;
    d.free_choice_source_character_id = null;
    d.catalog_id = v === '__none__' ? null : Number(v);
    onCatalogChange(position);
};

const onCrewCardMasterChange = (position: number, v: string) => {
    const d = advDrafts.value[position];
    if (!d) return;
    d.free_choice_source_character_id = v === '__none__' ? null : Number(v);
    d.catalog_id = null;
    d.crew_card_choice_id = null;
};

const onCrewCardMasterRowChange = (position: number, v: string) => {
    const d = advDrafts.value[position];
    if (!d) return;
    d.catalog_id = v === '__none__' ? null : Number(v);
    onCatalogChange(position);
};

// ───────── Any Joker free-choice search (Action/Ability tables, pg 49/51) ─────────
const jokerSearch = ref<Record<number, string>>({});
const jokerResults = ref<Record<number, Array<{ id: number; name: string; source_id: number; source_character_id: number | null }>>>({});

const searchJokerChoice = async (position: number) => {
    const d = advDrafts.value[position];
    const q = jokerSearch.value[position] ?? '';
    if (!d || q.length < 2) {
        jokerResults.value[position] = [];
        return;
    }
    const routeName = d.source_table === 'action' ? 'campaigns.crews.leader.search.actions' : 'campaigns.crews.leader.search.abilities';
    const url = new URL(route(routeName, [props.aftermath.campaign_game.campaign_id, props.aftermath.crew.share_code]), window.location.origin);
    url.searchParams.set('q', q);
    url.searchParams.set('max_cost', '10');
    // No `type` filter — Any Joker may pick either an attack or tactical action.
    const res = await fetch(url.toString());
    if (!res.ok) return;
    const rows: Array<{ id: number; name: string; source_id: number; source_character_id: number | null }> = await res.json();
    jokerResults.value[position] = rows;
};

const pickJokerChoice = (position: number, row: { name: string; source_id: number; source_character_id: number | null }) => {
    const d = advDrafts.value[position];
    if (!d) return;
    d.free_choice_source_id = row.source_id;
    d.free_choice_source_character_id = row.source_character_id;
    d.free_choice_label = row.name;
    jokerSearch.value[position] = '';
    jokerResults.value[position] = [];
};

// Flip-value gating removed for now (pg 31, 38-51 normally cap these lists by
// the player's flipped card) — every row from the table is offered, with its
// own flip_value shown inline in the label as a reference only. Joker
// declarations (Any Joker / Joker color) are a separate mechanism and still
// gate the list, since they're not flip-value based.
const eligibleCatalogRows = (source_table: string, isJokerFlipped = false, jokerColor: 'red' | 'black' | null = null): CatalogRow[] => {
    const rows = catalogRowsFor(source_table);
    // Action/Ability: the Any Joker row is only offered when the player
    // actually declares a joker flip.
    if (source_table === 'action' || source_table === 'ability') {
        return isJokerFlipped ? rows.filter((r) => r.is_joker) : rows.filter((r) => !r.is_joker);
    }
    // Attack Mod/Tactical Mod: Joker-gated rows carry is_black_joker/is_red_joker
    // instead of a flip_value. Both flags set = "Any Joker" (either color
    // qualifies); exactly one set = that specific color only.
    if (source_table === 'attack_mod' || source_table === 'tactical_mod') {
        if (jokerColor) {
            return rows.filter((r) => (jokerColor === 'red' && r.is_red_joker) || (jokerColor === 'black' && r.is_black_joker));
        }
        return rows.filter((r) => !r.is_black_joker && !r.is_red_joker);
    }
    return rows;
};

function defaultTableForTier(tier: number): string {
    if (tier === 1) return 'attack_mod';
    if (tier === 2) return 'action';
    if (tier === 3) return 'totem';
    return 'crew_card';
}

const tableOptionsForTier = (tier: number) => {
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

const selectedDraftRow = (position: number): CatalogRow | null => {
    const d = advDrafts.value[position];
    if (!d || d.catalog_id == null) return null;
    return catalogRowsFor(d.source_table).find((r) => r.id === d.catalog_id) ?? null;
};

const equipmentFor = (equipmentId: number | null): EquipmentTargetItem | null =>
    (xp_track.value?.equipment ?? []).find((e) => e.id === equipmentId) ?? null;

// Crew Card table only (pg 17-18): whether the selected borrowed effect also
// requires picking a token/marker/upgrade type, and the constrained pool it
// picks from (same pool Starting Arsenal uses).
const requiredCrewCardChoiceType = (position: number): 'token' | 'marker' | 'upgrade' | null => {
    const row = selectedDraftRow(position);
    if (!row) return null;
    if (row.requires_token_choice) return 'token';
    if (row.requires_marker_choice) return 'marker';
    if (row.requires_upgrade_type_choice) return 'upgrade';
    return null;
};
const crewCardChoiceOptionsFor = (position: number): ChoiceOption[] => {
    const type = requiredCrewCardChoiceType(position);
    const options = props.crew_card_choice_options ?? { tokens: [], markers: [], upgrades: [] };
    if (type === 'token') return options.tokens;
    if (type === 'marker') return options.markers;
    if (type === 'upgrade') return options.upgrades;
    return [];
};

// Skl Boost rows (pg 38-43) only offer actions whose current Skl falls in the
// row's qualifying range ("select one attack with a Skl of 0 or 1").
const sklBoostEligible = (row: CatalogRow | null, stat: number | string | null): boolean => {
    if (!row || row.modifier_type !== 'skl_boost' || row.skl_from == null) return true;
    const current = Number(stat);
    if (stat == null || Number.isNaN(current)) return false;
    const max = row.skl_from_max ?? row.skl_from;

    return current >= row.skl_from && current <= max;
};

// The category-filtered, Skl-Boost-eligible action options for the draft,
// sourced from whichever target (Leader/Totem/Equipment) is selected. `ref`
// is an actions[] array index for Leader/Totem, or the real actions.id for
// Equipment (it has no per-instance actions[] to index into).
const targetActionOptions = (position: number): Array<{ ref: number; name: string; category: string; stat: number | string | null }> => {
    const d = advDrafts.value[position];
    if (!d) return [];
    const category = d.source_table === 'attack_mod' ? 'attack' : 'tactical';
    const row = selectedDraftRow(position);
    const source =
        d.target_type === 'totem'
            ? (xp_track.value?.totem_actions ?? []).map((a) => ({ ref: a.index, name: a.name, category: a.category, stat: a.stat }))
            : d.target_type === 'equipment'
              ? (equipmentFor(d.target_equipment_id)?.actions ?? []).map((a) => ({ ref: a.id, name: a.name, category: a.category, stat: a.stat }))
              : (xp_track.value?.leader_actions ?? []).map((a) => ({ ref: a.index, name: a.name, category: a.category, stat: a.stat }));

    return source.filter((a) => a.category === category && sklBoostEligible(row, a.stat));
};

const submitAdvanceLeader = () => {
    router.post(route('campaigns.aftermaths.advance-leader', props.aftermath.id), {
        bruiser_killed_non_peon: xpForm.value.bruiser_killed,
        strategist_interacted: xpForm.value.strategist_interacted,
        lost: xpForm.value.lost,
        advancements: advancementsQueued.value
            .filter((adv) => {
                const d = advDrafts.value[adv.position_in_xp_track];
                return d && d.catalog_id !== null;
            })
            .map((adv) => {
                const d = advDrafts.value[adv.position_in_xp_track]!;
                const isTotemAdvancement = d.source_table === 'totem';
                const isTrigger = d.source_table === 'attack_mod' || d.source_table === 'tactical_mod';
                const isAbility = d.source_table === 'ability';
                const isEquipmentTarget = isTrigger && d.target_type === 'equipment';
                return {
                    source_table: d.source_table,
                    catalog_id: d.catalog_id,
                    applied_to_action_index: isTrigger && !isEquipmentTarget ? d.applied_to_action_index : undefined,
                    applied_to_action_id: isEquipmentTarget ? d.applied_to_action_id : undefined,
                    applied_to_custom_character_id:
                        (isTrigger || isAbility) && d.target_type === 'totem' ? (xp_track.value?.totem_id ?? undefined) : undefined,
                    from_equipment_id: isEquipmentTarget ? (d.target_equipment_id ?? undefined) : undefined,
                    joker_color: isTrigger ? d.joker_color : undefined,
                    position_in_xp_track: adv.position_in_xp_track,
                    free_choice:
                        d.free_choice_source_id || d.free_choice_source_character_id
                            ? { source_id: d.free_choice_source_id, source_character_id: d.free_choice_source_character_id }
                            : null,
                    totem_name: isTotemAdvancement ? d.totem_name || null : null,
                    totem_size: isTotemAdvancement ? d.totem_size || null : null,
                    totem_base: isTotemAdvancement ? d.totem_base || null : null,
                    crew_card_choice: d.crew_card_choice_id !== null ? { id: d.crew_card_choice_id } : null,
                };
            }),
    } as Record<string, unknown>);
};

// ───────── Phase 5 (Doctor) ─────────
// The doctor flip is made at the table (pg 33); the player picks the result row
// they got. An "Oops" result then picks the added injury from the catalog.
interface DoctorAttempt {
    injury_pivot_id: number;
    result_id: number | null;
    cheated: boolean;
    added_injury_upgrade_id: number | null;
    // Lucky Miss table result rolled after a red-joker annihilation.
    lucky_miss_flip_value: number;
    // The Lucky Miss flip was itself a joker → Doppelganger.
    lucky_miss_is_joker: boolean;
}
const doctorAttempts = ref<DoctorAttempt[]>([]);

const addDoctorAttempt = (pivotId: number) => {
    doctorAttempts.value.push({
        injury_pivot_id: pivotId,
        result_id: null,
        cheated: false,
        added_injury_upgrade_id: null,
        lucky_miss_flip_value: 1,
        lucky_miss_is_joker: false,
    });
};

const doctorAttemptLabel = (pivotId: number): string => {
    const inj = crew_injuries.value.find((i) => i.pivot_id === pivotId);
    if (!inj) return `injury pivot #${pivotId}`;
    const label = inj.label ? ` (${inj.label})` : '';
    return `${inj.display_name}${label} — ${inj.injury_name}`;
};

const removeDoctorAttempt = (idx: number) => doctorAttempts.value.splice(idx, 1);

const submitDoctor = () => {
    router.post(route('campaigns.aftermaths.doctor', props.aftermath.id), {
        attempts: doctorAttempts.value,
    } as Record<string, unknown>);
};

interface DoctorResultRow {
    id: number;
    name: string;
    body: string;
    outcome_kind: string;
}
const doctor_results = computed<DoctorResultRow[]>(() => props.doctor_results ?? []);
const doctorOutcome = (resultId: number | null) =>
    resultId == null ? null : (doctor_results.value.find((r) => r.id === resultId)?.outcome_kind ?? null);

// ───────── Phase 6 ─────────
// The injury flip is made at the table (pg 34-36); the player picks the injury
// that resulted directly from the catalog. Jokers stay explicit choices.
interface InjuryEntry {
    arsenal_model_id: number | null;
    custom_character_id: number | null;
    // The chosen injury upgrade (null until picked / when a joker is flipped).
    injury_upgrade_id: number | null;
    is_red_joker: boolean;
    // Black joker = Traitor (the model defects).
    is_black_joker: boolean;
    // Lucky Miss table result rolled when the kill flip is a red joker.
    lucky_miss_flip_value: number;
    // The Lucky Miss flip was itself a joker → Doppelganger.
    lucky_miss_is_joker: boolean;
    // The crew a Traitor defector joins (null = the game's recorded opponent,
    // or nowhere if there is none — e.g. a solo game with no opponent picked).
    traitor_target_crew_id: number | null;
}
const injuryFlips = ref<InjuryEntry[]>([]);
const addInjuryFlip = (m: KilledModelRow) => {
    injuryFlips.value.push({
        arsenal_model_id: m.custom_character_id !== null ? null : m.id,
        custom_character_id: m.custom_character_id ?? null,
        injury_upgrade_id: null,
        is_red_joker: false,
        is_black_joker: false,
        lucky_miss_flip_value: 1,
        lucky_miss_is_joker: false,
        // Default to the campaign's first other crew so a defection always has
        // a destination; the player can change it.
        traitor_target_crew_id: traitorTargetCrews.value[0]?.id ?? null,
    });
};
const removeInjuryFlip = (idx: number) => injuryFlips.value.splice(idx, 1);

// Resolve a flip entry to a human-readable name for the Pending Injuries list.
const modelDisplayName = (f: InjuryEntry): string => {
    const m =
        f.custom_character_id !== null
            ? props.killed_models.find((k) => k.custom_character_id === f.custom_character_id)
            : props.killed_models.find((k) => k.id === f.arsenal_model_id);
    if (!m) return f.custom_character_id !== null ? `Custom char #${f.custom_character_id}` : `Model #${f.arsenal_model_id}`;
    return m.display_name || (m.label ? `${m.character?.display_name ?? 'Model'} (${m.label})` : (m.character?.display_name ?? 'Model'));
};

interface InjuryCatalogRow {
    id: number;
    name: string;
    suit_pool: string | null;
    flip_value: number | null;
}
const injury_catalog = computed<InjuryCatalogRow[]>(() => props.injury_catalog ?? []);

interface TraitorCrewRow {
    id: number;
    name: string;
}
const traitorTargetCrews = computed<TraitorCrewRow[]>(() => props.traitor_target_crews ?? []);

// Optional per-game journal entry (not a rules mechanic) — shown
// chronologically on the Arsenal Sheet.
const storyEntry = ref('');

const submitInjuries = () => {
    router.post(route('campaigns.aftermaths.determine-injuries', props.aftermath.id), {
        flips: injuryFlips.value,
        story_entry: storyEntry.value || null,
    } as Record<string, unknown>);
};

const finalize = () =>
    router.post(route('campaigns.aftermaths.finalize', props.aftermath.id), {
        story_entry: storyEntry.value || null,
    });
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
                <h1 class="mt-1 text-3xl font-black tracking-tight">Aftermath</h1>
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
                    Make your barter flips at the table (pg 21–30), then record what you bought below — search the catalog by name and add each item.
                    Its cost is charged to your scrip.
                </p>
            </CardHeader>
            <CardContent class="space-y-3">
                <div>
                    <Label>Purchase equipment</Label>
                    <Input v-model="barterSearch" placeholder="Search equipment by name…" :disabled="!is_owner" />
                    <ul v-if="barterMatches.length" class="mt-1 max-h-56 space-y-1 overflow-y-auto rounded-md border p-1">
                        <li v-for="item in barterMatches" :key="item.id" class="rounded-sm px-2 py-1.5 text-sm hover:bg-muted/50">
                            <div class="flex items-center justify-between gap-2">
                                <div class="min-w-0 flex-1">
                                    <p class="truncate font-medium">{{ item.name }}</p>
                                    <p class="text-[10px] text-muted-foreground">
                                        BR {{ item.is_always_available ? 'Always' : (item.br ?? '—') }} • CC {{ item.cc }}
                                    </p>
                                </div>
                                <Button size="sm" variant="outline" :disabled="!is_owner" @click="addBarterItem(item.id)">Add</Button>
                            </div>
                            <p v-if="item.body" class="mt-1 text-[11px] leading-relaxed text-muted-foreground">
                                <GameText :text="item.body" />
                            </p>
                        </li>
                    </ul>
                    <p v-else-if="barterSearch.trim()" class="mt-1 text-[11px] text-muted-foreground">No equipment matches that name.</p>
                </div>

                <div v-if="purchasedItems.length" class="rounded-md border p-3">
                    <p class="mb-2 text-xs font-medium uppercase text-muted-foreground">Purchasing</p>
                    <ul class="space-y-1">
                        <li
                            v-for="item in purchasedItems"
                            :key="item.id"
                            class="flex items-center justify-between rounded-sm border px-2 py-1.5 text-sm"
                        >
                            <div class="min-w-0 flex-1">
                                <p class="truncate font-medium">{{ item.name }}</p>
                                <p class="text-[10px] text-muted-foreground">CC {{ item.cc }}</p>
                            </div>
                            <Button size="sm" variant="ghost" :disabled="!is_owner" @click="removeBarterItem(item.id)">Remove</Button>
                        </li>
                    </ul>
                </div>

                <div class="flex flex-wrap items-center justify-between gap-2">
                    <span class="text-sm">
                        Total:
                        <Badge variant="outline" class="text-[10px] tabular-nums">{{ barterTotalCc }} / {{ aftermath.crew.scrip }} scrip</Badge>
                    </span>
                    <Button :disabled="!is_owner || barterTotalCc > aftermath.crew.scrip" @click="submitBarter">
                        {{ purchasedItems.length ? 'Confirm purchases &amp; advance' : 'Skip — buy nothing' }}
                    </Button>
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
                        <!-- Show the option matching the leader's tag; if the tag is
                             unknown (older leader / not set), fall back to both and
                             explain why so the player can set it on the leader. -->
                        <p
                            v-if="!xp_track?.tag"
                            class="rounded-md border border-amber-500/40 bg-amber-500/10 p-2 text-xs text-amber-700 dark:text-amber-400"
                        >
                            This leader has no Bruiser/Strategist tag set, so both XP options are shown. Pick the leader's tag on the
                            <Link
                                :href="route('campaigns.crews.leader.edit', [aftermath.campaign_game.campaign.id, aftermath.crew.share_code])"
                                class="font-medium underline"
                                >Edit Leader</Link
                            >
                            page so only the correct one appears.
                        </p>
                        <label v-if="!xp_track?.tag || xp_track.tag === 'bruiser'" class="flex items-start gap-2">
                            <Checkbox :checked="xpForm.bruiser_killed" @update:checked="(v: boolean) => (xpForm.bruiser_killed = v)" />
                            <span>+1 Bruiser killed a non-peon enemy</span>
                        </label>
                        <label v-if="!xp_track?.tag || xp_track.tag === 'strategist'" class="flex items-start gap-2">
                            <Checkbox :checked="xpForm.strategist_interacted" @update:checked="(v: boolean) => (xpForm.strategist_interacted = v)" />
                            <span>+1 Strategist Interacted in enemy DZ</span>
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
                        <template v-if="advDrafts[adv.position_in_xp_track]">
                            <Select
                                :model-value="advDrafts[adv.position_in_xp_track].source_table"
                                @update:model-value="
                                    (v) => {
                                        advDrafts[adv.position_in_xp_track].source_table = v as string;
                                        onSourceTableChange(adv.position_in_xp_track);
                                    }
                                "
                            >
                                <SelectTrigger class="h-8 w-full text-xs text-foreground">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="opt in tableOptionsForTier(adv.box_tier)" :key="opt.value" :value="opt.value">
                                        {{ opt.label }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <label
                                v-if="
                                    advDrafts[adv.position_in_xp_track].source_table === 'action' ||
                                    advDrafts[adv.position_in_xp_track].source_table === 'ability'
                                "
                                class="flex items-center gap-2 text-[11px] text-muted-foreground"
                            >
                                <Checkbox
                                    :checked="advDrafts[adv.position_in_xp_track].is_joker_flipped"
                                    @update:checked="
                                        (v: boolean) => {
                                            advDrafts[adv.position_in_xp_track].is_joker_flipped = v;
                                            advDrafts[adv.position_in_xp_track].catalog_id = null;
                                            advDrafts[adv.position_in_xp_track].free_choice_source_id = null;
                                            advDrafts[adv.position_in_xp_track].free_choice_source_character_id = null;
                                            advDrafts[adv.position_in_xp_track].free_choice_label = null;
                                        }
                                    "
                                />
                                I flipped a Joker
                            </label>
                            <label
                                v-if="
                                    advDrafts[adv.position_in_xp_track].source_table === 'attack_mod' ||
                                    advDrafts[adv.position_in_xp_track].source_table === 'tactical_mod'
                                "
                                class="flex items-center gap-2 text-[11px] text-muted-foreground"
                            >
                                Joker flipped
                                <Select
                                    :model-value="advDrafts[adv.position_in_xp_track].joker_color ?? 'none'"
                                    @update:model-value="
                                        (v) => {
                                            advDrafts[adv.position_in_xp_track].joker_color = v === 'none' ? null : (v as 'red' | 'black');
                                            advDrafts[adv.position_in_xp_track].catalog_id = null;
                                        }
                                    "
                                >
                                    <SelectTrigger class="h-8 w-auto gap-1 text-xs text-foreground">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="none">None</SelectItem>
                                        <SelectItem value="red">Red Joker</SelectItem>
                                        <SelectItem value="black">Black Joker</SelectItem>
                                    </SelectContent>
                                </Select>
                            </label>
                            <Select
                                v-if="advDrafts[adv.position_in_xp_track].source_table !== 'crew_card'"
                                :model-value="advDrafts[adv.position_in_xp_track].catalog_id?.toString() ?? '__none__'"
                                @update:model-value="
                                    (v) => {
                                        advDrafts[adv.position_in_xp_track].catalog_id = v === '__none__' ? null : Number(v);
                                        onCatalogChange(adv.position_in_xp_track);
                                    }
                                "
                            >
                                <SelectTrigger class="h-8 w-full text-xs text-foreground">
                                    <SelectValue placeholder="— pick a row —" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="__none__">— pick a row —</SelectItem>
                                    <SelectItem
                                        v-for="row in eligibleCatalogRows(
                                            advDrafts[adv.position_in_xp_track].source_table,
                                            advDrafts[adv.position_in_xp_track].is_joker_flipped,
                                            advDrafts[adv.position_in_xp_track].joker_color,
                                        )"
                                        :key="row.id"
                                        :value="row.id.toString()"
                                    >
                                        {{ row.name }}<span v-if="row.flip_value != null"> (flip {{ row.flip_value }})</span>
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <!-- Crew Card: flat list of generic effects, or cascade Master → their own card (pg 32, 54) -->
                            <div v-if="advDrafts[adv.position_in_xp_track].source_table === 'crew_card'" class="space-y-2">
                                <Select
                                    :model-value="
                                        advDrafts[adv.position_in_xp_track].crew_card_master_mode
                                            ? CREW_CARD_FROM_MASTER
                                            : (advDrafts[adv.position_in_xp_track].catalog_id?.toString() ?? '__none__')
                                    "
                                    @update:model-value="(v) => onCrewCardTopChange(adv.position_in_xp_track, v as string)"
                                >
                                    <SelectTrigger class="h-8 w-full text-xs text-foreground">
                                        <SelectValue placeholder="— pick a row —" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="__none__">— pick a row —</SelectItem>
                                        <SelectItem :value="CREW_CARD_FROM_MASTER">Choose from a Master…</SelectItem>
                                        <SelectItem v-for="row in crewCardGenericRows" :key="row.id" :value="row.id.toString()">
                                            {{ row.name }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <div v-if="advDrafts[adv.position_in_xp_track].crew_card_master_mode" class="space-y-2 rounded border p-2">
                                    <div>
                                        <Label class="text-[11px] text-muted-foreground">Master</Label>
                                        <Select
                                            :model-value="
                                                advDrafts[adv.position_in_xp_track].free_choice_source_character_id?.toString() ?? '__none__'
                                            "
                                            @update:model-value="(v) => onCrewCardMasterChange(adv.position_in_xp_track, v as string)"
                                        >
                                            <SelectTrigger class="h-8 w-full text-xs text-foreground">
                                                <SelectValue placeholder="— pick a master —" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="__none__">— pick a master —</SelectItem>
                                                <SelectItem v-for="m in eligible_masters ?? []" :key="m.id" :value="m.id.toString()">{{
                                                    m.name
                                                }}</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div v-if="advDrafts[adv.position_in_xp_track].free_choice_source_character_id !== null">
                                        <Label class="text-[11px] text-muted-foreground">Their Crew Card</Label>
                                        <Select
                                            :model-value="advDrafts[adv.position_in_xp_track].catalog_id?.toString() ?? '__none__'"
                                            @update:model-value="(v) => onCrewCardMasterRowChange(adv.position_in_xp_track, v as string)"
                                        >
                                            <SelectTrigger class="h-8 w-full text-xs text-foreground">
                                                <SelectValue placeholder="— pick a card —" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="__none__">— pick a card —</SelectItem>
                                                <SelectItem
                                                    v-for="row in crewCardRowsForMaster(
                                                        advDrafts[adv.position_in_xp_track].free_choice_source_character_id,
                                                    )"
                                                    :key="row.id"
                                                    :value="row.id.toString()"
                                                    >{{ row.name }}</SelectItem
                                                >
                                            </SelectContent>
                                        </Select>
                                        <p
                                            v-if="
                                                crewCardRowsForMaster(advDrafts[adv.position_in_xp_track].free_choice_source_character_id).length ===
                                                0
                                            "
                                            class="mt-1 text-[11px] text-muted-foreground"
                                        >
                                            No Crew Card catalog rows are linked to this master yet.
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <!-- Any Joker: search for the free action/ability pick (non-master/totem ally, cost <= 10, pg 49/51) -->
                            <div
                                v-if="advDrafts[adv.position_in_xp_track].is_joker_flipped && advDrafts[adv.position_in_xp_track].catalog_id !== null"
                                class="space-y-1 rounded border p-2"
                            >
                                <p v-if="advDrafts[adv.position_in_xp_track].free_choice_label" class="text-xs font-medium">
                                    Picked: {{ advDrafts[adv.position_in_xp_track].free_choice_label }}
                                    <button
                                        type="button"
                                        class="ml-2 text-[10px] text-muted-foreground underline"
                                        @click="
                                            advDrafts[adv.position_in_xp_track].free_choice_source_id = null;
                                            advDrafts[adv.position_in_xp_track].free_choice_source_character_id = null;
                                            advDrafts[adv.position_in_xp_track].free_choice_label = null;
                                        "
                                    >
                                        change
                                    </button>
                                </p>
                                <template v-else>
                                    <Input
                                        v-model="jokerSearch[adv.position_in_xp_track]"
                                        placeholder="Search actions/abilities on an eligible ally (cost ≤ 10)…"
                                        class="h-8 text-xs"
                                        @input="searchJokerChoice(adv.position_in_xp_track)"
                                    />
                                    <ul v-if="jokerResults[adv.position_in_xp_track]?.length" class="max-h-40 space-y-1 overflow-y-auto text-xs">
                                        <li
                                            v-for="r in jokerResults[adv.position_in_xp_track]"
                                            :key="r.id"
                                            class="cursor-pointer rounded px-2 py-1 hover:bg-muted"
                                            @click="pickJokerChoice(adv.position_in_xp_track, r)"
                                        >
                                            {{ r.name }}
                                        </li>
                                    </ul>
                                </template>
                            </div>
                            <!-- Totem: ask for name, size, and base to create the totem card -->
                            <div
                                v-if="
                                    advDrafts[adv.position_in_xp_track].source_table === 'totem' &&
                                    advDrafts[adv.position_in_xp_track].catalog_id !== null
                                "
                                class="grid grid-cols-3 gap-2"
                            >
                                <div>
                                    <Label class="text-[11px] text-muted-foreground">Totem name</Label>
                                    <Input v-model="advDrafts[adv.position_in_xp_track].totem_name" placeholder="e.g. Rat King" class="h-8 text-xs" />
                                </div>
                                <div>
                                    <Label class="text-[11px] text-muted-foreground">Size (stat)</Label>
                                    <Input
                                        type="number"
                                        min="1"
                                        max="5"
                                        v-model.number="advDrafts[adv.position_in_xp_track].totem_size"
                                        placeholder="1"
                                        class="h-8 text-xs"
                                    />
                                </div>
                                <div>
                                    <Label class="text-[11px] text-muted-foreground">Base</Label>
                                    <Select v-model="advDrafts[adv.position_in_xp_track].totem_base">
                                        <SelectTrigger class="h-8 w-full text-xs text-foreground">
                                            <SelectValue placeholder="— pick —" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="30mm">30mm</SelectItem>
                                            <SelectItem value="40mm">40mm</SelectItem>
                                            <SelectItem value="50mm">50mm</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                            </div>
                            <!-- Crew Card: constrained pick for a chosen effect that itself requires a token/marker/upgrade (pg 17-18). -->
                            <div
                                v-if="
                                    advDrafts[adv.position_in_xp_track].source_table === 'crew_card' &&
                                    advDrafts[adv.position_in_xp_track].catalog_id !== null &&
                                    requiredCrewCardChoiceType(adv.position_in_xp_track)
                                "
                                class="rounded-md border border-primary/30 bg-primary/5 p-2"
                            >
                                <Label class="text-[11px] font-medium">
                                    Choose a {{ requiredCrewCardChoiceType(adv.position_in_xp_track) }} — listed on a crew card of a master sharing
                                    your keywords
                                </Label>
                                <Select
                                    :model-value="advDrafts[adv.position_in_xp_track].crew_card_choice_id?.toString() ?? '__none__'"
                                    @update:model-value="
                                        (v) =>
                                            (advDrafts[adv.position_in_xp_track].crew_card_choice_id =
                                                v === '__none__'
                                                    ? null
                                                    : (crewCardChoiceOptionsFor(adv.position_in_xp_track).find((o) => o.id.toString() === v)?.id ??
                                                      null))
                                    "
                                >
                                    <SelectTrigger class="mt-1 h-8 w-full text-xs text-foreground">
                                        <SelectValue :placeholder="`— pick a ${requiredCrewCardChoiceType(adv.position_in_xp_track)} —`" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="__none__"
                                            >— pick a {{ requiredCrewCardChoiceType(adv.position_in_xp_track) }} —</SelectItem
                                        >
                                        <SelectItem
                                            v-for="opt in crewCardChoiceOptionsFor(adv.position_in_xp_track)"
                                            :key="opt.id"
                                            :value="opt.id.toString()"
                                            >{{ opt.name }}</SelectItem
                                        >
                                    </SelectContent>
                                </Select>
                                <p
                                    v-if="crewCardChoiceOptionsFor(adv.position_in_xp_track).length === 0"
                                    class="mt-1 text-[11px] text-muted-foreground"
                                >
                                    No eligible {{ requiredCrewCardChoiceType(adv.position_in_xp_track) }}s found for your keywords.
                                </p>
                            </div>
                            <!-- Attack/tactical mod: pick what to affect (Leader/Totem/Equipment), then which action -->
                            <div
                                v-if="
                                    (advDrafts[adv.position_in_xp_track].source_table === 'attack_mod' ||
                                        advDrafts[adv.position_in_xp_track].source_table === 'tactical_mod') &&
                                    advDrafts[adv.position_in_xp_track].catalog_id !== null
                                "
                                class="space-y-2"
                            >
                                <div class="flex flex-wrap items-center gap-2">
                                    <Label class="shrink-0 text-[11px] text-muted-foreground">Affects:</Label>
                                    <Select
                                        :model-value="advDrafts[adv.position_in_xp_track].target_type"
                                        @update:model-value="
                                            (v) => {
                                                advDrafts[adv.position_in_xp_track].target_type = v as 'leader' | 'totem' | 'equipment';
                                                advDrafts[adv.position_in_xp_track].target_equipment_id = null;
                                                advDrafts[adv.position_in_xp_track].applied_to_action_index = -1;
                                                advDrafts[adv.position_in_xp_track].applied_to_action_id = null;
                                            }
                                        "
                                    >
                                        <SelectTrigger class="h-8 w-auto gap-1 text-xs text-foreground">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="leader">Leader</SelectItem>
                                            <SelectItem v-if="xp_track?.totem_id" value="totem">Totem</SelectItem>
                                            <SelectItem v-if="xp_track?.equipment?.length" value="equipment">Equipment</SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <Select
                                        v-if="advDrafts[adv.position_in_xp_track].target_type === 'equipment'"
                                        :model-value="advDrafts[adv.position_in_xp_track].target_equipment_id?.toString() ?? '__none__'"
                                        @update:model-value="
                                            (v) => {
                                                advDrafts[adv.position_in_xp_track].target_equipment_id = v === '__none__' ? null : Number(v);
                                                advDrafts[adv.position_in_xp_track].applied_to_action_index = -1;
                                                advDrafts[adv.position_in_xp_track].applied_to_action_id = null;
                                            }
                                        "
                                    >
                                        <SelectTrigger class="h-8 min-w-0 flex-1 text-xs text-foreground">
                                            <SelectValue placeholder="— pick equipment —" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="__none__">— pick equipment —</SelectItem>
                                            <SelectItem v-for="eq in xp_track?.equipment ?? []" :key="eq.id" :value="eq.id.toString()">
                                                {{ eq.name }}{{ eq.locked ? ' 🔒' : '' }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div v-if="targetActionOptions(adv.position_in_xp_track).length">
                                    <Label class="text-[11px] text-muted-foreground">Add trigger to action</Label>
                                    <Select
                                        :model-value="
                                            (advDrafts[adv.position_in_xp_track].target_type === 'equipment'
                                                ? advDrafts[adv.position_in_xp_track].applied_to_action_id
                                                : advDrafts[adv.position_in_xp_track].applied_to_action_index
                                            )?.toString() ?? '-1'
                                        "
                                        @update:model-value="
                                            (v) => {
                                                const val = Number(v);
                                                if (advDrafts[adv.position_in_xp_track].target_type === 'equipment') {
                                                    advDrafts[adv.position_in_xp_track].applied_to_action_id = val;
                                                } else {
                                                    advDrafts[adv.position_in_xp_track].applied_to_action_index = val;
                                                }
                                            }
                                        "
                                    >
                                        <SelectTrigger class="h-8 w-full text-xs text-foreground">
                                            <SelectValue placeholder="— select action —" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="-1">— select action —</SelectItem>
                                            <SelectItem
                                                v-for="a in targetActionOptions(adv.position_in_xp_track)"
                                                :key="a.ref"
                                                :value="a.ref.toString()"
                                            >
                                                {{ a.name }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                            </div>
                            <!-- Ability: pick what to affect (Leader/Totem) -->
                            <div
                                v-if="
                                    advDrafts[adv.position_in_xp_track].source_table === 'ability' &&
                                    advDrafts[adv.position_in_xp_track].catalog_id !== null
                                "
                                class="flex flex-wrap items-center gap-2"
                            >
                                <Label class="shrink-0 text-[11px] text-muted-foreground">Affects:</Label>
                                <Select
                                    :model-value="
                                        advDrafts[adv.position_in_xp_track].target_type === 'equipment'
                                            ? 'leader'
                                            : advDrafts[adv.position_in_xp_track].target_type
                                    "
                                    @update:model-value="(v) => (advDrafts[adv.position_in_xp_track].target_type = v as 'leader' | 'totem')"
                                >
                                    <SelectTrigger class="h-8 w-auto gap-1 text-xs text-foreground">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="leader">Leader</SelectItem>
                                        <SelectItem v-if="xp_track?.totem_id" value="totem">Totem</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <!-- Full card preview for the selected advancement -->
                            <template v-if="selectedDraftRow(adv.position_in_xp_track)">
                                <ActionCard
                                    v-if="
                                        advDrafts[adv.position_in_xp_track].source_table === 'action' ||
                                        advDrafts[adv.position_in_xp_track].source_table === 'summoning'
                                    "
                                    :action="selectedDraftRow(adv.position_in_xp_track)!"
                                    :hide-footer="true"
                                />
                                <AbilityCard
                                    v-else-if="advDrafts[adv.position_in_xp_track].source_table === 'ability'"
                                    :ability="selectedDraftRow(adv.position_in_xp_track)!"
                                    :hide-footer="true"
                                />
                                <TriggerCard
                                    v-else-if="
                                        advDrafts[adv.position_in_xp_track].source_table === 'attack_mod' ||
                                        advDrafts[adv.position_in_xp_track].source_table === 'tactical_mod'
                                    "
                                    :trigger="selectedDraftRow(adv.position_in_xp_track)!"
                                >
                                    <template #footer></template>
                                </TriggerCard>
                                <p
                                    v-else-if="selectedDraftRow(adv.position_in_xp_track)?.body"
                                    class="rounded-md border p-2 text-xs leading-relaxed text-muted-foreground"
                                >
                                    <GameText :text="selectedDraftRow(adv.position_in_xp_track)!.body!" />
                                </p>
                            </template>
                        </template>
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
                    Pay 1 scrip per attempt and flip on the doctor table (pg 33), then pick the result you got. The doctor keeps the scrip regardless.
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
                            <span class="flex-1 truncate">{{ doctorAttemptLabel(att.injury_pivot_id) }}</span>
                            <Select
                                :model-value="att.result_id?.toString() ?? '__none__'"
                                @update:model-value="(v) => (att.result_id = v === '__none__' ? null : Number(v))"
                            >
                                <SelectTrigger class="h-8 flex-1 text-xs text-foreground">
                                    <SelectValue placeholder="— pick the result —" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="__none__">— pick the result —</SelectItem>
                                    <SelectItem v-for="r in doctor_results" :key="r.id" :value="r.id.toString()">{{ r.name }}</SelectItem>
                                </SelectContent>
                            </Select>
                            <!-- "Oops" / flip-9 reflip — pick the new injury that was added. -->
                            <Select
                                v-if="doctorOutcome(att.result_id) === 'added_injury' || doctorOutcome(att.result_id) === 'removed_and_reflip'"
                                :model-value="att.added_injury_upgrade_id?.toString() ?? '__none__'"
                                @update:model-value="(v) => (att.added_injury_upgrade_id = v === '__none__' ? null : Number(v))"
                            >
                                <SelectTrigger class="h-8 flex-1 text-xs text-foreground">
                                    <SelectValue placeholder="— added injury —" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="__none__">— added injury —</SelectItem>
                                    <SelectItem v-for="inj in injury_catalog" :key="inj.id" :value="inj.id.toString()">{{ inj.name }}</SelectItem>
                                </SelectContent>
                            </Select>
                            <!-- Red Joker → Lucky Miss table (or Doppelganger). -->
                            <template v-if="doctorOutcome(att.result_id) === 'lucky_miss_reflip'">
                                <label class="flex items-center gap-1 text-[11px] text-muted-foreground">
                                    <Checkbox :checked="att.lucky_miss_is_joker" @update:checked="(v: boolean) => (att.lucky_miss_is_joker = v)" />
                                    Joker (Doppelganger)
                                </label>
                                <template v-if="!att.lucky_miss_is_joker">
                                    <span class="text-[11px] text-muted-foreground">Lucky Miss flip</span>
                                    <Input type="number" min="1" max="13" v-model.number="att.lucky_miss_flip_value" class="h-8 w-16" />
                                </template>
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
                    For each non-peon model killed this game, resolve the injury flip at the table (pg 34–35), then add the injury that resulted and
                    pick it from the list. Models hitting 3+ injuries are annihilated.
                </p>
            </CardHeader>
            <CardContent class="space-y-3">
                <p v-if="kills_are_authoritative" class="text-xs uppercase text-muted-foreground">Models killed this game (from the tracker)</p>
                <p v-else class="rounded-md border border-dashed bg-muted/20 p-2 text-xs text-muted-foreground">
                    No tracker data for this game — the full roster is shown below. Flip <strong>only</strong> for the non-peon models that were
                    actually killed this game (pg 34).
                </p>
                <ul class="space-y-1">
                    <li
                        v-for="m in killed_models"
                        :key="`${m.custom_character_id ?? 'a'}-${m.id}`"
                        class="flex items-center justify-between rounded-md border p-2 text-sm"
                    >
                        <span>
                            {{ m.display_name || m.character?.display_name || '—' }}
                            <span v-if="m.label" class="ml-1 text-[10px] text-muted-foreground">({{ m.label }})</span>
                        </span>
                        <Button size="sm" variant="outline" :disabled="!is_owner" @click="addInjuryFlip(m)">Add injury</Button>
                    </li>
                </ul>

                <div v-if="injuryFlips.length" class="rounded-md border p-3">
                    <p class="mb-2 text-xs font-medium uppercase text-muted-foreground">Pending Injuries</p>
                    <ul class="space-y-2">
                        <li v-for="(f, idx) in injuryFlips" :key="idx" class="flex flex-wrap items-center gap-2 text-sm">
                            <span class="flex-1 truncate font-medium">{{ modelDisplayName(f) }}</span>
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
                                <span class="text-[11px] text-muted-foreground">Defects to</span>
                                <Select
                                    v-if="traitorTargetCrews.length"
                                    :model-value="f.traitor_target_crew_id?.toString() ?? undefined"
                                    @update:model-value="(v) => (f.traitor_target_crew_id = v ? Number(v) : null)"
                                >
                                    <SelectTrigger class="h-8 w-auto gap-1 text-xs text-foreground">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem v-for="c in traitorTargetCrews" :key="c.id" :value="c.id.toString()">{{ c.name }}</SelectItem>
                                    </SelectContent>
                                </Select>
                                <span v-else class="text-[11px] text-amber-600">no other crew in this campaign — the model is just removed</span>
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
                                <Select
                                    :model-value="f.injury_upgrade_id?.toString() ?? '__none__'"
                                    @update:model-value="(v) => (f.injury_upgrade_id = v === '__none__' ? null : Number(v))"
                                >
                                    <SelectTrigger class="h-8 flex-1 text-xs text-foreground">
                                        <SelectValue placeholder="— pick the injury —" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="__none__">— pick the injury —</SelectItem>
                                        <SelectItem v-for="inj in injury_catalog" :key="inj.id" :value="inj.id.toString()">{{ inj.name }}</SelectItem>
                                    </SelectContent>
                                </Select>
                            </template>
                            <Button variant="ghost" size="sm" @click="removeInjuryFlip(idx)">×</Button>
                        </li>
                    </ul>
                </div>

                <div>
                    <Label for="story_entry" class="text-xs text-muted-foreground">Story entry (optional)</Label>
                    <Textarea
                        id="story_entry"
                        v-model="storyEntry"
                        :disabled="!is_owner"
                        rows="3"
                        placeholder="Write a bit about how this game went, if you want it saved to your crew's story log..."
                    />
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
