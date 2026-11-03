<script setup lang="ts">
import AbilityLookupModal from '@/components/CardCreator/AbilityLookupModal.vue';
import ActionLookupModal from '@/components/CardCreator/ActionLookupModal.vue';
import CardBackFace from '@/components/CardCreator/CardBackFace.vue';
import CardFrontFace from '@/components/CardCreator/CardFrontFace.vue';
import ArtPositioner from '@/components/CardCreator/ArtPositioner.vue';
import CardRenderer from '@/components/CardCreator/CardRenderer.vue';
import IconTokenPalette from '@/components/CardCreator/IconTokenPalette.vue';
import { createComboImage, exportComboAsPdf, captureCardFaces, saveCardJson, tarotCardSize, triggerDownload } from '@/components/CardCreator/utils';
import FactionLogo from '@/components/FactionLogo.vue';
import PageBanner from '@/components/PageBanner.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { useCardImageEvents } from '@/composables/useCardImageEvents';
import { useTarotGrowToFit } from '@/composables/useTarotGrowToFit';
import { useEditorDraft } from '@/composables/useEditorDraft';
import { useToast } from '@/composables/useToast';
import { cacheBustedImagePath } from '@/lib/cacheBustedImage';
import { csrfToken } from '@/lib/utils';
import type { SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ArrowLeft, ChevronDown, Copy, Download, FileText, ImagePlus, Loader2, Plus, RefreshCw, Save, Share2, Trash2, X } from 'lucide-vue-next';
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
    source_character_id?: number | null;
    triggers: TriggerData[];
}

interface AbilityData {
    name: string;
    suits: string | null;
    defensive_ability_type: string | null;
    costs_stone: boolean;
    description: string | null;
    source_id: number | null;
    source_character_id?: number | null;
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

const { hasDraft, restoreDraft, discardDraft, markSaved } = useEditorDraft({
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
        Object.assign(form, d.form);
        actions.splice(0, actions.length, ...d.actions);
        abilities.splice(0, abilities.length, ...d.abilities);
        keywords.splice(0, keywords.length, ...d.keywords);
        characteristics.splice(0, characteristics.length, ...d.characteristics);
        linkedCrewUpgrades.splice(0, linkedCrewUpgrades.length, ...d.linkedCrewUpgrades);
        linkedTotems.splice(0, linkedTotems.length, ...d.linkedTotems);
    },
});

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
    characterImagePreview.value ? { x: 50, y: 50, zoom: 100 } : { x: form.card_art_x, y: form.card_art_y, zoom: form.card_art_zoom },
);
const characterImageSrc = computed(() => characterImagePreview.value ?? selectedArt.value?.image_path ?? null);

const artPickerOpen = ref(false);
const artSearch = ref('');
const artCategory = ref<string | null>(null);
const artFactionOnly = ref(true);
const artCategories = computed(() => [...new Set(props.card_arts.map((a) => a.category).filter((c): c is string => !!c))].sort());
const visibleArts = computed(() => {
    const q = artSearch.value.trim().toLowerCase();
    return props.card_arts.filter((a) => {
        if (artFactionOnly.value && form.faction !== 'none' && a.faction && a.faction !== form.faction) return false;
        if (artCategory.value && a.category !== artCategory.value) return false;
        return !q || a.name.toLowerCase().includes(q);
    });
});
const pickArt = (id: number | null) => {
    // New art starts centred and unzoomed; re-picking the same piece keeps the framing the player set.
    if (id !== form.card_art_id) {
        form.card_art_x = 50;
        form.card_art_y = 50;
        form.card_art_zoom = 100;
    }
    form.card_art_id = id;
    artPickerOpen.value = false;
};

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

// ───────── Browse modals (mirrors Leader Builder's Action/Ability Lookup
// Modal, T3-32, but without keyword/cost-cap constraints — any homebrew
// card can borrow any official action/ability as a starting point). ─────────
const actionModalOpen = ref(false);
const abilityModalOpen = ref(false);

// Same trigger-stripping as pickAction above — triggers are added separately
// via the per-action trigger search so they aren't tied to a single source.
const commitActionsFromModal = (picked: ActionData[]) => {
    for (const a of picked) actions.push({ ...a, triggers: [] });
};

const commitAbilitiesFromModal = (picked: AbilityData[]) => {
    for (const a of picked) abilities.push({ ...a });
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
                        <p class="mt-1 text-[10px] text-muted-foreground">
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
                            <div class="border-t pt-3">
                                <label class="mb-1.5 block text-xs text-muted-foreground">Character Art</label>
                                <div
                                    v-if="selectedArt"
                                    class="flex items-center justify-between gap-2 rounded-md border bg-muted/30 px-3 py-1.5 text-xs"
                                >
                                    <span class="truncate font-medium">{{ selectedArt.name }}</span>
                                    <span class="flex shrink-0 gap-1">
                                        <Button type="button" variant="ghost" size="sm" class="h-6 px-2 text-xs" @click="artPickerOpen = true"
                                            >Change</Button
                                        >
                                        <Button type="button" variant="ghost" size="sm" class="h-6 px-2 text-xs" @click="pickArt(null)"
                                            >Remove</Button
                                        >
                                    </span>
                                </div>
                                <button
                                    v-else
                                    type="button"
                                    class="flex w-full items-center gap-3 rounded-lg border-2 border-dashed border-muted-foreground/25 px-4 py-3 text-left transition-colors hover:border-muted-foreground/50 hover:bg-muted/50"
                                    @click="artPickerOpen = true"
                                >
                                    <ImagePlus class="size-5 shrink-0 text-muted-foreground" />
                                    <span class="text-xs font-medium">Choose art from the library</span>
                                    <span class="ml-auto text-xs text-muted-foreground">{{ card_arts.length }} available</span>
                                </button>
                                <ArtPositioner
                                    v-if="selectedArt && !characterImagePreview"
                                    v-model:x="form.card_art_x"
                                    v-model:y="form.card_art_y"
                                    v-model:zoom="form.card_art_zoom"
                                    :src="selectedArt.image_path"
                                    :alt="selectedArt.name"
                                    class="mt-2"
                                />
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
                                        <p class="text-[10px]">
                                            Your own image is never saved and won't appear on exports from a later visit or on the public share link.
                                            Pick library art to keep it on the card.
                                        </p>
                                    </div>
                                </details>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- ═══ STATS ═══ -->
                    <Card>
                        <CardContent class="space-y-3 p-4">
                            <h2 class="text-sm font-semibold">Stats</h2>
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
                            <h2 class="text-sm font-semibold">Details</h2>

                            <!-- Keywords -->
                            <div>
                                <label class="mb-1.5 block text-xs font-medium text-muted-foreground">Keywords</label>
                                <div v-if="keywords.length" class="mb-2 flex flex-wrap gap-1.5">
                                    <Badge v-for="(kw, i) in keywords" :key="'kw-' + i" variant="secondary" class="gap-1">
                                        {{ kw.name }}
                                        <Badge v-if="kw.id" variant="outline" class="ml-0.5 px-1 py-0 text-[10px]">Official</Badge>
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
                                            <Badge variant="outline" class="px-1 py-0 text-[10px]">Official</Badge>
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
                                        <Badge v-if="u.source_type === 'official'" variant="outline" class="ml-0.5 px-1 py-0 text-[10px]"
                                            >Official</Badge
                                        >
                                        <Badge v-else variant="outline" class="ml-0.5 border-purple-400 px-1 py-0 text-[10px] text-purple-500"
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
                                            <Badge v-if="r.source_type === 'official'" variant="outline" class="px-1 py-0 text-[10px]"
                                                >Official</Badge
                                            >
                                            <Badge v-else variant="outline" class="border-purple-400 px-1 py-0 text-[10px] text-purple-500"
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
                                        <Badge v-if="t.source_type === 'official'" variant="outline" class="ml-0.5 px-1 py-0 text-[10px]"
                                            >Official</Badge
                                        >
                                        <Badge v-else variant="outline" class="ml-0.5 border-purple-400 px-1 py-0 text-[10px] text-purple-500"
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
                                            <Badge v-if="r.source_type === 'official'" variant="outline" class="px-1 py-0 text-[10px]"
                                                >Official</Badge
                                            >
                                            <Badge v-else variant="outline" class="border-purple-400 px-1 py-0 text-[10px] text-purple-500"
                                                >Custom</Badge
                                            >
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
                                                <Input
                                                    placeholder="Search official abilities..."
                                                    class="h-8 text-xs"
                                                    @input="(e: Event) => searchOfficial('ability', (e.target as HTMLInputElement).value)"
                                                />
                                                <div
                                                    v-if="searchType === 'ability' && searchResults.length"
                                                    class="absolute z-10 mt-1 w-full rounded-md border bg-popover p-1 shadow-md"
                                                >
                                                    <button
                                                        v-for="r in searchResults"
                                                        :key="r.id"
                                                        class="w-full rounded px-2 py-1.5 text-left text-sm hover:bg-accent"
                                                        @click="pickAbility(r)"
                                                    >
                                                        <span class="font-medium">{{ r.name }}</span>
                                                        <span class="ml-2 text-xs text-muted-foreground"
                                                            >{{ r.description?.slice(0, 60) }}{{ r.description?.length > 60 ? '...' : '' }}</span
                                                        >
                                                    </button>
                                                </div>
                                            </div>
                                            <Button variant="outline" size="sm" class="h-8 shrink-0 text-xs" @click="abilityModalOpen = true"
                                                ><Plus class="mr-1 size-3" /> Browse</Button
                                            >
                                            <Button variant="outline" size="sm" class="h-8 shrink-0 text-xs" @click="addAbility"
                                                ><Plus class="mr-1 size-3" /> Custom</Button
                                            >
                                        </div>

                                        <!-- Same per-type tint as the Custom Upgrade editor's Card Content
                                             blocks (blockTypeColor in UpgradeEditor.vue) — breaks up the
                                             Abilities/Actions/Triggers sections visually instead of every
                                             block looking identical. Alternates a lighter/darker shade of
                                             that tint by list position so adjacent entries are distinguishable. -->
                                        <div
                                            v-for="(ability, aIdx) in abilities"
                                            :key="'ability-' + aIdx"
                                            class="space-y-2 rounded-lg border p-3"
                                            :class="aIdx % 2 === 0 ? 'border-blue-500/20 bg-blue-500/10' : 'border-blue-500/15 bg-blue-500/5'"
                                        >
                                            <template v-if="ability.source_id">
                                                <div class="flex items-center justify-between">
                                                    <div class="flex items-center gap-2">
                                                        <span class="text-sm font-medium">{{ ability.name }}</span>
                                                        <Badge variant="outline" class="shrink-0 px-1 py-0 text-[10px]">Official</Badge>
                                                    </div>
                                                    <button
                                                        aria-label="Remove ability"
                                                        class="text-muted-foreground hover:text-destructive"
                                                        @click="removeAbility(aIdx)"
                                                    >
                                                        <Trash2 class="size-3.5" />
                                                    </button>
                                                </div>
                                                <div v-if="ability.description" class="text-xs text-muted-foreground">{{ ability.description }}</div>
                                            </template>
                                            <template v-else>
                                                <div class="flex items-center justify-between">
                                                    <Input v-model="ability.name" placeholder="Ability name" class="h-7 text-sm font-medium" />
                                                    <button
                                                        aria-label="Remove ability"
                                                        class="ml-2 text-muted-foreground hover:text-destructive"
                                                        @click="removeAbility(aIdx)"
                                                    >
                                                        <Trash2 class="size-3.5" />
                                                    </button>
                                                </div>
                                                <div class="grid gap-2 sm:grid-cols-3">
                                                    <Select v-model="ability.suits">
                                                        <SelectTrigger class="h-7 text-xs"><SelectValue placeholder="Suit" /></SelectTrigger>
                                                        <SelectContent>
                                                            <SelectItem value="none">None</SelectItem>
                                                            <SelectItem v-for="s in enums.suits" :key="s.value" :value="s.value">{{
                                                                s.name
                                                            }}</SelectItem>
                                                        </SelectContent>
                                                    </Select>
                                                    <Select v-model="ability.defensive_ability_type">
                                                        <SelectTrigger class="h-7 text-xs"><SelectValue placeholder="Defense type" /></SelectTrigger>
                                                        <SelectContent>
                                                            <SelectItem value="none">None</SelectItem>
                                                            <SelectItem v-for="d in enums.defensive_ability_types" :key="d.value" :value="d.value">{{
                                                                d.name
                                                            }}</SelectItem>
                                                        </SelectContent>
                                                    </Select>
                                                    <label class="flex items-center gap-1.5 text-xs">
                                                        <input v-model="ability.costs_stone" type="checkbox" class="rounded" />
                                                        Costs Soulstone
                                                    </label>
                                                </div>
                                                <Textarea
                                                    v-model="ability.description"
                                                    placeholder="Ability description..."
                                                    rows="2"
                                                    class="text-xs"
                                                />
                                                <IconTokenPalette class="mt-1" />
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
                                                <Input
                                                    placeholder="Search official actions..."
                                                    class="h-8 text-xs"
                                                    @input="(e: Event) => searchOfficial('action', (e.target as HTMLInputElement).value)"
                                                />
                                                <div
                                                    v-if="searchType === 'action' && searchResults.length"
                                                    class="absolute z-10 mt-1 w-full rounded-md border bg-popover p-1 shadow-md"
                                                >
                                                    <button
                                                        v-for="r in searchResults"
                                                        :key="r.id"
                                                        class="w-full rounded px-2 py-1.5 text-left text-sm hover:bg-accent"
                                                        @click="pickAction(r)"
                                                    >
                                                        <span class="font-medium">{{ r.name }}</span>
                                                        <Badge class="ml-2 text-[10px]">{{ r.type }}</Badge>
                                                    </button>
                                                </div>
                                            </div>
                                            <Button variant="outline" size="sm" class="h-8 shrink-0 text-xs" @click="actionModalOpen = true"
                                                ><Plus class="mr-1 size-3" /> Browse</Button
                                            >
                                            <Button variant="outline" size="sm" class="h-8 shrink-0 text-xs" @click="addAction"
                                                ><Plus class="mr-1 size-3" /> Custom</Button
                                            >
                                        </div>

                                        <!-- Alternates a lighter/darker amber shade by list position — see the
                                             matching comment on the Abilities boxes above. -->
                                        <div
                                            v-for="(action, idx) in actions"
                                            :key="'action-' + idx"
                                            class="space-y-3 rounded-lg border p-3"
                                            :class="idx % 2 === 0 ? 'border-amber-500/20 bg-amber-500/10' : 'border-amber-500/15 bg-amber-500/5'"
                                        >
                                            <div class="flex items-center justify-between">
                                                <div class="flex flex-1 items-center gap-2">
                                                    <Input v-model="action.name" placeholder="Action name" class="h-7 text-sm font-medium" />
                                                    <Badge v-if="action.source_id" variant="outline" class="shrink-0 px-1 py-0 text-[10px]"
                                                        >Official</Badge
                                                    >
                                                </div>
                                                <button
                                                    aria-label="Remove action"
                                                    class="ml-2 shrink-0 text-muted-foreground hover:text-destructive"
                                                    @click="removeAction(idx)"
                                                >
                                                    <Trash2 class="size-3.5" />
                                                </button>
                                            </div>
                                            <div class="grid grid-cols-3 gap-2 sm:grid-cols-6">
                                                <div>
                                                    <label class="text-[10px] text-muted-foreground">Type</label>
                                                    <Select v-model="action.type">
                                                        <SelectTrigger class="h-7 text-xs"><SelectValue /></SelectTrigger>
                                                        <SelectContent>
                                                            <SelectItem v-for="t in enums.action_types" :key="t.value" :value="t.value">{{
                                                                t.name
                                                            }}</SelectItem>
                                                        </SelectContent>
                                                    </Select>
                                                </div>
                                                <div>
                                                    <label class="text-[10px] text-muted-foreground">Range</label>
                                                    <Input v-model="action.range" placeholder="e.g. 2, *, X" class="h-7 text-xs" />
                                                </div>
                                                <div>
                                                    <label class="text-[10px] text-muted-foreground">Range Type</label>
                                                    <Select v-model="action.range_type">
                                                        <SelectTrigger class="h-7 text-xs"><SelectValue placeholder="—" /></SelectTrigger>
                                                        <SelectContent>
                                                            <SelectItem value="none">None</SelectItem>
                                                            <SelectItem v-for="r in enums.range_types" :key="r.value" :value="r.value">{{
                                                                r.name
                                                            }}</SelectItem>
                                                        </SelectContent>
                                                    </Select>
                                                </div>
                                                <div>
                                                    <label class="text-[10px] text-muted-foreground">Stat</label>
                                                    <Input v-model="action.stat" placeholder="e.g. 5, X" class="h-7 text-xs" />
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
                                            <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                                                <div>
                                                    <label class="text-[10px] text-muted-foreground">Stat Suits</label>
                                                    <Input v-model="action.stat_suits" placeholder="e.g. crow ram" class="h-7 text-xs" />
                                                </div>
                                                <div>
                                                    <label class="text-[10px] text-muted-foreground">Stat Modifier</label>
                                                    <Input v-model="action.stat_modifier" placeholder="e.g. crow" class="h-7 text-xs" />
                                                </div>
                                                <div>
                                                    <label class="text-[10px] text-muted-foreground">Target #</label>
                                                    <Input v-model="action.target_number" placeholder="e.g. 13" class="h-7 text-xs" />
                                                </div>
                                                <div>
                                                    <label class="text-[10px] text-muted-foreground">Target Suits</label>
                                                    <Input v-model="action.target_suits" placeholder="e.g. crow" class="h-7 text-xs" />
                                                </div>
                                            </div>
                                            <Textarea v-model="action.description" placeholder="Action description..." rows="2" class="text-xs" />
                                            <IconTokenPalette class="mt-1" />
                                            <label class="flex cursor-pointer items-center gap-1.5 text-xs text-muted-foreground">
                                                <Checkbox
                                                    :checked="action.is_signature"
                                                    @update:checked="(v: boolean) => (action.is_signature = v)"
                                                />
                                                Signature action
                                            </label>

                                            <!-- Triggers (always shown) -->
                                            <div class="space-y-2 border-t pt-2">
                                                <div class="flex items-center justify-between">
                                                    <span class="text-[11px] font-medium text-muted-foreground">Triggers</span>
                                                    <Button variant="ghost" size="sm" class="h-5 px-1.5 text-[10px]" @click="addTrigger(action)"
                                                        ><Plus class="mr-0.5 size-2.5" /> Add</Button
                                                    >
                                                </div>
                                                <div class="relative">
                                                    <Input
                                                        placeholder="Search official triggers..."
                                                        class="h-7 text-xs"
                                                        @input="(e: Event) => searchTriggers((e.target as HTMLInputElement).value, idx)"
                                                    />
                                                    <div
                                                        v-if="searchType === 'trigger' && searchTargetIndex === idx && searchResults.length"
                                                        class="absolute z-10 mt-1 w-full rounded-md border bg-popover p-1 shadow-md"
                                                    >
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
                                                <!-- Alternates a lighter/darker purple shade by list position — see
                                                     the matching comment on the Abilities boxes above. -->
                                                <div
                                                    v-for="(trigger, tIdx) in action.triggers"
                                                    :key="'trigger-' + tIdx"
                                                    class="flex items-start gap-2 rounded border p-2"
                                                    :class="
                                                        tIdx % 2 === 0
                                                            ? 'border-purple-500/20 bg-purple-500/10'
                                                            : 'border-purple-500/15 bg-purple-500/5'
                                                    "
                                                >
                                                    <template v-if="trigger.source_id">
                                                        <div class="flex-1 text-xs">
                                                            <span class="font-medium">{{ trigger.suits }} {{ trigger.name }}:</span>
                                                            <span class="text-muted-foreground"> {{ trigger.description }}</span>
                                                            <Badge variant="outline" class="ml-1 px-1 py-0 text-[10px]">Official</Badge>
                                                        </div>
                                                    </template>
                                                    <template v-else>
                                                        <div class="flex-1 space-y-1">
                                                            <div class="flex items-center gap-2">
                                                                <Input
                                                                    v-model="trigger.name"
                                                                    placeholder="Trigger name"
                                                                    class="h-6 text-xs font-medium"
                                                                />
                                                                <Input v-model="trigger.suits" placeholder="Suits" class="h-6 w-24 text-xs" />
                                                            </div>
                                                            <Input
                                                                v-model="trigger.description"
                                                                placeholder="Trigger description"
                                                                class="h-6 text-xs"
                                                            />
                                                        </div>
                                                    </template>
                                                    <button
                                                        aria-label="Remove trigger"
                                                        class="mt-1 text-muted-foreground hover:text-destructive"
                                                        @click="removeTrigger(action, tIdx)"
                                                    >
                                                        <X class="size-3" />
                                                    </button>
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
                <!-- order-first below lg so the preview leads on mobile/tablet (users get live
                     feedback while filling in the form instead of scrolling past it entirely);
                     lg:order-none restores normal DOM order (form left, preview right) at desktop. -->
                <div class="order-first lg:order-none lg:col-span-1">
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
                    <div v-if="Object.keys(errors).length" class="text-destructive">Please fix validation errors above.</div>
                    <div v-else-if="!isEdit" class="text-muted-foreground">
                        New character — a shareable card image is rendered automatically after you create it.
                    </div>
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
                            v-if="!imagePending && !rerendering"
                            type="button"
                            class="inline-flex items-center gap-1 underline-offset-2 hover:text-foreground hover:underline"
                            @click="rerender"
                        >
                            <RefreshCw class="size-3" /> Re-render
                        </button>
                    </div>
                </div>
                <Button :disabled="saving || !form.name || form.faction === 'none'" size="sm" @click="save">
                    <Loader2 v-if="saving" class="mr-2 size-4 animate-spin" />
                    <Save v-else class="mr-2 size-4" />
                    {{ isEdit ? 'Save Changes' : 'Create Character' }}
                </Button>
            </div>
        </div>

        <Dialog :open="artPickerOpen" @update:open="(o: boolean) => (artPickerOpen = o)">
            <DialogContent class="max-w-3xl">
                <DialogHeader>
                    <DialogTitle>Choose character art</DialogTitle>
                    <DialogDescription>Art from the BiggerHat library. It's saved with the card and shows on the share link.</DialogDescription>
                </DialogHeader>
                <div class="flex flex-wrap items-center gap-2">
                    <Input v-model="artSearch" placeholder="Search art…" class="h-8 max-w-[14rem]" aria-label="Search art" />
                    <label v-if="form.faction !== 'none'" class="flex items-center gap-1.5 text-xs">
                        <input v-model="artFactionOnly" type="checkbox" />
                        Only this faction (and unrestricted art)
                    </label>
                </div>
                <div v-if="artCategories.length" class="flex flex-wrap gap-1.5">
                    <button
                        type="button"
                        class="rounded-full border px-2.5 py-0.5 text-xs"
                        :class="artCategory === null ? 'border-primary bg-primary text-primary-foreground' : 'hover:bg-muted'"
                        @click="artCategory = null"
                    >
                        All
                    </button>
                    <button
                        v-for="c in artCategories"
                        :key="c"
                        type="button"
                        class="rounded-full border px-2.5 py-0.5 text-xs"
                        :class="artCategory === c ? 'border-primary bg-primary text-primary-foreground' : 'hover:bg-muted'"
                        @click="artCategory = c"
                    >
                        {{ c }}
                    </button>
                </div>
                <div class="max-h-[55vh] overflow-y-auto">
                    <div v-if="card_arts.length === 0" class="py-10 text-center text-sm text-muted-foreground">
                        The art library is empty for now — check back soon.
                    </div>
                    <div v-else-if="visibleArts.length === 0" class="py-10 text-center text-sm text-muted-foreground">
                        No art matches. Try clearing the filters.
                    </div>
                    <div v-else class="grid grid-cols-2 gap-2 sm:grid-cols-3 md:grid-cols-4">
                        <button
                            v-for="art in visibleArts"
                            :key="art.id"
                            type="button"
                            class="group overflow-hidden rounded-md border text-left transition-colors hover:border-primary focus-visible:ring-2 focus-visible:ring-ring"
                            :class="form.card_art_id === art.id ? 'border-primary ring-2 ring-primary' : ''"
                            :aria-pressed="form.card_art_id === art.id"
                            @click="pickArt(art.id)"
                        >
                            <img :src="'/storage/' + art.image_path" :alt="art.name" class="aspect-[4/3] w-full object-cover" loading="lazy" />
                            <span class="block truncate px-2 py-1 text-xs">{{ art.name }}</span>
                        </button>
                    </div>
                </div>
            </DialogContent>
        </Dialog>

        <ActionLookupModal v-model:open="actionModalOpen" @commit="commitActionsFromModal" />
        <AbilityLookupModal v-model:open="abilityModalOpen" @commit="commitAbilitiesFromModal" />
    </div>
</template>
