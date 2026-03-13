<script setup lang="ts">
import SearchableMultiselect from '@/components/SearchableMultiselect.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { router } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';

interface SelectOption {
    name: string;
    value: string;
}

const props = defineProps({
    channel: {
        type: [Object, Array],
        required: false,
        default() {
            return null;
        },
    },
    users: {
        type: Array as () => SelectOption[],
        required: false,
        default() {
            return [];
        },
    },
});

const formInfo = ref({
    name: null as string | null,
    description: null as string | null,
    image: null as File | null,
    user_ids: [] as string[],
});

const submit = () => {
    const formData = new FormData();
    if (formInfo.value.name) formData.append('name', formInfo.value.name);
    if (formInfo.value.description) formData.append('description', formInfo.value.description);
    if (formInfo.value.image) formData.append('image', formInfo.value.image);
    formInfo.value.user_ids.forEach((id) => formData.append('user_ids[]', id));

    router.post(props.channel ? route('admin.channels.update', props.channel.slug) : route('admin.channels.store'), formData);
};

const onFileChange = (event: Event) => {
    const target = event.target as HTMLInputElement;
    formInfo.value.image = target.files?.[0] ?? null;
};

onMounted(() => {
    formInfo.value.name = props.channel?.name ?? null;
    formInfo.value.description = props.channel?.description ?? null;
    formInfo.value.user_ids = props.channel?.users?.map((u: { id: number }) => String(u.id)) ?? [];
});
</script>

<template>
    <div class="container mx-auto mt-6">
        <Card>
            <CardHeader>
                <CardTitle>Channel</CardTitle>
                <CardDescription>Create and Edit Channel Information</CardDescription>
            </CardHeader>
            <CardContent>
                <form>
                    <div class="grid w-full items-center gap-4">
                        <div class="flex flex-col space-y-1.5">
                            <Label for="name">Name</Label>
                            <Input id="name" v-model="formInfo.name" placeholder="Channel Name" />
                        </div>
                        <div class="flex flex-col space-y-1.5">
                            <Label for="description">Description</Label>
                            <Textarea id="description" v-model="formInfo.description" placeholder="Channel description..." />
                        </div>
                        <div class="flex flex-col space-y-1.5">
                            <Label for="image">Image</Label>
                            <Input id="image" type="file" accept="image/*" @change="onFileChange" />
                            <img
                                v-if="channel?.image_url"
                                :src="channel.image_url"
                                alt="Current image"
                                class="mt-2 h-24 w-24 rounded-md object-cover"
                            />
                        </div>
                        <div class="flex flex-col space-y-1.5">
                            <Label>Owners</Label>
                            <SearchableMultiselect v-model="formInfo.user_ids" :options="users" placeholder="Search users..." />
                        </div>
                    </div>
                </form>
            </CardContent>
            <CardFooter class="flex justify-between px-6 pb-6">
                <Button @click="router.get(route('admin.channels.index'))" variant="outline"> Cancel </Button>
                <Button @click="submit">Save</Button>
            </CardFooter>
        </Card>
    </div>
</template>
