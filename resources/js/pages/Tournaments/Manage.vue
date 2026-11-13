<script setup lang="ts">
import {
    formatGameSize,
    TIEBREAKER_LABELS,
    type TosDeploymentInfo,
    type TosOperationInfo,
    type TournamentRuleset,
    type MasterOption,
    type StandingEntry,
    type CutState,
    type TableDetails,
    type TournamentAward,
    type TournamentGame,
    type TournamentPlayer,
    type TournamentRound,
} from '@/types/tournament';
import AllegianceLogo from '@/components/AllegianceLogo.vue';
import FactionLogo from '@/components/FactionLogo.vue';
import PageBanner from '@/components/PageBanner.vue';
import QRCodeDialog from '@/components/QRCodeDialog.vue';
import ManageDetailsTab from '@/components/Tournament/ManageDetailsTab.vue';
import ManagePlayersTab from '@/components/Tournament/ManagePlayersTab.vue';
import ManageRoundsTab from '@/components/Tournament/ManageRoundsTab.vue';
import NextStepCard from '@/components/Tournament/NextStepCard.vue';
import AnnouncementDialog from '@/components/Tournament/AnnouncementDialog.vue';
import DiscordCard from '@/components/Tournament/DiscordCard.vue';
import SeriesCard from '@/components/Tournament/SeriesCard.vue';
import ScorersCard from '@/components/Tournament/ScorersCard.vue';
import ScoreEntryDialog from '@/components/Tournament/ScoreEntryDialog.vue';
import TournamentSettingsForm from '@/components/Tournament/TournamentSettingsForm.vue';
import ScoringSummary from '@/components/Tournament/ScoringSummary.vue';
import StandingsTable from '@/components/Tournament/StandingsTable.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { useToast } from '@/composables/useToast';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useTournament } from '@/composables/useTournament';
import { useTournamentNextStep } from '@/composables/useTournamentNextStep';
import { useTournamentChannel } from '@/composables/useTournamentChannel';
import { useTournamentStatus } from '@/composables/useTournamentStatus';
import { formatDateOnly } from '@/lib/utils';
import type { SharedData } from '@/types';
import { Head, usePage } from '@inertiajs/vue3';
import { CalendarDays, Megaphone, MonitorPlay, Copy, Download, MapPin, Plus, QrCode, Share2, Shield, Trophy, X } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';

interface FactionInfo {
    name: string;
    slug: string;
    color: string;
    logo: string;
}

interface Rsvp {
    id: number;
    user_id: number;
    /** Optional intended faction the user picked at RSVP time. Pre-fills the
     *  TO's add-player dialog so they don't have to ask again. */
    faction: string | null;
    allegiance?: { id: number; slug: string; name: string } | null;
    allegiance_id?: number | null;
    user: { id: number; name: string } | null;
    /** 1 = next in line when the player limit is reached; null = has a seat. */
    waitlist_position?: number | null;
}

interface TournamentData {
    id: number;
    uuid: string;
    name: string;
    description: string | null;
    details?: Record<string, unknown> | null;
    status: string;
    encounter_size: number;
    encounter_type: string;
    planned_rounds: number;
    season_label: string;
    event_date: string;
    location: string | null;
    round_time_limit: number;
    bye_tp?: number | null;
    bye_diff?: number | null;
    bye_vp?: number | null;
    tiebreaker_mode?: 'diff_vp' | 'sos';
    variants?: Record<string, boolean | number> | null;
    allow_self_report?: boolean;
    announcement?: string | null;
    announced_at?: string | null;
    team_size?: number;
    max_players?: number | null;
    registration_closes_at?: string | null;
    instant_signup?: boolean;
    registration_closed?: boolean;
    scoring_config?: Record<string, unknown> | null;
    creator_id: number;
    players: TournamentPlayer[];
    rounds: TournamentRound[];
    organizers: { id: number; name: string }[];
    scorers?: { id: number; name: string }[];
    rsvps?: Rsvp[];
}

const props = defineProps<{
    tournament: TournamentData;
    ruleset: TournamentRuleset;
    /** The organizer's per-table notes, keyed by table number. */
    tables: TableDetails;
    /** The league this event belongs to, and the viewer's own leagues to choose from. */
    series: { uuid: string; name: string } | null;
    my_series: { uuid: string; name: string }[];
    /** Whether a Discord channel is connected (the webhook URL itself is never sent). */
    discord_connected: boolean;
    /** Where the top cut stands (size, whether it started, which sizes are open now). */
    cut: CutState;
    /** Side awards, including ones not yet given to anyone. */
    awards: TournamentAward[];
    standings: StandingEntry[];
    seasons: { value: string; label: string }[];
    factions: Record<string, FactionInfo>;
    /** Allegiances a player can declare (empty unless the ruleset uses them). */
    allegiances: { id: number; slug: string; name: string }[];
    encounter_types: { value: string; label: string }[];
    // `masters`, `all_strategies`, `all_schemes` are Inertia::defer() props —
    // null on initial render, populated by the follow-up request.
    masters: MasterOption[] | null;
    all_strategies: { id: number; name: string; slug: string; image_url: string | null }[] | null;
    all_schemes:
        | {
              id: number;
              name: string;
              slug: string;
              image_url: string | null;
              prerequisite: string | null;
              reveal: string | null;
              scoring: string | null;
          }[]
        | null;
    all_deployments: { value: string; label: string; description: string | null; image_url: string | null }[];
    /** TOS reference data; empty unless the ruleset's scenario uses it. */
    tos_operations: TosOperationInfo[];
    tos_deployments: TosDeploymentInfo[];
}>();

// Tournament-wide state + helpers (memoized player lookup, doAction, factionBackground, error toast).
const tournamentRef = computed(() => props.tournament);
const { submitting, doAction, doModalAction, reloadProps } = useTournament(tournamentRef);

const { statusColor, statusLabel } = useTournamentStatus();

// Restore tab from URL hash. Only allow tabs that are actually rendered for this tournament.
const visibleTabs = computed(() => {
    const t = ['standings', 'players', 'rounds', 'details', 'settings'];
    if (props.tournament.rsvps?.length || props.tournament.status === 'draft') {
        t.push('rsvps');
    }
    return t;
});
const initialTab = typeof window !== 'undefined' && window.location.hash ? window.location.hash.slice(1) : null;
// No hash to restore — open on the tab the organizer's next task lives on: Players while setting up,
// Rounds while running the event (pair / score / end), Standings once it is over.
const defaultTab = ['draft', 'registration'].includes(props.tournament.status)
    ? 'players'
    : props.tournament.status === 'active'
      ? 'rounds'
      : 'standings';
const activeTab = ref(initialTab && visibleTabs.value.includes(initialTab) ? initialTab : defaultTab);

// Which round the tournament is currently at, for the "Round X of Y" header
// badge — the in-progress round if there is one, else the most recently
// created round (covers the gap between rounds), else null pre-start.
const currentRoundNumber = computed(() => {
    const inProgress = props.tournament.rounds.find((r) => r.status === 'in_progress');
    if (inProgress) return inProgress.round_number;
    // Rounds are created up front (empty, in Setup) — only count ones that have been paired or played, so a
    // tournament that hasn't started doesn't claim to be "Round 3 of 3".
    const started = props.tournament.rounds.filter((r) => r.status === 'completed' || r.games.length > 0);
    return started.length ? Math.max(...started.map((r) => r.round_number)) : null;
});

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

const isLocked = computed(() => props.tournament.status === 'active' || props.tournament.status === 'completed');

const toast = useToast();
const copyPublicLink = async () => {
    await navigator.clipboard.writeText(route('tournaments.view', props.tournament.uuid));
    toast.success('Public link copied');
};
const qrOpen = ref(false);

watch(activeTab, (tab) => {
    if (typeof window !== 'undefined') window.location.hash = tab;
});

// Reload Inertia props after a mutation while preserving local UI state.
const reloadPage = () => reloadProps(['tournament', 'standings', 'cut']);
const announcementOpen = ref(false);
const playersTab = ref<InstanceType<typeof ManagePlayersTab> | null>(null);
const waitlistCount = computed(() => props.tournament.rsvps?.filter((r) => r.waitlist_position).length ?? 0);
const roundsTab = ref<InstanceType<typeof ManageRoundsTab> | null>(null);

// Subscribe to the public tournament broadcast channel so collaborating TOs
// (or this user's other tabs) see updates in near-realtime.
useTournamentChannel(props.tournament.uuid, () => reloadPage());

// ─── Check-in (the players tab owns the actions; the page only needs the counts to warn before pairing) ───
const checkedInCount = computed(() => props.tournament.players.filter((p) => !p.is_disqualified && p.checked_in_at).length);
const uncheckedPlayers = computed(() =>
    props.tournament.players.filter((p) => !p.is_disqualified && p.dropped_after_round === null && !p.checked_in_at),
);

// ─── Organizers ───
const sharedPage = usePage<SharedData>();
const currentUserId = computed(() => sharedPage.props.auth.user?.id ?? null);
const isCreator = computed(() => currentUserId.value === props.tournament.creator_id);

const addOrganizerDialogOpen = ref(false);
const addOrganizerQuery = ref('');
const addOrganizerResults = ref<{ id: number; name: string }[]>([]);
const addOrganizerSearching = ref(false);
const addOrganizerError = ref<string | null>(null);
let addOrganizerSearchTimer: ReturnType<typeof setTimeout> | null = null;

// Friends-only quick-pick — reused for both the Add Organizer and Link User
// dialogs so an organizer doesn't have to type a name for someone already
// on their friend list.
const friendOptions = ref<{ id: number; name: string }[]>([]);
onMounted(async () => {
    const res = await fetch(route('friends.accepted'), { headers: { Accept: 'application/json' } });
    if (res.ok) {
        const data = await res.json();
        friendOptions.value = data.friends ?? [];
    }
});
const addOrganizerFriendOptions = computed(() => {
    const existing = new Set(props.tournament.organizers.map((o) => o.id));
    return friendOptions.value.filter((f) => !existing.has(f.id));
});

const openAddOrganizerDialog = () => {
    addOrganizerQuery.value = '';
    addOrganizerResults.value = [];
    addOrganizerError.value = null;
    addOrganizerDialogOpen.value = true;
};

watch(addOrganizerQuery, (q) => {
    if (addOrganizerSearchTimer) clearTimeout(addOrganizerSearchTimer);
    if (!q || q.trim().length < 2) {
        addOrganizerResults.value = [];
        return;
    }
    addOrganizerSearchTimer = setTimeout(async () => {
        addOrganizerSearching.value = true;
        try {
            const res = await fetch(route('tournaments.users.search', props.tournament.uuid) + '?q=' + encodeURIComponent(q.trim()), {
                headers: { Accept: 'application/json' },
            });
            if (res.ok) {
                const data = await res.json();
                const existing = new Set(props.tournament.organizers.map((o) => o.id));
                addOrganizerResults.value = (data.users ?? []).filter((u: { id: number }) => !existing.has(u.id));
            }
        } finally {
            addOrganizerSearching.value = false;
        }
    }, 250);
});

const addOrganizer = async (userId: number) => {
    addOrganizerError.value = null;
    const { ok, error } = await doModalAction(route('tournaments.organizers.add', props.tournament.uuid), 'POST', { user_id: userId });
    if (ok) {
        addOrganizerDialogOpen.value = false;
        reloadPage();
    } else {
        addOrganizerError.value = error;
    }
};

const removeOrganizer = async (userId: number) => {
    if (userId === props.tournament.creator_id) return;
    if (await doAction(route('tournaments.organizers.remove', { tournament: props.tournament.uuid, userId }), 'DELETE')) {
        reloadPage();
    }
};

// Previous opponents lookup
const playerOpponents = computed(() => {
    const map: Record<number, string[]> = {};
    for (const round of props.tournament.rounds) {
        for (const game of round.games) {
            if (game.is_bye || !game.player_two) continue;
            if (!map[game.player_one_id]) map[game.player_one_id] = [];
            if (!map[game.player_two_id!]) map[game.player_two_id!] = [];
            map[game.player_one_id].push(`R${round.round_number}: ${game.player_two.display_name}`);
            map[game.player_two_id!].push(`R${round.round_number}: ${game.player_one.display_name}`);
        }
    }
    return map;
});

// ─── Game Scores ───
// The score-entry dialog (and all its form state) lives in ScoreEntryDialog; the page only says which game is open.
const scoreGame = ref<TournamentGame | null>(null);
const openScoreEdit = (game: TournamentGame) => {
    scoreGame.value = game;
};

const openGameById = (id: number) => {
    scoreGame.value = props.tournament.rounds.flatMap((r) => r.games).find((g) => g.id === id) ?? null;
};
// ─── Status ───
const statusTransitions: Record<string, { next: string; label: string }> = {
    draft: { next: 'registration', label: 'Open Registration' },
    registration: { next: 'active', label: 'Start Tournament' },
};

const revertToDraft = async () => {
    if (await doAction(route('tournaments.status', props.tournament.uuid), 'PUT', { status: 'draft' })) {
        reloadPage();
    }
};

const confirmStatusDialog = ref(false);
const confirmFinalizeDialog = ref(false);

const finalizeTournament = async () => {
    if (await doAction(route('tournaments.status', props.tournament.uuid), 'PUT', { status: 'completed' })) {
        confirmFinalizeDialog.value = false;
        reloadPage();
    }
};

const advanceStatus = async () => {
    const transition = statusTransitions[props.tournament.status];
    if (!transition) return;
    if (await doAction(route('tournaments.status', props.tournament.uuid), 'PUT', { status: transition.next })) {
        confirmStatusDialog.value = false;
        reloadPage();
    }
};

// statusColor / statusLabel come from useTournamentStatus() above

// ─── Settings Edit ───
// The edit form lives in TournamentSettingsForm; the page only toggles it.
const editingSettings = ref(false);
// Live tiebreaker toggle on the standings tab — distinct from the Settings
// edit form so TOs can flip it any time, even after the tournament starts.
const liveTiebreaker = computed(() => props.tournament.tiebreaker_mode ?? 'diff_vp');
const updateTiebreaker = async (mode: string) => {
    if (await doAction(route('tournaments.update', props.tournament.uuid), 'PUT', { tiebreaker_mode: mode })) {
        reloadPage();
    }
};

const openSettingsEdit = () => {
    editingSettings.value = true;
};

const activePlayers = computed(() => props.tournament.players.filter((p) => !p.is_disqualified && p.dropped_after_round === null));

const latestRound = computed(() => (props.tournament.rounds.length > 0 ? props.tournament.rounds[props.tournament.rounds.length - 1] : null));

const isLastRound = computed(() => props.tournament.rounds.length >= props.tournament.planned_rounds);

const canFinalize = computed(() => {
    if (props.tournament.status !== 'active') return false;
    if (!isLastRound.value) return false;
    if (!latestRound.value) return false;
    // A top cut has to reach its final before the event can be finalized.
    if (latestRound.value.is_cut && latestRound.value.games.filter((g) => !g.is_bye).length > 1) return false;
    return latestRound.value.status === 'completed';
});
// ─── Next step guidance (logic in useTournamentNextStep, rendering in NextStepCard) ───
const { nextStep } = useTournamentNextStep(() => ({
    tournament: props.tournament,
    latestRound,
    activePlayerCount: computed(() => activePlayers.value.length),
    canFinalize,
    submitting: computed(() => submitting.value || !!roundsTab.value?.submitting),
    transitionLabel: (status) => statusTransitions[status]?.label,
    pairBlockReason: (round) => roundsTab.value?.pairBlockReason(round) ?? null,
    scenarioBlockReason: (round) => roundsTab.value?.scenarioBlockReason(round) ?? null,
    startBlockReason: (round) => roundsTab.value?.startBlockReason(round) ?? null,
    canEndRound: (round) => roundsTab.value?.canEndRound(round) ?? false,
    openStatusDialog: () => (confirmStatusDialog.value = true),
    openFinalizeDialog: () => (confirmFinalizeDialog.value = true),
    createRound: () => roundsTab.value?.createRound(),
    generatePairings: (id) => roundsTab.value?.generatePairings(id),
    startRound: (id) => roundsTab.value?.startRound(id),
    endRound: (id) => roundsTab.value?.endRound(id),
    editScenario: (round) => {
        activeTab.value = 'rounds';
        roundsTab.value?.editScenario(round);
    },
    showRounds: () => (activeTab.value = 'rounds'),
    advanceCut: () => roundsTab.value?.advanceCut(),
    cutAvailable: () => roundsTab.value?.cutAvailable() ?? false,
}));

const formatDate = (d: string) => formatDateOnly(d, { month: 'long', day: 'numeric', year: 'numeric' });
const isEditable = computed(() => !isLocked.value);
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
                    <Badge :class="['border-0 text-2xs', statusColor(tournament.status)]" variant="outline">{{
                        statusLabel(tournament.status)
                    }}</Badge>
                    <span class="flex items-center gap-1"><CalendarDays class="size-3" /> {{ formatDate(tournament.event_date) }}</span>
                    <span v-if="tournament.location" class="flex items-center gap-1"><MapPin class="size-3" /> {{ tournament.location }}</span>
                    <span
                        >{{ formatGameSize(ruleset, tournament.encounter_size)
                        }}{{ ruleset.uses_encounter_type ? ' ' + encounterTypeLabel : '' }}</span
                    >
                    <span v-if="currentRoundNumber !== null">Round {{ currentRoundNumber }} of {{ tournament.planned_rounds }}</span>
                    <span v-else>{{ tournament.planned_rounds }} rounds</span>
                    <span v-if="ruleset.uses_season">{{ tournament.season_label }}</span>
                </div>
            </template>
        </PageBanner>

        <div class="container mx-auto sm:px-4">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-2" role="toolbar" aria-label="Event actions">
                <div class="flex flex-wrap items-center gap-2">
                    <Button variant="outline" size="sm" class="gap-1.5" @click="announcementOpen = true">
                        <Megaphone class="size-3.5" /> {{ tournament.announcement ? 'Edit announcement' : 'Announce' }}
                    </Button>
                    <Button as-child variant="outline" size="sm" class="gap-1.5">
                        <a :href="route('tournaments.display', tournament.uuid)" target="_blank" rel="noopener"
                            ><MonitorPlay class="size-3.5" /> Display</a
                        >
                    </Button>
                    <DropdownMenu :modal="false">
                        <DropdownMenuTrigger as-child>
                            <Button variant="outline" size="sm" class="gap-1.5"> <Share2 class="size-3.5" /> Share </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="start">
                            <DropdownMenuItem @select="copyPublicLink"> <Copy class="mr-2 size-4" /> Copy public link </DropdownMenuItem>
                            <DropdownMenuItem @select="qrOpen = true"> <QrCode class="mr-2 size-4" /> Show QR code </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <Button
                        v-if="tournament.status === 'registration'"
                        variant="ghost"
                        size="sm"
                        class="text-muted-foreground"
                        @click="revertToDraft"
                    >
                        Back to draft
                    </Button>
                    <Button v-if="statusTransitions[tournament.status] && !nextStep" size="sm" @click="confirmStatusDialog = true">
                        {{ statusTransitions[tournament.status].label }}
                    </Button>
                </div>
            </div>

            <AnnouncementDialog v-model:open="announcementOpen" :tournament="tournament" @saved="reloadPage" />

            <NextStepCard v-if="nextStep" :step="nextStep" />

            <Tabs v-model="activeTab" default-value="standings">
                <TabsList class="mb-4 flex w-full flex-wrap">
                    <TabsTrigger value="standings" class="gap-1 px-2 text-xs sm:px-3 sm:text-sm">Standings</TabsTrigger>
                    <TabsTrigger value="players" class="gap-1 px-2 text-xs sm:px-3 sm:text-sm">
                        Players
                        <Badge v-if="tournament.players.length" variant="secondary" class="ml-1 px-1 py-0 text-2xs">{{
                            tournament.players.length
                        }}</Badge>
                    </TabsTrigger>
                    <TabsTrigger
                        v-if="tournament.status === 'draft' || tournament.rsvps?.length"
                        value="rsvps"
                        class="gap-1 px-2 text-xs sm:px-3 sm:text-sm"
                    >
                        RSVPs
                        <Badge v-if="tournament.rsvps?.length" variant="secondary" class="ml-1 px-1 py-0 text-2xs">{{
                            tournament.rsvps.length
                        }}</Badge>
                    </TabsTrigger>
                    <TabsTrigger value="rounds" class="gap-1 px-2 text-xs sm:px-3 sm:text-sm">
                        Rounds
                        <Badge v-if="tournament.rounds.length" variant="secondary" class="ml-1 px-1 py-0 text-2xs">{{
                            tournament.rounds.length
                        }}</Badge>
                    </TabsTrigger>
                    <TabsTrigger value="details" class="px-2 text-xs sm:px-3 sm:text-sm">Details</TabsTrigger>
                    <TabsTrigger value="settings" class="px-2 text-xs sm:px-3 sm:text-sm">Settings</TabsTrigger>
                </TabsList>

                <!-- ═══ STANDINGS ═══ -->
                <TabsContent value="standings">
                    <!-- TO-only quick toggle for tiebreaker mode (changes can be made any time, even mid-tournament).
                         Only rulesets that allow it (Gaining Grounds) show the toggle; others state their fixed order. -->
                    <div v-if="ruleset.tiebreaker_toggle" class="mb-2 flex items-center justify-end gap-2 text-xs">
                        <Label class="text-muted-foreground">Tiebreaker:</Label>
                        <Select :model-value="liveTiebreaker" @update:model-value="updateTiebreaker">
                            <SelectTrigger class="h-7 w-44 text-xs"><SelectValue /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value="diff_vp">TP → DIFF → VP</SelectItem>
                                <SelectItem value="sos">TP → SoS → DIFF → VP</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <p v-else class="mb-2 text-right text-xs text-muted-foreground">
                        Ranked by TP → {{ ruleset.tiebreakers.map((k) => TIEBREAKER_LABELS[k]).join(' → ') }}
                    </p>
                    <StandingsTable :standings="standings" />
                    <div class="mt-3 flex justify-end">
                        <Button as-child variant="outline" size="sm" class="gap-1.5 text-xs">
                            <a :href="route('tournaments.export_csv', tournament.uuid)"><Download class="size-3.5" /> Download results (CSV)</a>
                        </Button>
                    </div>
                </TabsContent>

                <!-- ═══ RSVPs ═══ -->
                <TabsContent value="rsvps">
                    <div v-if="!tournament.rsvps?.length" class="py-8 text-center text-sm text-muted-foreground">
                        No RSVPs yet. Share the tournament link so players can express interest.
                    </div>
                    <template v-else>
                        <div class="mb-4">
                            <h3 class="text-sm font-semibold">
                                {{ tournament.rsvps.length }} RSVPs
                                <span v-if="tournament.max_players" class="font-normal text-muted-foreground">
                                    · limit {{ tournament.max_players }}<template v-if="waitlistCount"> · {{ waitlistCount }} waitlisted</template>
                                </span>
                            </h3>
                            <p v-if="tournament.status === 'draft'" class="text-xs text-muted-foreground">
                                Open registration to turn these RSVPs into players.
                            </p>
                        </div>
                        <div class="space-y-1.5">
                            <div
                                v-for="rsvp in tournament.rsvps"
                                :key="rsvp.id"
                                class="flex items-center justify-between gap-2 rounded-lg border px-3 py-2"
                            >
                                <div class="flex min-w-0 items-center gap-2">
                                    <FactionLogo v-if="rsvp.faction" :faction="rsvp.faction" class-name="size-4 shrink-0" />
                                    <AllegianceLogo v-else-if="rsvp.allegiance" :allegiance="rsvp.allegiance.slug" class-name="size-4 shrink-0" />
                                    <span class="truncate text-xs font-medium sm:text-sm">{{ rsvp.user?.name ?? 'Unknown User' }}</span>
                                    <span v-if="rsvp.faction" class="text-2xs text-muted-foreground sm:text-xs">{{
                                        factions[rsvp.faction]?.name ?? rsvp.faction
                                    }}</span>
                                    <span v-else-if="rsvp.allegiance" class="text-2xs text-muted-foreground sm:text-xs">{{
                                        rsvp.allegiance.name
                                    }}</span>
                                </div>
                                <div class="flex items-center gap-1">
                                    <Button
                                        v-if="
                                            tournament.status === 'registration' &&
                                            !(tournament.team_size && tournament.team_size > 1) &&
                                            !tournament.players.some((p) => p.user?.id === rsvp.user_id)
                                        "
                                        variant="outline"
                                        size="sm"
                                        class="h-8 gap-1 px-2.5 text-xs"
                                        @click="playersTab?.openRsvpPlayerDialog(rsvp)"
                                    >
                                        <Plus class="size-2.5" /> Register
                                    </Button>
                                    <Badge
                                        v-if="rsvp.waitlist_position"
                                        variant="outline"
                                        class="px-1 py-0 text-2xs text-amber-700 dark:text-amber-400"
                                        >Waitlist #{{ rsvp.waitlist_position }}</Badge
                                    >
                                    <Badge
                                        v-else-if="tournament.players.some((p) => p.user?.id === rsvp.user_id)"
                                        variant="outline"
                                        class="px-1 py-0 text-2xs"
                                        >Registered</Badge
                                    >
                                </div>
                            </div>
                        </div>
                    </template>
                </TabsContent>

                <!-- ═══ PLAYERS ═══ -->
                <ManagePlayersTab
                    ref="playersTab"
                    :tournament="tournament"
                    :ruleset="ruleset"
                    :factions="factions"
                    :allegiances="allegiances"
                    :opponents="playerOpponents"
                    :friend-options="friendOptions"
                />

                <ManageRoundsTab
                    ref="roundsTab"
                    :tournament="tournament"
                    :ruleset="ruleset"
                    :all_strategies="all_strategies"
                    :all_schemes="all_schemes"
                    :all_deployments="all_deployments"
                    :tos_operations="tos_operations"
                    :tos_deployments="tos_deployments"
                    :checked-in-count="checkedInCount"
                    :unchecked-players="uncheckedPlayers"
                    :can-finalize="canFinalize"
                    :cut="cut"
                    @edit-score="openScoreEdit"
                    @finalize="confirmFinalizeDialog = true"
                />

                <!-- ═══ DETAILS ═══ -->
                <ManageDetailsTab
                    :tournament="tournament"
                    :ruleset="ruleset"
                    :tables="tables"
                    :awards="awards"
                    @saved="reloadProps(['tournament', 'tables', 'awards'])"
                />

                <!-- ═══ SETTINGS ═══ -->
                <TabsContent value="settings">
                    <!-- Quick exports — useful even before the tournament finishes for sharing
                         a snapshot, transcribing into Longshanks, etc. -->
                    <Card class="mb-4">
                        <CardContent class="flex flex-wrap items-center justify-between gap-3 p-4 sm:p-5">
                            <div>
                                <div class="text-sm font-semibold">Export results</div>
                                <p class="text-xs text-muted-foreground">
                                    CSV with one row per pairing — players, factions, masters, scores, result. Use it as a tournament archive or to
                                    transcribe into Longshanks.
                                </p>
                            </div>
                            <a
                                :href="route('tournaments.export_csv', tournament.uuid)"
                                class="inline-flex h-9 items-center gap-1.5 rounded-md border border-input bg-background px-3 text-xs font-medium shadow-sm hover:bg-accent hover:text-accent-foreground"
                            >
                                <Download class="size-3.5" />
                                Download CSV
                            </a>
                        </CardContent>
                    </Card>

                    <ScorersCard :tournament="tournament" @saved="reloadPage" />

                    <SeriesCard :tournament="tournament" :series="series" :my-series="my_series" @saved="reloadProps(['series'])" />

                    <DiscordCard :tournament="tournament" :connected="discord_connected" @saved="reloadProps(['discord_connected'])" />

                    <!-- Edit Mode -->
                    <TournamentSettingsForm
                        v-if="editingSettings"
                        :tournament="tournament"
                        :ruleset="ruleset"
                        :can-change-team-size="!tournament.players.length"
                        :encounter-types="encounter_types"
                        @cancel="editingSettings = false"
                        @saved="
                            editingSettings = false;
                            reloadPage();
                        "
                    />

                    <!-- View Mode -->
                    <Card v-else>
                        <CardContent class="space-y-4 p-4">
                            <div class="flex items-center justify-between">
                                <h3 class="text-sm font-semibold">Tournament Details</h3>
                                <Button v-if="isEditable" variant="outline" size="sm" class="h-9 gap-1 text-xs" @click="openSettingsEdit">
                                    Edit
                                </Button>
                            </div>
                            <p v-if="!isEditable" class="-mt-2 text-xs text-muted-foreground">
                                Details are locked once the tournament has started, so pairings and scores stay consistent.
                            </p>
                            <div class="space-y-3 text-sm">
                                <div class="flex justify-between">
                                    <span class="text-muted-foreground">Status</span>
                                    <Badge :class="['border-0 text-2xs', statusColor(tournament.status)]" variant="outline">{{
                                        statusLabel(tournament.status)
                                    }}</Badge>
                                </div>
                                <div v-if="tournament.description" class="flex justify-between">
                                    <span class="text-muted-foreground">Description</span>
                                    <span class="max-w-[60%] text-right">{{ tournament.description }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-muted-foreground">Date</span>
                                    <span>{{ formatDate(tournament.event_date) }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-muted-foreground">Location</span>
                                    <span>{{ tournament.location || 'Not set' }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-muted-foreground">Encounter Size</span>
                                    <span
                                        >{{ formatGameSize(ruleset, tournament.encounter_size)
                                        }}{{ ruleset.uses_encounter_type ? ' ' + encounterTypeLabel : '' }}</span
                                    >
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-muted-foreground">Planned Rounds</span>
                                    <span>{{ tournament.planned_rounds }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-muted-foreground">Round Time Limit</span>
                                    <span>{{ tournament.round_time_limit }} min</span>
                                </div>
                                <div v-if="ruleset.uses_season" class="flex justify-between">
                                    <span class="text-muted-foreground">Season</span>
                                    <span>{{ tournament.season_label }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-muted-foreground">Player self-reporting</span>
                                    <span>{{ tournament.allow_self_report ? 'On' : 'Off' }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-muted-foreground">Bye Scoring</span>
                                    <span class="tabular-nums"
                                        >{{ ruleset.scoring.bye_tp }} TP / {{ ruleset.scoring.bye_diff }} DIFF / {{ ruleset.scoring.bye_vp }} VP</span
                                    >
                                </div>
                            </div>

                            <ScoringSummary :ruleset="ruleset" class="border-t pt-4" />

                            <div class="border-t pt-4">
                                <div class="mb-2 flex items-center justify-between">
                                    <h3 class="text-sm font-semibold">Organizers</h3>
                                    <Button v-if="isCreator" variant="outline" size="sm" class="h-7 gap-1 text-xs" @click="openAddOrganizerDialog">
                                        <Plus class="size-3" /> Add TO
                                    </Button>
                                </div>
                                <div class="space-y-1">
                                    <div
                                        v-for="org in tournament.organizers"
                                        :key="org.id"
                                        class="flex items-center justify-between gap-2 rounded-md px-1 py-1 text-sm hover:bg-accent/50"
                                    >
                                        <div class="flex items-center gap-2">
                                            <Shield class="size-3.5 text-muted-foreground" />
                                            <span>{{ org.name }}</span>
                                            <Badge v-if="org.id === tournament.creator_id" variant="outline" class="px-1 py-0 text-2xs"
                                                >Creator</Badge
                                            >
                                        </div>
                                        <button
                                            v-if="isCreator && org.id !== tournament.creator_id"
                                            class="rounded p-1 text-muted-foreground hover:bg-destructive/10 hover:text-destructive"
                                            title="Remove organizer"
                                            @click="removeOrganizer(org.id)"
                                        >
                                            <X class="size-3.5" />
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </TabsContent>
            </Tabs>
        </div>
    </div>

    <ScoreEntryDialog
        :game="scoreGame"
        :tournament="tournament"
        :ruleset="ruleset"
        :masters="masters"
        @close="scoreGame = null"
        @open-game="openGameById"
    />

    <!-- Finalize Tournament Confirmation -->
    <Dialog v-model:open="confirmFinalizeDialog">
        <DialogContent class="max-w-sm">
            <DialogHeader>
                <DialogTitle>Complete Tournament</DialogTitle>
                <DialogDescription>
                    This will finalize the standings and mark the tournament as completed. No more rounds or score changes can be made after this.
                </DialogDescription>
            </DialogHeader>
            <DialogFooter class="gap-2 sm:gap-0">
                <Button variant="outline" @click="confirmFinalizeDialog = false">Cancel</Button>
                <Button @click="finalizeTournament"> <Trophy class="mr-2 size-4" /> Complete Tournament </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <!-- Status Change Confirmation -->
    <Dialog v-model:open="confirmStatusDialog">
        <DialogContent class="max-w-sm">
            <DialogHeader>
                <DialogTitle>{{ statusTransitions[tournament.status]?.label }}</DialogTitle>
                <DialogDescription>Are you sure? This will advance the tournament to the next phase.</DialogDescription>
            </DialogHeader>
            <DialogFooter class="gap-2 sm:gap-0">
                <Button variant="outline" @click="confirmStatusDialog = false">Cancel</Button>
                <Button @click="advanceStatus">Confirm</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <!-- Add Organizer Dialog -->
    <Dialog v-model:open="addOrganizerDialogOpen">
        <DialogContent class="max-w-sm">
            <DialogHeader>
                <DialogTitle>Add Tournament Organizer</DialogTitle>
                <DialogDescription>Search by name. Added organizers can manage all aspects of this tournament.</DialogDescription>
            </DialogHeader>
            <div v-if="!addOrganizerQuery.trim() && addOrganizerFriendOptions.length" class="flex flex-wrap gap-2">
                <button
                    v-for="f in addOrganizerFriendOptions"
                    :key="f.id"
                    type="button"
                    class="rounded-full border px-3 py-1 text-xs hover:bg-accent"
                    @click="addOrganizer(f.id)"
                >
                    {{ f.name }}
                </button>
            </div>
            <Input v-model="addOrganizerQuery" placeholder="Search users (min 2 chars)..." class="text-sm" />
            <div class="max-h-64 overflow-y-auto">
                <div v-if="addOrganizerSearching" class="py-2 text-center text-xs text-muted-foreground">Searching...</div>
                <div
                    v-else-if="addOrganizerQuery.trim().length >= 2 && !addOrganizerResults.length"
                    class="py-2 text-center text-xs text-muted-foreground"
                >
                    No users match (or already organizers).
                </div>
                <button
                    v-for="u in addOrganizerResults"
                    :key="u.id"
                    class="flex w-full items-center justify-between rounded px-2 py-1.5 text-left text-sm hover:bg-accent"
                    @click="addOrganizer(u.id)"
                >
                    <span class="flex items-center gap-2"><Shield class="size-3.5 text-muted-foreground" /> {{ u.name }}</span>
                    <Plus class="size-3.5 text-muted-foreground" />
                </button>
            </div>
            <p v-if="addOrganizerError" class="text-xs text-destructive">{{ addOrganizerError }}</p>
            <DialogFooter>
                <Button variant="outline" @click="addOrganizerDialogOpen = false">Cancel</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <!-- QR Code -->
    <QRCodeDialog v-if="qrOpen" v-model:open="qrOpen" :url="route('tournaments.view', tournament.uuid)" title="Tournament Link" />
</template>
