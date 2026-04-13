<script setup lang="ts">
import FactionLogo from '@/components/FactionLogo.vue';
import PageBanner from '@/components/PageBanner.vue';
import QRCodeDialog from '@/components/QRCodeDialog.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NumberField, NumberFieldContent, NumberFieldDecrement, NumberFieldIncrement, NumberFieldInput } from '@/components/ui/number-field';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import type { SharedData } from '@/types';
import { Head, router, usePage } from '@inertiajs/vue3';
import { Drawer, DrawerClose, DrawerContent, DrawerFooter, DrawerHeader, DrawerTitle } from '@/components/ui/drawer';
import { useTournament } from '@/composables/useTournament';
import { useTournamentChannel } from '@/composables/useTournamentChannel';
import { useTournamentStatus } from '@/composables/useTournamentStatus';
import { ArrowLeft, CalendarDays, Copy, Crown, Dices, Eye, Link2, Link2Off, Loader2, LogOut, MapPin, Plus, QrCode, Shield, Skull, Star, Trophy, Users, X } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

interface TournamentPlayer {
    id: number;
    display_name: string;
    faction: string | null;
    user: { id: number; name: string } | null;
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
    player_one_strategy_vp: number | null;
    player_one_scheme_vp: number | null;
    player_two_faction: string | null;
    player_two_master: string | null;
    player_two_title: string | null;
    player_two_vp: number | null;
    player_two_strategy_vp: number | null;
    player_two_scheme_vp: number | null;
    is_bye: boolean;
    is_forfeit: boolean;
    forfeit_player_id: number | null;
    result: string;
    table_number: number | null;
}

interface TournamentRound {
    id: number;
    round_number: number;
    deployment: string | null;
    strategy: { id: number; name: string } | null;
    scheme_pool: number[] | null;
    status: string;
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
    creator_id: number;
    players: TournamentPlayer[];
    rounds: TournamentRound[];
    organizers: { id: number; name: string }[];
    rsvps?: Rsvp[];
}

const props = defineProps<{
    tournament: TournamentData;
    standings: StandingEntry[];
    seasons: { value: string; label: string }[];
    factions: Record<string, FactionInfo>;
    encounter_types: { value: string; label: string }[];
    masters: MasterOption[];
    all_strategies: { id: number; name: string; slug: string; image_url: string | null }[];
    all_schemes: { id: number; name: string; slug: string; image_url: string | null; prerequisite: string | null; reveal: string | null; scoring: string | null }[];
    all_deployments: { value: string; label: string; description: string | null; image_url: string | null }[];
}>();

// Tournament-wide state + helpers (memoized player lookup, doAction, factionBackground, error toast).
const tournamentRef = computed(() => props.tournament);
const { submitting, actionError, doAction, reloadProps, playerName, playerFaction, factionBackground } = useTournament(tournamentRef);
const { statusColor, statusLabel } = useTournamentStatus();

// Restore tab from URL hash. Only allow tabs that are actually rendered for this tournament.
const visibleTabs = computed(() => {
    const t = ['standings', 'players', 'rounds', 'settings'];
    if ((props.tournament as any).rsvps?.length || props.tournament.status === 'draft') {
        t.push('rsvps');
    }
    return t;
});
const initialTab = typeof window !== 'undefined' && window.location.hash ? window.location.hash.slice(1) : 'standings';
const activeTab = ref(visibleTabs.value.includes(initialTab) ? initialTab : 'standings');

const encounterTypeLabel = computed(() => {
    const map: Record<string, string> = {
        traditional: 'Traditional', enforcer_brawl: 'Enforcer Brawl', crossroads: 'Crossroads',
        team_event: 'Team Event', double_rush: 'Double Rush', total_war: 'Total War',
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

watch(activeTab, (tab) => { if (typeof window !== 'undefined') window.location.hash = tab; });

// Reload Inertia props after a mutation while preserving local UI state.
const reloadPage = () => reloadProps(['tournament', 'standings']);

// Subscribe to the public tournament broadcast channel so collaborating TOs
// (or this user's other tabs) see updates in near-realtime.
useTournamentChannel(props.tournament.uuid, () => reloadPage());

// ─── RSVP → Player conversion ───
const rsvpPlayerFaction = ref<string | null>(null);
const rsvpPlayerDialogOpen = ref(false);
const rsvpPlayerTarget = ref<any>(null);

const openRsvpPlayerDialog = (rsvp: any) => {
    rsvpPlayerTarget.value = rsvp;
    rsvpPlayerFaction.value = null;
    rsvpPlayerDialogOpen.value = true;
};

const confirmAddRsvpAsPlayer = async () => {
    const rsvp = rsvpPlayerTarget.value;
    if (!rsvp || !rsvpPlayerFaction.value) return;
    if (await doAction(route('tournaments.players.add', props.tournament.uuid), 'POST', {
        display_name: rsvp.user?.name ?? 'Unknown',
        user_id: rsvp.user_id,
        faction: rsvpPlayerFaction.value,
    })) {
        rsvpPlayerDialogOpen.value = false;
        rsvpPlayerTarget.value = null;
        reloadPage();
    }
};


// ─── Players ───
const newPlayerName = ref('');
const newPlayerFaction = ref<string | null>(null);

const addPlayer = async () => {
    if (!newPlayerName.value.trim() || !newPlayerFaction.value) return;
    submitting.value = true;
    const ok = await doAction(route('tournaments.players.add', props.tournament.uuid), 'POST', {
        display_name: newPlayerName.value.trim(),
        faction: newPlayerFaction.value,
    });
    submitting.value = false;
    if (ok) {
        newPlayerName.value = '';
        newPlayerFaction.value = null;
        reloadPage();
    }
};

const removePlayer = async (playerId: number) => {
    if (await doAction(route('tournaments.players.remove', { tournament: props.tournament.uuid, player: playerId }), 'DELETE')) {
        reloadPage();
    }
};

const toggleRinger = async (player: TournamentPlayer) => {
    if (await doAction(route('tournaments.players.update', { tournament: props.tournament.uuid, player: player.id }), 'PUT', { is_ringer: !player.is_ringer })) {
        reloadPage();
    }
};

const toggleDrop = async (player: TournamentPlayer) => {
    // Use the highest completed round number, not total rounds created
    const completedRounds = props.tournament.rounds.filter((r: any) => r.status === 'completed');
    const lastCompletedRound = completedRounds.length > 0
        ? Math.max(...completedRounds.map((r: any) => r.round_number))
        : 0;
    if (await doAction(route('tournaments.players.update', { tournament: props.tournament.uuid, player: player.id }), 'PUT', {
        dropped_after_round: player.dropped_after_round !== null ? null : lastCompletedRound,
    })) {
        reloadPage();
    }
};

// ─── Organizers ───
const sharedPage = usePage<SharedData>();
const currentUserId = computed(() => sharedPage.props.auth.user?.id ?? null);
const isCreator = computed(() => currentUserId.value === props.tournament.creator_id);

const addOrganizerDialogOpen = ref(false);
const addOrganizerQuery = ref('');
const addOrganizerResults = ref<{ id: number; name: string }[]>([]);
const addOrganizerSearching = ref(false);
let addOrganizerSearchTimer: ReturnType<typeof setTimeout> | null = null;

const openAddOrganizerDialog = () => {
    addOrganizerQuery.value = '';
    addOrganizerResults.value = [];
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
    if (await doAction(route('tournaments.organizers.add', props.tournament.uuid), 'POST', { user_id: userId })) {
        addOrganizerDialogOpen.value = false;
        reloadPage();
    }
};

const removeOrganizer = async (userId: number) => {
    if (userId === props.tournament.creator_id) return;
    if (await doAction(route('tournaments.organizers.remove', { tournament: props.tournament.uuid, userId }), 'DELETE')) {
        reloadPage();
    }
};

// ─── Link manually-added player to a BiggerHat user ───
const linkUserDialogOpen = ref(false);
const linkUserTarget = ref<TournamentPlayer | null>(null);
const linkUserQuery = ref('');
const linkUserResults = ref<{ id: number; name: string }[]>([]);
const linkUserSearching = ref(false);
let linkUserSearchTimer: ReturnType<typeof setTimeout> | null = null;

const openLinkUserDialog = (player: TournamentPlayer) => {
    linkUserTarget.value = player;
    linkUserQuery.value = '';
    linkUserResults.value = [];
    linkUserDialogOpen.value = true;
};

watch(linkUserQuery, (q) => {
    if (linkUserSearchTimer) clearTimeout(linkUserSearchTimer);
    if (!q || q.trim().length < 2) {
        linkUserResults.value = [];
        return;
    }
    linkUserSearchTimer = setTimeout(async () => {
        linkUserSearching.value = true;
        try {
            const res = await fetch(route('tournaments.users.search', props.tournament.uuid) + '?q=' + encodeURIComponent(q.trim()), {
                headers: { Accept: 'application/json' },
            });
            if (res.ok) {
                const data = await res.json();
                linkUserResults.value = data.users ?? [];
            }
        } finally {
            linkUserSearching.value = false;
        }
    }, 250);
});

const linkUserToPlayer = async (userId: number) => {
    if (!linkUserTarget.value) return;
    if (await doAction(route('tournaments.players.update', { tournament: props.tournament.uuid, player: linkUserTarget.value.id }), 'PUT', { user_id: userId })) {
        linkUserDialogOpen.value = false;
        linkUserTarget.value = null;
        reloadPage();
    }
};

const unlinkUserFromPlayer = async (player: TournamentPlayer) => {
    if (await doAction(route('tournaments.players.update', { tournament: props.tournament.uuid, player: player.id }), 'PUT', { user_id: null })) {
        reloadPage();
    }
};

const toggleDQ = async (player: TournamentPlayer) => {
    if (await doAction(route('tournaments.players.update', { tournament: props.tournament.uuid, player: player.id }), 'PUT', { is_disqualified: !player.is_disqualified })) {
        reloadPage();
    }
};

// ─── Rounds ───
const createRound = async () => {
    if (await doAction(route('tournaments.rounds.create', props.tournament.uuid))) {
        reloadPage();
    }
};

// Forfeit dialog
const forfeitDialogOpen = ref(false);
const forfeitGame = ref<TournamentGame | null>(null);
const openForfeitDialog = (game: TournamentGame) => {
    forfeitGame.value = game;
    forfeitDialogOpen.value = true;
};
const submitForfeit = async (loserPlayerId: number) => {
    if (!forfeitGame.value) return;
    if (await doAction(route('tournaments.games.forfeit', { tournament: props.tournament.uuid, game: forfeitGame.value.id }), 'POST', { forfeit_player_id: loserPlayerId })) {
        forfeitDialogOpen.value = false;
        forfeitGame.value = null;
        reloadPage();
    }
};
const removeForfeit = async (game: TournamentGame) => {
    if (await doAction(route('tournaments.games.forfeit', { tournament: props.tournament.uuid, game: game.id }), 'POST', {})) {
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

// AutoPair availability check
const canAutoPair = (round: TournamentRound): boolean => pairBlockReason(round) === null;

// Returns null if pairing is allowed, otherwise a human-readable reason.
const pairBlockReason = (round: TournamentRound): string | null => {
    if (round.status !== 'setup') return 'Round is not in setup';
    if (props.tournament.status !== 'active') {
        return round.round_number === 1
            ? 'Tournament must be started before Round 1 can be paired'
            : 'Tournament must be active to pair rounds';
    }
    if (round.round_number > 1) {
        const prevRound = props.tournament.rounds.find((r: any) => r.round_number === round.round_number - 1);
        if (!prevRound || prevRound.status !== 'completed') {
            return `Round ${round.round_number - 1} must be completed first`;
        }
    }
    return null;
};

const generatePairings = async (roundId: number) => {
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
const editingGame = ref<TournamentGame | null>(null);
const editP1Master = ref<string | null>(null);
const editP1Title = ref<string | null>(null);
const editP1StrategyVp = ref(0);
const editP1SchemeVp = ref(0);
const editP2Master = ref<string | null>(null);
const editP2Title = ref<string | null>(null);
const editP2StrategyVp = ref(0);
const editP2SchemeVp = ref(0);

const editP1Vp = computed(() => editP1StrategyVp.value + editP1SchemeVp.value);
const editP2Vp = computed(() => editP2StrategyVp.value + editP2SchemeVp.value);

const editP1Faction = computed(() => editingGame.value?.player_one_faction ?? props.tournament.players.find((p) => p.id === editingGame.value?.player_one_id)?.faction ?? null);
const editP2Faction = computed(() => editingGame.value?.player_two_faction ?? props.tournament.players.find((p) => p.id === editingGame.value?.player_two_id)?.faction ?? null);

const openScoreEdit = (game: TournamentGame) => {
    editingGame.value = game;
    editP1Master.value = game.player_one_master;
    editP1Title.value = game.player_one_title;
    editP1StrategyVp.value = game.player_one_strategy_vp ?? 0;
    editP1SchemeVp.value = game.player_one_scheme_vp ?? 0;
    editP2Master.value = game.player_two_master;
    editP2Title.value = game.player_two_title;
    editP2StrategyVp.value = game.player_two_strategy_vp ?? 0;
    editP2SchemeVp.value = game.player_two_scheme_vp ?? 0;
};

const saveScore = async () => {
    if (!editingGame.value) return;
    submitting.value = true;
    // Total VP is recomputed server-side from strategy + scheme.
    const ok = await doAction(route('tournaments.games.update', { tournament: props.tournament.uuid, game: editingGame.value.id }), 'PUT', {
        player_one_master: editP1Master.value,
        player_one_title: editP1Title.value,
        player_one_strategy_vp: editP1StrategyVp.value,
        player_one_scheme_vp: editP1SchemeVp.value,
        player_two_master: editP2Master.value,
        player_two_title: editP2Title.value,
        player_two_strategy_vp: editP2StrategyVp.value,
        player_two_scheme_vp: editP2SchemeVp.value,
    });
    submitting.value = false;
    if (ok) {
        editingGame.value = null;
        reloadPage();
    }
};

// ─── Round Scenario (inline editing) ───
const expandedScenarioRoundId = ref<number | null>(null);
const editDeployment = ref<string | null>(null);
const editStrategy = ref<string | null>(null);
const editSchemePool = ref<(string | null)[]>([null, null, null]);

const toggleScenarioEdit = (round: TournamentRound) => {
    if (expandedScenarioRoundId.value === round.id) {
        expandedScenarioRoundId.value = null;
        return;
    }
    expandedScenarioRoundId.value = round.id;
    editDeployment.value = round.deployment;
    editStrategy.value = round.strategy ? String(round.strategy.id) : null;
    editSchemePool.value = round.scheme_pool ? round.scheme_pool.map(String) : [null, null, null];
};

const saveRoundScenario = async (roundId: number) => {
    submitting.value = true;
    const ok = await doAction(route('tournaments.rounds.update', { tournament: props.tournament.uuid, round: roundId }), 'PUT', {
        deployment: editDeployment.value,
        strategy_id: editStrategy.value ? Number(editStrategy.value) : null,
        scheme_pool: editSchemePool.value.filter(Boolean).map(Number),
    });
    submitting.value = false;
    if (ok) {
        expandedScenarioRoundId.value = null;
        reloadPage();
    }
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
const editingSettings = ref(false);
const editName = ref('');
const editDescription = ref('');
const editEventDate = ref('');
const editLocation = ref('');
const editEncounterSize = ref(50);
const editEncounterType = ref('traditional');
const editPlannedRounds = ref(3);
const editRoundTimeLimit = ref(135);

const openSettingsEdit = () => {
    editName.value = props.tournament.name;
    editDescription.value = props.tournament.description ?? '';
    editEventDate.value = toDateInput(props.tournament.event_date);
    editLocation.value = props.tournament.location ?? '';
    editEncounterSize.value = props.tournament.encounter_size;
    editEncounterType.value = props.tournament.encounter_type ?? 'traditional';
    editPlannedRounds.value = props.tournament.planned_rounds;
    editRoundTimeLimit.value = props.tournament.round_time_limit;
    editingSettings.value = true;
};

const saveSettings = async () => {
    submitting.value = true;
    const ok = await doAction(route('tournaments.update', props.tournament.uuid), 'PUT', {
        name: editName.value,
        description: editDescription.value || null,
        event_date: editEventDate.value,
        location: editLocation.value || null,
        encounter_size: editEncounterSize.value,
        encounter_type: editEncounterType.value,
        planned_rounds: editPlannedRounds.value,
        round_time_limit: editRoundTimeLimit.value,
    });
    submitting.value = false;
    if (ok) {
        editingSettings.value = false;
        reloadPage();
    }
};

// ─── Randomize Scenario ───
const randomizeInDialog = () => {
    // Randomize locally in the form fields
    const strats = props.all_strategies;
    const schemes = props.all_schemes;
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

const viewStrategy = (idStr: string | null) => {
    if (!idStr) return;
    const s = props.all_strategies.find((x) => String(x.id) === idStr);
    if (s) openViewDrawer(s.name, s.image_url);
};

const viewScheme = (idStr: string | null) => {
    if (!idStr) return;
    const s = props.all_schemes.find((x) => String(x.id) === idStr);
    if (s) {
        const desc = [s.prerequisite ? `Prerequisite: ${s.prerequisite}` : '', s.reveal ? `Reveal: ${s.reveal}` : '', s.scoring ? `Scoring: ${s.scoring}` : ''].filter(Boolean).join('\n\n');
        openViewDrawer(s.name, s.image_url, desc || undefined);
    }
};

const activePlayers = computed(() => props.tournament.players.filter((p) => !p.is_disqualified && p.dropped_after_round === null));

const latestRound = computed(() => props.tournament.rounds.length > 0 ? props.tournament.rounds[props.tournament.rounds.length - 1] : null);

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
const formatDate = (d: string) => {
    const date = d.includes('T') ? new Date(d) : new Date(d + 'T00:00:00');
    return date.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
};
const toDateInput = (d: string) => d ? d.split('T')[0] : '';
const isEditable = computed(() => !isLocked.value);

// Masters filtered by faction
const mastersForFaction = (faction: string | null) => {
    if (!faction) return [];
    return props.masters.filter((m) => m.faction === faction || m.second_faction === faction);
};

const titlesForMaster = (masterName: string | null) => {
    if (!masterName) return [];
    const master = props.masters.find((m) => m.name === masterName);
    return master?.titles ?? [];
};

</script>

<template>
    <Head :title="tournament.name" />
    <div class="relative">
        <div
            class="pointer-events-none absolute inset-x-0 top-0 h-64 opacity-[0.07] dark:opacity-[0.12]"
            :style="{ background: 'radial-gradient(ellipse at top, hsl(var(--primary)) 0%, transparent 70%)' }"
        />

        <!-- Action error banner -->
        <div v-if="actionError" class="container mx-auto mb-2 px-4">
            <div class="flex items-center justify-between rounded-lg border border-destructive/40 bg-destructive/10 px-4 py-2 text-sm text-destructive">
                <span>{{ actionError }}</span>
                <button class="ml-2 text-xs hover:underline" @click="actionError = null">Dismiss</button>
            </div>
        </div>

        <PageBanner :title="tournament.name" class="mb-2">
            <template #subtitle>
                <div class="my-auto flex flex-wrap items-center gap-2 px-2 py-0 text-xs text-muted-foreground md:py-2 md:text-sm">
                    <Badge :class="['border-0 text-[10px]', statusColor(tournament.status)]" variant="outline">{{ statusLabel(tournament.status) }}</Badge>
                    <span class="flex items-center gap-1"><CalendarDays class="size-3" /> {{ formatDate(tournament.event_date) }}</span>
                    <span v-if="tournament.location" class="flex items-center gap-1"><MapPin class="size-3" /> {{ tournament.location }}</span>
                    <span>{{ tournament.encounter_size }}ss {{ encounterTypeLabel }}</span>
                    <span>{{ tournament.planned_rounds }} rounds</span>
                    <span>{{ tournament.season_label }}</span>
                </div>
            </template>
        </PageBanner>

        <div class="container mx-auto sm:px-4">
            <div class="mb-4 flex items-center justify-between">
                <Button variant="ghost" class="gap-1.5 text-sm" @click="router.get(route('tournaments.index'))">
                    <ArrowLeft class="size-4" /> Back
                </Button>
                <div class="flex items-center gap-2">
                    <Button variant="outline" size="sm" class="gap-1.5 text-xs" @click="copyPublicLink">
                        <Copy class="size-3" />
                        {{ linkCopied ? 'Copied!' : 'Share' }}
                    </Button>
                    <Button variant="outline" size="sm" class="gap-1 text-xs" @click="qrOpen = true">
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
                    <Button
                        v-if="statusTransitions[tournament.status]"
                        size="sm"
                        @click="confirmStatusDialog = true"
                    >
                        {{ statusTransitions[tournament.status].label }}
                    </Button>
                </div>
            </div>

            <Tabs v-model="activeTab" default-value="standings">
                <TabsList class="mb-4 flex w-full flex-wrap">
                    <TabsTrigger value="standings" class="gap-1 text-xs sm:text-sm">Standings</TabsTrigger>
                    <TabsTrigger value="players" class="gap-1 text-xs sm:text-sm">
                        Players
                        <Badge v-if="tournament.players.length" variant="secondary" class="ml-1 px-1 py-0 text-[9px]">{{ tournament.players.length }}</Badge>
                    </TabsTrigger>
                    <TabsTrigger v-if="tournament.status === 'draft' || (tournament as any).rsvps?.length" value="rsvps" class="gap-1 text-xs sm:text-sm">
                        RSVPs
                        <Badge v-if="(tournament as any).rsvps?.length" variant="secondary" class="ml-1 px-1 py-0 text-[9px]">{{ (tournament as any).rsvps.length }}</Badge>
                    </TabsTrigger>
                    <TabsTrigger value="rounds" class="gap-1 text-xs sm:text-sm">
                        Rounds
                        <Badge v-if="tournament.rounds.length" variant="secondary" class="ml-1 px-1 py-0 text-[9px]">{{ tournament.rounds.length }}</Badge>
                    </TabsTrigger>
                    <TabsTrigger value="settings" class="text-xs sm:text-sm">Settings</TabsTrigger>
                </TabsList>

                <!-- ═══ STANDINGS ═══ -->
                <TabsContent value="standings">
                    <Card>
                        <CardContent class="p-0">
                            <div class="overflow-x-auto">
                                <table class="w-full text-sm">
                                    <thead>
                                        <tr class="border-b text-left text-xs text-muted-foreground">
                                            <th class="px-2 py-2 font-medium sm:px-3">#</th>
                                            <th class="px-2 py-2 font-medium sm:px-3">Player</th>
                                            <th class="px-1 py-2 text-center font-medium sm:px-3">TP</th>
                                            <th class="px-1 py-2 text-center font-medium sm:px-3">DIFF</th>
                                            <th class="px-1 py-2 text-center font-medium sm:px-3">VP</th>
                                            <th class="hidden px-3 py-2 text-center font-medium sm:table-cell">Played</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-if="!standings.length">
                                            <td colspan="6" class="px-3 py-8 text-center text-muted-foreground">No results yet</td>
                                        </tr>
                                        <tr
                                            v-for="entry in standings"
                                            :key="entry.player_id"
                                            :class="[
                                                factionBackground(entry.faction),
                                                entry.faction ? 'text-white' : 'border-b last:border-0',
                                                entry.is_ringer ? 'opacity-50' : '',
                                            ]"
                                        >
                                            <td class="px-2 py-2 font-bold sm:px-3">{{ entry.rank ?? '-' }}</td>
                                            <td class="px-2 py-2 sm:px-3">
                                                <div class="flex items-center gap-1.5">
                                                    <FactionLogo v-if="entry.faction" :faction="entry.faction" class-name="size-4 shrink-0 drop-shadow-[0_1px_2px_rgba(0,0,0,0.5)]" />
                                                    <Crown v-if="entry.rank === 1 && !entry.is_ringer" class="size-3.5 shrink-0 text-amber-300 drop-shadow-[0_1px_2px_rgba(0,0,0,0.5)]" />
                                                    <span class="truncate text-xs font-medium sm:text-sm">{{ entry.display_name }}</span>
                                                    <Badge v-if="entry.is_ringer" variant="outline" class="hidden shrink-0 border-white/40 px-1 py-0 text-[9px] text-white/80 sm:inline-flex">Ringer</Badge>
                                                    <Badge v-if="entry.is_dropped" variant="outline" class="hidden shrink-0 border-white/40 px-1 py-0 text-[9px] text-white/60 sm:inline-flex">Dropped</Badge>
                                                </div>
                                            </td>
                                            <td class="px-1 py-2 text-center font-bold sm:px-3">{{ entry.total_tp }}</td>
                                            <td class="px-1 py-2 text-center font-medium sm:px-3">
                                                {{ entry.total_diff > 0 ? '+' : '' }}{{ entry.total_diff }}
                                            </td>
                                            <td class="px-1 py-2 text-center sm:px-3">{{ entry.total_vp }}</td>
                                            <td class="hidden px-3 py-2 text-center opacity-70 sm:table-cell">{{ entry.rounds_played }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </CardContent>
                    </Card>
                </TabsContent>

                <!-- ═══ RSVPs ═══ -->
                <TabsContent value="rsvps">
                    <div v-if="!(tournament as any).rsvps?.length" class="py-8 text-center text-sm text-muted-foreground">
                        No RSVPs yet. Share the tournament link so players can express interest.
                    </div>
                    <template v-else>
                        <div class="mb-4">
                            <h3 class="text-sm font-semibold">{{ (tournament as any).rsvps.length }} RSVPs</h3>
                        </div>
                        <div class="space-y-1.5">
                            <div
                                v-for="rsvp in (tournament as any).rsvps"
                                :key="rsvp.id"
                                class="flex items-center justify-between gap-2 rounded-lg border px-3 py-2"
                            >
                                <span class="text-xs font-medium sm:text-sm">{{ rsvp.user?.name ?? 'Unknown User' }}</span>
                                <div class="flex items-center gap-1">
                                    <Button
                                        v-if="tournament.status === 'registration' && !tournament.players.some((p: any) => p.user?.id === rsvp.user_id)"
                                        variant="outline"
                                        size="sm"
                                        class="h-6 gap-1 px-2 text-[10px]"
                                        @click="openRsvpPlayerDialog(rsvp)"
                                    >
                                        <Plus class="size-2.5" /> Register
                                    </Button>
                                    <Badge v-else-if="tournament.players.some((p: any) => p.user?.id === rsvp.user_id)" variant="outline" class="px-1 py-0 text-[9px]">Registered</Badge>
                                </div>
                            </div>
                        </div>
                    </template>
                </TabsContent>

                <!-- ═══ PLAYERS ═══ -->
                <TabsContent value="players">
                    <!-- Add Player Form -->
                    <Card class="mb-4">
                        <CardContent class="p-3 sm:p-4">
                            <div class="grid gap-2 sm:grid-cols-[1fr_auto_auto]  sm:items-end">
                                <div class="space-y-1">
                                    <Label class="text-xs">Player Name</Label>
                                    <Input v-model="newPlayerName" placeholder="Enter player name..." class="h-9 text-sm" @keydown.enter="addPlayer" />
                                </div>
                                <div class="space-y-1">
                                    <Label class="text-xs">Faction</Label>
                                    <Select v-model="newPlayerFaction">
                                        <SelectTrigger class="h-9 w-full text-xs sm:w-40"><SelectValue placeholder="Select faction..." /></SelectTrigger>
                                        <SelectContent>
                                            <SelectItem v-for="(f, key) in factions" :key="key" :value="(key as string)">
                                                <span class="flex items-center gap-1.5"><img :src="f.logo" class="size-4" /> {{ f.name }}</span>
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <Button size="sm" class="h-9 shrink-0 gap-1" :disabled="!newPlayerName.trim() || !newPlayerFaction || submitting" @click="addPlayer">
                                    <Plus class="size-3.5" /> Add
                                </Button>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Player Count -->
                    <div class="mb-3 flex items-center gap-2 text-sm text-muted-foreground">
                        <Users class="size-4" />
                        {{ activePlayers.length }} active players
                        <span v-if="tournament.players.length !== activePlayers.length">({{ tournament.players.length }} total)</span>
                    </div>

                    <!-- Player List -->
                    <div class="space-y-1">
                        <div
                            v-for="player in tournament.players"
                            :key="player.id"
                            :class="[
                                factionBackground(player.faction),
                                player.faction ? 'text-white' : 'border',
                                player.is_disqualified ? 'opacity-40 line-through' : player.dropped_after_round !== null ? 'opacity-60' : '',
                            ]"
                            class="flex items-start justify-between gap-2 rounded-lg px-2 py-2 sm:px-3"
                        >
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-1.5">
                                    <FactionLogo v-if="player.faction" :faction="player.faction" class-name="size-4 shrink-0 drop-shadow-[0_1px_2px_rgba(0,0,0,0.5)]" />
                                    <span class="truncate text-xs font-medium sm:text-sm">{{ player.display_name }}</span>
                                    <Badge v-if="player.is_ringer" variant="outline" class="hidden shrink-0 border-white/40 px-1 py-0 text-[9px] text-white/80 sm:inline-flex">Ringer</Badge>
                                    <Star v-if="player.is_ringer" class="size-3 shrink-0 fill-amber-300 text-amber-300 sm:hidden" />
                                    <Badge v-if="player.is_disqualified" class="shrink-0 bg-red-900/60 px-1 py-0 text-[9px] text-white">DQ</Badge>
                                    <Badge v-if="player.dropped_after_round !== null" class="shrink-0 bg-black/30 px-1 py-0 text-[9px] text-white/80">Dropped R{{ player.dropped_after_round }}</Badge>
                                </div>
                                <div v-if="playerOpponents[player.id]?.length" class="mt-0.5 pl-5 text-[9px] opacity-70">
                                    vs: {{ playerOpponents[player.id].join(' · ') }}
                                </div>
                            </div>
                            <div class="flex shrink-0 items-center gap-0.5">
                                <button
                                    v-if="!player.user"
                                    class="rounded p-1 hover:bg-white/20"
                                    title="Link to BiggerHat account"
                                    @click="openLinkUserDialog(player)"
                                >
                                    <Link2 class="size-3.5 text-white/50" />
                                </button>
                                <button
                                    v-else
                                    class="rounded p-1 hover:bg-white/20"
                                    :title="`Linked to ${player.user.name} — click to unlink`"
                                    @click="unlinkUserFromPlayer(player)"
                                >
                                    <Link2Off class="size-3.5 text-emerald-300" />
                                </button>
                                <button v-if="tournament.status === 'active'" class="rounded p-1 hover:bg-white/20" :title="player.dropped_after_round !== null ? 'Undrop' : 'Drop'" @click="toggleDrop(player)">
                                    <LogOut class="size-3.5" :class="player.dropped_after_round !== null ? 'text-amber-300' : 'text-white/50'" />
                                </button>
                                <button class="rounded p-1 hover:bg-white/20" title="Toggle Ringer" @click="toggleRinger(player)">
                                    <Star class="size-3.5" :class="player.is_ringer ? 'fill-amber-300 text-amber-300' : 'text-white/50'" />
                                </button>
                                <button class="rounded p-1 hover:bg-white/20" title="Disqualify" @click="toggleDQ(player)">
                                    <Skull class="size-3.5" :class="player.is_disqualified ? 'text-red-300' : 'text-white/50'" />
                                </button>
                                <button class="rounded p-1 hover:bg-white/20" title="Remove" @click="removePlayer(player.id)">
                                    <X class="size-3.5 text-white/50" />
                                </button>
                            </div>
                        </div>
                    </div>
                </TabsContent>

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
                                        <h3 class="text-sm font-semibold sm:text-base">Round {{ round.round_number }}</h3>
                                        <Badge
                                            :class="['border-0 text-[10px]', round.status === 'completed' ? 'bg-muted text-muted-foreground' : round.status === 'in_progress' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : round.games.length ? 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200' : 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200']"
                                            variant="outline"
                                        >
                                            {{ round.status === 'in_progress' ? 'In Progress' : round.status === 'completed' ? 'Completed' : round.games.length ? 'Paired' : 'Setup' }}
                                        </Badge>
                                        <span v-if="round.strategy" class="hidden text-xs text-muted-foreground sm:inline">{{ round.strategy.name }}</span>
                                    </div>
                                    <div class="flex items-center gap-1">
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        class="h-7 px-2 text-[11px] sm:text-xs"
                                        @click="toggleScenarioEdit(round)"
                                    >
                                        {{ expandedScenarioRoundId === round.id ? 'Hide Scenario' : round.strategy || round.deployment ? 'Scenario' : 'Set Scenario' }}
                                    </Button>
                                    <Button
                                        v-if="round.status === 'setup'"
                                        variant="outline"
                                        size="sm"
                                        class="h-7 gap-1 px-2 text-[11px] sm:text-xs"
                                        :disabled="submitting || !canAutoPair(round)"
                                        :title="pairBlockReason(round) ?? 'Generate Swiss pairings'"
                                        @click="generatePairings(round.id)"
                                    >
                                        <Loader2 v-if="submitting" class="size-3 animate-spin" />
                                        {{ round.games.length ? 'Re-Pair' : 'Auto Pair' }}
                                    </Button>
                                    <Button
                                        v-if="canStartRound(round)"
                                        size="sm"
                                        class="h-7 gap-1 px-2 text-[11px] sm:text-xs"
                                        :disabled="submitting"
                                        @click="startRound(round.id)"
                                    >
                                        <Loader2 v-if="submitting" class="size-3 animate-spin" />
                                        Start Round
                                    </Button>
                                    <Button
                                        v-if="round.status === 'in_progress'"
                                        size="sm"
                                        class="h-7 gap-1 px-2 text-[11px] sm:text-xs"
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
                                        <Button variant="outline" size="sm" class="h-6 gap-1 px-2 text-[10px]" @click="randomizeInDialog">
                                            <Dices class="size-3" /> Random
                                        </Button>
                                    </div>
                                    <div class="space-y-1.5">
                                        <div class="flex gap-1">
                                            <Select v-model="editDeployment">
                                                <SelectTrigger class="h-8 flex-1 text-xs"><SelectValue placeholder="Deployment..." /></SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem v-for="d in all_deployments" :key="d.value" :value="d.value">{{ d.label }}</SelectItem>
                                                </SelectContent>
                                            </Select>
                                            <button v-if="editDeployment" class="shrink-0 rounded-md border px-2 text-muted-foreground hover:bg-accent hover:text-foreground" @click="viewDeployment(editDeployment)">
                                                <Eye class="size-3" />
                                            </button>
                                        </div>
                                        <div class="flex gap-1">
                                            <Select v-model="editStrategy">
                                                <SelectTrigger class="h-8 flex-1 text-xs"><SelectValue placeholder="Strategy..." /></SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem v-for="s in all_strategies" :key="s.id" :value="String(s.id)">{{ s.name }}</SelectItem>
                                                </SelectContent>
                                            </Select>
                                            <button v-if="editStrategy" class="shrink-0 rounded-md border px-2 text-muted-foreground hover:bg-accent hover:text-foreground" @click="viewStrategy(editStrategy)">
                                                <Eye class="size-3" />
                                            </button>
                                        </div>
                                        <div v-for="(_, idx) in editSchemePool" :key="'rs-' + idx" class="flex gap-1">
                                            <Select :model-value="editSchemePool[idx] ?? undefined" @update:model-value="(v) => editSchemePool[idx] = v ?? null">
                                                <SelectTrigger class="h-8 flex-1 text-xs"><SelectValue :placeholder="'Scheme ' + (idx + 1)" /></SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem v-for="s in all_schemes" :key="s.id" :value="String(s.id)">{{ s.name }}</SelectItem>
                                                </SelectContent>
                                            </Select>
                                            <button v-if="editSchemePool[idx]" class="shrink-0 rounded-md border px-2 text-muted-foreground hover:bg-accent hover:text-foreground" @click="viewScheme(editSchemePool[idx])">
                                                <Eye class="size-3" />
                                            </button>
                                        </div>
                                    </div>
                                    <Button size="sm" class="w-full text-xs" :disabled="submitting" @click="saveRoundScenario(round.id)">
                                        <Loader2 v-if="submitting" class="mr-1.5 size-3 animate-spin" />
                                        Save Scenario
                                    </Button>
                                </div>
                                <!-- Scenario summary (when not editing) -->
                                <div v-else-if="round.strategy || round.deployment" class="mb-3 flex flex-wrap gap-x-3 gap-y-0.5 text-[11px] text-muted-foreground">
                                    <span v-if="round.deployment">{{ all_deployments.find((d) => d.value === round.deployment)?.label }}</span>
                                    <span v-if="round.strategy">{{ round.strategy.name }}</span>
                                    <span v-if="round.scheme_pool?.length">{{ round.scheme_pool.map((id: number) => all_schemes.find((s) => s.id === id)?.name ?? id).join(' / ') }}</span>
                                </div>

                                <div v-if="!round.games.length" class="py-4 text-center text-xs text-muted-foreground">No games in this round yet. Generate pairings or add them manually.</div>

                                <div v-else class="space-y-1.5">
                                    <div
                                        v-for="game in round.games"
                                        :key="game.id"
                                        class="rounded-md border px-2 py-2 text-sm sm:px-3"
                                        :class="game.result === 'completed' || game.result === 'forfeited' ? '' : 'border-dashed'"
                                    >
                                        <div class="flex items-center justify-between gap-1">
                                            <div class="flex min-w-0 flex-1 items-center gap-1.5">
                                                <span v-if="game.table_number" class="shrink-0 text-[10px] text-muted-foreground">T{{ game.table_number }}</span>
                                                <FactionLogo v-if="game.player_one_faction || playerFaction(game.player_one_id)" :faction="(game.player_one_faction || playerFaction(game.player_one_id))!" class-name="size-3.5 shrink-0" />
                                                <span class="truncate text-xs font-medium sm:text-sm">{{ playerName(game.player_one_id) }}</span>
                                                <template v-if="!game.is_bye">
                                                    <span class="shrink-0 text-[10px] text-muted-foreground">vs</span>
                                                    <FactionLogo v-if="game.player_two_faction || playerFaction(game.player_two_id)" :faction="(game.player_two_faction || playerFaction(game.player_two_id))!" class-name="size-3.5 shrink-0" />
                                                    <span class="truncate text-xs font-medium sm:text-sm">{{ playerName(game.player_two_id) }}</span>
                                                </template>
                                                <Badge v-if="game.is_bye" variant="outline" class="shrink-0 px-1 py-0 text-[9px]">BYE</Badge>
                                                <Badge v-if="game.is_forfeit" variant="destructive" class="shrink-0 px-1 py-0 text-[9px]">Forfeit</Badge>
                                            </div>
                                            <div class="flex shrink-0 items-center gap-1 sm:gap-2">
                                                <template v-if="game.result === 'completed' || game.result === 'forfeited'">
                                                    <span class="text-xs font-bold sm:text-sm">{{ game.player_one_vp ?? '-' }}</span>
                                                    <span class="text-[10px] text-muted-foreground">-</span>
                                                    <span class="text-xs font-bold sm:text-sm">{{ game.player_two_vp ?? '-' }}</span>
                                                </template>
                                                <Button v-if="!game.is_bye && round.status === 'in_progress'" variant="ghost" size="sm" class="h-7 px-1.5 text-[11px] sm:px-2 sm:text-xs" @click="openScoreEdit(game)">
                                                    {{ game.result === 'pending' ? 'Score' : 'Edit' }}
                                                </Button>
                                                <Button v-if="!game.is_bye && !game.is_forfeit && game.player_two && round.status === 'in_progress'" variant="ghost" size="sm" class="h-7 px-1.5 text-[11px] text-destructive hover:text-destructive sm:px-2 sm:text-xs" @click="openForfeitDialog(game)">
                                                    Forfeit
                                                </Button>
                                                <Button v-if="game.is_forfeit && round.status === 'in_progress'" variant="ghost" size="sm" class="h-7 px-1.5 text-[11px] sm:px-2 sm:text-xs" @click="removeForfeit(game)">
                                                    Undo
                                                </Button>
                                                <button v-if="round.status === 'setup'" class="rounded p-0.5 text-muted-foreground hover:bg-destructive/10 hover:text-destructive" @click="removeGame(game.id)">
                                                    <X class="size-3" />
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Pending scores indicator -->
                                <div v-if="round.status === 'in_progress' && round.games.length > 0 && !roundAllScored(round)" class="mt-2 flex items-center gap-1.5 text-xs text-muted-foreground">
                                    <Loader2 class="size-3 animate-spin" />
                                    {{ round.games.filter((g: any) => g.result === 'pending').length }} game(s) pending scores
                                </div>

                                <!-- Manual Pairing -->
                                <div v-if="round.status === 'setup' && unpairedPlayers(round.id).length" class="mt-3 rounded-md border border-dashed p-2 sm:p-3">
                                    <div class="mb-2 flex items-center justify-between gap-2">
                                        <span class="text-xs font-medium text-muted-foreground">Manual Pairing</span>
                                        <span v-if="pairBlockReason(round)" class="text-[10px] italic text-amber-600 dark:text-amber-400">{{ pairBlockReason(round) }}</span>
                                    </div>
                                    <fieldset :disabled="!canAutoPair(round)" :class="!canAutoPair(round) ? 'cursor-not-allowed opacity-50' : ''">
                                        <div class="grid gap-2 sm:grid-cols-[1fr_1fr_auto] sm:items-end">
                                            <div class="space-y-1">
                                                <Label class="text-[10px]">Player 1</Label>
                                                <Select v-model="getManualPair(round.id).p1">
                                                    <SelectTrigger class="h-8 text-xs"><SelectValue placeholder="Select player..." /></SelectTrigger>
                                                    <SelectContent>
                                                        <SelectItem v-for="p in unpairedPlayers(round.id)" :key="p.id" :value="String(p.id)">{{ p.display_name }}</SelectItem>
                                                    </SelectContent>
                                                </Select>
                                            </div>
                                            <div class="space-y-1">
                                                <Label class="text-[10px]">Player 2 <span class="text-muted-foreground">(empty = bye)</span></Label>
                                                <Select v-model="getManualPair(round.id).p2">
                                                    <SelectTrigger class="h-8 text-xs"><SelectValue placeholder="BYE" /></SelectTrigger>
                                                    <SelectContent>
                                                        <SelectItem value="bye">BYE</SelectItem>
                                                        <SelectItem v-for="p in unpairedPlayers(round.id).filter((p) => String(p.id) !== getManualPair(round.id).p1)" :key="p.id" :value="String(p.id)">{{ p.display_name }}</SelectItem>
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
                                    <div v-if="unpairedPlayers(round.id).length" class="mt-2 text-[10px] text-muted-foreground">
                                        {{ unpairedPlayers(round.id).length }} unpaired: {{ unpairedPlayers(round.id).map((p) => p.display_name).join(', ') }}
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
                    <!-- Edit Mode -->
                    <Card v-if="editingSettings" class="mb-4">
                        <CardContent class="space-y-4 p-4">
                            <h3 class="font-semibold">Edit Tournament</h3>
                            <div class="space-y-3">
                                <div class="space-y-1">
                                    <Label class="text-xs">Name</Label>
                                    <Input v-model="editName" class="h-9 text-sm" />
                                </div>
                                <div class="space-y-1">
                                    <Label class="text-xs">Description</Label>
                                    <Input v-model="editDescription" class="h-9 text-sm" placeholder="Optional" />
                                </div>
                                <div class="grid gap-3 sm:grid-cols-2">
                                    <div class="space-y-1">
                                        <Label class="text-xs">Event Date</Label>
                                        <Input v-model="editEventDate" type="date" class="h-9 text-sm" />
                                    </div>
                                    <div class="space-y-1">
                                        <Label class="text-xs">Location</Label>
                                        <Input v-model="editLocation" class="h-9 text-sm" placeholder="Optional" />
                                    </div>
                                </div>
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
                                            <SelectTrigger class="h-8 text-xs"><SelectValue /></SelectTrigger>
                                            <SelectContent>
                                                <SelectItem v-for="et in encounter_types" :key="et.value" :value="et.value">{{ et.label }}</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div class="space-y-1">
                                        <Label class="text-xs">Planned Rounds</Label>
                                        <NumberField v-model="editPlannedRounds" :min="1" :max="7">
                                            <NumberFieldContent><NumberFieldDecrement /><NumberFieldInput /><NumberFieldIncrement /></NumberFieldContent>
                                        </NumberField>
                                    </div>
                                    <div class="space-y-1">
                                        <Label class="text-xs">Time Limit (min)</Label>
                                        <NumberField v-model="editRoundTimeLimit" :min="30" :max="300" :step="5">
                                            <NumberFieldContent><NumberFieldDecrement /><NumberFieldInput /><NumberFieldIncrement /></NumberFieldContent>
                                        </NumberField>
                                    </div>
                                </div>
                            </div>
                            <div class="flex gap-2">
                                <Button variant="outline" class="flex-1" @click="editingSettings = false">Cancel</Button>
                                <Button class="flex-1" :disabled="!editName || !editEventDate || submitting" @click="saveSettings">
                                    <Loader2 v-if="submitting" class="mr-2 size-4 animate-spin" />
                                    Save Changes
                                </Button>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- View Mode -->
                    <Card v-else>
                        <CardContent class="space-y-4 p-4">
                            <div class="flex items-center justify-between">
                                <h3 class="font-semibold">Tournament Details</h3>
                                <Button v-if="isEditable" variant="outline" size="sm" class="h-7 gap-1 text-xs" @click="openSettingsEdit">
                                    Edit
                                </Button>
                            </div>
                            <div class="space-y-3 text-sm">
                                <div class="flex justify-between">
                                    <span class="text-muted-foreground">Status</span>
                                    <Badge :class="['border-0 text-[10px]', statusColor(tournament.status)]" variant="outline">{{ statusLabel(tournament.status) }}</Badge>
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
                                    <span>{{ tournament.encounter_size }}ss {{ encounterTypeLabel }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-muted-foreground">Planned Rounds</span>
                                    <span>{{ tournament.planned_rounds }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-muted-foreground">Round Time Limit</span>
                                    <span>{{ tournament.round_time_limit }} min</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-muted-foreground">Season</span>
                                    <span>{{ tournament.season_label }}</span>
                                </div>
                            </div>

                            <div class="border-t pt-4">
                                <div class="mb-2 flex items-center justify-between">
                                    <h3 class="font-semibold">Organizers</h3>
                                    <Button
                                        v-if="isCreator"
                                        variant="outline"
                                        size="sm"
                                        class="h-7 gap-1 text-xs"
                                        @click="openAddOrganizerDialog"
                                    >
                                        <Plus class="size-3" /> Add TO
                                    </Button>
                                </div>
                                <div class="space-y-1">
                                    <div v-for="org in tournament.organizers" :key="org.id" class="flex items-center justify-between gap-2 rounded-md px-1 py-1 text-sm hover:bg-accent/50">
                                        <div class="flex items-center gap-2">
                                            <Shield class="size-3.5 text-muted-foreground" />
                                            <span>{{ org.name }}</span>
                                            <Badge v-if="org.id === tournament.creator_id" variant="outline" class="px-1 py-0 text-[9px]">Creator</Badge>
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

    <!-- Score Entry Dialog -->
    <Dialog :open="!!editingGame" @update:open="(v) => { if (!v) editingGame = null; }">
        <DialogContent class="max-w-md">
            <DialogHeader>
                <DialogTitle>Game Score</DialogTitle>
                <DialogDescription v-if="editingGame">
                    {{ playerName(editingGame.player_one_id) }} vs {{ playerName(editingGame.player_two_id) }}
                </DialogDescription>
            </DialogHeader>
            <div v-if="editingGame" class="space-y-4">
                <!-- Player 1 -->
                <div class="space-y-2 rounded-lg border p-3">
                    <div class="text-xs font-semibold">{{ playerName(editingGame.player_one_id) }}</div>
                    <div class="grid grid-cols-2 gap-2">
                        <div class="space-y-1">
                            <Label class="text-[10px]">Master</Label>
                            <Select v-model="editP1Master">
                                <SelectTrigger class="h-8 text-xs"><SelectValue placeholder="Select master..." /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="m in mastersForFaction(editP1Faction)" :key="m.name" :value="m.name">{{ m.name }}</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div class="space-y-1">
                            <Label class="text-[10px]">Title</Label>
                            <Select v-model="editP1Title" :disabled="!editP1Master">
                                <SelectTrigger class="h-8 text-xs"><SelectValue placeholder="Title..." /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="t in titlesForMaster(editP1Master)" :key="t.id" :value="t.display_name">{{ t.title || t.display_name }}</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div class="space-y-1">
                            <Label class="text-[10px]">Strategy VP</Label>
                            <Input v-model.number="editP1StrategyVp" type="number" min="0" max="15" class="h-8 text-center text-sm font-bold" />
                        </div>
                        <div class="space-y-1">
                            <Label class="text-[10px]">Scheme VP</Label>
                            <Input v-model.number="editP1SchemeVp" type="number" min="0" max="15" class="h-8 text-center text-sm font-bold" />
                        </div>
                    </div>
                    <div class="text-right text-xs text-muted-foreground">Total: <span class="font-bold text-foreground">{{ editP1Vp }}</span> VP</div>
                </div>

                <!-- Player 2 -->
                <div class="space-y-2 rounded-lg border p-3">
                    <div class="text-xs font-semibold">{{ playerName(editingGame.player_two_id) }}</div>
                    <div class="grid grid-cols-2 gap-2">
                        <div class="space-y-1">
                            <Label class="text-[10px]">Master</Label>
                            <Select v-model="editP2Master">
                                <SelectTrigger class="h-8 text-xs"><SelectValue placeholder="Select master..." /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="m in mastersForFaction(editP2Faction)" :key="m.name" :value="m.name">{{ m.name }}</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div class="space-y-1">
                            <Label class="text-[10px]">Title</Label>
                            <Select v-model="editP2Title" :disabled="!editP2Master">
                                <SelectTrigger class="h-8 text-xs"><SelectValue placeholder="Title..." /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="t in titlesForMaster(editP2Master)" :key="t.id" :value="t.display_name">{{ t.title || t.display_name }}</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div class="space-y-1">
                            <Label class="text-[10px]">Strategy VP</Label>
                            <Input v-model.number="editP2StrategyVp" type="number" min="0" max="15" class="h-8 text-center text-sm font-bold" />
                        </div>
                        <div class="space-y-1">
                            <Label class="text-[10px]">Scheme VP</Label>
                            <Input v-model.number="editP2SchemeVp" type="number" min="0" max="15" class="h-8 text-center text-sm font-bold" />
                        </div>
                    </div>
                    <div class="text-right text-xs text-muted-foreground">Total: <span class="font-bold text-foreground">{{ editP2Vp }}</span> VP</div>
                </div>

                <!-- Score summary -->
                <div class="flex items-center justify-center gap-3 text-lg font-bold">
                    <span>{{ editP1Vp }}</span>
                    <span class="text-sm text-muted-foreground">-</span>
                    <span>{{ editP2Vp }}</span>
                </div>
            </div>
            <DialogFooter class="gap-2 sm:gap-0">
                <Button variant="outline" @click="editingGame = null">Cancel</Button>
                <Button :disabled="submitting" @click="saveScore">
                    <Loader2 v-if="submitting" class="mr-2 size-4 animate-spin" />
                    Save Score
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

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
                <Button @click="finalizeTournament">
                    <Trophy class="mr-2 size-4" /> Complete Tournament
                </Button>
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
                    <FactionLogo v-if="forfeitGame.player_one_faction || playerFaction(forfeitGame.player_one_id)" :faction="(forfeitGame.player_one_faction || playerFaction(forfeitGame.player_one_id))!" class-name="size-4 shrink-0" />
                    <span class="font-medium">{{ playerName(forfeitGame.player_one_id) }}</span>
                    <span class="ml-auto text-xs text-muted-foreground">conceded</span>
                </button>
                <button
                    v-if="forfeitGame.player_two"
                    class="flex w-full items-center gap-2 rounded-lg border p-3 text-left text-sm transition-colors hover:bg-destructive/5"
                    @click="submitForfeit(forfeitGame!.player_two_id!)"
                >
                    <FactionLogo v-if="forfeitGame.player_two_faction || playerFaction(forfeitGame.player_two_id)" :faction="(forfeitGame.player_two_faction || playerFaction(forfeitGame.player_two_id))!" class-name="size-4 shrink-0" />
                    <span class="font-medium">{{ playerName(forfeitGame.player_two_id) }}</span>
                    <span class="ml-auto text-xs text-muted-foreground">conceded</span>
                </button>
            </div>
            <DialogFooter>
                <Button variant="outline" @click="forfeitDialogOpen = false">Cancel</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <!-- RSVP → Player Faction Dialog -->
    <Dialog v-model:open="rsvpPlayerDialogOpen">
        <DialogContent class="max-w-xs">
            <DialogHeader>
                <DialogTitle>Register {{ rsvpPlayerTarget?.user?.name ?? 'Player' }}</DialogTitle>
                <DialogDescription>Select a faction to register this player.</DialogDescription>
            </DialogHeader>
            <Select v-model="rsvpPlayerFaction">
                <SelectTrigger class="h-9 text-sm"><SelectValue placeholder="Select Faction" /></SelectTrigger>
                <SelectContent>
                    <SelectItem v-for="(f, slug) in factions" :key="slug" :value="slug">{{ f.name }}</SelectItem>
                </SelectContent>
            </Select>
            <DialogFooter>
                <Button variant="outline" @click="rsvpPlayerDialogOpen = false">Cancel</Button>
                <Button :disabled="!rsvpPlayerFaction || submitting" @click="confirmAddRsvpAsPlayer">Register</Button>
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
            <Input v-model="addOrganizerQuery" placeholder="Search users (min 2 chars)..." class="text-sm" />
            <div class="max-h-64 overflow-y-auto">
                <div v-if="addOrganizerSearching" class="py-2 text-center text-xs text-muted-foreground">Searching...</div>
                <div v-else-if="addOrganizerQuery.trim().length >= 2 && !addOrganizerResults.length" class="py-2 text-center text-xs text-muted-foreground">No users match (or already organizers).</div>
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
            <DialogFooter>
                <Button variant="outline" @click="addOrganizerDialogOpen = false">Cancel</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <!-- Link Player to BiggerHat User Dialog -->
    <Dialog v-model:open="linkUserDialogOpen">
        <DialogContent class="max-w-sm">
            <DialogHeader>
                <DialogTitle>Link {{ linkUserTarget?.display_name ?? 'Player' }} to a BiggerHat account</DialogTitle>
                <DialogDescription>Search by name. Linking lets the player see this tournament in their account.</DialogDescription>
            </DialogHeader>
            <Input v-model="linkUserQuery" placeholder="Search users (min 2 chars)..." class="text-sm" />
            <div class="max-h-64 overflow-y-auto">
                <div v-if="linkUserSearching" class="py-2 text-center text-xs text-muted-foreground">Searching...</div>
                <div v-else-if="linkUserQuery.trim().length >= 2 && !linkUserResults.length" class="py-2 text-center text-xs text-muted-foreground">No users match.</div>
                <button
                    v-for="u in linkUserResults"
                    :key="u.id"
                    class="flex w-full items-center justify-between rounded px-2 py-1.5 text-left text-sm hover:bg-accent"
                    @click="linkUserToPlayer(u.id)"
                >
                    <span>{{ u.name }}</span>
                    <Link2 class="size-3.5 text-muted-foreground" />
                </button>
            </div>
            <DialogFooter>
                <Button variant="outline" @click="linkUserDialogOpen = false">Cancel</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <!-- QR Code -->
    <QRCodeDialog v-if="qrOpen" v-model:open="qrOpen" :url="route('tournaments.view', tournament.uuid)" title="Tournament Link" />
</template>
