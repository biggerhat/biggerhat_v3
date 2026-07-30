<script setup lang="ts">
import PageBanner from '@/components/PageBanner.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

interface CampaignData {
    id: number;
    name: string;
    current_week: number;
    length_weeks: number;
}
interface CrewData {
    id: number;
    share_code: string;
    name: string;
    faction: string | null;
}
interface Result {
    vp_self: number;
    vp_opponent: number;
    schemes_completed: number;
    won: boolean;
    withdrew: boolean;
    crew_cr: number;
    opponent_cr: number;
}
interface Tally {
    injuries: number;
    doctor_attempts: number;
    lucky_misses: number;
    ttw_pickups: number;
}

const props = defineProps<{
    campaign: CampaignData;
    crew: CrewData;
    week_number: number;
    story_entry: string | null;
    locked: boolean;
    result: Result;
    tally: Tally;
}>();

const tallyParts = computed(() => {
    const parts: string[] = [];
    if (props.tally.injuries > 0) parts.push(`Injuries: ${props.tally.injuries}`);
    if (props.tally.doctor_attempts > 0) parts.push(`Doctor: ${props.tally.doctor_attempts}`);
    if (props.tally.lucky_misses > 0) parts.push(`Lucky Miss: ${props.tally.lucky_misses}`);
    if (props.tally.ttw_pickups > 0) parts.push(`TTW pickup: ${props.tally.ttw_pickups}`);
    return parts;
});

const resultLabel = computed(() => {
    if (props.result.withdrew) return 'Withdrew';
    return props.result.won ? 'Won' : 'Lost';
});
const resultVariant = computed(() => {
    if (props.result.withdrew) return 'outline' as const;
    return props.result.won ? ('default' as const) : ('destructive' as const);
});
</script>

<template>
    <Head :title="`Game Recap — ${campaign.name}`" />

    <PageBanner title="Game Recap">
        <template #subtitle>
            <div class="px-2">
                <span class="text-sm text-muted-foreground">
                    {{ campaign.name }} • Week {{ week_number }} • <strong class="text-foreground">{{ crew.name }}</strong>
                </span>
            </div>
        </template>
        <template #actions>
            <div class="flex items-center px-2 py-2 md:py-4">
                <Link :href="route('campaigns.crews.arsenal.show', [campaign.id, crew.share_code])">
                    <Button variant="outline">← Back to Arsenal Sheet</Button>
                </Link>
            </div>
        </template>
    </PageBanner>

    <div class="container mx-auto max-w-2xl px-4 pb-16">
        <p class="mb-4 rounded-md border border-dashed bg-muted/20 p-2 text-xs text-muted-foreground">
            This game was logged manually — no live tracker session exists to replay, so this is a lightweight recap of the result recorded at the
            time.
        </p>

        <Card class="mb-4">
            <CardHeader>
                <CardTitle class="flex items-center gap-2">
                    Result
                    <Badge :variant="resultVariant">{{ resultLabel }}</Badge>
                </CardTitle>
            </CardHeader>
            <CardContent class="grid grid-cols-2 gap-3 text-sm">
                <div>
                    <p class="text-xs uppercase text-muted-foreground">Your VP</p>
                    <p class="text-lg font-bold">{{ result.vp_self }}</p>
                </div>
                <div>
                    <p class="text-xs uppercase text-muted-foreground">Opponent VP</p>
                    <p class="text-lg font-bold">{{ result.vp_opponent }}</p>
                </div>
                <div>
                    <p class="text-xs uppercase text-muted-foreground">Schemes Completed</p>
                    <p class="text-lg font-bold">{{ result.schemes_completed }}</p>
                </div>
                <div>
                    <p class="text-xs uppercase text-muted-foreground">Campaign Rating</p>
                    <p class="text-lg font-bold">{{ result.crew_cr }}</p>
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Story</CardTitle>
            </CardHeader>
            <CardContent>
                <p v-if="story_entry" class="whitespace-pre-wrap text-sm">{{ story_entry }}</p>
                <p v-else class="text-sm italic text-muted-foreground">No story written for this game.</p>
                <p v-if="tallyParts.length" class="mt-2 text-xs text-muted-foreground">{{ tallyParts.join(' · ') }}</p>
            </CardContent>
        </Card>
    </div>
</template>
