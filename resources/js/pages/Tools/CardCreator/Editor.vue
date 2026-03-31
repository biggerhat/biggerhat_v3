<script setup lang="ts">
import CardRenderer from '@/components/CardCreator/CardRenderer.vue';
import FactionLogo from '@/components/FactionLogo.vue';
import PageBanner from '@/components/PageBanner.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, Download, Eye, ImagePlus, Loader2, Plus, Save, Trash2, X } from 'lucide-vue-next';
import { computed, nextTick, reactive, ref } from 'vue';

interface EnumOption {
    name: string;
    value: string;
}

interface TriggerData {
    name: string;
    suits: string | null;
    stone_cost: number;
    description: string | null;
    source_id: number | null;
}

interface ActionData {
    name: string;
    type: string;
    is_signature: boolean;
    stone_cost: number;
    range: number | null;
    range_type: string | null;
    stat: number | null;
    stat_suits: string | null;
    stat_modifier: string | null;
    resisted_by: string | null;
    target_number: number | null;
    target_suits: string | null;
    damage: string | null;
    description: string | null;
    source_id: number | null;
    triggers: TriggerData[];
}

interface AbilityData {
    name: string;
    suits: string | null;
    defensive_ability_type: string | null;
    costs_stone: boolean;
    description: string | null;
    source_id: number | null;
}

interface KeywordData {
    id: number | null;
    name: string;
}

const props = defineProps<{
    character: {
        id: number;
        name: string;
        title: string | null;
        slug: string;
        faction: string;
        second_faction: string | null;
        station: string;
        cost: number | null;
        health: number;
        size: number | null;
        base: string;
        defense: number;
        defense_suit: string | null;
        willpower: number;
        willpower_suit: string | null;
        speed: number;
        count: number;
        summon_target_number: number | null;
        generates_stone: boolean;
        is_unhirable: boolean;
        character_image: string | null;
        front_image: string | null;
        back_image: string | null;
        combo_image: string | null;
        actions: ActionData[] | null;
        abilities: AbilityData[] | null;
        keywords: KeywordData[] | null;
        characteristics: string[] | null;
        notes: string | null;
        share_code: string;
    } | null;
    enums: {
        factions: EnumOption[];
        stations: EnumOption[];
        bases: EnumOption[];
        suits: EnumOption[];
        action_types: EnumOption[];
        range_types: EnumOption[];
        defensive_ability_types: EnumOption[];
    };
}>();

const isEdit = computed(() => !!props.character);

const form = reactive({
    name: props.character?.name ?? '',
    title: props.character?.title ?? '',
    faction: props.character?.faction ?? '',
    second_faction: props.character?.second_faction ?? '',
    station: props.character?.station ?? 'none',
    cost: props.character?.cost ?? 0,
    health: props.character?.health ?? 6,
    size: props.character?.size ?? 2,
    base: props.character?.base ?? '30',
    defense: props.character?.defense ?? 5,
    defense_suit: props.character?.defense_suit ?? 'none',
    willpower: props.character?.willpower ?? 5,
    willpower_suit: props.character?.willpower_suit ?? 'none',
    speed: props.character?.speed ?? 5,
    count: props.character?.count ?? 1,
    summon_target_number: props.character?.summon_target_number ?? null as number | null,
    generates_stone: props.character?.generates_stone ?? false,
    is_unhirable: props.character?.is_unhirable ?? false,
    notes: props.character?.notes ?? '',
});

const actions = reactive<ActionData[]>(props.character?.actions ?? []);
const abilities = reactive<AbilityData[]>(props.character?.abilities ?? []);
const keywords = reactive<KeywordData[]>(props.character?.keywords ?? []);
const characteristics = reactive<string[]>(props.character?.characteristics ?? []);

// Character art image
const characterImageFile = ref<File | null>(null);
const characterImagePreview = ref<string | null>(
    props.character?.character_image ? '/storage/' + props.character.character_image : null,
);

const onImageSelected = (e: Event) => {
    const file = (e.target as HTMLInputElement).files?.[0];
    if (!file) return;
    characterImageFile.value = file;
    characterImagePreview.value = URL.createObjectURL(file);
};

const removeImage = () => {
    characterImageFile.value = null;
    characterImagePreview.value = null;
};

const saving = ref(false);
const imageDialogOpen = ref(false);
const storedImages = ref<{ front: string | null; back: string | null; combo: string | null }>({
    front: props.character?.front_image ? `/storage/${props.character.front_image}` : null,
    back: props.character?.back_image ? `/storage/${props.character.back_image}` : null,
    combo: props.character?.combo_image ? `/storage/${props.character.combo_image}` : null,
});
const hasImages = computed(() => !!storedImages.value.front);
const errors = ref<Record<string, string>>({});
const cardRendererRef = ref<InstanceType<typeof CardRenderer> | null>(null);

const csrfToken = () => document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';

const createComboImage = (frontDataUrl: string, backDataUrl: string): Promise<string> => {
    return new Promise((resolve) => {
        const frontImg = new Image();
        const backImg = new Image();
        let loaded = 0;
        const onLoad = () => {
            loaded++;
            if (loaded < 2) return;
            const gap = 20;
            const canvas = document.createElement('canvas');
            canvas.width = frontImg.width + backImg.width + gap;
            canvas.height = Math.max(frontImg.height, backImg.height);
            const ctx = canvas.getContext('2d')!;
            ctx.drawImage(frontImg, 0, 0);
            ctx.drawImage(backImg, frontImg.width + gap, 0);
            resolve(canvas.toDataURL('image/png'));
        };
        frontImg.onload = onLoad;
        backImg.onload = onLoad;
        frontImg.src = frontDataUrl;
        backImg.src = backDataUrl;
    });
};

const fetchFontEmbedCSS = async (): Promise<string> => {
    const res = await fetch('/font/M4E-Symbols.otf');
    const buf = await res.arrayBuffer();
    const base64 = btoa(String.fromCharCode(...new Uint8Array(buf)));
    return `@font-face { font-family: 'M4E-Symbols'; src: url(data:font/opentype;base64,${base64}) format('opentype'); }`;
};

const blobToDataURL = (blobUrl: string): Promise<string> => {
    return fetch(blobUrl)
        .then((res) => res.blob())
        .then(
            (blob) =>
                new Promise((resolve) => {
                    const reader = new FileReader();
                    reader.onloadend = () => resolve(reader.result as string);
                    reader.readAsDataURL(blob);
                }),
        );
};

const generateAndUploadImages = async (characterId: number) => {
    if (!cardRendererRef.value) return;
    const { toPng } = await import('html-to-image');
    const fontEmbedCSS = await fetchFontEmbedCSS();
    const opts = { pixelRatio: 2, skipFonts: true, fontEmbedCSS };
    const frontEl = cardRendererRef.value.frontRef;
    const backEl = cardRendererRef.value.backRef;
    if (!frontEl || !backEl) return;

    // Convert any blob: image sources to data URLs so html-to-image can embed them
    const blobImages = frontEl.querySelectorAll<HTMLImageElement>('img[src^="blob:"]');
    const origSrcs: { el: HTMLImageElement; src: string }[] = [];
    for (const img of blobImages) {
        origSrcs.push({ el: img, src: img.src });
        img.src = await blobToDataURL(img.src);
    }

    // Temporarily make front face visible for capture
    const origFrontBackface = frontEl.style.backfaceVisibility;
    frontEl.style.backfaceVisibility = 'visible';
    const frontData = await toPng(frontEl, opts);
    frontEl.style.backfaceVisibility = origFrontBackface;

    // Restore original blob sources
    for (const { el, src } of origSrcs) {
        el.src = src;
    }

    // Temporarily remove the flip transform and backface-visibility so the back is captured correctly
    const origTransform = backEl.style.transform;
    const origBackface = backEl.style.backfaceVisibility;
    backEl.style.transform = 'none';
    backEl.style.backfaceVisibility = 'visible';
    const backData = await toPng(backEl, opts);
    backEl.style.transform = origTransform;
    backEl.style.backfaceVisibility = origBackface;

    const comboData = await createComboImage(frontData, backData);

    const res = await fetch(route('tools.card_creator.export', characterId), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
        body: JSON.stringify({ front_image: frontData, back_image: backData, combo_image: comboData }),
    });

    const data = await res.json();
    if (data.success) {
        storedImages.value = {
            front: `/storage/${data.front_image}`,
            back: `/storage/${data.back_image}`,
            combo: `/storage/${data.combo_image}`,
        };
    }
};

const save = async () => {
    saving.value = true;
    errors.value = {};

    const noneToNull = (v: string | null | undefined) => (!v || v === 'none' ? null : v);

    const body: Record<string, any> = {
        ...form,
        station: noneToNull(form.station),
        base: String(form.base),
        defense_suit: noneToNull(form.defense_suit),
        willpower_suit: noneToNull(form.willpower_suit),
        second_faction: form.second_faction || null,
        title: form.title || null,
        actions: actions.map((a) => ({ ...a, range_type: noneToNull(a.range_type), triggers: a.triggers })),
        abilities: abilities.map((a) => ({ ...a, suits: noneToNull(a.suits), defensive_ability_type: noneToNull(a.defensive_ability_type) })),
        keywords,
        characteristics,
    };

    // If user selected a new image, convert to base64 data URL for JSON upload
    if (characterImageFile.value) {
        body.character_image = await blobToDataURL(URL.createObjectURL(characterImageFile.value));
    } else if (characterImagePreview.value === null && props.character?.character_image) {
        // User removed the image
        body.remove_character_image = true;
    }

    const url = isEdit.value
        ? route('tools.card_creator.update', props.character!.id)
        : route('tools.card_creator.store');

    const res = await fetch(url, {
        method: isEdit.value ? 'PUT' : 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken(), Accept: 'application/json' },
        body: JSON.stringify(body),
    });

    const data = await res.json().catch(() => ({}));

    if (!res.ok) {
        errors.value = data.errors ?? {};
        saving.value = false;
        return;
    }

    if (data.redirect) {
        // New character — redirect to edit page (images will generate on first edit save)
        router.visit(data.redirect);
        return;
    }

    // Update character image preview to the persisted path if returned
    if (data.character_image) {
        characterImagePreview.value = `/storage/${data.character_image}`;
        characterImageFile.value = null;
    }

    // Wait for DOM to update with new image paths before capturing
    await nextTick();

    // Existing character — generate card images after successful save
    try {
        await generateAndUploadImages(props.character!.id);
    } catch (e) {
        console.error('Image generation failed:', e);
    }
    saving.value = false;
};

// Action helpers
const addAction = () => {
    actions.push({
        name: '',
        type: 'attack',
        is_signature: false,
        stone_cost: 0,
        range: null,
        range_type: 'none',
        stat: null,
        stat_suits: null,
        stat_modifier: null,
        resisted_by: null,
        target_number: null,
        target_suits: null,
        damage: null,
        description: null,
        source_id: null,
        triggers: [],
    });
};

const removeAction = (index: number) => actions.splice(index, 1);

const addTrigger = (action: ActionData) => {
    action.triggers.push({ name: '', suits: null, stone_cost: 0, description: null, source_id: null });
};

const removeTrigger = (action: ActionData, index: number) => action.triggers.splice(index, 1);

// Ability helpers
const addAbility = () => {
    abilities.push({ name: '', suits: 'none', defensive_ability_type: 'none', costs_stone: false, description: null, source_id: null });
};

const removeAbility = (index: number) => abilities.splice(index, 1);

// Keyword helpers
const newKeyword = ref('');
const addKeyword = () => {
    const name = newKeyword.value.trim();
    if (!name || keywords.some((k) => k.name.toLowerCase() === name.toLowerCase())) return;
    keywords.push({ id: null, name });
    newKeyword.value = '';
};

const removeKeyword = (index: number) => keywords.splice(index, 1);

// Characteristic helpers
const newCharacteristic = ref('');
const addCharacteristic = () => {
    const name = newCharacteristic.value.trim();
    if (!name || characteristics.includes(name)) return;
    characteristics.push(name);
    newCharacteristic.value = '';
};

const removeCharacteristic = (index: number) => characteristics.splice(index, 1);

// Search existing actions/abilities from official DB
const searchResults = ref<any[]>([]);
const searchType = ref<'action' | 'ability' | 'trigger' | null>(null);
const searchQuery = ref('');
const searchTargetIndex = ref<number | null>(null);
let searchDebounce: ReturnType<typeof setTimeout>;

const searchOfficial = (type: 'action' | 'ability', q: string) => {
    searchQuery.value = q;
    searchType.value = type;
    clearTimeout(searchDebounce);
    if (q.length < 2) {
        searchResults.value = [];
        return;
    }
    searchDebounce = setTimeout(async () => {
        const endpoint = type === 'action' ? 'api.card-creator.actions' : 'api.card-creator.abilities';
        const res = await fetch(route(endpoint) + '?q=' + encodeURIComponent(q));
        searchResults.value = await res.json();
    }, 300);
};

const pickAction = (official: any) => {
    actions.push({ ...official, triggers: [] });
    searchResults.value = [];
    searchQuery.value = '';
    searchType.value = null;
};

const pickAbility = (official: any) => {
    abilities.push({ ...official });
    searchResults.value = [];
    searchQuery.value = '';
    searchType.value = null;
};

const searchTriggers = (q: string, actionIndex: number) => {
    searchQuery.value = q;
    searchType.value = 'trigger';
    searchTargetIndex.value = actionIndex;
    clearTimeout(searchDebounce);
    if (q.length < 2) {
        searchResults.value = [];
        return;
    }
    searchDebounce = setTimeout(async () => {
        const res = await fetch(route('api.card-creator.triggers') + '?q=' + encodeURIComponent(q));
        searchResults.value = await res.json();
    }, 300);
};

const pickTrigger = (official: any) => {
    if (searchTargetIndex.value !== null && actions[searchTargetIndex.value]) {
        actions[searchTargetIndex.value].triggers.push({ ...official });
    }
    searchResults.value = [];
    searchQuery.value = '';
    searchType.value = null;
    searchTargetIndex.value = null;
};

const searchKeywords = (q: string) => {
    clearTimeout(searchDebounce);
    if (q.length < 2) {
        searchResults.value = [];
        searchType.value = null;
        return;
    }
    searchType.value = 'keyword' as any;
    searchDebounce = setTimeout(async () => {
        const res = await fetch(route('api.card-creator.keywords') + '?q=' + encodeURIComponent(q));
        searchResults.value = await res.json();
    }, 300);
};

const pickKeyword = (official: { id: number; name: string }) => {
    if (!keywords.some((k) => k.name.toLowerCase() === official.name.toLowerCase())) {
        keywords.push({ id: official.id, name: official.name });
    }
    newKeyword.value = '';
    searchResults.value = [];
    searchType.value = null;
};
</script>

<template>
    <Head :title="isEdit ? `Edit — ${character!.name}` : 'New Custom Character'" />

    <div class="relative pb-12">
        <div
            class="pointer-events-none absolute inset-x-0 top-0 h-64 opacity-[0.07] dark:opacity-[0.12]"
            :style="{ background: 'radial-gradient(ellipse at top, hsl(var(--primary)) 0%, transparent 70%)' }"
        />

        <PageBanner :title="isEdit ? 'Edit Character' : 'New Custom Character'">
            <template #subtitle>
                <div class="px-2 text-sm text-muted-foreground">
                    {{ isEdit ? `Editing ${character!.display_name}` : 'Build a custom character card with stats, actions, and abilities.' }}
                </div>
            </template>
        </PageBanner>

        <div class="container mx-auto mt-6 px-4 lg:px-6">
            <div class="mb-4">
                <Link :href="route('tools.card_creator.index')">
                    <Button variant="ghost" size="sm"><ArrowLeft class="mr-1 size-4" /> Back to List</Button>
                </Link>
            </div>

            <div class="grid gap-6 lg:grid-cols-3">
                <!-- Form (2 cols) -->
                <div class="space-y-6 lg:col-span-2">
                    <!-- Identity -->
                    <Card>
                        <CardContent class="space-y-4 p-4">
                            <h3 class="text-sm font-semibold">Identity</h3>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <div>
                                    <label class="mb-1 block text-xs text-muted-foreground">Name *</label>
                                    <Input v-model="form.name" placeholder="Character name" />
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs text-muted-foreground">Title</label>
                                    <Input v-model="form.title" placeholder="Optional title" />
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs text-muted-foreground">Faction *</label>
                                    <Select v-model="form.faction">
                                        <SelectTrigger><SelectValue placeholder="Select faction" /></SelectTrigger>
                                        <SelectContent>
                                            <SelectItem v-for="f in enums.factions" :key="f.value" :value="f.value">
                                                <div class="flex items-center gap-2">
                                                    <FactionLogo :faction="f.value" class-name="size-4" />
                                                    {{ f.name }}
                                                </div>
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs text-muted-foreground">Station</label>
                                    <Select v-model="form.station">
                                        <SelectTrigger><SelectValue placeholder="Select station" /></SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="none">None</SelectItem>
                                            <SelectItem v-for="s in enums.stations" :key="s.value" :value="s.value">{{ s.name }}</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Stats -->
                    <Card>
                        <CardContent class="space-y-4 p-4">
                            <h3 class="text-sm font-semibold">Stats</h3>
                            <div class="grid grid-cols-3 gap-3 sm:grid-cols-4 lg:grid-cols-6">
                                <div>
                                    <label class="mb-1 block text-xs text-muted-foreground">Cost</label>
                                    <Input v-model.number="form.cost" type="number" min="0" max="99" />
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs text-muted-foreground">Health *</label>
                                    <Input v-model.number="form.health" type="number" min="1" max="99" />
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs text-muted-foreground">Speed</label>
                                    <Input v-model.number="form.speed" type="number" min="0" max="20" />
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs text-muted-foreground">Defense</label>
                                    <Input v-model.number="form.defense" type="number" min="0" max="20" />
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs text-muted-foreground">Willpower</label>
                                    <Input v-model.number="form.willpower" type="number" min="0" max="20" />
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs text-muted-foreground">Size</label>
                                    <Input v-model.number="form.size" type="number" min="0" max="10" />
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs text-muted-foreground">Base</label>
                                    <Select v-model="form.base">
                                        <SelectTrigger><SelectValue /></SelectTrigger>
                                        <SelectContent>
                                            <SelectItem v-for="b in enums.bases" :key="b.value" :value="b.value">{{ b.name }}</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs text-muted-foreground">Count</label>
                                    <Input v-model.number="form.count" type="number" min="1" max="10" />
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Character Art -->
                    <Card>
                        <CardContent class="space-y-3 p-4">
                            <h3 class="text-sm font-semibold">Character Art</h3>
                            <div v-if="characterImagePreview" class="relative">
                                <img :src="characterImagePreview" alt="Character art preview" class="h-40 w-full rounded-md border object-cover" />
                                <button
                                    class="absolute right-2 top-2 rounded-full bg-black/60 p-1 text-white hover:bg-black/80"
                                    @click="removeImage"
                                >
                                    <X class="size-4" />
                                </button>
                            </div>
                            <label
                                v-else
                                class="flex cursor-pointer flex-col items-center gap-2 rounded-lg border-2 border-dashed border-muted-foreground/25 p-6 transition-colors hover:border-muted-foreground/50 hover:bg-muted/50"
                            >
                                <ImagePlus class="size-8 text-muted-foreground/50" />
                                <span class="text-xs text-muted-foreground">Click to upload (max 2MB, jpg/png/webp)</span>
                                <input type="file" accept="image/jpeg,image/png,image/webp" class="hidden" @change="onImageSelected" />
                            </label>
                        </CardContent>
                    </Card>

                    <!-- Keywords & Characteristics -->
                    <Card>
                        <CardContent class="space-y-4 p-4">
                            <h3 class="text-sm font-semibold">Keywords</h3>
                            <div class="flex flex-wrap gap-1.5">
                                <Badge v-for="(kw, i) in keywords" :key="'kw-' + i" variant="secondary" class="gap-1">
                                    {{ kw.name }}
                                    <Badge v-if="kw.id" variant="outline" class="ml-0.5 px-1 py-0 text-[8px]">Official</Badge>
                                    <button class="ml-0.5 hover:text-destructive" @click="removeKeyword(i)"><X class="size-3" /></button>
                                </Badge>
                            </div>
                            <div class="relative">
                                <Input
                                    v-model="newKeyword"
                                    placeholder="Search or type a keyword..."
                                    @input="searchKeywords(newKeyword)"
                                    @keydown.enter.prevent="addKeyword()"
                                />
                                <div v-if="searchType === 'keyword' && searchResults.length" class="absolute z-10 mt-1 w-full rounded-md border bg-popover p-1 shadow-md">
                                    <button
                                        v-for="r in searchResults"
                                        :key="r.id"
                                        class="flex w-full items-center gap-2 rounded px-2 py-1.5 text-left text-sm hover:bg-accent"
                                        @click="pickKeyword(r)"
                                    >
                                        {{ r.name }}
                                        <Badge variant="outline" class="px-1 py-0 text-[8px]">Official</Badge>
                                    </button>
                                </div>
                            </div>

                            <h3 class="text-sm font-semibold">Characteristics</h3>
                            <div class="flex flex-wrap gap-1.5">
                                <Badge v-for="(c, i) in characteristics" :key="'char-' + i" variant="outline" class="gap-1">
                                    {{ c }}
                                    <button class="ml-0.5 hover:text-destructive" @click="removeCharacteristic(i)"><X class="size-3" /></button>
                                </Badge>
                            </div>
                            <Input
                                v-model="newCharacteristic"
                                placeholder="Type a characteristic and press Enter"
                                @keydown.enter.prevent="addCharacteristic()"
                            />
                        </CardContent>
                    </Card>

                    <!-- Abilities -->
                    <Card>
                        <CardContent class="space-y-4 p-4">
                            <div class="flex items-center justify-between">
                                <h3 class="text-sm font-semibold">Abilities</h3>
                                <div class="flex gap-1">
                                    <Button variant="outline" size="sm" class="text-xs" @click="addAbility"><Plus class="mr-1 size-3" /> Custom</Button>
                                </div>
                            </div>

                            <!-- Search existing -->
                            <div class="relative">
                                <Input placeholder="Search official abilities..." @input="(e: Event) => searchOfficial('ability', (e.target as HTMLInputElement).value)" />
                                <div v-if="searchType === 'ability' && searchResults.length" class="absolute z-10 mt-1 w-full rounded-md border bg-popover p-1 shadow-md">
                                    <button
                                        v-for="r in searchResults"
                                        :key="r.id"
                                        class="w-full rounded px-2 py-1.5 text-left text-sm hover:bg-accent"
                                        @click="pickAbility(r)"
                                    >
                                        <span class="font-medium">{{ r.name }}</span>
                                        <span class="ml-2 text-xs text-muted-foreground">{{ r.description?.slice(0, 60) }}{{ r.description?.length > 60 ? '...' : '' }}</span>
                                    </button>
                                </div>
                            </div>

                            <div v-for="(ability, aIdx) in abilities" :key="'ability-' + aIdx" class="rounded-lg border p-3 space-y-2">
                                <!-- Official ability (read-only) -->
                                <template v-if="ability.source_id">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-2">
                                            <span class="text-sm font-medium">{{ ability.name }}</span>
                                            <Badge variant="outline" class="shrink-0 px-1 py-0 text-[8px]">Official</Badge>
                                        </div>
                                        <button class="text-muted-foreground hover:text-destructive" @click="removeAbility(aIdx)"><Trash2 class="size-3.5" /></button>
                                    </div>
                                    <div v-if="ability.description" class="text-xs text-muted-foreground">{{ ability.description }}</div>
                                </template>

                                <!-- Custom ability (editable) -->
                                <template v-else>
                                    <div class="flex items-center justify-between">
                                        <Input v-model="ability.name" placeholder="Ability name" class="h-7 text-sm font-medium" />
                                        <button class="ml-2 text-muted-foreground hover:text-destructive" @click="removeAbility(aIdx)"><Trash2 class="size-3.5" /></button>
                                    </div>
                                    <div class="grid gap-2 sm:grid-cols-3">
                                        <Select v-model="ability.suits">
                                            <SelectTrigger class="h-7 text-xs"><SelectValue placeholder="Suit" /></SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="none">None</SelectItem>
                                                <SelectItem v-for="s in enums.suits" :key="s.value" :value="s.value">{{ s.name }}</SelectItem>
                                            </SelectContent>
                                        </Select>
                                        <Select v-model="ability.defensive_ability_type">
                                            <SelectTrigger class="h-7 text-xs"><SelectValue placeholder="Defense type" /></SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="none">None</SelectItem>
                                                <SelectItem v-for="d in enums.defensive_ability_types" :key="d.value" :value="d.value">{{ d.name }}</SelectItem>
                                            </SelectContent>
                                        </Select>
                                        <label class="flex items-center gap-1.5 text-xs">
                                            <input type="checkbox" v-model="ability.costs_stone" class="rounded" />
                                            Costs Soulstone
                                        </label>
                                    </div>
                                    <Textarea v-model="ability.description" placeholder="Ability description..." rows="2" class="text-xs" />
                                </template>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Actions -->
                    <Card>
                        <CardContent class="space-y-4 p-4">
                            <div class="flex items-center justify-between">
                                <h3 class="text-sm font-semibold">Actions</h3>
                                <Button variant="outline" size="sm" class="text-xs" @click="addAction"><Plus class="mr-1 size-3" /> Custom</Button>
                            </div>

                            <!-- Search existing -->
                            <div class="relative">
                                <Input placeholder="Search official actions..." @input="(e: Event) => searchOfficial('action', (e.target as HTMLInputElement).value)" />
                                <div v-if="searchType === 'action' && searchResults.length" class="absolute z-10 mt-1 w-full rounded-md border bg-popover p-1 shadow-md">
                                    <button
                                        v-for="r in searchResults"
                                        :key="r.id"
                                        class="w-full rounded px-2 py-1.5 text-left text-sm hover:bg-accent"
                                        @click="pickAction(r)"
                                    >
                                        <span class="font-medium">{{ r.name }}</span>
                                        <Badge class="ml-2 text-[9px]">{{ r.type }}</Badge>
                                    </button>
                                </div>
                            </div>

                            <div v-for="(action, idx) in actions" :key="'action-' + idx" class="rounded-lg border p-3 space-y-3">
                                <!-- Official action (read-only) -->
                                <template v-if="action.source_id">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-2">
                                            <span class="text-sm font-medium">{{ action.name }}</span>
                                            <Badge class="text-[9px]">{{ action.type }}</Badge>
                                            <Badge variant="outline" class="shrink-0 px-1 py-0 text-[8px]">Official</Badge>
                                        </div>
                                        <button class="text-muted-foreground hover:text-destructive" @click="removeAction(idx)"><Trash2 class="size-3.5" /></button>
                                    </div>
                                    <div class="flex flex-wrap gap-x-3 gap-y-0.5 text-[11px] text-muted-foreground">
                                        <span v-if="action.range != null">Rg {{ action.range }}"</span>
                                        <span v-if="action.stat != null">Stat {{ action.stat }}{{ action.stat_suits ? ' ' + action.stat_suits : '' }}</span>
                                        <span v-if="action.resisted_by">vs {{ action.resisted_by }}</span>
                                        <span v-if="action.damage">Dmg {{ action.damage }}</span>
                                    </div>
                                    <div v-if="action.description" class="text-xs text-muted-foreground">{{ action.description }}</div>

                                    <!-- Triggers (editable even on official actions) -->
                                    <div class="space-y-2 border-t pt-2">
                                        <div class="flex items-center justify-between">
                                            <span class="text-[11px] font-medium text-muted-foreground">Triggers</span>
                                            <Button variant="ghost" size="sm" class="h-5 px-1.5 text-[10px]" @click="addTrigger(action)"><Plus class="mr-0.5 size-2.5" /> Add</Button>
                                        </div>

                                        <div class="relative">
                                            <Input placeholder="Search official triggers..." class="h-7 text-xs" @input="(e: Event) => searchTriggers((e.target as HTMLInputElement).value, idx)" />
                                            <div v-if="searchType === 'trigger' && searchTargetIndex === idx && searchResults.length" class="absolute z-10 mt-1 w-full rounded-md border bg-popover p-1 shadow-md">
                                                <button
                                                    v-for="r in searchResults"
                                                    :key="r.id"
                                                    class="w-full rounded px-2 py-1.5 text-left text-sm hover:bg-accent"
                                                    @click="pickTrigger(r)"
                                                >
                                                    <span class="font-medium">{{ r.name }}</span>
                                                    <span v-if="r.suits" class="ml-1 text-xs text-muted-foreground">({{ r.suits }})</span>
                                                </button>
                                            </div>
                                        </div>

                                        <div v-for="(trigger, tIdx) in action.triggers" :key="'trigger-' + tIdx" class="flex items-start gap-2 rounded border bg-muted/30 p-2">
                                            <template v-if="trigger.source_id">
                                                <div class="flex-1 text-xs">
                                                    <span class="font-medium">{{ trigger.suits }} {{ trigger.name }}:</span>
                                                    <span class="text-muted-foreground"> {{ trigger.description }}</span>
                                                    <Badge variant="outline" class="ml-1 px-1 py-0 text-[8px]">Official</Badge>
                                                </div>
                                            </template>
                                            <template v-else>
                                                <div class="flex-1 space-y-1">
                                                    <div class="flex items-center gap-2">
                                                        <Input v-model="trigger.name" placeholder="Trigger name" class="h-6 text-xs font-medium" />
                                                        <Input v-model="trigger.suits" placeholder="Suits" class="h-6 w-24 text-xs" />
                                                    </div>
                                                    <Input v-model="trigger.description" placeholder="Trigger description" class="h-6 text-xs" />
                                                </div>
                                            </template>
                                            <button class="mt-1 text-muted-foreground hover:text-destructive" @click="removeTrigger(action, tIdx)"><X class="size-3" /></button>
                                        </div>
                                    </div>
                                </template>

                                <!-- Custom action (editable) -->
                                <template v-else>
                                    <div class="flex items-center justify-between">
                                        <Input v-model="action.name" placeholder="Action name" class="h-7 text-sm font-medium" />
                                        <button class="ml-2 text-muted-foreground hover:text-destructive" @click="removeAction(idx)"><Trash2 class="size-3.5" /></button>
                                    </div>

                                    <div class="grid grid-cols-3 gap-2 sm:grid-cols-4 lg:grid-cols-6">
                                        <div>
                                            <label class="text-[10px] text-muted-foreground">Type</label>
                                            <Select v-model="action.type">
                                                <SelectTrigger class="h-7 text-xs"><SelectValue /></SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem v-for="t in enums.action_types" :key="t.value" :value="t.value">{{ t.name }}</SelectItem>
                                                </SelectContent>
                                            </Select>
                                        </div>
                                        <div>
                                            <label class="text-[10px] text-muted-foreground">Range</label>
                                            <Input v-model.number="action.range" type="number" min="0" class="h-7 text-xs" />
                                        </div>
                                        <div>
                                            <label class="text-[10px] text-muted-foreground">Range Type</label>
                                            <Select v-model="action.range_type">
                                                <SelectTrigger class="h-7 text-xs"><SelectValue placeholder="—" /></SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="none">None</SelectItem>
                                                    <SelectItem v-for="r in enums.range_types" :key="r.value" :value="r.value">{{ r.name }}</SelectItem>
                                                </SelectContent>
                                            </Select>
                                        </div>
                                        <div>
                                            <label class="text-[10px] text-muted-foreground">Stat</label>
                                            <Input v-model.number="action.stat" type="number" min="0" class="h-7 text-xs" />
                                        </div>
                                        <div>
                                            <label class="text-[10px] text-muted-foreground">Resisted By</label>
                                            <Input v-model="action.resisted_by" placeholder="Df" class="h-7 text-xs" />
                                        </div>
                                        <div>
                                            <label class="text-[10px] text-muted-foreground">Damage</label>
                                            <Input v-model="action.damage" placeholder="2/3/5" class="h-7 text-xs" />
                                        </div>
                                    </div>

                                    <Textarea v-model="action.description" placeholder="Action description..." rows="2" class="text-xs" />

                                    <!-- Triggers -->
                                    <div class="space-y-2 border-t pt-2">
                                        <div class="flex items-center justify-between">
                                            <span class="text-[11px] font-medium text-muted-foreground">Triggers</span>
                                            <Button variant="ghost" size="sm" class="h-5 px-1.5 text-[10px]" @click="addTrigger(action)"><Plus class="mr-0.5 size-2.5" /> Add</Button>
                                        </div>

                                        <!-- Search triggers -->
                                        <div class="relative">
                                            <Input placeholder="Search official triggers..." class="h-7 text-xs" @input="(e: Event) => searchTriggers((e.target as HTMLInputElement).value, idx)" />
                                            <div v-if="searchType === 'trigger' && searchTargetIndex === idx && searchResults.length" class="absolute z-10 mt-1 w-full rounded-md border bg-popover p-1 shadow-md">
                                                <button
                                                    v-for="r in searchResults"
                                                    :key="r.id"
                                                    class="w-full rounded px-2 py-1.5 text-left text-sm hover:bg-accent"
                                                    @click="pickTrigger(r)"
                                                >
                                                    <span class="font-medium">{{ r.name }}</span>
                                                    <span v-if="r.suits" class="ml-1 text-xs text-muted-foreground">({{ r.suits }})</span>
                                                </button>
                                            </div>
                                        </div>

                                        <div v-for="(trigger, tIdx) in action.triggers" :key="'trigger-' + tIdx" class="flex items-start gap-2 rounded border bg-muted/30 p-2">
                                            <!-- Official trigger (read-only) -->
                                            <template v-if="trigger.source_id">
                                                <div class="flex-1 text-xs">
                                                    <span class="font-medium">{{ trigger.suits }} {{ trigger.name }}:</span>
                                                    <span class="text-muted-foreground"> {{ trigger.description }}</span>
                                                    <Badge variant="outline" class="ml-1 px-1 py-0 text-[8px]">Official</Badge>
                                                </div>
                                            </template>
                                            <!-- Custom trigger (editable) -->
                                            <template v-else>
                                                <div class="flex-1 space-y-1">
                                                    <div class="flex items-center gap-2">
                                                        <Input v-model="trigger.name" placeholder="Trigger name" class="h-6 text-xs font-medium" />
                                                        <Input v-model="trigger.suits" placeholder="Suits" class="h-6 w-24 text-xs" />
                                                    </div>
                                                    <Input v-model="trigger.description" placeholder="Trigger description" class="h-6 text-xs" />
                                                </div>
                                            </template>
                                            <button class="mt-1 text-muted-foreground hover:text-destructive" @click="removeTrigger(action, tIdx)"><X class="size-3" /></button>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Notes -->
                    <Card>
                        <CardContent class="space-y-2 p-4">
                            <h3 class="text-sm font-semibold">Notes</h3>
                            <Textarea v-model="form.notes" placeholder="Personal notes..." rows="3" />
                        </CardContent>
                    </Card>

                    <!-- Save -->
                    <div class="flex items-center gap-3">
                        <Button :disabled="saving || !form.name || !form.faction" @click="save">
                            <Loader2 v-if="saving" class="mr-2 size-4 animate-spin" />
                            <Save v-else class="mr-2 size-4" />
                            {{ isEdit ? 'Save Changes' : 'Create Character' }}
                        </Button>
                        <div v-if="Object.keys(errors).length" class="text-sm text-destructive">
                            Please fix validation errors above.
                        </div>
                    </div>
                </div>

                <!-- Live card preview -->
                <div class="lg:col-span-1">
                    <div class="sticky top-4 space-y-3">
                        <div class="flex items-center justify-between">
                            <h3 class="text-sm font-semibold">Preview</h3>
                            <div class="flex items-center gap-1">
                                <span v-if="!hasImages && isEdit" class="text-[10px] text-muted-foreground">Save to generate images</span>
                                <Button variant="outline" size="sm" class="text-xs" :disabled="!hasImages" @click="imageDialogOpen = true">
                                    <Eye class="mr-1 size-3" />
                                    View Images
                                </Button>
                            </div>
                        </div>
                        <CardRenderer
                            ref="cardRendererRef"
                            :name="form.name || 'Character Name'"
                            :title="form.title || null"
                            :faction="form.faction || 'guild'"
                            :station="form.station || 'minion'"
                            :cost="form.cost"
                            :health="form.health"
                            :defense="form.defense"
                            :defense-suit="form.defense_suit || null"
                            :willpower="form.willpower"
                            :willpower-suit="form.willpower_suit || null"
                            :speed="form.speed"
                            :size="form.size"
                            :base="String(form.base)"
                            :keywords="keywords"
                            :characteristics="characteristics"
                            :character-image="characterImagePreview"
                            :actions="actions"
                            :abilities="abilities"
                        />
                    </div>
                </div>
            </div>
        </div>
    </div>

    <Dialog v-model:open="imageDialogOpen">
        <DialogContent class="max-w-2xl">
            <DialogHeader>
                <DialogTitle>Card Images</DialogTitle>
                <DialogDescription>Right-click to save, or use the download buttons below.</DialogDescription>
            </DialogHeader>
            <div v-if="storedImages.combo" class="space-y-4">
                <!-- Combined image -->
                <div class="space-y-2 text-center">
                    <img :src="storedImages.combo" alt="Card front and back" class="w-full rounded border" />
                    <a :href="storedImages.combo" :download="`${form.name || 'card'}.png`">
                        <Button variant="default" size="sm" class="w-full text-xs">
                            <Download class="mr-1 size-3" />
                            Download Combined
                        </Button>
                    </a>
                </div>

                <!-- Individual images -->
                <div class="grid grid-cols-2 gap-4">
                    <a :href="storedImages.front!" :download="`${form.name || 'card'}-front.png`">
                        <Button variant="outline" size="sm" class="w-full text-xs">
                            <Download class="mr-1 size-3" />
                            Front Only
                        </Button>
                    </a>
                    <a :href="storedImages.back!" :download="`${form.name || 'card'}-back.png`">
                        <Button variant="outline" size="sm" class="w-full text-xs">
                            <Download class="mr-1 size-3" />
                            Back Only
                        </Button>
                    </a>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>
