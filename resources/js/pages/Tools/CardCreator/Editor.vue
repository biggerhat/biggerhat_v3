<script setup lang="ts">
import CardRenderer from '@/components/CardCreator/CardRenderer.vue';
import { blobToDataURL, createComboImage, fetchFontEmbedCSS, formatRange, triggerDownload } from '@/components/CardCreator/utils';
import FactionLogo from '@/components/FactionLogo.vue';
import PageBanner from '@/components/PageBanner.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, ChevronDown, Copy, Download, ImagePlus, Loader2, Plus, Save, Trash2, X } from 'lucide-vue-next';
import { computed, reactive, ref, watch } from 'vue';

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
    stone_cost: number | string;
    range: number | string | null;
    range_type: string | null;
    stat: number | string | null;
    stat_suits: string | null;
    stat_modifier: string | null;
    resisted_by: string | null;
    target_number: number | string | null;
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

interface LinkedItem {
    source_type: 'official' | 'custom';
    id: number;
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
        actions: ActionData[] | null;
        abilities: AbilityData[] | null;
        keywords: KeywordData[] | null;
        characteristics: string[] | null;
        linked_crew_upgrades: LinkedItem[] | null;
        linked_totems: LinkedItem[] | null;
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
const displayNameLength = computed(() => {
    const name = form.name || '';
    const title = form.title || '';
    return title ? name.length + 2 + title.length : name.length; // +2 for ", "
});
const NAME_LIMIT = 32;

const form = reactive({
    name: props.character?.name ?? '',
    title: props.character?.title ?? '',
    faction: props.character?.faction ?? 'none',
    second_faction: props.character?.second_faction ?? 'none',
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

watch(
    () => form.faction,
    (newFaction) => {
        if (form.second_faction === newFaction) {
            form.second_faction = 'none';
        }
    },
);

const actions = reactive<ActionData[]>(props.character?.actions ?? []);
const abilities = reactive<AbilityData[]>(props.character?.abilities ?? []);
const keywords = reactive<KeywordData[]>(props.character?.keywords ?? []);
const characteristics = reactive<string[]>(props.character?.characteristics ?? []);
const linkedCrewUpgrades = reactive<LinkedItem[]>(props.character?.linked_crew_upgrades ?? []);
const linkedTotems = reactive<LinkedItem[]>(props.character?.linked_totems ?? []);

// Clone from official character
const cloneSearchQuery = ref('');
const cloneSearchResults = ref<{ id: number; name: string }[]>([]);
const cloneLoading = ref(false);
let cloneDebounce: ReturnType<typeof setTimeout>;

const searchCloneCharacter = (q: string) => {
    cloneSearchQuery.value = q;
    clearTimeout(cloneDebounce);
    if (q.length < 2) {
        cloneSearchResults.value = [];
        return;
    }
    cloneDebounce = setTimeout(async () => {
        const res = await fetch(route('api.card-creator.characters') + '?q=' + encodeURIComponent(q));
        const data = await res.json();
        cloneSearchResults.value = data.filter((r: any) => r.source_type === 'official');
    }, 300);
};

const cloneFromCharacter = async (charId: number) => {
    cloneLoading.value = true;
    cloneSearchResults.value = [];
    cloneSearchQuery.value = '';
    try {
        const res = await fetch(route('api.card-creator.character-detail', charId));
        const data = await res.json();

        form.name = data.name ?? '';
        form.title = data.title ?? '';
        form.faction = data.faction ?? 'none';
        form.second_faction = data.second_faction ?? 'none';
        form.station = data.station ?? 'none';
        form.cost = data.cost ?? 0;
        form.health = data.health ?? 6;
        form.defense = data.defense ?? 5;
        form.defense_suit = data.defense_suit ?? 'none';
        form.willpower = data.willpower ?? 5;
        form.willpower_suit = data.willpower_suit ?? 'none';
        form.speed = data.speed ?? 5;
        form.size = data.size ?? 2;
        form.base = String(data.base ?? '30');
        form.count = data.count ?? 1;
        form.summon_target_number = data.summon_target_number ?? null;
        form.generates_stone = data.generates_stone ?? false;
        form.is_unhirable = data.is_unhirable ?? false;

        actions.splice(0, actions.length, ...(data.actions ?? []));
        abilities.splice(0, abilities.length, ...(data.abilities ?? []));
        keywords.splice(0, keywords.length, ...(data.keywords ?? []));
        characteristics.splice(0, characteristics.length, ...(data.characteristics ?? []));
        linkedCrewUpgrades.splice(0, linkedCrewUpgrades.length, ...(data.linked_crew_upgrades ?? []));
        linkedTotems.splice(0, linkedTotems.length, ...(data.linked_totems ?? []));
    } catch (e) {
        console.error('Failed to clone character:', e);
    } finally {
        cloneLoading.value = false;
    }
};

// Character art image (blob-only, not persisted to server)
const characterImageFile = ref<File | null>(null);
const characterImagePreview = ref<string | null>(null);

const onImageSelected = (e: Event) => {
    const file = (e.target as HTMLInputElement).files?.[0];
    if (!file) return;
    if (characterImagePreview.value) URL.revokeObjectURL(characterImagePreview.value);
    characterImageFile.value = file;
    characterImagePreview.value = URL.createObjectURL(file);
};

const removeImage = () => {
    if (characterImagePreview.value) URL.revokeObjectURL(characterImagePreview.value);
    characterImageFile.value = null;
    characterImagePreview.value = null;
};

const saving = ref(false);
const errors = ref<Record<string, string>>({});
const cardRendererRef = ref<InstanceType<typeof CardRenderer> | null>(null);
const exporting = ref(false);

const csrfToken = () => document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';


const exportImages = async () => {
    if (!cardRendererRef.value) return;
    exporting.value = true;
    try {
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
        const baseName = form.name || 'card';

        triggerDownload(comboData, `${baseName}.png`);
    } catch (e) {
        console.error('Image export failed:', e);
    } finally {
        exporting.value = false;
    }
};

const save = async () => {
    saving.value = true;
    errors.value = {};

    const noneToNull = (v: string | null | undefined) => (!v || v === 'none' ? null : v);

    const body: Record<string, any> = {
        ...form,
        faction: noneToNull(form.faction),
        station: noneToNull(form.station),
        base: String(form.base),
        defense_suit: noneToNull(form.defense_suit),
        willpower_suit: noneToNull(form.willpower_suit),
        second_faction: noneToNull(form.second_faction),
        title: form.title || null,
        actions: actions.map((a) => ({
            ...a,
            range_type: noneToNull(a.range_type),
            range: a.range === '' ? null : a.range,
            stat: a.stat === '' ? null : a.stat,
            target_number: a.target_number === '' ? null : a.target_number,
            stone_cost: a.stone_cost === '' || a.stone_cost == null ? 0 : a.stone_cost,
            triggers: (a.triggers ?? []).map((t) => ({ ...t, stone_cost: t.stone_cost === '' || t.stone_cost == null ? 0 : t.stone_cost })),
        })),
        abilities: abilities.map((a) => ({ ...a, suits: noneToNull(a.suits), defensive_ability_type: noneToNull(a.defensive_ability_type) })),
        keywords,
        characteristics,
        linked_crew_upgrades: linkedCrewUpgrades,
        linked_totems: linkedTotems,
    };

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
        router.visit(data.redirect);
        return;
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

// Crew upgrade link helpers
const upgradeSearchResults = ref<LinkedItem[]>([]);
const upgradeSearchQuery = ref('');
let upgradeSearchDebounce: ReturnType<typeof setTimeout>;

const searchCrewUpgrades = (q: string) => {
    upgradeSearchQuery.value = q;
    clearTimeout(upgradeSearchDebounce);
    if (q.length < 2) {
        upgradeSearchResults.value = [];
        return;
    }
    upgradeSearchDebounce = setTimeout(async () => {
        const res = await fetch(route('api.card-creator.crew-upgrades') + '?q=' + encodeURIComponent(q));
        upgradeSearchResults.value = await res.json();
    }, 300);
};

const pickCrewUpgrade = (item: LinkedItem) => {
    if (!linkedCrewUpgrades.some((u) => u.source_type === item.source_type && u.id === item.id)) {
        linkedCrewUpgrades.push({ ...item });
    }
    upgradeSearchQuery.value = '';
    upgradeSearchResults.value = [];
};

const removeCrewUpgrade = (index: number) => linkedCrewUpgrades.splice(index, 1);

// Totem link helpers
const totemSearchResults = ref<LinkedItem[]>([]);
const totemSearchQuery = ref('');
let totemSearchDebounce: ReturnType<typeof setTimeout>;

const searchTotems = (q: string) => {
    totemSearchQuery.value = q;
    clearTimeout(totemSearchDebounce);
    if (q.length < 2) {
        totemSearchResults.value = [];
        return;
    }
    totemSearchDebounce = setTimeout(async () => {
        const res = await fetch(route('api.card-creator.characters') + '?q=' + encodeURIComponent(q));
        totemSearchResults.value = await res.json();
    }, 300);
};

const pickTotem = (item: LinkedItem) => {
    if (!linkedTotems.some((t) => t.source_type === item.source_type && t.id === item.id)) {
        linkedTotems.push({ ...item });
    }
    totemSearchQuery.value = '';
    totemSearchResults.value = [];
};

const removeTotem = (index: number) => linkedTotems.splice(index, 1);
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
                <!-- Form column -->
                <div class="space-y-4 lg:col-span-2">

                    <!-- ═══ CLONE FROM OFFICIAL ═══ -->
                    <div v-if="!isEdit" class="relative rounded-lg border border-dashed border-muted-foreground/30 bg-muted/30 p-3">
                        <div class="mb-1.5 flex items-center gap-1.5 text-xs font-medium text-muted-foreground">
                            <Copy class="size-3.5" />
                            Clone from an existing character
                        </div>
                        <div class="relative">
                            <Input
                                v-model="cloneSearchQuery"
                                :placeholder="cloneLoading ? 'Loading character data...' : 'Search for a character to clone...'"
                                :disabled="cloneLoading"
                                class="h-8 text-xs"
                                @input="searchCloneCharacter(cloneSearchQuery)"
                            />
                            <div v-if="cloneSearchResults.length" class="absolute z-10 mt-1 max-h-48 w-full overflow-auto rounded-md border bg-popover p-1 shadow-md">
                                <button
                                    v-for="r in cloneSearchResults"
                                    :key="r.id"
                                    class="w-full rounded px-2 py-1.5 text-left text-sm hover:bg-accent"
                                    @click="cloneFromCharacter(r.id)"
                                >
                                    {{ r.name }}
                                </button>
                            </div>
                        </div>
                        <p class="mt-1 text-[10px] text-muted-foreground/60">Populates all fields from an official character. You can edit everything after cloning.</p>
                    </div>

                    <!-- ═══ IDENTITY + ART ═══ -->
                    <Card>
                        <CardContent class="space-y-4 p-4">
                            <h3 class="text-sm font-semibold">Identity</h3>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <div>
                                    <label class="mb-1 block text-xs text-muted-foreground">Name *</label>
                                    <Input v-model="form.name" placeholder="Character name" maxlength="30" />
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs text-muted-foreground">Title</label>
                                    <Input v-model="form.title" placeholder="Optional title (e.g. The Returned)" maxlength="30" />
                                </div>
                            </div>
                            <div class="flex items-center gap-2 text-[11px]" :class="displayNameLength > NAME_LIMIT ? 'text-destructive' : 'text-muted-foreground'">
                                <span>{{ displayNameLength }}/{{ NAME_LIMIT }} characters</span>
                                <span v-if="displayNameLength > NAME_LIMIT">— name may not fit on card</span>
                            </div>
                            <div class="grid gap-3 sm:grid-cols-3">
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
                                    <label class="mb-1 block text-xs text-muted-foreground">Second Faction</label>
                                    <Select v-model="form.second_faction">
                                        <SelectTrigger><SelectValue placeholder="None" /></SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="none">None</SelectItem>
                                            <SelectItem v-for="f in enums.factions.filter((f) => f.value !== form.faction)" :key="'2f-' + f.value" :value="f.value">
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

                            <!-- Character Art (inline) -->
                            <div class="border-t pt-3">
                                <label class="mb-1.5 block text-xs text-muted-foreground">Character Art</label>
                                <div v-if="characterImagePreview" class="relative">
                                    <img :src="characterImagePreview" alt="Character art preview" class="h-32 w-full rounded-md border object-cover" />
                                    <button class="absolute right-2 top-2 rounded-full bg-black/60 p-1 text-white hover:bg-black/80" @click="removeImage"><X class="size-4" /></button>
                                </div>
                                <label v-else class="flex cursor-pointer items-center gap-3 rounded-lg border-2 border-dashed border-muted-foreground/25 px-4 py-3 transition-colors hover:border-muted-foreground/50 hover:bg-muted/50">
                                    <ImagePlus class="size-5 shrink-0 text-muted-foreground/50" />
                                    <div>
                                        <span class="text-xs font-medium">Click to upload</span>
                                        <span class="ml-1 text-xs text-muted-foreground">(jpg/png/webp)</span>
                                    </div>
                                    <input type="file" accept="image/jpeg,image/png,image/webp" class="hidden" @change="onImageSelected" />
                                </label>
                                <p class="mt-1 text-[10px] text-muted-foreground/60">Art is used for preview only and must be re-added each session.</p>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- ═══ STATS ═══ -->
                    <Card>
                        <CardContent class="space-y-3 p-4">
                            <h3 class="text-sm font-semibold">Stats</h3>
                            <!-- Row 1: Core -->
                            <div class="grid grid-cols-4 gap-2">
                                <div>
                                    <label class="mb-1 block text-[10px] uppercase tracking-wider text-muted-foreground">Cost</label>
                                    <Input v-model.number="form.cost" type="number" min="0" max="99" class="h-8" />
                                </div>
                                <div>
                                    <label class="mb-1 block text-[10px] uppercase tracking-wider text-muted-foreground">Health *</label>
                                    <Input v-model.number="form.health" type="number" min="1" max="99" class="h-8" />
                                </div>
                                <div>
                                    <label class="mb-1 block text-[10px] uppercase tracking-wider text-muted-foreground">Df</label>
                                    <Input v-model.number="form.defense" type="number" min="0" max="20" class="h-8" />
                                </div>
                                <div>
                                    <label class="mb-1 block text-[10px] uppercase tracking-wider text-muted-foreground">Wp</label>
                                    <Input v-model.number="form.willpower" type="number" min="0" max="20" class="h-8" />
                                </div>
                            </div>
                            <!-- Row 2: Physical -->
                            <div class="grid grid-cols-5 gap-2">
                                <div>
                                    <label class="mb-1 block text-[10px] uppercase tracking-wider text-muted-foreground">Mv</label>
                                    <Input v-model.number="form.speed" type="number" min="0" max="20" class="h-8" />
                                </div>
                                <div>
                                    <label class="mb-1 block text-[10px] uppercase tracking-wider text-muted-foreground">Sz</label>
                                    <Input v-model.number="form.size" type="number" min="0" max="10" class="h-8" />
                                </div>
                                <div>
                                    <label class="mb-1 block text-[10px] uppercase tracking-wider text-muted-foreground">Base</label>
                                    <Select v-model="form.base">
                                        <SelectTrigger class="h-8"><SelectValue /></SelectTrigger>
                                        <SelectContent>
                                            <SelectItem v-for="b in enums.bases" :key="b.value" :value="b.value">{{ b.name }}</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div>
                                    <label class="mb-1 block text-[10px] uppercase tracking-wider text-muted-foreground">Count</label>
                                    <Input v-model.number="form.count" type="number" min="1" max="10" class="h-8" />
                                </div>
                                <div>
                                    <label class="mb-1 block text-[10px] uppercase tracking-wider text-muted-foreground">Summon</label>
                                    <Input v-model.number="form.summon_target_number" type="number" min="1" max="20" placeholder="—" class="h-8" />
                                </div>
                            </div>
                            <!-- Row 3: Flags -->
                            <div class="flex flex-wrap gap-x-5 gap-y-1 pt-1">
                                <label class="flex items-center gap-1.5 text-xs">
                                    <input v-model="form.generates_stone" type="checkbox" class="rounded" />
                                    Generates Soulstone
                                </label>
                                <label class="flex items-center gap-1.5 text-xs">
                                    <input v-model="form.is_unhirable" type="checkbox" class="rounded" />
                                    Unhirable
                                </label>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- ═══ DETAILS: Keywords, Chars, Crew Upgrades, Totems ═══ -->
                    <Card>
                        <CardContent class="space-y-4 p-4">
                            <h3 class="text-sm font-semibold">Details</h3>

                            <!-- Keywords -->
                            <div>
                                <label class="mb-1.5 block text-xs font-medium text-muted-foreground">Keywords</label>
                                <div v-if="keywords.length" class="mb-2 flex flex-wrap gap-1.5">
                                    <Badge v-for="(kw, i) in keywords" :key="'kw-' + i" variant="secondary" class="gap-1">
                                        {{ kw.name }}
                                        <Badge v-if="kw.id" variant="outline" class="ml-0.5 px-1 py-0 text-[8px]">Official</Badge>
                                        <button class="ml-0.5 hover:text-destructive" @click="removeKeyword(i)"><X class="size-3" /></button>
                                    </Badge>
                                </div>
                                <div class="relative">
                                    <Input v-model="newKeyword" placeholder="Search or type a keyword..." class="h-8 text-xs" @input="searchKeywords(newKeyword)" @keydown.enter.prevent="addKeyword()" />
                                    <div v-if="searchType === 'keyword' && searchResults.length" class="absolute z-10 mt-1 w-full rounded-md border bg-popover p-1 shadow-md">
                                        <button v-for="r in searchResults" :key="r.id" class="flex w-full items-center gap-2 rounded px-2 py-1.5 text-left text-sm hover:bg-accent" @click="pickKeyword(r)">
                                            {{ r.name }}
                                            <Badge variant="outline" class="px-1 py-0 text-[8px]">Official</Badge>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Characteristics -->
                            <div>
                                <label class="mb-1.5 block text-xs font-medium text-muted-foreground">Characteristics</label>
                                <div v-if="characteristics.length" class="mb-2 flex flex-wrap gap-1.5">
                                    <Badge v-for="(c, i) in characteristics" :key="'char-' + i" variant="outline" class="gap-1">
                                        {{ c }}
                                        <button class="ml-0.5 hover:text-destructive" @click="removeCharacteristic(i)"><X class="size-3" /></button>
                                    </Badge>
                                </div>
                                <Input v-model="newCharacteristic" placeholder="Type a characteristic and press Enter" class="h-8 text-xs" @keydown.enter.prevent="addCharacteristic()" />
                            </div>

                            <!-- Crew Upgrades -->
                            <div>
                                <label class="mb-1.5 block text-xs font-medium text-muted-foreground">Crew Upgrades</label>
                                <div v-if="linkedCrewUpgrades.length" class="mb-2 flex flex-wrap gap-1.5">
                                    <Badge v-for="(u, i) in linkedCrewUpgrades" :key="'cu-' + i" variant="secondary" class="gap-1">
                                        {{ u.name }}
                                        <Badge v-if="u.source_type === 'official'" variant="outline" class="ml-0.5 px-1 py-0 text-[8px]">Official</Badge>
                                        <Badge v-else variant="outline" class="ml-0.5 border-purple-400 px-1 py-0 text-[8px] text-purple-500">Custom</Badge>
                                        <button class="ml-0.5 hover:text-destructive" @click="removeCrewUpgrade(i)"><X class="size-3" /></button>
                                    </Badge>
                                </div>
                                <div class="relative">
                                    <Input v-model="upgradeSearchQuery" placeholder="Search crew upgrades..." class="h-8 text-xs" @input="searchCrewUpgrades(upgradeSearchQuery)" />
                                    <div v-if="upgradeSearchResults.length" class="absolute z-10 mt-1 w-full rounded-md border bg-popover p-1 shadow-md">
                                        <button v-for="r in upgradeSearchResults" :key="r.source_type + '-' + r.id" class="flex w-full items-center gap-2 rounded px-2 py-1.5 text-left text-sm hover:bg-accent" @click="pickCrewUpgrade(r)">
                                            {{ r.name }}
                                            <Badge v-if="r.source_type === 'official'" variant="outline" class="px-1 py-0 text-[8px]">Official</Badge>
                                            <Badge v-else variant="outline" class="border-purple-400 px-1 py-0 text-[8px] text-purple-500">Custom</Badge>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Totems -->
                            <div>
                                <label class="mb-1.5 block text-xs font-medium text-muted-foreground">Totems</label>
                                <div v-if="linkedTotems.length" class="mb-2 flex flex-wrap gap-1.5">
                                    <Badge v-for="(t, i) in linkedTotems" :key="'totem-' + i" variant="secondary" class="gap-1">
                                        {{ t.name }}
                                        <Badge v-if="t.source_type === 'official'" variant="outline" class="ml-0.5 px-1 py-0 text-[8px]">Official</Badge>
                                        <Badge v-else variant="outline" class="ml-0.5 border-purple-400 px-1 py-0 text-[8px] text-purple-500">Custom</Badge>
                                        <button class="ml-0.5 hover:text-destructive" @click="removeTotem(i)"><X class="size-3" /></button>
                                    </Badge>
                                </div>
                                <div class="relative">
                                    <Input v-model="totemSearchQuery" placeholder="Search characters for totems..." class="h-8 text-xs" @input="searchTotems(totemSearchQuery)" />
                                    <div v-if="totemSearchResults.length" class="absolute z-10 mt-1 w-full rounded-md border bg-popover p-1 shadow-md">
                                        <button v-for="r in totemSearchResults" :key="r.source_type + '-' + r.id" class="flex w-full items-center gap-2 rounded px-2 py-1.5 text-left text-sm hover:bg-accent" @click="pickTotem(r)">
                                            {{ r.name }}
                                            <Badge v-if="r.source_type === 'official'" variant="outline" class="px-1 py-0 text-[8px]">Official</Badge>
                                            <Badge v-else variant="outline" class="border-purple-400 px-1 py-0 text-[8px] text-purple-500">Custom</Badge>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- ═══ ABILITIES (collapsible) ═══ -->
                    <Collapsible default-open>
                        <Card>
                            <CardContent class="p-4">
                                <CollapsibleTrigger class="flex w-full items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <h3 class="text-sm font-semibold">Abilities</h3>
                                        <Badge v-if="abilities.length" variant="secondary" class="text-[10px]">{{ abilities.length }}</Badge>
                                    </div>
                                    <ChevronDown class="size-4 text-muted-foreground transition-transform [[data-state=open]_&]:rotate-180" />
                                </CollapsibleTrigger>
                                <CollapsibleContent>
                                    <div class="mt-3 space-y-3">
                                        <div class="flex items-center gap-2">
                                            <div class="relative flex-1">
                                                <Input placeholder="Search official abilities..." class="h-8 text-xs" @input="(e: Event) => searchOfficial('ability', (e.target as HTMLInputElement).value)" />
                                                <div v-if="searchType === 'ability' && searchResults.length" class="absolute z-10 mt-1 w-full rounded-md border bg-popover p-1 shadow-md">
                                                    <button v-for="r in searchResults" :key="r.id" class="w-full rounded px-2 py-1.5 text-left text-sm hover:bg-accent" @click="pickAbility(r)">
                                                        <span class="font-medium">{{ r.name }}</span>
                                                        <span class="ml-2 text-xs text-muted-foreground">{{ r.description?.slice(0, 60) }}{{ r.description?.length > 60 ? '...' : '' }}</span>
                                                    </button>
                                                </div>
                                            </div>
                                            <Button variant="outline" size="sm" class="h-8 shrink-0 text-xs" @click="addAbility"><Plus class="mr-1 size-3" /> Custom</Button>
                                        </div>

                                        <div v-for="(ability, aIdx) in abilities" :key="'ability-' + aIdx" class="space-y-2 rounded-lg border p-3">
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
                                                        <input v-model="ability.costs_stone" type="checkbox" class="rounded" />
                                                        Costs Soulstone
                                                    </label>
                                                </div>
                                                <Textarea v-model="ability.description" placeholder="Ability description..." rows="2" class="text-xs" />
                                            </template>
                                        </div>
                                    </div>
                                </CollapsibleContent>
                            </CardContent>
                        </Card>
                    </Collapsible>

                    <!-- ═══ ACTIONS (collapsible) ═══ -->
                    <Collapsible default-open>
                        <Card>
                            <CardContent class="p-4">
                                <CollapsibleTrigger class="flex w-full items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <h3 class="text-sm font-semibold">Actions</h3>
                                        <Badge v-if="actions.length" variant="secondary" class="text-[10px]">{{ actions.length }}</Badge>
                                    </div>
                                    <ChevronDown class="size-4 text-muted-foreground transition-transform [[data-state=open]_&]:rotate-180" />
                                </CollapsibleTrigger>
                                <CollapsibleContent>
                                    <div class="mt-3 space-y-3">
                                        <div class="flex items-center gap-2">
                                            <div class="relative flex-1">
                                                <Input placeholder="Search official actions..." class="h-8 text-xs" @input="(e: Event) => searchOfficial('action', (e.target as HTMLInputElement).value)" />
                                                <div v-if="searchType === 'action' && searchResults.length" class="absolute z-10 mt-1 w-full rounded-md border bg-popover p-1 shadow-md">
                                                    <button v-for="r in searchResults" :key="r.id" class="w-full rounded px-2 py-1.5 text-left text-sm hover:bg-accent" @click="pickAction(r)">
                                                        <span class="font-medium">{{ r.name }}</span>
                                                        <Badge class="ml-2 text-[9px]">{{ r.type }}</Badge>
                                                    </button>
                                                </div>
                                            </div>
                                            <Button variant="outline" size="sm" class="h-8 shrink-0 text-xs" @click="addAction"><Plus class="mr-1 size-3" /> Custom</Button>
                                        </div>

                                        <div v-for="(action, idx) in actions" :key="'action-' + idx" class="space-y-3 rounded-lg border p-3">
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
                                                    <span v-if="action.range != null">Rg {{ formatRange(action.range) }}</span>
                                                    <span v-if="action.stat != null">Stat {{ action.stat }}{{ action.stat_suits ? ' ' + action.stat_suits : '' }}</span>
                                                    <span v-if="action.resisted_by">vs {{ action.resisted_by }}</span>
                                                    <span v-if="action.damage">Dmg {{ action.damage }}</span>
                                                </div>
                                                <div v-if="action.description" class="text-xs text-muted-foreground">{{ action.description }}</div>
                                            </template>
                                            <template v-else>
                                                <div class="flex items-center justify-between">
                                                    <Input v-model="action.name" placeholder="Action name" class="h-7 text-sm font-medium" />
                                                    <button class="ml-2 text-muted-foreground hover:text-destructive" @click="removeAction(idx)"><Trash2 class="size-3.5" /></button>
                                                </div>
                                                <div class="grid grid-cols-3 gap-2 sm:grid-cols-6">
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
                                                        <Input v-model="action.range" placeholder='e.g. 2, *, X' class="h-7 text-xs" />
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
                                                        <Input v-model="action.stat" placeholder='e.g. 5, X' class="h-7 text-xs" />
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
                                            </template>

                                            <!-- Triggers (always shown) -->
                                            <div class="space-y-2 border-t pt-2">
                                                <div class="flex items-center justify-between">
                                                    <span class="text-[11px] font-medium text-muted-foreground">Triggers</span>
                                                    <Button variant="ghost" size="sm" class="h-5 px-1.5 text-[10px]" @click="addTrigger(action)"><Plus class="mr-0.5 size-2.5" /> Add</Button>
                                                </div>
                                                <div class="relative">
                                                    <Input placeholder="Search official triggers..." class="h-7 text-xs" @input="(e: Event) => searchTriggers((e.target as HTMLInputElement).value, idx)" />
                                                    <div v-if="searchType === 'trigger' && searchTargetIndex === idx && searchResults.length" class="absolute z-10 mt-1 w-full rounded-md border bg-popover p-1 shadow-md">
                                                        <button v-for="r in searchResults" :key="r.id" class="w-full rounded px-2 py-1.5 text-left text-sm hover:bg-accent" @click="pickTrigger(r)">
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
                                        </div>
                                    </div>
                                </CollapsibleContent>
                            </CardContent>
                        </Card>
                    </Collapsible>

                    <!-- ═══ NOTES (collapsible) ═══ -->
                    <Collapsible>
                        <Card>
                            <CardContent class="p-4">
                                <CollapsibleTrigger class="flex w-full items-center justify-between">
                                    <h3 class="text-sm font-semibold">Notes</h3>
                                    <ChevronDown class="size-4 text-muted-foreground transition-transform [[data-state=open]_&]:rotate-180" />
                                </CollapsibleTrigger>
                                <CollapsibleContent>
                                    <div class="mt-3">
                                        <Textarea v-model="form.notes" placeholder="Personal notes (not shown on card)..." rows="3" class="text-xs" />
                                    </div>
                                </CollapsibleContent>
                            </CardContent>
                        </Card>
                    </Collapsible>
                </div>

                <!-- ═══ LIVE PREVIEW ═══ -->
                <div class="lg:col-span-1">
                    <div class="sticky top-4 space-y-3">
                        <div class="flex items-center justify-between">
                            <h3 class="text-sm font-semibold">Preview</h3>
                            <Button variant="outline" size="sm" class="text-xs" :disabled="exporting || !form.name" @click="exportImages">
                                <Loader2 v-if="exporting" class="mr-1 size-3 animate-spin" />
                                <Download v-else class="mr-1 size-3" />
                                {{ exporting ? 'Exporting...' : 'Export Image' }}
                            </Button>
                        </div>
                        <CardRenderer
                            ref="cardRendererRef"
                            :name="form.name || 'Character Name'"
                            :title="form.title || null"
                            :faction="form.faction !== 'none' ? form.faction : null"
                            :second-faction="form.second_faction !== 'none' ? form.second_faction : null"
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
                            :linked-crew-upgrades="linkedCrewUpgrades"
                            :linked-totems="linkedTotems"
                        />
                    </div>
                </div>
            </div>
        </div>

        <!-- ═══ SAVE BAR ═══ -->
        <div class="container mx-auto mt-4 px-4 lg:px-6">
            <div class="flex items-center justify-between rounded-lg border bg-muted/50 px-4 py-2.5">
                <div v-if="Object.keys(errors).length" class="text-xs text-destructive">Please fix validation errors above.</div>
                <div v-else class="text-xs text-muted-foreground">{{ isEdit ? 'Editing ' + (form.name || 'character') : 'New character' }}</div>
                <Button :disabled="saving || !form.name || form.faction === 'none'" size="sm" @click="save">
                    <Loader2 v-if="saving" class="mr-2 size-4 animate-spin" />
                    <Save v-else class="mr-2 size-4" />
                    {{ isEdit ? 'Save Changes' : 'Create Character' }}
                </Button>
            </div>
        </div>
    </div>

</template>
