<script setup lang="ts">
import EmptyState from '@/components/EmptyState.vue';
import HeadingEyebrow from '@/components/HeadingEyebrow.vue';
import PageBanner from '@/components/PageBanner.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { CARD_HOVER } from '@/lib/cardHover';
import { Head, Link } from '@inertiajs/vue3';
import {
    BarChart3,
    Building2,
    CalendarDays,
    Crown,
    Dice6,
    FileImage,
    Gift,
    Hammer,
    Heart,
    Library,
    Medal,
    Package,
    Paintbrush,
    Search,
    Share2,
    Shield,
    Swords,
    Trophy,
} from 'lucide-vue-next';
import { computed } from 'vue';

interface CampaignSummary {
    id: number;
    name: string;
    current_week: number;
    length_weeks: number;
}

interface UpcomingTournament {
    uuid: string;
    name: string;
    event_date: string;
}

interface Achievements {
    badges: { icon: string; label: string; description: string }[];
    total_games: number;
    wins: number;
    tournaments_played: number;
    best_tournament_finish: number | null;
}

const props = defineProps<{
    active_games: number;
    collection: {
        malifaux_miniatures: number;
        malifaux_packages: number;
        tos_unit_sculpts: number;
    };
    wishlists: {
        count: number;
        items: number;
    };
    crew_builds: number;
    campaigns: CampaignSummary[];
    upcoming_tournaments: UpcomingTournament[];
    is_supporter: boolean;
    tos_companies: number;
    tos_garrisons: number;
    custom_cards: number;
    saved_searches: {
        malifaux: number;
        tos: number;
    };
    // Inertia::defer prop — null until the follow-up request resolves.
    achievements: Achievements | null;
}>();

const winRate = computed(() => {
    if (!props.achievements || props.achievements.total_games === 0) return 0;
    return Math.round((props.achievements.wins / props.achievements.total_games) * 100);
});

const formatEventDate = (date: string) => {
    return new Date(`${date}T00:00:00`).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
};

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
</script>

<template>
    <Head title="My Hub" />

    <div class="container mx-auto mt-6 space-y-8 px-4 pb-12">
        <PageBanner title="My Hub" class="rounded-lg">
            <template #subtitle>
                <div class="my-auto px-2 py-0 text-xs text-muted-foreground md:py-2 md:text-sm md:text-foreground">
                    A quick look at your collection, games, and campaigns — across both Malifaux and The Other Side.
                </div>
            </template>
        </PageBanner>

        <!-- At a Glance -->
        <div>
            <HeadingEyebrow class="mb-2">At a Glance</HeadingEyebrow>
            <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                <Link :href="route('games.index')">
                    <Card :class="['h-full', CARD_HOVER]">
                        <CardContent class="p-4 text-center">
                            <Dice6 class="mx-auto mb-1 size-5 text-muted-foreground" />
                            <div class="text-2xl font-bold tracking-tight sm:text-3xl">{{ props.active_games }}</div>
                            <div class="mt-0.5 text-xs text-muted-foreground">Active Games</div>
                        </CardContent>
                    </Card>
                </Link>
                <Link :href="route('wishlists.index')">
                    <Card :class="['h-full', CARD_HOVER]">
                        <CardContent class="p-4 text-center">
                            <Heart class="mx-auto mb-1 size-5 text-muted-foreground" />
                            <div class="text-2xl font-bold tracking-tight sm:text-3xl">{{ props.wishlists.items }}</div>
                            <div class="mt-0.5 text-xs text-muted-foreground">
                                Wishlist Items ({{ props.wishlists.count }} {{ props.wishlists.count === 1 ? 'list' : 'lists' }})
                            </div>
                        </CardContent>
                    </Card>
                </Link>
                <Link :href="route('stats.my')">
                    <Card :class="['h-full', CARD_HOVER]">
                        <CardContent class="p-4 text-center">
                            <BarChart3 class="mx-auto mb-1 size-5 text-muted-foreground" />
                            <div class="text-sm font-semibold">My Stats</div>
                            <div class="mt-0.5 text-xs text-muted-foreground">Win/loss record &amp; breakdowns</div>
                        </CardContent>
                    </Card>
                </Link>
                <Card class="h-full">
                    <CardContent class="flex h-full flex-col p-4 text-center">
                        <Search class="mx-auto mb-1 size-5 text-muted-foreground" />
                        <div class="text-sm font-semibold">Saved Searches</div>
                        <!-- Two distinct destinations, so the card itself isn't a single
                             link — instead each half gets its own obviously-clickable
                             treatment (not just small underlined text) so it doesn't read
                             as a dead click zone next to fully-clickable sibling tiles. -->
                        <div class="mt-2 grid flex-1 grid-cols-2 gap-1.5 text-xs">
                            <Link
                                :href="route('search.view')"
                                class="flex flex-col items-center justify-center rounded-md border border-transparent px-2 py-1.5 text-muted-foreground transition-colors hover:border-primary/30 hover:bg-accent hover:text-foreground"
                            >
                                <span class="text-base font-semibold tabular-nums text-foreground">{{ props.saved_searches.malifaux }}</span>
                                Malifaux
                            </Link>
                            <Link
                                :href="route('tos.search')"
                                class="flex flex-col items-center justify-center rounded-md border border-transparent px-2 py-1.5 text-muted-foreground transition-colors hover:border-primary/30 hover:bg-accent hover:text-foreground"
                            >
                                <span class="text-base font-semibold tabular-nums text-foreground">{{ props.saved_searches.tos }}</span>
                                TOS
                            </Link>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>

        <!-- Malifaux -->
        <div>
            <HeadingEyebrow class="mb-2">Malifaux</HeadingEyebrow>
            <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                <Link :href="route('collection.index')">
                    <Card :class="['h-full', CARD_HOVER]">
                        <CardContent class="p-4 text-center">
                            <Library class="mx-auto mb-1 size-5 text-muted-foreground" />
                            <div class="text-2xl font-bold tracking-tight sm:text-3xl">{{ props.collection.malifaux_miniatures }}</div>
                            <div class="mt-0.5 text-xs text-muted-foreground">Miniatures ({{ props.collection.malifaux_packages }} packages)</div>
                        </CardContent>
                    </Card>
                </Link>
                <Link :href="route('tools.crew_builder.index')">
                    <Card :class="['h-full', CARD_HOVER]">
                        <CardContent class="p-4 text-center">
                            <Swords class="mx-auto mb-1 size-5 text-muted-foreground" />
                            <div class="text-2xl font-bold tracking-tight sm:text-3xl">{{ props.crew_builds }}</div>
                            <div class="mt-0.5 text-xs text-muted-foreground">Crew Builds</div>
                        </CardContent>
                    </Card>
                </Link>
                <Link :href="route('tools.card_creator.index')">
                    <Card :class="['h-full', CARD_HOVER]">
                        <CardContent class="p-4 text-center">
                            <FileImage class="mx-auto mb-1 size-5 text-muted-foreground" />
                            <div class="text-2xl font-bold tracking-tight sm:text-3xl">{{ props.custom_cards }}</div>
                            <div class="mt-0.5 text-xs text-muted-foreground">Custom Cards</div>
                        </CardContent>
                    </Card>
                </Link>
            </div>
        </div>

        <!-- The Other Side -->
        <div>
            <HeadingEyebrow class="mb-2">The Other Side</HeadingEyebrow>
            <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                <Link :href="route('tos.collection.index')">
                    <Card :class="['h-full', CARD_HOVER]">
                        <CardContent class="p-4 text-center">
                            <Package class="mx-auto mb-1 size-5 text-muted-foreground" />
                            <div class="text-2xl font-bold tracking-tight sm:text-3xl">{{ props.collection.tos_unit_sculpts }}</div>
                            <div class="mt-0.5 text-xs text-muted-foreground">Unit Sculpts</div>
                        </CardContent>
                    </Card>
                </Link>
                <Link :href="route('tos.companies.index')">
                    <Card :class="['h-full', CARD_HOVER]">
                        <CardContent class="p-4 text-center">
                            <Building2 class="mx-auto mb-1 size-5 text-muted-foreground" />
                            <div class="text-2xl font-bold tracking-tight sm:text-3xl">{{ props.tos_companies }}</div>
                            <div class="mt-0.5 text-xs text-muted-foreground">Companies</div>
                        </CardContent>
                    </Card>
                </Link>
                <Link :href="route('tos.garrisons.index')">
                    <Card :class="['h-full', CARD_HOVER]">
                        <CardContent class="p-4 text-center">
                            <Shield class="mx-auto mb-1 size-5 text-muted-foreground" />
                            <div class="text-2xl font-bold tracking-tight sm:text-3xl">{{ props.tos_garrisons }}</div>
                            <div class="mt-0.5 text-xs text-muted-foreground">Garrisons</div>
                        </CardContent>
                    </Card>
                </Link>
            </div>
        </div>

        <!-- Campaigns -->
        <div>
            <HeadingEyebrow class="mb-2">Active Campaigns</HeadingEyebrow>
            <div v-if="props.campaigns.length" class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <Link v-for="campaign in props.campaigns" :key="campaign.id" :href="route('campaigns.show', campaign.id)">
                    <Card :class="['h-full', CARD_HOVER]">
                        <CardContent class="p-4">
                            <div class="flex items-center justify-between gap-2">
                                <span class="truncate text-sm font-semibold">{{ campaign.name }}</span>
                                <Badge variant="outline" class="shrink-0 text-xs">
                                    Week {{ campaign.current_week }}/{{ campaign.length_weeks }}
                                </Badge>
                            </div>
                        </CardContent>
                    </Card>
                </Link>
            </div>
            <EmptyState v-else compact :icon="CalendarDays" title="No active campaigns" description="Join or start a campaign to see it here." />
        </div>

        <!-- Upcoming Tournaments -->
        <div>
            <HeadingEyebrow class="mb-2">Upcoming Tournaments</HeadingEyebrow>
            <div v-if="props.upcoming_tournaments.length" class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <Link v-for="tournament in props.upcoming_tournaments" :key="tournament.uuid" :href="route('tournaments.view', tournament.uuid)">
                    <Card :class="['h-full', CARD_HOVER]">
                        <CardContent class="p-4">
                            <div class="flex items-center justify-between gap-2">
                                <span class="truncate text-sm font-semibold">{{ tournament.name }}</span>
                                <Badge variant="outline" class="shrink-0 text-xs">{{ formatEventDate(tournament.event_date) }}</Badge>
                            </div>
                        </CardContent>
                    </Card>
                </Link>
            </div>
            <EmptyState v-else compact :icon="Trophy" title="No upcoming tournaments" description="RSVP to a tournament to see it here." />
        </div>

        <!-- Achievements -->
        <div>
            <HeadingEyebrow class="mb-2">Achievements</HeadingEyebrow>
            <Card v-if="achievements === null" class="animate-pulse">
                <CardContent class="p-4">
                    <div class="h-4 w-1/3 rounded bg-muted" />
                    <div class="mt-3 flex flex-wrap gap-2">
                        <div v-for="i in 5" :key="i" class="h-7 w-24 rounded-full bg-muted" />
                    </div>
                </CardContent>
            </Card>
            <Card v-else>
                <CardContent class="p-4">
                    <div class="mb-3 flex flex-wrap gap-4 text-sm text-muted-foreground">
                        <span
                            >{{ achievements.wins }} / {{ achievements.total_games }} games won
                            <span v-if="achievements.total_games > 0">({{ winRate }}%)</span></span
                        >
                        <span v-if="achievements.tournaments_played > 0">
                            {{ achievements.tournaments_played }} {{ achievements.tournaments_played === 1 ? 'tournament' : 'tournaments' }} played
                            <template v-if="achievements.best_tournament_finish"> — best finish #{{ achievements.best_tournament_finish }}</template>
                        </span>
                    </div>
                    <EmptyState
                        v-if="achievements.badges.length === 0"
                        compact
                        :icon="Trophy"
                        title="No achievements yet"
                        description="Play games, grow your collection, and enter tournaments to earn badges."
                    />
                    <div v-else class="flex flex-wrap gap-2">
                        <div
                            v-for="badge in achievements.badges"
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
        </div>

        <Card v-if="!props.is_supporter">
            <CardContent class="flex flex-col items-center justify-between gap-3 p-4 sm:flex-row">
                <div class="flex items-center gap-3">
                    <Gift class="size-6 shrink-0 text-muted-foreground" />
                    <div>
                        <div class="text-sm font-semibold">Support BiggerHat</div>
                        <div class="text-xs text-muted-foreground">Help keep the site running and get a Supporter badge.</div>
                    </div>
                </div>
                <Button as="a" href="https://ko-fi.com/biggerhat" target="_blank" variant="outline" size="sm"> Donate on Ko-fi </Button>
            </CardContent>
        </Card>
        <Card v-else class="border-primary/40 bg-primary/5">
            <CardContent class="flex items-center gap-3 p-4">
                <Gift class="size-6 shrink-0 text-primary" />
                <div class="text-sm font-semibold">Thank you for supporting BiggerHat!</div>
                <Badge variant="outline" class="ml-auto">Supporter</Badge>
            </CardContent>
        </Card>
    </div>
</template>
