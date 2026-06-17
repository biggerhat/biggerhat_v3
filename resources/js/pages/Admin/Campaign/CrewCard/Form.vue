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

interface CrewCardRow {
    id: number;
    name: string;
    description: string | null;
    requires_token_choice: boolean;
    requires_marker_choice: boolean;
    requires_upgrade_type_choice: boolean;
}

const props = defineProps<{ item?: CrewCardRow | null }>();

const form = ref({
    name: '',
    description: null as string | null,
    requires_token_choice: false,
    requires_marker_choice: false,
    requires_upgrade_type_choice: false,
});

const submit = () => {
    if (props.item) router.post(route('admin.campaign.crew-cards.update', props.item.id), form.value);
    else router.post(route('admin.campaign.crew-cards.store'), form.value);
};

onMounted(() => {
    if (!props.item) return;
    Object.assign(form.value, props.item);
});
</script>

<template>
    <Head title="Crew Card — Admin" />
    <div class="container mx-auto mt-6 px-4 pb-6">
        <Card>
            <CardHeader>
                <CardTitle>{{ item ? 'Edit' : 'New' }} Crew Card</CardTitle>
            </CardHeader>
            <CardContent class="space-y-4">
                <div>
                    <Label for="name">Name</Label>
                    <Input id="name" v-model="form.name" />
                    <InputError :message="usePage().props.errors.name" />
                </div>
                <div>
                    <Label for="description">Description</Label>
                    <Textarea id="description" v-model="form.description" rows="5" placeholder="The rule text that appears on the card..." />
                    <InputError :message="usePage().props.errors.description" />
                </div>
                <div class="space-y-2">
                    <p class="text-sm font-medium">Choice requirements</p>
                    <label class="flex items-start gap-2 text-sm">
                        <Checkbox :checked="form.requires_token_choice" @update:checked="(v: boolean) => (form.requires_token_choice = v)" />
                        <span>Requires player to choose a token type</span>
                    </label>
                    <label class="flex items-start gap-2 text-sm">
                        <Checkbox :checked="form.requires_marker_choice" @update:checked="(v: boolean) => (form.requires_marker_choice = v)" />
                        <span>Requires player to choose a marker type</span>
                    </label>
                    <label class="flex items-start gap-2 text-sm">
                        <Checkbox
                            :checked="form.requires_upgrade_type_choice"
                            @update:checked="(v: boolean) => (form.requires_upgrade_type_choice = v)"
                        />
                        <span>Requires player to choose an upgrade type</span>
                    </label>
                </div>
            </CardContent>
            <CardFooter class="justify-end gap-2">
                <Button variant="outline" @click="router.get(route('admin.campaign.crew-cards.index'))">Cancel</Button>
                <Button @click="submit">{{ item ? 'Update' : 'Create' }}</Button>
            </CardFooter>
        </Card>
    </div>
</template>
