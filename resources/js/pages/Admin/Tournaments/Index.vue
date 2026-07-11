<script setup lang="ts">
import PageBanner from '@/components/PageBanner.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Head, Link } from '@inertiajs/vue3';

interface TournamentRow {
    id: number;
    uuid: string;
    name: string;
    status: string | null;
    players_count: number;
    rounds_count: number;
    event_date: string | null;
}

defineProps<{
    tournaments: TournamentRow[];
    tournament_statuses: { value: string; label: string }[];
}>();

const statusBadgeClass = (status: string | null) => {
    switch (status) {
        case 'draft':
            return 'border-muted text-muted-foreground';
        case 'registration':
            return 'border-blue-500/40 bg-blue-500/10 text-blue-700 dark:text-blue-400';
        case 'active':
            return 'border-green-500/40 bg-green-500/10 text-green-700 dark:text-green-400';
        case 'completed':
            return 'border-purple-500/40 bg-purple-500/10 text-purple-700 dark:text-purple-400';
        default:
            return '';
    }
};
</script>

<template>
    <Head title="Tournaments - Admin Override" />
    <PageBanner title="Tournament Override" class="mb-2">
        <template #subtitle>
            <div class="my-auto px-2 py-0 text-xs text-muted-foreground md:py-2 md:text-sm md:text-foreground">
                Super-admin escape hatch for the tournament state machine. Use sparingly — every change is recorded in the activity log.
            </div>
        </template>
    </PageBanner>
    <div class="container mx-auto space-y-4 px-4 py-6 lg:px-8 xl:px-12">
        <div class="space-y-2">
            <Card v-for="t in tournaments" :key="t.id">
                <CardContent class="flex flex-col gap-2 p-3 sm:flex-row sm:items-center sm:gap-3">
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-sm font-semibold">{{ t.name }}</span>
                            <Badge variant="outline" :class="statusBadgeClass(t.status)">{{ t.status ?? '—' }}</Badge>
                            <Badge variant="secondary" class="text-[10px]">{{ t.players_count }} players</Badge>
                            <Badge variant="secondary" class="text-[10px]">{{ t.rounds_count }} rounds</Badge>
                        </div>
                        <div class="mt-0.5 text-xs text-muted-foreground">
                            <span v-if="t.event_date">{{ t.event_date }} · </span>
                            <code class="rounded bg-muted px-1">{{ t.uuid }}</code>
                        </div>
                    </div>
                    <Button as-child variant="outline" size="sm">
                        <Link :href="route('admin.tournaments.show', t.uuid)">Override</Link>
                    </Button>
                </CardContent>
            </Card>
            <div v-if="!tournaments.length" class="py-12 text-center text-sm text-muted-foreground">No tournaments.</div>
        </div>
    </div>
</template>
