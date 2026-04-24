<script setup lang="ts">
import FlipCard from '@/components/TOS/FlipCard.vue';
import UnitStatBlock from '@/components/TOS/UnitStatBlock.vue';
import TosMarginCost from '@/components/TosMarginCost.vue';
import TosSuits from '@/components/TosSuits.vue';
import TosText from '@/components/TosText.vue';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { RefreshCw, Swords } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface Sculpt {
    id: number;
    slug: string;
    name: string;
    front_image: string | null;
    back_image: string | null;
    combination_image: string | null;
}

interface Side {
    id: number;
    side: string;
    speed: number;
    defense: number;
    willpower: number;
    armor: number;
    abilities: Array<{ id: number; name: string; body: string | null }>;
    actions: Array<{
        id: number;
        name: string;
        type_links: Array<{ id: number; type: string }>;
        av: number | null;
        av_target: string | null;
        av_suits: string | null;
        range: string | null;
        strength: number | null;
        is_piercing: boolean;
        is_accurate: boolean;
        is_area: boolean;
        usage_limit: string | null;
        body: string | null;
        triggers?: Array<{ id: number; name: string; suits: string | null; margin_cost: number | null; timing: string; body: string | null }>;
    }>;
}

interface SpecialRule {
    id: number;
    slug?: string | null;
    name: string;
    pivot: { parameters: Record<string, unknown> | null };
}

interface Unit {
    id: number;
    slug: string;
    name: string;
    title: string | null;
    scrip: number;
    tactics: string | null;
    description: string | null;
    sides: Side[];
    sculpts?: Sculpt[];
    allegiances: Array<{ id: number; slug: string; name: string; color_slug: string | null }>;
    special_unit_rules: SpecialRule[];
}

const props = defineProps<{
    unit: Unit;
    /** Sculpt to feature as the card art. Defaults to the first sculpt. */
    activeSculpt?: Sculpt | null;
}>();

const sculpt = computed<Sculpt | null>(() => props.activeSculpt ?? props.unit.sculpts?.[0] ?? null);
const standardSide = computed(() => props.unit.sides.find((s) => s.side === 'standard') ?? null);
const glorySide = computed(() => props.unit.sides.find((s) => s.side === 'glory') ?? null);

/** Glory == flipped face. The image-flip and the stats panel below stay in sync. */
const flipped = ref(false);
const activeSide = computed(() => (flipped.value ? glorySide.value : standardSide.value));
const primaryAllegianceSlug = computed(() => props.unit.allegiances[0]?.slug ?? null);

// Commanders don't COST scrip — they provide the Company's starting scrip
// budget (rulebook p. 9). The `scrip` column stores the magnitude either way;
// display flips based on the Commander Special Unit Rule.
const isCommander = computed(() =>
    (props.unit.special_unit_rules ?? []).some((r) => r.slug === 'commander'),
);

/**
 * Per-rule parameter formatter. Maps the JSON shape stored on each
 * `tos_unit_special_rule.parameters` pivot to a human-friendly suffix
 * shown in the rule badge (rulebook p. 10–11):
 *   Fireteam      { base_mm, models_per_team, model_size_mm }
 *   Squad         { fireteam_count }
 *   Reserves      { x }
 *   Adjunct       { size_mm }
 *   Combined Arms child lives on tos_units.combined_arms_child_id, not here.
 * Unknown rules fall back to key:value pairs so new rule shapes still render.
 */
function ruleBadge(rule: SpecialRule): string {
    const p = rule.pivot?.parameters ?? null;
    if (!p) return rule.name;

    switch (rule.slug) {
        case 'fireteam': {
            const models = p.models_per_team;
            const baseSize = p.base_mm;
            const modelSize = p.model_size_mm;
            const parts: string[] = [];
            if (models != null) parts.push(`${models} × ${modelSize ?? '?'}mm`);
            if (baseSize != null) parts.push(`${baseSize}mm base`);
            return parts.length ? `${rule.name} (${parts.join(', ')})` : rule.name;
        }
        case 'squad': {
            const count = p.fireteam_count;
            return count != null ? `${rule.name} of ${count}` : rule.name;
        }
        case 'reserves': {
            const x = p.x;
            return x != null ? `${rule.name} (${x})` : rule.name;
        }
        case 'adjunct': {
            const size = p.size_mm;
            return size != null ? `${rule.name} (${size}mm)` : rule.name;
        }
        default: {
            const tail = Object.entries(p)
                .filter(([, v]) => v !== null && v !== undefined && v !== '')
                .map(([k, v]) => `${k.replace(/_/g, ' ')}: ${v}`)
                .join(' · ');
            return tail ? `${rule.name} (${tail})` : rule.name;
        }
    }
}
</script>

<template>
    <Card class="overflow-hidden">
        <div class="grid gap-4 p-4 lg:grid-cols-[minmax(0,300px)_1fr]">
            <!-- IMAGE-FIRST: card art is the primary visual. Click flips Standard ↔ Glory. -->
            <div class="space-y-2">
                <FlipCard
                    v-model:flipped="flipped"
                    :front-image="sculpt?.front_image"
                    :back-image="sculpt?.back_image"
                    :front-alt="`${unit.name} (Standard)`"
                    :back-alt="`${unit.name} (Glory)`"
                    :allegiance-slug="primaryAllegianceSlug"
                    :placeholder-icon="Swords"
                    :single-side="!sculpt?.back_image"
                />
                <div class="flex items-center justify-between text-[10px] text-muted-foreground">
                    <span class="capitalize tabular-nums">{{ flipped ? 'Glory side' : 'Standard side' }}</span>
                    <Button
                        v-if="sculpt?.back_image"
                        variant="ghost"
                        size="sm"
                        class="h-6 gap-1 px-2 text-[10px]"
                        @click="flipped = !flipped"
                    >
                        <RefreshCw class="size-3" /> Flip
                    </Button>
                </div>
            </div>

            <!-- SECONDARY: text content (header, stats, abilities, actions) -->
            <div class="min-w-0 space-y-3">
                <div>
                    <div class="flex flex-wrap items-baseline justify-between gap-2">
                        <h2 class="text-lg font-semibold">
                            {{ unit.name }}
                            <span v-if="unit.title" class="text-sm font-normal text-muted-foreground">— {{ unit.title }}</span>
                        </h2>
                        <div class="flex items-center gap-2 text-xs text-muted-foreground">
                            <span v-if="isCommander" class="tabular-nums font-medium text-emerald-700 dark:text-emerald-400">+{{ unit.scrip }} Scrip budget</span>
                            <span v-else class="tabular-nums">{{ unit.scrip }} Scrip</span>
                            <span v-if="unit.tactics" class="tabular-nums">· Tactics {{ unit.tactics }}</span>
                        </div>
                    </div>
                    <div class="mt-1.5 flex flex-wrap items-center gap-1.5">
                        <Badge v-for="a in unit.allegiances" :key="a.id" variant="outline" class="text-[10px]">{{ a.name }}</Badge>
                        <Badge v-for="r in unit.special_unit_rules" :key="r.id" class="bg-secondary text-[10px] text-secondary-foreground">
                            {{ ruleBadge(r) }}
                        </Badge>
                    </div>
                </div>

                <CardContent v-if="activeSide" class="space-y-3 px-0 pb-0">
                    <UnitStatBlock :side="activeSide" :label="flipped ? 'Glory AVs' : 'Standard AVs'" />

                    <div v-if="activeSide.abilities.length">
                        <p class="mb-1 text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">Abilities</p>
                        <ul class="space-y-1.5 text-xs">
                            <li v-for="a in activeSide.abilities" :key="a.id">
                                <span class="font-medium">{{ a.name }}.</span>
                                <span v-if="a.body" class="ml-1 text-muted-foreground"><TosText :text="a.body" /></span>
                            </li>
                        </ul>
                    </div>

                    <div v-if="activeSide.actions.length">
                        <p class="mb-1 text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">Actions</p>
                        <ul class="space-y-2 text-xs">
                            <li v-for="ac in activeSide.actions" :key="ac.id" class="rounded border bg-muted/30 p-2">
                                <div class="flex items-center justify-between gap-2">
                                    <div class="flex items-center gap-1.5">
                                        <span v-for="l in ac.type_links" :key="l.id" class="rounded bg-secondary px-1 py-0.5 text-[9px] capitalize text-secondary-foreground">{{ l.type }}</span>
                                        <span class="font-medium">{{ ac.name }}</span>
                                    </div>
                                    <span class="text-[10px] text-muted-foreground">
                                        <template v-if="ac.av != null">
                                            {{ ac.av }}<TosSuits v-if="ac.av_suits" :suits="ac.av_suits" /><template v-if="ac.av_target"> v {{ ac.av_target }}</template>
                                        </template>
                                        <template v-if="ac.range"> · {{ ac.range }}</template>
                                        <template v-if="ac.strength != null"> · Str {{ ac.strength }}</template>
                                    </span>
                                </div>
                                <div v-if="ac.is_piercing || ac.is_accurate || ac.is_area || ac.usage_limit" class="mt-1 flex flex-wrap gap-1 text-[9px]">
                                    <span v-if="ac.is_piercing" class="rounded bg-amber-500/10 px-1 text-amber-700 dark:text-amber-400">Piercing</span>
                                    <span v-if="ac.is_accurate" class="rounded bg-sky-500/10 px-1 text-sky-700 dark:text-sky-400">Accurate</span>
                                    <span v-if="ac.is_area" class="rounded bg-rose-500/10 px-1 text-rose-700 dark:text-rose-400">Area</span>
                                    <span v-if="ac.usage_limit" class="rounded border border-border px-1 capitalize text-muted-foreground">{{ ac.usage_limit.replace(/_/g, ' ') }}</span>
                                </div>
                                <p v-if="ac.body" class="mt-1 text-muted-foreground"><TosText :text="ac.body" /></p>
                                <ul v-if="ac.triggers?.length" class="mt-1 space-y-1 border-l-2 border-border pl-2">
                                    <li v-for="t in ac.triggers" :key="t.id">
                                        <TosSuits v-if="t.suits" :suits="t.suits" />
                                        <TosMarginCost v-else-if="t.margin_cost != null" :cost="t.margin_cost" />
                                        <span class="font-medium">{{ t.name }}</span>
                                        <span v-if="t.timing === 'immediately'" class="ml-1 text-[9px] italic text-muted-foreground">(Immediately)</span>
                                        <span v-if="t.body" class="text-muted-foreground"> — <TosText :text="t.body" /></span>
                                    </li>
                                </ul>
                            </li>
                        </ul>
                    </div>

                    <p v-if="unit.description" class="text-xs italic text-muted-foreground"><TosText :text="unit.description" /></p>
                </CardContent>
            </div>
        </div>
    </Card>
</template>
