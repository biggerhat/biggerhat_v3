<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import SearchableMultiselect from '@/components/SearchableMultiselect.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import type { TosSelectOption } from '@/types/tos';
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';

interface AllegianceCardRow {
    id: number;
    allegiance_id: number;
    name: string;
    slug: string;
    type: string;
    secondary_type: string | null;
    body: string | null;
    primary_body: string | null;
    image_path: string | null;
    sort_order: number;
    abilities: Array<{ id: number }>;
    actions: Array<{ id: number }>;
    triggers: Array<{ id: number }>;
    primary_abilities: Array<{ id: number }>;
    primary_actions: Array<{ id: number }>;
    primary_triggers: Array<{ id: number }>;
}

const props = defineProps<{
    card?: AllegianceCardRow | null;
    allegiances: Array<{ id: number; name: string; type: string }>;
    allegiance_types: TosSelectOption[];
    abilities: Array<{ id: number; name: string }>;
    actions: Array<{ id: number; name: string }>;
    triggers: Array<{ id: number; name: string }>;
}>();

const formInfo = ref({
    allegiance_id: null as number | null,
    name: '' as string,
    type: 'earth' as string,
    secondary_type: null as string | null,
    body: null as string | null,
    primary_body: null as string | null,
    image_path: null as File | null,
    sort_order: 0 as number,
    // Standard tier
    ability_ids: [] as string[],
    action_ids: [] as string[],
    trigger_ids: [] as string[],
    // Primary tier
    primary_ability_ids: [] as string[],
    primary_action_ids: [] as string[],
    primary_trigger_ids: [] as string[],
});

const existingImage = computed<string | null>(() => {
    const path = props.card?.image_path;
    if (!path) return null;
    return path.startsWith('/') || path.startsWith('http') ? path : `/storage/${path}`;
});

const toIntArray = (vs: string[]) => vs.map((v) => Number.parseInt(v, 10));

const submit = () => {
    const payload = {
        ...formInfo.value,
        ability_ids: toIntArray(formInfo.value.ability_ids),
        action_ids: toIntArray(formInfo.value.action_ids),
        trigger_ids: toIntArray(formInfo.value.trigger_ids),
        primary_ability_ids: toIntArray(formInfo.value.primary_ability_ids),
        primary_action_ids: toIntArray(formInfo.value.primary_action_ids),
        primary_trigger_ids: toIntArray(formInfo.value.primary_trigger_ids),
    };
    if (props.card) router.post(route('admin.tos.allegiance_cards.update', props.card.slug), payload);
    else router.post(route('admin.tos.allegiance_cards.store'), payload);
};

onMounted(() => {
    if (!props.card) return;
    formInfo.value.allegiance_id = props.card.allegiance_id;
    formInfo.value.name = props.card.name;
    formInfo.value.type = props.card.type;
    formInfo.value.secondary_type = props.card.secondary_type ?? null;
    formInfo.value.body = props.card.body;
    formInfo.value.primary_body = props.card.primary_body ?? null;
    formInfo.value.sort_order = props.card.sort_order;
    formInfo.value.ability_ids = props.card.abilities.map((a) => String(a.id));
    formInfo.value.action_ids = (props.card.actions ?? []).map((a) => String(a.id));
    formInfo.value.trigger_ids = (props.card.triggers ?? []).map((t) => String(t.id));
    formInfo.value.primary_ability_ids = (props.card.primary_abilities ?? []).map((a) => String(a.id));
    formInfo.value.primary_action_ids = (props.card.primary_actions ?? []).map((a) => String(a.id));
    formInfo.value.primary_trigger_ids = (props.card.primary_triggers ?? []).map((t) => String(t.id));
});
</script>

<template>
    <Head title="TOS Allegiance Card — Admin" />
    <div class="container mx-auto mt-6 px-4 pb-6">
        <Card>
            <CardHeader><CardTitle>{{ card ? 'Edit Allegiance Card' : 'New Allegiance Card' }}</CardTitle></CardHeader>
            <CardContent class="space-y-4">
                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <Label for="allegiance_id">Allegiance</Label>
                        <Select v-model.number="formInfo.allegiance_id">
                            <SelectTrigger><SelectValue placeholder="Allegiance" /></SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="a in allegiances" :key="a.id" :value="a.id">{{ a.name }}</SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="usePage().props.errors.allegiance_id" />
                    </div>
                    <div>
                        <Label for="type">Type</Label>
                        <Select v-model="formInfo.type">
                            <SelectTrigger><SelectValue placeholder="Type" /></SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="t in allegiance_types" :key="t.value" :value="t.value">{{ t.name }}</SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="usePage().props.errors.type" />
                    </div>
                    <div class="sm:col-span-2">
                        <Label for="secondary_type">Secondary Type (Hybrid only)</Label>
                        <Select id="secondary_type" v-model="formInfo.secondary_type">
                            <SelectTrigger>
                                <SelectValue placeholder="None — single-type Allegiance Card" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem :value="null">None</SelectItem>
                                <SelectItem v-for="t in allegiance_types" :key="t.value" :value="t.value">{{ t.name }}</SelectItem>
                            </SelectContent>
                        </Select>
                        <p class="text-[11px] text-muted-foreground">
                            Set only when an Allegiance Card lists both Earth and Malifaux on its face. Hybrid cards apply to
                            Allegiances of either type.
                        </p>
                        <InputError :message="usePage().props.errors.secondary_type" />
                    </div>
                    <div class="sm:col-span-2">
                        <Label for="name">Name</Label>
                        <Input id="name" v-model="formInfo.name" />
                        <InputError :message="usePage().props.errors.name" />
                    </div>
                </div>

                <!-- Standard tier -->
                <section class="rounded-md border-l-4 border-primary/60 bg-muted/30 p-3">
                    <h3 class="mb-2 text-xs font-semibold uppercase tracking-wider">Standard Tier</h3>
                    <div class="space-y-3">
                        <div>
                            <Label for="body">Body</Label>
                            <Textarea id="body" v-model="formInfo.body" rows="3" />
                        </div>
                        <div>
                            <Label>Standard Abilities</Label>
                            <SearchableMultiselect v-model="formInfo.ability_ids" placeholder="Search abilities…" :options="abilities" option-value="id" />
                        </div>
                        <div>
                            <Label>Standard Actions</Label>
                            <SearchableMultiselect v-model="formInfo.action_ids" placeholder="Search actions…" :options="actions" option-value="id" />
                        </div>
                        <div>
                            <Label>Standard Triggers</Label>
                            <SearchableMultiselect v-model="formInfo.trigger_ids" placeholder="Search triggers…" :options="triggers" option-value="id" />
                        </div>
                    </div>
                </section>

                <!-- Primary tier -->
                <section class="rounded-md border-l-4 border-amber-500/70 bg-amber-500/5 p-3">
                    <h3 class="mb-2 text-xs font-semibold uppercase tracking-wider text-amber-700 dark:text-amber-400">Primary Tier</h3>
                    <p class="mb-2 text-[11px] text-muted-foreground">Leave empty when the card has no Primary side.</p>
                    <div class="space-y-3">
                        <div>
                            <Label for="primary_body">Primary Body</Label>
                            <Textarea id="primary_body" v-model="formInfo.primary_body" rows="3" />
                        </div>
                        <div>
                            <Label>Primary Abilities</Label>
                            <SearchableMultiselect v-model="formInfo.primary_ability_ids" placeholder="Search abilities…" :options="abilities" option-value="id" />
                        </div>
                        <div>
                            <Label>Primary Actions</Label>
                            <SearchableMultiselect v-model="formInfo.primary_action_ids" placeholder="Search actions…" :options="actions" option-value="id" />
                        </div>
                        <div>
                            <Label>Primary Triggers</Label>
                            <SearchableMultiselect v-model="formInfo.primary_trigger_ids" placeholder="Search triggers…" :options="triggers" option-value="id" />
                        </div>
                    </div>
                </section>

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
                <Button variant="outline" @click="router.get(route('admin.tos.allegiance_cards.index'))">Cancel</Button>
                <Button @click="submit">Save</Button>
            </CardFooter>
        </Card>
    </div>
</template>
