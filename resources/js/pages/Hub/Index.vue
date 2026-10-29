<script setup lang="ts">
import EmptyState from '@/components/EmptyState.vue';
import HeadingEyebrow from '@/components/HeadingEyebrow.vue';
import PageBanner from '@/components/PageBanner.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { CARD_HOVER } from '@/lib/cardHover';
import type { SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    BarChart3,
    Check,
    ChevronRight,
    Circle,
    Crown,
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
    Users,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface RecentGame {
    uuid: string;
    name: string | null;
    encounter_size: number;
    status: string;
    current_turn: number;
    max_turns: number;
    is_solo: boolean;
    opponent: string | null;
}

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

interface Badge {
    icon: string;
    label: string;
    description: string;
}

interface Achievements {
    badges: Badge[];
    next_badges: (Badge & { current: number; target: number })[];
    total_games: number;
    wins: number;
    tournaments_played: number;
    best_tournament_finish: number | null;
}

const props = defineProps<{
    active_games: number;
    recent_games: RecentGame[];
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

const page = usePage<SharedData>();
const firstName = computed(() => (page.props.auth.user?.name ?? '').split(' ')[0] || 'there');

const hasContinue = computed(() => props.recent_games.length > 0 || props.campaigns.length > 0 || props.upcoming_tournaments.length > 0);

// New-user checklist, shown until the basics are done (and only while there is nothing to resume).
const checklist = computed(() => [
    {
        label: 'Build a crew',
        hint: 'Pick a master and hire your models',
        done: props.crew_builds > 0,
        href: route('tools.crew_builder.editor'),
        icon: Swords,
    },
    {
        label: 'Add models to your collection',
        hint: 'Track what you own, built and painted',
        done: props.collection.malifaux_miniatures > 0,
        href: route('collection.index'),
        icon: Library,
    },
    {
        label: 'Start a game',
        hint: 'Track a live game, solo or with a friend',
        done: props.active_games > 0 || (props.achievements?.total_games ?? 0) > 0,
        href: route('games.create'),
        icon: Trophy,
    },
    {
        label: 'Make a wishlist',
        hint: 'Keep a list of what you want next',
        done: props.wishlists.count > 0,
        href: route('wishlists.index'),
        icon: Heart,
    },
]);
const checklistDone = computed(() => checklist.value.filter((i) => i.done).length);
const showChecklist = computed(() => checklistDone.value < checklist.value.length && !hasContinue.value);

const malifauxRows = computed(() => [
    { label: 'Miniatures', value: props.collection.malifaux_miniatures, href: route('collection.index'), icon: Library },
    { label: 'Crew builds', value: props.crew_builds, href: route('tools.crew_builder.editor'), icon: Swords },
    { label: 'Custom cards', value: props.custom_cards, href: route('tools.card_creator.index'), icon: FileImage },
    { label: 'Saved searches', value: props.saved_searches.malifaux, href: route('search.view'), icon: Search },
]);
const tosRows = computed(() => [
    { label: 'Unit sculpts', value: props.collection.tos_unit_sculpts, href: route('tos.collection.index'), icon: Package },
    { label: 'Companies', value: props.tos_companies, href: route('tos.companies.index'), icon: Users },
    { label: 'Garrisons', value: props.tos_garrisons, href: route('tos.garrisons.index'), icon: Shield },
    { label: 'Saved searches', value: props.saved_searches.tos, href: route('tos.search'), icon: Search },
]);

const winRate = computed(() => {
    if (!props.achievements || props.achievements.total_games === 0) return 0;
    return Math.round((props.achievements.wins / props.achievements.total_games) * 100);
});

const formatEventDate = (date: string) =>
    new Date(`${date}T00:00:00`).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });

const STATUS_LABELS: Record<string, string> = {
    setup: 'Setting up',
    faction_select: 'Choosing factions',
    master_select: 'Choosing masters',
    crew_select: 'Building crews',
    scheme_select: 'Choosing schemes',
    in_progress: 'In progress',
};
const gameStatus = (g: RecentGame) =>
    g.status === 'in_progress' ? `Turn ${g.current_turn} of ${g.max_turns}` : (STATUS_LABELS[g.status] ?? 'Setting up');

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

const showAllBadges = ref(false);
const BADGE_PREVIEW = 8;
const visibleBadges = computed(() => (showAllBadges.value ? props.achievements?.badges : props.achievements?.badges.slice(0, BADGE_PREVIEW)) ?? []);
</script>

<template>
    <Head title="My Hub" />

    <div class="container mx-auto mt-6 space-y-8 pb-12">
        <PageBanner :title="`Welcome back, ${firstName}`" class="rounded-lg">
            <template #subtitle>
                <div class="my-auto px-2 py-0 text-xs text-muted-foreground md:py-2 md:text-sm md:text-foreground">
                    {{
                        showChecklist
                            ? "Let's get you set up."
                            : hasContinue
                              ? 'Pick up where you left off, or start something new.'
                              : 'Start something new.'
                    }}
                </div>
            </template>
            <template #actions>
                <div class="my-auto flex flex-wrap gap-2 px-2 pb-2 md:pb-0 md:pr-4">
                    <Button as-child size="sm">
                        <Link :href="route('games.create')"><Trophy class="mr-1.5 size-4" /> Start a game</Link>
                    </Button>
                    <Button as-child size="sm" variant="outline">
                        <Link :href="route('tools.crew_builder.editor')"><Swords class="mr-1.5 size-4" /> Build a crew</Link>
                    </Button>
                </div>
            </template>
        </PageBanner>

        <!-- Continue: things in motion -->
        <section v-if="hasContinue" aria-labelledby="hub-continue" class="space-y-3">
            <HeadingEyebrow id="hub-continue">Pick up where you left off</HeadingEyebrow>
            <div class="grid grid-cols-1 gap-3 md:grid-cols-2 lg:grid-cols-3">
                <Link v-for="game in props.recent_games" :key="game.uuid" :href="route('games.show', game.uuid)">
                    <Card :class="['h-full', CARD_HOVER]">
                        <CardContent class="flex h-full flex-col gap-2 p-4">
                            <div class="flex items-center justify-between gap-2">
                                <Badge variant="secondary" class="gap-1"><Swords class="size-3" /> Game</Badge>
                                <span class="text-xs text-muted-foreground">{{ game.encounter_size }}ss</span>
                            </div>
                            <div class="truncate text-sm font-semibold">
                                {{ game.name || (game.is_solo ? 'Solo game' : game.opponent ? `vs ${game.opponent}` : 'Open game') }}
                            </div>
                            <div class="mt-auto flex items-center justify-between text-xs text-muted-foreground">
                                <span>{{ gameStatus(game) }}</span>
                                <span class="inline-flex items-center gap-0.5 font-medium text-foreground"
                                    >Resume <ChevronRight class="size-3.5"
                                /></span>
                            </div>
                        </CardContent>
                    </Card>
                </Link>
                <Link v-for="campaign in props.campaigns" :key="`c-${campaign.id}`" :href="route('campaigns.show', campaign.id)">
                    <Card :class="['h-full', CARD_HOVER]">
                        <CardContent class="flex h-full flex-col gap-2 p-4">
                            <div class="flex items-center justify-between gap-2">
                                <Badge variant="secondary">Campaign</Badge>
                                <span class="text-xs text-muted-foreground">Week {{ campaign.current_week }} of {{ campaign.length_weeks }}</span>
                            </div>
                            <div class="truncate text-sm font-semibold">{{ campaign.name }}</div>
                            <div
                                class="mt-auto h-1.5 overflow-hidden rounded-full bg-muted"
                                role="progressbar"
                                :aria-valuenow="campaign.current_week"
                                :aria-valuemin="0"
                                :aria-valuemax="campaign.length_weeks"
                                :aria-label="`${campaign.name} progress`"
                            >
                                <div
                                    class="h-full rounded-full bg-primary"
                                    :style="{ width: `${Math.min(100, (campaign.current_week / campaign.length_weeks) * 100)}%` }"
                                />
                            </div>
                        </CardContent>
                    </Card>
                </Link>
                <Link v-for="tournament in props.upcoming_tournaments" :key="tournament.uuid" :href="route('tournaments.view', tournament.uuid)">
                    <Card :class="['h-full', CARD_HOVER]">
                        <CardContent class="flex h-full flex-col gap-2 p-4">
                            <div class="flex items-center justify-between gap-2">
                                <Badge variant="secondary" class="gap-1"><Trophy class="size-3" /> Tournament</Badge>
                                <span class="text-xs text-muted-foreground">{{ formatEventDate(tournament.event_date) }}</span>
                            </div>
                            <div class="truncate text-sm font-semibold">{{ tournament.name }}</div>
                        </CardContent>
                    </Card>
                </Link>
            </div>
        </section>

        <!-- Getting started (new accounts) -->
        <section v-if="showChecklist" aria-labelledby="hub-start">
            <Card>
                <CardContent class="p-4 sm:p-5">
                    <div class="mb-3 flex items-center justify-between gap-2">
                        <HeadingEyebrow id="hub-start">Get started</HeadingEyebrow>
                        <span class="text-xs text-muted-foreground">{{ checklistDone }} of {{ checklist.length }} done</span>
                    </div>
                    <ul class="grid gap-2 sm:grid-cols-2">
                        <li v-for="item in checklist" :key="item.label">
                            <Link
                                :href="item.href"
                                class="flex items-center gap-3 rounded-lg border p-3 transition-colors hover:bg-accent"
                                :class="item.done ? 'opacity-70' : ''"
                            >
                                <span
                                    class="flex size-7 shrink-0 items-center justify-center rounded-full"
                                    :class="item.done ? 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-400' : 'bg-muted text-muted-foreground'"
                                >
                                    <Check v-if="item.done" class="size-4" />
                                    <Circle v-else class="size-4" />
                                </span>
                                <span class="min-w-0">
                                    <span class="block text-sm font-medium" :class="item.done ? 'line-through' : ''">{{ item.label }}</span>
                                    <span class="block truncate text-xs text-muted-foreground">{{ item.hint }}</span>
                                </span>
                                <ChevronRight class="ml-auto size-4 shrink-0 text-muted-foreground" />
                            </Link>
                        </li>
                    </ul>
                </CardContent>
            </Card>
        </section>

        <!-- Your stuff -->
        <section aria-labelledby="hub-stuff" class="space-y-3">
            <HeadingEyebrow id="hub-stuff">Your stuff</HeadingEyebrow>
            <div class="grid gap-3 lg:grid-cols-3">
                <Card>
                    <CardContent class="p-2 sm:p-3">
                        <h3 class="px-3 pb-1 pt-2 text-sm font-semibold">Malifaux</h3>
                        <ul>
                            <li v-for="row in malifauxRows" :key="row.label">
                                <Link
                                    :href="row.href"
                                    class="flex items-center gap-3 rounded-md px-3 py-2.5 text-sm transition-colors hover:bg-accent"
                                >
                                    <component :is="row.icon" class="size-4 shrink-0 text-muted-foreground" />
                                    <span>{{ row.label }}</span>
                                    <span class="ml-auto font-semibold tabular-nums" :class="row.value === 0 ? 'text-muted-foreground' : ''">{{
                                        row.value
                                    }}</span>
                                    <ChevronRight class="size-4 shrink-0 text-muted-foreground" />
                                </Link>
                            </li>
                        </ul>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent class="p-2 sm:p-3">
                        <h3 class="px-3 pb-1 pt-2 text-sm font-semibold">The Other Side</h3>
                        <ul>
                            <li v-for="row in tosRows" :key="row.label">
                                <Link
                                    :href="row.href"
                                    class="flex items-center gap-3 rounded-md px-3 py-2.5 text-sm transition-colors hover:bg-accent"
                                >
                                    <component :is="row.icon" class="size-4 shrink-0 text-muted-foreground" />
                                    <span>{{ row.label }}</span>
                                    <span class="ml-auto font-semibold tabular-nums" :class="row.value === 0 ? 'text-muted-foreground' : ''">{{
                                        row.value
                                    }}</span>
                                    <ChevronRight class="size-4 shrink-0 text-muted-foreground" />
                                </Link>
                            </li>
                        </ul>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent class="p-2 sm:p-3">
                        <h3 class="px-3 pb-1 pt-2 text-sm font-semibold">Across both games</h3>
                        <ul>
                            <li>
                                <Link
                                    :href="route('wishlists.index')"
                                    class="flex items-center gap-3 rounded-md px-3 py-2.5 text-sm transition-colors hover:bg-accent"
                                >
                                    <Heart class="size-4 shrink-0 text-muted-foreground" />
                                    <span>Wishlist items</span>
                                    <span class="ml-auto text-xs text-muted-foreground"
                                        >{{ props.wishlists.count }} {{ props.wishlists.count === 1 ? 'list' : 'lists' }}</span
                                    >
                                    <span class="font-semibold tabular-nums" :class="props.wishlists.items === 0 ? 'text-muted-foreground' : ''">{{
                                        props.wishlists.items
                                    }}</span>
                                    <ChevronRight class="size-4 shrink-0 text-muted-foreground" />
                                </Link>
                            </li>
                            <li>
                                <Link
                                    :href="route('stats.my')"
                                    class="flex items-center gap-3 rounded-md px-3 py-2.5 text-sm transition-colors hover:bg-accent"
                                >
                                    <BarChart3 class="size-4 shrink-0 text-muted-foreground" />
                                    <span>My stats</span>
                                    <span class="ml-auto text-xs text-muted-foreground">Win/loss &amp; breakdowns</span>
                                    <ChevronRight class="size-4 shrink-0 text-muted-foreground" />
                                </Link>
                            </li>
                            <li>
                                <Link
                                    :href="route('friends.index')"
                                    class="flex items-center gap-3 rounded-md px-3 py-2.5 text-sm transition-colors hover:bg-accent"
                                >
                                    <Users class="size-4 shrink-0 text-muted-foreground" />
                                    <span>Friends</span>
                                    <ChevronRight class="ml-auto size-4 shrink-0 text-muted-foreground" />
                                </Link>
                            </li>
                        </ul>
                    </CardContent>
                </Card>
            </div>
        </section>

        <!-- Achievements -->
        <section aria-labelledby="hub-ach" class="space-y-3">
            <HeadingEyebrow id="hub-ach">Achievements</HeadingEyebrow>
            <Card v-if="achievements === null" class="animate-pulse">
                <CardContent class="p-4">
                    <div class="h-4 w-1/3 rounded bg-muted" />
                    <div class="mt-3 flex flex-wrap gap-2">
                        <div v-for="i in 5" :key="i" class="h-7 w-24 rounded-full bg-muted" />
                    </div>
                </CardContent>
            </Card>
            <Card v-else>
                <CardContent class="space-y-5 p-4 sm:p-5">
                    <div class="flex flex-wrap gap-x-6 gap-y-1 text-sm text-muted-foreground">
                        <span>
                            <span class="font-semibold text-foreground">{{ achievements.wins }}</span> of
                            <span class="font-semibold text-foreground">{{ achievements.total_games }}</span> games won
                            <span v-if="achievements.total_games > 0">({{ winRate }}%)</span>
                        </span>
                        <span v-if="achievements.tournaments_played > 0">
                            {{ achievements.tournaments_played }} {{ achievements.tournaments_played === 1 ? 'tournament' : 'tournaments' }} played
                            <template v-if="achievements.best_tournament_finish"> · best finish #{{ achievements.best_tournament_finish }}</template>
                        </span>
                    </div>

                    <div v-if="achievements.next_badges.length">
                        <h3 class="mb-2 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Up next</h3>
                        <ul class="grid gap-3 sm:grid-cols-3">
                            <li v-for="next in achievements.next_badges" :key="next.label" class="rounded-lg border p-3">
                                <div class="flex items-center gap-2 text-sm font-medium">
                                    <component :is="badgeIcon(next.icon)" class="size-4 text-muted-foreground" />
                                    {{ next.label }}
                                </div>
                                <div class="mt-0.5 text-xs text-muted-foreground">{{ next.description }}</div>
                                <div
                                    class="mt-2 h-1.5 overflow-hidden rounded-full bg-muted"
                                    role="progressbar"
                                    :aria-valuenow="next.current"
                                    :aria-valuemin="0"
                                    :aria-valuemax="next.target"
                                    :aria-label="`${next.label}: ${next.current} of ${next.target}`"
                                >
                                    <div class="h-full rounded-full bg-primary" :style="{ width: `${(next.current / next.target) * 100}%` }" />
                                </div>
                                <div class="mt-1 text-right text-[11px] tabular-nums text-muted-foreground">
                                    {{ next.current }} / {{ next.target }}
                                </div>
                            </li>
                        </ul>
                    </div>

                    <div>
                        <h3 class="mb-2 text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                            Earned ({{ achievements.badges.length }})
                        </h3>
                        <EmptyState
                            v-if="achievements.badges.length === 0"
                            compact
                            :icon="Trophy"
                            title="No achievements yet"
                            description="Play games, grow your collection, and enter tournaments to earn badges."
                        />
                        <template v-else>
                            <div class="flex flex-wrap gap-2">
                                <div
                                    v-for="badge in visibleBadges"
                                    :key="badge.label"
                                    class="flex items-center gap-1.5 rounded-full border bg-card px-3 py-1.5 text-xs shadow-sm"
                                    :title="badge.description"
                                >
                                    <component :is="badgeIcon(badge.icon)" class="size-3.5 text-primary" />
                                    <span class="font-medium">{{ badge.label }}</span>
                                </div>
                            </div>
                            <Button
                                v-if="achievements.badges.length > BADGE_PREVIEW"
                                variant="ghost"
                                size="sm"
                                class="mt-2"
                                @click="showAllBadges = !showAllBadges"
                            >
                                {{ showAllBadges ? 'Show fewer' : `Show all ${achievements.badges.length}` }}
                            </Button>
                        </template>
                    </div>
                </CardContent>
            </Card>
        </section>

        <Card v-if="!props.is_supporter">
            <CardContent class="flex flex-col items-center justify-between gap-3 p-4 sm:flex-row">
                <div class="flex items-center gap-3">
                    <Gift class="size-6 shrink-0 text-muted-foreground" />
                    <div>
                        <div class="text-sm font-semibold">Support BiggerHat</div>
                        <div class="text-xs text-muted-foreground">Help keep the site running and get a Supporter badge.</div>
                    </div>
                </div>
                <Button as="a" href="https://ko-fi.com/biggerhat" target="_blank" rel="noopener noreferrer" variant="outline" size="sm">
                    Donate on Ko-fi
                </Button>
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
