<script setup lang="ts">
import { cn } from '@/lib/utils';
import { usePage } from '@inertiajs/vue3';
import { Timer } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref, type HTMLAttributes } from 'vue';

const props = defineProps<{
    /** ISO timestamp the round started at. */
    startedAt: string | null;
    /** Round time limit in minutes, from the tournament settings. */
    limitMinutes: number;
    class?: HTMLAttributes['class'];
}>();

// Only tick on the client: rendering Date.now() during SSR would never match the hydrated markup.
const now = ref<number | null>(null);
let timer: ReturnType<typeof setInterval> | undefined;
onMounted(() => {
    now.value = Date.now();
    timer = setInterval(() => (now.value = Date.now()), 1000);
});
onBeforeUnmount(() => clearInterval(timer));

const remaining = computed(() => {
    if (!props.startedAt || now.value === null) return null;
    return Math.round((new Date(props.startedAt).getTime() + props.limitMinutes * 60_000 - now.value) / 1000);
});

// The page's ruleset (Manage / View) supplies the format's last-turn wording; clocks elsewhere fall back to 15 minutes.
const timeRules = computed(
    () =>
        (usePage().props.ruleset as { time_rules?: { last_turn_minutes: number; last_turn: string | null; time_called: string | null } } | undefined)
            ?.time_rules,
);
const lastTurnSeconds = computed(() => (timeRules.value?.last_turn_minutes ?? 15) * 60);
const inLastTurn = computed(() => remaining.value !== null && remaining.value >= 0 && remaining.value <= lastTurnSeconds.value);
const rulesText = computed(() => [timeRules.value?.last_turn, timeRules.value?.time_called].filter(Boolean).join(' ') || null);

const pad = (n: number) => String(n).padStart(2, '0');
const label = computed(() => {
    if (remaining.value === null) return null;
    const abs = Math.abs(remaining.value);
    const h = Math.floor(abs / 3600);
    const m = Math.floor((abs % 3600) / 60);
    const s = abs % 60;
    const clock = h > 0 ? `${h}:${pad(m)}:${pad(s)}` : `${m}:${pad(s)}`;
    if (remaining.value < 0) return `Time! +${clock}`;
    return inLastTurn.value && timeRules.value?.last_turn ? `${clock} left · last turn` : `${clock} left`;
});

const tone = computed(() => {
    if (remaining.value === null) return 'text-muted-foreground';
    if (remaining.value < 0) return 'border-destructive/50 bg-destructive/10 text-red-700 dark:text-red-300';
    if (remaining.value <= lastTurnSeconds.value) return 'border-amber-500/50 bg-amber-500/10 text-amber-700 dark:text-amber-300';
    return 'border-emerald-500/40 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300';
});
</script>

<template>
    <span
        v-if="startedAt"
        :class="cn('inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-xs font-medium tabular-nums', tone, props.class)"
        :title="[`Round time limit: ${limitMinutes} minutes`, inLastTurn || (remaining ?? 1) < 0 ? rulesText : null].filter(Boolean).join('\n')"
        role="timer"
    >
        <Timer class="size-3" aria-hidden="true" />
        <span>{{ label ?? `${limitMinutes} min round` }}</span>
    </span>
</template>
