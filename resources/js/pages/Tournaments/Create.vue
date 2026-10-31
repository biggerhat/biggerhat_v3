<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import PageBanner from '@/components/PageBanner.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NumberField, NumberFieldContent, NumberFieldDecrement, NumberFieldIncrement, NumberFieldInput } from '@/components/ui/number-field';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft, ChevronDown, Copy, Loader2, Settings2, Trophy } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps<{
    seasons: { value: string; label: string }[];
    default_season: string;
    encounter_types: { value: string; label: string }[];
    /** Settings copied from a previous event (`?copy=<uuid>`), or null for a blank form. */
    template: {
        name: string;
        description: string | null;
        location: string | null;
        encounter_size: number;
        encounter_type: string;
        planned_rounds: number;
        season: string;
        round_time_limit: number;
        bye_tp: number;
        bye_diff: number;
        bye_vp: number;
        tiebreaker_mode: 'diff_vp' | 'sos';
    } | null;
    previous_events: { uuid: string; name: string; event_date: string }[];
}>();

// Bye-scoring defaults match Gaining Grounds (3 TP / +4 DIFF / 6 VP).
const form = useForm({
    name: props.template?.name ?? '',
    description: props.template?.description ?? '',
    event_date: '',
    location: props.template?.location ?? '',
    encounter_size: props.template?.encounter_size ?? 50,
    encounter_type: props.template?.encounter_type ?? 'traditional',
    planned_rounds: props.template?.planned_rounds ?? 3,
    season: props.template?.season ?? props.default_season,
    round_time_limit: props.template?.round_time_limit ?? 135,
    bye_tp: props.template?.bye_tp ?? 3,
    bye_diff: props.template?.bye_diff ?? 4,
    bye_vp: props.template?.bye_vp ?? 6,
    tiebreaker_mode: (props.template?.tiebreaker_mode ?? 'diff_vp') as 'diff_vp' | 'sos',
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
    form.transform((data) => ({
        ...data,
        description: data.description.trim() || null,
        location: data.location.trim() || null,
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
                    Set up a new Gaining Grounds event
                </div>
            </template>
        </PageBanner>

        <div class="container mx-auto sm:px-4">
            <Button variant="ghost" class="mb-4 gap-1.5 text-sm" @click="router.get(route('tournaments.index'))">
                <ArrowLeft class="size-4" /> Back to Tournaments
            </Button>

            <form class="mx-auto max-w-lg" @submit.prevent="submit">
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

                            <div class="space-y-2">
                                <Label for="t-name">Tournament Name</Label>
                                <Input id="t-name" v-model="form.name" placeholder="e.g. Spring Showdown 2026" />
                                <InputError :message="form.errors.name" />
                            </div>

                            <div class="space-y-2">
                                <Label for="t-description">Description <span class="text-xs text-muted-foreground">(optional)</span></Label>
                                <Textarea id="t-description" v-model="form.description" placeholder="Event details, rules, prizes..." rows="3" />
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
                                    <Label>Encounter Size (SS)</Label>
                                    <NumberField v-model="form.encounter_size" :min="20" :max="100" :step="5">
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
                                    <InputError :message="form.errors.planned_rounds" />
                                </div>
                            </div>

                            <div class="grid gap-4 sm:grid-cols-2">
                                <div class="space-y-2">
                                    <Label>Encounter Type</Label>
                                    <Select v-model="form.encounter_type">
                                        <SelectTrigger><SelectValue /></SelectTrigger>
                                        <SelectContent>
                                            <SelectItem v-for="et in encounter_types" :key="et.value" :value="et.value">{{ et.label }}</SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <InputError :message="form.errors.encounter_type" />
                                </div>
                                <div class="space-y-2">
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
                        </section>

                        <!-- ─── Advanced Scoring (collapsed by default) ─── -->
                        <Collapsible v-model:open="advancedOpen" class="border-t pt-4">
                            <CollapsibleTrigger as-child>
                                <button type="button" class="flex w-full items-center justify-between rounded-md py-1 text-left hover:bg-accent/40">
                                    <span class="flex items-center gap-2 text-sm font-medium">
                                        <Settings2 class="size-4 text-muted-foreground" />
                                        Advanced Scoring
                                        <span class="text-xs font-normal text-muted-foreground">Defaults match Gaining Grounds</span>
                                    </span>
                                    <ChevronDown
                                        class="size-4 shrink-0 text-muted-foreground transition-transform"
                                        :class="advancedOpen ? 'rotate-180' : ''"
                                    />
                                </button>
                            </CollapsibleTrigger>
                            <CollapsibleContent class="space-y-4 pt-4">
                                <div class="space-y-2">
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
                                            <NumberField v-model="form.bye_tp" :min="0" :max="5">
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
        </div>
    </div>
</template>
