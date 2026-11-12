<script setup lang="ts">
import PageBanner from '@/components/PageBanner.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { useConfirm } from '@/composables/useConfirm';
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, Medal, Pencil, Trash2 } from 'lucide-vue-next';
import { computed } from 'vue';

/** One league: the cumulative leaderboard, how points work, and the events in it. */
interface Result {
    tournament_id: number;
    rank: number;
    points: number;
    counts: boolean;
}
interface Row {
    key: string;
    name: string;
    user_id: number | null;
    rank: number;
    total: number;
    events_played: number;
    wins: number;
    best_finish: number;
    results: Result[];
}
const props = defineProps<{
    series: {
        uuid: string;
        name: string;
        description: string | null;
        creator: string;
        points_table: number[];
        participation_points: number;
        best_of: number | null;
    };
    events: { uuid: string; name: string; event_date: string; status: string }[];
    standings: Row[];
    scored_events: { id: number; uuid: string; name: string; event_date: string }[];
    can_manage: boolean;
}>();

const confirm = useConfirm();
const ordinal = (n: number) =>
    n % 10 === 1 && n % 100 !== 11 ? `${n}st` : n % 10 === 2 && n % 100 !== 12 ? `${n}nd` : n % 10 === 3 && n % 100 !== 13 ? `${n}rd` : `${n}th`;
const pointsLine = computed(() => {
    const places = props.series.points_table.map((p, i) => `${ordinal(i + 1)} ${p}`).join(' · ');
    return `${places}${props.series.participation_points ? ` · everyone else who played ${props.series.participation_points}` : ''}`;
});
const resultFor = (row: Row, eventId: number) => row.results.find((r) => r.tournament_id === eventId);

const remove = async () => {
    if (
        !(await confirm({
            title: `Delete "${props.series.name}"?`,
            message: 'The league and its leaderboard are removed. The events stay.',
            confirmLabel: 'Delete league',
            destructive: true,
        }))
    )
        return;
    router.delete(route('tournaments.series.destroy', props.series.uuid));
};
const statusLabel = (s: string) => ({ draft: 'Draft', registration: 'Open', active: 'Running', completed: 'Finished' })[s] ?? s;
</script>

<template>
    <Head :title="series.name" />
    <PageBanner :title="series.name" class="mb-2">
        <template #subtitle>
            <div class="my-auto px-2 py-0 text-xs text-muted-foreground md:py-2 md:text-sm md:text-foreground">League by {{ series.creator }}</div>
        </template>
    </PageBanner>

    <div class="container mx-auto space-y-4 pb-8 sm:px-4">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <Button as-child variant="ghost" class="gap-1.5 text-sm">
                <Link :href="route('tournaments.series.index')"><ArrowLeft class="size-4" /> Leagues</Link>
            </Button>
            <div v-if="can_manage" class="flex gap-2">
                <Button as-child variant="outline" size="sm" class="gap-1.5">
                    <Link :href="route('tournaments.series.edit', series.uuid)"><Pencil class="size-3.5" /> Edit</Link>
                </Button>
                <Button variant="ghost" size="sm" class="gap-1.5 text-muted-foreground" @click="remove"><Trash2 class="size-3.5" /> Delete</Button>
            </div>
        </div>

        <p v-if="series.description" class="text-sm text-muted-foreground">{{ series.description }}</p>

        <Card>
            <CardContent class="space-y-3 p-4 sm:p-5">
                <h2 class="flex items-center gap-1.5 text-sm font-semibold">
                    <Medal class="size-4 text-amber-500" aria-hidden="true" /> Leaderboard
                </h2>
                <p v-if="!standings.length" class="text-sm text-muted-foreground">
                    No finished events yet — the table fills in as events in this league are completed.
                </p>
                <div v-else class="overflow-x-auto">
                    <table class="w-full text-sm" data-testid="series-standings">
                        <thead>
                            <tr class="border-b text-left text-xs text-muted-foreground">
                                <th class="w-10 py-1.5 pr-2">#</th>
                                <th class="py-1.5 pr-2">Player</th>
                                <th
                                    v-for="e in scored_events"
                                    :key="e.id"
                                    class="whitespace-nowrap px-2 py-1.5 text-right font-normal"
                                    :title="e.name"
                                >
                                    <Link :href="route('tournaments.view', e.uuid)" class="underline-offset-2 hover:underline">{{
                                        e.name.length > 14 ? e.name.slice(0, 13) + '…' : e.name
                                    }}</Link>
                                </th>
                                <th class="px-2 py-1.5 text-right">Wins</th>
                                <th class="py-1.5 pl-2 text-right">Points</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in standings" :key="row.key" class="border-b last:border-0">
                                <td class="py-1.5 pr-2 tabular-nums">{{ row.rank }}</td>
                                <td class="py-1.5 pr-2 font-medium">{{ row.name }}</td>
                                <td v-for="e in scored_events" :key="e.id" class="px-2 py-1.5 text-right tabular-nums">
                                    <template v-if="resultFor(row, e.id)">
                                        <span
                                            :class="resultFor(row, e.id)?.counts ? '' : 'text-muted-foreground line-through'"
                                            :title="`${ordinal(resultFor(row, e.id)!.rank)} place`"
                                            >{{ resultFor(row, e.id)?.points }}</span
                                        >
                                    </template>
                                    <span v-else class="text-muted-foreground">–</span>
                                </td>
                                <td class="px-2 py-1.5 text-right tabular-nums">{{ row.wins }}</td>
                                <td class="py-1.5 pl-2 text-right font-semibold tabular-nums">{{ row.total }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p class="text-xs text-muted-foreground">
                    Points: {{ pointsLine }}.<template v-if="series.best_of">
                        Only each player's best {{ series.best_of }} {{ series.best_of === 1 ? 'event counts' : 'events count' }} (struck-through
                        results don't).</template
                    >
                </p>
            </CardContent>
        </Card>

        <Card>
            <CardContent class="space-y-2 p-4 sm:p-5">
                <h2 class="text-sm font-semibold">Events</h2>
                <p v-if="!events.length" class="text-sm text-muted-foreground">No events have been added to this league yet.</p>
                <ul class="divide-y">
                    <li v-for="e in events" :key="e.uuid" class="flex items-center justify-between gap-2 py-2 text-sm">
                        <Link :href="route('tournaments.view', e.uuid)" class="font-medium hover:underline">{{ e.name }}</Link>
                        <span class="flex shrink-0 items-center gap-2 text-xs text-muted-foreground">
                            {{ e.event_date }} <Badge variant="outline" class="text-2xs">{{ statusLabel(e.status) }}</Badge>
                        </span>
                    </li>
                </ul>
            </CardContent>
        </Card>
    </div>
</template>
