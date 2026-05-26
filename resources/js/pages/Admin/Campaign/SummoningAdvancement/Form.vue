<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Head, router, usePage } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';

interface SummoningRow {
    id: number;
    name: string;
    body: string;
}

const props = defineProps<{ item?: SummoningRow | null }>();

const form = ref({ name: '', body: '', stat_block: null as Record<string, unknown> | null });

const submit = () => {
    if (props.item) router.post(route('admin.campaign.summoning-advancements.update', props.item.id), form.value);
    else router.post(route('admin.campaign.summoning-advancements.store'), form.value);
};

onMounted(() => {
    if (!props.item) return;
    Object.assign(form.value, props.item);
});
</script>

<template>
    <Head title="Summoning Advancement — Admin" />
    <div class="container mx-auto mt-6 px-4 pb-6">
        <Card>
            <CardHeader
                ><CardTitle>{{ item ? 'Edit' : 'New' }} Summoning Advancement</CardTitle></CardHeader
            >
            <CardContent class="space-y-4">
                <div>
                    <Label for="name">Name (e.g. "Formed of Blood")</Label>
                    <Input id="name" v-model="form.name" />
                    <InputError :message="usePage().props.errors.name" />
                </div>
                <div>
                    <Label for="body">Body Text</Label>
                    <Textarea id="body" v-model="form.body" rows="4" />
                    <InputError :message="usePage().props.errors.body" />
                </div>
            </CardContent>
            <CardFooter class="justify-end gap-2">
                <Button variant="outline" @click="router.get(route('admin.campaign.summoning-advancements.index'))">Cancel</Button>
                <Button @click="submit">{{ item ? 'Update' : 'Create' }}</Button>
            </CardFooter>
        </Card>
    </div>
</template>
