<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import PageBanner from '@/components/PageBanner.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useToast } from '@/composables/useToast';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const toast = useToast();

interface SelectOpt {
    name: string;
    value: string | number;
}

interface ArchetypeRow {
    id: number;
    slug: string;
    name: string;
    df: number;
    wp: number;
    sp: number;
    health: number;
    attack_actions_count: number;
    attack_action_cost_cap: number;
    attack_gets_trigger: boolean;
    tactical_actions_count: number;
    tactical_action_cost_cap: number;
    abilities_count: number;
    ability_cost_cap: number;
}

interface KeywordRow {
    id: number;
    name: string;
    factions: string[];
}

interface TriggerData {
    name: string;
    suits: string | null;
    stone_cost: number;
    description: string | null;
    source_id: number | null;
}

interface ActionData {
    name: string;
    type: string;
    category: 'attack' | 'tactical';
    is_signature: boolean;
    stone_cost: number;
    range: number | string | null;
    range_type: string | null;
    stat: number | string | null;
    stat_suits: string | null;
    stat_modifier: string | null;
    resisted_by: string | null;
    target_number: number | string | null;
    target_suits: string | null;
    damage: string | null;
    description: string | null;
    source_id: number | null;
    // The model this action was picked from — sent so the server can verify it's
    // a valid (non-master/non-totem, cost-bearing, in-keyword) source (pg 17).
    source_character_id: number | null;
    triggers: TriggerData[];
    // Heavy Hitter keeps ONE trigger on its starting attack — the full set the
    // player chooses from is held here client-side (stripped before submit).
    available_triggers?: TriggerData[];
}

interface AbilityData {
    name: string;
    suits: string | null;
    defensive_ability_type: string | null;
    costs_stone: boolean;
    description: string | null;
    source_id: number | null;
    source_character_id: number | null;
}

interface CrewData {
    id: number;
    share_code: string;
    name: string;
    faction: string | null;
    keyword_1_id: number | null;
    keyword_2_id: number | null;
}

interface CampaignData {
    id: number;
    name: string;
    status: string;
}

interface LeaderData {
    id: number;
    name: string;
    archetype: string | null;
    tag: string | null;
    faction: string;
    size: number;
    base: number;
    characteristics: string[];
    actions: ActionData[];
    abilities: AbilityData[];
    keywords: Array<{ id: number; name: string }>;
}

const props = defineProps<{
    campaign: CampaignData;
    crew: CrewData;
    campaign_started: boolean;
    leader: LeaderData | null;
    archetypes: ArchetypeRow[];
    archetype_enum: SelectOpt[];
    tag_enum: SelectOpt[];
    faction_enum: SelectOpt[];
    base_enum: SelectOpt[];
    all_keywords: KeywordRow[];
    characteristic_options: string[];
    equipment_catalog: Array<{ id: number; name: string; br: number | null; is_always_available: boolean }>;
    lucky_upstart_equipment_id: number | null;
}>();

const form = ref({
    name: props.leader?.name ?? '',
    archetype: props.leader?.archetype ?? 'generalist',
    tag: props.leader?.tag ?? 'bruiser',
    faction: props.leader?.faction ?? props.crew.faction ?? '',
    keyword_1_id: props.leader?.keywords?.[0]?.id ?? props.crew.keyword_1_id ?? null,
    keyword_2_id: props.leader?.keywords?.[1]?.id ?? props.crew.keyword_2_id ?? null,
    size: props.leader?.size ?? 2,
    base: props.leader?.base ?? 30,
    characteristics: props.leader?.characteristics ?? ([] as string[]),
    actions: props.leader?.actions ?? ([] as ActionData[]),
    abilities: props.leader?.abilities ?? ([] as AbilityData[]),
    lucky_upstart_equipment_id: props.lucky_upstart_equipment_id ?? null,
    lucky_upstart_flip_value: null as number | null,
});

const archetype = computed(() => props.archetypes.find((a) => a.slug === form.value.archetype) ?? null);

// Lucky Upstart flips a card, then may only take equipment whose BR matches
// that flip exactly (pg 17) — same BR-match rule the Aftermath Barter step
// enforces, just gated on a single flip instead of a purchase list.
const eligibleLuckyUpstartEquipment = computed(() => {
    const flip = form.value.lucky_upstart_flip_value;
    if (flip == null) return props.equipment_catalog;
    return props.equipment_catalog.filter((e) => e.is_always_available || e.br === flip);
});

watch(
    () => form.value.lucky_upstart_flip_value,
    () => {
        if (!eligibleLuckyUpstartEquipment.value.some((e) => e.id === form.value.lucky_upstart_equipment_id)) {
            form.value.lucky_upstart_equipment_id = null;
        }
    },
);

const attackActions = computed(() => form.value.actions.filter((a) => a.category === 'attack'));
const tacticalActions = computed(() => form.value.actions.filter((a) => a.category === 'tactical'));

// KW1 is restricted to keywords with at least one model in the selected faction.
const keywordsForKw1 = computed(() => {
    if (!form.value.faction) return [];
    return props.all_keywords.filter((k) => k.factions.includes(form.value.faction));
});

// KW2 can be any keyword except the one already chosen for KW1.
const keywordsForKw2 = computed(() => {
    return props.all_keywords.filter((k) => k.id !== form.value.keyword_1_id);
});

// Cascade resets: changing faction clears everything downstream.
watch(
    () => form.value.faction,
    () => {
        form.value.keyword_1_id = null;
        form.value.keyword_2_id = null;
        form.value.actions = [];
        form.value.abilities = [];
    },
);

// Changing KW1 clears KW2 and picked actions/abilities.
watch(
    () => form.value.keyword_1_id,
    () => {
        form.value.keyword_2_id = null;
        form.value.actions = [];
        form.value.abilities = [];
    },
);

// ───────── Action picker ─────────
const actionSearch = ref('');
const actionResults = ref<ActionData[]>([]);
const actionPickerCategory = ref<'attack' | 'tactical'>('attack');

const actionCap = computed(() =>
    actionPickerCategory.value === 'attack' ? (archetype.value?.attack_action_cost_cap ?? 99) : (archetype.value?.tactical_action_cost_cap ?? 99),
);

const searchActions = async () => {
    if (actionSearch.value.length < 2) {
        actionResults.value = [];
        return;
    }
    const url = new URL(route('campaigns.crews.leader.search.actions', [props.campaign.id, props.crew.share_code]), window.location.origin);
    url.searchParams.set('q', actionSearch.value);
    url.searchParams.set('max_cost', String(actionCap.value));
    // Only show actions of the category being picked (no Tacticals under Attack).
    url.searchParams.set('type', actionPickerCategory.value);
    // Use the keywords currently selected in the form so the picker works before
    // the leader/crew is saved (the server falls back to the crew's saved ones).
    if (form.value.keyword_1_id) url.searchParams.set('keyword_1_id', String(form.value.keyword_1_id));
    if (form.value.keyword_2_id) url.searchParams.set('keyword_2_id', String(form.value.keyword_2_id));
    const res = await fetch(url.toString());
    if (!res.ok) return;
    actionResults.value = await res.json();
};

watch(actionSearch, (v) => {
    if (v.length < 2) actionResults.value = [];
    else searchActions();
});

// Re-run the search when toggling Attack/Tactical so the type filter applies.
watch(actionPickerCategory, () => {
    if (actionSearch.value.length >= 2) searchActions();
});

const addAction = (a: ActionData) => {
    const limit =
        actionPickerCategory.value === 'attack' ? (archetype.value?.attack_actions_count ?? 0) : (archetype.value?.tactical_actions_count ?? 0);
    const current = actionPickerCategory.value === 'attack' ? attackActions.value.length : tacticalActions.value.length;
    if (current >= limit) {
        toast.warning('Action limit reached', {
            description: `${archetype.value?.name ?? 'Archetype'} allows at most ${limit} ${actionPickerCategory.value} action(s).`,
        });
        return;
    }

    const cloned: ActionData = JSON.parse(JSON.stringify(a));
    cloned.category = actionPickerCategory.value;
    // Strip triggers unless Heavy Hitter on an attack action — per pg 17.
    if (!(archetype.value?.attack_gets_trigger && cloned.category === 'attack')) {
        cloned.triggers = [];
    } else {
        // Heavy Hitter keeps ONE trigger — default to the first; the full set is
        // kept in available_triggers so the player can pick a different one.
        cloned.available_triggers = [...cloned.triggers];
        cloned.triggers = cloned.triggers.slice(0, 1);
    }
    form.value.actions.push(cloned);
    actionSearch.value = '';
    actionResults.value = [];
};

// Heavy Hitter: choose which of the source attack's triggers to keep.
const setKeptTrigger = (actionIdx: number, sourceId: number | null) => {
    const action = form.value.actions[actionIdx];
    const chosen = action.available_triggers?.find((t) => t.source_id === sourceId);
    if (chosen) action.triggers = [chosen];
};

const removeAction = (idx: number) => {
    form.value.actions.splice(idx, 1);
};

// ───────── Ability picker ─────────
const abilitySearch = ref('');
const abilityResults = ref<AbilityData[]>([]);

const searchAbilities = async () => {
    if (abilitySearch.value.length < 2) {
        abilityResults.value = [];
        return;
    }
    const url = new URL(route('campaigns.crews.leader.search.abilities', [props.campaign.id, props.crew.share_code]), window.location.origin);
    url.searchParams.set('q', abilitySearch.value);
    // Cap on the source ally's cost (rulebook pg 17).
    url.searchParams.set('max_cost', String(archetype.value?.ability_cost_cap ?? 99));
    // In-form keywords so the picker works before the leader/crew is saved.
    if (form.value.keyword_1_id) url.searchParams.set('keyword_1_id', String(form.value.keyword_1_id));
    if (form.value.keyword_2_id) url.searchParams.set('keyword_2_id', String(form.value.keyword_2_id));
    const res = await fetch(url.toString());
    if (!res.ok) return;
    abilityResults.value = await res.json();
};

watch(abilitySearch, (v) => {
    if (v.length < 2) abilityResults.value = [];
    else searchAbilities();
});

const addAbility = (a: AbilityData) => {
    if (form.value.abilities.length >= (archetype.value?.abilities_count ?? 0)) {
        toast.warning('Ability limit reached', {
            description: `${archetype.value?.name ?? 'Archetype'} allows at most ${archetype.value?.abilities_count ?? 0} ability/ies.`,
        });
        return;
    }
    form.value.abilities.push(JSON.parse(JSON.stringify(a)));
    abilitySearch.value = '';
    abilityResults.value = [];
};

const removeAbility = (idx: number) => {
    form.value.abilities.splice(idx, 1);
};

// ───────── Characteristics ─────────
// Picked from the official catalog (props.characteristic_options), not freeform.
const availableCharacteristics = computed(() => props.characteristic_options.filter((c) => !form.value.characteristics.includes(c)));
const addCharacteristic = (c: string) => {
    if (!c || form.value.characteristics.length >= 2 || form.value.characteristics.includes(c)) return;
    form.value.characteristics.push(c);
};

const removeCharacteristic = (i: number) => form.value.characteristics.splice(i, 1);

// ───────── Submit ─────────
const submitting = ref(false);
const submit = async () => {
    submitting.value = true;
    // Strip the client-only available_triggers helper before posting.
    const payload = {
        ...form.value,
        actions: form.value.actions.map((a) => {
            const copy: ActionData = { ...a };
            delete copy.available_triggers;
            return copy;
        }),
    };
    router.post(route('campaigns.crews.leader.update', [props.campaign.id, props.crew.share_code]), payload as Record<string, unknown>, {
        onFinish: () => (submitting.value = false),
    });
};
</script>

<template>
    <Head :title="`Leader Builder — ${campaign.name}`" />

    <PageBanner title="Leader Builder">
        <template #subtitle>
            <div class="px-2">
                <span class="text-sm text-muted-foreground">
                    {{ campaign.name }} • <strong class="text-foreground">{{ crew.name }}</strong>
                </span>
            </div>
        </template>
        <template #actions>
            <div class="flex flex-wrap items-center gap-2 px-2 py-2 md:py-4">
                <Link :href="route('campaigns.crews.arsenal.show', [campaign.id, crew.share_code])">
                    <Button variant="outline">← Back to Arsenal Sheet</Button>
                </Link>
                <Link :href="route('campaigns.show', campaign.id)">
                    <Button variant="ghost">Campaign</Button>
                </Link>
            </div>
        </template>
    </PageBanner>

    <div class="container mx-auto max-w-5xl px-4 pb-16">
        <div v-if="Object.keys(usePage().props.errors).length" class="mb-6 rounded-md border border-destructive/30 bg-destructive/10 p-4">
            <p class="mb-1 text-sm font-medium text-destructive">Please fix the following errors:</p>
            <ul class="ml-4 list-disc space-y-0.5 text-sm text-destructive">
                <li v-for="(msg, key) in usePage().props.errors" :key="key">{{ msg }}</li>
            </ul>
        </div>
        <Card class="mb-6">
            <CardHeader><CardTitle>1. Identity</CardTitle></CardHeader>
            <CardContent class="grid gap-3 md:grid-cols-2">
                <div>
                    <Label for="name">Leader Name</Label>
                    <Input id="name" v-model="form.name" placeholder="Mortimer Vance" />
                    <InputError :message="usePage().props.errors.name" />
                </div>
                <div>
                    <Label for="archetype">Archetype</Label>
                    <Select v-model="form.archetype">
                        <SelectTrigger><SelectValue /></SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="opt in archetype_enum" :key="opt.value" :value="opt.value">{{ opt.name }}</SelectItem>
                        </SelectContent>
                    </Select>
                    <p v-if="archetype" class="mt-1 text-[11px] text-muted-foreground">
                        Df {{ archetype.df }} • Wp {{ archetype.wp }} • Sp {{ archetype.sp }} • HP {{ archetype.health }}
                        <br />
                        Attack: {{ archetype.attack_actions_count }} (≤ cost {{ archetype.attack_action_cost_cap }})
                        <span v-if="archetype.attack_gets_trigger">+ 1 trigger</span>
                        • Tactical: {{ archetype.tactical_actions_count }} (≤ cost {{ archetype.tactical_action_cost_cap }}) • Abilities:
                        {{ archetype.abilities_count }}
                    </p>
                    <InputError :message="usePage().props.errors.archetype" />
                </div>
                <div v-if="form.archetype === 'lucky_upstart'" class="grid gap-3 md:col-span-2 md:grid-cols-[120px_1fr]">
                    <div>
                        <Label for="lucky_upstart_flip_value">Flip (1–13)</Label>
                        <Input id="lucky_upstart_flip_value" type="number" min="1" max="13" v-model.number="form.lucky_upstart_flip_value" />
                        <InputError :message="usePage().props.errors.lucky_upstart_flip_value" />
                    </div>
                    <div>
                        <Label>Free starter equipment (Lucky Upstart)</Label>
                        <Select
                            :model-value="form.lucky_upstart_equipment_id?.toString() ?? '__none__'"
                            @update:model-value="(v) => (form.lucky_upstart_equipment_id = v === '__none__' ? null : Number(v))"
                        >
                            <SelectTrigger class="h-9 w-full text-sm text-foreground">
                                <SelectValue placeholder="— pick equipment —" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="__none__">— pick equipment —</SelectItem>
                                <SelectItem v-for="e in eligibleLuckyUpstartEquipment" :key="e.id" :value="e.id.toString()">{{ e.name }}</SelectItem>
                            </SelectContent>
                        </Select>
                        <p class="mt-1 text-[11px] text-muted-foreground">
                            Flip a card (may not be cheated) — take equipment whose BR matches exactly. Doesn't count toward Campaign Rating (pg 17).
                        </p>
                        <InputError :message="usePage().props.errors.lucky_upstart_equipment_id" />
                    </div>
                </div>
                <div>
                    <Label>Faction (declared)</Label>
                    <Select v-model="form.faction">
                        <SelectTrigger><SelectValue placeholder="Pick faction" /></SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="opt in faction_enum" :key="opt.value" :value="opt.value">{{ opt.name }}</SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="usePage().props.errors.faction" />
                </div>
                <div>
                    <Label>Tag</Label>
                    <Select v-model="form.tag">
                        <SelectTrigger><SelectValue /></SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="opt in tag_enum" :key="opt.value" :value="opt.value">{{ opt.name }}</SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="usePage().props.errors.tag" />
                </div>
                <div>
                    <Label>Keyword 1</Label>
                    <Select
                        :model-value="form.keyword_1_id ? String(form.keyword_1_id) : ''"
                        :disabled="!form.faction"
                        @update:model-value="(v: string | number) => (form.keyword_1_id = Number(v))"
                    >
                        <SelectTrigger><SelectValue placeholder="Pick faction first" /></SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="k in keywordsForKw1" :key="k.id" :value="String(k.id)">
                                {{ k.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="usePage().props.errors.keyword_1_id" />
                </div>
                <div>
                    <Label>Keyword 2</Label>
                    <Select
                        :model-value="form.keyword_2_id ? String(form.keyword_2_id) : ''"
                        :disabled="!form.keyword_1_id"
                        @update:model-value="(v: string | number) => (form.keyword_2_id = Number(v))"
                    >
                        <SelectTrigger><SelectValue placeholder="Pick KW1 first" /></SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="k in keywordsForKw2" :key="k.id" :value="String(k.id)">
                                {{ k.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="usePage().props.errors.keyword_2_id" />
                </div>
                <div>
                    <Label>Size (1–4)</Label>
                    <Input type="number" min="1" max="4" v-model.number="form.size" />
                    <InputError :message="usePage().props.errors.size" />
                </div>
                <div>
                    <Label>Base</Label>
                    <Select :model-value="String(form.base)" @update:model-value="(v: string | number) => (form.base = Number(v))">
                        <SelectTrigger><SelectValue /></SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="opt in base_enum" :key="opt.value" :value="String(opt.value)">{{ opt.name }}</SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="usePage().props.errors.base" />
                </div>
            </CardContent>
        </Card>

        <Card class="mb-6">
            <CardHeader>
                <CardTitle>2. Actions (attack &amp; tactical)</CardTitle>
                <p class="text-sm text-muted-foreground">
                    Only actions on models sharing one of the chosen keywords (non-master/non-totem) appear. The picker enforces the cost cap.
                </p>
            </CardHeader>
            <CardContent class="space-y-4">
                <div class="flex items-center gap-2">
                    <Label>Pick for:</Label>
                    <Select v-model="actionPickerCategory">
                        <SelectTrigger class="w-40"><SelectValue /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="attack">Attack</SelectItem>
                            <SelectItem value="tactical">Tactical</SelectItem>
                        </SelectContent>
                    </Select>
                    <Input v-model="actionSearch" placeholder="Search by name…" class="flex-1" />
                </div>
                <div v-if="actionResults.length" class="max-h-64 space-y-1 overflow-y-auto rounded-md border p-2">
                    <button
                        v-for="a in actionResults"
                        :key="a.source_id ?? a.name"
                        @click="addAction(a)"
                        class="w-full rounded-sm px-2 py-1.5 text-left text-sm hover:bg-muted"
                    >
                        <span class="font-medium">{{ a.name }}</span>
                        <span class="ml-2 text-[10px] text-muted-foreground">cost {{ a.stone_cost ?? 0 }} • {{ a.type }}</span>
                    </button>
                </div>

                <div v-if="attackActions.length" class="space-y-1">
                    <p class="text-xs font-medium uppercase text-muted-foreground">Attack</p>
                    <div v-for="(a, idx) in form.actions" :key="`atk-${idx}`">
                        <div v-if="a.category === 'attack'" class="space-y-1 rounded-md border p-2 text-sm">
                            <div class="flex items-center justify-between">
                                <span
                                    >{{ a.name }} <Badge variant="outline" class="text-[10px]">cost {{ a.stone_cost }}</Badge></span
                                >
                                <Button variant="ghost" size="sm" @click="removeAction(idx)">Remove</Button>
                            </div>
                            <!-- Heavy Hitter keeps one trigger — pick which when the source has several. -->
                            <div v-if="(a.available_triggers?.length ?? 0) > 1" class="flex items-center gap-2 text-[11px] text-muted-foreground">
                                <span>Trigger:</span>
                                <Select
                                    :model-value="a.triggers[0]?.source_id?.toString() ?? '__none__'"
                                    @update:model-value="(v) => setKeptTrigger(idx, v === '__none__' ? null : Number(v))"
                                >
                                    <SelectTrigger class="h-7 w-auto gap-1 text-foreground">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="t in a.available_triggers"
                                            :key="t.source_id ?? t.name"
                                            :value="t.source_id?.toString() ?? '__none__'"
                                        >
                                            {{ t.name }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <p v-else-if="a.triggers.length" class="text-[11px] text-muted-foreground">Trigger: {{ a.triggers[0].name }}</p>
                            <InputError :message="(usePage().props.errors as Record<string, string>)[`actions.${idx}.source_character_id`]" />
                        </div>
                    </div>
                </div>

                <div v-if="tacticalActions.length" class="space-y-1">
                    <p class="text-xs font-medium uppercase text-muted-foreground">Tactical</p>
                    <div v-for="(a, idx) in form.actions" :key="`tac-${idx}`">
                        <div v-if="a.category === 'tactical'" class="space-y-1 rounded-md border p-2 text-sm">
                            <div class="flex items-center justify-between">
                                <span
                                    >{{ a.name }} <Badge variant="outline" class="text-[10px]">cost {{ a.stone_cost }}</Badge></span
                                >
                                <Button variant="ghost" size="sm" @click="removeAction(idx)">Remove</Button>
                            </div>
                            <InputError :message="(usePage().props.errors as Record<string, string>)[`actions.${idx}.source_character_id`]" />
                        </div>
                    </div>
                </div>
                <InputError :message="usePage().props.errors.actions" />
            </CardContent>
        </Card>

        <Card class="mb-6">
            <CardHeader>
                <CardTitle>3. Abilities</CardTitle>
            </CardHeader>
            <CardContent class="space-y-4">
                <Input v-model="abilitySearch" placeholder="Search by name…" />
                <div v-if="abilityResults.length" class="max-h-64 space-y-1 overflow-y-auto rounded-md border p-2">
                    <button
                        v-for="a in abilityResults"
                        :key="a.source_id ?? a.name"
                        @click="addAbility(a)"
                        class="w-full rounded-sm px-2 py-1.5 text-left text-sm hover:bg-muted"
                    >
                        <span class="font-medium">{{ a.name }}</span>
                    </button>
                </div>
                <div v-for="(ab, idx) in form.abilities" :key="`abi-${idx}`" class="space-y-1 rounded-md border p-2 text-sm">
                    <div class="flex items-center justify-between">
                        <span class="font-medium">{{ ab.name }}</span>
                        <Button variant="ghost" size="sm" @click="removeAbility(idx)">Remove</Button>
                    </div>
                    <InputError :message="(usePage().props.errors as Record<string, string>)[`abilities.${idx}.source_character_id`]" />
                </div>
                <InputError :message="usePage().props.errors.abilities" />
            </CardContent>
        </Card>

        <Card class="mb-6">
            <CardHeader>
                <CardTitle>4. Characteristics (optional, max 2)</CardTitle>
                <p class="text-sm text-muted-foreground">Pick from the official characteristics (e.g. Living, Construct, Undead).</p>
            </CardHeader>
            <CardContent class="space-y-3">
                <Select
                    :model-value="''"
                    :disabled="form.characteristics.length >= 2 || !availableCharacteristics.length"
                    @update:model-value="(v) => addCharacteristic(String(v))"
                >
                    <SelectTrigger><SelectValue placeholder="Add a characteristic" /></SelectTrigger>
                    <SelectContent>
                        <SelectItem v-for="c in availableCharacteristics" :key="c" :value="c">{{ c }}</SelectItem>
                    </SelectContent>
                </Select>
                <div class="flex flex-wrap gap-2">
                    <Badge v-for="(c, i) in form.characteristics" :key="c" class="cursor-pointer" @click="removeCharacteristic(i)"> {{ c }} × </Badge>
                </div>
                <InputError :message="usePage().props.errors.characteristics" />
            </CardContent>
        </Card>

        <div class="flex justify-end gap-2">
            <Link :href="route('campaigns.show', campaign.id)">
                <Button variant="outline">Cancel</Button>
            </Link>
            <Button @click="submit" :disabled="submitting">{{ leader ? 'Update Leader' : 'Save Leader' }}</Button>
        </div>
    </div>
</template>
