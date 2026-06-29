<script setup lang="ts">
import GameText from '@/components/GameText.vue';
import PageBanner from '@/components/PageBanner.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { useToast } from '@/composables/useToast';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

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
interface CrewCardActionItem {
    id: number;
    name: string;
    type: string | null;
    stat: number | string | null;
    stat_suits: string | null;
    stat_modifier: string | null;
    range: number | string | null;
    range_type: string | null;
    description: string | null;
}
interface CrewCardAbilityItem {
    id: number;
    name: string;
    suits: string | null;
    defensive_ability_type: string | null;
    costs_stone: boolean;
    description: string | null;
}

interface CrewCardEffectRow {
    id: number;
    name: string;
    body: string;
    requires_token_choice: boolean;
    requires_marker_choice: boolean;
    requires_upgrade_type_choice: boolean;
    actions: CrewCardActionItem[];
    abilities: CrewCardAbilityItem[];
}
interface ChoiceOption {
    // int for a token/marker, enum-value string for an upgrade type.
    id: number | string;
    name: string;
}
interface CrewCardChoiceOptions {
    tokens: ChoiceOption[];
    markers: ChoiceOption[];
    upgrades: ChoiceOption[];
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
    crew_card_choice: { type: string; id: number | string; name: string } | null;
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
    crew_card_choice_options: CrewCardChoiceOptions;
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

// Crew cards that require a token/marker/upgrade choice (pg 17) surface a
// constrained picker. Choice resets when the selected card changes.
const selectedCrewCardChoiceId = ref<number | string | null>(props.crew.crew_card_choice?.id ?? null);
const selectedCrewCard = computed(() => props.crew_card_effects.find((e) => e.id === selectedCrewCardEffectId.value) ?? null);
const requiredChoiceType = computed<'token' | 'marker' | 'upgrade' | null>(() => {
    const c = selectedCrewCard.value;
    if (!c) return null;
    if (c.requires_token_choice) return 'token';
    if (c.requires_marker_choice) return 'marker';
    if (c.requires_upgrade_type_choice) return 'upgrade';
    return null;
});
const choiceOptions = computed<ChoiceOption[]>(() => {
    if (requiredChoiceType.value === 'token') return props.crew_card_choice_options.tokens;
    if (requiredChoiceType.value === 'marker') return props.crew_card_choice_options.markers;
    if (requiredChoiceType.value === 'upgrade') return props.crew_card_choice_options.upgrades;
    return [];
});
// Optional: name under which the chosen crew card is saved to the player's
// Card Creator (as a crew upgrade). Prefilled from the card when first picked.
const crewCardName = ref('');
watch(selectedCrewCardEffectId, () => {
    selectedCrewCardChoiceId.value = null;
    if (selectedCrewCard.value && !crewCardName.value) {
        crewCardName.value = `${props.crew.name} — ${selectedCrewCard.value.name}`;
    }
});

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
    if (requiredChoiceType.value && !selectedCrewCardChoiceId.value) {
        toast.warning(`This crew card requires choosing a ${requiredChoiceType.value}.`);
        return;
    }
    router.post(route('campaigns.crews.starting-arsenal.update', [props.campaign.id, props.crew.share_code]), {
        hires: hires.value.map((h) => ({ character_id: h.character_id, label: h.label })),
        crew_card_effect_id: selectedCrewCardEffectId.value,
        crew_card_choice:
            requiredChoiceType.value && selectedCrewCardChoiceId.value
                ? { type: requiredChoiceType.value, id: selectedCrewCardChoiceId.value }
                : null,
        crew_card_name: crewCardName.value.trim() || null,
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
                            <p v-if="e.body" class="text-xs text-muted-foreground">{{ e.body }}</p>
                            <div v-if="e.abilities.length || e.actions.length" class="mt-1 space-y-0.5 text-[10px] text-muted-foreground">
                                <p v-if="e.abilities.length">
                                    <span class="font-semibold">Abilities:</span>
                                    {{ e.abilities.map((a) => a.name).join(', ') }}
                                </p>
                                <p v-if="e.actions.length">
                                    <span class="font-semibold">Actions:</span>
                                    {{ e.actions.map((a) => a.name).join(', ') }}
                                </p>
                            </div>
                        </button>
                    </li>
                </ul>

                <!-- Full detail for the selected effect so players can see what each
                     action/ability actually does, not just its name. -->
                <div v-if="selectedCrewCard && (selectedCrewCard.abilities.length || selectedCrewCard.actions.length)" class="mt-4 space-y-3">
                    <div v-if="selectedCrewCard.abilities.length">
                        <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Abilities</p>
                        <div class="space-y-2">
                            <div v-for="a in selectedCrewCard.abilities" :key="a.id" class="rounded-md border bg-card p-2 text-sm">
                                <p class="font-medium">
                                    <GameText :text="a.name" icon-class="h-4 inline-block align-text-bottom" />
                                    <span v-if="a.defensive_ability_type" class="ml-1 text-xs text-muted-foreground"
                                        >({{ a.defensive_ability_type }})</span
                                    >
                                </p>
                                <p v-if="a.description" class="mt-0.5 text-xs leading-relaxed text-muted-foreground">
                                    <GameText :text="a.description" icon-class="h-4 inline-block align-text-bottom" />
                                </p>
                            </div>
                        </div>
                    </div>
                    <div v-if="selectedCrewCard.actions.length">
                        <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Actions</p>
                        <div class="space-y-2">
                            <div v-for="ac in selectedCrewCard.actions" :key="ac.id" class="rounded-md border bg-card p-2 text-sm">
                                <p class="font-medium">
                                    <GameText :text="ac.name" icon-class="h-4 inline-block align-text-bottom" />
                                    <span v-if="ac.stat !== null" class="ml-1 text-xs text-muted-foreground">
                                        {{ ac.type }} · {{ ac.stat }}{{ ac.stat_suits ?? '' }}{{ ac.stat_modifier ?? '' }}
                                    </span>
                                </p>
                                <p v-if="ac.description" class="mt-0.5 text-xs leading-relaxed text-muted-foreground">
                                    <GameText :text="ac.description" icon-class="h-4 inline-block align-text-bottom" />
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Save the chosen crew card to the player's Card Creator (pg: the
                     master card already lives there). Leave blank to skip. -->
                <div v-if="selectedCrewCard" class="mt-4 rounded-md border p-3">
                    <label class="text-xs font-medium" for="crew_card_name">Save crew card to your Card Creator as</label>
                    <Input
                        id="crew_card_name"
                        v-model="crewCardName"
                        :disabled="locked"
                        placeholder="Crew card name (leave blank to skip)"
                        class="mt-1 h-9 text-sm"
                    />
                    <p class="mt-1 text-[11px] text-muted-foreground">Saved as a crew upgrade you can view and print in the Card Creator.</p>
                </div>

                <!-- Constrained pick for crew cards that require a token/marker/upgrade (pg 17). -->
                <div v-if="requiredChoiceType" class="mt-4 rounded-md border border-primary/30 bg-primary/5 p-3">
                    <label class="text-xs font-medium">
                        Choose a {{ requiredChoiceType }} — listed on a crew card of a master sharing your keywords
                    </label>
                    <!-- No .number: token/marker ids are ints, upgrade-type ids are enum strings. -->
                    <select
                        v-model="selectedCrewCardChoiceId"
                        :disabled="locked"
                        class="mt-1 h-9 w-full rounded border bg-background px-2 text-sm text-foreground"
                    >
                        <option :value="null">— pick a {{ requiredChoiceType }} —</option>
                        <option v-for="opt in choiceOptions" :key="opt.id" :value="opt.id">{{ opt.name }}</option>
                    </select>
                    <p v-if="choiceOptions.length === 0" class="mt-1 text-[11px] text-muted-foreground">
                        No eligible {{ requiredChoiceType }}s found for your keywords.
                    </p>
                </div>

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
