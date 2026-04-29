<script setup lang="ts">
import CharacterCardView from '@/components/CharacterCardView.vue';
import CrewBuilderReferences from '@/components/CrewBuilderReferences.vue';
import FactionLogo from '@/components/FactionLogo.vue';
import GameAbandonDialog from '@/components/Game/GameAbandonDialog.vue';
import GameAttachedUpgradeDrawer from '@/components/Game/GameAttachedUpgradeDrawer.vue';
import GameCardFullscreenDialog from '@/components/Game/GameCardFullscreenDialog.vue';
import GameCompleteDialog from '@/components/Game/GameCompleteDialog.vue';
import GameCrewMemberDrawer from '@/components/Game/GameCrewMemberDrawer.vue';
import GameEditScenarioDrawer from '@/components/Game/GameEditScenarioDrawer.vue';
import GameLeaveDialog from '@/components/Game/GameLeaveDialog.vue';
import GameOpponentSchemeDialog from '@/components/Game/GameOpponentSchemeDialog.vue';
import GameReplaceDialog from '@/components/Game/GameReplaceDialog.vue';
import GameReplaceOnDeathDialog from '@/components/Game/GameReplaceOnDeathDialog.vue';
import GameSummonDialog from '@/components/Game/GameSummonDialog.vue';
import GameTokenDialog from '@/components/Game/GameTokenDialog.vue';
import GameTokenInfoDrawer from '@/components/Game/GameTokenInfoDrawer.vue';
import GameUpgradeDialog from '@/components/Game/GameUpgradeDialog.vue';
import PowerBarBubbles from '@/components/Game/PowerBarBubbles.vue';
import GameIcon from '@/components/GameIcon.vue';
import QRCodeDialog from '@/components/QRCodeDialog.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Drawer, DrawerClose, DrawerContent, DrawerFooter, DrawerHeader, DrawerTitle } from '@/components/ui/drawer';
import { Input } from '@/components/ui/input';
import { Switch } from '@/components/ui/switch';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useGameChannel } from '@/composables/useGameChannel';
import { useToast } from '@/composables/useToast';
import { MAX_SCHEME_POOL, MAX_SCHEME_PER_TURN, TURN_BANNER_VISIBLE_MS } from '@/pages/Games/constants';
import { type SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
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
    Loader2,
    Minus,
    PanelLeftClose,
    PanelLeftOpen,
    Pencil,
    Plus,
    Puzzle,
    QrCode,
    Replace,
    RotateCcw,
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

interface CrewMember {
    id: number;
    character_id: number | null;
    display_name: string;
    faction: string | null;
    current_health: number;
    max_health: number;
    defense: number | null;
    willpower: number | null;
    speed: number | null;
    size: number | null;
    cost: number;
    station: string | null;
    hiring_category: string;
    front_image: string | null;
    back_image: string | null;
    is_killed: boolean;
    is_summoned: boolean;
    is_activated: boolean;
    is_custom: boolean;
    attached_tokens: { id: number; name: string }[];
    attached_upgrades: { id: number; name: string; current_power_bar?: number | null }[];
    attached_markers: { id: number; name: string }[];
    notes: string | null;
    sort_order: number;
    game_player_id: number;
}

interface GamePlayer {
    id: number;
    slot: number;
    faction: string | null;
    master_name: string | null;
    master_id: number | null;
    crew_build_id: number | null;
    crew_skipped: boolean;
    current_scheme_id: number | null;
    scheme_notes: { note?: string; selected_model?: string; selected_marker?: string; terrain_note?: string } | null;
    role: string | null;
    total_points: number;
    soulstone_pool: number;
    opponent_name: string | null;
    is_turn_complete: boolean;
    is_game_complete: boolean;
    crew_members: CrewMember[];
    master: { id: number; crew_upgrades: any[]; crew_upgrade_mode: string | null } | null;
    crew_build: { id: number; crew_upgrade_id: number | null } | null;
    active_crew_upgrade_id: number | null;
    crew_upgrade_power_bars: Record<string, number> | null;
    user: { id: number; name: string } | null;
}

interface SchemeData {
    id: number;
    name: string;
    slug: string;
    image_url: string | null;
    prerequisite: string | null;
    reveal: string | null;
    scoring: string | null;
    requirements: any[];
    next_scheme_one_id: number | null;
    next_scheme_two_id: number | null;
    next_scheme_three_id: number | null;
}

interface DeploymentData {
    value: string;
    label: string;
    description: string;
    image_url: string | null;
}

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
}

interface MasterOption {
    name: string;
    faction: string;
    second_faction: string | null;
    is_alternate_leader: boolean;
    front_image: string | null;
    titles: MasterTitle[];
}

interface CrewMember {
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
    members: CrewMember[];
}

interface GameData {
    id: number;
    uuid: string;
    name: string | null;
    status: string;
    creator_id: number;
    encounter_size: number;
    season: string;
    current_turn: number;
    max_turns: number;
    is_tie: boolean;
    is_solo: boolean;
    is_observable: boolean;
    settings: { auto_soulstone_on_kill?: boolean } | null;
    winner_slot: number | null;
    strategy: { id: number; name: string; slug: string } | null;
    players: GamePlayer[];
    winner: { id: number; name: string } | null;
    created_at: string;
    started_at: string | null;
    completed_at: string | null;
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
    is_observer: boolean;
}>();

const page = usePage<SharedData>();
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
const canEditScenario = computed(() => {
    const editableStatuses = ['setup', 'faction_select', 'master_select', 'crew_select', 'scheme_select'];
    return editableStatuses.includes(props.game.status) && isCreator.value;
});
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
    const savePromise = fetch(route('games.scenario.update', props.game.uuid), {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json', ...csrfHeaders() },
        body: JSON.stringify(body),
    }).then((res) => {
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

// Re-roll the scenario (deployment/strategy/scheme pool). Uses raw fetch + an
// explicit router.reload — same pattern as saveScenarioFromDrawer — because
// `router.post` to a same-page redirect was not consistently re-pulling the
// `game` / `schemes` / `deployment` props, leaving the UI stale even though
// the DB had been updated.
const regenerateScenario = async () => {
    const regenPromise = fetch(route('games.scenario.regenerate', props.game.uuid), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', ...csrfHeaders() },
    }).then((res) => {
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
const joinUrl = computed(() => route('games.join', props.game.uuid));
const linkCopied = ref(false);
const copyJoinLink = async () => {
    await navigator.clipboard.writeText(joinUrl.value);
    linkCopied.value = true;
    setTimeout(() => (linkCopied.value = false), 2000);
};

// Setup submission
const submitting = ref(false);

// Scheme setup: pending selection with requirement fields
const pendingSchemeId = ref<number | null>(null);
const pendingSchemeModel = ref('');
const pendingSchemeMarker = ref('');
const pendingSchemeTerrainNote = ref('');
const pendingScheme = computed(() => (pendingSchemeId.value ? props.schemes.find((s) => s.id === pendingSchemeId.value) : null));
const pendingSchemeReqs = computed(() => pendingScheme.value?.requirements ?? []);
const pendingModelReq = computed(() => pendingSchemeReqs.value.find((r: any) => r.type === 'select_model') ?? null);
const pendingHasMarkerReq = computed(() => pendingSchemeReqs.value.some((r: any) => r.type === 'select_marker'));
const pendingHasTerrainReq = computed(() => pendingSchemeReqs.value.some((r: any) => r.type === 'terrain_note'));
const pendingModelLabel = computed(() => {
    const req = pendingModelReq.value;
    if (!req) return '';
    const parts: string[] = [];
    if (req.unique) parts.push('Unique');
    parts.push(req.allegiance === 'friendly' ? 'Friendly' : 'Enemy');
    parts.push('Model');
    if (req.cost_operator && req.cost_value != null) parts.push(`(Cost ${req.cost_operator} ${req.cost_value})`);
    return parts.join(' ');
});
const pendingModelOptions = computed(() => {
    const req = pendingModelReq.value;
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
const selectPendingScheme = (schemeId: number) => {
    pendingSchemeId.value = schemeId;
    pendingSchemeModel.value = '';
    pendingSchemeMarker.value = '';
    pendingSchemeTerrainNote.value = '';
};
const confirmPendingScheme = async () => {
    if (!pendingSchemeId.value) return;
    // Scheme + its notes save atomically in one setup-endpoint call. The
    // standalone scheme-notes endpoint is in_progress-gated and can't be
    // used pregame, so folding the notes into the scheme submit avoids a
    // silent 422 that previously dropped the user's selections.
    const notes: Record<string, string | null> = {
        note: null,
        selected_model: pendingSchemeModel.value || null,
        selected_marker: pendingSchemeMarker.value || null,
        terrain_note: pendingSchemeTerrainNote.value || null,
    };
    const hasNotes = notes.selected_model || notes.selected_marker || notes.terrain_note;

    await postSetup(route('games.setup.scheme', props.game.uuid), {
        scheme_id: pendingSchemeId.value,
        ...(isSolo.value ? { slot: mySlot.value } : {}),
        ...(hasNotes ? { scheme_notes: notes } : {}),
    });
    pendingSchemeId.value = null;
};
const csrfToken = () => document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';

const csrfHeaders = (): Record<string, string> => {
    // Prefer the XSRF-TOKEN cookie (stays in sync with session across partial reloads) over the meta tag
    const cookie = document.cookie.split('; ').find((c) => c.startsWith('XSRF-TOKEN='));
    if (cookie) return { 'X-XSRF-TOKEN': decodeURIComponent(cookie.split('=')[1]) };
    return { 'X-CSRF-TOKEN': csrfToken() };
};

const postSetup = async (endpoint: string, body: Record<string, unknown>) => {
    submitting.value = true;
    try {
        const res = await fetch(endpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', ...csrfHeaders() },
            body: JSON.stringify(body),
        });
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
const availableMasters = computed(() => {
    if (!myPlayer.value?.faction) return [];
    const f = myPlayer.value.faction;
    return props.masters.filter((m) => m.faction === f || m.second_faction === f || m.is_alternate_leader);
});
const selectedMasterName = ref<string | null>(null);

const confirmMasterSelection = () => {
    if (!selectedMasterName.value) return;
    const body: Record<string, unknown> = { master_name: selectedMasterName.value };
    if (isSolo.value) body.slot = mySlot.value;
    postSetup(route('games.setup.master', props.game.uuid), body);
};

// Master title switching during crew select
const masterTitleOptions = computed(() => {
    if (!myPlayer.value?.master_name) return [];
    const baseName = myPlayer.value.master_name.split(',')[0];
    const masterGroup = props.masters.find((m) => m.name === baseName);
    return masterGroup?.titles ?? [];
});

// Title filter for crew select (filters visible crews, doesn't submit)
const filterTitleId = ref<number | null>(null);

// Reset title filter when master changes
watch(
    () => myPlayer.value?.master_id,
    () => {
        filterTitleId.value = null;
    },
);

// Crew select
const expandedCrewId = ref<number | null>(null);
const expandedOpponentCrewId = ref<number | null>(null);
const newCrewUrl = computed(() => {
    const faction = myPlayer.value?.faction ?? '';
    const gameParam = '&from_game=' + encodeURIComponent(props.game.uuid);
    const masterId = myPlayer.value?.master_id;
    if (masterId) {
        return route('tools.crew_builder.editor') + '?step=hiring&faction=' + encodeURIComponent(faction) + '&master=' + masterId + gameParam;
    }
    const masterName = myPlayer.value?.master_name?.split(',')[0] ?? '';
    return (
        route('tools.crew_builder.editor') +
        '?step=title&faction=' +
        encodeURIComponent(faction) +
        '&master=' +
        encodeURIComponent(masterName) +
        gameParam
    );
});
const newOpponentCrewUrl = computed(() => {
    const faction = opponentPlayer.value?.faction ?? '';
    const gameParam = '&from_game=' + encodeURIComponent(props.game.uuid);
    const masterId = opponentPlayer.value?.master_id;
    if (masterId) {
        return route('tools.crew_builder.editor') + '?step=hiring&faction=' + encodeURIComponent(faction) + '&master=' + masterId + gameParam;
    }
    const masterName = opponentPlayer.value?.master_name?.split(',')[0] ?? '';
    return (
        route('tools.crew_builder.editor') +
        '?step=title&faction=' +
        encodeURIComponent(faction) +
        '&master=' +
        encodeURIComponent(masterName) +
        gameParam
    );
});
const matchingCrews = computed(() => {
    if (!myPlayer.value?.master_name) return [];
    const baseName = myPlayer.value.master_name.split(',')[0].trim();
    let crews = props.my_crews.filter((c) => {
        const crewBaseName = c.master_name.split(',')[0].trim();
        return crewBaseName === baseName;
    });
    // If a title filter is active, narrow to that specific title's crews
    if (filterTitleId.value) {
        const title = masterTitleOptions.value.find((t) => t.id === filterTitleId.value);
        if (title) {
            crews = crews.filter((c) => c.master_name === title.display_name);
        }
    }
    return crews;
});

// Solo mode: setup for opponent (slot 2)
const opponentPlayer = computed(() => props.game.players.find((p) => p.slot === 2));

// Opponent title filter and matching crews (for solo crew select)
const opponentFilterTitleId = ref<number | null>(null);
const opponentTitleOptions = computed(() => {
    const oppMasterName = opponentPlayer.value?.master_name;
    if (!oppMasterName) return [];
    const baseName = oppMasterName.split(',')[0].trim();
    const masterGroup = props.masters.find((m) => m.name === baseName);
    return masterGroup?.titles ?? [];
});
const opponentMatchingCrews = computed(() => {
    const oppMasterName = opponentPlayer.value?.master_name;
    if (!oppMasterName) return [];
    const baseName = oppMasterName.split(',')[0].trim();
    let crews = props.my_crews.filter((c) => {
        const crewBaseName = c.master_name.split(',')[0].trim();
        return crewBaseName === baseName;
    });
    if (opponentFilterTitleId.value) {
        const title = opponentTitleOptions.value.find((t) => t.id === opponentFilterTitleId.value);
        if (title) {
            crews = crews.filter((c) => c.master_name === title.display_name);
        }
    }
    return crews;
});
const playerName = (player: GamePlayer | undefined) => player?.user?.name ?? player?.opponent_name ?? 'Opponent';

const selectedOpponentFaction = ref<string | null>(null);
const selectedOpponentMasterName = ref<string | null>(null);
const opponentAvailableMasters = computed(() => {
    const f = opponentPlayer.value?.faction;
    if (!f) return [];
    return props.masters.filter((m) => m.faction === f || m.second_faction === f || m.is_alternate_leader);
});
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

const confirmOpponentMasterSelection = () => {
    if (!selectedOpponentMasterName.value) return;
    postSetup(route('games.setup.master', props.game.uuid), { master_name: selectedOpponentMasterName.value, slot: opponentSlot.value });
};

const selectedOpponentTitleForSkip = ref<number | null>(null);

const skipOpponentCrew = async () => {
    // Submit the selected title first if one was picked
    const titleId = selectedOpponentTitleForSkip.value;
    if (titleId) {
        const title = opponentTitleOptions.value.find((t: any) => t.id === titleId);
        if (title) {
            await fetch(route('games.setup.master', props.game.uuid), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', ...csrfHeaders() },
                body: JSON.stringify({ master_name: title.display_name, slot: opponentSlot.value }),
            });
        }
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
    router.post(
        route('games.setup.swap_roles', props.game.uuid),
        {},
        { only: ['game'], preserveScroll: true, preserveState: true },
    );
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
            scheme_points: opponentSchemePoints.value,
            scheme_action: schemeAction,
            slot: opponentSlot.value,
        };
        if (identifiedSchemeId) {
            payload.identified_scheme_id = identifiedSchemeId;
        }
        const res = await fetch(route('games.play.turns.store', props.game.uuid), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', ...csrfHeaders() },
            body: JSON.stringify(payload),
        });
        if (!res.ok) {
            const err = await res.json().catch(() => ({}));
            console.error('Opponent turn submit failed:', res.status, err);
        }
    } catch (e) {
        console.error('Opponent turn submit error:', e);
    }
    opponentStrategyPoints.value = 0;
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
    const status = props.game.status;
    if (status === 'faction_select') return myStepDone('faction') && !opponentStepDone('faction');
    if (status === 'master_select') return myStepDone('master') && !opponentStepDone('master');
    if (status === 'crew_select') return myStepDone('crew') && !opponentStepDone('crew');
    return false;
});

// Turn change banner
const turnBanner = ref(false);
const lastSeenTurn = ref(props.game.current_turn);
let turnBannerTimer: ReturnType<typeof setTimeout> | null = null;
watch(
    () => props.game.current_turn,
    (turn) => {
        if (props.game.status === 'in_progress' && turn > lastSeenTurn.value) {
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
    router.post(
        route('games.toggle_observable', props.game.uuid),
        {},
        { only: ['game'], preserveScroll: true, preserveState: true },
    );
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

// Share summary
const summaryLinkCopied = ref(false);
const copySummaryLink = async () => {
    await navigator.clipboard.writeText(route('games.summary', props.game.uuid));
    summaryLinkCopied.value = true;
    setTimeout(() => (summaryLinkCopied.value = false), 2000);
};

const completeDialogOpen = ref(false);
const gameSettingsOpen = ref(false);
watch(gameSettingsOpen, (open) => {
    if (open && spectateOn.value) generateSpectateQR();
});
const expandedTurn = ref<number | null>(null);
const executeAbandon = async () => {
    abandonDialogOpen.value = false;
    await fetch(route('games.abandon', props.game.uuid), {
        method: 'POST',
        headers: { ...csrfHeaders(), Accept: 'application/json' },
    });
    router.visit(route('games.index'));
};

// ─── Crew References ───
const myReferences = ref<any>(null);
const opponentReferences = ref<any>(null);

const loadReferences = (target: 'my' | 'opponent') => {
    const refs = target === 'my' ? myReferences : opponentReferences;
    if (refs.value) return;
    const player = target === 'my' ? myPlayer.value : opponent.value;
    refs.value = player?.crew_build?.references ?? null;
};

const toggleMyRefs = () => loadReferences('my');
const toggleOpponentRefs = () => loadReferences('opponent');

// Auto-load references when game is in progress
onMounted(() => {
    if (props.game.status === 'in_progress') {
        toggleMyRefs();
        toggleOpponentRefs();
    }
});

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

const isGameInProgress = computed(() => props.game.status === 'in_progress');

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

onMounted(() => {
    if (isGameInProgress.value) {
        setupLeaveGuard();
    }
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
        fetch(route('games.play.scheme-notes', props.game.uuid), {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json', ...csrfHeaders() },
            body: JSON.stringify({
                scheme_notes: {
                    note: schemeNote.value || null,
                    selected_model: schemeSelectedModel.value || null,
                    selected_marker: schemeSelectedMarker.value || null,
                    terrain_note: schemeTerrainNote.value || null,
                },
            }),
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

// Scheme notes lock: editable before submitting your turn, locked after
// Scheme notes are always locked during gameplay — they're set during scheme selection (pregame or end-of-turn follow-up)
const schemeNotesLocked = computed(() => props.game.status === 'in_progress');

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
const cardFullscreenSrc = ref('');
const openCardFullscreen = (src: string) => {
    cardFullscreenSrc.value = src;
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

const openUpgradePreview = (upgrade: any) => {
    if (!upgrade.front_image) return;
    previewUpgrade.value = upgrade;
    upgradeDrawerOpen.value = true;
};

const myCrewUpgrades = computed(() => myPlayer.value?.master?.crew_upgrades ?? []);
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

const myCrewMembers = computed(() => myPlayer.value?.crew_members?.filter((m: any) => !m.is_killed) ?? []);
const myKilledMembers = computed(() => myPlayer.value?.crew_members?.filter((m: any) => m.is_killed) ?? []);
const opponentCrewMembers = computed(() => opponent.value?.crew_members?.filter((m: any) => !m.is_killed) ?? []);
const opponentKilledMembers = computed(() => opponent.value?.crew_members?.filter((m: any) => m.is_killed) ?? []);

// Inline card preview — track expanded members per crew via Set
const expandedMyCards = ref(new Set<number>());
const expandedOpponentCards = ref(new Set<number>());

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

const toggleAllCards = (crew: 'my' | 'opponent') => {
    const members = crew === 'my' ? myCrewMembers : opponentCrewMembers;
    const set = crew === 'my' ? expandedMyCards : expandedOpponentCards;
    const allWithImages = members.value.filter((m: any) => m.front_image).map((m: any) => m.id);
    const allExpanded = allWithImages.length > 0 && allWithImages.every((id: number) => set.value.has(id));
    set.value = allExpanded ? new Set() : new Set(allWithImages);
};

const allMyCardsExpanded = computed(() => {
    const ids = myCrewMembers.value.filter((m: any) => m.front_image).map((m: any) => m.id);
    return ids.length > 0 && ids.every((id: number) => expandedMyCards.value.has(id));
});

const allOpponentCardsExpanded = computed(() => {
    const ids = opponentCrewMembers.value.filter((m: any) => m.front_image).map((m: any) => m.id);
    return ids.length > 0 && ids.every((id: number) => expandedOpponentCards.value.has(id));
});

const toast = useToast();
const showError = (msg: string) => toast.error(msg);

const postPlay = async (url: string, method: string = 'POST', body?: Record<string, unknown>) => {
    try {
        const opts: RequestInit = { method, headers: { 'Content-Type': 'application/json', ...csrfHeaders() } };
        if (body) opts.body = JSON.stringify(body);
        const res = await fetch(url, opts);
        if (!res.ok) {
            const err = await res.json().catch(() => ({}));
            showError(err.error ?? 'Action failed. Please try again.');
            return;
        }
        router.reload({ only: ['game'], preserveScroll: true });
    } catch {
        showError('Network error. Please check your connection.');
    }
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
    const res = await fetch(route('games.play.crew.update', { game: props.game.uuid, gameCrewMember: member.id }), {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json', ...csrfHeaders() },
        body: JSON.stringify({ is_activated: member.is_activated }),
    });
    if (!res.ok) {
        member.is_activated = oldValue;
        router.reload({ only: ['game'], preserveScroll: true });
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

    const res = await fetch(route('games.play.crew.kill', { game: props.game.uuid, gameCrewMember: member.id }), {
        method: 'POST',
        headers: { ...csrfHeaders(), Accept: 'application/json' },
    });
    if (!res.ok) {
        member.is_killed = false;
        member.current_health = prevHealth;
        router.reload({ only: ['game'], preserveScroll: true });
        return;
    }
    const data = await res.json().catch(() => ({}));

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
            const res = await fetch(route('games.play.crew.summon', props.game.uuid), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', ...csrfHeaders(), Accept: 'application/json' },
                body: JSON.stringify(body),
            });
            if (!res.ok) {
                const err = await res.json().catch(() => ({}));
                if (err.at_limit) {
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

// Summary helper: get scheme info for a turn — reads directly from stored scheme_action
const getTurnSchemeInfo = (player: any, turnNumber: number): { schemeId: number | null; action: string | null } => {
    const turn = (player.turns ?? []).find((t: any) => t.turn_number === turnNumber);
    if (!turn) return { schemeId: null, action: null };
    return { schemeId: turn.scheme_id ?? null, action: turn.scheme_action ?? null };
};

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

// Strategy: 1/turn + 1 bonus once per game (max 2 any turn)
const myStrategyBonusUsed = computed(() => {
    return (myPlayer.value?.turns ?? []).some((t: any) => t.strategy_points > 1);
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
    return (opponent.value?.turns ?? []).some((t: any) => t.strategy_points > 1);
});
const opponentMaxStrategyThisTurn = computed(() => (opponentStrategyBonusUsed.value ? 1 : 2));
const opponentTotalSchemeScored = computed(() => {
    return (opponent.value?.turns ?? []).reduce((sum: number, t: any) => sum + (t.scheme_points ?? 0), 0);
});
const opponentMaxSchemeThisTurn = computed(() => Math.min(MAX_SCHEME_PER_TURN, MAX_SCHEME_POOL - opponentTotalSchemeScored.value));

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

    try {
        const res = await fetch(route('games.play.turns.store', props.game.uuid), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', ...csrfHeaders() },
            body: JSON.stringify({
                strategy_points: strategyPoints.value,
                scheme_points: schemePoints.value,
                scheme_action: schemeAction,
                next_scheme_id: nextSchemeId.value,
                next_scheme_notes: nextNotes,
            }),
        });
        if (!res.ok) {
            const err = await res.json().catch(() => ({}));
            console.error('Turn submit failed:', res.status, err);
        }
    } catch (e) {
        console.error('Turn submit error:', e);
    }

    strategyPoints.value = 0;
    schemePoints.value = 0;
    nextSchemeId.value = null;
    scoringTurn.value = false;
    router.reload({
        only: ['game', 'current_schemes', 'next_schemes', 'opponent_next_schemes', 'opponent_scheme_intel'],
        preserveState: true,
        preserveScroll: true,
    });
};

const markGameComplete = async () => {
    // Solo: ask for opponent's final scheme before completing
    if (isSolo.value) {
        const result = await openOppSchemeDialog('end-of-game');
        if (result === 'cancel') return;
        if (oppIdentifiedSchemeId.value) {
            // Set the identified scheme as opponent's current for final scoring
            await fetch(route('games.setup.scheme', props.game.uuid), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', ...csrfHeaders() },
                body: JSON.stringify({ scheme_id: oppIdentifiedSchemeId.value, slot: opponentSlot.value }),
            });
        }
    }

    const res = await fetch(route('games.play.complete', props.game.uuid), {
        method: 'POST',
        headers: { ...csrfHeaders(), Accept: 'application/json' },
    });
    const data = await res.json().catch(() => ({}));
    if (data.game_complete) {
        router.visit(route('games.show', props.game.uuid));
    } else {
        router.reload({ only: ['game'], preserveScroll: true });
    }
};

const cancelGameComplete = () => {
    router.post(
        route('games.play.cancel_complete', props.game.uuid),
        {},
        { only: ['game'], preserveScroll: true, preserveState: true },
    );
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

    const res = await fetch(route('games.play.crew.summon', props.game.uuid), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', ...csrfHeaders() },
        body: JSON.stringify(body),
    });
    const data = await res.json().catch(() => ({} as Record<string, unknown>));
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
    const res = await fetch(route('games.play.crew.update', { game: props.game.uuid, gameCrewMember: previewMember.value.id }), {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json', ...csrfHeaders() },
        body: JSON.stringify({ display_name: mini.display_name, front_image: mini.front_image, back_image: mini.back_image }),
    });

    if (!res.ok && previewMember.value) {
        // Roll back optimistic state on failure.
        previewMember.value.front_image = prev.front_image;
        previewMember.value.back_image = prev.back_image;
        previewMember.value.display_name = prev.display_name;
    }
};

// Crew member + upgrade notes — both players see the result via the existing
// GameCrewMemberUpdated broadcast; the drawer debounces edits to avoid hammering
// the endpoint on every keystroke.
const onCrewMemberNotesChange = async (payload: { notes: string | null; attached_upgrades: { id: number; name: string; notes?: string | null }[] }) => {
    if (! previewMember.value) return;
    // Optimistic local update so the next debounce cycle has the latest.
    previewMember.value.notes = payload.notes;
    previewMember.value.attached_upgrades = payload.attached_upgrades;
    await fetch(route('games.play.crew.update', { game: props.game.uuid, gameCrewMember: previewMember.value.id }), {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json', ...csrfHeaders() },
        body: JSON.stringify({
            notes: payload.notes,
            attached_upgrades: payload.attached_upgrades,
        }),
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
    await fetch(route('games.play.crew.update', { game: props.game.uuid, gameCrewMember: tokenInfoMember.value.id }), {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json', ...csrfHeaders() },
        body: JSON.stringify({ attached_tokens: updated }),
    });
    tokenInfoDrawerOpen.value = false;
    router.reload({ only: ['game'], preserveScroll: true });
};

const quickRemoveToken = async (member: any, tokenId: number) => {
    const current = member.attached_tokens ?? [];
    const updated = current.filter((t: any) => t.id !== tokenId);
    await fetch(route('games.play.crew.update', { game: props.game.uuid, gameCrewMember: member.id }), {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json', ...csrfHeaders() },
        body: JSON.stringify({ attached_tokens: updated }),
    });
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
    await fetch(route('games.play.crew.update', { game: props.game.uuid, gameCrewMember: tokenMember.value.id }), {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json', ...csrfHeaders() },
        body: JSON.stringify({ attached_tokens: updated }),
    });
    router.reload({ only: ['game'], preserveScroll: true });
};

const removeToken = async (tokenId: number) => {
    if (!tokenMember.value) return;
    const current = tokenMember.value.attached_tokens ?? [];
    const updated = current.filter((t: any) => t.id !== tokenId);
    tokenMember.value = { ...tokenMember.value, attached_tokens: updated };
    await fetch(route('games.play.crew.update', { game: props.game.uuid, gameCrewMember: tokenMember.value.id }), {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json', ...csrfHeaders() },
        body: JSON.stringify({ attached_tokens: updated }),
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

const toggleUpgrade = async (upgrade: { id: number; name: string; front_image: string | null; back_image: string | null; power_bar_count?: number | null }) => {
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
    await fetch(route('games.play.crew.update', { game: props.game.uuid, gameCrewMember: upgradeMember.value.id }), {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json', ...csrfHeaders() },
        body: JSON.stringify({ attached_upgrades: updated }),
    });
    router.reload({ only: ['game'], preserveScroll: true });
};

// Set the current_power_bar on an attached upgrade for a specific crew member.
// We mutate the local JSON, then PATCH the whole attached_upgrades array using
// the existing crew.update endpoint — same path toggleUpgrade uses.
const setMemberUpgradePowerBar = async (member: any, upgradeId: number, value: number) => {
    const list: any[] = (member.attached_upgrades ?? []).map((u: any) =>
        u.id === upgradeId ? { ...u, current_power_bar: value } : u,
    );
    member.attached_upgrades = list;
    await fetch(route('games.play.crew.update', { game: props.game.uuid, gameCrewMember: member.id }), {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json', ...csrfHeaders() },
        body: JSON.stringify({ attached_upgrades: list }),
    });
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
    await fetch(route('games.play.crew-upgrade-power-bar', { game: props.game.uuid }), {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json', ...csrfHeaders() },
        body: JSON.stringify(body),
    });
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
    await fetch(route('games.play.crew.update', { game: props.game.uuid, gameCrewMember: member.id }), {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json', ...csrfHeaders() },
        body: JSON.stringify({ attached_upgrades: updated }),
    });
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
const factionBackground = (faction: string): string => {
    if (!faction) return '';
    switch (faction.toLowerCase()) {
        case 'explorers_society':
            return 'bg-explorerssociety';
        case 'ten_thunders':
            return 'bg-tenthunders';
        default:
            return `bg-${faction}`;
    }
};

const categoryLabel = (cat: string): string =>
    ({ leader: 'Leader', totem: 'Totem', 'in-keyword': 'In Keyword', versatile: 'Versatile', ook: 'Out of Keyword' })[cat] ?? cat;

const categoryColor = (cat: string): string =>
    ({
        leader: 'bg-amber-400/20 text-amber-200',
        totem: 'bg-purple-400/20 text-purple-200',
        'in-keyword': 'bg-green-400/20 text-green-200',
        versatile: 'bg-blue-400/20 text-blue-200',
        ook: 'bg-red-400/20 text-red-200',
    })[cat] ?? '';

const setupSteps = ['faction', 'master', 'crew', 'scheme'] as const;
const stepLabels: Record<string, string> = { faction: 'Faction', master: 'Master', crew: 'Crew', scheme: 'Scheme' };
const statusOrder = ['faction_select', 'master_select', 'crew_select', 'scheme_select', 'in_progress'];
const isPastStep = (step: string) => statusOrder.indexOf(props.game.status) > statusOrder.indexOf(step + '_select');
</script>

<template>
    <Head :title="game.name || `Game - ${game.encounter_size}ss`" />
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

            <!-- Game Header (hidden during gameplay — info is in the Game tab) -->
            <div v-if="game.status !== 'in_progress'" class="mb-6 flex flex-wrap items-center gap-3">
                <Swords class="size-6 text-primary" />
                <h1 class="text-xl font-bold">{{ game.name || game.encounter_size + 'ss Encounter' }}</h1>
                <Badge variant="secondary" class="text-xs">{{ game.season_label }}</Badge>
                <Badge variant="secondary" class="text-xs">{{ game.encounter_size }}ss</Badge>
                <Badge v-if="isSolo" variant="outline" class="text-xs">Solo</Badge>
                <Badge
                    v-if="game.is_observable && game.status !== 'completed' && game.status !== 'abandoned'"
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

            <!-- Scenario (hidden during gameplay and completed) -->
            <div v-if="game.status !== 'in_progress' && game.status !== 'completed' && game.status !== 'abandoned'" class="mb-6">
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
            <h3 v-if="game.status !== 'in_progress' && game.status !== 'completed' && game.status !== 'abandoned'" class="mb-3 text-lg font-semibold">
                Players
            </h3>
            <Card v-if="game.status !== 'in_progress' && game.status !== 'completed' && game.status !== 'abandoned'" class="mb-6">
                <CardContent class="p-4 sm:p-6">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div v-for="player in game.players" :key="player.id" class="flex items-center gap-3 rounded-lg border p-3">
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
                                    <template
                                        v-if="isSolo && !isObserver && !player.user && game.status !== 'completed' && game.status !== 'abandoned'"
                                    >
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
                                    <Badge v-if="player.role" variant="outline" class="px-1 py-0 text-[9px] capitalize">
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
                                            game.status === 'scheme_select' ||
                                            game.status === 'in_progress' ||
                                            game.status === 'completed'
                                        "
                                    >
                                        {{ player.master_name }}
                                    </template>
                                    <template v-else>
                                        {{ player.master_name.split(',')[0] }}
                                    </template>
                                </div>
                                <div v-if="game.status === 'in_progress' || game.status === 'completed'" class="mt-1 text-sm font-bold">
                                    {{ player.total_points }} VP
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Solo: swap roles button -->
                    <div v-if="isSolo && !isObserver && game.status !== 'completed' && game.status !== 'abandoned'" class="mt-3 flex justify-center">
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
            <template v-if="game.status === 'setup' && !isSolo">
                <Card v-if="isCreator" class="mb-6">
                    <CardContent class="p-4 sm:p-6">
                        <h2 class="mb-3 font-semibold">Invite Opponent</h2>
                        <p class="mb-4 text-sm text-muted-foreground">Share this link with your opponent to join the game.</p>
                        <div class="flex items-center gap-2">
                            <Input :model-value="joinUrl" readonly class="text-xs" />
                            <Button variant="outline" size="sm" class="shrink-0 gap-1.5" @click="copyJoinLink">
                                <Check v-if="linkCopied" class="size-4 text-green-500" />
                                <Copy v-else class="size-4" />
                                {{ linkCopied ? 'Copied' : 'Copy' }}
                            </Button>
                            <Button variant="outline" size="sm" class="shrink-0" @click="openQR(joinUrl, 'Join Game')">
                                <QrCode class="size-4" />
                            </Button>
                        </div>
                    </CardContent>
                </Card>
                <Card v-else class="mb-6">
                    <CardContent class="p-4 text-center sm:p-6">
                        <Loader2 class="mx-auto mb-3 size-6 animate-spin text-muted-foreground" />
                        <p class="text-sm text-muted-foreground">Waiting for the host to start the game...</p>
                    </CardContent>
                </Card>
            </template>

            <!-- ═══ FACTION SELECT ═══ -->
            <Card
                v-if="game.status === 'faction_select'"
                class="mb-6"
                :class="isOpponentSetupPhase ? 'border-amber-500/40 bg-amber-500/5 dark:bg-amber-500/5' : ''"
            >
                <CardContent class="p-4 sm:p-6">
                    <!-- Solo: two-phase faction select -->
                    <template v-if="isSolo">
                        <template v-if="!myStepDone('faction')">
                            <h2 class="mb-1 font-semibold">Select Your Faction</h2>
                            <p class="mb-4 text-xs text-muted-foreground">Choose the faction you'll play this game.</p>
                            <div class="grid grid-cols-4 gap-2 sm:gap-3 md:grid-cols-8">
                                <button
                                    v-for="(faction, key) in factions"
                                    :key="key"
                                    class="flex flex-col items-center gap-1.5 rounded-lg border-2 p-2 transition-all sm:p-3"
                                    :class="selectedFaction === key ? 'border-primary bg-primary/10' : 'border-transparent hover:bg-muted'"
                                    @click="selectedFaction = key as string"
                                >
                                    <img :src="faction.logo" :alt="faction.name" class="size-10 sm:size-12" />
                                    <span class="text-center text-[10px] font-medium sm:text-xs">{{ faction.name }}</span>
                                </button>
                            </div>
                            <div v-if="selectedFaction" class="mt-4 flex justify-center">
                                <Button
                                    :disabled="submitting"
                                    @click="postSetup(route('games.setup.faction', game.uuid), { faction: selectedFaction, slot: mySlot })"
                                >
                                    <Loader2 v-if="submitting" class="mr-2 size-4 animate-spin" />
                                    Confirm Faction
                                </Button>
                            </div>
                        </template>
                        <template v-else-if="!opponentStepDone('faction')">
                            <div class="mb-3 flex items-center gap-2">
                                <FactionLogo :faction="myPlayer!.faction!" class-name="size-6" />
                                <Check class="size-4 text-green-500" />
                            </div>
                            <h2 class="mb-1 font-semibold">
                                Select Opponent's Faction
                                <Badge variant="outline" class="ml-1 border-amber-500/50 text-[10px] text-amber-600 dark:text-amber-400"
                                    >Opponent</Badge
                                >
                            </h2>
                            <p class="mb-4 text-xs text-muted-foreground">Choose the faction for your opponent.</p>
                            <div class="grid grid-cols-4 gap-2 sm:gap-3 md:grid-cols-8">
                                <button
                                    v-for="(faction, key) in factions"
                                    :key="key"
                                    class="flex flex-col items-center gap-1.5 rounded-lg border-2 p-2 transition-all sm:p-3"
                                    :class="selectedOpponentFaction === key ? 'border-primary bg-primary/10' : 'border-transparent hover:bg-muted'"
                                    @click="selectedOpponentFaction = key as string"
                                >
                                    <img :src="faction.logo" :alt="faction.name" class="size-10 sm:size-12" />
                                    <span class="text-center text-[10px] font-medium sm:text-xs">{{ faction.name }}</span>
                                </button>
                            </div>
                            <div v-if="selectedOpponentFaction" class="mt-4 flex justify-center">
                                <Button :disabled="submitting" @click="selectOpponentFaction">
                                    <Loader2 v-if="submitting" class="mr-2 size-4 animate-spin" />
                                    Confirm Opponent Faction
                                </Button>
                            </div>
                        </template>
                    </template>

                    <!-- Normal 2-player faction select -->
                    <template v-else>
                        <h2 class="mb-1 font-semibold">Select Your Faction</h2>
                        <p v-if="myStepDone('faction')" class="mb-4 text-xs text-muted-foreground">
                            <Loader2 class="mr-1 inline size-3 animate-spin" /> Waiting for opponent...
                        </p>
                        <p v-else class="mb-4 text-xs text-muted-foreground">Choose the faction you'll play this game.</p>

                        <template v-if="!myStepDone('faction')">
                            <div class="grid grid-cols-4 gap-2 sm:gap-3 md:grid-cols-8">
                                <button
                                    v-for="(faction, key) in factions"
                                    :key="key"
                                    class="flex flex-col items-center gap-1.5 rounded-lg border-2 p-2 transition-all sm:p-3"
                                    :class="selectedFaction === key ? 'border-primary bg-primary/10' : 'border-transparent hover:bg-muted'"
                                    @click="selectedFaction = key as string"
                                >
                                    <img :src="faction.logo" :alt="faction.name" class="size-10 sm:size-12" />
                                    <span class="text-center text-[10px] font-medium sm:text-xs">{{ faction.name }}</span>
                                </button>
                            </div>
                            <div v-if="selectedFaction" class="mt-4 flex justify-center">
                                <Button
                                    :disabled="submitting"
                                    @click="postSetup(route('games.setup.faction', game.uuid), { faction: selectedFaction })"
                                >
                                    <Loader2 v-if="submitting" class="mr-2 size-4 animate-spin" />
                                    Confirm Faction
                                </Button>
                            </div>
                        </template>
                        <div v-else class="flex items-center justify-center gap-2 py-4">
                            <FactionLogo :faction="myPlayer!.faction!" class-name="size-12" />
                            <Check class="size-5 text-green-500" />
                        </div>
                    </template>
                </CardContent>
            </Card>

            <!-- ═══ MASTER SELECT ═══ -->
            <Card
                v-if="game.status === 'master_select'"
                class="mb-6"
                :class="isOpponentSetupPhase ? 'border-amber-500/40 bg-amber-500/5 dark:bg-amber-500/5' : ''"
            >
                <CardContent class="p-4 sm:p-6">
                    <h2 class="mb-1 font-semibold">
                        {{ isSolo && myStepDone('master') ? "Select Opponent's Master" : 'Select Your Master' }}
                        <Badge
                            v-if="isOpponentSetupPhase"
                            variant="outline"
                            class="ml-1 border-amber-500/50 text-[10px] text-amber-600 dark:text-amber-400"
                            >Opponent</Badge
                        >
                    </h2>
                    <p v-if="myStepDone('master') && !isSolo" class="mb-4 text-xs text-muted-foreground">
                        <Loader2 class="mr-1 inline size-3 animate-spin" /> Waiting for opponent...
                    </p>
                    <p v-else-if="!myStepDone('master')" class="mb-4 text-xs text-muted-foreground">Choose the master for your crew.</p>
                    <p v-else class="mb-4 text-xs text-muted-foreground">Choose the master for the opponent.</p>

                    <template v-if="!myStepDone('master')">
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            <Card
                                v-for="master in availableMasters"
                                :key="master.name"
                                class="cursor-pointer transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md hover:ring-1 hover:ring-primary/50"
                                :class="selectedMasterName === master.name ? 'ring-2 ring-primary' : ''"
                                @click="selectedMasterName = master.name"
                            >
                                <CardContent class="flex items-start gap-3 p-3">
                                    <div v-if="master.front_image" class="shrink-0 overflow-hidden rounded-md">
                                        <img
                                            :src="'/storage/' + master.front_image"
                                            :alt="master.name"
                                            class="size-16 object-cover object-top"
                                            loading="lazy"
                                            decoding="async"
                                        />
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center gap-1.5">
                                            <span class="text-sm font-semibold">{{ master.name }}</span>
                                            <Badge
                                                v-if="master.is_alternate_leader"
                                                variant="outline"
                                                class="border-cyan-500/50 px-1 py-0 text-[9px] text-cyan-600 dark:text-cyan-400"
                                            >
                                                Alt Leader
                                            </Badge>
                                        </div>
                                        <div v-if="master.titles.length > 1" class="mt-0.5 text-[10px] text-muted-foreground">
                                            {{ master.titles.length }} titles — choose during crew select
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>
                        <div v-if="selectedMasterName" class="mt-4 flex justify-center">
                            <Button :disabled="submitting" @click="confirmMasterSelection">
                                <Loader2 v-if="submitting" class="mr-2 size-4 animate-spin" />
                                Confirm {{ selectedMasterName }}
                            </Button>
                        </div>
                    </template>
                    <!-- My master done -->
                    <template v-else-if="!isSolo || opponentStepDone('master')">
                        <div class="py-4 text-center">
                            <Badge variant="secondary" class="text-sm">{{ myPlayer!.master_name }}</Badge>
                            <Check class="ml-2 inline size-5 text-green-500" />
                        </div>
                    </template>

                    <!-- Solo: pick opponent master -->
                    <template v-else-if="isSolo && myStepDone('master') && !opponentStepDone('master')">
                        <div class="mb-3">
                            <Badge variant="secondary" class="text-sm">{{ myPlayer!.master_name }}</Badge>
                            <Check class="ml-2 inline size-4 text-green-500" />
                        </div>
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            <Card
                                v-for="master in opponentAvailableMasters"
                                :key="master.name"
                                class="cursor-pointer transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md hover:ring-1 hover:ring-primary/50"
                                :class="selectedOpponentMasterName === master.name ? 'ring-2 ring-primary' : ''"
                                @click="selectedOpponentMasterName = master.name"
                            >
                                <CardContent class="flex items-start gap-3 p-3">
                                    <div v-if="master.front_image" class="shrink-0 overflow-hidden rounded-md">
                                        <img
                                            :src="'/storage/' + master.front_image"
                                            :alt="master.name"
                                            class="size-16 object-cover object-top"
                                            loading="lazy"
                                            decoding="async"
                                        />
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <span class="text-sm font-semibold">{{ master.name }}</span>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>
                        <div v-if="selectedOpponentMasterName" class="mt-4 flex justify-center">
                            <Button :disabled="submitting" @click="confirmOpponentMasterSelection">
                                <Loader2 v-if="submitting" class="mr-2 size-4 animate-spin" />
                                Confirm {{ selectedOpponentMasterName }}
                            </Button>
                        </div>
                    </template>
                </CardContent>
            </Card>

            <!-- ═══ CREW SELECT ═══ -->
            <Card
                v-if="game.status === 'crew_select'"
                class="mb-6"
                :class="isOpponentSetupPhase ? 'border-amber-500/40 bg-amber-500/5 dark:bg-amber-500/5' : ''"
            >
                <CardContent class="p-4 sm:p-6">
                    <h2 class="mb-1 font-semibold">
                        {{ isSolo && myStepDone('crew') ? "Opponent's Crew" : 'Select Your Crew' }}
                        <Badge
                            v-if="isOpponentSetupPhase"
                            variant="outline"
                            class="ml-1 border-amber-500/50 text-[10px] text-amber-600 dark:text-amber-400"
                            >Opponent</Badge
                        >
                    </h2>
                    <p v-if="myStepDone('crew') && !isSolo" class="mb-4 text-xs text-muted-foreground">
                        <Loader2 class="mr-1 inline size-3 animate-spin" /> Waiting for opponent...
                    </p>
                    <template v-else>
                        <p class="mb-2 text-xs text-muted-foreground">
                            Choose a saved crew for <strong class="text-foreground">{{ myPlayer?.master_name?.split(',')[0] }}</strong> or
                            <Link :href="newCrewUrl" class="text-primary underline">create a new one</Link>.
                        </p>
                        <div v-if="masterTitleOptions.length > 1" class="mb-4 flex flex-wrap items-center gap-1.5">
                            <span class="text-[11px] text-muted-foreground">Filter:</span>
                            <button
                                class="rounded-md border px-2 py-0.5 text-[11px] transition-colors"
                                :class="!filterTitleId ? 'border-primary bg-primary/10 font-medium' : 'hover:bg-muted'"
                                @click="filterTitleId = null"
                            >
                                All
                            </button>
                            <button
                                v-for="title in masterTitleOptions"
                                :key="title.id"
                                class="rounded-md border px-2 py-0.5 text-[11px] transition-colors"
                                :class="filterTitleId === title.id ? 'border-primary bg-primary/10 font-medium' : 'hover:bg-muted'"
                                @click="filterTitleId = title.id"
                            >
                                {{ title.title || title.display_name }}
                            </button>
                        </div>
                    </template>

                    <template v-if="!myStepDone('crew')">
                        <div v-if="matchingCrews.length" class="grid gap-2.5 sm:grid-cols-2">
                            <div v-for="crew in matchingCrews" :key="crew.id">
                                <Card
                                    class="transition-all duration-200"
                                    :class="[
                                        expandedCrewId === crew.id ? 'shadow-md ring-1 ring-primary/50' : 'hover:-translate-y-0.5 hover:shadow-md',
                                        crew.is_over_budget ? 'border-destructive/50' : '',
                                    ]"
                                >
                                    <!-- Card header -->
                                    <CardContent
                                        class="flex cursor-pointer items-start gap-3 p-3"
                                        @click="expandedCrewId = expandedCrewId === crew.id ? null : crew.id"
                                    >
                                        <FactionLogo :faction="crew.faction" class-name="size-7 shrink-0 mt-0.5" />
                                        <div class="min-w-0 flex-1">
                                            <p class="break-words text-sm font-medium leading-tight">{{ crew.name }}</p>
                                            <div class="mt-1 flex flex-wrap items-center gap-1">
                                                <Badge v-if="crew.master_name" variant="secondary" class="text-[10px]">{{ crew.master_name }}</Badge>
                                                <Badge variant="secondary" class="text-[10px]">{{ crew.encounter_size }}ss</Badge>
                                                <Badge v-if="crew.is_over_budget" variant="destructive" class="text-[10px]">Over Budget</Badge>
                                            </div>
                                        </div>
                                        <ChevronDown
                                            class="mt-1 size-4 shrink-0 text-muted-foreground transition-transform duration-200"
                                            :class="expandedCrewId === crew.id ? 'rotate-180' : ''"
                                        />
                                    </CardContent>

                                    <!-- Expanded details -->
                                    <div v-if="expandedCrewId === crew.id" class="border-t px-3 pb-3 pt-2">
                                        <!-- Stats -->
                                        <div class="mb-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-muted-foreground">
                                            <span>
                                                Spent:
                                                <span class="font-medium text-foreground" :class="crew.is_over_budget ? 'text-destructive' : ''">
                                                    {{ crew.total_spent }}/{{ game.encounter_size }}
                                                </span>
                                                <GameIcon type="soulstone" class-name="ml-0.5 h-3 inline-block" />
                                            </span>
                                            <span>
                                                Pool: <span class="font-medium text-foreground">{{ crew.soulstone_pool }}</span>
                                                <GameIcon type="soulstone" class-name="ml-0.5 h-3 inline-block" />
                                            </span>
                                            <span>
                                                OOK:
                                                <span
                                                    class="font-medium text-foreground"
                                                    :class="crew.ook_count >= 2 ? 'text-amber-600 dark:text-amber-400' : ''"
                                                >
                                                    {{ crew.ook_count }}/2
                                                </span>
                                            </span>
                                        </div>

                                        <!-- Member list -->
                                        <div class="space-y-0.5">
                                            <div
                                                v-for="(member, mIdx) in crew.members"
                                                :key="mIdx"
                                                :class="factionBackground(member.faction)"
                                                class="flex items-center justify-between rounded px-2 py-1 text-xs text-white"
                                            >
                                                <div class="flex min-w-0 items-center gap-1.5">
                                                    <span class="truncate font-medium">{{ member.display_name }}</span>
                                                    <Badge :class="categoryColor(member.category)" class="shrink-0 px-1 py-0 text-[9px]">
                                                        {{ categoryLabel(member.category) }}
                                                    </Badge>
                                                </div>
                                                <div v-if="member.effective_cost > 0" class="flex shrink-0 items-center font-bold">
                                                    <template v-if="member.category === 'ook'">
                                                        {{ member.effective_cost }}
                                                        <GameIcon type="soulstone" class-name="ml-0.5 h-3 inline-block" />
                                                        <span class="ml-0.5 text-[9px] font-normal text-red-300">({{ member.cost }}+1)</span>
                                                    </template>
                                                    <template v-else>
                                                        {{ member.effective_cost }}
                                                        <GameIcon type="soulstone" class-name="ml-0.5 h-3 inline-block" />
                                                    </template>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="mt-3 flex gap-2">
                                            <Button
                                                class="flex-1"
                                                size="sm"
                                                :disabled="submitting || crew.is_over_budget"
                                                @click="
                                                    postSetup(route('games.setup.crew', game.uuid), {
                                                        crew_build_id: crew.id,
                                                        ...(isSolo ? { slot: mySlot } : {}),
                                                    })
                                                "
                                            >
                                                <Loader2 v-if="submitting" class="mr-2 size-4 animate-spin" />
                                                {{ crew.is_over_budget ? 'Over Budget' : 'Select This Crew' }}
                                            </Button>
                                            <Link :href="route('tools.crew_builder.editor') + '?build=' + crew.share_code">
                                                <Button variant="outline" size="sm" class="gap-1">
                                                    <Pencil class="size-3" />
                                                    Edit
                                                </Button>
                                            </Link>
                                        </div>
                                    </div>
                                </Card>
                            </div>
                        </div>
                        <div v-else class="py-6 text-center text-sm text-muted-foreground">
                            No saved crews for this faction.
                            <Link :href="newCrewUrl" class="text-primary underline">Create one</Link>
                        </div>
                    </template>
                    <template v-else-if="!isSolo || opponentStepDone('crew')">
                        <div class="py-4 text-center text-sm text-muted-foreground"><Check class="inline size-5 text-green-500" /> Crew selected</div>
                    </template>

                    <!-- Solo: opponent crew (optional) -->
                    <template v-else-if="isSolo && myStepDone('crew') && !opponentStepDone('crew')">
                        <div class="mb-3 text-center text-sm text-muted-foreground">
                            <Check class="inline size-4 text-green-500" /> Your crew selected
                        </div>
                        <p class="mb-4 text-xs text-muted-foreground">
                            Optionally select a saved crew for
                            <strong class="text-foreground">{{ opponentPlayer?.master_name?.split(',')[0] }}</strong
                            >, <Link :href="newOpponentCrewUrl" class="text-primary underline">Create a new crew</Link>, or skip to track points only.
                        </p>
                        <div v-if="opponentTitleOptions.length > 1" class="mb-4 flex flex-wrap items-center gap-1.5">
                            <span class="text-[11px] text-muted-foreground">Filter:</span>
                            <button
                                class="rounded-md border px-2 py-0.5 text-[11px] transition-colors"
                                :class="!opponentFilterTitleId ? 'border-primary bg-primary/10 font-medium' : 'hover:bg-muted'"
                                @click="opponentFilterTitleId = null"
                            >
                                All
                            </button>
                            <button
                                v-for="title in opponentTitleOptions"
                                :key="title.id"
                                class="rounded-md border px-2 py-0.5 text-[11px] transition-colors"
                                :class="opponentFilterTitleId === title.id ? 'border-primary bg-primary/10 font-medium' : 'hover:bg-muted'"
                                @click="opponentFilterTitleId = title.id"
                            >
                                {{ title.title || title.display_name }}
                            </button>
                        </div>
                        <div v-if="opponentMatchingCrews.length" class="mb-3 grid gap-2.5 sm:grid-cols-2">
                            <div v-for="crew in opponentMatchingCrews" :key="crew.id">
                                <Card
                                    class="transition-all duration-200"
                                    :class="[
                                        expandedOpponentCrewId === crew.id
                                            ? 'shadow-md ring-1 ring-primary/50'
                                            : 'hover:-translate-y-0.5 hover:shadow-md',
                                        crew.is_over_budget ? 'border-destructive/50' : '',
                                    ]"
                                >
                                    <CardContent
                                        class="flex cursor-pointer items-start gap-3 p-3"
                                        @click="expandedOpponentCrewId = expandedOpponentCrewId === crew.id ? null : crew.id"
                                    >
                                        <FactionLogo :faction="crew.faction" class-name="size-7 shrink-0 mt-0.5" />
                                        <div class="min-w-0 flex-1">
                                            <p class="break-words text-sm font-medium leading-tight">{{ crew.name }}</p>
                                            <div class="mt-1 flex flex-wrap items-center gap-1">
                                                <Badge v-if="crew.master_name" variant="secondary" class="text-[10px]">{{ crew.master_name }}</Badge>
                                                <Badge variant="secondary" class="text-[10px]">{{ crew.encounter_size }}ss</Badge>
                                                <Badge v-if="crew.is_over_budget" variant="destructive" class="text-[10px]">Over Budget</Badge>
                                            </div>
                                        </div>
                                        <ChevronDown
                                            class="mt-1 size-4 shrink-0 text-muted-foreground transition-transform duration-200"
                                            :class="expandedOpponentCrewId === crew.id ? 'rotate-180' : ''"
                                        />
                                    </CardContent>

                                    <div v-if="expandedOpponentCrewId === crew.id" class="border-t px-3 pb-3 pt-2">
                                        <div class="mb-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-muted-foreground">
                                            <span>
                                                Spent:
                                                <span class="font-medium text-foreground" :class="crew.is_over_budget ? 'text-destructive' : ''">
                                                    {{ crew.total_spent }}/{{ game.encounter_size }}
                                                </span>
                                                <GameIcon type="soulstone" class-name="ml-0.5 h-3 inline-block" />
                                            </span>
                                            <span>
                                                Pool: <span class="font-medium text-foreground">{{ crew.soulstone_pool }}</span>
                                                <GameIcon type="soulstone" class-name="ml-0.5 h-3 inline-block" />
                                            </span>
                                            <span>
                                                OOK:
                                                <span
                                                    class="font-medium text-foreground"
                                                    :class="crew.ook_count >= 2 ? 'text-amber-600 dark:text-amber-400' : ''"
                                                >
                                                    {{ crew.ook_count }}/2
                                                </span>
                                            </span>
                                        </div>
                                        <div class="space-y-0.5">
                                            <div
                                                v-for="(member, mIdx) in crew.members"
                                                :key="mIdx"
                                                :class="factionBackground(member.faction)"
                                                class="flex items-center justify-between rounded px-2 py-1 text-xs text-white"
                                            >
                                                <div class="flex min-w-0 items-center gap-1.5">
                                                    <span class="truncate font-medium">{{ member.display_name }}</span>
                                                    <Badge :class="categoryColor(member.category)" class="shrink-0 px-1 py-0 text-[9px]">
                                                        {{ categoryLabel(member.category) }}
                                                    </Badge>
                                                </div>
                                                <div v-if="member.effective_cost > 0" class="flex shrink-0 items-center font-bold">
                                                    <template v-if="member.category === 'ook'">
                                                        {{ member.effective_cost }}
                                                        <GameIcon type="soulstone" class-name="ml-0.5 h-3 inline-block" />
                                                        <span class="ml-0.5 text-[9px] font-normal text-red-300">({{ member.cost }}+1)</span>
                                                    </template>
                                                    <template v-else>
                                                        {{ member.effective_cost }}
                                                        <GameIcon type="soulstone" class-name="ml-0.5 h-3 inline-block" />
                                                    </template>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="mt-3">
                                            <Button
                                                class="w-full"
                                                size="sm"
                                                :disabled="submitting || crew.is_over_budget"
                                                @click="postSetup(route('games.setup.crew', game.uuid), { crew_build_id: crew.id, slot: opponentSlot })"
                                            >
                                                <Loader2 v-if="submitting" class="mr-2 size-4 animate-spin" />
                                                {{ crew.is_over_budget ? 'Over Budget' : 'Select This Crew' }}
                                            </Button>
                                        </div>
                                    </div>
                                </Card>
                            </div>
                        </div>
                        <!-- Title selection required before skipping -->
                        <div v-if="opponentTitleOptions.length > 1" class="mt-3 rounded-md border p-3">
                            <div class="mb-2 text-xs font-medium text-muted-foreground">Select opponent's title before skipping:</div>
                            <div class="mb-2 flex flex-wrap gap-1.5">
                                <button
                                    v-for="title in opponentTitleOptions"
                                    :key="'skip-title-' + title.id"
                                    class="rounded-md border px-2 py-0.5 text-[11px] transition-colors"
                                    :class="selectedOpponentTitleForSkip === title.id ? 'border-primary bg-primary/10 font-medium' : 'hover:bg-muted'"
                                    @click="selectedOpponentTitleForSkip = title.id"
                                >
                                    {{ title.title || title.display_name }}
                                </button>
                            </div>
                            <Button variant="outline" class="w-full" :disabled="!selectedOpponentTitleForSkip" @click="skipOpponentCrew">
                                Skip Opponent Crew
                            </Button>
                        </div>
                        <Button v-else variant="outline" class="w-full" @click="skipOpponentCrew"> Skip Opponent Crew </Button>
                    </template>
                </CardContent>
            </Card>

            <!-- ═══ SCHEME SELECT ═══ -->
            <template v-if="game.status === 'scheme_select'">
                <Card class="mb-6">
                    <CardContent class="p-4 sm:p-6">
                        <h2 class="mb-1 font-semibold">Select Your Scheme</h2>
                        <p v-if="myStepDone('scheme') && !isSolo" class="mb-4 text-xs text-muted-foreground">
                            <Loader2 class="mr-1 inline size-3 animate-spin" /> Waiting for opponent...
                        </p>
                        <p v-else-if="!myStepDone('scheme')" class="mb-4 text-xs text-muted-foreground">
                            Choose one scheme from the pool for Turn 1.
                            <template v-if="isSolo"> You can set the opponent's scheme during gameplay.</template>
                        </p>

                        <template v-if="!myStepDone('scheme')">
                            <!-- Scheme list -->
                            <div class="grid gap-2 sm:grid-cols-3">
                                <div
                                    v-for="scheme in schemes"
                                    :key="scheme.id"
                                    class="flex items-center gap-2 rounded-lg border p-3 transition-colors"
                                    :class="pendingSchemeId === scheme.id ? 'border-primary bg-primary/5' : ''"
                                >
                                    <div class="min-w-0 flex-1">
                                        <button class="text-sm font-medium text-primary hover:underline" @click="openSchemeDrawer(scheme)">
                                            {{ scheme.name }}
                                        </button>
                                    </div>
                                    <Button
                                        size="sm"
                                        class="shrink-0 text-xs"
                                        :variant="pendingSchemeId === scheme.id ? 'default' : 'outline'"
                                        :disabled="submitting"
                                        @click="selectPendingScheme(scheme.id)"
                                    >
                                        {{ pendingSchemeId === scheme.id ? 'Selected' : 'Select' }}
                                    </Button>
                                </div>
                            </div>

                            <!-- Requirement fields + confirm (shown when a scheme is selected) -->
                            <div v-if="pendingScheme" class="mt-4 rounded-lg border border-primary/30 bg-primary/5 p-4">
                                <div class="mb-2 text-sm font-medium">{{ pendingScheme.name }} — Setup</div>

                                <!-- Prerequisite hint -->
                                <div v-if="pendingScheme.prerequisite" class="mb-3 text-[11px] italic text-muted-foreground">
                                    {{ pendingScheme.prerequisite }}
                                </div>

                                <div v-if="pendingSchemeReqs.length" class="mb-3 space-y-2">
                                    <!-- Model selection -->
                                    <div v-if="pendingModelReq">
                                        <label class="text-[10px] uppercase text-muted-foreground">{{ pendingModelLabel }}</label>
                                        <select
                                            v-if="pendingModelOptions.length"
                                            v-model="pendingSchemeModel"
                                            class="mt-0.5 w-full rounded border bg-background px-2 py-1 text-xs"
                                        >
                                            <option value="">Select...</option>
                                            <option v-for="m in pendingModelOptions" :key="m.id" :value="m.display_name">
                                                {{ m.display_name }}<template v-if="m.cost != null"> ({{ m.cost }}ss)</template>
                                            </option>
                                        </select>
                                        <input
                                            v-else
                                            v-model="pendingSchemeModel"
                                            type="text"
                                            placeholder="Type model name..."
                                            class="mt-0.5 w-full rounded border bg-background px-2 py-1 text-xs"
                                        />
                                    </div>

                                    <!-- Marker selection -->
                                    <div v-if="pendingHasMarkerReq">
                                        <label class="text-[10px] uppercase text-muted-foreground">Target Marker</label>
                                        <select v-model="pendingSchemeMarker" class="mt-0.5 w-full rounded border bg-background px-2 py-1 text-xs">
                                            <option value="">Select...</option>
                                            <option v-for="m in all_markers" :key="m.id" :value="m.name">{{ m.name }}</option>
                                        </select>
                                    </div>

                                    <!-- Terrain note -->
                                    <div v-if="pendingHasTerrainReq">
                                        <label class="text-[10px] uppercase text-muted-foreground">Terrain Note</label>
                                        <input
                                            v-model="pendingSchemeTerrainNote"
                                            type="text"
                                            placeholder="e.g. the building on the left..."
                                            class="mt-0.5 w-full rounded border bg-background px-2 py-1 text-xs"
                                        />
                                    </div>
                                </div>

                                <div v-else class="mb-3 text-xs text-muted-foreground">No additional selections required for this scheme.</div>

                                <div class="flex items-center gap-2">
                                    <Button size="sm" :disabled="submitting" @click="confirmPendingScheme">
                                        <Loader2 v-if="submitting" class="mr-1.5 size-3 animate-spin" />
                                        Confirm Scheme
                                    </Button>
                                    <Button size="sm" variant="ghost" @click="pendingSchemeId = null">Cancel</Button>
                                </div>
                            </div>
                        </template>
                        <div v-else class="py-4 text-center text-sm text-muted-foreground">
                            <Check class="inline size-5 text-green-500" /> Scheme selected
                        </div>
                    </CardContent>
                </Card>

                <!-- Crew lists visible during scheme select -->
                <div class="mb-6 grid gap-4 sm:grid-cols-2">
                    <div v-for="player in game.players" :key="'scheme-crew-' + player.id">
                        <h3 class="mb-2 text-sm font-semibold">
                            <FactionLogo v-if="player.faction" :faction="player.faction" class-name="mr-1 inline size-4" />
                            {{ player.user?.name ?? player.opponent_name ?? 'Opponent' }}
                            <span v-if="player.master_name" class="ml-1 text-xs font-normal text-muted-foreground">— {{ player.master_name }}</span>
                        </h3>
                        <div v-if="player.crew_members?.length" class="space-y-0.5">
                            <div
                                v-for="member in player.crew_members"
                                :key="member.id"
                                :class="factionBackground(member.faction ?? player.faction ?? '')"
                                class="flex items-center justify-between rounded px-2 py-1 text-xs text-white"
                            >
                                <div class="flex min-w-0 items-center gap-1.5">
                                    <span
                                        class="truncate font-medium"
                                        :class="member.front_image ? 'cursor-pointer hover:underline' : ''"
                                        @click="openMemberPreview(member)"
                                        >{{ member.display_name }}</span
                                    >
                                    <Badge
                                        v-if="member.hiring_category && member.hiring_category !== 'leader' && member.hiring_category !== 'totem'"
                                        :class="categoryColor(member.hiring_category)"
                                        class="shrink-0 px-1 py-0 text-[9px]"
                                    >
                                        {{ categoryLabel(member.hiring_category) }}
                                    </Badge>
                                </div>
                                <div v-if="member.cost > 0" class="flex shrink-0 items-center font-bold">
                                    {{ member.cost }}
                                    <GameIcon type="soulstone" class-name="ml-0.5 h-3 inline-block" />
                                </div>
                            </div>
                        </div>
                        <div v-else-if="player.crew_skipped" class="rounded-md border border-dashed p-3 text-center text-xs text-muted-foreground">
                            Crew not tracked
                        </div>
                        <div v-else class="rounded-md border border-dashed p-3 text-center text-xs text-muted-foreground">No crew selected</div>
                    </div>
                </div>
            </template>

            <!-- ═══ IN PROGRESS ═══ -->
            <template v-if="game.status === 'in_progress'">
                <!-- Mobile: 3-tab switcher (scenario / my crew / opponent) -->
                <div class="mb-4 md:hidden">
                    <Tabs v-model="mobileGameplayTab">
                        <TabsList class="grid w-full grid-cols-3">
                            <TabsTrigger value="scenario">Game</TabsTrigger>
                            <TabsTrigger value="my-crew">{{ isObserver ? playerName(myPlayer) : 'My Crew' }}</TabsTrigger>
                            <TabsTrigger value="opponent">{{ playerName(opponent) }}</TabsTrigger>
                        </TabsList>
                    </Tabs>
                </div>

                <!-- Tablet: 2-tab switcher (game / both crews side-by-side) -->
                <div class="mb-4 hidden md:block xl:hidden">
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
                        gameplayTab === 'crews' ? 'md:grid-cols-2' : '',
                        scenarioCollapsed ? 'xl:grid-cols-[auto_1fr_1fr]' : 'xl:grid-cols-3',
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

                                <!-- Scores -->
                                <div class="grid grid-cols-2 gap-2">
                                    <div v-for="player in game.players" :key="'score-' + player.id" class="rounded-lg border p-3 text-center">
                                        <div class="flex items-center justify-center gap-1.5">
                                            <FactionLogo v-if="player.faction" :faction="player.faction" class-name="size-4" />
                                            <span class="text-xs font-medium">{{ playerName(player) }}</span>
                                        </div>
                                        <div class="mt-1 text-2xl font-bold">{{ player.total_points }}</div>
                                        <Badge v-if="player.role" variant="outline" class="mt-1 px-1 py-0 text-[9px] capitalize">{{
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

                                <!-- Turn scoring (hidden for observers) -->
                                <template v-if="isObserver">
                                    <!-- observers see nothing here -->
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
                                            @click="submitTurnScore"
                                        >
                                            <Loader2 v-if="scoringTurn" class="mr-2 size-4 animate-spin" />
                                            Submit Turn ({{ strategyPoints + schemePoints }} VP)
                                        </Button>
                                    </div>
                                </template>

                                <!-- Solo: Opponent scheme + scoring (in Game column) -->
                                <template v-if="isSolo && !isObserver">
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
                                <span><strong>{{ summonBanner.name }}</strong> added. Tap to change sculpt.</span>
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
                                        v-if="myUpgradeMode === 'swappable' && !isObserver && myActiveUpgradeId !== upgrade.id"
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
                        <Button v-if="!isObserver" variant="outline" size="sm" class="mt-2 w-full gap-1 text-xs" @click="openSummonForSlot(1)">
                            <Plus class="size-3" /> Summon
                        </Button>
                        <!-- Crew References -->
                        <details open class="mt-2 rounded-lg border" @toggle="($event.target as HTMLDetailsElement)?.open && toggleMyRefs()">
                            <summary class="cursor-pointer px-2 py-1.5 text-[11px] font-medium text-muted-foreground hover:text-foreground">
                                <Puzzle class="mr-1 inline size-3" />References
                            </summary>
                            <CrewBuilderReferences :references="myReferences" :loading="false" compact />
                        </details>
                    </div>

                    <!-- Column 3: Opponent Crew -->
                    <div :class="gameplayColumnClass('opponent')">
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
                                <span><strong>{{ summonBanner.name }}</strong> added. Tap to change sculpt.</span>
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
                        <!-- Solo: summon for opponent -->
                        <Button
                            v-if="isSolo && !isObserver && opponentCrewMembers.length"
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
            <template v-if="game.status === 'completed' || game.status === 'abandoned'">
                <!-- Result banner -->
                <Card class="mb-4 overflow-hidden">
                    <CardContent class="p-0">
                        <div
                            class="px-4 py-3 text-center"
                            :class="game.status === 'abandoned' ? 'bg-muted' : game.is_tie ? 'bg-muted' : 'bg-amber-500/10'"
                        >
                            <div v-if="game.status === 'abandoned'" class="text-lg font-bold text-muted-foreground">Game Abandoned</div>
                            <div v-else-if="game.is_tie" class="text-lg font-bold">Draw!</div>
                            <div v-else-if="game.winner" class="text-lg font-bold text-amber-700 dark:text-amber-400">
                                {{ game.winner.name }} Wins!
                            </div>
                            <div v-else-if="isSolo && game.winner_slot" class="text-lg font-bold text-amber-700 dark:text-amber-400">
                                {{ game.winner_slot === 1 ? playerName(myPlayer) : playerName(opponentPlayer) }} Wins!
                            </div>
                        </div>

                        <!-- Final scores side by side -->
                        <div class="grid grid-cols-2 divide-x border-t">
                            <div v-for="player in game.players" :key="'final-' + player.id" class="p-3 text-center">
                                <div class="flex items-center justify-center gap-1.5">
                                    <FactionLogo v-if="player.faction" :faction="player.faction" class-name="size-4" />
                                    <span class="text-xs font-medium">{{ playerName(player) }}</span>
                                    <Badge v-if="player.role" variant="outline" class="px-1 py-0 text-[8px] capitalize">{{ player.role }}</Badge>
                                </div>
                                <div v-if="player.master_name" class="mt-0.5 text-[10px] text-muted-foreground">{{ player.master_name }}</div>
                                <div
                                    class="mt-1 text-2xl font-bold"
                                    :class="
                                        (game.winner?.id === player.user?.id && game.winner) || (isSolo && game.winner_slot === player.slot)
                                            ? 'text-amber-600 dark:text-amber-400'
                                            : ''
                                    "
                                >
                                    {{ player.total_points }} <span class="text-sm font-normal text-muted-foreground">VP</span>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Share button -->
                <div v-if="game.status === 'completed'" class="mb-4 flex justify-center gap-2">
                    <Button variant="outline" size="sm" class="gap-1.5 text-xs" @click="copySummaryLink">
                        <Copy class="size-3" />
                        {{ summaryLinkCopied ? 'Copied!' : 'Share Summary' }}
                    </Button>
                    <Button variant="outline" size="sm" class="gap-1.5 text-xs" @click="openQR(route('games.summary', game.uuid), 'Game Summary')">
                        <QrCode class="size-3" />
                        QR Code
                    </Button>
                </div>

                <!-- Starting Crews -->
                <div v-if="starting_crews && Object.keys(starting_crews).length" class="mb-4 grid gap-4 sm:grid-cols-2">
                    <div v-for="player in game.players" :key="'start-crew-' + player.id">
                        <h3 class="mb-2 text-sm font-semibold">{{ playerName(player) }}'s Starting Crew</h3>
                        <div
                            v-if="!starting_crews[player.slot]?.length"
                            class="rounded-md border border-dashed p-3 text-center text-xs text-muted-foreground"
                        >
                            No crew tracked
                        </div>
                        <div v-else class="space-y-0.5">
                            <div
                                v-for="(member, mIdx) in starting_crews[player.slot]"
                                :key="'sc-' + player.slot + '-' + mIdx"
                                :class="factionBackground(member.faction ?? player.faction ?? '')"
                                class="flex items-center justify-between rounded px-2 py-1 text-xs text-white"
                            >
                                <div class="flex min-w-0 items-center gap-1.5">
                                    <span class="truncate font-medium">{{ member.display_name }}</span>
                                    <Badge
                                        v-if="member.hiring_category && member.hiring_category !== 'leader' && member.hiring_category !== 'totem'"
                                        :class="categoryColor(member.hiring_category)"
                                        class="shrink-0 px-1 py-0 text-[9px]"
                                    >
                                        {{ categoryLabel(member.hiring_category) }}
                                    </Badge>
                                </div>
                                <div v-if="member.cost > 0" class="flex shrink-0 items-center font-bold">
                                    {{ member.cost }}
                                    <GameIcon type="soulstone" class-name="ml-0.5 h-3 inline-block" />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Scoring Breakdown -->
                <Card class="mb-4">
                    <CardContent class="p-4">
                        <h3 class="mb-3 text-sm font-semibold">Scoring Breakdown</h3>
                        <div class="grid grid-cols-2 divide-x">
                            <div v-for="player in game.players" :key="'score-bk-' + player.id" class="space-y-2 px-3 first:pl-0 last:pr-0">
                                <div class="flex items-center gap-1.5 text-xs font-medium">
                                    <FactionLogo v-if="player.faction" :faction="player.faction" class-name="size-3.5" />
                                    {{ playerName(player) }}
                                </div>
                                <div class="grid grid-cols-2 gap-2 text-xs">
                                    <div class="rounded bg-muted/50 p-2 text-center">
                                        <div class="text-lg font-bold">
                                            {{ player.turns?.reduce((s: number, t: any) => s + (t.strategy_points ?? 0), 0) ?? 0 }}
                                        </div>
                                        <div class="text-[10px] text-muted-foreground">Strategy VP</div>
                                    </div>
                                    <div class="rounded bg-muted/50 p-2 text-center">
                                        <div class="text-lg font-bold">
                                            {{ player.turns?.reduce((s: number, t: any) => s + (t.scheme_points ?? 0), 0) ?? 0 }}
                                        </div>
                                        <div class="text-[10px] text-muted-foreground">Scheme VP</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Scheme Timeline -->
                <Card class="mb-4">
                    <CardContent class="p-4">
                        <h3 class="mb-3 text-sm font-semibold">Scheme Timeline</h3>
                        <div class="grid grid-cols-2 divide-x">
                            <div v-for="player in game.players" :key="'scheme-tl-' + player.id" class="space-y-1.5 px-3 first:pl-0 last:pr-0">
                                <div class="flex items-center gap-1.5 text-xs font-medium">
                                    <FactionLogo v-if="player.faction" :faction="player.faction" class-name="size-3.5" />
                                    {{ playerName(player) }}
                                </div>
                                <div
                                    v-for="turn in (player.turns ?? []).slice().sort((a: any, b: any) => a.turn_number - b.turn_number)"
                                    :key="'stl-' + player.id + '-' + turn.turn_number"
                                    class="flex items-center gap-1.5 text-[11px]"
                                >
                                    <span class="w-5 shrink-0 text-muted-foreground">T{{ turn.turn_number }}</span>
                                    <template v-if="turn.scheme_id && findScheme(turn.scheme_id)">
                                        <button
                                            class="truncate font-medium hover:text-primary"
                                            @click="openSchemeDrawer(findScheme(turn.scheme_id)!)"
                                        >
                                            {{ findScheme(turn.scheme_id)?.name }}
                                        </button>
                                    </template>
                                    <span v-else class="italic text-muted-foreground">Hidden</span>
                                    <Badge
                                        v-if="turn.scheme_action === 'scored'"
                                        variant="outline"
                                        class="shrink-0 border-green-500/50 px-1 py-0 text-[8px] text-green-600 dark:text-green-400"
                                        >+{{ turn.scheme_points }}</Badge
                                    >
                                    <Badge
                                        v-else-if="turn.scheme_action === 'discarded'"
                                        variant="outline"
                                        class="shrink-0 border-amber-500/50 px-1 py-0 text-[8px] text-amber-600 dark:text-amber-400"
                                        >Discarded</Badge
                                    >
                                    <Badge v-else-if="turn.scheme_action === 'held'" variant="outline" class="shrink-0 px-1 py-0 text-[8px]"
                                        >Held</Badge
                                    >
                                </div>
                                <div v-if="!(player.turns ?? []).length" class="text-xs text-muted-foreground">No turns recorded</div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Compact scenario rolldown -->
                <details class="mb-4 rounded-lg border">
                    <summary class="cursor-pointer px-3 py-2 text-xs font-medium text-muted-foreground hover:text-foreground">
                        Encounter Details
                    </summary>
                    <div class="flex flex-wrap gap-x-4 gap-y-1 border-t px-3 py-2 text-xs">
                        <div v-if="deployment">
                            <span class="text-muted-foreground">Deployment:</span>
                            <button class="ml-1 font-medium hover:text-primary" @click="deploymentDrawerOpen = true">{{ deployment.label }}</button>
                        </div>
                        <div v-if="deployment?.image_url" class="my-2 flex justify-center">
                            <img :src="deployment.image_url" :alt="deployment.label" class="max-h-48 rounded-lg" loading="lazy" />
                        </div>
                        <div v-if="game.strategy">
                            <span class="text-muted-foreground">Strategy:</span>
                            <button class="ml-1 font-medium hover:text-primary" @click="strategyDrawerOpen = true">{{ game.strategy.name }}</button>
                        </div>
                        <div v-if="schemes.length">
                            <span class="text-muted-foreground">Scheme Pool:</span>
                            <span v-for="(scheme, idx) in schemes" :key="scheme.id">
                                <span v-if="idx > 0">, </span>
                                <button class="font-medium hover:text-primary" @click="openSchemeDrawer(scheme)">{{ scheme.name }}</button>
                            </span>
                        </div>
                        <div>
                            <span class="text-muted-foreground">Size:</span>
                            <span class="ml-1 font-medium">{{ game.encounter_size }}ss</span>
                        </div>
                        <div>
                            <span class="text-muted-foreground">Season:</span>
                            <span class="ml-1 font-medium">{{ game.season_label }}</span>
                        </div>
                    </div>
                </details>

                <!-- Turn-by-turn breakdown with scheme info -->
                <div v-if="game.players[0]?.turns?.length" class="mb-4 space-y-2">
                    <h3 class="text-sm font-semibold">Turn-by-Turn Breakdown</h3>

                    <div v-for="turn in Math.max(...game.players.map((p: any) => p.turns?.length ?? 0))" :key="'detail-' + turn">
                        <Card class="overflow-hidden">
                            <button
                                class="flex w-full items-center justify-between px-3 py-2 text-left transition-colors hover:bg-muted/50"
                                @click="expandedTurn === turn ? (expandedTurn = null) : (expandedTurn = turn)"
                            >
                                <div class="flex items-center gap-2">
                                    <span class="text-xs font-semibold">Turn {{ turn }}</span>
                                    <div class="flex items-center gap-2 text-[11px]">
                                        <template v-for="player in game.players" :key="'tscore-' + player.id + '-' + turn">
                                            <span class="flex items-center gap-1">
                                                <FactionLogo v-if="player.faction" :faction="player.faction" class-name="size-3" />
                                                <span class="font-bold">
                                                    +{{
                                                        (getPlayerTurn(player, turn)?.strategy_points ?? 0) +
                                                        (getPlayerTurn(player, turn)?.scheme_points ?? 0)
                                                    }}
                                                </span>
                                                <span class="text-muted-foreground">
                                                    ({{
                                                        player.turns
                                                            ?.filter((t: any) => t.turn_number <= turn)
                                                            .reduce(
                                                                (sum: number, t: any) => sum + (t.strategy_points ?? 0) + (t.scheme_points ?? 0),
                                                                0,
                                                            )
                                                    }})
                                                </span>
                                            </span>
                                        </template>
                                    </div>
                                </div>
                                <ChevronDown
                                    class="size-3.5 shrink-0 text-muted-foreground transition-transform duration-200"
                                    :class="expandedTurn === turn ? 'rotate-180' : ''"
                                />
                            </button>

                            <div v-if="expandedTurn === turn" class="border-t">
                                <div class="grid grid-cols-2 divide-x">
                                    <div v-for="player in game.players" :key="'tdetail-' + player.id + '-' + turn" class="p-3">
                                        <div class="mb-2 flex items-center gap-1.5">
                                            <FactionLogo v-if="player.faction" :faction="player.faction" class-name="size-3.5" />
                                            <span class="text-xs font-semibold">{{ playerName(player) }}</span>
                                        </div>

                                        <!-- Scores -->
                                        <div class="space-y-0.5 text-xs">
                                            <div class="flex justify-between">
                                                <span class="text-muted-foreground">Strategy</span>
                                                <span class="font-medium">+{{ getPlayerTurn(player, turn)?.strategy_points ?? 0 }}</span>
                                            </div>
                                            <div class="flex justify-between">
                                                <span class="text-muted-foreground">Scheme</span>
                                                <span class="font-medium">+{{ getPlayerTurn(player, turn)?.scheme_points ?? 0 }}</span>
                                            </div>
                                        </div>

                                        <!-- Scheme used this turn -->
                                        <div
                                            v-if="getTurnSchemeInfo(player, turn).schemeId || getTurnSchemeInfo(player, turn).action"
                                            class="mt-2 rounded border border-dashed px-2 py-1 text-[10px]"
                                        >
                                            <template v-if="getTurnSchemeInfo(player, turn).schemeId">
                                                <span class="text-muted-foreground">Scheme:</span>
                                                <button
                                                    class="ml-1 font-medium hover:text-primary"
                                                    @click="openSchemeDrawer(findScheme(getTurnSchemeInfo(player, turn).schemeId)!)"
                                                >
                                                    {{ findScheme(getTurnSchemeInfo(player, turn).schemeId)?.name }}
                                                </button>
                                            </template>
                                            <span v-else class="text-muted-foreground">Scheme: Hidden</span>
                                            <Badge
                                                v-if="getTurnSchemeInfo(player, turn).action === 'held'"
                                                variant="outline"
                                                class="ml-1 border-blue-500/50 px-1 py-0 text-[8px] text-blue-600 dark:text-blue-400"
                                                >Held</Badge
                                            >
                                            <Badge
                                                v-if="getTurnSchemeInfo(player, turn).action === 'scored'"
                                                variant="outline"
                                                class="ml-1 border-green-500/50 px-1 py-0 text-[8px] text-green-600 dark:text-green-400"
                                                >Scored</Badge
                                            >
                                            <Badge
                                                v-if="getTurnSchemeInfo(player, turn).action === 'discarded'"
                                                variant="outline"
                                                class="ml-1 border-amber-500/50 px-1 py-0 text-[8px] text-amber-600 dark:text-amber-400"
                                                >Discarded</Badge
                                            >

                                            <!-- Scheme notes for this turn -->
                                            <template v-if="getPlayerTurn(player, turn)?.scheme_notes">
                                                <div
                                                    v-if="getPlayerTurn(player, turn).scheme_notes.selected_model"
                                                    class="mt-1 text-muted-foreground"
                                                >
                                                    <span class="font-medium">Target:</span>
                                                    {{ getPlayerTurn(player, turn).scheme_notes.selected_model }}
                                                </div>
                                                <div
                                                    v-if="getPlayerTurn(player, turn).scheme_notes.selected_marker"
                                                    class="mt-0.5 text-muted-foreground"
                                                >
                                                    <span class="font-medium">Marker:</span>
                                                    {{ getPlayerTurn(player, turn).scheme_notes.selected_marker }}
                                                </div>
                                                <div
                                                    v-if="getPlayerTurn(player, turn).scheme_notes.terrain_note"
                                                    class="mt-0.5 text-muted-foreground"
                                                >
                                                    <span class="font-medium">Terrain:</span>
                                                    {{ getPlayerTurn(player, turn).scheme_notes.terrain_note }}
                                                </div>
                                                <div v-if="getPlayerTurn(player, turn).scheme_notes.note" class="mt-0.5 italic text-muted-foreground">
                                                    {{ getPlayerTurn(player, turn).scheme_notes.note }}
                                                </div>
                                            </template>
                                        </div>

                                        <!-- Crew snapshot -->
                                        <div v-if="getPlayerTurn(player, turn)?.crew_snapshot?.length" class="mt-2">
                                            <div class="mb-1 text-[10px] font-medium uppercase text-muted-foreground">Crew</div>
                                            <div class="space-y-0.5">
                                                <div
                                                    :key="'snap-' + turn + '-' + player.id + '-' + mIdx"
                                                    v-for="(member, mIdx) in getPlayerTurn(player, turn).crew_snapshot"
                                                >
                                                    <div
                                                        :class="factionBackground(member.faction ?? player.faction ?? '')"
                                                        class="flex items-center justify-between rounded px-1.5 py-0.5 text-[11px] text-white"
                                                        :style="member.is_killed ? 'opacity: 0.4; text-decoration: line-through' : ''"
                                                    >
                                                        <div class="flex min-w-0 items-center gap-1">
                                                            <span class="truncate font-medium">{{ member.display_name }}</span>
                                                            <Badge
                                                                v-if="member.is_summoned"
                                                                class="bg-cyan-400/20 px-0.5 py-0 text-[8px] text-cyan-200"
                                                                >S</Badge
                                                            >
                                                        </div>
                                                        <div class="flex items-center gap-1">
                                                            <template v-if="member.attached_tokens?.length">
                                                                <div
                                                                    v-for="token in member.attached_tokens"
                                                                    :key="token.id"
                                                                    class="rounded bg-cyan-900/50 px-0.5 text-[8px] text-cyan-200"
                                                                >
                                                                    {{ token.name }}
                                                                </div>
                                                            </template>
                                                            <span class="flex items-center gap-0.5 font-bold">
                                                                <Heart
                                                                    class="size-2.5"
                                                                    :class="
                                                                        member.is_killed
                                                                            ? 'text-red-400'
                                                                            : member.current_health <= Math.ceil(member.max_health / 2)
                                                                              ? 'text-red-300'
                                                                              : ''
                                                                    "
                                                                />
                                                                {{ member.current_health }}/{{ member.max_health }}
                                                            </span>
                                                        </div>
                                                    </div>
                                                    <!-- Attached upgrades in snapshot -->
                                                    <div v-if="member.attached_upgrades?.length" class="space-y-0.5 pl-3">
                                                        <div
                                                            v-for="upgrade in member.attached_upgrades"
                                                            :key="'su-' + upgrade.id"
                                                            class="flex items-center gap-1 rounded bg-black/10 px-1 py-0 text-[9px] text-amber-300"
                                                        >
                                                            <ArrowUpCircle class="size-2 shrink-0" />
                                                            <span class="truncate">{{ upgrade.name }}</span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div v-else-if="player.faction" class="mt-2 text-[10px] text-muted-foreground">No crew tracked</div>
                                    </div>
                                </div>
                            </div>
                        </Card>
                    </div>
                </div>

                <!-- Final crew states with crew upgrades -->
                <div class="mb-6 grid gap-4 sm:grid-cols-2">
                    <div v-for="player in game.players" :key="'crew-' + player.id">
                        <h3 class="mb-2 text-sm font-semibold">{{ playerName(player) }}'s Final Crew</h3>
                        <div
                            v-if="!player.crew_members?.length && player.faction"
                            class="flex items-center gap-2 rounded-md border border-dashed p-3"
                        >
                            <FactionLogo :faction="player.faction" class-name="size-6" />
                            <div class="text-xs">
                                <div v-if="player.master_name" class="font-medium">{{ player.master_name }}</div>
                                <span class="text-muted-foreground">No crew tracked</span>
                            </div>
                        </div>
                        <template v-else>
                            <!-- Crew upgrades -->
                            <div v-if="player.master?.crew_upgrades?.length" class="mb-1.5 space-y-0.5">
                                <div
                                    v-for="upgrade in player.master.crew_upgrades"
                                    :key="'fu-' + upgrade.id"
                                    class="flex items-center gap-1.5 rounded-md border px-2 py-1.5 text-sm"
                                    :class="[
                                        player.crew_build?.crew_upgrade_id === upgrade.id
                                            ? 'border-amber-500/50 bg-amber-500/10'
                                            : 'border-border/50 bg-accent/30 opacity-60',
                                        upgrade.front_image ? 'cursor-pointer hover:bg-accent' : '',
                                    ]"
                                    @click="openUpgradePreview(upgrade)"
                                >
                                    <Star
                                        class="size-3.5 shrink-0"
                                        :class="
                                            player.crew_build?.crew_upgrade_id === upgrade.id
                                                ? 'fill-amber-500 text-amber-500'
                                                : 'text-muted-foreground'
                                        "
                                    />
                                    <span class="font-semibold">{{ upgrade.name }}</span>
                                </div>
                            </div>
                            <!-- Crew members -->
                            <div class="space-y-1">
                                <div
                                    v-for="member in player.crew_members"
                                    :key="member.id"
                                    :class="factionBackground(member.faction ?? player.faction ?? '')"
                                    class="rounded-md border border-white/20 px-2 py-1.5 text-white"
                                    :style="member.is_killed ? 'opacity: 0.4; text-decoration: line-through' : ''"
                                >
                                    <div class="flex items-center justify-between">
                                        <div class="flex min-w-0 items-center gap-1">
                                            <span
                                                class="truncate text-sm font-semibold"
                                                :class="member.front_image ? 'cursor-pointer hover:underline' : ''"
                                                @click="openMemberPreview(member)"
                                            >
                                                {{ member.display_name }}
                                            </span>
                                            <Badge
                                                v-if="member.is_summoned"
                                                variant="secondary"
                                                class="bg-cyan-500/20 px-1 py-0 text-[9px] text-cyan-200"
                                                >Summoned</Badge
                                            >
                                        </div>
                                        <span class="flex min-w-[3rem] items-center justify-center gap-0.5 text-xs font-bold">
                                            <Heart
                                                class="size-3"
                                                :class="
                                                    member.is_killed
                                                        ? 'text-red-400'
                                                        : member.current_health <= Math.ceil(member.max_health / 2)
                                                          ? 'text-red-300'
                                                          : ''
                                                "
                                            />
                                            {{ member.current_health }}/{{ member.max_health }}
                                        </span>
                                    </div>
                                    <div v-if="member.attached_tokens?.length" class="mt-0.5 flex flex-wrap gap-1">
                                        <Badge
                                            v-for="token in member.attached_tokens"
                                            :key="token.id"
                                            variant="secondary"
                                            class="border border-cyan-500/50 bg-cyan-900/60 px-1 py-0 text-[9px] text-cyan-200"
                                            >{{ token.name }}</Badge
                                        >
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </template>
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

    <!-- Replace on Death Dialog -->
    <GameReplaceOnDeathDialog
        :open="replaceOnDeathDialogOpen"
        :replacements="replaceOnDeathReplacements"
        :warnings="replaceOnDeathWarnings"
        :has-selected="hasSelectedReplacements"
        @update:open="replaceOnDeathDialogOpen = $event"
        @toggle="(id) => { const r = replaceOnDeathReplacements.find((x) => x.id === id); if (r) r.selected = !r.selected; }"
        @confirm="confirmReplaceOnDeath"
        @dismiss="dismissReplaceOnDeath"
    />

    <!-- QR Code Dialog -->
    <QRCodeDialog v-if="qrDialogOpen" v-model:open="qrDialogOpen" :url="qrDialogUrl" :title="qrDialogTitle" />

    <!-- Card Fullscreen Dialog -->
    <GameCardFullscreenDialog :open="cardFullscreenOpen" :src="cardFullscreenSrc" @update:open="cardFullscreenOpen = $event" />

    <!-- Leave confirmation for in-progress games -->
    <GameLeaveDialog :open="confirmLeaveOpen" @stay="cancelLeave" @leave="confirmLeave" />
</template>
