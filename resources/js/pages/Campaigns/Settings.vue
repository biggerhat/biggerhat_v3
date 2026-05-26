<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import PageBanner from '@/components/PageBanner.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';

interface CampaignSettingsData {
    id: number;
    name: string;
    length_weeks: number;
    current_week: number;
    status: string;
    optional_rules: Record<string, boolean> | null;
    competitive: boolean;
    weekly_event_active: boolean;
}

const props = defineProps<{ campaign: CampaignSettingsData }>();

const form = ref({
    name: '',
    length_weeks: 8,
    competitive: false,
    weekly_event_active: false,
    optional_rules: {} as Record<string, boolean>,
});

const submit = () => router.post(route('campaigns.update', props.campaign.id), form.value);

onMounted(() => {
    form.value.name = props.campaign.name;
    form.value.length_weeks = props.campaign.length_weeks;
    form.value.competitive = props.campaign.competitive;
    form.value.weekly_event_active = props.campaign.weekly_event_active;
    form.value.optional_rules = { ...(props.campaign.optional_rules ?? {}) };
});

const ruleKeys = [
    'no_injuries',
    'extra_scrip',
    'stay_dead',
    'cut_em_up',
    'corrupted_pawns',
    'empowered_aftermath',
    'evolving_leadership',
    'master_lead',
    'bounties',
    'black_market',
];
</script>

<template>
    <Head title="Campaign Settings" />

    <PageBanner :title="`${campaign.name} — Settings`">
        <template #subtitle>
            <div class="px-2">
                <span class="text-sm text-muted-foreground">
                    Status: <strong class="capitalize text-foreground">{{ campaign.status }}</strong> • Some settings lock once active.
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

    <div class="container mx-auto max-w-3xl px-4 pb-12">
        <Card>
            <CardHeader>
                <CardTitle>Configuration</CardTitle>
            </CardHeader>
            <CardContent class="space-y-4">
                <div>
                    <Label for="name">Name</Label>
                    <Input id="name" v-model="form.name" />
                    <InputError :message="usePage().props.errors.name" />
                </div>

                <div>
                    <Label for="length_weeks">Length (weeks)</Label>
                    <Input id="length_weeks" type="number" min="2" max="26" v-model.number="form.length_weeks" />
                </div>

                <div class="space-y-2 rounded-md border p-3">
                    <label class="flex items-start gap-2 text-sm">
                        <Checkbox :checked="form.competitive" @update:checked="(v: boolean) => (form.competitive = v)" />
                        <span>Competitive (track wins per player)</span>
                    </label>
                    <label class="flex items-start gap-2 text-sm">
                        <Checkbox :checked="form.weekly_event_active" @update:checked="(v: boolean) => (form.weekly_event_active = v)" />
                        <span>Weekly events</span>
                    </label>
                </div>

                <fieldset class="space-y-2 rounded-md border p-3">
                    <legend class="px-1 text-xs font-medium uppercase text-muted-foreground">Optional Rules</legend>
                    <label v-for="key in ruleKeys" :key="key" class="flex items-start gap-2 text-sm">
                        <Checkbox :checked="!!form.optional_rules[key]" @update:checked="(v: boolean) => (form.optional_rules[key] = v)" />
                        <span>{{ key.replace(/_/g, ' ') }}</span>
                    </label>
                </fieldset>
            </CardContent>
            <CardFooter class="justify-end gap-2">
                <Link :href="route('campaigns.show', campaign.id)">
                    <Button variant="outline">Cancel</Button>
                </Link>
                <Button @click="submit">Save</Button>
            </CardFooter>
        </Card>
    </div>
</template>
