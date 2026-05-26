<script setup lang="ts">
import PageBanner from '@/components/PageBanner.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { useToast } from '@/composables/useToast';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const toast = useToast();

interface KeywordRow {
    id: number;
    name: string;
}
interface CharRow {
    id: number;
    display_name: string;
    cost: number | null;
    faction: string;
    station: string;
    keywords?: KeywordRow[];
    characteristics?: { name: string }[];
}
interface ArsenalRow {
    id: number;
    character_id: number;
    label: string | null;
    character: CharRow | null;
}
interface CrewCardEffectRow {
    id: number;
    name: string;
    body: string;
    requires_token_choice: boolean;
    requires_marker_choice: boolean;
    requires_upgrade_type_choice: boolean;
}
interface CrewData {
    id: number;
    share_code: string;
    name: string;
    faction: string | null;
    keyword_1_id: number | null;
    keyword_2_id: number | null;
    scrip: number;
    crew_card_effect_id: number | null;
}
interface CampaignData {
    id: number;
    name: string;
    status: string;
}

const props = defineProps<{
    campaign: CampaignData;
    crew: CrewData;
    arsenal: ArsenalRow[];
    hireable: CharRow[];
    crew_card_effects: CrewCardEffectRow[];
    starting_budget_ss: number;
    max_leftover_scrip: number;
    locked: boolean;
}>();

// Pre-seed hires from existing arsenal so the wizard is idempotent (edit-friendly).
const hires = ref<Array<{ character_id: number; label: string | null; cost: number; display_name: string }>>(
    props.arsenal.map((row) => ({
        character_id: row.character_id,
        label: row.label,
        cost: row.character?.cost ?? 0,
        display_name: row.character?.display_name ?? '',
    })),
);

const selectedCrewCardEffectId = ref<number | null>(props.crew.crew_card_effect_id);

const filter = ref('');
const filteredHireable = computed(() => {
    const f = filter.value.toLowerCase().trim();
    return props.hireable.filter((c) => !f || c.display_name.toLowerCase().includes(f));
});

const totalSpent = computed(() => hires.value.reduce((sum, h) => sum + (h.cost ?? 0), 0));
const remainingBudget = computed(() => props.starting_budget_ss - totalSpent.value);
const projectedScrip = computed(() => Math.min(props.max_leftover_scrip, Math.max(0, remainingBudget.value)));

const overBudget = computed(() => totalSpent.value > props.starting_budget_ss);

const addHire = (c: CharRow) => {
    if (props.locked) return;
    if ((c.cost ?? 0) > remainingBudget.value) {
        toast.warning('Over budget', {
            description: `Adding ${c.display_name} (${c.cost} ss) would exceed your remaining ${remainingBudget.value} ss.`,
        });
        return;
    }
    hires.value.push({
        character_id: c.id,
        label: null,
        cost: c.cost ?? 0,
        display_name: c.display_name,
    });
};

const removeHire = (idx: number) => {
    if (props.locked) return;
    hires.value.splice(idx, 1);
};

const submit = () => {
    if (props.locked) return;
    if (!selectedCrewCardEffectId.value) {
        toast.warning('Pick a Crew Card effect before saving.');
        return;
    }
    router.post(route('campaigns.crews.starting-arsenal.update', [props.campaign.id, props.crew.share_code]), {
        hires: hires.value.map((h) => ({ character_id: h.character_id, label: h.label })),
        crew_card_effect_id: selectedCrewCardEffectId.value,
    });
};
</script>

<template>
    <Head :title="`Starting Arsenal — ${campaign.name}`" />

    <PageBanner title="Starting Arsenal">
        <template #subtitle>
            <div class="px-2">
                <span class="text-sm text-muted-foreground">
                    {{ campaign.name }} • <strong class="text-foreground">{{ crew.name }}</strong> • 25 ss budget • unspent → scrip (max 3)
                </span>
                <p v-if="locked" class="mt-1 text-sm font-medium text-amber-600">Locked — the campaign is no longer in planning.</p>
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

    <div class="container mx-auto max-w-6xl px-4 pb-16">
        <div class="mb-6 grid grid-cols-3 gap-3 rounded-lg border bg-muted/40 p-3 text-center text-sm">
            <div>
                <span class="block text-xs uppercase text-muted-foreground">Spent</span><span class="tabular-nums">{{ totalSpent }}</span> /
                {{ starting_budget_ss }} ss
            </div>
            <div :class="overBudget ? 'text-destructive' : ''">
                <span class="block text-xs uppercase text-muted-foreground">Remaining</span>
                <span class="tabular-nums">{{ remainingBudget }}</span> ss
            </div>
            <div>
                <span class="block text-xs uppercase text-muted-foreground">Scrip on Save</span>
                <span class="tabular-nums">{{ projectedScrip }}</span> / {{ max_leftover_scrip }}
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <Card>
                <CardHeader>
                    <CardTitle>Pick Models ({{ filteredHireable.length }})</CardTitle>
                    <Input v-model="filter" placeholder="Filter by name" class="mt-2" />
                </CardHeader>
                <CardContent>
                    <ul class="max-h-[60vh] space-y-1 overflow-y-auto pr-1">
                        <li v-for="c in filteredHireable" :key="c.id" class="flex items-center justify-between rounded-md border px-2 py-1.5 text-sm">
                            <div class="min-w-0 flex-1">
                                <p class="truncate font-medium">{{ c.display_name }}</p>
                                <p class="text-[10px] text-muted-foreground">{{ c.station }} • {{ c.faction }}</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <Badge variant="outline" class="text-[10px] tabular-nums">{{ c.cost }} ss</Badge>
                                <Button size="sm" :disabled="locked || (c.cost ?? 0) > remainingBudget" @click="addHire(c)">Add</Button>
                            </div>
                        </li>
                    </ul>
                </CardContent>
            </Card>

            <Card>
                <CardHeader
                    ><CardTitle>Your Arsenal ({{ hires.length }})</CardTitle></CardHeader
                >
                <CardContent>
                    <ul v-if="hires.length" class="space-y-1">
                        <li
                            v-for="(h, idx) in hires"
                            :key="`${h.character_id}-${idx}`"
                            class="flex items-center justify-between rounded-md border px-2 py-1.5 text-sm"
                        >
                            <div class="min-w-0 flex-1">
                                <p class="truncate font-medium">{{ h.display_name }}</p>
                                <Input v-model="h.label" placeholder="Label (e.g. 'Zombie A')" class="mt-1 h-7 text-xs" :disabled="locked" />
                            </div>
                            <div class="flex items-center gap-2">
                                <Badge variant="outline" class="text-[10px] tabular-nums">{{ h.cost }} ss</Badge>
                                <Button variant="ghost" size="sm" :disabled="locked" @click="removeHire(idx)">×</Button>
                            </div>
                        </li>
                    </ul>
                    <p v-else class="text-sm text-muted-foreground">Empty — add models from the left.</p>
                </CardContent>
            </Card>
        </div>

        <Card class="mt-6">
            <CardHeader>
                <CardTitle>Crew Card Effect</CardTitle>
                <p class="text-sm text-muted-foreground">
                    Pick one starter effect for your crew (Index of the Untold pg 15–16). Can be modified later via Tier-4 advancements.
                </p>
            </CardHeader>
            <CardContent>
                <ul class="grid gap-2 md:grid-cols-2">
                    <li v-for="e in crew_card_effects" :key="e.id">
                        <button
                            type="button"
                            :disabled="locked"
                            @click="selectedCrewCardEffectId = e.id"
                            class="w-full rounded-md border p-3 text-left text-sm transition hover:border-primary"
                            :class="selectedCrewCardEffectId === e.id ? 'border-primary bg-primary/10' : ''"
                        >
                            <p class="font-medium">{{ e.name }}</p>
                            <p class="text-xs text-muted-foreground">{{ e.body }}</p>
                        </button>
                    </li>
                </ul>
                <p v-if="crew_card_effects.length === 0" class="text-sm text-muted-foreground">
                    No crew card effects in the catalog yet. Have an admin seed them via the Campaign admin pages.
                </p>
            </CardContent>
        </Card>

        <div class="mt-8 flex items-center justify-end gap-2">
            <Link :href="route('campaigns.show', campaign.id)">
                <Button variant="outline">Cancel</Button>
            </Link>
            <Button :disabled="locked || overBudget" @click="submit">Save Starting Arsenal</Button>
        </div>

        <div v-if="usePage().props.errors" class="mt-3 text-sm text-destructive">
            <ul>
                <li v-for="(msg, key) in usePage().props.errors" :key="key">{{ msg }}</li>
            </ul>
        </div>
    </div>
</template>
