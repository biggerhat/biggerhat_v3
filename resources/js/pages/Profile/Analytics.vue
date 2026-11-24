<script setup lang="ts">
import EmptyState from '@/components/EmptyState.vue';
import PageBanner from '@/components/PageBanner.vue';
import RecordTable, { type RecordRow } from '@/components/Stats/RecordTable.vue';
import InsightBars from '@/components/Tournament/InsightBars.vue';
import InsightTiles from '@/components/Tournament/InsightTiles.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Head, Link, router } from '@inertiajs/vue3';
import { BarChart3, Download, Filter } from 'lucide-vue-next';
import { computed, reactive, ref, watch } from 'vue';

interface Tally extends RecordRow {
    interval: { low: number; high: number } | null;
}
interface Overview extends Tally {
    total_vp: number;
    avg_margin: number | null;
    current_streak: { type: string | null; count: number };
    best_win_streak: number;
    first_game: string | null;
    last_game: string | null;
}
interface Analytics {
    overview: Overview;
    trend: {
        monthly: { month: string; games: number; wins: number; win_rate: number | null; avg_vp: number | null; reliable: boolean }[];
        rolling: { date: string; win_rate: number }[];
    };
    by_faction: Tally[];
    by_master: Tally[];
    by_role: Tally[];
    by_opponent_faction: Tally[];
    by_strategy: Tally[];
    by_deployment: Tally[];
    by_size: Tally[];
    by_season: Tally[];
    by_play_mode: Tally[];
    by_venue: Tally[];
    by_source: Tally[];
    matchups: (Tally & { faction: string; opponent_faction: string })[];
    master_matchups: (Tally & { master: string; opponent_master: string })[];
    opponents: (Tally & { name: string; slug: string | null; last_played: string })[];
    schemes: {
        name: string;
        picked: number;
        scored: number;
        held: number;
        discarded: number;
        score_rate: number | null;
        total_vp: number;
        avg_vp_when_scored: number | null;
        reliable: boolean;
    }[];
    turns: {
        games: number;
        strategy_share: number | null;
        by_turn: { turn: number; games: number; strategy: number; scheme: number; opp_strategy: number; opp_scheme: number }[];
        turn_one_lead: { games: number; wins: number; win_rate: number | null; reliable: boolean };
    };
    models: { name: string; hired: number; killed: number; kill_rate: number; avg_cost: number | null; reliable: boolean }[];
    coverage: Record<string, number>;
}
interface GameRow {
    source: string;
    url: string;
    date: string;
    result: 'win' | 'loss' | 'tie';
    is_solo: boolean;
    faction: string | null;
    master: string | null;
    vp: number;
    opponent: string | null;
    opponent_faction: string | null;
    opponent_vp: number;
    strategy: string | null;
    venue: string | null;
}
type FilterKey =
    | 'from'
    | 'to'
    | 'result'
    | 'faction'
    | 'opponent_faction'
    | 'master'
    | 'opponent'
    | 'strategy'
    | 'deployment'
    | 'size'
    | 'season'
    | 'play_mode'
    | 'venue';

const props = defineProps<{
    user: { name: string; slug: string };
    is_own_profile: boolean;
    filters: Record<string, unknown>;
    options: Record<string, (string | number)[]>;
    faction_labels: Record<string, string>;
    total_games: number;
    analytics: Analytics;
    games: GameRow[];
    games_truncated: boolean;
}>();

const SOURCES = [
    { value: 'tracked', label: 'Game Tracker' },
    { value: 'logged', label: 'Logged by hand' },
    { value: 'tournament', label: 'Tournaments' },
];
const ALL = 'all';

const factionLabel = (v: string | null | undefined) => (v ? (props.faction_labels[v] ?? v) : '–');
const sourceLabel = (v: string) => SOURCES.find((s) => s.value === v)?.label ?? v;
const roleLabel = (v: string) => v.charAt(0).toUpperCase() + v.slice(1);

// ── filters: kept in the address, so a view can be shared ──
const text = (k: string) => (typeof props.filters[k] === 'string' || typeof props.filters[k] === 'number' ? String(props.filters[k]) : '');
const form = reactive<Record<FilterKey, string>>({
    from: text('from'),
    to: text('to'),
    result: text('result') || ALL,
    faction: text('faction') || ALL,
    opponent_faction: text('opponent_faction') || ALL,
    master: text('master') || ALL,
    opponent: text('opponent') || ALL,
    strategy: text('strategy') || ALL,
    deployment: text('deployment') || ALL,
    size: text('size') || ALL,
    season: text('season') || ALL,
    play_mode: text('play_mode') || ALL,
    venue: text('venue') || ALL,
});
const sources = ref<string[]>(Array.isArray(props.filters.sources) ? props.filters.sources.map(String) : []);
const includeSolo = ref(props.filters.solo !== false);

const query = computed(() => {
    const q: Record<string, string | string[]> = {};
    (Object.keys(form) as FilterKey[]).forEach((k) => {
        if (form[k] && form[k] !== ALL) q[k] = form[k];
    });
    if (sources.value.length) q.sources = sources.value;
    if (!includeSolo.value) q.solo = '0';
    return q;
});
const filtered = computed(() => Object.keys(query.value).length > 0);
let timer: ReturnType<typeof setTimeout> | undefined;
watch(
    [query],
    () => {
        clearTimeout(timer);
        timer = setTimeout(
            () => router.get(route('stats.analytics', props.user.slug), query.value, { preserveState: true, preserveScroll: true, replace: true }),
            350,
        );
    },
    { deep: true },
);
function reset() {
    (Object.keys(form) as FilterKey[]).forEach((k) => (form[k] = k === 'from' || k === 'to' ? '' : ALL));
    sources.value = [];
    includeSolo.value = true;
}
function toggleSource(v: string) {
    sources.value = sources.value.includes(v) ? sources.value.filter((s) => s !== v) : [...sources.value, v];
}
const showFilters = ref(filtered.value);
const csvUrl = computed(() => route('stats.analytics_csv', { user: props.user.slug, ...query.value }));

const selects: { key: FilterKey; label: string; option: string; fmt?: (v: string) => string }[] = [
    { key: 'result', label: 'Result', option: '' },
    { key: 'faction', label: 'My faction', option: 'factions', fmt: factionLabel },
    { key: 'master', label: 'My master', option: 'masters' },
    { key: 'opponent', label: 'Opponent', option: 'opponents' },
    { key: 'opponent_faction', label: 'Opponent faction', option: 'opponent_factions', fmt: factionLabel },
    { key: 'strategy', label: 'Strategy', option: 'strategies' },
    { key: 'deployment', label: 'Deployment', option: 'deployments' },
    { key: 'size', label: 'Encounter size', option: 'sizes' },
    { key: 'season', label: 'Season', option: 'seasons' },
    { key: 'play_mode', label: 'How played', option: 'play_modes' },
    { key: 'venue', label: 'Event or venue', option: 'venues' },
];
const choices = (s: (typeof selects)[number]) =>
    s.key === 'result'
        ? [
              { value: 'win', label: 'Wins' },
              { value: 'loss', label: 'Losses' },
              { value: 'tie', label: 'Ties' },
          ]
        : (props.options[s.option] ?? []).map((v) => ({ value: String(v), label: s.fmt ? s.fmt(String(v)) : String(v) }));
const visibleSelects = computed(() => selects.filter((s) => s.key === 'result' || (props.options[s.option] ?? []).length > 0));

// ── numbers ──
const o = computed(() => props.analytics.overview);
const rate = (r: { reliable: boolean; win_rate: number | null }) => (r.reliable && r.win_rate !== null ? `${r.win_rate}%` : '–');
const tiles = computed(() => [
    { label: 'Games', value: o.value.games },
    { label: 'Record', value: `${o.value.wins}-${o.value.losses}-${o.value.ties}`, hint: 'wins-losses-ties' },
    {
        label: 'Win rate',
        value: rate(o.value),
        hint: o.value.reliable && o.value.interval ? `likely ${o.value.interval.low}–${o.value.interval.high}%` : 'needs 5 games',
    },
    { label: 'Avg VP', value: o.value.avg_vp ?? '–', hint: o.value.avg_opp_vp !== null ? `against ${o.value.avg_opp_vp}` : undefined },
    {
        label: 'Avg margin',
        value: o.value.avg_margin === null ? '–' : (o.value.avg_margin > 0 ? '+' : '') + o.value.avg_margin,
        hint: 'VP for minus against',
    },
    {
        label: 'Streak',
        value: o.value.current_streak.type
            ? `${o.value.current_streak.count} ${o.value.current_streak.type}${o.value.current_streak.count === 1 ? '' : 's'}`
            : '–',
        hint: `best win streak ${o.value.best_win_streak}`,
    },
]);
const monthly = computed(() =>
    props.analytics.trend.monthly.map((m) => ({
        label: m.month,
        value: m.games,
        note: m.reliable && m.win_rate !== null ? `${m.win_rate}%` : undefined,
    })),
);
const bySource = computed(() => props.analytics.by_source.map((r) => ({ ...r, key: sourceLabel(r.key) })));
const byRole = computed(() => props.analytics.by_role.map((r) => ({ ...r, key: roleLabel(r.key) })));
const cov = computed(() => props.analytics.coverage);

// the rolling win rate, drawn as a line; the same numbers are in the list underneath for anyone who cannot see it
const spark = computed(() => {
    const pts = props.analytics.trend.rolling;
    if (pts.length < 2) return null;
    const w = 300;
    const h = 60;
    return pts.map((p, i) => `${((i / (pts.length - 1)) * w).toFixed(1)},${(h - (p.win_rate / 100) * h).toFixed(1)}`).join(' ');
});

// ── the games table ──
const sortDesc = ref(true);
const sortedGames = computed(() => (sortDesc.value ? props.games : [...props.games].reverse()));
const resultBadge = (r: string) => (r === 'win' ? 'default' : r === 'loss' ? 'destructive' : 'secondary');
const SHOW = 25;
const showAll = ref(false);
const shownGames = computed(() => (showAll.value ? sortedGames.value : sortedGames.value.slice(0, SHOW)));
const noData = computed(() => props.total_games === 0);
const noMatch = computed(() => !noData.value && o.value.games === 0);
</script>

<template>
    <Head :title="`${user.name}: game analytics`" />
    <PageBanner :title="`${user.name}'s analytics`">
        <template #subtitle>
            <div class="my-auto px-2 py-0 text-xs text-muted-foreground md:py-2 md:text-sm md:text-foreground">
                Every finished game: tracked, logged by hand and tournament rounds.
            </div>
        </template>
        <template #actions>
            <div class="flex flex-wrap items-center gap-2 px-2 pb-2 sm:pb-0">
                <Button as-child variant="outline" size="sm" class="max-sm:min-h-11">
                    <Link :href="route('stats.show', user.slug)"><BarChart3 class="mr-1.5 size-4" />Stats</Link>
                </Button>
                <Button v-if="is_own_profile" as-child size="sm" class="max-sm:min-h-11" data-testid="analytics-log">
                    <Link :href="route('games.logged.create')">Log a game</Link>
                </Button>
                <Button v-if="!noData" as-child variant="outline" size="sm" class="max-sm:min-h-11" data-testid="analytics-csv">
                    <a :href="csvUrl"><Download class="mr-1.5 size-4" />CSV</a>
                </Button>
            </div>
        </template>
    </PageBanner>

    <div class="container mx-auto space-y-4 px-4 pb-8 sm:px-6" data-testid="analytics">
        <EmptyState
            v-if="noData"
            title="No finished games yet"
            :description="
                is_own_profile
                    ? 'Play a game in the Game Tracker, or log one you played somewhere else, and your numbers appear here.'
                    : 'This player has no finished games to show.'
            "
        >
        </EmptyState>

        <template v-else>
            <!-- filters -->
            <Card>
                <CardContent class="space-y-3 p-3">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            class="max-sm:min-h-11"
                            :aria-expanded="showFilters"
                            data-testid="filters-toggle"
                            @click="showFilters = !showFilters"
                        >
                            <Filter class="mr-1.5 size-4" />Filters<Badge v-if="filtered" variant="secondary" class="ml-2">on</Badge>
                        </Button>
                        <p class="text-xs text-muted-foreground" role="status" aria-live="polite" data-testid="filter-count">
                            {{ o.games }} of {{ total_games }} games<span v-if="filtered">
                                ·
                                <button type="button" class="underline" data-testid="filters-reset" @click="reset">Clear filters</button></span
                            >
                        </p>
                    </div>
                    <div v-show="showFilters" class="space-y-3" data-testid="filters">
                        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                            <div class="space-y-1.5">
                                <Label for="f-from">From</Label>
                                <Input id="f-from" v-model="form.from" type="date" />
                            </div>
                            <div class="space-y-1.5">
                                <Label for="f-to">To</Label>
                                <Input id="f-to" v-model="form.to" type="date" />
                            </div>
                            <div v-for="s in visibleSelects" :key="s.key" class="space-y-1.5">
                                <Label :for="`f-${s.key}`">{{ s.label }}</Label>
                                <Select v-model="form[s.key]">
                                    <SelectTrigger :id="`f-${s.key}`" :data-testid="`filter-${s.key}`"><SelectValue /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem :value="ALL">Any</SelectItem>
                                        <SelectItem v-for="c in choices(s)" :key="c.value" :value="c.value">{{ c.label }}</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>
                        <div class="flex flex-wrap items-center gap-x-6 gap-y-2">
                            <fieldset class="flex flex-wrap items-center gap-2">
                                <legend class="sr-only">Where the games come from</legend>
                                <span class="text-xs text-muted-foreground">From</span>
                                <Button
                                    v-for="s in SOURCES"
                                    :key="s.value"
                                    type="button"
                                    size="sm"
                                    :variant="sources.includes(s.value) ? 'default' : 'outline'"
                                    :aria-pressed="sources.includes(s.value)"
                                    class="max-sm:min-h-11"
                                    :data-testid="`source-${s.value}`"
                                    @click="toggleSource(s.value)"
                                >
                                    {{ s.label }}
                                </Button>
                                <span class="text-2xs text-muted-foreground">{{ sources.length ? '' : 'all three' }}</span>
                            </fieldset>
                            <div class="flex items-center gap-2">
                                <Switch id="f-solo" v-model="includeSolo" data-testid="filter-solo" />
                                <Label for="f-solo" class="text-sm">Include solo games (playing both sides)</Label>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <EmptyState v-if="noMatch" title="No games match" description="Loosen or clear a filter." compact />

            <template v-else>
                <InsightTiles :tiles="tiles" data-testid="analytics-tiles" />
                <p class="text-xs text-muted-foreground">
                    Win rate counts a tie as not a win. A percentage appears once there are 5 games behind it, and hovering it gives the range it
                    likely sits in.
                </p>

                <!-- over time -->
                <Card data-testid="analytics-trend">
                    <CardContent class="space-y-3 p-4">
                        <h2 class="font-semibold">Over time</h2>
                        <div>
                            <p class="mb-1 text-xs text-muted-foreground">Games per month, with the win rate for that month beside it.</p>
                            <InsightBars :items="monthly" />
                        </div>
                        <div v-if="spark">
                            <p class="mb-1 text-xs text-muted-foreground">Win rate over the last 10 games (newest on the right).</p>
                            <svg
                                viewBox="0 0 300 60"
                                class="h-16 w-full max-w-md text-primary"
                                role="img"
                                :aria-label="`Win rate over the last 10 games, now ${analytics.trend.rolling[analytics.trend.rolling.length - 1].win_rate} percent`"
                                preserveAspectRatio="none"
                            >
                                <line x1="0" y1="30" x2="300" y2="30" stroke="currentColor" stroke-opacity="0.2" stroke-dasharray="3 3" />
                                <polyline :points="spark" fill="none" stroke="currentColor" stroke-width="2" vector-effect="non-scaling-stroke" />
                            </svg>
                        </div>
                        <p v-else class="text-xs text-muted-foreground">The rolling win rate needs 10 games.</p>
                    </CardContent>
                </Card>

                <div class="grid gap-4 lg:grid-cols-2">
                    <Card data-testid="analytics-factions">
                        <CardContent class="space-y-2 p-4">
                            <h2 class="font-semibold">By faction</h2>
                            <RecordTable :rows="analytics.by_faction" label="Faction" :format="factionLabel" />
                        </CardContent>
                    </Card>
                    <Card data-testid="analytics-masters">
                        <CardContent class="space-y-2 p-4">
                            <h2 class="font-semibold">By master</h2>
                            <RecordTable v-if="analytics.by_master.length" :rows="analytics.by_master" label="Master" :limit="15" />
                            <p v-else class="text-xs text-muted-foreground">No game has a master recorded.</p>
                        </CardContent>
                    </Card>
                    <Card data-testid="analytics-opp-factions">
                        <CardContent class="space-y-2 p-4">
                            <h2 class="font-semibold">Against each faction</h2>
                            <RecordTable
                                v-if="analytics.by_opponent_faction.length"
                                :rows="analytics.by_opponent_faction"
                                label="Opponent faction"
                                :format="factionLabel"
                            />
                            <p v-else class="text-xs text-muted-foreground">No game has the opponent's faction recorded.</p>
                        </CardContent>
                    </Card>
                    <Card data-testid="analytics-roles">
                        <CardContent class="space-y-2 p-4">
                            <h2 class="font-semibold">Attacker and defender</h2>
                            <RecordTable v-if="byRole.length" :rows="byRole" label="Role" />
                            <p v-else class="text-xs text-muted-foreground">No game has a role recorded ({{ cov.with_role }} of {{ cov.games }}).</p>
                        </CardContent>
                    </Card>
                </div>

                <Card data-testid="analytics-matchups">
                    <CardContent class="space-y-3 p-4">
                        <h2 class="font-semibold">Matchups</h2>
                        <div class="overflow-x-auto">
                            <Table v-if="analytics.matchups.length" class="text-xs">
                                <TableHeader>
                                    <TableRow>
                                        <TableHead scope="col">Mine</TableHead>
                                        <TableHead scope="col">Against</TableHead>
                                        <TableHead scope="col" class="text-right">Games</TableHead>
                                        <TableHead scope="col" class="text-right">W-L-T</TableHead>
                                        <TableHead scope="col" class="text-right">Win %</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow v-for="m in analytics.matchups.slice(0, 20)" :key="m.faction + m.opponent_faction">
                                        <TableCell>{{ factionLabel(m.faction) }}</TableCell>
                                        <TableCell>{{ factionLabel(m.opponent_faction) }}</TableCell>
                                        <TableCell class="text-right tabular-nums">{{ m.games }}</TableCell>
                                        <TableCell class="text-right tabular-nums">{{ m.wins }}-{{ m.losses }}-{{ m.ties }}</TableCell>
                                        <TableCell class="text-right tabular-nums">{{ rate(m) }}</TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                            <p v-else class="text-xs text-muted-foreground">Needs games with both factions recorded.</p>
                        </div>
                        <details v-if="analytics.master_matchups.length">
                            <summary class="cursor-pointer text-sm font-medium">Master against master</summary>
                            <div class="mt-2 overflow-x-auto">
                                <Table class="text-xs">
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead scope="col">Mine</TableHead>
                                            <TableHead scope="col">Against</TableHead>
                                            <TableHead scope="col" class="text-right">Games</TableHead>
                                            <TableHead scope="col" class="text-right">W-L-T</TableHead>
                                            <TableHead scope="col" class="text-right">Win %</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        <TableRow v-for="m in analytics.master_matchups" :key="m.master + m.opponent_master">
                                            <TableCell>{{ m.master }}</TableCell>
                                            <TableCell>{{ m.opponent_master }}</TableCell>
                                            <TableCell class="text-right tabular-nums">{{ m.games }}</TableCell>
                                            <TableCell class="text-right tabular-nums">{{ m.wins }}-{{ m.losses }}-{{ m.ties }}</TableCell>
                                            <TableCell class="text-right tabular-nums">{{ rate(m) }}</TableCell>
                                        </TableRow>
                                    </TableBody>
                                </Table>
                            </div>
                        </details>
                    </CardContent>
                </Card>

                <Card v-if="analytics.opponents.length" data-testid="analytics-opponents">
                    <CardContent class="space-y-2 p-4">
                        <h2 class="font-semibold">Head to head</h2>
                        <div class="overflow-x-auto">
                            <Table class="text-xs">
                                <TableHeader>
                                    <TableRow>
                                        <TableHead scope="col">Opponent</TableHead>
                                        <TableHead scope="col" class="text-right">Games</TableHead>
                                        <TableHead scope="col" class="text-right">W-L-T</TableHead>
                                        <TableHead scope="col" class="text-right">Win %</TableHead>
                                        <TableHead scope="col" class="hidden text-right sm:table-cell">Last played</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow v-for="r in analytics.opponents" :key="r.name">
                                        <TableCell class="max-w-[12rem] truncate font-medium">
                                            <Link v-if="r.slug" :href="route('stats.show', r.slug)" class="hover:underline">{{ r.name }}</Link>
                                            <span v-else>{{ r.name }}</span>
                                        </TableCell>
                                        <TableCell class="text-right tabular-nums">{{ r.games }}</TableCell>
                                        <TableCell class="text-right tabular-nums">{{ r.wins }}-{{ r.losses }}-{{ r.ties }}</TableCell>
                                        <TableCell class="text-right tabular-nums">{{ rate(r) }}</TableCell>
                                        <TableCell class="hidden text-right tabular-nums sm:table-cell">{{ r.last_played }}</TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </div>
                    </CardContent>
                </Card>

                <div class="grid gap-4 lg:grid-cols-2">
                    <Card v-if="analytics.by_strategy.length" data-testid="analytics-strategies">
                        <CardContent class="space-y-2 p-4">
                            <h2 class="font-semibold">By strategy</h2>
                            <RecordTable :rows="analytics.by_strategy" label="Strategy" />
                        </CardContent>
                    </Card>
                    <Card v-if="analytics.by_deployment.length" data-testid="analytics-deployments">
                        <CardContent class="space-y-2 p-4">
                            <h2 class="font-semibold">By deployment</h2>
                            <RecordTable :rows="analytics.by_deployment" label="Deployment" />
                        </CardContent>
                    </Card>
                    <Card v-if="analytics.by_size.length" data-testid="analytics-sizes">
                        <CardContent class="space-y-2 p-4">
                            <h2 class="font-semibold">By encounter size</h2>
                            <RecordTable :rows="analytics.by_size" label="Size (ss)" />
                        </CardContent>
                    </Card>
                    <Card v-if="analytics.by_season.length" data-testid="analytics-seasons">
                        <CardContent class="space-y-2 p-4">
                            <h2 class="font-semibold">By season</h2>
                            <RecordTable :rows="analytics.by_season" label="Season" />
                        </CardContent>
                    </Card>
                    <Card v-if="analytics.by_play_mode.length">
                        <CardContent class="space-y-2 p-4">
                            <h2 class="font-semibold">By how it was played</h2>
                            <RecordTable :rows="analytics.by_play_mode" label="How played" />
                        </CardContent>
                    </Card>
                    <Card v-if="analytics.by_venue.length">
                        <CardContent class="space-y-2 p-4">
                            <h2 class="font-semibold">By event or venue</h2>
                            <RecordTable :rows="analytics.by_venue" label="Event or venue" :limit="15" />
                        </CardContent>
                    </Card>
                    <Card data-testid="analytics-sources">
                        <CardContent class="space-y-2 p-4">
                            <h2 class="font-semibold">By where the game came from</h2>
                            <RecordTable :rows="bySource" label="Source" />
                        </CardContent>
                    </Card>
                </div>

                <!-- turn by turn: only the games that recorded turns -->
                <Card data-testid="analytics-turns">
                    <CardContent class="space-y-3 p-4">
                        <h2 class="font-semibold">Turn by turn</h2>
                        <p class="text-xs text-muted-foreground">
                            Based on the {{ cov.with_turns }} of {{ cov.games }} games that have their turns recorded.
                        </p>
                        <template v-if="analytics.turns.by_turn.length">
                            <div class="overflow-x-auto">
                                <Table class="text-xs">
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead scope="col">Turn</TableHead>
                                            <TableHead scope="col" class="text-right">Games</TableHead>
                                            <TableHead scope="col" class="text-right">Strategy VP</TableHead>
                                            <TableHead scope="col" class="text-right">Scheme VP</TableHead>
                                            <TableHead scope="col" class="text-right">Opp. strategy</TableHead>
                                            <TableHead scope="col" class="text-right">Opp. scheme</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        <TableRow v-for="t in analytics.turns.by_turn" :key="t.turn">
                                            <TableCell>{{ t.turn }}</TableCell>
                                            <TableCell class="text-right tabular-nums">{{ t.games }}</TableCell>
                                            <TableCell class="text-right tabular-nums">{{ t.strategy }}</TableCell>
                                            <TableCell class="text-right tabular-nums">{{ t.scheme }}</TableCell>
                                            <TableCell class="text-right tabular-nums">{{ t.opp_strategy }}</TableCell>
                                            <TableCell class="text-right tabular-nums">{{ t.opp_scheme }}</TableCell>
                                        </TableRow>
                                    </TableBody>
                                </Table>
                            </div>
                            <p v-if="analytics.turns.strategy_share !== null" class="text-xs">
                                {{ analytics.turns.strategy_share }}% of the points you scored came from the strategy, the rest from schemes.
                            </p>
                            <p v-if="analytics.turns.turn_one_lead.games" class="text-xs">
                                When you scored more than your opponent in turn 1 ({{ analytics.turns.turn_one_lead.games }} games), you won
                                <span v-if="analytics.turns.turn_one_lead.reliable">{{ analytics.turns.turn_one_lead.win_rate }}% of them</span>
                                <span v-else>{{ analytics.turns.turn_one_lead.wins }} of them (too few for a percentage)</span>.
                            </p>
                        </template>
                        <p v-else class="text-xs text-muted-foreground">None of these games has turns recorded.</p>
                    </CardContent>
                </Card>

                <Card data-testid="analytics-schemes">
                    <CardContent class="space-y-2 p-4">
                        <h2 class="font-semibold">Schemes</h2>
                        <div v-if="analytics.schemes.length" class="overflow-x-auto">
                            <Table class="text-xs">
                                <TableHeader>
                                    <TableRow>
                                        <TableHead scope="col">Scheme</TableHead>
                                        <TableHead scope="col" class="text-right">Picked</TableHead>
                                        <TableHead scope="col" class="text-right">Scored</TableHead>
                                        <TableHead scope="col" class="hidden text-right sm:table-cell">Held</TableHead>
                                        <TableHead scope="col" class="hidden text-right sm:table-cell">Discarded</TableHead>
                                        <TableHead scope="col" class="text-right">Score rate</TableHead>
                                        <TableHead scope="col" class="text-right">VP</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow v-for="s in analytics.schemes.slice(0, 20)" :key="s.name">
                                        <TableCell class="max-w-[12rem] truncate font-medium">{{ s.name }}</TableCell>
                                        <TableCell class="text-right tabular-nums">{{ s.picked }}</TableCell>
                                        <TableCell class="text-right tabular-nums">{{ s.scored }}</TableCell>
                                        <TableCell class="hidden text-right tabular-nums sm:table-cell">{{ s.held }}</TableCell>
                                        <TableCell class="hidden text-right tabular-nums sm:table-cell">{{ s.discarded }}</TableCell>
                                        <TableCell class="text-right tabular-nums">{{
                                            s.reliable && s.score_rate !== null ? `${s.score_rate}%` : '–'
                                        }}</TableCell>
                                        <TableCell class="text-right tabular-nums">{{ s.total_vp }}</TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </div>
                        <p v-else class="text-xs text-muted-foreground">No scheme is recorded against a turn yet.</p>
                    </CardContent>
                </Card>

                <Card data-testid="analytics-models">
                    <CardContent class="space-y-2 p-4">
                        <h2 class="font-semibold">Models</h2>
                        <p class="text-xs text-muted-foreground">
                            Based on the {{ cov.with_models }} of {{ cov.games }} games that have a crew recorded.
                        </p>
                        <div v-if="analytics.models.length" class="overflow-x-auto">
                            <Table class="text-xs">
                                <TableHeader>
                                    <TableRow>
                                        <TableHead scope="col">Model</TableHead>
                                        <TableHead scope="col" class="text-right">Hired</TableHead>
                                        <TableHead scope="col" class="text-right">Killed</TableHead>
                                        <TableHead scope="col" class="text-right">Kill rate</TableHead>
                                        <TableHead scope="col" class="hidden text-right sm:table-cell">Avg cost</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow v-for="m in analytics.models" :key="m.name">
                                        <TableCell class="max-w-[12rem] truncate font-medium">{{ m.name }}</TableCell>
                                        <TableCell class="text-right tabular-nums">{{ m.hired }}</TableCell>
                                        <TableCell class="text-right tabular-nums">{{ m.killed }}</TableCell>
                                        <TableCell class="text-right tabular-nums">{{ m.reliable ? `${m.kill_rate}%` : '–' }}</TableCell>
                                        <TableCell class="hidden text-right tabular-nums sm:table-cell">{{ m.avg_cost ?? '–' }}</TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </div>
                        <p v-else class="text-xs text-muted-foreground">No crew is recorded for these games.</p>
                    </CardContent>
                </Card>

                <!-- every game -->
                <Card data-testid="analytics-games">
                    <CardContent class="space-y-2 p-4">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <h2 class="font-semibold">Games</h2>
                            <Button variant="ghost" size="sm" class="max-sm:min-h-11" data-testid="games-sort" @click="sortDesc = !sortDesc">
                                {{ sortDesc ? 'Newest first' : 'Oldest first' }}
                            </Button>
                        </div>
                        <div class="overflow-x-auto">
                            <Table class="text-xs">
                                <TableHeader>
                                    <TableRow>
                                        <TableHead scope="col">Date</TableHead>
                                        <TableHead scope="col">Result</TableHead>
                                        <TableHead scope="col">Mine</TableHead>
                                        <TableHead scope="col">Against</TableHead>
                                        <TableHead scope="col" class="text-right">VP</TableHead>
                                        <TableHead scope="col" class="hidden sm:table-cell">Source</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow v-for="(g, i) in shownGames" :key="g.url + i" :data-testid="`game-row-${g.source}`">
                                        <TableCell class="whitespace-nowrap">
                                            <Link :href="g.url" class="underline-offset-2 hover:underline">{{ g.date }}</Link>
                                        </TableCell>
                                        <TableCell
                                            ><Badge :variant="resultBadge(g.result)">{{ g.result }}</Badge></TableCell
                                        >
                                        <TableCell class="max-w-[9rem] truncate"
                                            >{{ factionLabel(g.faction) }}<span v-if="g.master"> · {{ g.master }}</span></TableCell
                                        >
                                        <TableCell class="max-w-[9rem] truncate"
                                            >{{ g.is_solo ? 'Playing both sides' : (g.opponent ?? '–')
                                            }}<span v-if="g.opponent_faction"> · {{ factionLabel(g.opponent_faction) }}</span></TableCell
                                        >
                                        <TableCell class="text-right tabular-nums">{{ g.vp }}–{{ g.opponent_vp }}</TableCell>
                                        <TableCell class="hidden sm:table-cell">{{ sourceLabel(g.source) }}</TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </div>
                        <div class="flex flex-wrap items-center gap-3">
                            <Button
                                v-if="sortedGames.length > SHOW"
                                variant="outline"
                                size="sm"
                                class="max-sm:min-h-11"
                                data-testid="games-all"
                                @click="showAll = !showAll"
                            >
                                {{ showAll ? 'Show fewer' : `Show all ${sortedGames.length}` }}
                            </Button>
                            <p v-if="games_truncated" class="text-2xs text-muted-foreground">
                                The table lists the 500 most recent; the CSV has them all.
                            </p>
                        </div>
                    </CardContent>
                </Card>
            </template>
        </template>
    </div>
</template>
