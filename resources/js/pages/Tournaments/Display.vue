<script setup lang="ts">
import AllegianceLogo from '@/components/AllegianceLogo.vue';
import FactionLogo from '@/components/FactionLogo.vue';
import RoundClock from '@/components/Tournament/RoundClock.vue';
import StandingsTable from '@/components/Tournament/StandingsTable.vue';
import { useTournamentChannel } from '@/composables/useTournamentChannel';
import type { StandingEntry, TosDeploymentInfo, TosOperationInfo, TournamentRuleset } from '@/types/tournament';
import { Head, Link } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted, computed, ref } from 'vue';

/**
 * Big-screen mode for a TV / projector at the venue. Shows the current round's pairings by table with
 * the scenario and clock, rotating with the standings; updates live through the tournament channel.
 */
defineOptions({ layout: null });

interface DisplayPlayer {
    id: number;
    display_name: string;
    faction: string | null;
    allegiance?: { slug: string; name: string } | null;
    members?: { display_name: string }[];
}

interface DisplayGame {
    id: number;
    table_number: number | null;
    player_one_id: number;
    player_two_id: number | null;
    player_one_faction: string | null;
    player_two_faction: string | null;
    player_one_vp: number | null;
    player_two_vp: number | null;
    is_bye: boolean;
    is_forfeit: boolean;
    result: string;
    report_status?: 'pending' | 'disputed' | null;
}

interface DisplayRound {
    id: number;
    round_number: number;
    status: string;
    started_at: string | null;
    deployment: { label: string } | null;
    strategy: { name: string } | null;
    schemes: { id: number; name: string }[];
    tos_scenario: { operation: TosOperationInfo | null; deployment: TosDeploymentInfo | null } | null;
    games: DisplayGame[];
}

const props = defineProps<{
    tournament: { uuid: string; name: string; status: string; planned_rounds: number; round_time_limit: number; players: DisplayPlayer[] };
    ruleset: TournamentRuleset;
    rounds: DisplayRound[];
    standings: StandingEntry[];
}>();

useTournamentChannel(props.tournament.uuid);

// The round everyone cares about: the one being played, else the latest one that has pairings, else the newest.
const round = computed<DisplayRound | null>(() => {
    const inProgress = props.rounds.find((r) => r.status === 'in_progress');
    if (inProgress) return inProgress;
    const paired = [...props.rounds].reverse().find((r) => r.games.length > 0);
    return paired ?? props.rounds[props.rounds.length - 1] ?? null;
});

const playersById = computed(() => new Map(props.tournament.players.map((p) => [p.id, p])));
const nameOf = (id: number | null) => (id === null ? 'BYE' : (playersById.value.get(id)?.display_name ?? 'TBD'));
const factionOf = (id: number | null, snapshot: string | null) => snapshot || (id === null ? null : (playersById.value.get(id)?.faction ?? null));
const membersOf = (id: number | null) =>
    id === null
        ? null
        : playersById.value
              .get(id)
              ?.members?.map((m) => m.display_name)
              .join(' · ') || null;
const allegianceOf = (id: number | null) => (id === null ? null : (playersById.value.get(id)?.allegiance?.slug ?? null));

const roundLabel = computed(() => {
    const r = round.value;
    if (!r) return 'Waiting for round 1';
    const state =
        r.status === 'in_progress'
            ? 'in progress'
            : r.status === 'completed'
              ? 'complete'
              : r.games.length
                ? 'paired — find your table'
                : 'being set up';
    return `Round ${r.round_number} of ${props.tournament.planned_rounds} · ${state}`;
});

const scenario = computed(() => {
    const r = round.value;
    if (!r) return [] as { label: string; value: string }[];
    const out: { label: string; value: string }[] = [];
    if (r.tos_scenario?.operation) out.push({ label: 'Operation', value: r.tos_scenario.operation.name });
    if (r.tos_scenario?.deployment) out.push({ label: 'Deployment', value: r.tos_scenario.deployment.name });
    if (r.deployment) out.push({ label: 'Deployment', value: r.deployment.label });
    if (r.strategy) out.push({ label: 'Strategy', value: r.strategy.name });
    if (r.schemes.length) out.push({ label: 'Schemes', value: r.schemes.map((s) => s.name).join(' · ') });
    return out;
});

const played = (g: DisplayGame) => g.result !== 'pending' && !g.is_bye;
const scoreText = (g: DisplayGame) => (played(g) ? `${g.player_one_vp ?? 0} – ${g.player_two_vp ?? 0}` : g.report_status ? 'reported' : '');

// Rotate between pairings and standings; a TO can pin one with the buttons.
type Mode = 'auto' | 'pairings' | 'standings';
const mode = ref<Mode>('auto');
const showing = ref<'pairings' | 'standings'>('pairings');
let rotateTimer: ReturnType<typeof setInterval> | undefined;
onMounted(() => {
    rotateTimer = setInterval(() => {
        if (mode.value === 'auto') showing.value = showing.value === 'pairings' ? 'standings' : 'pairings';
    }, 20_000);
});
onBeforeUnmount(() => clearInterval(rotateTimer));
const visible = computed(() => (mode.value === 'auto' ? showing.value : mode.value));
const setMode = (m: Mode) => {
    mode.value = m;
    if (m !== 'auto') showing.value = m;
};
</script>

<template>
    <Head :title="`${tournament.name} — Display`" />
    <div class="min-h-screen bg-background p-4 text-foreground sm:p-8">
        <header class="mb-6 flex flex-wrap items-end justify-between gap-4 border-b pb-4">
            <div class="min-w-0">
                <h1 class="truncate text-3xl font-bold sm:text-5xl">{{ tournament.name }}</h1>
                <p class="mt-1 text-lg text-muted-foreground sm:text-2xl">{{ roundLabel }}</p>
            </div>
            <div class="flex items-center gap-4">
                <RoundClock
                    v-if="round?.status === 'in_progress' && round.started_at"
                    :started-at="round.started_at"
                    :limit-minutes="tournament.round_time_limit"
                    class="px-4 py-2 text-3xl sm:text-5xl"
                />
                <nav class="flex gap-1 text-xs" aria-label="Display mode" data-no-print>
                    <button
                        v-for="m in ['auto', 'pairings', 'standings'] as Mode[]"
                        :key="m"
                        type="button"
                        class="rounded-md border px-2 py-1 capitalize"
                        :class="mode === m ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:bg-accent'"
                        @click="setMode(m)"
                    >
                        {{ m }}
                    </button>
                    <Link
                        :href="route('tournaments.view', tournament.uuid)"
                        class="rounded-md border px-2 py-1 text-muted-foreground hover:bg-accent"
                    >
                        Exit
                    </Link>
                </nav>
            </div>
        </header>

        <section v-if="visible === 'pairings'" aria-label="Pairings">
            <p v-if="scenario.length" class="mb-6 flex flex-wrap gap-x-8 gap-y-1 text-xl sm:text-2xl">
                <span v-for="s in scenario" :key="s.label + s.value">
                    <span class="text-muted-foreground">{{ s.label }}:</span> <span class="font-semibold">{{ s.value }}</span>
                </span>
            </p>

            <p v-if="!round || !round.games.length" class="py-24 text-center text-3xl text-muted-foreground">Pairings will appear here.</p>
            <ul v-else class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                <li
                    v-for="g in round.games"
                    :key="g.id"
                    class="flex items-center gap-4 rounded-xl border bg-card p-4"
                    :class="played(g) ? 'opacity-60' : ''"
                >
                    <div class="flex size-16 shrink-0 flex-col items-center justify-center rounded-lg bg-primary/10 text-primary">
                        <span class="text-2xs uppercase tracking-wider">Table</span>
                        <span class="text-3xl font-bold tabular-nums">{{ g.table_number ?? '–' }}</span>
                    </div>
                    <div class="min-w-0 flex-1 space-y-1 text-xl font-semibold sm:text-2xl">
                        <div
                            v-for="side in [
                                ['player_one_id', 'player_one_faction'],
                                ['player_two_id', 'player_two_faction'],
                            ] as const"
                            :key="side[0]"
                            class="flex items-center gap-2"
                        >
                            <FactionLogo
                                v-if="factionOf(g[side[0]], g[side[1]])"
                                :faction="factionOf(g[side[0]], g[side[1]])!"
                                class-name="size-6 shrink-0"
                            />
                            <AllegianceLogo
                                v-else-if="allegianceOf(g[side[0]])"
                                :allegiance="allegianceOf(g[side[0]])!"
                                class-name="size-6 shrink-0"
                            />
                            <span class="truncate">{{ g.is_bye && side[0] === 'player_two_id' ? 'BYE' : nameOf(g[side[0]]) }}</span>
                            <span v-if="membersOf(g[side[0]])" class="truncate text-base font-normal text-muted-foreground">{{
                                membersOf(g[side[0]])
                            }}</span>
                        </div>
                    </div>
                    <div class="shrink-0 text-right text-2xl font-bold tabular-nums">{{ scoreText(g) }}</div>
                </li>
            </ul>
        </section>

        <section v-else aria-label="Standings" class="text-lg sm:text-xl [&_td]:py-3 [&_td]:text-base sm:[&_td]:text-xl [&_th]:py-3 [&_th]:text-sm">
            <StandingsTable :standings="standings" />
        </section>
    </div>
</template>
