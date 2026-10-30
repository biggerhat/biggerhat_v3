<script setup lang="ts">
import FactionLogo from '@/components/FactionLogo.vue';
import RoundClock from '@/components/Tournament/RoundClock.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Link } from '@inertiajs/vue3';
import { Gamepad2, Swords } from 'lucide-vue-next';
import { computed } from 'vue';

interface Side {
    display_name: string;
}

interface MatchGame {
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
}

interface MatchRound {
    round_number: number;
    status: string;
    started_at: string | null;
    deployment: { label: string } | null;
    strategy: { name: string } | null;
}

const props = defineProps<{
    round: MatchRound;
    game: MatchGame;
    myPlayerId: number;
    roundTimeLimit: number;
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

const inProgress = computed(() => props.round.status === 'in_progress');
const liveGame = computed(() => (props.game.tracker_game && props.game.tracker_game.status !== 'completed' ? props.game.tracker_game : null));
const finished = computed(() => props.game.result !== 'pending');
const scoreLine = computed(() => {
    const mine = iAmOne.value ? props.game.player_one_vp : props.game.player_two_vp;
    const theirs = iAmOne.value ? props.game.player_two_vp : props.game.player_one_vp;
    return mine === null || theirs === null ? null : `${mine} – ${theirs}`;
});
</script>

<template>
    <Card class="mb-6 border-primary/40 bg-primary/5" data-no-print>
        <CardContent class="space-y-3 p-4">
            <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-primary">Your match</h2>
                <span class="text-sm text-muted-foreground">
                    Round {{ round.round_number }}<template v-if="game.table_number"> · Table {{ game.table_number }}</template>
                </span>
                <RoundClock v-if="inProgress" :started-at="round.started_at" :limit-minutes="roundTimeLimit" class="ml-auto" />
            </div>

            <div v-if="game.is_bye" class="text-sm">You have a bye this round — a free win. Enjoy the break.</div>
            <div v-else class="flex flex-wrap items-center gap-3">
                <div class="flex min-w-0 items-center gap-2">
                    <FactionLogo v-if="myFaction" :faction="myFaction" class-name="size-6 shrink-0" />
                    <div class="min-w-0">
                        <div class="truncate font-semibold">You</div>
                        <div v-if="myMaster" class="truncate text-xs text-muted-foreground">{{ myMaster }}</div>
                    </div>
                </div>
                <Swords class="size-4 shrink-0 text-muted-foreground" aria-hidden="true" />
                <div class="flex min-w-0 items-center gap-2">
                    <FactionLogo v-if="opponent.faction" :faction="opponent.faction" class-name="size-6 shrink-0" />
                    <div class="min-w-0">
                        <div class="truncate font-semibold">{{ opponent.name ?? 'TBD' }}</div>
                        <div v-if="opponent.master" class="truncate text-xs text-muted-foreground">{{ opponent.master }}</div>
                    </div>
                </div>
                <div v-if="finished && scoreLine" class="ml-auto text-lg font-bold tabular-nums">{{ scoreLine }}</div>
            </div>

            <div v-if="round.deployment || round.strategy" class="text-xs text-muted-foreground">
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
                    Round {{ round.round_number }} hasn't started yet — find your table and get set up.
                </p>
                <p v-else-if="!finished" class="text-xs text-muted-foreground">Play your game, then give your scores to the organizer.</p>
                <p v-else-if="game.is_forfeit" class="text-xs text-muted-foreground">This game was recorded as a forfeit.</p>
                <p v-else class="text-xs text-muted-foreground">Result recorded.</p>
            </div>
        </CardContent>
    </Card>
</template>
