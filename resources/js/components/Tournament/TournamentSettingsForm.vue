<script setup lang="ts">
import ScoringEditor from '@/components/Tournament/ScoringEditor.vue';
import VariantPicker from '@/components/Tournament/VariantPicker.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NumberField, NumberFieldContent, NumberFieldDecrement, NumberFieldIncrement, NumberFieldInput } from '@/components/ui/number-field';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { useTournament } from '@/composables/useTournament';
import type { TournamentRuleset } from '@/types/tournament';
import { ChevronDown, Loader2, Settings2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';

/**
 * Edit form for a tournament's settings (event info, format, bye scoring, scoring overrides, variants,
 * player self-reporting). Mounted fresh each time the organizer opens it, so the form state is seeded
 * from the tournament once at setup; the page reacts to `saved` / `cancel`.
 */
const props = defineProps<{
    tournament: {
        uuid: string;
        name: string;
        description: string | null;
        event_date: string;
        location: string | null;
        encounter_size: number;
        encounter_type: string;
        planned_rounds: number;
        round_time_limit: number;
        allow_self_report?: boolean;
        team_size?: number;
        max_players?: number | null;
        require_crew_lists?: boolean;
        registration_closes_at?: string | null;
        instant_signup?: boolean;
        scoring_config?: Record<string, unknown> | null;
        [key: string]: unknown;
    };
    ruleset: TournamentRuleset;
    /** Switching between individual and team play is only possible while nobody is registered. */
    canChangeTeamSize: boolean;
    encounterTypes: { value: string; label: string }[];
}>();
const emit = defineEmits<{ (e: 'saved'): void; (e: 'cancel'): void }>();

const tournamentRef = computed(() => props.tournament as { uuid: string; players: never[] });
const { submitting, doModalAction } = useTournament(tournamentRef);

const toDateInput = (d: string) => (d ? d.split('T')[0] : '');

const editName = ref(props.tournament.name);
const editDescription = ref(props.tournament.description ?? '');
const editEventDate = ref(toDateInput(props.tournament.event_date));
const editLocation = ref(props.tournament.location ?? '');
const editEncounterSize = ref(props.tournament.encounter_size);
const editEncounterType = ref(props.tournament.encounter_type ?? 'traditional');
const editPlannedRounds = ref(props.tournament.planned_rounds);
const editRoundTimeLimit = ref(props.tournament.round_time_limit);
const editByeTp = ref(props.ruleset.scoring.bye_tp);
const editByeDiff = ref(props.ruleset.scoring.bye_diff);
const editByeVp = ref(props.ruleset.scoring.bye_vp);
const editAllowSelfReport = ref(!!props.tournament.allow_self_report);
const editRequireCrewLists = ref(!!props.tournament.require_crew_lists);
const editTeamSize = ref(props.tournament.team_size ?? 1);
// <input type=datetime-local> wants local "YYYY-MM-DDTHH:mm"; the server speaks UTC ISO strings.
const toLocalInput = (iso?: string | null) => {
    if (!iso) return '';
    const d = new Date(iso);
    const pad = (n: number) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
};
const editMaxPlayers = ref(props.tournament.max_players ? String(props.tournament.max_players) : '');
const editClosesAt = ref(toLocalInput(props.tournament.registration_closes_at));
const editInstantSignup = ref(props.tournament.instant_signup ?? false);
const editAdvancedOpen = ref(false);
const editVariants = ref<Record<string, boolean | number>>({ ...props.ruleset.active_variants });
const editScoringConfig = ref<Record<string, unknown>>({ ...(props.tournament.scoring_config ?? {}) });
const settingsError = ref<string | null>(null);

const saveSettings = async () => {
    submitting.value = true;
    settingsError.value = null;
    const { ok, error } = await doModalAction(route('tournaments.update', props.tournament.uuid), 'PUT', {
        name: editName.value.trim(),
        description: editDescription.value.trim() || null,
        event_date: editEventDate.value,
        location: editLocation.value.trim() || null,
        encounter_size: editEncounterSize.value,
        encounter_type: editEncounterType.value,
        planned_rounds: editPlannedRounds.value,
        round_time_limit: editRoundTimeLimit.value,
        // Only store a bye value when it differs from the ruleset's own, so rulesets stay the source of truth.
        bye_tp: editByeTp.value === props.ruleset.default_scoring.bye_tp ? null : editByeTp.value,
        bye_diff: editByeDiff.value === props.ruleset.default_scoring.bye_diff ? null : editByeDiff.value,
        bye_vp: editByeVp.value === props.ruleset.default_scoring.bye_vp ? null : editByeVp.value,
        variants: editVariants.value,
        allow_self_report: editAllowSelfReport.value,
        require_crew_lists: editRequireCrewLists.value,
        max_players: editMaxPlayers.value.trim() === '' ? null : Number(editMaxPlayers.value),
        registration_closes_at: editClosesAt.value ? new Date(editClosesAt.value).toISOString() : null,
        instant_signup: editInstantSignup.value,
        ...(props.canChangeTeamSize ? { team_size: editTeamSize.value } : {}),
        scoring_config: editScoringConfig.value,
    });
    submitting.value = false;
    if (ok) emit('saved');
    else settingsError.value = error;
};
</script>

<template>
    <Card class="mb-4">
        <CardContent class="space-y-6 p-4 sm:p-6">
            <!-- Event Info -->
            <section class="space-y-3">
                <header>
                    <h3 class="text-sm font-semibold">Event Info</h3>
                    <p class="text-xs text-muted-foreground">The basics — what, when, where.</p>
                </header>
                <div class="space-y-1">
                    <Label class="text-xs" for="settings-name">Name</Label>
                    <Input id="settings-name" v-model="editName" class="h-9 text-sm" />
                </div>
                <div class="space-y-1">
                    <Label class="text-xs" for="settings-description">Description</Label>
                    <Textarea id="settings-description" v-model="editDescription" rows="3" class="text-sm" placeholder="Optional" />
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="space-y-1">
                        <Label class="text-xs" for="settings-date">Event Date</Label>
                        <Input id="settings-date" v-model="editEventDate" type="date" class="h-9 text-sm" />
                    </div>
                    <div class="space-y-1">
                        <Label class="text-xs" for="settings-location">Location</Label>
                        <Input id="settings-location" v-model="editLocation" class="h-9 text-sm" placeholder="Optional" />
                    </div>
                </div>
            </section>

            <!-- Format -->
            <section class="space-y-3 border-t pt-5">
                <header>
                    <h3 class="text-sm font-semibold">Format</h3>
                    <p class="text-xs text-muted-foreground">Game size, rounds, and encounter type.</p>
                </header>
                <div class="grid gap-3 sm:grid-cols-3">
                    <div class="space-y-1">
                        <Label class="text-xs">Encounter Size</Label>
                        <NumberField v-model="editEncounterSize" :min="20" :max="100" :step="5">
                            <NumberFieldContent><NumberFieldDecrement /><NumberFieldInput /><NumberFieldIncrement /></NumberFieldContent>
                        </NumberField>
                    </div>
                    <div class="space-y-1">
                        <Label class="text-xs">Encounter Type</Label>
                        <Select v-model="editEncounterType">
                            <SelectTrigger class="h-9 text-xs"><SelectValue /></SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="et in encounterTypes" :key="et.value" :value="et.value">{{ et.label }}</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="space-y-1">
                        <Label class="text-xs">Planned Rounds</Label>
                        <NumberField v-model="editPlannedRounds" :min="1" :max="7">
                            <NumberFieldContent><NumberFieldDecrement /><NumberFieldInput /><NumberFieldIncrement /></NumberFieldContent>
                        </NumberField>
                    </div>
                </div>
                <div class="space-y-1">
                    <Label class="text-xs">Round Time Limit (minutes)</Label>
                    <NumberField v-model="editRoundTimeLimit" :min="30" :max="300" :step="5">
                        <NumberFieldContent><NumberFieldDecrement /><NumberFieldInput /><NumberFieldIncrement /></NumberFieldContent>
                    </NumberField>
                </div>
                <div v-if="ruleset.supports_teams" class="space-y-1.5">
                    <Label class="text-xs">Play</Label>
                    <Select
                        :model-value="String(editTeamSize)"
                        :disabled="!canChangeTeamSize"
                        @update:model-value="(v) => (editTeamSize = Number(v))"
                    >
                        <SelectTrigger class="h-9 text-sm"><SelectValue /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="1">Individually</SelectItem>
                            <SelectItem v-for="n in [2, 3, 4]" :key="n" :value="String(n)">Teams of {{ n }}</SelectItem>
                        </SelectContent>
                    </Select>
                    <p v-if="!canChangeTeamSize" class="text-2xs text-muted-foreground">
                        Remove the registered players first to switch between individual and team play.
                    </p>
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="space-y-1">
                        <Label class="text-xs" for="settings-max-players">Player limit <span class="text-muted-foreground">(optional)</span></Label>
                        <Input
                            id="settings-max-players"
                            v-model="editMaxPlayers"
                            type="number"
                            min="2"
                            max="512"
                            class="h-9 text-sm"
                            placeholder="No limit"
                        />
                        <p class="text-2xs text-muted-foreground">RSVPs beyond the limit join a waitlist and move up as seats free.</p>
                    </div>
                    <div class="space-y-1">
                        <Label class="text-xs" for="settings-closes-at"
                            >Registration closes <span class="text-muted-foreground">(optional)</span></Label
                        >
                        <Input id="settings-closes-at" v-model="editClosesAt" type="datetime-local" class="h-9 text-sm" />
                        <p class="text-2xs text-muted-foreground">After this, new RSVPs are refused (cancelling still works).</p>
                    </div>
                </div>
                <label v-if="editTeamSize === 1" class="flex items-start gap-2 text-sm">
                    <input v-model="editInstantSignup" type="checkbox" class="mt-1 size-3.5" />
                    <span>
                        <span class="font-medium">Instant sign-up</span>
                        <span class="block text-2xs text-muted-foreground">
                            Signing up adds people to the player list right away (up to the limit, then a waitlist) instead of waiting for you to
                            confirm each one.
                        </span>
                    </span>
                </label>
                <label v-if="ruleset.declaration === 'faction' && editTeamSize === 1" class="flex items-start gap-2 text-sm">
                    <input v-model="editRequireCrewLists" type="checkbox" class="mt-1 size-3.5" />
                    <span>
                        <span class="font-medium">Players pick the crew they bring each round</span>
                        <span class="block text-2xs text-muted-foreground">
                            Once a round is paired, players choose one of their Crew Builder crews for their game (it can change every round); you see
                            it, with size and faction flags, on the Rounds tab, and Start Round warns who hasn't picked.
                        </span>
                    </span>
                </label>
                <label class="flex items-start gap-2 text-sm">
                    <input v-model="editAllowSelfReport" type="checkbox" class="mt-1 size-3.5" />
                    <span>
                        <span class="font-medium">Let players report their own results</span>
                        <span class="block text-2xs text-muted-foreground">
                            One player submits the score, the opponent confirms or disputes it. Nothing counts until confirmed; you can always correct
                            it.
                        </span>
                    </span>
                </label>
            </section>

            <!-- Advanced Scoring (collapsed by default) -->
            <div v-if="ruleset.variants.length" class="space-y-2 border-t pt-4">
                <h3 class="text-sm font-semibold">Rule Variants</h3>
                <VariantPicker v-model="editVariants" :variants="ruleset.variants" />
            </div>

            <Collapsible v-model:open="editAdvancedOpen" class="border-t pt-4">
                <CollapsibleTrigger as-child>
                    <button type="button" class="flex w-full items-center justify-between rounded-md py-1 text-left hover:bg-accent/40">
                        <span class="flex items-center gap-2 text-sm font-medium">
                            <Settings2 class="size-4 text-muted-foreground" />
                            Advanced Scoring
                            <span class="text-xs font-normal text-muted-foreground">Bye scoring</span>
                        </span>
                        <ChevronDown
                            class="size-4 shrink-0 text-muted-foreground transition-transform"
                            :class="editAdvancedOpen ? 'rotate-180' : ''"
                        />
                    </button>
                </CollapsibleTrigger>
                <CollapsibleContent class="space-y-3 pt-3">
                    <div class="space-y-2 rounded-md border border-dashed p-3">
                        <Label class="text-xs uppercase tracking-wide text-muted-foreground">
                            Bye Scoring <span class="ml-1 text-2xs normal-case opacity-60">(awarded to whoever sits out)</span>
                        </Label>
                        <div class="grid gap-2 sm:grid-cols-3">
                            <div class="space-y-1">
                                <Label class="text-2xs">TP</Label>
                                <NumberField v-model="editByeTp" :min="0" :max="30">
                                    <NumberFieldContent><NumberFieldDecrement /><NumberFieldInput /><NumberFieldIncrement /></NumberFieldContent>
                                </NumberField>
                            </div>
                            <div class="space-y-1">
                                <Label class="text-2xs">Differential</Label>
                                <NumberField v-model="editByeDiff" :min="0" :max="20">
                                    <NumberFieldContent><NumberFieldDecrement /><NumberFieldInput /><NumberFieldIncrement /></NumberFieldContent>
                                </NumberField>
                            </div>
                            <div class="space-y-1">
                                <Label class="text-2xs">VP</Label>
                                <NumberField v-model="editByeVp" :min="0" :max="20">
                                    <NumberFieldContent><NumberFieldDecrement /><NumberFieldInput /><NumberFieldIncrement /></NumberFieldContent>
                                </NumberField>
                            </div>
                        </div>
                    </div>
                    <div class="space-y-2 rounded-md border border-dashed p-3">
                        <Label class="text-xs uppercase tracking-wide text-muted-foreground">Customize scoring</Label>
                        <ScoringEditor
                            v-model="editScoringConfig"
                            :defaults="ruleset.default_scoring"
                            :default-tiebreakers="ruleset.default_tiebreakers"
                            :show-tiebreakers="!ruleset.tiebreaker_toggle"
                        />
                    </div>
                    <p v-if="ruleset.tiebreaker_toggle" class="text-2xs text-muted-foreground">
                        Tiebreaker mode toggle lives on the Standings tab so you can flip it any time, even mid-tournament.
                    </p>
                </CollapsibleContent>
            </Collapsible>

            <p v-if="settingsError" class="text-xs text-destructive">{{ settingsError }}</p>

            <div class="flex gap-2">
                <Button variant="outline" class="flex-1" :disabled="submitting" @click="emit('cancel')">Cancel</Button>
                <Button class="flex-1" :disabled="!editName.trim() || !editEventDate || submitting" @click="saveSettings">
                    <Loader2 v-if="submitting" class="mr-2 size-4 animate-spin" />
                    Save Changes
                </Button>
            </div>
        </CardContent>
    </Card>
</template>
