<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Head, router, usePage } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';

interface EventRow {
    id: number;
    name: string;
    body: string;
    flip_value: number | null;
    is_black_joker: boolean;
    is_red_joker: boolean;
    requires_placement: boolean;
    is_one_time: boolean;
}

const props = defineProps<{ item?: EventRow | null }>();

const form = ref({
    name: '',
    body: '',
    flip_value: null as number | null,
    is_black_joker: false,
    is_red_joker: false,
    terrain_marker_def: null as Record<string, unknown> | null,
    requires_placement: false,
    is_one_time: false,
});

const submit = () => {
    if (props.item) router.post(route('admin.campaign.weekly-events.update', props.item.id), form.value);
    else router.post(route('admin.campaign.weekly-events.store'), form.value);
};

onMounted(() => {
    if (!props.item) return;
    Object.assign(form.value, props.item);
});
</script>

<template>
    <Head title="Weekly Event — Admin" />
    <div class="container mx-auto mt-6 px-4 pb-6">
        <Card>
            <CardHeader
                ><CardTitle>{{ item ? 'Edit' : 'New' }} Weekly Event</CardTitle></CardHeader
            >
            <CardContent class="space-y-4">
                <div class="grid gap-3 md:grid-cols-2">
                    <div>
                        <Label for="name">Name</Label>
                        <Input id="name" v-model="form.name" />
                        <InputError :message="usePage().props.errors.name" />
                    </div>
                    <div>
                        <Label for="flip_value">Flip Value</Label>
                        <Input id="flip_value" type="number" v-model.number="form.flip_value" />
                    </div>
                </div>
                <div>
                    <Label for="body">Body Text</Label>
                    <Textarea id="body" v-model="form.body" rows="4" />
                    <InputError :message="usePage().props.errors.body" />
                </div>
                <div class="grid gap-2 md:grid-cols-2">
                    <label class="flex items-start gap-2 text-sm">
                        <Checkbox :checked="form.is_black_joker" @update:checked="(v: boolean) => (form.is_black_joker = v)" />
                        <span>Black Joker (Up the Ante)</span>
                    </label>
                    <label class="flex items-start gap-2 text-sm">
                        <Checkbox :checked="form.is_red_joker" @update:checked="(v: boolean) => (form.is_red_joker = v)" />
                        <span>Red Joker (Bullet With Your Name on It)</span>
                    </label>
                    <label class="flex items-start gap-2 text-sm">
                        <Checkbox :checked="form.requires_placement" @update:checked="(v: boolean) => (form.requires_placement = v)" />
                        <span>Requires terrain marker placement</span>
                    </label>
                    <label class="flex items-start gap-2 text-sm">
                        <Checkbox :checked="form.is_one_time" @update:checked="(v: boolean) => (form.is_one_time = v)" />
                        <span>One-time only (reflip on second occurrence)</span>
                    </label>
                </div>
            </CardContent>
            <CardFooter class="justify-end gap-2">
                <Button variant="outline" @click="router.get(route('admin.campaign.weekly-events.index'))">Cancel</Button>
                <Button @click="submit">{{ item ? 'Update' : 'Create' }}</Button>
            </CardFooter>
        </Card>
    </div>
</template>
