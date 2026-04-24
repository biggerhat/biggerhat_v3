<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import SearchableMultiselect from '@/components/SearchableMultiselect.vue';
import SearchableSelect from '@/components/SearchableSelect.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Head, router, usePage } from '@inertiajs/vue3';
import { Plus, Trash2 } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';

interface SelectId {
    id: number;
    name: string;
}

type AbilityOption = SelectId;
interface ActionOption extends SelectId { type_links: Array<{ id: number; type: string }> }
interface AllegianceOption extends SelectId { is_syndicate: boolean }
interface SpecialRuleOption extends SelectId { slug: string }

interface SideForm {
    side: 'standard' | 'glory';
    speed: number;
    defense: number;
    willpower: number;
    armor: number;
    ability_ids: string[];
    action_ids: string[];
}

type RuleParams = Record<string, number | string | null | undefined>;

interface SpecialRuleEntry {
    special_unit_rule_id: string | null;
    params: RuleParams;
}

interface Unit {
    id: number;
    slug: string;
    name: string;
    title: string | null;
    scrip: number;
    tactics: string | null;
    description: string | null;
    lore_text: string | null;
    restriction: string | null;
    combined_arms_child_id: number | null;
    sort_order: number;
    sides: Array<{
        id: number;
        side: 'standard' | 'glory';
        speed: number;
        defense: number;
        willpower: number;
        armor: number;
        abilities: Array<{ id: number }>;
        actions: Array<{ id: number }>;
    }>;
    allegiances: Array<{ id: number }>;
    special_unit_rules: Array<{ id: number; pivot: { parameters: Record<string, unknown> | null } }>;
}

const props = defineProps<{
    unit?: Unit | null;
    allegiances: AllegianceOption[];
    special_rules: SpecialRuleOption[];
    abilities: AbilityOption[];
    actions: ActionOption[];
    units: SelectId[];
    restrictions: Array<{ name: string; value: string }>;
}>();

const allegianceOptions = computed(() =>
    props.allegiances.map((a) => ({
        id: a.id,
        name: a.is_syndicate ? `${a.name} (syndicate)` : a.name,
    })),
);

const actionOptions = computed(() =>
    props.actions.map((a) => ({
        id: a.id,
        name: a.type_links?.length ? `${a.name} (${a.type_links.map((l) => l.type).join(', ')})` : a.name,
    })),
);

const ruleSlugById = computed(() => {
    const map = new Map<string, string>();
    for (const r of props.special_rules) map.set(String(r.id), r.slug);
    return map;
});

const blankSide = (side: 'standard' | 'glory'): SideForm => ({
    side,
    speed: 5,
    defense: 4,
    willpower: 4,
    armor: 1,
    ability_ids: [],
    action_ids: [],
});

const formInfo = ref({
    name: '' as string,
    title: null as string | null,
    scrip: 4 as number,
    tactics: null as string | null,
    description: null as string | null,
    lore_text: null as string | null,
    restriction: null as string | null,
    combined_arms_child_id: null as string | null,
    sort_order: 0 as number,
    allegiance_ids: [] as string[],
    sides: [blankSide('standard'), blankSide('glory')] as SideForm[],
    special_rules: [] as SpecialRuleEntry[],
});

const toInt = (v: string) => Number.parseInt(v, 10);

function ruleSlugFor(rule: SpecialRuleEntry): string | null {
    if (!rule.special_unit_rule_id) return null;
    return ruleSlugById.value.get(rule.special_unit_rule_id) ?? null;
}

function onRuleChange(rule: SpecialRuleEntry) {
    // Resetting params keeps stale fields from the previous rule type out
    // of the submitted payload.
    rule.params = {};
}

function compactParams(rule: SpecialRuleEntry): Record<string, unknown> | null {
    const out: Record<string, unknown> = {};
    for (const [k, v] of Object.entries(rule.params)) {
        if (v !== null && v !== undefined && v !== '') out[k] = v;
    }
    return Object.keys(out).length ? out : null;
}

const submit = () => {
    const payload = {
        ...formInfo.value,
        combined_arms_child_id:
            formInfo.value.combined_arms_child_id !== null
                ? toInt(formInfo.value.combined_arms_child_id)
                : null,
        allegiance_ids: formInfo.value.allegiance_ids.map(toInt),
        sides: formInfo.value.sides.map((s) => ({
            side: s.side,
            speed: s.speed,
            defense: s.defense,
            willpower: s.willpower,
            armor: s.armor,
            ability_ids: s.ability_ids.map(toInt),
            action_ids: s.action_ids.map(toInt),
        })),
        special_rules: formInfo.value.special_rules
            .filter((r) => r.special_unit_rule_id !== null)
            .map((r) => ({
                special_unit_rule_id: toInt(r.special_unit_rule_id as string),
                parameters: compactParams(r),
            })),
    };

    if (props.unit) router.post(route('admin.tos.units.update', props.unit.slug), payload);
    else router.post(route('admin.tos.units.store'), payload);
};

function addRule() {
    formInfo.value.special_rules.push({ special_unit_rule_id: null, params: {} });
}

function removeRule(idx: number) {
    formInfo.value.special_rules.splice(idx, 1);
}

onMounted(() => {
    if (!props.unit) return;
    formInfo.value.name = props.unit.name;
    formInfo.value.title = props.unit.title;
    formInfo.value.scrip = props.unit.scrip;
    formInfo.value.tactics = props.unit.tactics;
    formInfo.value.description = props.unit.description;
    formInfo.value.lore_text = props.unit.lore_text;
    formInfo.value.restriction = props.unit.restriction;
    formInfo.value.combined_arms_child_id = props.unit.combined_arms_child_id !== null
        ? String(props.unit.combined_arms_child_id)
        : null;
    formInfo.value.sort_order = props.unit.sort_order;
    formInfo.value.allegiance_ids = props.unit.allegiances.map((a) => String(a.id));
    formInfo.value.sides = ['standard', 'glory'].map((s) => {
        const existing = props.unit!.sides.find((x) => x.side === s);
        if (!existing) return blankSide(s as 'standard' | 'glory');
        return {
            side: s as 'standard' | 'glory',
            speed: existing.speed,
            defense: existing.defense,
            willpower: existing.willpower,
            armor: existing.armor,
            ability_ids: existing.abilities.map((a) => String(a.id)),
            action_ids: existing.actions.map((a) => String(a.id)),
        };
    });
    formInfo.value.special_rules = props.unit.special_unit_rules.map((r) => ({
        special_unit_rule_id: String(r.id),
        params: (r.pivot.parameters ?? {}) as RuleParams,
    }));
});
</script>

<template>
    <Head title="TOS Unit — Admin" />
    <div class="container mx-auto mt-6 space-y-4 px-4 pb-6">
        <Card>
            <CardHeader>
                <CardTitle>{{ unit ? 'Edit Unit' : 'New Unit' }}</CardTitle>
                <CardDescription>The Other Side unit (every Unit Card has a Standard and Glory side)</CardDescription>
            </CardHeader>
            <CardContent class="space-y-6">
                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <Label for="name">Name</Label>
                        <Input id="name" v-model="formInfo.name" />
                        <InputError :message="usePage().props.errors.name" />
                    </div>
                    <div>
                        <Label for="title">Title</Label>
                        <Input id="title" v-model="formInfo.title" />
                    </div>
                    <div>
                        <Label for="scrip">Scrip</Label>
                        <Input id="scrip" v-model.number="formInfo.scrip" type="number" />
                        <InputError :message="usePage().props.errors.scrip" />
                    </div>
                    <div>
                        <Label for="tactics">Tactics</Label>
                        <Input id="tactics" v-model="formInfo.tactics" placeholder="1, 2, X, *" />
                    </div>
                    <div class="md:col-span-2">
                        <Label for="description">Description</Label>
                        <Textarea id="description" v-model="formInfo.description" />
                    </div>
                    <div class="md:col-span-2">
                        <Label for="lore_text">Lore</Label>
                        <Textarea id="lore_text" v-model="formInfo.lore_text" />
                    </div>
                </div>

                <div class="grid gap-4 md:grid-cols-[2fr_1fr]">
                    <div>
                        <Label>Allegiances</Label>
                        <SearchableMultiselect
                            v-model="formInfo.allegiance_ids"
                            placeholder="Search allegiances…"
                            :options="allegianceOptions"
                            option-value="id"
                        />
                        <InputError :message="usePage().props.errors.allegiance_ids" />
                    </div>
                    <div>
                        <Label for="restriction">Neutral (any Allegiance of type)</Label>
                        <select
                            id="restriction"
                            v-model="formInfo.restriction"
                            class="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm"
                        >
                            <option :value="null">—</option>
                            <option v-for="r in restrictions" :key="r.value" :value="r.value">{{ r.name }}</option>
                        </select>
                        <p class="mt-1 text-[10px] text-muted-foreground">Set when the unit is hireable by any Allegiance of that type (Neutral pool).</p>
                        <InputError :message="usePage().props.errors.restriction" />
                    </div>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div v-for="side in formInfo.sides" :key="side.side" class="rounded-md border p-3">
                        <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-muted-foreground">{{ side.side }} side</p>
                        <div class="grid grid-cols-4 gap-2">
                            <div>
                                <Label class="text-[10px]">Sp</Label>
                                <Input v-model.number="side.speed" type="number" />
                            </div>
                            <div>
                                <Label class="text-[10px]">Df</Label>
                                <Input v-model.number="side.defense" type="number" />
                            </div>
                            <div>
                                <Label class="text-[10px]">Wp</Label>
                                <Input v-model.number="side.willpower" type="number" />
                            </div>
                            <div>
                                <Label class="text-[10px]">Ar</Label>
                                <Input v-model.number="side.armor" type="number" />
                            </div>
                        </div>

                        <div class="mt-3">
                            <p class="text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">Abilities</p>
                            <SearchableMultiselect
                                v-model="side.ability_ids"
                                placeholder="Search abilities…"
                                :options="abilities"
                                option-value="id"
                            />
                        </div>

                        <div class="mt-3">
                            <p class="text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">Actions</p>
                            <SearchableMultiselect
                                v-model="side.action_ids"
                                placeholder="Search actions…"
                                :options="actionOptions"
                                option-value="id"
                            />
                        </div>
                    </div>
                </div>

                <div>
                    <div class="mb-1 flex items-center justify-between">
                        <Label>Special Unit Rules</Label>
                        <Button type="button" variant="outline" size="sm" @click="addRule">
                            <Plus class="mr-1 size-3" /> Add Rule
                        </Button>
                    </div>
                    <div v-for="(rule, idx) in formInfo.special_rules" :key="idx" class="mb-2 rounded-md border p-3">
                        <div class="flex items-start gap-2">
                            <div class="flex-1">
                                <SearchableSelect
                                    :model-value="rule.special_unit_rule_id"
                                    placeholder="Choose rule…"
                                    :options="special_rules"
                                    option-value="id"
                                    @update:model-value="(v) => { rule.special_unit_rule_id = v; onRuleChange(rule); }"
                                />
                            </div>
                            <Button type="button" variant="ghost" size="sm" @click="removeRule(idx)"><Trash2 class="size-4" /></Button>
                        </div>

                        <template v-if="ruleSlugFor(rule) === 'fireteam'">
                            <div class="mt-3 grid gap-2 md:grid-cols-3">
                                <div>
                                    <Label class="text-[10px]">Base (mm)</Label>
                                    <Input v-model.number="rule.params.base_mm" type="number" min="0" placeholder="30" />
                                </div>
                                <div>
                                    <Label class="text-[10px]">Models per team</Label>
                                    <Input v-model.number="rule.params.models_per_team" type="number" min="1" placeholder="3" />
                                </div>
                                <div>
                                    <Label class="text-[10px]">Model size (mm)</Label>
                                    <Input v-model.number="rule.params.model_size_mm" type="number" min="0" placeholder="30" />
                                </div>
                            </div>
                        </template>
                        <template v-else-if="ruleSlugFor(rule) === 'squad'">
                            <div class="mt-3">
                                <Label class="text-[10px]">Fireteam count</Label>
                                <Input v-model.number="rule.params.fireteam_count" type="number" min="1" class="max-w-[200px]" placeholder="4" />
                            </div>
                        </template>
                        <template v-else-if="ruleSlugFor(rule) === 'reserves'">
                            <div class="mt-3">
                                <Label class="text-[10px]">Reserves X</Label>
                                <Input v-model.number="rule.params.x" type="number" min="0" class="max-w-[200px]" placeholder="1" />
                            </div>
                        </template>
                        <template v-else-if="ruleSlugFor(rule) === 'combined_arms'">
                            <div class="mt-3">
                                <Label class="text-[10px]">Combined Arms child unit</Label>
                                <SearchableSelect
                                    v-model="formInfo.combined_arms_child_id"
                                    placeholder="Search units…"
                                    :options="units"
                                    option-value="id"
                                />
                                <p class="mt-1 text-[10px] text-muted-foreground">
                                    The embedded child card that activates with this unit. Stored on the unit itself, not this pivot.
                                </p>
                            </div>
                        </template>
                        <p
                            v-else-if="ruleSlugFor(rule)"
                            class="mt-3 text-[11px] text-muted-foreground"
                        >
                            No parameters required.
                        </p>
                    </div>
                </div>
            </CardContent>
            <CardFooter class="flex justify-between px-6 pb-6">
                <Button variant="outline" @click="router.get(route('admin.tos.units.index'))">Cancel</Button>
                <Button @click="submit">Save</Button>
            </CardFooter>
        </Card>
    </div>
</template>
