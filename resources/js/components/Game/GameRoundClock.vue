<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

/**
 * The tournament round's clock (Gaining Grounds One, Round Time Limit): time left, and what the TO's calls mean. At "last turn" players finish
 * the turn they are on and end the game in step F of the end phase; when time is called they finish the activation they are on, then one more
 * (unless that was the final activation of the turn). It counts against the server's time, so a wrong clock on this device does not matter.
 */
export interface RoundClock {
    now: string;
    started_at: string;
    ends_at: string;
    last_turn_at: string;
    limit: number;
}

const props = defineProps<{ clock: RoundClock }>();

// how far this device's clock is from the server's, taken when the page was loaded
const skew = Date.parse(props.clock.now) - Date.now();
const tick = ref(Date.now());
let timer: ReturnType<typeof setInterval> | undefined;
onMounted(() => (timer = setInterval(() => (tick.value = Date.now()), 1000)));
onBeforeUnmount(() => clearInterval(timer));

const left = computed(() => Date.parse(props.clock.ends_at) - (tick.value + skew));
const toLastTurn = computed(() => Date.parse(props.clock.last_turn_at) - (tick.value + skew));
const phase = computed<'running' | 'last' | 'called'>(() => (left.value <= 0 ? 'called' : toLastTurn.value <= 0 ? 'last' : 'running'));
const text = computed(() => {
    const total = Math.max(0, Math.floor(left.value / 1000));
    const h = Math.floor(total / 3600);
    const m = Math.floor((total % 3600) / 60);
    const s = total % 60;
    return h ? `${h}:${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}` : `${m}:${String(s).padStart(2, '0')}`;
});
</script>

<template>
    <div
        class="flex flex-wrap items-center gap-x-3 gap-y-1 rounded-md border px-3 py-2 text-sm"
        :class="phase === 'called' ? 'border-red-500/50 bg-red-500/10' : phase === 'last' ? 'border-amber-500/50 bg-amber-500/10' : 'bg-muted/30'"
        :data-phase="phase"
        data-testid="round-clock"
    >
        <span class="font-semibold tabular-nums" data-testid="round-clock-left">{{ phase === 'called' ? 'Time' : text + ' left' }}</span>
        <span v-if="phase === 'last'" data-testid="round-clock-note">
            Last turn called: finish the turn you are on, and the game ends in step F of its end phase.
        </span>
        <span v-else-if="phase === 'called'" data-testid="round-clock-note">
            Time called: finish the activation you are on, then one more (unless it was the last of the turn).
        </span>
        <span v-else class="text-xs text-muted-foreground">Round of {{ clock.limit }} minutes.</span>
    </div>
</template>
