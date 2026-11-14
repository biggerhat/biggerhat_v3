<script setup lang="ts">
import InsightBars from '@/components/Tournament/InsightBars.vue';
import InsightScenarios from '@/components/Tournament/InsightScenarios.vue';
import InsightBreakdown from '@/components/Tournament/InsightBreakdown.vue';
import InsightTiles from '@/components/Tournament/InsightTiles.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import type { EventInsightsData } from '@/types/tournament';
import { BarChart3, Download } from 'lucide-vue-next';
import { computed } from 'vue';

/** The Insights tab: how the event went. `insights` is undefined while the deferred request is still loading. */
const props = defineProps<{ insights?: EventInsightsData | null; isCompleted: boolean; csvUrl: string }>();

const dash = '–';
const num = (v: number | null | undefined, suffix = '') => (v === null || v === undefined ? dash : `${v}${suffix}`);

const turnout = computed(() => {
    const f = props.insights?.field;
    if (!f) return [];
    return [
        { label: 'Registered', value: f.registered, hint: f.rsvps > f.registered ? `${f.rsvps} RSVPs` : undefined },
        { label: 'Played', value: f.played, hint: f.registered > 0 ? `${Math.round((f.played / f.registered) * 100)}% of registered` : undefined },
        { label: 'Dropped', value: f.dropped },
        { label: 'Disqualified', value: f.disqualified },
        { label: 'Returning', value: f.returning, hint: f.with_accounts > 0 ? `of ${f.with_accounts} with accounts` : 'no accounts to compare' },
        { label: 'New to you', value: f.new },
    ];
});

const gameTiles = computed(() => {
    const g = props.insights?.games;
    if (!g) return [];
    return [
        { label: 'Games played', value: g.contested },
        { label: 'Avg score', value: num(g.avg_vp), hint: 'per player' },
        { label: 'Avg margin', value: num(g.avg_margin) },
        { label: 'Close games', value: num(g.close_pct, '%'), hint: `${g.close} decided by 2 or less` },
        { label: 'Byes', value: g.byes },
        { label: 'Forfeits', value: g.forfeits },
    ];
});

const marginItems = computed(() => (props.insights?.games.margin_buckets ?? []).map((b) => ({ label: b.label, value: b.count })));
const roundItems = computed(() =>
    (props.insights?.rounds ?? []).filter((r) => r.minutes !== null).map((r) => ({ label: r.label, value: r.minutes as number, note: 'min' })),
);
const roundsWithGames = computed(() => (props.insights?.rounds ?? []).filter((r) => r.games > 0 || r.forfeits > 0));
const hasGames = computed(() => (props.insights?.games.contested ?? 0) > 0);
</script>

<template>
    <div data-testid="event-insights">
        <div v-if="insights === undefined" class="space-y-3" aria-busy="true" aria-label="Loading insights">
            <Skeleton class="h-20 w-full" />
            <Skeleton class="h-40 w-full" />
        </div>

        <Card v-else-if="!insights">
            <CardContent class="p-8 text-center text-sm text-muted-foreground">
                <BarChart3 class="mx-auto mb-2 size-6" aria-hidden="true" />
                Insights appear once the first results are in.
            </CardContent>
        </Card>

        <div v-else class="space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <p v-if="!isCompleted" class="rounded-md border border-dashed px-3 py-2 text-xs text-muted-foreground">
                    The event is still running, so these figures will keep moving until the last round is scored.
                </p>
                <span v-else />
                <Button as-child variant="outline" size="sm" class="gap-1.5 text-xs">
                    <a :href="csvUrl" data-testid="insights-csv"><Download class="size-3.5" aria-hidden="true" /> Download insights (CSV)</a>
                </Button>
            </div>
            <Card>
                <CardContent class="space-y-3 p-4">
                    <h2 class="text-sm font-semibold">Turnout</h2>
                    <InsightTiles :tiles="turnout" />
                    <p v-if="insights.field.ringers" class="text-xs text-muted-foreground">
                        {{ insights.field.ringers }} {{ insights.field.ringers === 1 ? 'ringer is' : 'ringers are' }} counted in registered but not in
                        standings.
                    </p>
                </CardContent>
            </Card>

            <Card>
                <CardContent class="space-y-4 p-4">
                    <h2 class="text-sm font-semibold">How the games went</h2>
                    <InsightTiles :tiles="gameTiles" />
                    <div v-if="hasGames" class="grid gap-6 md:grid-cols-2">
                        <div>
                            <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Winning margin</h3>
                            <InsightBars :items="marginItems" />
                            <p class="mt-2 text-2xs text-muted-foreground">Games by how many points separated the sides.</p>
                        </div>
                        <div v-if="insights.games.components.length">
                            <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Where the points came from</h3>
                            <InsightBars :items="insights.games.components.map((c) => ({ label: c.label, value: c.avg ?? 0 }))" />
                            <p class="mt-2 text-2xs text-muted-foreground">Average per player per game.</p>
                        </div>
                    </div>
                    <ul v-if="insights.highlights.biggest_win || insights.highlights.undefeated.length" class="space-y-1 text-sm">
                        <li v-if="insights.highlights.biggest_win">
                            <span class="text-muted-foreground">Biggest win: </span>
                            <strong> {{ insights.highlights.biggest_win.winner }}</strong> {{ insights.highlights.biggest_win.winner_vp }}-{{
                                insights.highlights.biggest_win.loser_vp
                            }}
                            over {{ insights.highlights.biggest_win.loser }}
                            <span class="text-xs text-muted-foreground"
                                >({{ insights.highlights.biggest_win.round
                                }}<template v-if="insights.highlights.biggest_win.table">, table {{ insights.highlights.biggest_win.table }}</template
                                >)</span
                            >
                        </li>
                        <li v-if="insights.highlights.high_score">
                            <span class="text-muted-foreground">Highest score: </span>
                            <strong> {{ insights.highlights.high_score.vp }} VP</strong>
                            <span class="text-xs text-muted-foreground"> ({{ insights.highlights.high_score.round }})</span>
                        </li>
                        <li v-if="insights.highlights.undefeated.length">
                            <span class="text-muted-foreground">Unbeaten: </span>{{ insights.highlights.undefeated.join(', ') }}
                        </li>
                    </ul>
                </CardContent>
            </Card>

            <Card v-if="roundsWithGames.length">
                <CardContent class="space-y-3 p-4">
                    <h2 class="text-sm font-semibold">Round by round</h2>
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[32rem] text-xs">
                            <thead>
                                <tr class="border-b text-left text-muted-foreground">
                                    <th scope="col" class="py-1.5 pr-3 font-medium">Round</th>
                                    <th scope="col" class="px-2 text-right font-medium">Games</th>
                                    <th scope="col" class="px-2 text-right font-medium">Avg score</th>
                                    <th scope="col" class="px-2 text-right font-medium">Avg margin</th>
                                    <th scope="col" class="px-2 text-right font-medium">Forfeits</th>
                                    <th scope="col" class="px-2 text-right font-medium">Length</th>
                                    <th scope="col" class="pl-3 font-medium">Scenario</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="r in roundsWithGames" :key="r.round_number" class="border-b last:border-0">
                                    <th scope="row" class="py-1.5 pr-3 text-left font-medium">{{ r.label }}</th>
                                    <td class="px-2 text-right tabular-nums">{{ r.games }}</td>
                                    <td class="px-2 text-right tabular-nums">{{ num(r.avg_vp) }}</td>
                                    <td class="px-2 text-right tabular-nums">{{ num(r.avg_margin) }}</td>
                                    <td class="px-2 text-right tabular-nums">{{ r.forfeits }}</td>
                                    <td class="px-2 text-right tabular-nums">{{ num(r.minutes, ' min') }}</td>
                                    <td class="pl-3 text-muted-foreground">{{ [r.strategy, r.deployment].filter(Boolean).join(' · ') || dash }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div v-if="roundItems.length > 1">
                        <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Round length</h3>
                        <InsightBars
                            :items="roundItems"
                            :max="insights.time_limit ? Math.max(insights.time_limit, ...roundItems.map((i) => i.value)) : undefined"
                        />
                        <p class="mt-2 text-2xs text-muted-foreground">
                            From Start round to End round<template v-if="insights.time_limit"
                                >; the round limit was {{ insights.time_limit }} minutes</template
                            >. Rounds left open for half a day or more are left out.
                        </p>
                    </div>
                </CardContent>
            </Card>

            <Card v-if="insights.factions.length">
                <CardContent class="space-y-3 p-4">
                    <h2 class="text-sm font-semibold">Factions</h2>
                    <InsightBreakdown :rows="insights.factions" noun="across every game the faction played" />
                </CardContent>
            </Card>

            <InsightScenarios :strategies="insights.strategies" :schemes="insights.schemes" />

            <Card v-if="insights.tracks_leaders && insights.leaders.length">
                <CardContent class="space-y-3 p-4">
                    <h2 class="text-sm font-semibold">Leaders</h2>
                    <InsightBreakdown :rows="insights.leaders" noun="for the leaders recorded at score entry" />
                </CardContent>
            </Card>
        </div>
    </div>
</template>
