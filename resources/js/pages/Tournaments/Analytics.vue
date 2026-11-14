<script setup lang="ts">
import EmptyState from '@/components/EmptyState.vue';
import PageBanner from '@/components/PageBanner.vue';
import InsightScenarios from '@/components/Tournament/InsightScenarios.vue';
import InsightBars from '@/components/Tournament/InsightBars.vue';
import InsightBreakdown from '@/components/Tournament/InsightBreakdown.vue';
import InsightTiles from '@/components/Tournament/InsightTiles.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { formatDateOnly } from '@/lib/utils';
import type { OrganizerInsightsData } from '@/types/tournament';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, BarChart3, Download } from 'lucide-vue-next';
import { computed } from 'vue';

/** Analytics across every event the signed-in user organizes. */
const props = defineProps<
    OrganizerInsightsData & { scope: { title: string; subtitle: string; back: { label: string; href: string }; csv: string } }
>();

const dash = '–';
const num = (v: number | null | undefined, suffix = '') => (v === null || v === undefined ? dash : `${v}${suffix}`);

const tiles = computed(() => [
    { label: 'Events', value: props.summary.events, hint: `${props.summary.completed} finished` },
    { label: 'Different players', value: props.summary.unique_players, hint: `${props.summary.entries} entries` },
    { label: 'Avg turnout', value: num(props.summary.avg_attendance), hint: props.summary.largest ? `largest ${props.summary.largest}` : undefined },
    { label: 'Repeat players', value: num(props.summary.repeat_pct, '%'), hint: `${props.summary.repeat_players} came to 2 or more` },
    { label: 'Returning entries', value: num(props.summary.returning_pct, '%'), hint: 'after your first event' },
    { label: 'Avg rounds', value: num(props.summary.avg_rounds), hint: `${props.summary.games} games` },
]);

const turnout = computed(() =>
    props.events.map((e) => ({
        label: e.name,
        value: e.players,
        note: e.returning ? `${e.returning} back` : null,
    })),
);
const share = computed(() => props.factions.map((f) => ({ label: f.label, value: f.games })));
</script>

<template>
    <Head :title="scope.title === 'Tournament analytics' ? scope.title : `${scope.title} analytics`" />
    <PageBanner :title="scope.title" class="mb-2">
        <template #subtitle>
            <div class="my-auto px-2 py-0 text-xs text-muted-foreground md:py-2 md:text-sm md:text-foreground">{{ scope.subtitle }}</div>
        </template>
    </PageBanner>

    <div class="container mx-auto space-y-4 pb-8 sm:px-4" data-testid="organizer-analytics">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <Button as-child variant="ghost" class="gap-1.5 text-sm">
                <Link :href="scope.back.href"><ArrowLeft class="size-4" /> {{ scope.back.label }}</Link>
            </Button>
            <nav v-if="ranges.length" class="flex gap-1" aria-label="Time range">
                <Button v-for="r in ranges" :key="r.value" as-child size="sm" :variant="range === r.value ? 'default' : 'outline'" class="text-xs">
                    <Link
                        :href="route('tournaments.analytics', { range: r.value === 'all' ? undefined : r.value })"
                        :aria-current="range === r.value ? 'true' : undefined"
                        preserve-scroll
                        >{{ r.label }}</Link
                    >
                </Button>
            </nav>
            <Button v-if="summary.events > 0" as-child variant="outline" size="sm" class="gap-1.5 text-xs">
                <a :href="scope.csv" data-testid="analytics-csv"><Download class="size-3.5" aria-hidden="true" /> Download (CSV)</a>
            </Button>
        </div>

        <EmptyState
            v-if="summary.events === 0"
            :icon="BarChart3"
            title="Nothing to show yet"
            description="Once an event you run is under way, its turnout and results are counted here."
        />

        <template v-else>
            <InsightTiles :tiles="tiles" />

            <Card>
                <CardContent class="space-y-3 p-4">
                    <h2 class="text-sm font-semibold">Turnout by event</h2>
                    <InsightBars :items="turnout" />
                    <p class="text-2xs text-muted-foreground">
                        Oldest first; dates are in the event list below. "Back" is how many had been to an earlier event of yours; only players with
                        accounts, or the same name, are matched. Ringers and disqualified players are not counted.
                    </p>
                </CardContent>
            </Card>

            <div class="grid gap-4 md:grid-cols-2">
                <Card v-if="factions.length">
                    <CardContent class="space-y-3 p-4">
                        <h2 class="text-sm font-semibold">Faction results</h2>
                        <InsightBreakdown :rows="factions" noun="across all your events" />
                    </CardContent>
                </Card>
                <Card v-if="factions.length">
                    <CardContent class="space-y-3 p-4">
                        <h2 class="text-sm font-semibold">Faction popularity</h2>
                        <InsightBars :items="share" />
                        <p class="text-2xs text-muted-foreground">Games played by each faction.</p>
                    </CardContent>
                </Card>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <Card v-if="regulars.length">
                    <CardContent class="space-y-3 p-4">
                        <h2 class="text-sm font-semibold">Regulars</h2>
                        <InsightBars :items="regulars.map((r) => ({ label: r.name, value: r.events }))" unit=" events" />
                    </CardContent>
                </Card>
                <Card v-if="rulesets.length > 1">
                    <CardContent class="space-y-3 p-4">
                        <h2 class="text-sm font-semibold">Formats you run</h2>
                        <InsightBars :items="rulesets.map((r) => ({ label: r.label, value: r.events }))" unit=" events" />
                    </CardContent>
                </Card>
            </div>

            <InsightScenarios :strategies="strategies" :schemes="schemes" />

            <Card>
                <CardContent class="p-4">
                    <h2 class="mb-2 text-sm font-semibold">Events</h2>
                    <ul class="divide-y text-sm">
                        <li v-for="e in [...events].reverse()" :key="e.uuid">
                            <Link
                                :href="route('tournaments.manage', e.uuid) + '#insights'"
                                class="flex flex-wrap items-baseline justify-between gap-x-3 py-2 hover:underline"
                            >
                                <span class="font-medium">{{ e.name }}</span>
                                <span class="text-xs text-muted-foreground">
                                    {{ e.date ? formatDateOnly(e.date) : 'No date' }} · {{ e.players }} players · {{ e.rounds }} rounds ·
                                    {{ e.ruleset }}
                                </span>
                            </Link>
                        </li>
                    </ul>
                </CardContent>
            </Card>
        </template>
    </div>
</template>
