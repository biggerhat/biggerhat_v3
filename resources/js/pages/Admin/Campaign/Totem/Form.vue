<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Head, router, usePage } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';

interface TotemRow {
    id: number;
    name: string;
    flip_value: number | null;
    is_black_joker: boolean;
    is_red_joker: boolean;
    df: number;
    wp: number;
    sp: number;
    health: number;
    abilities: Array<Record<string, unknown>> | null;
    attack_actions: Array<Record<string, unknown>> | null;
    tactical_actions: Array<Record<string, unknown>> | null;
    special_replace_with_other_totem: boolean;
    is_mini_master: boolean;
}

const props = defineProps<{ item?: TotemRow | null }>();

const form = ref({
    name: '',
    flip_value: null as number | null,
    is_black_joker: false,
    is_red_joker: false,
    df: 5,
    wp: 5,
    sp: 6,
    health: 9,
    abilities: null as Array<Record<string, unknown>> | null,
    attack_actions: null as Array<Record<string, unknown>> | null,
    tactical_actions: null as Array<Record<string, unknown>> | null,
    special_replace_with_other_totem: false,
    is_mini_master: false,
});

const submit = () => {
    if (props.item) router.post(route('admin.campaign.totems.update', props.item.id), form.value);
    else router.post(route('admin.campaign.totems.store'), form.value);
};

onMounted(() => {
    if (!props.item) return;
    Object.assign(form.value, props.item);
});
</script>

<template>
    <Head title="Totem — Admin" />
    <div class="container mx-auto mt-6 px-4 pb-6">
        <Card>
            <CardHeader
                ><CardTitle>{{ item ? 'Edit' : 'New' }} Totem</CardTitle></CardHeader
            >
            <CardContent class="space-y-4">
                <div class="grid gap-3 md:grid-cols-2">
                    <div>
                        <Label for="name">Name</Label>
                        <Input id="name" v-model="form.name" />
                        <InputError :message="usePage().props.errors.name" />
                    </div>
                    <div>
                        <Label for="flip_value">Flip Value (exact match)</Label>
                        <Input id="flip_value" type="number" v-model.number="form.flip_value" />
                    </div>
                </div>
                <div class="grid gap-3 md:grid-cols-4">
                    <div><Label>Df</Label><Input type="number" v-model.number="form.df" /></div>
                    <div><Label>Wp</Label><Input type="number" v-model.number="form.wp" /></div>
                    <div><Label>Sp</Label><Input type="number" v-model.number="form.sp" /></div>
                    <div><Label>Health</Label><Input type="number" v-model.number="form.health" /></div>
                </div>
                <div class="grid gap-2 md:grid-cols-2">
                    <label class="flex items-start gap-2 text-sm">
                        <Checkbox :checked="form.is_black_joker" @update:checked="(v: boolean) => (form.is_black_joker = v)" />
                        <span>Black Joker (Sniveling Coward)</span>
                    </label>
                    <label class="flex items-start gap-2 text-sm">
                        <Checkbox :checked="form.is_red_joker" @update:checked="(v: boolean) => (form.is_red_joker = v)" />
                        <span>Red Joker (Mini-Master)</span>
                    </label>
                    <label class="flex items-start gap-2 text-sm">
                        <Checkbox
                            :checked="form.special_replace_with_other_totem"
                            @update:checked="(v: boolean) => (form.special_replace_with_other_totem = v)"
                        />
                        <span>Can be permanently replaced by another non-joker totem</span>
                    </label>
                    <label class="flex items-start gap-2 text-sm">
                        <Checkbox :checked="form.is_mini_master" @update:checked="(v: boolean) => (form.is_mini_master = v)" />
                        <span>Mini-Master (picks action from a master sharing keyword)</span>
                    </label>
                </div>
                <p class="text-[11px] text-muted-foreground">
                    Abilities, attack actions, and tactical actions are stored as JSON arrays. Use the JSON editor in a future iteration; for now,
                    edit via DB tooling for complex blocks.
                </p>
            </CardContent>
            <CardFooter class="justify-end gap-2">
                <Button variant="outline" @click="router.get(route('admin.campaign.totems.index'))">Cancel</Button>
                <Button @click="submit">{{ item ? 'Update' : 'Create' }}</Button>
            </CardFooter>
        </Card>
    </div>
</template>
