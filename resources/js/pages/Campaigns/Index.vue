<script setup lang="ts">
import PageBanner from '@/components/PageBanner.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { CARD_HOVER, CARD_HOVER_QUIET } from '@/lib/cardHover';
import { Head, Link } from '@inertiajs/vue3';
import { Calendar, Plus, Scroll, Sparkles, Trophy, Users } from 'lucide-vue-next';
import { computed } from 'vue';

interface Organizer {
    id: number;
    name: string;
}

interface CampaignRow {
    id: number;
    name: string;
    length_weeks: number;
    current_week: number;
    status: string;
    is_solo: boolean;
    started_at: string | null;
    ended_at: string | null;
    organizer: Organizer | null;
}

const props = defineProps<{ campaigns: CampaignRow[] }>();

const statusVariant = (s: string): 'default' | 'outline' | 'destructive' | 'secondary' => {
    switch (s) {
        case 'active':
            return 'default';
        case 'planning':
            return 'outline';
        case 'ended':
            return 'secondary';
        default:
            return 'outline';
    }
};

const progressPct = (c: CampaignRow): number => {
    if (c.length_weeks <= 0) return 0;
    return Math.min(100, Math.round((c.current_week / c.length_weeks) * 100));
};

const activeCampaigns = computed(() => props.campaigns.filter((c) => c.status === 'active'));
const planningCampaigns = computed(() => props.campaigns.filter((c) => c.status === 'planning'));
const endedCampaigns = computed(() => props.campaigns.filter((c) => c.status === 'ended'));
</script>

<template>
    <Head title="Campaigns — Malifaux 4E" />

    <PageBanner title="Campaigns">
        <template #subtitle>
            <div class="flex items-center gap-3 px-2">
                <span class="text-sm text-muted-foreground"> Index of the Untold — narrative crews across multiple games. </span>
            </div>
        </template>
        <template #actions>
            <div class="flex items-center px-2 py-2 md:py-4">
                <Link :href="route('campaigns.create')">
                    <Button><Plus class="mr-1 h-4 w-4" /> New Campaign</Button>
                </Link>
            </div>
        </template>
    </PageBanner>

    <div class="container mx-auto px-4 pb-12">
        <!-- Empty state -->
        <div v-if="campaigns.length === 0" class="mt-12 rounded-lg border-2 border-dashed border-muted-foreground/30 bg-muted/20 py-20 text-center">
            <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-primary/10">
                <Scroll class="h-8 w-8 text-primary" />
            </div>
            <h2 class="mt-6 text-xl font-semibold">No campaigns yet</h2>
            <p class="mx-auto mt-2 max-w-md text-sm text-muted-foreground">
                Start one to follow your crew through a 4–12 week saga: custom Leader, scrip economy, equipment hoards, the works.
            </p>
            <Link :href="route('campaigns.create')">
                <Button class="mt-6" size="lg"><Plus class="mr-1 h-4 w-4" /> Start your first campaign</Button>
            </Link>
        </div>

        <!-- Active campaigns get pride of place -->
        <section v-if="activeCampaigns.length" class="mt-6">
            <div class="mb-3 flex items-center gap-2">
                <Sparkles class="h-5 w-5 text-primary" />
                <h2 class="text-lg font-semibold uppercase tracking-wider">Active</h2>
                <Badge variant="outline" class="text-[10px]">{{ activeCampaigns.length }}</Badge>
            </div>
            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                <Link v-for="c in activeCampaigns" :key="c.id" :href="route('campaigns.show', c.id)" class="group">
                    <Card :class="['overflow-hidden', CARD_HOVER]">
                        <div class="h-1.5 bg-muted">
                            <div class="h-full bg-primary transition-all" :style="{ width: progressPct(c) + '%' }" />
                        </div>
                        <CardContent class="space-y-3 p-5">
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0 flex-1">
                                    <h3 class="truncate text-lg font-bold leading-tight">{{ c.name }}</h3>
                                    <p class="mt-0.5 truncate text-xs text-muted-foreground">Organizer: {{ c.organizer?.name ?? '—' }}</p>
                                </div>
                                <div class="flex flex-shrink-0 flex-col items-end gap-1">
                                    <Badge :variant="statusVariant(c.status)" class="text-[10px] uppercase">{{ c.status }}</Badge>
                                    <Badge v-if="c.is_solo" variant="outline" class="text-[10px] uppercase">Solo</Badge>
                                </div>
                            </div>
                            <div class="flex items-center gap-2 text-xs text-muted-foreground">
                                <Calendar class="h-3.5 w-3.5" />
                                Week <span class="font-medium tabular-nums text-foreground">{{ c.current_week }}</span> / {{ c.length_weeks }}
                            </div>
                        </CardContent>
                    </Card>
                </Link>
            </div>
        </section>

        <section v-if="planningCampaigns.length" class="mt-8">
            <div class="mb-3 flex items-center gap-2">
                <Users class="h-5 w-5 text-muted-foreground" />
                <h2 class="text-lg font-semibold uppercase tracking-wider text-muted-foreground">Planning</h2>
                <Badge variant="outline" class="text-[10px]">{{ planningCampaigns.length }}</Badge>
            </div>
            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                <Link v-for="c in planningCampaigns" :key="c.id" :href="route('campaigns.show', c.id)" class="group">
                    <Card :class="CARD_HOVER">
                        <CardContent class="space-y-2 p-5">
                            <div class="flex items-start justify-between gap-2">
                                <h3 class="min-w-0 flex-1 truncate text-lg font-semibold leading-tight">{{ c.name }}</h3>
                                <div class="flex flex-shrink-0 flex-col items-end gap-1">
                                    <Badge :variant="statusVariant(c.status)" class="text-[10px] uppercase">{{ c.status }}</Badge>
                                    <Badge v-if="c.is_solo" variant="outline" class="text-[10px] uppercase">Solo</Badge>
                                </div>
                            </div>
                            <p class="text-xs text-muted-foreground">
                                Organizer: {{ c.organizer?.name ?? '—' }} • {{ c.length_weeks }} weeks planned
                            </p>
                        </CardContent>
                    </Card>
                </Link>
            </div>
        </section>

        <section v-if="endedCampaigns.length" class="mt-8 opacity-75">
            <div class="mb-3 flex items-center gap-2">
                <Trophy class="h-5 w-5 text-muted-foreground" />
                <h2 class="text-lg font-semibold uppercase tracking-wider text-muted-foreground">Ended</h2>
                <Badge variant="outline" class="text-[10px]">{{ endedCampaigns.length }}</Badge>
            </div>
            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                <Link v-for="c in endedCampaigns" :key="c.id" :href="route('campaigns.show', c.id)" class="group">
                    <Card :class="CARD_HOVER_QUIET">
                        <CardContent class="space-y-1 p-4 text-sm">
                            <div class="flex items-start justify-between gap-2">
                                <span class="truncate font-medium">{{ c.name }}</span>
                                <Badge variant="secondary" class="text-[10px] uppercase">ended</Badge>
                            </div>
                            <p class="text-xs text-muted-foreground">{{ c.organizer?.name ?? '—' }}</p>
                        </CardContent>
                    </Card>
                </Link>
            </div>
        </section>
    </div>
</template>
