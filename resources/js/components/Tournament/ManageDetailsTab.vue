<script setup lang="ts">
import TipTapEditor from '@/components/blog/TipTapEditor.vue';
import AwardsEditor from '@/components/Tournament/AwardsEditor.vue';
import TableEditor from '@/components/Tournament/TableEditor.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { TabsContent } from '@/components/ui/tabs';
import { useToast } from '@/composables/useToast';
import { useTournament } from '@/composables/useTournament';
import type { TableDetails, TournamentAward, TournamentRuleset } from '@/types/tournament';
import { Loader2, Plus } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

/**
 * The organizer's Details tab: the long-form event page (rich text with images) and, for rulesets that ask for it,
 * a name / notes / photo for every table so players know the terrain before they sit down.
 */
const props = defineProps<{
    tournament: {
        uuid: string;
        details?: Record<string, unknown> | null;
        players: { id: number; display_name: string; is_ringer?: boolean; dropped_after_round: number | null; is_disqualified?: boolean }[];
        rounds: { games: { table_number: number | null }[] }[];
    };
    ruleset: TournamentRuleset;
    tables: TableDetails;
    awards: TournamentAward[];
}>();
const emit = defineEmits<{ (e: 'saved'): void }>();

const toast = useToast();
const { doModalAction } = useTournament(computed(() => props.tournament as never));

const empty = { type: 'doc', content: [] };
const doc = ref<Record<string, unknown>>((props.tournament.details as Record<string, unknown>) ?? empty);
const savedJson = ref(JSON.stringify(props.tournament.details ?? empty));
const saving = ref(false);
const error = ref<string | null>(null);

watch(
    () => props.tournament.details,
    (d) => {
        // Only take the server's copy when the organizer has nothing unsaved.
        if (JSON.stringify(doc.value) === savedJson.value) doc.value = (d as Record<string, unknown>) ?? empty;
        savedJson.value = JSON.stringify(d ?? empty);
    },
);

const dirty = computed(() => JSON.stringify(doc.value) !== savedJson.value);

const saveDetails = async () => {
    saving.value = true;
    error.value = null;
    const { ok, error: message } = await doModalAction(route('tournaments.details', props.tournament.uuid), 'PUT', { details: doc.value });
    saving.value = false;
    if (!ok) {
        error.value = message;
        return;
    }
    toast.success('Event details saved');
    emit('saved');
};

// Tables: one per pairing at the busiest round, plus any the organizer already described or added by hand.
const extra = ref(0);
const tableCount = computed(() => {
    const active = props.tournament.players.filter((p) => !p.is_disqualified && p.dropped_after_round === null).length;
    const used = Math.max(0, ...props.tournament.rounds.flatMap((r) => r.games.map((g) => g.table_number ?? 0)));
    const described = Math.max(0, ...Object.keys(props.tables).map(Number));
    return Math.max(Math.floor(active / 2), used, described, 1) + extra.value;
});
</script>

<template>
    <TabsContent value="details" class="space-y-4">
        <Card>
            <CardContent class="space-y-3 p-4 sm:p-5">
                <div>
                    <h3 class="text-sm font-semibold">Event details</h3>
                    <p class="text-xs text-muted-foreground">
                        The long version: venue and schedule, rules and house rules, prizes, what to bring. Shown to everyone on the event page.
                    </p>
                </div>
                <TipTapEditor
                    v-model="doc"
                    :entities="false"
                    :upload-image-route="route('tournaments.details.image', tournament.uuid)"
                    placeholder="Venue, schedule, rules, prizes…"
                    aria-label="Event details"
                />
                <p v-if="error" class="text-xs text-destructive" role="alert">{{ error }}</p>
                <div class="flex justify-end">
                    <Button type="button" size="sm" :disabled="!dirty || saving" @click="saveDetails">
                        <Loader2 v-if="saving" class="mr-1 size-3.5 animate-spin" /> Save details
                    </Button>
                </div>
            </CardContent>
        </Card>

        <AwardsEditor :tournament="tournament" :awards="awards" :players="tournament.players" @saved="emit('saved')" />

        <Card v-if="ruleset.supports_table_details">
            <CardContent class="space-y-3 p-4 sm:p-5">
                <div>
                    <h3 class="text-sm font-semibold">Tables and terrain</h3>
                    <p class="text-xs text-muted-foreground">
                        Name each table, describe its terrain and attach a photo or layout. Players see it next to their pairing.
                    </p>
                </div>
                <TableEditor
                    v-for="n in tableCount"
                    :key="n"
                    :tournament-uuid="tournament.uuid"
                    :number="n"
                    :detail="tables[n] ?? null"
                    @saved="emit('saved')"
                />
                <Button type="button" variant="outline" size="sm" class="gap-1 text-xs" @click="extra++">
                    <Plus class="size-3.5" /> Add another table
                </Button>
            </CardContent>
        </Card>
    </TabsContent>
</template>
