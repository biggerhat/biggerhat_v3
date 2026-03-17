<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import InputError from '@/components/InputError.vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';

const props = defineProps({
    lore_media: {
        type: [Object, Array],
        required: false,
        default() {
            return null;
        },
    },
    media_types: {
        type: [Object, Array],
        required: false,
        default() {
            return [];
        },
    },
});

const formInfo = ref({
    name: null as string | null,
    type: null as string | null,
    link: null as string | null,
});

const submit = () => {
    router.post(props.lore_media ? route('admin.lore_media.update', props.lore_media.id) : route('admin.lore_media.store'), formInfo.value);
};

onMounted(() => {
    formInfo.value.name = props.lore_media?.name ?? null;
    formInfo.value.type = props.lore_media?.type ?? null;
    formInfo.value.link = props.lore_media?.link ?? null;
});
</script>

<template>
    <Head title="Lore Media - Admin" />
    <div class="container mx-auto mt-6">
        <Card>
            <CardHeader>
                <CardTitle>Lore Media</CardTitle>
                <CardDescription>Create and Edit Lore Media (books, chronicles, broadcasts, etc.)</CardDescription>
            </CardHeader>
            <CardContent>
                <form>
                    <div class="grid w-full items-center gap-4">
                        <div class="flex flex-col space-y-1.5">
                            <Label for="name">Name</Label>
                            <Input id="name" v-model="formInfo.name" placeholder="Lore Media Name" />
                            <InputError :message="usePage().props.errors.name" />
                        </div>
                        <div class="flex flex-col space-y-1.5">
                            <Label for="type">Type</Label>
                            <Select id="type" v-model="formInfo.type">
                                <SelectTrigger>
                                    <SelectValue placeholder="Media Type" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="type in props.media_types" :value="type.value" :key="type.value">
                                        {{ type.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="usePage().props.errors.type" />
                        </div>
                        <div class="flex flex-col space-y-1.5">
                            <Label for="link">Link (optional)</Label>
                            <Input id="link" v-model="formInfo.link" placeholder="https://..." />
                            <InputError :message="usePage().props.errors.link" />
                        </div>
                    </div>
                </form>
            </CardContent>
            <CardFooter class="flex justify-between px-6 pb-6">
                <Button @click="router.get(route('admin.lore_media.index'))" variant="outline"> Cancel </Button>
                <Button @click="submit">Save</Button>
            </CardFooter>
        </Card>
    </div>
</template>
