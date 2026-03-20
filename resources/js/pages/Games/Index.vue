<script setup lang="ts">
import FactionLogo from '@/components/FactionLogo.vue';
import PageBanner from '@/components/PageBanner.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Head, Link, router } from '@inertiajs/vue3';
import { Plus, Swords, Trash2, Trophy } from 'lucide-vue-next';
import { ref } from 'vue';

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
}>();

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
                <div class="my-auto px-2 py-0 text-xs text-muted-foreground md:py-2 md:text-sm md:text-foreground">
                    Track your Malifaux games in real time
                </div>
            </template>
        </PageBanner>

        <div class="container mx-auto sm:px-4">
            <!-- Create Game CTA -->
            <Link :href="route('games.create')" class="group mb-6 block">
                <Card class="transition-all duration-200 hover:-translate-y-0.5 hover:shadow-lg">
                    <CardContent class="flex items-center gap-4 p-5">
                        <div
                            class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary transition-colors group-hover:bg-primary group-hover:text-primary-foreground"
                        >
                            <Plus class="size-6" />
                        </div>
                        <div>
                            <p class="font-semibold">Create New Game</p>
                            <p class="text-sm text-muted-foreground">Set up a new encounter and invite an opponent</p>
                        </div>
                    </CardContent>
                </Card>
            </Link>

            <!-- Active Games -->
            <div v-if="active_games.length" class="mb-8">
                <h2 class="mb-3 font-semibold">Active Games</h2>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div v-for="game in active_games" :key="game.id" class="group relative">
                        <Link :href="route('games.show', game.uuid)">
                            <Card class="h-full transition-all duration-200 group-hover:-translate-y-0.5 group-hover:shadow-md">
                                <CardContent class="p-4">
                                    <div class="mb-2 flex items-center justify-between">
                                        <Badge :class="['border-0 text-[10px]', statusColor(game.status)]" variant="outline">
                                            {{ statusLabel(game.status, game.is_solo) }}
                                        </Badge>
                                        <span class="text-[11px] text-muted-foreground">{{ formatDate(game.created_at) }}</span>
                                    </div>
                                    <div class="mb-2 flex items-center gap-2">
                                        <Swords class="size-4 text-muted-foreground" />
                                        <span class="text-sm font-medium">{{ game.name || game.encounter_size + 'ss' }}</span>
                                        <span v-if="game.strategy" class="text-xs text-muted-foreground">{{ game.strategy.name }}</span>
                                    </div>
                                    <div v-if="game.status === 'in_progress'" class="mb-2 text-xs text-muted-foreground">
                                        Turn {{ game.current_turn }}
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <div v-for="player in game.players" :key="player.id" class="flex items-center gap-1.5">
                                            <FactionLogo v-if="player.faction" :faction="player.faction" class-name="size-4" />
                                            <span class="text-xs">{{ player.user?.name ?? player.opponent_name ?? 'Opponent' }}</span>
                                            <Badge v-if="player.role" variant="outline" class="px-1 py-0 text-[9px] capitalize">
                                                {{ player.role }}
                                            </Badge>
                                        </div>
                                        <span v-if="!game.is_solo && game.players.length < 2" class="text-xs italic text-muted-foreground">Waiting for opponent...</span>
                                        <Badge v-if="game.is_solo" variant="outline" class="px-1 py-0 text-[9px]">Solo</Badge>
                                    </div>
                                </CardContent>
                            </Card>
                        </Link>
                        <button
                            class="absolute bottom-3 right-3 rounded-md p-1 text-destructive/60 transition-colors hover:bg-destructive/10 hover:text-destructive"
                            @click.prevent="confirmDelete(game)"
                        >
                            <Trash2 class="size-3.5" />
                        </button>
                    </div>
                </div>
            </div>

            <!-- Recent Games -->
            <div v-if="recent_games.length">
                <h2 class="mb-3 font-semibold">Recent Games</h2>
                <div class="space-y-2">
                    <div v-for="game in recent_games" :key="game.id" class="group relative">
                        <Link
                            :href="route('games.show', game.uuid)"
                            class="flex items-center gap-3 rounded-lg border px-3 py-2.5 transition-all duration-200 hover:border-primary/30 hover:bg-muted/50"
                        >
                            <Trophy v-if="game.status === 'completed' && !game.is_tie" class="size-4 shrink-0 text-amber-500" />
                            <Swords v-else class="size-4 shrink-0 text-muted-foreground" />
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2 text-sm">
                                    <span v-for="(player, idx) in game.players" :key="player.id">
                                        <span v-if="idx > 0" class="text-muted-foreground"> vs </span>
                                        <span :class="game.winner?.id === player.user?.id ? 'font-bold' : ''">{{ player.user?.name ?? player.opponent_name ?? 'Opponent' }}</span>
                                        <span class="text-muted-foreground">({{ player.total_points }})</span>
                                    </span>
                                    <Badge v-if="game.is_solo" variant="outline" class="px-1 py-0 text-[9px]">Solo</Badge>
                                </div>
                                <div class="flex items-center gap-2 text-[11px] text-muted-foreground">
                                    <span>{{ game.encounter_size }}ss</span>
                                    <span v-if="game.strategy">&middot; {{ game.strategy.name }}</span>
                                    <span v-if="game.completed_at">&middot; {{ formatDate(game.completed_at) }}</span>
                                </div>
                            </div>
                            <Badge :class="['border-0 text-[10px]', statusColor(game.status)]" variant="outline">
                                {{ statusLabel(game.status, game.is_solo) }}
                            </Badge>
                        </Link>
                        <button
                            class="absolute bottom-2 right-2 rounded-md p-1 text-destructive/60 transition-colors hover:bg-destructive/10 hover:text-destructive"
                            @click="confirmDelete(game)"
                        >
                            <Trash2 class="size-3.5" />
                        </button>
                    </div>
                </div>
            </div>

            <!-- Empty state -->
            <div v-if="!active_games.length && !recent_games.length" class="py-12 text-center">
                <Swords class="mx-auto mb-4 size-12 text-muted-foreground/30" />
                <p class="mb-2 text-lg font-semibold">No games yet</p>
                <p class="mb-4 text-sm text-muted-foreground">Create your first game to get started</p>
                <Link :href="route('games.create')">
                    <Button>
                        <Plus class="mr-2 size-4" />
                        Create Game
                    </Button>
                </Link>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Dialog -->
    <Dialog v-model:open="deleteDialogOpen">
        <DialogContent class="max-w-sm">
            <DialogHeader>
                <DialogTitle>Delete Game</DialogTitle>
                <DialogDescription>
                    Are you sure you want to remove "{{ gameToDelete?.name || (gameToDelete?.encounter_size ?? '') + 'ss Encounter' }}" from your game list?
                    <span v-if="!gameToDelete?.is_solo && (gameToDelete?.players?.length ?? 0) > 1" class="mt-1 block text-xs">Your opponent will still be able to see this game.</span>
                </DialogDescription>
            </DialogHeader>
            <DialogFooter class="gap-2 sm:gap-0">
                <Button variant="outline" @click="deleteDialogOpen = false">Cancel</Button>
                <Button variant="destructive" @click="executeDelete">Remove</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
