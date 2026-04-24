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

interface AbilityRow {
    id: number;
    slug: string;
    name: string;
    body: string | null;
    is_general: boolean;
}

const props = defineProps<{
    ability?: AbilityRow | null;
}>();

const formInfo = ref({
    name: '' as string,
    body: null as string | null,
    is_general: true as boolean,
});

const submit = () => {
    if (props.ability) {
        router.post(route('admin.tos.abilities.update', props.ability.slug), formInfo.value);
    } else {
        router.post(route('admin.tos.abilities.store'), formInfo.value);
    }
};

onMounted(() => {
    if (!props.ability) return;
    formInfo.value.name = props.ability.name;
    formInfo.value.body = props.ability.body;
    formInfo.value.is_general = props.ability.is_general;
});
</script>

<template>
    <Head title="TOS Ability — Admin" />
    <div class="container mx-auto mt-6 px-4 pb-6">
        <Card>
            <CardHeader><CardTitle>{{ ability ? 'Edit Ability' : 'New Ability' }}</CardTitle></CardHeader>
            <CardContent class="space-y-4">
                <div>
                    <Label for="name">Name</Label>
                    <Input id="name" v-model="formInfo.name" />
                    <InputError :message="usePage().props.errors.name" />
                </div>
                <div>
                    <Label for="body">Body</Label>
                    <Textarea id="body" v-model="formInfo.body" />
                </div>
                <div class="flex items-center gap-2">
                    <Checkbox id="is_general" v-model:checked="formInfo.is_general" />
                    <Label for="is_general">General (shared across allegiances)</Label>
                </div>
            </CardContent>
            <CardFooter class="flex justify-between px-6 pb-6">
                <Button variant="outline" @click="router.get(route('admin.tos.abilities.index'))">Cancel</Button>
                <Button @click="submit">Save</Button>
            </CardFooter>
        </Card>
    </div>
</template>
