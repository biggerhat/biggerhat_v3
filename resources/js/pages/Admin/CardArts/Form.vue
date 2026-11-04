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
    character_id: number | null;
}

const props = defineProps<{
    art: Art | null;
    linked_character: { id: number; name: string } | null;
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

// Optional link to the official character this art depicts — it is suggested first when a card is cloned from, or named
// after, that character. Plain search over the official characters; nothing about the link restricts who can use the art.
const linked = ref<{ id: number; name: string } | null>(props.linked_character);
const characterQuery = ref('');
const characterResults = ref<{ id: number; name: string }[]>([]);
let characterTimer: ReturnType<typeof setTimeout> | undefined;
let characterRequest = 0;
const searchCharacters = (q: string) => {
    characterQuery.value = q;
    clearTimeout(characterTimer);
    if (q.length < 2) {
        characterResults.value = [];
        return;
    }
    characterTimer = setTimeout(async () => {
        const id = ++characterRequest;
        try {
            const res = await fetch(route('api.card-creator.characters') + '?q=' + encodeURIComponent(q), {
                headers: { Accept: 'application/json' },
            });
            if (!res.ok || id !== characterRequest) return;
            characterResults.value = (await res.json()).filter((r: { source_type: string }) => r.source_type === 'official');
        } catch {
            characterResults.value = [];
        }
    }, 300);
};
const linkCharacter = (c: { id: number; name: string }) => {
    linked.value = c;
    characterQuery.value = '';
    characterResults.value = [];
};
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
            character_id: linked.value?.id ?? null,
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
                        <Label for="character">Depicts (optional)</Label>
                        <div v-if="linked" class="flex items-center justify-between gap-2 rounded-md border bg-muted/30 px-3 py-1.5 text-sm">
                            <span class="truncate font-medium">{{ linked.name }}</span>
                            <Button type="button" variant="ghost" size="sm" class="h-6 px-2 text-xs" @click="linked = null">Unlink</Button>
                        </div>
                        <div v-else class="relative">
                            <Input
                                id="character"
                                :model-value="characterQuery"
                                placeholder="Search for the official character this art shows…"
                                autocomplete="off"
                                @update:model-value="(v) => searchCharacters(String(v))"
                            />
                            <div
                                v-if="characterResults.length"
                                class="absolute z-10 mt-1 max-h-48 w-full overflow-auto rounded-md border bg-popover p-1 shadow-md"
                            >
                                <button
                                    v-for="r in characterResults"
                                    :key="r.id"
                                    type="button"
                                    class="w-full rounded px-2 py-1.5 text-left text-sm hover:bg-accent"
                                    @click="linkCharacter(r)"
                                >
                                    {{ r.name }}
                                </button>
                            </div>
                        </div>
                        <p class="text-xs text-muted-foreground">
                            Linked art is suggested first when a player clones or names a card after that character. It doesn't limit who can use it.
                        </p>
                        <InputError :message="errors.character_id" />
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
