<script setup lang="ts">
import FactionLogo from '@/components/FactionLogo.vue';
import PageBanner from '@/components/PageBanner.vue';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { Drawer, DrawerClose, DrawerContent, DrawerFooter, DrawerHeader, DrawerTitle } from '@/components/ui/drawer';
import { Button } from '@/components/ui/button';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useTournamentChannel } from '@/composables/useTournamentChannel';
import { type SharedData } from '@/types';
import { Head, router, usePage } from '@inertiajs/vue3';
import { BarChart3, CalendarDays, Crown, MapPin, Trophy, UserPlus } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface TournamentPlayer {
    id: number;
    display_name: string;
    faction: string | null;
    user: { id: number; name: string } | null;
    is_ringer: boolean;
    is_disqualified: boolean;
    dropped_after_round: number | null;
}

interface ViewGame {
    id: number;
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
    tracker_game: { id: number; uuid: string } | null;
}

interface ViewRound {
    id: number;
    round_number: number;
    status: string;
    deployment: { value: string; label: string; description: string; image_url: string | null } | null;
    strategy: { id: number; name: string; image_url: string | null } | null;
    schemes: { id: number; name: string; image_url: string | null; prerequisite: string | null; reveal: string | null; scoring: string | null }[];
    games: ViewGame[];
}

interface StandingEntry {
    rank: number | null;
    player_id: number;
    display_name: string;
    faction: string | null;
    is_ringer: boolean;
    is_dropped: boolean;
    total_tp: number;
    total_diff: number;
    total_vp: number;
    rounds_played: number;
}

interface FactionInfo {
    name: string;
    slug: string;
    color: string;
    logo: string;
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
        players: TournamentPlayer[];
        organizers: { id: number; name: string }[];
    };
    rounds: ViewRound[];
    standings: StandingEntry[];
    factions: Record<string, FactionInfo>;
}>();

// Live updates via websocket
useTournamentChannel(props.tournament.uuid);

const isCompleted = computed(() => props.tournament.status === 'completed');

// Default tab: completed → standings, active → latest round
const defaultTab = computed(() => {
    if (isCompleted.value) return 'standings';
    if (props.rounds.length) return `r${props.rounds[props.rounds.length - 1].round_number}`;
    return 'standings';
});
const activeTab = ref(defaultTab.value);

const page = usePage<SharedData>();
const isLoggedIn = computed(() => !!page.props.auth.user);
const currentUserId = computed(() => page.props.auth.user?.id);
const isRegistered = computed(() => props.tournament.players.some((p) => p.user?.id === currentUserId.value));
const hasRsvped = computed(() => (props.tournament as any).rsvps?.some((r: any) => r.user_id === currentUserId.value) ?? false);
const canRsvp = computed(
    () =>
        isLoggedIn.value &&
        (props.tournament.status === 'draft' || props.tournament.status === 'registration') &&
        !hasRsvped.value &&
        !isRegistered.value,
);
const myPlayerId = computed(() => props.tournament.players.find((p) => p.user?.id === currentUserId.value)?.id ?? null);

const rsvping = ref(false);
const csrfToken = () => document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';

const submitRsvp = async () => {
    rsvping.value = true;
    await fetch(route('tournaments.rsvp', props.tournament.uuid), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
        body: JSON.stringify({}),
    });
    rsvping.value = false;
    router.reload();
};

const cancelRsvp = async () => {
    await fetch(route('tournaments.rsvp.cancel', props.tournament.uuid), {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': csrfToken() },
    });
    router.reload();
};

const formatDate = (d: string) => {
    const date = d.includes('T') ? new Date(d) : new Date(d + 'T00:00:00');
    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
};

const factionBackground = (faction: string | null): string => {
    if (!faction) return '';
    switch (faction.toLowerCase()) {
        case 'explorers_society': return 'bg-explorerssociety';
        case 'ten_thunders': return 'bg-tenthunders';
        default: return `bg-${faction}`;
    }
};

const playerName = (id: number | null): string => {
    if (!id) return 'BYE';
    return props.tournament.players.find((p) => p.id === id)?.display_name ?? 'Unknown';
};

const playerFaction = (id: number | null): string | null => {
    if (!id) return null;
    return props.tournament.players.find((p) => p.id === id)?.faction ?? null;
};

const isMyGame = (game: ViewGame): boolean => {
    if (!myPlayerId.value) return false;
    return game.player_one_id === myPlayerId.value || game.player_two_id === myPlayerId.value;
};

const encounterTypeLabel = computed(() => {
    const map: Record<string, string> = {
        traditional: 'Traditional', enforcer_brawl: 'Enforcer Brawl', crossroads: 'Crossroads',
        team_event: 'Team Event', double_rush: 'Double Rush', total_war: 'Total War',
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
    const spoon = [...props.standings].filter((s) => !s.is_ringer).sort((a, b) => a.total_tp - b.total_tp || a.total_diff - b.total_diff)[0];

    // Best in faction
    const bestInFaction: Record<string, StandingEntry> = {};
    for (const s of props.standings) {
        if (s.is_ringer || !s.faction) continue;
        if (!bestInFaction[s.faction] || s.total_tp > bestInFaction[s.faction].total_tp) {
            bestInFaction[s.faction] = s;
        }
    }

    return {
        winner, topVp, topDiff, spoon, topFaction, topMaster, bestInFaction,
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
                    <Badge variant="outline" class="text-[10px]">{{ tournament.status === 'completed' ? 'Completed' : tournament.status === 'active' ? 'Active' : tournament.status === 'registration' ? 'Registration Open' : 'Draft' }}</Badge>
                    <span>{{ tournament.season_label }}</span>
                    <span>{{ tournament.encounter_size }}ss {{ encounterTypeLabel }}</span>
                    <span v-if="tournament.event_date" class="flex items-center gap-1"><CalendarDays class="size-3" />{{ formatDate(tournament.event_date) }}</span>
                    <span v-if="tournament.location" class="flex items-center gap-1"><MapPin class="size-3" />{{ tournament.location }}</span>
                    <span>{{ tournament.players.length }} players</span>
                    <span v-if="(tournament.status === 'draft' || tournament.status === 'registration') && (tournament as any).rsvps?.length">{{ (tournament as any).rsvps.length }} RSVPs</span>
                </div>
            </template>
        </PageBanner>

        <div class="container mx-auto pb-8 sm:px-4">
            <p v-if="tournament.description" class="mb-4 text-sm text-muted-foreground">{{ tournament.description }}</p>

            <!-- RSVP Banner (Draft and Registration phases) -->
            <Card v-if="canRsvp" class="mb-6 border-blue-500/30 bg-blue-500/5">
                <CardContent class="flex flex-col items-center gap-3 p-4 sm:flex-row sm:justify-between">
                    <div>
                        <div class="flex items-center gap-2 font-semibold">
                            <UserPlus class="size-4 text-blue-600 dark:text-blue-400" />
                            {{ tournament.status === 'registration' ? 'Registration Open' : 'RSVP Open' }}
                        </div>
                        <p class="mt-1 text-xs text-muted-foreground">Express your interest in playing. The organizer will finalize the player list and assign your faction.</p>
                    </div>
                    <Button size="sm" :disabled="rsvping" @click="submitRsvp">
                        <UserPlus class="mr-1.5 size-3.5" /> {{ tournament.status === 'registration' ? 'Register Interest' : 'RSVP' }}
                    </Button>
                </CardContent>
            </Card>
            <Card v-else-if="hasRsvped && (tournament.status === 'draft' || tournament.status === 'registration')" class="mb-6 border-blue-500/30 bg-blue-500/5">
                <CardContent class="flex items-center justify-between p-4">
                    <span class="text-sm text-blue-700 dark:text-blue-400">
                        {{ tournament.status === 'registration' ? "You've signed up. Waiting for the organizer to confirm you in the player list." : "You've RSVPed for this tournament." }}
                    </span>
                    <Button variant="ghost" size="sm" class="text-xs text-muted-foreground" @click="cancelRsvp">Cancel RSVP</Button>
                </CardContent>
            </Card>
            <!-- Registration confirmation -->
            <Card v-else-if="isRegistered && (tournament.status === 'registration' || tournament.status === 'active')" class="mb-6 border-green-500/30 bg-green-500/5">
                <CardContent class="p-4 text-center text-sm text-green-700 dark:text-green-400">
                    You're registered for this tournament.
                </CardContent>
            </Card>

            <!-- Main Tabs -->
            <Tabs v-model="activeTab">
                <TabsList class="mb-4 flex w-full flex-wrap">
                    <TabsTrigger v-for="round in rounds" :key="'tab-' + round.id" :value="'r' + round.round_number" class="text-xs sm:text-sm">
                        R{{ round.round_number }}
                    </TabsTrigger>
                    <TabsTrigger value="standings" class="text-xs sm:text-sm">
                        {{ isCompleted ? 'Final Standings' : 'Standings' }}
                    </TabsTrigger>
                    <TabsTrigger v-if="(tournament as any).rsvps?.length" value="rsvps" class="text-xs sm:text-sm">
                        RSVPs
                        <Badge variant="secondary" class="ml-1 px-1 py-0 text-[9px]">{{ (tournament as any).rsvps.length }}</Badge>
                    </TabsTrigger>
                    <TabsTrigger v-if="isCompleted" value="stats" class="text-xs sm:text-sm">
                        <BarChart3 class="mr-1 size-3" /> Stats
                    </TabsTrigger>
                </TabsList>

                <!-- Round Tabs -->
                <TabsContent v-for="round in rounds" :key="'rc-' + round.id" :value="'r' + round.round_number">
                    <!-- Compact Scenario -->
                    <div v-if="round.deployment || round.strategy || round.schemes.length" class="mb-4 rounded-lg border bg-muted/30 p-3">
                        <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs">
                            <span v-if="round.deployment" class="flex items-center gap-1">
                                <span class="text-muted-foreground">Deploy:</span>
                                <button class="font-medium hover:text-primary" @click="openCard(round.deployment.label, round.deployment.image_url, round.deployment.description)">{{ round.deployment.label }}</button>
                            </span>
                            <span v-if="round.strategy" class="flex items-center gap-1">
                                <span class="text-muted-foreground">Strategy:</span>
                                <button class="font-medium hover:text-primary" @click="openCard(round.strategy.name, round.strategy.image_url)">{{ round.strategy.name }}</button>
                            </span>
                            <span v-if="round.schemes.length" class="flex items-center gap-1">
                                <span class="text-muted-foreground">Schemes:</span>
                                <span v-for="(scheme, idx) in round.schemes" :key="scheme.id">
                                    <span v-if="idx > 0" class="text-muted-foreground">, </span>
                                    <button class="font-medium hover:text-primary" @click="openCard(scheme.name, scheme.image_url, [scheme.prerequisite ? 'Prerequisite: ' + scheme.prerequisite : '', scheme.reveal ? 'Reveal: ' + scheme.reveal : '', scheme.scoring ? 'Scoring: ' + scheme.scoring : ''].filter(Boolean).join('\n\n'))">{{ scheme.name }}</button>
                                </span>
                            </span>
                        </div>
                    </div>
                    <div v-else class="mb-4 rounded-lg border border-dashed p-3 text-center text-xs text-muted-foreground">Scenario not yet announced</div>

                    <!-- Pairings / Results -->
                    <div v-if="round.games.length" class="space-y-1.5">
                        <div
                            v-for="game in round.games"
                            :key="game.id"
                            class="flex items-center gap-2 rounded-md border px-2 py-1.5 text-xs sm:text-sm"
                            :class="isMyGame(game) ? 'border-primary/40 bg-primary/5' : ''"
                        >
                            <span v-if="game.table_number" class="shrink-0 text-[10px] text-muted-foreground">T{{ game.table_number }}</span>

                            <!-- Player 1 -->
                            <div class="flex min-w-0 flex-1 items-center gap-1.5">
                                <FactionLogo v-if="game.player_one_faction || playerFaction(game.player_one_id)" :faction="(game.player_one_faction || playerFaction(game.player_one_id))!" class-name="size-4 shrink-0" />
                                <span class="truncate font-medium" :class="myPlayerId === game.player_one_id ? 'text-primary' : ''">{{ playerName(game.player_one_id) }}</span>
                                <span v-if="game.player_one_master" class="truncate text-[10px] text-muted-foreground">· {{ game.player_one_title || game.player_one_master }}</span>
                            </div>

                            <template v-if="game.is_bye">
                                <Badge variant="outline" class="shrink-0 text-[9px]">BYE</Badge>
                            </template>
                            <template v-else>
                                <!-- Score -->
                                <div class="flex shrink-0 items-center gap-1 font-mono text-xs">
                                    <span :class="(game.player_one_vp ?? 0) > (game.player_two_vp ?? 0) ? 'font-bold text-green-600 dark:text-green-400' : ''">{{ game.player_one_vp ?? '–' }}</span>
                                    <span class="text-muted-foreground">-</span>
                                    <span :class="(game.player_two_vp ?? 0) > (game.player_one_vp ?? 0) ? 'font-bold text-green-600 dark:text-green-400' : ''">{{ game.player_two_vp ?? '–' }}</span>
                                </div>

                                <!-- Player 2 -->
                                <div class="flex min-w-0 flex-1 items-center justify-end gap-1.5 text-right">
                                    <span v-if="game.player_two_master" class="truncate text-[10px] text-muted-foreground">{{ game.player_two_title || game.player_two_master }} ·</span>
                                    <span class="truncate font-medium" :class="myPlayerId === game.player_two_id ? 'text-primary' : ''">{{ playerName(game.player_two_id) }}</span>
                                    <FactionLogo v-if="game.player_two_faction || playerFaction(game.player_two_id)" :faction="(game.player_two_faction || playerFaction(game.player_two_id))!" class-name="size-4 shrink-0" />
                                </div>

                                <Badge v-if="game.is_forfeit" variant="destructive" class="shrink-0 px-1 py-0 text-[9px]">Forfeit</Badge>

                                <a
                                    v-if="game.tracker_game?.uuid"
                                    :href="route('games.observe', game.tracker_game.uuid)"
                                    target="_blank"
                                    rel="noopener"
                                    class="shrink-0 text-[10px] font-medium text-primary hover:underline"
                                >
                                    View
                                </a>
                            </template>
                        </div>
                    </div>
                    <div v-else class="py-6 text-center text-sm text-muted-foreground">Pairings not yet generated</div>
                </TabsContent>

                <!-- Standings Tab -->
                <TabsContent value="standings">
                    <Card>
                        <CardContent class="p-0">
                            <div class="px-4 py-3">
                                <h2 class="flex items-center gap-2 font-semibold"><Trophy class="size-4" /> {{ isCompleted ? 'Final Standings' : 'Current Standings' }}</h2>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="w-full text-sm">
                                    <thead>
                                        <tr class="border-t text-left text-xs text-muted-foreground">
                                            <th class="px-2 py-2 font-medium sm:px-4">#</th>
                                            <th class="px-2 py-2 font-medium sm:px-4">Player</th>
                                            <th class="px-1 py-2 text-center font-medium sm:px-3">TP</th>
                                            <th class="px-1 py-2 text-center font-medium sm:px-3">DIFF</th>
                                            <th class="px-1 py-2 text-center font-medium sm:px-3">VP</th>
                                            <th class="hidden px-3 py-2 text-center font-medium sm:table-cell">Played</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-if="!standings.length">
                                            <td colspan="6" class="px-4 py-8 text-center text-muted-foreground">No results yet</td>
                                        </tr>
                                        <tr
                                            v-for="entry in standings"
                                            :key="entry.player_id"
                                            :class="[
                                                factionBackground(entry.faction),
                                                entry.faction ? 'text-white' : 'border-b last:border-0',
                                                entry.is_ringer ? 'opacity-50' : '',
                                                entry.player_id === myPlayerId ? 'ring-2 ring-inset ring-primary/60' : '',
                                            ]"
                                        >
                                            <td class="px-2 py-2 font-bold sm:px-4">{{ entry.rank ?? '-' }}</td>
                                            <td class="px-2 py-2 sm:px-4">
                                                <div class="flex items-center gap-1.5">
                                                    <FactionLogo v-if="entry.faction" :faction="entry.faction" class-name="size-4 shrink-0 drop-shadow-[0_1px_2px_rgba(0,0,0,0.5)]" />
                                                    <Crown v-if="entry.rank === 1 && !entry.is_ringer" class="size-3.5 shrink-0 text-amber-300 drop-shadow-[0_1px_2px_rgba(0,0,0,0.5)]" />
                                                    <span class="truncate text-xs font-medium sm:text-sm">{{ entry.display_name }}</span>
                                                    <Badge v-if="entry.is_ringer" variant="outline" class="hidden shrink-0 border-white/40 px-1 py-0 text-[9px] text-white/80 sm:inline-flex">Ringer</Badge>
                                                    <Badge v-if="entry.is_dropped" variant="outline" class="hidden shrink-0 border-white/40 px-1 py-0 text-[9px] text-white/60 sm:inline-flex">Dropped</Badge>
                                                </div>
                                            </td>
                                            <td class="px-1 py-2 text-center font-bold sm:px-3">{{ entry.total_tp }}</td>
                                            <td class="px-1 py-2 text-center font-medium sm:px-3">{{ entry.total_diff > 0 ? '+' : '' }}{{ entry.total_diff }}</td>
                                            <td class="px-1 py-2 text-center sm:px-3">{{ entry.total_vp }}</td>
                                            <td class="hidden px-3 py-2 text-center opacity-70 sm:table-cell">{{ entry.rounds_played }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </CardContent>
                    </Card>
                </TabsContent>

                <!-- RSVPs Tab -->
                <TabsContent value="rsvps">
                    <Card>
                        <CardContent class="p-4">
                            <h2 class="mb-3 flex items-center gap-2 font-semibold"><UserPlus class="size-4" /> {{ (tournament as any).rsvps?.length ?? 0 }} RSVPs</h2>
                            <div class="space-y-1.5">
                                <div
                                    v-for="rsvp in (tournament as any).rsvps ?? []"
                                    :key="rsvp.id"
                                    class="flex items-center gap-2 rounded-md border px-3 py-2 text-sm"
                                >
                                    <UserPlus class="size-3.5 shrink-0 text-muted-foreground" />
                                    <span class="font-medium">{{ rsvp.user?.name ?? 'Unknown User' }}</span>
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
                                <Trophy class="size-8 text-amber-500" />
                                <div>
                                    <div class="text-lg font-bold text-amber-700 dark:text-amber-400">{{ tournamentStats.winner.display_name }}</div>
                                    <div class="text-xs text-muted-foreground">Tournament Winner — {{ tournamentStats.winner.total_tp }} TP · {{ tournamentStats.winner.total_diff > 0 ? '+' : '' }}{{ tournamentStats.winner.total_diff }} DIFF · {{ tournamentStats.winner.total_vp }} VP</div>
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Stat cards -->
                        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            <Card>
                                <CardContent class="p-4">
                                    <div class="text-[10px] uppercase text-muted-foreground">Total Games</div>
                                    <div class="text-2xl font-bold">{{ tournamentStats.totalGames }}</div>
                                </CardContent>
                            </Card>
                            <Card v-if="tournamentStats.topFaction">
                                <CardContent class="p-4">
                                    <div class="text-[10px] uppercase text-muted-foreground">Most Played Faction</div>
                                    <div class="mt-1 flex items-center gap-1.5">
                                        <FactionLogo :faction="tournamentStats.topFaction[0]" class-name="size-5" />
                                        <span class="text-sm font-medium capitalize">{{ tournamentStats.topFaction[0]?.replace('_', ' ') }}</span>
                                        <span class="text-xs text-muted-foreground">({{ tournamentStats.topFaction[1] }}x)</span>
                                    </div>
                                </CardContent>
                            </Card>
                            <Card v-if="tournamentStats.topMaster">
                                <CardContent class="p-4">
                                    <div class="text-[10px] uppercase text-muted-foreground">Most Picked Master</div>
                                    <div class="mt-1 text-sm font-medium">{{ tournamentStats.topMaster[0] }} <span class="text-xs text-muted-foreground">({{ tournamentStats.topMaster[1] }}x)</span></div>
                                </CardContent>
                            </Card>
                            <Card v-if="tournamentStats.topVp && tournamentStats.topVp.player_id !== tournamentStats.winner?.player_id">
                                <CardContent class="p-4">
                                    <div class="text-[10px] uppercase text-muted-foreground">Top VP</div>
                                    <div class="mt-1 text-sm font-medium">{{ tournamentStats.topVp.display_name }} <span class="text-xs text-muted-foreground">({{ tournamentStats.topVp.total_vp }} VP)</span></div>
                                </CardContent>
                            </Card>
                            <Card v-if="tournamentStats.topDiff && tournamentStats.topDiff.player_id !== tournamentStats.winner?.player_id">
                                <CardContent class="p-4">
                                    <div class="text-[10px] uppercase text-muted-foreground">Top Differential</div>
                                    <div class="mt-1 text-sm font-medium">{{ tournamentStats.topDiff.display_name }} <span class="text-xs text-muted-foreground">(+{{ tournamentStats.topDiff.total_diff }})</span></div>
                                </CardContent>
                            </Card>
                            <Card v-if="tournamentStats.spoon">
                                <CardContent class="p-4">
                                    <div class="text-[10px] uppercase text-muted-foreground">Wooden Spoon</div>
                                    <div class="mt-1 text-sm font-medium">{{ tournamentStats.spoon.display_name }}</div>
                                </CardContent>
                            </Card>
                        </div>

                        <!-- Best in Faction -->
                        <Card v-if="Object.keys(tournamentStats.bestInFaction).length">
                            <CardContent class="p-4">
                                <div class="mb-3 text-[10px] font-semibold uppercase text-muted-foreground">Best in Faction</div>
                                <div class="grid gap-2 sm:grid-cols-2">
                                    <div v-for="(entry, faction) in tournamentStats.bestInFaction" :key="faction" class="flex items-center gap-2 rounded-md border px-3 py-2 text-xs">
                                        <FactionLogo :faction="String(faction)" class-name="size-5 shrink-0" />
                                        <div class="min-w-0 flex-1">
                                            <div class="truncate font-medium">{{ entry.display_name }}</div>
                                            <div class="text-[10px] text-muted-foreground">{{ entry.total_tp }} TP · {{ entry.total_vp }} VP</div>
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
