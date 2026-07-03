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

interface AdvancementAbilityRow {
    id: number;
    flip_value: number | null;
    is_joker: boolean;
    is_always_available: boolean;
    talent_name: string;
    effect_text: string;
    ability_id: number | null;
    suits: string | null;
    defensive_ability_type: string | null;
}

const props = defineProps<{
    item?: AdvancementAbilityRow | null;
    abilities: Array<{ name: string; value: number }>;
    defensive_ability_types: Array<{ name: string; value: string }>;
}>();

const form = ref({
    flip_value: null as number | null,
    is_joker: false,
    is_always_available: false,
    talent_name: '' as string,
    effect_text: '' as string,
    ability_id: null as number | null,
    suits: null as string | null,
    defensive_ability_type: null as string | null,
});

const submit = () => {
    if (props.item) router.post(route('admin.campaign.advancement-ability.update', props.item.id), form.value);
    else router.post(route('admin.campaign.advancement-ability.store'), form.value);
};

onMounted(() => {
    if (!props.item) return;
    Object.assign(form.value, props.item);
});
</script>

<template>
    <Head title="Campaign — Ability Advancement — Admin" />
    <div class="container mx-auto mt-6 px-4 pb-6">
        <Card>
            <CardHeader>
                <CardTitle>{{ item ? 'Edit' : 'New' }} Ability Advancement</CardTitle>
            </CardHeader>
            <CardContent class="space-y-4">
                <div class="grid gap-3 md:grid-cols-2">
                    <div>
                        <Label for="talent_name">Talent Name</Label>
                        <Input id="talent_name" v-model="form.talent_name" />
                        <InputError :message="usePage().props.errors.talent_name" />
                    </div>
                    <div>
                        <Label for="flip_value">Flip Value (1–13, blank for joker/always)</Label>
                        <Input id="flip_value" type="number" min="1" max="13" v-model.number="form.flip_value" />
                    </div>
                </div>

                <div>
                    <Label for="effect_text">Effect Text</Label>
                    <Textarea id="effect_text" v-model="form.effect_text" rows="3" />
                    <InputError :message="usePage().props.errors.effect_text" />
                </div>

                <div>
                    <Label for="ability_id">Ability Lookup</Label>
                    <Select
                        :model-value="form.ability_id?.toString() ?? undefined"
                        @update:model-value="(v) => (form.ability_id = v ? Number(v) : null)"
                    >
                        <SelectTrigger><SelectValue placeholder="Bespoke — no lookup" /></SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="opt in abilities" :key="opt.value" :value="opt.value.toString()">{{ opt.name }}</SelectItem>
                        </SelectContent>
                    </Select>
                    <p class="text-[10px] text-muted-foreground">
                        Set when this row grants an ability that already exists on some model's card — the fields below are ignored.
                    </p>
                </div>

                <div v-if="!form.ability_id" class="grid gap-3 rounded-md border p-3 md:grid-cols-2">
                    <div>
                        <Label for="suits">Suits</Label>
                        <Input id="suits" v-model="form.suits" />
                    </div>
                    <div>
                        <Label for="defensive_ability_type">Defensive Ability Type</Label>
                        <Select v-model="form.defensive_ability_type">
                            <SelectTrigger><SelectValue placeholder="—" /></SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="opt in defensive_ability_types" :key="opt.value" :value="opt.value">{{ opt.name }}</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </div>

                <label class="flex items-start gap-2 text-sm">
                    <Checkbox :checked="form.is_always_available" @update:checked="(v: boolean) => (form.is_always_available = v)" />
                    <span>Always available (no flip)</span>
                </label>
                <label class="flex items-start gap-2 text-sm">
                    <Checkbox :checked="form.is_joker" @update:checked="(v: boolean) => (form.is_joker = v)" />
                    <span>Any Joker — free pick (cost &lt;= 10, shares keyword)</span>
                </label>
            </CardContent>
            <CardFooter class="justify-end gap-2">
                <Button variant="outline" @click="router.get(route('admin.campaign.advancement-ability.index'))">Cancel</Button>
                <Button @click="submit">{{ item ? 'Update' : 'Create' }}</Button>
            </CardFooter>
        </Card>
    </div>
</template>
