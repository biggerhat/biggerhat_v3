<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { Head, router, usePage } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';

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
    special_notes: string | null;
}

const props = defineProps<{
    archetype?: ArchetypeRow | null;
    slug_options: Array<{ name: string; value: string }>;
}>();

const form = ref({
    slug: '' as string,
    name: '' as string,
    df: 5,
    wp: 5,
    sp: 6,
    health: 13,
    attack_actions_count: 1,
    attack_action_cost_cap: 6,
    attack_gets_trigger: false,
    tactical_actions_count: 1,
    tactical_action_cost_cap: 6,
    abilities_count: 1,
    ability_cost_cap: 6,
    special_notes: null as string | null,
});

const submit = () => {
    if (props.archetype) router.post(route('admin.campaign.leader-archetypes.update', props.archetype.slug), form.value);
    else router.post(route('admin.campaign.leader-archetypes.store'), form.value);
};

onMounted(() => {
    if (!props.archetype) return;
    Object.assign(form.value, {
        slug: props.archetype.slug,
        name: props.archetype.name,
        df: props.archetype.df,
        wp: props.archetype.wp,
        sp: props.archetype.sp,
        health: props.archetype.health,
        attack_actions_count: props.archetype.attack_actions_count,
        attack_action_cost_cap: props.archetype.attack_action_cost_cap,
        attack_gets_trigger: props.archetype.attack_gets_trigger,
        tactical_actions_count: props.archetype.tactical_actions_count,
        tactical_action_cost_cap: props.archetype.tactical_action_cost_cap,
        abilities_count: props.archetype.abilities_count,
        ability_cost_cap: props.archetype.ability_cost_cap,
        special_notes: props.archetype.special_notes,
    });
});
</script>

<template>
    <Head title="Leader Archetype — Admin" />
    <div class="container mx-auto mt-6 px-4 pb-6">
        <Card>
            <CardHeader
                ><CardTitle>{{ archetype ? 'Edit Archetype' : 'New Archetype' }}</CardTitle></CardHeader
            >
            <CardContent class="space-y-4">
                <div class="grid gap-3 md:grid-cols-2">
                    <div>
                        <Label for="slug">Archetype</Label>
                        <Select v-model="form.slug">
                            <SelectTrigger><SelectValue placeholder="Select…" /></SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="opt in slug_options" :key="opt.value" :value="opt.value">{{ opt.name }}</SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="usePage().props.errors.slug" />
                    </div>
                    <div>
                        <Label for="name">Display Name</Label>
                        <Input id="name" v-model="form.name" />
                        <InputError :message="usePage().props.errors.name" />
                    </div>
                </div>

                <div class="grid gap-3 md:grid-cols-4">
                    <div><Label>Df</Label><Input type="number" v-model.number="form.df" /></div>
                    <div><Label>Wp</Label><Input type="number" v-model.number="form.wp" /></div>
                    <div><Label>Sp</Label><Input type="number" v-model.number="form.sp" /></div>
                    <div><Label>Health</Label><Input type="number" v-model.number="form.health" /></div>
                </div>

                <fieldset class="space-y-3 rounded-md border p-3">
                    <legend class="px-1 text-xs font-medium uppercase text-muted-foreground">Attack Actions</legend>
                    <div class="grid gap-3 md:grid-cols-3">
                        <div><Label>Count</Label><Input type="number" v-model.number="form.attack_actions_count" /></div>
                        <div><Label>Cost cap (≤)</Label><Input type="number" v-model.number="form.attack_action_cost_cap" /></div>
                        <label class="mt-6 flex items-start gap-2 text-sm">
                            <Checkbox :checked="form.attack_gets_trigger" @update:checked="(v: boolean) => (form.attack_gets_trigger = v)" />
                            <span>Chosen attack action keeps one of its triggers (Heavy Hitter)</span>
                        </label>
                    </div>
                </fieldset>

                <fieldset class="space-y-3 rounded-md border p-3">
                    <legend class="px-1 text-xs font-medium uppercase text-muted-foreground">Tactical Actions</legend>
                    <div class="grid gap-3 md:grid-cols-2">
                        <div><Label>Count</Label><Input type="number" v-model.number="form.tactical_actions_count" /></div>
                        <div><Label>Cost cap (≤)</Label><Input type="number" v-model.number="form.tactical_action_cost_cap" /></div>
                    </div>
                </fieldset>

                <fieldset class="space-y-3 rounded-md border p-3">
                    <legend class="px-1 text-xs font-medium uppercase text-muted-foreground">Abilities</legend>
                    <div class="grid gap-3 md:grid-cols-2">
                        <div><Label>Count</Label><Input type="number" v-model.number="form.abilities_count" /></div>
                        <div><Label>Cost cap (≤)</Label><Input type="number" v-model.number="form.ability_cost_cap" /></div>
                    </div>
                </fieldset>

                <div>
                    <Label for="special_notes">Special Notes (e.g. Lucky Upstart starter equipment)</Label>
                    <Textarea id="special_notes" v-model="form.special_notes" rows="3" />
                </div>
            </CardContent>
            <CardFooter class="justify-end gap-2">
                <Button variant="outline" @click="router.get(route('admin.campaign.leader-archetypes.index'))">Cancel</Button>
                <Button @click="submit">{{ archetype ? 'Update' : 'Create' }}</Button>
            </CardFooter>
        </Card>
    </div>
</template>
