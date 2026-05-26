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

interface DoctorRow {
    id: number;
    name: string;
    body: string;
    flip_value_min: number | null;
    flip_value_max: number | null;
    is_black_joker: boolean;
    is_red_joker: boolean;
    outcome_kind: string;
}

const props = defineProps<{ item?: DoctorRow | null }>();

const form = ref({
    name: '',
    body: '',
    flip_value_min: null as number | null,
    flip_value_max: null as number | null,
    is_black_joker: false,
    is_red_joker: false,
    outcome_kind: 'no_effect',
});

const submit = () => {
    if (props.item) router.post(route('admin.campaign.back-alley-doctor.update', props.item.id), form.value);
    else router.post(route('admin.campaign.back-alley-doctor.store'), form.value);
};

onMounted(() => {
    if (!props.item) return;
    Object.assign(form.value, props.item);
});
</script>

<template>
    <Head title="Back-Alley Doctor Result — Admin" />
    <div class="container mx-auto mt-6 px-4 pb-6">
        <Card>
            <CardHeader
                ><CardTitle>{{ item ? 'Edit' : 'New' }} Doctor Result</CardTitle></CardHeader
            >
            <CardContent class="space-y-4">
                <div class="grid gap-3 md:grid-cols-2">
                    <div>
                        <Label for="name">Name (e.g. "Oops?", "Success!")</Label>
                        <Input id="name" v-model="form.name" />
                        <InputError :message="usePage().props.errors.name" />
                    </div>
                    <div>
                        <Label for="outcome_kind">Outcome</Label>
                        <Select v-model="form.outcome_kind">
                            <SelectTrigger><SelectValue /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value="no_effect">No effect</SelectItem>
                                <SelectItem value="removed">Injury removed</SelectItem>
                                <SelectItem value="added_injury">Added injury (Oops)</SelectItem>
                                <SelectItem value="gained_undead">Removed + gained Undead</SelectItem>
                                <SelectItem value="gained_construct">Removed + gained Construct</SelectItem>
                                <SelectItem value="lucky_miss_reflip">Removed + Lucky Miss reflip</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </div>
                <div class="grid gap-3 md:grid-cols-2">
                    <div><Label>Flip Min</Label><Input type="number" v-model.number="form.flip_value_min" /></div>
                    <div><Label>Flip Max</Label><Input type="number" v-model.number="form.flip_value_max" /></div>
                </div>
                <div class="grid gap-2 md:grid-cols-2">
                    <label class="flex items-start gap-2 text-sm">
                        <Checkbox :checked="form.is_black_joker" @update:checked="(v: boolean) => (form.is_black_joker = v)" />
                        <span>Black Joker</span>
                    </label>
                    <label class="flex items-start gap-2 text-sm">
                        <Checkbox :checked="form.is_red_joker" @update:checked="(v: boolean) => (form.is_red_joker = v)" />
                        <span>Red Joker</span>
                    </label>
                </div>
                <div>
                    <Label for="body">Body Text</Label>
                    <Textarea id="body" v-model="form.body" rows="3" />
                    <InputError :message="usePage().props.errors.body" />
                </div>
            </CardContent>
            <CardFooter class="justify-end gap-2">
                <Button variant="outline" @click="router.get(route('admin.campaign.back-alley-doctor.index'))">Cancel</Button>
                <Button @click="submit">{{ item ? 'Update' : 'Create' }}</Button>
            </CardFooter>
        </Card>
    </div>
</template>
