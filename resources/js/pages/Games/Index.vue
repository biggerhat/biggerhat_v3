<script setup lang="ts">
import EmptyState from '@/components/EmptyState.vue';
import FactionLogo from '@/components/FactionLogo.vue';
import PageBanner from '@/components/PageBanner.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { CARD_HOVER, CARD_HOVER_GROUP } from '@/lib/cardHover';
import { type SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { BarChart3, ChevronRight, Eye, Plus, Swords, Trash2, Trophy } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface GamePlayer {
    id: number;
    slot: number;
    faction: string | null;
    role: string | null;
    total_points: number;
    opponent_name: string | null;
    user: { id: number; name: string } | null;
}

interface Game {
    id: number;
    uuid: string;
    status: string;
    is_solo: boolean;
    /** 'standard' | 'bonanza_brawl' — drives the format badge in the lists. */
    format: string;
    encounter_size: number;
    season: string;
    current_turn: number;
    strategy: { id: number; name: string } | null;
    players: GamePlayer[];
    winner: { id: number; name: string } | null;
    created_at: string;
    completed_at: string | null;
}

defineProps<{
    active_games: Game[];
    recent_games: Game[];
    observable_games: Game[];
}>();

const page = usePage<SharedData>();
const isLoggedIn = computed(() => !!page.props.auth.user);

const deleteDialogOpen = ref(false);
const gameToDelete = ref<Game | null>(null);

const confirmDelete = (game: Game) => {
    gameToDelete.value = game;
    deleteDialogOpen.value = true;
};

const executeDelete = () => {
    if (!gameToDelete.value) return;
    router.delete(route('games.destroy', gameToDelete.value.uuid));
    deleteDialogOpen.value = false;
    gameToDelete.value = null;
};

const statusLabel = (status: string, isSolo: boolean = false): string =>
    ({
        setup: isSolo ? 'Setting Up' : 'Waiting for Opponent',
        faction_select: 'Selecting Factions',
        master_select: 'Selecting Masters',
        crew_select: 'Selecting Crews',
        scheme_select: 'Selecting Schemes',
        in_progress: 'In Progress',
        completed: 'Completed',
        abandoned: 'Abandoned',
    })[status] ?? status;

const statusColor = (status: string): string =>
    ({
        setup: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
        faction_select: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
        master_select: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
        crew_select: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
        scheme_select: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
        in_progress: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
        completed: 'bg-muted text-muted-foreground',
        abandoned: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
    })[status] ?? '';

const meId = computed(() => page.props.auth.user?.id ?? null);
const playerLabel = (p: GamePlayer) => p.user?.name ?? p.opponent_name ?? 'Opponent';

/** What to call a game: its name, else who it's against, so cards in a list are distinguishable. */
const gameTitle = (g: Game): string => {
    if (g.name) return g.name;
    if (g.is_solo) return 'Solo game';
    const mine = g.players.some((p) => p.user?.id === meId.value);
    if (mine) {
        const others = g.players.filter((p) => p.user?.id !== meId.value).map(playerLabel);
        return others.length ? `vs ${others.join(' & ')}` : 'Open game';
    }
    return g.players.map(playerLabel).join(' vs ') || 'Open game';
};

const showScores = (g: Game) => g.status === 'in_progress' || g.status === 'completed';

const formatDate = (dateStr: string) => {
    return new Date(dateStr).toLocaleDateString('en-US', { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' });
};
</script>

<template>
    <Head title="Game Tracker" />
    <div class="relative">
        <div
            class="pointer-events-none absolute inset-x-0 top-0 h-64 opacity-[0.07] dark:opacity-[0.12]"
            :style="{ background: 'radial-gradient(ellipse at top, hsl(var(--primary)) 0%, transparent 70%)' }"
        />

        <PageBanner title="Game Tracker" class="mb-2">
            <template #subtitle>
                <div class="my-auto flex items-center gap-2 px-2 py-0 text-xs text-muted-foreground md:py-2 md:text-sm md:text-foreground">
                    Track your Malifaux games in real time
                </div>
            </template>
            <template v-if="isLoggedIn" #actions>
                <div class="my-auto flex flex-wrap gap-2 px-2 pb-2 md:pb-0 md:pr-4">
                    <Button as-child size="sm" variant="outline">
                        <Link :href="route('stats.my')"><BarChart3 class="mr-1.5 size-4" /> My stats</Link>
                    </Button>
                    <Button as-child size="sm">
                        <Link :href="route('games.create')"><Plus class="mr-1.5 size-4" /> New game</Link>
                    </Button>
                </div>
            </template>
        </PageBanner>

        <div class="container mx-auto">
            <!-- Active Games -->
            <section v-if="isLoggedIn && active_games.length" class="mb-8" aria-labelledby="games-active">
                <h2 id="games-active" class="mb-3 text-lg font-semibold">Active games</h2>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div v-for="game in active_games" :key="game.id" class="group relative">
                        <Link
                            :href="route('games.show', game.uuid)"
                            class="block rounded-lg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                        >
                            <Card :class="['h-full', CARD_HOVER_GROUP]">
                                <CardContent class="flex h-full flex-col gap-2.5 p-4">
                                    <div class="flex items-center justify-between gap-2 pr-8">
                                        <Badge :class="['border-0 text-2xs', statusColor(game.status)]" variant="outline">
                                            {{ statusLabel(game.status, game.is_solo) }}
                                        </Badge>
                                        <span class="text-2xs tabular-nums text-muted-foreground">{{ formatDate(game.created_at) }}</span>
                                    </div>
                                    <div>
                                        <div class="flex flex-wrap items-center gap-2">
                                            <Swords class="size-4 shrink-0 text-muted-foreground" />
                                            <span class="text-base font-semibold leading-tight">{{ gameTitle(game) }}</span>
                                            <Badge
                                                v-if="game.format === 'bonanza_brawl'"
                                                variant="outline"
                                                class="border-purple-500/50 px-1.5 py-0 text-2xs text-purple-700 dark:text-purple-300"
                                            >
                                                Bonanza Brawl
                                            </Badge>
                                        </div>
                                        <div class="mt-0.5 text-xs text-muted-foreground">
                                            {{ game.encounter_size }}ss<template v-if="game.strategy"> · {{ game.strategy.name }}</template
                                            ><template v-if="game.status === 'in_progress'"> · Turn {{ game.current_turn }}</template>
                                        </div>
                                    </div>
                                    <div class="flex flex-wrap items-center gap-x-4 gap-y-1">
                                        <div v-for="player in game.players" :key="player.id" class="flex items-center gap-1.5">
                                            <FactionLogo v-if="player.faction" :faction="player.faction" class-name="size-4" />
                                            <span class="text-xs">{{ playerLabel(player) }}</span>
                                            <span v-if="showScores(game)" class="text-xs font-bold tabular-nums text-muted-foreground">{{
                                                player.total_points
                                            }}</span>
                                        </div>
                                        <span v-if="!game.is_solo && game.players.length < 2" class="text-xs italic text-muted-foreground"
                                            >Waiting for an opponent…</span
                                        >
                                        <Badge v-if="game.is_solo" variant="outline" class="px-1 py-0 text-2xs">Solo</Badge>
                                    </div>
                                    <div class="mt-auto flex justify-end text-xs font-medium">
                                        <span class="inline-flex items-center gap-0.5"
                                            >{{ game.status === 'in_progress' ? 'Resume' : 'Continue setup' }} <ChevronRight class="size-3.5"
                                        /></span>
                                    </div>
                                </CardContent>
                            </Card>
                        </Link>
                        <button
                            aria-label="Delete game"
                            class="absolute right-2 top-2 rounded-md p-1.5 text-destructive/70 transition-colors hover:bg-destructive/10 hover:text-destructive"
                            @click.prevent="confirmDelete(game)"
                        >
                            <Trash2 class="size-4" />
                        </button>
                    </div>
                </div>
            </section>

            <!-- Recent Games -->
            <section v-if="isLoggedIn && recent_games.length" aria-labelledby="games-recent">
                <h2 id="games-recent" class="mb-3 text-lg font-semibold">Recent games</h2>
                <ul class="space-y-2">
                    <li v-for="game in recent_games" :key="game.id" class="flex items-center gap-1">
                        <Link
                            :href="route('games.show', game.uuid)"
                            class="flex min-w-0 flex-1 items-center gap-3 rounded-lg border px-3 py-2.5 transition-all duration-200 ease-out hover:border-primary/30 hover:bg-muted/50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                        >
                            <Trophy v-if="game.status === 'completed' && !game.is_tie" class="size-4 shrink-0 text-amber-700 dark:text-amber-400" />
                            <Swords v-else class="size-4 shrink-0 text-muted-foreground" />
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-x-2 text-sm">
                                    <span v-if="game.name" class="font-semibold">{{ game.name }}</span>
                                    <span v-for="(player, idx) in game.players" :key="player.id">
                                        <span v-if="idx > 0" class="text-muted-foreground"> vs </span>
                                        <span :class="game.winner?.id === player.user?.id ? 'font-bold' : ''">{{ playerLabel(player) }}</span>
                                        <span class="tabular-nums text-muted-foreground"> ({{ player.total_points }})</span>
                                    </span>
                                    <Badge v-if="game.is_solo" variant="outline" class="px-1 py-0 text-2xs">Solo</Badge>
                                </div>
                                <div class="flex flex-wrap items-center gap-x-2 text-2xs text-muted-foreground">
                                    <span class="tabular-nums">{{ game.encounter_size }}ss</span>
                                    <span v-if="game.format === 'bonanza_brawl'" class="text-purple-700 dark:text-purple-300">· Bonanza Brawl</span>
                                    <span v-if="game.strategy">· {{ game.strategy.name }}</span>
                                    <span v-if="game.completed_at">· {{ formatDate(game.completed_at) }}</span>
                                </div>
                            </div>
                            <Badge :class="['shrink-0 border-0 text-2xs', statusColor(game.status)]" variant="outline">
                                {{ statusLabel(game.status, game.is_solo) }}
                            </Badge>
                        </Link>
                        <button
                            aria-label="Delete game"
                            class="shrink-0 rounded-md p-2 text-destructive/70 transition-colors hover:bg-destructive/10 hover:text-destructive"
                            @click="confirmDelete(game)"
                        >
                            <Trash2 class="size-4" />
                        </button>
                    </li>
                </ul>
            </section>

            <!-- Observable Games -->
            <section v-if="observable_games.length" aria-labelledby="games-public">
                <div class="my-6 border-t" />
                <h2 id="games-public" class="mb-1 flex items-center gap-2 text-lg font-semibold">
                    <Eye class="size-4 text-amber-700 dark:text-amber-400" />
                    Public games
                </h2>
                <p class="mb-3 text-xs text-muted-foreground">Games with spectating enabled from the last 24 hours</p>
                <div class="grid gap-3 sm:grid-cols-2">
                    <Link
                        v-for="game in observable_games"
                        :key="game.id"
                        :href="route('games.observe', game.uuid)"
                        class="block rounded-lg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                    >
                        <Card :class="['h-full', CARD_HOVER]">
                            <CardContent class="flex h-full flex-col gap-2.5 p-4">
                                <div class="flex items-center justify-between">
                                    <Badge :class="['border-0 text-2xs', statusColor(game.status)]" variant="outline">
                                        {{ statusLabel(game.status, game.is_solo) }}
                                    </Badge>
                                    <Badge
                                        v-if="game.status !== 'completed'"
                                        variant="outline"
                                        class="border-amber-500/50 text-2xs text-amber-800 dark:text-amber-400"
                                    >
                                        <Eye class="mr-1 size-2.5" /> Watch live
                                    </Badge>
                                </div>
                                <div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <Swords class="size-4 shrink-0 text-muted-foreground" />
                                        <span class="text-sm font-semibold">{{ gameTitle(game) }}</span>
                                        <Badge
                                            v-if="game.format === 'bonanza_brawl'"
                                            variant="outline"
                                            class="border-purple-500/50 px-1.5 py-0 text-2xs text-purple-700 dark:text-purple-300"
                                        >
                                            Bonanza Brawl
                                        </Badge>
                                    </div>
                                    <div class="mt-0.5 text-xs text-muted-foreground">
                                        {{ game.encounter_size }}ss<template v-if="game.strategy"> · {{ game.strategy.name }}</template
                                        ><template v-if="game.status === 'in_progress'"> · Turn {{ game.current_turn }}</template>
                                    </div>
                                </div>
                                <div v-if="game.status === 'completed' && game.winner" class="text-xs font-medium text-amber-700 dark:text-amber-400">
                                    {{ game.winner.name }} wins!
                                </div>
                                <div class="flex flex-wrap items-center gap-x-4 gap-y-1">
                                    <div v-for="player in game.players" :key="player.id" class="flex items-center gap-1.5">
                                        <FactionLogo v-if="player.faction" :faction="player.faction" class-name="size-4" />
                                        <span class="text-xs" :class="game.winner?.id === player.user?.id ? 'font-bold' : ''">{{
                                            playerLabel(player)
                                        }}</span>
                                        <span v-if="showScores(game)" class="text-xs font-bold tabular-nums text-muted-foreground">{{
                                            player.total_points
                                        }}</span>
                                    </div>
                                    <Badge v-if="game.is_solo" variant="outline" class="px-1 py-0 text-2xs">Solo</Badge>
                                </div>
                            </CardContent>
                        </Card>
                    </Link>
                </div>
            </section>

            <!-- Empty state -->
            <EmptyState
                v-if="isLoggedIn && !active_games.length && !recent_games.length"
                :icon="Swords"
                title="No games yet"
                description="Create your first game to start tracking a match in real time."
            >
                <template #action>
                    <Link :href="route('games.create')">
                        <Button>
                            <Plus class="mr-2 size-4" />
                            Create Game
                        </Button>
                    </Link>
                </template>
            </EmptyState>
        </div>
    </div>

    <!-- Delete Confirmation Dialog -->
    <Dialog v-model:open="deleteDialogOpen">
        <DialogContent class="max-w-sm">
            <DialogHeader>
                <DialogTitle>Delete Game</DialogTitle>
                <DialogDescription>
                    Are you sure you want to remove "{{ gameToDelete?.name || (gameToDelete?.encounter_size ?? '') + 'ss Encounter' }}" from your game
                    list?
                    <span v-if="!gameToDelete?.is_solo && (gameToDelete?.players?.length ?? 0) > 1" class="mt-1 block text-xs"
                        >Your opponent will still be able to see this game.</span
                    >
                </DialogDescription>
            </DialogHeader>
            <DialogFooter class="gap-2 sm:gap-0">
                <Button variant="outline" @click="deleteDialogOpen = false">Cancel</Button>
                <Button variant="destructive" @click="executeDelete">Remove</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
