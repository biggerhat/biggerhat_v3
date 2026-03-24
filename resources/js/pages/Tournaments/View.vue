<script setup lang="ts">
import FactionLogo from '@/components/FactionLogo.vue';
import PageBanner from '@/components/PageBanner.vue';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { Drawer, DrawerClose, DrawerContent, DrawerFooter, DrawerHeader, DrawerTitle } from '@/components/ui/drawer';
import { Button } from '@/components/ui/button';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Head } from '@inertiajs/vue3';
import { CalendarDays, Crown, MapPin, Trophy } from 'lucide-vue-next';
import { ref } from 'vue';

interface TournamentPlayer {
    id: number;
    display_name: string;
    faction: string | null;
    user: { id: number; name: string } | null;
    is_ringer: boolean;
    is_disqualified: boolean;
    dropped_after_round: number | null;
}

interface ViewGame {
    id: number;
    player_one_id: number;
    player_two_id: number | null;
    player_one: TournamentPlayer;
    player_two: TournamentPlayer | null;
    player_one_faction: string | null;
    player_one_master: string | null;
    player_one_title: string | null;
    player_one_vp: number | null;
    player_two_faction: string | null;
    player_two_master: string | null;
    player_two_title: string | null;
    player_two_vp: number | null;
    is_bye: boolean;
    is_forfeit: boolean;
    result: string;
    table_number: number | null;
}

interface ViewRound {
    id: number;
    round_number: number;
    status: string;
    deployment: { value: string; label: string; description: string; image_url: string | null } | null;
    strategy: { id: number; name: string; image_url: string | null } | null;
    schemes: { id: number; name: string; image_url: string | null; prerequisite: string | null; reveal: string | null; scoring: string | null }[];
    games: ViewGame[];
}

interface StandingEntry {
    rank: number | null;
    player_id: number;
    display_name: string;
    faction: string | null;
    is_ringer: boolean;
    is_dropped: boolean;
    total_tp: number;
    total_diff: number;
    total_vp: number;
    rounds_played: number;
}

interface FactionInfo {
    name: string;
    slug: string;
    color: string;
    logo: string;
}

const props = defineProps<{
    tournament: {
        uuid: string;
        name: string;
        description: string | null;
        status: string;
        encounter_size: number;
        planned_rounds: number;
        season_label: string;
        event_date: string;
        location: string | null;
        round_time_limit: number;
        players: TournamentPlayer[];
        organizers: { id: number; name: string }[];
    };
    rounds: ViewRound[];
    standings: StandingEntry[];
    factions: Record<string, FactionInfo>;
}>();

const activeRound = ref(String(props.rounds.length > 0 ? props.rounds[props.rounds.length - 1].round_number : 1));

const factionBackground = (faction: string | null): string => {
    if (!faction) return '';
    switch (faction.toLowerCase()) {
        case 'explorers_society': return 'bg-explorerssociety';
        case 'ten_thunders': return 'bg-tenthunders';
        default: return `bg-${faction}`;
    }
};

const playerName = (id: number | null): string => {
    if (!id) return 'BYE';
    return props.tournament.players.find((p) => p.id === id)?.display_name ?? 'Unknown';
};

const playerFaction = (id: number | null): string | null => {
    if (!id) return null;
    return props.tournament.players.find((p) => p.id === id)?.faction ?? null;
};

const statusColor = (status: string): string =>
    ({
        draft: 'bg-muted text-muted-foreground',
        registration: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
        active: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
        completed: 'bg-muted text-muted-foreground',
    })[status] ?? '';

const statusLabel = (status: string): string =>
    ({ draft: 'Draft', registration: 'Registration', active: 'In Progress', completed: 'Completed' })[status] ?? status;

const formatDate = (d: string) => {
    const date = d.includes('T') ? new Date(d) : new Date(d + 'T00:00:00');
    return date.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
};

// Card preview drawer
const drawerOpen = ref(false);
const drawerTitle = ref('');
const drawerImage = ref<string | null>(null);
const drawerDescription = ref('');

const openCard = (title: string, image: string | null, description?: string) => {
    drawerTitle.value = title;
    drawerImage.value = image;
    drawerDescription.value = description ?? '';
    drawerOpen.value = true;
};
</script>

<template>
    <Head :title="tournament.name" />
    <div class="relative">
        <div
            class="pointer-events-none absolute inset-x-0 top-0 h-64 opacity-[0.07] dark:opacity-[0.12]"
            :style="{ background: 'radial-gradient(ellipse at top, hsl(var(--primary)) 0%, transparent 70%)' }"
        />

        <PageBanner :title="tournament.name" class="mb-2">
            <template #subtitle>
                <div class="my-auto flex flex-wrap items-center gap-2 px-2 py-0 text-xs text-muted-foreground md:py-2 md:text-sm">
                    <Badge :class="['border-0 text-[10px]', statusColor(tournament.status)]" variant="outline">{{ statusLabel(tournament.status) }}</Badge>
                    <span class="flex items-center gap-1"><CalendarDays class="size-3" /> {{ formatDate(tournament.event_date) }}</span>
                    <span v-if="tournament.location" class="flex items-center gap-1"><MapPin class="size-3" /> {{ tournament.location }}</span>
                    <span>{{ tournament.encounter_size }}ss</span>
                    <span>{{ tournament.planned_rounds }} rounds</span>
                    <span>{{ tournament.season_label }}</span>
                </div>
            </template>
        </PageBanner>

        <div class="container mx-auto pb-8 sm:px-4">
            <!-- Description -->
            <p v-if="tournament.description" class="mb-4 text-sm text-muted-foreground">{{ tournament.description }}</p>

            <!-- Standings -->
            <Card class="mb-6">
                <CardContent class="p-0">
                    <div class="px-4 py-3">
                        <h2 class="flex items-center gap-2 font-semibold"><Trophy class="size-4" /> Standings</h2>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-t text-left text-xs text-muted-foreground">
                                    <th class="px-2 py-2 font-medium sm:px-4">#</th>
                                    <th class="px-2 py-2 font-medium sm:px-4">Player</th>
                                    <th class="px-1 py-2 text-center font-medium sm:px-3">TP</th>
                                    <th class="px-1 py-2 text-center font-medium sm:px-3">DIFF</th>
                                    <th class="px-1 py-2 text-center font-medium sm:px-3">VP</th>
                                    <th class="hidden px-3 py-2 text-center font-medium sm:table-cell">Played</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="!standings.length">
                                    <td colspan="6" class="px-4 py-8 text-center text-muted-foreground">No results yet</td>
                                </tr>
                                <tr
                                    v-for="entry in standings"
                                    :key="entry.player_id"
                                    :class="[
                                        factionBackground(entry.faction),
                                        entry.faction ? 'text-white' : 'border-b last:border-0',
                                        entry.is_ringer ? 'opacity-50' : '',
                                    ]"
                                >
                                    <td class="px-2 py-2 font-bold sm:px-4">{{ entry.rank ?? '-' }}</td>
                                    <td class="px-2 py-2 sm:px-4">
                                        <div class="flex items-center gap-1.5">
                                            <FactionLogo v-if="entry.faction" :faction="entry.faction" class-name="size-4 shrink-0 drop-shadow-[0_1px_2px_rgba(0,0,0,0.5)]" />
                                            <Crown v-if="entry.rank === 1 && !entry.is_ringer" class="size-3.5 shrink-0 text-amber-300 drop-shadow-[0_1px_2px_rgba(0,0,0,0.5)]" />
                                            <span class="truncate text-xs font-medium sm:text-sm">{{ entry.display_name }}</span>
                                            <Badge v-if="entry.is_ringer" variant="outline" class="hidden shrink-0 border-white/40 px-1 py-0 text-[9px] text-white/80 sm:inline-flex">Ringer</Badge>
                                            <Badge v-if="entry.is_dropped" variant="outline" class="hidden shrink-0 border-white/40 px-1 py-0 text-[9px] text-white/60 sm:inline-flex">Dropped</Badge>
                                        </div>
                                    </td>
                                    <td class="px-1 py-2 text-center font-bold sm:px-3">{{ entry.total_tp }}</td>
                                    <td class="px-1 py-2 text-center font-medium sm:px-3">{{ entry.total_diff > 0 ? '+' : '' }}{{ entry.total_diff }}</td>
                                    <td class="px-1 py-2 text-center sm:px-3">{{ entry.total_vp }}</td>
                                    <td class="hidden px-3 py-2 text-center opacity-70 sm:table-cell">{{ entry.rounds_played }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>

            <!-- Rounds -->
            <div v-if="rounds.length">
                <h2 class="mb-3 font-semibold">Rounds</h2>
                <Tabs v-model="activeRound">
                    <!-- grid-cols-1 grid-cols-2 grid-cols-3 grid-cols-4 grid-cols-5 grid-cols-6 grid-cols-7 -->
                    <TabsList class="mb-4 grid w-full" :style="{ gridTemplateColumns: `repeat(${Math.min(rounds.length, 7)}, minmax(0, 1fr))` }">
                        <TabsTrigger v-for="round in rounds" :key="round.id" :value="String(round.round_number)" class="text-xs sm:text-sm">
                            R{{ round.round_number }}
                        </TabsTrigger>
                    </TabsList>

                    <TabsContent v-for="round in rounds" :key="'content-' + round.id" :value="String(round.round_number)">
                        <!-- Scenario -->
                        <div v-if="round.deployment || round.strategy || round.schemes.length" class="mb-6">
                            <!-- Deployment + Strategy -->
                            <div class="mb-4 grid gap-4 sm:grid-cols-2">
                                <!-- Deployment -->
                                <Card v-if="round.deployment" class="cursor-pointer overflow-hidden transition-shadow hover:shadow-md" @click="openCard(round.deployment.label, round.deployment.image_url, round.deployment.description)">
                                    <img v-if="round.deployment.image_url" :src="round.deployment.image_url" :alt="round.deployment.label" class="w-full object-cover" loading="lazy" decoding="async" />
                                    <CardContent class="p-3">
                                        <div class="text-[10px] uppercase text-muted-foreground">Deployment</div>
                                        <div class="text-sm font-medium">{{ round.deployment.label }}</div>
                                    </CardContent>
                                </Card>

                                <!-- Strategy -->
                                <Card v-if="round.strategy" class="cursor-pointer overflow-hidden transition-shadow hover:shadow-md" @click="openCard(round.strategy.name, round.strategy.image_url)">
                                    <img v-if="round.strategy.image_url" :src="round.strategy.image_url" :alt="round.strategy.name" class="w-full object-cover" loading="lazy" decoding="async" />
                                    <CardContent class="p-3">
                                        <div class="text-[10px] uppercase text-muted-foreground">Strategy</div>
                                        <div class="text-sm font-medium">{{ round.strategy.name }}</div>
                                    </CardContent>
                                </Card>
                            </div>

                            <!-- Schemes -->
                            <div v-if="round.schemes.length" class="mb-4">
                                <div class="mb-2 text-[10px] uppercase text-muted-foreground">Scheme Pool</div>
                                <div class="grid grid-cols-3 gap-3">
                                    <Card
                                        v-for="scheme in round.schemes"
                                        :key="scheme.id"
                                        class="cursor-pointer overflow-hidden transition-shadow hover:shadow-md"
                                        @click="openCard(scheme.name, scheme.image_url, [scheme.prerequisite ? 'Prerequisite: ' + scheme.prerequisite : '', scheme.reveal ? 'Reveal: ' + scheme.reveal : '', scheme.scoring ? 'Scoring: ' + scheme.scoring : ''].filter(Boolean).join('\n\n'))"
                                    >
                                        <img v-if="scheme.image_url" :src="scheme.image_url" :alt="scheme.name" class="w-full" loading="lazy" decoding="async" />
                                        <CardContent v-else class="p-3">
                                            <div class="text-sm font-medium">{{ scheme.name }}</div>
                                        </CardContent>
                                    </Card>
                                </div>
                            </div>
                        </div>
                        <div v-else class="mb-6 py-6 text-center text-sm text-muted-foreground">Scenario not yet announced</div>

                        <!-- Games -->
                        <div v-if="round.games.length">
                            <div class="mb-2 text-[10px] uppercase text-muted-foreground">Results</div>
                            <div class="grid gap-2 sm:grid-cols-2">
                                <Card v-for="game in round.games" :key="game.id">
                                    <CardContent class="p-3">
                                        <div v-if="game.is_bye" class="flex items-center justify-between">
                                            <div class="flex items-center gap-1.5">
                                                <FactionLogo v-if="playerFaction(game.player_one_id)" :faction="playerFaction(game.player_one_id)!" class-name="size-4" />
                                                <span class="text-sm font-medium">{{ playerName(game.player_one_id) }}</span>
                                            </div>
                                            <Badge variant="outline" class="text-[9px]">BYE</Badge>
                                        </div>
                                        <div v-else>
                                            <div class="flex items-center justify-between">
                                                <div class="flex min-w-0 items-center gap-1.5">
                                                    <FactionLogo v-if="game.player_one_faction || playerFaction(game.player_one_id)" :faction="(game.player_one_faction || playerFaction(game.player_one_id))!" class-name="size-4 shrink-0" />
                                                    <div class="min-w-0">
                                                        <div class="truncate text-xs font-medium sm:text-sm">{{ playerName(game.player_one_id) }}</div>
                                                        <div v-if="game.player_one_master" class="truncate text-[10px] text-muted-foreground">{{ game.player_one_title || game.player_one_master }}</div>
                                                    </div>
                                                </div>
                                                <span class="shrink-0 text-sm font-bold" :class="(game.player_one_vp ?? 0) > (game.player_two_vp ?? 0) ? 'text-green-600 dark:text-green-400' : ''">{{ game.player_one_vp ?? '-' }}</span>
                                            </div>
                                            <div class="my-1 border-t" />
                                            <div class="flex items-center justify-between">
                                                <div class="flex min-w-0 items-center gap-1.5">
                                                    <FactionLogo v-if="game.player_two_faction || playerFaction(game.player_two_id)" :faction="(game.player_two_faction || playerFaction(game.player_two_id))!" class-name="size-4 shrink-0" />
                                                    <div class="min-w-0">
                                                        <div class="truncate text-xs font-medium sm:text-sm">{{ playerName(game.player_two_id) }}</div>
                                                        <div v-if="game.player_two_master" class="truncate text-[10px] text-muted-foreground">{{ game.player_two_title || game.player_two_master }}</div>
                                                    </div>
                                                </div>
                                                <span class="shrink-0 text-sm font-bold" :class="(game.player_two_vp ?? 0) > (game.player_one_vp ?? 0) ? 'text-green-600 dark:text-green-400' : ''">{{ game.player_two_vp ?? '-' }}</span>
                                            </div>
                                            <Badge v-if="game.is_forfeit" variant="destructive" class="mt-1 px-1 py-0 text-[9px]">Forfeit</Badge>
                                        </div>
                                    </CardContent>
                                </Card>
                            </div>
                        </div>
                        <div v-else class="py-4 text-center text-sm text-muted-foreground">No games played yet</div>
                    </TabsContent>
                </Tabs>
            </div>

            <!-- Organizers -->
            <div v-if="tournament.organizers.length" class="mt-6 text-xs text-muted-foreground">
                <span class="font-medium">Organized by:</span>
                {{ tournament.organizers.map((o) => o.name).join(', ') }}
            </div>
        </div>
    </div>

    <!-- Card Preview Drawer -->
    <Drawer v-model:open="drawerOpen">
        <DrawerContent>
            <div class="mx-auto w-full max-w-sm">
                <DrawerHeader class="pb-2">
                    <DrawerTitle class="text-center">{{ drawerTitle }}</DrawerTitle>
                </DrawerHeader>
                <div class="px-4 pb-4">
                    <div v-if="drawerImage" class="mb-3 flex justify-center">
                        <img :src="drawerImage" :alt="drawerTitle" class="max-h-[60dvh] w-auto rounded-lg" loading="lazy" decoding="async" />
                    </div>
                    <p v-if="drawerDescription" class="whitespace-pre-line text-sm leading-relaxed text-muted-foreground">{{ drawerDescription }}</p>
                </div>
                <DrawerFooter class="shrink-0 pt-2">
                    <DrawerClose as-child>
                        <Button variant="outline">Close</Button>
                    </DrawerClose>
                </DrawerFooter>
            </div>
        </DrawerContent>
    </Drawer>
</template>
