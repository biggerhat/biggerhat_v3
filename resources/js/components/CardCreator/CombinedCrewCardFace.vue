<script setup lang="ts">
import { formatRange, splitSuits } from '@/components/CardCreator/utils';
import GameIcon from '@/components/GameIcon.vue';
import GameText from '@/components/GameText.vue';
import { computed } from 'vue';

interface TriggerData {
    name: string;
    suits: string | null;
    stone_cost: number;
    description: string | null;
}

interface ActionData {
    name: string;
    type: string;
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
    triggers: TriggerData[];
}

interface AbilityData {
    name: string;
    suits: string | null;
    defensive_ability_type: string | null;
    costs_stone: boolean;
    description: string | null;
}

interface TextData {
    body: string;
}

interface ChoiceData {
    type: string;
    id: number | string;
    name: string;
    // Which held effect this token/marker/upgrade-type pick belongs to —
    // lets the top-of-card note say "Chosen for X:" (pg 17-18, T3-33).
    effect_name: string;
}

interface TokenMarkerItem {
    type: 'token' | 'marker';
    id: number;
    name: string;
    description: string | null;
    base: string | null;
}

interface CombinedItem {
    type: 'action' | 'ability' | 'trigger' | 'text' | 'choice';
    // Restriction qualifying text (pg 32, 54) — printed above the effect it
    // gates, e.g. "Friendly Ten Thunders models gain the following action:".
    // The starter effect and a generic-catalog Tier-4 borrow use the same
    // shared defaults (CombinedCrewCardEffects::DEFAULT_RESTRICTION /
    // BORROWED_RESTRICTION); a real Crew Card Upgrade borrow uses its own
    // stored restriction pivot.
    qualifier: string | null;
    // Same qualifying text without naming a type (action/ability/trigger) —
    // used as the shared header once 2+ effects sharing this restriction get
    // grouped under one line instead of repeating it per effect.
    qualifier_generic: string | null;
    // Groups effects sharing an identical restriction under one header
    // (see effectRows below) — matched key, so this must always be present
    // even for unrestricted items (null).
    restriction: string | null;
    // Kept for the restriction-qualifier logic above — no longer used to
    // split faces (per-user decision: both the starter effect and every
    // held Tier-4 borrow print on the front, like a normal single-sided
    // crew card; the back is a Tokens/Markers quick-reference instead).
    source: 'starter' | 'borrowed';
    data: ActionData | AbilityData | TriggerData | TextData | ChoiceData;
}

const props = withDefaults(
    defineProps<{
        crewName: string;
        items: CombinedItem[];
        tokensMarkers?: TokenMarkerItem[];
        // 'front' shows the crew's full held effect set (starter + every
        // Tier-4 borrow); 'back' shows the Tokens/Markers quick-reference
        // gathered from the crew's active arsenal.
        side?: 'front' | 'back';
    }>(),
    { side: 'front', tokensMarkers: () => [] },
);

const visibleItems = computed(() => (props.side === 'front' ? props.items : []));

// Chosen Token/Marker/Upgrade type (pg 17-18, T3-33) — hoisted to a top note
// naming which effect it was picked for, rather than inlined amongst the
// effect list below (a pick isn't itself an action/ability/trigger).
const choiceItems = computed(() => visibleItems.value.filter(isChoice));

type ActionAbilityTriggerItem = CombinedItem & { data: ActionData | AbilityData | TriggerData };
type EffectRow =
    | { kind: 'text'; source: 'starter' | 'borrowed'; item: CombinedItem & { data: TextData } }
    | { kind: 'group'; source: 'starter' | 'borrowed'; qualifier: string | null; items: ActionAbilityTriggerItem[] };

// Effects sharing the same restriction print under one shared header instead
// of repeating it per effect (QA: group effects with matching restriction
// text) — grouping is scoped to `source` so a starter item and a borrowed
// item never merge even if their restriction happens to coincide. This also
// naturally solves the starter's own actions/abilities: they all share
// DEFAULT_RESTRICTION, so they collapse into a single "Non-peon models in
// this crew with either of your chosen keywords gain the following:" header
// instead of repeating it above every action/ability.
const effectRows = computed<EffectRow[]>(() => {
    const rows: EffectRow[] = [];
    const groupIndexByKey = new Map<string, number>();
    // A null restriction means "no restriction" for that one effect, not a
    // shared restriction across effects — without this, every unrestricted
    // item would fall back to the same `source|''` key and visually merge
    // into one box despite having nothing in common.
    let unrestrictedCounter = 0;

    for (const item of visibleItems.value) {
        if (isChoice(item)) continue;
        if (isText(item)) {
            rows.push({ kind: 'text', source: item.source, item });
            continue;
        }
        const key = item.restriction === null ? `${item.source}|unrestricted-${unrestrictedCounter++}` : `${item.source}|${item.restriction}`;
        const existingIdx = groupIndexByKey.get(key);
        const existingRow = existingIdx !== undefined ? rows[existingIdx] : undefined;
        if (existingRow?.kind === 'group') {
            existingRow.items.push(item as ActionAbilityTriggerItem);
        } else {
            groupIndexByKey.set(key, rows.length);
            rows.push({ kind: 'group', source: item.source, qualifier: item.qualifier, items: [item as ActionAbilityTriggerItem] });
        }
    }

    // A single-effect group keeps its specific, type-named qualifier ("...gain
    // the following action:") — once a second effect joins, no single type
    // name fits all of them, so the header drops to the generic phrasing
    // ("...gain the following:") instead.
    return rows.map((row) => (row.kind === 'group' && row.items.length > 1 ? { ...row, qualifier: row.items[0].qualifier_generic } : row));
});

// "Borrowed Effects" section title prints once, right before the first row
// sourced from a Tier-4 borrow (whichever kind that row happens to be).
const firstBorrowedRowIndex = computed(() => effectRows.value.findIndex((row) => row.source === 'borrowed'));

// Tarot proportions (matches Leader/Totem/single-catalog-row cards): 550x950.
// A combined card holds the starter effect plus every Tier-4 borrow, so its
// content is unbounded — rather than shrinking text to cram it into a fixed
// 550x950 box (which either clips or becomes unreadable), the CARD ITSELF
// grows through discrete tiers, always keeping the tarot aspect ratio, while
// text stays one fixed, comfortably large size throughout.
const TAROT_RATIO = 950 / 550;
const WIDTH_TIERS = [550, 650, 750, 850, 950, 1050, 1150];

const totalContentChars = computed(() => {
    if (props.side === 'back') {
        return props.tokensMarkers.reduce((sum, tm) => sum + tm.name.length + (tm.description?.length ?? 0), 0);
    }

    return visibleItems.value.reduce((sum, item) => {
        if (item.type === 'text') {
            return sum + (item.data as TextData).body.length;
        }
        if (item.type === 'choice') {
            const c = item.data as ChoiceData;
            return sum + c.type.length + c.name.length + c.effect_name.length;
        }
        const d = item.data as ActionData & AbilityData;
        const qualifierChars = item.qualifier?.length ?? 0;
        const triggerChars = 'triggers' in d ? d.triggers.reduce((ts, t) => ts + (t.description?.length ?? 0) + t.name.length, 0) : 0;
        return sum + qualifierChars + (d.description?.length ?? 0) + d.name.length + triggerChars;
    }, 0);
});

const cardWidth = computed(() => {
    const tierIndex = Math.min(Math.floor(totalContentChars.value / 900), WIDTH_TIERS.length - 1);
    return WIDTH_TIERS[tierIndex];
});
const cardHeight = computed(() => Math.round(cardWidth.value * TAROT_RATIO));

const isAction = (item: CombinedItem): item is CombinedItem & { data: ActionData } => item.type === 'action';
const isAbility = (item: CombinedItem): item is CombinedItem & { data: AbilityData } => item.type === 'ability';
const isTrigger = (item: CombinedItem): item is CombinedItem & { data: TriggerData } => item.type === 'trigger';
const isChoice = (item: CombinedItem): item is CombinedItem & { data: ChoiceData } => item.type === 'choice';
const isText = (item: CombinedItem): item is CombinedItem & { data: TextData } => item.type === 'text';
</script>

<template>
    <!-- No overflow-hidden here — the tarot-tiered size above is a target,
         not a hard cap. If a real crew's content ever outgrows the largest
         tier, the box grows past it rather than silently clipping content;
         the rounded corners on the border strips below make that safe. -->
    <div
        class="card-face card-crew relative flex flex-col bg-neutral-900 text-white"
        :style="{ width: cardWidth + 'px', minHeight: cardHeight + 'px' }"
    >
        <!-- Border -->
        <div class="h-1.5 w-full rounded-t-lg" style="background: hsl(var(--primary))" />

        <!-- Header -->
        <div class="flex items-center gap-2 px-3 py-3" style="background: rgba(255, 255, 255, 0.06)">
            <div class="min-w-0 flex-1">
                <div class="text-2xl font-bold leading-snug">{{ crewName }}</div>
                <div class="mt-0.5 text-xs uppercase tracking-wider text-white/60">
                    Crew Card — {{ side === 'front' ? 'Effects' : 'Tokens & Markers' }}
                </div>
            </div>
        </div>

        <!-- Back face: Tokens/Markers quick-reference gathered from the
             crew's currently-active arsenal (per-user decision — replaces
             the old "borrowed effects" back face; those now print on the
             front alongside the starter effect, like a normal crew card). -->
        <div v-if="side === 'back'" class="flex-1 px-3 py-2.5 text-sm leading-6">
            <p v-if="!tokensMarkers.length" class="italic text-white/50">No tokens or markers in this crew's arsenal.</p>
            <div
                v-for="tm in tokensMarkers"
                :key="`${tm.type}-${tm.id}`"
                class="mb-2 rounded px-2 py-1.5"
                style="background: rgba(255, 255, 255, 0.04)"
            >
                <span class="font-bold capitalize">{{ tm.name }}</span>
                <span class="ml-1 text-[10px] uppercase tracking-wide text-white/50">{{ tm.type }}</span>
                <span v-if="tm.base" class="ml-1 text-[10px] uppercase tracking-wide text-white/50">— {{ tm.base }}</span>
                <div v-if="tm.description" class="text-white/80">
                    <GameText :text="tm.description" icon-class="inline-block align-text-bottom" />
                </div>
            </div>
        </div>

        <!-- Front face: the crew's full held effect set (starter + every
             Tier-4 borrow, in acquisition order). -->
        <div v-else class="flex-1 px-3 py-2.5 text-sm leading-6">
            <!-- Chosen Token/Marker/Upgrade type (pg 17-18, T3-33) — a pick
                 belongs to a specific held effect, so it's named at the top
                 rather than inlined amongst the action/ability list below. -->
            <div
                v-for="(choice, ci) in choiceItems"
                :key="`choice-${ci}`"
                class="mb-2 rounded px-2 py-1.5 text-xs italic text-white/70"
                style="background: rgba(255, 255, 255, 0.04)"
            >
                Chosen for {{ (choice.data as ChoiceData).effect_name }}:
                <span class="font-semibold capitalize not-italic">{{ (choice.data as ChoiceData).type }}</span>
                — {{ (choice.data as ChoiceData).name }}
            </div>

            <p v-if="!effectRows.length" class="italic text-white/50">No effects held yet.</p>

            <template v-for="(row, idx) in effectRows" :key="idx">
                <p v-if="idx === firstBorrowedRowIndex" class="mb-1 mt-3 text-[11px] font-bold uppercase tracking-wider text-white/70">
                    Borrowed Effects
                </p>

                <!-- Starter/borrowed effect's own flavor/rule text -->
                <div v-if="row.kind === 'text'" class="mb-2 rounded px-2 py-1.5 text-white/80" style="background: rgba(255, 255, 255, 0.04)">
                    <GameText :text="row.item.data.body" icon-class="inline-block align-text-bottom" />
                </div>

                <!-- Group of effects sharing one restriction (a single-effect
                     group is visually identical to before — one header, one
                     effect; 2+ effects share the header instead of repeating it). -->
                <div v-else class="mb-2 rounded" style="background: rgba(255, 255, 255, 0.04)">
                    <p v-if="row.qualifier" class="px-2 pt-1.5 text-xs font-semibold uppercase italic tracking-wide text-white/60">
                        {{ row.qualifier }}
                    </p>

                    <div v-for="(item, itemIdx) in row.items" :key="itemIdx" :class="itemIdx > 0 ? 'border-t border-white/10' : ''">
                        <!-- Ability -->
                        <div v-if="isAbility(item)" class="px-2 py-1.5">
                            <span class="font-bold">
                                <GameIcon v-if="item.data.costs_stone" type="soulstone" class-name="text-sm" />
                                <GameIcon
                                    v-if="item.data.defensive_ability_type && item.data.defensive_ability_type !== 'none'"
                                    :type="item.data.defensive_ability_type"
                                    class-name="text-sm"
                                />
                                {{ item.data.name }}
                                <GameIcon v-if="item.data.suits && item.data.suits !== 'none'" :type="item.data.suits" class-name="text-sm" />:
                            </span>
                            <span v-if="item.data.description" class="text-white/80">
                                <GameText :text="item.data.description" icon-class="inline-block align-text-bottom" />
                            </span>
                        </div>

                        <!-- Standalone trigger -->
                        <div v-else-if="isTrigger(item)" class="px-2 py-1.5">
                            <span class="font-bold">
                                <GameIcon v-for="s in splitSuits(item.data.suits)" :key="s" :type="s" class-name="text-sm" />
                                <template v-for="n in item.data.stone_cost" :key="'tsc-' + n">
                                    <GameIcon type="soulstone" class-name="text-sm" />
                                </template>
                                {{ item.data.name }}:
                            </span>
                            <span v-if="item.data.description" class="text-white/80">
                                <GameText :text="item.data.description" icon-class="inline-block align-text-bottom" />
                            </span>
                        </div>

                        <!-- Action -->
                        <template v-else-if="isAction(item)">
                            <div class="flex items-center px-2 py-1.5">
                                <div class="flex min-w-0 flex-1 items-center gap-1 font-bold">
                                    <GameIcon v-if="item.data.is_signature" type="signature_action" class-name="text-sm shrink-0" />
                                    <template v-for="n in item.data.stone_cost" :key="'sc-' + n">
                                        <GameIcon type="soulstone" class-name="text-sm shrink-0" />
                                    </template>
                                    <span class="truncate">{{ item.data.name }}</span>
                                </div>
                                <span class="w-9 text-center">
                                    <span class="inline-flex items-center justify-center gap-0.5">
                                        <GameIcon v-if="item.data.range_type" :type="item.data.range_type" class-name="text-xs" :size-em="1.0" />
                                        {{ formatRange(item.data.range) }}
                                    </span>
                                </span>
                                <span class="w-9 text-center">
                                    <span v-if="item.data.stat != null" class="inline-flex items-center justify-center gap-0.5">
                                        {{ item.data.stat
                                        }}<GameIcon
                                            v-for="s in splitSuits(item.data.stat_suits)"
                                            :key="s"
                                            :type="s"
                                            class-name="text-xs"
                                            :size-em="1.0"
                                        />
                                    </span>
                                    <span v-else>-</span>
                                </span>
                                <span class="w-8 text-center text-white/60">{{ item.data.resisted_by ?? '-' }}</span>
                                <span class="w-9 text-center">
                                    <span v-if="item.data.target_number != null" class="inline-flex items-center justify-center gap-0.5">
                                        {{ item.data.target_number
                                        }}<GameIcon
                                            v-for="s in splitSuits(item.data.target_suits)"
                                            :key="s"
                                            :type="s"
                                            class-name="text-xs"
                                            :size-em="1.0"
                                        />
                                    </span>
                                    <span v-else>-</span>
                                </span>
                                <span class="w-9 text-center font-medium text-red-400">{{ item.data.damage ?? '-' }}</span>
                            </div>

                            <div v-if="item.data.description" class="px-2 pb-1.5 text-white/80">
                                <GameText :text="item.data.description" icon-class="inline-block align-text-bottom" />
                            </div>

                            <div v-if="item.data.triggers.length" class="space-y-1 border-t border-white/10 px-2 py-1.5">
                                <div v-for="trigger in item.data.triggers" :key="trigger.name">
                                    <span class="font-bold">
                                        <GameIcon v-for="s in splitSuits(trigger.suits)" :key="s" :type="s" class-name="text-sm" />
                                        <template v-for="n in trigger.stone_cost" :key="'tsc-' + n">
                                            <GameIcon type="soulstone" class-name="text-sm" />
                                        </template>
                                        {{ trigger.name }}:
                                    </span>
                                    <span class="text-white/80">
                                        <GameText
                                            v-if="trigger.description"
                                            :text="trigger.description"
                                            icon-class="inline-block align-text-bottom"
                                        />
                                    </span>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </template>
        </div>

        <!-- Border -->
        <div class="h-1 w-full rounded-b-lg" style="background: hsl(var(--primary))" />
    </div>
</template>
