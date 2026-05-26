<script setup lang="ts">
import PageBanner from '@/components/PageBanner.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

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
    is_solo: boolean;
}

const props = defineProps<{
    campaign: CampaignData;
    my_crew: CrewRow | null;
    my_arsenal_ss: number;
    my_cr: number;
}>();

const form = ref({
    name: '',
    vp_self: 0,
    vp_opponent: 0,
    schemes_completed: 0,
    won: false,
    withdrew: false,
    withdrew_turn: null as number | null,
});

// Disable Withdrew turn input unless Withdrew is checked, to match what
// the server accepts (`withdrew_turn` is only meaningful when withdrawn).
const turnDisabled = computed(() => !form.value.withdrew);

const submit = () => {
    if (!props.my_crew) return;
    router.post(route('campaigns.games.log.store', props.campaign.id), {
        name: form.value.name || null,
        vp_self: form.value.vp_self,
        vp_opponent: form.value.vp_opponent,
        schemes_completed: form.value.schemes_completed,
        won: form.value.won,
        withdrew: form.value.withdrew,
        withdrew_turn: form.value.withdrew ? form.value.withdrew_turn : null,
    });
};
</script>

<template>
    <Head :title="`Log Game — ${campaign.name}`" />

    <PageBanner title="Log Solo Game">
        <template #subtitle>
            <div class="px-2">
                <span class="text-sm text-muted-foreground">
                    {{ campaign.name }} • Week {{ campaign.current_week }} / {{ campaign.length_weeks }} •
                    <strong class="text-foreground">{{ my_crew?.name ?? 'No crew' }}</strong>
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

    <div class="container mx-auto max-w-2xl px-4 pb-16">
        <Card>
            <CardHeader>
                <CardTitle>Game Result</CardTitle>
                <p class="text-xs text-muted-foreground">
                    Solo mode: enter the result of a game you played offline. The Aftermath wizard will start as soon as you save — Payday and
                    downstream phases use these numbers.
                </p>
            </CardHeader>
            <CardContent class="space-y-4">
                <div>
                    <Label for="name">Game Name (optional)</Label>
                    <Input id="name" v-model="form.name" placeholder="Friday night learning game" />
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <Label for="vp_self">Your VP</Label>
                        <Input id="vp_self" type="number" min="0" max="20" v-model.number="form.vp_self" />
                    </div>
                    <div>
                        <Label for="vp_opponent">Opponent VP</Label>
                        <Input id="vp_opponent" type="number" min="0" max="20" v-model.number="form.vp_opponent" />
                        <p class="text-[11px] text-muted-foreground">0 if you played against the game itself.</p>
                    </div>
                </div>

                <div>
                    <Label for="schemes_completed">Schemes Completed (0–3)</Label>
                    <Input id="schemes_completed" type="number" min="0" max="3" v-model.number="form.schemes_completed" />
                    <p class="text-[11px] text-muted-foreground">Drives your Aftermath hand size.</p>
                </div>

                <div class="space-y-2 rounded-md border p-3">
                    <label class="flex items-start gap-2 text-sm">
                        <Checkbox :checked="form.won" @update:checked="(v: boolean) => (form.won = v)" />
                        <span>I won the game</span>
                    </label>
                    <label class="flex items-start gap-2 text-sm">
                        <Checkbox :checked="form.withdrew" @update:checked="(v: boolean) => (form.withdrew = v)" />
                        <span>I withdrew (pg 20 — affects scrip payout)</span>
                    </label>
                    <div v-if="form.withdrew" class="ml-6">
                        <Label for="withdrew_turn">Withdrew on turn</Label>
                        <Input
                            id="withdrew_turn"
                            type="number"
                            min="1"
                            max="10"
                            :disabled="turnDisabled"
                            v-model.number="form.withdrew_turn"
                            class="w-24"
                        />
                    </div>
                </div>
            </CardContent>
            <CardFooter class="justify-end gap-2">
                <Link :href="route('campaigns.show', campaign.id)">
                    <Button variant="outline">Cancel</Button>
                </Link>
                <Button :disabled="!my_crew" @click="submit">Save &amp; Start Aftermath</Button>
            </CardFooter>
        </Card>
    </div>
</template>
