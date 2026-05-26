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

interface InjuryRow {
    id: number;
    name: string;
    body: string;
    flip_value: number | null;
    suit_pool: string;
    reflip_if_no_triggers: boolean;
    reflip_if_master_or_totem: boolean;
    is_traitor: boolean;
    is_close_call: boolean;
    annihilates_model: boolean;
}

const props = defineProps<{ item?: InjuryRow | null }>();

const form = ref({
    name: '',
    body: '',
    flip_value: null as number | null,
    suit_pool: 'pc',
    reflip_if_no_triggers: false,
    reflip_if_master_or_totem: false,
    is_traitor: false,
    is_close_call: false,
    annihilates_model: false,
});

const submit = () => {
    if (props.item) router.post(route('admin.campaign.injuries.update', props.item.id), form.value);
    else router.post(route('admin.campaign.injuries.store'), form.value);
};

onMounted(() => {
    if (!props.item) return;
    Object.assign(form.value, props.item);
});
</script>

<template>
    <Head title="Injury — Admin" />
    <div class="container mx-auto mt-6 px-4 pb-6">
        <Card>
            <CardHeader>
                <CardTitle>{{ item ? 'Edit Injury' : 'New Injury' }}</CardTitle>
            </CardHeader>
            <CardContent class="space-y-4">
                <div class="grid gap-3 md:grid-cols-3">
                    <div>
                        <Label for="name">Name</Label>
                        <Input id="name" v-model="form.name" />
                        <InputError :message="usePage().props.errors.name" />
                    </div>
                    <div>
                        <Label for="flip_value">Flip Value</Label>
                        <Input id="flip_value" type="number" v-model.number="form.flip_value" />
                    </div>
                    <div>
                        <Label for="suit_pool">Suit Pool</Label>
                        <Select v-model="form.suit_pool">
                            <SelectTrigger><SelectValue /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value="pc">Ram / Crow (pc)</SelectItem>
                                <SelectItem value="te">Tome / Mask (te)</SelectItem>
                                <SelectItem value="black_joker">Black Joker</SelectItem>
                                <SelectItem value="red_joker">Red Joker</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </div>

                <div>
                    <Label for="body">Body Text</Label>
                    <Textarea id="body" v-model="form.body" rows="3" />
                    <InputError :message="usePage().props.errors.body" />
                </div>

                <div class="grid gap-2 md:grid-cols-2">
                    <label class="flex items-start gap-2 text-sm">
                        <Checkbox :checked="form.reflip_if_no_triggers" @update:checked="(v: boolean) => (form.reflip_if_no_triggers = v)" />
                        <span>Reflip if target has no triggers (Permanent Hex)</span>
                    </label>
                    <label class="flex items-start gap-2 text-sm">
                        <Checkbox :checked="form.reflip_if_master_or_totem" @update:checked="(v: boolean) => (form.reflip_if_master_or_totem = v)" />
                        <span>Reflip if master/totem (Headstrong)</span>
                    </label>
                    <label class="flex items-start gap-2 text-sm">
                        <Checkbox :checked="form.is_traitor" @update:checked="(v: boolean) => (form.is_traitor = v)" />
                        <span>Traitor — model swaps to opposing crew (Black Joker)</span>
                    </label>
                    <label class="flex items-start gap-2 text-sm">
                        <Checkbox :checked="form.is_close_call" @update:checked="(v: boolean) => (form.is_close_call = v)" />
                        <span>Close Call — reflip on Lucky Miss (Red Joker)</span>
                    </label>
                    <label class="flex items-start gap-2 text-sm md:col-span-2">
                        <Checkbox :checked="form.annihilates_model" @update:checked="(v: boolean) => (form.annihilates_model = v)" />
                        <span>Annihilates the model (Killed Off)</span>
                    </label>
                </div>
            </CardContent>
            <CardFooter class="justify-end gap-2">
                <Button variant="outline" @click="router.get(route('admin.campaign.injuries.index'))">Cancel</Button>
                <Button @click="submit">{{ item ? 'Update' : 'Create' }}</Button>
            </CardFooter>
        </Card>
    </div>
</template>
