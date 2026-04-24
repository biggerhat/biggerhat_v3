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

interface StratagemRow {
    id: number;
    name: string;
    allegiance_id: number | null;
    allegiance_type: string | null;
    tactical_cost: number;
    effect: string | null;
    image_path: string | null;
    sort_order: number;
}

const props = defineProps<{
    stratagem?: StratagemRow | null;
    allegiances: Array<{ id: number; name: string }>;
    allegiance_types: Array<{ name: string; value: string }>;
}>();

const formInfo = ref({
    name: '' as string,
    allegiance_id: null as number | null,
    allegiance_type: null as string | null,
    tactical_cost: 1 as number,
    effect: null as string | null,
    image_path: null as File | null,
    sort_order: 0 as number,
});

const existingImage = computed<string | null>(() => {
    const path = props.stratagem?.image_path;
    if (!path) return null;
    return path.startsWith('/') || path.startsWith('http') ? path : `/storage/${path}`;
});

const submit = () => {
    if (props.stratagem) router.post(route('admin.tos.stratagems.update', props.stratagem.slug), formInfo.value);
    else router.post(route('admin.tos.stratagems.store'), formInfo.value);
};

onMounted(() => {
    if (!props.stratagem) return;
    formInfo.value.name = props.stratagem.name;
    formInfo.value.allegiance_id = props.stratagem.allegiance_id;
    formInfo.value.allegiance_type = props.stratagem.allegiance_type;
    formInfo.value.tactical_cost = props.stratagem.tactical_cost;
    formInfo.value.effect = props.stratagem.effect;
    formInfo.value.sort_order = props.stratagem.sort_order;
});
</script>

<template>
    <Head title="TOS Stratagem — Admin" />
    <div class="container mx-auto mt-6 px-4 pb-6">
        <Card>
            <CardHeader><CardTitle>{{ stratagem ? 'Edit Stratagem' : 'New Stratagem' }}</CardTitle></CardHeader>
            <CardContent class="space-y-3">
                <div>
                    <Label for="name">Name</Label>
                    <Input id="name" v-model="formInfo.name" />
                    <InputError :message="usePage().props.errors.name" />
                </div>
                <p class="text-[11px] text-muted-foreground">
                    Leave Allegiance blank and pick Allegiance Type to make the Stratagem available to any Allegiance of that type (rulebook p. 13).
                </p>
                <div class="grid gap-3 md:grid-cols-2">
                    <div>
                        <Label for="allegiance_id">Allegiance (specific)</Label>
                        <Select v-model.number="formInfo.allegiance_id">
                            <SelectTrigger><SelectValue placeholder="Allegiance (optional)" /></SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="a in allegiances" :key="a.id" :value="a.id">{{ a.name }}</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div>
                        <Label for="allegiance_type">Allegiance Type</Label>
                        <Select v-model="formInfo.allegiance_type">
                            <SelectTrigger><SelectValue placeholder="Type (optional)" /></SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="t in allegiance_types" :key="t.value" :value="t.value">{{ t.name }}</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </div>
                <div>
                    <Label for="tactical_cost">Tactical Cost (Tactics Tokens)</Label>
                    <Input id="tactical_cost" v-model.number="formInfo.tactical_cost" type="number" min="1" />
                    <InputError :message="usePage().props.errors.tactical_cost" />
                </div>
                <div>
                    <Label for="effect">Effect</Label>
                    <Textarea id="effect" v-model="formInfo.effect" />
                </div>
                <div class="space-y-1.5">
                    <Label for="image_path">Card Image</Label>
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
                    <p class="text-[11px] text-muted-foreground">
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
                <Button variant="outline" @click="router.get(route('admin.tos.stratagems.index'))">Cancel</Button>
                <Button @click="submit">Save</Button>
            </CardFooter>
        </Card>
    </div>
</template>
