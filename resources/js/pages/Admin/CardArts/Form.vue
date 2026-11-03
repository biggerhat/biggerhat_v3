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

interface Art {
    id: number;
    name: string;
    faction: string | null;
    category: string | null;
    credit: string | null;
    image_path: string;
    is_active: boolean;
    sort_order: number;
}

const props = defineProps<{
    art: Art | null;
    factions: { value: string; name: string }[];
    categories: string[];
}>();

const page = usePage();
const errors = computed(() => page.props.errors as Record<string, string>);

const form = ref({
    name: props.art?.name ?? '',
    faction: props.art?.faction ?? 'any',
    category: props.art?.category ?? '',
    credit: props.art?.credit ?? '',
    is_active: props.art?.is_active ?? true,
    sort_order: props.art?.sort_order ?? 0,
});
const file = ref<File | null>(null);
const preview = ref<string | null>(props.art ? '/storage/' + props.art.image_path : null);
const saving = ref(false);

const onFile = (e: Event) => {
    const picked = (e.target as HTMLInputElement).files?.[0] ?? null;
    if (preview.value?.startsWith('blob:')) URL.revokeObjectURL(preview.value);
    file.value = picked;
    preview.value = picked ? URL.createObjectURL(picked) : props.art ? '/storage/' + props.art.image_path : null;
};
onBeforeUnmount(() => {
    if (preview.value?.startsWith('blob:')) URL.revokeObjectURL(preview.value);
});

const submit = () => {
    saving.value = true;
    router.post(
        props.art ? route('admin.card_arts.update', props.art.id) : route('admin.card_arts.store'),
        {
            ...form.value,
            faction: form.value.faction === 'any' ? null : form.value.faction,
            category: form.value.category || null,
            credit: form.value.credit || null,
            is_active: form.value.is_active ? 1 : 0,
            image: file.value,
        },
        { forceFormData: true, onFinish: () => (saving.value = false) },
    );
};
</script>

<template>
    <Head title="Card Art - Admin" />
    <div class="container mx-auto mt-6 max-w-2xl pb-10">
        <Card>
            <CardHeader>
                <CardTitle as="h1">{{ art ? 'Edit art' : 'Add art' }}</CardTitle>
                <CardDescription>
                    Shown in the character card editor's art picker. Use a clean portrait with room around the subject — it's cropped to the card's
                    art window (about 4:3).
                </CardDescription>
            </CardHeader>
            <CardContent>
                <form class="grid gap-4" @submit.prevent="submit">
                    <div class="flex flex-col space-y-1.5">
                        <Label for="image">Image {{ art ? '(leave empty to keep the current one)' : '' }}</Label>
                        <img v-if="preview" :src="preview" alt="Art preview" class="aspect-[4/3] max-w-xs rounded-md border bg-muted object-cover" />
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
                        <Input id="name" v-model="form.name" maxlength="120" placeholder="e.g. Hooded Gunslinger" />
                        <InputError :message="errors.name" />
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="flex flex-col space-y-1.5">
                            <Label for="faction">Faction</Label>
                            <Select id="faction" v-model="form.faction">
                                <SelectTrigger><SelectValue placeholder="Any faction" /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="any">Any faction</SelectItem>
                                    <SelectItem v-for="f in factions" :key="f.value" :value="f.value">{{ f.name }}</SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="errors.faction" />
                        </div>
                        <div class="flex flex-col space-y-1.5">
                            <Label for="category">Category</Label>
                            <Input
                                id="category"
                                v-model="form.category"
                                list="art-categories"
                                maxlength="60"
                                placeholder="e.g. Human, Construct, Beast"
                            />
                            <datalist id="art-categories">
                                <option v-for="c in categories" :key="c" :value="c" />
                            </datalist>
                            <InputError :message="errors.category" />
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
                            <p class="text-xs text-muted-foreground">Lower numbers appear first in the picker.</p>
                            <InputError :message="errors.sort_order" />
                        </div>
                        <label class="flex items-center gap-2 self-end pb-2 text-sm">
                            <Checkbox :checked="form.is_active" @update:checked="(v: boolean) => (form.is_active = v)" />
                            Active (available to players)
                        </label>
                    </div>
                </form>
            </CardContent>
            <CardFooter class="flex justify-between px-6 pb-6">
                <Button variant="outline" @click="router.get(route('admin.card_arts.index'))">Cancel</Button>
                <Button :disabled="saving" @click="submit">{{ saving ? 'Saving…' : 'Save' }}</Button>
            </CardFooter>
        </Card>
    </div>
</template>
