<script setup lang="ts">
import CharacterCardView from '@/components/CharacterCardView.vue';
import FactionLogo from '@/components/FactionLogo.vue';
import GameIcon from '@/components/GameIcon.vue';
import UpgradeFlipCard from '@/components/UpgradeFlipCard.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Drawer, DrawerClose, DrawerContent, DrawerFooter, DrawerHeader, DrawerTitle } from '@/components/ui/drawer';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useGameChannel } from '@/composables/useGameChannel';
import { type SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ArrowLeft, Check, ChevronDown, Circle, Copy, Dices, Eye, EyeOff, Heart, Loader2, Minus, Pencil, Plus, RotateCcw, Shield, ShieldAlert, Skull, Star, Swords, Users } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface GamePlayer {
    id: number;
    slot: number;
    faction: string | null;
    master_name: string | null;
    master_id: number | null;
    crew_build_id: number | null;
    crew_skipped: boolean;
    current_scheme_id: number | null;
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
    next_schemes: SchemeData[];
    opponent_next_schemes: SchemeData[];
    tokens: { id: number; name: string; slug: string }[];
    markers: { id: number; name: string; slug: string }[];
}>();

const page = usePage<SharedData>();
const currentUserId = computed(() => page.props.auth.user?.id);
const myPlayer = computed(() => props.game.players.find((p) => p.user?.id === currentUserId.value));
const opponent = computed(() => props.game.players.find((p) => p.slot === 2) ?? props.game.players.find((p) => p.user?.id !== currentUserId.value));

const isSolo = computed(() => props.game.is_solo);
const { onlineMembers } = useGameChannel(isSolo.value ? '' : props.game.uuid);
const isUserOnline = (userId: number) => onlineMembers.value.some((m) => m.id === userId);

// Scenario editing
const isCreator = computed(() => currentUserId.value === props.game.creator_id);
const canEditScenario = computed(() => props.game.status === 'setup' && isCreator.value);
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
    await fetch(route('games.scenario.update', props.game.uuid), {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
        body: JSON.stringify(body),
    });
    router.reload({ only: ['game', 'schemes', 'deployment'] });
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
const csrfToken = () => document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';

const postSetup = async (endpoint: string, body: Record<string, unknown>) => {
    submitting.value = true;
    try {
        await fetch(endpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
            body: JSON.stringify(body),
        });
        router.reload();
    } finally {
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
const selectedMasterInfo = computed(() => availableMasters.value.find((m) => m.name === selectedMasterName.value));
const masterHasMultipleTitles = computed(() => (selectedMasterInfo.value?.titles.length ?? 0) > 1);

const selectMasterTitle = (title: MasterTitle) => {
    postSetup(route('games.setup.master', props.game.uuid), { master_name: title.display_name });
};

const selectMasterName = (name: string) => {
    selectedMasterName.value = name;
    const info = availableMasters.value.find((m) => m.name === name);
    // If only one title, submit immediately
    if (info && info.titles.length === 1) {
        selectMasterTitle(info.titles[0]);
    }
};

// Master title switching during crew select
const masterTitleOptions = computed(() => {
    if (!myPlayer.value?.master_name) return [];
    const baseName = myPlayer.value.master_name.split(',')[0];
    const masterGroup = props.masters.find((m) => m.name === baseName);
    return masterGroup?.titles ?? [];
});

const switchMasterTitle = (title: MasterTitle) => {
    if (title.id === myPlayer.value?.master_id) return;
    postSetup(route('games.setup.master', props.game.uuid), { master_name: title.display_name });
};

// Crew select
const expandedCrewId = ref<number | null>(null);
const newCrewUrl = computed(() => {
    const faction = myPlayer.value?.faction ?? '';
    const masterId = myPlayer.value?.master_id;
    if (masterId) {
        return route('tools.crew_builder.editor') + '?step=hiring&faction=' + encodeURIComponent(faction) + '&master=' + masterId;
    }
    const masterName = myPlayer.value?.master_name?.split(',')[0] ?? '';
    return route('tools.crew_builder.editor') + '?step=title&faction=' + encodeURIComponent(faction) + '&master=' + encodeURIComponent(masterName);
});
const matchingCrews = computed(() => {
    if (!myPlayer.value?.faction) return props.my_crews;
    return props.my_crews.filter((c) => c.faction === myPlayer.value!.faction);
});

// Solo mode: setup for opponent (slot 2)
const opponentPlayer = computed(() => props.game.players.find((p) => p.slot === 2));
const playerName = (player: GamePlayer | undefined) => player?.user?.name ?? player?.opponent_name ?? 'Opponent';

const selectedOpponentFaction = ref<string | null>(null);
const selectedOpponentMasterName = ref<string | null>(null);
const opponentAvailableMasters = computed(() => {
    const f = opponentPlayer.value?.faction;
    if (!f) return [];
    return props.masters.filter((m) => m.faction === f || m.second_faction === f || m.is_alternate_leader);
});
const selectedOpponentMasterInfo = computed(() => opponentAvailableMasters.value.find((m) => m.name === selectedOpponentMasterName.value));
const opponentMasterHasMultipleTitles = computed(() => (selectedOpponentMasterInfo.value?.titles.length ?? 0) > 1);

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

const selectOpponentMasterName = (name: string) => {
    selectedOpponentMasterName.value = name;
    const info = opponentAvailableMasters.value.find((m) => m.name === name);
    if (info && info.titles.length === 1) {
        postSetup(route('games.setup.master', props.game.uuid), { master_name: info.titles[0].display_name, slot: 2 });
    }
};

const selectOpponentMasterTitle = (title: MasterTitle) => {
    postSetup(route('games.setup.master', props.game.uuid), { master_name: title.display_name, slot: 2 });
};

const skipOpponentCrew = () => {
    postSetup(route('games.setup.crew.skip', props.game.uuid), {});
};

const swapRoles = async () => {
    await fetch(route('games.setup.swap_roles', props.game.uuid), {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': csrfToken() },
    });
    router.reload({ only: ['game'] });
};

// Solo gameplay: opponent scoring
const opponentStrategyPoints = ref(0);
const opponentSchemePoints = ref(0);
const opponentNextSchemeId = ref<number | null>(null);
const opponentSchemeRevealed = ref(false);

const submitOpponentTurnScore = async () => {
    scoringTurn.value = true;
    await fetch(route('games.play.turns.store', props.game.uuid), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
        body: JSON.stringify({
            strategy_points: opponentStrategyPoints.value,
            scheme_points: opponentSchemePoints.value,
            next_scheme_id: opponentNextSchemeId.value,
            slot: 2,
        }),
    });
    opponentStrategyPoints.value = 0;
    opponentSchemePoints.value = 0;
    opponentNextSchemeId.value = null;
    scoringTurn.value = false;
    router.reload({ only: ['game', 'next_schemes', 'opponent_next_schemes'] });
};

const updateOpponentSoulstonePool = (delta: number) => {
    const current = opponentPlayer.value?.soulstone_pool ?? 0;
    const newVal = Math.max(0, current + delta);
    postPlay(route('games.play.soulstones', props.game.uuid), 'PATCH', { soulstone_pool: newVal, slot: 2 });
};

// Solo: change opponent scheme during gameplay
const opponentSchemeDialogOpen = ref(false);

const setOpponentScheme = async (schemeId: number) => {
    await fetch(route('games.setup.scheme', props.game.uuid), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
        body: JSON.stringify({ scheme_id: schemeId, slot: 2 }),
    });
    opponentSchemeDialogOpen.value = false;
    router.reload({ only: ['game', 'opponent_next_schemes'] });
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

const abandonDialogOpen = ref(false);
const executeAbandon = () => {
    router.post(route('games.abandon', props.game.uuid));
    abandonDialogOpen.value = false;
};

// ─── Gameplay ───
const gameplayTab = ref<'scenario' | 'my-crew' | 'opponent'>('my-crew');
const schemeHidden = ref(false);

// Card preview drawers
const crewMemberDrawerOpen = ref(false);
const previewMember = ref<any>(null);
const upgradeDrawerOpen = ref(false);
const previewUpgrade = ref<any>(null);

const openMemberPreview = (member: any) => {
    if (!member.front_image && !member.back_image) return;
    previewMember.value = member;
    crewMemberDrawerOpen.value = true;
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
    const opts: RequestInit = { method, headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken() } };
    if (body) opts.body = JSON.stringify(body);
    await fetch(url, opts);
    router.reload({ only: ['game'] });
};

const updateHealth = (member: any, delta: number) => {
    const newHealth = Math.max(0, Math.min(member.max_health, member.current_health + delta));
    if (newHealth === member.current_health) return;
    postPlay(route('games.play.crew.update', { game: props.game.uuid, gameCrewMember: member.id }), 'PATCH', { current_health: newHealth });
};

const toggleActivated = (member: any) => {
    postPlay(route('games.play.crew.update', { game: props.game.uuid, gameCrewMember: member.id }), 'PATCH', { is_activated: !member.is_activated });
};

const killMember = (member: any) => {
    postPlay(route('games.play.crew.kill', { game: props.game.uuid, gameCrewMember: member.id }));
};

const reviveMember = (member: any) => {
    postPlay(route('games.play.crew.revive', { game: props.game.uuid, gameCrewMember: member.id }));
};

// Turn scoring
const strategyPoints = ref(0);
const schemePoints = ref(0);
const nextSchemeId = ref<number | null>(null);
const scoringTurn = ref(false);

const isLastTurn = computed(() => props.game.current_turn >= props.game.max_turns);
const currentSchemeScored = computed(() => schemePoints.value > 0);

const submitTurnScore = async () => {
    scoringTurn.value = true;
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
    router.reload({ only: ['game', 'next_schemes', 'opponent_next_schemes'] });
};

const markGameComplete = async () => {
    await fetch(route('games.play.complete', props.game.uuid), {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': csrfToken() },
    });
    router.reload({ only: ['game'] });
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
        const res = await fetch(route('api.characters.view') + '?name=' + encodeURIComponent(q));
        const data = await res.json();
        // API returns miniatures with character relation — dedupe by character_id
        const chars = (Array.isArray(data) ? data : data.data ?? []).map((m: any) => ({
            id: m.character?.id ?? m.character_id ?? m.id,
            display_name: m.character?.display_name ?? m.display_name ?? m.name,
            station: m.character?.station ?? m.station,
            front_image: m.front_image ?? m.character?.front_image,
        }));
        const seen = new Set<number>();
        summonResults.value = chars.filter((c: any) => { if (seen.has(c.id)) return false; seen.add(c.id); return true; });
        summonLoading.value = false;
    }, 300);
};

const summonCharacter = async (characterId: number) => {
    const body: Record<string, unknown> = { character_id: characterId };
    if (isSolo.value) body.slot = summonForSlot.value;
    await fetch(route('games.play.crew.summon', props.game.uuid), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
        body: JSON.stringify(body),
    });
    summonDialogOpen.value = false;
    summonSearch.value = '';
    summonResults.value = [];
    router.reload({ only: ['game'] });
};

// Token/marker management
const tokenDialogOpen = ref(false);
const tokenMember = ref<any>(null);
const tokenSearch = ref('');

const openTokenDialog = (member: any) => {
    tokenMember.value = member;
    tokenDialogOpen.value = true;
    tokenSearch.value = '';
};

const addToken = async (tokenId: number, tokenName: string, type: 'token' | 'marker' = 'token') => {
    if (!tokenMember.value) return;
    const current = tokenMember.value.attached_tokens ?? [];
    const key = `${type}-${tokenId}`;
    const existing = current.find((t: any) => t.key === key);
    const updated = existing
        ? current.map((t: any) => t.key === key ? { ...t, count: t.count + 1 } : t)
        : [...current, { key, id: tokenId, name: tokenName, type, count: 1 }];
    await fetch(route('games.play.crew.update', { game: props.game.uuid, gameCrewMember: tokenMember.value.id }), {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
        body: JSON.stringify({ attached_tokens: updated }),
    });
    router.reload({ only: ['game'] });
};

const removeToken = async (key: string) => {
    if (!tokenMember.value) return;
    const current = tokenMember.value.attached_tokens ?? [];
    const updated = current
        .map((t: any) => t.key === key ? { ...t, count: t.count - 1 } : t)
        .filter((t: any) => t.count > 0);
    await fetch(route('games.play.crew.update', { game: props.game.uuid, gameCrewMember: tokenMember.value.id }), {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
        body: JSON.stringify({ attached_tokens: updated }),
    });
    router.reload({ only: ['game'] });
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

            <!-- Game Header (hidden during gameplay — info is in the Game tab) -->
            <div v-if="game.status !== 'in_progress'" class="mb-6 flex flex-wrap items-center gap-3">
                <Swords class="size-6 text-primary" />
                <h1 class="text-xl font-bold">{{ game.name || game.encounter_size + 'ss Encounter' }}</h1>
                <Badge variant="secondary" class="text-xs capitalize">{{ game.season }}</Badge>
                <Badge variant="secondary" class="text-xs">{{ game.encounter_size }}ss</Badge>
                <Badge v-if="isSolo" variant="outline" class="text-xs">Solo</Badge>
                <Button v-if="game.status === 'setup' && isCreator" variant="ghost" size="sm" class="ml-auto gap-1" @click="regenerateScenario">
                    <Dices class="size-3.5" />
                    Re-roll
                </Button>
            </div>

            <!-- Scenario (hidden during gameplay) -->
            <div v-if="game.status !== 'in_progress'" class="mb-6 space-y-6">
                <!-- Deployment -->
                <section v-if="deployment">
                    <div class="mb-3 flex items-center gap-2">
                        <h3 class="text-lg font-semibold">Deployment</h3>
                        <button v-if="canEditScenario" class="rounded-md p-1 text-muted-foreground transition-colors hover:bg-accent hover:text-foreground" @click="editingDeployment = !editingDeployment">
                            <Pencil class="size-3.5" />
                        </button>
                    </div>
                    <div v-if="editingDeployment" class="mb-3">
                        <Select :model-value="editDeployment" @update:model-value="(v: string) => { editDeployment = v; editingDeployment = false; saveScenarioField('deployment', v); }">
                            <SelectTrigger class="w-full sm:w-64"><SelectValue placeholder="Select Deployment" /></SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="d in all_deployments" :key="d.value" :value="d.value">{{ d.label }}</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <Card class="cursor-pointer overflow-hidden transition-shadow hover:shadow-md md:flex" @click="deploymentDrawerOpen = true">
                        <img v-if="deployment.image_url" :src="deployment.image_url" :alt="deployment.label" class="w-full object-cover md:w-48" loading="lazy" decoding="async" />
                        <CardContent class="flex-1 p-4">
                            <div class="text-lg font-medium">{{ deployment.label }}</div>
                            <p class="mt-1 text-sm text-muted-foreground">{{ deployment.description }}</p>
                        </CardContent>
                    </Card>
                </section>

                <!-- Strategy -->
                <section v-if="game.strategy">
                    <div class="mb-3 flex items-center gap-2">
                        <h3 class="text-lg font-semibold">Strategy</h3>
                        <button v-if="canEditScenario" class="rounded-md p-1 text-muted-foreground transition-colors hover:bg-accent hover:text-foreground" @click="editingStrategy = !editingStrategy">
                            <Pencil class="size-3.5" />
                        </button>
                    </div>
                    <div v-if="editingStrategy" class="mb-3">
                        <Select :model-value="editStrategy" @update:model-value="(v: string) => { editStrategy = v; editingStrategy = false; saveScenarioField('strategy_id', Number(v)); }">
                            <SelectTrigger class="w-full sm:w-64"><SelectValue placeholder="Select Strategy" /></SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="s in all_strategies" :key="s.id" :value="String(s.id)">{{ s.name }}</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="cursor-pointer" @click="strategyDrawerOpen = true">
                        <div v-if="game.strategy.image_url" class="overflow-hidden rounded-lg shadow-md transition-shadow hover:shadow-lg md:max-w-xs">
                            <img :src="game.strategy.image_url" :alt="game.strategy.name" class="w-full rounded-lg" loading="lazy" decoding="async" />
                        </div>
                        <Card v-else class="transition-shadow hover:shadow-lg md:max-w-xs">
                            <CardContent class="p-4">
                                <div class="text-lg font-medium">{{ game.strategy.name }}</div>
                            </CardContent>
                        </Card>
                    </div>
                </section>

                <!-- Schemes -->
                <section v-if="schemes.length">
                    <div class="mb-3 flex items-center gap-2">
                        <h3 class="text-lg font-semibold">Schemes</h3>
                        <button v-if="canEditScenario" class="rounded-md p-1 text-muted-foreground transition-colors hover:bg-accent hover:text-foreground" @click="editingSchemes = !editingSchemes">
                            <Pencil class="size-3.5" />
                        </button>
                    </div>
                    <div v-if="editingSchemes" class="mb-3 grid gap-3 sm:grid-cols-3">
                        <div v-for="(schemeId, index) in editSchemePool" :key="'edit-' + index">
                            <Select :model-value="String(schemeId)" @update:model-value="(v: string) => setScheme(index, v)">
                                <SelectTrigger class="w-full"><SelectValue placeholder="Select Scheme" /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="s in availableSchemes(index)" :key="s.id" :value="String(s.id)">{{ s.name }}</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-3">
                        <div v-for="scheme in schemes" :key="scheme.id" class="cursor-pointer" @click="openSchemeDrawer(scheme)">
                            <div v-if="scheme.image_url" class="overflow-hidden rounded-lg shadow-md transition-shadow hover:shadow-lg">
                                <img :src="scheme.image_url" :alt="scheme.name" class="w-full rounded-lg" loading="lazy" decoding="async" />
                            </div>
                            <Card v-else class="transition-shadow hover:shadow-lg">
                                <CardContent class="p-4">
                                    <div class="text-sm font-medium">{{ scheme.name }}</div>
                                </CardContent>
                            </Card>
                        </div>
                    </div>
                </section>
            </div>

            <!-- Players (hidden during gameplay — shown in Game tab) -->
            <h3 v-if="game.status !== 'in_progress'" class="mb-3 text-lg font-semibold">Players</h3>
            <Card v-if="game.status !== 'in_progress'" class="mb-6">
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
                                    <span class="font-medium">{{ playerName(player) }}</span>
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
                    <div v-if="isSolo && game.status !== 'completed' && game.status !== 'abandoned'" class="mt-3 flex justify-center">
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
            <Card v-if="game.status === 'faction_select'" class="mb-6">
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
                            <h2 class="mb-1 font-semibold">Select Opponent's Faction</h2>
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
            <Card v-if="game.status === 'master_select'" class="mb-6">
                <CardContent class="p-4 sm:p-6">
                    <h2 class="mb-1 font-semibold">{{ isSolo && myStepDone('master') ? "Select Opponent's Master" : 'Select Your Master' }}</h2>
                    <p v-if="myStepDone('master') && !isSolo" class="mb-4 text-xs text-muted-foreground">
                        <Loader2 class="mr-1 inline size-3 animate-spin" /> Waiting for opponent...
                    </p>
                    <p v-else-if="!myStepDone('master')" class="mb-4 text-xs text-muted-foreground">Choose the master for your crew.</p>
                    <p v-else class="mb-4 text-xs text-muted-foreground">Choose the master for the opponent.</p>

                    <template v-if="!myStepDone('master')">
                        <!-- Step 1: Choose master name -->
                        <div v-if="!masterHasMultipleTitles" class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            <Card
                                v-for="master in availableMasters"
                                :key="master.name"
                                class="cursor-pointer transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md hover:ring-1 hover:ring-primary/50"
                                :class="selectedMasterName === master.name ? 'ring-2 ring-primary' : ''"
                                @click="selectMasterName(master.name)"
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
                                            {{ master.titles.length }} titles
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>

                        <!-- Step 2: Choose title (if multiple) -->
                        <div v-else>
                            <button class="mb-3 text-xs text-primary hover:underline" @click="selectedMasterName = null">
                                &larr; Back to masters
                            </button>
                            <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                                <Card
                                    v-for="title in selectedMasterInfo!.titles"
                                    :key="title.id"
                                    class="cursor-pointer transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md hover:ring-1 hover:ring-primary/50"
                                    @click="selectMasterTitle(title)"
                                >
                                    <CardContent class="p-3">
                                        <span class="text-sm font-semibold">{{ title.display_name }}</span>
                                    </CardContent>
                                </Card>
                            </div>
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
                        <div v-if="!opponentMasterHasMultipleTitles" class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            <Card
                                v-for="master in opponentAvailableMasters"
                                :key="master.name"
                                class="cursor-pointer transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md hover:ring-1 hover:ring-primary/50"
                                :class="selectedOpponentMasterName === master.name ? 'ring-2 ring-primary' : ''"
                                @click="selectOpponentMasterName(master.name)"
                            >
                                <CardContent class="flex items-start gap-3 p-3">
                                    <div v-if="master.front_image" class="shrink-0 overflow-hidden rounded-md">
                                        <img :src="'/storage/' + master.front_image" :alt="master.name" class="size-16 object-cover object-top" loading="lazy" decoding="async" />
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <span class="text-sm font-semibold">{{ master.name }}</span>
                                        <div v-if="master.titles.length > 1" class="mt-0.5 text-[10px] text-muted-foreground">{{ master.titles.length }} titles</div>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>
                        <div v-else>
                            <button class="mb-3 text-xs text-primary hover:underline" @click="selectedOpponentMasterName = null">&larr; Back to masters</button>
                            <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                                <Card
                                    v-for="title in selectedOpponentMasterInfo!.titles"
                                    :key="title.id"
                                    class="cursor-pointer transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md hover:ring-1 hover:ring-primary/50"
                                    @click="selectOpponentMasterTitle(title)"
                                >
                                    <CardContent class="p-3">
                                        <span class="text-sm font-semibold">{{ title.display_name }}</span>
                                    </CardContent>
                                </Card>
                            </div>
                        </div>
                    </template>
                </CardContent>
            </Card>

            <!-- ═══ CREW SELECT ═══ -->
            <Card v-if="game.status === 'crew_select'" class="mb-6">
                <CardContent class="p-4 sm:p-6">
                    <h2 class="mb-1 font-semibold">{{ isSolo && myStepDone('crew') ? "Opponent's Crew" : 'Select Your Crew' }}</h2>
                    <p v-if="myStepDone('crew') && !isSolo" class="mb-4 text-xs text-muted-foreground">
                        <Loader2 class="mr-1 inline size-3 animate-spin" /> Waiting for opponent...
                    </p>
                    <template v-else>
                        <p class="mb-2 text-xs text-muted-foreground">
                            Choose a saved crew for <strong class="text-foreground">{{ myPlayer?.master_name }}</strong> or
                            <Link :href="newCrewUrl" class="text-primary underline">create a new one</Link>.
                        </p>
                        <div v-if="masterTitleOptions.length > 1" class="mb-4 flex flex-wrap items-center gap-1.5">
                            <span class="text-[11px] text-muted-foreground">Title:</span>
                            <button
                                v-for="title in masterTitleOptions"
                                :key="title.id"
                                class="rounded-md border px-2 py-0.5 text-[11px] transition-colors"
                                :class="myPlayer?.master_id === title.id ? 'border-primary bg-primary/10 font-medium' : 'hover:bg-muted'"
                                @click="switchMasterTitle(title)"
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
                                                @click="postSetup(route('games.setup.crew', game.uuid), { crew_build_id: crew.id })"
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
                            Optionally select a saved crew for the opponent, or skip to track points only.
                        </p>
                        <div v-if="matchingCrews.length" class="mb-3 grid gap-2.5 sm:grid-cols-2">
                            <div v-for="crew in matchingCrews" :key="crew.id">
                                <Card
                                    class="cursor-pointer transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md"
                                    :class="crew.is_over_budget ? 'border-destructive/50' : ''"
                                    @click="postSetup(route('games.setup.crew', game.uuid), { crew_build_id: crew.id, slot: 2 })"
                                >
                                    <CardContent class="flex items-start gap-3 p-3">
                                        <FactionLogo :faction="crew.faction" class-name="size-7 shrink-0 mt-0.5" />
                                        <div class="min-w-0 flex-1">
                                            <p class="truncate text-sm font-medium">{{ crew.name }}</p>
                                            <div class="mt-1 flex flex-wrap items-center gap-1">
                                                <Badge variant="secondary" class="text-[10px]">{{ crew.master_name }}</Badge>
                                                <Badge variant="secondary" class="text-[10px]">{{ crew.encounter_size }}ss</Badge>
                                                <Badge v-if="crew.is_over_budget" variant="destructive" class="text-[10px]">Over Budget</Badge>
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>
                            </div>
                        </div>
                        <Button variant="outline" class="w-full" @click="skipOpponentCrew">
                            Skip Opponent Crew
                        </Button>
                    </template>
                </CardContent>
            </Card>

            <!-- ═══ SCHEME SELECT ═══ -->
            <Card v-if="game.status === 'scheme_select'" class="mb-6">
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
                        <div class="grid gap-3 sm:grid-cols-3">
                            <Card v-for="scheme in schemes" :key="scheme.id" class="overflow-hidden">
                                <div class="cursor-pointer" @click="openSchemeDrawer(scheme)">
                                    <img v-if="scheme.image_url" :src="scheme.image_url" :alt="scheme.name" class="w-full" loading="lazy" decoding="async" />
                                    <CardContent v-else class="p-3">
                                        <div class="mb-1 text-sm font-semibold">{{ scheme.name }}</div>
                                        <p v-if="scheme.prerequisite" class="text-[11px] text-muted-foreground line-clamp-3">{{ scheme.prerequisite }}</p>
                                    </CardContent>
                                </div>
                                <div class="border-t p-2">
                                    <Button class="w-full" size="sm" :disabled="submitting" @click="postSetup(route('games.setup.scheme', game.uuid), { scheme_id: scheme.id })">
                                        <Loader2 v-if="submitting" class="mr-2 size-4 animate-spin" />
                                        Select {{ scheme.name }}
                                    </Button>
                                </div>
                            </Card>
                        </div>
                    </template>
                    <div v-else class="py-4 text-center text-sm text-muted-foreground">
                        <Check class="inline size-5 text-green-500" /> Scheme selected
                    </div>
                </CardContent>
            </Card>

            <!-- ═══ IN PROGRESS ═══ -->
            <template v-if="game.status === 'in_progress'">
                <!-- Mobile: Tab switcher -->
                <div class="mb-4 lg:hidden">
                    <Tabs v-model="gameplayTab">
                        <TabsList class="grid w-full grid-cols-3">
                            <TabsTrigger value="scenario">Game</TabsTrigger>
                            <TabsTrigger value="my-crew">My Crew</TabsTrigger>
                            <TabsTrigger value="opponent">Opponent</TabsTrigger>
                        </TabsList>
                    </Tabs>
                </div>

                <!-- Desktop: 3-column grid / Mobile: tab content -->
                <div class="grid gap-4 lg:grid-cols-3">
                    <!-- Column 1: Scenario Info -->
                    <div :class="gameplayTab !== 'scenario' ? 'hidden lg:block' : ''">
                        <Card>
                            <CardContent class="space-y-4 p-4">
                                <div class="text-center text-2xl font-bold">Turn {{ game.current_turn }} <span class="text-base font-normal text-muted-foreground">/ {{ game.max_turns }}</span></div>

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

                                <!-- Current scheme -->
                                <div v-if="myPlayer?.current_scheme_id" class="rounded-lg border border-primary/30 bg-primary/5 p-2 text-center">
                                    <div class="flex items-center justify-center gap-1.5">
                                        <span class="text-[10px] uppercase text-muted-foreground">Your Scheme</span>
                                        <button class="rounded p-0.5 text-muted-foreground hover:text-foreground" @click="schemeHidden = !schemeHidden">
                                            <component :is="schemeHidden ? EyeOff : Eye" class="size-3" />
                                        </button>
                                    </div>
                                    <div v-if="schemeHidden" class="text-sm font-medium text-muted-foreground">Hidden</div>
                                    <div v-else class="text-sm font-medium">{{ schemes.find((s) => s.id === myPlayer?.current_scheme_id)?.name }}</div>
                                </div>

                                <!-- Reference: Strategy, Scheme Details, Scheme Pool -->
                                <details class="rounded-lg border">
                                    <summary class="cursor-pointer px-3 py-2 text-xs font-medium text-muted-foreground hover:text-foreground">
                                        View Strategy &amp; Schemes
                                    </summary>
                                    <div class="space-y-3 border-t px-3 py-3">
                                        <!-- Strategy -->
                                        <div v-if="game.strategy" class="cursor-pointer rounded-md border p-2 transition-colors hover:bg-muted/50" @click="strategyDrawerOpen = true">
                                            <div class="text-[10px] uppercase text-muted-foreground">Strategy</div>
                                            <div class="text-sm font-medium">{{ game.strategy.name }}</div>
                                        </div>

                                        <!-- Current Scheme Detail -->
                                        <div v-if="myPlayer?.current_scheme_id && !schemeHidden">
                                            <div class="text-[10px] uppercase text-muted-foreground">Current Scheme</div>
                                            <div
                                                class="mt-1 cursor-pointer rounded-md border border-primary/30 bg-primary/5 p-2 transition-colors hover:bg-primary/10"
                                                @click="openSchemeDrawer(schemes.find((s) => s.id === myPlayer?.current_scheme_id)!)"
                                            >
                                                <div class="text-sm font-medium">{{ schemes.find((s) => s.id === myPlayer?.current_scheme_id)?.name }}</div>
                                                <p v-if="schemes.find((s) => s.id === myPlayer?.current_scheme_id)?.prerequisite" class="mt-1 text-[11px] text-muted-foreground line-clamp-2">
                                                    {{ schemes.find((s) => s.id === myPlayer?.current_scheme_id)?.prerequisite }}
                                                </p>
                                            </div>
                                        </div>

                                        <!-- Full Scheme Pool -->
                                        <div>
                                            <div class="text-[10px] uppercase text-muted-foreground">Scheme Pool</div>
                                            <div class="mt-1 space-y-1">
                                                <div
                                                    v-for="scheme in schemes"
                                                    :key="'ref-' + scheme.id"
                                                    class="cursor-pointer rounded-md border p-2 transition-colors hover:bg-muted/50"
                                                    :class="!schemeHidden && myPlayer?.current_scheme_id === scheme.id ? 'border-primary/30 bg-primary/5' : ''"
                                                    @click="openSchemeDrawer(scheme)"
                                                >
                                                    <div class="flex items-center justify-between">
                                                        <span class="text-xs font-medium">{{ scheme.name }}</span>
                                                        <Badge v-if="!schemeHidden && myPlayer?.current_scheme_id === scheme.id" variant="outline" class="px-1 py-0 text-[9px]">Active</Badge>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </details>

                                <!-- Turn scoring -->
                                <template v-if="myPlayer?.is_turn_complete">
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

                                        <!-- Strategy points -->
                                        <div class="flex items-center justify-between">
                                            <span class="text-xs text-muted-foreground">Strategy VP</span>
                                            <div class="flex items-center gap-1.5">
                                                <button class="rounded border p-0.5 hover:bg-muted" @click="strategyPoints = Math.max(0, strategyPoints - 1)"><Minus class="size-3.5" /></button>
                                                <span class="w-6 text-center font-bold">{{ strategyPoints }}</span>
                                                <button class="rounded border p-0.5 hover:bg-muted" @click="strategyPoints = Math.min(5, strategyPoints + 1)"><Plus class="size-3.5" /></button>
                                            </div>
                                        </div>

                                        <!-- Scheme points -->
                                        <div class="flex items-center justify-between">
                                            <span class="text-xs text-muted-foreground">Scheme VP</span>
                                            <div class="flex items-center gap-1.5">
                                                <button class="rounded border p-0.5 hover:bg-muted" @click="schemePoints = Math.max(0, schemePoints - 1)"><Minus class="size-3.5" /></button>
                                                <span class="w-6 text-center font-bold">{{ schemePoints }}</span>
                                                <button class="rounded border p-0.5 hover:bg-muted" @click="schemePoints = Math.min(5, schemePoints + 1)"><Plus class="size-3.5" /></button>
                                            </div>
                                        </div>

                                        <!-- Next scheme (not on last turn) -->
                                        <div v-if="!isLastTurn">
                                            <div class="mb-1.5 text-xs text-muted-foreground">
                                                {{ currentSchemeScored ? 'Select next scheme:' : 'Keep current scheme or change:' }}
                                            </div>
                                            <div class="space-y-1">
                                                <button
                                                    v-if="!currentSchemeScored"
                                                    class="w-full rounded-md border px-2 py-1.5 text-left text-xs transition-colors"
                                                    :class="!nextSchemeId ? 'border-primary bg-primary/10 font-medium' : 'hover:bg-muted'"
                                                    @click="nextSchemeId = null"
                                                >
                                                    Keep Current
                                                </button>
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
                                                    No next schemes available for your current scheme.
                                                </div>
                                            </div>
                                        </div>

                                        <Button class="w-full" size="sm" :disabled="scoringTurn || (!isLastTurn && currentSchemeScored && !nextSchemeId && next_schemes.length > 0)" @click="submitTurnScore">
                                            <Loader2 v-if="scoringTurn" class="mr-2 size-4 animate-spin" />
                                            Submit Turn ({{ strategyPoints + schemePoints }} VP)
                                        </Button>
                                    </div>
                                </template>

                                <!-- End game -->
                                <div class="border-t pt-3">
                                    <Button v-if="!myPlayer?.is_game_complete" variant="outline" size="sm" class="w-full text-xs" @click="markGameComplete">
                                        Mark Game Complete
                                    </Button>
                                    <span v-else class="text-xs text-muted-foreground">
                                        <Check class="mr-1 inline size-3 text-green-500" /> You've marked the game as complete
                                    </span>
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    <!-- Column 2: My Crew (editable) -->
                    <div :class="gameplayTab !== 'my-crew' ? 'hidden lg:block' : ''">
                        <div class="mb-2 flex items-center justify-between">
                            <h3 class="text-sm font-semibold">Your Crew</h3>
                            <div class="flex items-center gap-1">
                                <button class="rounded p-0.5 hover:bg-muted" @click="updateSoulstonePool(-1)"><Minus class="size-3" /></button>
                                <span class="flex items-center gap-0.5 text-xs font-bold">
                                    {{ myPlayer?.soulstone_pool ?? 0 }}<GameIcon type="soulstone" class-name="h-3 inline-block" />
                                </span>
                                <button class="rounded p-0.5 hover:bg-muted" @click="updateSoulstonePool(1)"><Plus class="size-3" /></button>
                            </div>
                        </div>
                        <!-- Crew Upgrades -->
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
                                :class="factionBackground(myPlayer?.faction ?? '')"
                                class="rounded-md border border-white/20 px-2 py-1.5 text-white"
                                :style="member.is_activated ? 'opacity: 0.5' : ''"
                            >
                                <div class="flex items-center justify-between">
                                    <div class="flex min-w-0 flex-1 items-center gap-1">
                                        <button class="shrink-0 rounded p-0.5 hover:bg-white/20" @click="toggleActivated(member)" :title="member.is_activated ? 'Mark unactivated' : 'Mark activated'">
                                            <Check v-if="member.is_activated" class="size-3.5 text-green-300" />
                                            <Circle v-else class="size-3.5 text-white/30" />
                                        </button>
                                        <span class="cursor-pointer truncate text-sm font-semibold hover:underline" @click="openMemberPreview(member)">{{ member.display_name }}</span>
                                    </div>
                                    <div class="flex items-center gap-0.5">
                                        <button class="rounded p-0.5 text-cyan-300 hover:bg-white/20" title="Tokens" @click="openTokenDialog(member)"><Plus class="size-3" /></button>
                                        <button class="rounded p-0.5 hover:bg-white/20" @click="updateHealth(member, -1)"><Minus class="size-3" /></button>
                                        <span class="flex items-center gap-0.5 text-xs font-bold">
                                            <Heart class="size-3" :class="member.current_health <= Math.ceil(member.max_health / 2) ? 'text-red-300' : ''" />
                                            {{ member.current_health }}/{{ member.max_health }}
                                        </span>
                                        <button class="rounded p-0.5 hover:bg-white/20" @click="updateHealth(member, 1)"><Plus class="size-3" /></button>
                                        <button class="ml-0.5 rounded p-0.5 text-red-300 hover:bg-red-500/20" @click="killMember(member)"><Skull class="size-3" /></button>
                                    </div>
                                </div>
                                <!-- Token badges -->
                                <div v-if="member.attached_tokens?.length" class="mt-1 flex flex-wrap gap-1">
                                    <Badge
                                        v-for="token in member.attached_tokens"
                                        :key="token.key ?? token.id"
                                        variant="secondary"
                                        class="gap-0.5 bg-white/20 px-1 py-0 text-[9px] text-white"
                                    >
                                        {{ token.name }}
                                        <span v-if="token.count > 1" class="font-bold">x{{ token.count }}</span>
                                    </Badge>
                                </div>
                            </div>
                            <div
                                v-for="member in myKilledMembers"
                                :key="'killed-' + member.id"
                                class="flex items-center justify-between rounded-md bg-muted/50 px-2 py-1 text-xs text-muted-foreground line-through opacity-50"
                            >
                                <span class="cursor-pointer hover:underline" @click="openMemberPreview(member)">{{ member.display_name }}</span>
                                <button class="rounded p-0.5 text-green-600 hover:bg-green-500/20" @click="reviveMember(member)"><RotateCcw class="size-3" /></button>
                            </div>
                        </div>
                        <Button variant="outline" size="sm" class="mt-2 w-full gap-1 text-xs" @click="openSummonForSlot(1)">
                            <Plus class="size-3" /> Summon
                        </Button>
                    </div>

                    <!-- Column 3: Opponent Crew -->
                    <div :class="gameplayTab !== 'opponent' ? 'hidden lg:block' : ''">
                        <div class="mb-2 flex items-center justify-between">
                            <h3 class="text-sm font-semibold">{{ playerName(opponent) }}</h3>
                            <div class="flex items-center gap-1">
                                <template v-if="isSolo">
                                    <button class="rounded p-0.5 hover:bg-muted" @click="updateOpponentSoulstonePool(-1)"><Minus class="size-3" /></button>
                                </template>
                                <span class="flex items-center gap-0.5 text-xs font-bold" :class="!isSolo ? 'text-muted-foreground' : ''">
                                    {{ opponent?.soulstone_pool ?? 0 }}<GameIcon type="soulstone" class-name="h-3 inline-block" />
                                </span>
                                <template v-if="isSolo">
                                    <button class="rounded p-0.5 hover:bg-muted" @click="updateOpponentSoulstonePool(1)"><Plus class="size-3" /></button>
                                </template>
                            </div>
                        </div>

                        <!-- Solo: Opponent scheme management -->
                        <div v-if="isSolo" class="mb-2">
                            <div class="rounded-md border border-dashed p-2 text-center text-xs">
                                <template v-if="opponent?.current_scheme_id && opponentSchemeRevealed">
                                    <span class="text-muted-foreground">Scheme:</span>
                                    <span class="ml-1 font-medium">{{ schemes.find((s) => s.id === opponent?.current_scheme_id)?.name }}</span>
                                    <button class="ml-1 text-muted-foreground hover:text-foreground" @click="opponentSchemeRevealed = false"><EyeOff class="inline size-3" /></button>
                                </template>
                                <template v-else-if="opponent?.current_scheme_id">
                                    <span class="text-muted-foreground">Scheme: Hidden</span>
                                    <button class="ml-1 text-primary hover:underline" @click="opponentSchemeRevealed = true">Reveal</button>
                                </template>
                                <button class="ml-2 text-primary hover:underline" @click="opponentSchemeDialogOpen = true">
                                    {{ opponent?.current_scheme_id ? 'Change' : 'Set Scheme' }}
                                </button>
                            </div>
                        </div>

                        <!-- Opponent faction/master info (when no crew) -->
                        <div v-if="isSolo && opponent?.crew_skipped && !opponentCrewMembers.length" class="mb-3 flex items-center gap-2 rounded-md border border-dashed p-2">
                            <FactionLogo v-if="opponent?.faction" :faction="opponent.faction" class-name="size-6" />
                            <div class="min-w-0 text-xs">
                                <div v-if="opponent?.master_name" class="font-medium">{{ opponent.master_name }}</div>
                                <div v-if="opponent?.role" class="capitalize text-muted-foreground">{{ opponent.role }}</div>
                            </div>
                        </div>

                        <!-- Opponent Crew Upgrades -->
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
                                :class="factionBackground(opponent?.faction ?? '')"
                                class="rounded-md border border-white/20 px-2 py-1.5 text-white"
                                :style="member.is_activated ? 'opacity: 0.5' : ''"
                            >
                                <div class="flex items-center justify-between">
                                    <div class="flex min-w-0 flex-1 items-center gap-1">
                                        <!-- Solo: full activation toggle; Normal: read-only indicator -->
                                        <template v-if="isSolo">
                                            <button class="shrink-0 rounded p-0.5 hover:bg-white/20" @click="toggleActivated(member)">
                                                <Check v-if="member.is_activated" class="size-3.5 text-green-300" />
                                                <Circle v-else class="size-3.5 text-white/30" />
                                            </button>
                                        </template>
                                        <template v-else>
                                            <Check v-if="member.is_activated" class="size-3.5 shrink-0 text-green-300" />
                                            <Circle v-else class="size-3.5 shrink-0 text-white/30" />
                                        </template>
                                        <span class="cursor-pointer truncate text-sm font-semibold hover:underline" @click="openMemberPreview(member)">{{ member.display_name }}</span>
                                    </div>
                                    <!-- Solo: full health controls; Normal: read-only -->
                                    <div v-if="isSolo" class="flex items-center gap-0.5">
                                        <button class="rounded p-0.5 text-cyan-300 hover:bg-white/20" title="Tokens" @click="openTokenDialog(member)"><Plus class="size-3" /></button>
                                        <button class="rounded p-0.5 hover:bg-white/20" @click="updateHealth(member, -1)"><Minus class="size-3" /></button>
                                        <span class="flex items-center gap-0.5 text-xs font-bold">
                                            <Heart class="size-3" :class="member.current_health <= Math.ceil(member.max_health / 2) ? 'text-red-300' : ''" />
                                            {{ member.current_health }}/{{ member.max_health }}
                                        </span>
                                        <button class="rounded p-0.5 hover:bg-white/20" @click="updateHealth(member, 1)"><Plus class="size-3" /></button>
                                        <button class="ml-0.5 rounded p-0.5 text-red-300 hover:bg-red-500/20" @click="killMember(member)"><Skull class="size-3" /></button>
                                    </div>
                                    <span v-else class="flex items-center gap-0.5 text-xs font-bold">
                                        <Heart class="size-3" :class="member.current_health <= Math.ceil(member.max_health / 2) ? 'text-red-300' : ''" />
                                        {{ member.current_health }}/{{ member.max_health }}
                                    </span>
                                </div>
                                <!-- Token badges -->
                                <div v-if="member.attached_tokens?.length" class="mt-1 flex flex-wrap gap-1">
                                    <Badge
                                        v-for="token in member.attached_tokens"
                                        :key="token.key ?? token.id"
                                        variant="secondary"
                                        class="gap-0.5 bg-white/20 px-1 py-0 text-[9px] text-white"
                                    >
                                        {{ token.name }}
                                        <span v-if="token.count > 1" class="font-bold">x{{ token.count }}</span>
                                    </Badge>
                                </div>
                            </div>
                            <div
                                v-for="member in opponentKilledMembers"
                                :key="'killed-' + member.id"
                                class="flex items-center justify-between rounded-md bg-muted/50 px-2 py-1 text-xs text-muted-foreground line-through opacity-50"
                            >
                                <span class="cursor-pointer hover:underline" @click="openMemberPreview(member)">{{ member.display_name }}</span>
                                <button v-if="isSolo" class="rounded p-0.5 text-green-600 hover:bg-green-500/20" @click="reviveMember(member)"><RotateCcw class="size-3" /></button>
                            </div>
                        </div>
                        <!-- Solo: summon for opponent -->
                        <Button v-if="isSolo && opponentCrewMembers.length" variant="outline" size="sm" class="mt-2 w-full gap-1 text-xs" @click="openSummonForSlot(2)">
                            <Plus class="size-3" /> Summon
                        </Button>

                        <!-- Solo: Opponent turn scoring -->
                        <template v-if="isSolo">
                            <div class="mt-4 space-y-3 border-t pt-3">
                                <div class="text-xs font-semibold">Opponent's Turn {{ game.current_turn }}</div>
                                <template v-if="opponent?.is_turn_complete">
                                    <div class="py-2 text-center text-xs text-green-600"><Check class="mr-1 inline size-3" /> Opponent turn submitted</div>
                                </template>
                                <template v-else>
                                    <div class="flex items-center justify-between">
                                        <span class="text-xs text-muted-foreground">Strategy VP</span>
                                        <div class="flex items-center gap-1.5">
                                            <button class="rounded border p-0.5 hover:bg-muted" @click="opponentStrategyPoints = Math.max(0, opponentStrategyPoints - 1)"><Minus class="size-3.5" /></button>
                                            <span class="w-6 text-center font-bold">{{ opponentStrategyPoints }}</span>
                                            <button class="rounded border p-0.5 hover:bg-muted" @click="opponentStrategyPoints = Math.min(5, opponentStrategyPoints + 1)"><Plus class="size-3.5" /></button>
                                        </div>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <span class="text-xs text-muted-foreground">Scheme VP</span>
                                        <div class="flex items-center gap-1.5">
                                            <button class="rounded border p-0.5 hover:bg-muted" @click="opponentSchemePoints = Math.max(0, opponentSchemePoints - 1)"><Minus class="size-3.5" /></button>
                                            <span class="w-6 text-center font-bold">{{ opponentSchemePoints }}</span>
                                            <button class="rounded border p-0.5 hover:bg-muted" @click="opponentSchemePoints = Math.min(5, opponentSchemePoints + 1)"><Plus class="size-3.5" /></button>
                                        </div>
                                    </div>
                                    <!-- Opponent next scheme -->
                                    <div v-if="!isLastTurn && opponent_next_schemes.length">
                                        <div class="mb-1.5 text-xs text-muted-foreground">Opponent's next scheme:</div>
                                        <div class="space-y-1">
                                            <button
                                                class="w-full rounded-md border px-2 py-1.5 text-left text-xs transition-colors"
                                                :class="!opponentNextSchemeId ? 'border-primary bg-primary/10 font-medium' : 'hover:bg-muted'"
                                                @click="opponentNextSchemeId = null"
                                            >Keep Current</button>
                                            <button
                                                v-for="scheme in opponent_next_schemes"
                                                :key="scheme.id"
                                                class="w-full rounded-md border px-2 py-1.5 text-left text-xs transition-colors"
                                                :class="opponentNextSchemeId === scheme.id ? 'border-primary bg-primary/10 font-medium' : 'hover:bg-muted'"
                                                @click="opponentNextSchemeId = scheme.id"
                                            >{{ scheme.name }}</button>
                                        </div>
                                    </div>
                                    <Button class="w-full" size="sm" :disabled="scoringTurn" @click="submitOpponentTurnScore">
                                        <Loader2 v-if="scoringTurn" class="mr-2 size-4 animate-spin" />
                                        Submit Opponent ({{ opponentStrategyPoints + opponentSchemePoints }} VP)
                                    </Button>
                                </template>
                            </div>
                        </template>
                    </div>
                </div>
            </template>

            <!-- ═══ COMPLETED / ABANDONED ═══ -->
            <template v-if="game.status === 'completed' || game.status === 'abandoned'">
                <!-- Result banner -->
                <Card class="mb-6 overflow-hidden">
                    <CardContent class="p-0">
                        <div
                            class="p-6 text-center"
                            :class="game.status === 'abandoned' ? 'bg-muted' : game.is_tie ? 'bg-muted' : 'bg-amber-500/10'"
                        >
                            <div v-if="game.status === 'abandoned'" class="text-lg font-bold text-muted-foreground">Game Abandoned</div>
                            <div v-else-if="game.is_tie" class="text-lg font-bold">Draw!</div>
                            <div v-else-if="game.winner" class="text-lg font-bold text-amber-700 dark:text-amber-400">{{ game.winner.name }} Wins!</div>
                            <div v-else-if="isSolo && game.winner_slot" class="text-lg font-bold text-amber-700 dark:text-amber-400">
                                {{ game.winner_slot === 1 ? playerName(myPlayer) : playerName(opponentPlayer) }} Wins!
                            </div>
                        </div>

                        <!-- Final scores -->
                        <div class="grid grid-cols-2 divide-x border-t">
                            <div v-for="player in game.players" :key="'final-' + player.id" class="p-4 text-center">
                                <div class="flex items-center justify-center gap-2">
                                    <FactionLogo v-if="player.faction" :faction="player.faction" class-name="size-5" />
                                    <span class="text-sm font-medium">{{ playerName(player) }}</span>
                                </div>
                                <div class="mt-2 text-3xl font-bold" :class="(game.winner?.id === player.user?.id && game.winner) || (isSolo && game.winner_slot === player.slot) ? 'text-amber-600 dark:text-amber-400' : ''">
                                    {{ player.total_points }}
                                </div>
                                <div class="mt-1 text-xs text-muted-foreground">VP</div>
                                <Badge v-if="player.role" variant="outline" class="mt-2 px-1 py-0 text-[9px] capitalize">{{ player.role }}</Badge>
                                <div v-if="player.master_name" class="mt-1 text-xs text-muted-foreground">{{ player.master_name }}</div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Turn-by-turn breakdown -->
                <Card v-if="game.players[0]?.turns?.length" class="mb-6">
                    <CardContent class="p-4">
                        <h3 class="mb-3 text-sm font-semibold">Turn-by-Turn Scoring</h3>
                        <div class="overflow-auto">
                            <table class="w-full text-xs">
                                <thead>
                                    <tr class="border-b">
                                        <th class="py-1.5 text-left font-medium text-muted-foreground">Turn</th>
                                        <th v-for="player in game.players" :key="'th-' + player.id" class="py-1.5 text-center font-medium text-muted-foreground" colspan="2">
                                            {{ playerName(player) }}
                                        </th>
                                    </tr>
                                    <tr class="border-b text-[10px]">
                                        <th></th>
                                        <template v-for="player in game.players" :key="'sub-' + player.id">
                                            <th class="py-1 text-center text-muted-foreground">Strat</th>
                                            <th class="py-1 text-center text-muted-foreground">Scheme</th>
                                        </template>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="turn in Math.max(...game.players.map((p: any) => p.turns?.length ?? 0))" :key="turn" class="border-b last:border-0">
                                        <td class="py-1.5 font-medium">{{ turn }}</td>
                                        <template v-for="player in game.players" :key="'turn-' + player.id + '-' + turn">
                                            <td class="py-1.5 text-center">
                                                {{ player.turns?.find((t: any) => t.turn_number === turn)?.strategy_points ?? '-' }}
                                            </td>
                                            <td class="py-1.5 text-center">
                                                {{ player.turns?.find((t: any) => t.turn_number === turn)?.scheme_points ?? '-' }}
                                            </td>
                                        </template>
                                    </tr>
                                    <tr class="border-t font-bold">
                                        <td class="py-1.5">Total</td>
                                        <template v-for="player in game.players" :key="'total-' + player.id">
                                            <td class="py-1.5 text-center" colspan="2">{{ player.total_points }}</td>
                                        </template>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>

                <!-- Final crew states -->
                <div class="mb-6 grid gap-4 sm:grid-cols-2">
                    <div v-for="player in game.players" :key="'crew-' + player.id">
                        <h3 class="mb-2 text-sm font-semibold">{{ playerName(player) }}'s Crew</h3>
                        <!-- Faction/master info when no crew members -->
                        <div v-if="!player.crew_members?.length && player.faction" class="flex items-center gap-2 rounded-md border border-dashed p-3">
                            <FactionLogo :faction="player.faction" class-name="size-6" />
                            <div class="text-xs">
                                <div v-if="player.master_name" class="font-medium">{{ player.master_name }}</div>
                                <span class="text-muted-foreground">No crew tracked</span>
                            </div>
                        </div>
                        <div v-else class="space-y-1">
                            <div
                                v-for="member in player.crew_members"
                                :key="member.id"
                                :class="factionBackground(player.faction ?? '')"
                                class="rounded-md border border-white/20 px-2 py-1.5 text-white"
                                :style="member.is_killed ? 'opacity: 0.4; text-decoration: line-through' : ''"
                            >
                                <div class="flex items-center justify-between">
                                    <span
                                        class="min-w-0 flex-1 truncate text-sm font-semibold"
                                        :class="member.front_image ? 'cursor-pointer hover:underline' : ''"
                                        @click="openMemberPreview(member)"
                                    >
                                        {{ member.display_name }}
                                    </span>
                                    <span class="flex items-center gap-0.5 text-xs font-bold">
                                        <Heart class="size-3" :class="member.is_killed ? 'text-red-400' : member.current_health <= Math.ceil(member.max_health / 2) ? 'text-red-300' : ''" />
                                        {{ member.current_health }}/{{ member.max_health }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </template>

            <!-- Actions -->
            <div v-if="game.status !== 'completed' && game.status !== 'abandoned'" class="flex justify-end">
                <Button variant="destructive" size="sm" @click="abandonDialogOpen = true">Abandon Game</Button>
            </div>
        </div>
    </div>

    <!-- Strategy Drawer -->
    <Drawer v-model:open="strategyDrawerOpen">
        <DrawerContent>
            <div v-if="game.strategy" class="mx-auto w-full max-w-sm">
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
            <div v-if="deployment" class="mx-auto w-full max-w-sm">
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
            <div v-if="activeScheme" class="mx-auto w-full max-w-sm">
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
            <div v-if="previewMember" class="mx-auto w-full max-w-sm">
                <DrawerHeader class="pb-2">
                    <DrawerTitle class="text-center">{{ previewMember.display_name }}</DrawerTitle>
                </DrawerHeader>
                <div class="flex min-h-0 flex-1 items-start justify-center px-4 pb-2 [&_img]:max-h-[55dvh] [&_img]:w-auto [&_img]:object-contain">
                    <CharacterCardView
                        v-if="previewMember.front_image"
                        :miniature="{ id: previewMember.id, display_name: previewMember.display_name, slug: '', front_image: previewMember.front_image, back_image: previewMember.back_image }"
                        :show-link="false"
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

    <!-- Crew Upgrade Preview Drawer -->
    <Drawer v-model:open="upgradeDrawerOpen">
        <DrawerContent>
            <div v-if="previewUpgrade" class="mx-auto w-full max-w-sm">
                <DrawerHeader class="pb-2">
                    <DrawerTitle class="text-center">{{ previewUpgrade.name }}</DrawerTitle>
                    <div class="mt-1 text-center text-xs text-muted-foreground">Crew Upgrade</div>
                </DrawerHeader>
                <div class="flex min-h-0 flex-1 items-start justify-center px-4 pb-2 [&_img]:max-h-[55dvh] [&_img]:w-auto [&_img]:object-contain">
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

    <!-- Opponent Scheme Dialog (Solo) -->
    <Dialog v-if="isSolo" v-model:open="opponentSchemeDialogOpen">
        <DialogContent class="max-w-sm">
            <DialogHeader>
                <DialogTitle>Set Opponent's Scheme</DialogTitle>
                <DialogDescription>Select a scheme from the pool or next schemes.</DialogDescription>
            </DialogHeader>
            <div class="space-y-1">
                <button
                    v-for="scheme in [...schemes, ...opponent_next_schemes.filter((ns) => !schemes.find((s) => s.id === ns.id))]"
                    :key="'opp-scheme-' + scheme.id"
                    class="flex w-full items-center justify-between rounded-md px-3 py-2 text-left text-sm hover:bg-accent"
                    :class="opponent?.current_scheme_id === scheme.id ? 'bg-primary/10 font-medium' : ''"
                    @click="setOpponentScheme(scheme.id)"
                >
                    {{ scheme.name }}
                    <Badge v-if="opponent?.current_scheme_id === scheme.id" variant="outline" class="text-[9px]">Current</Badge>
                </button>
            </div>
        </DialogContent>
    </Dialog>

    <!-- Summon Dialog -->
    <Dialog v-model:open="summonDialogOpen">
        <DialogContent class="max-w-sm">
            <DialogHeader>
                <DialogTitle>Summon Character</DialogTitle>
                <DialogDescription>Search for a character to summon into your crew.</DialogDescription>
            </DialogHeader>
            <Input
                :model-value="summonSearch"
                placeholder="Search characters..."
                @update:model-value="searchSummon($event as string)"
            />
            <div class="max-h-60 space-y-1 overflow-y-auto">
                <div v-if="summonLoading" class="flex justify-center py-4">
                    <Loader2 class="size-5 animate-spin text-muted-foreground" />
                </div>
                <template v-else-if="summonResults.length">
                    <button
                        v-for="char in summonResults"
                        :key="char.id"
                        class="flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-left text-sm hover:bg-accent"
                        @click="summonCharacter(char.id)"
                    >
                        <img v-if="char.front_image" :src="char.front_image" class="size-8 rounded object-cover" />
                        <div class="min-w-0 flex-1">
                            <div class="truncate font-medium">{{ char.display_name ?? char.name }}</div>
                            <div v-if="char.station" class="text-xs text-muted-foreground capitalize">{{ char.station }}</div>
                        </div>
                    </button>
                </template>
                <div v-else-if="summonSearch.length >= 2" class="py-4 text-center text-sm text-muted-foreground">No characters found</div>
            </div>
        </DialogContent>
    </Dialog>

    <!-- Token/Marker Dialog -->
    <Dialog v-model:open="tokenDialogOpen">
        <DialogContent class="max-w-sm">
            <DialogHeader>
                <DialogTitle>Manage Tokens</DialogTitle>
                <DialogDescription v-if="tokenMember">{{ tokenMember.display_name }}</DialogDescription>
            </DialogHeader>
            <!-- Current tokens -->
            <div v-if="tokenMember?.attached_tokens?.length" class="space-y-1">
                <div class="text-xs font-medium text-muted-foreground">Current Tokens</div>
                <div class="flex flex-wrap gap-1">
                    <Badge
                        v-for="token in tokenMember.attached_tokens"
                        :key="'current-' + (token.key ?? token.id)"
                        variant="secondary"
                        class="cursor-pointer gap-1 pr-1"
                        @click="removeToken(token.key ?? `token-${token.id}`)"
                    >
                        {{ token.name }}
                        <span v-if="token.count > 1" class="font-bold">x{{ token.count }}</span>
                        <Minus class="size-3 text-red-400" />
                    </Badge>
                </div>
            </div>
            <!-- Available tokens -->
            <div>
                <div class="mb-1 text-xs font-medium text-muted-foreground">Add Token</div>
                <Input v-model="tokenSearch" placeholder="Filter tokens..." class="mb-2" />
                <div class="max-h-40 space-y-0.5 overflow-y-auto">
                    <button
                        v-for="token in props.tokens.filter((t) => !tokenSearch || t.name.toLowerCase().includes(tokenSearch.toLowerCase()))"
                        :key="token.id"
                        class="flex w-full items-center gap-2 rounded px-2 py-1 text-left text-sm hover:bg-accent"
                        @click="addToken(token.id, token.name)"
                    >
                        <Plus class="size-3 shrink-0 text-green-500" />
                        {{ token.name }}
                    </button>
                </div>
            </div>
            <!-- Available markers -->
            <div v-if="props.markers.length">
                <div class="mb-1 text-xs font-medium text-muted-foreground">Add Marker</div>
                <div class="max-h-40 space-y-0.5 overflow-y-auto">
                    <button
                        v-for="marker in props.markers.filter((m) => !tokenSearch || m.name.toLowerCase().includes(tokenSearch.toLowerCase()))"
                        :key="'m-' + marker.id"
                        class="flex w-full items-center gap-2 rounded px-2 py-1 text-left text-sm hover:bg-accent"
                        @click="addToken(marker.id, marker.name, 'marker')"
                    >
                        <Plus class="size-3 shrink-0 text-blue-500" />
                        {{ marker.name }}
                    </button>
                </div>
            </div>
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
</template>
