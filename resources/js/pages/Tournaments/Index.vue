<script setup lang="ts">
import PageBanner from '@/components/PageBanner.vue';
import { useTournamentStatus } from '@/composables/useTournamentStatus';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { CalendarDays, MapPin, Plus, Trophy, Users } from 'lucide-vue-next';
import { computed } from 'vue';

interface TournamentSummary {
    id: number;
    uuid: string;
    name: string;
    event_date: string;
    status: string;
    location: string | null;
    encounter_size: number;
    encounter_type: string;
    planned_rounds: number;
    season_label: string;
    players_count: number;
    creator: { id: number; name: string };
}

defineProps<{
    my_tournaments: TournamentSummary[];
    public_tournaments: TournamentSummary[];
}>();

const page = usePage<SharedData>();
const isLoggedIn = computed(() => !!page.props.auth.user);
const canCreateTournaments = computed(() => (page.props.auth.permissions ?? []).includes('create_tournaments'));

const { statusColor, publicStatusLabel: statusLabel } = useTournamentStatus();

const formatDate = (dateStr: string) => {
    const d = dateStr.includes('T') ? new Date(dateStr) : new Date(dateStr + 'T00:00:00');
    return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
};
</script>

<template>
    <Head title="Tournaments" />
    <div class="relative">
        <div
            class="pointer-events-none absolute inset-x-0 top-0 h-64 opacity-[0.07] dark:opacity-[0.12]"
            :style="{ background: 'radial-gradient(ellipse at top, hsl(var(--primary)) 0%, transparent 70%)' }"
        />

        <PageBanner title="Tournaments" class="mb-2">
            <template #subtitle>
                <div class="my-auto flex items-center gap-2 px-2 py-0 text-xs text-muted-foreground md:py-2 md:text-sm md:text-foreground">
                    Organize and track Gaining Grounds events
                    <Badge class="border-amber-500/60 bg-amber-500/10 px-1.5 py-0 text-[9px] font-bold text-amber-600 dark:text-amber-400">Beta</Badge>
                </div>
            </template>
        </PageBanner>

        <div class="container mx-auto sm:px-4">
            <!-- Create Tournament CTA -->
            <Link v-if="canCreateTournaments" :href="route('tournaments.create')" class="group mb-6 block">
                <Card class="transition-all duration-200 hover:-translate-y-0.5 hover:shadow-lg">
                    <CardContent class="flex items-center gap-4 p-5">
                        <div class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary transition-colors group-hover:bg-primary group-hover:text-primary-foreground">
                            <Plus class="size-6" />
                        </div>
                        <div>
                            <p class="font-semibold">Create Tournament</p>
                            <p class="text-sm text-muted-foreground">Set up a new Gaining Grounds event</p>
                        </div>
                    </CardContent>
                </Card>
            </Link>

            <!-- My Tournaments -->
            <div v-if="isLoggedIn && my_tournaments.length" class="mb-8">
                <h2 class="mb-3 font-semibold">My Tournaments</h2>
                <div class="grid gap-3 sm:grid-cols-2">
                    <Link v-for="t in my_tournaments" :key="t.id" :href="route('tournaments.manage', t.uuid)">
                        <Card class="h-full transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md">
                            <CardContent class="p-4">
                                <div class="mb-2 flex items-center justify-between">
                                    <Badge :class="['border-0 text-[10px]', statusColor(t.status)]" variant="outline">{{ statusLabel(t.status) }}</Badge>
                                    <span class="text-[11px] text-muted-foreground">{{ t.encounter_size }}ss {{ t.encounter_type === 'traditional' ? '' : t.encounter_type?.replace('_', ' ') }}</span>
                                </div>
                                <div class="mb-2 flex items-center gap-2">
                                    <Trophy class="size-4 text-muted-foreground" />
                                    <span class="text-sm font-medium">{{ t.name }}</span>
                                </div>
                                <div class="flex flex-wrap gap-x-3 gap-y-1 text-[11px] text-muted-foreground">
                                    <span class="flex items-center gap-1"><CalendarDays class="size-3" /> {{ formatDate(t.event_date) }}</span>
                                    <span v-if="t.location" class="flex items-center gap-1"><MapPin class="size-3" /> {{ t.location }}</span>
                                    <span class="flex items-center gap-1"><Users class="size-3" /> {{ t.players_count }} players</span>
                                    <span>{{ t.planned_rounds }} rounds</span>
                                </div>
                            </CardContent>
                        </Card>
                    </Link>
                </div>
            </div>

            <!-- Public Tournaments -->
            <div v-if="public_tournaments.length">
                <h2 class="mb-3 font-semibold">Public Tournaments</h2>
                <div class="grid gap-3 sm:grid-cols-2">
                    <Link v-for="t in public_tournaments" :key="'pub-' + t.id" :href="route('tournaments.view', t.uuid)">
                        <Card class="h-full transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md">
                            <CardContent class="p-4">
                                <div class="mb-2 flex items-center justify-between">
                                    <Badge :class="['border-0 text-[10px]', statusColor(t.status)]" variant="outline">{{ statusLabel(t.status) }}</Badge>
                                    <span class="text-[11px] text-muted-foreground">{{ t.encounter_size }}ss {{ t.encounter_type === 'traditional' ? '' : t.encounter_type?.replace('_', ' ') }}</span>
                                </div>
                                <div class="mb-2 flex items-center gap-2">
                                    <Trophy class="size-4 text-muted-foreground" />
                                    <span class="text-sm font-medium">{{ t.name }}</span>
                                </div>
                                <div class="flex flex-wrap gap-x-3 gap-y-1 text-[11px] text-muted-foreground">
                                    <span class="flex items-center gap-1"><CalendarDays class="size-3" /> {{ formatDate(t.event_date) }}</span>
                                    <span v-if="t.location" class="flex items-center gap-1"><MapPin class="size-3" /> {{ t.location }}</span>
                                    <span class="flex items-center gap-1"><Users class="size-3" /> {{ t.players_count }} players</span>
                                    <span>by {{ t.creator.name }}</span>
                                </div>
                            </CardContent>
                        </Card>
                    </Link>
                </div>
            </div>

            <!-- Empty state -->
            <div v-if="!my_tournaments.length && !public_tournaments.length" class="py-12 text-center">
                <Trophy class="mx-auto mb-4 size-12 text-muted-foreground/30" />
                <p class="mb-2 text-lg font-semibold">No tournaments yet</p>
                <p class="mb-4 text-sm text-muted-foreground">Create your first tournament to get started</p>
                <Link v-if="canCreateTournaments" :href="route('tournaments.create')">
                    <Button><Plus class="mr-2 size-4" /> Create Tournament</Button>
                </Link>
                <p v-else-if="isLoggedIn" class="text-xs italic text-muted-foreground">
                    Tournament creation is restricted. Ask an admin for the Tournament Organizer role.
                </p>
            </div>
        </div>
    </div>
</template>
