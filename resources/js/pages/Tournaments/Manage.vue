<script setup lang="ts">
import {
    formatGameSize,
    TIEBREAKER_LABELS,
    tosOperationText,
    type TosDeploymentInfo,
    type TosOperationInfo,
    type TournamentRuleset,
} from '@/types/tournament';
import AllegianceLogo from '@/components/AllegianceLogo.vue';
import FactionLogo from '@/components/FactionLogo.vue';
import PageBanner from '@/components/PageBanner.vue';
import QRCodeDialog from '@/components/QRCodeDialog.vue';
import ManagePlayersTab from '@/components/Tournament/ManagePlayersTab.vue';
import ScoreEntryDialog from '@/components/Tournament/ScoreEntryDialog.vue';
import TournamentSettingsForm from '@/components/Tournament/TournamentSettingsForm.vue';
import ScoringSummary from '@/components/Tournament/ScoringSummary.vue';
import RoundClock from '@/components/Tournament/RoundClock.vue';
import StandingsTable from '@/components/Tournament/StandingsTable.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Drawer, DrawerClose, DrawerContent, DrawerFooter, DrawerHeader, DrawerTitle } from '@/components/ui/drawer';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useConfirm } from '@/composables/useConfirm';
import { useTournament } from '@/composables/useTournament';
import { useTournamentChannel } from '@/composables/useTournamentChannel';
import { useTournamentStatus } from '@/composables/useTournamentStatus';
import { formatDateOnly } from '@/lib/utils';
import type { SharedData } from '@/types';
import { Head, router, usePage } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ArrowRight,
    CalendarDays,
    Check,
    MonitorPlay,
    Copy,
    Dices,
    Download,
    Eye,
    Loader2,
    MapPin,
    MoreHorizontal,
    Plus,
    QrCode,
    Shield,
    Trophy,
    X,
} from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';

interface TournamentPlayer {
    id: number;
    display_name: string;
    faction: string | null;
    checked_in_at?: string | null;
    allegiance_id?: number | null;
    allegiance?: { id: number; slug: string; name: string } | null;
    garrison?: { name: string; share_code?: string | null; is_public: boolean } | null;
    user: { id: number; name: string; meta?: { id: number; name: string } | null } | null;
    meta?: { id: number; name: string } | null;
    is_ringer: boolean;
    is_disqualified: boolean;
    dropped_after_round: number | null;
}

interface TournamentGame {
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
    /** A player-submitted result that isn't official yet (self-reporting). */
    report_status?: 'pending' | 'disputed' | null;
    reported_by_player_id?: number | null;
    reported_score?: { player_one: Record<string, number>; player_two: Record<string, number>; agreed: boolean } | null;
    report_note?: string | null;
    /** Per-side score components keyed by the ruleset's score-field keys. */
    score_breakdown: { player_one?: Record<string, number>; player_two?: Record<string, number> } | null;
    is_bye: boolean;
    is_forfeit: boolean;
    forfeit_player_id: number | null;
    result: string;
    table_number: number | null;
    tracker_game?: {
        id: number;
        uuid: string;
        status: string;
        is_solo: boolean;
        current_turn: number | null;
        max_turns: number | null;
        winner_id: number | null;
        is_tie: boolean;
    } | null;
}

interface TournamentRound {
    id: number;
    round_number: number;
    deployment: string | null;
    strategy: { id: number; name: string } | null;
    scheme_pool: number[] | null;
    /** Ruleset-defined scenario ids (TOS: operation_id + deployment_id). */
    scenario?: { operation_id?: number; deployment_id?: number } | null;
    status: string;
    started_at: string | null;
    games: TournamentGame[];
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
    total_sos: number;
    rounds_played: number;
}

interface MasterOption {
    name: string;
    faction: string;
    second_faction: string | null;
    titles: { id: number; display_name: string; title: string | null }[];
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
    /** Optional intended faction the user picked at RSVP time. Pre-fills the
     *  TO's add-player dialog so they don't have to ask again. */
    faction: string | null;
    allegiance?: { id: number; slug: string; name: string } | null;
    allegiance_id?: number | null;
    user: { id: number; name: string } | null;
}

interface TournamentData {
    id: number;
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
    bye_tp?: number | null;
    bye_diff?: number | null;
    bye_vp?: number | null;
    tiebreaker_mode?: 'diff_vp' | 'sos';
    variants?: Record<string, boolean | number> | null;
    allow_self_report?: boolean;
    scoring_config?: Record<string, unknown> | null;
    creator_id: number;
    players: TournamentPlayer[];
    rounds: TournamentRound[];
    organizers: { id: number; name: string }[];
    rsvps?: Rsvp[];
}

const props = defineProps<{
    tournament: TournamentData;
    ruleset: TournamentRuleset;
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
const { submitting, showError, doAction, doModalAction, reloadProps, playerName, playerFaction } = useTournament(tournamentRef);

const { statusColor, statusLabel } = useTournamentStatus();
const confirm = useConfirm();

// Restore tab from URL hash. Only allow tabs that are actually rendered for this tournament.
const visibleTabs = computed(() => {
    const t = ['standings', 'players', 'rounds', 'settings'];
    if (props.tournament.rsvps?.length || props.tournament.status === 'draft') {
        t.push('rsvps');
    }
    return t;
});
const initialTab = typeof window !== 'undefined' && window.location.hash ? window.location.hash.slice(1) : null;
// No hash to restore — default to Rounds when a round is actively being
// played (the organizer's most likely next task: pairing/scoring/ending it),
// else Standings.
const activeRoundInProgress = props.tournament.rounds.some((r) => r.status === 'in_progress');
const defaultTab = activeRoundInProgress ? 'rounds' : 'standings';
const activeTab = ref(initialTab && visibleTabs.value.includes(initialTab) ? initialTab : defaultTab);

// Which round the tournament is currently at, for the "Round X of Y" header
// badge — the in-progress round if there is one, else the most recently
// created round (covers the gap between rounds), else null pre-start.
const currentRoundNumber = computed(() => {
    const inProgress = props.tournament.rounds.find((r) => r.status === 'in_progress');
    if (inProgress) return inProgress.round_number;
    if (!props.tournament.rounds.length) return null;
    return Math.max(...props.tournament.rounds.map((r) => r.round_number));
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

const linkCopied = ref(false);
const copyPublicLink = async () => {
    await navigator.clipboard.writeText(route('tournaments.view', props.tournament.uuid));
    linkCopied.value = true;
    setTimeout(() => (linkCopied.value = false), 2000);
};
const qrOpen = ref(false);

watch(activeTab, (tab) => {
    if (typeof window !== 'undefined') window.location.hash = tab;
});

// Reload Inertia props after a mutation while preserving local UI state.
const reloadPage = () => reloadProps(['tournament', 'standings']);
const playersTab = ref<InstanceType<typeof ManagePlayersTab> | null>(null);

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

// ─── Rounds ───
const createRound = async () => {
    if (await doAction(route('tournaments.rounds.create', props.tournament.uuid))) {
        reloadPage();
    }
};

const deleteRound = async (roundId: number, roundNumber: number) => {
    if (
        !(await confirm({
            title: `Delete Round ${roundNumber}?`,
            message: 'This will remove the round and any pairings/games it contains.',
            confirmLabel: 'Delete round',
            destructive: true,
        }))
    )
        return;
    if (await doAction(route('tournaments.rounds.delete', { tournament: props.tournament.uuid, round: roundId }), 'DELETE')) {
        reloadPage();
    }
};

// Forfeit dialog
const forfeitDialogOpen = ref(false);
const forfeitGame = ref<TournamentGame | null>(null);
const forfeitError = ref<string | null>(null);
const openForfeitDialog = (game: TournamentGame) => {
    forfeitGame.value = game;
    forfeitError.value = null;
    forfeitDialogOpen.value = true;
};
const submitForfeit = async (loserPlayerId: number) => {
    if (!forfeitGame.value) return;
    forfeitError.value = null;
    const { ok, error } = await doModalAction(
        route('tournaments.games.forfeit', { tournament: props.tournament.uuid, game: forfeitGame.value.id }),
        'POST',
        { forfeit_player_id: loserPlayerId },
    );
    if (ok) {
        forfeitDialogOpen.value = false;
        forfeitGame.value = null;
        reloadPage();
    } else {
        forfeitError.value = error;
    }
};
const removeForfeit = async (game: TournamentGame) => {
    if (await doAction(route('tournaments.games.forfeit', { tournament: props.tournament.uuid, game: game.id }), 'POST', {})) {
        reloadPage();
    }
};

// Id-keyed lookup maps for deferred enum data. Without these, templates hit
// .find() per-render on every scheme-pool cell, which is O(pool × catalog)
// per re-render — cheap per call, but fires a lot during broadcast churn.
const schemeById = computed<Record<number, NonNullable<typeof props.all_schemes>[number]>>(() => {
    const map: Record<number, NonNullable<typeof props.all_schemes>[number]> = {};
    for (const s of props.all_schemes ?? []) map[s.id] = s;
    return map;
});
const deploymentByValue = computed<Record<string, (typeof props.all_deployments)[number]>>(() => {
    const map: Record<string, (typeof props.all_deployments)[number]> = {};
    for (const d of props.all_deployments) map[d.value] = d;
    return map;
});

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

// AutoPair availability check
const canAutoPair = (round: TournamentRound): boolean => pairBlockReason(round) === null;

// Returns null if pairing is allowed, otherwise a human-readable reason.
// Mirrors backend TournamentStateMachine::canPairRound — keep in sync.
const pairBlockReason = (round: TournamentRound): string | null => {
    if (round.status !== 'setup') return 'Round is not in setup';
    if (props.tournament.status !== 'active') {
        return round.round_number === 1 ? 'Tournament must be started before Round 1 can be paired' : 'Tournament must be active to pair rounds';
    }
    if (round.round_number > 1) {
        const prevRound = props.tournament.rounds.find((r: any) => r.round_number === round.round_number - 1);
        if (!prevRound || prevRound.status !== 'completed') {
            return `Round ${round.round_number - 1} must be completed first`;
        }
    }
    const required = props.ruleset.scenario_fields;
    const missing: string[] = [];
    if (required.includes('strategy') && !round.strategy) missing.push('strategy');
    if (required.includes('deployment') && !round.deployment) missing.push('deployment');
    if (required.includes('scheme_pool') && (!round.scheme_pool || round.scheme_pool.length < 3)) missing.push('scheme pool');
    if (required.includes('tos_operation') && !round.scenario?.operation_id) missing.push('operation');
    if (required.includes('tos_deployment') && !round.scenario?.deployment_id) missing.push('deployment');
    if (missing.length) return `Set the round scenario before pairing (missing: ${missing.join(', ')})`;
    return null;
};

const generatePairings = async (roundId: number) => {
    // If check-in is being used and some active players haven't arrived, make the TO decide on purpose.
    if (checkedInCount.value > 0 && uncheckedPlayers.value.length > 0) {
        const names = uncheckedPlayers.value.map((p) => p.display_name).join(', ');
        const proceed = await confirm({
            title: `${uncheckedPlayers.value.length} player(s) haven't checked in`,
            message: `${names} would still be paired. Check them in, drop them, or pair anyway?`,
            confirmLabel: 'Pair anyway',
        });
        if (!proceed) return;
    }
    submitting.value = true;
    const ok = await doAction(route('tournaments.rounds.pair', { tournament: props.tournament.uuid, round: roundId }), 'POST');
    submitting.value = false;
    if (ok) reloadPage();
};

// ─── Manual Pairing ───
// Per-round state so editing the Round 3 pair widget doesn't bleed into Round 2's.
const manualPairings = ref<Record<number, { p1: string; p2: string }>>({});
const getManualPair = (roundId: number) => {
    if (!manualPairings.value[roundId]) {
        manualPairings.value[roundId] = { p1: '', p2: '' };
    }
    return manualPairings.value[roundId];
};

const pairedPlayerIds = (roundId: number): Set<number> => {
    const round = props.tournament.rounds.find((r) => r.id === roundId);
    if (!round) return new Set();
    const ids = new Set<number>();
    for (const g of round.games) {
        ids.add(g.player_one_id);
        if (g.player_two_id) ids.add(g.player_two_id);
    }
    return ids;
};

const unpairedPlayers = (roundId: number) => {
    const paired = pairedPlayerIds(roundId);
    return props.tournament.players.filter((p) => !p.is_disqualified && p.dropped_after_round === null && !paired.has(p.id));
};

const addManualPairing = async (roundId: number) => {
    const pair = getManualPair(roundId);
    if (!pair.p1) return;
    submitting.value = true;
    const isBye = !pair.p2 || pair.p2 === 'bye';
    const ok = await doAction(route('tournaments.games.create', { tournament: props.tournament.uuid, round: roundId }), 'POST', {
        player_one_id: Number(pair.p1),
        player_two_id: isBye ? null : Number(pair.p2),
        is_bye: isBye,
    });
    submitting.value = false;
    if (ok) {
        pair.p1 = '';
        pair.p2 = '';
        reloadPage();
    }
};

const removeGame = async (gameId: number) => {
    if (await doAction(route('tournaments.games.delete', { tournament: props.tournament.uuid, game: gameId }), 'DELETE')) {
        reloadPage();
    }
};

// ─── Game Scores ───
// The score-entry dialog (and all its form state) lives in ScoreEntryDialog; the page only says which game is open.
const scoreGame = ref<TournamentGame | null>(null);
const openScoreEdit = (game: TournamentGame) => {
    scoreGame.value = game;
};

// ─── Round Scenario (inline editing — auto-saves on change) ───
const expandedScenarioRoundId = ref<number | null>(null);
const editDeployment = ref<string | null>(null);
const editStrategy = ref<string | null>(null);
const editSchemePool = ref<(string | null)[]>([null, null, null]);
// TOS-style scenario (Operation + Deployment from reference data) instead of Malifaux strategy/deployment/schemes.
const usesTosScenario = computed(() => props.ruleset.scenario_fields.some((f) => f.startsWith('tos_')));
const editTosOperation = ref<string | null>(null);
const editTosDeployment = ref<string | null>(null);
// Live indicator: 'idle' | 'saving' | 'saved'. Used by the editor's status pill.
const scenarioSaveState = ref<'idle' | 'saving' | 'saved'>('idle');
let scenarioSaveTimer: ReturnType<typeof setTimeout> | null = null;
let savedFlashTimer: ReturnType<typeof setTimeout> | null = null;
// Suppress the save-watcher during programmatic hydration (toggling the
// editor open shouldn't trigger an immediate save of the loaded values).
let suppressScenarioSave = false;

const toggleScenarioEdit = (round: TournamentRound) => {
    if (expandedScenarioRoundId.value === round.id) {
        expandedScenarioRoundId.value = null;
        return;
    }
    suppressScenarioSave = true;
    expandedScenarioRoundId.value = round.id;
    editDeployment.value = round.deployment;
    editStrategy.value = round.strategy ? String(round.strategy.id) : null;
    editSchemePool.value = round.scheme_pool ? round.scheme_pool.map(String) : [null, null, null];
    editTosOperation.value = round.scenario?.operation_id ? String(round.scenario.operation_id) : null;
    editTosDeployment.value = round.scenario?.deployment_id ? String(round.scenario.deployment_id) : null;
    scenarioSaveState.value = 'idle';
    // Re-enable the watcher on the next tick so hydration assignments don't fire it.
    setTimeout(() => {
        suppressScenarioSave = false;
    }, 0);
};

const saveRoundScenario = async (roundId: number) => {
    scenarioSaveState.value = 'saving';
    // Use doModalAction so validation errors show local to the pane (we'll
    // route them into the save-state indicator) rather than raising the
    // page-level toast behind the open editor.
    const payload = usesTosScenario.value
        ? {
              scenario: {
                  operation_id: editTosOperation.value ? Number(editTosOperation.value) : null,
                  deployment_id: editTosDeployment.value ? Number(editTosDeployment.value) : null,
              },
          }
        : {
              deployment: editDeployment.value,
              strategy_id: editStrategy.value ? Number(editStrategy.value) : null,
              scheme_pool: editSchemePool.value.filter(Boolean).map(Number),
          };
    const { ok, error } = await doModalAction(
        route('tournaments.rounds.update', { tournament: props.tournament.uuid, round: roundId }),
        'PUT',
        payload,
    );
    if (ok) {
        scenarioSaveState.value = 'saved';
        if (savedFlashTimer) clearTimeout(savedFlashTimer);
        savedFlashTimer = setTimeout(() => {
            scenarioSaveState.value = 'idle';
        }, 1500);
        // The server broadcasts `round_updated` to OTHER clients via
        // toOthers(), so the saver is excluded and doesn't auto-refresh.
        // Reload locally so the edited values are visible once the user
        // closes the pane. preserveState keeps the editor open and preserves
        // the in-progress scenarioSaveState flash.
        reloadPage();
    } else {
        scenarioSaveState.value = 'idle';
        if (error) showError(error);
    }
};

// Auto-save: any change to the editor's fields triggers a debounced PUT.
// Uses 500ms so multi-step interactions (e.g. picking 3 schemes) coalesce,
// and we only fire when the full scenario is actually valid — otherwise the
// server returns 422 on every partial field change, which used to spam the
// error toast and force redundant reloads.
const scenarioIsComplete = (): boolean => {
    if (usesTosScenario.value) return !!editTosOperation.value && !!editTosDeployment.value;
    if (!editDeployment.value) return false;
    if (!editStrategy.value) return false;
    const schemes = editSchemePool.value.filter(Boolean) as string[];
    if (schemes.length !== 3) return false;
    // Reject duplicates client-side — server's `distinct` rule would 422 anyway,
    // but skipping the request keeps the autosave error toast quiet while the
    // user is still mid-pick after reusing a slot.
    return new Set(schemes).size === schemes.length;
};

// Per-slot scheme list excluding ids already picked in OTHER slots, so the
// user physically can't choose the same scheme twice. Mirrors the same shape
// the Crew Builder uses for sculpt picking.
const availableSchemesForSlot = (idx: number) => {
    const taken = new Set(editSchemePool.value.map((v, i) => (i === idx ? null : v)).filter(Boolean) as string[]);
    return (props.all_schemes ?? []).filter((s: { id: number }) => !taken.has(String(s.id)));
};

watch(
    [editDeployment, editStrategy, editSchemePool, editTosOperation, editTosDeployment],
    () => {
        if (suppressScenarioSave || expandedScenarioRoundId.value === null) return;
        if (!scenarioIsComplete()) return;
        if (scenarioSaveTimer) clearTimeout(scenarioSaveTimer);
        scenarioSaveTimer = setTimeout(() => {
            const id = expandedScenarioRoundId.value;
            if (id !== null) saveRoundScenario(id);
        }, 500);
    },
    { deep: true },
);

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

const startRound = async (roundId: number) => {
    submitting.value = true;
    if (await doAction(route('tournaments.rounds.update', { tournament: props.tournament.uuid, round: roundId }), 'PUT', { status: 'in_progress' })) {
        reloadPage();
    }
    submitting.value = false;
};

const endRound = async (roundId: number) => {
    submitting.value = true;
    if (await doAction(route('tournaments.rounds.update', { tournament: props.tournament.uuid, round: roundId }), 'PUT', { status: 'completed' })) {
        reloadPage();
    }
    submitting.value = false;
};

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

// ─── Randomize Scenario ───
const randomizeInDialog = () => {
    if (usesTosScenario.value) {
        const ops = props.tos_operations;
        const deps = props.tos_deployments;
        if (ops.length) editTosOperation.value = String(ops[Math.floor(Math.random() * ops.length)].id);
        if (deps.length) editTosDeployment.value = String(deps[Math.floor(Math.random() * deps.length)].id);
        return;
    }
    // Randomize locally in the form fields. Deferred props may still be loading
    // when the user clicks Random; silently skip fields whose data isn't in yet.
    const strats = props.all_strategies ?? [];
    const schemes = props.all_schemes ?? [];
    const deploys = props.all_deployments;
    if (deploys.length) editDeployment.value = deploys[Math.floor(Math.random() * deploys.length)].value;
    if (strats.length) editStrategy.value = String(strats[Math.floor(Math.random() * strats.length)].id);
    if (schemes.length >= 3) {
        const shuffled = [...schemes].sort(() => Math.random() - 0.5);
        editSchemePool.value = [String(shuffled[0].id), String(shuffled[1].id), String(shuffled[2].id)];
    }
};

// ─── View Drawer ───
const viewDrawerOpen = ref(false);
const viewDrawerTitle = ref('');
const viewDrawerImage = ref<string | null>(null);
const viewDrawerDescription = ref('');

const openViewDrawer = (title: string, image: string | null, description?: string) => {
    viewDrawerTitle.value = title;
    viewDrawerImage.value = image;
    viewDrawerDescription.value = description ?? '';
    viewDrawerOpen.value = true;
};

const viewDeployment = (value: string | null) => {
    if (!value) return;
    const d = props.all_deployments.find((x) => x.value === value);
    if (d) openViewDrawer(d.label, d.image_url, d.description ?? undefined);
};

const viewTosOperation = (idStr: string | null) => {
    const o = props.tos_operations.find((x) => String(x.id) === idStr);
    if (o) openViewDrawer(o.name, o.image_url, tosOperationText(o));
};

const viewTosDeployment = (idStr: string | null) => {
    const d = props.tos_deployments.find((x) => String(x.id) === idStr);
    if (d) openViewDrawer(d.name, d.image_url, d.description ?? undefined);
};

const tosOperationName = (id?: number) => props.tos_operations.find((o) => o.id === id)?.name;
const tosDeploymentName = (id?: number) => props.tos_deployments.find((d) => d.id === id)?.name;

const viewStrategy = (idStr: string | null) => {
    if (!idStr) return;
    const s = props.all_strategies?.find((x) => String(x.id) === idStr);
    if (s) openViewDrawer(s.name, s.image_url);
};

const viewScheme = (idStr: string | null) => {
    if (!idStr) return;
    const s = schemeById.value[Number(idStr)];
    if (s) {
        const desc = [
            s.prerequisite ? `Prerequisite: ${s.prerequisite}` : '',
            s.reveal ? `Reveal: ${s.reveal}` : '',
            s.scoring ? `Scoring: ${s.scoring}` : '',
        ]
            .filter(Boolean)
            .join('\n\n');
        openViewDrawer(s.name, s.image_url, desc || undefined);
    }
};

const activePlayers = computed(() => props.tournament.players.filter((p) => !p.is_disqualified && p.dropped_after_round === null));

const latestRound = computed(() => (props.tournament.rounds.length > 0 ? props.tournament.rounds[props.tournament.rounds.length - 1] : null));

// One-glance status for a pairing row: tracker progress, reported result, or waiting.
const gameStatus = (game: any, round: any): { label: string; tone: string } => {
    if (game.is_bye) return { label: 'Bye', tone: 'text-muted-foreground' };
    if (game.is_forfeit) return { label: 'Forfeit', tone: 'border-destructive/50 text-destructive' };
    if (game.report_status === 'disputed') return { label: 'Disputed', tone: 'border-destructive/50 text-destructive' };
    if (game.report_status === 'pending')
        return { label: 'Reported · awaiting confirmation', tone: 'border-amber-500/60 text-amber-700 dark:text-amber-300' };
    if (game.result === 'agreed') return { label: 'Agreed result', tone: 'border-sky-500/50 text-sky-700 dark:text-sky-300' };
    if (game.result === 'completed') {
        return { label: 'Reported', tone: 'border-emerald-500/50 text-emerald-700 dark:text-emerald-300' };
    }
    if (game.tracker_game?.status === 'in_progress') {
        return {
            label: `Playing · T${game.tracker_game.current_turn ?? 1}/${game.tracker_game.max_turns ?? 5}`,
            tone: 'border-amber-500/60 text-amber-700 dark:text-amber-300',
        };
    }
    if (game.tracker_game?.status === 'abandoned') return { label: 'Tracker abandoned', tone: 'border-destructive/50 text-destructive' };
    if (round.status === 'in_progress') return { label: 'Waiting for score', tone: 'text-muted-foreground' };
    return { label: 'Not started', tone: 'text-muted-foreground' };
};

const roundAllScored = (round: TournamentRound): boolean => {
    if (!round.games.length) return false;
    return round.games.every((g: any) => g.result === 'completed' || g.result === 'agreed' || g.result === 'forfeited');
};

const canStartRound = (round: TournamentRound): boolean => {
    return round.status === 'setup' && round.games.length > 0 && props.tournament.status === 'active';
};

const canEndRound = (round: TournamentRound): boolean => {
    return round.status === 'in_progress' && roundAllScored(round);
};

const isLastRound = computed(() => props.tournament.rounds.length >= props.tournament.planned_rounds);

const canFinalize = computed(() => {
    if (props.tournament.status !== 'active') return false;
    if (!isLastRound.value) return false;
    if (!latestRound.value) return false;
    return latestRound.value.status === 'completed';
});
// ─── Next step guidance ───
// One obvious "what do I do now" for the organizer, derived from the tournament and its latest round,
// plus a four-stage progress strip. Actions reuse the same handlers as the buttons deeper in the page.
type StepStage = 'players' | 'pairing' | 'play' | 'results';
interface NextStep {
    stage: StepStage;
    title: string;
    detail: string;
    actionLabel?: string;
    action?: () => void;
    disabled?: boolean;
}

const nextStep = computed<NextStep | null>(() => {
    const t = props.tournament;
    const round = latestRound.value;

    if (t.status === 'completed') return null;
    if (t.status === 'draft') {
        return {
            stage: 'players',
            title: 'Set up your tournament',
            detail: 'Add players or collect RSVPs, then open registration.',
            actionLabel: statusTransitions.draft.label,
            action: () => (confirmStatusDialog.value = true),
        };
    }
    if (t.status === 'registration') {
        return {
            stage: 'players',
            title: 'Registration is open',
            detail: `${t.players.length} ${t.players.length === 1 ? 'player is' : 'players are'} in. Start the tournament when everyone has arrived.`,
            actionLabel: statusTransitions.registration.label,
            action: () => (confirmStatusDialog.value = true),
        };
    }
    if (!round) {
        return {
            stage: 'pairing',
            title: 'Add Round 1',
            detail: 'Create the first round, set its scenario, then pair the players.',
            actionLabel: 'Add Round 1',
            action: createRound,
        };
    }
    if (round.status === 'setup' && round.games.length === 0) {
        const reason = pairBlockReason(round);
        if (reason === null) {
            return {
                stage: 'pairing',
                title: `Pair Round ${round.round_number}`,
                detail: `The scenario is set. Generate Swiss pairings for ${activePlayers.value.length} players.`,
                actionLabel: 'Auto-pair players',
                action: () => generatePairings(round.id),
                disabled: submitting.value,
            };
        }
        return {
            stage: 'pairing',
            title: `Set up Round ${round.round_number}`,
            detail: reason,
            actionLabel: 'Set scenario',
            action: () => {
                activeTab.value = 'rounds';
                if (expandedScenarioRoundId.value !== round.id) toggleScenarioEdit(round);
            },
        };
    }
    if (round.status === 'setup') {
        return {
            stage: 'pairing',
            title: `Round ${round.round_number} is paired`,
            detail: 'Check the pairings, then start the round to create the games.',
            actionLabel: `Start Round ${round.round_number}`,
            action: () => startRound(round.id),
            disabled: submitting.value,
        };
    }
    if (round.status === 'in_progress') {
        const total = round.games.filter((g: any) => !g.is_bye).length;
        const done = round.games.filter((g: any) => !g.is_bye && ['completed', 'agreed', 'forfeited'].includes(g.result)).length;
        if (canEndRound(round)) {
            return {
                stage: 'play',
                title: `Round ${round.round_number}: all scores are in`,
                detail: 'Every game has a result. End the round to update standings.',
                actionLabel: `End Round ${round.round_number}`,
                action: () => endRound(round.id),
                disabled: submitting.value,
            };
        }
        return {
            stage: 'play',
            title: `Round ${round.round_number} is in progress`,
            detail: `${done} of ${total} games reported. The round can end once every result is entered.`,
            actionLabel: 'View games',
            action: () => (activeTab.value = 'rounds'),
        };
    }
    // Latest round completed
    if (canFinalize.value) {
        return {
            stage: 'results',
            title: 'All rounds are finished',
            detail: 'Finalize the standings to complete the tournament.',
            actionLabel: 'Finalize standings',
            action: () => (confirmFinalizeDialog.value = true),
        };
    }
    return {
        stage: 'pairing',
        title: `Round ${round.round_number} is complete`,
        detail: `Add Round ${nextRoundNumber()} when you're ready.`,
        actionLabel: `Add Round ${nextRoundNumber()}`,
        action: createRound,
    };
});
const nextRoundNumber = () => props.tournament.rounds.length + 1;

const STEP_STAGES: { key: StepStage; label: string }[] = [
    { key: 'players', label: 'Players' },
    { key: 'pairing', label: 'Pairing' },
    { key: 'play', label: 'Play' },
    { key: 'results', label: 'Results' },
];
const stageState = (key: StepStage): 'done' | 'current' | 'upcoming' => {
    const current = nextStep.value?.stage;
    if (!current) return 'done';
    return STEP_STAGES.findIndex((x) => x.key === key) < STEP_STAGES.findIndex((x) => x.key === current)
        ? 'done'
        : key === current
          ? 'current'
          : 'upcoming';
};

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
            <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
                <Button variant="ghost" class="gap-1.5 text-sm" @click="router.get(route('tournaments.index'))">
                    <ArrowLeft class="size-4" /> Back
                </Button>
                <div class="flex flex-wrap items-center gap-2">
                    <Button variant="outline" size="sm" class="gap-1.5 text-xs" @click="copyPublicLink">
                        <Copy class="size-3" />
                        {{ linkCopied ? 'Copied!' : 'Share' }}
                    </Button>
                    <Button as-child variant="outline" size="sm" class="gap-1 text-xs">
                        <a :href="route('tournaments.display', tournament.uuid)" target="_blank" rel="noopener"
                            ><MonitorPlay class="size-3" /> Display</a
                        >
                    </Button>
                    <Button aria-label="Show QR code" variant="outline" size="sm" class="gap-1 text-xs" @click="qrOpen = true">
                        <QrCode class="size-3" />
                    </Button>
                    <Button
                        v-if="tournament.status === 'registration'"
                        variant="ghost"
                        size="sm"
                        class="text-xs text-muted-foreground"
                        @click="revertToDraft"
                    >
                        Back to Draft
                    </Button>
                    <Button v-if="statusTransitions[tournament.status]" size="sm" @click="confirmStatusDialog = true">
                        {{ statusTransitions[tournament.status].label }}
                    </Button>
                </div>
            </div>

            <!-- Next step: what to do now, and where we are in the tournament -->
            <Card v-if="nextStep" class="mb-4 border-primary/30 bg-primary/5">
                <CardContent class="space-y-3 p-4">
                    <ol class="flex items-center gap-1 text-xs" aria-label="Tournament progress">
                        <li
                            v-for="(stage, i) in STEP_STAGES"
                            :key="stage.key"
                            class="flex items-center gap-1"
                            :aria-current="stageState(stage.key) === 'current' ? 'step' : undefined"
                        >
                            <span
                                class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 font-medium"
                                :class="
                                    stageState(stage.key) === 'current'
                                        ? 'bg-primary text-primary-foreground'
                                        : stageState(stage.key) === 'done'
                                          ? 'bg-emerald-500/15 text-emerald-800 dark:text-emerald-400'
                                          : 'bg-muted text-muted-foreground'
                                "
                            >
                                <Check v-if="stageState(stage.key) === 'done'" class="size-3" />
                                {{ stage.label }}
                            </span>
                            <span v-if="i < STEP_STAGES.length - 1" class="h-px w-3 bg-border sm:w-6" aria-hidden="true" />
                        </li>
                    </ol>
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <div class="text-sm font-semibold">{{ nextStep.title }}</div>
                            <div class="text-xs text-muted-foreground sm:text-sm">{{ nextStep.detail }}</div>
                        </div>
                        <Button v-if="nextStep.action" class="shrink-0 gap-1.5" :disabled="nextStep.disabled" @click="nextStep.action">
                            <Loader2 v-if="nextStep.disabled" class="size-4 animate-spin" />
                            {{ nextStep.actionLabel }}
                            <ArrowRight v-if="!nextStep.disabled" class="size-4" />
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <Tabs v-model="activeTab" default-value="standings">
                <TabsList class="mb-4 flex w-full flex-wrap">
                    <TabsTrigger value="standings" class="gap-1 text-xs sm:text-sm">Standings</TabsTrigger>
                    <TabsTrigger value="players" class="gap-1 text-xs sm:text-sm">
                        Players
                        <Badge v-if="tournament.players.length" variant="secondary" class="ml-1 px-1 py-0 text-2xs">{{
                            tournament.players.length
                        }}</Badge>
                    </TabsTrigger>
                    <TabsTrigger v-if="tournament.status === 'draft' || tournament.rsvps?.length" value="rsvps" class="gap-1 text-xs sm:text-sm">
                        RSVPs
                        <Badge v-if="tournament.rsvps?.length" variant="secondary" class="ml-1 px-1 py-0 text-2xs">{{
                            tournament.rsvps.length
                        }}</Badge>
                    </TabsTrigger>
                    <TabsTrigger value="rounds" class="gap-1 text-xs sm:text-sm">
                        Rounds
                        <Badge v-if="tournament.rounds.length" variant="secondary" class="ml-1 px-1 py-0 text-2xs">{{
                            tournament.rounds.length
                        }}</Badge>
                    </TabsTrigger>
                    <TabsTrigger value="settings" class="text-xs sm:text-sm">Settings</TabsTrigger>
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
                </TabsContent>

                <!-- ═══ RSVPs ═══ -->
                <TabsContent value="rsvps">
                    <div v-if="!tournament.rsvps?.length" class="py-8 text-center text-sm text-muted-foreground">
                        No RSVPs yet. Share the tournament link so players can express interest.
                    </div>
                    <template v-else>
                        <div class="mb-4">
                            <h3 class="text-sm font-semibold">{{ tournament.rsvps.length }} RSVPs</h3>
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
                                            tournament.status === 'registration' && !tournament.players.some((p: any) => p.user?.id === rsvp.user_id)
                                        "
                                        variant="outline"
                                        size="sm"
                                        class="h-8 gap-1 px-2.5 text-xs"
                                        @click="playersTab?.openRsvpPlayerDialog(rsvp)"
                                    >
                                        <Plus class="size-2.5" /> Register
                                    </Button>
                                    <Badge
                                        v-else-if="tournament.players.some((p: any) => p.user?.id === rsvp.user_id)"
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

                <!-- ═══ ROUNDS ═══ -->
                <TabsContent value="rounds">
                    <!-- Finalize Tournament (last round completed) -->
                    <div v-if="canFinalize" class="mb-4 flex flex-wrap gap-2">
                        <Button class="gap-1" size="sm" @click="confirmFinalizeDialog = true">
                            <Trophy class="size-3.5" />
                            Finalize Standings &amp; Complete
                        </Button>
                    </div>

                    <div v-if="!tournament.rounds.length" class="py-8 text-center text-sm text-muted-foreground">
                        No rounds yet — use the button below to add one.
                    </div>

                    <div class="space-y-4">
                        <Card v-for="round in tournament.rounds" :key="round.id">
                            <CardContent class="p-4">
                                <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                                    <div class="flex items-center gap-2">
                                        <h2 class="text-sm font-semibold sm:text-base">Round {{ round.round_number }}</h2>
                                        <Badge
                                            :class="[
                                                'border-0 text-2xs',
                                                round.status === 'completed'
                                                    ? 'bg-muted text-muted-foreground'
                                                    : round.status === 'in_progress'
                                                      ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
                                                      : round.games.length
                                                        ? 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200'
                                                        : 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
                                            ]"
                                            variant="outline"
                                        >
                                            {{
                                                round.status === 'in_progress'
                                                    ? 'In Progress'
                                                    : round.status === 'completed'
                                                      ? 'Completed'
                                                      : round.games.length
                                                        ? 'Paired'
                                                        : 'Setup'
                                            }}
                                        </Badge>
                                        <RoundClock
                                            v-if="round.status === 'in_progress' && round.started_at"
                                            :started-at="round.started_at"
                                            :limit-minutes="tournament.round_time_limit"
                                        />
                                        <span
                                            v-if="round.strategy"
                                            class="max-w-[110px] truncate text-xs text-muted-foreground sm:max-w-none"
                                            :title="round.strategy.name"
                                            >{{ round.strategy.name }}</span
                                        >
                                        <button
                                            v-if="round.status !== 'in_progress' && tournament.status !== 'completed'"
                                            class="rounded p-1 text-muted-foreground hover:bg-destructive/10 hover:text-destructive"
                                            title="Delete this round"
                                            :aria-label="`Delete round ${round.round_number}`"
                                            @click="deleteRound(round.id, round.round_number)"
                                        >
                                            <X class="size-3.5" />
                                        </button>
                                    </div>
                                    <div class="flex items-center gap-1">
                                        <Button
                                            v-if="
                                                ruleset.scenario_fields.length &&
                                                (tournament.status !== 'completed' || round.strategy || round.deployment)
                                            "
                                            variant="ghost"
                                            size="sm"
                                            class="h-7 px-2 text-2xs sm:text-xs"
                                            @click="toggleScenarioEdit(round)"
                                        >
                                            {{
                                                expandedScenarioRoundId === round.id
                                                    ? 'Hide Scenario'
                                                    : round.strategy || round.deployment || round.scenario?.operation_id
                                                      ? 'Scenario'
                                                      : 'Set Scenario'
                                            }}
                                        </Button>
                                        <Button
                                            v-if="round.status === 'setup'"
                                            variant="outline"
                                            size="sm"
                                            class="h-7 gap-1 px-2 text-2xs sm:text-xs"
                                            :disabled="submitting || !canAutoPair(round)"
                                            :title="
                                                pairBlockReason(round) ??
                                                (round.games.length
                                                    ? 'Regenerate Swiss pairings (preserves manual pairings)'
                                                    : 'Generate Swiss pairings')
                                            "
                                            @click="generatePairings(round.id)"
                                        >
                                            <Loader2 v-if="submitting" class="size-3 animate-spin" />
                                            Auto Pair
                                        </Button>
                                        <Button
                                            v-if="canStartRound(round)"
                                            size="sm"
                                            class="h-7 gap-1 px-2 text-2xs sm:text-xs"
                                            :disabled="submitting"
                                            @click="startRound(round.id)"
                                        >
                                            <Loader2 v-if="submitting" class="size-3 animate-spin" />
                                            Start Round
                                        </Button>
                                        <Button
                                            v-if="round.status === 'in_progress'"
                                            size="sm"
                                            class="h-7 gap-1 px-2 text-2xs sm:text-xs"
                                            :class="canEndRound(round) ? '' : 'opacity-50'"
                                            :disabled="submitting || !canEndRound(round)"
                                            :title="!canEndRound(round) ? 'All scores must be entered before ending the round' : ''"
                                            @click="endRound(round.id)"
                                        >
                                            <Loader2 v-if="submitting" class="size-3 animate-spin" />
                                            End Round
                                        </Button>
                                    </div>
                                </div>

                                <!-- Inline Scenario Editor -->
                                <div v-if="expandedScenarioRoundId === round.id" class="mb-3 space-y-2 rounded-md border border-dashed p-3">
                                    <div class="flex items-center justify-between">
                                        <span class="text-xs font-medium text-muted-foreground">Scenario</span>
                                        <Button variant="outline" size="sm" class="h-6 gap-1 px-2 text-2xs" @click="randomizeInDialog">
                                            <Dices class="size-3" /> Random
                                        </Button>
                                    </div>
                                    <div v-if="!usesTosScenario" class="space-y-1.5">
                                        <div class="flex gap-1">
                                            <Select v-model="editDeployment">
                                                <SelectTrigger class="h-8 flex-1 text-xs"><SelectValue placeholder="Deployment..." /></SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem v-for="d in all_deployments" :key="d.value" :value="d.value">{{
                                                        d.label
                                                    }}</SelectItem>
                                                </SelectContent>
                                            </Select>
                                            <button
                                                aria-label="View deployment"
                                                v-if="editDeployment"
                                                class="shrink-0 rounded-md border px-2 text-muted-foreground hover:bg-accent hover:text-foreground"
                                                @click="viewDeployment(editDeployment)"
                                            >
                                                <Eye class="size-3" />
                                            </button>
                                        </div>
                                        <div class="flex gap-1">
                                            <Select v-model="editStrategy">
                                                <SelectTrigger class="h-8 flex-1 text-xs"
                                                    ><SelectValue :placeholder="all_strategies ? 'Strategy...' : 'Loading…'"
                                                /></SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem v-for="s in all_strategies ?? []" :key="s.id" :value="String(s.id)">{{
                                                        s.name
                                                    }}</SelectItem>
                                                </SelectContent>
                                            </Select>
                                            <button
                                                aria-label="View strategy"
                                                v-if="editStrategy"
                                                class="shrink-0 rounded-md border px-2 text-muted-foreground hover:bg-accent hover:text-foreground"
                                                @click="viewStrategy(editStrategy)"
                                            >
                                                <Eye class="size-3" />
                                            </button>
                                        </div>
                                        <div v-for="(_, idx) in editSchemePool" :key="'rs-' + idx" class="flex gap-1">
                                            <Select
                                                :model-value="editSchemePool[idx] ?? undefined"
                                                @update:model-value="(v) => (editSchemePool[idx] = v ?? null)"
                                            >
                                                <SelectTrigger class="h-8 flex-1 text-xs"
                                                    ><SelectValue :placeholder="all_schemes ? 'Scheme ' + (idx + 1) : 'Loading…'"
                                                /></SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem v-for="s in availableSchemesForSlot(idx)" :key="s.id" :value="String(s.id)">{{
                                                        s.name
                                                    }}</SelectItem>
                                                </SelectContent>
                                            </Select>
                                            <button
                                                aria-label="View scheme"
                                                v-if="editSchemePool[idx]"
                                                class="shrink-0 rounded-md border px-2 text-muted-foreground hover:bg-accent hover:text-foreground"
                                                @click="viewScheme(editSchemePool[idx])"
                                            >
                                                <Eye class="size-3" />
                                            </button>
                                        </div>
                                    </div>
                                    <div v-else class="space-y-1.5">
                                        <div class="flex gap-1">
                                            <Select v-model="editTosOperation">
                                                <SelectTrigger class="h-8 flex-1 text-xs"><SelectValue placeholder="Operation..." /></SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem v-for="o in tos_operations" :key="o.id" :value="String(o.id)">{{
                                                        o.name
                                                    }}</SelectItem>
                                                </SelectContent>
                                            </Select>
                                            <button
                                                v-if="editTosOperation"
                                                aria-label="View operation"
                                                class="shrink-0 rounded-md border px-2 text-muted-foreground hover:bg-accent hover:text-foreground"
                                                @click="viewTosOperation(editTosOperation)"
                                            >
                                                <Eye class="size-3" />
                                            </button>
                                        </div>
                                        <div class="flex gap-1">
                                            <Select v-model="editTosDeployment">
                                                <SelectTrigger class="h-8 flex-1 text-xs"><SelectValue placeholder="Deployment..." /></SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem v-for="d in tos_deployments" :key="d.id" :value="String(d.id)">{{
                                                        d.name
                                                    }}</SelectItem>
                                                </SelectContent>
                                            </Select>
                                            <button
                                                v-if="editTosDeployment"
                                                aria-label="View deployment"
                                                class="shrink-0 rounded-md border px-2 text-muted-foreground hover:bg-accent hover:text-foreground"
                                                @click="viewTosDeployment(editTosDeployment)"
                                            >
                                                <Eye class="size-3" />
                                            </button>
                                        </div>
                                    </div>
                                    <div class="flex items-center justify-between text-2xs">
                                        <span class="text-muted-foreground">
                                            <span v-if="scenarioSaveState === 'saving'" class="inline-flex items-center gap-1"
                                                ><Loader2 class="size-3 animate-spin" /> Saving…</span
                                            >
                                            <span
                                                v-else-if="scenarioSaveState === 'saved'"
                                                class="inline-flex items-center gap-1 text-emerald-600 dark:text-emerald-400"
                                                ><Check class="size-3" /> Saved</span
                                            >
                                            <span v-else>Auto-saves as you change fields.</span>
                                        </span>
                                        <Button variant="ghost" size="sm" class="h-7 px-2 text-2xs" @click="expandedScenarioRoundId = null"
                                            >Done</Button
                                        >
                                    </div>
                                </div>
                                <!-- Scenario summary (when not editing) -->
                                <div
                                    v-else-if="usesTosScenario && round.scenario"
                                    class="mb-3 flex flex-wrap gap-x-3 gap-y-0.5 text-2xs text-muted-foreground"
                                >
                                    <span v-if="round.scenario.operation_id">{{ tosOperationName(round.scenario.operation_id) }}</span>
                                    <span v-if="round.scenario.deployment_id">{{ tosDeploymentName(round.scenario.deployment_id) }}</span>
                                </div>
                                <div
                                    v-else-if="round.strategy || round.deployment"
                                    class="mb-3 flex flex-wrap gap-x-3 gap-y-0.5 text-2xs text-muted-foreground"
                                >
                                    <span v-if="round.deployment">{{ deploymentByValue[round.deployment]?.label }}</span>
                                    <span v-if="round.strategy">{{ round.strategy.name }}</span>
                                    <span v-if="round.scheme_pool?.length">{{
                                        round.scheme_pool.map((id: number) => schemeById[id]?.name ?? id).join(' / ')
                                    }}</span>
                                </div>

                                <div v-if="!round.games.length" class="py-4 text-center text-xs text-muted-foreground">
                                    No games in this round yet. Generate pairings or add them manually.
                                </div>

                                <div v-else class="space-y-1.5">
                                    <div
                                        v-for="game in round.games"
                                        :key="game.id"
                                        class="rounded-md border px-2 py-2 text-sm sm:px-3"
                                        :class="
                                            game.result === 'completed' || game.result === 'agreed' || game.result === 'forfeited'
                                                ? ''
                                                : 'border-dashed'
                                        "
                                    >
                                        <div class="flex flex-col gap-2 lg:flex-row lg:items-center lg:justify-between">
                                            <div class="flex min-w-0 flex-1 items-center gap-2">
                                                <span
                                                    v-if="game.table_number"
                                                    class="shrink-0 rounded bg-muted px-1.5 py-0.5 text-2xs font-semibold tabular-nums text-muted-foreground"
                                                    >Table {{ game.table_number }}</span
                                                >
                                                <div class="flex min-w-0 flex-1 flex-col gap-0.5 sm:flex-row sm:items-center sm:gap-1.5">
                                                    <span class="flex min-w-0 items-center gap-1.5">
                                                        <FactionLogo
                                                            v-if="game.player_one_faction || playerFaction(game.player_one_id)"
                                                            :faction="(game.player_one_faction || playerFaction(game.player_one_id))!"
                                                            class-name="size-4 shrink-0"
                                                        />
                                                        <span class="truncate font-medium">{{ playerName(game.player_one_id) }}</span>
                                                    </span>
                                                    <template v-if="!game.is_bye">
                                                        <span class="hidden shrink-0 text-2xs text-muted-foreground sm:inline">vs</span>
                                                        <span class="flex min-w-0 items-center gap-1.5">
                                                            <FactionLogo
                                                                v-if="game.player_two_faction || playerFaction(game.player_two_id)"
                                                                :faction="(game.player_two_faction || playerFaction(game.player_two_id))!"
                                                                class-name="size-4 shrink-0"
                                                            />
                                                            <span class="truncate font-medium">{{ playerName(game.player_two_id) }}</span>
                                                        </span>
                                                    </template>
                                                </div>
                                            </div>

                                            <div class="flex shrink-0 flex-wrap items-center gap-2">
                                                <!-- One status chip per game: where is this match right now? -->
                                                <span
                                                    class="inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-2xs font-medium"
                                                    :class="gameStatus(game, round).tone"
                                                    >{{ gameStatus(game, round).label }}</span
                                                >
                                                <span v-if="game.is_bye" class="text-xs text-muted-foreground">Automatic win</span>
                                                <span
                                                    v-else-if="game.result === 'completed' || game.result === 'agreed' || game.result === 'forfeited'"
                                                    class="text-sm font-bold tabular-nums"
                                                    >{{ game.player_one_vp ?? '-' }} – {{ game.player_two_vp ?? '-' }}</span
                                                >
                                                <Button
                                                    v-if="!game.is_bye && round.status === 'in_progress'"
                                                    size="sm"
                                                    :variant="game.result === 'pending' ? 'default' : 'outline'"
                                                    class="h-8"
                                                    @click="openScoreEdit(game)"
                                                >
                                                    {{ game.result === 'pending' ? 'Enter score' : 'Edit score' }}
                                                </Button>
                                                <DropdownMenu v-if="round.status === 'in_progress' && !game.is_bye && game.player_two">
                                                    <DropdownMenuTrigger as-child>
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            class="size-8 text-muted-foreground"
                                                            :aria-label="`More actions for table ${game.table_number ?? ''}`.trim()"
                                                        >
                                                            <MoreHorizontal class="size-4" />
                                                        </Button>
                                                    </DropdownMenuTrigger>
                                                    <DropdownMenuContent align="end">
                                                        <DropdownMenuItem
                                                            v-if="!game.is_forfeit"
                                                            class="text-destructive focus:text-destructive"
                                                            @select="openForfeitDialog(game)"
                                                        >
                                                            Record a forfeit…
                                                        </DropdownMenuItem>
                                                        <DropdownMenuItem v-else @select="removeForfeit(game)">Undo forfeit</DropdownMenuItem>
                                                    </DropdownMenuContent>
                                                </DropdownMenu>
                                                <button
                                                    aria-label="Remove game"
                                                    v-if="round.status === 'setup'"
                                                    class="rounded p-1 text-muted-foreground hover:bg-destructive/10 hover:text-destructive"
                                                    @click="removeGame(game.id)"
                                                >
                                                    <X class="size-3.5" />
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Pending scores indicator -->
                                <div
                                    v-if="round.status === 'in_progress' && round.games.length > 0 && !roundAllScored(round)"
                                    class="mt-2 flex items-center gap-1.5 text-xs text-muted-foreground"
                                >
                                    <Loader2 class="size-3 animate-spin" />
                                    {{ round.games.filter((g: any) => g.result === 'pending').length }} game(s) pending scores
                                </div>

                                <!-- Manual Pairing -->
                                <div
                                    v-if="round.status === 'setup' && unpairedPlayers(round.id).length"
                                    class="mt-3 rounded-md border border-dashed p-2 sm:p-3"
                                >
                                    <div class="mb-2 flex items-center justify-between gap-2">
                                        <span class="text-xs font-medium text-muted-foreground">Manual Pairing</span>
                                        <span v-if="pairBlockReason(round)" class="text-2xs italic text-amber-600 dark:text-amber-400">{{
                                            pairBlockReason(round)
                                        }}</span>
                                    </div>
                                    <fieldset :disabled="!canAutoPair(round)" :class="!canAutoPair(round) ? 'cursor-not-allowed opacity-50' : ''">
                                        <div class="grid gap-2 sm:grid-cols-[1fr_1fr_auto] sm:items-end">
                                            <div class="space-y-1">
                                                <Label class="text-2xs">Player 1</Label>
                                                <Select v-model="getManualPair(round.id).p1">
                                                    <SelectTrigger class="h-8 text-xs"><SelectValue placeholder="Select player..." /></SelectTrigger>
                                                    <SelectContent>
                                                        <SelectItem v-for="p in unpairedPlayers(round.id)" :key="p.id" :value="String(p.id)">{{
                                                            p.display_name
                                                        }}</SelectItem>
                                                    </SelectContent>
                                                </Select>
                                            </div>
                                            <div class="space-y-1">
                                                <Label class="text-2xs">Player 2 <span class="text-muted-foreground">(empty = bye)</span></Label>
                                                <Select v-model="getManualPair(round.id).p2">
                                                    <SelectTrigger class="h-8 text-xs"><SelectValue placeholder="BYE" /></SelectTrigger>
                                                    <SelectContent>
                                                        <SelectItem value="bye">BYE</SelectItem>
                                                        <SelectItem
                                                            v-for="p in unpairedPlayers(round.id).filter(
                                                                (p) => String(p.id) !== getManualPair(round.id).p1,
                                                            )"
                                                            :key="p.id"
                                                            :value="String(p.id)"
                                                            >{{ p.display_name }}</SelectItem
                                                        >
                                                    </SelectContent>
                                                </Select>
                                            </div>
                                            <Button
                                                size="sm"
                                                class="h-8 w-full gap-1 sm:w-auto"
                                                :disabled="!getManualPair(round.id).p1 || submitting || !canAutoPair(round)"
                                                :title="pairBlockReason(round) ?? ''"
                                                @click="addManualPairing(round.id)"
                                            >
                                                <Plus class="size-3" /> Pair
                                            </Button>
                                        </div>
                                    </fieldset>
                                    <div v-if="unpairedPlayers(round.id).length" class="mt-2 text-2xs text-muted-foreground">
                                        {{ unpairedPlayers(round.id).length }} unpaired:
                                        {{
                                            unpairedPlayers(round.id)
                                                .map((p) => p.display_name)
                                                .join(', ')
                                        }}
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    <!-- Add another round (always available; will bump planned_rounds if exceeded) -->
                    <div v-if="tournament.status !== 'completed'" class="mt-4 flex justify-center">
                        <Button variant="outline" size="sm" class="gap-1" :disabled="submitting" @click="createRound">
                            <Plus class="size-3.5" /> Add Round {{ tournament.rounds.length + 1 }}
                        </Button>
                    </div>
                </TabsContent>

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

                    <!-- Edit Mode -->
                    <TournamentSettingsForm
                        v-if="editingSettings"
                        :tournament="tournament"
                        :ruleset="ruleset"
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

    <ScoreEntryDialog :game="scoreGame" :tournament="tournament" :ruleset="ruleset" :masters="masters" @close="scoreGame = null" />

    <!-- View Drawer (Strategy / Scheme / Deployment details) -->
    <Drawer v-model:open="viewDrawerOpen">
        <DrawerContent>
            <div class="mx-auto w-full max-w-sm">
                <DrawerHeader class="pb-2">
                    <DrawerTitle class="text-center">{{ viewDrawerTitle }}</DrawerTitle>
                </DrawerHeader>
                <div class="px-4 pb-4">
                    <div v-if="viewDrawerImage" class="mb-3 flex justify-center">
                        <img :src="viewDrawerImage" :alt="viewDrawerTitle" class="max-h-[50dvh] w-auto rounded-lg" loading="lazy" decoding="async" />
                    </div>
                    <p v-if="viewDrawerDescription" class="text-sm leading-relaxed text-muted-foreground">{{ viewDrawerDescription }}</p>
                    <p v-else-if="!viewDrawerImage" class="text-center text-sm text-muted-foreground">No additional details available.</p>
                </div>
                <DrawerFooter class="shrink-0 pt-2">
                    <DrawerClose as-child>
                        <Button variant="outline">Close</Button>
                    </DrawerClose>
                </DrawerFooter>
            </div>
        </DrawerContent>
    </Drawer>

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

    <!-- Forfeit Dialog -->
    <Dialog v-model:open="forfeitDialogOpen">
        <DialogContent class="max-w-xs">
            <DialogHeader>
                <DialogTitle>Record Forfeit</DialogTitle>
                <DialogDescription>Which player conceded?</DialogDescription>
            </DialogHeader>
            <div v-if="forfeitGame" class="space-y-2">
                <button
                    class="flex w-full items-center gap-2 rounded-lg border p-3 text-left text-sm transition-colors hover:bg-destructive/5"
                    @click="submitForfeit(forfeitGame!.player_one_id)"
                >
                    <FactionLogo
                        v-if="forfeitGame.player_one_faction || playerFaction(forfeitGame.player_one_id)"
                        :faction="(forfeitGame.player_one_faction || playerFaction(forfeitGame.player_one_id))!"
                        class-name="size-4 shrink-0"
                    />
                    <span class="font-medium">{{ playerName(forfeitGame.player_one_id) }}</span>
                    <span class="ml-auto text-xs text-muted-foreground">conceded</span>
                </button>
                <button
                    v-if="forfeitGame.player_two"
                    class="flex w-full items-center gap-2 rounded-lg border p-3 text-left text-sm transition-colors hover:bg-destructive/5"
                    @click="submitForfeit(forfeitGame!.player_two_id!)"
                >
                    <FactionLogo
                        v-if="forfeitGame.player_two_faction || playerFaction(forfeitGame.player_two_id)"
                        :faction="(forfeitGame.player_two_faction || playerFaction(forfeitGame.player_two_id))!"
                        class-name="size-4 shrink-0"
                    />
                    <span class="font-medium">{{ playerName(forfeitGame.player_two_id) }}</span>
                    <span class="ml-auto text-xs text-muted-foreground">conceded</span>
                </button>
            </div>
            <p v-if="forfeitError" class="text-xs text-destructive">{{ forfeitError }}</p>
            <DialogFooter>
                <Button variant="outline" @click="forfeitDialogOpen = false">Cancel</Button>
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
