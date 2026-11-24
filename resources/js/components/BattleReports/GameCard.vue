<script setup lang="ts">
import FactionLogo from '@/components/FactionLogo.vue';
import { Badge } from '@/components/ui/badge';
import { Link } from '@inertiajs/vue3';

/** The game a battle report is about: who played, with what, and the score. Same for tracker games and logged ones. */
export interface ReportGame {
    uuid: string;
    origin: string;
    name: string | null;
    played_on: string | null;
    encounter_size: number;
    strategy: string | null;
    is_tie: boolean;
    winner_slot: number | null;
    url: string | null;
    players: { slot: number; name: string; faction: string | null; master: string | null; points: number }[];
}
defineProps<{ game: ReportGame; compact?: boolean }>();
</script>

<template>
    <div class="rounded-lg border bg-muted/20 p-3" data-testid="report-game">
        <div class="mb-2 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-muted-foreground">
            <Badge v-if="game.origin === 'logged'" variant="outline" class="px-1 py-0 text-2xs">Logged by hand</Badge>
            <Badge v-else-if="game.origin === 'tournament'" variant="outline" class="px-1 py-0 text-2xs">Tournament round</Badge>
            <span v-if="game.played_on">{{ game.played_on }}</span>
            <span>· {{ game.encounter_size }}ss</span>
            <span v-if="game.strategy">· {{ game.strategy }}</span>
            <span v-if="game.is_tie" class="font-medium text-foreground">· A tie</span>
        </div>
        <ul class="grid gap-2" :class="compact ? '' : 'sm:grid-cols-2'">
            <li
                v-for="p in game.players"
                :key="p.slot"
                class="flex items-center gap-2 rounded-md border px-2 py-1.5"
                :class="!game.is_tie && game.winner_slot === p.slot ? 'border-primary/50 bg-primary/5' : ''"
            >
                <FactionLogo v-if="p.faction" :faction="p.faction" class-name="size-5 shrink-0" />
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-medium">
                        {{ p.name }}<span v-if="!game.is_tie && game.winner_slot === p.slot" class="ml-1 text-xs font-normal text-primary">won</span>
                    </p>
                    <p v-if="p.master" class="truncate text-xs text-muted-foreground">{{ p.master }}</p>
                </div>
                <span class="text-lg font-bold tabular-nums">{{ p.points }}<span class="text-xs font-normal text-muted-foreground"> VP</span></span>
            </li>
        </ul>
        <p v-if="game.url && !compact" class="mt-2 text-xs">
            <Link :href="game.url" class="underline">{{
                game.origin === 'logged' ? 'The logged game' : game.origin === 'tournament' ? 'The tournament' : 'The full game summary'
            }}</Link>
        </p>
    </div>
</template>
