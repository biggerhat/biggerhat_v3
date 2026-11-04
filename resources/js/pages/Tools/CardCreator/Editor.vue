<script setup lang="ts">
import AbilitiesPanel from '@/components/CardCreator/AbilitiesPanel.vue';
import ActionsPanel from '@/components/CardCreator/ActionsPanel.vue';
import CardBackFace from '@/components/CardCreator/CardBackFace.vue';
import CardFrontFace from '@/components/CardCreator/CardFrontFace.vue';
import CardArtField from '@/components/CardCreator/CardArtField.vue';
import type { AbilityData, ActionData, EnumOption } from '@/components/CardCreator/types';
import CardRenderer from '@/components/CardCreator/CardRenderer.vue';
import { createComboImage, exportComboAsPdf, captureCardFaces, saveCardJson, tarotCardSize, triggerDownload } from '@/components/CardCreator/utils';
import FactionLogo from '@/components/FactionLogo.vue';
import PageBanner from '@/components/PageBanner.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { useCardImageEvents } from '@/composables/useCardImageEvents';
import { useTarotGrowToFit } from '@/composables/useTarotGrowToFit';
import { useEditorDraft } from '@/composables/useEditorDraft';
import { useCardJsonTransfer } from '@/composables/useCardJsonTransfer';
import { useLatestFetch } from '@/composables/useLatestFetch';
import { useToast } from '@/composables/useToast';
import { cacheBustedImagePath } from '@/lib/cacheBustedImage';
import { csrfToken } from '@/lib/utils';
import type { SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ArrowLeft, ChevronDown, Copy, Download, FileJson, FileText, Loader2, Eye, RefreshCw, Save, Share2, Upload, X } from 'lucide-vue-next';
import { computed, reactive, ref, watch } from 'vue';

interface KeywordData {
    id: number | null;
    name: string;
}

interface LinkedItem {
    source_type: 'official' | 'custom';
    id: number;
    name: string;
}

const toast = useToast();

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
        card_art_id: number | null;
        card_art_x: number;
        card_art_y: number;
        card_art_zoom: number;
        card_art_dx: number;
        card_art_dy: number;
        is_campaign_leader: boolean;
        is_campaign_totem: boolean;
        archetype: string | null;
        tag: string | null;
        actions: ActionData[] | null;
        abilities: AbilityData[] | null;
        keywords: KeywordData[] | null;
        characteristics: string[] | null;
        linked_crew_upgrades: LinkedItem[] | null;
        linked_totems: LinkedItem[] | null;
        notes: string | null;
        share_code: string;
        front_image?: string | null;
        combination_image?: string | null;
        card_image_generated_at?: string | null;
    } | null;
    enums: {
        factions: EnumOption[];
        stations: EnumOption[];
        bases: EnumOption[];
        suits: EnumOption[];
        action_types: EnumOption[];
        range_types: EnumOption[];
        defensive_ability_types: EnumOption[];
        tags: EnumOption[];
    };
    card_arts: Array<{ id: number; name: string; image_path: string; faction: string | null; category: string | null }>;
    campaign_back_url: string | null;
    card_image_pending?: boolean;
}>();

const isEdit = computed(() => !!props.character);
const isCampaignLeader = computed(() => props.character?.is_campaign_leader ?? false);
const isCampaignTotem = computed(() => props.character?.is_campaign_totem ?? false);
const isCampaignManaged = computed(() => isCampaignLeader.value || isCampaignTotem.value);

// Set once a save actually goes through this session — see the save handler
// below and ArsenalSheet.vue's poll_card handling.
const justSavedCardTarget = ref<'leader' | 'totem' | null>(null);
const campaignBackUrl = computed(() => {
    if (!props.campaign_back_url) return null;
    if (!justSavedCardTarget.value) return props.campaign_back_url;

    return `${props.campaign_back_url}?poll_card=${justSavedCardTarget.value}`;
});
// Saved card image: rendered server-side shortly after each save; the event swaps the status in when it lands.
const imagePending = computed(() => props.card_image_pending ?? false);
const imageUrl = computed(() =>
    props.character?.combination_image
        ? '/storage/' + cacheBustedImagePath(props.character.combination_image, props.character.card_image_generated_at)
        : null,
);
const rerendering = ref(false);
useCardImageEvents(usePage<SharedData>().props.auth.user?.id, (event) => {
    if (event.kind === 'custom_character' && event.id === props.character?.id) {
        router.reload({ only: ['character', 'card_image_pending'], preserveScroll: true });
    }
});
// Below lg the preview sits under the form; the save bar's Preview button jumps to it.
const scrollToPreview = () => document.getElementById('card-preview')?.scrollIntoView({ behavior: 'smooth', block: 'start' });

const rerender = async () => {
    if (!props.character || rerendering.value) return;
    rerendering.value = true;
    const res = await fetch(route('tools.card_creator.rerender', props.character.id), {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': csrfToken(), Accept: 'application/json' },
    });
    rerendering.value = false;
    if (!res.ok) {
        toast.error('Could not start the render — please try again in a moment.');
        return;
    }
    router.reload({ only: ['card_image_pending'], preserveScroll: true });
};

const formatSlug = (s: string) => s.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());

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
    summon_target_number: props.character?.summon_target_number ?? (null as number | null),
    generates_stone: props.character?.generates_stone ?? false,
    is_unhirable: props.character?.is_unhirable ?? false,
    card_art_id: props.character?.card_art_id ?? (null as number | null),
    card_art_x: props.character?.card_art_x ?? 50,
    card_art_y: props.character?.card_art_y ?? 50,
    card_art_zoom: props.character?.card_art_zoom ?? 100,
    card_art_dx: props.character?.card_art_dx ?? 0,
    card_art_dy: props.character?.card_art_dy ?? 0,
    notes: props.character?.notes ?? '',
    // Only meaningful (and only editable — see the template) for a Campaign
    // Leader; left as whatever it already is for every other character.
    tag: props.character?.tag ?? 'bruiser',
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

const { hasDraft, dirty, restoreDraft, discardDraft, markSaved, currentSnapshot, applySnapshot } = useEditorDraft({
    key: 'bh_draft_card',
    keepDraft: !props.character,
    snapshot: () => ({
        form: { ...form },
        actions: JSON.parse(JSON.stringify(actions)),
        abilities: JSON.parse(JSON.stringify(abilities)),
        keywords: JSON.parse(JSON.stringify(keywords)),
        characteristics: [...characteristics],
        linkedCrewUpgrades: JSON.parse(JSON.stringify(linkedCrewUpgrades)),
        linkedTotems: JSON.parse(JSON.stringify(linkedTotems)),
    }),
    restore: (d) => {
        // Drafts and imported files are only trusted for keys the form has and values of the right shape.
        for (const key of Object.keys(form) as (keyof typeof form)[]) {
            if (d.form && key in d.form) (form as Record<string, unknown>)[key] = d.form[key];
        }
        const list = <T,>(v: unknown): T[] => (Array.isArray(v) ? (v as T[]) : []);
        actions.splice(0, actions.length, ...list<ActionData>(d.actions));
        abilities.splice(0, abilities.length, ...list<AbilityData>(d.abilities));
        keywords.splice(0, keywords.length, ...list<KeywordData>(d.keywords));
        characteristics.splice(0, characteristics.length, ...list<string>(d.characteristics));
        linkedCrewUpgrades.splice(0, linkedCrewUpgrades.length, ...list<LinkedItem>(d.linkedCrewUpgrades));
        linkedTotems.splice(0, linkedTotems.length, ...list<LinkedItem>(d.linkedTotems));
    },
});

const { exportJson, importFile } = useCardJsonTransfer({
    kind: 'custom-character',
    snapshot: currentSnapshot,
    apply: applySnapshot,
    // Private notes stay home, and links to the owner's own custom cards mean nothing to anyone else.
    scrub: (d) => ({
        ...d,
        form: { ...d.form, notes: '' },
        linkedCrewUpgrades: d.linkedCrewUpgrades.filter((l: LinkedItem) => l.source_type === 'official'),
        linkedTotems: d.linkedTotems.filter((l: LinkedItem) => l.source_type === 'official'),
    }),
    filename: () => form.name,
});

const fetchLatest = useLatestFetch();

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
        const data = await fetchLatest<any>('clone', route('api.card-creator.characters') + '?q=' + encodeURIComponent(q));
        if (data) cloneSearchResults.value = data.filter((r: any) => r.source_type === 'official');
    }, 300);
};

const cloneFromCharacter = async (charId: number) => {
    cloneLoading.value = true;
    cloneSearchResults.value = [];
    cloneSearchQuery.value = '';
    try {
        const res = await fetch(route('api.card-creator.character-detail', charId), { headers: { Accept: 'application/json' } });
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
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
        toast.error('Could not load that character', { description: 'Try again in a moment.' });
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
const exportFrontRef = ref<InstanceType<typeof CardFrontFace> | null>(null);
const exportBackRef = ref<InstanceType<typeof CardBackFace> | null>(null);
const printFrontRef = ref<InstanceType<typeof CardFrontFace> | null>(null);
const printBackRef = ref<InstanceType<typeof CardBackFace> | null>(null);
const exporting = ref(false);
const exportingPdf = ref(false);

// ─── Library art picker ───
const selectedArt = computed(() => props.card_arts.find((a) => a.id === form.card_art_id) ?? null);
// An uploaded file still wins for the live preview (it's the player's own try-out); otherwise the chosen library art.
// Framing belongs to the library art; a try-out upload is always shown centred.
const artFocus = computed(() =>
    characterImagePreview.value
        ? { x: 50, y: 50, zoom: 100 }
        : { x: form.card_art_x, y: form.card_art_y, zoom: form.card_art_zoom, dx: form.card_art_dx, dy: form.card_art_dy },
);
const characterImageSrc = computed(() => characterImagePreview.value ?? selectedArt.value?.image_path ?? null);

// The live preview (CardRenderer.vue) sits in this page's 1/3-width column,
// which routinely clamps its box below every tarotCardSize tier — there's no
// room for it to grow into, so it falls back to shrinkToFit (shrinking text
// to fit whatever width the column allows) rather than growing. Exports must
// not inherit that constraint, so both PNG and PDF export capture from their
// own hidden, off-screen, unconstrained instances instead of the live
// preview — these grow-to-fit measures on demand right before each export,
// not continuously, since they're never shown on screen.
const {
    width: exportWidth,
    height: exportHeight,
    measure: measureExportSize,
} = useTarotGrowToFit(
    () => exportFrontRef.value?.rootRef,
    () => exportBackRef.value?.rootRef,
    () => tarotCardSize(abilities.reduce((sum, a) => sum + (a.description?.length ?? 0) + a.name.length, 0)).width,
);
const {
    width: printWidth,
    height: printHeight,
    measure: measurePrintSize,
} = useTarotGrowToFit(
    () => printFrontRef.value?.rootRef,
    () => printBackRef.value?.rootRef,
    () => tarotCardSize(abilities.reduce((sum, a) => sum + (a.description?.length ?? 0) + a.name.length, 0)).width,
);

const exportImages = async () => {
    exporting.value = true;
    try {
        await measureExportSize();
        const captured = await captureCardFaces(exportFrontRef.value?.rootRef, exportBackRef.value?.rootRef);
        if (!captured) return;
        const comboData = await createComboImage(captured.frontData, captured.backData);
        triggerDownload(comboData, `${form.name || 'card'}.png`);
    } catch (e) {
        console.error('Image export failed:', e);
        toast.error('Image export failed', { description: e instanceof Error ? e.message : 'Try again.' });
    } finally {
        exporting.value = false;
    }
};

// PDF export captures the hidden print-mode faces (light theme + bottom Hp
// line — see printFrontRef/printBackRef in the template), same shape as
// exportImages() above but from the print instances instead of the export
// instances.
const exportPdf = async () => {
    exportingPdf.value = true;
    try {
        await measurePrintSize();
        const captured = await captureCardFaces(printFrontRef.value?.rootRef, printBackRef.value?.rootRef);
        if (!captured) return;
        await exportComboAsPdf(captured.frontData, captured.backData, captured.frontEl, captured.backEl, `${form.name || 'card'}.pdf`);
    } catch (e) {
        console.error('PDF export failed:', e);
        toast.error('PDF export failed', { description: e instanceof Error ? e.message : 'Try again.' });
    } finally {
        exportingPdf.value = false;
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
        tag: isCampaignLeader.value ? form.tag : null,
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

    const url = isEdit.value ? route('tools.card_creator.update', props.character!.id) : route('tools.card_creator.store');

    const result = await saveCardJson(url, isEdit.value ? 'PUT' : 'POST', body, csrfToken());
    const data = result.data;

    if (!result.ok) {
        errors.value = result.errors;
        if (result.message) toast.error('Not saved', { description: result.message });
        saving.value = false;
        return;
    }

    // The Leader/Totem card image regenerates in a queued job, not
    // synchronously — flag it so "Back to Arsenal Sheet" can ask that page
    // to poll for the finished image instead of showing whatever was
    // already on disk from before this save (see ArsenalSheet.vue's
    // poll_card handling).
    if (isCampaignManaged.value) {
        justSavedCardTarget.value = isCampaignTotem.value ? 'totem' : 'leader';
    }

    markSaved();

    if (data.redirect) {
        router.visit(data.redirect);
        return;
    }

    // Stay on the editor: pick up the "rendering" state the save just triggered.
    router.reload({ only: ['character', 'card_image_pending'], preserveScroll: true });

    saving.value = false;
};

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

// Keyword search results (the dropdown under the Keywords input).
const searchResults = ref<any[]>([]);
const searchType = ref<'keyword' | null>(null);
let searchDebounce: ReturnType<typeof setTimeout>;

const searchKeywords = (q: string) => {
    clearTimeout(searchDebounce);
    if (q.length < 2) {
        searchResults.value = [];
        searchType.value = null;
        return;
    }
    searchType.value = 'keyword';
    searchDebounce = setTimeout(async () => {
        const data = await fetchLatest<any>('official', route('api.card-creator.keywords') + '?q=' + encodeURIComponent(q));
        if (data) searchResults.value = data;
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
        const data = await fetchLatest<any>('upgrade', route('api.card-creator.crew-upgrades') + '?q=' + encodeURIComponent(q));
        if (data) upgradeSearchResults.value = data;
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
        const data = await fetchLatest<any>('totem', route('api.card-creator.characters') + '?q=' + encodeURIComponent(q));
        if (data) totemSearchResults.value = data;
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

        <div v-if="hasDraft" class="container mx-auto mt-4 px-4 lg:px-6">
            <div class="flex flex-wrap items-center gap-3 rounded-lg border border-primary/40 bg-primary/10 px-4 py-3 text-sm">
                <span class="min-w-0 flex-1">You have an unsaved draft from earlier. Restore it?</span>
                <Button size="sm" @click="restoreDraft">Restore draft</Button>
                <Button size="sm" variant="ghost" @click="discardDraft">Discard</Button>
            </div>
        </div>

        <div class="container mx-auto mt-6 px-4 lg:px-6">
            <div class="mb-4 flex gap-2">
                <Link v-if="campaignBackUrl" :href="campaignBackUrl">
                    <Button variant="ghost" size="sm"><ArrowLeft class="mr-1 size-4" /> Back to Arsenal Sheet</Button>
                </Link>
                <Link :href="route('tools.card_creator.index')">
                    <Button variant="ghost" size="sm"><ArrowLeft class="mr-1 size-4" /> Back to List</Button>
                </Link>
                <div class="ml-auto flex gap-2">
                    <label v-if="!isEdit" class="inline-flex h-8 cursor-pointer items-center gap-1.5 rounded-md px-3 text-sm hover:bg-accent">
                        <Upload class="size-4" /> Import JSON
                        <input type="file" accept="application/json,.json" class="sr-only" @change="importFile" />
                    </label>
                    <Button variant="ghost" size="sm" @click="exportJson"><FileJson class="mr-1 size-4" /> Export JSON</Button>
                </div>
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
                            <div
                                v-if="cloneSearchResults.length"
                                class="absolute z-10 mt-1 max-h-48 w-full overflow-auto rounded-md border bg-popover p-1 shadow-md"
                            >
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
                        <p class="mt-1 text-2xs text-muted-foreground">
                            Populates all fields from an official character. You can edit everything after cloning.
                        </p>
                    </div>

                    <!-- ═══ IDENTITY + ART ═══ -->
                    <Card>
                        <CardContent class="space-y-4 p-4">
                            <h2 class="text-sm font-semibold">Identity</h2>
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
                            <div
                                class="flex items-center gap-2 text-[11px]"
                                :class="displayNameLength > NAME_LIMIT ? 'text-destructive' : 'text-muted-foreground'"
                            >
                                <span>{{ displayNameLength }}/{{ NAME_LIMIT }} characters</span>
                                <span v-if="displayNameLength > NAME_LIMIT">— name may not fit on card</span>
                            </div>
                            <!-- Campaign leader/totem locked-field notice -->
                            <div
                                v-if="isCampaignLeader"
                                class="mb-3 rounded-md border border-amber-500/40 bg-amber-500/10 px-3 py-2 text-xs text-amber-700 dark:text-amber-400"
                            >
                                <strong>Campaign Leader</strong> — faction, keywords, and archetype are locked. Edit name, actions, abilities, and tag
                                freely.
                                <div class="mt-1.5 flex flex-wrap items-center gap-3 text-[11px] text-muted-foreground">
                                    <span v-if="character?.archetype"><strong>Archetype:</strong> {{ formatSlug(character.archetype) }}</span>
                                    <span class="flex items-center gap-1.5">
                                        <strong>Tag:</strong>
                                        <Select v-model="form.tag">
                                            <SelectTrigger class="h-7 w-auto gap-1 text-[11px] text-foreground">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem v-for="t in enums.tags" :key="t.value" :value="t.value">{{ t.name }}</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </span>
                                </div>
                            </div>
                            <div
                                v-else-if="isCampaignTotem"
                                class="mb-3 rounded-md border border-amber-500/40 bg-amber-500/10 px-3 py-2 text-xs text-amber-700 dark:text-amber-400"
                            >
                                <strong>Campaign Totem</strong> — faction and keywords are locked to match your Leader. Edit name, actions, and
                                abilities freely.
                            </div>

                            <div class="grid gap-3 sm:grid-cols-3">
                                <div>
                                    <label class="mb-1 block text-xs text-muted-foreground">Faction *</label>
                                    <Select v-model="form.faction" :disabled="isCampaignManaged">
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
                                            <SelectItem
                                                v-for="f in enums.factions.filter((f) => f.value !== form.faction)"
                                                :key="'2f-' + f.value"
                                                :value="f.value"
                                            >
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

                            <!-- Character Art (library) -->
                            <CardArtField
                                v-model:art-id="form.card_art_id"
                                v-model:x="form.card_art_x"
                                v-model:y="form.card_art_y"
                                v-model:zoom="form.card_art_zoom"
                                v-model:dx="form.card_art_dx"
                                v-model:dy="form.card_art_dy"
                                :card-arts="card_arts"
                                :faction="form.faction"
                                :show-positioner="!characterImagePreview"
                                class="border-t pt-3"
                            >
                                <details class="mt-2 text-xs text-muted-foreground">
                                    <summary class="cursor-pointer">Try your own image (preview only)</summary>
                                    <div class="mt-1.5 space-y-1.5">
                                        <div v-if="characterImagePreview" class="flex items-center gap-2">
                                            <img
                                                :src="characterImagePreview"
                                                alt="Your uploaded preview"
                                                class="h-12 w-16 rounded border object-cover"
                                            />
                                            <button type="button" class="underline" @click="removeImage">Remove</button>
                                        </div>
                                        <input
                                            v-else
                                            type="file"
                                            accept="image/jpeg,image/png,image/webp"
                                            class="text-xs"
                                            @change="onImageSelected"
                                        />
                                        <p class="text-2xs">
                                            Your own image is never saved and won't appear on exports from a later visit or on the public share link.
                                            Pick library art to keep it on the card.
                                        </p>
                                    </div>
                                </details>
                            </CardArtField>
                        </CardContent>
                    </Card>

                    <!-- ═══ STATS ═══ -->
                    <Card>
                        <CardContent class="space-y-3 p-4">
                            <h2 class="text-sm font-semibold">Stats</h2>
                            <!-- Row 1: Core -->
                            <div class="grid grid-cols-4 gap-2">
                                <div>
                                    <label class="mb-1 block text-2xs uppercase tracking-wider text-muted-foreground">Cost</label>
                                    <Input v-model.number="form.cost" type="number" min="0" max="99" class="h-8" />
                                </div>
                                <div>
                                    <label class="mb-1 block text-2xs uppercase tracking-wider text-muted-foreground">Health *</label>
                                    <Input v-model.number="form.health" type="number" min="1" max="99" class="h-8" />
                                </div>
                                <div>
                                    <label class="mb-1 block text-2xs uppercase tracking-wider text-muted-foreground">Df</label>
                                    <Input v-model.number="form.defense" type="number" min="0" max="20" class="h-8" />
                                </div>
                                <div>
                                    <label class="mb-1 block text-2xs uppercase tracking-wider text-muted-foreground">Wp</label>
                                    <Input v-model.number="form.willpower" type="number" min="0" max="20" class="h-8" />
                                </div>
                            </div>
                            <!-- Row 2: Physical -->
                            <div class="grid grid-cols-5 gap-2">
                                <div>
                                    <label class="mb-1 block text-2xs uppercase tracking-wider text-muted-foreground">Mv</label>
                                    <Input v-model.number="form.speed" type="number" min="0" max="20" class="h-8" />
                                </div>
                                <div>
                                    <label class="mb-1 block text-2xs uppercase tracking-wider text-muted-foreground">Sz</label>
                                    <Input v-model.number="form.size" type="number" min="0" max="10" class="h-8" />
                                </div>
                                <div>
                                    <label class="mb-1 block text-2xs uppercase tracking-wider text-muted-foreground">Base</label>
                                    <Select v-model="form.base">
                                        <SelectTrigger class="h-8"><SelectValue /></SelectTrigger>
                                        <SelectContent>
                                            <SelectItem v-for="b in enums.bases" :key="b.value" :value="b.value">{{ b.name }}</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div>
                                    <label class="mb-1 block text-2xs uppercase tracking-wider text-muted-foreground">Count</label>
                                    <Input v-model.number="form.count" type="number" min="1" max="10" class="h-8" />
                                </div>
                                <div>
                                    <label class="mb-1 block text-2xs uppercase tracking-wider text-muted-foreground">Summon</label>
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
                            <h2 class="text-sm font-semibold">Details</h2>

                            <!-- Keywords -->
                            <div>
                                <label class="mb-1.5 block text-xs font-medium text-muted-foreground">Keywords</label>
                                <div v-if="keywords.length" class="mb-2 flex flex-wrap gap-1.5">
                                    <Badge v-for="(kw, i) in keywords" :key="'kw-' + i" variant="secondary" class="gap-1">
                                        {{ kw.name }}
                                        <Badge v-if="kw.id" variant="outline" class="ml-0.5 px-1 py-0 text-2xs">Official</Badge>
                                        <button
                                            aria-label="Remove keyword"
                                            v-if="!isCampaignManaged"
                                            class="ml-0.5 hover:text-destructive"
                                            @click="removeKeyword(i)"
                                        >
                                            <X class="size-3" />
                                        </button>
                                    </Badge>
                                </div>
                                <div v-if="!isCampaignManaged" class="relative">
                                    <Input
                                        v-model="newKeyword"
                                        placeholder="Search or type a keyword..."
                                        class="h-8 text-xs"
                                        @input="searchKeywords(newKeyword)"
                                        @keydown.enter.prevent="addKeyword()"
                                    />
                                    <div
                                        v-if="searchType === 'keyword' && searchResults.length"
                                        class="absolute z-10 mt-1 w-full rounded-md border bg-popover p-1 shadow-md"
                                    >
                                        <button
                                            v-for="r in searchResults"
                                            :key="r.id"
                                            class="flex w-full items-center gap-2 rounded px-2 py-1.5 text-left text-sm hover:bg-accent"
                                            @click="pickKeyword(r)"
                                        >
                                            {{ r.name }}
                                            <Badge variant="outline" class="px-1 py-0 text-2xs">Official</Badge>
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
                                        <button
                                            aria-label="Remove characteristic"
                                            class="ml-0.5 hover:text-destructive"
                                            @click="removeCharacteristic(i)"
                                        >
                                            <X class="size-3" />
                                        </button>
                                    </Badge>
                                </div>
                                <Input
                                    v-model="newCharacteristic"
                                    placeholder="Type a characteristic and press Enter"
                                    class="h-8 text-xs"
                                    @keydown.enter.prevent="addCharacteristic()"
                                />
                            </div>

                            <!-- Crew Upgrades -->
                            <div>
                                <label class="mb-1.5 block text-xs font-medium text-muted-foreground">Crew Upgrades</label>
                                <div v-if="linkedCrewUpgrades.length" class="mb-2 flex flex-wrap gap-1.5">
                                    <Badge v-for="(u, i) in linkedCrewUpgrades" :key="'cu-' + i" variant="secondary" class="gap-1">
                                        {{ u.name }}
                                        <Badge v-if="u.source_type === 'official'" variant="outline" class="ml-0.5 px-1 py-0 text-2xs"
                                            >Official</Badge
                                        >
                                        <Badge v-else variant="outline" class="ml-0.5 border-purple-400 px-1 py-0 text-2xs text-purple-500"
                                            >Custom</Badge
                                        >
                                        <button aria-label="Remove crew upgrade" class="ml-0.5 hover:text-destructive" @click="removeCrewUpgrade(i)">
                                            <X class="size-3" />
                                        </button>
                                    </Badge>
                                </div>
                                <div class="relative">
                                    <Input
                                        v-model="upgradeSearchQuery"
                                        placeholder="Search crew upgrades..."
                                        class="h-8 text-xs"
                                        @input="searchCrewUpgrades(upgradeSearchQuery)"
                                    />
                                    <div
                                        v-if="upgradeSearchResults.length"
                                        class="absolute z-10 mt-1 w-full rounded-md border bg-popover p-1 shadow-md"
                                    >
                                        <button
                                            v-for="r in upgradeSearchResults"
                                            :key="r.source_type + '-' + r.id"
                                            class="flex w-full items-center gap-2 rounded px-2 py-1.5 text-left text-sm hover:bg-accent"
                                            @click="pickCrewUpgrade(r)"
                                        >
                                            {{ r.name }}
                                            <Badge v-if="r.source_type === 'official'" variant="outline" class="px-1 py-0 text-2xs">Official</Badge>
                                            <Badge v-else variant="outline" class="border-purple-400 px-1 py-0 text-2xs text-purple-500"
                                                >Custom</Badge
                                            >
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
                                        <Badge v-if="t.source_type === 'official'" variant="outline" class="ml-0.5 px-1 py-0 text-2xs"
                                            >Official</Badge
                                        >
                                        <Badge v-else variant="outline" class="ml-0.5 border-purple-400 px-1 py-0 text-2xs text-purple-500"
                                            >Custom</Badge
                                        >
                                        <button aria-label="Remove totem" class="ml-0.5 hover:text-destructive" @click="removeTotem(i)">
                                            <X class="size-3" />
                                        </button>
                                    </Badge>
                                </div>
                                <div class="relative">
                                    <Input
                                        v-model="totemSearchQuery"
                                        placeholder="Search characters for totems..."
                                        class="h-8 text-xs"
                                        @input="searchTotems(totemSearchQuery)"
                                    />
                                    <div
                                        v-if="totemSearchResults.length"
                                        class="absolute z-10 mt-1 w-full rounded-md border bg-popover p-1 shadow-md"
                                    >
                                        <button
                                            v-for="r in totemSearchResults"
                                            :key="r.source_type + '-' + r.id"
                                            class="flex w-full items-center gap-2 rounded px-2 py-1.5 text-left text-sm hover:bg-accent"
                                            @click="pickTotem(r)"
                                        >
                                            {{ r.name }}
                                            <Badge v-if="r.source_type === 'official'" variant="outline" class="px-1 py-0 text-2xs">Official</Badge>
                                            <Badge v-else variant="outline" class="border-purple-400 px-1 py-0 text-2xs text-purple-500"
                                                >Custom</Badge
                                            >
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <AbilitiesPanel v-model="abilities" :enums="enums" />
                    <ActionsPanel v-model="actions" :enums="enums" />

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
                <!-- Below lg the preview follows the form (it is a screen-tall card, and leading with it pushed the
                     fields out of view); the save bar's Preview button jumps to it. Side by side at desktop. -->
                <div id="card-preview" class="scroll-mt-4 lg:col-span-1">
                    <div class="sticky top-4 space-y-3">
                        <div class="flex items-center justify-between">
                            <h3 class="text-sm font-semibold">Preview</h3>
                            <div class="flex gap-2">
                                <Button variant="outline" size="sm" class="text-xs" :disabled="exporting || !form.name" @click="exportImages">
                                    <Loader2 v-if="exporting" class="mr-1 size-3 animate-spin" />
                                    <Download v-else class="mr-1 size-3" />
                                    {{ exporting ? 'Exporting...' : 'Export Image' }}
                                </Button>
                                <Button variant="outline" size="sm" class="text-xs" :disabled="exportingPdf || !form.name" @click="exportPdf">
                                    <Loader2 v-if="exportingPdf" class="mr-1 size-3 animate-spin" />
                                    <FileText v-else class="mr-1 size-3" />
                                    {{ exportingPdf ? 'Exporting...' : 'Export PDF' }}
                                </Button>
                            </div>
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
                            :character-image="characterImageSrc"
                            :character-image-focus="artFocus"
                            :actions="actions"
                            :abilities="abilities"
                            :linked-crew-upgrades="linkedCrewUpgrades"
                            :linked-totems="linkedTotems"
                        />

                        <!-- Hidden export-only instance (dark theme, matching the live
                             preview) — exportImages() captures these instead of the live
                             preview above, which is width-clamped by this page's 1/3
                             column and can't reliably grow to fit dense content (see
                             CardRenderer.vue's doc comment). Fixed off-screen, unconstrained
                             by any layout, sized via grow-to-fit measured on demand right
                             before export — see measureExportSize in exportImages(). -->
                        <div
                            class="pointer-events-none fixed left-[-9999px] top-0"
                            :style="{ width: exportWidth + 'px', height: exportHeight + 'px' }"
                        >
                            <CardFrontFace
                                ref="exportFrontRef"
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
                                :character-image="characterImageSrc"
                                :character-image-focus="artFocus"
                                :abilities="abilities"
                                :linked-crew-upgrades="linkedCrewUpgrades"
                                :linked-totems="linkedTotems"
                                :card-min-height="exportHeight"
                            />
                        </div>
                        <div
                            class="pointer-events-none fixed left-[-9999px] top-0"
                            :style="{ width: exportWidth + 'px', height: exportHeight + 'px' }"
                        >
                            <CardBackFace
                                ref="exportBackRef"
                                :name="form.name || 'Character Name'"
                                :title="form.title || null"
                                :faction="form.faction !== 'none' ? form.faction : null"
                                :second-faction="form.second_faction !== 'none' ? form.second_faction : null"
                                :actions="actions"
                                :abilities="abilities"
                                :card-min-height="exportHeight"
                            />
                        </div>

                        <!-- Hidden print-only instance (light theme + bottom Hp line,
                             see printMode) — exportPdf() captures these instead of the
                             live dark preview above. Fixed off-screen position so this
                             doesn't affect page layout/scroll. Sized via the same
                             grow-to-fit measurement (printWidth/printHeight, measured on
                             demand right before export — see measurePrintSize in
                             exportPdf()) rather than a fixed 550x950 box, which clipped
                             dense cards. -->
                        <div class="pointer-events-none fixed left-[-9999px] top-0" :style="{ width: printWidth + 'px', height: printHeight + 'px' }">
                            <CardFrontFace
                                ref="printFrontRef"
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
                                :character-image="characterImageSrc"
                                :character-image-focus="artFocus"
                                :abilities="abilities"
                                :linked-crew-upgrades="linkedCrewUpgrades"
                                :linked-totems="linkedTotems"
                                :card-min-height="printHeight"
                                print-mode
                            />
                        </div>
                        <div class="pointer-events-none fixed left-[-9999px] top-0" :style="{ width: printWidth + 'px', height: printHeight + 'px' }">
                            <CardBackFace
                                ref="printBackRef"
                                :name="form.name || 'Character Name'"
                                :title="form.title || null"
                                :faction="form.faction !== 'none' ? form.faction : null"
                                :second-faction="form.second_faction !== 'none' ? form.second_faction : null"
                                :actions="actions"
                                :abilities="abilities"
                                :card-min-height="printHeight"
                                print-mode
                            />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ═══ SAVE BAR ═══ -->
        <!-- Sticky so Save is always in reach on this long form; shows the saved card image's state when editing. -->
        <div
            class="sticky bottom-[var(--tabbar-h,0px)] z-20 mt-4 border-t bg-background/95 py-2 backdrop-blur supports-[backdrop-filter]:bg-background/80"
        >
            <div class="container mx-auto flex flex-wrap items-center justify-between gap-2 px-4 lg:px-6">
                <div class="min-w-0 text-xs" aria-live="polite">
                    <div
                        v-if="dirty && !Object.keys(errors).length"
                        class="mb-0.5 flex items-center gap-1.5 font-medium text-amber-700 dark:text-amber-400"
                    >
                        <span class="size-1.5 rounded-full bg-current" aria-hidden="true" /> Unsaved changes
                    </div>
                    <div v-if="Object.keys(errors).length" class="text-destructive">Please fix validation errors above.</div>
                    <div v-else-if="!isEdit" class="text-muted-foreground">A card image is rendered after you create it.</div>
                    <div v-else class="flex flex-wrap items-center gap-x-3 gap-y-1 text-muted-foreground">
                        <span class="truncate">Editing {{ form.name || 'character' }}</span>
                        <span v-if="imagePending || rerendering" class="inline-flex items-center gap-1 text-foreground">
                            <Loader2 class="size-3 animate-spin" /> Rendering card image…
                        </span>
                        <template v-else-if="imageUrl">
                            <a :href="imageUrl" :download="`${form.name || 'card'}.png`" class="inline-flex items-center gap-1 hover:text-foreground">
                                <Download class="size-3" /> Card image
                            </a>
                            <Link
                                :href="route('tools.card_creator.share', character!.share_code)"
                                class="inline-flex items-center gap-1 hover:text-foreground"
                            >
                                <Share2 class="size-3" /> Share page
                            </Link>
                        </template>
                        <span v-else>No card image yet.</span>
                        <button
                            v-if="!imagePending && !rerendering && !dirty"
                            type="button"
                            class="inline-flex items-center gap-1 underline-offset-2 hover:text-foreground hover:underline"
                            @click="rerender"
                        >
                            <RefreshCw class="size-3" /> Re-render
                        </button>
                    </div>
                </div>
                <Button variant="outline" size="sm" class="ml-auto lg:hidden" @click="scrollToPreview"><Eye class="mr-1.5 size-4" /> Preview</Button>
                <Button :disabled="saving || !form.name || form.faction === 'none'" size="sm" @click="save">
                    <Loader2 v-if="saving" class="mr-2 size-4 animate-spin" />
                    <Save v-else class="mr-2 size-4" />
                    {{ isEdit ? 'Save Changes' : 'Create Character' }}
                </Button>
            </div>
        </div>
    </div>
</template>
