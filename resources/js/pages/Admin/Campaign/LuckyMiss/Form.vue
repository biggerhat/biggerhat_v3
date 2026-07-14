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

interface LuckyMissRow {
    id: number;
    name: string;
    body: string;
    flip_value: number | null;
    is_doppelganger: boolean;
    ability_id: number | null;
}

const props = defineProps<{ item?: LuckyMissRow | null; abilities: Array<{ name: string; value: number }> }>();

const form = ref({ name: '', body: '', flip_value: null as number | null, is_doppelganger: false, ability_id: null as number | null });

const submit = () => {
    if (props.item) router.post(route('admin.campaign.lucky-miss.update', props.item.id), form.value);
    else router.post(route('admin.campaign.lucky-miss.store'), form.value);
};

onMounted(() => {
    if (!props.item) return;
    Object.assign(form.value, props.item);
});
</script>

<template>
    <Head title="Lucky Miss — Admin" />
    <div class="container mx-auto mt-6 px-4 pb-6">
        <Card>
            <CardHeader
                ><CardTitle>{{ item ? 'Edit' : 'New' }} Lucky Miss</CardTitle></CardHeader
            >
            <CardContent class="space-y-4">
                <div class="grid gap-3 md:grid-cols-2">
                    <div>
                        <Label for="name">Name</Label>
                        <Input id="name" v-model="form.name" />
                        <InputError :message="usePage().props.errors.name" />
                    </div>
                    <div>
                        <Label for="flip_value">Flip (1–13, blank = Doppelganger)</Label>
                        <Input id="flip_value" type="number" v-model.number="form.flip_value" />
                    </div>
                </div>
                <div>
                    <Label for="body">Body Text</Label>
                    <Textarea id="body" v-model="form.body" rows="3" />
                    <InputError :message="usePage().props.errors.body" />
                </div>
                <div>
                    <Label for="ability_id">Ability Lookup</Label>
                    <Select
                        :model-value="form.ability_id?.toString() ?? undefined"
                        @update:model-value="(v) => (form.ability_id = v ? Number(v) : null)"
                    >
                        <SelectTrigger><SelectValue placeholder="No ability — flavor text only" /></SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="opt in abilities" :key="opt.value" :value="opt.value.toString()">{{ opt.name }}</SelectItem>
                        </SelectContent>
                    </Select>
                    <p class="text-[10px] text-muted-foreground">
                        Set when this result grants a real ability to the affected unit — applied permanently, Campaign-only.
                    </p>
                    <InputError :message="usePage().props.errors.ability_id" />
                </div>
                <label class="flex items-start gap-2 text-sm">
                    <Checkbox :checked="form.is_doppelganger" @update:checked="(v: boolean) => (form.is_doppelganger = v)" />
                    <span>Doppelganger (any joker — free copy of model into arsenal)</span>
                </label>
            </CardContent>
            <CardFooter class="justify-end gap-2">
                <Button variant="outline" @click="router.get(route('admin.campaign.lucky-miss.index'))">Cancel</Button>
                <Button @click="submit">{{ item ? 'Update' : 'Create' }}</Button>
            </CardFooter>
        </Card>
    </div>
</template>
