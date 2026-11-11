<script setup lang="ts">
import AllegianceLogo from '@/components/AllegianceLogo.vue';
import DeclarationFields from '@/components/Tournament/DeclarationFields.vue';
import TeamForm from '@/components/Tournament/TeamForm.vue';
import FactionLogo from '@/components/FactionLogo.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Check, ChevronsUpDown, Link2, Link2Off, Loader2, LogOut, MoreHorizontal, Pencil, Plus, Star, Users, X } from 'lucide-vue-next';
import {
    Combobox,
    ComboboxAnchor,
    ComboboxEmpty,
    ComboboxGroup,
    ComboboxInput,
    ComboboxItem,
    ComboboxList,
    ComboboxSeparator,
    ComboboxTrigger,
} from '@/components/ui/combobox';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { TabsContent } from '@/components/ui/tabs';
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import { csrfToken } from '@/lib/utils';
import { useToast } from '@/composables/useToast';
import { useTournament } from '@/composables/useTournament';
import type { TeamMember, TeamMemberDraft, TournamentRuleset } from '@/types/tournament';

/**
 * The organizer's Players tab: add / edit / drop / disqualify / check in players, set metas, link accounts,
 * and turn RSVPs into players. Owns all of that form state and talks to the tournament API directly;
 * the page only hands it the tournament and (via `openRsvpPlayerDialog`) the RSVP the TO wants to register.
 */
interface MetaOption {
    id: number;
    name: string;
    slug?: string;
}

interface Rsvp {
    id: number;
    user_id: number;
    faction: string | null;
    allegiance_id?: number | null;
    allegiance?: { id: number; slug: string; name: string } | null;
    user: { id: number; name: string } | null;
}

interface TournamentPlayer {
    id: number;
    display_name: string;
    faction: string | null;
    checked_in_at?: string | null;
    allegiance_id?: number | null;
    allegiance?: { id: number; slug: string; name: string } | null;
    garrison?: { name: string; share_code?: string | null; is_public: boolean } | null;
    user: { id: number; name: string; meta?: MetaOption | null } | null;
    meta?: MetaOption | null;
    is_ringer: boolean;
    is_disqualified: boolean;
    dropped_after_round: number | null;
    /** Team events: the people on this team. */
    members?: TeamMember[];
}

// A fragment (the tab plus its dialogs): nothing should fall through onto the first root.
defineOptions({ inheritAttrs: false });

const props = defineProps<{
    tournament: {
        uuid: string;
        status: string;
        players: TournamentPlayer[];
        rsvps?: Rsvp[];
        team_size?: number;
        [key: string]: unknown;
    };
    ruleset: TournamentRuleset;
    factions: Record<string, { name: string; slug: string; color: string; logo: string }>;
    allegiances: { id: number; slug: string; name: string }[];
    /** player_id → names of opponents faced so far. */
    opponents: Record<number, string[]>;
    /** The organizer's accepted friends, offered as quick picks when linking an account. */
    friendOptions: { id: number; name: string }[];
}>();

const tournamentRef = computed(() => props.tournament);
const { submitting, doAction, doModalAction, reloadProps, factionBackground } = useTournament(tournamentRef);
const reloadPage = () => reloadProps(['tournament', 'standings']);

// What players declare at check-in (Malifaux faction vs TOS Allegiance + Garrison) comes from the ruleset.
const declaration = computed(() => props.ruleset.declaration);
const isDeclared = (faction: string | null, allegiance: string | null) =>
    declaration.value === 'none' || (declaration.value === 'allegiance' ? !!allegiance : !!faction);
const declarationPayload = (faction: string | null, allegiance: string | null, garrisonCode?: string) =>
    declaration.value === 'allegiance'
        ? {
              allegiance_id: allegiance ? Number(allegiance) : null,
              ...(garrisonCode === undefined ? {} : { garrison_share_code: garrisonCode.trim() }),
          }
        : { faction };
const declarationLabel = computed(() => (declaration.value === 'allegiance' ? 'allegiance' : 'faction'));

// ─── Team events: a team is one competitor made of `team_size` people, each declaring for themself ───
const teamSize = computed(() => Number(props.tournament.team_size ?? 1));
const isTeamEvent = computed(() => teamSize.value > 1);
const unitLabel = computed(() => (isTeamEvent.value ? 'team' : 'player'));

const blankMember = (): TeamMemberDraft => ({
    id: null,
    display_name: '',
    user_id: null,
    user_name: null,
    faction: null,
    allegiance: null,
    garrison_code: '',
    garrison_original: '',
});
const draftFrom = (m: TeamMember): TeamMemberDraft => ({
    id: m.id,
    display_name: m.display_name,
    user_id: m.user_id,
    user_name: m.user?.name ?? null,
    faction: m.faction,
    allegiance: m.allegiance_id ? String(m.allegiance_id) : null,
    garrison_code: m.garrison?.share_code ?? '',
    garrison_original: m.garrison?.share_code ?? '',
});
// Accounts that RSVPed and aren't on a team yet, offered when linking a member.
const availableUsers = computed(() => {
    const taken = new Set(
        props.tournament.players.flatMap((p) => [p.user?.id, ...(p.members ?? []).map((m) => m.user_id)]).filter((id): id is number => !!id),
    );
    return (props.tournament.rsvps ?? []).filter((r) => r.user && !taken.has(r.user_id)).map((r) => ({ id: r.user_id, name: r.user!.name }));
});
const takenUserIds = computed(() =>
    props.tournament.players.flatMap((p) => [p.user?.id, ...(p.members ?? []).map((m) => m.user_id)]).filter((id): id is number => !!id),
);
const memberPayload = (m: TeamMemberDraft) => ({
    ...(m.id ? { id: m.id } : {}),
    display_name: m.display_name.trim(),
    user_id: m.user_id,
    // A private Garrison's code is hidden from the payload, so only send it when the TO changed it.
    ...declarationPayload(m.faction, m.allegiance, m.garrison_code !== m.garrison_original ? m.garrison_code : undefined),
});
// A ringer team stands in for an odd field and needs no members; every other team needs a full, declared roster.
const isEmptyRoster = (members: TeamMemberDraft[]) => members.every((m) => !m.display_name.trim() && !m.user_id);
const membersReady = (members: TeamMemberDraft[], ringer = false) =>
    (ringer && isEmptyRoster(members)) ||
    (members.length === teamSize.value && members.every((m) => m.display_name.trim() && isDeclared(m.faction, m.allegiance)));
const newTeamName = ref('');
const newTeamIsRinger = ref(false);
const newTeamMembers = ref<TeamMemberDraft[]>(Array.from({ length: teamSize.value }, blankMember));
const addTeam = async () => {
    if (!newTeamName.value.trim() || !(newTeamIsRinger.value || membersReady(newTeamMembers.value))) return;
    submitting.value = true;
    const ok = await doAction(
        route('tournaments.players.add', props.tournament.uuid),
        'POST',
        newTeamIsRinger.value
            ? { display_name: newTeamName.value.trim(), is_ringer: true }
            : { display_name: newTeamName.value.trim(), members: newTeamMembers.value.map(memberPayload) },
    );
    submitting.value = false;
    if (ok) {
        newTeamName.value = '';
        newTeamIsRinger.value = false;
        newTeamMembers.value = Array.from({ length: teamSize.value }, blankMember);
        reloadPage();
    }
};

const teamDialogOpen = ref(false);
const teamEditing = ref<TournamentPlayer | null>(null);
const teamName = ref('');
const teamMembers = ref<TeamMemberDraft[]>([]);
const teamError = ref<string | null>(null);
const teamSaving = ref(false);
const openTeamDialog = (team: TournamentPlayer) => {
    teamEditing.value = team;
    teamName.value = team.display_name;
    const drafts = (team.members ?? []).map(draftFrom);
    while (drafts.length < teamSize.value) drafts.push(blankMember());
    teamMembers.value = drafts;
    teamError.value = null;
    teamDialogOpen.value = true;
};
const submitTeam = async () => {
    const ringer = !!teamEditing.value?.is_ringer;
    if (!teamEditing.value || !teamName.value.trim() || !membersReady(teamMembers.value, ringer)) return;
    teamSaving.value = true;
    teamError.value = null;
    const { ok, error } = await doModalAction(
        route('tournaments.players.update', { tournament: props.tournament.uuid, player: teamEditing.value.id }),
        'PUT',
        { display_name: teamName.value.trim(), members: ringer && isEmptyRoster(teamMembers.value) ? [] : teamMembers.value.map(memberPayload) },
    );
    teamSaving.value = false;
    if (ok) {
        teamDialogOpen.value = false;
        teamEditing.value = null;
        reloadPage();
    } else {
        teamError.value = error;
    }
};

const activePlayers = computed(() => props.tournament.players.filter((p) => !p.is_disqualified && p.dropped_after_round === null));

// Check-in summary (the page keeps its own copies for the pairing confirmation).
const checkedInCount = computed(() => props.tournament.players.filter((p) => !p.is_disqualified && p.checked_in_at).length);
const uncheckedPlayers = computed(() =>
    props.tournament.players.filter((p) => !p.is_disqualified && p.dropped_after_round === null && !p.checked_in_at),
);
const canCheckIn = computed(() => ['registration', 'active'].includes(props.tournament.status));

// ─── RSVP → Player conversion ───
const rsvpPlayerFaction = ref<string | null>(null);
const rsvpPlayerAllegiance = ref<string | null>(null);
const rsvpPlayerDialogOpen = ref(false);
const rsvpPlayerTarget = ref<any>(null);
const rsvpPlayerError = ref<string | null>(null);

const openRsvpPlayerDialog = (rsvp: Rsvp) => {
    rsvpPlayerTarget.value = rsvp;
    // Pre-fill with the faction the user picked at RSVP time so the TO can
    // accept without re-typing. They're free to change it before submit.
    rsvpPlayerFaction.value = rsvp.faction ?? null;
    rsvpPlayerAllegiance.value = rsvp.allegiance_id ? String(rsvp.allegiance_id) : null;
    rsvpPlayerError.value = null;
    rsvpPlayerDialogOpen.value = true;
};

const confirmAddRsvpAsPlayer = async () => {
    const rsvp = rsvpPlayerTarget.value;
    if (!rsvp || !isDeclared(rsvpPlayerFaction.value, rsvpPlayerAllegiance.value)) {
        rsvpPlayerError.value = `Choose a ${declarationLabel.value} before adding this player.`;
        return;
    }
    rsvpPlayerError.value = null;
    const { ok, error } = await doModalAction(route('tournaments.players.add', props.tournament.uuid), 'POST', {
        display_name: rsvp.user?.name ?? 'Unknown',
        user_id: rsvp.user_id,
        ...declarationPayload(rsvpPlayerFaction.value, rsvpPlayerAllegiance.value),
    });
    if (ok) {
        rsvpPlayerDialogOpen.value = false;
        rsvpPlayerTarget.value = null;
        reloadPage();
    } else {
        rsvpPlayerError.value = error;
    }
};

// ─── Players ───
interface MetaOption {
    id: number;
    name: string;
    slug: string;
}

const toast = useToast();
const newPlayerName = ref('');
const newPlayerFaction = ref<string | null>(null);
const newPlayerAllegiance = ref<string | null>(null);
const newPlayerGarrison = ref('');
const newPlayerMeta = ref<MetaOption | null>(null);

// ── Edit player dialog ──
// TOs need to fix typos / change faction / re-assign meta after a player has
// been added — there's no per-row inline edit, so we surface a dialog.
const editPlayerDialogOpen = ref(false);
const editingPlayer = ref<TournamentPlayer | null>(null);
const editPlayerName = ref('');
const editPlayerFaction = ref<string | null>(null);
const editPlayerAllegiance = ref<string | null>(null);
const editPlayerGarrison = ref('');
// A private Garrison's code is hidden from the payload, so only send the field when the TO actually changed it.
let editPlayerGarrisonOriginal = '';
const editPlayerMeta = ref<MetaOption | null>(null);
const editPlayerMetaSearch = ref('');
const editPlayerSubmitting = ref(false);
const editPlayerError = ref<string | null>(null);

const openEditPlayerDialog = (player: TournamentPlayer) => {
    if (isTeamEvent.value) return openTeamDialog(player);
    editingPlayer.value = player;
    editPlayerName.value = player.display_name;
    editPlayerFaction.value = player.faction ?? null;
    editPlayerAllegiance.value = player.allegiance_id ? String(player.allegiance_id) : null;
    editPlayerGarrisonOriginal = player.garrison?.share_code ?? '';
    editPlayerGarrison.value = editPlayerGarrisonOriginal;
    // The player's meta lives either directly on the TournamentPlayer or on
    // the linked User account. Edit overrides via the player record only.
    editPlayerMeta.value = (player.meta ?? player.user?.meta ?? null) as MetaOption | null;
    editPlayerMetaSearch.value = '';
    editPlayerError.value = null;
    editPlayerDialogOpen.value = true;
};

const submitEditPlayer = async () => {
    if (!editingPlayer.value || !editPlayerName.value.trim() || !isDeclared(editPlayerFaction.value, editPlayerAllegiance.value)) return;
    editPlayerSubmitting.value = true;
    editPlayerError.value = null;

    const { ok, error } = await doModalAction(
        route('tournaments.players.update', { tournament: props.tournament.uuid, player: editingPlayer.value.id }),
        'PUT',
        {
            display_name: editPlayerName.value.trim(),
            ...declarationPayload(
                editPlayerFaction.value,
                editPlayerAllegiance.value,
                editPlayerGarrison.value !== editPlayerGarrisonOriginal ? editPlayerGarrison.value : undefined,
            ),
            meta_id: editPlayerMeta.value?.id ?? null,
        },
    );

    editPlayerSubmitting.value = false;
    if (ok) {
        editPlayerDialogOpen.value = false;
        editingPlayer.value = null;
        reloadPage();
    } else {
        editPlayerError.value = error;
    }
};

// All known metas, fetched once at mount. We refetch after creating one so the
// new meta shows up in the list (and is selected) immediately.
const allMetas = ref<MetaOption[]>([]);
const refreshMetas = async () => {
    try {
        const res = await fetch(route('metas.index'), { headers: { Accept: 'application/json' } });
        if (res.ok) {
            allMetas.value = (await res.json()).metas ?? [];
        }
    } catch {
        // Optional UX — if it fails the dropdown is just empty.
    }
};
onMounted(refreshMetas);

// Metas already used by players in THIS tournament — surface these at the top
// of the picker so it's a one-click choice for typical "everyone is from a
// handful of local metas" tournaments.
const tournamentMetas = computed<MetaOption[]>(() => {
    const seen = new Map<number, MetaOption>();
    for (const p of props.tournament.players) {
        const m = p.meta || p.user?.meta;
        if (m && !seen.has(m.id)) seen.set(m.id, m);
    }
    return [...seen.values()].sort((a, b) => a.name.localeCompare(b.name));
});

// Everything else — sorted alphabetically, excluding the ones surfaced above.
const otherMetas = computed<MetaOption[]>(() => {
    const used = new Set(tournamentMetas.value.map((m) => m.id));
    return allMetas.value.filter((m) => !used.has(m.id));
});

// "Create new Meta" dialog.
const newMetaDialogOpen = ref(false);
const newMetaName = ref('');
const newMetaSubmitting = ref(false);
const newMetaError = ref<string | null>(null);

const newPlayerMetaSearch = ref('');

// Prefill from whatever the user has typed in the meta combobox — turns the
// "type, don't see it, click +" flow into one click.
// Which picker the freshly created meta should land in.
const newMetaTarget = ref<'new' | 'edit'>('new');

// The pickers must be closed before the dialog opens: a Combobox popper and a Dialog both trap focus,
// and having both open at once makes them fight over it until the tab freezes.
const newMetaPickerOpen = ref(false);
const editMetaPickerOpen = ref(false);

const openNewMetaDialog = (target: 'new' | 'edit' = 'new', seed?: string) => {
    newMetaPickerOpen.value = false;
    editMetaPickerOpen.value = false;
    newMetaTarget.value = target;
    newMetaName.value = (seed ?? (target === 'edit' ? editPlayerMetaSearch.value : newPlayerMetaSearch.value) ?? '').trim();
    newMetaError.value = null;
    nextTick(() => (newMetaDialogOpen.value = true));
};

// POST the name (find-or-create server side) and drop the result into the picker that asked for it.
const createMeta = async (name: string, target: 'new' | 'edit'): Promise<string | null> => {
    try {
        const res = await fetch(route('metas.store'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                Accept: 'application/json',
            },
            body: JSON.stringify({ name }),
        });
        if (!res.ok) {
            const err = await res.json().catch(() => ({}));
            return err.error ?? err.message ?? 'Could not save meta — try a different name.';
        }
        const data = await res.json();
        await refreshMetas();
        if (target === 'edit') editPlayerMeta.value = data.meta;
        else newPlayerMeta.value = data.meta;
        return null;
    } catch {
        return 'Network error. Please try again.';
    }
};

const submitNewMeta = async () => {
    const name = newMetaName.value.trim();
    if (!name) {
        newMetaError.value = 'Meta name is required.';
        return;
    }
    newMetaSubmitting.value = true;
    newMetaError.value = await createMeta(name, newMetaTarget.value);
    newMetaSubmitting.value = false;
    if (!newMetaError.value) newMetaDialogOpen.value = false;
};

// "Add "x"" row inside the picker: create it on the spot — opening a dialog on top of an open popper freezes the page.
const quickAddMeta = async (target: 'new' | 'edit') => {
    const name = (target === 'edit' ? editPlayerMetaSearch.value : newPlayerMetaSearch.value).trim();
    if (!name) return;
    const error = await createMeta(name, target);
    if (target === 'edit') {
        editPlayerMetaSearch.value = '';
        editMetaPickerOpen.value = false;
        if (error) editPlayerError.value = error;
    } else {
        newPlayerMetaSearch.value = '';
        newMetaPickerOpen.value = false;
        if (error) toast.error(error);
    }
};

const addPlayer = async () => {
    if (!newPlayerName.value.trim() || !isDeclared(newPlayerFaction.value, newPlayerAllegiance.value)) return;
    submitting.value = true;
    const ok = await doAction(route('tournaments.players.add', props.tournament.uuid), 'POST', {
        display_name: newPlayerName.value.trim(),
        ...declarationPayload(newPlayerFaction.value, newPlayerAllegiance.value, newPlayerGarrison.value),
        meta_id: newPlayerMeta.value?.id ?? null,
    });
    submitting.value = false;
    if (ok) {
        newPlayerName.value = '';
        newPlayerFaction.value = null;
        newPlayerAllegiance.value = null;
        newPlayerGarrison.value = '';
        newPlayerMeta.value = null;
        reloadPage();
    }
};

const removePlayer = async (playerId: number) => {
    if (await doAction(route('tournaments.players.remove', { tournament: props.tournament.uuid, player: playerId }), 'DELETE')) {
        reloadPage();
    }
};

// ─── Check-in ───

const toggleCheckIn = async (player: TournamentPlayer) => {
    if (
        await doAction(route('tournaments.players.update', { tournament: props.tournament.uuid, player: player.id }), 'PUT', {
            checked_in: !player.checked_in_at,
        })
    ) {
        reloadPage();
    }
};

const checkInAll = async () => {
    if (await doAction(route('tournaments.players.check_in_all', props.tournament.uuid), 'POST')) reloadPage();
};

const toggleRinger = async (player: TournamentPlayer) => {
    if (
        await doAction(route('tournaments.players.update', { tournament: props.tournament.uuid, player: player.id }), 'PUT', {
            is_ringer: !player.is_ringer,
        })
    ) {
        reloadPage();
    }
};

const toggleDrop = async (player: TournamentPlayer) => {
    // Use the highest completed round number, not total rounds created
    const completedRounds = props.tournament.rounds.filter((r: any) => r.status === 'completed');
    const lastCompletedRound = completedRounds.length > 0 ? Math.max(...completedRounds.map((r: any) => r.round_number)) : 0;
    if (
        await doAction(route('tournaments.players.update', { tournament: props.tournament.uuid, player: player.id }), 'PUT', {
            dropped_after_round: player.dropped_after_round !== null ? null : lastCompletedRound,
        })
    ) {
        reloadPage();
    }
};

// ─── Link manually-added player to a BiggerHat user ───
const linkUserDialogOpen = ref(false);
const linkUserTarget = ref<TournamentPlayer | null>(null);
const linkUserQuery = ref('');
const linkUserResults = ref<{ id: number; name: string }[]>([]);
const linkUserSearching = ref(false);
const linkUserError = ref<string | null>(null);
let linkUserSearchTimer: ReturnType<typeof setTimeout> | null = null;

const openLinkUserDialog = (player: TournamentPlayer) => {
    linkUserTarget.value = player;
    linkUserQuery.value = '';
    linkUserResults.value = [];
    linkUserError.value = null;
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
    linkUserError.value = null;
    const { ok, error } = await doModalAction(
        route('tournaments.players.update', { tournament: props.tournament.uuid, player: linkUserTarget.value.id }),
        'PUT',
        { user_id: userId },
    );
    if (ok) {
        linkUserDialogOpen.value = false;
        linkUserTarget.value = null;
        reloadPage();
    } else {
        linkUserError.value = error;
    }
};

const unlinkUserFromPlayer = async (player: TournamentPlayer) => {
    if (await doAction(route('tournaments.players.update', { tournament: props.tournament.uuid, player: player.id }), 'PUT', { user_id: null })) {
        reloadPage();
    }
};

defineExpose({ openRsvpPlayerDialog });
</script>

<template>
    <TabsContent value="players">
        <!-- Add Team Form (team events) -->
        <Card v-if="isTeamEvent" class="mb-4">
            <CardContent class="space-y-3 p-3 sm:p-4">
                <div class="space-y-1">
                    <Label class="text-xs">Team name</Label>
                    <Input v-model="newTeamName" placeholder="Enter team name..." class="h-9 text-sm" />
                </div>
                <label class="flex items-start gap-2 text-xs">
                    <input v-model="newTeamIsRinger" type="checkbox" class="mt-0.5 size-3.5" />
                    <span>
                        <span class="font-medium">Ringer team</span>
                        <span class="block text-2xs text-muted-foreground">
                            Stands in when there's an odd number of teams, so it needs no members. It plays a game but isn't ranked.
                        </span>
                    </span>
                </label>
                <TeamForm
                    v-if="!newTeamIsRinger"
                    v-model="newTeamMembers"
                    :declaration="declaration"
                    :factions="factions"
                    :allegiances="allegiances"
                    :available-users="availableUsers"
                    :search-url="route('tournaments.users.search', tournament.uuid)"
                    :taken-user-ids="takenUserIds"
                />
                <Button
                    size="sm"
                    class="h-9 gap-1"
                    :disabled="!newTeamName.trim() || !(newTeamIsRinger || membersReady(newTeamMembers)) || submitting"
                    @click="addTeam"
                >
                    <Plus class="size-3.5" /> Add team
                </Button>
            </CardContent>
        </Card>

        <!-- Add Player Form -->
        <Card v-else class="mb-4">
            <CardContent class="p-3 sm:p-4">
                <div class="grid gap-2 sm:grid-cols-[1fr_1fr_auto_auto] sm:items-end">
                    <div class="space-y-1">
                        <Label class="text-xs">Player Name</Label>
                        <Input v-model="newPlayerName" placeholder="Enter player name..." class="h-9 text-sm" @keydown.enter="addPlayer" />
                    </div>
                    <div class="space-y-1">
                        <Label class="text-xs">Meta <span class="text-muted-foreground">(optional)</span></Label>
                        <div class="flex gap-1">
                            <Combobox v-model="newPlayerMeta" v-model:open="newMetaPickerOpen" by="id" class="flex-1">
                                <ComboboxAnchor as-child>
                                    <ComboboxTrigger as-child>
                                        <Button variant="outline" class="h-9 w-full justify-between text-xs font-normal">
                                            <span class="truncate">{{ newPlayerMeta?.name ?? 'Select meta...' }}</span>
                                            <ChevronsUpDown class="ml-2 size-3.5 shrink-0 opacity-50" />
                                        </Button>
                                    </ComboboxTrigger>
                                </ComboboxAnchor>
                                <ComboboxList class="max-h-72 overflow-y-auto">
                                    <ComboboxInput
                                        v-model="newPlayerMetaSearch"
                                        class="h-9 rounded-none border-0 border-b text-xs focus-visible:ring-0"
                                        placeholder="Search or type a new meta..."
                                    />
                                    <ComboboxEmpty class="px-2 py-3 text-left">
                                        <button
                                            v-if="newPlayerMetaSearch.trim()"
                                            type="button"
                                            class="flex w-full items-center gap-2 rounded px-2 py-1.5 text-sm hover:bg-accent"
                                            @click="quickAddMeta('new')"
                                        >
                                            <Plus class="size-3.5" /> Add "<span class="font-medium">{{ newPlayerMetaSearch.trim() }}</span
                                            >"
                                        </button>
                                        <span v-else class="text-xs text-muted-foreground">Type to search or add a meta.</span>
                                    </ComboboxEmpty>
                                    <ComboboxGroup v-if="tournamentMetas.length">
                                        <div class="px-2 pt-2 text-2xs font-semibold uppercase tracking-wide text-muted-foreground">
                                            In this tournament
                                        </div>
                                        <ComboboxItem v-for="m in tournamentMetas" :key="'t-' + m.id" :value="m">
                                            {{ m.name }}
                                            <Check v-if="newPlayerMeta?.id === m.id" class="ml-auto size-3.5" />
                                        </ComboboxItem>
                                    </ComboboxGroup>
                                    <ComboboxSeparator v-if="tournamentMetas.length && otherMetas.length" />
                                    <ComboboxGroup v-if="otherMetas.length">
                                        <div class="px-2 pt-2 text-2xs font-semibold uppercase tracking-wide text-muted-foreground">All metas</div>
                                        <ComboboxItem v-for="m in otherMetas" :key="'o-' + m.id" :value="m">
                                            {{ m.name }}
                                            <Check v-if="newPlayerMeta?.id === m.id" class="ml-auto size-3.5" />
                                        </ComboboxItem>
                                    </ComboboxGroup>
                                </ComboboxList>
                            </Combobox>
                            <Button variant="outline" size="sm" class="h-9 shrink-0 px-2" title="Add a new meta" @click="openNewMetaDialog()">
                                <Plus class="size-3.5" />
                            </Button>
                            <Button
                                v-if="newPlayerMeta"
                                variant="ghost"
                                size="sm"
                                class="h-9 shrink-0 px-2 text-muted-foreground"
                                title="Clear"
                                @click="newPlayerMeta = null"
                            >
                                <X class="size-3.5" />
                            </Button>
                        </div>
                    </div>
                    <DeclarationFields
                        v-model:faction="newPlayerFaction"
                        v-model:allegiance="newPlayerAllegiance"
                        v-model:garrison-code="newPlayerGarrison"
                        :declaration="declaration"
                        :factions="factions"
                        :allegiances="allegiances"
                        trigger-class="h-9 w-full text-xs sm:w-40"
                    />
                    <Button
                        size="sm"
                        class="h-9 shrink-0 gap-1"
                        :disabled="!newPlayerName.trim() || !isDeclared(newPlayerFaction, newPlayerAllegiance) || submitting"
                        @click="addPlayer"
                    >
                        <Plus class="size-3.5" /> Add
                    </Button>
                </div>
            </CardContent>
        </Card>

        <!-- Player Count -->
        <div class="mb-3 flex items-center gap-2 text-sm text-muted-foreground">
            <Users class="size-4" />
            {{ activePlayers.length }} active {{ unitLabel }}s
            <span v-if="tournament.players.length !== activePlayers.length">({{ tournament.players.length }} total)</span>
            <template v-if="canCheckIn && tournament.players.length">
                · {{ checkedInCount }} checked in
                <Button v-if="uncheckedPlayers.length" variant="outline" size="sm" class="ml-1 h-7 px-2 text-xs" @click="checkInAll">
                    Check in all
                </Button>
            </template>
        </div>

        <!-- Player List -->
        <div class="space-y-1">
            <div
                v-for="player in tournament.players"
                :key="player.id"
                :class="[
                    factionBackground(player.faction),
                    player.faction ? 'text-white' : 'border',
                    player.is_disqualified ? 'line-through opacity-40' : player.dropped_after_round !== null ? 'opacity-60' : '',
                ]"
                class="flex items-start justify-between gap-2 rounded-lg px-2 py-2 sm:px-3"
            >
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-1.5">
                        <FactionLogo
                            v-if="player.faction"
                            :faction="player.faction"
                            class-name="size-4 shrink-0 drop-shadow-[0_1px_2px_rgba(0,0,0,0.5)]"
                        />
                        <AllegianceLogo v-else-if="player.allegiance" :allegiance="player.allegiance.slug" class-name="size-4 shrink-0" />
                        <span class="truncate text-xs font-medium sm:text-sm">{{ player.display_name }}</span>
                        <span v-if="player.allegiance" class="shrink-0 text-2xs opacity-70">{{ player.allegiance.name }}</span>
                        <a
                            v-if="player.garrison?.share_code"
                            :href="route('tos.garrisons.shared', player.garrison.share_code)"
                            target="_blank"
                            rel="noopener"
                            class="shrink-0 text-2xs font-medium underline-offset-2 hover:underline"
                            >Garrison</a
                        >
                        <span v-else-if="player.garrison" class="shrink-0 text-2xs opacity-70">{{ player.garrison.name }}</span>
                        <Check v-if="player.checked_in_at" class="size-3.5 shrink-0 text-emerald-300" aria-label="Checked in" title="Checked in" />
                        <Badge
                            v-if="player.is_ringer"
                            variant="outline"
                            class="hidden shrink-0 border-white/40 px-1 py-0 text-2xs text-white/80 sm:inline-flex"
                            >Ringer</Badge
                        >
                        <Star v-if="player.is_ringer" class="size-3 shrink-0 fill-amber-300 text-amber-300 sm:hidden" />
                        <Badge v-if="player.is_disqualified" class="shrink-0 bg-red-900/60 px-1 py-0 text-2xs text-white">DQ</Badge>
                        <Badge
                            v-if="player.dropped_after_round !== null"
                            class="shrink-0 bg-amber-700/80 px-1 py-0 text-2xs font-semibold uppercase tracking-wide text-white"
                            >Dropped after R{{ player.dropped_after_round }}</Badge
                        >
                        <Badge
                            v-if="player.meta || player.user?.meta"
                            variant="outline"
                            class="shrink-0 border-white/40 px-1 py-0 text-2xs text-white/90"
                            >{{ player.meta?.name || player.user?.meta?.name }}</Badge
                        >
                    </div>
                    <div v-if="player.members?.length" class="mt-0.5 pl-5 text-2xs opacity-80">
                        <span v-for="(m, i) in player.members" :key="m.id">
                            <template v-if="i > 0"> · </template>{{ m.display_name
                            }}<template v-if="m.allegiance"> ({{ m.allegiance.name }})</template
                            ><template v-else-if="m.faction && factions[m.faction]"> ({{ factions[m.faction].name }})</template>
                        </span>
                    </div>
                    <div v-if="opponents[player.id]?.length" class="mt-0.5 pl-5 text-2xs opacity-70">vs: {{ opponents[player.id].join(' · ') }}</div>
                </div>
                <div class="flex shrink-0 items-center gap-1">
                    <Button
                        variant="ghost"
                        size="sm"
                        class="h-8 gap-1 px-2 text-white hover:bg-white/20 hover:text-white"
                        :class="player.faction ? '' : 'text-foreground hover:bg-accent hover:text-foreground'"
                        @click="openEditPlayerDialog(player)"
                    >
                        <Pencil class="size-3.5" /> <span class="hidden sm:inline">Edit</span>
                        <span class="sr-only sm:hidden">Edit {{ player.display_name }}</span>
                    </Button>
                    <DropdownMenu>
                        <DropdownMenuTrigger as-child>
                            <Button
                                variant="ghost"
                                size="icon"
                                class="size-8 text-white hover:bg-white/20 hover:text-white"
                                :class="player.faction ? '' : 'text-foreground hover:bg-accent hover:text-foreground'"
                                :aria-label="`More actions for ${player.display_name}`"
                            >
                                <MoreHorizontal class="size-4" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" class="w-52">
                            <DropdownMenuItem v-if="!player.user" @select="openLinkUserDialog(player)">
                                <Link2 class="mr-2 size-4" /> Link to a BiggerHat account…
                            </DropdownMenuItem>
                            <DropdownMenuItem v-else @select="unlinkUserFromPlayer(player)">
                                <Link2Off class="mr-2 size-4" /> Unlink {{ player.user.name }}
                            </DropdownMenuItem>
                            <DropdownMenuItem v-if="tournament.status === 'active'" @select="toggleDrop(player)">
                                <LogOut class="mr-2 size-4" />
                                {{ player.dropped_after_round !== null ? 'Undo drop' : 'Drop from tournament' }}
                            </DropdownMenuItem>
                            <DropdownMenuItem v-if="canCheckIn" @select="toggleCheckIn(player)">
                                <Check class="mr-2 size-4" /> {{ player.checked_in_at ? 'Undo check-in' : 'Check in' }}
                            </DropdownMenuItem>
                            <DropdownMenuItem @select="toggleRinger(player)">
                                <Star class="mr-2 size-4" /> {{ player.is_ringer ? 'Remove ringer status' : 'Make ringer' }}
                            </DropdownMenuItem>
                            <!--
                                            Remove permanently deletes the player. Once the tournament has
                                            started they may have games on record, so we hide it entirely
                                            and direct the TO to use Drop instead. This avoids the
                                            cascade-delete-of-game-history footgun that previously made it
                                            look like opponents' scores had changed.
                                        -->
                            <DropdownMenuItem
                                v-if="tournament.status === 'draft' || tournament.status === 'registration'"
                                class="text-destructive focus:text-destructive"
                                @select="removePlayer(player.id)"
                            >
                                <X class="mr-2 size-4" /> Remove player
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            </div>
        </div>
    </TabsContent>

    <!-- RSVP → Player Faction Dialog -->
    <Dialog v-model:open="rsvpPlayerDialogOpen">
        <DialogContent class="max-w-xs">
            <DialogHeader>
                <DialogTitle>Register {{ rsvpPlayerTarget?.user?.name ?? 'Player' }}</DialogTitle>
                <DialogDescription>Select a {{ declarationLabel }} to register this player.</DialogDescription>
            </DialogHeader>
            <DeclarationFields
                v-model:faction="rsvpPlayerFaction"
                v-model:allegiance="rsvpPlayerAllegiance"
                :declaration="declaration"
                :factions="factions"
                :allegiances="allegiances"
                hide-garrison
            />
            <p v-if="rsvpPlayerError" class="text-xs text-destructive">{{ rsvpPlayerError }}</p>
            <DialogFooter>
                <Button variant="outline" @click="rsvpPlayerDialogOpen = false">Cancel</Button>
                <Button :disabled="!isDeclared(rsvpPlayerFaction, rsvpPlayerAllegiance) || submitting" @click="confirmAddRsvpAsPlayer"
                    >Register</Button
                >
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <!-- Create Meta Dialog -->
    <Dialog v-model:open="newMetaDialogOpen">
        <DialogContent class="max-w-xs">
            <DialogHeader>
                <DialogTitle>Add a Meta</DialogTitle>
                <DialogDescription>Metas group players by community / region (e.g. "Boston", "PNW Online").</DialogDescription>
            </DialogHeader>
            <Input v-model="newMetaName" placeholder="Meta name..." class="text-sm" @keydown.enter="submitNewMeta" />
            <p v-if="newMetaError" class="text-xs text-destructive">{{ newMetaError }}</p>
            <DialogFooter>
                <Button variant="outline" @click="newMetaDialogOpen = false">Cancel</Button>
                <Button :disabled="!newMetaName.trim() || newMetaSubmitting" @click="submitNewMeta">
                    <Loader2 v-if="newMetaSubmitting" class="mr-1.5 size-3 animate-spin" />
                    Add Meta
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <!-- Edit Team Dialog (team events) -->
    <Dialog v-model:open="teamDialogOpen">
        <DialogContent class="max-h-[90vh] max-w-2xl overflow-y-auto">
            <DialogHeader>
                <DialogTitle>Edit team</DialogTitle>
                <DialogDescription>Rename the team or change who is on it and what each person declared.</DialogDescription>
            </DialogHeader>
            <div class="space-y-3">
                <div class="space-y-1">
                    <Label class="text-xs">Team name</Label>
                    <Input v-model="teamName" class="h-9 text-sm" />
                </div>
                <TeamForm
                    v-model="teamMembers"
                    :declaration="declaration"
                    :factions="factions"
                    :allegiances="allegiances"
                    :available-users="availableUsers"
                    :search-url="route('tournaments.users.search', tournament.uuid)"
                    :taken-user-ids="takenUserIds"
                />
                <p v-if="teamError" class="text-xs text-destructive">{{ teamError }}</p>
            </div>
            <DialogFooter>
                <Button variant="outline" @click="teamDialogOpen = false">Cancel</Button>
                <Button :disabled="!teamName.trim() || !membersReady(teamMembers, !!teamEditing?.is_ringer) || teamSaving" @click="submitTeam">
                    <Loader2 v-if="teamSaving" class="mr-2 size-4 animate-spin" /> Save team
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <!-- Edit Player Dialog -->
    <Dialog v-model:open="editPlayerDialogOpen">
        <DialogContent class="max-w-md">
            <DialogHeader>
                <DialogTitle>Edit Player</DialogTitle>
                <DialogDescription>Fix typos, change {{ declarationLabel }}, or reassign their meta.</DialogDescription>
            </DialogHeader>
            <div class="space-y-3">
                <div class="space-y-1">
                    <Label class="text-xs">Player Name</Label>
                    <Input v-model="editPlayerName" placeholder="Enter player name..." class="h-9 text-sm" />
                </div>
                <DeclarationFields
                    v-model:faction="editPlayerFaction"
                    v-model:allegiance="editPlayerAllegiance"
                    v-model:garrison-code="editPlayerGarrison"
                    :declaration="declaration"
                    :factions="factions"
                    :allegiances="allegiances"
                />
                <div class="space-y-1">
                    <Label class="text-xs">Meta <span class="text-muted-foreground">(optional)</span></Label>
                    <div class="flex gap-1">
                        <Combobox v-model="editPlayerMeta" v-model:open="editMetaPickerOpen" by="id" class="flex-1">
                            <ComboboxAnchor as-child>
                                <ComboboxTrigger as-child>
                                    <Button variant="outline" class="h-9 w-full justify-between text-xs font-normal">
                                        <span class="truncate">{{ editPlayerMeta?.name ?? 'Select meta...' }}</span>
                                        <ChevronsUpDown class="ml-2 size-3.5 shrink-0 opacity-50" />
                                    </Button>
                                </ComboboxTrigger>
                            </ComboboxAnchor>
                            <ComboboxList inline class="max-h-72 overflow-y-auto">
                                <ComboboxInput
                                    v-model="editPlayerMetaSearch"
                                    class="h-9 rounded-none border-0 border-b text-xs focus-visible:ring-0"
                                    placeholder="Search or type a new meta..."
                                />
                                <ComboboxEmpty class="px-2 py-3 text-left">
                                    <button
                                        v-if="editPlayerMetaSearch.trim()"
                                        type="button"
                                        class="flex w-full items-center gap-2 rounded px-2 py-1.5 text-sm hover:bg-accent"
                                        @click="quickAddMeta('edit')"
                                    >
                                        <Plus class="size-3.5" /> Add "<span class="font-medium">{{ editPlayerMetaSearch.trim() }}</span
                                        >"
                                    </button>
                                    <span v-else class="text-xs text-muted-foreground">No matches.</span>
                                </ComboboxEmpty>
                                <ComboboxGroup v-if="tournamentMetas.length">
                                    <div class="px-2 pt-2 text-2xs font-semibold uppercase tracking-wide text-muted-foreground">
                                        In this tournament
                                    </div>
                                    <ComboboxItem v-for="m in tournamentMetas" :key="'et-' + m.id" :value="m">
                                        {{ m.name }}
                                        <Check v-if="editPlayerMeta?.id === m.id" class="ml-auto size-3.5" />
                                    </ComboboxItem>
                                </ComboboxGroup>
                                <ComboboxSeparator v-if="tournamentMetas.length && otherMetas.length" />
                                <ComboboxGroup v-if="otherMetas.length">
                                    <div class="px-2 pt-2 text-2xs font-semibold uppercase tracking-wide text-muted-foreground">All metas</div>
                                    <ComboboxItem v-for="m in otherMetas" :key="'eo-' + m.id" :value="m">
                                        {{ m.name }}
                                        <Check v-if="editPlayerMeta?.id === m.id" class="ml-auto size-3.5" />
                                    </ComboboxItem>
                                </ComboboxGroup>
                            </ComboboxList>
                        </Combobox>
                        <Button
                            v-if="editPlayerMeta"
                            variant="ghost"
                            size="sm"
                            class="h-9 shrink-0 px-2 text-muted-foreground"
                            title="Clear"
                            @click="editPlayerMeta = null"
                        >
                            <X class="size-3.5" />
                        </Button>
                    </div>
                </div>
                <p v-if="editPlayerError" class="text-xs text-destructive">{{ editPlayerError }}</p>
            </div>
            <DialogFooter>
                <Button variant="outline" @click="editPlayerDialogOpen = false">Cancel</Button>
                <Button :disabled="!editPlayerName.trim() || !editPlayerFaction || editPlayerSubmitting" @click="submitEditPlayer">
                    <Loader2 v-if="editPlayerSubmitting" class="mr-1.5 size-3 animate-spin" />
                    Save
                </Button>
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
            <div v-if="!linkUserQuery.trim() && friendOptions.length" class="flex flex-wrap gap-2">
                <button
                    v-for="f in friendOptions"
                    :key="f.id"
                    type="button"
                    class="rounded-full border px-3 py-1 text-xs hover:bg-accent"
                    @click="linkUserToPlayer(f.id)"
                >
                    {{ f.name }}
                </button>
            </div>
            <Input v-model="linkUserQuery" placeholder="Search users (min 2 chars)..." class="text-sm" />
            <div class="max-h-64 overflow-y-auto">
                <div v-if="linkUserSearching" class="py-2 text-center text-xs text-muted-foreground">Searching...</div>
                <div v-else-if="linkUserQuery.trim().length >= 2 && !linkUserResults.length" class="py-2 text-center text-xs text-muted-foreground">
                    No users match.
                </div>
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
            <p v-if="linkUserError" class="text-xs text-destructive">{{ linkUserError }}</p>
            <DialogFooter>
                <Button variant="outline" @click="linkUserDialogOpen = false">Cancel</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
