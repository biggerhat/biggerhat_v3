<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import PageBanner from '@/components/PageBanner.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { CARD_HOVER_QUIET } from '@/lib/cardHover';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';

const form = ref({
    name: '',
    length_weeks: 8,
    is_solo: false,
    competitive: false,
    weekly_event_active: false,
    optional_rules: {
        no_injuries: false,
        extra_scrip: false,
        stay_dead: false,
        cut_em_up: false,
        corrupted_pawns: false,
        empowered_aftermath: false,
        evolving_leadership: false,
        master_lead: false,
        bounties: false,
        black_market: false,
    } as Record<string, boolean>,
});

const submit = () => router.post(route('campaigns.store'), form.value);

const optionalRuleLabels: Record<string, { title: string; body: string }> = {
    no_injuries: {
        title: 'No Injuries',
        body: 'Skip Phase 5 Doctor + Phase 6 Determine Injuries. For narrative groups only.',
    },
    extra_scrip: { title: 'Extra Scrip', body: '+1 scrip per friendly henchman still in play at game end.' },
    stay_dead: { title: 'Stay Dead', body: 'Annihilated unique models cannot be re-added. More thematic.' },
    cut_em_up: { title: 'Cut Em Up For Parts', body: 'Scrap a crew member for scrip = half its cost (rounded up).' },
    corrupted_pawns: {
        title: 'Corrupted Pawns',
        body: 'Each crew starts with one Those Who Thirst item attached to a cost-6-or-less model.',
    },
    empowered_aftermath: {
        title: 'Empowered Aftermath',
        body: "Discard ≤5 aftermath card to add a raise + the discarded card's suit to a flip.",
    },
    evolving_leadership: {
        title: 'Evolving Leadership',
        body: 'Leader annihilations replace the leader with a minion of cost ≤6 sharing a keyword (instead of building a new leader).',
    },
    master_lead: { title: 'Master-Led Campaigns', body: 'Use a real master + titles instead of a custom Leader. No leader advancement.' },
    bounties: { title: 'Bounties', body: 'Players can place scrip bounties on specific opposing models.' },
    black_market: { title: 'Black Market', body: 'Players can request specific equipment trades between sessions.' },
};
</script>

<template>
    <Head title="New Campaign" />

    <PageBanner title="New Campaign">
        <template #subtitle>
            <div class="px-2">
                <span class="text-sm text-muted-foreground"> You'll be the organizer. Invite players after creating. </span>
            </div>
        </template>
    </PageBanner>

    <div class="container mx-auto max-w-3xl px-4 pb-12">
        <Card>
            <CardHeader>
                <CardTitle>Campaign Details</CardTitle>
            </CardHeader>
            <CardContent class="space-y-4">
                <div>
                    <Label for="name">Campaign Name</Label>
                    <Input id="name" v-model="form.name" placeholder="The Untold Saga" />
                    <InputError :message="usePage().props.errors.name" />
                </div>

                <div>
                    <Label for="length_weeks">Length (weeks)</Label>
                    <Input id="length_weeks" type="number" min="2" max="26" v-model.number="form.length_weeks" />
                    <p class="text-[11px] text-muted-foreground">Rulebook recommends 4–12; runs longer or shorter on request.</p>
                    <InputError :message="usePage().props.errors.length_weeks" />
                </div>

                <!-- Mode chooser. Solo locks out invitations + the live game tracker,
                     swapping in a manual Log-Game form before each Aftermath. -->
                <div class="rounded-md border-2 border-primary/30 bg-primary/5 p-3">
                    <p class="text-xs font-medium uppercase text-muted-foreground">Mode</p>
                    <div class="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-2">
                        <button
                            type="button"
                            class="rounded-md border p-3 text-left"
                            :class="!form.is_solo ? 'border-primary bg-background ring-2 ring-primary' : CARD_HOVER_QUIET"
                            @click="form.is_solo = false"
                        >
                            <p class="text-sm font-semibold">Multiplayer</p>
                            <p class="text-[11px] text-muted-foreground">
                                Invite players, play games on the live tracker, run Aftermaths against opponents.
                            </p>
                        </button>
                        <button
                            type="button"
                            class="rounded-md border p-3 text-left"
                            :class="form.is_solo ? 'border-primary bg-background ring-2 ring-primary' : CARD_HOVER_QUIET"
                            @click="form.is_solo = true"
                        >
                            <p class="text-sm font-semibold">Solo</p>
                            <p class="text-[11px] text-muted-foreground">
                                Just track yourself. Log game results manually after offline / co-op play.
                            </p>
                        </button>
                    </div>
                </div>

                <div class="space-y-2 rounded-md border p-3">
                    <p class="text-xs font-medium uppercase text-muted-foreground">Top-level toggles</p>
                    <label v-if="!form.is_solo" class="flex items-start gap-2 text-sm">
                        <Checkbox :checked="form.competitive" @update:checked="(v: boolean) => (form.competitive = v)" />
                        <span><strong>Competitive</strong> — track wins per player to declare a winner at campaign end.</span>
                    </label>
                    <label class="flex items-start gap-2 text-sm">
                        <Checkbox :checked="form.weekly_event_active" @update:checked="(v: boolean) => (form.weekly_event_active = v)" />
                        <span><strong>Weekly Events</strong> — roll the Weekly Events table at the start of each week (pg 148).</span>
                    </label>
                </div>

                <fieldset class="space-y-3 rounded-md border p-3">
                    <legend class="px-1 text-xs font-medium uppercase text-muted-foreground">Optional Rules</legend>
                    <label v-for="(meta, key) in optionalRuleLabels" :key="key" class="flex items-start gap-2 text-sm">
                        <Checkbox :checked="form.optional_rules[key]" @update:checked="(v: boolean) => (form.optional_rules[key] = v)" />
                        <div class="space-y-0.5">
                            <strong>{{ meta.title }}</strong>
                            <span class="block text-xs text-muted-foreground">{{ meta.body }}</span>
                        </div>
                    </label>
                </fieldset>
            </CardContent>
            <CardFooter class="justify-end gap-2">
                <Link :href="route('campaigns.index')">
                    <Button variant="outline">Cancel</Button>
                </Link>
                <Button @click="submit">Create Campaign</Button>
            </CardFooter>
        </Card>
    </div>
</template>
