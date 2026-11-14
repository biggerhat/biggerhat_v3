<script setup lang="ts">
import InsightBars from '@/components/Tournament/InsightBars.vue';
import { Card, CardContent } from '@/components/ui/card';
import type { InsightSchemes, InsightStrategyRow } from '@/types/tournament';
import { computed } from 'vue';

/** Strategy and scheme results, shared by the event Insights tab and the all-events / league pages. */
const props = defineProps<{ strategies: InsightStrategyRow[]; schemes: InsightSchemes }>();

const num = (v: number | null | undefined) => (v === null || v === undefined ? '–' : v);
const scoredItems = computed(() =>
    props.schemes.rows.filter((s) => s.picked > 0).map((s) => ({ label: s.name, value: s.score_rate ?? 0, note: `${s.scored}/${s.picked}` })),
);
const trackedPct = computed(() =>
    props.schemes.coverage.total ? Math.round((props.schemes.coverage.tracked / props.schemes.coverage.total) * 100) : 0,
);
</script>

<template>
    <Card v-if="strategies.length" data-testid="insight-strategies">
        <CardContent class="space-y-3 p-4">
            <h2 class="text-sm font-semibold">Strategies</h2>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[28rem] text-xs">
                    <thead>
                        <tr class="border-b text-left text-muted-foreground">
                            <th scope="col" class="py-1.5 pr-3 font-medium">Strategy</th>
                            <th scope="col" class="px-2 text-right font-medium">Rounds</th>
                            <th scope="col" class="px-2 text-right font-medium">Games</th>
                            <th scope="col" class="px-2 text-right font-medium">Avg score</th>
                            <th scope="col" class="px-2 text-right font-medium">Avg margin</th>
                            <th scope="col" class="pl-2 text-right font-medium">Strategy VP</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="s in strategies" :key="s.name" class="border-b last:border-0">
                            <th scope="row" class="py-1.5 pr-3 text-left font-medium">{{ s.name }}</th>
                            <td class="px-2 text-right tabular-nums">{{ s.rounds }}</td>
                            <td class="px-2 text-right tabular-nums">{{ s.games }}</td>
                            <td class="px-2 text-right tabular-nums">{{ num(s.avg_vp) }}</td>
                            <td class="px-2 text-right tabular-nums">{{ num(s.avg_margin) }}</td>
                            <td class="pl-2 text-right tabular-nums">{{ num(s.components.strategy) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p class="text-2xs text-muted-foreground">Scores are per player per game. A strategy played in more than one round is combined.</p>
        </CardContent>
    </Card>

    <Card v-if="schemes.rows.length" data-testid="insight-schemes">
        <CardContent class="space-y-3 p-4">
            <h2 class="text-sm font-semibold">Schemes</h2>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[28rem] text-xs">
                    <thead>
                        <tr class="border-b text-left text-muted-foreground">
                            <th scope="col" class="py-1.5 pr-3 font-medium">Scheme</th>
                            <th scope="col" class="px-2 text-right font-medium">Picked</th>
                            <th scope="col" class="px-2 text-right font-medium">Scored</th>
                            <th scope="col" class="px-2 text-right font-medium">Discarded</th>
                            <th scope="col" class="px-2 text-right font-medium">Points</th>
                            <th scope="col" class="pl-2 text-right font-medium">Avg when scored</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="s in schemes.rows" :key="s.name" class="border-b last:border-0">
                            <th scope="row" class="py-1.5 pr-3 text-left font-medium">{{ s.name }}</th>
                            <td class="px-2 text-right tabular-nums">{{ s.picked }}</td>
                            <td class="px-2 text-right tabular-nums">{{ s.scored }}</td>
                            <td class="px-2 text-right tabular-nums">{{ s.discarded }}</td>
                            <td class="px-2 text-right tabular-nums">{{ s.points }}</td>
                            <td class="pl-2 text-right tabular-nums">{{ num(s.avg_points) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div v-if="scoredItems.length > 1">
                <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">How often a picked scheme scored</h3>
                <InsightBars :items="scoredItems" :max="100" unit="%" />
            </div>
            <p class="text-2xs text-muted-foreground">
                Only games played through the Game Tracker record schemes: {{ schemes.coverage.tracked }} of {{ schemes.coverage.total }} games ({{
                    trackedPct
                }}%). A scheme still held at the end of a game is not counted.
            </p>
        </CardContent>
    </Card>
</template>
