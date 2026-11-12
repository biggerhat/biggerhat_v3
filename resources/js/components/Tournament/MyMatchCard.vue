<script setup lang="ts">
import FactionLogo from '@/components/FactionLogo.vue';
import RoundClock from '@/components/Tournament/RoundClock.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import TableTerrain from '@/components/Tournament/TableTerrain.vue';
import type { TableDetail } from '@/types/tournament';
import { Textarea } from '@/components/ui/textarea';
import { Link, router, usePage } from '@inertiajs/vue3';
import { Gamepad2, Loader2, Swords } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface Side {
    display_name: string;
}

interface ReportedScore {
    player_one: Record<string, number>;
    player_two: Record<string, number>;
    agreed: boolean;
}

interface MatchGame {
    id: number;
    player_one_id: number;
    player_two_id: number | null;
    player_one: Side;
    player_two: Side | null;
    player_one_faction: string | null;
    player_one_master: string | null;
    player_one_title: string | null;
    player_one_vp: number | null;
    player_two_faction: string | null;
    player_two_master: string | null;
    player_two_title: string | null;
    player_two_vp: number | null;
    is_bye: boolean;
    is_forfeit: boolean;
    result: string;
    table_number: number | null;
    tracker_game: { uuid: string; status?: string } | null;
    /** A player-submitted result that isn't official yet. */
    report_status?: 'pending' | 'disputed' | null;
    reported_by_player_id?: number | null;
    reported_score?: ReportedScore | null;
    report_note?: string | null;
}

interface MatchRound {
    round_number: number;
    label?: string;
    is_cut?: boolean;
    status: string;
    started_at: string | null;
    deployment: { label: string } | null;
    strategy: { name: string } | null;
    /** TOS-style scenario (operation + deployment), when the ruleset has one. */
    tos_scenario?: { operation: { name: string } | null; deployment: { name: string } | null } | null;
}

const props = defineProps<{
    round: MatchRound;
    game: MatchGame;
    myPlayerId: number;
    roundTimeLimit: number;
    tournamentUuid: string;
    /** The event's players, so team events can show who is on each team. */
    players?: { id: number; display_name: string; members?: { display_name: string }[] }[];
    /** Whether players may submit their own results for the opponent to confirm. */
    allowSelfReport?: boolean;
    /** The ruleset's score components (e.g. strategy + scheme VP), one input each. */
    scoreFields?: { key: string; label: string; min: number; max: number }[];
    /** The organizer's notes for this game's table (name, terrain, photo). */
    tableDetail?: TableDetail | null;
}>();

const iAmOne = computed(() => props.game.player_one_id === props.myPlayerId);
const opponent = computed(() => {
    const g = props.game;
    return iAmOne.value
        ? { name: g.player_two?.display_name ?? null, faction: g.player_two_faction, master: g.player_two_title || g.player_two_master }
        : { name: g.player_one.display_name, faction: g.player_one_faction, master: g.player_one_title || g.player_one_master };
});
const myFaction = computed(() => (iAmOne.value ? props.game.player_one_faction : props.game.player_two_faction));
const myMaster = computed(() =>
    iAmOne.value ? props.game.player_one_title || props.game.player_one_master : props.game.player_two_title || props.game.player_two_master,
);

// Team events: the team name and its members, for both sides.
const teamLine = (id: number | null) => {
    const p = id === null ? undefined : props.players?.find((x) => x.id === id);
    return p?.members?.length ? `${p.display_name} — ${p.members.map((m) => m.display_name).join(' · ')}` : null;
};
const myTeam = computed(() => teamLine(props.myPlayerId));
const opponentTeam = computed(() => teamLine(iAmOne.value ? props.game.player_two_id : props.game.player_one_id));

const inProgress = computed(() => props.round.status === 'in_progress');
const liveGame = computed(() => (props.game.tracker_game && props.game.tracker_game.status !== 'completed' ? props.game.tracker_game : null));
const finished = computed(() => props.game.result !== 'pending');
const scoreLine = computed(() => {
    const mine = iAmOne.value ? props.game.player_one_vp : props.game.player_two_vp;
    const theirs = iAmOne.value ? props.game.player_two_vp : props.game.player_one_vp;
    return mine === null || theirs === null ? null : `${mine} – ${theirs}`;
});

// ─── Self-reporting ───
const fields = computed(() => props.scoreFields ?? []);
const opponentName = computed(() => opponent.value.name ?? 'your opponent');
const reportError = computed(() => (usePage().props.errors as Record<string, string> | undefined)?.report ?? null);
const canReport = computed(
    () =>
        !!props.allowSelfReport && inProgress.value && !finished.value && !props.game.is_bye && !props.game.is_forfeit && !!props.game.player_two_id,
);
const iReported = computed(() => props.game.reported_by_player_id === props.myPlayerId);
const proposal = computed(() => props.game.reported_score ?? null);
/** The proposed score from my point of view: [mine, theirs] component maps. */
const proposedMineTheirs = computed(() => {
    const p = proposal.value;
    if (!p) return null;
    return iAmOne.value ? { mine: p.player_one, theirs: p.player_two } : { mine: p.player_two, theirs: p.player_one };
});
const total = (scores: Record<string, number> | undefined) => Object.values(scores ?? {}).reduce((a, b) => a + (Number(b) || 0), 0);

const reporting = ref(false);
const disputing = ref(false);
const busy = ref(false);
const mine = ref<Record<string, number>>({});
const theirs = ref<Record<string, number>>({});
const agreed = ref(false);
const disputeNote = ref('');

const openReportForm = () => {
    mine.value = Object.fromEntries(fields.value.map((f) => [f.key, 0]));
    theirs.value = Object.fromEntries(fields.value.map((f) => [f.key, 0]));
    agreed.value = false;
    reporting.value = true;
};

const reportRoute = (name: string) => route(name, { tournament: props.tournamentUuid, game: props.game.id });
const send = (method: 'post' | 'delete', name: string, data: Record<string, unknown> = {}, done?: () => void) => {
    busy.value = true;
    router[method](reportRoute(name), data, {
        preserveScroll: true,
        onSuccess: () => done?.(),
        onFinish: () => (busy.value = false),
    });
};
const submitReport = () =>
    send(
        'post',
        'tournaments.games.report',
        {
            player_one_score: iAmOne.value ? mine.value : theirs.value,
            player_two_score: iAmOne.value ? theirs.value : mine.value,
            agreed: agreed.value,
        },
        () => (reporting.value = false),
    );
const confirmReport = () => send('post', 'tournaments.games.report.confirm');
const submitDispute = () =>
    send('post', 'tournaments.games.report.dispute', { note: disputeNote.value.trim() || null }, () => (disputing.value = false));
const withdrawReport = () => send('delete', 'tournaments.games.report.withdraw');
</script>

<template>
    <Card class="mb-6 border-primary/40 bg-primary/5" data-no-print>
        <CardContent class="space-y-3 p-4">
            <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-primary">Your match</h2>
                <span class="text-sm text-muted-foreground">
                    {{ round.label ?? `Round ${round.round_number}` }}<template v-if="game.table_number"> · Table {{ game.table_number }}</template
                    ><template v-if="tableDetail?.name"> — {{ tableDetail.name }}</template>
                </span>
                <RoundClock v-if="inProgress" :started-at="round.started_at" :limit-minutes="roundTimeLimit" class="ml-auto" />
            </div>

            <TableTerrain
                v-if="!game.is_bye && tableDetail && (tableDetail.description || tableDetail.image_url)"
                :detail="tableDetail"
                :show-title="false"
            />

            <div v-if="game.is_bye" class="text-sm">You have a bye this round — a free win. Enjoy the break.</div>
            <div v-else class="flex flex-wrap items-center gap-3">
                <div class="flex min-w-0 items-center gap-2">
                    <FactionLogo v-if="myFaction" :faction="myFaction" class-name="size-6 shrink-0" />
                    <div class="min-w-0">
                        <div class="truncate font-semibold">You</div>
                        <div v-if="myTeam" class="truncate text-xs text-muted-foreground">{{ myTeam }}</div>
                        <div v-if="myMaster" class="truncate text-xs text-muted-foreground">{{ myMaster }}</div>
                    </div>
                </div>
                <Swords class="size-4 shrink-0 text-muted-foreground" aria-hidden="true" />
                <div class="flex min-w-0 items-center gap-2">
                    <FactionLogo v-if="opponent.faction" :faction="opponent.faction" class-name="size-6 shrink-0" />
                    <div class="min-w-0">
                        <div class="truncate font-semibold">{{ opponent.name ?? 'TBD' }}</div>
                        <div v-if="opponentTeam" class="truncate text-xs text-muted-foreground">{{ opponentTeam }}</div>
                        <div v-if="opponent.master" class="truncate text-xs text-muted-foreground">{{ opponent.master }}</div>
                    </div>
                </div>
                <div v-if="finished && scoreLine" class="ml-auto text-lg font-bold tabular-nums">{{ scoreLine }}</div>
            </div>

            <div v-if="round.tos_scenario" class="text-xs text-muted-foreground">
                <span v-if="round.tos_scenario.operation"
                    >Operation: <span class="font-medium text-foreground">{{ round.tos_scenario.operation.name }}</span></span
                >
                <span v-if="round.tos_scenario.operation && round.tos_scenario.deployment"> · </span>
                <span v-if="round.tos_scenario.deployment"
                    >Deployment: <span class="font-medium text-foreground">{{ round.tos_scenario.deployment.name }}</span></span
                >
            </div>
            <div v-else-if="round.deployment || round.strategy" class="text-xs text-muted-foreground">
                <span v-if="round.deployment"
                    >Deployment: <span class="font-medium text-foreground">{{ round.deployment.label }}</span></span
                >
                <span v-if="round.deployment && round.strategy"> · </span>
                <span v-if="round.strategy"
                    >Strategy: <span class="font-medium text-foreground">{{ round.strategy.name }}</span></span
                >
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <Button v-if="liveGame" as-child size="sm">
                    <Link :href="route('games.show', liveGame.uuid)"><Gamepad2 class="mr-1.5 size-4" /> Open live game</Link>
                </Button>
                <p v-else-if="game.is_bye" class="text-xs text-muted-foreground">Nothing to do until the next round is paired.</p>
                <p v-else-if="!inProgress" class="text-xs text-muted-foreground">
                    {{ round.label ?? `Round ${round.round_number}` }} {{ round.is_cut ? 'have' : 'has' }}n't started yet — find your table and get
                    set up.
                </p>
                <p v-else-if="!finished" class="text-xs text-muted-foreground">
                    {{ canReport ? 'Play your game, then report the result below.' : 'Play your game, then give your scores to the organizer.' }}
                </p>
                <p v-else-if="game.is_forfeit" class="text-xs text-muted-foreground">This game was recorded as a forfeit.</p>
                <p v-else class="text-xs text-muted-foreground">Result recorded.</p>
            </div>

            <!-- Player self-reporting: propose → opponent confirms/disputes → official -->
            <div v-if="canReport" class="space-y-2 rounded-md border bg-background/60 p-3 text-sm" data-testid="self-report">
                <p v-if="reportError" class="text-xs text-destructive" role="alert">{{ reportError }}</p>

                <template v-if="game.report_status === 'disputed'">
                    <p class="text-xs">
                        <span class="font-medium">This result was disputed</span> — the organizer will settle it.
                        <span v-if="game.report_note" class="block text-muted-foreground">“{{ game.report_note }}”</span>
                    </p>
                    <Button v-if="!reporting" size="sm" variant="outline" :disabled="busy" @click="openReportForm">Submit a new result</Button>
                </template>

                <template v-else-if="game.report_status === 'pending' && proposedMineTheirs">
                    <p v-if="iReported" class="text-xs">
                        You reported
                        <span class="font-bold tabular-nums">{{ total(proposedMineTheirs.mine) }} – {{ total(proposedMineTheirs.theirs) }}</span>
                        <span v-if="proposal?.agreed"> (agreed result)</span>. Waiting for {{ opponentName }} to confirm.
                    </p>
                    <div
                        v-else
                        class="space-y-2 rounded-md border border-amber-500/50 bg-amber-500/10 p-3"
                        role="group"
                        aria-label="Result to confirm"
                    >
                        <p class="text-xs font-medium">
                            {{ opponentName }} reported this result<span v-if="proposal?.agreed"> (an agreed result)</span> — is it right?
                        </p>
                        <div class="flex items-center justify-center gap-4 text-3xl font-bold tabular-nums">
                            <span><span class="mr-1 text-xs font-normal text-muted-foreground">You</span>{{ total(proposedMineTheirs.mine) }}</span>
                            <span class="text-muted-foreground" aria-hidden="true">–</span>
                            <span
                                >{{ total(proposedMineTheirs.theirs) }}<span class="ml-1 text-xs font-normal text-muted-foreground">Them</span></span
                            >
                        </div>
                        <ul v-if="fields.length > 1" class="flex flex-wrap justify-center gap-x-4 gap-y-0.5 text-2xs text-muted-foreground">
                            <li v-for="f in fields" :key="'p-' + f.key">
                                {{ f.label }}: {{ proposedMineTheirs.mine[f.key] ?? 0 }} – {{ proposedMineTheirs.theirs[f.key] ?? 0 }}
                            </li>
                        </ul>
                    </div>
                    <div v-if="iReported" class="flex gap-2">
                        <Button size="sm" variant="outline" :disabled="busy" @click="withdrawReport">Withdraw</Button>
                    </div>
                    <div v-else-if="!disputing" class="flex flex-wrap gap-2">
                        <Button size="sm" :disabled="busy" @click="confirmReport"
                            ><Loader2 v-if="busy" class="mr-1 size-3 animate-spin" /> Confirm</Button
                        >
                        <Button size="sm" variant="outline" :disabled="busy" @click="disputing = true">Dispute</Button>
                    </div>
                    <div v-else class="space-y-2">
                        <Label for="dispute-note" class="text-2xs">What's different? (optional)</Label>
                        <Textarea id="dispute-note" v-model="disputeNote" rows="2" maxlength="500" class="text-xs" />
                        <div class="flex gap-2">
                            <Button size="sm" variant="destructive" :disabled="busy" @click="submitDispute">Send dispute</Button>
                            <Button size="sm" variant="ghost" :disabled="busy" @click="disputing = false">Cancel</Button>
                        </div>
                    </div>
                </template>

                <Button v-else-if="!reporting" size="sm" :disabled="busy" @click="openReportForm">Report result</Button>

                <form v-if="reporting" class="space-y-3" @submit.prevent="submitReport">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <fieldset class="space-y-1.5">
                            <legend class="text-xs font-semibold">You</legend>
                            <div v-for="f in fields" :key="'m-' + f.key" class="space-y-1">
                                <Label :for="`rep-mine-${f.key}`" class="text-2xs"
                                    >{{ f.label }} <span class="text-muted-foreground">/ {{ f.max }}</span></Label
                                >
                                <Input
                                    :id="`rep-mine-${f.key}`"
                                    v-model.number="mine[f.key]"
                                    type="number"
                                    :min="f.min"
                                    :max="f.max"
                                    class="h-8 text-center text-sm font-bold"
                                />
                            </div>
                        </fieldset>
                        <fieldset class="space-y-1.5">
                            <legend class="text-xs font-semibold">{{ opponentName }}</legend>
                            <div v-for="f in fields" :key="'t-' + f.key" class="space-y-1">
                                <Label :for="`rep-theirs-${f.key}`" class="text-2xs"
                                    >{{ f.label }} <span class="text-muted-foreground">/ {{ f.max }}</span></Label
                                >
                                <Input
                                    :id="`rep-theirs-${f.key}`"
                                    v-model.number="theirs[f.key]"
                                    type="number"
                                    :min="f.min"
                                    :max="f.max"
                                    class="h-8 text-center text-sm font-bold"
                                />
                            </div>
                        </fieldset>
                    </div>
                    <label class="flex items-start gap-2 text-xs">
                        <Checkbox :checked="agreed" class="mt-0.5" @update:checked="(on) => (agreed = on === true)" />
                        <span>The game wasn't played out — we agreed this is the likely result as it stands.</span>
                    </label>
                    <div class="flex gap-2">
                        <Button type="submit" size="sm" :disabled="busy"
                            ><Loader2 v-if="busy" class="mr-1 size-3 animate-spin" /> Submit result</Button
                        >
                        <Button type="button" size="sm" variant="ghost" :disabled="busy" @click="reporting = false">Cancel</Button>
                    </div>
                    <p class="text-2xs text-muted-foreground">{{ opponentName }} will be asked to confirm. The organizer can always correct it.</p>
                </form>
            </div>
        </CardContent>
    </Card>
</template>
