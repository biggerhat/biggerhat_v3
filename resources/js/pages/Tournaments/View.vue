<script setup lang="ts">
import AllegianceLogo from '@/components/AllegianceLogo.vue';
import ScoringSummary from '@/components/Tournament/ScoringSummary.vue';
import { formatGameSize, tosOperationText, type TosDeploymentInfo, type TosOperationInfo, type TournamentRuleset } from '@/types/tournament';
import FactionLogo from '@/components/FactionLogo.vue';
import PageBanner from '@/components/PageBanner.vue';
import MyMatchCard from '@/components/Tournament/MyMatchCard.vue';
import PairingRow from '@/components/Tournament/PairingRow.vue';
import RoundClock from '@/components/Tournament/RoundClock.vue';
import StandingsTable from '@/components/Tournament/StandingsTable.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Drawer, DrawerClose, DrawerContent, DrawerFooter, DrawerHeader, DrawerTitle } from '@/components/ui/drawer';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useTournamentChannel } from '@/composables/useTournamentChannel';
import { formatDateOnly } from '@/lib/utils';
import { type SharedData } from '@/types';
import { Head, router, usePage } from '@inertiajs/vue3';
import { BarChart3, CalendarDays, MapPin, MonitorPlay, Printer, Trophy, UserPlus } from 'lucide-vue-next';
import { computed, nextTick, ref } from 'vue';

interface TournamentPlayer {
    id: number;
    display_name: string;
    faction: string | null;
    user: { id: number; name: string } | null;
    /** Team events: the people on this team (their accounts count as playing for it). */
    members?: { id: number; display_name: string; user_id: number | null }[];
    is_ringer: boolean;
    is_disqualified: boolean;
    dropped_after_round: number | null;
}

interface ViewGame {
    id: number;
    report_status?: 'pending' | 'disputed' | null;
    reported_by_player_id?: number | null;
    reported_score?: { player_one: Record<string, number>; player_two: Record<string, number>; agreed: boolean } | null;
    report_note?: string | null;
    player_one_id: number;
    player_two_id: number | null;
    player_one: TournamentPlayer;
    player_two: TournamentPlayer | null;
    player_one_faction: string | null;
    player_one_master: string | null;
    player_one_title: string | null;
    player_one_vp: number | null;
    player_two_faction: string | null;
    player_two_master: string | null;
    player_two_title: string | null;
    player_two_vp: number | null;
    is_bye: boolean;
    is_forfeit: boolean;
    result: string;
    table_number: number | null;
    game_id: number | null;
    tracker_game: {
        id: number;
        uuid: string;
        status?: string;
        is_solo?: boolean;
        current_turn?: number | null;
        max_turns?: number | null;
    } | null;
}

interface ViewRound {
    id: number;
    round_number: number;
    status: string;
    started_at: string | null;
    deployment: { value: string; label: string; description: string; image_url: string | null } | null;
    strategy: { id: number; name: string; image_url: string | null } | null;
    schemes: { id: number; name: string; image_url: string | null; prerequisite: string | null; reveal: string | null; scoring: string | null }[];
    /** TOS-style scenario resolved from reference data (null for Malifaux rulesets). */
    tos_scenario: { operation: TosOperationInfo | null; deployment: TosDeploymentInfo | null } | null;
    games: ViewGame[];
}

interface StandingEntry {
    rank: number | null;
    player_id: number;
    display_name: string;
    faction: string | null;
    allegiance?: { slug: string; name: string } | null;
    is_ringer: boolean;
    is_dropped: boolean;
    total_tp: number;
    total_diff: number;
    total_vp: number;
    total_sos: number;
    rounds_played: number;
}

interface FactionInfo {
    name: string;
    slug: string;
    color: string;
    logo: string;
}

interface Rsvp {
    id: number;
    user_id: number;
    /** Optional intended faction. Captured at RSVP time so TOs can plan terrain
     *  / meta balance before the player list is finalized. */
    faction: string | null;
    user?: { id: number; name: string } | null;
}

const props = defineProps<{
    tournament: {
        uuid: string;
        name: string;
        description: string | null;
        status: string;
        encounter_size: number;
        encounter_type: string;
        planned_rounds: number;
        season_label: string;
        event_date: string;
        location: string | null;
        round_time_limit: number;
        allow_self_report?: boolean;
        team_size?: number;
        players: TournamentPlayer[];
        organizers: { id: number; name: string }[];
        rsvps?: Rsvp[];
    };
    ruleset: TournamentRuleset;
    /** Allegiances a player can declare when RSVPing (empty unless the ruleset uses them). */
    allegiances: { id: number; slug: string; name: string }[];
    rounds: ViewRound[];
    standings: StandingEntry[];
    factions: Record<string, FactionInfo>;
    scheme_stats: { most_scored: { id: number; name: string; total_points: number; scoring_turns: number } } | null;
}>();

// Live updates via websocket
useTournamentChannel(props.tournament.uuid, () => {
    // Narrow partial reload so the broadcast refresh avoids recomputing the
    // static `factions` enum map on every tick.
    router.reload({ only: ['tournament', 'rounds', 'standings'], preserveScroll: true, preserveState: true });
});

const isCompleted = computed(() => props.tournament.status === 'completed');

// Default to the round currently being played, if any — that's the most
// actionable view mid-tournament (your pairing, live scores). Falls back to
// Standings once nothing is actively in progress: before the first round
// starts, between rounds, or after the tournament completes.
const activeInProgressRound = props.rounds.find((r) => r.status === 'in_progress');
const activeTab = ref(activeInProgressRound ? `r${activeInProgressRound.round_number}` : 'standings');

// Which round the tournament is currently at, for the "Round X of Y" header
// badge — the in-progress round if there is one, else the most recently
// created round (covers the gap between rounds), else null pre-start.
const currentRoundNumber = computed(() => {
    const inProgress = props.rounds.find((r) => r.status === 'in_progress');
    if (inProgress) return inProgress.round_number;
    // Rounds are created up front (empty, in Setup) — only count ones that have been paired or played, so a
    // tournament that hasn't started doesn't claim to be "Round 3 of 3".
    const started = props.rounds.filter((r) => r.status === 'completed' || r.games.length > 0);
    return started.length ? Math.max(...started.map((r) => r.round_number)) : null;
});

const page = usePage<SharedData>();
const isLoggedIn = computed(() => !!page.props.auth.user);
const currentUserId = computed(() => page.props.auth.user?.id);
// A user plays either as an individual player or as a member of a team.
const playsFor = (p: TournamentPlayer) =>
    p.user?.id === currentUserId.value || !!p.members?.some((m) => !!currentUserId.value && m.user_id === currentUserId.value);
const isRegistered = computed(() => props.tournament.players.some(playsFor));
const hasRsvped = computed(() => props.tournament.rsvps?.some((r) => r.user_id === currentUserId.value) ?? false);
const canRsvp = computed(
    () =>
        isLoggedIn.value &&
        (props.tournament.status === 'draft' || props.tournament.status === 'registration') &&
        !hasRsvped.value &&
        !isRegistered.value,
);
const myPlayerRecord = computed(() => props.tournament.players.find(playsFor) as ({ checked_in_at?: string | null } & TournamentPlayer) | undefined);
const checkingIn = ref(false);
const toggleCheckIn = () => {
    checkingIn.value = true;
    const opts = { preserveScroll: true, onFinish: () => (checkingIn.value = false) };
    if (myPlayerRecord.value?.checked_in_at) router.delete(route('tournaments.check_in.cancel', props.tournament.uuid), opts);
    else router.post(route('tournaments.check_in', props.tournament.uuid), {}, opts);
};
const canCheckIn = computed(() => ['registration', 'active'].includes(props.tournament.status));

const myPlayerId = computed(() => props.tournament.players.find(playsFor)?.id ?? null);

// The user's pairing in the newest round that isn't finished yet — the "where do I sit, who do I play" answer.
const myMatch = computed(() => {
    if (!myPlayerId.value) return null;
    const newestFirst = [...props.rounds].sort((a, b) => b.round_number - a.round_number);
    for (const round of newestFirst) {
        if (round.status === 'completed') continue;
        const game = round.games.find(isMyGame);
        if (game) return { round, game };
    }
    return null;
});

// Print just this round's pairings: switch to its tab first so the right sheet is on screen.
const printRound = async (roundNumber: number) => {
    activeTab.value = `r${roundNumber}`;
    await nextTick();
    // Paper is white: drop dark mode for the print job, then put it back.
    const root = document.documentElement;
    const wasDark = root.classList.contains('dark');
    if (wasDark) {
        root.classList.remove('dark');
        window.addEventListener('afterprint', () => root.classList.add('dark'), { once: true });
    }
    window.print();
};

const rsvping = ref(false);
// Optional faction the user intends to play. Stays null when they don't pick
// one — the TO can still finalize without it.
const rsvpFaction = ref<string | null>(null);
const rsvpAllegiance = ref<string | null>(null);

// Use Inertia's router rather than raw fetch: CSRF + progress bar come for
// free, and the preserveScroll reload keeps the user's position on the page.
const submitRsvp = () => {
    router.post(
        route('tournaments.rsvp', props.tournament.uuid),
        rsvpFaction.value ? { faction: rsvpFaction.value } : rsvpAllegiance.value ? { allegiance_id: Number(rsvpAllegiance.value) } : {},
        {
            preserveScroll: true,
            onStart: () => (rsvping.value = true),
            onFinish: () => {
                rsvping.value = false;
                rsvpFaction.value = null;
                rsvpAllegiance.value = null;
            },
        },
    );
};

const cancelRsvp = () => {
    router.delete(route('tournaments.rsvp.cancel', props.tournament.uuid), {
        preserveScroll: true,
    });
};

const formatDate = (d: string) => formatDateOnly(d);

const isMyGame = (game: ViewGame): boolean => {
    if (!myPlayerId.value) return false;
    return game.player_one_id === myPlayerId.value || game.player_two_id === myPlayerId.value;
};

const encounterTypeLabel = computed(() => {
    const map: Record<string, string> = {
        traditional: 'Traditional',
        enforcer_brawl: 'Enforcer Brawl',
        crossroads: 'Crossroads',
        team_event: 'Team Event',
        double_rush: 'Double Rush',
        total_war: 'Total War',
    };
    return map[props.tournament.encounter_type] ?? props.tournament.encounter_type;
});

// Tournament stats (for completed tournaments)
const tournamentStats = computed(() => {
    if (!isCompleted.value || !props.standings.length) return null;

    const factionCounts: Record<string, number> = {};
    const masterCounts: Record<string, number> = {};

    for (const round of props.rounds) {
        for (const game of round.games) {
            if (game.player_one_faction) factionCounts[game.player_one_faction] = (factionCounts[game.player_one_faction] ?? 0) + 1;
            if (game.player_two_faction) factionCounts[game.player_two_faction] = (factionCounts[game.player_two_faction] ?? 0) + 1;
            const m1 = game.player_one_title || game.player_one_master;
            const m2 = game.player_two_title || game.player_two_master;
            if (m1) masterCounts[m1] = (masterCounts[m1] ?? 0) + 1;
            if (m2) masterCounts[m2] = (masterCounts[m2] ?? 0) + 1;
        }
    }

    const topFaction = Object.entries(factionCounts).sort(([, a], [, b]) => b - a)[0];
    const topMaster = Object.entries(masterCounts).sort(([, a], [, b]) => b - a)[0];
    const winner = props.standings.find((s) => s.rank === 1 && !s.is_ringer);
    const topVp = [...props.standings].sort((a, b) => b.total_vp - a.total_vp)[0];
    const topDiff = [...props.standings].sort((a, b) => b.total_diff - a.total_diff)[0];
    // Wooden Spoon should reflect the lowest finisher who actually played the
    // whole event — exclude ringers AND anyone who dropped, since neither
    // played a full slate of rounds.
    const spoon = [...props.standings]
        .filter((s) => !s.is_ringer && !s.is_dropped)
        .sort((a, b) => a.total_tp - b.total_tp || a.total_diff - b.total_diff)[0];

    // Best in faction
    const bestInFaction: Record<string, StandingEntry> = {};
    for (const s of props.standings) {
        if (s.is_ringer || !s.faction) continue;
        if (!bestInFaction[s.faction] || s.total_tp > bestInFaction[s.faction].total_tp) {
            bestInFaction[s.faction] = s;
        }
    }

    return {
        winner,
        topVp,
        topDiff,
        spoon,
        topFaction,
        topMaster,
        bestInFaction,
        totalGames: props.rounds.reduce((sum, r) => sum + r.games.filter((g) => !g.is_bye).length, 0),
    };
});

// Drawer
const drawerOpen = ref(false);
const drawerTitle = ref('');
const drawerImage = ref<string | null>(null);
const drawerDescription = ref<string | null>(null);
const openCard = (title: string, image?: string | null, description?: string | null) => {
    drawerTitle.value = title;
    drawerImage.value = image ?? null;
    drawerDescription.value = description ?? null;
    drawerOpen.value = true;
};
</script>

<template>
    <Head :title="tournament.name" />
    <div class="relative">
        <div
            class="pointer-events-none absolute inset-x-0 top-0 h-64 opacity-[0.07] dark:opacity-[0.12]"
            :style="{ background: 'radial-gradient(ellipse at top, hsl(var(--primary)) 0%, transparent 70%)' }"
        />

        <PageBanner :title="tournament.name" class="mb-2">
            <template #subtitle>
                <div class="my-auto flex flex-wrap items-center gap-2 px-2 py-0 text-xs text-muted-foreground md:py-2 md:text-sm">
                    <Badge variant="outline" class="text-2xs">{{
                        tournament.status === 'completed'
                            ? 'Completed'
                            : tournament.status === 'active'
                              ? 'Active'
                              : tournament.status === 'registration'
                                ? 'Registration Open'
                                : 'Draft'
                    }}</Badge>
                    <span v-if="currentRoundNumber !== null">Round {{ currentRoundNumber }} of {{ tournament.planned_rounds }}</span>
                    <span v-if="ruleset.uses_season">{{ tournament.season_label }}</span>
                    <span
                        >{{ formatGameSize(ruleset, tournament.encounter_size)
                        }}{{ ruleset.uses_encounter_type ? ' ' + encounterTypeLabel : '' }}</span
                    >
                    <span v-if="tournament.event_date" class="flex items-center gap-1"
                        ><CalendarDays class="size-3" />{{ formatDate(tournament.event_date) }}</span
                    >
                    <span v-if="tournament.location" class="flex items-center gap-1"><MapPin class="size-3" />{{ tournament.location }}</span>
                    <span>{{ tournament.players.length }} {{ (tournament.team_size ?? 1) > 1 ? 'teams' : 'players' }}</span>
                    <span v-if="(tournament.status === 'draft' || tournament.status === 'registration') && tournament.rsvps?.length"
                        >{{ tournament.rsvps.length }} RSVPs</span
                    >
                </div>
            </template>
        </PageBanner>

        <div class="container mx-auto pb-8 sm:px-4" data-print-area>
            <p v-if="tournament.description" class="mb-4 text-sm text-muted-foreground">{{ tournament.description }}</p>
            <details class="mb-4 rounded-lg border bg-muted/20 px-3 py-2" data-no-print>
                <summary class="cursor-pointer text-sm font-medium">
                    Format &amp; rules
                    <span class="ml-1 text-xs font-normal text-muted-foreground">{{ ruleset.label }}</span>
                    <span v-if="ruleset.scoring_customized || Object.keys(ruleset.active_variants).length" class="ml-1 text-xs text-primary">
                        · {{ ruleset.scoring_customized ? 'modified scoring' : ''
                        }}{{ ruleset.scoring_customized && Object.keys(ruleset.active_variants).length ? ', ' : ''
                        }}{{ Object.keys(ruleset.active_variants).length ? Object.keys(ruleset.active_variants).length + ' variant(s)' : '' }}
                    </span>
                </summary>
                <ScoringSummary :ruleset="ruleset" class="mt-3" />
            </details>

            <!-- RSVP Banner (Draft and Registration phases) -->
            <Card v-if="canRsvp" class="mb-6 border-blue-500/30 bg-blue-500/5">
                <CardContent class="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2 font-semibold">
                            <UserPlus class="size-4 text-blue-600 dark:text-blue-400" />
                            {{ tournament.status === 'registration' ? 'Registration Open' : 'RSVP Open' }}
                        </div>
                        <p class="mt-1 text-xs text-muted-foreground">
                            Express your interest in playing. Optionally pick
                            {{ ruleset.declaration === 'allegiance' ? 'an allegiance' : 'a faction' }} so the organizer can plan accordingly — you can
                            change it before the tournament starts.
                        </p>
                    </div>
                    <div class="flex flex-col items-stretch gap-2 sm:flex-row sm:items-center">
                        <!-- Optional intended faction. Empty string sentinel because
                             Reka's Select can't bind to literal null on its value prop. -->
                        <Select
                            v-if="ruleset.declaration !== 'allegiance'"
                            :model-value="rsvpFaction ?? '__none__'"
                            @update:model-value="rsvpFaction = $event === '__none__' ? null : ($event as string)"
                        >
                            <SelectTrigger class="h-8 w-full min-w-[180px] text-xs sm:w-auto">
                                <SelectValue placeholder="Faction (optional)" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="__none__">No faction yet</SelectItem>
                                <SelectItem v-for="(info, key) in factions" :key="key" :value="String(key)">
                                    <div class="flex items-center gap-2">
                                        <FactionLogo :faction="String(key)" class-name="size-4 shrink-0" />
                                        <span>{{ info.name }}</span>
                                    </div>
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <Select
                            v-else
                            :model-value="rsvpAllegiance ?? '__none__'"
                            @update:model-value="rsvpAllegiance = $event === '__none__' ? null : ($event as string)"
                        >
                            <SelectTrigger class="h-8 w-full min-w-[180px] text-xs sm:w-auto">
                                <SelectValue placeholder="Allegiance (optional)" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="__none__">No allegiance yet</SelectItem>
                                <SelectItem v-for="a in allegiances" :key="a.id" :value="String(a.id)">
                                    <div class="flex items-center gap-2">
                                        <AllegianceLogo :allegiance="a.slug" class-name="size-4 shrink-0" />
                                        <span>{{ a.name }}</span>
                                    </div>
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <Button size="sm" :disabled="rsvping" @click="submitRsvp">
                            <UserPlus class="mr-1.5 size-3.5" /> {{ tournament.status === 'registration' ? 'Register Interest' : 'RSVP' }}
                        </Button>
                    </div>
                </CardContent>
            </Card>
            <Card
                v-else-if="hasRsvped && !isRegistered && (tournament.status === 'draft' || tournament.status === 'registration')"
                class="mb-6 border-blue-500/30 bg-blue-500/5"
            >
                <CardContent class="flex items-center justify-between p-4">
                    <span class="text-sm text-blue-700 dark:text-blue-400">
                        {{
                            tournament.status === 'registration'
                                ? "You've signed up. Waiting for the organizer to confirm you in the player list."
                                : "You've RSVPed for this tournament."
                        }}
                    </span>
                    <Button variant="ghost" size="sm" class="text-xs text-muted-foreground" @click="cancelRsvp">Cancel RSVP</Button>
                </CardContent>
            </Card>
            <!-- Registration confirmation: say what happens next instead of just "registered". -->
            <Card
                v-else-if="isRegistered && (tournament.status === 'registration' || (tournament.status === 'active' && !myMatch))"
                class="mb-6 border-green-500/30 bg-green-500/5"
            >
                <CardContent class="p-4 text-sm">
                    <div class="font-semibold text-green-700 dark:text-green-400">You're registered for this tournament.</div>
                    <div v-if="canCheckIn" class="mt-2 flex items-center gap-2">
                        <Button
                            size="sm"
                            :variant="myPlayerRecord?.checked_in_at ? 'outline' : 'default'"
                            :disabled="checkingIn"
                            @click="toggleCheckIn"
                        >
                            {{ myPlayerRecord?.checked_in_at ? 'Checked in ✓ (undo)' : "I've arrived — check in" }}
                        </Button>
                    </div>
                    <p class="mt-0.5 text-muted-foreground">
                        {{
                            tournament.status === 'registration'
                                ? "When the organizer starts the tournament and pairs Round 1, you'll get a notification with your table and opponent."
                                : "You're between rounds. You'll get a notification with your table as soon as the next round is paired."
                        }}
                    </p>
                </CardContent>
            </Card>

            <MyMatchCard
                v-if="myMatch && myPlayerId && tournament.status === 'active'"
                :round="myMatch.round"
                :game="myMatch.game"
                :my-player-id="myPlayerId"
                :round-time-limit="tournament.round_time_limit"
                :tournament-uuid="tournament.uuid"
                :players="tournament.players"
                :allow-self-report="tournament.allow_self_report"
                :score-fields="ruleset.score_fields"
            />

            <!-- Main Tabs -->
            <Tabs v-model="activeTab">
                <!-- Horizontal scroll on mobile instead of wrapping — keeps a single tidy row
                     even for tournaments with many rounds. Wraps normally on sm+. -->
                <div class="-mx-2 mb-4 overflow-x-auto px-2 sm:mx-0 sm:overflow-visible sm:px-0" data-no-print>
                    <TabsList class="inline-flex w-max min-w-full flex-nowrap gap-1 sm:w-full sm:flex-wrap">
                        <TabsTrigger value="standings" class="text-xs sm:text-sm">
                            <Trophy class="mr-1 size-3" />
                            {{ isCompleted ? 'Final Standings' : 'Standings' }}
                        </TabsTrigger>
                        <TabsTrigger v-for="round in rounds" :key="'tab-' + round.id" :value="'r' + round.round_number" class="text-xs sm:text-sm">
                            R{{ round.round_number }}
                        </TabsTrigger>
                        <TabsTrigger v-if="tournament.rsvps?.length" value="rsvps" class="text-xs sm:text-sm">
                            RSVPs
                            <Badge variant="secondary" class="ml-1 px-1 py-0 text-2xs">{{ tournament.rsvps.length }}</Badge>
                        </TabsTrigger>
                        <TabsTrigger v-if="isCompleted" value="stats" class="text-xs sm:text-sm">
                            <BarChart3 class="mr-1 size-3" /> Stats
                        </TabsTrigger>
                    </TabsList>
                </div>

                <!-- Round Tabs -->
                <TabsContent v-for="round in rounds" :key="'rc-' + round.id" :value="'r' + round.round_number">
                    <div class="mb-3 flex flex-wrap items-center justify-end gap-2" data-no-print>
                        <RoundClock
                            v-if="round.status === 'in_progress' && round.started_at && myMatch?.round.round_number !== round.round_number"
                            :started-at="round.started_at"
                            :limit-minutes="tournament.round_time_limit"
                        />
                        <Button as-child variant="outline" size="sm" class="h-8 gap-1.5">
                            <a :href="route('tournaments.display', tournament.uuid)" target="_blank" rel="noopener"
                                ><MonitorPlay class="size-3.5" /> Display mode</a
                            >
                        </Button>
                        <Button v-if="round.games.length" variant="outline" size="sm" class="h-8 gap-1.5" @click="printRound(round.round_number)">
                            <Printer class="size-3.5" /> Print pairings
                        </Button>
                    </div>
                    <h2 class="mb-3 hidden text-xl font-bold print:block">{{ tournament.name }} — Round {{ round.round_number }}</h2>
                    <!-- Compact Scenario -->
                    <div
                        v-if="round.deployment || round.strategy || round.schemes.length || round.tos_scenario"
                        class="mb-4 rounded-lg border bg-muted/30 p-3"
                    >
                        <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs">
                            <span v-if="round.deployment" class="flex items-center gap-1">
                                <span class="text-muted-foreground">Deploy:</span>
                                <button
                                    class="font-medium hover:text-primary"
                                    @click="openCard(round.deployment.label, round.deployment.image_url, round.deployment.description)"
                                >
                                    {{ round.deployment.label }}
                                </button>
                            </span>
                            <span v-if="round.tos_scenario?.operation" class="flex items-center gap-1">
                                <span class="text-muted-foreground">Operation:</span>
                                <button
                                    class="font-medium hover:text-primary"
                                    @click="
                                        openCard(
                                            round.tos_scenario.operation.name,
                                            round.tos_scenario.operation.image_url,
                                            tosOperationText(round.tos_scenario.operation),
                                        )
                                    "
                                >
                                    {{ round.tos_scenario.operation.name }}
                                </button>
                            </span>
                            <span v-if="round.tos_scenario?.deployment" class="flex items-center gap-1">
                                <span class="text-muted-foreground">Deploy:</span>
                                <button
                                    class="font-medium hover:text-primary"
                                    @click="
                                        openCard(
                                            round.tos_scenario.deployment.name,
                                            round.tos_scenario.deployment.image_url,
                                            round.tos_scenario.deployment.description,
                                        )
                                    "
                                >
                                    {{ round.tos_scenario.deployment.name }}
                                </button>
                            </span>
                            <span v-if="round.strategy" class="flex items-center gap-1">
                                <span class="text-muted-foreground">Strategy:</span>
                                <button class="font-medium hover:text-primary" @click="openCard(round.strategy.name, round.strategy.image_url)">
                                    {{ round.strategy.name }}
                                </button>
                            </span>
                            <span v-if="round.schemes.length" class="flex items-center gap-1">
                                <span class="text-muted-foreground">Schemes:</span>
                                <span v-for="(scheme, idx) in round.schemes" :key="scheme.id">
                                    <span v-if="idx > 0" class="text-muted-foreground">, </span>
                                    <button
                                        class="font-medium hover:text-primary"
                                        @click="
                                            openCard(
                                                scheme.name,
                                                scheme.image_url,
                                                [
                                                    scheme.prerequisite ? 'Prerequisite: ' + scheme.prerequisite : '',
                                                    scheme.reveal ? 'Reveal: ' + scheme.reveal : '',
                                                    scheme.scoring ? 'Scoring: ' + scheme.scoring : '',
                                                ]
                                                    .filter(Boolean)
                                                    .join('\n\n'),
                                            )
                                        "
                                    >
                                        {{ scheme.name }}
                                    </button>
                                </span>
                            </span>
                        </div>
                    </div>
                    <div v-else class="mb-4 rounded-lg border border-dashed p-3 text-center text-xs text-muted-foreground">
                        Scenario not yet announced
                    </div>

                    <!-- Pairings / Results —
                         On mobile, each pairing is a 2-line card: top = P1 line, bottom = P2 line,
                         with the score centered between. Cleaner hierarchy, no truncation surprises.
                         On sm+ screens we keep the original single-row layout for density. -->
                    <div v-if="round.games.length" class="space-y-2 sm:space-y-1.5">
                        <PairingRow
                            v-for="game in round.games"
                            :key="game.id"
                            :game="game"
                            :players="tournament.players"
                            :my-player-id="myPlayerId"
                        />
                    </div>
                    <div v-else class="py-6 text-center text-sm text-muted-foreground">Pairings not yet generated</div>
                </TabsContent>

                <!-- Standings Tab -->
                <TabsContent value="standings">
                    <div class="mb-3 flex items-center gap-2 px-1">
                        <Trophy class="size-4" />
                        <h2 class="text-lg font-semibold">{{ isCompleted ? 'Final Standings' : 'Current Standings' }}</h2>
                    </div>
                    <StandingsTable :standings="standings" :highlight-player-id="myPlayerId" />
                </TabsContent>

                <!-- RSVPs Tab -->
                <TabsContent value="rsvps">
                    <Card>
                        <CardContent class="p-4">
                            <h2 class="mb-3 flex items-center gap-2 text-lg font-semibold">
                                <UserPlus class="size-4" /> {{ tournament.rsvps?.length ?? 0 }} RSVPs
                            </h2>
                            <div class="space-y-1.5">
                                <div
                                    v-for="rsvp in tournament.rsvps ?? []"
                                    :key="rsvp.id"
                                    class="flex items-center gap-2 rounded-md border px-3 py-2 text-sm"
                                >
                                    <FactionLogo v-if="rsvp.faction" :faction="rsvp.faction" class-name="size-4 shrink-0" />
                                    <UserPlus v-else class="size-3.5 shrink-0 text-muted-foreground" />
                                    <span class="font-medium">{{ rsvp.user?.name ?? 'Unknown User' }}</span>
                                    <span v-if="rsvp.faction" class="text-xs text-muted-foreground">{{
                                        factions[rsvp.faction]?.name ?? rsvp.faction
                                    }}</span>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </TabsContent>

                <!-- Stats Tab (completed only) -->
                <TabsContent v-if="isCompleted && tournamentStats" value="stats">
                    <div class="space-y-4">
                        <!-- Winner -->
                        <Card v-if="tournamentStats.winner" class="border-amber-500/30 bg-amber-500/5">
                            <CardContent class="flex items-center gap-3 p-4">
                                <Trophy class="size-8 text-amber-700 dark:text-amber-400" />
                                <div>
                                    <div class="text-lg font-bold text-amber-700 dark:text-amber-400">{{ tournamentStats.winner.display_name }}</div>
                                    <div class="text-xs text-muted-foreground">
                                        Tournament Winner — {{ tournamentStats.winner.total_tp }} TP ·
                                        {{ tournamentStats.winner.total_diff > 0 ? '+' : '' }}{{ tournamentStats.winner.total_diff }} DIFF ·
                                        {{ tournamentStats.winner.total_vp }} VP
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Stat cards -->
                        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            <Card>
                                <CardContent class="p-4">
                                    <div class="text-2xs uppercase text-muted-foreground">Total Games</div>
                                    <div class="text-2xl font-bold">{{ tournamentStats.totalGames }}</div>
                                </CardContent>
                            </Card>
                            <Card v-if="tournamentStats.topFaction">
                                <CardContent class="p-4">
                                    <div class="text-2xs uppercase text-muted-foreground">Most Played Faction</div>
                                    <div class="mt-1 flex items-center gap-1.5">
                                        <FactionLogo :faction="tournamentStats.topFaction[0]" class-name="size-5" />
                                        <span class="text-sm font-medium capitalize">{{ tournamentStats.topFaction[0]?.replace('_', ' ') }}</span>
                                        <span class="text-xs text-muted-foreground">({{ tournamentStats.topFaction[1] }}x)</span>
                                    </div>
                                </CardContent>
                            </Card>
                            <Card v-if="tournamentStats.topMaster">
                                <CardContent class="p-4">
                                    <div class="text-2xs uppercase text-muted-foreground">Most Picked Master</div>
                                    <div class="mt-1 text-sm font-medium">
                                        {{ tournamentStats.topMaster[0] }}
                                        <span class="text-xs text-muted-foreground">({{ tournamentStats.topMaster[1] }}x)</span>
                                    </div>
                                </CardContent>
                            </Card>
                            <Card v-if="tournamentStats.topVp && tournamentStats.topVp.player_id !== tournamentStats.winner?.player_id">
                                <CardContent class="p-4">
                                    <div class="text-2xs uppercase text-muted-foreground">Top VP</div>
                                    <div class="mt-1 text-sm font-medium">
                                        {{ tournamentStats.topVp.display_name }}
                                        <span class="text-xs text-muted-foreground">({{ tournamentStats.topVp.total_vp }} VP)</span>
                                    </div>
                                </CardContent>
                            </Card>
                            <Card v-if="tournamentStats.topDiff && tournamentStats.topDiff.player_id !== tournamentStats.winner?.player_id">
                                <CardContent class="p-4">
                                    <div class="text-2xs uppercase text-muted-foreground">Top Differential</div>
                                    <div class="mt-1 text-sm font-medium">
                                        {{ tournamentStats.topDiff.display_name }}
                                        <span class="text-xs text-muted-foreground">(+{{ tournamentStats.topDiff.total_diff }})</span>
                                    </div>
                                </CardContent>
                            </Card>
                            <Card v-if="tournamentStats.spoon">
                                <CardContent class="p-4">
                                    <div class="text-2xs uppercase text-muted-foreground">Wooden Spoon</div>
                                    <div class="mt-1 text-sm font-medium">{{ tournamentStats.spoon.display_name }}</div>
                                </CardContent>
                            </Card>
                            <Card v-if="scheme_stats?.most_scored">
                                <CardContent class="p-4">
                                    <div class="text-2xs uppercase text-muted-foreground">Most Scored Scheme</div>
                                    <div class="mt-1 text-sm font-medium">{{ scheme_stats.most_scored.name }}</div>
                                    <div class="text-2xs text-muted-foreground">
                                        {{ scheme_stats.most_scored.total_points }} VP across {{ scheme_stats.most_scored.scoring_turns }} turns
                                    </div>
                                </CardContent>
                            </Card>
                        </div>

                        <!-- Best in Faction -->
                        <Card v-if="Object.keys(tournamentStats.bestInFaction).length">
                            <CardContent class="p-4">
                                <div class="mb-3 text-2xs font-semibold uppercase text-muted-foreground">Best in Faction</div>
                                <div class="grid gap-2 sm:grid-cols-2">
                                    <div
                                        v-for="(entry, faction) in tournamentStats.bestInFaction"
                                        :key="faction"
                                        class="flex items-center gap-2 rounded-md border px-3 py-2 text-xs"
                                    >
                                        <FactionLogo :faction="String(faction)" class-name="size-5 shrink-0" />
                                        <div class="min-w-0 flex-1">
                                            <div class="truncate font-medium">{{ entry.display_name }}</div>
                                            <div class="text-2xs text-muted-foreground">{{ entry.total_tp }} TP · {{ entry.total_vp }} VP</div>
                                        </div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </TabsContent>
            </Tabs>

            <!-- Organizers -->
            <div v-if="tournament.organizers.length" class="mt-6 text-xs text-muted-foreground">
                <span class="font-medium">Organized by:</span>
                {{ tournament.organizers.map((o) => o.name).join(', ') }}
            </div>
        </div>
    </div>

    <!-- Card Preview Drawer -->
    <Drawer v-model:open="drawerOpen">
        <DrawerContent>
            <div class="mx-auto w-full max-w-sm">
                <DrawerHeader class="pb-2">
                    <DrawerTitle class="text-center">{{ drawerTitle }}</DrawerTitle>
                </DrawerHeader>
                <div class="px-4 pb-4">
                    <div v-if="drawerImage" class="mb-3 flex justify-center">
                        <img :src="drawerImage" :alt="drawerTitle" class="max-h-[60dvh] w-auto rounded-lg" loading="lazy" decoding="async" />
                    </div>
                    <p v-if="drawerDescription" class="whitespace-pre-line text-sm leading-relaxed text-muted-foreground">{{ drawerDescription }}</p>
                </div>
                <DrawerFooter class="shrink-0 pt-2">
                    <DrawerClose as-child>
                        <Button variant="outline">Close</Button>
                    </DrawerClose>
                </DrawerFooter>
            </div>
        </DrawerContent>
    </Drawer>
</template>
