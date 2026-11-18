<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref } from 'vue';

interface Image {
    id: number;
    name: string;
    kind: 'table' | 'terrain';
    terrain_key: string | null;
    credit: string | null;
    is_active: boolean;
    sort_order: number;
    url: string;
}

const props = defineProps<{ image: Image | null; terrain_kinds: { value: string; label: string }[] }>();

const page = usePage();
const errors = computed(() => page.props.errors as Record<string, string>);

const form = ref({
    name: props.image?.name ?? '',
    kind: props.image?.kind ?? 'table',
    terrain_key: props.image?.terrain_key ?? props.terrain_kinds[0]?.value ?? '',
    credit: props.image?.credit ?? '',
    is_active: props.image?.is_active ?? true,
    sort_order: props.image?.sort_order ?? 0,
});

const file = ref<File | null>(null);
const preview = ref<string | null>(props.image?.url ?? null);
const saving = ref(false);

const onFile = (e: Event) => {
    const picked = (e.target as HTMLInputElement).files?.[0] ?? null;
    if (preview.value?.startsWith('blob:')) URL.revokeObjectURL(preview.value);
    file.value = picked;
    preview.value = picked ? URL.createObjectURL(picked) : (props.image?.url ?? null);
};
onBeforeUnmount(() => {
    if (preview.value?.startsWith('blob:')) URL.revokeObjectURL(preview.value);
});

const submit = () => {
    saving.value = true;
    router.post(
        props.image ? route('admin.tabletop_images.update', props.image.id) : route('admin.tabletop_images.store'),
        {
            ...form.value,
            terrain_key: form.value.kind === 'terrain' ? form.value.terrain_key : null,
            credit: form.value.credit || null,
            is_active: form.value.is_active ? 1 : 0,
            image: file.value,
        },
        { forceFormData: true, onFinish: () => (saving.value = false) },
    );
};
</script>

<template>
    <Head title="Tabletop Image - Admin" />
    <div class="container mx-auto mt-6 max-w-2xl pb-10">
        <Card>
            <CardHeader>
                <CardTitle as="h1">{{ image ? 'Edit image' : 'Add image' }}</CardTitle>
                <CardDescription>
                    A table surface is stretched over the whole 36" table, so a square picture works best. A terrain texture repeats across every
                    piece of that kind of terrain (one repeat is 6"), so use a square picture that tiles.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <form class="grid gap-4" @submit.prevent="submit">
                    <div class="flex flex-col space-y-1.5">
                        <Label for="image">Image {{ image ? '(leave empty to keep the current one)' : '' }}</Label>
                        <img v-if="preview" :src="preview" alt="Preview" class="aspect-square max-w-xs rounded-md border bg-muted object-cover" />
                        <input
                            id="image"
                            type="file"
                            accept="image/png,image/jpeg,image/webp"
                            class="text-sm file:mr-3 file:rounded-md file:border file:bg-background file:px-3 file:py-1.5 file:text-sm"
                            @change="onFile"
                        />
                        <p class="text-xs text-muted-foreground">PNG, JPG or WebP, up to 5 MB.</p>
                        <InputError :message="errors.image" />
                    </div>
                    <div class="flex flex-col space-y-1.5">
                        <Label for="name">Name</Label>
                        <Input id="name" v-model="form.name" maxlength="120" placeholder="e.g. Marble floor" />
                        <InputError :message="errors.name" />
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="flex flex-col space-y-1.5">
                            <Label for="kind">What it is</Label>
                            <Select id="kind" v-model="form.kind">
                                <SelectTrigger><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="table">Table surface</SelectItem>
                                    <SelectItem value="terrain">Terrain texture</SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="errors.kind" />
                        </div>
                        <div v-if="form.kind === 'terrain'" class="flex flex-col space-y-1.5">
                            <Label for="terrain_key">Terrain it is for</Label>
                            <Select id="terrain_key" v-model="form.terrain_key">
                                <SelectTrigger><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="k in terrain_kinds" :key="k.value" :value="k.value">{{ k.label }}</SelectItem>
                                </SelectContent>
                            </Select>
                            <p class="text-xs text-muted-foreground">Replaces the built-in texture for this kind of terrain.</p>
                            <InputError :message="errors.terrain_key" />
                        </div>
                    </div>
                    <div class="flex flex-col space-y-1.5">
                        <Label for="credit">Credit (optional)</Label>
                        <Input id="credit" v-model="form.credit" maxlength="255" placeholder="Artist or source" />
                        <InputError :message="errors.credit" />
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="flex flex-col space-y-1.5">
                            <Label for="sort_order">Sort order</Label>
                            <Input id="sort_order" v-model.number="form.sort_order" type="number" min="0" />
                            <p class="text-xs text-muted-foreground">
                                Lower numbers are listed first, and win when two textures are for the same terrain.
                            </p>
                            <InputError :message="errors.sort_order" />
                        </div>
                        <label class="flex items-center gap-2 self-end pb-2 text-sm">
                            <Checkbox :checked="form.is_active" @update:checked="(v: boolean) => (form.is_active = v)" />
                            Active (shown on the tabletop)
                        </label>
                    </div>
                </form>
            </CardContent>
            <CardFooter class="flex justify-between px-6 pb-6">
                <Button variant="outline" @click="router.get(route('admin.tabletop_images.index'))">Cancel</Button>
                <Button :disabled="saving" @click="submit">{{ saving ? 'Saving…' : 'Save' }}</Button>
            </CardFooter>
        </Card>
    </div>
</template>
