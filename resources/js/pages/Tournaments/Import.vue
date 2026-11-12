<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import PageBanner from '@/components/PageBanner.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { csrfToken } from '@/lib/utils';
import type { RulesetInfo } from '@/types/tournament';
import { Head, router, useForm } from '@inertiajs/vue3';
import { AlertTriangle, ArrowLeft, CheckCircle2, Download, FileUp, Loader2 } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

/**
 * Back-fill a finished event from a CSV of its games (the same columns as the tournament export). The file is checked
 * first — counts, problems with line numbers, warnings — and only a clean file can be imported.
 */
const props = defineProps<{
    rulesets: RulesetInfo[];
    default_ruleset: string;
    seasons: { value: string; label: string }[];
    default_season: string;
}>();

const rulesetByKey = computed(() => Object.fromEntries(props.rulesets.map((r) => [r.key, r])));
const form = useForm({
    ruleset: props.default_ruleset,
    name: '',
    event_date: '',
    location: '',
    encounter_size: rulesetByKey.value[props.default_ruleset]?.game_size.default ?? 50,
    season: props.default_season,
    file: null as File | null,
});
const ruleset = computed(() => rulesetByKey.value[form.ruleset]);

interface Preview {
    ok: boolean;
    errors: string[];
    warnings: string[];
    rounds: number;
    games: number;
    players: string[];
    player_count: number;
}
const preview = ref<Preview | null>(null);
const checking = ref(false);
const checkError = ref<string | null>(null);
const fileInput = ref<HTMLInputElement | null>(null);

// Anything that changes what the file means invalidates the last check.
watch([() => form.ruleset, () => form.file], () => {
    preview.value = null;
    checkError.value = null;
});
watch(
    () => form.ruleset,
    () => {
        form.encounter_size = ruleset.value.game_size.default;
    },
);

const pick = (e: Event) => {
    form.file = (e.target as HTMLInputElement).files?.[0] ?? null;
};

const ready = computed(() => !!form.name.trim() && !!form.event_date && !!form.file);

const check = async () => {
    if (!form.file) return;
    checking.value = true;
    checkError.value = null;
    preview.value = null;
    const body = new FormData();
    for (const key of ['ruleset', 'name', 'event_date', 'location', 'encounter_size', 'season'] as const) body.append(key, String(form[key] ?? ''));
    body.append('file', form.file);
    try {
        const res = await fetch(route('tournaments.import.preview'), {
            method: 'POST',
            body,
            headers: { 'X-CSRF-TOKEN': csrfToken(), Accept: 'application/json' },
        });
        const data = await res.json();
        if (res.status === 422) {
            checkError.value = (Object.values(data.errors ?? {}).flat()[0] as string) ?? 'Check the form fields.';
        } else if (!res.ok) {
            checkError.value = data.message ?? 'Could not check the file.';
        } else {
            preview.value = data;
        }
    } catch {
        checkError.value = 'Network error. Please try again.';
    } finally {
        checking.value = false;
    }
};

const submit = () => {
    form.transform((d) => ({ ...d, location: d.location.trim() || null })).post(route('tournaments.import.store'), { forceFormData: true });
};
</script>

<template>
    <Head title="Import Results" />
    <div class="relative">
        <PageBanner title="Import Results" class="mb-2">
            <template #subtitle>
                <div class="my-auto px-2 py-0 text-xs text-muted-foreground md:py-2 md:text-sm md:text-foreground">
                    Add a finished event from a CSV of its games
                </div>
            </template>
        </PageBanner>

        <div class="container mx-auto sm:px-4">
            <Button variant="ghost" class="mb-4 gap-1.5 text-sm" @click="router.get(route('tournaments.create'))">
                <ArrowLeft class="size-4" /> Back to Create
            </Button>

            <form class="mx-auto max-w-lg space-y-4" @submit.prevent="submit">
                <Card>
                    <CardContent class="space-y-4 p-6">
                        <div v-if="rulesets.length > 1" class="space-y-2">
                            <Label for="i-ruleset">Game &amp; Format</Label>
                            <Select v-model="form.ruleset">
                                <SelectTrigger id="i-ruleset"><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="r in rulesets" :key="r.key" :value="r.key">{{ r.label }}</SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="form.errors.ruleset" />
                        </div>
                        <div class="space-y-2">
                            <Label for="i-name">Tournament Name</Label>
                            <Input id="i-name" v-model="form.name" placeholder="e.g. Spring Showdown 2026" />
                            <InputError :message="form.errors.name" />
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="space-y-2">
                                <Label for="i-date">Event Date</Label>
                                <Input id="i-date" v-model="form.event_date" type="date" />
                                <InputError :message="form.errors.event_date" />
                            </div>
                            <div class="space-y-2">
                                <Label for="i-location">Location <span class="text-xs text-muted-foreground">(optional)</span></Label>
                                <Input id="i-location" v-model="form.location" placeholder="Venue name / city" />
                                <InputError :message="form.errors.location" />
                            </div>
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="space-y-2">
                                <Label for="i-size">{{ ruleset.game_size.label }}</Label>
                                <Input
                                    id="i-size"
                                    v-model.number="form.encounter_size"
                                    type="number"
                                    :min="ruleset.game_size.min"
                                    :max="ruleset.game_size.max"
                                />
                                <InputError :message="form.errors.encounter_size" />
                            </div>
                            <div v-if="ruleset.uses_season" class="space-y-2">
                                <Label for="i-season">Season</Label>
                                <Select v-model="form.season">
                                    <SelectTrigger id="i-season"><SelectValue /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem v-for="s in seasons" :key="s.value" :value="s.value">{{ s.label }}</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent class="space-y-3 p-6">
                        <div>
                            <h2 class="flex items-center gap-1.5 text-sm font-semibold"><FileUp class="size-4" aria-hidden="true" /> Results file</h2>
                            <p class="text-xs text-muted-foreground">
                                One row per game: <code>round</code>, <code>player_one</code>, <code>player_two</code> and the scores
                                (<code>player_one_vp</code>, <code>player_two_vp</code
                                >{{ ruleset.score_fields.length > 1 ? ', or one column per score part' : '' }}). Optional: <code>table</code>,
                                factions, <code>result</code>, <code>is_bye</code>, <code>is_forfeit</code>. A file exported from another event works
                                as is.
                            </p>
                            <a
                                :href="route('tournaments.import.template', form.ruleset)"
                                class="mt-1 inline-flex items-center gap-1 text-xs underline"
                                data-testid="import-template"
                                ><Download class="size-3" aria-hidden="true" /> Download an example for this format</a
                            >
                        </div>
                        <input
                            ref="fileInput"
                            type="file"
                            accept=".csv,text/csv,text/plain"
                            class="block w-full text-sm"
                            aria-label="Results CSV file"
                            @change="pick"
                        />
                        <InputError :message="form.errors.file" />
                        <Button type="button" variant="outline" size="sm" :disabled="!ready || checking" @click="check">
                            <Loader2 v-if="checking" class="mr-1 size-3.5 animate-spin" /> Check file
                        </Button>
                        <p v-if="checkError" class="text-xs text-destructive" role="alert">{{ checkError }}</p>

                        <div v-if="preview" class="space-y-2 rounded-md border p-3 text-sm" data-testid="import-preview" role="status">
                            <div v-if="preview.ok" class="flex items-start gap-2">
                                <CheckCircle2 class="mt-0.5 size-4 shrink-0 text-green-600" aria-hidden="true" />
                                <div>
                                    <div class="font-medium">
                                        {{ preview.rounds }} rounds · {{ preview.games }} games · {{ preview.player_count }} players
                                    </div>
                                    <div class="text-xs text-muted-foreground">
                                        {{ preview.players.join(', ') }}<template v-if="preview.player_count > preview.players.length">…</template>
                                    </div>
                                </div>
                            </div>
                            <div v-else class="flex items-start gap-2 text-destructive">
                                <AlertTriangle class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
                                <div>
                                    <div class="font-medium">This file can't be imported yet</div>
                                    <ul class="list-disc pl-4 text-xs">
                                        <li v-for="e in preview.errors" :key="e">{{ e }}</li>
                                    </ul>
                                </div>
                            </div>
                            <ul v-if="preview.warnings.length" class="list-disc pl-6 text-xs text-muted-foreground">
                                <li v-for="w in preview.warnings" :key="w">{{ w }}</li>
                            </ul>
                        </div>
                    </CardContent>
                </Card>

                <Button type="submit" class="w-full" :disabled="form.processing || !preview?.ok">
                    <Loader2 v-if="form.processing" class="mr-2 size-4 animate-spin" /> Import results
                </Button>
                <p v-if="!preview?.ok" class="text-center text-xs text-muted-foreground">Check the file first; only a clean file can be imported.</p>
            </form>
        </div>
    </div>
</template>
