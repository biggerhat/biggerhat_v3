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

interface EffectRow {
    id: number;
    name: string;
    body: string;
    requires_token_choice: boolean;
    requires_marker_choice: boolean;
    requires_upgrade_type_choice: boolean;
    notes: string | null;
}

const props = defineProps<{ item?: EffectRow | null }>();

const form = ref({
    name: '',
    body: '',
    requires_token_choice: false,
    requires_marker_choice: false,
    requires_upgrade_type_choice: false,
    restrictions: null as Record<string, unknown> | null,
    grants_ability: null as Record<string, unknown> | null,
    grants_action: null as Record<string, unknown> | null,
    notes: null as string | null,
});

const submit = () => {
    if (props.item) router.post(route('admin.campaign.crew-card-effects.update', props.item.id), form.value);
    else router.post(route('admin.campaign.crew-card-effects.store'), form.value);
};

onMounted(() => {
    if (!props.item) return;
    Object.assign(form.value, props.item);
});
</script>

<template>
    <Head title="Crew Card Effect — Admin" />
    <div class="container mx-auto mt-6 px-4 pb-6">
        <Card>
            <CardHeader
                ><CardTitle>{{ item ? 'Edit' : 'New' }} Crew Card Effect</CardTitle></CardHeader
            >
            <CardContent class="space-y-4">
                <div>
                    <Label for="name">Name</Label>
                    <Input id="name" v-model="form.name" />
                    <InputError :message="usePage().props.errors.name" />
                </div>
                <div>
                    <Label for="body">Body Text</Label>
                    <Textarea id="body" v-model="form.body" rows="4" />
                    <InputError :message="usePage().props.errors.body" />
                </div>
                <div class="grid gap-2 md:grid-cols-3">
                    <label class="flex items-start gap-2 text-sm">
                        <Checkbox :checked="form.requires_token_choice" @update:checked="(v: boolean) => (form.requires_token_choice = v)" />
                        <span>Requires token choice</span>
                    </label>
                    <label class="flex items-start gap-2 text-sm">
                        <Checkbox :checked="form.requires_marker_choice" @update:checked="(v: boolean) => (form.requires_marker_choice = v)" />
                        <span>Requires marker choice</span>
                    </label>
                    <label class="flex items-start gap-2 text-sm">
                        <Checkbox
                            :checked="form.requires_upgrade_type_choice"
                            @update:checked="(v: boolean) => (form.requires_upgrade_type_choice = v)"
                        />
                        <span>Requires upgrade-type choice</span>
                    </label>
                </div>
                <div>
                    <Label for="notes">Internal Notes</Label>
                    <Textarea id="notes" v-model="form.notes" rows="2" />
                </div>
            </CardContent>
            <CardFooter class="justify-end gap-2">
                <Button variant="outline" @click="router.get(route('admin.campaign.crew-card-effects.index'))">Cancel</Button>
                <Button @click="submit">{{ item ? 'Update' : 'Create' }}</Button>
            </CardFooter>
        </Card>
    </div>
</template>
