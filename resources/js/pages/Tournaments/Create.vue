<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import ScoringEditor from '@/components/Tournament/ScoringEditor.vue';
import VariantPicker from '@/components/Tournament/VariantPicker.vue';
import PageBanner from '@/components/PageBanner.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NumberField, NumberFieldContent, NumberFieldDecrement, NumberFieldIncrement, NumberFieldInput } from '@/components/ui/number-field';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { formatGameSize, TIEBREAKER_LABELS, type RulesetInfo } from '@/types/tournament';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft, ChevronDown, Copy, ListChecks, Loader2, Settings2, Trophy } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

const props = defineProps<{
    rulesets: RulesetInfo[];
    default_ruleset: string;
    seasons: { value: string; label: string }[];
    default_season: string;
    encounter_types: { value: string; label: string }[];
    /** Settings copied from a previous event (`?copy=<uuid>`), or null for a blank form. */
    template: {
        ruleset: string;
        name: string;
        description: string | null;
        location: string | null;
        encounter_size: number;
        encounter_type: string;
        planned_rounds: number;
        season: string;
        round_time_limit: number;
        bye_tp: number | null;
        bye_diff: number | null;
        bye_vp: number | null;
        tiebreaker_mode: 'diff_vp' | 'sos';
        variants: Record<string, boolean | number> | null;
        allow_self_report: boolean;
        team_size: number;
        max_players: number | null;
        require_crew_lists: boolean;
        scoring_config: Record<string, unknown> | null;
    } | null;
    previous_events: { uuid: string; name: string; event_date: string }[];
}>();

const rulesetByKey = computed(() => Object.fromEntries(props.rulesets.map((r) => [r.key, r])));
const initialRuleset = rulesetByKey.value[props.template?.ruleset ?? props.default_ruleset] ?? props.rulesets[0];

// Scoring, game size and round time default from the chosen ruleset; a copied event's own overrides win.
const form = useForm({
    ruleset: initialRuleset.key,
    name: props.template?.name ?? '',
    description: props.template?.description ?? '',
    event_date: '',
    location: props.template?.location ?? '',
    encounter_size: props.template?.encounter_size ?? initialRuleset.game_size.default,
    encounter_type: props.template?.encounter_type ?? 'traditional',
    planned_rounds: props.template?.planned_rounds ?? 3,
    season: props.template?.season ?? props.default_season,
    round_time_limit: props.template?.round_time_limit ?? initialRuleset.default_round_time_limit ?? 135,
    bye_tp: props.template?.bye_tp ?? initialRuleset.scoring.bye_tp,
    bye_diff: props.template?.bye_diff ?? initialRuleset.scoring.bye_diff,
    bye_vp: props.template?.bye_vp ?? initialRuleset.scoring.bye_vp,
    tiebreaker_mode: (props.template?.tiebreaker_mode ?? 'diff_vp') as 'diff_vp' | 'sos',
    allow_self_report: props.template?.allow_self_report ?? false,
    team_size: props.template?.team_size ?? 1,
    max_players: (props.template?.max_players ?? '') as number | '',
    registration_closes_at: '',
    instant_signup: false,
    require_crew_lists: props.template?.require_crew_lists ?? false,
    variants: (props.template?.variants ?? {}) as Record<string, boolean | number>,
    // Scoring overrides on top of the ruleset (only keys that differ), e.g. { win_tp: 4 }.
    scoring_config: (props.template?.scoring_config ?? {}) as Record<string, unknown>,
});

const ruleset = computed(() => rulesetByKey.value[form.ruleset] ?? initialRuleset);

// Switching ruleset re-seeds the fields the ruleset owns (game size, round time, bye scoring).
watch(
    () => form.ruleset,
    () => {
        const r = ruleset.value;
        form.encounter_size = r.game_size.default;
        form.round_time_limit = r.default_round_time_limit ?? form.round_time_limit;
        form.bye_tp = r.scoring.bye_tp;
        form.bye_diff = r.scoring.bye_diff;
        form.bye_vp = r.scoring.bye_vp;
        // Variants and scoring overrides belong to the ruleset they were chosen for.
        form.variants = {};
        form.scoring_config = {};
        if (!r.supports_teams) form.team_size = 1;
    },
);

const tiebreakerSummary = computed(() => ['TP', ...ruleset.value.tiebreakers.map((k) => TIEBREAKER_LABELS[k])].join(' → '));
const suggestedRoundsHint = computed(() =>
    ruleset.value.suggested_rounds.map(([min, max, rounds]) => `${max === null ? `${min}+` : `${min}–${max}`} players: ${rounds}`).join(' · '),
);

// A live read-back of the choices, so the organizer sees what they are creating without scrolling the form.
const summaryRows = computed(() => {
    const r = ruleset.value;
    const sos = r.tiebreaker_toggle && form.tiebreaker_mode === 'sos';
    const ranking = r.tiebreaker_toggle ? (sos ? ['sos', 'diff', 'vp'] : ['diff', 'vp']) : r.tiebreakers;
    const variantNames = r.variants.filter((v) => form.variants[v.key] !== undefined && form.variants[v.key] !== false).map((v) => v.label);
    const rows: { label: string; value: string }[] = [
        { label: 'Format', value: r.label },
        { label: 'Game size', value: formatGameSize(r, Number(form.encounter_size) || r.game_size.default) },
        { label: 'Rounds', value: `${form.planned_rounds} × ${form.round_time_limit} min` },
        { label: 'Ranked by', value: ['TP', ...ranking.map((k) => TIEBREAKER_LABELS[k])].join(' → ') },
        { label: 'Play', value: form.team_size > 1 ? `Teams of ${form.team_size}` : 'Individuals' },
        {
            label: 'Players',
            value:
                (form.max_players === '' ? 'No limit' : `Up to ${form.max_players}, then a waitlist`) +
                (form.instant_signup ? ' · instant sign-up' : ''),
        },
        { label: 'Results', value: form.allow_self_report ? 'Players report, opponents confirm' : 'Entered by you and your scorers' },
    ];
    if (variantNames.length) rows.push({ label: 'Variants', value: variantNames.join(', ') });
    if (Object.keys(form.scoring_config).length) rows.push({ label: 'Scoring', value: 'Modified from the format' });

    return rows;
});

const copyFrom = (uuid: string) => router.get(route('tournaments.create'), uuid === '__none__' ? {} : { copy: uuid });

const advancedOpen = ref(false);

const requiredMissing = computed(() => {
    const missing: string[] = [];
    if (!form.name.trim()) missing.push('name');
    if (!form.event_date) missing.push('event date');
    return missing;
});

const submitDisabledReason = computed(() => {
    if (requiredMissing.value.length === 0) return null;
    return `Fill in ${requiredMissing.value.join(' and ')} first.`;
});

const submit = () => {
    const defaults = ruleset.value.scoring;
    form.transform((data) => ({
        ...data,
        description: data.description.trim() || null,
        location: data.location.trim() || null,
        // Store bye values only when they differ from the ruleset's, so the ruleset stays the source of truth.
        bye_tp: data.bye_tp === defaults.bye_tp ? null : data.bye_tp,
        bye_diff: data.bye_diff === defaults.bye_diff ? null : data.bye_diff,
        bye_vp: data.bye_vp === defaults.bye_vp ? null : data.bye_vp,
        max_players: data.max_players === '' ? null : data.max_players,
        registration_closes_at: data.registration_closes_at ? new Date(data.registration_closes_at).toISOString() : null,
    })).post(route('tournaments.store'));
};
</script>

<template>
    <Head title="Create Tournament" />
    <div class="relative">
        <div
            class="pointer-events-none absolute inset-x-0 top-0 h-64 opacity-[0.07] dark:opacity-[0.12]"
            :style="{ background: 'radial-gradient(ellipse at top, hsl(var(--primary)) 0%, transparent 70%)' }"
        />

        <PageBanner title="Create Tournament" class="mb-2">
            <template #subtitle>
                <div class="my-auto px-2 py-0 text-xs text-muted-foreground md:py-2 md:text-sm md:text-foreground">
                    Set up a new {{ ruleset.label }} event
                </div>
            </template>
        </PageBanner>

        <div class="container mx-auto sm:px-4">
            <Button variant="ghost" class="mb-4 gap-1.5 text-sm" @click="router.get(route('tournaments.index'))">
                <ArrowLeft class="size-4" /> Back to Tournaments
            </Button>

            <div class="mx-auto grid max-w-5xl gap-6 lg:grid-cols-[minmax(0,32rem)_1fr] lg:items-start">
                <form class="w-full max-w-lg lg:max-w-none" @submit.prevent="submit">
                    <p class="mb-3 text-xs text-muted-foreground">
                        Already played it?
                        <a :href="route('tournaments.import.create')" class="underline" data-testid="import-link">Import the results from a CSV</a>
                        instead.
                    </p>
                    <div v-if="previous_events.length" class="mb-4 flex flex-wrap items-center gap-x-3 gap-y-2 rounded-lg border bg-muted/30 p-3">
                        <Copy class="size-4 shrink-0 text-muted-foreground" aria-hidden="true" />
                        <div class="min-w-48 flex-1">
                            <Label for="t-copy" class="text-sm font-medium">Start from a previous event</Label>
                            <p class="text-xs text-muted-foreground">Copies the format, scoring and description. You set the date.</p>
                        </div>
                        <Select :model-value="template ? undefined : '__none__'" @update:model-value="(v) => copyFrom(String(v))">
                            <SelectTrigger id="t-copy" class="h-9 w-full text-xs sm:w-44"><SelectValue placeholder="Choose event…" /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value="__none__">Blank form</SelectItem>
                                <SelectItem v-for="e in previous_events" :key="e.uuid" :value="e.uuid">{{ e.name }}</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <Card>
                        <CardContent class="space-y-8 p-6">
                            <!-- ─── Event Info ─── -->
                            <section class="space-y-4">
                                <header class="flex items-center gap-3">
                                    <div class="flex size-10 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                        <Trophy class="size-5" />
                                    </div>
                                    <div>
                                        <h2 class="text-lg font-semibold">Event Info</h2>
                                        <p class="text-sm text-muted-foreground">The basics — what, when, where.</p>
                                    </div>
                                </header>

                                <div v-if="rulesets.length > 1" class="space-y-2">
                                    <Label for="t-ruleset">Game &amp; Format</Label>
                                    <Select v-model="form.ruleset">
                                        <SelectTrigger id="t-ruleset"><SelectValue /></SelectTrigger>
                                        <SelectContent>
                                            <SelectItem v-for="r in rulesets" :key="r.key" :value="r.key">{{ r.label }}</SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <p class="text-xs text-muted-foreground" data-testid="ruleset-blurb">
                                        {{ ruleset.blurb || 'Sets the scoring, tiebreakers and defaults for the event.' }}
                                    </p>
                                    <InputError :message="form.errors.ruleset" />
                                </div>

                                <div class="space-y-2">
                                    <Label for="t-name">Tournament Name</Label>
                                    <Input id="t-name" v-model="form.name" placeholder="e.g. Spring Showdown 2026" />
                                    <InputError :message="form.errors.name" />
                                </div>

                                <div class="space-y-2">
                                    <Label for="t-description">Summary <span class="text-xs text-muted-foreground">(optional)</span></Label>
                                    <Textarea
                                        id="t-description"
                                        v-model="form.description"
                                        placeholder="One or two lines for the event list…"
                                        rows="2"
                                    />
                                    <p class="text-2xs text-muted-foreground">
                                        Rules, schedule, prizes and terrain notes go in the event's Details tab once it exists.
                                    </p>
                                    <InputError :message="form.errors.description" />
                                </div>

                                <div class="grid gap-4 sm:grid-cols-2">
                                    <div class="space-y-2">
                                        <Label for="t-date">Event Date</Label>
                                        <Input id="t-date" v-model="form.event_date" type="date" />
                                        <InputError :message="form.errors.event_date" />
                                    </div>
                                    <div class="space-y-2">
                                        <Label for="t-location">Location <span class="text-xs text-muted-foreground">(optional)</span></Label>
                                        <Input id="t-location" v-model="form.location" placeholder="Venue name / city" />
                                        <InputError :message="form.errors.location" />
                                    </div>
                                </div>
                            </section>

                            <!-- ─── Tournament Format ─── -->
                            <section class="space-y-4 border-t pt-6">
                                <header>
                                    <h2 class="text-lg font-semibold">Format</h2>
                                    <p class="text-sm text-muted-foreground">Game size, rounds, and the scheme/strategy pool.</p>
                                </header>

                                <div class="grid gap-4 sm:grid-cols-2">
                                    <div class="space-y-2">
                                        <Label>{{ ruleset.game_size.label }}</Label>
                                        <NumberField
                                            v-model="form.encounter_size"
                                            :min="ruleset.game_size.min"
                                            :max="ruleset.game_size.max"
                                            :step="ruleset.game_size.step"
                                        >
                                            <NumberFieldContent>
                                                <NumberFieldDecrement />
                                                <NumberFieldInput />
                                                <NumberFieldIncrement />
                                            </NumberFieldContent>
                                        </NumberField>
                                        <InputError :message="form.errors.encounter_size" />
                                    </div>
                                    <div class="space-y-2">
                                        <Label>Planned Rounds</Label>
                                        <NumberField v-model="form.planned_rounds" :min="1" :max="7">
                                            <NumberFieldContent>
                                                <NumberFieldDecrement />
                                                <NumberFieldInput />
                                                <NumberFieldIncrement />
                                            </NumberFieldContent>
                                        </NumberField>
                                        <p v-if="suggestedRoundsHint" class="text-2xs text-muted-foreground">Suggested — {{ suggestedRoundsHint }}</p>
                                        <InputError :message="form.errors.planned_rounds" />
                                    </div>
                                </div>

                                <div v-if="ruleset.uses_season || ruleset.uses_encounter_type" class="grid gap-4 sm:grid-cols-2">
                                    <div v-if="ruleset.uses_encounter_type" class="space-y-2">
                                        <Label>Encounter Type</Label>
                                        <Select v-model="form.encounter_type">
                                            <SelectTrigger><SelectValue /></SelectTrigger>
                                            <SelectContent>
                                                <SelectItem v-for="et in encounter_types" :key="et.value" :value="et.value">{{
                                                    et.label
                                                }}</SelectItem>
                                            </SelectContent>
                                        </Select>
                                        <InputError :message="form.errors.encounter_type" />
                                    </div>
                                    <div v-if="ruleset.uses_season" class="space-y-2">
                                        <Label>Season</Label>
                                        <Select v-model="form.season">
                                            <SelectTrigger><SelectValue /></SelectTrigger>
                                            <SelectContent>
                                                <SelectItem v-for="s in seasons" :key="s.value" :value="s.value">{{ s.label }}</SelectItem>
                                            </SelectContent>
                                        </Select>
                                        <InputError :message="form.errors.season" />
                                    </div>
                                </div>

                                <div class="space-y-2">
                                    <Label>Round Time Limit (minutes)</Label>
                                    <NumberField v-model="form.round_time_limit" :min="30" :max="300" :step="5">
                                        <NumberFieldContent>
                                            <NumberFieldDecrement />
                                            <NumberFieldInput />
                                            <NumberFieldIncrement />
                                        </NumberFieldContent>
                                    </NumberField>
                                    <InputError :message="form.errors.round_time_limit" />
                                </div>

                                <div v-if="ruleset.supports_teams" class="space-y-2">
                                    <Label for="t-team-size">Play</Label>
                                    <Select :model-value="String(form.team_size)" @update:model-value="(v) => (form.team_size = Number(v))">
                                        <SelectTrigger id="t-team-size"><SelectValue /></SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="1">Individually</SelectItem>
                                            <SelectItem v-for="n in [2, 3, 4]" :key="n" :value="String(n)">Teams of {{ n }}</SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <p class="text-2xs text-muted-foreground">
                                        A team plays one game together against another team and is paired and ranked as one. Each member declares
                                        their own
                                        {{ ruleset.declaration === 'allegiance' ? 'allegiance' : 'faction' }}.
                                    </p>
                                    <InputError :message="form.errors.team_size" />
                                </div>

                                <div class="grid gap-3 sm:grid-cols-2">
                                    <div class="space-y-1.5">
                                        <Label for="t-max-players">Player limit <span class="text-muted-foreground">(optional)</span></Label>
                                        <Input id="t-max-players" v-model="form.max_players" type="number" min="2" max="512" placeholder="No limit" />
                                        <p class="text-2xs text-muted-foreground">
                                            RSVPs beyond the limit join a waitlist and move up as seats free.
                                        </p>
                                        <InputError :message="form.errors.max_players" />
                                    </div>
                                    <div class="space-y-1.5">
                                        <Label for="t-closes-at">Registration closes <span class="text-muted-foreground">(optional)</span></Label>
                                        <Input id="t-closes-at" v-model="form.registration_closes_at" type="datetime-local" />
                                        <p class="text-2xs text-muted-foreground">After this, new RSVPs are refused.</p>
                                        <InputError :message="form.errors.registration_closes_at" />
                                    </div>
                                </div>

                                <label v-if="form.team_size === 1" class="flex items-start gap-2 text-sm">
                                    <input v-model="form.instant_signup" type="checkbox" class="mt-1 size-3.5" />
                                    <span>
                                        <span class="font-medium">Instant sign-up</span>
                                        <span class="block text-2xs text-muted-foreground">
                                            Signing up adds people to the player list right away (up to the limit, then a waitlist).
                                        </span>
                                    </span>
                                </label>

                                <label v-if="ruleset.declaration === 'faction' && form.team_size === 1" class="flex items-start gap-2 text-sm">
                                    <input v-model="form.require_crew_lists" type="checkbox" class="mt-1 size-3.5" />
                                    <span>
                                        <span class="font-medium">Players pick the crew they bring each round</span>
                                        <span class="block text-2xs text-muted-foreground">
                                            Once a round is paired, players choose one of their Crew Builder crews for their game (it can change every
                                            round); you see it, with size and faction flags, on the Rounds tab.
                                        </span>
                                    </span>
                                </label>
                                <label class="flex items-start gap-2 text-sm">
                                    <input v-model="form.allow_self_report" type="checkbox" class="mt-1 size-3.5" />
                                    <span>
                                        <span class="font-medium">Let players report their own results</span>
                                        <span class="block text-2xs text-muted-foreground">
                                            One player submits the score and the opponent confirms or disputes it. Nothing counts until confirmed; you
                                            can always correct it.
                                        </span>
                                    </span>
                                </label>
                            </section>

                            <!-- ─── Rule variants ─── -->
                            <section v-if="ruleset.variants.length" class="space-y-3 border-t pt-6">
                                <header>
                                    <h2 class="text-lg font-semibold">Rule Variants</h2>
                                    <p class="text-sm text-muted-foreground">
                                        Optional rules for your community. Players see these before the event.
                                    </p>
                                </header>
                                <VariantPicker v-model="form.variants" :variants="ruleset.variants" />
                                <InputError :message="form.errors.variants" />
                            </section>

                            <!-- ─── Advanced Scoring (collapsed by default) ─── -->
                            <Collapsible v-model:open="advancedOpen" class="border-t pt-4">
                                <CollapsibleTrigger as-child>
                                    <button
                                        type="button"
                                        class="flex w-full items-center justify-between rounded-md py-1 text-left hover:bg-accent/40"
                                    >
                                        <span class="flex items-center gap-2 text-sm font-medium">
                                            <Settings2 class="size-4 text-muted-foreground" />
                                            Advanced Scoring
                                            <span class="text-xs font-normal text-muted-foreground">Defaults follow {{ ruleset.label }}</span>
                                        </span>
                                        <ChevronDown
                                            class="size-4 shrink-0 text-muted-foreground transition-transform"
                                            :class="advancedOpen ? 'rotate-180' : ''"
                                        />
                                    </button>
                                </CollapsibleTrigger>
                                <CollapsibleContent class="space-y-4 pt-4">
                                    <div v-if="!ruleset.tiebreaker_toggle" class="space-y-1">
                                        <Label>Tiebreaker</Label>
                                        <p class="text-sm">{{ tiebreakerSummary }}</p>
                                        <p class="text-2xs text-muted-foreground">Fixed by this format.</p>
                                    </div>
                                    <div v-else class="space-y-2">
                                        <Label>Tiebreaker</Label>
                                        <Select v-model="form.tiebreaker_mode">
                                            <SelectTrigger><SelectValue /></SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="diff_vp">TP → Differential → VP (default)</SelectItem>
                                                <SelectItem value="sos">TP → Strength of Schedule → DIFF → VP</SelectItem>
                                            </SelectContent>
                                        </Select>
                                        <p class="text-2xs text-muted-foreground">
                                            SoS = sum of your opponents' tournament points. Either way, SoS is shown on standings.
                                        </p>
                                        <InputError :message="form.errors.tiebreaker_mode" />
                                    </div>

                                    <div class="space-y-2 rounded-md border border-dashed p-3">
                                        <Label class="text-xs uppercase tracking-wide text-muted-foreground">
                                            Bye Scoring <span class="ml-1 text-2xs normal-case opacity-60">(awarded to whoever sits out)</span>
                                        </Label>
                                        <div class="grid gap-2 sm:grid-cols-3">
                                            <div class="space-y-1">
                                                <Label class="text-2xs">TP</Label>
                                                <NumberField v-model="form.bye_tp" :min="0" :max="30">
                                                    <NumberFieldContent
                                                        ><NumberFieldDecrement /><NumberFieldInput /><NumberFieldIncrement
                                                    /></NumberFieldContent>
                                                </NumberField>
                                            </div>
                                            <div class="space-y-1">
                                                <Label class="text-2xs">Differential</Label>
                                                <NumberField v-model="form.bye_diff" :min="0" :max="20">
                                                    <NumberFieldContent
                                                        ><NumberFieldDecrement /><NumberFieldInput /><NumberFieldIncrement
                                                    /></NumberFieldContent>
                                                </NumberField>
                                            </div>
                                            <div class="space-y-1">
                                                <Label class="text-2xs">VP</Label>
                                                <NumberField v-model="form.bye_vp" :min="0" :max="20">
                                                    <NumberFieldContent
                                                        ><NumberFieldDecrement /><NumberFieldInput /><NumberFieldIncrement
                                                    /></NumberFieldContent>
                                                </NumberField>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="space-y-2 rounded-md border border-dashed p-3">
                                        <Label class="text-xs uppercase tracking-wide text-muted-foreground">
                                            Customize scoring
                                            <span class="ml-1 text-2xs normal-case opacity-60">(changes are shown to players as modified)</span>
                                        </Label>
                                        <ScoringEditor
                                            v-model="form.scoring_config"
                                            :defaults="ruleset.scoring"
                                            :default-tiebreakers="ruleset.tiebreakers"
                                            :show-tiebreakers="!ruleset.tiebreaker_toggle"
                                        />
                                        <InputError :message="form.errors.scoring_config" />
                                    </div>
                                </CollapsibleContent>
                            </Collapsible>

                            <!-- ─── Submit ─── -->
                            <div class="space-y-2">
                                <Button type="submit" class="w-full" :disabled="form.processing || !!submitDisabledReason">
                                    <Loader2 v-if="form.processing" class="mr-2 size-4 animate-spin" />
                                    <Trophy v-else class="mr-2 size-4" />
                                    Create Tournament
                                </Button>
                                <p v-if="submitDisabledReason" class="text-center text-xs text-muted-foreground">{{ submitDisabledReason }}</p>
                            </div>
                        </CardContent>
                    </Card>
                </form>

                <aside class="hidden lg:sticky lg:top-20 lg:block" aria-label="Event summary">
                    <Card data-testid="create-summary">
                        <CardContent class="space-y-4 p-5">
                            <h2 class="text-sm font-semibold">Your event</h2>
                            <dl class="grid grid-cols-[6.5rem_1fr] gap-x-3 gap-y-1.5 text-sm">
                                <template v-for="row in summaryRows" :key="row.label">
                                    <dt class="text-muted-foreground">{{ row.label }}</dt>
                                    <dd class="font-medium">{{ row.value }}</dd>
                                </template>
                            </dl>
                            <div class="space-y-1.5 border-t pt-4">
                                <h3 class="flex items-center gap-1.5 text-sm font-semibold">
                                    <ListChecks class="size-4" aria-hidden="true" /> After you create it
                                </h3>
                                <ol class="list-decimal space-y-1 pl-5 text-xs text-muted-foreground">
                                    <li>Add players (or share the link for RSVPs), then open registration.</li>
                                    <li>Start the tournament once everyone has arrived.</li>
                                    <li>For each round: set the scenario, pair, start, and enter results.</li>
                                    <li>Finalize to publish standings and a results sheet.</li>
                                </ol>
                            </div>
                        </CardContent>
                    </Card>
                </aside>
            </div>
        </div>
    </div>
</template>
