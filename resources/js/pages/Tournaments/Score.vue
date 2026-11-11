<script setup lang="ts">
import PageBanner from '@/components/PageBanner.vue';
import ScoreEntryDialog from '@/components/Tournament/ScoreEntryDialog.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { useTournamentChannel } from '@/composables/useTournamentChannel';
import { useTournament } from '@/composables/useTournament';
import {
    isScoredResult,
    tableLabel,
    type MasterOption,
    type StandingEntry,
    type TableDetails,
    type TournamentGame,
    type TournamentRound,
    type TournamentRuleset,
} from '@/types/tournament';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, ClipboardCheck, Pencil } from 'lucide-vue-next';
import { computed, ref } from 'vue';

/**
 * The scoring desk: every pairing of the event with a button to enter or correct its result, for scorekeepers (and
 * organizers who only want to score). Nothing here changes pairings, players or settings.
 */
interface DeskPlayer {
    id: number;
    display_name: string;
    faction: string | null;
    is_disqualified?: boolean;
    dropped_after_round?: number | null;
}
interface DeskTournament {
    id: number;
    uuid: string;
    name: string;
    status: string;
    players: DeskPlayer[];
    rounds: TournamentRound[];
    [key: string]: unknown;
}

const props = defineProps<{
    tournament: DeskTournament;
    ruleset: TournamentRuleset;
    standings: StandingEntry[];
    tables: TableDetails;
    /** Deferred: null until the follow-up request fills it in (and always null for rulesets that do not track leaders). */
    masters: MasterOption[] | null;
}>();

const tournamentRef = computed(() => props.tournament);
const { reloadProps } = useTournament(tournamentRef);
const reload = () => reloadProps(['tournament', 'standings']);
useTournamentChannel(props.tournament.uuid, reload);

// Open on the round being played, else the latest one.
const initialRound = () => {
    const live = props.tournament.rounds.find((r) => r.status === 'in_progress');
    return (live ?? props.tournament.rounds[props.tournament.rounds.length - 1])?.round_number ?? null;
};
const roundNumber = ref<number | null>(initialRound());
const round = computed(() => props.tournament.rounds.find((r) => r.round_number === roundNumber.value) ?? null);

const nameOf = (id: number | null) => props.tournament.players.find((p) => p.id === id)?.display_name ?? '—';
const games = computed(() => (round.value?.games ?? []).filter((g) => !g.is_bye));
const byes = computed(() => (round.value?.games ?? []).filter((g) => g.is_bye));
const reported = computed(() => games.value.filter((g) => isScoredResult(g.result)).length);

const lock = computed(() => {
    if (!round.value) return null;
    if (round.value.status === 'setup') return 'This round has not started yet — scores can be entered once it does.';
    if (round.value.status === 'completed') return 'This round is finished — its scores are locked.';
    return null;
});

const scoreGame = ref<TournamentGame | null>(null);
const scoreLabel = (g: TournamentGame) => (g.player_one_vp !== null && isScoredResult(g.result) ? `${g.player_one_vp} – ${g.player_two_vp}` : null);
</script>

<template>
    <Head :title="`${tournament.name} — scoring desk`" />
    <PageBanner :title="tournament.name" class="mb-2">
        <template #subtitle>
            <div class="my-auto flex items-center gap-2 px-2 py-0 text-xs text-muted-foreground md:py-2 md:text-sm">
                <ClipboardCheck class="size-3.5" /> Scoring desk
            </div>
        </template>
    </PageBanner>

    <div class="container mx-auto space-y-4 pb-8 sm:px-4">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <Button as-child variant="ghost" class="gap-1.5 text-sm">
                <Link :href="route('tournaments.view', tournament.uuid)"><ArrowLeft class="size-4" /> Event page</Link>
            </Button>
            <div v-if="tournament.rounds.length" class="flex flex-wrap gap-1.5" role="group" aria-label="Round">
                <Button
                    v-for="r in tournament.rounds"
                    :key="r.id"
                    size="sm"
                    :variant="r.round_number === roundNumber ? 'default' : 'outline'"
                    :aria-pressed="r.round_number === roundNumber"
                    @click="roundNumber = r.round_number"
                >
                    R{{ r.round_number }}
                </Button>
            </div>
        </div>

        <Card v-if="!round">
            <CardContent class="p-6 text-center text-sm text-muted-foreground">There are no rounds to score yet.</CardContent>
        </Card>
        <template v-else>
            <div class="flex flex-wrap items-center gap-2 text-sm">
                <span class="font-semibold">Round {{ round.round_number }}</span>
                <Badge variant="outline" class="text-2xs">{{ games.length ? `${reported} of ${games.length} reported` : 'No pairings' }}</Badge>
            </div>
            <p v-if="lock" class="rounded-md border bg-muted/40 px-3 py-2 text-sm text-muted-foreground">{{ lock }}</p>

            <ul class="grid gap-2 sm:grid-cols-2">
                <li v-for="g in games" :key="g.id" class="rounded-lg border bg-card p-3" :data-game="g.id">
                    <div class="mb-1.5 flex items-center gap-2 text-xs text-muted-foreground">
                        <span v-if="g.table_number">{{ tableLabel(g.table_number, tables) }}</span>
                        <Badge v-if="g.report_status === 'pending'" variant="outline" class="ml-auto px-1 py-0 text-2xs">Player report waiting</Badge>
                        <Badge v-else-if="g.report_status === 'disputed'" variant="destructive" class="ml-auto px-1 py-0 text-2xs">Disputed</Badge>
                        <Badge v-else-if="g.is_forfeit" variant="destructive" class="ml-auto px-1 py-0 text-2xs">Forfeit</Badge>
                    </div>
                    <div class="flex items-center gap-3">
                        <div class="min-w-0 flex-1 text-sm font-medium">
                            <div class="truncate">{{ nameOf(g.player_one_id) }}</div>
                            <div class="truncate">{{ nameOf(g.player_two_id) }}</div>
                        </div>
                        <div class="shrink-0 text-right">
                            <div v-if="scoreLabel(g)" class="text-lg font-bold tabular-nums">{{ scoreLabel(g) }}</div>
                            <div v-else class="text-xs text-muted-foreground">Not reported</div>
                        </div>
                    </div>
                    <Button
                        class="mt-2 w-full gap-1.5"
                        size="sm"
                        :variant="scoreLabel(g) ? 'outline' : 'default'"
                        :disabled="round.status !== 'in_progress'"
                        @click="scoreGame = g"
                    >
                        <Pencil class="size-3.5" /> {{ scoreLabel(g) ? 'Edit score' : 'Enter score' }}
                    </Button>
                </li>
            </ul>
            <p v-if="byes.length" class="text-xs text-muted-foreground">Bye: {{ byes.map((g) => nameOf(g.player_one_id)).join(', ') }}</p>
        </template>
    </div>

    <ScoreEntryDialog :game="scoreGame" :tournament="tournament" :ruleset="ruleset" :masters="masters" @close="scoreGame = null" />
</template>
