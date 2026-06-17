<script setup lang="ts">
import ActionCard from '@/components/ActionCard.vue';
import LootAbilityDisplay from '@/components/Bonanza/LootAbilityDisplay.vue';
import LootTriggerDisplay from '@/components/Bonanza/LootTriggerDisplay.vue';
import GameText from '@/components/GameText.vue';
import { computed } from 'vue';

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
    stat_modifier?: string | null;
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

const isConnectorOnly = (value: string): boolean => /^[\s,;.]*(?:and|or)?[\s,;.]*$/i.test(value);

type Lookup =
    | { kind: 'ability'; name: string; entity: LootAbilityRef }
    | { kind: 'action'; name: string; entity: LootActionRef }
    | { kind: 'trigger'; name: string; entity: LootTriggerRef };

const segments = computed((): Segment[] => {
    if (!props.text) return [];

    // Sort longest-first so "Arcane Reservoir" beats a conflicting "Arcane".
    const lookups: Lookup[] = [
        ...(props.abilities ?? []).map<Lookup>((a) => ({ kind: 'ability', name: a.name, entity: a })),
        ...(props.actions ?? []).map<Lookup>((a) => ({ kind: 'action', name: a.name, entity: a })),
        ...(props.triggers ?? []).map<Lookup>((t) => ({ kind: 'trigger', name: t.name, entity: t })),
    ].sort((a, b) => b.name.length - a.name.length);

    if (lookups.length === 0) return [{ type: 'text', value: props.text }];

    // No `\b` boundary — entity names can contain non-word chars (e.g. "Bête Noire").
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

    // Drop stray "," / " and " between two adjacent entity cards.
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

// ActionCard reads `is_signature`, not pivot.is_signature_action.
const actionWithSignature = (action: LootActionRef): LootActionRef & { is_signature: boolean } => ({
    ...action,
    is_signature: action.pivot?.is_signature_action ?? action.is_signature ?? false,
});
</script>

<template>
    <div class="space-y-0.5">
        <template v-for="(segment, i) in segments" :key="`seg-${i}`">
            <p v-if="segment.type === 'text'" class="whitespace-pre-line leading-relaxed text-muted-foreground">
                <GameText :text="segment.value" icon-class="text-[9px] inline-block align-text-bottom" />
            </p>
            <LootAbilityDisplay v-else-if="segment.type === 'ability'" :ability="segment.entity" />
            <ActionCard v-else-if="segment.type === 'action'" :action="actionWithSignature(segment.entity)" hide-footer for-loot-card />
            <LootTriggerDisplay v-else-if="segment.type === 'trigger'" :trigger="segment.entity" />
        </template>

        <!-- Attached but not name-dropped in the prose — render below. -->
        <LootAbilityDisplay v-for="a in leftoverAbilities" :key="`lab-${a.id}`" :ability="a" />
        <ActionCard v-for="a in leftoverActions" :key="`lac-${a.id}`" :action="actionWithSignature(a)" hide-footer for-loot-card />
        <LootTriggerDisplay v-for="t in leftoverTriggers" :key="`ltr-${t.id}`" :trigger="t" />
    </div>
</template>
