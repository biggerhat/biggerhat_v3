<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';

interface DeploymentRow {
    id: number;
    slug: string;
    name: string;
    suit: string | null;
    description: string | null;
    image_path: string | null;
    sort_order: number;
}

const SUITS = ['R', 'C', 'T', 'M'];

const props = defineProps<{ deployment?: DeploymentRow | null }>();

const formInfo = ref({
    name: '' as string,
    suit: null as string | null,
    description: null as string | null,
    image_path: null as File | null,
    sort_order: 0 as number,
});

const existingImage = computed<string | null>(() => {
    const path = props.deployment?.image_path;
    if (!path) return null;
    return path.startsWith('/') || path.startsWith('http') ? path : `/storage/${path}`;
});

const submit = () => {
    // forceFormData because image_path is a File (matches the other TOS forms).
    const options = { forceFormData: true };
    if (props.deployment) router.post(route('admin.tos.deployments.update', props.deployment.slug), formInfo.value, options);
    else router.post(route('admin.tos.deployments.store'), formInfo.value, options);
};

onMounted(() => {
    if (!props.deployment) return;
    formInfo.value.name = props.deployment.name;
    formInfo.value.suit = props.deployment.suit;
    formInfo.value.description = props.deployment.description;
    formInfo.value.sort_order = props.deployment.sort_order;
});
</script>

<template>
    <Head title="TOS Deployment — Admin" />
    <div class="container mx-auto mt-6 px-4 pb-6">
        <Card>
            <CardHeader
                ><CardTitle as="h1">{{ deployment ? 'Edit Deployment' : 'New Deployment' }}</CardTitle></CardHeader
            >
            <CardContent class="space-y-3">
                <div>
                    <Label for="name">Name</Label>
                    <Input id="name" v-model="formInfo.name" />
                    <InputError :message="usePage().props.errors.name" />
                </div>
                <div>
                    <Label for="suit">Fate Suit</Label>
                    <Select v-model="formInfo.suit">
                        <SelectTrigger id="suit"><SelectValue placeholder="No suit" /></SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="s in SUITS" :key="s" :value="s">{{ s }}</SelectItem>
                        </SelectContent>
                    </Select>
                    <p class="text-2xs text-muted-foreground">
                        The Defender's flip picks the Deployment by suit. A flipped Joker is treated as R (Confrontation).
                    </p>
                    <InputError :message="usePage().props.errors.suit" />
                </div>
                <div>
                    <Label for="description">Description</Label>
                    <Textarea id="description" v-model="formInfo.description" rows="5" />
                    <InputError :message="usePage().props.errors.description" />
                </div>

                <div class="space-y-1.5">
                    <Label for="image_path">Reference Image</Label>
                    <img
                        v-if="existingImage"
                        :src="existingImage"
                        :alt="formInfo.name || 'Current image'"
                        class="h-40 w-auto rounded border object-cover"
                    />
                    <Input
                        id="image_path"
                        type="file"
                        accept="image/*"
                        @input="formInfo.image_path = ($event.target as HTMLInputElement).files?.[0] ?? null"
                    />
                    <p class="text-2xs text-muted-foreground">
                        {{ existingImage ? 'Choose a new file to replace, or leave empty to keep.' : 'PNG / JPG up to 30 MB.' }}
                    </p>
                    <InputError :message="usePage().props.errors.image_path" />
                </div>
                <div>
                    <Label for="sort_order">Sort Order</Label>
                    <Input id="sort_order" v-model.number="formInfo.sort_order" type="number" min="0" />
                </div>
            </CardContent>
            <CardFooter class="flex justify-between px-6 pb-6">
                <Button variant="outline" @click="router.get(route('admin.tos.deployments.index'))">Cancel</Button>
                <Button @click="submit">Save</Button>
            </CardFooter>
        </Card>
    </div>
</template>
