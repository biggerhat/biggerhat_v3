<script setup lang="ts">
import PageBanner from '@/components/PageBanner.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

interface UserMini {
    id: number;
    name: string;
}
interface CrewRow {
    id: number;
    campaign_id: number;
    user_id: number;
    share_code: string;
    name: string;
    faction: string | null;
    scrip: number;
    user: UserMini | null;
}
interface CampaignData {
    id: number;
    name: string;
    status: string;
    current_week: number;
    length_weeks: number;
}

const props = defineProps<{
    campaign: CampaignData;
    my_crew: CrewRow | null;
    opponents: CrewRow[];
    my_arsenal_ss: number;
    my_cr: number;
}>();

const selectedOpponent = ref<CrewRow | null>(null);
const name = ref('');

// Encounter size is computed authoritatively on the server at submit time
// (min(myArsenal, opponentArsenal) + 6 — opponent arsenal ss isn't in the
// initial payload because it can mutate between page-load and submit).
// Future iteration can fetch opponent's current arsenal ss via an XHR.

const submit = () => {
    if (!selectedOpponent.value) return;
    router.post(route('campaigns.games.store', props.campaign.id), {
        opponent_crew_id: selectedOpponent.value.id,
        name: name.value || null,
    });
};
</script>

<template>
    <Head :title="`New Game — ${campaign.name}`" />

    <PageBanner title="New Campaign Game">
        <template #subtitle>
            <div class="px-2">
                <span class="text-sm text-muted-foreground">
                    {{ campaign.name }} • Week {{ campaign.current_week }} / {{ campaign.length_weeks }}
                </span>
            </div>
        </template>
        <template #actions>
            <div class="flex items-center px-2 py-2 md:py-4">
                <Link :href="route('campaigns.show', campaign.id)">
                    <Button variant="outline">← Back to Campaign</Button>
                </Link>
            </div>
        </template>
    </PageBanner>

    <div class="container mx-auto max-w-4xl px-4 pb-16">
        <Card v-if="my_crew" class="mb-4">
            <CardHeader><CardTitle>Your Crew</CardTitle></CardHeader>
            <CardContent class="text-sm">
                <p class="font-medium">{{ my_crew.name }}</p>
                <p class="text-muted-foreground">
                    {{ my_crew.faction ?? 'Faction TBD' }} • Arsenal {{ my_arsenal_ss }} ss • CR {{ my_cr }} • {{ my_crew.scrip }} scrip
                </p>
            </CardContent>
        </Card>

        <Card class="mb-4">
            <CardHeader><CardTitle>Pick Opponent</CardTitle></CardHeader>
            <CardContent>
                <p v-if="opponents.length === 0" class="text-sm text-muted-foreground">No other crews in this campaign yet — invite more players.</p>
                <ul v-else class="space-y-2">
                    <li v-for="o in opponents" :key="o.id">
                        <button
                            type="button"
                            @click="selectedOpponent = o"
                            class="w-full rounded-md border p-3 text-left transition hover:border-primary"
                            :class="selectedOpponent?.id === o.id ? 'border-primary bg-primary/10' : ''"
                        >
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="font-medium">{{ o.name }}</p>
                                    <p class="text-xs text-muted-foreground">{{ o.user?.name }} • {{ o.faction ?? '—' }}</p>
                                </div>
                                <Badge variant="outline" class="text-[10px] tabular-nums">{{ o.scrip }} scrip</Badge>
                            </div>
                        </button>
                    </li>
                </ul>
            </CardContent>
        </Card>

        <Card class="mb-4">
            <CardHeader><CardTitle>Game Settings</CardTitle></CardHeader>
            <CardContent class="space-y-3">
                <div>
                    <Label for="name">Game Name (optional)</Label>
                    <Input id="name" v-model="name" placeholder="Saturday night brawl" />
                </div>
                <p class="text-xs text-muted-foreground">
                    Encounter size, strategy, and scheme pool are generated on submit. CR + ss-pool bonus will surface on the in-game tracker once
                    Aftermath data is live (Phase 9).
                </p>
            </CardContent>
            <CardFooter class="justify-end gap-2">
                <Link :href="route('campaigns.show', campaign.id)">
                    <Button variant="outline">Cancel</Button>
                </Link>
                <Button :disabled="!selectedOpponent" @click="submit">Start Game</Button>
            </CardFooter>
        </Card>
    </div>
</template>
