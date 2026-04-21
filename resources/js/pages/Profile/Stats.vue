<script setup lang="ts">
import FactionLogo from '@/components/FactionLogo.vue';
import PageBanner from '@/components/PageBanner.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Head, Link } from '@inertiajs/vue3';
import EmptyState from '@/components/EmptyState.vue';
import { BarChart3, Copy, Crown, Hammer, Medal, Package, Paintbrush, Share2, Shield, Swords, TrendingUp, Trophy } from 'lucide-vue-next';
import { ref } from 'vue';

interface FactionStat {
    faction: string;
    games: number;
    wins: number;
    losses: number;
    ties: number;
    win_rate: number;
    avg_vp: number;
}

interface MasterStat {
    master_name: string;
    faction: string;
    games: number;
    wins: number;
    losses: number;
    ties: number;
    win_rate: number;
    avg_vp: number;
}

interface SchemeStat {
    name: string;
    times_scored: number;
    total_vp: number;
}

interface Matchup {
    my_keyword: string;
    opp_keyword: string;
    wins: number;
    losses: number;
    ties: number;
    games: number;
    win_rate: number;
}

interface RecentGame {
    uuid: string;
    result: 'win' | 'loss' | 'tie';
    faction: string;
    master_name: string | null;
    total_points: number;
    encounter_size: number;
    is_solo: boolean;
    strategy: string | null;
    completed_at: string | null;
}

defineProps<{
    user: { name: string; slug: string };
    stats: {
        total_games: number;
        wins: number;
        losses: number;
        ties: number;
        win_rate: number;
        solo_games: number;
        duel_games: number;
        total_vp: number;
        avg_vp: number;
        avg_scheme_vp: number;
        avg_strategy_vp: number;
        faction_stats: FactionStat[];
        master_stats: MasterStat[];
        scheme_stats: SchemeStat[];
        matchups: Matchup[];
        recent_games: RecentGame[];
    };
    profile: {
        collection: { total: number; built: number; painted: number };
        tournaments_played: number;
        best_tournament_finish: number | null;
        public_crews: number;
        favorite_faction: string | null;
        badges: { icon: string; label: string; description: string }[];
    };
    is_own_profile: boolean;
}>();

const badgeIcon = (icon: string) => {
    const map: Record<string, any> = {
        swords: Swords,
        trophy: Trophy,
        package: Package,
        paintbrush: Paintbrush,
        hammer: Hammer,
        crown: Crown,
        medal: Medal,
        share: Share2,
    };
    return map[icon] ?? Trophy;
};

const linkCopied = ref(false);
const copyLink = async () => {
    await navigator.clipboard.writeText(window.location.href);
    linkCopied.value = true;
    setTimeout(() => (linkCopied.value = false), 2000);
};

const showAllMasters = ref(false);
const showAllMatchups = ref(false);

const resultColor = (result: string) => {
    if (result === 'win') return 'text-green-600 dark:text-green-400';
    if (result === 'loss') return 'text-red-600 dark:text-red-400';
    return 'text-muted-foreground';
};

const resultBg = (result: string) => {
    if (result === 'win') return 'border-green-500/30 bg-green-500/5';
    if (result === 'loss') return 'border-red-500/30 bg-red-500/5';
    return 'border-border bg-muted/30';
};

const winRateColor = (rate: number) => {
    if (rate >= 60) return 'text-green-600 dark:text-green-400';
    if (rate >= 40) return 'text-amber-600 dark:text-amber-400';
    return 'text-red-600 dark:text-red-400';
};
</script>

<template>
    <Head :title="`${user.name}'s Stats`" />

    <div class="relative">
        <div
            class="pointer-events-none absolute inset-x-0 top-0 h-64 opacity-[0.07] dark:opacity-[0.12]"
            :style="{ background: 'radial-gradient(ellipse at top, hsl(var(--primary)) 0%, transparent 70%)' }"
        />

        <PageBanner :title="`${user.name}'s Profile`">
            <template #subtitle>
                <div class="flex items-center gap-2 px-2 text-sm text-muted-foreground">
                    <BarChart3 class="size-4" />
                    Player Profile &amp; Statistics
                </div>
            </template>
        </PageBanner>

        <div class="container mx-auto px-4 pb-12 pt-4">
            <!-- Share -->
            <div class="mb-6 flex justify-end">
                <Button variant="outline" size="sm" class="gap-1.5 text-xs" @click="copyLink">
                    <Copy class="size-3" />
                    {{ linkCopied ? 'Copied!' : 'Share Profile' }}
                </Button>
            </div>

            <!-- Profile Overview -->
            <div class="mb-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <Card v-if="profile.favorite_faction">
                    <CardContent class="p-4 text-center">
                        <FactionLogo :faction="profile.favorite_faction" class-name="mx-auto mb-1 size-8" />
                        <div class="text-[10px] uppercase text-muted-foreground">Favorite Faction</div>
                        <div class="text-sm font-bold capitalize">{{ profile.favorite_faction.replace('_', ' ') }}</div>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent class="p-4 text-center">
                        <Package class="mx-auto mb-1 size-6 text-muted-foreground" />
                        <div class="text-[10px] uppercase text-muted-foreground">Collection</div>
                        <div class="text-sm font-bold">{{ profile.collection.total }} models</div>
                        <div v-if="profile.collection.total > 0" class="mt-1 flex justify-center gap-2 text-[10px] text-muted-foreground">
                            <span>{{ profile.collection.built }} built</span>
                            <span>{{ profile.collection.painted }} painted</span>
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent class="p-4 text-center">
                        <Crown class="mx-auto mb-1 size-6 text-muted-foreground" />
                        <div class="text-[10px] uppercase text-muted-foreground">Tournaments</div>
                        <div class="text-sm font-bold">{{ profile.tournaments_played }}</div>
                        <div v-if="profile.best_tournament_finish" class="mt-1 text-[10px] text-muted-foreground">
                            Best:
                            {{
                                profile.best_tournament_finish === 1
                                    ? '1st'
                                    : profile.best_tournament_finish === 2
                                      ? '2nd'
                                      : profile.best_tournament_finish === 3
                                        ? '3rd'
                                        : profile.best_tournament_finish + 'th'
                            }}
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent class="p-4 text-center">
                        <Share2 class="mx-auto mb-1 size-6 text-muted-foreground" />
                        <div class="text-[10px] uppercase text-muted-foreground">Shared Crews</div>
                        <div class="text-sm font-bold">{{ profile.public_crews }}</div>
                    </CardContent>
                </Card>
            </div>

            <!-- Achievement Badges -->
            <Card v-if="profile.badges.length" class="mb-6">
                <CardContent class="p-4">
                    <div class="mb-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Achievements</div>
                    <div class="flex flex-wrap gap-2">
                        <div
                            v-for="badge in profile.badges"
                            :key="badge.label"
                            class="flex items-center gap-1.5 rounded-full border bg-card px-3 py-1.5 text-xs shadow-sm"
                            :title="badge.description"
                        >
                            <component :is="badgeIcon(badge.icon)" class="size-3.5 text-primary" />
                            <span class="font-medium">{{ badge.label }}</span>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- No games -->
            <EmptyState
                v-if="stats.total_games === 0"
                :icon="Trophy"
                title="No completed games yet"
                description="Stats will appear after completing games in the Game Tracker."
            />

            <template v-else>
                <!-- Overview -->
                <div class="mb-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <Card>
                        <CardContent class="p-4 text-center">
                            <div class="text-3xl font-bold tabular-nums">{{ stats.total_games }}</div>
                            <div class="text-xs text-muted-foreground">Games Played</div>
                            <div class="mt-1 text-[10px] tabular-nums text-muted-foreground">{{ stats.duel_games }} duel · {{ stats.solo_games }} solo</div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent class="p-4 text-center">
                            <div class="text-3xl font-bold tabular-nums" :class="winRateColor(stats.win_rate)">{{ stats.win_rate }}%</div>
                            <div class="text-xs text-muted-foreground">Win Rate</div>
                            <div class="mt-1 text-[10px] tabular-nums text-muted-foreground">{{ stats.wins }}W · {{ stats.losses }}L · {{ stats.ties }}T</div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent class="p-4 text-center">
                            <div class="text-3xl font-bold tabular-nums">{{ stats.avg_vp }}</div>
                            <div class="text-xs text-muted-foreground">Avg VP / Game</div>
                            <div class="mt-1 text-[10px] tabular-nums text-muted-foreground">{{ stats.total_vp }} total VP</div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent class="p-4 text-center">
                            <div class="flex items-center justify-center gap-3 text-lg font-bold tabular-nums">
                                <span>{{ stats.avg_strategy_vp }}</span>
                                <span class="text-xs font-normal text-muted-foreground">/</span>
                                <span>{{ stats.avg_scheme_vp }}</span>
                            </div>
                            <div class="text-xs text-muted-foreground">Avg Strategy / Scheme VP</div>
                        </CardContent>
                    </Card>
                </div>

                <!-- Faction & Master Stats -->
                <div class="mb-6 grid gap-6 lg:grid-cols-2">
                    <!-- Factions -->
                    <Card v-if="stats.faction_stats.length">
                        <CardContent class="p-4">
                            <h3 class="mb-3 flex items-center gap-2 text-sm font-semibold"><Shield class="size-4" /> Factions</h3>
                            <div class="space-y-1.5">
                                <div
                                    v-for="f in stats.faction_stats"
                                    :key="f.faction"
                                    class="flex items-center gap-2 rounded-md border px-3 py-2 text-xs"
                                >
                                    <FactionLogo :faction="f.faction" class-name="size-5 shrink-0" />
                                    <div class="min-w-0 flex-1">
                                        <div class="font-medium capitalize">{{ f.faction?.replace('_', ' ') }}</div>
                                        <div class="text-[10px] text-muted-foreground">{{ f.games }} games · {{ f.avg_vp }} avg VP</div>
                                    </div>
                                    <div class="text-right">
                                        <div class="font-bold" :class="winRateColor(f.win_rate)">{{ f.win_rate }}%</div>
                                        <div class="text-[10px] text-muted-foreground">{{ f.wins }}W {{ f.losses }}L {{ f.ties }}T</div>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Masters -->
                    <Card v-if="stats.master_stats.length">
                        <CardContent class="p-4">
                            <h3 class="mb-3 flex items-center gap-2 text-sm font-semibold"><Crown class="size-4" /> Masters</h3>
                            <div class="space-y-1.5">
                                <div
                                    v-for="m in showAllMasters ? stats.master_stats : stats.master_stats.slice(0, 8)"
                                    :key="m.master_name"
                                    class="flex items-center gap-2 rounded-md border px-3 py-2 text-xs"
                                >
                                    <FactionLogo :faction="m.faction" class-name="size-4 shrink-0" />
                                    <div class="min-w-0 flex-1">
                                        <div class="truncate font-medium">{{ m.master_name }}</div>
                                        <div class="text-[10px] text-muted-foreground">{{ m.games }} games · {{ m.avg_vp }} avg VP</div>
                                    </div>
                                    <div class="text-right">
                                        <div class="font-bold" :class="winRateColor(m.win_rate)">{{ m.win_rate }}%</div>
                                        <div class="text-[10px] text-muted-foreground">{{ m.wins }}W {{ m.losses }}L</div>
                                    </div>
                                </div>
                            </div>
                            <button
                                v-if="stats.master_stats.length > 8"
                                class="mt-2 w-full text-center text-xs text-muted-foreground hover:text-foreground"
                                @click="showAllMasters = !showAllMasters"
                            >
                                {{ showAllMasters ? 'Show less' : `Show all ${stats.master_stats.length}` }}
                            </button>
                        </CardContent>
                    </Card>
                </div>

                <!-- Scheme Stats -->
                <Card v-if="stats.scheme_stats.length" class="mb-6">
                    <CardContent class="p-4">
                        <h3 class="mb-3 flex items-center gap-2 text-sm font-semibold"><TrendingUp class="size-4" /> Top Scoring Schemes</h3>
                        <div class="grid gap-2 sm:grid-cols-2">
                            <div
                                v-for="s in stats.scheme_stats"
                                :key="s.name"
                                class="flex items-center justify-between rounded-md border px-3 py-2 text-xs"
                            >
                                <span class="font-medium">{{ s.name }}</span>
                                <div class="text-right">
                                    <span class="font-bold text-green-600 dark:text-green-400">{{ s.total_vp }} VP</span>
                                    <span class="ml-1 text-muted-foreground">({{ s.times_scored }}x)</span>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Keyword Matchup Matrix -->
                <Card v-if="stats.matchups.length" class="mb-6">
                    <CardContent class="p-4">
                        <h3 class="mb-3 flex items-center gap-2 text-sm font-semibold"><Swords class="size-4" /> Keyword Matchups (Duel)</h3>
                        <p class="mb-3 text-xs text-muted-foreground">Your keyword vs opponent's keyword — win rate from completed duel games.</p>
                        <div class="overflow-x-auto">
                            <table class="w-full border-collapse text-xs">
                                <thead>
                                    <tr class="border-b">
                                        <th class="px-2 py-1.5 text-left text-[10px] font-semibold uppercase tracking-wide text-muted-foreground">
                                            Your Keyword
                                        </th>
                                        <th class="px-2 py-1.5 text-left text-[10px] font-semibold uppercase tracking-wide text-muted-foreground">
                                            vs
                                        </th>
                                        <th class="px-2 py-1.5 text-center text-[10px] font-semibold uppercase tracking-wide text-muted-foreground">
                                            Games
                                        </th>
                                        <th class="px-2 py-1.5 text-center text-[10px] font-semibold uppercase tracking-wide text-muted-foreground">
                                            Record
                                        </th>
                                        <th class="px-2 py-1.5 text-center text-[10px] font-semibold uppercase tracking-wide text-muted-foreground">
                                            Win %
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="(m, idx) in showAllMatchups ? stats.matchups : stats.matchups.slice(0, 15)"
                                        :key="idx"
                                        class="border-b border-border/50 last:border-b-0"
                                    >
                                        <td class="px-2 py-1.5 font-medium">{{ m.my_keyword }}</td>
                                        <td class="px-2 py-1.5 text-muted-foreground">{{ m.opp_keyword }}</td>
                                        <td class="px-2 py-1.5 text-center">{{ m.games }}</td>
                                        <td class="px-2 py-1.5 text-center text-muted-foreground">{{ m.wins }}-{{ m.losses }}-{{ m.ties }}</td>
                                        <td class="px-2 py-1.5 text-center font-bold" :class="winRateColor(m.win_rate)">{{ m.win_rate }}%</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <button
                            v-if="stats.matchups.length > 15"
                            class="mt-2 w-full text-center text-xs text-muted-foreground hover:text-foreground"
                            @click="showAllMatchups = !showAllMatchups"
                        >
                            {{ showAllMatchups ? 'Show less' : `Show all ${stats.matchups.length} matchups` }}
                        </button>
                    </CardContent>
                </Card>

                <!-- Recent Games -->
                <Card v-if="stats.recent_games.length">
                    <CardContent class="p-4">
                        <h3 class="mb-3 flex items-center gap-2 text-sm font-semibold"><Swords class="size-4" /> Recent Games</h3>
                        <div class="space-y-1.5">
                            <Link
                                v-for="g in stats.recent_games"
                                :key="g.uuid"
                                :href="route('games.summary', g.uuid)"
                                class="flex items-center gap-3 rounded-md border px-3 py-2 text-xs transition-colors hover:bg-muted/50"
                                :class="resultBg(g.result)"
                            >
                                <FactionLogo v-if="g.faction" :faction="g.faction" class-name="size-5 shrink-0" />
                                <div class="min-w-0 flex-1">
                                    <div class="truncate font-medium">{{ g.master_name || 'Unknown Master' }}</div>
                                    <div class="text-[10px] text-muted-foreground">
                                        {{ g.encounter_size }}ss · {{ g.strategy || 'No strategy' }}
                                        <Badge v-if="g.is_solo" variant="outline" class="ml-1 px-1 py-0 text-[8px]">Solo</Badge>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <div class="font-bold" :class="resultColor(g.result)">
                                        {{ g.result === 'win' ? 'Win' : g.result === 'loss' ? 'Loss' : 'Tie' }}
                                    </div>
                                    <div class="text-[10px] text-muted-foreground">{{ g.total_points }} VP · {{ g.completed_at }}</div>
                                </div>
                            </Link>
                        </div>
                    </CardContent>
                </Card>
            </template>
        </div>
    </div>
</template>
