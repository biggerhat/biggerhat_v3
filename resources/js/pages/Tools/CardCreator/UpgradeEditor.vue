<script setup lang="ts">
import UpgradeCardRenderer from '@/components/CardCreator/UpgradeCardRenderer.vue';
import { createComboImage, fetchFontEmbedCSS, formatRange, triggerDownload } from '@/components/CardCreator/utils';
import PageBanner from '@/components/PageBanner.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, ChevronDown, Download, Loader2, Plus, Save, Trash2 } from 'lucide-vue-next';
import { computed, reactive, ref } from 'vue';

interface EnumOption {
    name: string;
    value: string;
}

interface ContentBlock {
    type: 'text' | 'ability' | 'action' | 'trigger';
    text?: string;
    data?: Record<string, any>;
}

interface TokenData {
    name: string;
    description: string | null;
    source_id?: number | null;
}

interface MarkerData {
    name: string;
    description: string | null;
    source_id?: number | null;
}

const props = defineProps<{
    upgrade: {
        id: number;
        name: string;
        slug: string;
        domain: string;
        type: string | null;
        faction: string | null;
        limitations: string | null;
        plentiful: number | null;
        master_name: string | null;
        keyword_name: string | null;
        content_blocks: ContentBlock[] | null;
        back_tokens: TokenData[] | null;
        back_markers: MarkerData[] | null;
        notes: string | null;
        share_code: string;
    } | null;
    domain: string;
    enums: {
        factions: EnumOption[];
        upgrade_types: EnumOption[];
        limitations: EnumOption[];
        suits: EnumOption[];
        action_types: EnumOption[];
        range_types: EnumOption[];
    };
}>();

const isEdit = computed(() => !!props.upgrade);
const isCrew = computed(() => props.domain === 'crew');
const domainLabel = computed(() => (isCrew.value ? 'Crew Card' : 'Upgrade'));

const form = reactive({
    name: props.upgrade?.name ?? '',
    type: props.upgrade?.type ?? '',
    faction: props.upgrade?.faction ?? 'none',
    limitations: props.upgrade?.limitations ?? '',
    plentiful: props.upgrade?.plentiful ?? null as number | null,
    master_name: props.upgrade?.master_name ?? '',
    keyword_name: props.upgrade?.keyword_name ?? '',
    notes: props.upgrade?.notes ?? '',
});

const showTypeSuggestions = ref(false);
const typeSuggestions = computed(() => {
    const q = (form.type || '').toLowerCase();
    const all = props.enums.upgrade_types;
    if (!q) return all;
    return all.filter((t) => t.name.toLowerCase().includes(q));
});

const showLimitationsSuggestions = ref(false);
const limitationsSuggestions = computed(() => {
    const q = (form.limitations || '').toLowerCase();
    const all = props.enums.limitations;
    if (!q) return all;
    return all.filter((l) => l.name.toLowerCase().includes(q));
});

const contentBlocks = reactive<ContentBlock[]>(props.upgrade?.content_blocks ?? []);
const backTokens = reactive<TokenData[]>(props.upgrade?.back_tokens ?? []);
const backMarkers = reactive<MarkerData[]>(props.upgrade?.back_markers ?? []);

const saving = ref(false);
const exporting = ref(false);
const errors = ref<Record<string, string>>({});
const cardRendererRef = ref<InstanceType<typeof UpgradeCardRenderer> | null>(null);

const csrfToken = () => document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
const noneToNull = (v: string | null | undefined) => (!v || v === 'none' ? null : v);

// Labels for card preview
const upgradeTypeLabel = computed(() => form.type || null);
const limitationsLabel = computed(() => form.limitations || null);

// Content block helpers
const addTextBlock = () => contentBlocks.push({ type: 'text', text: '' });

const addAbilityBlock = () =>
    contentBlocks.push({
        type: 'ability',
        data: { name: '', suits: null, defensive_ability_type: null, costs_stone: false, description: null, source_id: null },
    });

const addActionBlock = () =>
    contentBlocks.push({
        type: 'action',
        data: {
            name: '',
            type: 'tactical',
            is_signature: false,
            stone_cost: 0,
            range: null,
            range_type: null,
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
        },
    });

const addTriggerBlock = () =>
    contentBlocks.push({
        type: 'trigger',
        data: { name: '', suits: null, stone_cost: 0, description: null, source_id: null },
    });

const removeBlock = (index: number) => contentBlocks.splice(index, 1);
const moveBlockUp = (index: number) => {
    if (index > 0) {
        const item = contentBlocks.splice(index, 1)[0];
        contentBlocks.splice(index - 1, 0, item);
    }
};
const moveBlockDown = (index: number) => {
    if (index < contentBlocks.length - 1) {
        const item = contentBlocks.splice(index, 1)[0];
        contentBlocks.splice(index + 1, 0, item);
    }
};

// Back face helpers
const addToken = () => backTokens.push({ name: '', description: null });
const removeToken = (index: number) => backTokens.splice(index, 1);
const addMarker = () => backMarkers.push({ name: '', description: null });
const removeMarker = (index: number) => backMarkers.splice(index, 1);

// Token/Marker search
const tokenSearchResults = ref<any[]>([]);
const markerSearchResults = ref<any[]>([]);
let tokenSearchDebounce: ReturnType<typeof setTimeout>;
let markerSearchDebounce: ReturnType<typeof setTimeout>;

const searchTokens = (q: string) => {
    clearTimeout(tokenSearchDebounce);
    if (q.length < 2) {
        tokenSearchResults.value = [];
        return;
    }
    tokenSearchDebounce = setTimeout(async () => {
        const res = await fetch(route('api.card-creator.tokens') + '?q=' + encodeURIComponent(q));
        tokenSearchResults.value = await res.json();
    }, 300);
};

const pickToken = (official: any) => {
    backTokens.push({ name: official.name, description: official.description, source_id: official.source_id });
    tokenSearchResults.value = [];
};

const searchMarkers = (q: string) => {
    clearTimeout(markerSearchDebounce);
    if (q.length < 2) {
        markerSearchResults.value = [];
        return;
    }
    markerSearchDebounce = setTimeout(async () => {
        const res = await fetch(route('api.card-creator.markers') + '?q=' + encodeURIComponent(q));
        markerSearchResults.value = await res.json();
    }, 300);
};

const pickMarker = (official: any) => {
    backMarkers.push({ name: official.name, description: official.description, source_id: official.source_id });
    markerSearchResults.value = [];
};

// Search
const searchResults = ref<any[]>([]);
const searchType = ref<'action' | 'ability' | 'trigger' | null>(null);
let searchDebounce: ReturnType<typeof setTimeout>;

const searchOfficial = (type: 'action' | 'ability' | 'trigger', q: string) => {
    searchType.value = type;
    clearTimeout(searchDebounce);
    if (q.length < 2) {
        searchResults.value = [];
        return;
    }
    searchDebounce = setTimeout(async () => {
        const endpoints: Record<string, string> = {
            action: 'api.card-creator.actions',
            ability: 'api.card-creator.abilities',
            trigger: 'api.card-creator.triggers',
        };
        const res = await fetch(route(endpoints[type]) + '?q=' + encodeURIComponent(q));
        searchResults.value = await res.json();
    }, 300);
};

const pickSearchResult = (official: any) => {
    if (searchType.value === 'ability') {
        contentBlocks.push({ type: 'ability', data: { ...official } });
    } else if (searchType.value === 'action') {
        contentBlocks.push({ type: 'action', data: { ...official, triggers: official.triggers ?? [] } });
    } else if (searchType.value === 'trigger') {
        contentBlocks.push({ type: 'trigger', data: { ...official } });
    }
    searchResults.value = [];
    searchType.value = null;
};

// Image export

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
        const origFrontBackface = frontEl.style.backfaceVisibility;
        frontEl.style.backfaceVisibility = 'visible';
        const frontData = await toPng(frontEl, opts);
        frontEl.style.backfaceVisibility = origFrontBackface;
        const origTransform = backEl.style.transform;
        const origBackface = backEl.style.backfaceVisibility;
        backEl.style.transform = 'none';
        backEl.style.backfaceVisibility = 'visible';
        const backData = await toPng(backEl, opts);
        backEl.style.transform = origTransform;
        backEl.style.backfaceVisibility = origBackface;
        const comboData = await createComboImage(frontData, backData);
        triggerDownload(comboData, `${form.name || 'upgrade'}.png`);
    } catch (e) {
        console.error('Image export failed:', e);
    } finally {
        exporting.value = false;
    }
};

// Save
const emptyToNull = (v: unknown): unknown => (v === '' || v === undefined ? null : v);

const normalizeActionData = (data: Record<string, unknown>): Record<string, unknown> => ({
    ...data,
    range: emptyToNull(data.range),
    stat: emptyToNull(data.stat),
    target_number: emptyToNull(data.target_number),
    stone_cost: data.stone_cost === '' || data.stone_cost == null ? 0 : data.stone_cost,
    triggers: Array.isArray(data.triggers)
        ? data.triggers.map((t: Record<string, unknown>) => ({ ...t, stone_cost: t.stone_cost === '' || t.stone_cost == null ? 0 : t.stone_cost }))
        : [],
});

const save = async () => {
    saving.value = true;
    errors.value = {};

    const normalizedBlocks = contentBlocks.map((b) => {
        if (b.type === 'action' && b.data) return { ...b, data: normalizeActionData(b.data) };
        if (b.type === 'trigger' && b.data) return { ...b, data: { ...b.data, stone_cost: b.data.stone_cost === '' || b.data.stone_cost == null ? 0 : b.data.stone_cost } };
        return b;
    });

    const body: Record<string, any> = {
        name: form.name,
        domain: props.domain,
        type: form.type || null,
        faction: noneToNull(form.faction),
        limitations: form.limitations || null,
        plentiful: form.plentiful,
        master_name: form.master_name || null,
        keyword_name: form.keyword_name || null,
        notes: form.notes || null,
        content_blocks: normalizedBlocks,
        back_tokens: isCrew.value ? backTokens : [],
        back_markers: isCrew.value ? backMarkers : [],
    };

    const url = isEdit.value ? route('tools.card_creator.upgrades.update', props.upgrade!.id) : route('tools.card_creator.upgrades.store');

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

const blockTypeLabel = (type: string) => {
    const map: Record<string, string> = { text: 'Text', ability: 'Ability', action: 'Action', trigger: 'Trigger' };
    return map[type] ?? type;
};

const blockTypeColor = (type: string) => {
    const map: Record<string, string> = { text: 'bg-muted', ability: 'bg-blue-500/10 border-blue-500/20', action: 'bg-amber-500/10 border-amber-500/20', trigger: 'bg-purple-500/10 border-purple-500/20' };
    return map[type] ?? 'bg-muted';
};
</script>

<template>
    <Head :title="isEdit ? `Edit — ${upgrade!.name}` : `New Custom ${domainLabel}`" />

    <div class="relative pb-12">
        <div class="pointer-events-none absolute inset-x-0 top-0 h-64 opacity-[0.07] dark:opacity-[0.12]" :style="{ background: 'radial-gradient(ellipse at top, hsl(var(--primary)) 0%, transparent 70%)' }" />

        <PageBanner :title="isEdit ? `Edit ${domainLabel}` : `New Custom ${domainLabel}`">
            <template #subtitle>
                <div class="px-2 text-sm text-muted-foreground">
                    {{ isEdit ? `Editing ${upgrade!.name}` : `Build a custom ${domainLabel.toLowerCase()} with abilities, actions, and triggers.` }}
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

                    <!-- Identity -->
                    <Card>
                        <CardContent class="space-y-4 p-4">
                            <h3 class="text-sm font-semibold">{{ domainLabel }} Identity</h3>
                            <div>
                                <label class="mb-1 block text-xs text-muted-foreground">Name *</label>
                                <Input v-model="form.name" :placeholder="`${domainLabel} name`" />
                                <p v-if="errors.name" class="mt-1 text-xs text-destructive">{{ errors.name }}</p>
                            </div>
                            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                <div>
                                    <label class="mb-1 block text-xs text-muted-foreground">Faction</label>
                                    <Select v-model="form.faction">
                                        <SelectTrigger><SelectValue placeholder="Select faction" /></SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="none">None</SelectItem>
                                            <SelectItem v-for="f in enums.factions" :key="f.value" :value="f.value">{{ f.name }}</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div v-if="!isCrew" class="relative">
                                    <label class="mb-1 block text-xs text-muted-foreground">Upgrade Type</label>
                                    <Input
                                        v-model="form.type"
                                        placeholder="Search or type custom..."
                                        @focus="showTypeSuggestions = true"
                                        @blur="window.setTimeout(() => (showTypeSuggestions = false), 150)"
                                    />
                                    <div v-if="showTypeSuggestions && typeSuggestions.length" class="absolute z-10 mt-1 max-h-48 w-full overflow-auto rounded-md border bg-popover p-1 shadow-md">
                                        <button
                                            v-for="t in typeSuggestions"
                                            :key="t.value"
                                            class="flex w-full items-center justify-between rounded px-2 py-1.5 text-left text-sm hover:bg-accent"
                                            @mousedown.prevent="form.type = t.name; showTypeSuggestions = false"
                                        >
                                            {{ t.name }}
                                            <Badge variant="outline" class="px-1 py-0 text-[8px]">Official</Badge>
                                        </button>
                                    </div>
                                </div>
                                <div v-if="!isCrew" class="relative">
                                    <label class="mb-1 block text-xs text-muted-foreground">Limitations</label>
                                    <Input
                                        v-model="form.limitations"
                                        placeholder="Search or type custom..."
                                        @focus="showLimitationsSuggestions = true"
                                        @blur="window.setTimeout(() => (showLimitationsSuggestions = false), 150)"
                                    />
                                    <div v-if="showLimitationsSuggestions && limitationsSuggestions.length" class="absolute z-10 mt-1 max-h-48 w-full overflow-auto rounded-md border bg-popover p-1 shadow-md">
                                        <button
                                            v-for="l in limitationsSuggestions"
                                            :key="l.value"
                                            class="flex w-full items-center justify-between rounded px-2 py-1.5 text-left text-sm hover:bg-accent"
                                            @mousedown.prevent="form.limitations = l.name; showLimitationsSuggestions = false"
                                        >
                                            {{ l.name }}
                                            <Badge variant="outline" class="px-1 py-0 text-[8px]">Official</Badge>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <!-- Crew-specific fields -->
                            <div v-if="isCrew">
                                <label class="mb-1 block text-xs text-muted-foreground">Master Name</label>
                                <Input v-model="form.master_name" placeholder="e.g. Maxine Agassiz, The Renowned" />
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Content Blocks -->
                    <Collapsible default-open>
                        <Card>
                            <CardContent class="p-4">
                                <CollapsibleTrigger class="flex w-full items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <h3 class="text-sm font-semibold">Card Content</h3>
                                        <Badge v-if="contentBlocks.length" variant="secondary" class="text-[10px]">{{ contentBlocks.length }}</Badge>
                                    </div>
                                    <ChevronDown class="size-4 text-muted-foreground transition-transform [[data-state=open]_&]:rotate-180" />
                                </CollapsibleTrigger>
                                <CollapsibleContent>
                                    <div class="mt-3 space-y-3">
                                        <p class="text-[11px] text-muted-foreground">Add text prefaces and game elements in the order they should appear on the card.</p>

                                        <!-- Search official -->
                                        <div class="relative">
                                            <Input placeholder="Search official abilities, actions, or triggers..." class="h-8 text-xs" @input="(e: Event) => searchOfficial('ability', (e.target as HTMLInputElement).value)" />
                                            <div v-if="searchResults.length" class="absolute z-10 mt-1 w-full rounded-md border bg-popover p-1 shadow-md">
                                                <button v-for="r in searchResults" :key="r.id" class="w-full rounded px-2 py-1.5 text-left text-sm hover:bg-accent" @click="pickSearchResult(r)">
                                                    <span class="font-medium">{{ r.name }}</span>
                                                    <span v-if="r.description" class="ml-2 text-xs text-muted-foreground">{{ r.description?.slice(0, 50) }}...</span>
                                                </button>
                                            </div>
                                        </div>

                                        <!-- Add buttons -->
                                        <div class="flex flex-wrap gap-1.5">
                                            <Button variant="outline" size="sm" class="h-7 text-[11px]" @click="addTextBlock"><Plus class="mr-1 size-3" /> Text</Button>
                                            <Button variant="outline" size="sm" class="h-7 border-blue-500/30 text-[11px] text-blue-600 dark:text-blue-400" @click="addAbilityBlock"><Plus class="mr-1 size-3" /> Ability</Button>
                                            <Button variant="outline" size="sm" class="h-7 border-amber-500/30 text-[11px] text-amber-600 dark:text-amber-400" @click="addActionBlock"><Plus class="mr-1 size-3" /> Action</Button>
                                            <Button variant="outline" size="sm" class="h-7 border-purple-500/30 text-[11px] text-purple-600 dark:text-purple-400" @click="addTriggerBlock"><Plus class="mr-1 size-3" /> Trigger</Button>
                                        </div>

                                        <!-- Block list -->
                                        <div v-for="(block, idx) in contentBlocks" :key="'block-' + idx" class="rounded-lg border p-3" :class="blockTypeColor(block.type)">
                                            <div class="mb-2 flex items-center justify-between">
                                                <div class="flex items-center gap-2">
                                                    <div class="flex flex-col">
                                                        <button class="text-muted-foreground hover:text-foreground disabled:opacity-30" :disabled="idx === 0" @click="moveBlockUp(idx)"><ChevronDown class="size-3 rotate-180" /></button>
                                                        <button class="text-muted-foreground hover:text-foreground disabled:opacity-30" :disabled="idx === contentBlocks.length - 1" @click="moveBlockDown(idx)"><ChevronDown class="size-3" /></button>
                                                    </div>
                                                    <Badge variant="outline" class="text-[9px]">{{ blockTypeLabel(block.type) }}</Badge>
                                                    <span v-if="block.data?.source_id" class="text-[9px] text-muted-foreground">(Official)</span>
                                                </div>
                                                <button class="text-muted-foreground hover:text-destructive" @click="removeBlock(idx)"><Trash2 class="size-3.5" /></button>
                                            </div>

                                            <!-- Text block -->
                                            <div v-if="block.type === 'text'">
                                                <Input v-model="block.text" placeholder="e.g. This model gains the following ability:" class="h-8 text-xs italic" />
                                            </div>

                                            <!-- Ability block -->
                                            <div v-else-if="block.type === 'ability'" class="space-y-2">
                                                <template v-if="block.data?.source_id">
                                                    <div class="text-sm font-medium">{{ block.data.name }}</div>
                                                    <div v-if="block.data.description" class="text-xs text-muted-foreground">{{ block.data.description }}</div>
                                                </template>
                                                <template v-else>
                                                    <Input v-model="block.data!.name" placeholder="Ability name" class="h-7 text-sm font-medium" />
                                                    <div class="grid gap-2 sm:grid-cols-3">
                                                        <Select v-model="block.data!.suits">
                                                            <SelectTrigger class="h-7 text-xs"><SelectValue placeholder="Suit" /></SelectTrigger>
                                                            <SelectContent>
                                                                <SelectItem value="none">None</SelectItem>
                                                                <SelectItem v-for="s in enums.suits" :key="s.value" :value="s.value">{{ s.name }}</SelectItem>
                                                            </SelectContent>
                                                        </Select>
                                                        <Select v-model="block.data!.defensive_ability_type">
                                                            <SelectTrigger class="h-7 text-xs"><SelectValue placeholder="Defense type" /></SelectTrigger>
                                                            <SelectContent>
                                                                <SelectItem value="none">None</SelectItem>
                                                            </SelectContent>
                                                        </Select>
                                                        <label class="flex items-center gap-1.5 text-xs">
                                                            <input v-model="block.data!.costs_stone" type="checkbox" class="rounded" />
                                                            Costs Soulstone
                                                        </label>
                                                    </div>
                                                    <Textarea v-model="block.data!.description" placeholder="Ability description..." rows="2" class="text-xs" />
                                                </template>
                                            </div>

                                            <!-- Action block -->
                                            <div v-else-if="block.type === 'action'" class="space-y-2">
                                                <template v-if="block.data?.source_id">
                                                    <div class="flex items-center gap-2">
                                                        <span class="text-sm font-medium">{{ block.data.name }}</span>
                                                        <Badge class="text-[9px]">{{ block.data.type }}</Badge>
                                                    </div>
                                                    <div class="flex flex-wrap gap-x-3 gap-y-0.5 text-[11px] text-muted-foreground">
                                                        <span v-if="block.data.range != null">Rg {{ formatRange(block.data.range as number | string | null | undefined) }}</span>
                                                        <span v-if="block.data.stat != null">Stat {{ block.data.stat }}</span>
                                                        <span v-if="block.data.damage">Dmg {{ block.data.damage }}</span>
                                                    </div>
                                                    <div v-if="block.data.description" class="text-xs text-muted-foreground">{{ block.data.description }}</div>
                                                </template>
                                                <template v-else>
                                                    <Input v-model="block.data!.name" placeholder="Action name" class="h-7 text-sm font-medium" />
                                                    <div class="grid grid-cols-3 gap-2 sm:grid-cols-6">
                                                        <div>
                                                            <label class="text-[10px] text-muted-foreground">Type</label>
                                                            <Select v-model="block.data!.type">
                                                                <SelectTrigger class="h-7 text-xs"><SelectValue /></SelectTrigger>
                                                                <SelectContent>
                                                                    <SelectItem v-for="t in enums.action_types" :key="t.value" :value="t.value">{{ t.name }}</SelectItem>
                                                                </SelectContent>
                                                            </Select>
                                                        </div>
                                                        <div>
                                                            <label class="text-[10px] text-muted-foreground">Range</label>
                                                            <Input v-model="block.data!.range" placeholder='e.g. 2, *, X' class="h-7 text-xs" />
                                                        </div>
                                                        <div>
                                                            <label class="text-[10px] text-muted-foreground">Stat</label>
                                                            <Input v-model="block.data!.stat" placeholder='e.g. 5, X' class="h-7 text-xs" />
                                                        </div>
                                                        <div>
                                                            <label class="text-[10px] text-muted-foreground">Resisted By</label>
                                                            <Input v-model="block.data!.resisted_by" placeholder="Df" class="h-7 text-xs" />
                                                        </div>
                                                        <div>
                                                            <label class="text-[10px] text-muted-foreground">TN</label>
                                                            <Input v-model="block.data!.target_number" placeholder='e.g. 12' class="h-7 text-xs" />
                                                        </div>
                                                        <div>
                                                            <label class="text-[10px] text-muted-foreground">Damage</label>
                                                            <Input v-model="block.data!.damage" placeholder="2/3/5" class="h-7 text-xs" />
                                                        </div>
                                                    </div>
                                                    <Textarea v-model="block.data!.description" placeholder="Action description..." rows="2" class="text-xs" />
                                                </template>
                                            </div>

                                            <!-- Trigger block -->
                                            <div v-else-if="block.type === 'trigger'" class="space-y-2">
                                                <template v-if="block.data?.source_id">
                                                    <div class="text-sm font-medium">
                                                        <span v-if="block.data.suits" class="text-muted-foreground">{{ block.data.suits }}</span>
                                                        {{ block.data.name }}
                                                    </div>
                                                    <div v-if="block.data.description" class="text-xs text-muted-foreground">{{ block.data.description }}</div>
                                                </template>
                                                <template v-else>
                                                    <div class="flex items-center gap-2">
                                                        <Input v-model="block.data!.name" placeholder="Trigger name" class="h-7 text-sm font-medium" />
                                                        <Input v-model="block.data!.suits" placeholder="Suits" class="h-7 w-28 text-xs" />
                                                    </div>
                                                    <Input v-model="block.data!.description" placeholder="Trigger description" class="h-7 text-xs" />
                                                </template>
                                            </div>
                                        </div>
                                    </div>
                                </CollapsibleContent>
                            </CardContent>
                        </Card>
                    </Collapsible>

                    <!-- Back Face: Tokens & Markers (crew only) -->
                    <Collapsible v-if="isCrew" default-open>
                        <Card>
                            <CardContent class="p-4">
                                <CollapsibleTrigger class="flex w-full items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <h3 class="text-sm font-semibold">Back — Tokens & Markers</h3>
                                        <Badge v-if="backTokens.length + backMarkers.length" variant="secondary" class="text-[10px]">{{ backTokens.length + backMarkers.length }}</Badge>
                                    </div>
                                    <ChevronDown class="size-4 text-muted-foreground transition-transform [[data-state=open]_&]:rotate-180" />
                                </CollapsibleTrigger>
                                <CollapsibleContent>
                                    <div class="mt-3 space-y-4">
                                        <!-- Tokens -->
                                        <div>
                                            <div class="mb-2 flex items-center justify-between">
                                                <label class="text-xs font-medium text-muted-foreground">Tokens</label>
                                                <Button variant="outline" size="sm" class="h-7 text-[11px]" @click="addToken"><Plus class="mr-1 size-3" /> Custom</Button>
                                            </div>
                                            <div class="relative mb-2">
                                                <Input placeholder="Search official tokens..." class="h-8 text-xs" @input="(e: Event) => searchTokens((e.target as HTMLInputElement).value)" />
                                                <div v-if="tokenSearchResults.length" class="absolute z-10 mt-1 w-full rounded-md border bg-popover p-1 shadow-md">
                                                    <button v-for="r in tokenSearchResults" :key="r.id" class="w-full rounded px-2 py-1.5 text-left text-sm hover:bg-accent" @click="pickToken(r)">
                                                        <span class="font-medium">{{ r.name }}</span>
                                                        <Badge variant="outline" class="ml-2 px-1 py-0 text-[8px]">Official</Badge>
                                                    </button>
                                                </div>
                                            </div>
                                            <div v-for="(token, tIdx) in backTokens" :key="'token-' + tIdx" class="mb-2 flex items-start gap-2 rounded-lg border p-2">
                                                <div class="flex-1 space-y-1">
                                                    <template v-if="token.source_id">
                                                        <div class="flex items-center gap-2">
                                                            <span class="text-xs font-medium">{{ token.name }}</span>
                                                            <Badge variant="outline" class="px-1 py-0 text-[8px]">Official</Badge>
                                                        </div>
                                                        <div v-if="token.description" class="text-xs text-muted-foreground">{{ token.description }}</div>
                                                    </template>
                                                    <template v-else>
                                                        <Input v-model="token.name" placeholder="Token name (e.g. Fast, Shielded)" class="h-7 text-xs font-medium" />
                                                        <Textarea v-model="token.description" placeholder="Token effect description..." rows="2" class="text-xs" />
                                                    </template>
                                                </div>
                                                <button class="mt-1 text-muted-foreground hover:text-destructive" @click="removeToken(tIdx)"><Trash2 class="size-3.5" /></button>
                                            </div>
                                        </div>

                                        <!-- Markers -->
                                        <div>
                                            <div class="mb-2 flex items-center justify-between">
                                                <label class="text-xs font-medium text-muted-foreground">Markers</label>
                                                <Button variant="outline" size="sm" class="h-7 text-[11px]" @click="addMarker"><Plus class="mr-1 size-3" /> Custom</Button>
                                            </div>
                                            <div class="relative mb-2">
                                                <Input placeholder="Search official markers..." class="h-8 text-xs" @input="(e: Event) => searchMarkers((e.target as HTMLInputElement).value)" />
                                                <div v-if="markerSearchResults.length" class="absolute z-10 mt-1 w-full rounded-md border bg-popover p-1 shadow-md">
                                                    <button v-for="r in markerSearchResults" :key="r.id" class="w-full rounded px-2 py-1.5 text-left text-sm hover:bg-accent" @click="pickMarker(r)">
                                                        <span class="font-medium">{{ r.name }}</span>
                                                        <Badge variant="outline" class="ml-2 px-1 py-0 text-[8px]">Official</Badge>
                                                    </button>
                                                </div>
                                            </div>
                                            <div v-for="(marker, mIdx) in backMarkers" :key="'marker-' + mIdx" class="mb-2 flex items-start gap-2 rounded-lg border p-2">
                                                <div class="flex-1 space-y-1">
                                                    <template v-if="marker.source_id">
                                                        <div class="flex items-center gap-2">
                                                            <span class="text-xs font-medium">{{ marker.name }}</span>
                                                            <Badge variant="outline" class="px-1 py-0 text-[8px]">Official</Badge>
                                                        </div>
                                                        <div v-if="marker.description" class="text-xs text-muted-foreground">{{ marker.description }}</div>
                                                    </template>
                                                    <template v-else>
                                                        <Input v-model="marker.name" placeholder="Marker name" class="h-7 text-xs font-medium" />
                                                        <Textarea v-model="marker.description" placeholder="Marker description..." rows="2" class="text-xs" />
                                                    </template>
                                                </div>
                                                <button class="mt-1 text-muted-foreground hover:text-destructive" @click="removeMarker(mIdx)"><Trash2 class="size-3.5" /></button>
                                            </div>
                                        </div>
                                    </div>
                                </CollapsibleContent>
                            </CardContent>
                        </Card>
                    </Collapsible>

                    <!-- Notes -->
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

                <!-- Preview -->
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
                        <UpgradeCardRenderer
                            ref="cardRendererRef"
                            :name="form.name || domainLabel + ' Name'"
                            :domain="domain"
                            :faction="form.faction !== 'none' ? form.faction : null"
                            :upgrade-type="form.type || null"
                            :upgrade-type-label="upgradeTypeLabel"
                            :limitations="form.limitations || null"
                            :limitations-label="limitationsLabel"
                            :master-name="form.master_name || null"
                            :keyword-name="form.keyword_name || null"
                            :content-blocks="contentBlocks"
                            :back-tokens="backTokens"
                            :back-markers="backMarkers"
                        />
                    </div>
                </div>
            </div>
        </div>

        <!-- Save bar -->
        <div class="container mx-auto mt-4 px-4 lg:px-6">
            <div class="flex items-center justify-between rounded-lg border bg-muted/50 px-4 py-2.5">
                <div v-if="Object.keys(errors).length" class="text-xs text-destructive">Please fix validation errors above.</div>
                <div v-else class="text-xs text-muted-foreground">{{ isEdit ? 'Editing ' + (form.name || domainLabel.toLowerCase()) : `New ${domainLabel.toLowerCase()}` }}</div>
                <Button :disabled="saving || !form.name" size="sm" @click="save">
                    <Loader2 v-if="saving" class="mr-2 size-4 animate-spin" />
                    <Save v-else class="mr-2 size-4" />
                    {{ isEdit ? 'Save Changes' : `Create ${domainLabel}` }}
                </Button>
            </div>
        </div>
    </div>
</template>
