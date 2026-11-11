<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useConfirm } from '@/composables/useConfirm';
import { useTournament } from '@/composables/useTournament';
import type { TournamentRuleset } from '@/types/tournament';
import { Loader2 } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

/**
 * The organizer's score-entry dialog for one tournament game: ruleset score components, leader
 * (master / title) for rulesets that track them, agreed results, post-score drops, the linked
 * tracker game's status, and any pending / disputed player report. The page only says which game is
 * open (the game prop); this component owns the form state and saves through the tournament API.
 */
interface ScoreEntryGame {
    id: number;
    player_one_id: number;
    player_two_id: number | null;
    player_one_faction: string | null;
    player_one_master: string | null;
    player_one_title: string | null;
    player_two_faction: string | null;
    player_two_master: string | null;
    player_two_title: string | null;
    result: string;
    tracker_game?: { uuid: string; status: string; current_turn?: number; max_turns?: number | null } | null;
    report_status?: 'pending' | 'disputed' | null;
    reported_by_player_id?: number | null;
    reported_score?: { player_one: Record<string, number>; player_two: Record<string, number>; agreed: boolean } | null;
    report_note?: string | null;
    score_breakdown?: { player_one?: Record<string, number>; player_two?: Record<string, number> } | null;
}

interface MasterOption {
    name: string;
    faction: string;
    second_faction: string | null;
    titles: { id: number; display_name: string; title: string | null }[];
}

const props = defineProps<{
    game: ScoreEntryGame | null;
    tournament: {
        uuid: string;
        players: { id: number; display_name: string; faction: string | null; is_disqualified?: boolean; dropped_after_round?: number | null }[];
        rounds: { round_number: number; games: ScoreEntryGame[] }[];
        [key: string]: unknown;
    };
    ruleset: TournamentRuleset;
    /** Deferred prop: null until the follow-up request fills it in. */
    masters: MasterOption[] | null;
}>();
const emit = defineEmits<{ (e: 'close'): void }>();

const tournamentRef = computed(() => props.tournament);
const { submitting, doAction, doModalAction, reloadProps, playerName } = useTournament(tournamentRef);
const confirm = useConfirm();
const reloadPage = () => reloadProps(['tournament', 'standings']);

const editingGame = ref<ScoreEntryGame | null>(null);
const editP1Master = ref<string | null>(null);
const editP1Title = ref<string | null>(null);
const editP1Score = ref<Record<string, number>>({});
const editP2Master = ref<string | null>(null);
const editP2Title = ref<string | null>(null);
const editP2Score = ref<Record<string, number>>({});
// Optional: drop a player after submitting their score for this round.
// Agreed result: the game ended by agreement (concession / ran out of time) and the entered scores are the agreed outcome.
const editAgreed = ref(false);
const editDropP1 = ref(false);
const editDropP2 = ref(false);
const scoreError = ref<string | null>(null);

const sumScore = (score: Record<string, number>) => props.ruleset.score_fields.reduce((sum, f) => sum + (Number(score[f.key]) || 0), 0);
const editP1Vp = computed(() => sumScore(editP1Score.value));
const editP2Vp = computed(() => sumScore(editP2Score.value));
// One input per ruleset score field (Malifaux: strategy + scheme; Fields of Glory: a single VP total).
const scoreGridClass = computed(() => (props.ruleset.score_fields.length > 1 ? 'grid-cols-2' : 'grid-cols-1'));

const editP1Faction = computed(
    () => editingGame.value?.player_one_faction ?? props.tournament.players.find((p) => p.id === editingGame.value?.player_one_id)?.faction ?? null,
);
const editP2Faction = computed(
    () => editingGame.value?.player_two_faction ?? props.tournament.players.find((p) => p.id === editingGame.value?.player_two_id)?.faction ?? null,
);

const scoreFromGame = (game: ScoreEntryGame, side: 'player_one' | 'player_two'): Record<string, number> =>
    Object.fromEntries(props.ruleset.score_fields.map((f) => [f.key, game.score_breakdown?.[side]?.[f.key] ?? 0]));

const hydrate = (game: ScoreEntryGame) => {
    editingGame.value = game;
    showLeaders.value = !!(game.player_one_master || game.player_two_master);
    editP1Master.value = game.player_one_master;
    editP1Title.value = game.player_one_title;
    // A pending / disputed player report pre-fills the form, so approving it is one click.
    const proposal = game.report_status ? game.reported_score : null;
    editP1Score.value = proposal ? { ...proposal.player_one } : scoreFromGame(game, 'player_one');
    editP2Master.value = game.player_two_master;
    editP2Title.value = game.player_two_title;
    editP2Score.value = proposal ? { ...proposal.player_two } : scoreFromGame(game, 'player_two');
    editAgreed.value = proposal ? proposal.agreed : game.result === 'agreed';
    editDropP1.value = false;
    editDropP2.value = false;
    scoreError.value = null;
};

// Masters and titles are optional record-keeping; most organizers only type scores, so they stay folded away until asked for.
const showLeaders = ref(false);

// Round number this game belongs to — used when the TO checks "drop after this round".
// Alternating Leaders variant: a master (by title) can't be hired again in a later round of the same event.
const leaderReuseWarning = (playerId: number, master: string | null, title: string | null): string | null => {
    if (!props.ruleset.active_variants.alternating_leaders || !master || !editingGame.value) return null;
    const editingId = editingGame.value.id;
    const thisRound = editingRoundNumber.value;
    const key = title || master;
    for (const round of props.tournament.rounds) {
        if (thisRound !== null && round.round_number >= thisRound) continue;
        for (const g of round.games) {
            if (g.id === editingId) continue;
            const used =
                g.player_one_id === playerId
                    ? g.player_one_title || g.player_one_master
                    : g.player_two_id === playerId
                      ? g.player_two_title || g.player_two_master
                      : null;
            if (used && used === key) return `${key} was already played in Round ${round.round_number} (Alternating Leaders is on).`;
        }
    }
    return null;
};

const editingRoundNumber = computed(() => {
    if (!editingGame.value) return null;
    for (const r of props.tournament.rounds) {
        if (r.games.some((g: any) => g.id === editingGame.value!.id)) return r.round_number;
    }
    return null;
});

// Tracker surface: is the linked tracker game still being played? Drives a
// warning banner in the score dialog + an auto-included override flag so the
// TO's confirm lands through the backend's InProgress guard in one round-trip.
const trackerInProgress = computed(() => editingGame.value?.tracker_game?.status === 'in_progress');
const trackerComplete = computed(() => editingGame.value?.tracker_game?.status === 'completed');
const trackerAbandoned = computed(() => editingGame.value?.tracker_game?.status === 'abandoned');

// Force-complete a stuck tracker game from the TO side. Used when players
// walked away mid-game and never marked the tracker complete, leaving it
// blocking on their dashboards forever.
const completingTracker = ref(false);
const completeTrackerGame = async () => {
    if (!editingGame.value) return;
    if (
        !(await confirm({
            title: 'Force complete tracker game?',
            message:
                'Marks the tracker game as Completed without recording a winner. The tournament score below is the source of truth — use this to clean up games players left in progress.',
            confirmLabel: 'Complete',
        }))
    )
        return;
    completingTracker.value = true;
    if (await doAction(route('tournaments.games.complete_tracker', { tournament: props.tournament.uuid, game: editingGame.value.id }), 'POST')) {
        reloadPage();
    }
    completingTracker.value = false;
};

const saveScore = async () => {
    if (!editingGame.value) return;
    submitting.value = true;
    scoreError.value = null;
    // Total VP is recomputed server-side from the ruleset's score components.
    const scoreResult = await doModalAction(
        route('tournaments.games.update', { tournament: props.tournament.uuid, game: editingGame.value.id }),
        'PUT',
        {
            player_one_master: editP1Master.value,
            player_one_title: editP1Title.value,
            player_one_score: editP1Score.value,
            player_two_master: editP2Master.value,
            player_two_title: editP2Title.value,
            player_two_score: editP2Score.value,
            agreed: editAgreed.value,
            // Pre-acknowledge the "tracker still InProgress" guard — the banner
            // above the save button has already warned the TO.
            ...(trackerInProgress.value ? { confirm_override: true } : {}),
        },
    );
    if (!scoreResult.ok) {
        submitting.value = false;
        scoreError.value =
            scoreResult.error === 'tracker_in_progress'
                ? 'The tracker game is still in progress. Refresh and try again to confirm.'
                : scoreResult.error;
        return;
    }

    // Apply post-score drops if requested. Done sequentially so we surface
    // any error before clearing the dialog.
    const dropAfter = editingRoundNumber.value;
    if (dropAfter !== null) {
        if (editDropP1.value && editingGame.value.player_one_id) {
            const dropResult = await doModalAction(
                route('tournaments.players.update', { tournament: props.tournament.uuid, player: editingGame.value.player_one_id }),
                'PUT',
                { dropped_after_round: dropAfter },
            );
            if (!dropResult.ok) {
                submitting.value = false;
                scoreError.value = dropResult.error;
                return;
            }
        }
        if (editDropP2.value && editingGame.value.player_two_id) {
            const dropResult = await doModalAction(
                route('tournaments.players.update', { tournament: props.tournament.uuid, player: editingGame.value.player_two_id }),
                'PUT',
                { dropped_after_round: dropAfter },
            );
            if (!dropResult.ok) {
                submitting.value = false;
                scoreError.value = dropResult.error;
                return;
            }
        }
    }

    submitting.value = false;
    close();
    reloadPage();
};

// Masters filtered by faction. Returns [] while the deferred prop is still loading.
const mastersForFaction = (faction: string | null) => {
    if (!faction || !props.masters) return [];
    return props.masters.filter((m) => m.faction === faction || m.second_faction === faction);
};

const titlesForMaster = (masterName: string | null) => {
    if (!masterName || !props.masters) return [];
    const master = props.masters.find((m) => m.name === masterName);
    return master?.titles ?? [];
};

const close = () => {
    editingGame.value = null;
    emit('close');
};

// Hydrate the form whenever the page opens a (different) game.
watch(
    () => props.game,
    (g) => {
        editingGame.value = g;
        if (g) hydrate(g);
    },
    { immediate: true },
);
</script>

<template>
    <Dialog
        :open="!!editingGame"
        @update:open="
            (v) => {
                if (!v) close();
            }
        "
    >
        <DialogContent class="max-w-md">
            <DialogHeader>
                <DialogTitle>Game Score</DialogTitle>
                <DialogDescription v-if="editingGame">
                    {{ playerName(editingGame.player_one_id) }} vs {{ playerName(editingGame.player_two_id) }}
                </DialogDescription>
            </DialogHeader>
            <div v-if="editingGame" class="space-y-4">
                <!-- Tracker status — visible whenever this tournament game is linked to a live tracker game. -->
                <div
                    v-if="editingGame.tracker_game"
                    class="flex flex-wrap items-start gap-2 rounded-md border p-2.5 text-xs"
                    :class="
                        trackerInProgress
                            ? 'border-amber-300 bg-amber-50 text-amber-900 dark:border-amber-700/60 dark:bg-amber-950/40 dark:text-amber-200'
                            : trackerComplete
                              ? 'border-emerald-300 bg-emerald-50 text-emerald-900 dark:border-emerald-700/60 dark:bg-emerald-950/40 dark:text-emerald-200'
                              : trackerAbandoned
                                ? 'border-destructive/40 bg-destructive/10 text-destructive dark:text-destructive'
                                : 'border-muted-foreground/20 bg-muted/50 text-muted-foreground'
                    "
                >
                    <div class="min-w-48 flex-1 space-y-0.5">
                        <div class="font-semibold">
                            <template v-if="trackerInProgress">
                                Tracker game in progress (turn {{ editingGame.tracker_game.current_turn }} of
                                {{ editingGame.tracker_game.max_turns ?? 5 }})
                            </template>
                            <template v-else-if="trackerComplete">Tracker game finalized</template>
                            <template v-else-if="trackerAbandoned">Tracker game was abandoned</template>
                            <template v-else>Tracker linked ({{ editingGame.tracker_game.status.replace('_', ' ') }})</template>
                        </div>
                        <div class="text-2xs opacity-90">
                            <template v-if="trackerInProgress">
                                Scores below are the live tracker totals. Saving here locks this tournament record even if the tracker hasn't been
                                marked complete.
                            </template>
                            <template v-else-if="trackerComplete">Scores below were pulled from the tracker.</template>
                            <template v-else-if="trackerAbandoned">
                                The players ended the tracker game early. Enter a final score below or mark a forfeit from the round list.
                            </template>
                            <template v-else>Scores below may be pre-filled from the linked tracker.</template>
                        </div>
                    </div>
                    <div class="flex shrink-0 flex-wrap gap-1.5 sm:flex-col">
                        <a
                            :href="route('games.show', editingGame.tracker_game.uuid)"
                            target="_blank"
                            rel="noopener"
                            class="border-current/30 hover:bg-current/10 rounded border px-2.5 py-1.5 text-center text-xs font-medium"
                        >
                            Open tracker
                        </a>
                        <button
                            v-if="trackerInProgress"
                            type="button"
                            class="border-current/30 hover:bg-current/10 rounded border px-2.5 py-1.5 text-xs font-medium"
                            :disabled="completingTracker"
                            @click="completeTrackerGame"
                        >
                            {{ completingTracker ? 'Closing…' : 'Force complete' }}
                        </button>
                    </div>
                </div>
                <div
                    v-if="editingGame.report_status"
                    class="rounded-md border p-2 text-xs"
                    :class="
                        editingGame.report_status === 'disputed' ? 'border-destructive/50 bg-destructive/5' : 'border-amber-500/50 bg-amber-500/5'
                    "
                >
                    <template v-if="editingGame.report_status === 'pending'">
                        <span class="font-medium">{{ playerName(editingGame.reported_by_player_id ?? 0) }}</span> reported these scores; the opponent
                        hasn't confirmed. Saving makes them official.
                    </template>
                    <template v-else>
                        <span class="font-medium">Disputed.</span> {{ playerName(editingGame.reported_by_player_id ?? 0) }} reported these scores and
                        the opponent disagrees<span v-if="editingGame.report_note">: “{{ editingGame.report_note }}”</span>. Enter the correct result.
                    </template>
                </div>

                <label class="flex items-start gap-2 rounded-md border border-dashed p-2 text-xs">
                    <Checkbox :checked="editAgreed" class="mt-0.5" @update:checked="(on) => (editAgreed = on === true)" />
                    <span>
                        <span class="font-medium">Agreed result</span>
                        <span class="block text-2xs text-muted-foreground">
                            The game wasn't played to its end. Both players and you agree these scores are a likely outcome as the game stands — no
                            inducements, no guessing at future turns. Scored like a normal result.
                        </span>
                    </span>
                </label>

                <Button
                    v-if="ruleset.tracks_leaders && !showLeaders"
                    type="button"
                    variant="ghost"
                    size="sm"
                    class="h-7 text-xs"
                    @click="showLeaders = true"
                >
                    Add masters &amp; titles (optional)
                </Button>

                <!-- Player 1 -->
                <div class="space-y-2 rounded-lg border p-3">
                    <div class="text-xs font-semibold">{{ playerName(editingGame.player_one_id) }}</div>
                    <div v-if="ruleset.tracks_leaders && showLeaders" class="grid grid-cols-2 gap-2">
                        <div class="space-y-1">
                            <Label class="text-2xs">Master</Label>
                            <Select v-model="editP1Master">
                                <SelectTrigger class="h-8 text-xs"><SelectValue placeholder="Select master..." /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="m in mastersForFaction(editP1Faction)" :key="m.name" :value="m.name">{{ m.name }}</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div class="space-y-1">
                            <Label class="text-2xs">Title</Label>
                            <Select v-model="editP1Title" :disabled="!editP1Master">
                                <SelectTrigger class="h-8 text-xs"><SelectValue placeholder="Title..." /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="t in titlesForMaster(editP1Master)" :key="t.id" :value="t.display_name">{{
                                        t.title || t.display_name
                                    }}</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>
                    <p
                        v-if="editingGame.player_one_id && leaderReuseWarning(editingGame.player_one_id, editP1Master, editP1Title)"
                        class="text-2xs text-amber-600 dark:text-amber-400"
                    >
                        {{ leaderReuseWarning(editingGame.player_one_id, editP1Master, editP1Title) }}
                    </p>
                    <div class="grid gap-2" :class="scoreGridClass">
                        <div v-for="field in ruleset.score_fields" :key="field.key" class="space-y-1">
                            <Label class="text-2xs"
                                >{{ field.label }} <span class="text-muted-foreground">/ {{ field.max }}</span></Label
                            >
                            <Input
                                v-model.number="editP1Score[field.key]"
                                type="number"
                                :min="field.min"
                                :max="field.max"
                                class="h-8 text-center text-sm font-bold"
                                @focus="($event.target as HTMLInputElement).select()"
                                @keydown.enter.prevent="saveScore"
                            />
                        </div>
                    </div>
                    <div class="text-right text-xs text-muted-foreground">
                        Total: <span class="font-bold text-foreground">{{ editP1Vp }}</span> VP
                    </div>
                    <label class="flex items-center gap-2 pt-1 text-xs text-amber-700 dark:text-amber-400">
                        <Checkbox :checked="editDropP1" @update:checked="(on) => (editDropP1 = on === true)" />
                        Drop {{ playerName(editingGame.player_one_id) }} after this round
                    </label>
                </div>

                <!-- Player 2 -->
                <div class="space-y-2 rounded-lg border p-3">
                    <div class="text-xs font-semibold">{{ playerName(editingGame.player_two_id) }}</div>
                    <div v-if="ruleset.tracks_leaders && showLeaders" class="grid grid-cols-2 gap-2">
                        <div class="space-y-1">
                            <Label class="text-2xs">Master</Label>
                            <Select v-model="editP2Master">
                                <SelectTrigger class="h-8 text-xs"><SelectValue placeholder="Select master..." /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="m in mastersForFaction(editP2Faction)" :key="m.name" :value="m.name">{{ m.name }}</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div class="space-y-1">
                            <Label class="text-2xs">Title</Label>
                            <Select v-model="editP2Title" :disabled="!editP2Master">
                                <SelectTrigger class="h-8 text-xs"><SelectValue placeholder="Title..." /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="t in titlesForMaster(editP2Master)" :key="t.id" :value="t.display_name">{{
                                        t.title || t.display_name
                                    }}</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>
                    <p
                        v-if="editingGame.player_two_id && leaderReuseWarning(editingGame.player_two_id, editP2Master, editP2Title)"
                        class="text-2xs text-amber-600 dark:text-amber-400"
                    >
                        {{ leaderReuseWarning(editingGame.player_two_id, editP2Master, editP2Title) }}
                    </p>
                    <div class="grid gap-2" :class="scoreGridClass">
                        <div v-for="field in ruleset.score_fields" :key="field.key" class="space-y-1">
                            <Label class="text-2xs"
                                >{{ field.label }} <span class="text-muted-foreground">/ {{ field.max }}</span></Label
                            >
                            <Input
                                v-model.number="editP2Score[field.key]"
                                type="number"
                                :min="field.min"
                                :max="field.max"
                                class="h-8 text-center text-sm font-bold"
                                @focus="($event.target as HTMLInputElement).select()"
                                @keydown.enter.prevent="saveScore"
                            />
                        </div>
                    </div>
                    <div class="text-right text-xs text-muted-foreground">
                        Total: <span class="font-bold text-foreground">{{ editP2Vp }}</span> VP
                    </div>
                    <label v-if="editingGame.player_two_id" class="flex items-center gap-2 pt-1 text-xs text-amber-700 dark:text-amber-400">
                        <Checkbox :checked="editDropP2" @update:checked="(on) => (editDropP2 = on === true)" />
                        Drop {{ playerName(editingGame.player_two_id) }} after this round
                    </label>
                </div>

                <!-- Score summary -->
                <div class="flex items-center justify-center gap-3 text-lg font-bold">
                    <span>{{ editP1Vp }}</span>
                    <span class="text-sm text-muted-foreground">-</span>
                    <span>{{ editP2Vp }}</span>
                </div>
            </div>
            <p v-if="scoreError" class="text-xs text-destructive">{{ scoreError }}</p>
            <DialogFooter class="gap-2 sm:gap-0">
                <Button variant="outline" @click="close">Cancel</Button>
                <Button :disabled="submitting" @click="saveScore">
                    <Loader2 v-if="submitting" class="mr-2 size-4 animate-spin" />
                    Save Score
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
