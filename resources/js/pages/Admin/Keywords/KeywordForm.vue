<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { Head, router, usePage } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';

const props = defineProps({
    keyword: {
        type: [Object, Array],
        required: false,
        default() {
            return null;
        },
    },
    game_mode_types: {
        type: [Object, Array],
        required: false,
        default() {
            return [];
        },
    },
});

const formInfo = ref({
    game_mode_type: 'standard',
    name: null,
    description: null,
});

const submit = () => {
    router.post(props.keyword ? route('admin.keywords.update', props.keyword.slug) : route('admin.keywords.store'), formInfo.value);
};

onMounted(() => {
    formInfo.value.game_mode_type = props.keyword?.game_mode_type ?? 'standard';
    formInfo.value.name = props.keyword?.name ?? null;
    formInfo.value.description = props.keyword?.description ?? null;
});
</script>

<template>
    <Head title="Keywords - Admin" />
    <div class="container mx-auto mt-6">
        <Card>
            <CardHeader>
                <CardTitle>Keyword</CardTitle>
                <CardDescription>Create and Edit Keyword Information</CardDescription>
            </CardHeader>
            <CardContent>
                <form>
                    <div class="grid w-full items-center gap-4">
                        <div class="flex flex-col space-y-1.5">
                            <Label for="game_mode_type">Game Mode</Label>
                            <Select id="game_mode_type" v-model="formInfo.game_mode_type">
                                <SelectTrigger>
                                    <SelectValue placeholder="Game Mode Type" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="mode in props.game_mode_types" :value="mode.value" :key="mode.value">
                                        {{ mode.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="usePage().props.errors.game_mode_type" />
                        </div>
                        <div class="flex flex-col space-y-1.5">
                            <Label for="name">Name</Label>
                            <Input id="name" v-model="formInfo.name" placeholder="Keyword Name" />
                            <InputError :message="usePage().props.errors.name" />
                        </div>
                        <div class="flex flex-col space-y-1.5">
                            <Label for="description">Description</Label>
                            <Textarea id="description" v-model="formInfo.description" placeholder="Type the keyword description here." />
                            <InputError :message="usePage().props.errors.description" />
                        </div>
                    </div>
                </form>
            </CardContent>
            <CardFooter class="flex justify-between px-6 pb-6">
                <Button @click="router.get(route('admin.keywords.index'))" variant="outline"> Cancel </Button>
                <Button @click="submit">Save</Button>
            </CardFooter>
        </Card>
    </div>
</template>
