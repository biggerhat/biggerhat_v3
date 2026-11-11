<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';

interface OperationRow {
    id: number;
    slug: string;
    name: string;
    source: string | null;
    flavor: string | null;
    additional_setup: string | null;
    special_rules: string | null;
    victory_conditions: string | null;
    image_path: string | null;
    sort_order: number;
}

const props = defineProps<{ operation?: OperationRow | null }>();

const formInfo = ref({
    name: '' as string,
    source: null as string | null,
    flavor: null as string | null,
    additional_setup: null as string | null,
    special_rules: null as string | null,
    victory_conditions: null as string | null,
    image_path: null as File | null,
    sort_order: 0 as number,
});

const existingImage = computed<string | null>(() => {
    const path = props.operation?.image_path;
    if (!path) return null;
    return path.startsWith('/') || path.startsWith('http') ? path : `/storage/${path}`;
});

const submit = () => {
    // forceFormData because image_path is a File (matches the other TOS forms).
    const options = { forceFormData: true };
    if (props.operation) router.post(route('admin.tos.operations.update', props.operation.slug), formInfo.value, options);
    else router.post(route('admin.tos.operations.store'), formInfo.value, options);
};

onMounted(() => {
    if (!props.operation) return;
    formInfo.value.name = props.operation.name;
    formInfo.value.source = props.operation.source;
    formInfo.value.flavor = props.operation.flavor;
    formInfo.value.additional_setup = props.operation.additional_setup;
    formInfo.value.special_rules = props.operation.special_rules;
    formInfo.value.victory_conditions = props.operation.victory_conditions;
    formInfo.value.sort_order = props.operation.sort_order;
});
</script>

<template>
    <Head title="TOS Operation — Admin" />
    <div class="container mx-auto mt-6 px-4 pb-6">
        <Card>
            <CardHeader
                ><CardTitle as="h1">{{ operation ? 'Edit Operation' : 'New Operation' }}</CardTitle></CardHeader
            >
            <CardContent class="space-y-3">
                <div>
                    <Label for="name">Name</Label>
                    <Input id="name" v-model="formInfo.name" />
                    <InputError :message="usePage().props.errors.name" />
                </div>
                <div>
                    <Label for="source">Source</Label>
                    <Input id="source" v-model="formInfo.source" placeholder="e.g. Fields of Glory v3.0, Core Rulebook" />
                    <InputError :message="usePage().props.errors.source" />
                </div>
                <div>
                    <Label for="flavor">Flavor Text</Label>
                    <Textarea id="flavor" v-model="formInfo.flavor" rows="5" />
                    <InputError :message="usePage().props.errors.flavor" />
                </div>
                <div>
                    <Label for="additional_setup">Additional Setup</Label>
                    <Textarea id="additional_setup" v-model="formInfo.additional_setup" rows="5" />
                    <InputError :message="usePage().props.errors.additional_setup" />
                </div>
                <div>
                    <Label for="special_rules">Special Rules</Label>
                    <Textarea id="special_rules" v-model="formInfo.special_rules" rows="5" />
                    <InputError :message="usePage().props.errors.special_rules" />
                </div>
                <div>
                    <Label for="victory_conditions">Victory Conditions</Label>
                    <Textarea id="victory_conditions" v-model="formInfo.victory_conditions" rows="5" />
                    <InputError :message="usePage().props.errors.victory_conditions" />
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
                <Button variant="outline" @click="router.get(route('admin.tos.operations.index'))">Cancel</Button>
                <Button @click="submit">Save</Button>
            </CardFooter>
        </Card>
    </div>
</template>
