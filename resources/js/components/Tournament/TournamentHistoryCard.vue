<script setup lang="ts">
import FactionLogo from '@/components/FactionLogo.vue';
import HeadingEyebrow from '@/components/HeadingEyebrow.vue';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { formatDateOnly } from '@/lib/utils';
import type { TournamentHistory } from '@/types/tournament';
import { Link } from '@inertiajs/vue3';
import { Trophy } from 'lucide-vue-next';
import { computed } from 'vue';

/** A player's tournament record on their public stats page: headline numbers, per-faction results, rivals and every finished event. */
const props = defineProps<{ history: TournamentHistory }>();

const ordinal = (n: number) => {
    const mod100 = n % 100;
    if (mod100 >= 11 && mod100 <= 13) return `${n}th`;
    return `${n}${({ 1: 'st', 2: 'nd', 3: 'rd' } as Record<number, string>)[n % 10] ?? 'th'}`;
};
const placeTone = (rank: number | null) =>
    rank === 1
        ? 'border-amber-500/60 bg-amber-500/10 text-amber-700 dark:text-amber-300'
        : rank === 2
          ? 'border-slate-400/60 bg-slate-400/10'
          : rank === 3
            ? 'border-orange-700/60 bg-orange-700/10 text-orange-800 dark:text-orange-300'
            : '';
const record = (r: { won: number; lost: number; tied: number }) => `${r.won}-${r.lost}-${r.tied}`;

const tiles = computed(() => {
    const s = props.history.summary;
    return [
        { label: 'Events', value: String(s.events) },
        { label: 'Wins', value: String(s.wins) },
        { label: 'Podiums', value: String(s.podiums) },
        { label: 'Best finish', value: s.best_finish ? ordinal(s.best_finish) : '–' },
        { label: 'Record', value: record(s) },
        { label: 'Win rate', value: s.win_rate === null ? '–' : `${s.win_rate}%` },
    ];
});
</script>

<template>
    <Card v-if="history.summary.events > 0" class="mb-6" data-testid="tournament-history">
        <CardContent class="space-y-4 p-4">
            <HeadingEyebrow class="flex items-center gap-2"><Trophy class="size-4" /> Tournament history</HeadingEyebrow>

            <dl class="grid grid-cols-3 gap-2 sm:grid-cols-6">
                <div v-for="t in tiles" :key="t.label" class="rounded-md border bg-muted/30 p-2 text-center">
                    <dt class="text-2xs uppercase text-muted-foreground">{{ t.label }}</dt>
                    <dd class="text-sm font-bold tabular-nums">{{ t.value }}</dd>
                </div>
            </dl>

            <ul class="space-y-1.5">
                <li v-for="e in history.events" :key="e.uuid">
                    <Link
                        :href="route('tournaments.view', e.uuid)"
                        class="flex items-center gap-3 rounded-md border px-3 py-2 text-xs transition-colors hover:bg-muted/50"
                    >
                        <span
                            v-if="e.rank"
                            class="w-16 shrink-0 rounded-full border px-2 py-0.5 text-center font-semibold tabular-nums"
                            :class="placeTone(e.rank)"
                        >
                            {{ ordinal(e.rank) }}<span class="font-normal text-muted-foreground"> / {{ e.of }}</span>
                        </span>
                        <FactionLogo v-if="e.faction" :faction="e.faction" class-name="size-5 shrink-0" />
                        <div class="min-w-0 flex-1">
                            <div class="truncate font-medium">{{ e.name }}</div>
                            <div class="truncate text-2xs text-muted-foreground">
                                {{ formatDateOnly(e.event_date) }} · {{ e.ruleset }}
                                <template v-if="e.is_team"> · with {{ e.team_name }}</template>
                            </div>
                        </div>
                        <div class="shrink-0 text-right tabular-nums">
                            <div class="font-bold">{{ record(e) }}</div>
                            <div class="text-2xs text-muted-foreground">{{ e.tp }} TP</div>
                        </div>
                    </Link>
                </li>
            </ul>

            <div v-if="history.by_faction.length > 1 || history.rivals.length" class="grid gap-4 sm:grid-cols-2">
                <div v-if="history.by_faction.length > 1">
                    <h3 class="mb-2 text-sm font-semibold">By faction</h3>
                    <ul class="space-y-1">
                        <li v-for="f in history.by_faction" :key="f.faction" class="flex items-center gap-2 text-xs">
                            <FactionLogo :faction="f.faction" class-name="size-4 shrink-0" />
                            <span class="flex-1 truncate">{{ f.label }}</span>
                            <span class="tabular-nums text-muted-foreground">{{ f.games }} {{ f.games === 1 ? 'game' : 'games' }}</span>
                            <Badge variant="outline" class="px-1.5 py-0 tabular-nums">{{ record(f) }}</Badge>
                        </li>
                    </ul>
                </div>
                <div v-if="history.rivals.length">
                    <h3 class="mb-2 text-sm font-semibold">Most-played opponents</h3>
                    <ul class="space-y-1">
                        <li v-for="r in history.rivals" :key="r.name + (r.slug ?? '')" class="flex items-center gap-2 text-xs">
                            <Link v-if="r.slug" :href="route('stats.show', r.slug)" class="flex-1 truncate text-primary hover:underline">{{
                                r.name
                            }}</Link>
                            <span v-else class="flex-1 truncate">{{ r.name }}</span>
                            <span class="tabular-nums text-muted-foreground">{{ r.games }} games</span>
                            <Badge variant="outline" class="px-1.5 py-0 tabular-nums">{{ record(r) }}</Badge>
                        </li>
                    </ul>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
