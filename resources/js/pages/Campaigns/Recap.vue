<script setup lang="ts">
import EmptyState from '@/components/EmptyState.vue';
import PageBanner from '@/components/PageBanner.vue';
import SeoHead from '@/components/SeoHead.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Link } from '@inertiajs/vue3';
import { ScrollText } from 'lucide-vue-next';

interface Standing {
    id: number;
    name: string;
    player: string | null;
    wins: number;
    games: number;
    scrip: number;
}

interface RecapGame {
    id: number;
    crew_a: string | null;
    crew_b: string;
    vp_a: number;
    vp_b: number;
    winner: string | null;
    withdrew: string | null;
}

defineProps<{
    campaign: { id: number; name: string; status: string; current_week: number; length_weeks: number };
    standings: Standing[];
    weeks: { week: number; games: RecapGame[] }[];
}>();
</script>

<template>
    <SeoHead :title="`${campaign.name} — Recap`" description="Campaign standings and results by week." />
    <PageBanner :title="`${campaign.name} — Recap`">
        <template #subtitle>
            <div class="flex flex-wrap items-center gap-3 px-2">
                <span class="text-sm text-muted-foreground">Week {{ campaign.current_week }} of {{ campaign.length_weeks }}</span>
                <Link :href="route('campaigns.show', campaign.id)">
                    <Button variant="ghost" size="sm" class="h-7 text-xs">Back to campaign</Button>
                </Link>
            </div>
        </template>
    </PageBanner>

    <div class="container mx-auto mt-6 space-y-6 pb-10 sm:px-4 lg:px-6">
        <Card>
            <CardHeader><CardTitle class="text-lg">Standings</CardTitle></CardHeader>
            <CardContent class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-xs uppercase tracking-wide text-muted-foreground">
                            <th scope="col" class="py-1 pr-3 font-medium">#</th>
                            <th scope="col" class="py-1 pr-3 font-medium">Crew</th>
                            <th scope="col" class="py-1 pr-3 text-right font-medium">Wins</th>
                            <th scope="col" class="py-1 pr-3 text-right font-medium">Games</th>
                            <th scope="col" class="py-1 text-right font-medium">Scrip</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(row, i) in standings" :key="row.id" class="border-t">
                            <td class="py-2 pr-3 text-muted-foreground">{{ i + 1 }}</td>
                            <td class="py-2 pr-3">
                                <div class="font-medium">{{ row.name }}</div>
                                <div v-if="row.player" class="text-xs text-muted-foreground">{{ row.player }}</div>
                            </td>
                            <td class="py-2 pr-3 text-right font-semibold tabular-nums">{{ row.wins }}</td>
                            <td class="py-2 pr-3 text-right tabular-nums">{{ row.games }}</td>
                            <td class="py-2 text-right tabular-nums">{{ row.scrip }}</td>
                        </tr>
                    </tbody>
                </table>
            </CardContent>
        </Card>

        <EmptyState
            v-if="weeks.length === 0"
            :icon="ScrollText"
            title="No games yet"
            description="Results appear here as soon as games are played."
        />

        <section v-for="w in [...weeks].reverse()" :key="w.week" class="space-y-2">
            <h2 class="px-1 text-lg font-semibold">Week {{ w.week }}</h2>
            <div class="grid gap-2 md:grid-cols-2">
                <div v-for="g in w.games" :key="g.id" class="rounded-lg border bg-card px-3 py-2.5">
                    <div class="flex items-center justify-between gap-2 text-sm">
                        <span :class="g.winner === g.crew_a ? 'font-semibold' : ''">{{ g.crew_a }}</span>
                        <span class="tabular-nums text-muted-foreground">{{ g.vp_a }} – {{ g.vp_b }}</span>
                        <span :class="g.winner === g.crew_b ? 'font-semibold' : ''" class="text-right">{{ g.crew_b }}</span>
                    </div>
                    <div class="mt-1 flex flex-wrap gap-1.5 text-2xs">
                        <Badge v-if="g.winner" variant="secondary">{{ g.winner }} won</Badge>
                        <Badge v-else variant="outline">No winner recorded</Badge>
                        <Badge v-if="g.withdrew" variant="outline">{{ g.withdrew }} withdrew</Badge>
                    </div>
                </div>
            </div>
        </section>
    </div>
</template>
