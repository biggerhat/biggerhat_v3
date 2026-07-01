<script setup lang="ts">
import CharacterCardView from '@/components/CharacterCardView.vue';
import CrewBuilderReferences from '@/components/CrewBuilderReferences.vue';
import FactionLogo from '@/components/FactionLogo.vue';
import GameAbandonDialog from '@/components/Game/GameAbandonDialog.vue';
import GameAllCardsDialog from '@/components/Game/GameAllCardsDialog.vue';
import GameAttachedUpgradeDrawer from '@/components/Game/GameAttachedUpgradeDrawer.vue';
import GameCardFullscreenDialog from '@/components/Game/GameCardFullscreenDialog.vue';
import GameCompleteDialog from '@/components/Game/GameCompleteDialog.vue';
import GameCrewMemberDrawer from '@/components/Game/GameCrewMemberDrawer.vue';
import GameCrewSelectPanel from '@/components/Game/GameCrewSelectPanel.vue';
import GameEditScenarioDrawer from '@/components/Game/GameEditScenarioDrawer.vue';
import GameFactionSelectPanel from '@/components/Game/GameFactionSelectPanel.vue';
import GameLeaveDialog from '@/components/Game/GameLeaveDialog.vue';
import GameMasterSelectPanel from '@/components/Game/GameMasterSelectPanel.vue';
import GameOpponentSchemeDialog from '@/components/Game/GameOpponentSchemeDialog.vue';
import GameReplaceDialog from '@/components/Game/GameReplaceDialog.vue';
import GameReplaceOnDeathDialog from '@/components/Game/GameReplaceOnDeathDialog.vue';
import GameSchemeSelectPanel from '@/components/Game/GameSchemeSelectPanel.vue';
import GameSetupLobby from '@/components/Game/GameSetupLobby.vue';
import GameSubmitTurnDialog from '@/components/Game/GameSubmitTurnDialog.vue';
import GameSummaryPanel from '@/components/Game/GameSummaryPanel.vue';
import GameSummonDialog from '@/components/Game/GameSummonDialog.vue';
import GameTokenDialog from '@/components/Game/GameTokenDialog.vue';
import GameTokenInfoDrawer from '@/components/Game/GameTokenInfoDrawer.vue';
import GameUpgradeDialog from '@/components/Game/GameUpgradeDialog.vue';
import PowerBarBubbles from '@/components/Game/PowerBarBubbles.vue';
import GameIcon from '@/components/GameIcon.vue';
import QRCodeDialog from '@/components/QRCodeDialog.vue';
import SeoHead from '@/components/SeoHead.vue';
import UpgradeFlipCard from '@/components/UpgradeFlipCard.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Drawer, DrawerClose, DrawerContent, DrawerFooter, DrawerHeader, DrawerTitle } from '@/components/ui/drawer';
import { Input } from '@/components/ui/input';
import { Switch } from '@/components/ui/switch';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useConfirm } from '@/composables/useConfirm';
import { csrfHeaders, useGameApi } from '@/composables/useGameApi';
import { useGameChannel } from '@/composables/useGameChannel';
import { useToast } from '@/composables/useToast';
import { factionBackground, playerName } from '@/lib/gameDisplay';
import { MAX_SCHEME_PER_TURN, MAX_SCHEME_POOL, TURN_BANNER_VISIBLE_MS } from '@/pages/Games/constants';
import { type SharedData } from '@/types';
import {
    GAME_FINISHED_STATUSES,
    GAME_SETUP_STATUSES,
    GameFormat,
    GameStatus,
    type CrewMember,
    type DeploymentData,
    type GameData,
    type LootCardSummary,
    type LootMarker,
    type SchemeData,
} from '@/types/game';
import { Link, router, usePage } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ArrowUpCircle,
    Banana,
    Check,
    ChevronDown,
    Circle,
    Copy,
    Dices,
    Eye,
    EyeOff,
    Footprints,
    Heart,
    Layers,
    LayoutGrid,
    Loader2,
    Minus,
    PanelLeftClose,
    PanelLeftOpen,
    Pencil,
    Plus,
    Puzzle,
    Replace,
    RotateCcw,
    Search,
    Settings,
    Shield,
    ShieldAlert,
    Skull,
    Star,
    Swords,
    UserRound,
    Users,
    X,
} from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

interface FactionInfo {
    name: string;
    slug: string;
    color: string;
    logo: string;
}

interface MasterTitle {
    id: number;
    display_name: string;
    title: string | null;
    /** Effective hire cost in Bonanza Brawl (totems/peons derived from health). */
    bonanza_cost?: number;
}

interface MasterOption {
    name: string;
    faction: string;
    second_faction: string | null;
    is_alternate_leader: boolean;
    front_image: string | null;
    titles: MasterTitle[];
}

interface CrewOptionMember {
    display_name: string;
    faction: string;
    cost: number;
    effective_cost: number;
    category: string;
}

interface CrewOption {
    id: number;
    name: string;
    share_code: string;
    faction: string;
    master_name: string;
    encounter_size: number;
    crew_count: number;
    total_spent: number;
    soulstone_pool: number;
    ook_count: number;
    is_over_budget: boolean;
    members: CrewOptionMember[];
}

const props = defineProps<{
    game: GameData;
    schemes: SchemeData[];
    deployment: DeploymentData | null;
    factions: Record<string, FactionInfo>;
    masters: MasterOption[];
    my_crews: CrewOption[];
    all_strategies: { id: number; name: string; slug: string; image_url: string | null }[];
    all_schemes: { id: number; name: string; slug: string; image_url: string | null }[];
    all_deployments: { value: string; label: string; image_url: string | null }[];
    current_schemes: SchemeData[];
    opponent_scheme_intel: {
        last_revealed: { id: number; name: string; turn_number: number; scored: boolean } | null;
        possible_schemes: SchemeData[];
    } | null;
    next_schemes: SchemeData[];
    opponent_next_schemes: SchemeData[];
    tokens: { id: number; name: string; slug: string; description: string | null }[];
    character_upgrades: {
        id: number;
        name: string;
        slug: string;
        front_image: string | null;
        back_image: string | null;
        type: string | null;
        plentiful: number | null;
        power_bar_count: number | null;
    }[];
    all_markers: { id: number; name: string; slug: string }[];
    all_reachable_schemes: SchemeData[];
    observer_scheme_intel: Record<
        number,
        {
            possible_schemes: SchemeData[];
            revealed_scheme_id: number | null;
            last_scored_turn: number | null;
        }
    > | null;
    starting_crews?: Record<
        number,
        { display_name: string; faction: string; cost: number; hiring_category: string; front_image: string | null; back_image: string | null }[]
    >;
    /** Bonanza-only catalog of every loot card. Used to resolve card_id refs
     *  in dropped markers + attached_upgrades. Empty array on non-Bonanza. */
    loot_card_catalog?: LootCardSummary[];
    /** Bonanza-only: crew upgrades aggregated from the picked character's
     *  keywords (since a Bonanza model usually isn't a master with direct
     *  crew upgrades). Empty array on non-Bonanza. */
    bonanza_crew_upgrades?: {
        id: number;
        name: string;
        slug: string;
        front_image: string | null;
        back_image: string | null;
        type: string | null;
        plentiful: number | null;
        power_bar_count: number | null;
    }[];
    is_observer: boolean;
    campaign_context: {
        campaign: { id: number; name: string; current_week: number; length_weeks: number } | null;
        crew_a: { id: number; share_code: string; name: string; user_id: number } | null;
        crew_b: { id: number; share_code: string; name: string; user_id: number } | null;
        cr_a: number;
        cr_b: number;
        ss_bonus_to_lower: number;
        encounter_size: number;
        week_number: number;
    } | null;
    campaign_arsenal?: {
        character_id: number;
        name: string;
        faction: string;
        station: string;
        cost: number;
        effective_cost: number;
        is_ook: boolean;
        is_peon: boolean;
    }[];
}>();

const page = usePage<SharedData>();
const gameApi = useGameApi();
const currentUserId = computed(() => page.props.auth.user?.id);
const myPlayer = computed(() => {
    if (props.is_observer) return props.game.players.find((p) => p.slot === 1);
    return props.game.players.find((p) => p.user?.id === currentUserId.value);
});
const opponent = computed(() => {
    if (props.is_observer) return props.game.players.find((p) => p.slot === 2);
    if (props.game.is_solo) return props.game.players.find((p) => p.slot === 2);
    return props.game.players.find((p) => p.user?.id !== currentUserId.value);
});

const isSolo = computed(() => props.game.is_solo);
const isObserver = computed(() => props.is_observer);
// Bonanza Brawl: 11ss single-model FFA, no scenario, manual VP. Used to gate
// the standard-format scenario panel + the per-turn scheme/strategy scoring
// widgets, and to show a rules-summary banner on the gameplay surface.
const isBonanza = computed(() => props.game.format === GameFormat.BonanzaBrawl);
const isCampaign = computed(() => props.game.format === GameFormat.Campaign);
// Resolve the actual slot numbers rather than hardcoding 1/2 — solo games
// created from a tournament round can place the registered user in slot 2
// (see TournamentTrackerGameFactory::createForGame), which breaks anything
// that assumes the creator is always slot 1.
const mySlot = computed(() => myPlayer.value?.slot ?? 1);
const opponentSlot = computed(() => opponent.value?.slot ?? 2);
const { onlineMembers } = useGameChannel(isSolo.value && !isObserver.value ? '' : props.game.uuid, isObserver.value);
const isUserOnline = (userId: number) => onlineMembers.value.some((m) => m.id === userId);

// Scenario editing
const isCreator = computed(() => currentUserId.value === props.game.creator_id);
const canEditScenario = computed(() => GAME_SETUP_STATUSES.includes(props.game.status) && isCreator.value);
const editScenarioOpen = ref(false);
const editStrategy = ref<string>(String(props.game.strategy?.id ?? ''));
const editDeployment = ref<string>(props.deployment?.value ?? '');
const editSchemePool = ref<(string | null)[]>(props.schemes.map((s) => String(s.id)));

const openEditScenario = () => {
    editStrategy.value = String(props.game.strategy?.id ?? '');
    editDeployment.value = props.deployment?.value ?? '';
    editSchemePool.value = props.schemes.length ? props.schemes.map((s) => String(s.id)) : [null, null, null];
    editScenarioOpen.value = true;
};

const availableSchemes = (index: number) => {
    const pickedIds = new Set(editSchemePool.value.filter((v, i) => v && i !== index));
    return props.all_schemes.filter((s) => !pickedIds.has(String(s.id)));
};

const saveScenarioFromDrawer = async () => {
    const body: Record<string, unknown> = {
        strategy_id: editStrategy.value ? Number(editStrategy.value) : null,
        deployment: editDeployment.value || null,
        scheme_pool: editSchemePool.value.filter(Boolean).map(Number),
    };
    const savePromise = gameApi.put(route('games.scenario.update', props.game.uuid), body).then((res) => {
        if (!res.ok) throw new Error(`status ${res.status}`);
        return res;
    });

    toast.promise(savePromise, {
        loading: 'Saving scenario…',
        success: 'Scenario saved',
        error: 'Could not save scenario',
    });

    try {
        await savePromise;
        editScenarioOpen.value = false;
        router.reload({ only: ['game', 'schemes', 'deployment'], preserveScroll: true });
    } catch {
        // Toast already surfaced via toast.promise.
    }
};

// Re-roll the scenario (deployment/strategy/scheme pool). Uses gameApi + an
// explicit router.reload — same pattern as saveScenarioFromDrawer — because
// `router.post` to a same-page redirect was not consistently re-pulling the
// `game` / `schemes` / `deployment` props, leaving the UI stale even though
// the DB had been updated.
const regenerateScenario = async () => {
    const regenPromise = gameApi.post(route('games.scenario.regenerate', props.game.uuid)).then((res) => {
        if (!res.ok) throw new Error(`status ${res.status}`);
        return res;
    });

    toast.promise(regenPromise, {
        loading: 'Regenerating scenario…',
        success: 'New scenario generated',
        error: 'Could not regenerate scenario',
    });

    try {
        await regenPromise;
        router.reload({ only: ['game', 'schemes', 'deployment'], preserveScroll: true });
    } catch {
        // Toast already surfaced via toast.promise.
    }
};

// Scenario drawers
const strategyDrawerOpen = ref(false);
const deploymentDrawerOpen = ref(false);
const schemeDrawerOpen = ref(false);
const activeScheme = ref<SchemeData | null>(null);

const openSchemeDrawer = (scheme: SchemeData) => {
    activeScheme.value = scheme;
    schemeDrawerOpen.value = true;
};

// Lobby

// Setup submission
const submitting = ref(false);

const postSetup = async (endpoint: string, body: Record<string, unknown>) => {
    submitting.value = true;
    try {
        const res = await gameApi.post(endpoint, body);
        if (!res.ok) {
            console.error('Setup failed:', res.status);
            submitting.value = false;
            return;
        }
        // Setup steps can change status which affects available props — reload all relevant data
        router.reload({
            only: [
                'game',
                'schemes',
                'all_reachable_schemes',
                'deployment',
                'masters',
                'my_crews',
                'current_schemes',
                'next_schemes',
                'opponent_next_schemes',
                'tokens',
                'character_upgrades',
                'all_markers',
                // Bonanza props are status-gated (populate only at in_progress),
                // and the master-submit step is what flips Bonanza to in_progress
                // — so they must reload here or the loot-deck Select button and
                // crew cards stay empty until a manual refresh.
                'starting_crews',
                'loot_card_catalog',
                'bonanza_crew_upgrades',
                'campaign_arsenal',
            ],
            preserveScroll: true,
            preserveState: true,
            onFinish: () => {
                submitting.value = false;
            },
        });
    } catch (e) {
        console.error('Setup error:', e);
        submitting.value = false;
    }
};

// Faction select
const selectedFaction = ref<string | null>(null);

// Master select — two-step: name then title
const selectedMasterName = ref<string | null>(null);
// For Bonanza, the user must pick a specific title (display_name) when a
// model has multiple titles — no auto-select. For non-Bonanza, the base
// master name is enough; title is picked during crew select. This holds
// the title display_name to submit when one's been chosen.
const selectedMasterTitle = ref<string | null>(null);

// Solo mode: setup for opponent (slot 2)
const opponentPlayer = computed(() => props.game.players.find((p) => p.slot === 2));

const selectedOpponentFaction = ref<string | null>(null);
const selectedOpponentMasterName = ref<string | null>(null);
const opponentStepDone = (step: string) => {
    if (!opponentPlayer.value) return false;
    switch (step) {
        case 'faction':
            return !!opponentPlayer.value.faction;
        case 'master':
            return !!opponentPlayer.value.master_name;
        case 'crew':
            return !!opponentPlayer.value.crew_build_id || opponentPlayer.value.crew_skipped;
        case 'scheme':
            return !!opponentPlayer.value.current_scheme_id;
        default:
            return false;
    }
};

const selectOpponentFaction = () => {
    if (!selectedOpponentFaction.value) return;
    postSetup(route('games.setup.faction', props.game.uuid), { faction: selectedOpponentFaction.value, slot: opponentSlot.value });
};

// Solo opponent-crew skip: lock in the opponent's title (if the panel surfaced
// a multi-title pick) before skipping their crew. The panel emits the chosen
// title's display_name; the shared postSetup owns the skip + reload.
const onSkipOpponentCrew = async (titleDisplayName: string | null) => {
    if (titleDisplayName) {
        await gameApi.post(route('games.setup.master', props.game.uuid), { master_name: titleDisplayName, slot: opponentSlot.value });
    }
    postSetup(route('games.setup.crew.skip', props.game.uuid), {});
};

// Solo: edit opponent name
const editingOpponentName = ref(false);
const opponentNameInput = ref('');

const startEditOpponentName = () => {
    opponentNameInput.value = opponentPlayer.value?.opponent_name ?? 'Opponent';
    editingOpponentName.value = true;
};

const saveOpponentName = () => {
    const name = opponentNameInput.value.trim();
    if (!name) {
        editingOpponentName.value = false;
        return;
    }
    editingOpponentName.value = false;
    router.post(
        route('games.setup.opponent_name', props.game.uuid),
        { opponent_name: name },
        { only: ['game'], preserveScroll: true, preserveState: true },
    );
};

const swapRoles = () => {
    router.post(route('games.setup.swap_roles', props.game.uuid), {}, { only: ['game'], preserveScroll: true, preserveState: true });
};

// Solo gameplay: opponent scoring
const opponentStrategyPoints = ref(0);
const opponentSchemePoints = ref(0);
const opponentSchemeScored = computed(() => opponentSchemePoints.value > 0);

// Solo opponent scheme dialog state
const oppDialogOpen = ref(false);
const oppDialogMode = ref<'scored' | 'discard' | 'end-of-game'>('scored');
const oppIdentifiedSchemeId = ref<number | null>(null);
const oppDialogResolve = ref<((result: 'cancel' | 'done') => void) | null>(null);

const openOppSchemeDialog = (mode: 'scored' | 'discard' | 'end-of-game'): Promise<'cancel' | 'done'> => {
    return new Promise((resolve) => {
        oppDialogMode.value = mode;
        oppIdentifiedSchemeId.value = null;
        oppDialogResolve.value = resolve;
        oppDialogOpen.value = true;
    });
};

const oppSelectScheme = (schemeId: number) => {
    oppIdentifiedSchemeId.value = schemeId;
    oppDialogOpen.value = false;
    oppDialogResolve.value?.('done');
    oppDialogResolve.value = null;
};

const oppKeepHidden = () => {
    oppIdentifiedSchemeId.value = null;
    oppDialogOpen.value = false;
    oppDialogResolve.value?.('done');
    oppDialogResolve.value = null;
};

const oppCancelDialog = () => {
    oppDialogOpen.value = false;
    oppDialogResolve.value?.('cancel');
    oppDialogResolve.value = null;
};

const doSubmitOpponentTurn = async (schemeAction: string, identifiedSchemeId: number | null) => {
    scoringOpponentTurn.value = true;
    try {
        const payload: Record<string, any> = {
            strategy_points: opponentStrategyPoints.value,
            strategy_bonus_used: opponentStrategyPoints.value === 2 || (opponentStrategyPoints.value === 1 && opponentStrategyBonusOnly.value),
            scheme_points: opponentSchemePoints.value,
            scheme_action: schemeAction,
            slot: opponentSlot.value,
        };
        if (identifiedSchemeId) {
            payload.identified_scheme_id = identifiedSchemeId;
        }
        const res = await gameApi.post(route('games.play.turns.store', props.game.uuid), payload);
        if (!res.ok) {
            console.error('Opponent turn submit failed:', res.status, res.data);
        }
    } catch (e) {
        console.error('Opponent turn submit error:', e);
    }
    opponentStrategyPoints.value = 0;
    opponentStrategyBonusOnly.value = false;
    opponentSchemePoints.value = 0;
    scoringOpponentTurn.value = false;
    const savedStrategy = strategyPoints.value;
    const savedScheme = schemePoints.value;
    const savedNextScheme = nextSchemeId.value;
    router.reload({
        only: ['game', 'current_schemes', 'next_schemes', 'opponent_next_schemes', 'opponent_scheme_intel'],
        preserveState: true,
        preserveScroll: true,
        onFinish: () => {
            strategyPoints.value = savedStrategy;
            schemePoints.value = savedScheme;
            nextSchemeId.value = savedNextScheme;
        },
    });
};

const submitOpponentTurnScore = async () => {
    // Scored VP > 0: must identify which scheme via dialog
    if (opponentSchemeScored.value) {
        const result = await openOppSchemeDialog('scored');
        if (result === 'cancel') return;
        await doSubmitOpponentTurn('scored', oppIdentifiedSchemeId.value);
        return;
    }

    // Scored 0 VP: open dialog for hold/discard choice
    const result = await openOppSchemeDialog('discard');
    if (result === 'cancel') return;

    if (oppIdentifiedSchemeId.value) {
        // User picked a scheme to discard
        await doSubmitOpponentTurn('discarded', oppIdentifiedSchemeId.value);
    } else {
        // User chose "Hold Scheme (Hidden)"
        await doSubmitOpponentTurn('held', null);
    }
};

const updateOpponentSoulstonePool = (delta: number) => {
    const current = opponentPlayer.value?.soulstone_pool ?? 0;
    const newVal = Math.max(0, current + delta);
    postPlay(route('games.play.soulstones', props.game.uuid), 'PATCH', { soulstone_pool: newVal, slot: opponentSlot.value });
};

// Solo: opponent possible scheme pool (read from stored scheme_pool via opponent_next_schemes)
const opponentSchemePool = computed(() => {
    return props.opponent_next_schemes.length ? props.opponent_next_schemes : props.schemes;
});

// Summon count check
const summonCrewCount = (characterId: number) => {
    const slot = summonForSlot.value;
    const player = slot === 1 ? myPlayer.value : opponent.value;
    return (player?.crew_members ?? []).filter((m: any) => m.character_id === characterId && !m.is_killed).length;
};

// Solo: summon for opponent
const summonForSlot = ref<number>(1);
const openSummonForSlot = (slot: number) => {
    summonForSlot.value = slot;
    summonDialogOpen.value = true;
};

// Helpers
const myStepDone = (step: string) => {
    if (!myPlayer.value) return false;
    switch (step) {
        case 'faction':
            return !!myPlayer.value.faction;
        case 'master':
            return !!myPlayer.value.master_name;
        case 'crew':
            return !!myPlayer.value.crew_build_id;
        case 'scheme':
            return !!myPlayer.value.current_scheme_id;
        default:
            return false;
    }
};

// Solo: detect when we're picking for the opponent to style the card differently
const isOpponentSetupPhase = computed(() => {
    if (!isSolo.value) return false;
    // Bonanza is solo-by-force but opponent-less — the user only ever runs
    // their own setup, so we never enter the opponent-half of any phase.
    if (isBonanza.value) return false;
    const status = props.game.status;
    if (status === GameStatus.FactionSelect) return myStepDone('faction') && !opponentStepDone('faction');
    if (status === GameStatus.MasterSelect) return myStepDone('master') && !opponentStepDone('master');
    if (status === GameStatus.CrewSelect) return myStepDone('crew') && !opponentStepDone('crew');
    return false;
});

// Turn change banner
const turnBanner = ref(false);
const lastSeenTurn = ref(props.game.current_turn);
let turnBannerTimer: ReturnType<typeof setTimeout> | null = null;
watch(
    () => props.game.current_turn,
    (turn) => {
        if (props.game.status === GameStatus.InProgress && turn > lastSeenTurn.value) {
            lastSeenTurn.value = turn;
            turnBanner.value = true;
            if (turnBannerTimer) clearTimeout(turnBannerTimer);
            turnBannerTimer = setTimeout(() => (turnBanner.value = false), TURN_BANNER_VISIBLE_MS);
        }
    },
);

const abandonDialogOpen = ref(false);

// Observation mode
const observeLinkCopied = ref(false);
const spectateOn = ref(props.game.is_observable);
const spectateQR = ref('');
watch(
    () => props.game.is_observable,
    (v) => {
        spectateOn.value = v;
    },
);
// Clear local selection refs when the game status advances (e.g. opponent
// submits via broadcast → Inertia reload with preserveState). Without this,
// the old highlighted card/selection stays rendered even though the game
// has moved past that step.
watch(
    () => props.game.status,
    () => {
        selectedFaction.value = null;
        selectedMasterName.value = null;
        selectedOpponentFaction.value = null;
        selectedOpponentMasterName.value = null;
    },
);

// After any Inertia reload, re-apply pending health values that haven't been
// confirmed by the server yet. This prevents broadcast-triggered reloads from
// reverting optimistic health changes mid-click-stream.
watch(
    () => props.game.players,
    () => {
        if (pendingHealth.size === 0) return;
        for (const p of props.game.players) {
            for (const m of p.crew_members ?? []) {
                const pending = pendingHealth.get(m.id);
                if (pending !== undefined && m.current_health !== pending) {
                    m.current_health = pending;
                }
            }
        }
    },
    { deep: false },
);

const generateSpectateQR = async () => {
    if (!spectateQR.value) {
        const QRCode = (await import('qrcode')).default;
        spectateQR.value = await QRCode.toDataURL(route('games.observe', props.game.uuid), {
            width: 200,
            margin: 2,
            color: { dark: '#000000', light: '#ffffff' },
        });
    }
};

const syncSpectateToggle = (value: boolean) => {
    spectateOn.value = value;
    if (value) generateSpectateQR();
    router.post(route('games.toggle_observable', props.game.uuid), {}, { only: ['game'], preserveScroll: true, preserveState: true });
};

// Soulstone award banner
const soulstoneAwardSlot = ref<number | null>(null);
const soulstoneAwardName = ref('');
let soulstoneAwardTimer: ReturnType<typeof setTimeout>;
const showSoulstoneAward = (slot: number, memberName: string) => {
    soulstoneAwardSlot.value = slot;
    soulstoneAwardName.value = memberName;
    clearTimeout(soulstoneAwardTimer);
    soulstoneAwardTimer = setTimeout(() => {
        soulstoneAwardSlot.value = null;
    }, 4000);
};

// Game settings
const autoSoulstoneOnKill = ref(props.game.settings?.auto_soulstone_on_kill !== false); // Default true
watch(
    () => props.game.settings,
    (s) => {
        autoSoulstoneOnKill.value = s?.auto_soulstone_on_kill !== false;
    },
);
const saveGameSetting = (key: string, value: any) => {
    router.patch(
        route('games.settings.update', props.game.uuid),
        { settings: { [key]: value } },
        { only: ['game'], preserveScroll: true, preserveState: true },
    );
};

// QR generation on settings open is registered after gameSettingsOpen ref (below)
const copyObserveLink = async () => {
    await navigator.clipboard.writeText(route('games.observe', props.game.uuid));
    observeLinkCopied.value = true;
    setTimeout(() => (observeLinkCopied.value = false), 2000);
};
// QR Code
const qrDialogOpen = ref(false);
const qrDialogUrl = ref('');
const qrDialogTitle = ref('');

const openQR = (url: string, title: string) => {
    qrDialogUrl.value = url;
    qrDialogTitle.value = title;
    qrDialogOpen.value = true;
};

const completeDialogOpen = ref(false);
const gameSettingsOpen = ref(false);
watch(gameSettingsOpen, (open) => {
    if (open && spectateOn.value) generateSpectateQR();
});
const executeAbandon = async () => {
    abandonDialogOpen.value = false;
    await gameApi.post(route('games.abandon', props.game.uuid));
    router.visit(route('games.index'));
};

// ─── Crew References ───
// References are reactive: the server augments each player's references with
// general + Strategy tokens and the player's LIVE crew members (so summoned/
// added models bring their tokens in — dynamic, never removed). Prefer
// player.references; crew_build.references is the fallback for older payloads.
const myReferences = computed<any>(() => myPlayer.value?.references ?? myPlayer.value?.crew_build?.references ?? null);
const opponentReferences = computed<any>(() => opponent.value?.references ?? opponent.value?.crew_build?.references ?? null);

// Kept as no-ops for the <details> @toggle bindings — references load reactively.
const toggleMyRefs = () => {};
const toggleOpponentRefs = () => {};

// ── Quick Add: attach a token to multiple of your living models at once ──
const quickAddOpen = ref(false);
const quickAddToken = ref<{ id: number; name: string } | null>(null);
const quickAddMemberIds = ref<number[]>([]);

const openQuickAddToken = (token: { id: number; name: string }) => {
    quickAddToken.value = token;
    quickAddMemberIds.value = [];
    quickAddOpen.value = true;
};

const toggleQuickAddMember = (id: number) => {
    const i = quickAddMemberIds.value.indexOf(id);
    if (i === -1) quickAddMemberIds.value.push(id);
    else quickAddMemberIds.value.splice(i, 1);
};

const submitQuickAdd = async () => {
    if (!quickAddToken.value || quickAddMemberIds.value.length === 0) return;
    await gameApi.post(route('games.play.crew.tokens.bulk', props.game.uuid), {
        token_id: quickAddToken.value.id,
        member_ids: quickAddMemberIds.value,
    });
    quickAddOpen.value = false;
    router.reload({ only: ['game'], preserveScroll: true });
};

// ─── Leave-confirmation guard for in-progress games ───
//
// We use exactly two mechanisms — no more — to keep behavior predictable:
//
// 1. Inertia's `before` hook handles every SPA navigation (Link clicks,
//    programmatic router.visit, AND browser back/forward between Inertia
//    pages). When triggered, we cancel and show our custom dialog.
//
// 2. `beforeunload` handles non-SPA exits the browser owns: closing the
//    tab, hard refreshes, navigating to an external URL. The browser
//    shows its native confirm — we can't customize that, but it's the
//    only reliable way to catch true page exits.
//
// We deliberately do NOT push a fake history entry or attach a popstate
// listener — both conflict with Inertia 2.x's own history management,
// which is what caused the inconsistent dialog/native-confirm flicker.
const confirmLeaveOpen = ref(false);
const pendingNavigation = ref<null | (() => void)>(null);
let inertiaBeforeRemover: (() => void) | null = null;

const isGameInProgress = computed(() => props.game.status === GameStatus.InProgress);

const handleBeforeUnload = (e: BeforeUnloadEvent) => {
    if (!isGameInProgress.value) return;
    e.preventDefault();
    e.returnValue = '';
};

const setupLeaveGuard = () => {
    if (inertiaBeforeRemover) return; // already set up
    window.addEventListener('beforeunload', handleBeforeUnload);
    inertiaBeforeRemover = router.on('before', (event) => {
        if (!isGameInProgress.value) return;

        const visit = event.detail.visit;

        // Partial reloads (`only` / `except` keys set) are real-time game state
        // refreshes — summon, kill, token add, broadcast-triggered sync, etc.
        // They never navigate away; always let them through regardless of URL.
        if ((visit.only?.length ?? 0) > 0 || (visit.except?.length ?? 0) > 0) return;

        // Same-page navigation — e.g. a Link click pointing at this exact game
        // page, or an Inertia-driven query-string update. Pathname-only compare
        // so hash fragments and trailing slashes don't falsely trigger the guard.
        const targetUrl = new URL(visit.url.toString(), window.location.origin);
        if (targetUrl.pathname === window.location.pathname) return;

        event.preventDefault();
        pendingNavigation.value = () => {
            teardownLeaveGuard();
            router.visit(targetUrl.toString(), { method: visit.method });
        };
        confirmLeaveOpen.value = true;
    });
};

const teardownLeaveGuard = () => {
    window.removeEventListener('beforeunload', handleBeforeUnload);
    if (inertiaBeforeRemover) {
        inertiaBeforeRemover();
        inertiaBeforeRemover = null;
    }
};

const cancelLeave = () => {
    pendingNavigation.value = null;
    confirmLeaveOpen.value = false;
};

const confirmLeave = () => {
    confirmLeaveOpen.value = false;
    if (pendingNavigation.value) {
        const fn = pendingNavigation.value;
        pendingNavigation.value = null;
        fn();
    }
};

// When the tab regains focus, refresh `auth` shared data so a sign-in that
// happened in a different tab — e.g. user clicked a join link, was bounced
// to login in another tab, came back here — gets reflected. Without this,
// `auth.user` and other auth-derived UI state stay frozen at the initial
// (anonymous) Blade render until the user hard-refreshes, making save/join
// actions non-functional.
const onAuthVisibilityChange = () => {
    if (typeof document === 'undefined' || document.visibilityState !== 'visible') return;
    router.reload({ only: ['auth'], preserveScroll: true, preserveState: true });
};

onMounted(() => {
    if (isGameInProgress.value) {
        setupLeaveGuard();
    }
    document.addEventListener('visibilitychange', onAuthVisibilityChange);
});

// Watch in case status changes (e.g., game completed mid-session)
watch(isGameInProgress, (active) => {
    if (active) {
        setupLeaveGuard();
    } else {
        teardownLeaveGuard();
    }
});

onUnmounted(() => {
    teardownLeaveGuard();
    document.removeEventListener('visibilitychange', onAuthVisibilityChange);
});

// ─── Gameplay ───
// Three-tier responsive gameplay layout:
//   • Mobile (< md): 3 single tabs (scenario / my-crew / opponent)
//   • Tablet (md → xl-1): 2 tabs (Game / Crews-both-side-by-side) — 3 cols too
//     cramped at 1024–1279px, 1 col wastes a lot of horizontal space.
//   • Desktop (xl+): 3 columns, no tabs.
//
// Single source of truth is `gameplayTab`; the mobile and tablet selectors
// read/write through computed proxies so the active state stays consistent
// across breakpoint changes (e.g. rotating a tablet to portrait).
type GameplayTab = 'scenario' | 'my-crew' | 'opponent' | 'crews';
const gameplayTab = ref<GameplayTab>('my-crew');
const scenarioCollapsed = ref(false);
const schemeHidden = ref(false);

/** Mobile selector: 'crews' has no mobile representation → fall back to 'my-crew'. */
const mobileGameplayTab = computed<'scenario' | 'my-crew' | 'opponent'>({
    get: () => (gameplayTab.value === 'crews' ? 'my-crew' : gameplayTab.value),
    set: (v) => {
        gameplayTab.value = v;
    },
});

/** Tablet selector: anything that isn't 'scenario' is treated as the merged 'crews' tab. */
const tabletGameplayTab = computed<'scenario' | 'crews'>({
    get: () => (gameplayTab.value === 'scenario' ? 'scenario' : 'crews'),
    set: (v) => {
        gameplayTab.value = v;
    },
});

/**
 * Visibility class for a gameplay column. At xl+ every column is always
 * visible; below xl we follow the active tab. 'crews' mode shows both crew
 * columns at tablet+ (md) but neither at mobile — mobile's own selector
 * handles the per-column split.
 */
const gameplayColumnClass = (column: 'scenario' | 'my-crew' | 'opponent'): string => {
    if (column === 'scenario') {
        return gameplayTab.value === 'scenario' ? '' : 'hidden xl:block';
    }
    // crew columns
    if (gameplayTab.value === column) return '';
    if (gameplayTab.value === 'crews') return 'hidden md:block';
    return 'hidden xl:block';
};

// Scheme notes
const schemeNote = ref(myPlayer.value?.scheme_notes?.note ?? '');
const schemeSelectedModel = ref(myPlayer.value?.scheme_notes?.selected_model ?? '');
const schemeSelectedMarker = ref(myPlayer.value?.scheme_notes?.selected_marker ?? '');
const schemeTerrainNote = ref(myPlayer.value?.scheme_notes?.terrain_note ?? '');
let schemeNotesDebounce: ReturnType<typeof setTimeout>;
const saveSchemeNotes = () => {
    clearTimeout(schemeNotesDebounce);
    schemeNotesDebounce = setTimeout(() => {
        gameApi.patch(route('games.play.scheme-notes', props.game.uuid), {
            scheme_notes: {
                note: schemeNote.value || null,
                selected_model: schemeSelectedModel.value || null,
                selected_marker: schemeSelectedMarker.value || null,
                terrain_note: schemeTerrainNote.value || null,
            },
        });
    }, 800);
};

const currentSchemeRequirements = computed(() => findScheme(myDisplaySchemeId.value)?.requirements ?? []);
const schemeModelReq = computed(() => currentSchemeRequirements.value.find((r: any) => r.type === 'select_model') ?? null);
const schemeHasMarkerReq = computed(() => currentSchemeRequirements.value.some((r: any) => r.type === 'select_marker'));
const schemeHasTerrainReq = computed(() => currentSchemeRequirements.value.some((r: any) => r.type === 'terrain_note'));

const modelReqLabel = computed(() => {
    const req = schemeModelReq.value;
    if (!req) return '';
    const parts: string[] = [];
    if (req.unique) parts.push('Unique');
    parts.push(req.allegiance === 'friendly' ? 'Friendly' : 'Enemy');
    parts.push('Model');
    if (req.cost_operator && req.cost_value != null) {
        parts.push(`(Cost ${req.cost_operator} ${req.cost_value})`);
    }
    return parts.join(' ');
});

const schemeModelOptions = computed(() => {
    const req = schemeModelReq.value;
    if (!req) return [];

    // Pick from enemy or friendly crew — include all models (even killed) since selection is made before the turn
    const pool = req.allegiance === 'friendly' ? [...(myPlayer.value?.crew_members ?? [])] : [...(opponent.value?.crew_members ?? [])];

    return pool.filter((m: any) => {
        // Unique = not minion, not peon
        if (req.unique && (m.station === 'minion' || m.station === 'peon')) return false;
        // Cost filter
        if (req.cost_operator && req.cost_value != null && m.cost != null) {
            const cost = m.cost as number;
            const target = req.cost_value as number;
            if (req.cost_operator === '>' && !(cost > target)) return false;
            if (req.cost_operator === '<' && !(cost < target)) return false;
            if (req.cost_operator === '>=' && !(cost >= target)) return false;
            if (req.cost_operator === '<=' && !(cost <= target)) return false;
        }
        return true;
    });
});

// Scheme notes lock: editable until the current turn is submitted. Solo
// players can edit either side's notes freely during a turn (no concept
// of opponent-submitting in solo).
const schemeNotesLocked = computed(() => !isSolo.value && !!myPlayer.value?.is_turn_complete);

// Sync scheme notes from props when they change (e.g. after reload)
watch(
    () => myPlayer.value?.scheme_notes,
    (notes) => {
        schemeNote.value = notes?.note ?? '';
        schemeSelectedModel.value = notes?.selected_model ?? '';
        schemeSelectedMarker.value = notes?.selected_marker ?? '';
        schemeTerrainNote.value = notes?.terrain_note ?? '';
    },
);

// Card preview drawers
const crewMemberDrawerOpen = ref(false);
const previewMember = ref<any>(null);
const cardFullscreenOpen = ref(false);
const cardFullscreenSrc = ref<string | null>(null);
const cardFullscreenBackSrc = ref<string | null>(null);
const cardFullscreenTitle = ref<string | null>(null);
const openCardFullscreen = (payload: { src: string; backSrc?: string | null; title?: string | null }) => {
    cardFullscreenSrc.value = payload.src;
    cardFullscreenBackSrc.value = payload.backSrc ?? null;
    cardFullscreenTitle.value = payload.title ?? null;
    cardFullscreenOpen.value = true;
};
const upgradeDrawerOpen = ref(false);
const previewUpgrade = ref<any>(null);

const openMemberPreview = (member: any) => {
    if (!member.front_image && !member.back_image) return;
    previewMember.value = member;
    crewMemberDrawerOpen.value = true;
    // Only load sculpts for own crew members (or both in solo mode)
    const isOwnMember = isSolo.value || myPlayer.value?.crew_members?.some((m: any) => m.id === member.id);
    if (!isObserver.value && isOwnMember) loadMemberMiniatures(member);
};

const updateSoulstonePool = (delta: number) => {
    const current = myPlayer.value?.soulstone_pool ?? 0;
    const newVal = Math.max(0, current + delta);
    postPlay(route('games.play.soulstones', props.game.uuid), 'PATCH', { soulstone_pool: newVal });
};

// Bonanza VP — event-driven scoring (kill = +3, damage at max HP = +1, etc).
// Server clamps to a minimum of 0 to honor the rules' "to a minimum of 0 Total VP".
const adjustBonanzaVp = (delta: number, slot?: number) => {
    const body: Record<string, number> = { delta };
    if (slot) body.slot = slot;
    postPlay(route('games.play.bonanza_vp', props.game.uuid), 'PATCH', body);
};

// Bonanza turn advance — no per-turn scoring panel, just bump the counter.
// Hitting max_turns auto-finalizes the game (is_tie=true, no winner declared).
const advanceBonanzaTurn = async () => {
    if (props.game.current_turn >= props.game.max_turns) {
        const ok = await confirmDialog({
            title: 'End game?',
            message: `This is the last turn (${props.game.max_turns}). Advancing will finalize the game with your current VP — you can't undo this.`,
            confirmLabel: 'End game',
        });
        if (!ok) return;
    }
    const data = await postPlay(route('games.play.bonanza_next_turn', props.game.uuid));
    showEndOfTurnUndoToast(data?.removed_tokens);
};

// ── Bonanza Loot Deck ──────────────────────────────────────────────────────
// State for the post-draw side-picker dialog and the post-Yoink side-picker
// dialog. Both share a single dialog (the picker UI is identical) keyed by
// mode + payload so we don't duplicate the rendering.
// `attach` = post-random-draw flow (card has been popped from deck)
// `select` = solo-only choose-your-loot flow (card is still in the pool, the
//            server will remove it via the /loot/select endpoint)
// `yoink`  = claim a dropped marker
type LootSidePickerMode =
    | { type: 'attach'; card: LootCardSummary }
    | { type: 'select'; card: LootCardSummary }
    | { type: 'yoink'; marker: LootMarker; card: LootCardSummary };
const lootSidePicker = ref<LootSidePickerMode | null>(null);
const lootSidePickerMemberId = ref<number | null>(null);

// Controls the new "Select a loot card" picker dialog (solo mode).
const lootCardSelectorOpen = ref(false);
const lootCardSelectorSearch = ref('');

const lootDeckSize = computed(() => props.game.loot_state?.deck?.length ?? 0);
const lootDiscardSize = computed(() => props.game.loot_state?.discard?.length ?? 0);
const lootMarkers = computed<LootMarker[]>(() => props.game.loot_state?.dropped_markers ?? []);

const lootCardCatalog = computed<Map<number, LootCardSummary>>(() => {
    const map = new Map<number, LootCardSummary>();
    for (const c of props.loot_card_catalog ?? []) map.set(c.id, c);
    return map;
});
const lootCardById = (id: number): LootCardSummary | null => lootCardCatalog.value.get(id) ?? null;

const lootCardSuitIcon = (suit: string | null | undefined): string | null => {
    const s = (suit ?? '').toLowerCase();
    return ['crow', 'mask', 'ram', 'tome'].includes(s) ? s : null;
};

// In solo, the creator can attach to either side. In a duel, only your own.
// Typed as `any[]` because Show.vue has two `CrewMember` interface declarations
// that shadow each other — the existing code works around it by sticking to
// `any` for crew-member computeds, and we follow that pattern.
const attachableMembers = computed<any[]>(() => {
    if (isSolo.value) {
        return props.game.players.flatMap((p) => p.crew_members ?? []).filter((m) => !m.is_killed);
    }
    return (myPlayer.value?.crew_members ?? []).filter((m) => !m.is_killed);
});

const drawLoot = async () => {
    try {
        const res = await gameApi.post(route('games.play.loot.draw', props.game.uuid));
        if (!res.ok) {
            showError((res.data.error as string) ?? 'Could not draw a loot card.');
            return;
        }
        const data = res.data as any;
        // Open the side-picker; pre-select the only living member if just one.
        lootSidePicker.value = { type: 'attach', card: data.card };
        const candidates = attachableMembers.value;
        lootSidePickerMemberId.value = candidates.length === 1 ? candidates[0].id : null;
        router.reload({ only: ['game'], preserveScroll: true });
    } catch {
        showError('Network error. Please check your connection.');
    }
};

const submitLootSide = async (side: 'a' | 'b') => {
    const picker = lootSidePicker.value;
    const memberId = lootSidePickerMemberId.value;
    if (!picker || !memberId) {
        showError('Pick a model to receive the loot.');
        return;
    }
    if (picker.type === 'attach') {
        await postPlay(route('games.play.loot.attach', props.game.uuid), 'POST', {
            game_crew_member_id: memberId,
            loot_card_id: picker.card.id,
            side,
        });
    } else if (picker.type === 'select') {
        await postPlay(route('games.play.loot.select', props.game.uuid), 'POST', {
            game_crew_member_id: memberId,
            loot_card_id: picker.card.id,
            side,
        });
    } else {
        await postPlay(route('games.play.loot.yoink', props.game.uuid), 'POST', {
            game_crew_member_id: memberId,
            marker_id: picker.marker.id,
            side,
        });
    }
    lootSidePicker.value = null;
    lootSidePickerMemberId.value = null;
};

const openLootCardSelector = () => {
    lootCardSelectorOpen.value = true;
    lootCardSelectorSearch.value = '';
};

const pickLootCardForSelect = (card: LootCardSummary) => {
    lootCardSelectorOpen.value = false;
    lootSidePicker.value = { type: 'select', card };
    const candidates = attachableMembers.value;
    lootSidePickerMemberId.value = candidates.length === 1 ? candidates[0].id : null;
};

// Solo doesn't enforce deck-tracking — show every catalog card so the user
// can grab any of the 54. The server still 422s if the card is already in
// play. When multiplayer lands, swap this back to the pool-aware filter.
const availableLootCards = computed<LootCardSummary[]>(() => {
    const search = lootCardSelectorSearch.value.trim().toLowerCase();
    const filter = lootCardSelectorSuit.value;
    return (props.loot_card_catalog ?? [])
        .filter((c) => (filter === 'all' ? true : c.suit === filter))
        .filter((c) => {
            if (!search) return true;
            const hay = `${c.name} ${c.value_label ?? ''}`.toLowerCase();
            return hay.includes(search);
        })
        .sort((a, b) => {
            const suitOrder = ['crow', 'mask', 'ram', 'tome', 'joker'];
            const sa = suitOrder.indexOf(a.suit ?? '');
            const sb = suitOrder.indexOf(b.suit ?? '');
            if (sa !== sb) return sa - sb;
            return (a.value ?? 0) - (b.value ?? 0);
        });
});

// Suit filter for the select dialog.
const lootCardSelectorSuit = ref<'all' | 'crow' | 'mask' | 'ram' | 'tome' | 'joker'>('all');

const lootSuitClass = (suit: string): string => {
    switch (suit.toLowerCase()) {
        case 'crow':
            return 'border-green-500/50 bg-green-500/10 text-green-700 dark:text-green-300';
        case 'mask':
            return 'border-purple-500/50 bg-purple-500/10 text-purple-700 dark:text-purple-300';
        case 'ram':
            return 'border-red-500/50 bg-red-500/10 text-red-700 dark:text-red-300';
        case 'tome':
            return 'border-blue-500/50 bg-blue-500/10 text-blue-700 dark:text-blue-300';
        case 'joker':
            return 'border-amber-500/50 bg-amber-500/10 text-amber-700 dark:text-amber-300';
        default:
            return 'border-input bg-background/60 text-foreground';
    }
};

const openYoinkPicker = (marker: LootMarker) => {
    const card = lootCardById(marker.card_id);
    if (!card) {
        showError('Loot card data not loaded.');
        return;
    }
    lootSidePicker.value = { type: 'yoink', marker, card };
    const candidates = attachableMembers.value;
    lootSidePickerMemberId.value = candidates.length === 1 ? candidates[0].id : null;
};

const closeLootPicker = () => {
    lootSidePicker.value = null;
    lootSidePickerMemberId.value = null;
};

const openUpgradePreview = (upgrade: any) => {
    if (!upgrade.front_image) return;
    previewUpgrade.value = upgrade;
    upgradeDrawerOpen.value = true;
};

const myCrewUpgrades = computed(() => {
    // Bonanza falls back to the keyword-aggregated list because the picked
    // character is usually a non-master without direct crew upgrades. The
    // prop is populated server-side from the character's keywords.
    if (isBonanza.value) return props.bonanza_crew_upgrades ?? [];
    return myPlayer.value?.master?.crew_upgrades ?? [];
});
const opponentCrewUpgrades = computed(() => opponent.value?.master?.crew_upgrades ?? []);
const myActiveUpgradeId = computed(() => myPlayer.value?.active_crew_upgrade_id ?? myPlayer.value?.crew_build?.crew_upgrade_id ?? null);
const opponentActiveUpgradeId = computed(() => opponent.value?.active_crew_upgrade_id ?? opponent.value?.crew_build?.crew_upgrade_id ?? null);
const myUpgradeMode = computed(() => myPlayer.value?.master?.crew_upgrade_mode ?? 'select_one');
const opponentUpgradeMode = computed(() => opponent.value?.master?.crew_upgrade_mode ?? 'select_one');

const swapCrewUpgrade = async (upgradeId: number, slot?: number) => {
    const payload: Record<string, any> = { active_crew_upgrade_id: upgradeId };
    if (slot) payload.slot = slot;
    postPlay(route('games.play.crew-upgrade', props.game.uuid), 'PATCH', payload);
};

const myCrewMembers = computed(() => myPlayer.value?.crew_members?.filter((m) => !m.is_killed) ?? []);
const myKilledMembers = computed(() => myPlayer.value?.crew_members?.filter((m) => m.is_killed) ?? []);
const opponentCrewMembers = computed(() => opponent.value?.crew_members?.filter((m) => !m.is_killed) ?? []);
const opponentKilledMembers = computed(() => opponent.value?.crew_members?.filter((m) => m.is_killed) ?? []);

// Inline card preview — track expanded members per crew via Set
const expandedMyCards = ref(new Set<number>());
const expandedOpponentCards = ref(new Set<number>());

// One crew upgrade can be inline-expanded per side at a time (typically
// there's only one active crew upgrade per crew anyway).
const expandedMyCrewUpgradeId = ref<number | null>(null);
const expandedOppCrewUpgradeId = ref<number | null>(null);
const toggleCrewUpgradeExpand = (upgradeId: number, crew: 'my' | 'opponent') => {
    const target = crew === 'my' ? expandedMyCrewUpgradeId : expandedOppCrewUpgradeId;
    target.value = target.value === upgradeId ? null : upgradeId;
};

const toggleInlineCard = (memberId: number, crew: 'my' | 'opponent') => {
    const set = crew === 'my' ? expandedMyCards : expandedOpponentCards;
    if (set.value.has(memberId)) {
        // Close this one
        const next = new Set(set.value);
        next.delete(memberId);
        set.value = next;
    } else {
        // Single-select: close others, open this one
        set.value = new Set([memberId]);
    }
};

// The crew (upgrade) card included in Expand All: prefer the active upgrade,
// else the first one with card art.
const crewUpgradeToExpand = (crew: 'my' | 'opponent'): number | null => {
    const upgrades = crew === 'my' ? myCrewUpgrades.value : opponentCrewUpgrades.value;
    const activeId = crew === 'my' ? myActiveUpgradeId.value : opponentActiveUpgradeId.value;
    const withImage = upgrades.filter((u: any) => u.front_image);
    if (!withImage.length) return null;
    return (withImage.find((u: any) => u.id === activeId) ?? withImage[0]).id;
};

const toggleAllCards = (crew: 'my' | 'opponent') => {
    const members = crew === 'my' ? myCrewMembers : opponentCrewMembers;
    const set = crew === 'my' ? expandedMyCards : expandedOpponentCards;
    const upgradeRef = crew === 'my' ? expandedMyCrewUpgradeId : expandedOppCrewUpgradeId;
    const crewUpgradeId = crewUpgradeToExpand(crew);

    const allWithImages = members.value.filter((m: any) => m.front_image).map((m: any) => m.id);
    const membersExpanded = allWithImages.length === 0 || allWithImages.every((id: number) => set.value.has(id));
    const upgradeExpanded = crewUpgradeId === null || upgradeRef.value === crewUpgradeId;

    if (membersExpanded && upgradeExpanded) {
        set.value = new Set();
        upgradeRef.value = null;
    } else {
        set.value = new Set(allWithImages);
        upgradeRef.value = crewUpgradeId;
    }
};

const allMyCardsExpanded = computed(() => {
    const ids = myCrewMembers.value.filter((m: any) => m.front_image).map((m: any) => m.id);
    const cu = crewUpgradeToExpand('my');
    if (!ids.length && cu === null) return false;
    return ids.every((id: number) => expandedMyCards.value.has(id)) && (cu === null || expandedMyCrewUpgradeId.value === cu);
});

const allOpponentCardsExpanded = computed(() => {
    const ids = opponentCrewMembers.value.filter((m: any) => m.front_image).map((m: any) => m.id);
    const cu = crewUpgradeToExpand('opponent');
    if (!ids.length && cu === null) return false;
    return ids.every((id: number) => expandedOpponentCards.value.has(id)) && (cu === null || expandedOppCrewUpgradeId.value === cu);
});

// ─── All-cards dialog (desktop wide-screen card grid) ───
// Single source of truth for which side's cards we're viewing. The button is
// gated to lg+ in the template since cramming a 4-column card grid into a
// phone is unworkable; the inline card-expand toggle still covers mobile use.
const allCardsDialogOpen = ref(false);
const allCardsSide = ref<'my' | 'opponent'>('my');

interface AllCardsEntry {
    id: string | number;
    title: string;
    subtitle?: string | null;
    front_image: string | null;
    back_image: string | null;
    badge?: string | null;
    badgeTone?: 'amber' | 'red' | 'muted' | 'primary';
}

const buildAllCardsEntries = (members: any[], crewUpgrades: any[], activeUpgradeId: number | null): AllCardsEntry[] => {
    const entries: AllCardsEntry[] = [];

    // Surface the active crew upgrade first — it's the player-level rules card
    // every member references during play. Skip non-active swappable upgrades
    // since only one is in play at a time.
    const activeUpgrade = crewUpgrades.find((u: any) => u.id === activeUpgradeId);
    if (activeUpgrade?.front_image) {
        entries.push({
            id: 'crew-upgrade-' + activeUpgrade.id,
            title: activeUpgrade.name,
            subtitle: 'Crew Upgrade',
            front_image: activeUpgrade.front_image,
            back_image: activeUpgrade.back_image ?? null,
            badge: 'Crew',
            badgeTone: 'amber',
        });
    }

    // Sort so the master(s) appear directly after the crew upgrade — they're
    // the at-a-glance reference for the rest of the crew. `count > 1` masters
    // (pair models) each get their own entry since createCrewMember rolls one
    // GameCrewMember per copy.
    const sorted = [...members].sort((a: any, b: any) => {
        const rank = (m: any) => (m.hiring_category === 'leader' ? 0 : m.hiring_category === 'totem' ? 1 : 2);
        return rank(a) - rank(b);
    });

    for (const m of sorted) {
        if (!m.front_image) continue;
        const isLeader = m.hiring_category === 'leader';
        entries.push({
            id: 'member-' + m.id,
            title: m.display_name,
            subtitle: m.is_summoned ? 'Summoned' : null,
            front_image: m.front_image,
            back_image: m.back_image ?? null,
            badge: m.is_killed ? 'Killed' : isLeader ? 'Master' : null,
            badgeTone: m.is_killed ? 'red' : isLeader ? 'primary' : 'muted',
        });
    }

    return entries;
};

const allCardsEntries = computed<AllCardsEntry[]>(() => {
    if (allCardsSide.value === 'my') {
        return buildAllCardsEntries(myCrewMembers.value, myCrewUpgrades.value, myActiveUpgradeId.value);
    }
    return buildAllCardsEntries(opponentCrewMembers.value, opponentCrewUpgrades.value, opponentActiveUpgradeId.value);
});

const allCardsTitle = computed(() => {
    if (allCardsSide.value === 'my') {
        return `${isObserver.value ? playerName(myPlayer.value) : 'Your Crew'} — All Cards`;
    }
    return `${playerName(opponent.value)} — All Cards`;
});

const openAllCards = (side: 'my' | 'opponent') => {
    allCardsSide.value = side;
    allCardsDialogOpen.value = true;
};

const toast = useToast();
const confirmDialog = useConfirm();
const showError = (msg: string) => toast.error(msg);

const postPlay = async (url: string, method: string = 'POST', body?: Record<string, unknown>) => {
    try {
        const { ok, data } = method === 'PATCH' ? await gameApi.patch(url, body) : await gameApi.post(url, body);
        if (!ok) {
            showError((data.error as string) ?? 'Action failed. Please try again.');
            return;
        }
        router.reload({ only: ['game'], preserveScroll: true });
        return data;
    } catch {
        showError('Network error. Please check your connection.');
    }
};

// ── Auto-removed end-of-turn tokens: surface an Undo toast ──
interface RemovedToken {
    member_id: number;
    member_name: string;
    token_id: number;
    token_name: string;
}

const restoreEndOfTurnTokens = async (removed: RemovedToken[]) => {
    await gameApi.post(route('games.play.crew.tokens.restore', props.game.uuid), {
        tokens: removed.map((r) => ({ member_id: r.member_id, token_id: r.token_id, token_name: r.token_name })),
    });
    router.reload({ only: ['game'], preserveScroll: true });
};

const showEndOfTurnUndoToast = (removed?: RemovedToken[]) => {
    if (!removed?.length) return;
    const counts = removed.reduce<Record<string, number>>((acc, r) => {
        acc[r.token_name] = (acc[r.token_name] ?? 0) + 1;
        return acc;
    }, {});
    const summary = Object.entries(counts)
        .map(([name, n]) => (n > 1 ? `${name} ×${n}` : name))
        .join(', ');
    toast.warning('End-of-turn tokens removed', {
        description: summary,
        action: { label: 'Undo', onClick: () => restoreEndOfTurnTokens(removed) },
    });
};

// Per-member AbortController for health PATCHes. Each click aborts any
// in-flight request for the same member and fires immediately — no debounce
// gap means no window where a broadcast-triggered reload can overwrite the
// optimistic value with stale server data.
const healthAborts = new Map<number, AbortController>();
// Track members with un-confirmed health changes so we can re-apply them
// if an Inertia reload overwrites the local value before the PATCH lands.
const pendingHealth = new Map<number, number>();

// Set health to an absolute target value (clamped to [0, max_health]). Used
// by both the +/- buttons (via updateHealth delta wrapper) and click-to-set
// HP bubbles. Reaching 0 routes through killMember so death-replacement and
// soulstone-award flows fire correctly.
const setHealth = (member: CrewMember, target: number) => {
    const newHealth = Math.max(0, Math.min(member.max_health, target));
    if (newHealth === member.current_health) return;
    member.current_health = newHealth;
    pendingHealth.set(member.id, newHealth);
    if (newHealth === 0) {
        healthAborts.get(member.id)?.abort();
        healthAborts.delete(member.id);
        pendingHealth.delete(member.id);
        killMember(member);
        return;
    }
    healthAborts.get(member.id)?.abort();
    const controller = new AbortController();
    healthAborts.set(member.id, controller);

    // Stays on raw fetch (not gameApi): rapid HP taps abort the in-flight
    // request via controller.signal, which gameApi.patch doesn't model.
    fetch(route('games.play.crew.update', { game: props.game.uuid, gameCrewMember: member.id }), {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json', ...csrfHeaders() },
        body: JSON.stringify({ current_health: newHealth }),
        signal: controller.signal,
    })
        .then((res) => {
            if (healthAborts.get(member.id) === controller) {
                healthAborts.delete(member.id);
                pendingHealth.delete(member.id);
            }
            if (!res.ok) {
                router.reload({ only: ['game'], preserveScroll: true });
            }
        })
        .catch((err) => {
            // AbortError is expected when we cancel a stale request — ignore it.
            if (err?.name === 'AbortError') return;
            if (healthAborts.get(member.id) === controller) {
                healthAborts.delete(member.id);
                pendingHealth.delete(member.id);
            }
            router.reload({ only: ['game'], preserveScroll: true });
        });
};

const updateHealth = (member: CrewMember, delta: number) => {
    setHealth(member, member.current_health + delta);
};

// Click semantics on HP pips, mirroring PowerBarBubbles: tapping pip N sets
// health to N, except tapping the already-active topmost pip (pip ===
// current_health) decrements by 1. Readonly callers (observers, opponent
// in non-solo) pass true to disable.
const onHealthPipClick = (member: CrewMember, pip: number, readonly: boolean) => {
    if (readonly) return;
    if (pip === member.current_health) {
        setHealth(member, pip - 1);
    } else {
        setHealth(member, pip);
    }
};

const toggleActivated = async (member: any) => {
    const oldValue = member.is_activated;
    // Optimistic update for instant UI feedback
    member.is_activated = !member.is_activated;
    const res = await gameApi.patch<{ removed_tokens?: { id: number; name: string }[] }>(
        route('games.play.crew.update', { game: props.game.uuid, gameCrewMember: member.id }),
        { is_activated: member.is_activated },
    );
    if (!res.ok) {
        member.is_activated = oldValue;
        router.reload({ only: ['game'], preserveScroll: true });
        return;
    }
    // End-of-activation tokens fall off as the model activates — reflect the
    // server's removal locally so they disappear without a reload.
    const removed = res.data.removed_tokens ?? [];
    if (removed.length) {
        const removedIds = new Set(removed.map((t) => t.id));
        member.attached_tokens = (member.attached_tokens ?? []).filter((t: any) => !removedIds.has(t.id));
    }
};

// Kill with replacement detection
const replaceOnDeathDialogOpen = ref(false);
const replaceOnDeathReplacements = ref<
    { id: number; display_name: string; count: number; health: number | null; front_image: string | null; selected: boolean }[]
>([]);
const replaceOnDeathSlot = ref<number>(1);
const replaceOnDeathKilledMember = ref<any>(null); // Track who died for soulstone logic
const replaceOnDeathInheritedTokens = ref<any[]>([]);
const replaceOnDeathInheritedUpgrades = ref<any[]>([]);
const replaceOnDeathWasActivated = ref(false);
const hasSelectedReplacements = computed(() => replaceOnDeathReplacements.value.some((r) => r.selected));

const killMember = async (member: any) => {
    // Save state before killing for inheritance
    const killedTokens = [...(member.attached_tokens ?? [])];
    const killedUpgrades = [...(member.attached_upgrades ?? [])];
    const wasActivated = !!member.is_activated;

    // Optimistic UI — mark killed immediately, with rollback on failure.
    const prevHealth = member.current_health;
    member.is_killed = true;
    member.current_health = 0;

    const res = await gameApi.post(route('games.play.crew.kill', { game: props.game.uuid, gameCrewMember: member.id }));
    if (!res.ok) {
        member.is_killed = false;
        member.current_health = prevHealth;
        router.reload({ only: ['game'], preserveScroll: true });
        return;
    }
    const data = res.data as any;

    if (data.replacements?.length) {
        const isMyMember = myPlayer.value?.crew_members?.some((m: any) => m.id === member.id);
        replaceOnDeathSlot.value = isMyMember ? mySlot.value : opponentSlot.value;
        replaceOnDeathInheritedTokens.value = killedTokens;
        replaceOnDeathInheritedUpgrades.value = killedUpgrades;
        replaceOnDeathWasActivated.value = wasActivated;
        replaceOnDeathKilledMember.value = member;
        replaceOnDeathReplacements.value = data.replacements.map((r: any) => ({ ...r, selected: false }));
        replaceOnDeathDialogOpen.value = true;
    } else {
        // Auto soulstone: non-peon, non-summoned, no Summon token → add 1 to crew's pool
        if (autoSoulstoneOnKill.value && member.station !== 'peon' && !member.is_summoned) {
            const hasSummonToken = (member.attached_tokens ?? []).some((t: any) => t.name?.toLowerCase() === 'summon');
            if (!hasSummonToken) {
                const isMyMember = myPlayer.value?.crew_members?.some((m: any) => m.id === member.id);
                const slot = isMyMember ? mySlot.value : opponentSlot.value;
                const player = isMyMember ? myPlayer.value : opponent.value;
                const current = player?.soulstone_pool ?? 0;
                const payload: Record<string, any> = { soulstone_pool: current + 1 };
                if (!isMyMember) payload.slot = opponentSlot.value;
                showSoulstoneAward(slot, member.display_name);
                postPlay(route('games.play.soulstones', props.game.uuid), 'PATCH', payload);
                return; // postPlay handles the reload
            }
        }
        router.reload({ only: ['game'], preserveScroll: true });
    }
};

const replaceOnDeathWarnings = ref<string[]>([]);

const confirmReplaceOnDeath = async () => {
    replaceOnDeathWarnings.value = [];
    let anyAdded = false;
    let inheritanceGiven = false;
    for (const replacement of replaceOnDeathReplacements.value) {
        if (!replacement.selected) continue;
        let hitLimit = false;
        for (let i = 0; i < replacement.count; i++) {
            const body: Record<string, unknown> = {
                character_id: replacement.id,
                is_replacement: true,
                replacement_health: replacement.health ?? null,
                inherited_tokens: !inheritanceGiven ? replaceOnDeathInheritedTokens.value : [],
                inherited_upgrades: !inheritanceGiven ? replaceOnDeathInheritedUpgrades.value : [],
                is_activated: replaceOnDeathWasActivated.value,
            };
            if (isSolo.value) body.slot = replaceOnDeathSlot.value;
            const res = await gameApi.post(route('games.play.crew.summon', props.game.uuid), body);
            if (!res.ok) {
                if (res.data.at_limit) {
                    hitLimit = true;
                    break;
                }
            } else {
                anyAdded = true;
                inheritanceGiven = true;
            }
        }
        if (hitLimit) {
            replaceOnDeathWarnings.value.push(`${replacement.display_name} is at its limit.`);
            // Deselect and mark so user can pick alternatives
            replacement.selected = false;
        }
    }
    if (anyAdded) {
        router.reload({ only: ['game'], preserveScroll: true });
    }
    // Only auto-close if no warnings — let user pick alternatives if some failed
    if (!replaceOnDeathWarnings.value.length) {
        replaceOnDeathDialogOpen.value = false;
        replaceOnDeathKilledMember.value = null;
        replaceOnDeathReplacements.value = [];
    }
};

const dismissReplaceOnDeath = () => {
    // User skipped replacement — model truly died, award soulstone if applicable
    const member = replaceOnDeathKilledMember.value;
    if (member && autoSoulstoneOnKill.value && member.station !== 'peon' && !member.is_summoned) {
        const hasSummonToken = (member.attached_tokens ?? []).some((t: any) => t.name?.toLowerCase() === 'summon');
        if (!hasSummonToken) {
            const isMyMember = myPlayer.value?.crew_members?.some((m: any) => m.id === member.id);
            const slot = isMyMember ? mySlot.value : opponentSlot.value;
            const player = isMyMember ? myPlayer.value : opponent.value;
            const current = player?.soulstone_pool ?? 0;
            const payload: Record<string, any> = { soulstone_pool: current + 1 };
            if (!isMyMember) payload.slot = opponentSlot.value;
            showSoulstoneAward(slot, member.display_name);
            postPlay(route('games.play.soulstones', props.game.uuid), 'PATCH', payload);
        }
    }

    replaceOnDeathDialogOpen.value = false;
    replaceOnDeathKilledMember.value = null;
    replaceOnDeathReplacements.value = [];
    replaceOnDeathInheritedTokens.value = [];
    replaceOnDeathInheritedUpgrades.value = [];
    replaceOnDeathWasActivated.value = false;
    replaceOnDeathWarnings.value = [];
};

const reviveMember = (member: any) => {
    postPlay(route('games.play.crew.revive', { game: props.game.uuid, gameCrewMember: member.id }));
};

// Turn scoring
const strategyPoints = ref(0);
const schemePoints = ref(0);
const nextSchemeId = ref<number | null>(null);
const scoringTurn = ref(false);
const scoringOpponentTurn = ref(false);

const isLastTurn = computed(() => props.game.current_turn >= props.game.max_turns);

// Next scheme requirement fields (for the follow-up scheme the user is switching to)
const nextSchemeModel = ref('');
const nextSchemeMarker = ref('');
const nextSchemeTerrain = ref('');
const nextSchemeReqs = computed(() => {
    if (!nextSchemeId.value) return [];
    return findScheme(nextSchemeId.value)?.requirements ?? [];
});
const nextSchemeModelReq = computed(() => nextSchemeReqs.value.find((r: any) => r.type === 'select_model') ?? null);
const nextSchemeModelLabel = computed(() => {
    const req = nextSchemeModelReq.value;
    if (!req) return '';
    const parts: string[] = [];
    if (req.unique) parts.push('Unique');
    parts.push(req.allegiance === 'friendly' ? 'Friendly' : 'Enemy');
    parts.push('Model');
    if (req.cost_operator && req.cost_value != null) parts.push(`(Cost ${req.cost_operator} ${req.cost_value})`);
    return parts.join(' ');
});
const nextSchemeModelOptions = computed(() => {
    const req = nextSchemeModelReq.value;
    if (!req) return [];
    const pool = req.allegiance === 'friendly' ? [...(myPlayer.value?.crew_members ?? [])] : [...(opponent.value?.crew_members ?? [])];
    return pool.filter((m: any) => {
        if (req.unique && (m.station === 'minion' || m.station === 'peon')) return false;
        if (req.cost_operator && req.cost_value != null && m.cost != null) {
            const cost = m.cost as number;
            const target = req.cost_value as number;
            if (req.cost_operator === '>' && !(cost > target)) return false;
            if (req.cost_operator === '<' && !(cost < target)) return false;
            if (req.cost_operator === '>=' && !(cost >= target)) return false;
            if (req.cost_operator === '<=' && !(cost <= target)) return false;
        }
        return true;
    });
});
// Reset next scheme fields when selection changes
watch(nextSchemeId, () => {
    nextSchemeModel.value = '';
    nextSchemeMarker.value = '';
    nextSchemeTerrain.value = '';
});

// All known schemes (pool + next schemes for lookup)
const allKnownSchemes = computed(() => {
    const map = new Map<number, SchemeData>();
    for (const s of props.all_reachable_schemes) map.set(s.id, s);
    for (const s of props.schemes) map.set(s.id, s);
    for (const s of props.current_schemes) map.set(s.id, s);
    for (const s of props.next_schemes) map.set(s.id, s);
    for (const s of props.opponent_next_schemes) map.set(s.id, s);
    return map;
});
const findScheme = (id: number | null | undefined) => (id ? allKnownSchemes.value.get(id) : undefined);

// Summary helper: find a player's turn data by turn number (avoids repeated .find() in template)
const getPlayerTurn = (player: any, turnNumber: number) => {
    return (player.turns ?? []).find((t: any) => t.turn_number === turnNumber);
};
const currentSchemeScored = computed(() => schemePoints.value > 0);

// In solo mode, after submitting our turn, current_scheme_id is already updated to the next scheme
// but the turn hasn't advanced yet. Show the scheme from this turn's record instead.
const myDisplaySchemeId = computed(() => {
    if (isSolo.value && myPlayer.value?.is_turn_complete) {
        const turnRecord = getPlayerTurn(myPlayer.value, props.game.current_turn);
        if (turnRecord?.scheme_id) return turnRecord.scheme_id;
    }
    return myPlayer.value?.current_scheme_id ?? null;
});

// Strategy: 1/turn + 1 bonus once per game (max 2 any turn). The bonus can
// also be the *only* point scored in a turn (yielding strategy_points = 1
// that draws from the once-per-game pool), so we also honor an explicit
// `strategy_bonus_used` flag set when the user confirmed that case.
const myStrategyBonusUsed = computed(() => {
    return (myPlayer.value?.turns ?? []).some((t: any) => t.strategy_bonus_used || t.strategy_points > 1);
});
// When the user has selected exactly 1 strategy VP, this flag captures
// whether that single point was the once-per-game bonus rather than the
// regular base point. Reset whenever the score moves off 1.
const strategyBonusOnly = ref(false);
watch(strategyPoints, (v) => {
    if (v !== 1) strategyBonusOnly.value = false;
});
const maxStrategyThisTurn = computed(() => (myStrategyBonusUsed.value ? 1 : 2));

// Scheme: max MAX_SCHEME_PER_TURN per turn, max MAX_SCHEME_POOL across game.
const myTotalSchemeScored = computed(() => {
    return (myPlayer.value?.turns ?? []).reduce((sum: number, t: any) => sum + (t.scheme_points ?? 0), 0);
});
const maxSchemeThisTurn = computed(() => Math.min(MAX_SCHEME_PER_TURN, MAX_SCHEME_POOL - myTotalSchemeScored.value));

// Once the player has scored the full scheme cap (6 VP), no future scheme can
// score additional points, so the "pick a next scheme" requirement is moot.
// Used to relax the Submit Turn button gating below.
const mySchemeCapReached = computed(() => myTotalSchemeScored.value + schemePoints.value >= MAX_SCHEME_POOL);

// Opponent scoring limits (solo)
const opponentStrategyBonusUsed = computed(() => {
    return (opponent.value?.turns ?? []).some((t: any) => t.strategy_bonus_used || t.strategy_points > 1);
});
const opponentStrategyBonusOnly = ref(false);
watch(
    () => opponentStrategyPoints.value,
    (v) => {
        if (v !== 1) opponentStrategyBonusOnly.value = false;
    },
);
const opponentMaxStrategyThisTurn = computed(() => (opponentStrategyBonusUsed.value ? 1 : 2));
const opponentTotalSchemeScored = computed(() => {
    return (opponent.value?.turns ?? []).reduce((sum: number, t: any) => sum + (t.scheme_points ?? 0), 0);
});
const opponentMaxSchemeThisTurn = computed(() => Math.min(MAX_SCHEME_PER_TURN, MAX_SCHEME_POOL - opponentTotalSchemeScored.value));

// ─── Edit previous turn ───
const editTurnDialogOpen = ref(false);
const editTurnTarget = ref<{ playerId: number; slot: number; turnId: number; turnNumber: number } | null>(null);
const editTurnStrategy = ref(0);
const editTurnScheme = ref(0);
const editTurnSubmitting = ref(false);

const previousTurnFor = (player: any) => {
    const prev = props.game.current_turn - 1;
    if (prev < 1) return null;
    return (player.turns ?? []).find((t: any) => t.turn_number === prev) ?? null;
};

const openEditTurn = (player: any) => {
    const turn = previousTurnFor(player);
    if (!turn) return;
    editTurnTarget.value = { playerId: player.id, slot: player.slot, turnId: turn.id, turnNumber: turn.turn_number };
    editTurnStrategy.value = turn.strategy_points ?? 0;
    editTurnScheme.value = turn.scheme_points ?? 0;
    editTurnDialogOpen.value = true;
};

const submitEditTurn = async () => {
    if (!editTurnTarget.value) return;
    editTurnSubmitting.value = true;
    try {
        const res = await gameApi.patch(route('games.play.turns.edit', { game: props.game.uuid, turn: editTurnTarget.value.turnId }), {
            strategy_points: editTurnStrategy.value,
            scheme_points: editTurnScheme.value,
            ...(isSolo.value ? { slot: editTurnTarget.value.slot } : {}),
        });
        if (!res.ok) {
            showError((res.data.error as string) ?? 'Failed to update turn score.');
            return;
        }
        editTurnDialogOpen.value = false;
        router.reload({ only: ['game'], preserveScroll: true });
    } catch {
        showError('Network error. Please check your connection.');
    } finally {
        editTurnSubmitting.value = false;
    }
};

const submitTurnScore = async () => {
    scoringTurn.value = true;

    // Determine scheme action
    const schemeAction = schemePoints.value > 0 ? 'scored' : nextSchemeId.value ? 'discarded' : 'held';

    // Build next scheme notes (saved AFTER the turn is recorded, not before)
    const nextNotes =
        nextSchemeId.value && nextSchemeReqs.value.length
            ? {
                  note: null,
                  selected_model: nextSchemeModel.value || null,
                  selected_marker: nextSchemeMarker.value || null,
                  terrain_note: nextSchemeTerrain.value || null,
              }
            : null;

    let turnData: { removed_tokens?: RemovedToken[] } = {};
    try {
        const res = await gameApi.post<{ removed_tokens?: RemovedToken[] }>(route('games.play.turns.store', props.game.uuid), {
            strategy_points: strategyPoints.value,
            strategy_bonus_used: strategyPoints.value === 2 || (strategyPoints.value === 1 && strategyBonusOnly.value),
            scheme_points: schemePoints.value,
            scheme_action: schemeAction,
            next_scheme_id: nextSchemeId.value,
            next_scheme_notes: nextNotes,
        });
        if (!res.ok) {
            console.error('Turn submit failed:', res.status, res.data);
        } else {
            turnData = res.data;
        }
    } catch (e) {
        console.error('Turn submit error:', e);
    }

    strategyPoints.value = 0;
    strategyBonusOnly.value = false;
    schemePoints.value = 0;
    nextSchemeId.value = null;
    scoringTurn.value = false;
    router.reload({
        only: ['game', 'current_schemes', 'next_schemes', 'opponent_next_schemes', 'opponent_scheme_intel'],
        preserveState: true,
        preserveScroll: true,
    });
    showEndOfTurnUndoToast(turnData?.removed_tokens);
};

// Confirmation dialog for Submit Turn — users were locking in the wrong
// scheme pick or VP on accident. Show a summary first, then POST.
const submitTurnDialogOpen = ref(false);
const submitTurnSchemeAction = computed<'scored' | 'discarded' | 'held'>(() =>
    schemePoints.value > 0 ? 'scored' : nextSchemeId.value ? 'discarded' : 'held',
);
const confirmSubmitTurn = async () => {
    await submitTurnScore();
    submitTurnDialogOpen.value = false;
};

const markGameComplete = async () => {
    // Solo: ask for opponent's final scheme before completing
    if (isSolo.value) {
        const result = await openOppSchemeDialog('end-of-game');
        if (result === 'cancel') return;
        if (oppIdentifiedSchemeId.value) {
            // Set the identified scheme as opponent's current for final scoring
            await gameApi.post(route('games.setup.scheme', props.game.uuid), { scheme_id: oppIdentifiedSchemeId.value, slot: opponentSlot.value });
        }
    }

    const res = await gameApi.post(route('games.play.complete', props.game.uuid));
    if (res.data.game_complete) {
        router.visit(route('games.show', props.game.uuid));
    } else {
        router.reload({ only: ['game'], preserveScroll: true });
    }
};

const cancelGameComplete = () => {
    router.post(route('games.play.cancel_complete', props.game.uuid), {}, { only: ['game'], preserveScroll: true, preserveState: true });
};

// Summon modal
const summonDialogOpen = ref(false);
const summonSearch = ref('');
const summonResults = ref<any[]>([]);
const summonLoading = ref(false);
let summonDebounce: ReturnType<typeof setTimeout>;

const searchSummon = (q: string) => {
    summonSearch.value = q;
    clearTimeout(summonDebounce);
    if (q.length < 2) {
        summonResults.value = [];
        return;
    }
    summonLoading.value = true;
    summonDebounce = setTimeout(async () => {
        try {
            const res = await fetch(route('api.characters.search') + '?q=' + encodeURIComponent(q));
            if (!res.ok) throw new Error(`status ${res.status}`);
            summonResults.value = await res.json();
        } catch {
            summonResults.value = [];
            toast.error('Character search failed', { description: 'Try again in a moment.' });
        }
        summonLoading.value = false;
    }, 300);
};

// Summon flow: always use the character's base sculpt (first miniature). After
// the server confirms, a transient banner appears in the receiving crew column
// ("X summoned — tap to change sculpt") that opens the member drawer on click.
// We pass the character display_name through so the banner doesn't have to
// wait for the Inertia reload to resolve the new member.
const selectCharacterForSummon = (char: any) => {
    const minis = char.miniatures ?? [];
    summonCharacter(char.id, minis[0]?.id ?? null, char.display_name ?? char.name ?? 'Model');
};

/**
 * Transient "X summoned — tap to change sculpt" banner shown in the relevant
 * crew column after a successful summon/replace. Replaces the previous
 * auto-open-drawer behaviour, which was racey in production (reload
 * sometimes hadn't propagated the new member into the reactive state by
 * the time onSuccess fired, so the drawer never opened).
 *
 * The banner is set immediately after the POST resolves — no dependency on
 * the subsequent reload completing — and stays for BANNER_DURATION_MS or
 * until the user taps it / summons something else.
 */
interface SummonBannerState {
    memberId: number | null; // server's returned member id (null if response didn't include it)
    name: string; // character display_name, captured from the picker selection
    slot: number; // game_player slot the new member lives on, so the banner renders in the right column
}
const summonBanner = ref<SummonBannerState | null>(null);
let summonBannerTimer: ReturnType<typeof setTimeout> | undefined;
const BANNER_DURATION_MS = 6000;

const dismissSummonBanner = () => {
    summonBanner.value = null;
    if (summonBannerTimer) clearTimeout(summonBannerTimer);
};

const showSummonBanner = (state: SummonBannerState) => {
    summonBanner.value = state;
    if (summonBannerTimer) clearTimeout(summonBannerTimer);
    summonBannerTimer = setTimeout(() => {
        summonBanner.value = null;
    }, BANNER_DURATION_MS);
};

const clickSummonBanner = () => {
    if (!summonBanner.value) return;
    const memberId = summonBanner.value.memberId;
    if (memberId !== null) {
        const allMembers = [...(myPlayer.value?.crew_members ?? []), ...(opponent.value?.crew_members ?? [])];
        const member = allMembers.find((m: any) => m.id === memberId);
        if (member) openMemberPreview(member);
    }
    dismissSummonBanner();
};

const summonCharacter = async (characterId: number, miniatureId: number | null = null, displayName = 'Model') => {
    const body: Record<string, unknown> = { character_id: characterId };
    if (miniatureId) body.miniature_id = miniatureId;
    if (isSolo.value) body.slot = summonForSlot.value;

    const res = await gameApi.post(route('games.play.crew.summon', props.game.uuid), body);
    const data = res.data;
    summonDialogOpen.value = false;
    summonSearch.value = '';
    summonResults.value = [];

    // Show the banner regardless of whether the reload has landed yet — we already
    // know the new member's id (server returns it) and which crew column it goes in.
    if (res.ok) {
        const targetSlot = isSolo.value ? summonForSlot.value : (myPlayer.value?.slot ?? 1);
        showSummonBanner({
            memberId: typeof data.member_id === 'number' ? data.member_id : null,
            name: displayName,
            slot: targetSlot,
        });
    }

    router.reload({ only: ['game'], preserveScroll: true });
};

// Replace crew member
const replaceDialogOpen = ref(false);
const replaceMember = ref<any>(null);
const replaceSearch = ref('');
const replaceResults = ref<any[]>([]);
const replaceLoading = ref(false);
let replaceDebounce: ReturnType<typeof setTimeout>;

const openReplace = (member: any) => {
    replaceMember.value = member;
    replaceDialogOpen.value = true;
};

const searchReplace = (q: string) => {
    replaceSearch.value = q;
    clearTimeout(replaceDebounce);
    if (q.length < 2) {
        replaceResults.value = [];
        return;
    }
    replaceLoading.value = true;
    replaceDebounce = setTimeout(async () => {
        try {
            const res = await fetch(route('api.characters.search') + '?q=' + encodeURIComponent(q));
            if (!res.ok) throw new Error(`status ${res.status}`);
            replaceResults.value = await res.json();
        } catch {
            replaceResults.value = [];
            toast.error('Character search failed', { description: 'Try again in a moment.' });
        }
        replaceLoading.value = false;
    }, 300);
};

// Replace flow follows the same simplification as summon: base sculpt, banner.
const selectCharacterForReplace = (char: any) => {
    const minis = char.miniatures ?? [];
    replaceCharacter(char.id, minis[0]?.id ?? null, char.display_name ?? char.name ?? 'Model');
};

const replaceCharacter = async (characterId: number, miniatureId: number | null = null, displayName = 'Model') => {
    if (!replaceMember.value) return;
    const body: Record<string, unknown> = { character_id: characterId };
    if (miniatureId) body.miniature_id = miniatureId;

    // Replace updates the existing crew row in place (same id). The member's
    // slot is derived from its game_player_id → players[] lookup.
    const replacedMemberId = replaceMember.value.id;
    const targetPlayer = props.game.players.find((p) => p.id === replaceMember.value?.game_player_id);
    const targetSlot = targetPlayer?.slot ?? myPlayer.value?.slot ?? 1;

    replaceDialogOpen.value = false;
    replaceSearch.value = '';
    replaceResults.value = [];
    replaceMember.value = null;

    router.post(route('games.play.crew.replace', { game: props.game.uuid, gameCrewMember: replacedMemberId }), body, {
        only: ['game'],
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            showSummonBanner({
                memberId: replacedMemberId,
                name: displayName,
                slot: targetSlot,
            });
        },
    });
};

// Sculpt dropdown in member preview drawer
const memberMiniatures = ref<any[]>([]);
const miniatureCache = new Map<number, any[]>();

const loadMemberMiniatures = async (member: any) => {
    memberMiniatures.value = [];
    if (!member?.character_id) return;
    const cached = miniatureCache.get(member.character_id);
    if (cached) {
        memberMiniatures.value = cached;
        return;
    }
    try {
        const res = await fetch('/api/characters/' + member.character_id + '/miniatures');
        const data = await res.json();
        miniatureCache.set(member.character_id, data);
        memberMiniatures.value = data;
    } catch {
        memberMiniatures.value = [];
    }
};

const onSculptChange = async (miniatureId: string) => {
    if (!previewMember.value) return;
    const mini = memberMiniatures.value.find((m: any) => m.id === Number(miniatureId));
    if (!mini) return;

    // Snapshot prior values so we can roll back if the server rejects.
    const prev = {
        front_image: previewMember.value.front_image,
        back_image: previewMember.value.back_image,
        display_name: previewMember.value.display_name,
    };

    // Optimistic local update — the drawer + crew list re-render instantly.
    previewMember.value.front_image = mini.front_image;
    previewMember.value.back_image = mini.back_image;
    previewMember.value.display_name = mini.display_name;

    // Persist. A sculpt change only affects the three fields we just set locally,
    // so we deliberately DO NOT call router.reload() — the previous reload round-trip
    // was triggering an Inertia visit that the leave-guard / beforeunload machinery
    // could flag while a dialog was in the teardown path. Broadcast (GameCrewMemberUpdated)
    // still fires for the other player in 2P; they get updated via useGameChannel.
    const { ok } = await gameApi.patch(route('games.play.crew.update', { game: props.game.uuid, gameCrewMember: previewMember.value.id }), {
        display_name: mini.display_name,
        front_image: mini.front_image,
        back_image: mini.back_image,
    });

    if (!ok && previewMember.value) {
        // Roll back optimistic state on failure.
        previewMember.value.front_image = prev.front_image;
        previewMember.value.back_image = prev.back_image;
        previewMember.value.display_name = prev.display_name;
    }
};

// Crew member + upgrade notes — both players see the result via the existing
// GameCrewMemberUpdated broadcast; the drawer debounces edits to avoid hammering
// the endpoint on every keystroke.
const onCrewMemberNotesChange = async (payload: {
    notes: string | null;
    attached_upgrades: { id: number; name: string; notes?: string | null }[];
}) => {
    if (!previewMember.value) return;
    // Optimistic local update so the next debounce cycle has the latest.
    previewMember.value.notes = payload.notes;
    previewMember.value.attached_upgrades = payload.attached_upgrades;
    await gameApi.patch(route('games.play.crew.update', { game: props.game.uuid, gameCrewMember: previewMember.value.id }), {
        notes: payload.notes,
        attached_upgrades: payload.attached_upgrades,
    });
};

// Token management (1 of each kind max)
const tokenDialogOpen = ref(false);
const tokenMember = ref<any>(null);
const tokenSearch = ref('');

const openTokenDialog = (member: any) => {
    tokenMember.value = member;
    tokenDialogOpen.value = true;
    tokenSearch.value = '';
};

// Token info drawer
const tokenInfoDrawerOpen = ref(false);
const tokenInfoData = ref<{ id: number; name: string; description: string | null } | null>(null);
const tokenInfoMember = ref<any>(null);

const openTokenInfo = (tokenId: number, member: any) => {
    const token = props.tokens.find((t) => t.id === tokenId);
    if (token) {
        tokenInfoData.value = token;
        tokenInfoMember.value = member;
        tokenInfoDrawerOpen.value = true;
    }
};

const removeTokenFromInfo = async () => {
    if (!tokenInfoMember.value || !tokenInfoData.value) return;
    const current = tokenInfoMember.value.attached_tokens ?? [];
    const updated = current.filter((t: any) => t.id !== tokenInfoData.value!.id);
    await gameApi.patch(route('games.play.crew.update', { game: props.game.uuid, gameCrewMember: tokenInfoMember.value.id }), {
        attached_tokens: updated,
    });
    tokenInfoDrawerOpen.value = false;
    router.reload({ only: ['game'], preserveScroll: true });
};

const quickRemoveToken = async (member: any, tokenId: number) => {
    const current = member.attached_tokens ?? [];
    const updated = current.filter((t: any) => t.id !== tokenId);
    await gameApi.patch(route('games.play.crew.update', { game: props.game.uuid, gameCrewMember: member.id }), { attached_tokens: updated });
    router.reload({ only: ['game'], preserveScroll: true });
};

// Reference characters from loaded references (summons/replaces)
const referenceCharacters = computed(() => {
    const chars: any[] = [];
    const seen = new Set<number>();
    for (const c of myReferences.value?.characters ?? []) {
        if (!seen.has(c.id)) {
            seen.add(c.id);
            chars.push(c);
        }
    }
    for (const c of opponentReferences.value?.characters ?? []) {
        if (!seen.has(c.id)) {
            seen.add(c.id);
            chars.push(c);
        }
    }
    return chars;
});

// Reference token IDs from loaded references
const referenceTokenIds = computed(() => {
    const ids = new Set<number>();
    for (const t of myReferences.value?.tokens ?? []) ids.add(t.id);
    for (const t of opponentReferences.value?.tokens ?? []) ids.add(t.id);
    return ids;
});

const toggleToken = async (tokenId: number, tokenName: string) => {
    if (!tokenMember.value) return;
    const current = tokenMember.value.attached_tokens ?? [];
    const has = current.some((t: any) => t.id === tokenId);
    const updated = has ? current.filter((t: any) => t.id !== tokenId) : [...current, { id: tokenId, name: tokenName }];
    // Optimistic update for responsive dialog
    tokenMember.value = { ...tokenMember.value, attached_tokens: updated };
    await gameApi.patch(route('games.play.crew.update', { game: props.game.uuid, gameCrewMember: tokenMember.value.id }), {
        attached_tokens: updated,
    });
    router.reload({ only: ['game'], preserveScroll: true });
};

const removeToken = async (tokenId: number) => {
    if (!tokenMember.value) return;
    const current = tokenMember.value.attached_tokens ?? [];
    const updated = current.filter((t: any) => t.id !== tokenId);
    tokenMember.value = { ...tokenMember.value, attached_tokens: updated };
    await gameApi.patch(route('games.play.crew.update', { game: props.game.uuid, gameCrewMember: tokenMember.value.id }), {
        attached_tokens: updated,
    });
    router.reload({ only: ['game'], preserveScroll: true });
};

// Character upgrade management
const upgradeDialogOpen = ref(false);
const upgradeMember = ref<any>(null);
const upgradeSearch = ref('');

const openUpgradeDialog = (member: any) => {
    upgradeMember.value = member;
    upgradeDialogOpen.value = true;
    upgradeSearch.value = '';
};

// Reference upgrades for this member (from the references data if loaded)
const memberReferenceUpgradeIds = computed(() => {
    const refs = upgradeMember.value?.__refUpgradeIds;
    if (refs) return new Set(refs);
    // Pull from myReferences or opponentReferences
    const allUpgradeIds = new Set<number>();
    for (const u of myReferences.value?.upgrades ?? []) allUpgradeIds.add(u.id);
    for (const u of opponentReferences.value?.upgrades ?? []) allUpgradeIds.add(u.id);
    return allUpgradeIds;
});

// Count upgrade usage across the crew (excluding the member being edited)
const upgradeUsageCount = (upgradeId: number) => {
    if (!upgradeMember.value) return 0;
    const playerId = upgradeMember.value.game_player_id;
    const memberId = upgradeMember.value.id;
    const allMembers = [...(myPlayer.value?.crew_members ?? []), ...(opponent.value?.crew_members ?? [])];
    return allMembers.filter(
        (m: any) => m.game_player_id === playerId && m.id !== memberId && (m.attached_upgrades ?? []).some((u: any) => u.id === upgradeId),
    ).length;
};

const filteredUpgrades = computed(() => {
    const q = upgradeSearch.value.toLowerCase();
    let list = props.character_upgrades;
    if (q) {
        list = list.filter((u) => u.name.toLowerCase().includes(q));
    }
    // Sort: reference upgrades first
    const refIds = memberReferenceUpgradeIds.value;
    return [...list].sort((a, b) => {
        const aRef = refIds.has(a.id) ? 0 : 1;
        const bRef = refIds.has(b.id) ? 0 : 1;
        return aRef - bRef || a.name.localeCompare(b.name);
    });
});

const toggleUpgrade = async (upgrade: {
    id: number;
    name: string;
    front_image: string | null;
    back_image: string | null;
    power_bar_count?: number | null;
}) => {
    if (!upgradeMember.value) return;
    const current = upgradeMember.value.attached_upgrades ?? [];
    const has = current.some((u: any) => u.id === upgrade.id);
    // When attaching a power-bar upgrade, seed current_power_bar at 0 — most
    // power bars represent a counter that fills up from play (tokens earned,
    // charges generated), not a battery that drains. Players click bubbles to
    // increment as they accumulate. We resolve power_bar_count from the
    // catalog (character_upgrades) so callers don't have to thread it through.
    const catalog = props.character_upgrades.find((u) => u.id === upgrade.id);
    const powerMax = catalog?.power_bar_count ?? upgrade.power_bar_count ?? null;
    const newRow: any = { id: upgrade.id, name: upgrade.name, front_image: upgrade.front_image, back_image: upgrade.back_image };
    if (powerMax !== null && powerMax > 0) {
        newRow.current_power_bar = 0;
    }
    const updated = has ? current.filter((u: any) => u.id !== upgrade.id) : [...current, newRow];
    upgradeMember.value = { ...upgradeMember.value, attached_upgrades: updated };
    await gameApi.patch(route('games.play.crew.update', { game: props.game.uuid, gameCrewMember: upgradeMember.value.id }), {
        attached_upgrades: updated,
    });
    router.reload({ only: ['game'], preserveScroll: true });
};

// Set the current_power_bar on an attached upgrade for a specific crew member.
// We mutate the local JSON, then PATCH the whole attached_upgrades array using
// the existing crew.update endpoint — same path toggleUpgrade uses.
const setMemberUpgradePowerBar = async (member: any, upgradeId: number, value: number) => {
    const list: any[] = (member.attached_upgrades ?? []).map((u: any) => (u.id === upgradeId ? { ...u, current_power_bar: value } : u));
    member.attached_upgrades = list;
    await gameApi.patch(route('games.play.crew.update', { game: props.game.uuid, gameCrewMember: member.id }), { attached_upgrades: list });
};

// Lookup max power bar for a character upgrade — the catalog row is the source
// of truth; the per-game JSON only stores the current counter.
const upgradePowerMax = (upgradeId: number): number => {
    return props.character_upgrades.find((u) => u.id === upgradeId)?.power_bar_count ?? 0;
};

// Crew-level (master) upgrade power bars — keyed by upgrade id on GamePlayer.
const setCrewUpgradePowerBar = async (player: any, upgradeId: number, value: number, slot?: number) => {
    if (!player) return;
    const map: Record<string, number> = { ...(player.crew_upgrade_power_bars ?? {}), [String(upgradeId)]: value };
    player.crew_upgrade_power_bars = map;
    const body: Record<string, any> = { upgrade_id: upgradeId, current_power_bar: value };
    if (slot) body.slot = slot;
    await gameApi.patch(route('games.play.crew-upgrade-power-bar', { game: props.game.uuid }), body);
};

const crewUpgradePowerCurrent = (player: any, upgradeId: number, max: number): number => {
    // Default to empty (0) so the player fills the bar in as they accumulate
    // tokens during the game. Stored value wins once they've clicked any bubble.
    const stored = player?.crew_upgrade_power_bars?.[String(upgradeId)];
    return stored == null ? 0 : Math.min(max, Math.max(0, Number(stored)));
};

const quickRemoveUpgrade = async (member: any, upgradeId: number) => {
    const updated = (member.attached_upgrades ?? []).filter((u: any) => u.id !== upgradeId);
    member.attached_upgrades = updated;
    await gameApi.patch(route('games.play.crew.update', { game: props.game.uuid, gameCrewMember: member.id }), { attached_upgrades: updated });
    router.reload({ only: ['game'], preserveScroll: true });
};

// Upgrade preview from crew list
const previewAttachedUpgrade = ref<any>(null);
const attachedUpgradeDrawerOpen = ref(false);

const openAttachedUpgradePreview = (upgrade: any) => {
    if (!upgrade.front_image) return;
    previewAttachedUpgrade.value = upgrade;
    attachedUpgradeDrawerOpen.value = true;
};

// Crew display helpers

const setupSteps = ['faction', 'master', 'crew', 'scheme'] as const;
const stepLabels: Record<string, string> = { faction: 'Faction', master: 'Master', crew: 'Crew', scheme: 'Scheme' };

// Friendly label for the observer placeholder card during setup phases. Maps
// the full status enum (faction_select, etc) — `stepLabels` above is keyed by
// short step name and used elsewhere for the breadcrumb dots.
const observerSetupLabel = computed(() => {
    switch (props.game.status) {
        case GameStatus.Setup:
            return 'Waiting for opponent';
        case GameStatus.FactionSelect:
            return 'Selecting Factions';
        case GameStatus.MasterSelect:
            return 'Selecting Masters';
        case GameStatus.CrewSelect:
            return 'Building Crews';
        case GameStatus.SchemeSelect:
            return 'Selecting Schemes';
        default:
            return 'Game Setup';
    }
});
const statusOrder = [GameStatus.FactionSelect, GameStatus.MasterSelect, GameStatus.CrewSelect, GameStatus.SchemeSelect, GameStatus.InProgress];
const isPastStep = (step: string) => statusOrder.indexOf(props.game.status) > statusOrder.indexOf(step + '_select');
</script>

<template>
    <SeoHead
        :title="
            game.players.map((p: any) => p.user?.name ?? p.opponent_name ?? `Player ${p.slot}`).join(' vs ') ||
            game.name ||
            `Game - ${game.encounter_size}ss`
        "
        :description="`${game.encounter_size}ss${game.strategy ? ` · ${game.strategy.name}` : ''} · ${
            game.status === GameStatus.Completed
                ? 'Completed'
                : game.status === GameStatus.Abandoned
                  ? 'Abandoned'
                  : game.status === GameStatus.InProgress
                    ? 'In progress'
                    : 'Setup'
        }`"
        :image="game.strategy?.image_url"
    />
    <div class="relative">
        <div
            class="pointer-events-none absolute inset-x-0 top-0 h-64 opacity-[0.07] dark:opacity-[0.12]"
            :style="{ background: 'radial-gradient(ellipse at top, hsl(var(--primary)) 0%, transparent 70%)' }"
        />

        <!-- Gameplay is horizontally dense (tabs, 2/3 columns, etc.). Tailwind's
             `container` utility caps width at each breakpoint (e.g. 768px at md),
             which leaves wasted margin in the 900–1279px tablet range. Use an
             explicit 2xl cap so the layout fills the available width through
             tablet and only starts centering on very wide monitors. -->
        <div class="mx-auto w-full max-w-screen-2xl px-3 pb-8 pt-4 sm:px-4 lg:pt-6">
            <!-- Campaign-context banner. Only renders when this Game is wrapped
                 by a campaign_games row (format=Campaign). Shows CR per crew,
                 the ss-pool bonus owed to the lower-rated side, and links back
                 to each crew's Arsenal Sheet. -->
            <div v-if="campaign_context" class="mb-4 rounded-md border border-primary/40 bg-primary/5 px-3 py-2 text-xs">
                <div class="flex flex-wrap items-center gap-3">
                    <span class="font-medium uppercase text-primary">Campaign</span>
                    <Link
                        v-if="campaign_context.campaign"
                        :href="route('campaigns.show', campaign_context.campaign.id)"
                        class="font-medium hover:underline"
                    >
                        {{ campaign_context.campaign.name }}
                    </Link>
                    <span class="text-muted-foreground">
                        Week {{ campaign_context.week_number }}
                        <span v-if="campaign_context.campaign">/ {{ campaign_context.campaign.length_weeks }}</span>
                        • Encounter {{ campaign_context.encounter_size }}ss
                    </span>
                </div>
                <div class="mt-1 flex flex-wrap items-center gap-3 text-muted-foreground">
                    <span v-if="campaign_context.crew_a">
                        <Link
                            :href="route('campaigns.crews.arsenal.show', [campaign_context.campaign?.id, campaign_context.crew_a.share_code])"
                            class="font-medium hover:underline"
                            >{{ campaign_context.crew_a.name }}</Link
                        >
                        — CR <span class="tabular-nums">{{ campaign_context.cr_a }}</span>
                    </span>
                    <span class="text-foreground/40">vs</span>
                    <span v-if="campaign_context.crew_b">
                        <Link
                            :href="route('campaigns.crews.arsenal.show', [campaign_context.campaign?.id, campaign_context.crew_b.share_code])"
                            class="font-medium hover:underline"
                            >{{ campaign_context.crew_b.name }}</Link
                        >
                        — CR <span class="tabular-nums">{{ campaign_context.cr_b }}</span>
                    </span>
                    <span v-if="campaign_context.ss_bonus_to_lower > 0" class="ml-auto rounded bg-primary/15 px-2 py-0.5 text-primary">
                        +{{ campaign_context.ss_bonus_to_lower }} ss to lower-rated crew
                    </span>
                </div>
            </div>

            <Link
                :href="route('games.index')"
                class="group mb-4 inline-flex items-center gap-1.5 text-sm text-muted-foreground transition-colors hover:text-foreground"
            >
                <ArrowLeft class="h-4 w-4 transition-transform group-hover:-translate-x-1" />
                Back to Games
            </Link>

            <!-- Observer banner -->
            <div v-if="isObserver" class="mb-4 flex items-center gap-2 rounded-lg border border-amber-500/30 bg-amber-500/5 px-4 py-2 text-sm">
                <Eye class="size-4 text-amber-500" />
                <span class="font-medium text-amber-700 dark:text-amber-400">Spectating</span>
                <span class="text-xs text-muted-foreground">You are watching this game in real time. All changes are read-only.</span>
            </div>

            <!-- Bonanza Brawl rules-summary banner. The tracker covers crew creation
                 + manual VP; everything else (Loot deck, initiative, Treasure Pile
                 markers) is dealer-handled off-table. Linked rules let the player
                 jump out for the full text without leaving the tab. -->
            <div
                v-if="isBonanza"
                class="mb-4 flex flex-col gap-1 rounded-lg border border-purple-500/40 bg-purple-500/5 px-4 py-2 text-sm dark:bg-purple-500/10"
            >
                <div class="flex items-center gap-2 font-medium text-purple-700 dark:text-purple-300"><Dices class="size-4" /> Bonanza Brawl</div>
                <p class="text-xs text-muted-foreground">
                    Single-model FFA at 11ss. The Game Tracker handles crew + manual VP — the Dealer manages the Loot deck, initiative draws, and
                    Treasure Pile markers off-table. VP scoring is event-driven (kill = +3, damaging a max-HP enemy = +1, dying = -3 to a minimum of
                    0).
                </p>
                <p class="text-xs">
                    <Link :href="route('tools.bonanza_loot_deck')" class="font-medium text-primary underline-offset-2 hover:underline">
                        Browse the Loot Deck reference →
                    </Link>
                </p>
            </div>

            <!-- Bonanza VP widget — only shown during in-progress Bonanza games.
                 The standard scoreboard is hidden for Bonanza (no strategy/scheme),
                 so this is the primary scoring surface. Quick buttons match the
                 most-common Bonanza VP events. Negative values floor at 0
                 server-side. -->
            <Card
                v-if="isBonanza && game.status === GameStatus.InProgress && !isObserver"
                class="mb-4 border-purple-500/30 bg-purple-500/5 dark:bg-purple-500/10"
            >
                <CardContent class="flex flex-wrap items-center gap-3 p-3">
                    <div class="flex items-center gap-2">
                        <Trophy class="size-4 text-purple-600 dark:text-purple-300" />
                        <span class="text-sm font-medium">Your VP</span>
                        <span class="font-mono text-base font-bold tabular-nums">{{ myPlayer?.total_points ?? 0 }}</span>
                    </div>
                    <div class="flex flex-wrap items-center gap-1.5">
                        <Button size="sm" variant="outline" class="h-7 px-2 text-xs" @click="adjustBonanzaVp(1)">+1</Button>
                        <Button size="sm" variant="outline" class="h-7 px-2 text-xs" @click="adjustBonanzaVp(1)">+1 dmg</Button>
                        <Button size="sm" variant="outline" class="h-7 px-2 text-xs" @click="adjustBonanzaVp(2)">+2 cost up</Button>
                        <Button size="sm" variant="outline" class="h-7 px-2 text-xs" @click="adjustBonanzaVp(3)">+3 kill</Button>
                        <Button size="sm" variant="outline" class="h-7 px-2 text-xs" @click="adjustBonanzaVp(4)">+4 kill+cost</Button>
                        <Button
                            size="sm"
                            variant="outline"
                            class="h-7 border-red-500/50 px-2 text-xs text-red-600 hover:bg-red-500/10 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300"
                            @click="adjustBonanzaVp(-3)"
                        >
                            -3 died
                        </Button>
                        <Button
                            size="sm"
                            variant="outline"
                            class="h-7 border-red-500/50 px-2 text-xs text-red-600 hover:bg-red-500/10 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300"
                            @click="adjustBonanzaVp(-1)"
                        >
                            -1
                        </Button>
                    </div>
                    <div class="ml-auto flex items-center gap-2 border-l pl-3">
                        <span class="text-xs text-muted-foreground">Turn</span>
                        <span class="font-mono text-sm font-bold tabular-nums">{{ game.current_turn }}/{{ game.max_turns }}</span>
                        <Button size="sm" class="h-7 gap-1 px-2 text-xs" @click="advanceBonanzaTurn">
                            <ArrowUpCircle class="size-3.5" />
                            {{ game.current_turn >= game.max_turns ? 'End Game' : 'End Turn' }}
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <!-- Bonanza Loot Deck panel — deck/discard/marker counters + Draw +
                 dropped marker list (with Yoink). The Dealer normally manages
                 the deck off-table, but this panel lets a solo or remote player
                 run it through the tracker. -->
            <Card v-if="isBonanza && game.status === GameStatus.InProgress" class="mb-4 border-amber-500/30 bg-amber-500/5 dark:bg-amber-500/10">
                <CardContent class="flex flex-col gap-3 p-3">
                    <div class="flex flex-wrap items-center gap-3">
                        <div class="flex items-center gap-2">
                            <Layers class="size-4 text-amber-600 dark:text-amber-300" />
                            <span class="text-sm font-medium">Loot Deck</span>
                        </div>
                        <Badge variant="secondary" class="text-xs">Deck {{ lootDeckSize }}</Badge>
                        <Badge variant="secondary" class="text-xs">Discard {{ lootDiscardSize }}</Badge>
                        <Badge variant="secondary" class="text-xs">Markers {{ lootMarkers.length }}</Badge>
                        <div v-if="!isObserver" class="ml-auto flex gap-1.5">
                            <Button
                                v-if="isSolo"
                                size="sm"
                                variant="outline"
                                class="h-7 gap-1 text-xs"
                                :disabled="availableLootCards.length === 0"
                                @click="openLootCardSelector"
                            >
                                <Layers class="size-3.5" /> Select
                            </Button>
                            <Button size="sm" class="h-7 gap-1 text-xs" :disabled="lootDeckSize === 0 && lootDiscardSize === 0" @click="drawLoot">
                                <Dices class="size-3.5" /> Draw
                            </Button>
                        </div>
                    </div>
                    <div v-if="lootMarkers.length" class="flex flex-col gap-1.5 border-t border-amber-500/20 pt-2">
                        <div class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Dropped Loot Markers</div>
                        <div class="flex flex-wrap gap-1.5">
                            <div
                                v-for="marker in lootMarkers"
                                :key="marker.id"
                                class="flex items-center gap-1.5 rounded-md border border-amber-500/30 bg-background/60 px-2 py-1 text-xs"
                            >
                                <Banana class="size-3.5 text-amber-600 dark:text-amber-300" />
                                <span class="font-medium">{{ lootCardById(marker.card_id)?.name ?? `Card #${marker.card_id}` }}</span>
                                <span class="text-muted-foreground">({{ marker.side === 'a' ? 'A' : 'B' }})</span>
                                <Button
                                    v-if="!isObserver"
                                    size="sm"
                                    variant="outline"
                                    class="ml-1 h-5 px-1.5 py-0 text-[10px]"
                                    @click="openYoinkPicker(marker)"
                                >
                                    Yoink
                                </Button>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Loot side-picker dialog: shared between draw → attach and Yoink → attach.
                 Shows both sides of the card, lets the player pick a side and (in solo)
                 a target model. The endpoint enforces one-side-only and policy. -->
            <Dialog :open="lootSidePicker !== null" @update:open="(v) => !v && closeLootPicker()">
                <DialogContent class="max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>
                            <template v-if="lootSidePicker?.type === 'attach'">Loot drawn — pick a side</template>
                            <template v-else-if="lootSidePicker?.type === 'select'">Selected loot — pick a side</template>
                            <template v-else>Yoink loot — pick a side</template>
                        </DialogTitle>
                        <DialogDescription v-if="lootSidePicker">
                            {{ lootSidePicker.card.name
                            }}<span v-if="lootSidePicker.card.value_label" class="ml-2 text-muted-foreground">{{
                                lootSidePicker.card.value_label
                            }}</span>
                        </DialogDescription>
                    </DialogHeader>
                    <div v-if="lootSidePicker" class="flex flex-col gap-4">
                        <div
                            v-if="attachableMembers.length === 0"
                            class="rounded-md border border-amber-500/40 bg-amber-500/10 p-3 text-xs text-amber-700 dark:text-amber-300"
                        >
                            No live models to attach loot to. Add a model to your crew first.
                        </div>
                        <div v-else class="flex flex-col gap-1.5">
                            <label class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Attach to</label>
                            <select v-model.number="lootSidePickerMemberId" class="h-9 rounded-md border border-input bg-background px-2 text-sm">
                                <option :value="null">Select a model…</option>
                                <option v-for="m in attachableMembers" :key="m.id" :value="m.id">{{ m.display_name }}</option>
                            </select>
                        </div>
                        <div class="grid gap-3 md:grid-cols-2">
                            <button
                                type="button"
                                :disabled="!lootSidePickerMemberId"
                                class="flex flex-col gap-1.5 rounded-lg border border-input bg-background/60 p-3 text-left transition hover:border-primary/60 hover:bg-primary/5 disabled:cursor-not-allowed disabled:opacity-50"
                                @click="submitLootSide('a')"
                            >
                                <div class="flex items-center gap-2 font-semibold">
                                    Side A
                                    <span v-if="lootSidePicker.card.title_a" class="font-normal text-muted-foreground">
                                        — {{ lootSidePicker.card.title_a }}
                                    </span>
                                </div>
                                <p v-if="lootSidePicker.card.effect_a" class="whitespace-pre-line text-xs text-muted-foreground">
                                    {{ lootSidePicker.card.effect_a }}
                                </p>
                            </button>
                            <button
                                type="button"
                                :disabled="!lootSidePickerMemberId"
                                class="flex flex-col gap-1.5 rounded-lg border border-input bg-background/60 p-3 text-left transition hover:border-primary/60 hover:bg-primary/5 disabled:cursor-not-allowed disabled:opacity-50"
                                @click="submitLootSide('b')"
                            >
                                <div class="flex items-center gap-2 font-semibold">
                                    Side B
                                    <span v-if="lootSidePicker.card.title_b" class="font-normal text-muted-foreground">
                                        — {{ lootSidePicker.card.title_b }}
                                    </span>
                                </div>
                                <p v-if="lootSidePicker.card.effect_b" class="whitespace-pre-line text-xs text-muted-foreground">
                                    {{ lootSidePicker.card.effect_b }}
                                </p>
                            </button>
                        </div>
                    </div>
                </DialogContent>
            </Dialog>

            <Dialog :open="lootCardSelectorOpen" @update:open="(v: boolean) => (lootCardSelectorOpen = v)">
                <DialogContent class="max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>Select a loot card</DialogTitle>
                        <DialogDescription>
                            Pick a card and choose which side to apply. {{ availableLootCards.length }} of {{ loot_card_catalog?.length ?? 0 }} shown.
                        </DialogDescription>
                    </DialogHeader>
                    <div class="flex flex-col gap-3">
                        <div class="relative">
                            <Search class="pointer-events-none absolute left-2 top-1/2 size-3.5 -translate-y-1/2 text-muted-foreground" />
                            <Input v-model="lootCardSelectorSearch" placeholder="Search by name or number…" class="h-9 pl-7 text-sm" />
                        </div>
                        <div class="flex flex-wrap gap-1">
                            <button
                                v-for="s in ['all', 'crow', 'mask', 'ram', 'tome', 'joker'] as const"
                                :key="s"
                                type="button"
                                class="inline-flex items-center gap-1 rounded border px-2 py-0.5 text-[11px] capitalize transition-colors"
                                :class="
                                    lootCardSelectorSuit === s
                                        ? lootSuitClass(s) + ' font-semibold'
                                        : 'border-border bg-muted/30 text-muted-foreground hover:bg-muted/60'
                                "
                                @click="lootCardSelectorSuit = s"
                            >
                                <GameIcon v-if="lootCardSuitIcon(s)" :type="lootCardSuitIcon(s) as string" class-name="h-3 inline-block" />
                                {{ s }}
                            </button>
                        </div>
                        <div
                            v-if="availableLootCards.length === 0"
                            class="rounded-md border border-dashed bg-muted/30 p-6 text-center text-sm text-muted-foreground"
                        >
                            No cards match your filter.
                        </div>
                        <div v-else class="max-h-[60dvh] overflow-y-auto pr-1">
                            <div class="grid gap-1.5 sm:grid-cols-2">
                                <button
                                    v-for="card in availableLootCards"
                                    :key="card.id"
                                    type="button"
                                    class="flex items-center gap-2 rounded-md border px-2.5 py-2 text-left text-sm transition hover:shadow-sm hover:brightness-110"
                                    :class="lootSuitClass(card.suit ?? 'all')"
                                    @click="pickLootCardForSelect(card)"
                                >
                                    <span
                                        class="border-current/40 inline-flex shrink-0 items-center gap-0.5 rounded border bg-background/40 px-1 font-mono text-[10px] tabular-nums"
                                    >
                                        {{ card.value_label
                                        }}<GameIcon
                                            v-if="lootCardSuitIcon(card.suit)"
                                            :type="lootCardSuitIcon(card.suit) as string"
                                            class-name="h-2.5 inline-block"
                                        />
                                    </span>
                                    <span class="min-w-0 flex-1 truncate font-medium">{{ card.name }}</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </DialogContent>
            </Dialog>

            <!-- Game Header (hidden during gameplay — info is in the Game tab) -->
            <div v-if="game.status !== GameStatus.InProgress" class="mb-6 flex flex-wrap items-center gap-3">
                <Swords class="size-6 text-primary" />
                <h1 class="text-xl font-bold">{{ game.name || game.encounter_size + 'ss Encounter' }}</h1>
                <Badge variant="secondary" class="text-xs">{{ game.season_label }}</Badge>
                <Badge variant="secondary" class="text-xs">{{ game.encounter_size }}ss</Badge>
                <Badge v-if="isSolo" variant="outline" class="text-xs">Solo</Badge>
                <Badge
                    v-if="game.is_observable && !GAME_FINISHED_STATUSES.includes(game.status)"
                    variant="outline"
                    class="border-amber-500/50 text-xs text-amber-600 dark:text-amber-400"
                    >Public</Badge
                >
                <div v-if="canEditScenario" class="ml-auto flex items-center gap-1">
                    <Button variant="ghost" size="sm" class="gap-1" @click="regenerateScenario">
                        <Dices class="size-3.5" />
                        Re-roll
                    </Button>
                    <Button variant="outline" size="sm" class="gap-1" @click="openEditScenario">
                        <Pencil class="size-3.5" />
                        Edit
                    </Button>
                </div>
            </div>

            <!-- Scenario (hidden during gameplay and completed; Bonanza Brawl
                 has no scenario at all) -->
            <div v-if="!isBonanza && game.status !== GameStatus.InProgress && !GAME_FINISHED_STATUSES.includes(game.status)" class="mb-6">
                <!-- Mobile: compact text rows -->
                <div class="space-y-1.5 sm:hidden">
                    <div v-if="deployment" class="flex items-center justify-between rounded-lg border px-3 py-2">
                        <div>
                            <div class="text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">Deployment</div>
                            <div class="text-sm font-medium">{{ deployment.label }}</div>
                        </div>
                        <Button variant="ghost" size="sm" class="h-7 shrink-0 text-xs" @click="deploymentDrawerOpen = true">View</Button>
                    </div>
                    <div v-if="game.strategy" class="flex items-center justify-between rounded-lg border px-3 py-2">
                        <div>
                            <div class="text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">Strategy</div>
                            <div class="text-sm font-medium">{{ game.strategy.name }}</div>
                        </div>
                        <Button variant="ghost" size="sm" class="h-7 shrink-0 text-xs" @click="strategyDrawerOpen = true">View</Button>
                    </div>
                    <div v-if="schemes.length" class="rounded-lg border px-3 py-2">
                        <div class="mb-1 text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">Scheme Pool</div>
                        <div class="flex flex-wrap gap-1.5">
                            <button
                                v-for="scheme in schemes"
                                :key="'m-' + scheme.id"
                                class="rounded-md bg-muted px-2 py-1 text-xs font-medium transition-colors hover:bg-accent"
                                @click="openSchemeDrawer(scheme)"
                            >
                                {{ scheme.name }}
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Desktop: visual card images -->
                <div class="hidden gap-3 sm:grid sm:grid-cols-[1fr_1fr_2fr]">
                    <!-- Deployment -->
                    <div v-if="deployment" class="text-center">
                        <div class="mb-1.5 text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">Deployment</div>
                        <button
                            class="mx-auto block w-full overflow-hidden rounded-lg transition-all hover:-translate-y-0.5 hover:shadow-lg"
                            @click="deploymentDrawerOpen = true"
                        >
                            <img
                                v-if="deployment.image_url"
                                :src="deployment.image_url"
                                :alt="deployment.label"
                                class="w-full rounded-lg"
                                loading="lazy"
                            />
                            <div
                                v-else
                                class="flex aspect-square items-center justify-center rounded-lg border bg-muted text-sm font-medium text-muted-foreground"
                            >
                                {{ deployment.label }}
                            </div>
                        </button>
                        <div class="mt-1.5 text-sm font-medium">{{ deployment.label }}</div>
                    </div>

                    <!-- Strategy -->
                    <div v-if="game.strategy" class="text-center">
                        <div class="mb-1.5 text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">Strategy</div>
                        <button
                            class="mx-auto block w-full overflow-hidden rounded-lg transition-all hover:-translate-y-0.5 hover:shadow-lg"
                            @click="strategyDrawerOpen = true"
                        >
                            <img
                                v-if="game.strategy.image_url"
                                :src="game.strategy.image_url"
                                :alt="game.strategy.name"
                                class="w-full rounded-lg"
                                loading="lazy"
                            />
                            <div
                                v-else
                                class="flex aspect-[550/950] items-center justify-center rounded-lg border bg-muted text-sm font-medium text-muted-foreground"
                            >
                                {{ game.strategy.name }}
                            </div>
                        </button>
                        <div class="mt-1.5 text-sm font-medium">{{ game.strategy.name }}</div>
                    </div>

                    <!-- Scheme Pool -->
                    <div v-if="schemes.length">
                        <div class="mb-1.5 text-center text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">Scheme Pool</div>
                        <div class="grid grid-cols-3 gap-2">
                            <button
                                v-for="scheme in schemes"
                                :key="scheme.id"
                                class="text-center transition-all hover:-translate-y-0.5 hover:shadow-lg"
                                @click="openSchemeDrawer(scheme)"
                            >
                                <img v-if="scheme.image_url" :src="scheme.image_url" :alt="scheme.name" class="w-full rounded-lg" loading="lazy" />
                                <div
                                    v-else
                                    class="flex aspect-[550/950] items-center justify-center rounded-lg border bg-muted px-1 text-xs font-medium text-muted-foreground"
                                >
                                    {{ scheme.name }}
                                </div>
                                <div class="mt-1 text-xs font-medium">{{ scheme.name }}</div>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Players (hidden during gameplay — shown in Game tab) -->
            <h3 v-if="game.status !== GameStatus.InProgress && !GAME_FINISHED_STATUSES.includes(game.status)" class="mb-3 text-lg font-semibold">
                Players
            </h3>
            <Card v-if="game.status !== GameStatus.InProgress && !GAME_FINISHED_STATUSES.includes(game.status)" class="mb-6">
                <CardContent class="p-4 sm:p-6">
                    <!-- Bonanza is personal-tracking only — slot 2 is inert, so the
                         player grid drops to a single column showing just the user. -->
                    <div :class="['grid gap-4', isBonanza ? '' : 'sm:grid-cols-2']">
                        <div
                            v-for="player in isBonanza ? game.players.filter((p) => p.slot === 1) : game.players"
                            :key="player.id"
                            class="flex items-center gap-3 rounded-lg border p-3"
                        >
                            <div class="relative">
                                <FactionLogo v-if="player.faction" :faction="player.faction" class-name="size-8" />
                                <Users v-else class="size-8 text-muted-foreground/30" />
                                <Circle
                                    v-if="player.user"
                                    class="absolute -bottom-0.5 -right-0.5 size-3"
                                    :class="
                                        isUserOnline(player.user.id)
                                            ? 'fill-green-500 text-green-500'
                                            : 'fill-muted-foreground/30 text-muted-foreground/30'
                                    "
                                />
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2">
                                    <!-- Solo opponent: editable name -->
                                    <template v-if="isSolo && !isObserver && !player.user && !GAME_FINISHED_STATUSES.includes(game.status)">
                                        <template v-if="editingOpponentName">
                                            <Input
                                                v-model="opponentNameInput"
                                                class="h-6 w-32 text-sm font-medium"
                                                @keydown.enter="saveOpponentName"
                                                @blur="saveOpponentName"
                                            />
                                        </template>
                                        <button v-else class="flex items-center gap-1 font-medium hover:text-primary" @click="startEditOpponentName">
                                            {{ playerName(player) }}
                                            <Pencil class="size-2.5 text-muted-foreground" />
                                        </button>
                                    </template>
                                    <span v-else class="font-medium">{{ playerName(player) }}</span>
                                    <!-- Attacker/Defender role is meaningless in Bonanza
                                         (there's no opposing player to flip with). -->
                                    <Badge v-if="player.role && !isBonanza" variant="outline" class="px-1 py-0 text-[9px] capitalize">
                                        <Shield v-if="player.role === 'defender'" class="mr-0.5 size-3" />
                                        <ShieldAlert v-else class="mr-0.5 size-3" />
                                        {{ player.role }}
                                    </Badge>
                                </div>
                                <div v-if="player.master_name" class="text-xs text-muted-foreground">
                                    <template
                                        v-if="
                                            isSolo ||
                                            player.user?.id === currentUserId ||
                                            game.status === GameStatus.SchemeSelect ||
                                            game.status === GameStatus.InProgress ||
                                            game.status === GameStatus.Completed
                                        "
                                    >
                                        {{ player.master_name }}
                                    </template>
                                    <template v-else>
                                        {{ player.master_name.split(',')[0] }}
                                    </template>
                                </div>
                                <div
                                    v-if="game.status === GameStatus.InProgress || game.status === GameStatus.Completed"
                                    class="mt-1 text-sm font-bold"
                                >
                                    {{ player.total_points }} VP
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Solo: swap roles button (suppressed in Bonanza — no opponent
                         to swap with in the personal-tracker mode). -->
                    <div v-if="isSolo && !isBonanza && !isObserver && !GAME_FINISHED_STATUSES.includes(game.status)" class="mt-3 flex justify-center">
                        <Button variant="ghost" size="sm" class="gap-1.5 text-xs text-muted-foreground" @click="swapRoles">
                            <RotateCcw class="size-3" />
                            Swap Attacker / Defender
                        </Button>
                    </div>

                    <!-- Result -->
                    <div v-if="game.is_tie" class="mt-4 rounded-lg border border-muted-foreground/30 bg-muted/50 p-3 text-center">
                        <span class="text-sm font-semibold">Draw!</span>
                    </div>
                    <div v-else-if="game.winner" class="mt-4 rounded-lg border border-amber-500/30 bg-amber-500/10 p-3 text-center">
                        <span class="text-sm font-semibold text-amber-700 dark:text-amber-400">{{ game.winner.name }} wins!</span>
                    </div>
                </CardContent>
            </Card>

            <!-- Setup Step Indicator -->
            <div v-if="game.status.includes('select')" class="mb-6">
                <div class="flex items-center justify-center gap-1">
                    <template v-for="(step, idx) in setupSteps" :key="step">
                        <div v-if="idx > 0" class="h-px w-8 bg-border" />
                        <div
                            class="flex size-8 items-center justify-center rounded-full text-xs font-bold"
                            :class="
                                game.status === step + '_select'
                                    ? 'bg-primary text-primary-foreground'
                                    : isPastStep(step)
                                      ? 'bg-green-500 text-white'
                                      : 'bg-muted text-muted-foreground'
                            "
                        >
                            <Check v-if="isPastStep(step)" class="size-4" />
                            <span v-else>{{ idx + 1 }}</span>
                        </div>
                    </template>
                </div>
                <div class="mt-1 flex items-center justify-center gap-1">
                    <template v-for="(step, idx) in setupSteps" :key="step + '-label'">
                        <div v-if="idx > 0" class="w-8" />
                        <span class="w-8 text-center text-[10px] text-muted-foreground">{{ stepLabels[step] }}</span>
                    </template>
                </div>
            </div>

            <!-- ═══ LOBBY ═══ -->
            <GameSetupLobby
                v-if="game.status === GameStatus.Setup && !isSolo && !isObserver"
                :game="game"
                :is-creator="isCreator"
                @open-qr="openQR"
            />

            <!-- Observer placeholder during setup steps. Replaces the
                 interactive lobby/faction/master/crew/scheme pickers so
                 spectators can't see (or click) buttons that aren't theirs
                 to use. -->
            <Card v-if="isObserver && GAME_SETUP_STATUSES.includes(game.status)" class="mb-6 border-amber-500/30 bg-amber-500/5 dark:bg-amber-500/5">
                <CardContent class="p-4 text-center sm:p-6">
                    <Loader2 class="mx-auto mb-3 size-6 animate-spin text-muted-foreground" />
                    <h2 class="mb-1 font-semibold">{{ observerSetupLabel }}</h2>
                    <p class="text-sm text-muted-foreground">
                        Players are setting up the game. The crew list will populate here once they begin play.
                    </p>
                </CardContent>
            </Card>

            <!-- ═══ FACTION SELECT ═══ -->
            <GameFactionSelectPanel
                v-if="game.status === GameStatus.FactionSelect && !isObserver"
                :factions="factions"
                :is-solo="isSolo"
                :submitting="submitting"
                :is-opponent-setup-phase="isOpponentSetupPhase"
                :my-faction="myPlayer?.faction ?? null"
                :faction-step-done="myStepDone('faction')"
                :opponent-faction-step-done="opponentStepDone('faction')"
                v-model:selected-faction="selectedFaction"
                v-model:selected-opponent-faction="selectedOpponentFaction"
                @confirm-faction="
                    postSetup(route('games.setup.faction', game.uuid), { faction: selectedFaction, ...(isSolo ? { slot: mySlot } : {}) })
                "
                @confirm-opponent-faction="selectOpponentFaction"
            />

            <!-- ═══ MASTER SELECT ═══ -->
            <GameMasterSelectPanel
                v-if="game.status === GameStatus.MasterSelect && !isObserver"
                :masters="masters"
                :my-faction="myPlayer?.faction ?? null"
                :opponent-faction="opponentPlayer?.faction ?? null"
                :is-bonanza="isBonanza"
                :is-campaign="isCampaign"
                :is-solo="isSolo"
                :submitting="submitting"
                :my-slot="mySlot"
                :is-opponent-setup-phase="isOpponentSetupPhase"
                :master-step-done="myStepDone('master')"
                :opponent-master-step-done="opponentStepDone('master')"
                :my-master-name="myPlayer?.master_name ?? null"
                v-model:selected-master-name="selectedMasterName"
                v-model:selected-master-title="selectedMasterTitle"
                v-model:selected-opponent-master-name="selectedOpponentMasterName"
                @confirm="(body) => postSetup(route('games.setup.master', game.uuid), body)"
                @confirm-opponent="(name) => postSetup(route('games.setup.master', game.uuid), { master_name: name, slot: opponentSlot })"
            />

            <!-- ═══ CREW SELECT ═══ -->
            <GameCrewSelectPanel
                v-if="game.status === GameStatus.CrewSelect && !isObserver"
                :game="game"
                :my-crews="my_crews"
                :masters="masters"
                :my-player="myPlayer"
                :opponent-player="opponentPlayer"
                :is-solo="isSolo"
                :is-campaign="isCampaign"
                :campaign-arsenal="campaign_arsenal ?? []"
                :submitting="submitting"
                :my-slot="mySlot"
                :opponent-slot="opponentSlot"
                :is-opponent-setup-phase="isOpponentSetupPhase"
                :crew-step-done="myStepDone('crew')"
                :opponent-crew-step-done="opponentStepDone('crew')"
                @confirm="(body) => postSetup(route('games.setup.crew', game.uuid), body)"
                @confirm-campaign-crew="(ids) => postSetup(route('games.setup.campaign-crew', game.uuid), { character_ids: ids })"
                @skip-opponent-crew="onSkipOpponentCrew"
            />

            <!-- ═══ SCHEME SELECT ═══ -->
            <GameSchemeSelectPanel
                v-if="game.status === GameStatus.SchemeSelect && !isObserver"
                :game="game"
                :schemes="schemes"
                :all-markers="all_markers"
                :is-solo="isSolo"
                :my-slot="mySlot"
                :scheme-step-done="myStepDone('scheme')"
                :submitting="submitting"
                :my-player="myPlayer"
                :opponent-player="opponent"
                @open-scheme="openSchemeDrawer"
                @open-member-preview="openMemberPreview"
                @confirm="(payload) => postSetup(route('games.setup.scheme', game.uuid), payload)"
            />

            <!-- ═══ IN PROGRESS ═══ -->
            <template v-if="game.status === GameStatus.InProgress">
                <!-- Mobile: 3-tab switcher (scenario / my crew / opponent).
                     Bonanza skips the opponent tab — the format is a personal
                     tracker so there's no opponent panel to switch to. -->
                <div class="mb-4 md:hidden">
                    <Tabs v-model="mobileGameplayTab">
                        <TabsList :class="['grid w-full', isBonanza ? 'grid-cols-2' : 'grid-cols-3']">
                            <TabsTrigger value="scenario">Game</TabsTrigger>
                            <TabsTrigger value="my-crew">{{ isObserver ? playerName(myPlayer) : 'My Crew' }}</TabsTrigger>
                            <TabsTrigger v-if="!isBonanza" value="opponent">{{ playerName(opponent) }}</TabsTrigger>
                        </TabsList>
                    </Tabs>
                </div>

                <!-- Tablet: 2-tab switcher (game / both crews side-by-side).
                     Bonanza has only one crew, so the Crews tab is just the
                     user's own — keep the simpler tabs since the layout still
                     works. -->
                <div v-if="!isBonanza" class="mb-4 hidden md:block xl:hidden">
                    <Tabs v-model="tabletGameplayTab">
                        <TabsList class="grid w-full grid-cols-2">
                            <TabsTrigger value="scenario">Game</TabsTrigger>
                            <TabsTrigger value="crews">Crews</TabsTrigger>
                        </TabsList>
                    </Tabs>
                </div>

                <!-- Turn change banner (full width) -->
                <Transition
                    enter-active-class="transition-all duration-300 ease-out"
                    leave-active-class="transition-all duration-500 ease-in"
                    enter-from-class="max-h-0 opacity-0"
                    enter-to-class="max-h-16 opacity-100"
                    leave-from-class="max-h-16 opacity-100"
                    leave-to-class="max-h-0 opacity-0"
                >
                    <div
                        v-if="turnBanner"
                        class="mb-4 overflow-hidden rounded-lg border border-amber-500/40 bg-gradient-to-r from-amber-500/20 via-amber-400/10 to-amber-500/20 py-3 text-center"
                    >
                        <div class="text-lg font-bold text-amber-600 dark:text-amber-400">Turn {{ game.current_turn }} Started</div>
                    </div>
                </Transition>

                <!-- Responsive grid:
                     • < md: 1 col, active tab visible (mobile).
                     • md → xl-1: 1 col for Game tab, 2 cols when Crews tab is active (tablet).
                     • xl+: 3 cols always.
                     Individual column visibility is handled by gameplayColumnClass(). -->
                <div
                    class="grid grid-cols-1 gap-4"
                    :class="[
                        gameplayTab === 'crews' && !isBonanza ? 'md:grid-cols-2' : '',
                        isBonanza
                            ? scenarioCollapsed
                                ? 'xl:grid-cols-[auto_1fr]'
                                : 'xl:grid-cols-2'
                            : scenarioCollapsed
                              ? 'xl:grid-cols-[auto_1fr_1fr]'
                              : 'xl:grid-cols-3',
                    ]"
                >
                    <!-- Column 1: Scenario Info -->
                    <div :class="gameplayColumnClass('scenario')">
                        <!-- Collapsed: thin vertical strip with expand button -->
                        <div v-if="scenarioCollapsed" class="hidden h-full xl:flex xl:flex-col xl:items-center xl:gap-2 xl:py-2">
                            <button
                                class="rounded p-1 text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                                title="Expand game panel"
                                aria-label="Expand game panel"
                                @click="scenarioCollapsed = false"
                            >
                                <PanelLeftOpen class="size-4" />
                            </button>
                            <div class="flex flex-1 items-center">
                                <span class="text-xs font-semibold text-muted-foreground [writing-mode:vertical-lr]"
                                    >Turn {{ game.current_turn }}/{{ game.max_turns }}</span
                                >
                            </div>
                        </div>
                        <!-- Full scenario panel (mobile always, desktop when not collapsed) -->
                        <Card :class="scenarioCollapsed ? 'xl:hidden' : ''">
                            <CardContent class="space-y-4 p-4">
                                <div class="flex items-center justify-between">
                                    <button
                                        class="hidden rounded p-1 text-muted-foreground transition-colors hover:bg-muted hover:text-foreground xl:inline-flex"
                                        title="Collapse game panel"
                                        aria-label="Collapse game panel"
                                        @click="scenarioCollapsed = true"
                                    >
                                        <PanelLeftClose class="size-4" />
                                    </button>
                                    <div class="text-center text-2xl font-bold">
                                        Turn {{ game.current_turn }}
                                        <span class="text-base font-normal text-muted-foreground">/ {{ game.max_turns }}</span>
                                    </div>
                                    <button
                                        aria-label="Game settings"
                                        class="rounded p-1 text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                                        @click="gameSettingsOpen = true"
                                    >
                                        <Settings class="size-4" />
                                    </button>
                                </div>

                                <!-- Scores. Bonanza renders only the user's score
                                     (single-column) — slot 2 is inert and the
                                     Attacker/Defender role isn't meaningful. -->
                                <div :class="['grid gap-2', isBonanza ? 'grid-cols-1' : 'grid-cols-2']">
                                    <div
                                        v-for="player in isBonanza ? game.players.filter((p) => p.slot === 1) : game.players"
                                        :key="'score-' + player.id"
                                        class="rounded-lg border p-3 text-center"
                                    >
                                        <div class="flex items-center justify-center gap-1.5">
                                            <FactionLogo v-if="player.faction" :faction="player.faction" class-name="size-4" />
                                            <span class="text-xs font-medium">{{ playerName(player) }}</span>
                                        </div>
                                        <div class="mt-1 flex items-center justify-center gap-1.5">
                                            <span class="text-2xl font-bold">{{ player.total_points }}</span>
                                            <button
                                                v-if="!isObserver && previousTurnFor(player)"
                                                type="button"
                                                class="rounded p-1 text-muted-foreground hover:bg-muted hover:text-foreground"
                                                :title="`Edit T${game.current_turn - 1} score`"
                                                @click="openEditTurn(player)"
                                            >
                                                <Pencil class="size-3" />
                                            </button>
                                        </div>
                                        <Badge v-if="player.role && !isBonanza" variant="outline" class="mt-1 px-1 py-0 text-[9px] capitalize">{{
                                            player.role
                                        }}</Badge>
                                    </div>
                                </div>

                                <!-- Deployment & Strategy -->
                                <div
                                    v-if="deployment"
                                    role="button"
                                    tabindex="0"
                                    class="cursor-pointer rounded-lg border p-2 text-center transition-colors hover:bg-muted/50"
                                    @click="deploymentDrawerOpen = true"
                                    @keydown.enter="deploymentDrawerOpen = true"
                                >
                                    <div class="text-[10px] uppercase text-muted-foreground">Deployment</div>
                                    <div class="text-sm font-medium">{{ deployment.label }}</div>
                                </div>
                                <div
                                    v-if="game.strategy"
                                    role="button"
                                    tabindex="0"
                                    class="cursor-pointer rounded-lg border p-2 text-center transition-colors hover:bg-muted/50"
                                    @click="strategyDrawerOpen = true"
                                    @keydown.enter="strategyDrawerOpen = true"
                                >
                                    <div class="text-[10px] uppercase text-muted-foreground">Strategy</div>
                                    <div class="text-sm font-medium">{{ game.strategy.name }}</div>
                                </div>

                                <!-- Schemes display -->
                                <template v-if="isObserver">
                                    <!-- Observer: show possible scheme pool per player -->
                                    <details v-for="player in game.players" :key="'obs-scheme-' + player.id" class="rounded-lg border">
                                        <summary class="cursor-pointer px-3 py-2 text-center">
                                            <span class="text-[10px] uppercase text-muted-foreground"
                                                >{{ playerName(player) }}'s Possible Schemes</span
                                            >
                                        </summary>
                                        <div class="space-y-1 border-t px-3 py-2">
                                            <template v-if="observer_scheme_intel?.[player.slot]">
                                                <div
                                                    v-for="scheme in observer_scheme_intel[player.slot].possible_schemes"
                                                    :key="'obs-ps-' + player.id + '-' + scheme.id"
                                                    class="flex items-center gap-2 rounded px-2 py-1 text-xs transition-colors hover:bg-muted/50"
                                                    :class="
                                                        scheme.id === observer_scheme_intel[player.slot].revealed_scheme_id
                                                            ? 'border border-green-500/30 bg-green-500/10'
                                                            : ''
                                                    "
                                                >
                                                    <button
                                                        class="min-w-0 flex-1 text-left font-medium hover:text-primary"
                                                        @click="openSchemeDrawer(scheme)"
                                                    >
                                                        {{ scheme.name }}
                                                    </button>
                                                    <Badge
                                                        v-if="scheme.id === observer_scheme_intel[player.slot].revealed_scheme_id"
                                                        variant="outline"
                                                        class="shrink-0 border-green-500/50 px-1 py-0 text-[8px] text-green-600 dark:text-green-400"
                                                    >
                                                        Scored T{{ observer_scheme_intel[player.slot].last_scored_turn }}
                                                    </Badge>
                                                </div>
                                            </template>
                                            <div v-else class="py-2 text-center text-xs text-muted-foreground">No scheme data yet</div>
                                        </div>
                                    </details>
                                </template>
                                <template v-else>
                                    <!-- Player: own scheme with hide toggle -->
                                    <div v-if="myDisplaySchemeId" class="rounded-lg border border-primary/30 bg-primary/5 p-2">
                                        <div class="flex items-center justify-center gap-1.5">
                                            <span class="text-[10px] uppercase text-muted-foreground">Your Scheme</span>
                                            <button
                                                class="rounded p-0.5 text-muted-foreground hover:text-foreground"
                                                @click="schemeHidden = !schemeHidden"
                                            >
                                                <component :is="schemeHidden ? EyeOff : Eye" class="size-3" />
                                            </button>
                                        </div>
                                        <div v-if="schemeHidden" class="text-center text-sm font-medium text-muted-foreground">Hidden</div>
                                        <template v-else>
                                            <button
                                                class="w-full text-center text-sm font-medium hover:text-primary"
                                                @click="openSchemeDrawer(findScheme(myDisplaySchemeId)!)"
                                            >
                                                {{ findScheme(myDisplaySchemeId)?.name }}
                                            </button>

                                            <!-- Scheme notes -->
                                            <div class="mt-2 space-y-1.5 border-t border-primary/20 pt-2">
                                                <!-- Prerequisite hint (only before locking) -->
                                                <div
                                                    v-if="!schemeNotesLocked && findScheme(myDisplaySchemeId)?.prerequisite"
                                                    class="text-[10px] italic text-muted-foreground"
                                                >
                                                    {{ findScheme(myDisplaySchemeId)?.prerequisite }}
                                                </div>

                                                <!-- Model selection -->
                                                <div v-if="schemeModelReq">
                                                    <label class="text-[10px] uppercase text-muted-foreground">{{ modelReqLabel }}</label>
                                                    <template v-if="schemeNotesLocked">
                                                        <div class="mt-0.5 rounded border bg-muted/50 px-2 py-1 text-xs font-medium">
                                                            {{ schemeSelectedModel || 'Not selected' }}
                                                        </div>
                                                    </template>
                                                    <template v-else>
                                                        <select
                                                            v-if="schemeModelOptions.length"
                                                            v-model="schemeSelectedModel"
                                                            class="mt-0.5 w-full rounded border bg-background px-2 py-1 text-xs"
                                                            @change="saveSchemeNotes"
                                                        >
                                                            <option value="">Select...</option>
                                                            <option v-for="m in schemeModelOptions" :key="m.id" :value="m.display_name">
                                                                {{ m.display_name }}<template v-if="m.cost != null"> ({{ m.cost }}ss)</template>
                                                            </option>
                                                        </select>
                                                        <input
                                                            v-else
                                                            v-model="schemeSelectedModel"
                                                            type="text"
                                                            placeholder="No matching models in crew — type manually"
                                                            class="mt-0.5 w-full rounded border bg-background px-2 py-1 text-xs"
                                                            @input="saveSchemeNotes"
                                                        />
                                                    </template>
                                                </div>

                                                <!-- Marker selection -->
                                                <div v-if="schemeHasMarkerReq">
                                                    <label class="text-[10px] uppercase text-muted-foreground">Target Marker</label>
                                                    <template v-if="schemeNotesLocked">
                                                        <div class="mt-0.5 rounded border bg-muted/50 px-2 py-1 text-xs font-medium">
                                                            {{ schemeSelectedMarker || 'Not selected' }}
                                                        </div>
                                                    </template>
                                                    <select
                                                        v-else
                                                        v-model="schemeSelectedMarker"
                                                        class="mt-0.5 w-full rounded border bg-background px-2 py-1 text-xs"
                                                        @change="saveSchemeNotes"
                                                    >
                                                        <option value="">None</option>
                                                        <option v-for="m in all_markers" :key="m.id" :value="m.name">{{ m.name }}</option>
                                                    </select>
                                                </div>

                                                <!-- Terrain note -->
                                                <div v-if="schemeHasTerrainReq">
                                                    <label class="text-[10px] uppercase text-muted-foreground">Terrain Note</label>
                                                    <template v-if="schemeNotesLocked">
                                                        <div class="mt-0.5 rounded border bg-muted/50 px-2 py-1 text-xs font-medium">
                                                            {{ schemeTerrainNote || 'Not set' }}
                                                        </div>
                                                    </template>
                                                    <input
                                                        v-else
                                                        v-model="schemeTerrainNote"
                                                        type="text"
                                                        placeholder="e.g. the building on the left..."
                                                        class="mt-0.5 w-full rounded border bg-background px-2 py-1 text-xs"
                                                        @input="saveSchemeNotes"
                                                    />
                                                </div>

                                                <!-- Free text note (always editable) -->
                                                <div>
                                                    <label class="text-[10px] uppercase text-muted-foreground">Notes</label>
                                                    <textarea
                                                        v-model="schemeNote"
                                                        placeholder="Any notes about your scheme..."
                                                        rows="2"
                                                        class="mt-0.5 w-full resize-none rounded border bg-background px-2 py-1 text-xs"
                                                        @input="saveSchemeNotes"
                                                    />
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </template>

                                <!-- Opponent's Possible Schemes (duel only — solo shows scheme directly) -->
                                <details v-if="opponent_scheme_intel && !isSolo && !isObserver" class="rounded-lg border">
                                    <summary class="cursor-pointer px-3 py-2 text-xs font-medium text-muted-foreground hover:text-foreground">
                                        Opponent's Possible Schemes
                                    </summary>
                                    <div class="space-y-3 border-t px-3 py-3">
                                        <!-- Last revealed scheme -->
                                        <div
                                            v-if="opponent_scheme_intel.last_revealed"
                                            class="rounded-md border border-amber-500/30 bg-amber-500/5 p-2"
                                        >
                                            <div class="flex items-center justify-between">
                                                <div class="text-[10px] uppercase text-muted-foreground">
                                                    Last Revealed (Turn {{ opponent_scheme_intel.last_revealed.turn_number }})
                                                </div>
                                                <Badge
                                                    v-if="opponent_scheme_intel.last_revealed.scheme_action === 'scored'"
                                                    variant="outline"
                                                    class="border-green-500/50 px-1 py-0 text-[9px] text-green-600 dark:text-green-400"
                                                    >Scored</Badge
                                                >
                                                <Badge
                                                    v-else-if="opponent_scheme_intel.last_revealed.scheme_action === 'discarded'"
                                                    variant="outline"
                                                    class="border-red-500/50 px-1 py-0 text-[9px] text-red-600 dark:text-red-400"
                                                    >Discarded</Badge
                                                >
                                            </div>
                                            <div
                                                class="mt-1 cursor-pointer text-sm font-medium hover:text-primary"
                                                @click="openSchemeDrawer(findScheme(opponent_scheme_intel.last_revealed.scheme_id)!)"
                                            >
                                                {{ opponent_scheme_intel.last_revealed.scheme_name }}
                                            </div>
                                        </div>

                                        <!-- Possible current schemes -->
                                        <div>
                                            <div class="text-[10px] uppercase text-muted-foreground">
                                                {{ opponent_scheme_intel.last_revealed ? 'Possible Current Schemes' : 'Scheme Pool' }}
                                            </div>
                                            <div class="mt-1 space-y-1">
                                                <div
                                                    v-for="scheme in opponent_scheme_intel.possible_schemes"
                                                    :key="'opp-ref-' + scheme.id"
                                                    class="cursor-pointer rounded-md border p-2 transition-colors hover:bg-muted/50"
                                                    :class="
                                                        opponent_scheme_intel.last_revealed?.scheme_id === scheme.id
                                                            ? 'border-amber-500/20 bg-amber-500/5'
                                                            : ''
                                                    "
                                                    @click="openSchemeDrawer(scheme)"
                                                >
                                                    <div class="flex items-center justify-between">
                                                        <span class="text-xs font-medium">{{ scheme.name }}</span>
                                                        <Badge
                                                            v-if="opponent_scheme_intel.last_revealed?.scheme_id === scheme.id"
                                                            variant="outline"
                                                            class="px-1 py-0 text-[9px]"
                                                            >Kept</Badge
                                                        >
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Scheme history -->
                                        <div v-if="opponent_scheme_intel.scheme_history?.length" class="border-t pt-2">
                                            <div class="text-[10px] uppercase text-muted-foreground">Scheme History</div>
                                            <div class="mt-1 space-y-0.5">
                                                <div
                                                    v-for="entry in opponent_scheme_intel.scheme_history"
                                                    :key="'opp-hist-' + entry.turn_number"
                                                    class="flex items-center justify-between rounded px-2 py-1 text-xs"
                                                >
                                                    <div class="flex items-center gap-1.5">
                                                        <span class="text-muted-foreground">T{{ entry.turn_number }}</span>
                                                        <button
                                                            class="font-medium hover:text-primary"
                                                            @click="openSchemeDrawer(findScheme(entry.scheme_id)!)"
                                                        >
                                                            {{ entry.scheme_name }}
                                                        </button>
                                                    </div>
                                                    <Badge
                                                        v-if="entry.scheme_action === 'scored'"
                                                        variant="outline"
                                                        class="border-green-500/50 px-1 py-0 text-[8px] text-green-600 dark:text-green-400"
                                                        >Scored</Badge
                                                    >
                                                    <Badge
                                                        v-else-if="entry.scheme_action === 'discarded'"
                                                        variant="outline"
                                                        class="border-red-500/50 px-1 py-0 text-[8px] text-red-600 dark:text-red-400"
                                                        >Discarded</Badge
                                                    >
                                                    <Badge
                                                        v-else
                                                        variant="outline"
                                                        class="border-amber-500/50 px-1 py-0 text-[8px] text-amber-600 dark:text-amber-400"
                                                        >Held</Badge
                                                    >
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </details>

                                <!-- Turn scoring (hidden for observers, and entirely
                                     skipped for Bonanza Brawl which uses event-driven
                                     manual VP via the banner widget at the top). -->
                                <template v-if="isObserver || isBonanza">
                                    <!-- observers + Bonanza see nothing here -->
                                </template>
                                <template v-else-if="myPlayer?.is_turn_complete">
                                    <div class="py-2 text-center text-xs text-muted-foreground">
                                        <template v-if="isSolo"> <Check class="mr-1 inline size-3 text-green-500" /> Your turn submitted </template>
                                        <template v-else> <Loader2 class="mr-1 inline size-3 animate-spin" /> Waiting for opponent... </template>
                                    </div>
                                </template>
                                <template v-else>
                                    <div class="space-y-3 border-t pt-3">
                                        <div class="text-xs font-semibold">End of Turn {{ game.current_turn }}</div>

                                        <!-- Strategy points (1/turn + 1 bonus once/game) -->
                                        <div class="flex items-center justify-between">
                                            <div>
                                                <span class="text-xs text-muted-foreground">Strategy VP</span>
                                                <span v-if="!myStrategyBonusUsed && strategyPoints < 2" class="ml-1 text-[9px] text-amber-500"
                                                    >+1 bonus available</span
                                                >
                                                <span v-if="myStrategyBonusUsed" class="ml-1 text-[9px] text-muted-foreground/50">bonus used</span>
                                            </div>
                                            <div class="flex items-center gap-1.5">
                                                <button
                                                    class="rounded border p-1.5 hover:bg-muted sm:p-0.5"
                                                    @click="strategyPoints = Math.max(0, strategyPoints - 1)"
                                                >
                                                    <Minus class="size-3.5" />
                                                </button>
                                                <span class="w-6 text-center font-bold">{{ strategyPoints }}</span>
                                                <button
                                                    class="rounded border p-1.5 hover:bg-muted sm:p-0.5"
                                                    :disabled="strategyPoints >= maxStrategyThisTurn"
                                                    :class="strategyPoints >= maxStrategyThisTurn ? 'opacity-30' : ''"
                                                    @click="strategyPoints = Math.min(maxStrategyThisTurn, strategyPoints + 1)"
                                                >
                                                    <Plus class="size-3.5" />
                                                </button>
                                            </div>
                                        </div>
                                        <!-- "Bonus-only" disambiguation: 1 VP can be the base point OR the
                                             once-per-game bonus scored alone. Surfacing it as a checkbox stops
                                             the bonus from leaking into future turns when it was already used. -->
                                        <label
                                            v-if="strategyPoints === 1 && !myStrategyBonusUsed"
                                            class="-mt-1 flex cursor-pointer items-start gap-2 rounded-md border border-amber-500/40 bg-amber-500/5 p-2 text-xs"
                                        >
                                            <Checkbox
                                                class="mt-0.5"
                                                :checked="strategyBonusOnly"
                                                @update:checked="(v: boolean) => (strategyBonusOnly = v)"
                                            />
                                            <span>
                                                <span class="font-medium text-amber-700 dark:text-amber-400">This was the bonus point</span>
                                                <span class="block text-[10px] text-muted-foreground">
                                                    Check if your 1 VP came from the once-per-game bonus (it can't be scored again).
                                                </span>
                                            </span>
                                        </label>

                                        <!-- Scheme points (max 2/turn, max 6 total) -->
                                        <div class="flex items-center justify-between">
                                            <div>
                                                <span class="text-xs text-muted-foreground">Scheme VP</span>
                                                <span class="ml-1 text-[9px] text-muted-foreground/50">{{ myTotalSchemeScored }}/6 total</span>
                                            </div>
                                            <div class="flex items-center gap-1.5">
                                                <button
                                                    class="rounded border p-1.5 hover:bg-muted sm:p-0.5"
                                                    @click="schemePoints = Math.max(0, schemePoints - 1)"
                                                >
                                                    <Minus class="size-3.5" />
                                                </button>
                                                <span class="w-6 text-center font-bold">{{ schemePoints }}</span>
                                                <button
                                                    class="rounded border p-1.5 hover:bg-muted sm:p-0.5"
                                                    :disabled="schemePoints >= maxSchemeThisTurn"
                                                    :class="schemePoints >= maxSchemeThisTurn ? 'opacity-30' : ''"
                                                    @click="schemePoints = Math.min(maxSchemeThisTurn, schemePoints + 1)"
                                                >
                                                    <Plus class="size-3.5" />
                                                </button>
                                            </div>
                                        </div>

                                        <!-- Next scheme (not on last turn) -->
                                        <div v-if="!isLastTurn">
                                            <div class="mb-1.5 text-xs font-medium">
                                                {{ currentSchemeScored ? 'Scored — select next scheme:' : 'Hold scheme or discard:' }}
                                            </div>
                                            <div class="space-y-1">
                                                <!-- Hold option (only when not scored) -->
                                                <button
                                                    v-if="!currentSchemeScored"
                                                    class="w-full rounded-md border px-2 py-1.5 text-left text-xs transition-colors"
                                                    :class="!nextSchemeId ? 'border-primary bg-primary/10 font-medium' : 'hover:bg-muted'"
                                                    @click="nextSchemeId = null"
                                                >
                                                    Hold Scheme (Hidden)
                                                </button>
                                                <!-- Divider when showing discard options -->
                                                <div
                                                    v-if="!currentSchemeScored && next_schemes.length"
                                                    class="py-0.5 text-center text-[9px] text-muted-foreground"
                                                >
                                                    — or discard &amp; switch to —
                                                </div>
                                                <!-- Follow-up schemes -->
                                                <template v-if="next_schemes.length">
                                                    <div v-for="scheme in next_schemes" :key="scheme.id" class="flex items-center gap-1">
                                                        <button
                                                            class="min-w-0 flex-1 rounded-md border px-2 py-1.5 text-left text-xs transition-colors"
                                                            :class="
                                                                nextSchemeId === scheme.id
                                                                    ? 'border-primary bg-primary/10 font-medium'
                                                                    : 'hover:bg-muted'
                                                            "
                                                            @click="nextSchemeId = scheme.id"
                                                        >
                                                            {{ scheme.name }}
                                                        </button>
                                                        <button
                                                            class="shrink-0 rounded p-1 text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                                                            @click="openSchemeDrawer(scheme)"
                                                        >
                                                            <Eye class="size-3.5" />
                                                        </button>
                                                    </div>
                                                </template>
                                                <div v-else-if="currentSchemeScored" class="text-[11px] text-muted-foreground">
                                                    No follow-up schemes available.
                                                </div>
                                            </div>

                                            <!-- Requirement fields for the selected next scheme -->
                                            <div
                                                v-if="nextSchemeId && findScheme(nextSchemeId)?.requirements?.length"
                                                class="mt-2 space-y-1.5 rounded border border-primary/20 bg-primary/5 p-2"
                                            >
                                                <div class="text-[10px] font-medium uppercase text-muted-foreground">
                                                    {{ findScheme(nextSchemeId)?.name }} — Setup
                                                </div>
                                                <div v-if="findScheme(nextSchemeId)?.prerequisite" class="text-[10px] italic text-muted-foreground">
                                                    {{ findScheme(nextSchemeId)?.prerequisite }}
                                                </div>
                                                <!-- Model -->
                                                <div v-if="nextSchemeModelReq">
                                                    <label class="text-[10px] uppercase text-muted-foreground">{{ nextSchemeModelLabel }}</label>
                                                    <select
                                                        v-if="nextSchemeModelOptions.length"
                                                        v-model="nextSchemeModel"
                                                        class="mt-0.5 w-full rounded border bg-background px-2 py-1 text-xs"
                                                    >
                                                        <option value="">Select...</option>
                                                        <option v-for="m in nextSchemeModelOptions" :key="m.id" :value="m.display_name">
                                                            {{ m.display_name }}<template v-if="m.cost != null"> ({{ m.cost }}ss)</template>
                                                        </option>
                                                    </select>
                                                    <input
                                                        v-else
                                                        v-model="nextSchemeModel"
                                                        type="text"
                                                        placeholder="Type model name..."
                                                        class="mt-0.5 w-full rounded border bg-background px-2 py-1 text-xs"
                                                    />
                                                </div>
                                                <!-- Marker -->
                                                <div v-if="findScheme(nextSchemeId)?.requirements?.some((r: any) => r.type === 'select_marker')">
                                                    <label class="text-[10px] uppercase text-muted-foreground">Target Marker</label>
                                                    <select
                                                        v-model="nextSchemeMarker"
                                                        class="mt-0.5 w-full rounded border bg-background px-2 py-1 text-xs"
                                                    >
                                                        <option value="">Select...</option>
                                                        <option v-for="m in all_markers" :key="m.id" :value="m.name">{{ m.name }}</option>
                                                    </select>
                                                </div>
                                                <!-- Terrain -->
                                                <div v-if="findScheme(nextSchemeId)?.requirements?.some((r: any) => r.type === 'terrain_note')">
                                                    <label class="text-[10px] uppercase text-muted-foreground">Terrain Note</label>
                                                    <input
                                                        v-model="nextSchemeTerrain"
                                                        type="text"
                                                        placeholder="e.g. the building on the left..."
                                                        class="mt-0.5 w-full rounded border bg-background px-2 py-1 text-xs"
                                                    />
                                                </div>
                                            </div>
                                        </div>

                                        <Button
                                            class="w-full"
                                            size="sm"
                                            :disabled="
                                                scoringTurn ||
                                                (!isLastTurn &&
                                                    !mySchemeCapReached &&
                                                    (currentSchemeScored || nextSchemeId) &&
                                                    !nextSchemeId &&
                                                    next_schemes.length > 0)
                                            "
                                            @click="submitTurnDialogOpen = true"
                                        >
                                            <Loader2 v-if="scoringTurn" class="mr-2 size-4 animate-spin" />
                                            Submit Turn ({{ strategyPoints + schemePoints }} VP)
                                        </Button>
                                    </div>
                                </template>

                                <!-- Solo: Opponent scheme + scoring (in Game column).
                                     Hidden for Bonanza — manual VP buttons in the
                                     top banner already cover both crews. -->
                                <template v-if="isSolo && !isObserver && !isBonanza">
                                    <div class="space-y-3 rounded-lg border border-amber-500/40 bg-amber-500/5 p-3 dark:bg-amber-500/5">
                                        <div class="flex items-center gap-1.5 text-xs font-semibold text-amber-700 dark:text-amber-400">
                                            <UserRound class="size-3.5" /> Opponent — Turn {{ game.current_turn }}
                                        </div>
                                        <template v-if="opponent?.is_turn_complete">
                                            <div class="py-2 text-center text-xs text-green-600">
                                                <Check class="mr-1 inline size-3" /> Opponent turn submitted
                                            </div>
                                        </template>
                                        <template v-else>
                                            <div class="flex items-center justify-between">
                                                <div>
                                                    <span class="text-xs text-muted-foreground">Strategy VP</span>
                                                    <span
                                                        v-if="!opponentStrategyBonusUsed && opponentStrategyPoints < 2"
                                                        class="ml-1 text-[9px] text-amber-500"
                                                        >+1 bonus available</span
                                                    >
                                                    <span v-if="opponentStrategyBonusUsed" class="ml-1 text-[9px] text-muted-foreground/50"
                                                        >bonus used</span
                                                    >
                                                </div>
                                                <div class="flex items-center gap-1.5">
                                                    <button
                                                        class="rounded border p-1.5 hover:bg-muted sm:p-0.5"
                                                        @click="opponentStrategyPoints = Math.max(0, opponentStrategyPoints - 1)"
                                                    >
                                                        <Minus class="size-3.5" />
                                                    </button>
                                                    <span class="w-6 text-center font-bold">{{ opponentStrategyPoints }}</span>
                                                    <button
                                                        class="rounded border p-1.5 hover:bg-muted sm:p-0.5"
                                                        :disabled="opponentStrategyPoints >= opponentMaxStrategyThisTurn"
                                                        :class="opponentStrategyPoints >= opponentMaxStrategyThisTurn ? 'opacity-30' : ''"
                                                        @click="
                                                            opponentStrategyPoints = Math.min(opponentMaxStrategyThisTurn, opponentStrategyPoints + 1)
                                                        "
                                                    >
                                                        <Plus class="size-3.5" />
                                                    </button>
                                                </div>
                                            </div>
                                            <label
                                                v-if="opponentStrategyPoints === 1 && !opponentStrategyBonusUsed"
                                                class="-mt-1 flex cursor-pointer items-start gap-2 rounded-md border border-amber-500/40 bg-amber-500/5 p-2 text-xs"
                                            >
                                                <Checkbox
                                                    class="mt-0.5"
                                                    :checked="opponentStrategyBonusOnly"
                                                    @update:checked="(v: boolean) => (opponentStrategyBonusOnly = v)"
                                                />
                                                <span>
                                                    <span class="font-medium text-amber-700 dark:text-amber-400">This was the bonus point</span>
                                                    <span class="block text-[10px] text-muted-foreground">
                                                        Check if the opponent's 1 VP came from the once-per-game bonus.
                                                    </span>
                                                </span>
                                            </label>
                                            <div class="flex items-center justify-between">
                                                <div>
                                                    <span class="text-xs text-muted-foreground">Scheme VP</span>
                                                    <span class="ml-1 text-[9px] text-muted-foreground/50"
                                                        >{{ opponentTotalSchemeScored }}/6 total</span
                                                    >
                                                </div>
                                                <div class="flex items-center gap-1.5">
                                                    <button
                                                        class="rounded border p-1.5 hover:bg-muted sm:p-0.5"
                                                        @click="opponentSchemePoints = Math.max(0, opponentSchemePoints - 1)"
                                                    >
                                                        <Minus class="size-3.5" />
                                                    </button>
                                                    <span class="w-6 text-center font-bold">{{ opponentSchemePoints }}</span>
                                                    <button
                                                        class="rounded border p-1.5 hover:bg-muted sm:p-0.5"
                                                        :disabled="opponentSchemePoints >= opponentMaxSchemeThisTurn"
                                                        :class="opponentSchemePoints >= opponentMaxSchemeThisTurn ? 'opacity-30' : ''"
                                                        @click="opponentSchemePoints = Math.min(opponentMaxSchemeThisTurn, opponentSchemePoints + 1)"
                                                    >
                                                        <Plus class="size-3.5" />
                                                    </button>
                                                </div>
                                            </div>
                                            <Button class="w-full" size="sm" :disabled="scoringOpponentTurn" @click="submitOpponentTurnScore">
                                                <Loader2 v-if="scoringOpponentTurn" class="mr-2 size-4 animate-spin" />
                                                Submit Opponent ({{ opponentStrategyPoints + opponentSchemePoints }} VP)
                                            </Button>
                                        </template>
                                    </div>
                                </template>

                                <!-- Game complete status -->
                                <div
                                    v-if="!isObserver && (myPlayer?.is_game_complete || opponent?.is_game_complete)"
                                    class="border-t pt-3 text-center text-xs"
                                >
                                    <div v-if="myPlayer?.is_game_complete && opponent?.is_game_complete" class="text-green-600 dark:text-green-400">
                                        <Check class="mr-1 inline size-3" /> Both players ready — finalizing...
                                    </div>
                                    <div v-else-if="myPlayer?.is_game_complete" class="text-muted-foreground">
                                        <Check class="mr-1 inline size-3 text-green-500" /> Waiting for opponent to confirm...
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            class="mt-2 w-full text-xs text-destructive hover:text-destructive"
                                            @click="cancelGameComplete"
                                        >
                                            Cancel End Game
                                        </Button>
                                    </div>
                                    <div v-else-if="opponent?.is_game_complete" class="text-amber-600 dark:text-amber-400">
                                        <span class="font-medium">{{ playerName(opponent) }}</span> wants to end the game.
                                        <div class="mt-2 flex gap-2">
                                            <Button variant="outline" size="sm" class="flex-1 text-xs" @click="completeDialogOpen = true">
                                                Confirm &amp; Complete
                                            </Button>
                                        </div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    <!-- Column 2: My Crew (editable) -->
                    <div :class="gameplayColumnClass('my-crew')">
                        <!-- Transient summon/replace banner for this crew column -->
                        <Transition
                            enter-active-class="transition-all duration-200 ease-out"
                            leave-active-class="transition-all duration-200 ease-in"
                            enter-from-class="opacity-0 -translate-y-1"
                            leave-to-class="opacity-0 -translate-y-1"
                        >
                            <button
                                v-if="summonBanner && summonBanner.slot === (myPlayer?.slot ?? 1)"
                                type="button"
                                class="mb-2 flex w-full items-center justify-between gap-2 rounded-md border border-primary/40 bg-primary/10 px-3 py-2 text-left text-xs transition-colors hover:bg-primary/15"
                                @click="clickSummonBanner"
                            >
                                <span
                                    ><strong>{{ summonBanner.name }}</strong> added. Tap to change sculpt.</span
                                >
                                <X class="size-3.5 shrink-0 text-muted-foreground" @click.stop="dismissSummonBanner" />
                            </button>
                        </Transition>
                        <div class="mb-1 flex items-center justify-between">
                            <div class="flex items-center gap-1">
                                <h3 class="text-sm font-semibold">{{ isObserver ? playerName(myPlayer) : 'Your Crew' }}</h3>
                                <button
                                    class="rounded p-0.5 hover:bg-muted"
                                    :title="allMyCardsExpanded ? 'Collapse all cards' : 'Expand all cards'"
                                    aria-label="Toggle all cards"
                                    @click="toggleAllCards('my')"
                                >
                                    <Layers class="size-3.5" :class="allMyCardsExpanded ? 'text-amber-500' : 'text-muted-foreground'" />
                                </button>
                                <!-- Wide-screen-only: open every member + active crew upgrade
                                     in a single grid view. Hidden under lg because the grid
                                     is unusable on a narrow viewport (single-column scrolling
                                     defeats the at-a-glance purpose). -->
                                <button
                                    class="hidden rounded p-0.5 hover:bg-muted lg:inline-flex"
                                    title="View all crew cards"
                                    aria-label="View all crew cards"
                                    @click="openAllCards('my')"
                                >
                                    <LayoutGrid class="size-3.5 text-muted-foreground" />
                                </button>
                            </div>
                            <div class="flex items-center gap-1">
                                <button v-if="!isObserver" class="rounded bg-black/20 p-2 hover:bg-black/40 sm:p-1" @click="updateSoulstonePool(-1)">
                                    <Minus class="size-4 sm:size-3.5" />
                                </button>
                                <span class="flex min-w-[3rem] items-center justify-center gap-0.5 text-xs font-bold">
                                    {{ myPlayer?.soulstone_pool ?? 0 }}<GameIcon type="soulstone" class-name="h-3 inline-block" />
                                </span>
                                <button v-if="!isObserver" class="rounded bg-black/20 p-2 hover:bg-black/40 sm:p-1" @click="updateSoulstonePool(1)">
                                    <Plus class="size-4 sm:size-3.5" />
                                </button>
                            </div>
                        </div>
                        <Transition
                            enter-active-class="transition-all duration-300 ease-out"
                            leave-active-class="transition-all duration-500 ease-in"
                            enter-from-class="max-h-0 opacity-0"
                            enter-to-class="max-h-10 opacity-100"
                            leave-from-class="max-h-10 opacity-100"
                            leave-to-class="max-h-0 opacity-0"
                        >
                            <div
                                v-if="soulstoneAwardSlot === 1"
                                class="mb-2 overflow-hidden rounded-md bg-amber-500/10 px-2 py-1 text-center text-[11px] text-amber-700 dark:text-amber-400"
                            >
                                +1<GameIcon type="soulstone" class-name="mx-0.5 h-3 inline-block" /> from {{ soulstoneAwardName }}'s death
                            </div>
                        </Transition>
                        <div class="mb-2 text-[10px] text-muted-foreground">
                            Activations:
                            <span class="font-medium text-foreground">{{ myCrewMembers.filter((m: any) => !m.is_activated).length }}</span
                            >/<span>{{ myCrewMembers.length }}</span> remaining
                        </div>
                        <!-- Reference Upgrades -->
                        <div v-if="myCrewUpgrades.length" class="mb-2 space-y-1">
                            <div
                                v-for="upgrade in myCrewUpgrades"
                                :key="upgrade.id"
                                class="rounded-md border px-2 py-1.5 text-sm transition-colors"
                                :class="[
                                    myActiveUpgradeId === upgrade.id
                                        ? 'border-amber-500/50 bg-amber-500/10'
                                        : 'border-border/50 bg-accent/30 opacity-60',
                                    upgrade.front_image ? 'cursor-pointer hover:bg-accent' : '',
                                ]"
                                @click="openUpgradePreview(upgrade)"
                            >
                                <div class="flex items-center gap-1.5">
                                    <Star
                                        class="size-3.5 shrink-0"
                                        :class="myActiveUpgradeId === upgrade.id ? 'fill-amber-500 text-amber-500' : 'text-muted-foreground'"
                                    />
                                    <span class="flex-1 font-semibold">{{ upgrade.name }}</span>
                                    <button
                                        v-if="(myUpgradeMode === 'swappable' || isBonanza) && !isObserver && myActiveUpgradeId !== upgrade.id"
                                        class="rounded bg-amber-500/20 px-1.5 py-0.5 text-[10px] font-medium text-amber-600 hover:bg-amber-500/30 dark:text-amber-400"
                                        @click.stop="swapCrewUpgrade(upgrade.id)"
                                    >
                                        Activate
                                    </button>
                                    <Badge
                                        v-if="myActiveUpgradeId === upgrade.id"
                                        variant="outline"
                                        class="border-amber-500/50 px-1.5 py-0 text-[9px] text-amber-600 dark:text-amber-400"
                                        >Active</Badge
                                    >
                                    <button
                                        v-if="upgrade.front_image"
                                        type="button"
                                        class="rounded p-0.5 text-muted-foreground hover:text-foreground"
                                        :title="expandedMyCrewUpgradeId === upgrade.id ? 'Collapse card' : 'Expand card'"
                                        @click.stop="toggleCrewUpgradeExpand(upgrade.id, 'my')"
                                    >
                                        <ChevronDown
                                            class="size-3.5 transition-transform"
                                            :class="expandedMyCrewUpgradeId === upgrade.id ? 'rotate-180' : ''"
                                        />
                                    </button>
                                </div>
                                <PowerBarBubbles
                                    v-if="(upgrade.power_bar_count ?? 0) > 0"
                                    class="mt-1 pl-5"
                                    :max="upgrade.power_bar_count"
                                    :current="crewUpgradePowerCurrent(myPlayer, upgrade.id, upgrade.power_bar_count)"
                                    :readonly="isObserver"
                                    compact
                                    @update="(v) => setCrewUpgradePowerBar(myPlayer, upgrade.id, v)"
                                />
                                <Transition
                                    enter-active-class="transition-all duration-300 ease-out"
                                    leave-active-class="transition-all duration-200 ease-in"
                                    enter-from-class="max-h-0 opacity-0"
                                    enter-to-class="max-h-[600px] opacity-100"
                                    leave-from-class="max-h-[600px] opacity-100"
                                    leave-to-class="max-h-0 opacity-0"
                                >
                                    <div
                                        v-if="expandedMyCrewUpgradeId === upgrade.id && upgrade.front_image"
                                        class="mt-2 overflow-hidden [&_img]:max-h-[70dvh] [&_img]:w-auto [&_img]:object-contain xl:[&_img]:max-h-[50dvh]"
                                        @click.stop
                                    >
                                        <UpgradeFlipCard
                                            :front-image="upgrade.front_image"
                                            :back-image="upgrade.back_image"
                                            :alt-text="upgrade.name"
                                            :show-link="false"
                                        />
                                    </div>
                                </Transition>
                            </div>
                        </div>
                        <div class="space-y-1">
                            <div
                                v-for="member in myCrewMembers"
                                :key="member.id"
                                :class="factionBackground(member.faction ?? myPlayer?.faction ?? '')"
                                class="rounded-md border border-white/20 px-2 py-1.5 text-white"
                                :style="member.is_activated ? 'opacity: 0.7' : ''"
                            >
                                <div class="min-w-0 flex-1">
                                    <!-- Line 1: Activation + Name (+ desktop action buttons) -->
                                    <div class="flex items-center gap-1">
                                        <template v-if="!isObserver">
                                            <button
                                                class="shrink-0 rounded p-1.5 hover:bg-white/20 sm:p-0.5"
                                                @click="toggleActivated(member)"
                                                :title="member.is_activated ? 'Mark unactivated' : 'Mark activated'"
                                            >
                                                <Check
                                                    v-if="member.is_activated"
                                                    class="size-4 text-green-400 drop-shadow-[0_1px_2px_rgba(0,0,0,0.5)] sm:size-3.5"
                                                />
                                                <Circle v-else class="size-4 text-white/50 drop-shadow-[0_1px_2px_rgba(0,0,0,0.5)] sm:size-3.5" />
                                            </button>
                                            <button
                                                class="hidden shrink-0 rounded bg-black/30 p-1 text-amber-200 hover:bg-black/50 sm:inline-flex"
                                                aria-label="Upgrades"
                                                title="Upgrades"
                                                @click.stop="openUpgradeDialog(member)"
                                            >
                                                <ArrowUpCircle class="size-3.5" />
                                            </button>
                                            <button
                                                class="hidden shrink-0 rounded bg-black/30 p-1 text-cyan-200 hover:bg-black/50 sm:inline-flex"
                                                aria-label="Tokens"
                                                title="Tokens"
                                                @click.stop="openTokenDialog(member)"
                                            >
                                                <Puzzle class="size-3.5" />
                                            </button>
                                            <button
                                                class="hidden shrink-0 rounded bg-black/30 p-1 text-blue-200 hover:bg-black/50 sm:inline-flex"
                                                aria-label="Replace"
                                                title="Replace"
                                                @click.stop="openReplace(member)"
                                            >
                                                <Replace class="size-3.5" />
                                            </button>
                                        </template>
                                        <template v-else>
                                            <Check
                                                v-if="member.is_activated"
                                                class="size-3.5 shrink-0 text-green-400 drop-shadow-[0_1px_2px_rgba(0,0,0,0.5)]"
                                            />
                                            <Circle v-else class="size-3.5 shrink-0 text-white/50 drop-shadow-[0_1px_2px_rgba(0,0,0,0.5)]" />
                                        </template>
                                        <button
                                            class="cursor-pointer truncate text-base font-semibold hover:underline"
                                            @click="openMemberPreview(member)"
                                        >
                                            {{ member.display_name }}
                                        </button>
                                        <button
                                            v-if="member.front_image"
                                            class="ml-auto shrink-0 rounded p-1 hover:bg-white/20"
                                            aria-label="Toggle card preview"
                                            title="Toggle card preview"
                                            @click.stop="toggleInlineCard(member.id, 'my')"
                                        >
                                            <Eye
                                                class="size-4 drop-shadow-[0_1px_2px_rgba(0,0,0,0.6)]"
                                                :class="expandedMyCards.has(member.id) ? 'text-amber-300' : 'text-white'"
                                            />
                                        </button>
                                    </div>
                                    <!-- Line 2 (mobile): Health pips under name / Desktop: combined with stats -->
                                    <div class="mt-0.5 flex pl-8 sm:hidden">
                                        <div class="flex gap-0.5">
                                            <button
                                                v-for="pip in member.max_health"
                                                :key="'hp-m-' + pip"
                                                type="button"
                                                class="size-3.5 rounded-sm transition-colors"
                                                :class="[
                                                    pip <= member.current_health
                                                        ? member.current_health <= Math.ceil(member.max_health / 2)
                                                            ? 'bg-red-400/90'
                                                            : 'bg-white/60'
                                                        : 'bg-black/30 ring-1 ring-inset ring-white/10',
                                                    isObserver ? 'cursor-default' : 'cursor-pointer hover:bg-white/80',
                                                ]"
                                                :disabled="isObserver"
                                                :aria-label="`Set health to ${pip} of ${member.max_health}`"
                                                @click.stop="onHealthPipClick(member, pip, isObserver)"
                                            />
                                        </div>
                                    </div>
                                    <!-- Line 3 (mobile): Stats + Health controls / Desktop: Stats + pips + controls on one row -->
                                    <div class="mt-0.5 flex items-center gap-2 pl-8 sm:pl-6">
                                        <div
                                            v-if="member.defense || member.willpower || member.speed || member.size"
                                            class="flex gap-1.5 text-sm font-semibold text-white drop-shadow-[0_1px_2px_rgba(0,0,0,0.6)] sm:text-xs"
                                        >
                                            <span v-if="member.defense" title="Defense"
                                                ><Shield class="mr-0.5 inline size-4 sm:size-3.5" />{{ member.defense }}</span
                                            >
                                            <span v-if="member.willpower" title="Willpower"
                                                ><ShieldAlert class="mr-0.5 inline size-4 sm:size-3.5" />{{ member.willpower }}</span
                                            >
                                            <span v-if="member.speed" title="Speed"
                                                ><Footprints class="mr-0.5 inline size-4 sm:size-3.5" />{{ member.speed }}</span
                                            >
                                            <span v-if="member.size" title="Size"
                                                ><Banana class="mr-0.5 inline size-4 sm:size-3.5" />{{ member.size }}</span
                                            >
                                        </div>
                                        <!-- Desktop: health pips inline -->
                                        <div class="hidden gap-0.5 sm:flex">
                                            <button
                                                v-for="pip in member.max_health"
                                                :key="'hp-' + pip"
                                                type="button"
                                                class="size-3 rounded-sm transition-colors"
                                                :class="[
                                                    pip <= member.current_health
                                                        ? member.current_health <= Math.ceil(member.max_health / 2)
                                                            ? 'bg-red-400/90'
                                                            : 'bg-white/60'
                                                        : 'bg-black/30 ring-1 ring-inset ring-white/10',
                                                    isObserver ? 'cursor-default' : 'cursor-pointer hover:bg-white/80',
                                                ]"
                                                :disabled="isObserver"
                                                :aria-label="`Set health to ${pip} of ${member.max_health}`"
                                                @click.stop="onHealthPipClick(member, pip, isObserver)"
                                            />
                                        </div>
                                        <template v-if="!isObserver">
                                            <div class="ml-auto flex shrink-0 items-center gap-0.5">
                                                <button class="rounded bg-black/20 p-2 hover:bg-black/40 sm:p-1" @click="updateHealth(member, -1)">
                                                    <Minus class="size-4 sm:size-3.5" />
                                                </button>
                                                <span class="flex min-w-[3rem] items-center justify-center gap-0.5 text-sm font-bold">
                                                    <Heart
                                                        class="size-3.5"
                                                        :class="member.current_health <= Math.ceil(member.max_health / 2) ? 'text-red-300' : ''"
                                                    />
                                                    {{ member.current_health }}/{{ member.max_health }}
                                                </span>
                                                <button class="rounded bg-black/20 p-2 hover:bg-black/40 sm:p-1" @click="updateHealth(member, 1)">
                                                    <Plus class="size-4 sm:size-3.5" />
                                                </button>
                                            </div>
                                        </template>
                                        <span v-else class="ml-auto flex shrink-0 items-center justify-center gap-0.5 text-sm font-bold">
                                            <Heart
                                                class="size-3.5"
                                                :class="member.current_health <= Math.ceil(member.max_health / 2) ? 'text-red-300' : ''"
                                            />
                                            {{ member.current_health }}/{{ member.max_health }}
                                        </span>
                                    </div>
                                    <!-- Line 4 (mobile): Action buttons -->
                                    <div v-if="!isObserver" class="mt-1 flex gap-1.5 pl-8 sm:hidden">
                                        <button
                                            class="flex items-center gap-1 rounded bg-black/30 px-2 py-1.5 text-amber-200 active:bg-black/50"
                                            @click.stop="openUpgradeDialog(member)"
                                        >
                                            <ArrowUpCircle class="size-3.5" /><span class="text-[10px] font-medium">Upgrades</span>
                                        </button>
                                        <button
                                            class="flex items-center gap-1 rounded bg-black/30 px-2 py-1.5 text-cyan-200 active:bg-black/50"
                                            @click.stop="openTokenDialog(member)"
                                        >
                                            <Puzzle class="size-3.5" /><span class="text-[10px] font-medium">Tokens</span>
                                        </button>
                                        <button
                                            class="flex items-center gap-1 rounded bg-black/30 px-2 py-1.5 text-blue-200 active:bg-black/50"
                                            @click.stop="openReplace(member)"
                                        >
                                            <Replace class="size-3.5" /><span class="text-[10px] font-medium">Replace</span>
                                        </button>
                                    </div>
                                </div>
                                <!-- Token badges -->
                                <div v-if="member.attached_tokens?.length" class="mt-1 flex flex-wrap gap-1">
                                    <Badge
                                        v-for="token in member.attached_tokens"
                                        :key="token.id"
                                        variant="secondary"
                                        class="group cursor-pointer gap-0.5 border border-cyan-500/50 bg-cyan-900/60 px-1.5 py-0.5 text-xs font-medium text-cyan-200 transition-colors hover:bg-cyan-800/70"
                                        @click="openTokenInfo(token.id, member)"
                                    >
                                        {{ token.name }}
                                        <button
                                            v-if="!isObserver"
                                            class="ml-0.5 shrink-0 rounded-full bg-white/15 p-0.5 text-white/80 transition-colors hover:bg-red-500/60 hover:text-white"
                                            aria-label="Remove token"
                                            @click.stop="quickRemoveToken(member, token.id)"
                                        >
                                            <X class="size-2.5" />
                                        </button>
                                    </Badge>
                                </div>
                                <!-- Attached upgrades -->
                                <div v-if="member.attached_upgrades?.length" class="mt-1 space-y-0.5 pl-3">
                                    <div
                                        v-for="upgrade in member.attached_upgrades"
                                        :key="'au-' + upgrade.id"
                                        class="rounded bg-black/20 px-2 py-1 text-sm"
                                        :class="upgrade.front_image ? 'cursor-pointer hover:bg-black/30' : ''"
                                    >
                                        <div
                                            class="flex items-center gap-1.5"
                                            :role="upgrade.front_image ? 'button' : undefined"
                                            :tabindex="upgrade.front_image ? 0 : undefined"
                                            @click="openAttachedUpgradePreview(upgrade)"
                                            @keydown.enter="openAttachedUpgradePreview(upgrade)"
                                        >
                                            <ArrowUpCircle class="size-3.5 shrink-0 text-amber-300" />
                                            <template v-if="(upgrade as any).loot_side">
                                                <span
                                                    class="inline-flex h-4 min-w-4 shrink-0 items-center justify-center rounded bg-amber-400 px-1 text-[10px] font-bold uppercase text-black"
                                                    :title="`Side ${(upgrade as any).loot_side.toUpperCase()} active`"
                                                >
                                                    {{ (upgrade as any).loot_side.toUpperCase() }}
                                                </span>
                                                <span
                                                    v-if="lootCardById((upgrade as any).loot_card_id)"
                                                    class="inline-flex h-4 shrink-0 items-center gap-0.5 rounded border border-white/30 bg-black/30 px-1 font-mono text-[10px] tabular-nums"
                                                >
                                                    {{ lootCardById((upgrade as any).loot_card_id)?.value_label
                                                    }}<GameIcon
                                                        v-if="lootCardSuitIcon(lootCardById((upgrade as any).loot_card_id)?.suit)"
                                                        :type="lootCardSuitIcon(lootCardById((upgrade as any).loot_card_id)?.suit) as string"
                                                        class-name="h-2.5 inline-block"
                                                    />
                                                </span>
                                            </template>
                                            <span class="min-w-0 flex-1 truncate font-medium">{{ upgrade.name }}</span>
                                            <button
                                                v-if="!isObserver"
                                                class="shrink-0 rounded-full bg-white/15 p-0.5 text-white/80 transition-colors hover:bg-red-500/60 hover:text-white"
                                                aria-label="Remove upgrade"
                                                @click.stop="quickRemoveUpgrade(member, upgrade.id)"
                                            >
                                                <X class="size-3" />
                                            </button>
                                        </div>
                                        <PowerBarBubbles
                                            v-if="upgradePowerMax(upgrade.id) > 0"
                                            class="mt-1 pl-5"
                                            :max="upgradePowerMax(upgrade.id)"
                                            :current="upgrade.current_power_bar ?? upgradePowerMax(upgrade.id)"
                                            :readonly="isObserver"
                                            compact
                                            @update="(v) => setMemberUpgradePowerBar(member, upgrade.id, v)"
                                        />
                                    </div>
                                </div>
                                <!-- Inline card preview -->
                                <Transition
                                    enter-active-class="transition-all duration-300 ease-out"
                                    leave-active-class="transition-all duration-200 ease-in"
                                    enter-from-class="max-h-0 opacity-0"
                                    enter-to-class="max-h-[600px] opacity-100"
                                    leave-from-class="max-h-[600px] opacity-100"
                                    leave-to-class="max-h-0 opacity-0"
                                >
                                    <div
                                        v-if="expandedMyCards.has(member.id) && member.front_image"
                                        class="mt-2 overflow-hidden [&_img]:max-h-[70dvh] [&_img]:w-auto [&_img]:object-contain xl:[&_img]:max-h-[50dvh]"
                                    >
                                        <CharacterCardView
                                            :miniature="{
                                                id: member.id,
                                                display_name: member.display_name,
                                                slug: '',
                                                front_image: member.front_image,
                                                back_image: member.back_image,
                                            }"
                                            :show-link="false"
                                            :show-collection="false"
                                        />
                                    </div>
                                </Transition>
                            </div>
                            <div
                                v-for="member in myKilledMembers"
                                :key="'killed-' + member.id"
                                class="flex items-center justify-between rounded-md border border-border/40 bg-muted/40 px-2 py-1.5 text-sm text-muted-foreground line-through opacity-60"
                            >
                                <button class="cursor-pointer hover:underline" @click="openMemberPreview(member)">{{ member.display_name }}</button>
                                <button v-if="!isObserver" class="rounded p-0.5 text-green-600 hover:bg-green-500/20" @click="reviveMember(member)">
                                    <RotateCcw class="size-3.5" />
                                </button>
                            </div>
                        </div>
                        <Button
                            v-if="!isObserver && !isBonanza"
                            variant="outline"
                            size="sm"
                            class="mt-2 w-full gap-1 text-xs"
                            @click="openSummonForSlot(1)"
                        >
                            <Plus class="size-3" /> Summon
                        </Button>
                        <!-- Crew References -->
                        <details open class="mt-2 rounded-lg border" @toggle="($event.target as HTMLDetailsElement)?.open && toggleMyRefs()">
                            <summary class="cursor-pointer px-2 py-1.5 text-[11px] font-medium text-muted-foreground hover:text-foreground">
                                <Puzzle class="mr-1 inline size-3" />References
                            </summary>
                            <CrewBuilderReferences
                                :references="myReferences"
                                :loading="false"
                                compact
                                :enable-quick-add="!isObserver"
                                @quick-add-token="openQuickAddToken"
                            />
                        </details>
                    </div>

                    <!-- Column 3: Opponent Crew. Hidden entirely for Bonanza —
                         the format is a personal-tracking surface for one model
                         in an FFA, so there's nothing meaningful to render for
                         the (always-empty) slot 2. -->
                    <div v-if="!isBonanza" :class="gameplayColumnClass('opponent')">
                        <!-- Transient summon/replace banner for the opponent column (solo mode summoning to slot 2) -->
                        <Transition
                            enter-active-class="transition-all duration-200 ease-out"
                            leave-active-class="transition-all duration-200 ease-in"
                            enter-from-class="opacity-0 -translate-y-1"
                            leave-to-class="opacity-0 -translate-y-1"
                        >
                            <button
                                v-if="summonBanner && summonBanner.slot === (opponent?.slot ?? 2)"
                                type="button"
                                class="mb-2 flex w-full items-center justify-between gap-2 rounded-md border border-primary/40 bg-primary/10 px-3 py-2 text-left text-xs transition-colors hover:bg-primary/15"
                                @click="clickSummonBanner"
                            >
                                <span
                                    ><strong>{{ summonBanner.name }}</strong> added. Tap to change sculpt.</span
                                >
                                <X class="size-3.5 shrink-0 text-muted-foreground" @click.stop="dismissSummonBanner" />
                            </button>
                        </Transition>
                        <div class="mb-1 flex items-center justify-between">
                            <div class="flex items-center gap-1">
                                <h3 class="text-sm font-semibold">{{ playerName(opponent) }}</h3>
                                <button
                                    class="rounded p-0.5 hover:bg-muted"
                                    :title="allOpponentCardsExpanded ? 'Collapse all cards' : 'Expand all cards'"
                                    aria-label="Toggle all cards"
                                    @click="toggleAllCards('opponent')"
                                >
                                    <Layers class="size-3.5" :class="allOpponentCardsExpanded ? 'text-amber-500' : 'text-muted-foreground'" />
                                </button>
                                <button
                                    class="hidden rounded p-0.5 hover:bg-muted lg:inline-flex"
                                    title="View all crew cards"
                                    aria-label="View all crew cards"
                                    @click="openAllCards('opponent')"
                                >
                                    <LayoutGrid class="size-3.5 text-muted-foreground" />
                                </button>
                            </div>
                            <div class="flex items-center gap-1">
                                <template v-if="isSolo && !isObserver">
                                    <button class="rounded p-0.5 hover:bg-muted" @click="updateOpponentSoulstonePool(-1)">
                                        <Minus class="size-3" />
                                    </button>
                                </template>
                                <span
                                    class="flex min-w-[3rem] items-center justify-center gap-0.5 text-xs font-bold"
                                    :class="!isSolo || isObserver ? 'text-muted-foreground' : ''"
                                >
                                    {{ opponent?.soulstone_pool ?? 0 }}<GameIcon type="soulstone" class-name="h-3 inline-block" />
                                </span>
                                <template v-if="isSolo && !isObserver">
                                    <button class="rounded p-0.5 hover:bg-muted" @click="updateOpponentSoulstonePool(1)">
                                        <Plus class="size-3" />
                                    </button>
                                </template>
                            </div>
                        </div>
                        <Transition
                            enter-active-class="transition-all duration-300 ease-out"
                            leave-active-class="transition-all duration-500 ease-in"
                            enter-from-class="max-h-0 opacity-0"
                            enter-to-class="max-h-10 opacity-100"
                            leave-from-class="max-h-10 opacity-100"
                            leave-to-class="max-h-0 opacity-0"
                        >
                            <div
                                v-if="soulstoneAwardSlot === 2"
                                class="mb-2 overflow-hidden rounded-md bg-amber-500/10 px-2 py-1 text-center text-[11px] text-amber-700 dark:text-amber-400"
                            >
                                +1<GameIcon type="soulstone" class-name="mx-0.5 h-3 inline-block" /> from {{ soulstoneAwardName }}'s death
                            </div>
                        </Transition>
                        <div v-if="opponentCrewMembers.length" class="mb-2 text-[10px] text-muted-foreground">
                            Activations:
                            <span class="font-medium text-foreground">{{ opponentCrewMembers.filter((m: any) => !m.is_activated).length }}</span
                            >/<span>{{ opponentCrewMembers.length }}</span> remaining
                        </div>

                        <!-- Opponent faction/master info (when no crew) -->
                        <div
                            v-if="isSolo && opponent?.crew_skipped && !opponentCrewMembers.length"
                            class="mb-3 flex items-center gap-2 rounded-md border border-dashed p-2"
                        >
                            <FactionLogo v-if="opponent?.faction" :faction="opponent.faction" class-name="size-6" />
                            <div class="min-w-0 text-xs">
                                <div v-if="opponent?.master_name" class="font-medium">{{ opponent.master_name }}</div>
                                <div v-if="opponent?.role" class="capitalize text-muted-foreground">{{ opponent.role }}</div>
                            </div>
                        </div>

                        <!-- Opponent Reference Upgrades -->
                        <div v-if="opponentCrewUpgrades.length" class="mb-2 space-y-1">
                            <div
                                v-for="upgrade in opponentCrewUpgrades"
                                :key="upgrade.id"
                                class="rounded-md border px-2 py-1.5 text-sm transition-colors"
                                :class="[
                                    opponentActiveUpgradeId === upgrade.id
                                        ? 'border-amber-500/50 bg-amber-500/10'
                                        : 'border-border/50 bg-accent/30 opacity-60',
                                    upgrade.front_image ? 'cursor-pointer hover:bg-accent' : '',
                                ]"
                                @click="openUpgradePreview(upgrade)"
                            >
                                <div class="flex items-center gap-1.5">
                                    <Star
                                        class="size-3.5 shrink-0"
                                        :class="opponentActiveUpgradeId === upgrade.id ? 'fill-amber-500 text-amber-500' : 'text-muted-foreground'"
                                    />
                                    <span class="flex-1 font-semibold">{{ upgrade.name }}</span>
                                    <button
                                        v-if="opponentUpgradeMode === 'swappable' && isSolo && !isObserver && opponentActiveUpgradeId !== upgrade.id"
                                        class="rounded bg-amber-500/20 px-1.5 py-0.5 text-[10px] font-medium text-amber-600 hover:bg-amber-500/30 dark:text-amber-400"
                                        @click.stop="swapCrewUpgrade(upgrade.id, 2)"
                                    >
                                        Activate
                                    </button>
                                    <Badge
                                        v-if="opponentActiveUpgradeId === upgrade.id"
                                        variant="outline"
                                        class="border-amber-500/50 px-1.5 py-0 text-[9px] text-amber-600 dark:text-amber-400"
                                        >Active</Badge
                                    >
                                </div>
                                <PowerBarBubbles
                                    v-if="(upgrade.power_bar_count ?? 0) > 0"
                                    class="mt-1 pl-5"
                                    :max="upgrade.power_bar_count"
                                    :current="crewUpgradePowerCurrent(opponent, upgrade.id, upgrade.power_bar_count)"
                                    :readonly="!isSolo || isObserver"
                                    compact
                                    @update="(v) => setCrewUpgradePowerBar(opponent, upgrade.id, v, 2)"
                                />
                            </div>
                        </div>
                        <div class="space-y-1">
                            <div
                                v-for="member in opponentCrewMembers"
                                :key="member.id"
                                :class="factionBackground(member.faction ?? opponent?.faction ?? '')"
                                class="rounded-md border border-white/20 px-2 py-1.5 text-white"
                                :style="member.is_activated ? 'opacity: 0.7' : ''"
                            >
                                <div class="min-w-0 flex-1">
                                    <!-- Line 1: Activation + Name (+ desktop action buttons) -->
                                    <div class="flex items-center gap-1">
                                        <template v-if="isSolo && !isObserver">
                                            <button class="shrink-0 rounded p-1.5 hover:bg-white/20 sm:p-0.5" @click="toggleActivated(member)">
                                                <Check v-if="member.is_activated" class="size-4 text-green-300 sm:size-3.5" />
                                                <Circle v-else class="size-4 text-white/30 sm:size-3.5" />
                                            </button>
                                            <button
                                                class="hidden shrink-0 rounded bg-black/30 p-1 text-amber-200 hover:bg-black/50 sm:inline-flex"
                                                aria-label="Upgrades"
                                                title="Upgrades"
                                                @click.stop="openUpgradeDialog(member)"
                                            >
                                                <ArrowUpCircle class="size-3.5" />
                                            </button>
                                            <button
                                                class="hidden shrink-0 rounded bg-black/30 p-1 text-cyan-200 hover:bg-black/50 sm:inline-flex"
                                                aria-label="Tokens"
                                                title="Tokens"
                                                @click.stop="openTokenDialog(member)"
                                            >
                                                <Puzzle class="size-3.5" />
                                            </button>
                                            <button
                                                class="hidden shrink-0 rounded bg-black/30 p-1 text-blue-200 hover:bg-black/50 sm:inline-flex"
                                                aria-label="Replace"
                                                title="Replace"
                                                @click.stop="openReplace(member)"
                                            >
                                                <Replace class="size-3.5" />
                                            </button>
                                        </template>
                                        <template v-else>
                                            <Check
                                                v-if="member.is_activated"
                                                class="size-3.5 shrink-0 text-green-400 drop-shadow-[0_1px_2px_rgba(0,0,0,0.5)]"
                                            />
                                            <Circle v-else class="size-3.5 shrink-0 text-white/50 drop-shadow-[0_1px_2px_rgba(0,0,0,0.5)]" />
                                        </template>
                                        <button
                                            class="cursor-pointer truncate text-base font-semibold hover:underline"
                                            @click="openMemberPreview(member)"
                                        >
                                            {{ member.display_name }}
                                        </button>
                                        <button
                                            v-if="member.front_image"
                                            class="ml-auto shrink-0 rounded p-1 hover:bg-white/20"
                                            aria-label="Toggle card preview"
                                            title="Toggle card preview"
                                            @click.stop="toggleInlineCard(member.id, 'opponent')"
                                        >
                                            <Eye
                                                class="size-4 drop-shadow-[0_1px_2px_rgba(0,0,0,0.6)]"
                                                :class="expandedOpponentCards.has(member.id) ? 'text-amber-300' : 'text-white'"
                                            />
                                        </button>
                                    </div>
                                    <!-- Line 2 (mobile): Health pips under name / Desktop: combined with stats -->
                                    <div class="mt-0.5 flex sm:hidden" :class="isSolo ? 'pl-8' : 'pl-5'">
                                        <div class="flex gap-0.5">
                                            <button
                                                v-for="pip in member.max_health"
                                                :key="'ohp-m-' + pip"
                                                type="button"
                                                class="size-3.5 rounded-sm transition-colors"
                                                :class="[
                                                    pip <= member.current_health
                                                        ? member.current_health <= Math.ceil(member.max_health / 2)
                                                            ? 'bg-red-400/90'
                                                            : 'bg-white/60'
                                                        : 'bg-black/30 ring-1 ring-inset ring-white/10',
                                                    !isSolo || isObserver ? 'cursor-default' : 'cursor-pointer hover:bg-white/80',
                                                ]"
                                                :disabled="!isSolo || isObserver"
                                                :aria-label="`Set health to ${pip} of ${member.max_health}`"
                                                @click.stop="onHealthPipClick(member, pip, !isSolo || isObserver)"
                                            />
                                        </div>
                                    </div>
                                    <!-- Line 3 (mobile): Stats + Health controls / Desktop: Stats + pips + controls on one row -->
                                    <div class="mt-0.5 flex items-center gap-2" :class="isSolo ? 'pl-8 sm:pl-6' : 'pl-5'">
                                        <div
                                            v-if="member.defense || member.willpower || member.speed || member.size"
                                            class="flex gap-1.5 text-sm font-semibold text-white drop-shadow-[0_1px_2px_rgba(0,0,0,0.6)] sm:text-xs"
                                        >
                                            <span v-if="member.defense" title="Defense"
                                                ><Shield class="mr-0.5 inline size-4 sm:size-3.5" />{{ member.defense }}</span
                                            >
                                            <span v-if="member.willpower" title="Willpower"
                                                ><ShieldAlert class="mr-0.5 inline size-4 sm:size-3.5" />{{ member.willpower }}</span
                                            >
                                            <span v-if="member.speed" title="Speed"
                                                ><Footprints class="mr-0.5 inline size-4 sm:size-3.5" />{{ member.speed }}</span
                                            >
                                            <span v-if="member.size" title="Size"
                                                ><Banana class="mr-0.5 inline size-4 sm:size-3.5" />{{ member.size }}</span
                                            >
                                        </div>
                                        <!-- Desktop: health pips inline -->
                                        <div class="hidden gap-0.5 sm:flex">
                                            <button
                                                v-for="pip in member.max_health"
                                                :key="'ohp-' + pip"
                                                type="button"
                                                class="size-3 rounded-sm transition-colors"
                                                :class="[
                                                    pip <= member.current_health
                                                        ? member.current_health <= Math.ceil(member.max_health / 2)
                                                            ? 'bg-red-400/90'
                                                            : 'bg-white/60'
                                                        : 'bg-black/30 ring-1 ring-inset ring-white/10',
                                                    !isSolo || isObserver ? 'cursor-default' : 'cursor-pointer hover:bg-white/80',
                                                ]"
                                                :disabled="!isSolo || isObserver"
                                                :aria-label="`Set health to ${pip} of ${member.max_health}`"
                                                @click.stop="onHealthPipClick(member, pip, !isSolo || isObserver)"
                                            />
                                        </div>
                                        <template v-if="isSolo && !isObserver">
                                            <div class="ml-auto flex shrink-0 items-center gap-0.5">
                                                <button class="rounded bg-black/20 p-2 hover:bg-black/40 sm:p-1" @click="updateHealth(member, -1)">
                                                    <Minus class="size-4 sm:size-3.5" />
                                                </button>
                                                <span class="flex min-w-[3rem] items-center justify-center gap-0.5 text-sm font-bold">
                                                    <Heart
                                                        class="size-3.5"
                                                        :class="member.current_health <= Math.ceil(member.max_health / 2) ? 'text-red-300' : ''"
                                                    />
                                                    {{ member.current_health }}/{{ member.max_health }}
                                                </span>
                                                <button class="rounded bg-black/20 p-2 hover:bg-black/40 sm:p-1" @click="updateHealth(member, 1)">
                                                    <Plus class="size-4 sm:size-3.5" />
                                                </button>
                                            </div>
                                        </template>
                                        <span v-else class="ml-auto flex shrink-0 items-center justify-center gap-0.5 text-sm font-bold">
                                            <Heart
                                                class="size-3.5"
                                                :class="member.current_health <= Math.ceil(member.max_health / 2) ? 'text-red-300' : ''"
                                            />
                                            {{ member.current_health }}/{{ member.max_health }}
                                        </span>
                                    </div>
                                    <!-- Line 4 (mobile): Action buttons -->
                                    <div v-if="isSolo && !isObserver" class="mt-1 flex gap-1.5 pl-8 sm:hidden">
                                        <button
                                            class="flex items-center gap-1 rounded bg-black/30 px-2 py-1.5 text-amber-200 active:bg-black/50"
                                            @click.stop="openUpgradeDialog(member)"
                                        >
                                            <ArrowUpCircle class="size-3.5" /><span class="text-[10px] font-medium">Upgrades</span>
                                        </button>
                                        <button
                                            class="flex items-center gap-1 rounded bg-black/30 px-2 py-1.5 text-cyan-200 active:bg-black/50"
                                            @click.stop="openTokenDialog(member)"
                                        >
                                            <Puzzle class="size-3.5" /><span class="text-[10px] font-medium">Tokens</span>
                                        </button>
                                        <button
                                            class="flex items-center gap-1 rounded bg-black/30 px-2 py-1.5 text-blue-200 active:bg-black/50"
                                            @click.stop="openReplace(member)"
                                        >
                                            <Replace class="size-3.5" /><span class="text-[10px] font-medium">Replace</span>
                                        </button>
                                    </div>
                                </div>
                                <!-- Token badges -->
                                <div v-if="member.attached_tokens?.length" class="mt-1 flex flex-wrap gap-1">
                                    <Badge
                                        v-for="token in member.attached_tokens"
                                        :key="token.id"
                                        variant="secondary"
                                        class="cursor-pointer gap-0.5 border border-cyan-500/50 bg-cyan-900/60 px-1.5 py-0.5 text-xs font-medium text-cyan-200 transition-colors hover:bg-cyan-800/70"
                                        @click="openTokenInfo(token.id, member)"
                                    >
                                        {{ token.name }}
                                        <button
                                            v-if="isSolo && !isObserver"
                                            class="ml-0.5 shrink-0 rounded-full bg-white/15 p-0.5 text-white/80 transition-colors hover:bg-red-500/60 hover:text-white"
                                            aria-label="Remove token"
                                            @click.stop="quickRemoveToken(member, token.id)"
                                        >
                                            <X class="size-2.5" />
                                        </button>
                                    </Badge>
                                </div>
                                <!-- Attached upgrades -->
                                <div v-if="member.attached_upgrades?.length" class="mt-1 space-y-0.5 pl-3">
                                    <div
                                        v-for="upgrade in member.attached_upgrades"
                                        :key="'oau-' + upgrade.id"
                                        class="rounded bg-black/20 px-2 py-1 text-sm"
                                        :class="upgrade.front_image ? 'cursor-pointer hover:bg-black/30' : ''"
                                    >
                                        <div
                                            class="flex items-center gap-1.5"
                                            :role="upgrade.front_image ? 'button' : undefined"
                                            :tabindex="upgrade.front_image ? 0 : undefined"
                                            @click="openAttachedUpgradePreview(upgrade)"
                                            @keydown.enter="openAttachedUpgradePreview(upgrade)"
                                        >
                                            <ArrowUpCircle class="size-3.5 shrink-0 text-amber-300" />
                                            <template v-if="(upgrade as any).loot_side">
                                                <span
                                                    class="inline-flex h-4 min-w-4 shrink-0 items-center justify-center rounded bg-amber-400 px-1 text-[10px] font-bold uppercase text-black"
                                                    :title="`Side ${(upgrade as any).loot_side.toUpperCase()} active`"
                                                >
                                                    {{ (upgrade as any).loot_side.toUpperCase() }}
                                                </span>
                                                <span
                                                    v-if="lootCardById((upgrade as any).loot_card_id)"
                                                    class="inline-flex h-4 shrink-0 items-center gap-0.5 rounded border border-white/30 bg-black/30 px-1 font-mono text-[10px] tabular-nums"
                                                >
                                                    {{ lootCardById((upgrade as any).loot_card_id)?.value_label
                                                    }}<GameIcon
                                                        v-if="lootCardSuitIcon(lootCardById((upgrade as any).loot_card_id)?.suit)"
                                                        :type="lootCardSuitIcon(lootCardById((upgrade as any).loot_card_id)?.suit) as string"
                                                        class-name="h-2.5 inline-block"
                                                    />
                                                </span>
                                            </template>
                                            <span class="min-w-0 flex-1 truncate font-medium">{{ upgrade.name }}</span>
                                            <button
                                                v-if="isSolo && !isObserver"
                                                class="shrink-0 rounded-full bg-white/15 p-0.5 text-white/80 transition-colors hover:bg-red-500/60 hover:text-white"
                                                aria-label="Remove upgrade"
                                                @click.stop="quickRemoveUpgrade(member, upgrade.id)"
                                            >
                                                <X class="size-3" />
                                            </button>
                                        </div>
                                        <PowerBarBubbles
                                            v-if="upgradePowerMax(upgrade.id) > 0"
                                            class="mt-1 pl-5"
                                            :max="upgradePowerMax(upgrade.id)"
                                            :current="upgrade.current_power_bar ?? upgradePowerMax(upgrade.id)"
                                            :readonly="!isSolo || isObserver"
                                            compact
                                            @update="(v) => setMemberUpgradePowerBar(member, upgrade.id, v)"
                                        />
                                    </div>
                                </div>
                                <!-- Inline card preview -->
                                <Transition
                                    enter-active-class="transition-all duration-300 ease-out"
                                    leave-active-class="transition-all duration-200 ease-in"
                                    enter-from-class="max-h-0 opacity-0"
                                    enter-to-class="max-h-[600px] opacity-100"
                                    leave-from-class="max-h-[600px] opacity-100"
                                    leave-to-class="max-h-0 opacity-0"
                                >
                                    <div
                                        v-if="expandedOpponentCards.has(member.id) && member.front_image"
                                        class="mt-2 overflow-hidden [&_img]:max-h-[70dvh] [&_img]:w-auto [&_img]:object-contain xl:[&_img]:max-h-[50dvh]"
                                    >
                                        <CharacterCardView
                                            :miniature="{
                                                id: member.id,
                                                display_name: member.display_name,
                                                slug: '',
                                                front_image: member.front_image,
                                                back_image: member.back_image,
                                            }"
                                            :show-link="false"
                                            :show-collection="false"
                                        />
                                    </div>
                                </Transition>
                            </div>
                            <div
                                v-for="member in opponentKilledMembers"
                                :key="'killed-' + member.id"
                                class="flex items-center justify-between rounded-md border border-border/40 bg-muted/40 px-2 py-1.5 text-sm text-muted-foreground line-through opacity-60"
                            >
                                <button class="cursor-pointer hover:underline" @click="openMemberPreview(member)">{{ member.display_name }}</button>
                                <button
                                    v-if="isSolo && !isObserver"
                                    class="rounded p-0.5 text-green-600 hover:bg-green-500/20"
                                    @click="reviveMember(member)"
                                >
                                    <RotateCcw class="size-3.5" />
                                </button>
                            </div>
                        </div>
                        <!-- Solo: summon for opponent (not in Bonanza — solo-vs-loot, no summoning) -->
                        <Button
                            v-if="isSolo && !isObserver && !isBonanza && opponentCrewMembers.length"
                            variant="outline"
                            size="sm"
                            class="mt-2 w-full gap-1 text-xs"
                            @click="openSummonForSlot(2)"
                        >
                            <Plus class="size-3" /> Summon
                        </Button>
                        <!-- Opponent Crew References -->
                        <details
                            v-if="opponentCrewMembers.length"
                            open
                            class="mt-2 rounded-lg border"
                            @toggle="($event.target as HTMLDetailsElement)?.open && toggleOpponentRefs()"
                        >
                            <summary class="cursor-pointer px-2 py-1.5 text-[11px] font-medium text-muted-foreground hover:text-foreground">
                                <Puzzle class="mr-1 inline size-3" />References
                            </summary>
                            <CrewBuilderReferences :references="opponentReferences" :loading="false" compact />
                        </details>
                    </div>
                </div>
            </template>

            <!-- ═══ COMPLETED / ABANDONED ═══ -->
            <GameSummaryPanel
                v-if="GAME_FINISHED_STATUSES.includes(game.status)"
                :game="game"
                :is-solo="isSolo"
                :is-bonanza="isBonanza"
                :deployment="deployment"
                :schemes="schemes"
                :starting-crews="starting_crews"
                :my-player="myPlayer"
                :opponent-player="opponentPlayer"
                :scheme-lookup="allKnownSchemes"
                :loot-card-catalog="lootCardCatalog"
                @open-scheme="openSchemeDrawer"
                @open-deployment="deploymentDrawerOpen = true"
                @open-strategy="strategyDrawerOpen = true"
                @open-qr="openQR"
                @open-member-preview="openMemberPreview"
                @open-upgrade-preview="openUpgradePreview"
            />
        </div>
    </div>

    <!-- Edit Scenario Drawer -->
    <GameEditScenarioDrawer
        :open="editScenarioOpen"
        :deployments="all_deployments"
        :strategies="all_strategies"
        :edit-deployment="editDeployment"
        :edit-strategy="editStrategy"
        :edit-scheme-pool="editSchemePool"
        :available-schemes="availableSchemes"
        @update:open="editScenarioOpen = $event"
        @update:edit-deployment="editDeployment = $event"
        @update:edit-strategy="editStrategy = $event"
        @update:edit-scheme-pool-at="(idx, v) => (editSchemePool[idx] = v)"
        @regenerate="
            regenerateScenario();
            editScenarioOpen = false;
        "
        @save="saveScenarioFromDrawer"
    />

    <!-- Strategy Drawer -->
    <Drawer v-model:open="strategyDrawerOpen">
        <DrawerContent>
            <button
                class="absolute right-3 top-3 z-10 rounded-full bg-muted p-1.5 text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                aria-label="Close"
                @click="strategyDrawerOpen = false"
            >
                <X class="size-4" />
            </button>
            <div v-if="game.strategy" class="mx-auto w-full max-w-md">
                <DrawerHeader class="pb-2">
                    <DrawerTitle class="text-center">{{ game.strategy.name }}</DrawerTitle>
                    <div class="mt-1 text-center text-xs text-muted-foreground">Strategy</div>
                </DrawerHeader>
                <div class="px-4 pb-4">
                    <img
                        v-if="game.strategy.image_url"
                        :src="game.strategy.image_url"
                        :alt="game.strategy.name"
                        class="w-full rounded-lg"
                        loading="lazy"
                        decoding="async"
                    />
                    <template v-else>
                        <div v-if="game.strategy.setup" class="mb-3 text-sm">
                            <div class="mb-1 text-xs font-medium uppercase text-muted-foreground">Setup</div>
                            <p class="text-muted-foreground">{{ game.strategy.setup }}</p>
                        </div>
                        <div v-if="game.strategy.rules" class="mb-3 text-sm">
                            <div class="mb-1 text-xs font-medium uppercase text-muted-foreground">Rules</div>
                            <p class="text-muted-foreground">{{ game.strategy.rules }}</p>
                        </div>
                        <div v-if="game.strategy.scoring" class="mb-3 text-sm">
                            <div class="mb-1 text-xs font-medium uppercase text-muted-foreground">Scoring</div>
                            <p class="text-muted-foreground">{{ game.strategy.scoring }}</p>
                        </div>
                        <div v-if="game.strategy.additional_scoring" class="text-sm">
                            <div class="mb-1 text-xs font-medium uppercase text-muted-foreground">Additional Scoring</div>
                            <p class="text-muted-foreground">{{ game.strategy.additional_scoring }}</p>
                        </div>
                    </template>
                </div>
                <DrawerFooter class="pt-2">
                    <DrawerClose as-child>
                        <Button variant="outline">Close</Button>
                    </DrawerClose>
                </DrawerFooter>
            </div>
        </DrawerContent>
    </Drawer>

    <!-- Deployment Drawer -->
    <Drawer v-model:open="deploymentDrawerOpen">
        <DrawerContent>
            <button
                class="absolute right-3 top-3 z-10 rounded-full bg-muted p-1.5 text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                aria-label="Close"
                @click="deploymentDrawerOpen = false"
            >
                <X class="size-4" />
            </button>
            <div v-if="deployment" class="mx-auto w-full max-w-md">
                <DrawerHeader class="pb-2">
                    <DrawerTitle class="text-center">{{ deployment.label }}</DrawerTitle>
                    <div class="mt-1 text-center text-xs text-muted-foreground">Deployment</div>
                </DrawerHeader>
                <div class="px-4 pb-4">
                    <img
                        v-if="deployment.image_url"
                        :src="deployment.image_url"
                        :alt="deployment.label"
                        class="w-full rounded-lg"
                        loading="lazy"
                        decoding="async"
                    />
                    <p v-else-if="deployment.description" class="text-sm text-muted-foreground">{{ deployment.description }}</p>
                </div>
                <DrawerFooter class="pt-2">
                    <DrawerClose as-child>
                        <Button variant="outline">Close</Button>
                    </DrawerClose>
                </DrawerFooter>
            </div>
        </DrawerContent>
    </Drawer>

    <!-- Scheme Drawer -->
    <Drawer v-model:open="schemeDrawerOpen">
        <DrawerContent>
            <div v-if="activeScheme" class="mx-auto w-full max-w-md">
                <DrawerHeader class="pb-2">
                    <DrawerTitle class="text-center">{{ activeScheme.name }}</DrawerTitle>
                    <div class="mt-1 text-center text-xs text-muted-foreground">Scheme</div>
                </DrawerHeader>
                <div class="px-4 pb-4">
                    <img
                        v-if="activeScheme.image_url"
                        :src="activeScheme.image_url"
                        :alt="activeScheme.name"
                        class="w-full rounded-lg"
                        loading="lazy"
                        decoding="async"
                    />
                    <template v-else>
                        <div v-if="activeScheme.prerequisite" class="mb-3 text-sm">
                            <div class="mb-1 text-xs font-medium uppercase text-muted-foreground">Prerequisite</div>
                            <p class="text-muted-foreground">{{ activeScheme.prerequisite }}</p>
                        </div>
                        <div v-if="activeScheme.reveal" class="mb-3 text-sm">
                            <div class="mb-1 text-xs font-medium uppercase text-muted-foreground">Reveal</div>
                            <p class="text-muted-foreground">{{ activeScheme.reveal }}</p>
                        </div>
                        <div v-if="activeScheme.scoring" class="text-sm">
                            <div class="mb-1 text-xs font-medium uppercase text-muted-foreground">Scoring</div>
                            <p class="text-muted-foreground">{{ activeScheme.scoring }}</p>
                        </div>
                    </template>
                </div>
                <DrawerFooter class="pt-2">
                    <DrawerClose as-child>
                        <Button variant="outline">Close</Button>
                    </DrawerClose>
                </DrawerFooter>
            </div>
        </DrawerContent>
    </Drawer>

    <!-- Crew Member Card Preview Drawer -->
    <GameCrewMemberDrawer
        :open="crewMemberDrawerOpen"
        :member="previewMember"
        :miniatures="memberMiniatures"
        :can-change-sculpt="!isObserver && (isSolo || !!myPlayer?.crew_members?.some((m: any) => m.id === previewMember?.id))"
        :can-edit-notes="!isObserver && (isSolo || !!myPlayer?.crew_members?.some((m: any) => m.id === previewMember?.id))"
        @update:open="crewMemberDrawerOpen = $event"
        @sculpt-change="onSculptChange"
        @open-fullscreen="openCardFullscreen"
        @notes-change="onCrewMemberNotesChange"
    />

    <!-- Crew Card Preview Drawer -->
    <GameAttachedUpgradeDrawer :open="upgradeDrawerOpen" :upgrade="previewUpgrade" @update:open="upgradeDrawerOpen = $event" />

    <!-- Token Info Drawer -->
    <GameTokenInfoDrawer
        :open="tokenInfoDrawerOpen"
        :token="tokenInfoData"
        :member="tokenInfoMember"
        :can-remove="!isObserver"
        @update:open="tokenInfoDrawerOpen = $event"
        @remove="removeTokenFromInfo"
    />

    <!-- Opponent Scheme Dialog (Solo) — Multi-step (scored / discard / end-of-game) -->
    <GameOpponentSchemeDialog
        v-if="isSolo && !isObserver"
        :open="oppDialogOpen"
        :mode="oppDialogMode"
        :scheme-pool="opponentSchemePool"
        @update:open="oppDialogOpen = $event"
        @select="oppSelectScheme"
        @keep-hidden="oppKeepHidden"
        @cancel="oppCancelDialog"
    />

    <!-- Summon Dialog -->
    <GameSummonDialog
        :open="summonDialogOpen"
        :reference-characters="referenceCharacters"
        :results="summonResults"
        :search="summonSearch"
        :loading="summonLoading"
        :crew-count="summonCrewCount"
        @update:open="summonDialogOpen = $event"
        @update:search="searchSummon"
        @select="selectCharacterForSummon"
    />

    <!-- Replace Crew Member Dialog -->
    <GameReplaceDialog
        :open="replaceDialogOpen"
        :member="replaceMember"
        :reference-characters="referenceCharacters"
        :results="replaceResults"
        :search="replaceSearch"
        :loading="replaceLoading"
        @update:open="replaceDialogOpen = $event"
        @update:search="searchReplace"
        @select="selectCharacterForReplace"
    />

    <!-- Upgrade Dialog -->
    <GameUpgradeDialog
        :open="upgradeDialogOpen"
        :member="upgradeMember"
        :options="filteredUpgrades"
        :reference-ids="memberReferenceUpgradeIds"
        :search="upgradeSearch"
        :usage-count="upgradeUsageCount"
        @update:open="upgradeDialogOpen = $event"
        @update:search="upgradeSearch = $event"
        @toggle="toggleUpgrade"
    />

    <!-- Attached Upgrade Preview Drawer -->
    <GameAttachedUpgradeDrawer
        :open="attachedUpgradeDrawerOpen"
        :upgrade="previewAttachedUpgrade"
        @update:open="attachedUpgradeDrawerOpen = $event"
    />

    <!-- Token Dialog -->
    <GameTokenDialog
        :open="tokenDialogOpen"
        :member="tokenMember"
        :tokens="props.tokens"
        :reference-token-ids="referenceTokenIds"
        :search="tokenSearch"
        @update:open="tokenDialogOpen = $event"
        @update:search="tokenSearch = $event"
        @toggle="toggleToken"
        @remove="removeToken"
    />

    <!-- Quick Add token to multiple models -->
    <Dialog :open="quickAddOpen" @update:open="(v) => (quickAddOpen = v)">
        <DialogContent class="max-w-md">
            <DialogHeader>
                <DialogTitle>Add “{{ quickAddToken?.name }}” to models</DialogTitle>
                <DialogDescription>Select your living models to attach this token to.</DialogDescription>
            </DialogHeader>
            <div class="max-h-[50vh] space-y-1 overflow-y-auto">
                <label
                    v-for="m in myCrewMembers"
                    :key="m.id"
                    class="flex cursor-pointer items-center gap-2 rounded-md border px-2 py-1.5 text-sm transition-colors hover:bg-accent"
                >
                    <input type="checkbox" :checked="quickAddMemberIds.includes(m.id)" class="size-4" @change="toggleQuickAddMember(m.id)" />
                    <span class="min-w-0 flex-1 truncate">{{ m.display_name }}</span>
                </label>
                <p v-if="!myCrewMembers.length" class="py-3 text-center text-xs text-muted-foreground">No living models to add to.</p>
            </div>
            <div class="flex justify-end gap-2">
                <Button variant="outline" @click="quickAddOpen = false">Cancel</Button>
                <Button :disabled="!quickAddMemberIds.length" @click="submitQuickAdd">
                    Add to {{ quickAddMemberIds.length }} model{{ quickAddMemberIds.length === 1 ? '' : 's' }}
                </Button>
            </div>
        </DialogContent>
    </Dialog>

    <!-- Complete Game Confirmation Dialog -->
    <!-- Game Settings Dialog -->
    <Dialog v-model:open="gameSettingsOpen">
        <DialogContent class="max-w-sm">
            <DialogHeader>
                <DialogTitle>Game Settings</DialogTitle>
                <DialogDescription>Manage game options and sharing.</DialogDescription>
            </DialogHeader>
            <div class="space-y-3">
                <!-- Spectating toggle (creator only) -->
                <div v-if="!isObserver && isCreator" class="rounded-lg border p-3">
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="text-sm font-medium">Spectate Mode</div>
                            <div class="text-xs text-muted-foreground">Allow others to watch live</div>
                        </div>
                        <Switch v-model="spectateOn" @update:model-value="syncSpectateToggle" />
                    </div>

                    <!-- QR + copy link (visible when on) -->
                    <Transition
                        enter-active-class="transition-all duration-300 ease-out overflow-hidden"
                        leave-active-class="transition-all duration-200 ease-in overflow-hidden"
                        enter-from-class="max-h-0 opacity-0"
                        enter-to-class="max-h-96 opacity-100"
                        leave-from-class="max-h-96 opacity-100"
                        leave-to-class="max-h-0 opacity-0"
                    >
                        <div v-if="spectateOn" class="mt-3 flex flex-col items-center gap-3 border-t pt-3">
                            <img v-if="spectateQR" :src="spectateQR" alt="Spectate QR Code" class="rounded-lg" />
                            <p class="break-all text-center text-[10px] text-muted-foreground">{{ route('games.observe', game.uuid) }}</p>
                            <Button variant="outline" size="sm" class="w-full gap-1.5 text-xs" @click="copyObserveLink()">
                                <Copy class="size-3" />
                                {{ observeLinkCopied ? 'Copied!' : 'Copy Spectate Link' }}
                            </Button>
                        </div>
                    </Transition>
                </div>

                <!-- Non-creator observable view -->
                <div v-else-if="spectateOn" class="rounded-lg border p-3">
                    <div class="flex flex-col items-center gap-3">
                        <div class="text-sm font-medium">Spectate Link</div>
                        <img v-if="spectateQR" :src="spectateQR" alt="Spectate QR Code" class="rounded-lg" />
                        <p class="break-all text-center text-[10px] text-muted-foreground">{{ route('games.observe', game.uuid) }}</p>
                        <Button variant="outline" size="sm" class="w-full gap-1.5 text-xs" @click="copyObserveLink()">
                            <Copy class="size-3" />
                            {{ observeLinkCopied ? 'Copied!' : 'Copy Spectate Link' }}
                        </Button>
                    </div>
                </div>

                <!-- Automation settings -->
                <div v-if="!isObserver" class="rounded-lg border p-3">
                    <div class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Automation</div>
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="text-sm font-medium">Auto Soulstone on Kill</div>
                            <div class="text-xs text-muted-foreground">Add 1ss to pool when a non-peon, non-summoned model dies</div>
                        </div>
                        <Switch v-model="autoSoulstoneOnKill" @update:model-value="(v: boolean) => saveGameSetting('auto_soulstone_on_kill', v)" />
                    </div>
                </div>

                <!-- Complete game -->
                <button
                    v-if="!isObserver && !myPlayer?.is_game_complete"
                    class="flex w-full items-center gap-3 rounded-lg border p-3 text-left text-sm transition-colors hover:bg-muted/50"
                    @click="
                        gameSettingsOpen = false;
                        completeDialogOpen = true;
                    "
                >
                    <Check class="size-4 shrink-0 text-muted-foreground" />
                    <div class="flex-1">
                        <div class="font-medium">Mark Game Complete</div>
                        <div class="text-xs text-muted-foreground">Finalize scores and end the game</div>
                    </div>
                </button>

                <!-- Abandon game -->
                <button
                    v-if="!isObserver"
                    class="flex w-full items-center gap-3 rounded-lg border border-destructive/20 p-3 text-left text-sm transition-colors hover:bg-destructive/5"
                    @click="
                        gameSettingsOpen = false;
                        abandonDialogOpen = true;
                    "
                >
                    <Skull class="size-4 shrink-0 text-destructive" />
                    <div class="flex-1">
                        <div class="font-medium text-destructive">Abandon Game</div>
                        <div class="text-xs text-muted-foreground">End the game without scoring</div>
                    </div>
                </button>
            </div>
        </DialogContent>
    </Dialog>

    <GameCompleteDialog
        :open="completeDialogOpen"
        @update:open="completeDialogOpen = $event"
        @confirm="
            completeDialogOpen = false;
            markGameComplete();
        "
    />

    <GameAbandonDialog :open="abandonDialogOpen" @update:open="abandonDialogOpen = $event" @confirm="executeAbandon" />

    <!-- Confirm-before-submit dialog: surfaces the chosen VP and scheme so
         users can double-check before locking the turn in. -->
    <Dialog :open="editTurnDialogOpen" @update:open="(v: boolean) => (editTurnDialogOpen = v)">
        <DialogContent class="max-w-sm">
            <DialogHeader>
                <DialogTitle>Correct Turn {{ editTurnTarget?.turnNumber }} Score</DialogTitle>
                <DialogDescription>Fix a mis-clicked score. Updates the player's total VP automatically.</DialogDescription>
            </DialogHeader>
            <div v-if="editTurnTarget" class="space-y-3">
                <div class="space-y-1">
                    <label class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Strategy points</label>
                    <Input v-model.number="editTurnStrategy" type="number" min="0" max="2" class="h-9 text-sm" />
                </div>
                <div class="space-y-1">
                    <label class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Scheme points</label>
                    <Input v-model.number="editTurnScheme" type="number" min="0" max="3" class="h-9 text-sm" />
                </div>
                <div class="flex justify-end gap-2">
                    <Button variant="ghost" :disabled="editTurnSubmitting" @click="editTurnDialogOpen = false">Cancel</Button>
                    <Button :disabled="editTurnSubmitting" @click="submitEditTurn">
                        <Loader2 v-if="editTurnSubmitting" class="mr-2 size-4 animate-spin" />
                        Save
                    </Button>
                </div>
            </div>
        </DialogContent>
    </Dialog>

    <GameSubmitTurnDialog
        :open="submitTurnDialogOpen"
        :turn-number="game.current_turn"
        :strategy-points="strategyPoints"
        :strategy-bonus-used="strategyPoints === 2 || (strategyPoints === 1 && strategyBonusOnly)"
        :scheme-points="schemePoints"
        :current-scheme-name="findScheme(myDisplaySchemeId)?.name ?? null"
        :scheme-action="submitTurnSchemeAction"
        :next-scheme-name="findScheme(nextSchemeId)?.name ?? null"
        :next-scheme-model="nextSchemeModel || null"
        :next-scheme-marker="nextSchemeMarker || null"
        :next-scheme-terrain="nextSchemeTerrain || null"
        :submitting="scoringTurn"
        @update:open="submitTurnDialogOpen = $event"
        @confirm="confirmSubmitTurn"
    />

    <!-- Replace on Death Dialog -->
    <GameReplaceOnDeathDialog
        :open="replaceOnDeathDialogOpen"
        :replacements="replaceOnDeathReplacements"
        :warnings="replaceOnDeathWarnings"
        :has-selected="hasSelectedReplacements"
        @update:open="replaceOnDeathDialogOpen = $event"
        @toggle="
            (id) => {
                const r = replaceOnDeathReplacements.find((x) => x.id === id);
                if (r) r.selected = !r.selected;
            }
        "
        @confirm="confirmReplaceOnDeath"
        @dismiss="dismissReplaceOnDeath"
    />

    <!-- QR Code Dialog -->
    <QRCodeDialog v-if="qrDialogOpen" v-model:open="qrDialogOpen" :url="qrDialogUrl" :title="qrDialogTitle" />

    <!-- Card Fullscreen Dialog — backSrc is forwarded so members with a
         flip side get the in-dialog Flip button instead of needing two
         separate zoom buttons to view both faces. -->
    <GameCardFullscreenDialog
        :open="cardFullscreenOpen"
        :src="cardFullscreenSrc"
        :back-src="cardFullscreenBackSrc"
        :title="cardFullscreenTitle"
        @update:open="cardFullscreenOpen = $event"
    />

    <!-- All-cards desktop grid (per-side, opens from the layout-grid icon) -->
    <GameAllCardsDialog :open="allCardsDialogOpen" :title="allCardsTitle" :entries="allCardsEntries" @update:open="allCardsDialogOpen = $event" />

    <!-- Leave confirmation for in-progress games -->
    <GameLeaveDialog :open="confirmLeaveOpen" @stay="cancelLeave" @leave="confirmLeave" />
</template>
