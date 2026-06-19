<script setup lang="ts">
import BonanzaSplitCard from '@/components/Bonanza/BonanzaSplitCard.vue';
import SearchableMultiselect from '@/components/SearchableMultiselect.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { captureLootCardImage } from '@/composables/useLootCardCapture';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft, ImageOff, Save, Trash2, Upload } from 'lucide-vue-next';
import { computed, ref } from 'vue';

// Union of fields for ability/action/trigger entities — populated as
// available so the BonanzaSplitCard preview can render with as much
// detail as the loaded data supports.
interface Linked {
    id: number;
    name: string;
    slug: string;
    type?: string;
    is_signature?: boolean;
    stone_cost?: number;
    range?: number | null;
    range_type?: string | null;
    stat?: number | null;
    stat_suits?: string | null;
    stat_modifier?: string | null;
    resisted_by?: string | null;
    target_number?: number | null;
    target_suits?: string | null;
    damage?: number | string | null;
    triggers?: Array<{ id?: number; name: string; suits?: string | null; stone_cost?: number; description?: string | null }>;
    // Ability fields
    suits?: string | null;
    defensive_ability_type?: string | null;
    costs_stone?: boolean;
    description?: string | null;
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
    /** Null when creating a new card; populated when editing. */
    card: LootCardRow | null;
    all_actions: ActionOption[] | null;
    all_abilities: NamedOption[] | null;
    all_triggers: NamedOption[] | null;
}>();

const isCreate = computed(() => props.card === null);

const slugList = (rows: { slug: string }[]) => rows.map((r) => r.slug);

// Suit + value are create-only; immutable on edit.
const newSuit = ref<string>('crow');
const newValue = ref<number | null>(null);
const newValueLabel = ref<string>('');

const isJoker = computed(() => newSuit.value === 'joker');

/**
 * Mirror of `LootCardAdminController::resolveValueLabel`. The loot deck is
 * numeric-only (no A/J/Q/K) — non-jokers print their face as the raw value;
 * jokers use the admin-supplied label.
 */
const derivedValueLabel = computed<string>(() => {
    if (isJoker.value) return newValueLabel.value.trim() || 'Joker';
    return newValue.value === null ? '' : String(newValue.value);
});

const sideAActionRows = ref<{ slug: string; is_signature_action: boolean }[]>(
    (props.card?.side_a_actions ?? []).map((a) => ({ slug: a.slug, is_signature_action: !!a.pivot?.is_signature_action })),
);
const sideBActionRows = ref<{ slug: string; is_signature_action: boolean }[]>(
    (props.card?.side_b_actions ?? []).map((a) => ({ slug: a.slug, is_signature_action: !!a.pivot?.is_signature_action })),
);

const sideAAbilitySlugs = ref<string[]>(slugList(props.card?.side_a_abilities ?? []));
const sideBAbilitySlugs = ref<string[]>(slugList(props.card?.side_b_abilities ?? []));
const sideATriggerSlugs = ref<string[]>(slugList(props.card?.side_a_triggers ?? []));
const sideBTriggerSlugs = ref<string[]>(slugList(props.card?.side_b_triggers ?? []));

const actionOptions = computed(() =>
    (props.all_actions ?? []).map((a) => ({
        value: a.slug,
        name: `${a.name} (#${a.id})`,
    })),
);
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

const imageFile = ref<File | null>(null);
const removeImage = ref(false);
const capturing = ref(false);
const onImageChange = (event: Event) => {
    const target = event.target as HTMLInputElement;
    imageFile.value = target.files?.[0] ?? null;
    if (imageFile.value) removeImage.value = false;
};

const form = useForm<Record<string, unknown>>({
    name: props.card?.name ?? '',
    title_a: props.card?.title_a ?? '',
    title_b: props.card?.title_b ?? '',
    effect_a: props.card?.effect_a ?? '',
    effect_b: props.card?.effect_b ?? '',
    side_a_actions: [],
    side_b_actions: [],
    side_a_abilities: [],
    side_b_abilities: [],
    side_a_triggers: [],
    side_b_triggers: [],
    image: null as File | null,
    remove_image: false,
    // Create-only — the server ignores these on update.
    suit: 'crow',
    value: null as number | null,
    value_label: '',
});

const toggleSignature = (rows: typeof sideAActionRows.value, slug: string) => {
    const row = rows.find((r) => r.slug === slug);
    if (row) row.is_signature_action = !row.is_signature_action;
};

// New slugs arrive without is_signature; existing rows preserve their flag.
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

// Later sources overwrite earlier — pass lite `all_*` first so card-side
// relations (with full description/stat fields) win.
const buildLookup = <T extends { slug: string }>(...sources: (T[] | null | undefined)[]): Record<string, T> => {
    const map: Record<string, T> = {};
    for (const source of sources) {
        for (const item of source ?? []) map[item.slug] = item;
    }
    return map;
};

const abilityLookup = computed(() =>
    buildLookup<Linked | NamedOption>(props.all_abilities, props.card?.side_a_abilities, props.card?.side_b_abilities),
);
const actionLookup = computed(() => buildLookup<Linked | ActionOption>(props.all_actions, props.card?.side_a_actions, props.card?.side_b_actions));
const triggerLookup = computed(() => buildLookup<Linked | NamedOption>(props.all_triggers, props.card?.side_a_triggers, props.card?.side_b_triggers));

const previewAbilities = (slugs: string[]) => slugs.map((s) => abilityLookup.value[s]).filter(Boolean) as Linked[];
const previewActions = (rows: { slug: string; is_signature_action: boolean }[]) =>
    rows
        .map((row) => {
            const base = actionLookup.value[row.slug];
            if (!base) return null;
            return { ...base, pivot: { is_signature_action: row.is_signature_action }, is_signature: row.is_signature_action };
        })
        .filter(Boolean) as Linked[];
const previewTriggers = (slugs: string[]) => slugs.map((s) => triggerLookup.value[s]).filter(Boolean) as Linked[];

const previewSideA = computed(() => ({
    title: (form.title_a as string | null) || null,
    effect: (form.effect_a as string | null) || null,
    abilities: previewAbilities(sideAAbilitySlugs.value),
    actions: previewActions(sideAActionRows.value),
    triggers: previewTriggers(sideATriggerSlugs.value),
}));
const previewSideB = computed(() => ({
    title: (form.title_b as string | null) || null,
    effect: (form.effect_b as string | null) || null,
    abilities: previewAbilities(sideBAbilitySlugs.value),
    actions: previewActions(sideBActionRows.value),
    triggers: previewTriggers(sideBTriggerSlugs.value),
}));

const previewSuit = computed(() => (isCreate.value ? newSuit.value : props.card!.suit));
const previewValueLabel = computed(() => (isCreate.value ? derivedValueLabel.value || '?' : props.card!.value_label));

const previewRef = ref<HTMLDivElement | null>(null);

const captureCardImage = async (): Promise<File | null> => {
    if (!previewRef.value) return null;
    const target = previewRef.value.firstElementChild as HTMLElement | null;
    if (!target) return null;

    return captureLootCardImage(target, (form.name as string) || `loot-card-${Date.now()}`);
};

const submit = async () => {
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
    form.remove_image = removeImage.value;
    form.suit = newSuit.value;
    form.value = isJoker.value ? null : newValue.value;
    form.value_label = derivedValueLabel.value;

    // Manual upload wins; otherwise always capture the live preview (even after a remove).
    if (imageFile.value) {
        form.image = imageFile.value;
    } else {
        capturing.value = true;
        try {
            form.image = await captureCardImage();
        } finally {
            capturing.value = false;
        }
    }

    const url = isCreate.value ? route('admin.loot_cards.store') : route('admin.loot_cards.update', props.card!.slug);

    form.post(url, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => router.visit(route('admin.loot_cards.index')),
    });
};

const actionLabelFor = (slug: string) => actionOptions.value.find((o) => o.value === slug)?.name ?? slug;
</script>

<template>
    <Head :title="isCreate ? 'New Loot Card · Admin' : `Edit ${card!.name} · Loot Card`" />

    <div class="container mx-auto space-y-4 p-4 lg:p-6">
        <Link :href="route('admin.loot_cards.index')">
            <Button variant="ghost" size="sm" class="gap-1.5 text-sm"> <ArrowLeft class="size-4" /> Back to Loot Cards </Button>
        </Link>

        <div>
            <h1 class="text-xl font-bold">{{ isCreate ? 'New Loot Card' : card!.name }}</h1>
            <p v-if="!isCreate" class="text-sm text-muted-foreground">
                <span class="capitalize">{{ card!.suit }}</span> · <span class="font-mono">{{ card!.value_label }}</span>
            </p>
            <p v-else class="text-sm text-muted-foreground">Add a homebrew or expansion card to the Bonanza Brawl loot deck.</p>
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

                    <div v-if="isCreate" class="grid gap-3 sm:grid-cols-2">
                        <div class="space-y-1.5">
                            <Label for="suit">Suit</Label>
                            <select id="suit" v-model="newSuit" class="h-9 w-full rounded-md border border-input bg-background px-2 text-sm">
                                <option value="crow">Crow</option>
                                <option value="mask">Mask</option>
                                <option value="ram">Ram</option>
                                <option value="tome">Tome</option>
                                <option value="joker">Joker</option>
                            </select>
                            <p v-if="form.errors.suit" class="text-xs text-destructive">{{ form.errors.suit }}</p>
                        </div>
                        <div v-if="!isJoker" class="space-y-1.5">
                            <Label for="value">Value</Label>
                            <Input id="value" v-model.number="newValue" type="number" min="1" max="13" placeholder="1-13" />
                            <p class="text-[11px] text-muted-foreground">
                                Printed face is derived automatically:
                                <span class="font-mono">{{ derivedValueLabel || '—' }}</span>
                            </p>
                            <p v-if="form.errors.value" class="text-xs text-destructive">{{ form.errors.value }}</p>
                        </div>
                        <div v-else class="space-y-1.5">
                            <Label for="value_label">Display Label</Label>
                            <select
                                id="value_label"
                                v-model="newValueLabel"
                                class="h-9 w-full rounded-md border border-input bg-background px-2 text-sm"
                            >
                                <option value="">Choose…</option>
                                <option value="Red Joker">Red Joker</option>
                                <option value="Black Joker">Black Joker</option>
                            </select>
                            <p v-if="form.errors.value_label" class="text-xs text-destructive">{{ form.errors.value_label }}</p>
                        </div>
                    </div>

                    <!-- Suit/value on edit: read-only display (immutable to preserve deck identity). -->
                    <div v-else class="flex flex-wrap gap-3">
                        <div class="flex flex-col gap-1">
                            <span class="text-[10px] uppercase tracking-wider text-muted-foreground">Suit</span>
                            <Badge variant="outline" class="capitalize">{{ card!.suit }}</Badge>
                        </div>
                        <div class="flex flex-col gap-1">
                            <span class="text-[10px] uppercase tracking-wider text-muted-foreground">Value</span>
                            <Badge variant="outline" class="font-mono tabular-nums">{{ card!.value_label }}</Badge>
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <Label>Card Image</Label>
                        <div v-if="card?.image && !removeImage" class="flex items-center gap-3">
                            <img :src="`/storage/${card.image}`" :alt="card.name" class="size-24 rounded-md border object-cover" />
                            <Button type="button" variant="outline" size="sm" class="gap-1.5" @click="removeImage = true">
                                <Trash2 class="size-3.5" /> Remove image
                            </Button>
                        </div>
                        <div v-else-if="removeImage" class="flex items-center gap-3 text-xs text-muted-foreground">
                            <ImageOff class="size-4" /> Image will be removed on save.
                            <Button type="button" variant="ghost" size="sm" class="text-xs" @click="removeImage = false">Undo</Button>
                        </div>
                        <label
                            class="flex cursor-pointer items-center gap-2 rounded-md border border-dashed bg-muted/30 px-3 py-2 text-xs hover:bg-muted/50"
                        >
                            <Upload class="size-4 text-muted-foreground" />
                            <span class="flex-1 text-muted-foreground">{{
                                imageFile?.name ?? 'Upload a new image (jpeg, png, webp, heic; max 30MB)'
                            }}</span>
                            <input type="file" accept="image/*" class="hidden" @change="onImageChange" />
                        </label>
                        <p v-if="form.errors.image" class="text-xs text-destructive">{{ form.errors.image }}</p>
                    </div>
                </CardContent>
            </Card>

            <div class="grid gap-4 lg:grid-cols-2">
                <Card>
                    <CardContent class="space-y-4 p-4">
                        <div class="flex items-center gap-2">
                            <span class="inline-flex size-6 items-center justify-center rounded-full bg-primary/15 text-xs font-bold text-primary"
                                >A</span
                            >
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
                                        <Checkbox :checked="row.is_signature_action" @update:checked="toggleSignature(sideAActionRows, row.slug)" />
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
                            <span class="inline-flex size-6 items-center justify-center rounded-full bg-primary/15 text-xs font-bold text-primary"
                                >B</span
                            >
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
                                        <Checkbox :checked="row.is_signature_action" @update:checked="toggleSignature(sideBActionRows, row.slug)" />
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

            <div class="flex justify-end">
                <Button type="submit" :disabled="form.processing || capturing" class="gap-1.5">
                    <Save class="size-4" />
                    <span v-if="capturing">Capturing image…</span>
                    <span v-else>Save</span>
                </Button>
            </div>
        </form>

        <!-- Offscreen capture target. 420px ≈ the xl 3-col display cell, so
             the captured PNG renders 1:1 instead of being downscaled. -->
        <div ref="previewRef" aria-hidden="true" class="pointer-events-none fixed -left-[9999px] top-0 select-none">
            <BonanzaSplitCard
                :name="(form.name as string) || ''"
                :suit="previewSuit"
                :value-label="previewValueLabel"
                :image="null"
                :side-a="previewSideA"
                :side-b="previewSideB"
                :mirror="true"
                :hide-toggle="true"
            />
        </div>
    </div>
</template>
