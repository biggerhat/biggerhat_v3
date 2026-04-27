<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useConfirm } from '@/composables/useConfirm';
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, AlertTriangle, Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';

const confirm = useConfirm();

interface Round {
    id: number;
    round_number: number;
    status: string | null;
    started_at: string | null;
    completed_at: string | null;
}

interface Player {
    id: number;
    display_name: string;
    is_disqualified: boolean;
    dropped_after_round: number | null;
}

interface Tournament {
    id: number;
    uuid: string;
    name: string;
    status: string | null;
    event_date: string | null;
    rounds: Round[];
    players: Player[];
}

const props = defineProps<{
    tournament: Tournament;
    tournament_statuses: { value: string; label: string }[];
    round_statuses: { value: string; label: string }[];
}>();

const newTournamentStatus = ref(props.tournament.status ?? '');
const roundStatusDrafts = ref<Record<number, string>>(
    Object.fromEntries(props.tournament.rounds.map((r) => [r.id, r.status ?? ''])),
);

const forceTournamentStatus = async () => {
    if (!newTournamentStatus.value || newTournamentStatus.value === props.tournament.status) return;
    if (!(await confirm({
        title: 'Force tournament status',
        message: `Force tournament to "${newTournamentStatus.value}"? This bypasses every guard.`,
        confirmLabel: 'Force',
        destructive: true,
    }))) return;
    router.post(
        route('admin.tournaments.force_status', props.tournament.uuid),
        { status: newTournamentStatus.value },
        { preserveScroll: true },
    );
};

const forceRoundStatus = async (round: Round) => {
    const next = roundStatusDrafts.value[round.id];
    if (!next || next === round.status) return;
    if (!(await confirm({
        title: `Force round ${round.round_number}`,
        message: `Force round ${round.round_number} to "${next}"? This bypasses pairing/scoring guards.`,
        confirmLabel: 'Force',
        destructive: true,
    }))) return;
    router.post(
        route('admin.tournaments.rounds.force_status', { tournament: props.tournament.uuid, round: round.id }),
        { status: next },
        { preserveScroll: true },
    );
};

const deleteTournament = async () => {
    if (!(await confirm({
        title: 'Soft-delete tournament',
        message: `Soft-delete "${props.tournament.name}"? Recoverable from the database, but it will disappear from the UI immediately.`,
        confirmLabel: 'Delete',
        destructive: true,
    }))) return;
    router.post(route('admin.tournaments.delete', props.tournament.uuid), {}, { preserveScroll: false });
};
</script>

<template>
    <Head :title="`${tournament.name} - Admin Override`" />
    <div class="container mx-auto space-y-4 px-4 py-6 lg:px-8 xl:px-12">
        <div class="flex flex-wrap items-center gap-2">
            <Button variant="ghost" size="sm" as-child>
                <Link :href="route('admin.tournaments.index')"><ArrowLeft class="mr-1 size-3.5" /> All tournaments</Link>
            </Button>
            <h1 class="text-2xl font-semibold tracking-tight">{{ tournament.name }}</h1>
            <Badge variant="outline">{{ tournament.status ?? '—' }}</Badge>
        </div>
        <p class="text-sm text-muted-foreground">
            Super-admin overrides — these bypass the state machine. Every change is recorded in the activity log.
        </p>

        <div class="rounded-md border border-amber-500/40 bg-amber-500/5 px-4 py-2 text-xs text-amber-900 dark:text-amber-200">
            <AlertTriangle class="mr-1.5 inline size-3.5" />
            Use these controls only to fix stuck tournaments. The regular Manage page is the right tool for normal operations.
        </div>

        <Card>
            <CardContent class="space-y-2 p-4">
                <div class="text-sm font-semibold">Tournament status</div>
                <div class="flex items-center gap-2">
                    <Select v-model="newTournamentStatus">
                        <SelectTrigger class="w-48"><SelectValue /></SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="s in tournament_statuses" :key="s.value" :value="s.value">{{ s.label }}</SelectItem>
                        </SelectContent>
                    </Select>
                    <Button :disabled="newTournamentStatus === tournament.status" @click="forceTournamentStatus">Force</Button>
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardContent class="space-y-2 p-4">
                <div class="text-sm font-semibold">Rounds</div>
                <div v-if="!tournament.rounds.length" class="py-3 text-xs text-muted-foreground">No rounds yet.</div>
                <div v-else class="space-y-1.5">
                    <div
                        v-for="round in tournament.rounds"
                        :key="round.id"
                        class="flex flex-wrap items-center gap-2 rounded-md border px-3 py-2 text-sm"
                    >
                        <span class="font-medium">R{{ round.round_number }}</span>
                        <Badge variant="outline">{{ round.status ?? '—' }}</Badge>
                        <span v-if="round.started_at" class="text-xs text-muted-foreground">
                            started {{ new Date(round.started_at).toLocaleString() }}
                        </span>
                        <span v-if="round.completed_at" class="text-xs text-muted-foreground">
                            done {{ new Date(round.completed_at).toLocaleString() }}
                        </span>
                        <div class="ml-auto flex items-center gap-2">
                            <Select v-model="roundStatusDrafts[round.id]">
                                <SelectTrigger class="w-36"><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="s in round_statuses" :key="s.value" :value="s.value">{{ s.label }}</SelectItem>
                                </SelectContent>
                            </Select>
                            <Button size="sm" :disabled="roundStatusDrafts[round.id] === round.status" @click="forceRoundStatus(round)">
                                Force
                            </Button>
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardContent class="space-y-2 p-4">
                <div class="text-sm font-semibold">Players ({{ tournament.players.length }})</div>
                <div class="grid gap-1.5 sm:grid-cols-2">
                    <div
                        v-for="p in tournament.players"
                        :key="p.id"
                        class="flex items-center gap-2 rounded-md border px-2 py-1.5 text-xs"
                    >
                        <span class="truncate font-medium">{{ p.display_name }}</span>
                        <Badge v-if="p.is_disqualified" variant="destructive" class="text-[9px]">DQ</Badge>
                        <Badge v-else-if="p.dropped_after_round" variant="outline" class="text-[9px]">dropped R{{ p.dropped_after_round }}</Badge>
                    </div>
                </div>
                <p class="text-xs text-muted-foreground">
                    Player-level overrides (DQ, drop, faction reassign) live on the regular Manage page — those flows already gate by `manage` policy
                    rather than the state machine, so this admin panel doesn't duplicate them.
                </p>
            </CardContent>
        </Card>

        <Card class="border-destructive/40">
            <CardContent class="flex flex-wrap items-center justify-between gap-3 p-4">
                <div>
                    <div class="text-sm font-semibold text-destructive">Danger zone</div>
                    <p class="text-xs text-muted-foreground">Soft-delete this tournament. The DB row stays — it just disappears from the UI.</p>
                </div>
                <Button variant="destructive" @click="deleteTournament">
                    <Trash2 class="mr-1.5 size-3.5" /> Soft-delete
                </Button>
            </CardContent>
        </Card>
    </div>
</template>
