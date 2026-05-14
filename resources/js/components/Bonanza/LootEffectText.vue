<script setup lang="ts">
import ActionCard from '@/components/ActionCard.vue';
import LootAbilityDisplay from '@/components/Bonanza/LootAbilityDisplay.vue';
import LootTriggerDisplay from '@/components/Bonanza/LootTriggerDisplay.vue';
import GameText from '@/components/GameText.vue';
import { computed } from 'vue';

// Loose shapes — match enough of the compact display components' expected
// props that they can render, without re-declaring every optional field.
// Missing fields are treated gracefully downstream.
export interface LootAbilityRef {
    id: number;
    name: string;
    suits?: string | null;
    defensive_ability_type?: string | null;
    costs_stone?: boolean;
    description?: string | null;
}
export interface LootActionRef {
    id: number;
    name: string;
    type?: string;
    is_signature?: boolean;
    stone_cost?: number;
    range?: number | null;
    range_type?: string | null;
    stat?: number | null;
    stat_suits?: string | null;
    resisted_by?: string | null;
    target_number?: number | null;
    target_suits?: string | null;
    damage?: number | string | null;
    description?: string | null;
    triggers?: Array<{ id?: number; name: string; suits?: string | null; stone_cost?: number; description?: string | null }>;
    pivot?: { is_signature_action?: boolean };
}
export interface LootTriggerRef {
    id: number;
    name: string;
    suits?: string | null;
    stone_cost?: number;
    description?: string | null;
}

const props = defineProps<{
    text: string | null;
    abilities?: LootAbilityRef[];
    actions?: LootActionRef[];
    triggers?: LootTriggerRef[];
}>();

type Segment =
    | { type: 'text'; value: string }
    | { type: 'ability'; entity: LootAbilityRef }
    | { type: 'action'; entity: LootActionRef }
    | { type: 'trigger'; entity: LootTriggerRef };

// Adjacent entity blocks share connecting punctuation in the prose (", ",
// " and ", " or "); drop it so two stacked cards don't have an awkward
// comma floating between them.
const isConnectorOnly = (value: string): boolean => /^[\s,;.]*(?:and|or)?[\s,;.]*$/i.test(value);

type Lookup =
    | { kind: 'ability'; name: string; entity: LootAbilityRef }
    | { kind: 'action'; name: string; entity: LootActionRef }
    | { kind: 'trigger'; name: string; entity: LootTriggerRef };

const segments = computed((): Segment[] => {
    if (!props.text) return [];

    // Build a single match table sorted longest-first so "Arcane Reservoir"
    // wins over a hypothetical shorter "Arcane".
    const lookups: Lookup[] = [
        ...(props.abilities ?? []).map<Lookup>((a) => ({ kind: 'ability', name: a.name, entity: a })),
        ...(props.actions ?? []).map<Lookup>((a) => ({ kind: 'action', name: a.name, entity: a })),
        ...(props.triggers ?? []).map<Lookup>((t) => ({ kind: 'trigger', name: t.name, entity: t })),
    ].sort((a, b) => b.name.length - a.name.length);

    if (lookups.length === 0) return [{ type: 'text', value: props.text }];

    // Escape regex-meaningful chars in each name, then build one big
    // case-insensitive alternation. `\b` boundaries don't play nice with
    // names that have non-word characters (e.g. "Bête Noire"), so we just
    // match raw and rely on attached-name uniqueness.
    const escape = (s: string) => s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    const pattern = new RegExp(lookups.map((l) => escape(l.name)).join('|'), 'gi');

    const parts: Segment[] = [];
    let lastIndex = 0;
    let match: RegExpExecArray | null;
    while ((match = pattern.exec(props.text)) !== null) {
        if (match.index > lastIndex) {
            parts.push({ type: 'text', value: props.text.slice(lastIndex, match.index) });
        }
        const matched = match[0].toLowerCase();
        const hit = lookups.find((l) => l.name.toLowerCase() === matched)!;
        if (hit.kind === 'ability') parts.push({ type: 'ability', entity: hit.entity });
        else if (hit.kind === 'action') parts.push({ type: 'action', entity: hit.entity });
        else parts.push({ type: 'trigger', entity: hit.entity });
        lastIndex = match.index + match[0].length;
    }
    if (lastIndex < props.text.length) {
        parts.push({ type: 'text', value: props.text.slice(lastIndex) });
    }

    // Collapse pure-connector text segments sandwiched between two entity
    // segments so adjacent cards don't have a stray "," or " and " between
    // them.
    const collapsed: Segment[] = [];
    for (let i = 0; i < parts.length; i++) {
        const cur = parts[i];
        if (
            cur.type === 'text' &&
            isConnectorOnly(cur.value) &&
            i > 0 &&
            i < parts.length - 1 &&
            parts[i - 1].type !== 'text' &&
            parts[i + 1].type !== 'text'
        ) {
            continue;
        }
        collapsed.push(cur);
    }
    return collapsed;
});

// IDs of entities that landed inline, so the template can render any
// leftover attachments after the parsed prose without double-printing.
const matchedIds = computed(() => {
    const ids = { ability: new Set<number>(), action: new Set<number>(), trigger: new Set<number>() };
    for (const seg of segments.value) {
        if (seg.type === 'ability') ids.ability.add(seg.entity.id);
        else if (seg.type === 'action') ids.action.add(seg.entity.id);
        else if (seg.type === 'trigger') ids.trigger.add(seg.entity.id);
    }
    return ids;
});

const leftoverAbilities = computed(() => (props.abilities ?? []).filter((a) => !matchedIds.value.ability.has(a.id)));
const leftoverActions = computed(() => (props.actions ?? []).filter((a) => !matchedIds.value.action.has(a.id)));
const leftoverTriggers = computed(() => (props.triggers ?? []).filter((t) => !matchedIds.value.trigger.has(t.id)));

// Carry the pivot signature flag from the loot_card_action attachment onto
// the action that ActionCard ultimately renders. ActionCard reads from
// `is_signature` (not the pivot), so we surface it explicitly here.
const actionWithSignature = (action: LootActionRef): LootActionRef & { is_signature: boolean } => ({
    ...action,
    is_signature: action.pivot?.is_signature_action ?? action.is_signature ?? false,
});
</script>

<template>
    <div class="space-y-1.5">
        <template v-for="(segment, i) in segments" :key="`seg-${i}`">
            <p v-if="segment.type === 'text'" class="whitespace-pre-line text-xs leading-relaxed text-muted-foreground">
                <GameText :text="segment.value" icon-class="h-3.5 inline-block align-text-bottom" />
            </p>
            <LootAbilityDisplay v-else-if="segment.type === 'ability'" :ability="segment.entity" />
            <ActionCard v-else-if="segment.type === 'action'" :action="actionWithSignature(segment.entity)" hide-footer />
            <LootTriggerDisplay v-else-if="segment.type === 'trigger'" :trigger="segment.entity" />
        </template>

        <!-- Anything attached but not name-dropped in the prose still gets
             rendered here so a sloppy effect-text doesn't accidentally hide
             a real ability/action/trigger. -->
        <LootAbilityDisplay v-for="a in leftoverAbilities" :key="`lab-${a.id}`" :ability="a" />
        <ActionCard v-for="a in leftoverActions" :key="`lac-${a.id}`" :action="actionWithSignature(a)" hide-footer />
        <LootTriggerDisplay v-for="t in leftoverTriggers" :key="`ltr-${t.id}`" :trigger="t" />
    </div>
</template>
