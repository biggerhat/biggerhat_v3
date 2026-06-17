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
    triggers: TriggerData[];
}

interface AbilityData {
    name: string;
    suits: string | null;
    defensive_ability_type: string | null;
    costs_stone: boolean;
    description: string | null;
    source_id: number | null;
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
    leader: LeaderData | null;
    archetypes: ArchetypeRow[];
    archetype_enum: SelectOpt[];
    tag_enum: SelectOpt[];
    faction_enum: SelectOpt[];
    base_enum: SelectOpt[];
    all_keywords: KeywordRow[];
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
});

const archetype = computed(() => props.archetypes.find((a) => a.slug === form.value.archetype) ?? null);

const attackActions = computed(() => form.value.actions.filter((a) => a.category === 'attack'));
const tacticalActions = computed(() => form.value.actions.filter((a) => a.category === 'tactical'));

const filteredKeywordsForFaction = computed(() => {
    if (!form.value.faction) return props.all_keywords;
    // Show all keywords but mark which are in the declared faction. Allows
    // cross-faction pairs as long as at least one is in the declared faction.
    return [...props.all_keywords].sort((a, b) => {
        const aIn = a.faction === form.value.faction;
        const bIn = b.faction === form.value.faction;
        if (aIn === bIn) return a.name.localeCompare(b.name);
        return aIn ? -1 : 1;
    });
});

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
    const res = await fetch(url.toString());
    if (!res.ok) return;
    actionResults.value = await res.json();
};

watch(actionSearch, (v) => {
    if (v.length < 2) actionResults.value = [];
    else searchActions();
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
        // Heavy Hitter keeps only ONE trigger — default to first, user can change.
        cloned.triggers = cloned.triggers.slice(0, 1);
    }
    form.value.actions.push(cloned);
    actionSearch.value = '';
    actionResults.value = [];
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
const newCharacteristic = ref('');
const addCharacteristic = () => {
    const c = newCharacteristic.value.trim();
    if (!c || form.value.characteristics.length >= 2 || form.value.characteristics.includes(c)) return;
    form.value.characteristics.push(c);
    newCharacteristic.value = '';
};

const removeCharacteristic = (i: number) => form.value.characteristics.splice(i, 1);

// ───────── Submit ─────────
const submitting = ref(false);
const submit = async () => {
    submitting.value = true;
    router.post(route('campaigns.crews.leader.update', [props.campaign.id, props.crew.share_code]), form.value as Record<string, unknown>, {
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
            <div class="flex items-center px-2 py-2 md:py-4">
                <Link :href="route('campaigns.show', campaign.id)">
                    <Button variant="outline">← Back to Campaign</Button>
                </Link>
            </div>
        </template>
    </PageBanner>

    <div class="container mx-auto max-w-5xl px-4 pb-16">
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
                </div>
                <div>
                    <Label>Faction (declared)</Label>
                    <Select v-model="form.faction">
                        <SelectTrigger><SelectValue placeholder="Pick faction" /></SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="opt in faction_enum" :key="opt.value" :value="opt.value">{{ opt.name }}</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div>
                    <Label>Tag</Label>
                    <Select v-model="form.tag">
                        <SelectTrigger><SelectValue /></SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="opt in tag_enum" :key="opt.value" :value="opt.value">{{ opt.name }}</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div>
                    <Label>Keyword 1</Label>
                    <Select
                        :model-value="form.keyword_1_id ? String(form.keyword_1_id) : ''"
                        @update:model-value="(v: string | number) => (form.keyword_1_id = Number(v))"
                    >
                        <SelectTrigger><SelectValue placeholder="Pick keyword" /></SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="k in filteredKeywordsForFaction" :key="k.id" :value="String(k.id)">
                                {{ k.name }} <span class="text-[10px] text-muted-foreground">({{ k.faction }})</span>
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div>
                    <Label>Keyword 2</Label>
                    <Select
                        :model-value="form.keyword_2_id ? String(form.keyword_2_id) : ''"
                        @update:model-value="(v: string | number) => (form.keyword_2_id = Number(v))"
                    >
                        <SelectTrigger><SelectValue placeholder="Pick keyword" /></SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="k in filteredKeywordsForFaction" :key="k.id" :value="String(k.id)">
                                {{ k.name }} <span class="text-[10px] text-muted-foreground">({{ k.faction }})</span>
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div>
                    <Label>Size (1–4)</Label>
                    <Input type="number" min="1" max="4" v-model.number="form.size" />
                </div>
                <div>
                    <Label>Base</Label>
                    <Select :model-value="String(form.base)" @update:model-value="(v: string | number) => (form.base = Number(v))">
                        <SelectTrigger><SelectValue /></SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="opt in base_enum" :key="opt.value" :value="String(opt.value)">{{ opt.name }}</SelectItem>
                        </SelectContent>
                    </Select>
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
                        <div v-if="a.category === 'attack'" class="flex items-center justify-between rounded-md border p-2 text-sm">
                            <span
                                >{{ a.name }} <Badge variant="outline" class="text-[10px]">cost {{ a.stone_cost }}</Badge></span
                            >
                            <Button variant="ghost" size="sm" @click="removeAction(idx)">Remove</Button>
                        </div>
                    </div>
                </div>

                <div v-if="tacticalActions.length" class="space-y-1">
                    <p class="text-xs font-medium uppercase text-muted-foreground">Tactical</p>
                    <div v-for="(a, idx) in form.actions" :key="`tac-${idx}`">
                        <div v-if="a.category === 'tactical'" class="flex items-center justify-between rounded-md border p-2 text-sm">
                            <span
                                >{{ a.name }} <Badge variant="outline" class="text-[10px]">cost {{ a.stone_cost }}</Badge></span
                            >
                            <Button variant="ghost" size="sm" @click="removeAction(idx)">Remove</Button>
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
                <div v-for="(ab, idx) in form.abilities" :key="`abi-${idx}`" class="flex items-center justify-between rounded-md border p-2 text-sm">
                    <span class="font-medium">{{ ab.name }}</span>
                    <Button variant="ghost" size="sm" @click="removeAbility(idx)">Remove</Button>
                </div>
                <InputError :message="usePage().props.errors.abilities" />
            </CardContent>
        </Card>

        <Card class="mb-6">
            <CardHeader>
                <CardTitle>4. Characteristics (optional, max 2)</CardTitle>
                <p class="text-sm text-muted-foreground">e.g. Living, Construct, Spirit, Undead</p>
            </CardHeader>
            <CardContent class="space-y-3">
                <div class="flex gap-2">
                    <Input v-model="newCharacteristic" placeholder="Add characteristic" @keydown.enter.prevent="addCharacteristic" />
                    <Button variant="outline" @click="addCharacteristic" :disabled="form.characteristics.length >= 2">Add</Button>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Badge v-for="(c, i) in form.characteristics" :key="c" class="cursor-pointer" @click="removeCharacteristic(i)"> {{ c }} × </Badge>
                </div>
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
