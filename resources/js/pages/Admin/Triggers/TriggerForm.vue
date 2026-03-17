<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import InputError from '@/components/InputError.vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';

const props = defineProps({
    trigger: {
        type: [Object, Array],
        required: false,
        default() {
            return null;
        },
    },
    suits: {
        type: [Object, Array],
        required: false,
        default() {
            return [];
        },
    },
});

const formInfo = ref({
    name: null,
    suits: null,
    stone_cost: 0,
    description: null,
});

const submit = () => {
    router.post(props.trigger ? route('admin.triggers.update', props.trigger.slug) : route('admin.triggers.store'), formInfo.value);
};

onMounted(() => {
    formInfo.value.name = props.trigger?.name ?? null;
    formInfo.value.stone_cost = props.trigger?.stone_cost ?? 0;
    formInfo.value.suits = props.trigger?.suits ?? null;
    formInfo.value.description = props.trigger?.description ?? null;
});
</script>

<template>
    <Head title="Triggers - Admin" />
    <div class="container mx-auto mt-6">
        <Card>
            <CardHeader>
                <CardTitle>Trigger</CardTitle>
                <CardDescription>Create and Edit Trigger Information</CardDescription>
            </CardHeader>
            <CardContent>
                <form>
                    <div class="grid w-full items-center gap-4">
                        <div class="flex flex-col space-y-1.5">
                            <Label for="name">Name</Label>
                            <Input id="name" autofocus v-model="formInfo.name" placeholder="Trigger Name" />
                            <InputError :message="usePage().props.errors.name" />
                        </div>
                        <div class="flex flex-col space-y-1.5">
                            <div class="grid auto-rows-min gap-4 md:grid-cols-2">
                                <div class="flex flex-col space-y-1.5">
                                    <Label for="suits">Required Suits</Label>
                                    <Input id="suits" v-model="formInfo.suits" placeholder="Required Suits" />
                                    <InputError :message="usePage().props.errors.suits" />
                                </div>
                                <div class="flex flex-col space-y-1.5">
                                    <Label for="stone_cost">Stone Cost</Label>
                                    <Input id="stone_cost" v-model="formInfo.stone_cost" type="number" min="0" placeholder="0" />
                                    <InputError :message="usePage().props.errors.stone_cost" />
                                </div>
                            </div>
                        </div>
                        <div class="flex flex-col space-y-1.5">
                            <div class="flex flex-col space-y-1.5">
                                <Label for="description">Trigger Text</Label>
                                <Textarea id="description" v-model="formInfo.description" placeholder="Type the trigger text here." />
                                <InputError :message="usePage().props.errors.description" />
                            </div>
                        </div>
                    </div>
                </form>
            </CardContent>
            <CardFooter class="flex justify-between px-6 pb-6">
                <Button @click="router.get(route('admin.triggers.index'))" variant="outline"> Cancel </Button>
                <Button @click="submit">Save</Button>
            </CardFooter>
        </Card>
    </div>
</template>
