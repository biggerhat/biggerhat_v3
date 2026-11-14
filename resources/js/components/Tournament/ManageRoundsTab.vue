<script setup lang="ts">
import FactionLogo from '@/components/FactionLogo.vue';
import RoundClock from '@/components/Tournament/RoundClock.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Drawer, DrawerClose, DrawerContent, DrawerFooter, DrawerHeader, DrawerTitle } from '@/components/ui/drawer';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { TabsContent } from '@/components/ui/tabs';
import { useConfirm } from '@/composables/useConfirm';
import { useToast } from '@/composables/useToast';
import TopCutCard from '@/components/Tournament/TopCutCard.vue';
import { useTournament } from '@/composables/useTournament';
import { gameStatus, roundStatus, useTournamentStatus } from '@/composables/useTournamentStatus';
import { Check, Dices, Eye, Loader2, MoreHorizontal, Plus, RotateCcw, Trash2, Trophy } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

import {
    isScoredResult,
    tosOperationText,
    type ScenarioOptions,
    roundName,
    type CutState,
    type TosDeploymentInfo,
    type TosOperationInfo,
    type TournamentGame,
    type TournamentPlayer,
    type TournamentRound,
    type TournamentRuleset,
} from '@/types/tournament';

/**
 * The organizer's Rounds tab: round lifecycle (add / pair / start / end / delete), the scenario editor, manual pairing,
 * click-to-swap pairing fixes, forfeits and the card drawer. The page owns the score dialog and the finalize confirmation,
 * so those two arrive as events; everything the next-step guide needs from here is exposed at the bottom.
 */
interface RoundsTournament {
    uuid: string;
    status: string;
    planned_rounds: number;
    round_time_limit: number;
    team_size?: number;
    require_crew_lists?: boolean;
    players: TournamentPlayer[];
    rounds: TournamentRound[];
}

const props = defineProps<{
    tournament: RoundsTournament;
    ruleset: TournamentRuleset;
    all_strategies: ScenarioOptions['strategies'];
    all_schemes: ScenarioOptions['schemes'];
    all_deployments: ScenarioOptions['deployments'];
    tos_operations: TosOperationInfo[];
    tos_deployments: TosDeploymentInfo[];
    checkedInCount: number;
    uncheckedPlayers: TournamentPlayer[];
    canFinalize: boolean;
    /** Where the top cut stands. */
    cut: CutState;
}>();

const emit = defineEmits<{
    (e: 'edit-score', game: TournamentGame): void;
    (e: 'finalize'): void;
}>();

const tournamentRef = computed(() => props.tournament);
const { submitting, showError, doAction, doActionWithData, doModalAction, reloadProps, playerName, playerFaction } = useTournament(tournamentRef);
const toast = useToast();
const confirm = useConfirm();
const reloadPage = () => reloadProps(['tournament', 'standings', 'cut']);

const openScoreEdit = (game: TournamentGame) => emit('edit-score', game);

// ─── Rounds ───
const createRound = async () => {
    if (await doAction(route('tournaments.rounds.create', props.tournament.uuid))) {
        reloadPage();
    }
};

const deleteRound = async (roundId: number, name: string) => {
    if (
        !(await confirm({
            title: `Delete ${name}?`,
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

// AutoPair availability check
const canAutoPair = (round: TournamentRound): boolean => pairBlockReason(round) === null;

// A round can't be paired or started until the scenario its ruleset asks for is complete.
const scenarioBlockReason = (round: TournamentRound): string | null => {
    const required = props.ruleset.scenario_fields;
    const missing: string[] = [];
    if (required.includes('strategy') && !round.strategy) missing.push('strategy');
    if (required.includes('deployment') && !round.deployment) missing.push('deployment');
    if (required.includes('scheme_pool') && (!round.scheme_pool || round.scheme_pool.length < 3)) missing.push('scheme pool');
    if (required.includes('tos_operation') && !round.scenario?.operation_id) missing.push('operation');
    if (required.includes('tos_deployment') && !round.scenario?.deployment_id) missing.push('deployment');
    return missing.length ? `Set the round scenario before pairing (missing: ${missing.join(', ')})` : null;
};

// Returns null if pairing is allowed, otherwise a human-readable reason.
// Mirrors backend TournamentStateMachine::canPairRound — keep in sync.
const pairBlockReason = (round: TournamentRound): string | null => {
    if (round.status !== 'setup') return 'Round is not in setup';
    if (props.tournament.status !== 'active') {
        return round.round_number === 1 ? 'Tournament must be started before Round 1 can be paired' : 'Tournament must be active to pair rounds';
    }
    if (round.round_number > 1) {
        const prevRound = props.tournament.rounds.find((r) => r.round_number === round.round_number - 1);
        if (!prevRound || prevRound.status !== 'completed') {
            return `Round ${round.round_number - 1} must be completed first`;
        }
    }
    const scenarioReason = scenarioBlockReason(round);
    if (scenarioReason) return scenarioReason;
    return null;
};

const generatePairings = async (roundId: number) => {
    // If check-in is being used and some active players haven't arrived, make the TO decide on purpose.
    if (props.checkedInCount > 0 && props.uncheckedPlayers.length > 0) {
        const names = props.uncheckedPlayers.map((p) => p.display_name).join(', ');
        const proceed = await confirm({
            title: `${props.uncheckedPlayers.length} player(s) haven't checked in`,
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

// ─── Fix pairings: click one player (or table, or unpaired player), then another, to swap them ───
type Slot = 'player_one' | 'player_two';
type SwapPick =
    | { kind: 'slot'; roundId: number; gameId: number; slot: Slot }
    | { kind: 'unpaired'; roundId: number; playerId: number }
    | { kind: 'table'; roundId: number; gameId: number };
const swapPick = ref<SwapPick | null>(null);
const slotPicked = (gameId: number, slot: Slot) =>
    swapPick.value?.kind === 'slot' && swapPick.value.gameId === gameId && swapPick.value.slot === slot;
const tablePicked = (gameId: number) => swapPick.value?.kind === 'table' && swapPick.value.gameId === gameId;
const unpairedPicked = (playerId: number) => swapPick.value?.kind === 'unpaired' && swapPick.value.playerId === playerId;

const runSwap = async (round: TournamentRound, body: Record<string, unknown>, tables = false) => {
    swapPick.value = null;
    submitting.value = true;
    const { ok, data } = await doActionWithData(
        route(tables ? 'tournaments.games.swap_tables' : 'tournaments.games.swap', { tournament: props.tournament.uuid, round: round.id }),
        'POST',
        body,
    );
    submitting.value = false;
    if (ok) {
        for (const warning of (data?.warnings ?? []) as string[]) toast.warning(warning);
        reloadPage();
    }
};

const pickSlot = async (round: TournamentRound, game: TournamentGame, slot: Slot) => {
    const pick = swapPick.value;
    if (pick?.roundId === round.id && pick.kind === 'slot' && pick.gameId !== game.id) {
        return runSwap(round, { game_id: pick.gameId, slot: pick.slot, other_game_id: game.id, other_slot: slot });
    }
    if (pick?.roundId === round.id && pick.kind === 'unpaired') {
        return runSwap(round, { game_id: game.id, slot, player_id: pick.playerId });
    }
    swapPick.value = slotPicked(game.id, slot) ? null : { kind: 'slot', roundId: round.id, gameId: game.id, slot };
};

const pickUnpaired = async (round: TournamentRound, playerId: number) => {
    const pick = swapPick.value;
    if (pick?.roundId === round.id && pick.kind === 'slot') {
        return runSwap(round, { game_id: pick.gameId, slot: pick.slot, player_id: playerId });
    }
    swapPick.value = unpairedPicked(playerId) ? null : { kind: 'unpaired', roundId: round.id, playerId };
};

const pickTable = async (round: TournamentRound, game: TournamentGame) => {
    const pick = swapPick.value;
    if (pick?.roundId === round.id && pick.kind === 'table' && pick.gameId !== game.id) {
        return runSwap(round, { game_id: pick.gameId, other_game_id: game.id }, true);
    }
    swapPick.value = tablePicked(game.id) ? null : { kind: 'table', roundId: round.id, gameId: game.id };
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

// Crews change every round, so the organizer is warned (not blocked) when someone hasn't said which one they're bringing.
const crewNagNames = (round: TournamentRound): string[] => {
    if (!props.tournament.require_crew_lists || props.ruleset.declaration !== 'faction' || (props.tournament.team_size ?? 1) > 1) return [];
    const names: string[] = [];
    for (const g of round.games) {
        if (g.is_bye || !g.player_two) continue;
        if (!g.crew_one) names.push(g.player_one.display_name);
        if (!g.crew_two) names.push(g.player_two.display_name);
    }
    return names;
};

const startRound = async (roundId: number) => {
    const round = props.tournament.rounds.find((r) => r.id === roundId);
    const missing = round ? crewNagNames(round) : [];
    if (missing.length) {
        const proceed = await confirm({
            title: `${missing.length} player(s) haven't chosen a crew`,
            message: `${missing.join(', ')} ${missing.length === 1 ? "hasn't" : "haven't"} picked the crew they're bringing to Round ${round?.round_number}. They can still pick one until their game is scored.`,
            confirmLabel: 'Start anyway',
        });
        if (!proceed) return;
    }
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

const resetRound = async (roundId: number) => {
    const round = props.tournament.rounds.find((r) => r.id === roundId);
    const scored = round?.games.filter((g) => !g.is_bye && g.result !== 'pending').length ?? 0;
    const proceed = await confirm({
        title: `Reset Round ${round?.round_number}?`,
        message:
            `This puts the round back to before it started: ${scored ? `${scored} entered score(s) and ` : ''}forfeits, self-reports and the live tracker games are deleted. ` +
            'Pairings, tables and the scenario are kept, so you can fix them and start the round again. This cannot be undone.',
        confirmLabel: 'Reset round',
    });
    if (!proceed) return;
    submitting.value = true;
    if (await doAction(route('tournaments.rounds.reset', { tournament: props.tournament.uuid, round: roundId }), 'POST', {})) {
        reloadPage();
    }
    submitting.value = false;
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

const { badgeTone, chipTone } = useTournamentStatus();

const playedCount = (round: TournamentRound): number => round.games.filter((g) => !g.is_bye).length;
const scoredCount = (round: TournamentRound): number => round.games.filter((g) => !g.is_bye && g.result !== 'pending').length;

const roundAllScored = (round: TournamentRound): boolean => {
    if (!round.games.length) return false;
    return round.games.every((g) => isScoredResult(g.result));
};

// Mirrors the server's start guards (TournamentStateMachine::guardStartRound): scenario first, then every active player paired.
const startBlockReason = (round: TournamentRound): string | null => {
    const scenario = scenarioBlockReason(round);
    if (scenario) return scenario.replace('before pairing', 'before starting');
    const unpaired = unpairedPlayers(round.id);
    if (!unpaired.length) return null;
    const names = unpaired
        .slice(0, 3)
        .map((p) => p.display_name)
        .join(', ');
    return `All active players must be paired (or given a bye) before starting — unpaired: ${names}${unpaired.length > 3 ? ` (+${unpaired.length - 3} more)` : ''}`;
};

const canStartRound = (round: TournamentRound): boolean => {
    return round.status === 'setup' && round.games.length > 0 && props.tournament.status === 'active';
};

const canEndRound = (round: TournamentRound): boolean => {
    return round.status === 'in_progress' && roundAllScored(round);
};

// The page's next-step guide opens the scenario editor for a round (it switches to this tab itself).
const editScenario = (round: TournamentRound) => {
    if (expandedScenarioRoundId.value !== round.id) toggleScenarioEdit(round);
};

const topCut = ref<InstanceType<typeof TopCutCard> | null>(null);

defineExpose({
    submitting,
    createRound,
    generatePairings,
    startRound,
    endRound,
    editScenario,
    pairBlockReason,
    scenarioBlockReason,
    startBlockReason,
    canEndRound,
    advanceCut: () => topCut.value?.advance(),
    cutAvailable: () => !!topCut.value?.canStart,
});
</script>

<template>
    <!-- ═══ ROUNDS ═══ -->
    <TabsContent value="rounds">
        <TopCutCard
            ref="topCut"
            :tournament="tournament"
            :cut="cut"
            :latest-round="tournament.rounds[tournament.rounds.length - 1] ?? null"
            @changed="reloadPage"
        />

        <!-- Finalize Tournament (last round completed) -->
        <div v-if="canFinalize" class="mb-4 flex flex-wrap gap-2">
            <Button class="gap-1" size="sm" @click="emit('finalize')">
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
                    <div class="mb-3 space-y-2">
                        <!-- Title row: what round is this and where is it? -->
                        <div class="flex items-center justify-between gap-2">
                            <div class="flex min-w-0 flex-wrap items-center gap-2">
                                <h2 class="text-sm font-semibold sm:text-base">{{ roundName(round) }}</h2>
                                <Badge :class="['text-2xs', badgeTone(roundStatus(round).tone)]" variant="outline">{{
                                    roundStatus(round).label
                                }}</Badge>
                                <RoundClock
                                    v-if="round.status === 'in_progress' && round.started_at"
                                    :started-at="round.started_at"
                                    :limit-minutes="tournament.round_time_limit"
                                />
                            </div>
                            <DropdownMenu v-if="round.status === 'in_progress' || tournament.status !== 'completed'" :modal="false">
                                <DropdownMenuTrigger as-child>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        class="size-8 shrink-0 text-muted-foreground"
                                        :aria-label="`Options for ${roundName(round)}`"
                                    >
                                        <MoreHorizontal class="size-4" />
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end">
                                    <DropdownMenuItem
                                        v-if="round.status === 'in_progress'"
                                        class="text-destructive focus:text-destructive"
                                        @select="resetRound(round.id)"
                                    >
                                        <RotateCcw class="mr-2 size-4" /> Reset round…
                                    </DropdownMenuItem>
                                    <DropdownMenuItem
                                        v-else
                                        class="text-destructive focus:text-destructive"
                                        @select="deleteRound(round.id, roundName(round))"
                                    >
                                        <Trash2 class="mr-2 size-4" /> Delete round…
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </div>

                        <!-- Action row: the scenario on the left, the one thing to do next on the right -->
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <Button
                                v-if="ruleset.scenario_fields.length && (tournament.status !== 'completed' || round.strategy || round.deployment)"
                                variant="ghost"
                                size="sm"
                                class="-ml-2"
                                @click="toggleScenarioEdit(round)"
                            >
                                {{
                                    expandedScenarioRoundId === round.id
                                        ? 'Hide scenario'
                                        : round.strategy || round.deployment || round.scenario?.operation_id
                                          ? 'Scenario'
                                          : 'Set scenario'
                                }}
                            </Button>
                            <span v-else />
                            <div class="flex items-center gap-2">
                                <Button
                                    v-if="round.status === 'setup'"
                                    :variant="canStartRound(round) ? 'outline' : 'default'"
                                    size="sm"
                                    class="gap-1"
                                    :disabled="submitting || !canAutoPair(round)"
                                    :title="
                                        pairBlockReason(round) ??
                                        (round.games.length ? 'Regenerate Swiss pairings (preserves manual pairings)' : 'Generate Swiss pairings')
                                    "
                                    @click="generatePairings(round.id)"
                                >
                                    <Loader2 v-if="submitting" class="size-3 animate-spin" />
                                    Auto pair
                                </Button>
                                <Button
                                    v-if="canStartRound(round)"
                                    size="sm"
                                    class="gap-1"
                                    :disabled="submitting || !!startBlockReason(round)"
                                    :title="startBlockReason(round) ?? ''"
                                    @click="startRound(round.id)"
                                >
                                    <Loader2 v-if="submitting" class="size-3 animate-spin" />
                                    Start round
                                </Button>
                                <Button
                                    v-if="round.status === 'in_progress'"
                                    size="sm"
                                    class="gap-1"
                                    :disabled="submitting || !canEndRound(round)"
                                    :title="!canEndRound(round) ? 'All scores must be entered before ending the round' : ''"
                                    @click="endRound(round.id)"
                                >
                                    <Loader2 v-if="submitting" class="size-3 animate-spin" />
                                    End round
                                </Button>
                            </div>
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
                                        <SelectItem v-for="d in all_deployments" :key="d.value" :value="d.value">{{ d.label }}</SelectItem>
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
                                        <SelectItem v-for="s in all_strategies ?? []" :key="s.id" :value="String(s.id)">{{ s.name }}</SelectItem>
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
                                        <SelectItem v-for="o in tos_operations" :key="o.id" :value="String(o.id)">{{ o.name }}</SelectItem>
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
                                        <SelectItem v-for="d in tos_deployments" :key="d.id" :value="String(d.id)">{{ d.name }}</SelectItem>
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
                            <Button variant="ghost" size="sm" class="h-7 px-2 text-2xs" @click="expandedScenarioRoundId = null">Done</Button>
                        </div>
                    </div>
                    <!-- Scenario summary (when not editing) -->
                    <dl v-else-if="usesTosScenario && round.scenario" class="mb-3 flex flex-wrap gap-x-4 gap-y-1 text-xs">
                        <div v-if="round.scenario.operation_id" class="flex gap-1.5">
                            <dt class="text-muted-foreground">Operation</dt>
                            <dd class="font-medium">{{ tosOperationName(round.scenario.operation_id) }}</dd>
                        </div>
                        <div v-if="round.scenario.deployment_id" class="flex gap-1.5">
                            <dt class="text-muted-foreground">Deployment</dt>
                            <dd class="font-medium">{{ tosDeploymentName(round.scenario.deployment_id) }}</dd>
                        </div>
                    </dl>
                    <dl v-else-if="round.strategy || round.deployment" class="mb-3 flex flex-wrap gap-x-4 gap-y-1 text-xs">
                        <div v-if="round.deployment" class="flex gap-1.5">
                            <dt class="text-muted-foreground">Deployment</dt>
                            <dd class="font-medium">{{ deploymentByValue[round.deployment]?.label }}</dd>
                        </div>
                        <div v-if="round.strategy" class="flex gap-1.5">
                            <dt class="text-muted-foreground">Strategy</dt>
                            <dd class="font-medium">{{ round.strategy.name }}</dd>
                        </div>
                        <div v-if="round.scheme_pool?.length" class="flex gap-1.5">
                            <dt class="text-muted-foreground">Schemes</dt>
                            <dd class="font-medium">{{ round.scheme_pool.map((id: number) => schemeById[id]?.name ?? id).join(' · ') }}</dd>
                        </div>
                    </dl>

                    <div v-if="!round.games.length" class="py-4 text-center text-xs text-muted-foreground">
                        No games in this round yet. Generate pairings or add them manually.
                    </div>

                    <div v-else class="space-y-1.5">
                        <div
                            v-for="game in round.games"
                            :key="game.id"
                            class="rounded-md border px-2 py-2 text-sm sm:px-3"
                            :class="isScoredResult(game.result) ? '' : 'border-dashed'"
                        >
                            <div class="flex flex-col gap-2 lg:flex-row lg:items-center lg:justify-between">
                                <div class="flex min-w-0 flex-1 items-center gap-2">
                                    <button
                                        v-if="game.table_number && round.status === 'setup'"
                                        type="button"
                                        class="shrink-0 cursor-pointer rounded bg-muted px-1.5 py-0.5 text-2xs font-semibold tabular-nums text-muted-foreground hover:bg-accent"
                                        :class="tablePicked(game.id) ? 'ring-2 ring-primary' : ''"
                                        :aria-pressed="tablePicked(game.id)"
                                        :title="tablePicked(game.id) ? 'Pick another table to swap' : 'Click, then another table, to swap tables'"
                                        @click="pickTable(round, game)"
                                    >
                                        Table {{ game.table_number }}
                                    </button>
                                    <span
                                        v-else-if="game.table_number"
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
                                            <button
                                                v-if="round.status === 'setup'"
                                                type="button"
                                                class="cursor-pointer truncate rounded px-1 font-medium underline decoration-muted-foreground/50 decoration-dotted underline-offset-4 hover:bg-accent"
                                                :class="slotPicked(game.id, 'player_one') ? 'bg-primary/15 ring-1 ring-primary' : ''"
                                                :aria-pressed="slotPicked(game.id, 'player_one')"
                                                @click="pickSlot(round, game, 'player_one')"
                                            >
                                                {{ playerName(game.player_one_id) }}
                                            </button>
                                            <span v-else class="truncate font-medium">{{ playerName(game.player_one_id) }}</span>
                                        </span>
                                        <template v-if="!game.is_bye">
                                            <span class="hidden shrink-0 text-2xs text-muted-foreground sm:inline">vs</span>
                                            <span class="flex min-w-0 items-center gap-1.5">
                                                <FactionLogo
                                                    v-if="game.player_two_faction || playerFaction(game.player_two_id)"
                                                    :faction="(game.player_two_faction || playerFaction(game.player_two_id))!"
                                                    class-name="size-4 shrink-0"
                                                />
                                                <button
                                                    v-if="round.status === 'setup'"
                                                    type="button"
                                                    class="cursor-pointer truncate rounded px-1 font-medium underline decoration-muted-foreground/50 decoration-dotted underline-offset-4 hover:bg-accent"
                                                    :class="slotPicked(game.id, 'player_two') ? 'bg-primary/15 ring-1 ring-primary' : ''"
                                                    :aria-pressed="slotPicked(game.id, 'player_two')"
                                                    @click="pickSlot(round, game, 'player_two')"
                                                >
                                                    {{ playerName(game.player_two_id) }}
                                                </button>
                                                <span v-else class="truncate font-medium">{{ playerName(game.player_two_id) }}</span>
                                            </span>
                                        </template>
                                    </div>
                                </div>

                                <div class="flex shrink-0 flex-wrap items-center gap-2">
                                    <!-- One status chip per game: where is this match right now? -->
                                    <span
                                        class="inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-2xs font-medium"
                                        :class="chipTone(gameStatus(game, round).tone)"
                                        >{{ gameStatus(game, round).label }}</span
                                    >
                                    <span v-if="game.is_bye" class="text-xs text-muted-foreground">Automatic win</span>
                                    <span v-else-if="isScoredResult(game.result)" class="text-sm font-bold tabular-nums"
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
                                    <DropdownMenu
                                        v-if="(round.status === 'in_progress' && !game.is_bye && game.player_two) || round.status === 'setup'"
                                        :modal="false"
                                    >
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
                                            <template v-if="round.status === 'in_progress'">
                                                <DropdownMenuItem
                                                    v-if="!game.is_forfeit"
                                                    class="text-destructive focus:text-destructive"
                                                    @select="openForfeitDialog(game)"
                                                >
                                                    Record a forfeit…
                                                </DropdownMenuItem>
                                                <DropdownMenuItem v-else @select="removeForfeit(game)">Undo forfeit</DropdownMenuItem>
                                            </template>
                                            <DropdownMenuItem v-else class="text-destructive focus:text-destructive" @select="removeGame(game.id)">
                                                Remove game
                                            </DropdownMenuItem>
                                        </DropdownMenuContent>
                                    </DropdownMenu>
                                </div>
                            </div>
                            <div
                                v-if="game.crew_one || game.crew_two || crewNagNames(round).length"
                                class="mt-1.5 grid gap-x-4 gap-y-0.5 border-t pt-1.5 text-2xs sm:grid-cols-2"
                            >
                                <div
                                    v-for="side in [
                                        ['crew_one', game.player_one],
                                        ['crew_two', game.player_two],
                                    ] as const"
                                    :key="side[0]"
                                    class="min-w-0"
                                >
                                    <template v-if="side[1] && !game.is_bye">
                                        <a
                                            v-if="game[side[0]]"
                                            :href="route('tools.crew_builder.share', game[side[0]]!.share_code)"
                                            target="_blank"
                                            rel="noopener"
                                            class="font-medium text-primary hover:underline"
                                            >{{ side[1].display_name }}: {{ game[side[0]]!.master ?? game[side[0]]!.name }} ·
                                            {{ game[side[0]]!.encounter_size }}ss</a
                                        >
                                        <span v-else class="text-amber-700 dark:text-amber-400">{{ side[1].display_name }}: no crew picked</span>
                                        <span
                                            v-for="problem in game[side[0]]?.problems ?? []"
                                            :key="problem"
                                            class="ml-2 text-amber-700 dark:text-amber-400"
                                            >⚠ {{ problem }}</span
                                        >
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Pending scores indicator -->
                    <div
                        v-if="round.status === 'in_progress' && round.games.length > 0 && !roundAllScored(round)"
                        class="mt-2 space-y-1"
                        role="status"
                    >
                        <div class="flex items-center justify-between text-xs text-muted-foreground">
                            <span>{{ scoredCount(round) }} of {{ playedCount(round) }} games scored</span>
                            <span>End round unlocks when every game has a result</span>
                        </div>
                        <div class="h-1 overflow-hidden rounded-full bg-muted">
                            <div
                                class="h-full rounded-full bg-primary transition-all"
                                :style="{ width: (scoredCount(round) / Math.max(1, playedCount(round))) * 100 + '%' }"
                            />
                        </div>
                    </div>

                    <!-- Fix pairings -->
                    <div v-if="round.status === 'setup' && round.games.length" class="mt-2 space-y-1.5 text-xs text-muted-foreground" role="status">
                        <p>
                            <template v-if="swapPick && swapPick.roundId === round.id">
                                Now pick the player to swap with{{ swapPick.kind === 'table' ? ' (a table number)' : '' }}.
                                <button type="button" class="underline underline-offset-2 hover:text-foreground" @click="swapPick = null">
                                    Cancel
                                </button>
                            </template>
                            <template v-else>To fix a pairing, click a player (or a table number), then the one to swap with.</template>
                        </p>
                        <div v-if="unpairedPlayers(round.id).length" class="flex flex-wrap items-center gap-1.5">
                            <span>Unpaired:</span>
                            <button
                                v-for="p in unpairedPlayers(round.id)"
                                :key="p.id"
                                type="button"
                                class="rounded-full border px-2 py-0.5 hover:bg-accent"
                                :class="unpairedPicked(p.id) ? 'bg-primary/15 ring-1 ring-primary' : ''"
                                :aria-pressed="unpairedPicked(p.id)"
                                @click="pickUnpaired(round, p.id)"
                            >
                                {{ p.display_name }}
                            </button>
                        </div>
                    </div>

                    <!-- Manual Pairing -->
                    <div v-if="round.status === 'setup' && unpairedPlayers(round.id).length" class="mt-3 rounded-md border border-dashed p-2 sm:p-3">
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
                                                v-for="p in unpairedPlayers(round.id).filter((p) => String(p.id) !== getManualPair(round.id).p1)"
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
</template>
