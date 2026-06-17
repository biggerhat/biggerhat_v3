<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import type { TosSelectOption } from '@/types/tos';
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';

interface StratagemRow {
    id: number;
    slug: string;
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
    allegiance_types: TosSelectOption[];
}>();

// Rulebook p. 13: a Stratagem is scoped to a specific Allegiance OR an
// Allegiance Type OR neither (universal) — never both. The segmented control
// mirrors the suits/margin/none control on TriggerForm and guarantees only one
// of the two fields is ever populated, so the `prohibits` validation never fires.
type ScopeType = 'allegiance' | 'type' | 'universal';

const scope = ref<ScopeType>('universal');

const formInfo = ref({
    name: '' as string,
    allegiance_id: null as number | null,
    allegiance_type: null as string | null,
    tactical_cost: 1 as number,
    effect: null as string | null,
    image_path: null as File | null,
    sort_order: 0 as number,
});

function setScope(next: ScopeType) {
    scope.value = next;
    if (next !== 'allegiance') formInfo.value.allegiance_id = null;
    if (next !== 'type') formInfo.value.allegiance_type = null;
}

const existingImage = computed<string | null>(() => {
    const path = props.stratagem?.image_path;
    if (!path) return null;
    return path.startsWith('/') || path.startsWith('http') ? path : `/storage/${path}`;
});

const submit = () => {
    // forceFormData because image_path is a File — without it Inertia JSON-encodes
    // the payload and the file silently becomes a string (matches AllegianceForm).
    const options = { forceFormData: true };
    if (props.stratagem) router.post(route('admin.tos.stratagems.update', props.stratagem.slug), formInfo.value, options);
    else router.post(route('admin.tos.stratagems.store'), formInfo.value, options);
};

onMounted(() => {
    if (!props.stratagem) return;
    formInfo.value.name = props.stratagem.name;
    formInfo.value.allegiance_id = props.stratagem.allegiance_id;
    formInfo.value.allegiance_type = props.stratagem.allegiance_type;
    formInfo.value.tactical_cost = props.stratagem.tactical_cost;
    formInfo.value.effect = props.stratagem.effect;
    formInfo.value.sort_order = props.stratagem.sort_order;

    // Derive scope from the record. allegiance_id wins if both happen to be set
    // (rulebook p. 13: specific Allegiance beats type) — setScope then clears the
    // counterpart so a legacy record with both never re-submits both.
    if (props.stratagem.allegiance_id !== null) setScope('allegiance');
    else if (props.stratagem.allegiance_type !== null) setScope('type');
    else setScope('universal');
});
</script>

<template>
    <Head title="TOS Stratagem — Admin" />
    <div class="container mx-auto mt-6 px-4 pb-6">
        <Card>
            <CardHeader
                ><CardTitle>{{ stratagem ? 'Edit Stratagem' : 'New Stratagem' }}</CardTitle></CardHeader
            >
            <CardContent class="space-y-3">
                <div>
                    <Label for="name">Name</Label>
                    <Input id="name" v-model="formInfo.name" />
                    <InputError :message="usePage().props.errors.name" />
                </div>
                <div>
                    <Label>Scope</Label>
                    <div
                        class="mt-1 inline-flex items-center rounded-md border border-input bg-background/60 p-0.5 text-xs font-medium"
                        role="group"
                        aria-label="Stratagem scope"
                    >
                        <button
                            type="button"
                            class="inline-flex h-7 items-center rounded px-3 transition-colors"
                            :class="
                                scope === 'allegiance' ? 'bg-primary text-primary-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'
                            "
                            @click="setScope('allegiance')"
                        >
                            Specific Allegiance
                        </button>
                        <button
                            type="button"
                            class="inline-flex h-7 items-center rounded px-3 transition-colors"
                            :class="scope === 'type' ? 'bg-primary text-primary-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'"
                            @click="setScope('type')"
                        >
                            Allegiance Type
                        </button>
                        <button
                            type="button"
                            class="inline-flex h-7 items-center rounded px-3 transition-colors"
                            :class="
                                scope === 'universal' ? 'bg-primary text-primary-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'
                            "
                            @click="setScope('universal')"
                        >
                            Universal
                        </button>
                    </div>
                    <p class="mt-1 text-[11px] text-muted-foreground">
                        A Stratagem keys to a specific Allegiance, OR to an Allegiance Type (available to any Allegiance of that type), OR is Universal —
                        never both (rulebook p. 13).
                    </p>
                </div>
                <div>
                    <div v-if="scope === 'allegiance'">
                        <Label for="allegiance_id">Allegiance</Label>
                        <Select v-model.number="formInfo.allegiance_id">
                            <SelectTrigger><SelectValue placeholder="Choose an Allegiance" /></SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="a in allegiances" :key="a.id" :value="a.id">{{ a.name }}</SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="usePage().props.errors.allegiance_id" />
                    </div>
                    <div v-else-if="scope === 'type'">
                        <Label for="allegiance_type">Allegiance Type</Label>
                        <Select v-model="formInfo.allegiance_type">
                            <SelectTrigger><SelectValue placeholder="Choose a Type" /></SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="t in allegiance_types" :key="t.value" :value="t.value">{{ t.name }}</SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="usePage().props.errors.allegiance_type" />
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
