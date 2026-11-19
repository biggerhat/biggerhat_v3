<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { computed } from 'vue';

/**
 * Where the turn is (CRG, Turn sequence): who has initiative, whose activation it is, how many models each crew has left to activate,
 * and whether passing is allowed. It only shows the state the server keeps and emits what the player presses; the rules live in
 * App\Services\Game\TurnSequence.
 */
export interface TurnState {
    turn: number;
    max_turns: number;
    /** Slot with initiative this turn, or null until it has been chosen. */
    initiative: number | null;
    /** Slot whose activation it is, or null before initiative / when everyone has activated. */
    active: number | null;
    /** Slot that must choose who has initiative (turn 2 on), while it has not been chosen. */
    chooser: number | null;
    unactivated: Record<number, number>;
    can_pass: Record<number, boolean>;
    /** Why a pass is not allowed, when it is the active player's turn and they may not. */
    pass_note: string | null;
}

const props = defineProps<{
    turn: TurnState;
    /** Display names by slot. */
    names: Record<number, string>;
    /** The slots this viewer plays (both in a solo game, none for an observer). */
    mine: number[];
    busy?: boolean;
}>();
const emit = defineEmits<{ pass: [slot: number]; initiative: [chooser: number, initiative: number] }>();

const nameOf = (slot: number | null) => (slot === null ? 'Nobody' : (props.names[slot] ?? `Player ${slot}`));
const slots = computed(() => Object.keys(props.turn.unactivated).map(Number).sort());
const iChoose = computed(() => props.turn.chooser !== null && props.mine.includes(props.turn.chooser));
const iAmActive = computed(() => props.turn.active !== null && props.mine.includes(props.turn.active));
const allDone = computed(() => slots.value.every((s) => props.turn.unactivated[s] === 0));
</script>

<template>
    <div class="space-y-1.5 rounded-md border bg-muted/30 px-3 py-2 text-sm" data-testid="turn-bar">
        <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
            <span class="font-semibold" data-testid="turn-number">Turn {{ turn.turn }} of {{ turn.max_turns }}</span>
            <span v-if="turn.initiative !== null" data-testid="turn-initiative">Initiative: {{ nameOf(turn.initiative) }}</span>
            <span v-if="turn.active !== null" class="font-medium text-primary" data-testid="turn-active">{{ nameOf(turn.active) }} to activate</span>
            <span v-else-if="turn.initiative !== null && allDone" class="text-muted-foreground" data-testid="turn-end-phase">
                Everyone has activated: end phase
            </span>
            <span class="ml-auto text-xs text-muted-foreground" data-testid="turn-left">
                Left to activate:
                <template v-for="(s, i) in slots" :key="s">{{ i ? ' · ' : ' ' }}{{ nameOf(s) }} {{ turn.unactivated[s] }}</template>
            </span>
        </div>

        <div v-if="turn.chooser !== null && turn.initiative === null" class="flex flex-wrap items-center gap-1.5" data-testid="turn-choose">
            <span>{{ nameOf(turn.chooser) }} chooses who has initiative:</span>
            <template v-if="iChoose">
                <Button
                    v-for="s in slots"
                    :key="s"
                    size="sm"
                    variant="outline"
                    :disabled="busy"
                    :data-testid="`turn-initiative-${s}`"
                    @click="emit('initiative', turn.chooser!, s)"
                    >{{ nameOf(s) }}</Button
                >
            </template>
        </div>

        <div v-if="iAmActive" class="flex flex-wrap items-center gap-2" data-testid="turn-pass-row">
            <Button
                size="sm"
                variant="outline"
                :disabled="busy || !turn.can_pass[turn.active!]"
                data-testid="turn-pass"
                @click="emit('pass', turn.active!)"
                >Pass</Button
            >
            <span class="text-xs text-muted-foreground">{{
                turn.can_pass[turn.active!]
                    ? 'You have no more models than your opponent left to activate, so you may pass.'
                    : (turn.pass_note ?? 'You may pass only with fewer models left to activate than your opponent (or tied for fewest).')
            }}</span>
        </div>
    </div>
</template>
