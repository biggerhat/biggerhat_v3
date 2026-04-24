<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Head, router, usePage } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';

interface Rule {
    id: number;
    slug: string;
    name: string;
    description: string | null;
    sort_order: number;
}

const props = defineProps<{
    rule?: Rule | null;
}>();

const formInfo = ref({
    name: '' as string,
    description: null as string | null,
    sort_order: 0 as number,
});

const submit = () => {
    if (props.rule) router.post(route('admin.tos.special_rules.update', props.rule.slug), formInfo.value);
    else router.post(route('admin.tos.special_rules.store'), formInfo.value);
};

onMounted(() => {
    if (!props.rule) return;
    formInfo.value.name = props.rule.name;
    formInfo.value.description = props.rule.description;
    formInfo.value.sort_order = props.rule.sort_order;
});
</script>

<template>
    <Head title="TOS Special Rule — Admin" />
    <div class="container mx-auto mt-6 px-4 pb-6">
        <Card>
            <CardHeader><CardTitle>{{ rule ? 'Edit Special Rule' : 'New Special Rule' }}</CardTitle></CardHeader>
            <CardContent class="space-y-3">
                <div>
                    <Label for="name">Name</Label>
                    <Input id="name" v-model="formInfo.name" />
                    <InputError :message="usePage().props.errors.name" />
                </div>
                <div>
                    <Label for="description">Description</Label>
                    <Textarea id="description" v-model="formInfo.description" />
                </div>
                <div>
                    <Label for="sort_order">Sort Order</Label>
                    <Input id="sort_order" v-model.number="formInfo.sort_order" type="number" min="0" />
                </div>
            </CardContent>
            <CardFooter class="flex justify-between px-6 pb-6">
                <Button variant="outline" @click="router.get(route('admin.tos.special_rules.index'))">Cancel</Button>
                <Button @click="submit">Save</Button>
            </CardFooter>
        </Card>
    </div>
</template>
