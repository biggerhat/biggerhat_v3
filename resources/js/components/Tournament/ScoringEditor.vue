<script setup lang="ts">
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { TIEBREAKER_PRESETS, type RulesetScoring } from '@/types/tournament';
import { computed } from 'vue';

/**
 * Edits the scoring rules of an event as a set of overrides on its ruleset. The model only holds keys that
 * differ from `defaults`, so an untouched editor produces `{}` and the ruleset stays the source of truth.
 */
const props = defineProps<{
    defaults: RulesetScoring;
    /** The ruleset's own standings order (after TP). */
    defaultTiebreakers: string[];
    /** Rulesets with the live Standings-tab toggle manage their tiebreaker elsewhere. */
    showTiebreakers: boolean;
}>();

type Overrides = Partial<RulesetScoring> & { tiebreakers?: string[] };
const model = defineModel<Overrides>({ default: () => ({}) });

type NumericKey = 'base_tp' | 'win_tp' | 'tie_tp' | 'loss_tp' | 'forfeit_vp' | 'forfeit_loser_tp';
const numericFields: { key: NumericKey; label: string; hint?: string }[] = [
    { key: 'base_tp', label: 'Base TP', hint: 'Awarded for playing a game' },
    { key: 'win_tp', label: 'Win TP' },
    { key: 'tie_tp', label: 'Tie TP' },
    { key: 'loss_tp', label: 'Loss TP' },
    { key: 'forfeit_vp', label: 'Forfeit VP', hint: 'VP (and DIFF) the winner gets' },
    { key: 'forfeit_loser_tp', label: 'Forfeit loser TP' },
];

const value = (key: NumericKey) => (model.value[key] ?? props.defaults[key]) as number;
const set = <K extends keyof Overrides>(key: K, v: Overrides[K], defaultValue: unknown) => {
    const next = { ...model.value } as Record<string, unknown>;
    if (JSON.stringify(v) === JSON.stringify(defaultValue)) delete next[key as string];
    else next[key as string] = v;
    model.value = next as Overrides;
};
const setNumber = (key: NumericKey, raw: string | number) => {
    const n = Math.max(0, Math.round(Number(raw)));
    if (Number.isFinite(n)) set(key, n, props.defaults[key]);
};

const diffAdds = computed(() => model.value.diff_adds_to_tp ?? props.defaults.diff_adds_to_tp);
const diffCap = computed(() => ('diff_cap' in model.value ? model.value.diff_cap : props.defaults.diff_cap));
const setDiffCap = (raw: string | number) => {
    const text = String(raw).trim();
    const cap = text === '' ? null : Math.max(1, Math.round(Number(text)));
    const next = { ...model.value } as Record<string, unknown>;
    if (cap === props.defaults.diff_cap) delete next.diff_cap;
    else next.diff_cap = cap;
    model.value = next as Overrides;
};

const currentTiebreakers = computed(() => (model.value.tiebreakers ?? props.defaultTiebreakers).join(','));
const setTiebreakers = (csv: string) => set('tiebreakers', csv.split(','), props.defaultTiebreakers);
</script>

<template>
    <div class="space-y-4">
        <div class="grid gap-3 sm:grid-cols-3">
            <div v-for="f in numericFields" :key="f.key" class="space-y-1">
                <Label :for="`score-${f.key}`" class="text-2xs">{{ f.label }}</Label>
                <Input
                    :id="`score-${f.key}`"
                    type="number"
                    min="0"
                    max="100"
                    class="h-8 text-center text-sm"
                    :model-value="value(f.key)"
                    @update:model-value="(raw) => setNumber(f.key, raw)"
                />
                <p v-if="f.hint" class="text-2xs text-muted-foreground">{{ f.hint }}</p>
            </div>
        </div>

        <div class="grid gap-3 sm:grid-cols-2">
            <div class="flex items-start gap-2">
                <Checkbox
                    id="score-diff-adds"
                    class="mt-0.5"
                    :checked="diffAdds"
                    @update:checked="(on) => set('diff_adds_to_tp', on === true, props.defaults.diff_adds_to_tp)"
                />
                <div>
                    <label for="score-diff-adds" class="text-sm font-medium">Add differential to TP</label>
                    <p class="text-2xs text-muted-foreground">Each game's DIFF is added to the tournament points (Fields of Glory style).</p>
                </div>
            </div>
            <div class="space-y-1">
                <Label for="score-diff-cap" class="text-2xs">DIFF cap per game</Label>
                <Input
                    id="score-diff-cap"
                    type="number"
                    min="1"
                    max="99"
                    class="h-8 text-center text-sm"
                    placeholder="No cap"
                    :model-value="diffCap ?? ''"
                    @update:model-value="setDiffCap"
                />
                <p class="text-2xs text-muted-foreground">Leave empty for no cap.</p>
            </div>
        </div>

        <div v-if="showTiebreakers" class="space-y-1">
            <Label class="text-2xs">Tiebreakers (after TP)</Label>
            <Select :model-value="currentTiebreakers" @update:model-value="(v) => setTiebreakers(String(v))">
                <SelectTrigger class="h-8 text-xs"><SelectValue /></SelectTrigger>
                <SelectContent>
                    <SelectItem v-for="p in TIEBREAKER_PRESETS" :key="p.value" :value="p.value">{{ p.label }}</SelectItem>
                </SelectContent>
            </Select>
        </div>
    </div>
</template>
