<script setup lang="ts">
import AnnouncementBanner from '@/components/Tournament/AnnouncementBanner.vue';
import AllegianceLogo from '@/components/AllegianceLogo.vue';
import FactionLogo from '@/components/FactionLogo.vue';
import RoundClock from '@/components/Tournament/RoundClock.vue';
import StandingsTable from '@/components/Tournament/StandingsTable.vue';
import TournamentLiveRegion from '@/components/Tournament/TournamentLiveRegion.vue';
import { useTournamentAnnouncements } from '@/composables/useTournamentAnnouncements';
import { useTournamentChannel } from '@/composables/useTournamentChannel';
import type { StandingEntry, TosDeploymentInfo, TosOperationInfo, TournamentRuleset } from '@/types/tournament';
import { Head, Link, router } from '@inertiajs/vue3';
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
    label?: string;
    is_cut?: boolean;
    status: string;
    started_at: string | null;
    deployment: { label: string } | null;
    strategy: { name: string } | null;
    schemes: { id: number; name: string }[];
    tos_scenario: { operation: TosOperationInfo | null; deployment: TosDeploymentInfo | null } | null;
    games: DisplayGame[];
}

const props = defineProps<{
    /** The organizer's table names, keyed by table number. */
    tables?: Record<string, { name: string | null }>;
    tournament: {
        uuid: string;
        name: string;
        status: string;
        planned_rounds: number;
        round_time_limit: number;
        players: DisplayPlayer[];
        announcement?: string | null;
        announced_at?: string | null;
    };
    ruleset: TournamentRuleset;
    rounds: DisplayRound[];
    standings: StandingEntry[];
}>();

// Narrow reloads: the display only reads these props, and a TV refreshes on every score.
const { connected } = useTournamentChannel(props.tournament.uuid, () =>
    router.reload({ only: ['tournament', 'rounds', 'standings', 'ruleset'], preserveScroll: true, preserveState: true }),
);

const { message: liveMessage } = useTournamentAnnouncements(
    () => props.rounds,
    () => props.tournament.status,
);

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
    return r.label && r.label !== `Round ${r.round_number}`
        ? `${r.label} · ${state}`
        : `Round ${r.round_number} of ${props.tournament.planned_rounds} · ${state}`;
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
const vpOf = (g: DisplayGame, key: 'player_one_id' | 'player_two_id') => (key === 'player_one_id' ? g.player_one_vp : g.player_two_vp);
const sideState = (g: DisplayGame, key: 'player_one_id' | 'player_two_id'): 'win' | 'loss' | 'tie' | null => {
    if (!played(g)) return null;
    const mine = vpOf(g, key) ?? 0;
    const theirs = vpOf(g, key === 'player_one_id' ? 'player_two_id' : 'player_one_id') ?? 0;
    return mine > theirs ? 'win' : mine < theirs ? 'loss' : 'tie';
};
const gameChip = (g: DisplayGame): { label: string; tone: string } | null => {
    if (g.is_bye) return null;
    if (played(g)) return { label: 'Final', tone: 'text-emerald-400' };
    if (g.report_status) return { label: 'Reported', tone: 'text-amber-400' };
    if (round.value?.status === 'in_progress') return { label: 'Playing', tone: 'text-amber-300' };
    return null;
};

// Before anything is scored, a table of zeros says nothing: show who is playing instead.
const nothingScoredYet = computed(() => props.standings.every((e) => e.rounds_played === 0) && props.tournament.status !== 'completed');
const roster = computed(() =>
    props.tournament.players
        .filter((p) => !p.is_disqualified)
        .map((p) => p.display_name)
        .sort((a, b) => a.localeCompare(b)),
);

// Controls fade out after a few idle seconds so a venue TV shows the event, not the buttons.
const controlsVisible = ref(true);
let idleTimer: ReturnType<typeof setTimeout> | undefined;
const wake = () => {
    controlsVisible.value = true;
    clearTimeout(idleTimer);
    idleTimer = setTimeout(() => (controlsVisible.value = false), 5000);
};

// A scannable link to the public page, so people can follow on their phone.
const qr = ref('');
onMounted(async () => {
    wake();
    try {
        const QRCode = (await import('qrcode')).default;
        qr.value = await QRCode.toDataURL(route('tournaments.view', props.tournament.uuid), { width: 220, margin: 1 });
    } catch {
        /* a missing QR is not worth breaking the display for */
    }
});

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
onBeforeUnmount(() => {
    clearInterval(rotateTimer);
    clearTimeout(idleTimer);
});
const visible = computed(() => (mode.value === 'auto' ? showing.value : mode.value));
const setMode = (m: Mode) => {
    mode.value = m;
    if (m !== 'auto') showing.value = m;
};
</script>

<template>
    <Head :title="`${tournament.name} — Display`" />
    <main class="min-h-screen bg-background p-4 text-foreground sm:p-8" @mousemove="wake" @pointerdown="wake" @keydown="wake" @focusin="wake">
        <TournamentLiveRegion :message="liveMessage" />
        <header class="mb-6 flex flex-wrap items-end justify-between gap-4 border-b pb-4">
            <div class="min-w-0">
                <h1 class="truncate text-3xl font-bold sm:text-5xl">{{ tournament.name }}</h1>
                <p class="mt-1 text-lg text-muted-foreground sm:text-2xl">
                    {{ roundLabel }}
                    <span
                        role="status"
                        class="ml-3 inline-flex items-center gap-1.5 align-middle text-sm"
                        :class="connected ? 'text-emerald-500' : 'text-amber-500'"
                    >
                        <span class="size-2 rounded-full bg-current" :class="connected ? '' : 'animate-pulse'" aria-hidden="true" />
                        {{ connected ? 'Live' : 'Reconnecting — refreshing every 30s' }}
                    </span>
                </p>
            </div>
            <div class="flex items-center gap-4">
                <RoundClock
                    v-if="round?.status === 'in_progress' && round.started_at"
                    :started-at="round.started_at"
                    :limit-minutes="tournament.round_time_limit"
                    class="px-4 py-2 text-3xl sm:text-5xl"
                />
                <nav
                    class="flex gap-1.5 text-sm transition-opacity duration-500 has-[:focus-visible]:opacity-100"
                    :class="controlsVisible ? 'opacity-100' : 'opacity-0'"
                    aria-label="Display mode"
                    data-no-print
                >
                    <button
                        v-for="m in ['auto', 'pairings', 'standings'] as Mode[]"
                        :key="m"
                        type="button"
                        class="rounded-md border px-3 py-1.5 capitalize"
                        :class="mode === m ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:bg-accent'"
                        @click="setMode(m)"
                    >
                        {{ m }}
                    </button>
                    <Link
                        :href="route('tournaments.view', tournament.uuid)"
                        class="rounded-md border px-3 py-1.5 text-muted-foreground hover:bg-accent"
                    >
                        Exit
                    </Link>
                </nav>
            </div>
        </header>

        <AnnouncementBanner
            v-if="tournament.announcement"
            :text="tournament.announcement"
            :announced-at="tournament.announced_at"
            large
            class="mb-6"
        />

        <section v-if="visible === 'pairings'" aria-label="Pairings">
            <p v-if="scenario.length" class="mb-6 flex flex-wrap gap-x-8 gap-y-1 text-xl sm:text-2xl">
                <span v-for="s in scenario" :key="s.label + s.value">
                    <span class="text-muted-foreground">{{ s.label }}:</span> <span class="font-semibold">{{ s.value }}</span>
                </span>
            </p>

            <p
                v-if="round && round.status === 'setup' && round.games.length"
                class="mb-6 rounded-xl bg-primary/15 px-5 py-3 text-2xl font-semibold text-primary sm:text-3xl"
            >
                {{ round.label ?? `Round ${round.round_number}` }} {{ round.is_cut ? 'are' : 'is' }} paired — find your table
            </p>

            <p v-if="!round || !round.games.length" class="py-24 text-center text-3xl text-muted-foreground">Pairings will appear here.</p>
            <ul v-else class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                <li v-for="g in round.games" :key="g.id" class="flex items-center gap-4 rounded-xl border bg-card p-4">
                    <div class="flex size-16 shrink-0 flex-col items-center justify-center rounded-lg bg-primary/10 text-primary">
                        <span class="text-2xs uppercase tracking-wider">Table</span>
                        <span class="text-3xl font-bold tabular-nums">{{ g.table_number ?? '–' }}</span>
                    </div>
                    <div class="min-w-0 flex-1 space-y-1 text-xl font-semibold sm:text-2xl">
                        <div v-if="tables?.[g.table_number ?? 0]?.name" class="truncate text-base font-normal text-muted-foreground">
                            {{ tables[g.table_number ?? 0].name }}
                        </div>
                        <div
                            v-for="side in [
                                ['player_one_id', 'player_one_faction'],
                                ['player_two_id', 'player_two_faction'],
                            ] as const"
                            :key="side[0]"
                            class="flex items-center gap-2"
                            :class="
                                sideState(g, side[0]) === 'loss' ? 'text-muted-foreground' : sideState(g, side[0]) === 'win' ? 'text-foreground' : ''
                            "
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
                            <span class="truncate" :class="sideState(g, side[0]) === 'win' ? 'font-extrabold' : ''">{{
                                g.is_bye && side[0] === 'player_two_id' ? 'BYE' : nameOf(g[side[0]])
                            }}</span>
                            <span v-if="membersOf(g[side[0]])" class="truncate text-base font-normal text-muted-foreground">{{
                                membersOf(g[side[0]])
                            }}</span>
                        </div>
                    </div>
                    <div class="shrink-0 space-y-1 text-right">
                        <div v-if="played(g)" class="space-y-1 text-2xl font-bold tabular-nums">
                            <div :class="sideState(g, 'player_one_id') === 'loss' ? 'text-muted-foreground' : ''">{{ g.player_one_vp ?? 0 }}</div>
                            <div :class="sideState(g, 'player_two_id') === 'loss' ? 'text-muted-foreground' : ''">{{ g.player_two_vp ?? 0 }}</div>
                        </div>
                        <div v-if="gameChip(g)" class="text-sm font-semibold uppercase tracking-wide" :class="gameChip(g)!.tone">
                            {{ gameChip(g)!.label }}
                        </div>
                    </div>
                </li>
            </ul>
        </section>

        <section v-else aria-label="Standings" class="text-lg sm:text-xl [&_td]:py-3 [&_td]:text-base sm:[&_td]:text-xl [&_th]:py-3 [&_th]:text-sm">
            <div v-if="nothingScoredYet" class="py-8">
                <p class="mb-6 text-center text-3xl text-muted-foreground">First results will appear here</p>
                <ul class="mx-auto grid max-w-5xl gap-x-6 gap-y-2 text-xl sm:grid-cols-2 sm:text-2xl lg:grid-cols-3">
                    <li v-for="name in roster" :key="name" class="truncate">{{ name }}</li>
                </ul>
            </div>
            <StandingsTable v-else :standings="standings" />
        </section>

        <!-- Scan to follow on a phone -->
        <div v-if="qr" class="fixed bottom-4 right-4 flex items-center gap-3 rounded-xl bg-white p-2 pr-4 text-black shadow-lg" data-no-print>
            <img :src="qr" alt="QR code for the tournament page" class="size-24" />
            <span class="max-w-28 text-sm font-semibold leading-tight">Scan to follow along</span>
        </div>
    </main>
</template>
