<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import SearchableSelect from '@/components/SearchableSelect.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';

interface SculptRow {
    id: number;
    slug: string;
    unit_id: number;
    name: string;
    front_image: string | null;
    back_image: string | null;
    combination_image: string | null;
    release_date: string | null;
    box_reference: string | null;
    sort_order: number;
}

const props = defineProps<{
    sculpt?: SculptRow | null;
    units: Array<{ id: number; name: string }>;
}>();

const formInfo = ref({
    unit_id: null as string | null,
    name: '' as string,
    front_image: null as File | null,
    back_image: null as File | null,
    release_date: null as string | null,
    box_reference: null as string | null,
    sort_order: 0 as number,
});

function resolveImage(path: string | null | undefined): string | null {
    if (!path) return null;
    return path.startsWith('/') || path.startsWith('http') ? path : `/storage/${path}`;
}

const existingFront = computed(() => resolveImage(props.sculpt?.front_image));
const existingBack = computed(() => resolveImage(props.sculpt?.back_image));
const existingCombo = computed(() => resolveImage(props.sculpt?.combination_image));

const submit = () => {
    const payload = {
        ...formInfo.value,
        unit_id: formInfo.value.unit_id !== null ? Number.parseInt(formInfo.value.unit_id, 10) : null,
    };
    if (props.sculpt) router.post(route('admin.tos.sculpts.update', props.sculpt.slug), payload);
    else router.post(route('admin.tos.sculpts.store'), payload);
};

onMounted(() => {
    if (!props.sculpt) return;
    formInfo.value.unit_id = String(props.sculpt.unit_id);
    formInfo.value.name = props.sculpt.name;
    formInfo.value.release_date = props.sculpt.release_date;
    formInfo.value.box_reference = props.sculpt.box_reference;
    formInfo.value.sort_order = props.sculpt.sort_order;
});
</script>

<template>
    <Head title="TOS Sculpt — Admin" />
    <div class="container mx-auto mt-6 px-4 pb-6">
        <Card>
            <CardHeader><CardTitle>{{ sculpt ? 'Edit Sculpt' : 'New Sculpt' }}</CardTitle></CardHeader>
            <CardContent class="space-y-4">
                <div class="grid gap-3 md:grid-cols-2">
                    <div>
                        <Label for="unit_id">Unit</Label>
                        <SearchableSelect
                            v-model="formInfo.unit_id"
                            placeholder="Search units…"
                            :options="units"
                            option-value="id"
                        />
                        <InputError :message="usePage().props.errors.unit_id" />
                    </div>
                    <div>
                        <Label for="name">Name</Label>
                        <Input id="name" v-model="formInfo.name" />
                        <InputError :message="usePage().props.errors.name" />
                    </div>
                </div>

                <div class="grid gap-3 md:grid-cols-2">
                    <div class="space-y-1.5">
                        <Label for="front_image">Standard (Front) Image</Label>
                        <img
                            v-if="existingFront"
                            :src="existingFront"
                            :alt="formInfo.name || 'Front image'"
                            class="h-48 w-auto rounded border object-cover"
                        />
                        <Input
                            id="front_image"
                            type="file"
                            accept=".jpeg,.jpg"
                            @input="formInfo.front_image = ($event.target as HTMLInputElement).files?.[0] ?? null"
                        />
                        <p class="text-[11px] text-muted-foreground">JPG only. Up to 30 MB.</p>
                        <InputError :message="usePage().props.errors.front_image" />
                    </div>
                    <div class="space-y-1.5">
                        <Label for="back_image">Glory (Back) Image</Label>
                        <img
                            v-if="existingBack"
                            :src="existingBack"
                            :alt="formInfo.name || 'Back image'"
                            class="h-48 w-auto rounded border object-cover"
                        />
                        <Input
                            id="back_image"
                            type="file"
                            accept=".jpeg,.jpg"
                            @input="formInfo.back_image = ($event.target as HTMLInputElement).files?.[0] ?? null"
                        />
                        <p class="text-[11px] text-muted-foreground">JPG only. Up to 30 MB.</p>
                        <InputError :message="usePage().props.errors.back_image" />
                    </div>
                </div>

                <div v-if="existingCombo" class="space-y-1.5">
                    <Label>Generated Combo</Label>
                    <img :src="existingCombo" :alt="formInfo.name || 'Combo image'" class="h-48 w-auto rounded border object-cover" />
                    <p class="text-[11px] text-muted-foreground">Auto-generated from the front + back images above. Re-generated on image changes.</p>
                </div>

                <div class="grid gap-3 md:grid-cols-3">
                    <div>
                        <Label for="release_date">Release Date</Label>
                        <Input id="release_date" v-model="formInfo.release_date" type="date" />
                    </div>
                    <div>
                        <Label for="box_reference">Box Reference</Label>
                        <Input id="box_reference" v-model="formInfo.box_reference" />
                    </div>
                    <div>
                        <Label for="sort_order">Sort Order</Label>
                        <Input id="sort_order" v-model.number="formInfo.sort_order" type="number" min="0" />
                    </div>
                </div>

            </CardContent>
            <CardFooter class="flex justify-between px-6 pb-6">
                <Button variant="outline" @click="router.get(route('admin.tos.sculpts.index'))">Cancel</Button>
                <Button @click="submit">Save</Button>
            </CardFooter>
        </Card>
    </div>
</template>
