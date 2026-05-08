<script setup lang="ts">
import SearchableMultiselect from '@/components/SearchableMultiselect.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft, ImageOff, Save, Trash2, Upload } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface Linked {
    id: number;
    name: string;
    slug: string;
    pivot?: { is_signature_action?: boolean };
}

interface ActionOption {
    id: number;
    name: string;
    slug: string;
    is_signature: boolean;
}

interface NamedOption {
    id: number;
    name: string;
    slug: string;
}

interface LootCardRow {
    id: number;
    slug: string;
    suit: string;
    value: number | null;
    value_label: string;
    name: string;
    title_a: string | null;
    title_b: string | null;
    effect_a: string | null;
    effect_b: string | null;
    image: string | null;
    side_a_actions: Linked[];
    side_b_actions: Linked[];
    side_a_abilities: Linked[];
    side_b_abilities: Linked[];
    side_a_triggers: Linked[];
    side_b_triggers: Linked[];
}

const props = defineProps<{
    card: LootCardRow;
    all_actions: ActionOption[] | null;
    all_abilities: NamedOption[] | null;
    all_triggers: NamedOption[] | null;
}>();

// Multi-select inputs work in slug arrays — convert the linked entities to
// slugs on init, and back to id arrays on submit (server expects ids).
const slugList = (rows: { slug: string }[]) => rows.map((r) => r.slug);

const sideAActionRows = ref<{ slug: string; is_signature_action: boolean }[]>(
    props.card.side_a_actions.map((a) => ({ slug: a.slug, is_signature_action: !!a.pivot?.is_signature_action })),
);
const sideBActionRows = ref<{ slug: string; is_signature_action: boolean }[]>(
    props.card.side_b_actions.map((a) => ({ slug: a.slug, is_signature_action: !!a.pivot?.is_signature_action })),
);

const sideAAbilitySlugs = ref<string[]>(slugList(props.card.side_a_abilities));
const sideBAbilitySlugs = ref<string[]>(slugList(props.card.side_b_abilities));
const sideATriggerSlugs = ref<string[]>(slugList(props.card.side_a_triggers));
const sideBTriggerSlugs = ref<string[]>(slugList(props.card.side_b_triggers));

const actionOptions = computed(() => (props.all_actions ?? []).map((a) => ({ value: a.slug, name: a.name })));
const abilityOptions = computed(() => (props.all_abilities ?? []).map((a) => ({ value: a.slug, name: a.name })));
const triggerOptions = computed(() => (props.all_triggers ?? []).map((t) => ({ value: t.slug, name: t.name })));

const slugToActionId = computed(() => {
    const map: Record<string, number> = {};
    for (const a of props.all_actions ?? []) map[a.slug] = a.id;
    return map;
});
const slugToAbilityId = computed(() => {
    const map: Record<string, number> = {};
    for (const a of props.all_abilities ?? []) map[a.slug] = a.id;
    return map;
});
const slugToTriggerId = computed(() => {
    const map: Record<string, number> = {};
    for (const t of props.all_triggers ?? []) map[t.slug] = t.id;
    return map;
});

// Image upload — separate from useForm since the file input is its own ref.
const imageFile = ref<File | null>(null);
const removeImage = ref(false);
const onImageChange = (event: Event) => {
    const target = event.target as HTMLInputElement;
    imageFile.value = target.files?.[0] ?? null;
    if (imageFile.value) removeImage.value = false;
};

// useForm gives us errors, processing, and CSRF for free; we attach the
// non-form values just before submit since the relations / image are tracked
// in their own refs above.
const form = useForm<Record<string, unknown>>({
    name: props.card.name,
    title_a: props.card.title_a ?? '',
    title_b: props.card.title_b ?? '',
    effect_a: props.card.effect_a ?? '',
    effect_b: props.card.effect_b ?? '',
    side_a_actions: [],
    side_b_actions: [],
    side_a_abilities: [],
    side_b_abilities: [],
    side_a_triggers: [],
    side_b_triggers: [],
    image: null as File | null,
    remove_image: false,
});

// Toggle is_signature_action for an action row. Triggered from the action row
// list rendered below the multiselect.
const toggleSignature = (rows: typeof sideAActionRows.value, slug: string) => {
    const row = rows.find((r) => r.slug === slug);
    if (row) row.is_signature_action = !row.is_signature_action;
};

// Sync the action-rows array with the multiselect's slug array. New slugs
// arrive without is_signature; existing rows preserve their flag.
const syncActionRows = (rows: typeof sideAActionRows.value, slugs: string[]) => {
    const next: typeof rows = [];
    for (const s of slugs) {
        const existing = rows.find((r) => r.slug === s);
        next.push(existing ?? { slug: s, is_signature_action: false });
    }
    rows.splice(0, rows.length, ...next);
};

const sideASlugs = computed({
    get: () => sideAActionRows.value.map((r) => r.slug),
    set: (slugs: string[]) => syncActionRows(sideAActionRows.value, slugs),
});
const sideBSlugs = computed({
    get: () => sideBActionRows.value.map((r) => r.slug),
    set: (slugs: string[]) => syncActionRows(sideBActionRows.value, slugs),
});

const submit = () => {
    // Build the form payload from the side-row state at submit time.
    form.side_a_actions = sideAActionRows.value
        .filter((r) => slugToActionId.value[r.slug])
        .map((r) => ({ action_id: slugToActionId.value[r.slug], is_signature_action: r.is_signature_action }));
    form.side_b_actions = sideBActionRows.value
        .filter((r) => slugToActionId.value[r.slug])
        .map((r) => ({ action_id: slugToActionId.value[r.slug], is_signature_action: r.is_signature_action }));
    form.side_a_abilities = sideAAbilitySlugs.value.map((s) => slugToAbilityId.value[s]).filter(Boolean);
    form.side_b_abilities = sideBAbilitySlugs.value.map((s) => slugToAbilityId.value[s]).filter(Boolean);
    form.side_a_triggers = sideATriggerSlugs.value.map((s) => slugToTriggerId.value[s]).filter(Boolean);
    form.side_b_triggers = sideBTriggerSlugs.value.map((s) => slugToTriggerId.value[s]).filter(Boolean);
    form.image = imageFile.value;
    form.remove_image = removeImage.value;

    form.post(route('admin.loot_cards.update', props.card.slug), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => router.visit(route('admin.loot_cards.index')),
    });
};

const actionLabelFor = (slug: string) => actionOptions.value.find((o) => o.value === slug)?.name ?? slug;
</script>

<template>
    <Head :title="`Edit ${card.name} · Loot Card`" />

    <div class="container mx-auto space-y-4 p-4 lg:p-6">
        <Link :href="route('admin.loot_cards.index')">
            <Button variant="ghost" size="sm" class="gap-1.5 text-sm">
                <ArrowLeft class="size-4" /> Back to Loot Cards
            </Button>
        </Link>

        <div>
            <h1 class="text-xl font-bold">{{ card.name }}</h1>
            <p class="text-sm text-muted-foreground">
                <span class="capitalize">{{ card.suit }}</span> · <span class="font-mono">{{ card.value_label }}</span>
            </p>
        </div>

        <form @submit.prevent="submit" class="space-y-4">
            <!-- Card-level fields -->
            <Card>
                <CardContent class="space-y-4 p-4">
                    <div class="space-y-1.5">
                        <Label for="name">Card Name</Label>
                        <Input id="name" v-model="form.name" />
                        <p v-if="form.errors.name" class="text-xs text-destructive">{{ form.errors.name }}</p>
                    </div>

                    <div class="space-y-1.5">
                        <Label>Card Image</Label>
                        <div v-if="card.image && !removeImage" class="flex items-center gap-3">
                            <img :src="`/storage/${card.image}`" :alt="card.name" class="size-24 rounded-md border object-cover" />
                            <Button type="button" variant="outline" size="sm" class="gap-1.5" @click="removeImage = true">
                                <Trash2 class="size-3.5" /> Remove image
                            </Button>
                        </div>
                        <div v-else-if="removeImage" class="flex items-center gap-3 text-xs text-muted-foreground">
                            <ImageOff class="size-4" /> Image will be removed on save.
                            <Button type="button" variant="ghost" size="sm" class="text-xs" @click="removeImage = false">Undo</Button>
                        </div>
                        <label class="flex cursor-pointer items-center gap-2 rounded-md border border-dashed bg-muted/30 px-3 py-2 text-xs hover:bg-muted/50">
                            <Upload class="size-4 text-muted-foreground" />
                            <span class="flex-1 text-muted-foreground">{{ imageFile?.name ?? 'Upload a new image (jpeg, png, webp, heic; max 30MB)' }}</span>
                            <input type="file" accept="image/*" class="hidden" @change="onImageChange" />
                        </label>
                        <p v-if="form.errors.image" class="text-xs text-destructive">{{ form.errors.image }}</p>
                    </div>
                </CardContent>
            </Card>

            <!-- Per-side blocks. Two parallel sections — each carries its own
                 title, effect, and three relation pickers (abilities / actions /
                 triggers). The action picker has an inline signature toggle. -->
            <div class="grid gap-4 lg:grid-cols-2">
                <Card>
                    <CardContent class="space-y-4 p-4">
                        <div class="flex items-center gap-2">
                            <span class="inline-flex size-6 items-center justify-center rounded-full bg-primary/15 text-xs font-bold text-primary">A</span>
                            <h2 class="text-sm font-semibold">Side A</h2>
                        </div>

                        <div class="space-y-1.5">
                            <Label for="title_a">Title</Label>
                            <Input id="title_a" v-model="form.title_a" placeholder="e.g. Bag of Gold" />
                        </div>

                        <div class="space-y-1.5">
                            <Label for="effect_a">Effect</Label>
                            <Textarea id="effect_a" v-model="form.effect_a" rows="4" placeholder="Side A effect text…" />
                            <p v-if="form.errors.effect_a" class="text-xs text-destructive">{{ form.errors.effect_a }}</p>
                        </div>

                        <div class="space-y-1.5">
                            <Label>Granted Abilities</Label>
                            <SearchableMultiselect v-model="sideAAbilitySlugs" :options="abilityOptions" placeholder="Search abilities…" />
                        </div>

                        <div class="space-y-1.5">
                            <Label>Granted Actions</Label>
                            <SearchableMultiselect v-model="sideASlugs" :options="actionOptions" placeholder="Search actions…" />
                            <ul v-if="sideAActionRows.length" class="space-y-1 rounded-md border bg-muted/20 p-2 text-xs">
                                <li v-for="row in sideAActionRows" :key="`a-${row.slug}`" class="flex items-center justify-between gap-2">
                                    <span class="font-medium">{{ actionLabelFor(row.slug) }}</span>
                                    <label class="flex shrink-0 items-center gap-1.5 text-[11px] text-muted-foreground">
                                        <Checkbox :model-value="row.is_signature_action" @update:model-value="toggleSignature(sideAActionRows, row.slug)" />
                                        Signature
                                    </label>
                                </li>
                            </ul>
                        </div>

                        <div class="space-y-1.5">
                            <Label>Granted Triggers</Label>
                            <SearchableMultiselect v-model="sideATriggerSlugs" :options="triggerOptions" placeholder="Search triggers…" />
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent class="space-y-4 p-4">
                        <div class="flex items-center gap-2">
                            <span class="inline-flex size-6 items-center justify-center rounded-full bg-primary/15 text-xs font-bold text-primary">B</span>
                            <h2 class="text-sm font-semibold">Side B</h2>
                        </div>

                        <div class="space-y-1.5">
                            <Label for="title_b">Title</Label>
                            <Input id="title_b" v-model="form.title_b" placeholder="e.g. Hoard" />
                        </div>

                        <div class="space-y-1.5">
                            <Label for="effect_b">Effect</Label>
                            <Textarea id="effect_b" v-model="form.effect_b" rows="4" placeholder="Side B effect text…" />
                            <p v-if="form.errors.effect_b" class="text-xs text-destructive">{{ form.errors.effect_b }}</p>
                        </div>

                        <div class="space-y-1.5">
                            <Label>Granted Abilities</Label>
                            <SearchableMultiselect v-model="sideBAbilitySlugs" :options="abilityOptions" placeholder="Search abilities…" />
                        </div>

                        <div class="space-y-1.5">
                            <Label>Granted Actions</Label>
                            <SearchableMultiselect v-model="sideBSlugs" :options="actionOptions" placeholder="Search actions…" />
                            <ul v-if="sideBActionRows.length" class="space-y-1 rounded-md border bg-muted/20 p-2 text-xs">
                                <li v-for="row in sideBActionRows" :key="`b-${row.slug}`" class="flex items-center justify-between gap-2">
                                    <span class="font-medium">{{ actionLabelFor(row.slug) }}</span>
                                    <label class="flex shrink-0 items-center gap-1.5 text-[11px] text-muted-foreground">
                                        <Checkbox :model-value="row.is_signature_action" @update:model-value="toggleSignature(sideBActionRows, row.slug)" />
                                        Signature
                                    </label>
                                </li>
                            </ul>
                        </div>

                        <div class="space-y-1.5">
                            <Label>Granted Triggers</Label>
                            <SearchableMultiselect v-model="sideBTriggerSlugs" :options="triggerOptions" placeholder="Search triggers…" />
                        </div>
                    </CardContent>
                </Card>
            </div>

            <p class="text-[11px] text-muted-foreground">
                Reference the canonical effect text at
                <a href="https://wyrdgames.net/bonanza-loot-deck" target="_blank" rel="noopener" class="text-primary underline-offset-2 hover:underline">
                    wyrdgames.net/bonanza-loot-deck
                </a>.
            </p>

            <div class="flex justify-end">
                <Button type="submit" :disabled="form.processing" class="gap-1.5">
                    <Save class="size-4" /> Save
                </Button>
            </div>
        </form>
    </div>
</template>
