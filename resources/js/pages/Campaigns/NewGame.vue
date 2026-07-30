<script setup lang="ts">
import EmptyState from '@/components/EmptyState.vue';
import PageBanner from '@/components/PageBanner.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { CARD_HOVER_QUIET } from '@/lib/cardHover';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

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
    arsenal_ss: number;
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

const MIN_ENCOUNTER_SIZE = 10;

const selectedOpponent = ref<CrewRow | null>(null);
const name = ref('');
const encounterSize = ref<number | null>(null);

// Max encounter size (pg 19): smaller arsenal total + 6. The server re-derives
// this authoritatively at submit time from current arsenal contents (it can
// mutate between page-load and submit), so this is a preview only — the
// server clamps down to it regardless of what's submitted here.
const maxEncounterSize = computed(() => {
    if (!selectedOpponent.value) return null;
    return Math.min(props.my_arsenal_ss, selectedOpponent.value.arsenal_ss) + 6;
});

watch(maxEncounterSize, (max) => {
    if (max !== null) encounterSize.value = max;
});

const clampEncounterSize = () => {
    if (encounterSize.value === null || maxEncounterSize.value === null) return;
    encounterSize.value = Math.min(Math.max(encounterSize.value, MIN_ENCOUNTER_SIZE), maxEncounterSize.value);
};

const encounterSizeValid = computed(
    () => encounterSize.value !== null && encounterSize.value >= MIN_ENCOUNTER_SIZE && encounterSize.value <= (maxEncounterSize.value ?? Infinity),
);

const submit = () => {
    if (!selectedOpponent.value) return;
    router.post(route('campaigns.games.store', props.campaign.id), {
        opponent_crew_id: selectedOpponent.value.id,
        name: name.value || null,
        encounter_size: encounterSize.value,
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
                <EmptyState v-if="opponents.length === 0" compact title="No other crews yet" description="Invite more players to this campaign." />
                <ul v-else class="space-y-2">
                    <li v-for="o in opponents" :key="o.id">
                        <button
                            type="button"
                            @click="selectedOpponent = o"
                            class="w-full rounded-md border p-3 text-left"
                            :class="selectedOpponent?.id === o.id ? 'border-primary bg-primary/10' : CARD_HOVER_QUIET"
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
                <div v-if="selectedOpponent">
                    <Label for="encounter-size">Encounter Size</Label>
                    <Input
                        id="encounter-size"
                        v-model.number="encounterSize"
                        type="number"
                        :min="MIN_ENCOUNTER_SIZE"
                        :max="maxEncounterSize ?? undefined"
                        @blur="clampEncounterSize"
                    />
                    <p class="mt-1 text-xs text-muted-foreground">
                        Max {{ maxEncounterSize }}ss (smaller arsenal + 6). Players may agree to play smaller.
                    </p>
                </div>
                <p class="text-xs text-muted-foreground">Strategy and scheme pool are generated on submit.</p>
            </CardContent>
            <CardFooter class="justify-end gap-2">
                <Link :href="route('campaigns.show', campaign.id)">
                    <Button variant="outline">Cancel</Button>
                </Link>
                <Button :disabled="!selectedOpponent || !encounterSizeValid" @click="submit">Start Game</Button>
            </CardFooter>
        </Card>
    </div>
</template>
