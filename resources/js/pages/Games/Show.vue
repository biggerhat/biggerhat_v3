<script setup lang="ts">
import CharacterCardView from '@/components/CharacterCardView.vue';
import CrewBuilderReferences from '@/components/CrewBuilderReferences.vue';
import FactionLogo from '@/components/FactionLogo.vue';
import QRCodeDialog from '@/components/QRCodeDialog.vue';
import GameIcon from '@/components/GameIcon.vue';
import UpgradeFlipCard from '@/components/UpgradeFlipCard.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Drawer, DrawerClose, DrawerContent, DrawerFooter, DrawerHeader, DrawerTitle } from '@/components/ui/drawer';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useGameChannel } from '@/composables/useGameChannel';
import { type SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ArrowLeft, ArrowUpCircle, Check, ChevronDown, Circle, Copy, Dices, EllipsisVertical, Eye, EyeOff, Heart, Loader2, Minus, Pencil, Plus, Puzzle, QrCode, Replace, RotateCcw, Shield, ShieldAlert, Skull, Star, Swords, UserRound, Users } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';

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
    crew_members: any[];
    master: { id: number; crew_upgrades: any[] } | null;
    crew_build: { id: number; crew_upgrade_id: number | null } | null;
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
    all_strategies: { id: number; name: string; slug: string }[];
    all_schemes: { id: number; name: string; slug: string }[];
    all_deployments: { value: string; label: string }[];
    current_schemes: SchemeData[];
    opponent_scheme_intel: {
        last_revealed: { id: number; name: string; turn_number: number; scored: boolean } | null;
        possible_schemes: SchemeData[];
    } | null;
    next_schemes: SchemeData[];
    opponent_next_schemes: SchemeData[];
    tokens: { id: number; name: string; slug: string; description: string | null }[];
    character_upgrades: { id: number; name: string; slug: string; front_image: string | null; back_image: string | null; type: string | null; plentiful: number | null }[];
    all_markers: { id: number; name: string; slug: string }[];
    all_reachable_schemes: SchemeData[];
    observer_scheme_intel: Record<number, {
        possible_schemes: SchemeData[];
        revealed_scheme_id: number | null;
        last_scored_turn: number | null;
    }> | null;
    starting_crews?: Record<number, { display_name: string; faction: string; cost: number; hiring_category: string; front_image: string | null; back_image: string | null }[]>;
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
const { onlineMembers } = useGameChannel(isSolo.value && !isObserver.value ? '' : props.game.uuid, isObserver.value);
const isUserOnline = (userId: number) => onlineMembers.value.some((m) => m.id === userId);

// Scenario editing
const isCreator = computed(() => currentUserId.value === props.game.creator_id);
const canEditScenario = computed(() => {
    const editableStatuses = ['setup', 'faction_select', 'master_select', 'crew_select', 'scheme_select'];
    return editableStatuses.includes(props.game.status) && isCreator.value;
});
const editingDeployment = ref(false);
const editingStrategy = ref(false);
const editingSchemes = ref(false);
const editStrategy = ref<string>(String(props.game.strategy?.id ?? ''));
const editDeployment = ref<string>(props.deployment?.value ?? '');
const editSchemePool = ref<number[]>(props.schemes.map((s) => s.id));

const availableSchemes = computed(() => {
    const pickedIds = new Set(editSchemePool.value);
    return (index: number) => props.all_schemes.filter((s) => s.id === editSchemePool.value[index] || !pickedIds.has(s.id));
});

const setScheme = (index: number, value: string) => {
    const found = props.all_schemes.find((s) => String(s.id) === value);
    if (found) {
        editSchemePool.value[index] = found.id;
        saveScenarioField('scheme_pool', [...editSchemePool.value]);
    }
};

const saveScenarioField = async (field: string, value: unknown) => {
    const body: Record<string, unknown> = {
        strategy_id: field === 'strategy_id' ? value : (props.game.strategy?.id ?? null),
        deployment: field === 'deployment' ? value : (props.deployment?.value ?? null),
        scheme_pool: field === 'scheme_pool' ? value : props.schemes.map((s) => s.id),
    };
    try {
        await fetch(route('games.scenario.update', props.game.uuid), {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
            body: JSON.stringify(body),
        });
        router.reload({ only: ['game', 'schemes', 'deployment'], preserveScroll: true });
    } catch (e) {
        console.error('Scenario update error:', e);
    }
};

const regenerateScenario = () => {
    router.post(route('games.scenario.regenerate', props.game.uuid));
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
const pendingScheme = computed(() => pendingSchemeId.value ? props.schemes.find((s) => s.id === pendingSchemeId.value) : null);
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
    const pool = req.allegiance === 'friendly'
        ? [...(myPlayer.value?.crew_members ?? [])]
        : [...(opponent.value?.crew_members ?? [])];
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
    // Save scheme notes first if there are requirements
    const notes: Record<string, string | null> = {
        note: null,
        selected_model: pendingSchemeModel.value || null,
        selected_marker: pendingSchemeMarker.value || null,
        terrain_note: pendingSchemeTerrainNote.value || null,
    };
    const hasNotes = notes.selected_model || notes.selected_marker || notes.terrain_note;
    if (hasNotes) {
        await fetch(route('games.play.scheme-notes', props.game.uuid), {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
            body: JSON.stringify({ scheme_notes: notes }),
        });
    }
    await postSetup(route('games.setup.scheme', props.game.uuid), {
        scheme_id: pendingSchemeId.value,
        ...(isSolo.value ? { slot: 1 } : {}),
    });
    pendingSchemeId.value = null;
};
const csrfToken = () => document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';

const postSetup = async (endpoint: string, body: Record<string, unknown>) => {
    submitting.value = true;
    try {
        const res = await fetch(endpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
            body: JSON.stringify(body),
        });
        if (!res.ok) {
            console.error('Setup failed:', res.status);
            submitting.value = false;
            return;
        }
        // Setup steps can change status which affects available props — reload all relevant data
        router.reload({
            only: ['game', 'schemes', 'deployment', 'masters', 'my_crews', 'current_schemes', 'next_schemes', 'opponent_next_schemes', 'tokens', 'character_upgrades', 'all_markers'],
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
    if (isSolo.value) body.slot = 1;
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
    return route('tools.crew_builder.editor') + '?step=title&faction=' + encodeURIComponent(faction) + '&master=' + encodeURIComponent(masterName) + gameParam;
});
const newOpponentCrewUrl = computed(() => {
    const faction = opponentPlayer.value?.faction ?? '';
    const gameParam = '&from_game=' + encodeURIComponent(props.game.uuid);
    const masterId = opponentPlayer.value?.master_id;
    if (masterId) {
        return route('tools.crew_builder.editor') + '?step=hiring&faction=' + encodeURIComponent(faction) + '&master=' + masterId + gameParam;
    }
    const masterName = opponentPlayer.value?.master_name?.split(',')[0] ?? '';
    return route('tools.crew_builder.editor') + '?step=title&faction=' + encodeURIComponent(faction) + '&master=' + encodeURIComponent(masterName) + gameParam;
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
        case 'faction': return !!opponentPlayer.value.faction;
        case 'master': return !!opponentPlayer.value.master_name;
        case 'crew': return !!opponentPlayer.value.crew_build_id || opponentPlayer.value.crew_skipped;
        case 'scheme': return !!opponentPlayer.value.current_scheme_id;
        default: return false;
    }
};

const selectOpponentFaction = () => {
    if (!selectedOpponentFaction.value) return;
    postSetup(route('games.setup.faction', props.game.uuid), { faction: selectedOpponentFaction.value, slot: 2 });
};

const confirmOpponentMasterSelection = () => {
    if (!selectedOpponentMasterName.value) return;
    postSetup(route('games.setup.master', props.game.uuid), { master_name: selectedOpponentMasterName.value, slot: 2 });
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
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
                body: JSON.stringify({ master_name: title.display_name, slot: 2 }),
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

const saveOpponentName = async () => {
    const name = opponentNameInput.value.trim();
    if (!name) { editingOpponentName.value = false; return; }
    await fetch(route('games.setup.opponent_name', props.game.uuid), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
        body: JSON.stringify({ opponent_name: name }),
    });
    editingOpponentName.value = false;
    router.reload({ only: ['game'], preserveScroll: true });
};

const swapRoles = async () => {
    await fetch(route('games.setup.swap_roles', props.game.uuid), {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': csrfToken() },
    });
    router.reload({ only: ['game'], preserveScroll: true });
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

const submitOpponentTurnScore = async () => {
    const mode = opponentSchemeScored.value ? 'scored' : 'discard';
    const result = await openOppSchemeDialog(mode);
    if (result === 'cancel') return;

    scoringOpponentTurn.value = true;
    const payload: Record<string, any> = {
        strategy_points: opponentStrategyPoints.value,
        scheme_points: opponentSchemePoints.value,
        slot: 2,
    };

    // Identified scheme = what they scored/discarded this turn.
    // This sets current_scheme_id on the backend, so next turn's pool
    // derives from this scheme's chain automatically.
    if (oppIdentifiedSchemeId.value) {
        payload.solo_scheme_id = oppIdentifiedSchemeId.value;
    }

    await fetch(route('games.play.turns.store', props.game.uuid), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
        body: JSON.stringify(payload),
    });

    opponentStrategyPoints.value = 0;
    opponentSchemePoints.value = 0;
    opponentNextSchemeId.value = null;
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

const updateOpponentSoulstonePool = (delta: number) => {
    const current = opponentPlayer.value?.soulstone_pool ?? 0;
    const newVal = Math.max(0, current + delta);
    postPlay(route('games.play.soulstones', props.game.uuid), 'PATCH', { soulstone_pool: newVal, slot: 2 });
};

// Solo: opponent possible scheme pool (for the identify step)
const opponentSchemePool = computed(() => {
    if (!opponent.value?.current_scheme_id || !props.opponent_next_schemes.length) {
        return props.schemes;
    }
    return props.opponent_next_schemes;
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
            turnBannerTimer = setTimeout(() => (turnBanner.value = false), 4000);
        }
    },
);

const abandonDialogOpen = ref(false);

// Observation mode
const observeLinkCopied = ref(false);
const toggleObservable = async () => {
    await fetch(route('games.toggle_observable', props.game.uuid), {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': csrfToken() },
    });
    router.reload({ only: ['game'], preserveScroll: true });
};
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
const expandedTurn = ref<number | null>(null);
const executeAbandon = async () => {
    abandonDialogOpen.value = false;
    await fetch(route('games.abandon', props.game.uuid), {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': csrfToken(), Accept: 'application/json' },
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

// ─── Gameplay ───
const gameplayTab = ref<'scenario' | 'my-crew' | 'opponent'>('my-crew');
const schemeHidden = ref(false);

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
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
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
    const pool = req.allegiance === 'friendly'
        ? [...(myPlayer.value?.crew_members ?? [])]
        : [...(opponent.value?.crew_members ?? [])];

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
const schemeNotesLocked = computed(() => !!myPlayer.value?.is_turn_complete);

// Sync scheme notes from props when they change (e.g. after reload)
watch(() => myPlayer.value?.scheme_notes, (notes) => {
    schemeNote.value = notes?.note ?? '';
    schemeSelectedModel.value = notes?.selected_model ?? '';
    schemeSelectedMarker.value = notes?.selected_marker ?? '';
    schemeTerrainNote.value = notes?.terrain_note ?? '';
});

// Card preview drawers
const crewMemberDrawerOpen = ref(false);
const previewMember = ref<any>(null);
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
const myActiveUpgradeId = computed(() => myPlayer.value?.crew_build?.crew_upgrade_id ?? null);
const opponentActiveUpgradeId = computed(() => opponent.value?.crew_build?.crew_upgrade_id ?? null);

const myCrewMembers = computed(() => myPlayer.value?.crew_members?.filter((m: any) => !m.is_killed) ?? []);
const myKilledMembers = computed(() => myPlayer.value?.crew_members?.filter((m: any) => m.is_killed) ?? []);
const opponentCrewMembers = computed(() => opponent.value?.crew_members?.filter((m: any) => !m.is_killed) ?? []);
const opponentKilledMembers = computed(() => opponent.value?.crew_members?.filter((m: any) => m.is_killed) ?? []);

const postPlay = async (url: string, method: string = 'POST', body?: Record<string, unknown>) => {
    try {
        const opts: RequestInit = { method, headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken() } };
        if (body) opts.body = JSON.stringify(body);
        const res = await fetch(url, opts);
        if (!res.ok) console.error('Play action failed:', res.status);
        router.reload({ only: ['game'], preserveScroll: true });
    } catch (e) {
        console.error('Play action error:', e);
    }
};

const updateHealth = async (member: any, delta: number) => {
    const newHealth = Math.max(0, Math.min(member.max_health, member.current_health + delta));
    if (newHealth === member.current_health) return;
    const oldHealth = member.current_health;
    // Optimistic update
    member.current_health = newHealth;
    if (newHealth === 0) {
        killMember(member);
        return;
    }
    const res = await fetch(route('games.play.crew.update', { game: props.game.uuid, gameCrewMember: member.id }), {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
        body: JSON.stringify({ current_health: newHealth }),
    });
    if (!res.ok) {
        member.current_health = oldHealth;
        router.reload({ only: ['game'], preserveScroll: true });
    }
};

const toggleActivated = async (member: any) => {
    const oldValue = member.is_activated;
    // Optimistic update for instant UI feedback
    member.is_activated = !member.is_activated;
    const res = await fetch(route('games.play.crew.update', { game: props.game.uuid, gameCrewMember: member.id }), {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
        body: JSON.stringify({ is_activated: member.is_activated }),
    });
    if (!res.ok) {
        member.is_activated = oldValue;
        router.reload({ only: ['game'], preserveScroll: true });
    }
};

// Kill with replacement detection
const replaceOnDeathDialogOpen = ref(false);
const replaceOnDeathReplacements = ref<{ id: number; display_name: string; count: number; health: number | null; front_image: string | null; selected: boolean }[]>([]);
const replaceOnDeathSlot = ref<number>(1);
const replaceOnDeathInheritedTokens = ref<any[]>([]);
const replaceOnDeathInheritedUpgrades = ref<any[]>([]);
const replaceOnDeathWasActivated = ref(false);
const hasSelectedReplacements = computed(() => replaceOnDeathReplacements.value.some((r) => r.selected));

const killMember = async (member: any) => {
    // Save state before killing for inheritance
    const killedTokens = [...(member.attached_tokens ?? [])];
    const killedUpgrades = [...(member.attached_upgrades ?? [])];
    const wasActivated = !!member.is_activated;

    // Optimistic UI — mark killed immediately
    member.is_killed = true;
    member.current_health = 0;

    // Fire kill request — don't await the reload if there are replacements
    const res = await fetch(route('games.play.crew.kill', { game: props.game.uuid, gameCrewMember: member.id }), {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': csrfToken(), Accept: 'application/json' },
    });
    const data = await res.json().catch(() => ({}));

    if (data.replacements?.length) {
        const isMyMember = myPlayer.value?.crew_members?.some((m: any) => m.id === member.id);
        replaceOnDeathSlot.value = isMyMember ? 1 : 2;
        replaceOnDeathInheritedTokens.value = killedTokens;
        replaceOnDeathInheritedUpgrades.value = killedUpgrades;
        replaceOnDeathWasActivated.value = wasActivated;
        replaceOnDeathReplacements.value = data.replacements.map((r: any) => ({ ...r, selected: false }));
        replaceOnDeathDialogOpen.value = true;
        // Defer reload until dialog is handled
    } else {
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
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken(), Accept: 'application/json' },
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
        replaceOnDeathReplacements.value = [];
    }
};

const dismissReplaceOnDeath = () => {
    replaceOnDeathDialogOpen.value = false;
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
    const pool = req.allegiance === 'friendly'
        ? [...(myPlayer.value?.crew_members ?? [])]
        : [...(opponent.value?.crew_members ?? [])];
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
watch(nextSchemeId, () => { nextSchemeModel.value = ''; nextSchemeMarker.value = ''; nextSchemeTerrain.value = ''; });


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
const findScheme = (id: number | null | undefined) => id ? allKnownSchemes.value.get(id) : undefined;

// Post-game summary: resolve scheme per turn (backfill nulls) and detect held schemes
// Cache keyed by player ID + turn count to auto-invalidate when data changes
let schemeInfoCacheKey = '';
const schemeInfoCache = new Map<number, Map<number, { schemeId: number | null; held: boolean }>>();
const resolvePlayerSchemes = (player: any): Map<number, { schemeId: number | null; held: boolean }> => {
    const cacheKey = `${props.game.current_turn}-${props.game.players.map((p: any) => (p.turns?.length ?? 0)).join(',')}`;
    if (cacheKey !== schemeInfoCacheKey) { schemeInfoCache.clear(); schemeInfoCacheKey = cacheKey; }
    if (schemeInfoCache.has(player.id)) return schemeInfoCache.get(player.id)!;

    const turns = (player.turns ?? []).slice().sort((a: any, b: any) => a.turn_number - b.turn_number);
    const result = new Map<number, { schemeId: number | null; held: boolean }>();

    // Backward-only fill: each null turn looks forward to the next non-null scheme.
    // This handles solo opponent hidden schemes — if they reveal on T3, T1 and T2
    // are assumed to have held that same scheme hidden.
    // We never forward-fill because a discard changes the scheme chain.
    const resolved: { turnNumber: number; schemeId: number | null; backfilled: boolean }[] = [];

    for (let i = 0; i < turns.length; i++) {
        const raw = turns[i].scheme_id ?? null;
        if (raw) {
            resolved.push({ turnNumber: turns[i].turn_number, schemeId: raw, backfilled: false });
        } else {
            // Look forward for the next non-null scheme
            let futureScheme: number | null = null;
            for (let j = i + 1; j < turns.length; j++) {
                if (turns[j].scheme_id) { futureScheme = turns[j].scheme_id; break; }
            }
            resolved.push({ turnNumber: turns[i].turn_number, schemeId: futureScheme, backfilled: !!futureScheme });
        }
    }

    // Held = backfilled from future turn, OR same scheme as previous turn with 0 scheme points scored
    for (let i = 0; i < resolved.length; i++) {
        const curr = resolved[i].schemeId;
        const prev = i > 0 ? resolved[i - 1].schemeId : null;
        const held = resolved[i].backfilled || (i > 0 && !!curr && curr === prev && (turns[i].scheme_points ?? 0) === 0);
        result.set(resolved[i].turnNumber, { schemeId: curr, held });
    }

    schemeInfoCache.set(player.id, result);
    return result;
};

const getTurnSchemeInfo = (player: any, turnNumber: number): { schemeId: number | null; held: boolean } => {
    return resolvePlayerSchemes(player).get(turnNumber) ?? { schemeId: null, held: false };
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
const maxStrategyThisTurn = computed(() => myStrategyBonusUsed.value ? 1 : 2);

// Scheme: max 2/turn, max 6 total across game
const myTotalSchemeScored = computed(() => {
    return (myPlayer.value?.turns ?? []).reduce((sum: number, t: any) => sum + (t.scheme_points ?? 0), 0);
});
const maxSchemeThisTurn = computed(() => Math.min(2, 6 - myTotalSchemeScored.value));

// Opponent scoring limits (solo)
const opponentStrategyBonusUsed = computed(() => {
    return (opponent.value?.turns ?? []).some((t: any) => t.strategy_points > 1);
});
const opponentMaxStrategyThisTurn = computed(() => opponentStrategyBonusUsed.value ? 1 : 2);
const opponentTotalSchemeScored = computed(() => {
    return (opponent.value?.turns ?? []).reduce((sum: number, t: any) => sum + (t.scheme_points ?? 0), 0);
});
const opponentMaxSchemeThisTurn = computed(() => Math.min(2, 6 - opponentTotalSchemeScored.value));

const submitTurnScore = async () => {
    scoringTurn.value = true;

    // If switching schemes, save the new scheme's requirement selections
    if (nextSchemeId.value && nextSchemeReqs.value.length) {
        await fetch(route('games.play.scheme-notes', props.game.uuid), {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
            body: JSON.stringify({
                scheme_notes: {
                    note: null,
                    selected_model: nextSchemeModel.value || null,
                    selected_marker: nextSchemeMarker.value || null,
                    terrain_note: nextSchemeTerrain.value || null,
                },
            }),
        });
    }

    await fetch(route('games.play.turns.store', props.game.uuid), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
        body: JSON.stringify({
            strategy_points: strategyPoints.value,
            scheme_points: schemePoints.value,
            next_scheme_id: nextSchemeId.value,
        }),
    });
    strategyPoints.value = 0;
    schemePoints.value = 0;
    nextSchemeId.value = null;
    scoringTurn.value = false;
    router.reload({ only: ['game', 'current_schemes', 'next_schemes', 'opponent_next_schemes', 'opponent_scheme_intel'], preserveState: true, preserveScroll: true });
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
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
                body: JSON.stringify({ scheme_id: oppIdentifiedSchemeId.value, slot: 2 }),
            });
        }
    }

    const res = await fetch(route('games.play.complete', props.game.uuid), {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': csrfToken(), Accept: 'application/json' },
    });
    const data = await res.json().catch(() => ({}));
    if (data.game_complete) {
        router.visit(route('games.show', props.game.uuid));
    } else {
        router.reload({ only: ['game'], preserveScroll: true });
    }
};

const cancelGameComplete = async () => {
    await fetch(route('games.play.cancel_complete', props.game.uuid), {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': csrfToken() },
    });
    router.reload({ only: ['game'], preserveScroll: true });
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
    if (q.length < 2) { summonResults.value = []; return; }
    summonLoading.value = true;
    summonDebounce = setTimeout(async () => {
        try {
            const res = await fetch(route('api.characters.search') + '?q=' + encodeURIComponent(q));
            summonResults.value = await res.json();
        } catch {
            summonResults.value = [];
        }
        summonLoading.value = false;
    }, 300);
};

// Sculpt picker (shared between summon and replace)
const sculptPickerOpen = ref(false);
const sculptPickerMiniatures = ref<any[]>([]);
const sculptPickerAction = ref<'summon' | 'replace'>('summon');
const sculptPickerCharacterId = ref<number>(0);

const selectCharacterForSummon = (char: any) => {
    const minis = char.miniatures ?? [];
    if (minis.length > 1) {
        sculptPickerCharacterId.value = char.id;
        sculptPickerMiniatures.value = minis;
        sculptPickerAction.value = 'summon';
        sculptPickerOpen.value = true;
    } else {
        summonCharacter(char.id, minis[0]?.id ?? null);
    }
};

const summonCharacter = async (characterId: number, miniatureId: number | null = null) => {
    const body: Record<string, unknown> = { character_id: characterId };
    if (miniatureId) body.miniature_id = miniatureId;
    if (isSolo.value) body.slot = summonForSlot.value;
    await fetch(route('games.play.crew.summon', props.game.uuid), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
        body: JSON.stringify(body),
    });
    summonDialogOpen.value = false;
    sculptPickerOpen.value = false;
    summonSearch.value = '';
    summonResults.value = [];
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
    if (q.length < 2) { replaceResults.value = []; return; }
    replaceLoading.value = true;
    replaceDebounce = setTimeout(async () => {
        try {
            const res = await fetch(route('api.characters.search') + '?q=' + encodeURIComponent(q));
            replaceResults.value = await res.json();
        } catch {
            replaceResults.value = [];
        }
        replaceLoading.value = false;
    }, 300);
};

const selectCharacterForReplace = (char: any) => {
    const minis = char.miniatures ?? [];
    if (minis.length > 1) {
        sculptPickerCharacterId.value = char.id;
        sculptPickerMiniatures.value = minis;
        sculptPickerAction.value = 'replace';
        sculptPickerOpen.value = true;
    } else {
        replaceCharacter(char.id, minis[0]?.id ?? null);
    }
};

const replaceCharacter = async (characterId: number, miniatureId: number | null = null) => {
    if (!replaceMember.value) return;
    const body: Record<string, unknown> = { character_id: characterId };
    if (miniatureId) body.miniature_id = miniatureId;
    await fetch(route('games.play.crew.replace', { game: props.game.uuid, gameCrewMember: replaceMember.value.id }), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
        body: JSON.stringify(body),
    });
    replaceDialogOpen.value = false;
    sculptPickerOpen.value = false;
    replaceSearch.value = '';
    replaceResults.value = [];
    replaceMember.value = null;
    router.reload({ only: ['game'], preserveScroll: true });
};

const confirmSculptSelection = (miniatureId: number) => {
    if (sculptPickerAction.value === 'summon') {
        summonCharacter(sculptPickerCharacterId.value, miniatureId);
    } else {
        replaceCharacter(sculptPickerCharacterId.value, miniatureId);
    }
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
    // Update preview immediately
    previewMember.value.front_image = mini.front_image;
    previewMember.value.back_image = mini.back_image;
    previewMember.value.display_name = mini.display_name;
    // Persist to server
    await fetch(route('games.play.crew.update', { game: props.game.uuid, gameCrewMember: previewMember.value.id }), {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
        body: JSON.stringify({ display_name: mini.display_name, front_image: mini.front_image, back_image: mini.back_image }),
    });
    router.reload({ only: ['game'], preserveScroll: true });
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
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
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
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
        body: JSON.stringify({ attached_tokens: updated }),
    });
    router.reload({ only: ['game'], preserveScroll: true });
};

// Reference characters from loaded references (summons/replaces)
const referenceCharacters = computed(() => {
    const chars: any[] = [];
    const seen = new Set<number>();
    for (const c of myReferences.value?.characters ?? []) {
        if (!seen.has(c.id)) { seen.add(c.id); chars.push(c); }
    }
    for (const c of opponentReferences.value?.characters ?? []) {
        if (!seen.has(c.id)) { seen.add(c.id); chars.push(c); }
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

const memberHasToken = (tokenId: number) => {
    return (tokenMember.value?.attached_tokens ?? []).some((t: any) => t.id === tokenId);
};

const toggleToken = async (tokenId: number, tokenName: string) => {
    if (!tokenMember.value) return;
    const current = tokenMember.value.attached_tokens ?? [];
    const has = current.some((t: any) => t.id === tokenId);
    const updated = has
        ? current.filter((t: any) => t.id !== tokenId)
        : [...current, { id: tokenId, name: tokenName }];
    // Optimistic update for responsive dialog
    tokenMember.value = { ...tokenMember.value, attached_tokens: updated };
    await fetch(route('games.play.crew.update', { game: props.game.uuid, gameCrewMember: tokenMember.value.id }), {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
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
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
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

const memberHasUpgrade = (upgradeId: number) => {
    return (upgradeMember.value?.attached_upgrades ?? []).some((u: any) => u.id === upgradeId);
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

const isUpgradeAtLimit = (upgradeId: number, plentiful: number | null) => {
    const limit = plentiful ?? 1;
    const used = upgradeUsageCount(upgradeId);
    const selfHas = memberHasUpgrade(upgradeId) ? 1 : 0;
    return used + selfHas >= limit;
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

const toggleUpgrade = async (upgrade: { id: number; name: string; front_image: string | null; back_image: string | null }) => {
    if (!upgradeMember.value) return;
    const current = upgradeMember.value.attached_upgrades ?? [];
    const has = current.some((u: any) => u.id === upgrade.id);
    const updated = has
        ? current.filter((u: any) => u.id !== upgrade.id)
        : [...current, { id: upgrade.id, name: upgrade.name, front_image: upgrade.front_image, back_image: upgrade.back_image }];
    upgradeMember.value = { ...upgradeMember.value, attached_upgrades: updated };
    await fetch(route('games.play.crew.update', { game: props.game.uuid, gameCrewMember: upgradeMember.value.id }), {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
        body: JSON.stringify({ attached_upgrades: updated }),
    });
    router.reload({ only: ['game'], preserveScroll: true });
};

const quickRemoveUpgrade = async (member: any, upgradeId: number) => {
    const updated = (member.attached_upgrades ?? []).filter((u: any) => u.id !== upgradeId);
    member.attached_upgrades = updated;
    await fetch(route('games.play.crew.update', { game: props.game.uuid, gameCrewMember: member.id }), {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
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
        case 'explorers_society': return 'bg-explorerssociety';
        case 'ten_thunders': return 'bg-tenthunders';
        default: return `bg-${faction}`;
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

        <div class="container mx-auto pb-8 pt-4 sm:px-4 lg:pt-6">
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
                <Badge v-if="game.is_observable && game.status !== 'completed' && game.status !== 'abandoned'" variant="outline" class="border-amber-500/50 text-xs text-amber-600 dark:text-amber-400">Public</Badge>
                <Button v-if="canEditScenario" variant="ghost" size="sm" class="ml-auto gap-1" @click="regenerateScenario">
                    <Dices class="size-3.5" />
                    Re-roll
                </Button>
            </div>

            <!-- Scenario (hidden during gameplay and completed) -->
            <div v-if="game.status !== 'in_progress' && game.status !== 'completed' && game.status !== 'abandoned'" class="mb-6 space-y-4">
                <!-- Deployment & Strategy row -->
                <div class="grid gap-3 sm:grid-cols-2">
                    <!-- Deployment -->
                    <div v-if="deployment">
                        <div class="mb-1.5 flex items-center gap-2">
                            <h3 class="text-sm font-semibold">Deployment</h3>
                            <button v-if="canEditScenario" class="rounded-md p-0.5 text-muted-foreground transition-colors hover:bg-accent hover:text-foreground" @click="editingDeployment = !editingDeployment">
                                <Pencil class="size-3" />
                            </button>
                        </div>
                        <div v-if="editingDeployment" class="mb-2">
                            <Select :model-value="editDeployment" @update:model-value="(v: string) => { editDeployment = v; editingDeployment = false; saveScenarioField('deployment', v); }">
                                <SelectTrigger class="w-full"><SelectValue placeholder="Select Deployment" /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="d in all_deployments" :key="d.value" :value="d.value">{{ d.label }}</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <Card class="cursor-pointer transition-all hover:-translate-y-0.5 hover:shadow-md" @click="deploymentDrawerOpen = true">
                            <CardContent class="p-3">
                                <div class="text-sm font-medium">{{ deployment.label }}</div>
                                <p class="mt-0.5 text-[11px] leading-snug text-muted-foreground">{{ deployment.description }}</p>
                            </CardContent>
                        </Card>
                    </div>

                    <!-- Strategy -->
                    <div v-if="game.strategy">
                        <div class="mb-1.5 flex items-center gap-2">
                            <h3 class="text-sm font-semibold">Strategy</h3>
                            <button v-if="canEditScenario" class="rounded-md p-0.5 text-muted-foreground transition-colors hover:bg-accent hover:text-foreground" @click="editingStrategy = !editingStrategy">
                                <Pencil class="size-3" />
                            </button>
                        </div>
                        <div v-if="editingStrategy" class="mb-2">
                            <Select :model-value="editStrategy" @update:model-value="(v: string) => { editStrategy = v; editingStrategy = false; saveScenarioField('strategy_id', Number(v)); }">
                                <SelectTrigger class="w-full"><SelectValue placeholder="Select Strategy" /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="s in all_strategies" :key="s.id" :value="String(s.id)">{{ s.name }}</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <Card class="cursor-pointer transition-all hover:-translate-y-0.5 hover:shadow-md" @click="strategyDrawerOpen = true">
                            <CardContent class="p-3">
                                <div class="text-sm font-medium">{{ game.strategy.name }}</div>
                            </CardContent>
                        </Card>
                    </div>
                </div>

                <!-- Schemes -->
                <div v-if="schemes.length">
                    <div class="mb-1.5 flex items-center gap-2">
                        <h3 class="text-sm font-semibold">Scheme Pool</h3>
                        <button v-if="canEditScenario" class="rounded-md p-0.5 text-muted-foreground transition-colors hover:bg-accent hover:text-foreground" @click="editingSchemes = !editingSchemes">
                            <Pencil class="size-3" />
                        </button>
                    </div>
                    <div v-if="editingSchemes" class="mb-2 grid gap-2 sm:grid-cols-3">
                        <div v-for="(schemeId, index) in editSchemePool" :key="'edit-' + index">
                            <Select :model-value="String(schemeId)" @update:model-value="(v: string) => setScheme(index, v)">
                                <SelectTrigger class="w-full"><SelectValue placeholder="Select Scheme" /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="s in availableSchemes(index)" :key="s.id" :value="String(s.id)">{{ s.name }}</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>
                    <div class="grid gap-2 sm:grid-cols-3">
                        <Card
                            v-for="scheme in schemes"
                            :key="scheme.id"
                            class="cursor-pointer transition-all hover:-translate-y-0.5 hover:shadow-md"
                            @click="openSchemeDrawer(scheme)"
                        >
                            <CardContent class="p-3">
                                <div class="text-sm font-medium">{{ scheme.name }}</div>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>

            <!-- Players (hidden during gameplay — shown in Game tab) -->
            <h3 v-if="game.status !== 'in_progress' && game.status !== 'completed' && game.status !== 'abandoned'" class="mb-3 text-lg font-semibold">Players</h3>
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
                                    :class="isUserOnline(player.user.id) ? 'fill-green-500 text-green-500' : 'fill-muted-foreground/30 text-muted-foreground/30'"
                                />
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2">
                                    <!-- Solo opponent: editable name -->
                                    <template v-if="isSolo && !isObserver && !player.user && game.status !== 'completed' && game.status !== 'abandoned'">
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
                                    <template v-if="isSolo || player.user?.id === currentUserId || game.status === 'scheme_select' || game.status === 'in_progress' || game.status === 'completed'">
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
            <Card v-if="game.status === 'faction_select'" class="mb-6" :class="isOpponentSetupPhase ? 'border-amber-500/40 bg-amber-500/5 dark:bg-amber-500/5' : ''">
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
                                <Button :disabled="submitting" @click="postSetup(route('games.setup.faction', game.uuid), { faction: selectedFaction, slot: 1 })">
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
                            <h2 class="mb-1 font-semibold">Select Opponent's Faction <Badge variant="outline" class="ml-1 border-amber-500/50 text-[10px] text-amber-600 dark:text-amber-400">Opponent</Badge></h2>
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
                                <Button :disabled="submitting" @click="postSetup(route('games.setup.faction', game.uuid), { faction: selectedFaction })">
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
            <Card v-if="game.status === 'master_select'" class="mb-6" :class="isOpponentSetupPhase ? 'border-amber-500/40 bg-amber-500/5 dark:bg-amber-500/5' : ''">
                <CardContent class="p-4 sm:p-6">
                    <h2 class="mb-1 font-semibold">
                        {{ isSolo && myStepDone('master') ? "Select Opponent's Master" : 'Select Your Master' }}
                        <Badge v-if="isOpponentSetupPhase" variant="outline" class="ml-1 border-amber-500/50 text-[10px] text-amber-600 dark:text-amber-400">Opponent</Badge>
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
                                        <img :src="'/storage/' + master.front_image" :alt="master.name" class="size-16 object-cover object-top" loading="lazy" decoding="async" />
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
            <Card v-if="game.status === 'crew_select'" class="mb-6" :class="isOpponentSetupPhase ? 'border-amber-500/40 bg-amber-500/5 dark:bg-amber-500/5' : ''">
                <CardContent class="p-4 sm:p-6">
                    <h2 class="mb-1 font-semibold">
                        {{ isSolo && myStepDone('crew') ? "Opponent's Crew" : 'Select Your Crew' }}
                        <Badge v-if="isOpponentSetupPhase" variant="outline" class="ml-1 border-amber-500/50 text-[10px] text-amber-600 dark:text-amber-400">Opponent</Badge>
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
                                            <p class="truncate text-sm font-medium">{{ crew.name }}</p>
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
                                                <span class="font-medium text-foreground" :class="crew.ook_count >= 2 ? 'text-amber-600 dark:text-amber-400' : ''">
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
                                                @click="postSetup(route('games.setup.crew', game.uuid), { crew_build_id: crew.id, ...(isSolo ? { slot: 1 } : {}) })"
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
                        <div class="py-4 text-center text-sm text-muted-foreground">
                            <Check class="inline size-5 text-green-500" /> Crew selected
                        </div>
                    </template>

                    <!-- Solo: opponent crew (optional) -->
                    <template v-else-if="isSolo && myStepDone('crew') && !opponentStepDone('crew')">
                        <div class="mb-3 text-center text-sm text-muted-foreground">
                            <Check class="inline size-4 text-green-500" /> Your crew selected
                        </div>
                        <p class="mb-4 text-xs text-muted-foreground">
                            Optionally select a saved crew for <strong class="text-foreground">{{ opponentPlayer?.master_name?.split(',')[0] }}</strong>, <Link :href="newOpponentCrewUrl" class="text-primary underline">Create a new crew</Link>, or skip to track points only.
                        </p>
                        <div v-if="opponentTitleOptions.length > 1" class="mb-4 flex flex-wrap items-center gap-1.5">
                            <span class="text-[11px] text-muted-foreground">Filter:</span>
                            <button
                                class="rounded-md border px-2 py-0.5 text-[11px] transition-colors"
                                :class="!opponentFilterTitleId ? 'border-primary bg-primary/10 font-medium' : 'hover:bg-muted'"
                                @click="opponentFilterTitleId = null"
                            >All</button>
                            <button
                                v-for="title in opponentTitleOptions"
                                :key="title.id"
                                class="rounded-md border px-2 py-0.5 text-[11px] transition-colors"
                                :class="opponentFilterTitleId === title.id ? 'border-primary bg-primary/10 font-medium' : 'hover:bg-muted'"
                                @click="opponentFilterTitleId = title.id"
                            >{{ title.title || title.display_name }}</button>
                        </div>
                        <div v-if="opponentMatchingCrews.length" class="mb-3 grid gap-2.5 sm:grid-cols-2">
                            <div v-for="crew in opponentMatchingCrews" :key="crew.id">
                                <Card
                                    class="transition-all duration-200"
                                    :class="[
                                        expandedOpponentCrewId === crew.id ? 'shadow-md ring-1 ring-primary/50' : 'hover:-translate-y-0.5 hover:shadow-md',
                                        crew.is_over_budget ? 'border-destructive/50' : '',
                                    ]"
                                >
                                    <CardContent
                                        class="flex cursor-pointer items-start gap-3 p-3"
                                        @click="expandedOpponentCrewId = expandedOpponentCrewId === crew.id ? null : crew.id"
                                    >
                                        <FactionLogo :faction="crew.faction" class-name="size-7 shrink-0 mt-0.5" />
                                        <div class="min-w-0 flex-1">
                                            <p class="truncate text-sm font-medium">{{ crew.name }}</p>
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
                                                <span class="font-medium text-foreground" :class="crew.ook_count >= 2 ? 'text-amber-600 dark:text-amber-400' : ''">
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
                                                @click="postSetup(route('games.setup.crew', game.uuid), { crew_build_id: crew.id, slot: 2 })"
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
                        <Button v-else variant="outline" class="w-full" @click="skipOpponentCrew">
                            Skip Opponent Crew
                        </Button>
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
                                        <button class="text-sm font-medium text-primary hover:underline" @click="openSchemeDrawer(scheme)">{{ scheme.name }}</button>
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
                                        <select
                                            v-model="pendingSchemeMarker"
                                            class="mt-0.5 w-full rounded border bg-background px-2 py-1 text-xs"
                                        >
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
                                    >{{ member.display_name }}</span>
                                    <Badge v-if="member.hiring_category && member.hiring_category !== 'leader' && member.hiring_category !== 'totem'" :class="categoryColor(member.hiring_category)" class="shrink-0 px-1 py-0 text-[9px]">
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
                        <div v-else class="rounded-md border border-dashed p-3 text-center text-xs text-muted-foreground">
                            No crew selected
                        </div>
                    </div>
                </div>
            </template>

            <!-- ═══ IN PROGRESS ═══ -->
            <template v-if="game.status === 'in_progress'">
                <!-- Mobile: Tab switcher -->
                <div class="mb-4 lg:hidden">
                    <Tabs v-model="gameplayTab">
                        <TabsList class="grid w-full grid-cols-3">
                            <TabsTrigger value="scenario">Game</TabsTrigger>
                            <TabsTrigger value="my-crew">{{ isObserver ? playerName(myPlayer) : 'My Crew' }}</TabsTrigger>
                            <TabsTrigger value="opponent">{{ playerName(opponent) }}</TabsTrigger>
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
                    <div v-if="turnBanner" class="mb-4 overflow-hidden rounded-lg border border-amber-500/40 bg-gradient-to-r from-amber-500/20 via-amber-400/10 to-amber-500/20 py-3 text-center">
                        <div class="text-lg font-bold text-amber-600 dark:text-amber-400">Turn {{ game.current_turn }} Started</div>
                    </div>
                </Transition>

                <!-- Desktop: 3-column grid / Mobile: tab content -->
                <div class="grid gap-4 lg:grid-cols-3">
                    <!-- Column 1: Scenario Info -->
                    <div :class="gameplayTab !== 'scenario' ? 'hidden lg:block' : ''">
                        <Card>
                            <CardContent class="space-y-4 p-4">
                                <div class="flex items-center justify-between">
                                    <div class="w-8"></div>
                                    <div class="text-center text-2xl font-bold">Turn {{ game.current_turn }} <span class="text-base font-normal text-muted-foreground">/ {{ game.max_turns }}</span></div>
                                    <DropdownMenu>
                                        <DropdownMenuTrigger as-child>
                                            <button class="rounded p-1 text-muted-foreground hover:bg-muted hover:text-foreground">
                                                <EllipsisVertical class="size-4" />
                                            </button>
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent align="end" class="w-44">
                                            <DropdownMenuItem v-if="!isObserver && !myPlayer?.is_game_complete" class="cursor-pointer text-xs" @click="completeDialogOpen = true">
                                                <Check class="mr-2 size-3.5" /> Mark Game Complete
                                            </DropdownMenuItem>
                                            <DropdownMenuItem v-if="!isObserver && isCreator" class="cursor-pointer text-xs" @click="toggleObservable">
                                                <Eye class="mr-2 size-3.5" /> {{ game.is_observable ? 'Disable' : 'Enable' }} Spectating
                                            </DropdownMenuItem>
                                            <DropdownMenuItem v-if="game.is_observable" class="cursor-pointer text-xs" @click="openQR(route('games.observe', game.uuid), 'Spectate Link')">
                                                <QrCode class="mr-2 size-4" /> QR Code
                                            </DropdownMenuItem>
                                            <DropdownMenuItem v-if="game.is_observable" class="cursor-pointer text-xs" @click="copyObserveLink">
                                                <Copy class="mr-2 size-3.5" /> {{ observeLinkCopied ? 'Link Copied!' : 'Copy Spectate Link' }}
                                            </DropdownMenuItem>
                                            <DropdownMenuSeparator v-if="!isObserver" />
                                            <DropdownMenuItem v-if="!isObserver" class="cursor-pointer text-xs text-destructive focus:text-destructive" @click="abandonDialogOpen = true">
                                                <Skull class="mr-2 size-3.5" /> Abandon Game
                                            </DropdownMenuItem>
                                        </DropdownMenuContent>
                                    </DropdownMenu>
                                </div>

                                <!-- Scores -->
                                <div class="grid grid-cols-2 gap-2">
                                    <div v-for="player in game.players" :key="'score-' + player.id" class="rounded-lg border p-3 text-center">
                                        <div class="flex items-center justify-center gap-1.5">
                                            <FactionLogo v-if="player.faction" :faction="player.faction" class-name="size-4" />
                                            <span class="text-xs font-medium">{{ playerName(player) }}</span>
                                        </div>
                                        <div class="mt-1 text-2xl font-bold">{{ player.total_points }}</div>
                                        <Badge v-if="player.role" variant="outline" class="mt-1 px-1 py-0 text-[9px] capitalize">{{ player.role }}</Badge>
                                    </div>
                                </div>

                                <!-- Deployment & Strategy -->
                                <div v-if="deployment" class="cursor-pointer rounded-lg border p-2 text-center transition-colors hover:bg-muted/50" @click="deploymentDrawerOpen = true">
                                    <div class="text-[10px] uppercase text-muted-foreground">Deployment</div>
                                    <div class="text-sm font-medium">{{ deployment.label }}</div>
                                </div>
                                <div v-if="game.strategy" class="cursor-pointer rounded-lg border p-2 text-center transition-colors hover:bg-muted/50" @click="strategyDrawerOpen = true">
                                    <div class="text-[10px] uppercase text-muted-foreground">Strategy</div>
                                    <div class="text-sm font-medium">{{ game.strategy.name }}</div>
                                </div>

                                <!-- Schemes display -->
                                <template v-if="isObserver">
                                    <!-- Observer: show possible scheme pool per player -->
                                    <details v-for="player in game.players" :key="'obs-scheme-' + player.id" class="rounded-lg border">
                                        <summary class="cursor-pointer px-3 py-2 text-center">
                                            <span class="text-[10px] uppercase text-muted-foreground">{{ playerName(player) }}'s Possible Schemes</span>
                                        </summary>
                                        <div class="space-y-1 border-t px-3 py-2">
                                            <template v-if="observer_scheme_intel?.[player.slot]">
                                                <div
                                                    v-for="scheme in observer_scheme_intel[player.slot].possible_schemes"
                                                    :key="'obs-ps-' + player.id + '-' + scheme.id"
                                                    class="flex items-center gap-2 rounded px-2 py-1 text-xs transition-colors hover:bg-muted/50"
                                                    :class="scheme.id === observer_scheme_intel[player.slot].revealed_scheme_id ? 'bg-green-500/10 border border-green-500/30' : ''"
                                                >
                                                    <button class="min-w-0 flex-1 text-left font-medium hover:text-primary" @click="openSchemeDrawer(scheme)">
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
                                            <button class="rounded p-0.5 text-muted-foreground hover:text-foreground" @click="schemeHidden = !schemeHidden">
                                                <component :is="schemeHidden ? EyeOff : Eye" class="size-3" />
                                            </button>
                                        </div>
                                        <div v-if="schemeHidden" class="text-center text-sm font-medium text-muted-foreground">Hidden</div>
                                        <template v-else>
                                            <button class="w-full text-center text-sm font-medium hover:text-primary" @click="openSchemeDrawer(findScheme(myDisplaySchemeId)!)">
                                                {{ findScheme(myDisplaySchemeId)?.name }}
                                            </button>

                                            <!-- Scheme notes -->
                                            <div class="mt-2 space-y-1.5 border-t border-primary/20 pt-2">
                                                <!-- Prerequisite hint (only before locking) -->
                                                <div v-if="!schemeNotesLocked && findScheme(myDisplaySchemeId)?.prerequisite" class="text-[10px] italic text-muted-foreground">
                                                    {{ findScheme(myDisplaySchemeId)?.prerequisite }}
                                                </div>

                                                <!-- Model selection -->
                                                <div v-if="schemeModelReq">
                                                    <label class="text-[10px] uppercase text-muted-foreground">{{ modelReqLabel }}</label>
                                                    <template v-if="schemeNotesLocked">
                                                        <div class="mt-0.5 rounded border bg-muted/50 px-2 py-1 text-xs font-medium">{{ schemeSelectedModel || 'Not selected' }}</div>
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
                                                        <div class="mt-0.5 rounded border bg-muted/50 px-2 py-1 text-xs font-medium">{{ schemeSelectedMarker || 'Not selected' }}</div>
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
                                                        <div class="mt-0.5 rounded border bg-muted/50 px-2 py-1 text-xs font-medium">{{ schemeTerrainNote || 'Not set' }}</div>
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
                                        <div v-if="opponent_scheme_intel.last_revealed" class="rounded-md border border-amber-500/30 bg-amber-500/5 p-2">
                                            <div class="flex items-center justify-between">
                                                <div class="text-[10px] uppercase text-muted-foreground">Last Revealed (Turn {{ opponent_scheme_intel.last_revealed.turn_number }})</div>
                                                <Badge v-if="opponent_scheme_intel.last_revealed.scored" variant="outline" class="border-green-500/50 px-1 py-0 text-[9px] text-green-600 dark:text-green-400">Scored</Badge>
                                                <Badge v-else variant="outline" class="px-1 py-0 text-[9px]">Not Scored</Badge>
                                            </div>
                                            <div
                                                class="mt-1 cursor-pointer text-sm font-medium hover:text-primary"
                                                @click="openSchemeDrawer(findScheme(opponent_scheme_intel.last_revealed.id)!)"
                                            >
                                                {{ opponent_scheme_intel.last_revealed.name }}
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
                                                    :class="opponent_scheme_intel.last_revealed?.id === scheme.id ? 'border-amber-500/20 bg-amber-500/5' : ''"
                                                    @click="openSchemeDrawer(scheme)"
                                                >
                                                    <div class="flex items-center justify-between">
                                                        <span class="text-xs font-medium">{{ scheme.name }}</span>
                                                        <Badge v-if="opponent_scheme_intel.last_revealed?.id === scheme.id" variant="outline" class="px-1 py-0 text-[9px]">Kept</Badge>
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
                                                        <button class="font-medium hover:text-primary" @click="openSchemeDrawer(findScheme(entry.scheme_id)!)">
                                                            {{ entry.scheme_name }}
                                                        </button>
                                                    </div>
                                                    <Badge
                                                        v-if="entry.scored"
                                                        variant="outline"
                                                        class="border-green-500/50 px-1 py-0 text-[8px] text-green-600 dark:text-green-400"
                                                    >Scored</Badge>
                                                    <Badge
                                                        v-else
                                                        variant="outline"
                                                        class="px-1 py-0 text-[8px]"
                                                    >Held</Badge>
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
                                        <template v-if="isSolo">
                                            <Check class="mr-1 inline size-3 text-green-500" /> Your turn submitted
                                        </template>
                                        <template v-else>
                                            <Loader2 class="mr-1 inline size-3 animate-spin" /> Waiting for opponent...
                                        </template>
                                    </div>
                                </template>
                                <template v-else>
                                    <div class="space-y-3 border-t pt-3">
                                        <div class="text-xs font-semibold">End of Turn {{ game.current_turn }}</div>

                                        <!-- Strategy points (1/turn + 1 bonus once/game) -->
                                        <div class="flex items-center justify-between">
                                            <div>
                                                <span class="text-xs text-muted-foreground">Strategy VP</span>
                                                <span v-if="!myStrategyBonusUsed && strategyPoints < 2" class="ml-1 text-[9px] text-amber-500">+1 bonus available</span>
                                                <span v-if="myStrategyBonusUsed" class="ml-1 text-[9px] text-muted-foreground/50">bonus used</span>
                                            </div>
                                            <div class="flex items-center gap-1.5">
                                                <button class="rounded border p-0.5 hover:bg-muted" @click="strategyPoints = Math.max(0, strategyPoints - 1)"><Minus class="size-3.5" /></button>
                                                <span class="w-6 text-center font-bold">{{ strategyPoints }}</span>
                                                <button class="rounded border p-0.5 hover:bg-muted" :disabled="strategyPoints >= maxStrategyThisTurn" :class="strategyPoints >= maxStrategyThisTurn ? 'opacity-30' : ''" @click="strategyPoints = Math.min(maxStrategyThisTurn, strategyPoints + 1)"><Plus class="size-3.5" /></button>
                                            </div>
                                        </div>

                                        <!-- Scheme points (max 2/turn, max 6 total) -->
                                        <div class="flex items-center justify-between">
                                            <div>
                                                <span class="text-xs text-muted-foreground">Scheme VP</span>
                                                <span class="ml-1 text-[9px] text-muted-foreground/50">{{ myTotalSchemeScored }}/6 total</span>
                                            </div>
                                            <div class="flex items-center gap-1.5">
                                                <button class="rounded border p-0.5 hover:bg-muted" @click="schemePoints = Math.max(0, schemePoints - 1)"><Minus class="size-3.5" /></button>
                                                <span class="w-6 text-center font-bold">{{ schemePoints }}</span>
                                                <button class="rounded border p-0.5 hover:bg-muted" :disabled="schemePoints >= maxSchemeThisTurn" :class="schemePoints >= maxSchemeThisTurn ? 'opacity-30' : ''" @click="schemePoints = Math.min(maxSchemeThisTurn, schemePoints + 1)"><Plus class="size-3.5" /></button>
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
                                                <div v-if="!currentSchemeScored && next_schemes.length" class="py-0.5 text-center text-[9px] text-muted-foreground">— or discard &amp; switch to —</div>
                                                <!-- Follow-up schemes -->
                                                <template v-if="next_schemes.length">
                                                    <div v-for="scheme in next_schemes" :key="scheme.id" class="flex items-center gap-1">
                                                        <button
                                                            class="min-w-0 flex-1 rounded-md border px-2 py-1.5 text-left text-xs transition-colors"
                                                            :class="nextSchemeId === scheme.id ? 'border-primary bg-primary/10 font-medium' : 'hover:bg-muted'"
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
                                            <div v-if="nextSchemeId && findScheme(nextSchemeId)?.requirements?.length" class="mt-2 rounded border border-primary/20 bg-primary/5 p-2 space-y-1.5">
                                                <div class="text-[10px] font-medium uppercase text-muted-foreground">{{ findScheme(nextSchemeId)?.name }} — Setup</div>
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
                                                </div>
                                                <!-- Marker -->
                                                <div v-if="findScheme(nextSchemeId)?.requirements?.some((r: any) => r.type === 'select_marker')">
                                                    <label class="text-[10px] uppercase text-muted-foreground">Target Marker</label>
                                                    <select v-model="nextSchemeMarker" class="mt-0.5 w-full rounded border bg-background px-2 py-1 text-xs">
                                                        <option value="">Select...</option>
                                                        <option v-for="m in all_markers" :key="m.id" :value="m.name">{{ m.name }}</option>
                                                    </select>
                                                </div>
                                                <!-- Terrain -->
                                                <div v-if="findScheme(nextSchemeId)?.requirements?.some((r: any) => r.type === 'terrain_note')">
                                                    <label class="text-[10px] uppercase text-muted-foreground">Terrain Note</label>
                                                    <input v-model="nextSchemeTerrain" type="text" placeholder="e.g. the building on the left..." class="mt-0.5 w-full rounded border bg-background px-2 py-1 text-xs" />
                                                </div>
                                            </div>
                                        </div>

                                        <Button class="w-full" size="sm" :disabled="scoringTurn || (!isLastTurn && (currentSchemeScored || nextSchemeId) && !nextSchemeId && next_schemes.length > 0)" @click="submitTurnScore">
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
                                            <div class="py-2 text-center text-xs text-green-600"><Check class="mr-1 inline size-3" /> Opponent turn submitted</div>
                                        </template>
                                        <template v-else>
                                            <div class="flex items-center justify-between">
                                                <div>
                                                    <span class="text-xs text-muted-foreground">Strategy VP</span>
                                                    <span v-if="!opponentStrategyBonusUsed && opponentStrategyPoints < 2" class="ml-1 text-[9px] text-amber-500">+1 bonus available</span>
                                                    <span v-if="opponentStrategyBonusUsed" class="ml-1 text-[9px] text-muted-foreground/50">bonus used</span>
                                                </div>
                                                <div class="flex items-center gap-1.5">
                                                    <button class="rounded border p-0.5 hover:bg-muted" @click="opponentStrategyPoints = Math.max(0, opponentStrategyPoints - 1)"><Minus class="size-3.5" /></button>
                                                    <span class="w-6 text-center font-bold">{{ opponentStrategyPoints }}</span>
                                                    <button class="rounded border p-0.5 hover:bg-muted" :disabled="opponentStrategyPoints >= opponentMaxStrategyThisTurn" :class="opponentStrategyPoints >= opponentMaxStrategyThisTurn ? 'opacity-30' : ''" @click="opponentStrategyPoints = Math.min(opponentMaxStrategyThisTurn, opponentStrategyPoints + 1)"><Plus class="size-3.5" /></button>
                                                </div>
                                            </div>
                                            <div class="flex items-center justify-between">
                                                <div>
                                                    <span class="text-xs text-muted-foreground">Scheme VP</span>
                                                    <span class="ml-1 text-[9px] text-muted-foreground/50">{{ opponentTotalSchemeScored }}/6 total</span>
                                                </div>
                                                <div class="flex items-center gap-1.5">
                                                    <button class="rounded border p-0.5 hover:bg-muted" @click="opponentSchemePoints = Math.max(0, opponentSchemePoints - 1)"><Minus class="size-3.5" /></button>
                                                    <span class="w-6 text-center font-bold">{{ opponentSchemePoints }}</span>
                                                    <button class="rounded border p-0.5 hover:bg-muted" :disabled="opponentSchemePoints >= opponentMaxSchemeThisTurn" :class="opponentSchemePoints >= opponentMaxSchemeThisTurn ? 'opacity-30' : ''" @click="opponentSchemePoints = Math.min(opponentMaxSchemeThisTurn, opponentSchemePoints + 1)"><Plus class="size-3.5" /></button>
                                                </div>
                                            </div>
                                            <Button
                                                class="w-full"
                                                size="sm"
                                                :disabled="scoringOpponentTurn"
                                                @click="submitOpponentTurnScore"
                                            >
                                                <Loader2 v-if="scoringOpponentTurn" class="mr-2 size-4 animate-spin" />
                                                Submit Opponent ({{ opponentStrategyPoints + opponentSchemePoints }} VP)
                                            </Button>
                                        </template>
                                    </div>
                                </template>

                                <!-- Game complete status -->
                                <div v-if="!isObserver && (myPlayer?.is_game_complete || opponent?.is_game_complete)" class="border-t pt-3 text-center text-xs">
                                    <div v-if="myPlayer?.is_game_complete && opponent?.is_game_complete" class="text-green-600 dark:text-green-400">
                                        <Check class="mr-1 inline size-3" /> Both players ready — finalizing...
                                    </div>
                                    <div v-else-if="myPlayer?.is_game_complete" class="text-muted-foreground">
                                        <Check class="mr-1 inline size-3 text-green-500" /> Waiting for opponent to confirm...
                                        <Button variant="ghost" size="sm" class="mt-2 w-full text-xs text-destructive hover:text-destructive" @click="cancelGameComplete">
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
                    <div :class="gameplayTab !== 'my-crew' ? 'hidden lg:block' : ''">
                        <div class="mb-1 flex items-center justify-between">
                            <h3 class="text-sm font-semibold">{{ isObserver ? playerName(myPlayer) : 'Your Crew' }}</h3>
                            <div class="flex items-center gap-1">
                                <button v-if="!isObserver" class="rounded p-0.5 hover:bg-muted" @click="updateSoulstonePool(-1)"><Minus class="size-3" /></button>
                                <span class="flex min-w-[3rem] items-center justify-center gap-0.5 text-xs font-bold">
                                    {{ myPlayer?.soulstone_pool ?? 0 }}<GameIcon type="soulstone" class-name="h-3 inline-block" />
                                </span>
                                <button v-if="!isObserver" class="rounded p-0.5 hover:bg-muted" @click="updateSoulstonePool(1)"><Plus class="size-3" /></button>
                            </div>
                        </div>
                        <div class="mb-2 text-[10px] text-muted-foreground">
                            Activations: <span class="font-medium text-foreground">{{ myCrewMembers.filter((m: any) => !m.is_activated).length }}</span>/<span>{{ myCrewMembers.length }}</span> remaining
                        </div>
                        <!-- Reference Upgrades -->
                        <div v-if="myCrewUpgrades.length" class="mb-2 space-y-1">
                            <div
                                v-for="upgrade in myCrewUpgrades"
                                :key="upgrade.id"
                                class="flex items-center gap-1.5 rounded-md border px-2 py-1 text-xs transition-colors"
                                :class="[
                                    myActiveUpgradeId === upgrade.id ? 'border-amber-500/50 bg-amber-500/10' : 'border-border/50 bg-accent/30 opacity-60',
                                    upgrade.front_image ? 'cursor-pointer hover:bg-accent' : '',
                                ]"
                                @click="openUpgradePreview(upgrade)"
                            >
                                <Star class="size-3 shrink-0" :class="myActiveUpgradeId === upgrade.id ? 'fill-amber-500 text-amber-500' : 'text-muted-foreground'" />
                                <span class="font-semibold">{{ upgrade.name }}</span>
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
                                <div class="flex items-start justify-between gap-1">
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center gap-1">
                                            <template v-if="!isObserver">
                                                <button class="shrink-0 rounded p-0.5 hover:bg-white/20" @click="toggleActivated(member)" :title="member.is_activated ? 'Mark unactivated' : 'Mark activated'">
                                                    <Check v-if="member.is_activated" class="size-3.5 text-green-400 drop-shadow-[0_1px_2px_rgba(0,0,0,0.5)]" />
                                                    <Circle v-else class="size-3.5 text-white/50 drop-shadow-[0_1px_2px_rgba(0,0,0,0.5)]" />
                                                </button>
                                                <button class="shrink-0 rounded bg-black/30 p-0.5 text-amber-200 hover:bg-black/50" title="Upgrades" @click.stop="openUpgradeDialog(member)"><ArrowUpCircle class="size-3" /></button>
                                                <button class="shrink-0 rounded bg-black/30 p-0.5 text-cyan-200 hover:bg-black/50" title="Tokens" @click.stop="openTokenDialog(member)"><Puzzle class="size-3" /></button>
                                                <button class="shrink-0 rounded bg-black/30 p-0.5 text-blue-200 hover:bg-black/50" title="Replace" @click.stop="openReplace(member)"><Replace class="size-3" /></button>
                                            </template>
                                            <template v-else>
                                                <Check v-if="member.is_activated" class="size-3.5 shrink-0 text-green-400 drop-shadow-[0_1px_2px_rgba(0,0,0,0.5)]" />
                                                <Circle v-else class="size-3.5 shrink-0 text-white/50 drop-shadow-[0_1px_2px_rgba(0,0,0,0.5)]" />
                                            </template>
                                            <span class="cursor-pointer truncate text-xs font-semibold hover:underline sm:text-sm" @click="openMemberPreview(member)">{{ member.display_name }}</span>
                                        </div>
                                        <!-- Health pips -->
                                        <div class="mt-0.5 flex gap-0.5 pl-6">
                                            <div
                                                v-for="pip in member.max_health"
                                                :key="'hp-' + pip"
                                                class="size-2 rounded-sm"
                                                :class="pip <= member.current_health
                                                    ? (member.current_health <= Math.ceil(member.max_health / 2) ? 'bg-red-400/90' : 'bg-white/60')
                                                    : 'bg-black/30 ring-1 ring-inset ring-white/10'"
                                            />
                                        </div>
                                    </div>
                                    <template v-if="!isObserver">
                                        <div class="flex shrink-0 items-center gap-0.5">
                                            <button class="rounded bg-black/20 p-0.5 hover:bg-black/40" @click="updateHealth(member, -1)"><Minus class="size-3" /></button>
                                            <span class="flex min-w-[3rem] items-center justify-center gap-0.5 text-xs font-bold">
                                                <Heart class="size-3" :class="member.current_health <= Math.ceil(member.max_health / 2) ? 'text-red-300' : ''" />
                                                {{ member.current_health }}/{{ member.max_health }}
                                            </span>
                                            <button class="rounded bg-black/20 p-0.5 hover:bg-black/40" @click="updateHealth(member, 1)"><Plus class="size-3" /></button>
                                            <button class="ml-0.5 rounded bg-black/30 p-0.5 text-red-300 hover:bg-black/50" @click="killMember(member)"><Skull class="size-3" /></button>
                                        </div>
                                    </template>
                                    <span v-else class="flex shrink-0 min-w-[3rem] items-center justify-center gap-0.5 text-xs font-bold">
                                        <Heart class="size-3" :class="member.current_health <= Math.ceil(member.max_health / 2) ? 'text-red-300' : ''" />
                                        {{ member.current_health }}/{{ member.max_health }}
                                    </span>
                                </div>
                                <!-- Token badges -->
                                <div v-if="member.attached_tokens?.length" class="mt-1 flex flex-wrap gap-1">
                                    <Badge
                                        v-for="token in member.attached_tokens"
                                        :key="token.id"
                                        variant="secondary"
                                        class="group cursor-pointer gap-0.5 border border-cyan-500/50 bg-cyan-900/60 px-1.5 py-0.5 text-[10px] font-medium text-cyan-200 transition-colors hover:bg-cyan-800/70"
                                        @click="openTokenInfo(token.id, member)"
                                    >
                                        {{ token.name }}
                                        <button v-if="!isObserver" class="ml-0.5 rounded-full text-cyan-300/60 hover:text-white" @click.stop="quickRemoveToken(member, token.id)">
                                            <Minus class="size-2.5" />
                                        </button>
                                    </Badge>
                                </div>
                                <!-- Attached upgrades -->
                                <div v-if="member.attached_upgrades?.length" class="mt-1 space-y-0.5 pl-3">
                                    <div
                                        v-for="upgrade in member.attached_upgrades"
                                        :key="'au-' + upgrade.id"
                                        class="flex items-center gap-1.5 rounded bg-black/20 px-1.5 py-0.5 text-[10px]"
                                        :class="upgrade.front_image ? 'cursor-pointer hover:bg-black/30' : ''"
                                        @click="openAttachedUpgradePreview(upgrade)"
                                    >
                                        <ArrowUpCircle class="size-2.5 shrink-0 text-amber-300" />
                                        <span class="min-w-0 flex-1 truncate font-medium">{{ upgrade.name }}</span>
                                        <button v-if="!isObserver" class="shrink-0 rounded-full text-red-300/60 hover:text-red-300" @click.stop="quickRemoveUpgrade(member, upgrade.id)">
                                            <Minus class="size-2.5" />
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div
                                v-for="member in myKilledMembers"
                                :key="'killed-' + member.id"
                                class="flex items-center justify-between rounded-md bg-muted/50 px-2 py-1 text-xs text-muted-foreground line-through opacity-50"
                            >
                                <span class="cursor-pointer hover:underline" @click="openMemberPreview(member)">{{ member.display_name }}</span>
                                <button v-if="!isObserver" class="rounded p-0.5 text-green-600 hover:bg-green-500/20" @click="reviveMember(member)"><RotateCcw class="size-3" /></button>
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
                    <div :class="gameplayTab !== 'opponent' ? 'hidden lg:block' : ''">
                        <div class="mb-1 flex items-center justify-between">
                            <h3 class="text-sm font-semibold">{{ playerName(opponent) }}</h3>
                            <div class="flex items-center gap-1">
                                <template v-if="isSolo && !isObserver">
                                    <button class="rounded p-0.5 hover:bg-muted" @click="updateOpponentSoulstonePool(-1)"><Minus class="size-3" /></button>
                                </template>
                                <span class="flex min-w-[3rem] items-center justify-center gap-0.5 text-xs font-bold" :class="!isSolo || isObserver ? 'text-muted-foreground' : ''">
                                    {{ opponent?.soulstone_pool ?? 0 }}<GameIcon type="soulstone" class-name="h-3 inline-block" />
                                </span>
                                <template v-if="isSolo && !isObserver">
                                    <button class="rounded p-0.5 hover:bg-muted" @click="updateOpponentSoulstonePool(1)"><Plus class="size-3" /></button>
                                </template>
                            </div>
                        </div>
                        <div v-if="opponentCrewMembers.length" class="mb-2 text-[10px] text-muted-foreground">
                            Activations: <span class="font-medium text-foreground">{{ opponentCrewMembers.filter((m: any) => !m.is_activated).length }}</span>/<span>{{ opponentCrewMembers.length }}</span> remaining
                        </div>


                        <!-- Opponent faction/master info (when no crew) -->
                        <div v-if="isSolo && opponent?.crew_skipped && !opponentCrewMembers.length" class="mb-3 flex items-center gap-2 rounded-md border border-dashed p-2">
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
                                class="flex items-center gap-1.5 rounded-md border px-2 py-1 text-xs transition-colors"
                                :class="[
                                    opponentActiveUpgradeId === upgrade.id ? 'border-amber-500/50 bg-amber-500/10' : 'border-border/50 bg-accent/30 opacity-60',
                                    upgrade.front_image ? 'cursor-pointer hover:bg-accent' : '',
                                ]"
                                @click="openUpgradePreview(upgrade)"
                            >
                                <Star class="size-3 shrink-0" :class="opponentActiveUpgradeId === upgrade.id ? 'fill-amber-500 text-amber-500' : 'text-muted-foreground'" />
                                <span class="font-semibold">{{ upgrade.name }}</span>
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
                                <div class="flex items-start justify-between gap-1">
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center gap-1">
                                            <!-- Solo: full activation toggle; Normal/Observer: read-only indicator -->
                                            <template v-if="isSolo && !isObserver">
                                                <button class="shrink-0 rounded p-0.5 hover:bg-white/20" @click="toggleActivated(member)">
                                                    <Check v-if="member.is_activated" class="size-3.5 text-green-300" />
                                                    <Circle v-else class="size-3.5 text-white/30" />
                                                </button>
                                                <button class="shrink-0 rounded bg-black/30 p-0.5 text-amber-200 hover:bg-black/50" title="Upgrades" @click.stop="openUpgradeDialog(member)"><ArrowUpCircle class="size-3" /></button>
                                                <button class="shrink-0 rounded bg-black/30 p-0.5 text-cyan-200 hover:bg-black/50" title="Tokens" @click.stop="openTokenDialog(member)"><Puzzle class="size-3" /></button>
                                                <button class="shrink-0 rounded bg-black/30 p-0.5 text-blue-200 hover:bg-black/50" title="Replace" @click.stop="openReplace(member)"><Replace class="size-3" /></button>
                                            </template>
                                            <template v-else>
                                                <Check v-if="member.is_activated" class="size-3.5 shrink-0 text-green-400 drop-shadow-[0_1px_2px_rgba(0,0,0,0.5)]" />
                                                <Circle v-else class="size-3.5 shrink-0 text-white/50 drop-shadow-[0_1px_2px_rgba(0,0,0,0.5)]" />
                                            </template>
                                            <span class="cursor-pointer truncate text-xs font-semibold hover:underline sm:text-sm" @click="openMemberPreview(member)">{{ member.display_name }}</span>
                                        </div>
                                        <!-- Health pips -->
                                        <div class="mt-0.5 flex gap-0.5" :class="isSolo ? 'pl-6' : 'pl-5'">
                                            <div
                                                v-for="pip in member.max_health"
                                                :key="'ohp-' + pip"
                                                class="size-2 rounded-sm"
                                                :class="pip <= member.current_health
                                                    ? (member.current_health <= Math.ceil(member.max_health / 2) ? 'bg-red-400/90' : 'bg-white/60')
                                                    : 'bg-black/30 ring-1 ring-inset ring-white/10'"
                                            />
                                        </div>
                                    </div>
                                    <!-- Solo: full health controls; Normal/Observer: read-only -->
                                    <div v-if="isSolo && !isObserver" class="flex shrink-0 items-center gap-0.5">
                                        <button class="rounded bg-black/20 p-0.5 hover:bg-black/40" @click="updateHealth(member, -1)"><Minus class="size-3" /></button>
                                        <span class="flex min-w-[3rem] items-center justify-center gap-0.5 text-xs font-bold">
                                            <Heart class="size-3" :class="member.current_health <= Math.ceil(member.max_health / 2) ? 'text-red-300' : ''" />
                                            {{ member.current_health }}/{{ member.max_health }}
                                        </span>
                                        <button class="rounded bg-black/20 p-0.5 hover:bg-black/40" @click="updateHealth(member, 1)"><Plus class="size-3" /></button>
                                        <button class="ml-0.5 rounded bg-black/30 p-0.5 text-red-300 hover:bg-black/50" @click="killMember(member)"><Skull class="size-3" /></button>
                                    </div>
                                    <span v-else class="flex shrink-0 min-w-[3rem] items-center justify-center gap-0.5 text-xs font-bold">
                                        <Heart class="size-3" :class="member.current_health <= Math.ceil(member.max_health / 2) ? 'text-red-300' : ''" />
                                        {{ member.current_health }}/{{ member.max_health }}
                                    </span>
                                </div>
                                <!-- Token badges -->
                                <div v-if="member.attached_tokens?.length" class="mt-1 flex flex-wrap gap-1">
                                    <Badge
                                        v-for="token in member.attached_tokens"
                                        :key="token.id"
                                        variant="secondary"
                                        class="cursor-pointer gap-0.5 border border-cyan-500/50 bg-cyan-900/60 px-1.5 py-0.5 text-[10px] font-medium text-cyan-200 transition-colors hover:bg-cyan-800/70"
                                        @click="openTokenInfo(token.id, member)"
                                    >
                                        {{ token.name }}
                                        <button v-if="isSolo && !isObserver" class="ml-0.5 rounded-full text-cyan-300/60 hover:text-white" @click.stop="quickRemoveToken(member, token.id)">
                                            <Minus class="size-2.5" />
                                        </button>
                                    </Badge>
                                </div>
                                <!-- Attached upgrades -->
                                <div v-if="member.attached_upgrades?.length" class="mt-1 space-y-0.5 pl-3">
                                    <div
                                        v-for="upgrade in member.attached_upgrades"
                                        :key="'oau-' + upgrade.id"
                                        class="flex items-center gap-1.5 rounded bg-black/20 px-1.5 py-0.5 text-[10px]"
                                        :class="upgrade.front_image ? 'cursor-pointer hover:bg-black/30' : ''"
                                        @click="openAttachedUpgradePreview(upgrade)"
                                    >
                                        <ArrowUpCircle class="size-2.5 shrink-0 text-amber-300" />
                                        <span class="min-w-0 flex-1 truncate font-medium">{{ upgrade.name }}</span>
                                        <button v-if="isSolo && !isObserver" class="shrink-0 rounded-full text-red-300/60 hover:text-red-300" @click.stop="quickRemoveUpgrade(member, upgrade.id)">
                                            <Minus class="size-2.5" />
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div
                                v-for="member in opponentKilledMembers"
                                :key="'killed-' + member.id"
                                class="flex items-center justify-between rounded-md bg-muted/50 px-2 py-1 text-xs text-muted-foreground line-through opacity-50"
                            >
                                <span class="cursor-pointer hover:underline" @click="openMemberPreview(member)">{{ member.display_name }}</span>
                                <button v-if="isSolo && !isObserver" class="rounded p-0.5 text-green-600 hover:bg-green-500/20" @click="reviveMember(member)"><RotateCcw class="size-3" /></button>
                            </div>
                        </div>
                        <!-- Solo: summon for opponent -->
                        <Button v-if="isSolo && !isObserver && opponentCrewMembers.length" variant="outline" size="sm" class="mt-2 w-full gap-1 text-xs" @click="openSummonForSlot(2)">
                            <Plus class="size-3" /> Summon
                        </Button>
                        <!-- Opponent Crew References -->
                        <details v-if="opponentCrewMembers.length" open class="mt-2 rounded-lg border" @toggle="($event.target as HTMLDetailsElement)?.open && toggleOpponentRefs()">
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
                            <div v-else-if="game.winner" class="text-lg font-bold text-amber-700 dark:text-amber-400">{{ game.winner.name }} Wins!</div>
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
                                <div class="mt-1 text-2xl font-bold" :class="(game.winner?.id === player.user?.id && game.winner) || (isSolo && game.winner_slot === player.slot) ? 'text-amber-600 dark:text-amber-400' : ''">
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
                        <div v-if="!starting_crews[player.slot]?.length" class="rounded-md border border-dashed p-3 text-center text-xs text-muted-foreground">
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
                                    <Badge v-if="member.hiring_category && member.hiring_category !== 'leader' && member.hiring_category !== 'totem'" :class="categoryColor(member.hiring_category)" class="shrink-0 px-1 py-0 text-[9px]">
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
                                                    +{{ (getPlayerTurn(player, turn)?.strategy_points ?? 0) + (getPlayerTurn(player, turn)?.scheme_points ?? 0) }}
                                                </span>
                                                <span class="text-muted-foreground">
                                                    ({{ player.turns?.filter((t: any) => t.turn_number <= turn).reduce((sum: number, t: any) => sum + (t.strategy_points ?? 0) + (t.scheme_points ?? 0), 0) }})
                                                </span>
                                            </span>
                                        </template>
                                    </div>
                                </div>
                                <ChevronDown class="size-3.5 shrink-0 text-muted-foreground transition-transform duration-200" :class="expandedTurn === turn ? 'rotate-180' : ''" />
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
                                        <div v-if="getTurnSchemeInfo(player, turn).schemeId" class="mt-2 rounded border border-dashed px-2 py-1 text-[10px]">
                                            <span class="text-muted-foreground">Scheme:</span>
                                            <button class="ml-1 font-medium hover:text-primary" @click="openSchemeDrawer(findScheme(getTurnSchemeInfo(player, turn).schemeId)!)">
                                                {{ findScheme(getTurnSchemeInfo(player, turn).schemeId)?.name }}
                                            </button>
                                            <Badge
                                                v-if="getTurnSchemeInfo(player, turn).held"
                                                variant="outline"
                                                class="ml-1 border-blue-500/50 px-1 py-0 text-[8px] text-blue-600 dark:text-blue-400"
                                            >Held</Badge>
                                            <Badge
                                                v-if="getPlayerTurn(player, turn)?.scheme_points > 0"
                                                variant="outline"
                                                class="ml-1 border-green-500/50 px-1 py-0 text-[8px] text-green-600 dark:text-green-400"
                                            >Scored</Badge>

                                            <!-- Scheme notes for this turn -->
                                            <template v-if="getPlayerTurn(player, turn)?.scheme_notes">
                                                <div v-if="getPlayerTurn(player, turn).scheme_notes.selected_model" class="mt-1 text-muted-foreground">
                                                    <span class="font-medium">Target:</span> {{ getPlayerTurn(player, turn).scheme_notes.selected_model }}
                                                </div>
                                                <div v-if="getPlayerTurn(player, turn).scheme_notes.selected_marker" class="mt-0.5 text-muted-foreground">
                                                    <span class="font-medium">Marker:</span> {{ getPlayerTurn(player, turn).scheme_notes.selected_marker }}
                                                </div>
                                                <div v-if="getPlayerTurn(player, turn).scheme_notes.terrain_note" class="mt-0.5 text-muted-foreground">
                                                    <span class="font-medium">Terrain:</span> {{ getPlayerTurn(player, turn).scheme_notes.terrain_note }}
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
                                                <div :key="'snap-' + turn + '-' + player.id + '-' + mIdx" v-for="(member, mIdx) in getPlayerTurn(player, turn).crew_snapshot">
                                                    <div
                                                        :class="factionBackground(member.faction ?? player.faction ?? '')"
                                                        class="flex items-center justify-between rounded px-1.5 py-0.5 text-[11px] text-white"
                                                        :style="member.is_killed ? 'opacity: 0.4; text-decoration: line-through' : ''"
                                                    >
                                                        <div class="flex min-w-0 items-center gap-1">
                                                            <span class="truncate font-medium">{{ member.display_name }}</span>
                                                            <Badge v-if="member.is_summoned" class="bg-cyan-400/20 px-0.5 py-0 text-[8px] text-cyan-200">S</Badge>
                                                        </div>
                                                        <div class="flex items-center gap-1">
                                                            <template v-if="member.attached_tokens?.length">
                                                                <div v-for="token in member.attached_tokens" :key="token.id" class="rounded bg-cyan-900/50 px-0.5 text-[8px] text-cyan-200">{{ token.name }}</div>
                                                            </template>
                                                            <span class="flex items-center gap-0.5 font-bold">
                                                                <Heart class="size-2.5" :class="member.is_killed ? 'text-red-400' : member.current_health <= Math.ceil(member.max_health / 2) ? 'text-red-300' : ''" />
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
                        <div v-if="!player.crew_members?.length && player.faction" class="flex items-center gap-2 rounded-md border border-dashed p-3">
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
                                    class="flex items-center gap-1.5 rounded-md border px-2 py-1 text-xs"
                                    :class="[
                                        player.crew_build?.crew_upgrade_id === upgrade.id ? 'border-amber-500/50 bg-amber-500/10' : 'border-border/50 bg-accent/30 opacity-60',
                                        upgrade.front_image ? 'cursor-pointer hover:bg-accent' : '',
                                    ]"
                                    @click="openUpgradePreview(upgrade)"
                                >
                                    <Star class="size-3 shrink-0" :class="player.crew_build?.crew_upgrade_id === upgrade.id ? 'fill-amber-500 text-amber-500' : 'text-muted-foreground'" />
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
                                            <Badge v-if="member.is_summoned" variant="secondary" class="bg-cyan-500/20 px-1 py-0 text-[9px] text-cyan-200">Summoned</Badge>
                                        </div>
                                        <span class="flex min-w-[3rem] items-center justify-center gap-0.5 text-xs font-bold">
                                            <Heart class="size-3" :class="member.is_killed ? 'text-red-400' : member.current_health <= Math.ceil(member.max_health / 2) ? 'text-red-300' : ''" />
                                            {{ member.current_health }}/{{ member.max_health }}
                                        </span>
                                    </div>
                                    <div v-if="member.attached_tokens?.length" class="mt-0.5 flex flex-wrap gap-1">
                                        <Badge
                                            v-for="token in member.attached_tokens"
                                            :key="token.id"
                                            variant="secondary"
                                            class="border border-cyan-500/50 bg-cyan-900/60 px-1 py-0 text-[9px] text-cyan-200"
                                        >{{ token.name }}</Badge>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </template>

        </div>
    </div>

    <!-- Strategy Drawer -->
    <Drawer v-model:open="strategyDrawerOpen">
        <DrawerContent>
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
    <Drawer v-model:open="crewMemberDrawerOpen">
        <DrawerContent>
            <div v-if="previewMember" class="mx-auto w-full max-w-md">
                <DrawerHeader class="pb-2">
                    <DrawerTitle class="text-center">{{ previewMember.display_name }}</DrawerTitle>
                    <div v-if="!isObserver && memberMiniatures.length > 1 && (isSolo || myPlayer?.crew_members?.some((m: any) => m.id === previewMember.id))" class="mt-2 flex justify-center">
                        <Select
                            :model-value="String(memberMiniatures.find((m: any) => m.front_image === previewMember.front_image)?.id ?? memberMiniatures[0]?.id ?? '')"
                            @update:model-value="onSculptChange"
                        >
                            <SelectTrigger class="w-auto gap-2 text-xs">
                                <SelectValue placeholder="Select sculpt" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="mini in memberMiniatures" :key="mini.id" :value="String(mini.id)">
                                    {{ mini.display_name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </DrawerHeader>
                <div class="flex min-h-0 flex-1 items-start justify-center px-4 pb-2 [&_img]:max-h-[65dvh] [&_img]:w-auto [&_img]:object-contain">
                    <CharacterCardView
                        v-if="previewMember.front_image"
                        :key="previewMember.front_image"
                        :miniature="{ id: previewMember.id, display_name: previewMember.display_name, slug: '', front_image: previewMember.front_image, back_image: previewMember.back_image }"
                        :show-link="false"
                        :show-collection="false"
                    />
                    <div v-else class="py-8 text-center text-sm text-muted-foreground">No card image available</div>
                </div>
                <DrawerFooter class="shrink-0 pt-2">
                    <DrawerClose as-child>
                        <Button variant="outline">Close</Button>
                    </DrawerClose>
                </DrawerFooter>
            </div>
        </DrawerContent>
    </Drawer>

    <!-- Crew Card Preview Drawer -->
    <Drawer v-model:open="upgradeDrawerOpen">
        <DrawerContent>
            <div v-if="previewUpgrade" class="mx-auto w-full max-w-md">
                <DrawerHeader class="pb-2">
                    <DrawerTitle class="text-center">{{ previewUpgrade.name }}</DrawerTitle>
                    <div class="mt-1 text-center text-xs text-muted-foreground">Crew Card</div>
                </DrawerHeader>
                <div class="flex min-h-0 flex-1 items-start justify-center px-4 pb-2 [&_img]:max-h-[65dvh] [&_img]:w-auto [&_img]:object-contain">
                    <UpgradeFlipCard
                        :front-image="previewUpgrade.front_image"
                        :back-image="previewUpgrade.back_image"
                        :alt-text="previewUpgrade.name"
                        :show-link="false"
                    />
                </div>
                <DrawerFooter class="shrink-0 pt-2">
                    <DrawerClose as-child>
                        <Button variant="outline">Close</Button>
                    </DrawerClose>
                </DrawerFooter>
            </div>
        </DrawerContent>
    </Drawer>

    <!-- Token Info Drawer -->
    <Drawer v-model:open="tokenInfoDrawerOpen">
        <DrawerContent>
            <div v-if="tokenInfoData" class="mx-auto w-full max-w-sm">
                <DrawerHeader class="pb-2">
                    <DrawerTitle class="text-center">{{ tokenInfoData.name }}</DrawerTitle>
                    <div class="mt-1 text-center text-xs text-muted-foreground">Token</div>
                </DrawerHeader>
                <div class="px-4 pb-4">
                    <p v-if="tokenInfoData.description" class="text-sm leading-relaxed text-muted-foreground">{{ tokenInfoData.description }}</p>
                    <p v-else class="text-center text-sm text-muted-foreground">No description available.</p>
                </div>
                <DrawerFooter class="gap-2 pt-2">
                    <Button v-if="tokenInfoMember" variant="destructive" size="sm" @click="removeTokenFromInfo">
                        <Minus class="mr-1.5 size-3.5" />
                        Remove from {{ tokenInfoMember.display_name }}
                    </Button>
                    <DrawerClose as-child>
                        <Button variant="outline">Close</Button>
                    </DrawerClose>
                </DrawerFooter>
            </div>
        </DrawerContent>
    </Drawer>

    <!-- Opponent Scheme Dialog (Solo) — Multi-step -->
    <Dialog v-if="isSolo && !isObserver" :open="oppDialogOpen" @update:open="(v) => { if (!v) oppCancelDialog(); }">
        <DialogContent class="max-w-sm">
            <DialogHeader>
                <DialogTitle>
                    <template v-if="oppDialogMode === 'scored'">Opponent Scored Scheme VP</template>
                    <template v-else-if="oppDialogMode === 'end-of-game'">Opponent's Final Scheme</template>
                    <template v-else>Opponent's Scheme</template>
                </DialogTitle>
                <DialogDescription>
                    <template v-if="oppDialogMode === 'scored'">Which scheme did they score on? Their next pool will derive from this scheme.</template>
                    <template v-else-if="oppDialogMode === 'end-of-game'">Identify the opponent's scheme for final scoring, or keep hidden.</template>
                    <template v-else>Hold scheme hidden, or select which scheme they're discarding. Their next pool will derive from the discarded scheme.</template>
                </DialogDescription>
            </DialogHeader>
            <div class="space-y-1">
                <!-- Hold hidden (discard + end-of-game only) -->
                <button
                    v-if="oppDialogMode === 'discard' || oppDialogMode === 'end-of-game'"
                    class="flex w-full items-center justify-between rounded-md bg-primary/10 px-3 py-2.5 text-left text-sm font-medium hover:bg-primary/20"
                    @click="oppKeepHidden"
                >
                    Hold Scheme (Hidden)
                </button>
                <div
                    v-if="(oppDialogMode === 'discard' || oppDialogMode === 'end-of-game') && opponentSchemePool.length"
                    class="py-1 text-center text-[10px] text-muted-foreground"
                >— or {{ oppDialogMode === 'discard' ? 'discard' : 'reveal' }} —</div>
                <!-- Scheme options from current pool -->
                <button
                    v-for="scheme in opponentSchemePool"
                    :key="'opp-id-' + scheme.id"
                    class="flex w-full items-center justify-between rounded-md px-3 py-2 text-left text-sm hover:bg-accent"
                    @click="oppSelectScheme(scheme.id)"
                >
                    {{ scheme.name }}
                </button>
            </div>
        </DialogContent>
    </Dialog>

    <!-- Summon Dialog -->
    <Dialog
        v-model:open="summonDialogOpen"
        @update:open="
            (open) => {
                if (!open) {
                    summonSearch = '';
                    summonResults = [];
                }
            }
        "
    >
        <DialogContent class="max-w-sm">
            <DialogHeader>
                <DialogTitle>Summon Character</DialogTitle>
                <DialogDescription>Select a reference character or search for any character.</DialogDescription>
            </DialogHeader>
            <!-- Reference characters -->
            <div v-if="referenceCharacters.length">
                <div class="mb-1 text-xs font-medium text-muted-foreground">Reference Characters</div>
                <div class="max-h-40 space-y-0.5 overflow-y-auto">
                    <button
                        v-for="char in referenceCharacters"
                        :key="'ref-sum-' + char.id"
                        class="flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-left text-sm transition-colors"
                        :class="summonCrewCount(char.id) >= (char.count ?? 99) ? 'opacity-40 cursor-not-allowed' : 'hover:bg-accent'"
                        :disabled="summonCrewCount(char.id) >= (char.count ?? 99)"
                        @click="selectCharacterForSummon(char)"
                    >
                        <img v-if="char.front_image" :src="'/storage/' + char.front_image" class="size-8 rounded object-cover" />
                        <div class="min-w-0 flex-1">
                            <div class="truncate font-medium">{{ char.display_name }}</div>
                            <div v-if="char.type" class="text-[10px] text-muted-foreground">{{ char.type }}</div>
                        </div>
                        <span v-if="summonCrewCount(char.id) > 0" class="shrink-0 text-[10px] text-muted-foreground">
                            {{ summonCrewCount(char.id) }}
                        </span>
                    </button>
                </div>
            </div>
            <!-- Search all characters -->
            <details class="rounded-md border">
                <summary class="cursor-pointer px-2 py-1.5 text-xs font-medium text-muted-foreground hover:text-foreground">Search All Characters</summary>
                <div class="border-t px-1 pb-1 pt-1">
                    <Input
                        :model-value="summonSearch"
                        placeholder="Search..."
                        class="mb-1"
                        @update:model-value="searchSummon($event as string)"
                    />
                    <div class="max-h-36 space-y-0.5 overflow-y-auto">
                        <div v-if="summonLoading" class="flex justify-center py-3">
                            <Loader2 class="size-4 animate-spin text-muted-foreground" />
                        </div>
                        <template v-else-if="summonResults.length">
                            <button
                                v-for="char in summonResults"
                                :key="char.id"
                                class="flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-left text-sm transition-colors"
                                :class="summonCrewCount(char.id) >= (char.count ?? 1) ? 'opacity-40 cursor-not-allowed' : 'hover:bg-accent'"
                                :disabled="summonCrewCount(char.id) >= (char.count ?? 1)"
                                @click="selectCharacterForSummon(char)"
                            >
                                <img v-if="char.front_image" :src="char.front_image" class="size-8 rounded object-cover" />
                                <div class="min-w-0 flex-1">
                                    <div class="truncate font-medium">{{ char.display_name ?? char.name }}</div>
                                    <div v-if="char.station" class="text-xs text-muted-foreground capitalize">{{ char.station }}</div>
                                </div>
                                <span v-if="summonCrewCount(char.id) > 0" class="shrink-0 text-[10px] text-muted-foreground">
                                    {{ summonCrewCount(char.id) }}/{{ char.count ?? 1 }}
                                </span>
                            </button>
                        </template>
                        <div v-else-if="summonSearch.length >= 2" class="py-3 text-center text-xs text-muted-foreground">No characters found</div>
                    </div>
                </div>
            </details>
        </DialogContent>
    </Dialog>

    <!-- Replace Crew Member Dialog -->
    <Dialog
        v-model:open="replaceDialogOpen"
        @update:open="
            (open) => {
                if (!open) {
                    replaceSearch = '';
                    replaceResults = [];
                    replaceMember = null;
                }
            }
        "
    >
        <DialogContent class="max-w-sm">
            <DialogHeader>
                <DialogTitle>Replace Crew Member</DialogTitle>
                <DialogDescription v-if="replaceMember">
                    Replacing <strong>{{ replaceMember.display_name }}</strong> ({{ replaceMember.current_health }}/{{ replaceMember.max_health }} HP)
                </DialogDescription>
            </DialogHeader>
            <!-- Reference characters -->
            <div v-if="referenceCharacters.length">
                <div class="mb-1 text-xs font-medium text-muted-foreground">Reference Characters</div>
                <div class="max-h-40 space-y-0.5 overflow-y-auto">
                    <button
                        v-for="char in referenceCharacters"
                        :key="'ref-rep-' + char.id"
                        class="flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-left text-sm transition-colors hover:bg-accent"
                        @click="selectCharacterForReplace(char)"
                    >
                        <img v-if="char.front_image" :src="'/storage/' + char.front_image" class="size-8 rounded object-cover" />
                        <div class="min-w-0 flex-1">
                            <div class="truncate font-medium">{{ char.display_name }}</div>
                            <div v-if="char.type" class="text-[10px] text-muted-foreground">{{ char.type }}</div>
                        </div>
                    </button>
                </div>
            </div>
            <!-- Search all characters -->
            <details class="rounded-md border">
                <summary class="cursor-pointer px-2 py-1.5 text-xs font-medium text-muted-foreground hover:text-foreground">Search All Characters</summary>
                <div class="border-t px-1 pb-1 pt-1">
                    <Input
                        :model-value="replaceSearch"
                        placeholder="Search..."
                        class="mb-1"
                        @update:model-value="searchReplace($event as string)"
                    />
                    <div class="max-h-36 space-y-0.5 overflow-y-auto">
                        <div v-if="replaceLoading" class="flex justify-center py-3">
                            <Loader2 class="size-4 animate-spin text-muted-foreground" />
                        </div>
                        <template v-else-if="replaceResults.length">
                            <button
                                v-for="char in replaceResults"
                                :key="char.id"
                                class="flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-left text-sm transition-colors hover:bg-accent"
                                @click="selectCharacterForReplace(char)"
                            >
                                <img v-if="char.front_image" :src="char.front_image" class="size-8 rounded object-cover" />
                                <div class="min-w-0 flex-1">
                                    <div class="truncate font-medium">{{ char.display_name ?? char.name }}</div>
                                    <div v-if="char.station" class="text-xs text-muted-foreground capitalize">{{ char.station }}</div>
                                </div>
                            </button>
                        </template>
                        <div v-else-if="replaceSearch.length >= 2" class="py-3 text-center text-xs text-muted-foreground">No characters found</div>
                    </div>
                </div>
            </details>
            <DialogFooter>
                <Button variant="outline" class="w-full" @click="replaceDialogOpen = false">Cancel</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <!-- Sculpt Picker Dialog -->
    <Dialog v-model:open="sculptPickerOpen">
        <DialogContent class="max-w-sm">
            <DialogHeader>
                <DialogTitle>Choose Sculpt</DialogTitle>
                <DialogDescription>Select which version to use.</DialogDescription>
            </DialogHeader>
            <div class="grid grid-cols-2 gap-2">
                <button
                    v-for="mini in sculptPickerMiniatures"
                    :key="mini.id"
                    class="overflow-hidden rounded-lg border-2 border-transparent transition-all hover:border-primary hover:shadow-md"
                    @click="confirmSculptSelection(mini.id)"
                >
                    <img
                        v-if="mini.front_image"
                        :src="'/storage/' + mini.front_image"
                        :alt="mini.display_name"
                        class="aspect-[550/950] w-full object-cover"
                        loading="lazy"
                        decoding="async"
                    />
                    <div class="bg-muted/50 px-2 py-1 text-center text-[10px] font-medium">{{ mini.display_name }}</div>
                </button>
            </div>
            <DialogFooter>
                <Button variant="outline" class="w-full" @click="sculptPickerOpen = false">Cancel</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <!-- Upgrade Dialog -->
    <Dialog v-model:open="upgradeDialogOpen">
        <DialogContent class="max-w-sm">
            <DialogHeader>
                <DialogTitle>Manage Upgrades</DialogTitle>
                <DialogDescription v-if="upgradeMember">{{ upgradeMember.display_name }}</DialogDescription>
            </DialogHeader>
            <!-- Current upgrades -->
            <div v-if="upgradeMember?.attached_upgrades?.length" class="space-y-1">
                <div class="text-xs font-medium text-muted-foreground">Active Upgrades</div>
                <div class="space-y-0.5">
                    <div
                        v-for="upgrade in upgradeMember.attached_upgrades"
                        :key="'cu-' + upgrade.id"
                        class="flex items-center justify-between rounded-md border border-amber-500/30 bg-amber-500/5 px-2 py-1 text-xs"
                    >
                        <div class="flex items-center gap-1.5">
                            <ArrowUpCircle class="size-3 shrink-0 text-amber-500" />
                            <span class="font-medium">{{ upgrade.name }}</span>
                        </div>
                        <button class="rounded p-0.5 text-red-400 hover:bg-red-500/10" @click="toggleUpgrade(upgrade)">
                            <Minus class="size-3" />
                        </button>
                    </div>
                </div>
            </div>
            <!-- Reference upgrades -->
            <div v-if="filteredUpgrades.filter((u) => memberReferenceUpgradeIds.has(u.id)).length">
                <div class="mb-1 text-xs font-medium text-muted-foreground">Reference Upgrades</div>
                <div class="max-h-32 space-y-0.5 overflow-y-auto">
                    <button
                        v-for="upgrade in filteredUpgrades.filter((u) => memberReferenceUpgradeIds.has(u.id))"
                        :key="'ref-' + upgrade.id"
                        class="flex w-full items-center gap-2 rounded px-2 py-1 text-left text-sm transition-colors"
                        :class="[
                            memberHasUpgrade(upgrade.id) ? 'bg-amber-500/10 font-medium' : '',
                            !memberHasUpgrade(upgrade.id) && isUpgradeAtLimit(upgrade.id, upgrade.plentiful) ? 'opacity-40 cursor-not-allowed' : 'hover:bg-accent',
                        ]"
                        :disabled="!memberHasUpgrade(upgrade.id) && isUpgradeAtLimit(upgrade.id, upgrade.plentiful)"
                        @click="toggleUpgrade(upgrade)"
                    >
                        <Check v-if="memberHasUpgrade(upgrade.id)" class="size-3 shrink-0 text-amber-500" />
                        <ArrowUpCircle v-else class="size-3 shrink-0 text-muted-foreground" />
                        <span class="min-w-0 flex-1 truncate text-xs">{{ upgrade.name }}</span>
                        <span v-if="(upgrade.plentiful ?? 1) > 1" class="shrink-0 text-[9px] text-muted-foreground">{{ upgradeUsageCount(upgrade.id) + (memberHasUpgrade(upgrade.id) ? 1 : 0) }}/{{ upgrade.plentiful }}</span>
                    </button>
                </div>
            </div>
            <!-- All upgrades -->
            <details class="rounded-md border">
                <summary class="cursor-pointer px-2 py-1.5 text-xs font-medium text-muted-foreground hover:text-foreground">All Upgrades</summary>
                <div class="border-t px-1 pb-1 pt-1">
                    <Input v-model="upgradeSearch" placeholder="Filter..." class="mb-1" />
                    <div class="max-h-36 space-y-0.5 overflow-y-auto">
                        <button
                            v-for="upgrade in filteredUpgrades"
                            :key="upgrade.id"
                            class="flex w-full items-center gap-2 rounded px-2 py-1 text-left text-sm transition-colors"
                            :class="[
                                memberHasUpgrade(upgrade.id) ? 'bg-amber-500/10 font-medium' : '',
                                !memberHasUpgrade(upgrade.id) && isUpgradeAtLimit(upgrade.id, upgrade.plentiful) ? 'opacity-40 cursor-not-allowed' : 'hover:bg-accent',
                            ]"
                            :disabled="!memberHasUpgrade(upgrade.id) && isUpgradeAtLimit(upgrade.id, upgrade.plentiful)"
                            @click="toggleUpgrade(upgrade)"
                        >
                            <Check v-if="memberHasUpgrade(upgrade.id)" class="size-3 shrink-0 text-amber-500" />
                            <ArrowUpCircle v-else class="size-3 shrink-0 text-muted-foreground" />
                            <span class="min-w-0 flex-1 truncate text-xs">{{ upgrade.name }}</span>
                            <span v-if="(upgrade.plentiful ?? 1) > 1" class="shrink-0 text-[9px] text-muted-foreground">{{ upgradeUsageCount(upgrade.id) + (memberHasUpgrade(upgrade.id) ? 1 : 0) }}/{{ upgrade.plentiful }}</span>
                        </button>
                    </div>
                </div>
            </details>
            <DialogFooter>
                <Button variant="outline" class="w-full" @click="upgradeDialogOpen = false">Close</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <!-- Attached Upgrade Preview Drawer -->
    <Drawer v-model:open="attachedUpgradeDrawerOpen">
        <DrawerContent>
            <div v-if="previewAttachedUpgrade" class="mx-auto w-full max-w-md">
                <DrawerHeader class="pb-2">
                    <DrawerTitle class="text-center">{{ previewAttachedUpgrade.name }}</DrawerTitle>
                    <div class="mt-1 text-center text-xs text-muted-foreground">Upgrade</div>
                </DrawerHeader>
                <div class="flex min-h-0 flex-1 items-start justify-center px-4 pb-2 [&_img]:max-h-[65dvh] [&_img]:w-auto [&_img]:object-contain">
                    <UpgradeFlipCard
                        :front-image="previewAttachedUpgrade.front_image"
                        :back-image="previewAttachedUpgrade.back_image"
                        :alt-text="previewAttachedUpgrade.name"
                        :show-link="false"
                    />
                </div>
                <DrawerFooter class="shrink-0 pt-2">
                    <DrawerClose as-child>
                        <Button variant="outline">Close</Button>
                    </DrawerClose>
                </DrawerFooter>
            </div>
        </DrawerContent>
    </Drawer>

    <!-- Token Dialog -->
    <Dialog v-model:open="tokenDialogOpen">
        <DialogContent class="max-w-sm">
            <DialogHeader>
                <DialogTitle>Manage Tokens</DialogTitle>
                <DialogDescription v-if="tokenMember">{{ tokenMember.display_name }}</DialogDescription>
            </DialogHeader>
            <!-- Current tokens -->
            <div v-if="tokenMember?.attached_tokens?.length" class="space-y-1">
                <div class="text-xs font-medium text-muted-foreground">Active Tokens</div>
                <div class="flex flex-wrap gap-1">
                    <Badge
                        v-for="token in tokenMember.attached_tokens"
                        :key="'current-' + token.id"
                        variant="secondary"
                        class="cursor-pointer gap-1 pr-1"
                        @click="removeToken(token.id)"
                    >
                        {{ token.name }}
                        <Minus class="size-3 text-red-400" />
                    </Badge>
                </div>
            </div>
            <!-- Reference tokens -->
            <div v-if="props.tokens.filter((t) => referenceTokenIds.has(t.id)).length">
                <div class="mb-1 text-xs font-medium text-muted-foreground">Reference Tokens</div>
                <div class="max-h-32 space-y-0.5 overflow-y-auto">
                    <button
                        v-for="token in props.tokens.filter((t) => referenceTokenIds.has(t.id))"
                        :key="'ref-' + token.id"
                        class="flex w-full items-center gap-2 rounded px-2 py-1 text-left text-sm transition-colors"
                        :class="memberHasToken(token.id) ? 'bg-primary/10 font-medium' : 'hover:bg-accent'"
                        @click="toggleToken(token.id, token.name)"
                    >
                        <Check v-if="memberHasToken(token.id)" class="size-3 shrink-0 text-green-500" />
                        <Plus v-else class="size-3 shrink-0 text-muted-foreground" />
                        {{ token.name }}
                    </button>
                </div>
            </div>
            <!-- All tokens -->
            <details class="rounded-md border">
                <summary class="cursor-pointer px-2 py-1.5 text-xs font-medium text-muted-foreground hover:text-foreground">All Tokens</summary>
                <div class="border-t px-1 pb-1 pt-1">
                    <Input v-model="tokenSearch" placeholder="Filter..." class="mb-1" />
                    <div class="max-h-36 space-y-0.5 overflow-y-auto">
                        <button
                            v-for="token in props.tokens.filter((t) => !tokenSearch || t.name.toLowerCase().includes(tokenSearch.toLowerCase()))"
                            :key="token.id"
                            class="flex w-full items-center gap-2 rounded px-2 py-1 text-left text-sm transition-colors"
                            :class="memberHasToken(token.id) ? 'bg-primary/10 font-medium' : 'hover:bg-accent'"
                            @click="toggleToken(token.id, token.name)"
                        >
                            <Check v-if="memberHasToken(token.id)" class="size-3 shrink-0 text-green-500" />
                            <Plus v-else class="size-3 shrink-0 text-muted-foreground" />
                            {{ token.name }}
                        </button>
                    </div>
                </div>
            </details>
            <DialogFooter>
                <Button variant="outline" class="w-full" @click="tokenDialogOpen = false">Close</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <!-- Complete Game Confirmation Dialog -->
    <Dialog v-model:open="completeDialogOpen">
        <DialogContent class="max-w-sm">
            <DialogHeader>
                <DialogTitle>Complete Game</DialogTitle>
                <DialogDescription>
                    Are you sure you want to mark this game as complete? Final scores will be calculated and the game will end.
                </DialogDescription>
            </DialogHeader>
            <DialogFooter class="gap-2 sm:gap-0">
                <Button variant="outline" @click="completeDialogOpen = false">Cancel</Button>
                <Button @click="completeDialogOpen = false; markGameComplete()">Complete Game</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <!-- Abandon Confirmation Dialog -->
    <Dialog v-model:open="abandonDialogOpen">
        <DialogContent class="max-w-sm">
            <DialogHeader>
                <DialogTitle>Abandon Game</DialogTitle>
                <DialogDescription>
                    Are you sure you want to abandon this game? The game will be marked as abandoned and cannot be resumed.
                </DialogDescription>
            </DialogHeader>
            <DialogFooter class="gap-2 sm:gap-0">
                <Button variant="outline" @click="abandonDialogOpen = false">Cancel</Button>
                <Button variant="destructive" @click="executeAbandon">Abandon</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <!-- Replace on Death Dialog -->
    <Dialog v-model:open="replaceOnDeathDialogOpen">
        <DialogContent class="max-w-sm">
            <DialogHeader>
                <DialogTitle>Replace on Death</DialogTitle>
                <DialogDescription>
                    {{ replaceOnDeathReplacements.length > 1 ? 'Select which models to add to the crew.' : 'This model replaces into the following when killed.' }}
                </DialogDescription>
            </DialogHeader>
            <div class="space-y-1.5">
                <button
                    v-for="replacement in replaceOnDeathReplacements"
                    :key="replacement.id"
                    class="flex w-full items-center gap-3 rounded-lg border p-2 text-left transition-colors"
                    :class="replacement.selected ? 'border-primary bg-primary/5' : 'opacity-50'"
                    @click="replacement.selected = !replacement.selected"
                >
                    <div class="flex size-5 shrink-0 items-center justify-center rounded border" :class="replacement.selected ? 'border-primary bg-primary text-primary-foreground' : 'border-muted-foreground/30'">
                        <Check v-if="replacement.selected" class="size-3" />
                    </div>
                    <img v-if="replacement.front_image" :src="replacement.front_image" class="size-10 shrink-0 rounded object-cover" />
                    <div class="min-w-0 flex-1">
                        <div class="text-sm font-medium">{{ replacement.display_name }}</div>
                        <div v-if="replacement.count > 1" class="text-xs text-muted-foreground">&times;{{ replacement.count }}</div>
                    </div>
                </button>
            </div>
            <div v-if="replaceOnDeathWarnings.length" class="space-y-1">
                <div v-for="(warn, i) in replaceOnDeathWarnings" :key="i" class="rounded-md border border-amber-500/30 bg-amber-500/5 px-3 py-1.5 text-xs text-amber-700 dark:text-amber-400">
                    {{ warn }}
                </div>
                <p class="text-xs text-muted-foreground">Select a different option and try again, or close.</p>
            </div>
            <DialogFooter class="gap-2 sm:gap-0">
                <Button variant="outline" @click="dismissReplaceOnDeath">{{ replaceOnDeathWarnings.length ? 'Close' : 'Skip All' }}</Button>
                <Button :disabled="!hasSelectedReplacements" @click="confirmReplaceOnDeath">
                    Add Selected
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <!-- QR Code Dialog -->
    <QRCodeDialog v-if="qrDialogOpen" v-model:open="qrDialogOpen" :url="qrDialogUrl" :title="qrDialogTitle" />
</template>
