<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { useTournamentStatus } from '@/composables/useTournamentStatus';
import { CARD_HOVER } from '@/lib/cardHover';
import { formatDateOnly } from '@/lib/utils';
import { Link } from '@inertiajs/vue3';
import { CalendarDays, MapPin, Trash2, Trophy, Users } from 'lucide-vue-next';

export interface TournamentSummary {
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
    tournament: TournamentSummary;
    href: string;
    /** Show "N rounds" (organizer's own events) or "by Creator" (public ones). */
    meta: 'rounds' | 'creator';
    deletable?: boolean;
}>();

defineEmits<{ (e: 'delete', t: TournamentSummary): void }>();

const { statusColor, publicStatusLabel: statusLabel } = useTournamentStatus();
</script>

<template>
    <Link :href="href" class="block rounded-lg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2">
        <Card :class="['h-full', CARD_HOVER]">
            <CardContent class="p-4">
                <div class="mb-2 flex items-center justify-between">
                    <Badge :class="['border-0 text-2xs', statusColor(tournament.status)]" variant="outline">{{
                        statusLabel(tournament.status)
                    }}</Badge>
                    <div class="flex items-center gap-2">
                        <span class="text-2xs tabular-nums text-muted-foreground"
                            >{{ tournament.encounter_size }}ss
                            {{ tournament.encounter_type === 'traditional' ? '' : tournament.encounter_type?.replace('_', ' ') }}</span
                        >
                        <button
                            v-if="deletable"
                            type="button"
                            class="rounded p-1 text-muted-foreground transition-colors hover:bg-destructive/10 hover:text-destructive"
                            :title="`Delete ${tournament.name}`"
                            :aria-label="`Delete ${tournament.name}`"
                            @click.stop.prevent="$emit('delete', tournament)"
                        >
                            <Trash2 class="size-3.5" />
                        </button>
                    </div>
                </div>
                <div class="mb-2 flex items-center gap-2">
                    <Trophy class="size-4 text-muted-foreground" />
                    <span class="text-sm font-medium">{{ tournament.name }}</span>
                </div>
                <div class="flex flex-wrap gap-x-3 gap-y-1 text-2xs text-muted-foreground">
                    <span class="flex items-center gap-1 tabular-nums"
                        ><CalendarDays class="size-3" /> {{ formatDateOnly(tournament.event_date) }}</span
                    >
                    <span v-if="tournament.location" class="flex items-center gap-1"><MapPin class="size-3" /> {{ tournament.location }}</span>
                    <span class="flex items-center gap-1 tabular-nums"
                        ><Users class="size-3" /> {{ tournament.players_count }} {{ tournament.players_count === 1 ? 'player' : 'players' }}</span
                    >
                    <span v-if="meta === 'rounds'" class="tabular-nums">{{ tournament.planned_rounds }} rounds</span>
                    <span v-else>by {{ tournament.creator.name }}</span>
                </div>
            </CardContent>
        </Card>
    </Link>
</template>
