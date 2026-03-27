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
import { Switch } from '@/components/ui/switch';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Head, router } from '@inertiajs/vue3';
import { Drawer, DrawerClose, DrawerContent, DrawerFooter, DrawerHeader, DrawerTitle } from '@/components/ui/drawer';
import { ArrowLeft, CalendarDays, Copy, Crown, Dices, Eye, Loader2, LogOut, MapPin, Plus, QrCode, Shield, Skull, Star, Trophy, Users, X } from 'lucide-vue-next';
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

interface TournamentData {
    id: number;
    uuid: string;
    name: string;
    description: string | null;
    status: string;
    encounter_size: number;
    planned_rounds: number;
    season_label: string;
    event_date: string;
    location: string | null;
    round_time_limit: number;
    is_public: boolean;
    players: TournamentPlayer[];
    rounds: TournamentRound[];
    organizers: { id: number; name: string }[];
}

const props = defineProps<{
    tournament: TournamentData;
    standings: StandingEntry[];
    seasons: { value: string; label: string }[];
    factions: Record<string, FactionInfo>;
    masters: MasterOption[];
    all_strategies: { id: number; name: string; slug: string; image_url: string | null }[];
    all_schemes: { id: number; name: string; slug: string; image_url: string | null; prerequisite: string | null; reveal: string | null; scoring: string | null }[];
    all_deployments: { value: string; label: string; description: string | null; image_url: string | null }[];
}>();

const csrfToken = () => document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';

// Restore tab from URL hash
const initialTab = typeof window !== 'undefined' && window.location.hash ? window.location.hash.slice(1) : 'standings';
const activeTab = ref(['standings', 'players', 'rounds', 'settings'].includes(initialTab) ? initialTab : 'standings');

const linkCopied = ref(false);
const copyPublicLink = async () => {
    await navigator.clipboard.writeText(route('tournaments.view', props.tournament.uuid));
    linkCopied.value = true;
    setTimeout(() => (linkCopied.value = false), 2000);
};
const qrOpen = ref(false);

watch(activeTab, (tab) => { if (typeof window !== 'undefined') window.location.hash = tab; });

const reloadPage = () => {
    router.visit(route('tournaments.manage', props.tournament.uuid) + '#' + activeTab.value, { preserveScroll: true });
};
const submitting = ref(false);

// ─── Players ───
const newPlayerName = ref('');
const newPlayerFaction = ref<string | null>(null);

const addPlayer = async () => {
    if (!newPlayerName.value.trim()) return;
    submitting.value = true;
    try {
        const res = await fetch(route('tournaments.players.add', props.tournament.uuid), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken(), Accept: 'application/json' },
            body: JSON.stringify({ display_name: newPlayerName.value.trim(), faction: newPlayerFaction.value }),
        });
        if (res.ok) {
            newPlayerName.value = '';
            newPlayerFaction.value = null;
        } else {
            console.error('Failed to add player:', res.status, await res.text());
        }
    } catch (e) {
        console.error('Add player error:', e);
    } finally {
        submitting.value = false;
        reloadPage();
    }
};

const removePlayer = async (playerId: number) => {
    await fetch(route('tournaments.players.remove', { tournament: props.tournament.uuid, player: playerId }), {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': csrfToken() },
    });
    reloadPage();
};

const toggleRinger = async (player: TournamentPlayer) => {
    await fetch(route('tournaments.players.update', { tournament: props.tournament.uuid, player: player.id }), {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
        body: JSON.stringify({ is_ringer: !player.is_ringer }),
    });
    reloadPage();
};

const toggleDrop = async (player: TournamentPlayer) => {
    const currentRound = props.tournament.rounds.length || 0;
    await fetch(route('tournaments.players.update', { tournament: props.tournament.uuid, player: player.id }), {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken(), Accept: 'application/json' },
        body: JSON.stringify({ dropped_after_round: player.dropped_after_round !== null ? null : currentRound }),
    });
    reloadPage();
};

const toggleDQ = async (player: TournamentPlayer) => {
    await fetch(route('tournaments.players.update', { tournament: props.tournament.uuid, player: player.id }), {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
        body: JSON.stringify({ is_disqualified: !player.is_disqualified }),
    });
    reloadPage();
};

// ─── Rounds ───
const createRound = async () => {
    submitting.value = true;
    await fetch(route('tournaments.rounds.create', props.tournament.uuid), {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': csrfToken() },
    });
    submitting.value = false;
    reloadPage();
};

const generatePairings = async (roundId: number) => {
    submitting.value = true;
    try {
        const res = await fetch(route('tournaments.rounds.pair', { tournament: props.tournament.uuid, round: roundId }), {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken(), Accept: 'application/json' },
        });
        if (!res.ok) console.error('Pairing failed:', res.status, await res.text());
    } catch (e) {
        console.error('Pairing error:', e);
    } finally {
        submitting.value = false;
        reloadPage();
    }
};

// ─── Manual Pairing ───
const manualP1 = ref<string>('');
const manualP2 = ref<string>('');

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
    if (!manualP1.value) return;
    submitting.value = true;
    const isBye = !manualP2.value || manualP2.value === 'bye';
    const body: Record<string, unknown> = {
        player_one_id: Number(manualP1.value),
        player_two_id: isBye ? null : Number(manualP2.value),
        is_bye: isBye,
    };
    await fetch(route('tournaments.games.create', { tournament: props.tournament.uuid, round: roundId }), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
        body: JSON.stringify(body),
    });
    manualP1.value = '';
    manualP2.value = '';
    submitting.value = false;
    reloadPage();
};

const removeGame = async (gameId: number) => {
    await fetch(route('tournaments.games.delete', { tournament: props.tournament.uuid, game: gameId }), {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': csrfToken() },
    });
    reloadPage();
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
    try {
        const res = await fetch(route('tournaments.games.update', { tournament: props.tournament.uuid, game: editingGame.value.id }), {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken(), Accept: 'application/json' },
            body: JSON.stringify({
                player_one_master: editP1Master.value,
                player_one_title: editP1Title.value,
                player_one_vp: editP1Vp.value,
                player_one_strategy_vp: editP1StrategyVp.value,
                player_one_scheme_vp: editP1SchemeVp.value,
                player_two_master: editP2Master.value,
                player_two_title: editP2Title.value,
                player_two_vp: editP2Vp.value,
                player_two_strategy_vp: editP2StrategyVp.value,
                player_two_scheme_vp: editP2SchemeVp.value,
            }),
        });
        if (!res.ok) console.error('Save score failed:', await res.text());
    } catch (e) {
        console.error('Save score error:', e);
    }
    editingGame.value = null;
    submitting.value = false;
    reloadPage();
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
    await fetch(route('tournaments.rounds.update', { tournament: props.tournament.uuid, round: roundId }), {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken(), Accept: 'application/json' },
        body: JSON.stringify({
            deployment: editDeployment.value,
            strategy_id: editStrategy.value ? Number(editStrategy.value) : null,
            scheme_pool: editSchemePool.value.filter(Boolean).map(Number),
        }),
    });
    expandedScenarioRoundId.value = null;
    submitting.value = false;
    reloadPage();
};

// ─── Status ───
const statusTransitions: Record<string, { next: string; label: string }> = {
    draft: { next: 'registration', label: 'Open Registration' },
    registration: { next: 'active', label: 'Start Tournament' },
    active: { next: 'completed', label: 'Complete Tournament' },
};

const confirmStatusDialog = ref(false);
const confirmFinalizeDialog = ref(false);

const startNextRound = async () => {
    submitting.value = true;
    // Create the next round
    const res = await fetch(route('tournaments.rounds.create', props.tournament.uuid), {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': csrfToken(), Accept: 'application/json' },
    });
    if (res.ok) {
        const data = await res.json();
        // Auto-generate pairings for the new round
        await fetch(route('tournaments.rounds.pair', { tournament: props.tournament.uuid, round: data.round.id }), {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken(), Accept: 'application/json' },
        });
    }
    submitting.value = false;
    reloadPage();
};

const finalizeTournament = async () => {
    await fetch(route('tournaments.status', props.tournament.uuid), {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken(), Accept: 'application/json' },
        body: JSON.stringify({ status: 'completed' }),
    });
    confirmFinalizeDialog.value = false;
    reloadPage();
};

const advanceStatus = async () => {
    const transition = statusTransitions[props.tournament.status];
    if (!transition) return;
    await fetch(route('tournaments.status', props.tournament.uuid), {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
        body: JSON.stringify({ status: transition.next }),
    });
    confirmStatusDialog.value = false;
    reloadPage();
};

const statusColor = (status: string): string =>
    ({
        draft: 'bg-muted text-muted-foreground',
        registration: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
        active: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
        completed: 'bg-muted text-muted-foreground',
    })[status] ?? '';

const statusLabel = (status: string): string =>
    ({ draft: 'Draft', registration: 'Registration', active: 'Active', completed: 'Completed' })[status] ?? status;

// ─── Settings Edit ───
const editingSettings = ref(false);
const editName = ref('');
const editDescription = ref('');
const editEventDate = ref('');
const editLocation = ref('');
const editEncounterSize = ref(50);
const editPlannedRounds = ref(3);
const editRoundTimeLimit = ref(135);
const editIsPublic = ref(false);

const openSettingsEdit = () => {
    editName.value = props.tournament.name;
    editDescription.value = props.tournament.description ?? '';
    editEventDate.value = toDateInput(props.tournament.event_date);
    editLocation.value = props.tournament.location ?? '';
    editEncounterSize.value = props.tournament.encounter_size;
    editPlannedRounds.value = props.tournament.planned_rounds;
    editRoundTimeLimit.value = props.tournament.round_time_limit;
    editIsPublic.value = props.tournament.is_public;
    editingSettings.value = true;
};

const saveSettings = async () => {
    submitting.value = true;
    try {
        const res = await fetch(route('tournaments.update', props.tournament.uuid), {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken(), Accept: 'application/json' },
            body: JSON.stringify({
                name: editName.value,
                description: editDescription.value || null,
                event_date: editEventDate.value,
                location: editLocation.value || null,
                encounter_size: editEncounterSize.value,
                planned_rounds: editPlannedRounds.value,
                round_time_limit: editRoundTimeLimit.value,
                is_public: editIsPublic.value,
            }),
        });
        if (res.ok) {
            editingSettings.value = false;
        } else {
            console.error('Save settings failed:', res.status, await res.text());
        }
    } catch (e) {
        console.error('Save settings error:', e);
    } finally {
        submitting.value = false;
        reloadPage();
    }
};

// ─── Randomize Scenario ───
const randomizeRoundAndReload = async (roundId: number) => {
    submitting.value = true;
    await fetch(route('tournaments.rounds.randomize', { tournament: props.tournament.uuid, round: roundId }), {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': csrfToken(), Accept: 'application/json' },
    });
    submitting.value = false;
    reloadPage();
};

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

const viewStrategyById = (id: number) => {
    const s = props.all_strategies.find((x) => x.id === id);
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

const viewSchemeById = (id: number) => viewScheme(String(id));

const activePlayers = computed(() => props.tournament.players.filter((p) => !p.is_disqualified && p.dropped_after_round === null));

const latestRound = computed(() => props.tournament.rounds.length > 0 ? props.tournament.rounds[props.tournament.rounds.length - 1] : null);
const allGamesScored = computed(() => {
    if (!latestRound.value) return false;
    const games = latestRound.value.games;
    if (!games.length) return false;
    return games.every((g: any) => g.result === 'completed' || g.result === 'agreed' || g.result === 'forfeited');
});
const isLastRound = computed(() => props.tournament.rounds.length >= props.tournament.planned_rounds);
const canStartNextRound = computed(() => allGamesScored.value && !isLastRound.value && props.tournament.status === 'active');
const canFinalize = computed(() => allGamesScored.value && isLastRound.value && props.tournament.status === 'active');
const formatDate = (d: string) => {
    const date = d.includes('T') ? new Date(d) : new Date(d + 'T00:00:00');
    return date.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
};
const toDateInput = (d: string) => d ? d.split('T')[0] : '';
const isEditable = computed(() => props.tournament.status !== 'completed');

const factionBackground = (faction: string | null): string => {
    if (!faction) return '';
    switch (faction.toLowerCase()) {
        case 'explorers_society': return 'bg-explorerssociety';
        case 'ten_thunders': return 'bg-tenthunders';
        default: return `bg-${faction}`;
    }
};

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

const playerName = (id: number | null): string => {
    if (!id) return 'BYE';
    return props.tournament.players.find((p) => p.id === id)?.display_name ?? 'Unknown';
};
const playerFaction = (id: number | null): string | null => {
    if (!id) return null;
    return props.tournament.players.find((p) => p.id === id)?.faction ?? null;
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
                    <Badge :class="['border-0 text-[10px]', statusColor(tournament.status)]" variant="outline">{{ statusLabel(tournament.status) }}</Badge>
                    <span class="flex items-center gap-1"><CalendarDays class="size-3" /> {{ formatDate(tournament.event_date) }}</span>
                    <span v-if="tournament.location" class="flex items-center gap-1"><MapPin class="size-3" /> {{ tournament.location }}</span>
                    <span>{{ tournament.encounter_size }}ss</span>
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
                    <Button v-if="tournament.is_public" variant="outline" size="sm" class="gap-1.5 text-xs" @click="copyPublicLink">
                        <Copy class="size-3" />
                        {{ linkCopied ? 'Copied!' : 'Share' }}
                    </Button>
                    <Button v-if="tournament.is_public" variant="outline" size="sm" class="gap-1 text-xs" @click="qrOpen = true">
                        <QrCode class="size-3" />
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
                <TabsList class="mb-4 grid w-full grid-cols-2 sm:grid-cols-4">
                    <TabsTrigger value="standings" class="gap-1 text-xs sm:text-sm">Standings</TabsTrigger>
                    <TabsTrigger value="players" class="gap-1 text-xs sm:text-sm">
                        Players
                        <Badge v-if="tournament.players.length" variant="secondary" class="ml-1 px-1 py-0 text-[9px]">{{ tournament.players.length }}</Badge>
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
                            class="flex items-center justify-between gap-2 rounded-lg px-2 py-2 sm:px-3"
                        >
                            <div class="flex min-w-0 items-center gap-1.5">
                                <FactionLogo v-if="player.faction" :faction="player.faction" class-name="size-4 shrink-0 drop-shadow-[0_1px_2px_rgba(0,0,0,0.5)]" />
                                <span class="truncate text-xs font-medium sm:text-sm">{{ player.display_name }}</span>
                                <Badge v-if="player.is_ringer" variant="outline" class="hidden shrink-0 border-white/40 px-1 py-0 text-[9px] text-white/80 sm:inline-flex">Ringer</Badge>
                                <Star v-if="player.is_ringer" class="size-3 shrink-0 fill-amber-300 text-amber-300 sm:hidden" />
                                <Badge v-if="player.is_disqualified" class="shrink-0 bg-red-900/60 px-1 py-0 text-[9px] text-white">DQ</Badge>
                                <Badge v-if="player.dropped_after_round !== null" class="shrink-0 bg-black/30 px-1 py-0 text-[9px] text-white/80">Dropped R{{ player.dropped_after_round }}</Badge>
                            </div>
                            <div class="flex shrink-0 items-center gap-0.5">
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
                    <div v-if="tournament.status === 'active'" class="mb-4 flex flex-wrap gap-2">
                        <!-- Start Next Round (only when all games scored and more rounds to go) -->
                        <Button
                            v-if="canStartNextRound"
                            size="sm"
                            class="gap-1"
                            :disabled="submitting"
                            @click="startNextRound"
                        >
                            <Loader2 v-if="submitting" class="size-3.5 animate-spin" />
                            <Plus v-else class="size-3.5" />
                            Start Round {{ tournament.rounds.length + 1 }}
                        </Button>
                        <!-- Finalize Tournament (last round, all scored) -->
                        <Button
                            v-if="canFinalize"
                            class="gap-1"
                            size="sm"
                            @click="confirmFinalizeDialog = true"
                        >
                            <Trophy class="size-3.5" />
                            Finalize Standings &amp; Complete
                        </Button>
                        <!-- Status indicator when games still pending -->
                        <div v-if="latestRound && !allGamesScored && latestRound.games.length > 0" class="flex items-center gap-1.5 text-xs text-muted-foreground">
                            <Loader2 class="size-3 animate-spin" />
                            {{ latestRound.games.filter((g: any) => g.result === 'pending').length }} game(s) pending scores
                        </div>
                    </div>
                    <!-- Pre-tournament: still allow creating rounds for scenario config -->
                    <div v-else-if="tournament.status !== 'completed' && tournament.rounds.length < tournament.planned_rounds" class="mb-4">
                        <Button size="sm" class="gap-1" :disabled="submitting" @click="createRound">
                            <Plus class="size-3.5" /> Create Round {{ tournament.rounds.length + 1 }}
                        </Button>
                    </div>

                    <div v-if="!tournament.rounds.length" class="py-8 text-center text-sm text-muted-foreground">
                        No rounds created yet. Create rounds to configure scenarios and pair players.
                    </div>

                    <div class="space-y-4">
                        <Card v-for="round in tournament.rounds" :key="round.id">
                            <CardContent class="p-4">
                                <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                                    <div class="flex items-center gap-2">
                                        <h3 class="text-sm font-semibold sm:text-base">Round {{ round.round_number }}</h3>
                                        <Badge
                                            :class="['border-0 text-[10px]', round.status === 'completed' ? 'bg-muted text-muted-foreground' : round.status === 'in_progress' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200']"
                                            variant="outline"
                                        >
                                            {{ round.status === 'in_progress' ? 'In Progress' : round.status === 'completed' ? 'Completed' : 'Setup' }}
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
                                        :disabled="submitting"
                                        @click="generatePairings(round.id)"
                                    >
                                        <Loader2 v-if="submitting" class="size-3 animate-spin" />
                                        {{ round.games.length ? 'Re-Pair' : 'Auto Pair' }}
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
                                                <Button v-if="!game.is_bye" variant="ghost" size="sm" class="h-7 px-1.5 text-[11px] sm:px-2 sm:text-xs" @click="openScoreEdit(game)">
                                                    {{ game.result === 'pending' ? 'Score' : 'Edit' }}
                                                </Button>
                                                <button v-if="round.status === 'setup'" class="rounded p-0.5 text-muted-foreground hover:bg-destructive/10 hover:text-destructive" @click="removeGame(game.id)">
                                                    <X class="size-3" />
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Manual Pairing -->
                                <div v-if="round.status === 'setup' && unpairedPlayers(round.id).length" class="mt-3 rounded-md border border-dashed p-2 sm:p-3">
                                    <div class="mb-2 text-xs font-medium text-muted-foreground">Manual Pairing</div>
                                    <div class="grid gap-2 sm:grid-cols-[1fr_1fr_auto] sm:items-end">
                                        <div class="space-y-1">
                                            <Label class="text-[10px]">Player 1</Label>
                                            <Select v-model="manualP1">
                                                <SelectTrigger class="h-8 text-xs"><SelectValue placeholder="Select player..." /></SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem v-for="p in unpairedPlayers(round.id)" :key="p.id" :value="String(p.id)">{{ p.display_name }}</SelectItem>
                                                </SelectContent>
                                            </Select>
                                        </div>
                                        <div class="space-y-1">
                                            <Label class="text-[10px]">Player 2 <span class="text-muted-foreground">(empty = bye)</span></Label>
                                            <Select v-model="manualP2">
                                                <SelectTrigger class="h-8 text-xs"><SelectValue placeholder="BYE" /></SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="bye">BYE</SelectItem>
                                                    <SelectItem v-for="p in unpairedPlayers(round.id).filter((p) => String(p.id) !== manualP1)" :key="p.id" :value="String(p.id)">{{ p.display_name }}</SelectItem>
                                                </SelectContent>
                                            </Select>
                                        </div>
                                        <Button size="sm" class="h-8 w-full gap-1 sm:w-auto" :disabled="!manualP1 || submitting" @click="addManualPairing(round.id)">
                                            <Plus class="size-3" /> Pair
                                        </Button>
                                    </div>
                                    <div v-if="unpairedPlayers(round.id).length" class="mt-2 text-[10px] text-muted-foreground">
                                        {{ unpairedPlayers(round.id).length }} unpaired: {{ unpairedPlayers(round.id).map((p) => p.display_name).join(', ') }}
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
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
                                <div class="flex items-center justify-between rounded-lg border p-3">
                                    <Label class="cursor-pointer text-xs" @click="editIsPublic = !editIsPublic">Public Tournament</Label>
                                    <Switch v-model="editIsPublic" />
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
                                    <span>{{ tournament.encounter_size }}ss</span>
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
                                <div class="flex items-center justify-between">
                                    <span class="text-muted-foreground">Public</span>
                                    <Badge v-if="tournament.is_public" variant="outline" class="border-green-500/50 text-[10px] text-green-600">Yes</Badge>
                                    <Badge v-else variant="outline" class="text-[10px]">No</Badge>
                                </div>
                            </div>

                            <div class="border-t pt-4">
                                <h3 class="mb-2 font-semibold">Organizers</h3>
                                <div class="space-y-1">
                                    <div v-for="org in tournament.organizers" :key="org.id" class="flex items-center gap-2 text-sm">
                                        <Shield class="size-3.5 text-muted-foreground" />
                                        {{ org.name }}
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Round Scenarios -->
                    <Card class="mt-4">
                        <CardContent class="p-4">
                            <div class="mb-3 flex items-center justify-between">
                                <h3 class="font-semibold">Round Scenarios</h3>
                                <Button
                                    v-if="tournament.rounds.length < tournament.planned_rounds"
                                    variant="outline"
                                    size="sm"
                                    class="h-7 gap-1 text-xs"
                                    :disabled="submitting"
                                    @click="createRound"
                                >
                                    <Plus class="size-3" /> Add Round
                                </Button>
                            </div>
                            <div v-if="!tournament.rounds.length" class="py-4 text-center text-xs text-muted-foreground">
                                No rounds configured yet. Add rounds to set up scenarios.
                            </div>
                            <div v-else class="space-y-2">
                                <div
                                    v-for="round in tournament.rounds"
                                    :key="'scenario-' + round.id"
                                    class="rounded-lg border p-3"
                                >
                                    <div class="mb-2 flex items-center justify-between">
                                        <span class="text-sm font-semibold">Round {{ round.round_number }}</span>
                                        <div class="flex items-center gap-1">
                                            <Button variant="ghost" size="sm" class="h-6 gap-1 px-2 text-[11px]" :disabled="submitting" @click="randomizeRoundAndReload(round.id)">
                                                <Dices class="size-3" /> Random
                                            </Button>
                                            <Button variant="ghost" size="sm" class="h-6 px-2 text-[11px]" @click="toggleScenarioEdit(round)">
                                                {{ expandedScenarioRoundId === round.id ? 'Close' : 'Edit' }}
                                            </Button>
                                        </div>
                                    </div>

                                    <!-- Inline editor -->
                                    <div v-if="expandedScenarioRoundId === round.id" class="space-y-1.5">
                                        <div class="flex gap-1">
                                            <Select v-model="editDeployment">
                                                <SelectTrigger class="h-8 flex-1 text-xs"><SelectValue placeholder="Deployment..." /></SelectTrigger>
                                                <SelectContent><SelectItem v-for="d in all_deployments" :key="d.value" :value="d.value">{{ d.label }}</SelectItem></SelectContent>
                                            </Select>
                                            <button v-if="editDeployment" class="shrink-0 rounded-md border px-2 text-muted-foreground hover:bg-accent" @click="viewDeployment(editDeployment)"><Eye class="size-3" /></button>
                                        </div>
                                        <div class="flex gap-1">
                                            <Select v-model="editStrategy">
                                                <SelectTrigger class="h-8 flex-1 text-xs"><SelectValue placeholder="Strategy..." /></SelectTrigger>
                                                <SelectContent><SelectItem v-for="s in all_strategies" :key="s.id" :value="String(s.id)">{{ s.name }}</SelectItem></SelectContent>
                                            </Select>
                                            <button v-if="editStrategy" class="shrink-0 rounded-md border px-2 text-muted-foreground hover:bg-accent" @click="viewStrategy(editStrategy)"><Eye class="size-3" /></button>
                                        </div>
                                        <div v-for="(_, idx) in editSchemePool" :key="'set-scheme-' + idx" class="flex gap-1">
                                            <Select :model-value="editSchemePool[idx] ?? undefined" @update:model-value="(v) => editSchemePool[idx] = v ?? null">
                                                <SelectTrigger class="h-8 flex-1 text-xs"><SelectValue :placeholder="'Scheme ' + (idx + 1)" /></SelectTrigger>
                                                <SelectContent><SelectItem v-for="s in all_schemes" :key="s.id" :value="String(s.id)">{{ s.name }}</SelectItem></SelectContent>
                                            </Select>
                                            <button v-if="editSchemePool[idx]" class="shrink-0 rounded-md border px-2 text-muted-foreground hover:bg-accent" @click="viewScheme(editSchemePool[idx])"><Eye class="size-3" /></button>
                                        </div>
                                        <Button size="sm" class="w-full text-xs" :disabled="submitting" @click="saveRoundScenario(round.id)">Save</Button>
                                    </div>

                                    <!-- Summary view -->
                                    <div v-else class="space-y-1 text-xs">
                                        <div v-if="round.deployment" class="text-muted-foreground">
                                            Deployment: <button class="font-medium text-foreground hover:text-primary hover:underline" @click="viewDeployment(round.deployment)">{{ all_deployments.find((d) => d.value === round.deployment)?.label ?? round.deployment }}</button>
                                        </div>
                                        <div v-if="round.strategy" class="text-muted-foreground">
                                            Strategy: <button class="font-medium text-foreground hover:text-primary hover:underline" @click="viewStrategyById(round.strategy!.id)">{{ round.strategy.name }}</button>
                                        </div>
                                        <div v-if="round.scheme_pool?.length" class="text-muted-foreground">
                                            Schemes:
                                            <template v-for="(id, idx) in round.scheme_pool" :key="id">
                                                <span v-if="idx > 0">, </span>
                                                <button class="font-medium text-foreground hover:text-primary hover:underline" @click="viewSchemeById(id)">{{ all_schemes.find((s) => s.id === id)?.name ?? id }}</button>
                                            </template>
                                        </div>
                                        <div v-if="!round.deployment && !round.strategy && !round.scheme_pool?.length" class="italic text-muted-foreground">Not configured</div>
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

    <!-- QR Code -->
    <QRCodeDialog v-if="qrOpen" v-model:open="qrOpen" :url="route('tournaments.view', tournament.uuid)" title="Tournament Link" />
</template>
