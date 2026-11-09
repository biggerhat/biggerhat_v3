<script setup lang="ts">
import UpgradeBackFace from '@/components/CardCreator/UpgradeBackFace.vue';
import UpgradeCardRenderer from '@/components/CardCreator/UpgradeCardRenderer.vue';
import UpgradeFrontFace from '@/components/CardCreator/UpgradeFrontFace.vue';
import { createComboImage, exportComboAsPdf, captureCardFaces, saveCardJson, tarotCardSize, triggerDownload } from '@/components/CardCreator/utils';
import ContentBlocksPanel from '@/components/CardCreator/ContentBlocksPanel.vue';
import TokensMarkersPanel from '@/components/CardCreator/TokensMarkersPanel.vue';
import type { ContentBlock, MarkerData, TokenData } from '@/components/CardCreator/types';
import SuggestInput from '@/components/CardCreator/SuggestInput.vue';
import PageBanner from '@/components/PageBanner.vue';
import CardHistoryDialog, { type CardVersion } from '@/components/CardCreator/CardHistoryDialog.vue';
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
import { ArrowLeft, ChevronDown, Copy, Download, FileJson, History, FileText, Eye, Loader2, RefreshCw, Save, Share2, Upload } from 'lucide-vue-next';
import { computed, reactive, ref } from 'vue';

interface EnumOption {
    name: string;
    value: string;
}

const toast = useToast();

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
        is_campaign_crew_card: boolean;
        campaign_crew_id?: number | null;
        front_image?: string | null;
        combination_image?: string | null;
        card_image_generated_at?: string | null;
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
    campaign_back_url: string | null;
    card_image_pending?: boolean;
    versions?: CardVersion[];
    // Auto-calculated from the crew's arsenal (CombinedCrewCardEffects::
    // arsenalTokensAndMarkers()) — only present for an is_campaign_crew_card
    // upgrade, whose own back_tokens/back_markers columns are never read by
    // the actual generated card (see the readonly section below).
    crew_computed_tokens_markers: { type: 'token' | 'marker'; name: string; description: string | null }[] | null;
}>();

const isEdit = computed(() => !!props.upgrade);
const isCrew = computed(() => props.domain === 'crew');
const domainLabel = computed(() => (isCrew.value ? 'Crew Card' : 'Upgrade'));

// Saved card image: rendered server-side shortly after each save; the finished event swaps the status in.
const imagePending = computed(() => props.card_image_pending ?? false);
const imageUrl = computed(() =>
    props.upgrade?.combination_image
        ? '/storage/' + cacheBustedImagePath(props.upgrade.combination_image, props.upgrade.card_image_generated_at)
        : null,
);
const rerendering = ref(false);
useCardImageEvents(usePage<SharedData>().props.auth.user?.id, (event) => {
    const ownEvent = event.kind === 'custom_upgrade' && event.id === props.upgrade?.id;
    // A campaign crew card shows the crew's combined card, which finishes under its own event.
    const crewEvent = event.kind === 'crew_card' && !!props.upgrade?.is_campaign_crew_card && event.id === props.upgrade?.campaign_crew_id;
    if (ownEvent || crewEvent) {
        router.reload({ only: ['upgrade', 'card_image_pending'], preserveScroll: true });
    }
});
// Below lg the preview sits under the form; the save bar's Preview button jumps to it.
const scrollToPreview = () => document.getElementById('card-preview')?.scrollIntoView({ behavior: 'smooth', block: 'start' });

const rerender = async () => {
    if (!props.upgrade || rerendering.value) return;
    rerendering.value = true;
    const res = await fetch(route('tools.card_creator.upgrades.rerender', props.upgrade.id), {
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

const form = reactive({
    name: props.upgrade?.name ?? '',
    type: props.upgrade?.type ?? '',
    faction: props.upgrade?.faction ?? 'none',
    limitations: props.upgrade?.limitations ?? '',
    plentiful: props.upgrade?.plentiful ?? (null as number | null),
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

const { hasDraft, dirty, restoreDraft, discardDraft, markSaved, currentSnapshot, applySnapshot } = useEditorDraft({
    key: `bh_draft_upgrade_${props.domain}`,
    keepDraft: !props.upgrade,
    snapshot: () => ({
        form: { ...form },
        contentBlocks: JSON.parse(JSON.stringify(contentBlocks)),
        backTokens: JSON.parse(JSON.stringify(backTokens)),
        backMarkers: JSON.parse(JSON.stringify(backMarkers)),
    }),
    restore: (d) => {
        // Drafts and imported files are only trusted for keys the form has and values of the right shape.
        for (const key of Object.keys(form) as (keyof typeof form)[]) {
            if (d.form && key in d.form) (form as Record<string, unknown>)[key] = d.form[key];
        }
        const list = <T,>(v: unknown): T[] => (Array.isArray(v) ? (v as T[]) : []);
        contentBlocks.splice(0, contentBlocks.length, ...list<ContentBlock>(d.contentBlocks));
        backTokens.splice(0, backTokens.length, ...list<TokenData>(d.backTokens));
        backMarkers.splice(0, backMarkers.length, ...list<MarkerData>(d.backMarkers));
    },
});

const historyOpen = ref(false);
const { exportJson, importFile } = useCardJsonTransfer({
    kind: 'custom-upgrade',
    snapshot: currentSnapshot,
    apply: applySnapshot,
    scrub: (d) => ({ ...d, form: { ...d.form, notes: '' } }),
    filename: () => form.name,
});

const saving = ref(false);
const exporting = ref(false);
const errors = ref<Record<string, string>>({});
const cardRendererRef = ref<InstanceType<typeof UpgradeCardRenderer> | null>(null);
const exportFrontRef = ref<InstanceType<typeof UpgradeFrontFace> | null>(null);
const exportBackRef = ref<InstanceType<typeof UpgradeBackFace> | null>(null);
const printFrontRef = ref<InstanceType<typeof UpgradeFrontFace> | null>(null);
const printBackRef = ref<InstanceType<typeof UpgradeBackFace> | null>(null);

// The live preview (UpgradeCardRenderer.vue) sits in this page's 1/3-width
// column, which routinely clamps its box below every tarotCardSize tier —
// there's no room for it to grow into, so it falls back to shrinkToFit
// (shrinking text to fit whatever width the column allows) rather than
// growing. Exports must not inherit that constraint, so both PNG and PDF
// export capture from their own hidden, off-screen, unconstrained instances
// instead of the live preview — measured on demand right before each
// export, not continuously, since they're never shown on screen. Char-count
// guess mirrors UpgradeCardRenderer.vue's frontChars formula.
const contentCharGuess = () =>
    tarotCardSize(
        contentBlocks.reduce((sum, block) => {
            if (block.type === 'text') return sum + (block.text?.length ?? 0);
            const d = block.data;
            if (!d) return sum;
            const triggerChars = ((d.triggers as { name: string; description: string | null }[] | undefined) ?? []).reduce(
                (ts, t) => ts + (t.description?.length ?? 0) + t.name.length,
                0,
            );
            return sum + ((d.description as string | null)?.length ?? 0) + ((d.name as string | undefined)?.length ?? 0) + triggerChars;
        }, 0),
    ).width;
const {
    width: exportWidth,
    height: exportHeight,
    measure: measureExportSize,
} = useTarotGrowToFit(
    () => exportFrontRef.value?.rootRef,
    () => exportBackRef.value?.rootRef,
    contentCharGuess,
);
const {
    width: printWidth,
    height: printHeight,
    measure: measurePrintSize,
} = useTarotGrowToFit(
    () => printFrontRef.value?.rootRef,
    () => printBackRef.value?.rootRef,
    contentCharGuess,
);

const noneToNull = (v: string | null | undefined) => (!v || v === 'none' ? null : v);

// Labels for card preview
const upgradeTypeLabel = computed(() => form.type || null);
const limitationsLabel = computed(() => form.limitations || null);

const fetchLatest = useLatestFetch();

// Duplicate from an official upgrade
const cloneSearchQuery = ref('');
const cloneSearchResults = ref<{ id: number; name: string }[]>([]);
const cloneLoading = ref(false);
let cloneDebounce: ReturnType<typeof setTimeout>;

const searchCloneUpgrade = (q: string) => {
    clearTimeout(cloneDebounce);
    if (q.length < 2) {
        cloneSearchResults.value = [];
        return;
    }
    cloneDebounce = setTimeout(async () => {
        const data = await fetchLatest<{ id: number; name: string }[]>(
            'clone',
            route('api.card-creator.upgrades') + '?domain=' + props.domain + '&q=' + encodeURIComponent(q),
        );
        if (data) cloneSearchResults.value = data;
    }, 300);
};

const cloneFromUpgrade = async (id: number) => {
    cloneLoading.value = true;
    cloneSearchResults.value = [];
    cloneSearchQuery.value = '';
    const data = await fetchLatest<any>('clone-detail', route('api.card-creator.upgrade-detail', id));
    cloneLoading.value = false;
    if (!data) {
        toast.error('Could not load that upgrade', { description: 'Try again in a moment.' });
        return;
    }
    form.name = data.name ?? '';
    form.type = data.type ?? '';
    form.faction = data.faction ?? 'none';
    form.limitations = data.limitations ?? '';
    form.plentiful = data.plentiful ?? null;
    form.master_name = data.master_name ?? '';
    form.keyword_name = data.keyword_name ?? '';
    contentBlocks.splice(0, contentBlocks.length, ...(data.content_blocks ?? []));
    backTokens.splice(0, backTokens.length, ...(isCrew.value ? (data.back_tokens ?? []) : []));
    backMarkers.splice(0, backMarkers.length, ...(isCrew.value ? (data.back_markers ?? []) : []));
};

// Image export

const exportingPdf = ref(false);

const exportImages = async () => {
    exporting.value = true;
    try {
        await measureExportSize();
        const captured = await captureCardFaces(exportFrontRef.value?.rootRef, exportBackRef.value?.rootRef);
        if (!captured) return;
        const comboData = await createComboImage(captured.frontData, captured.backData);
        triggerDownload(comboData, `${form.name || 'upgrade'}.png`);
    } catch (e) {
        console.error('Image export failed:', e);
        toast.error('Image export failed', { description: e instanceof Error ? e.message : 'Try again.' });
    } finally {
        exporting.value = false;
    }
};

// Captures the hidden print-mode faces (light theme — see printFrontRef/
// printBackRef in the template), same shape as exportImages() above but
// from the print instances instead of the export instances.
const exportPdf = async () => {
    exportingPdf.value = true;
    try {
        await measurePrintSize();
        const captured = await captureCardFaces(printFrontRef.value?.rootRef, printBackRef.value?.rootRef);
        if (!captured) return;
        await exportComboAsPdf(captured.frontData, captured.backData, captured.frontEl, captured.backEl, `${form.name || 'upgrade'}.pdf`);
    } catch (e) {
        console.error('PDF export failed:', e);
        toast.error('PDF export failed', { description: e instanceof Error ? e.message : 'Try again.' });
    } finally {
        exportingPdf.value = false;
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
        if (b.type === 'trigger' && b.data)
            return { ...b, data: { ...b.data, stone_cost: b.data.stone_cost === '' || b.data.stone_cost == null ? 0 : b.data.stone_cost } };
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

    const result = await saveCardJson(url, isEdit.value ? 'PUT' : 'POST', body, csrfToken());
    const data = result.data;

    if (!result.ok) {
        errors.value = result.errors;
        if (result.message) toast.error('Not saved', { description: result.message });
        saving.value = false;
        return;
    }

    markSaved();

    if (data.redirect) {
        router.visit(data.redirect);
        return;
    }

    // Stay on the editor: pick up the "rendering" state the save just triggered.
    router.reload({ only: ['upgrade', 'card_image_pending'], preserveScroll: true });

    saving.value = false;
};
</script>

<template>
    <Head :title="isEdit ? `Edit — ${upgrade!.name}` : `New Custom ${domainLabel}`" />

    <div class="relative pb-12">
        <div
            class="pointer-events-none absolute inset-x-0 top-0 h-64 opacity-[0.07] dark:opacity-[0.12]"
            :style="{ background: 'radial-gradient(ellipse at top, hsl(var(--primary)) 0%, transparent 70%)' }"
        />

        <PageBanner :title="isEdit ? `Edit ${domainLabel}` : `New Custom ${domainLabel}`">
            <template #subtitle>
                <div class="px-2 text-sm text-muted-foreground">
                    {{ isEdit ? `Editing ${upgrade!.name}` : `Build a custom ${domainLabel.toLowerCase()} with abilities, actions, and triggers.` }}
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
                <Link v-if="props.campaign_back_url" :href="props.campaign_back_url">
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
                    <Button v-if="isEdit && versions?.length" variant="ghost" size="sm" @click="historyOpen = true"
                        ><History class="mr-1 size-4" /> History</Button
                    >
                    <Button variant="ghost" size="sm" @click="exportJson"><FileJson class="mr-1 size-4" /> Export JSON</Button>
                </div>
            </div>

            <div class="grid gap-6 lg:grid-cols-3">
                <!-- Form column -->
                <div class="space-y-4 lg:col-span-2">
                    <!-- Duplicate from an official upgrade -->
                    <div v-if="!isEdit" class="relative rounded-lg border border-dashed border-muted-foreground/30 bg-muted/30 p-3">
                        <div class="mb-1.5 flex items-center gap-1.5 text-xs font-medium text-muted-foreground">
                            <Copy class="size-3.5" />
                            Duplicate from an official {{ isCrew ? 'crew upgrade' : 'upgrade' }}
                        </div>
                        <div class="relative">
                            <Input
                                v-model="cloneSearchQuery"
                                :placeholder="
                                    cloneLoading
                                        ? 'Loading upgrade data...'
                                        : `Search for ${isCrew ? 'a crew upgrade' : 'an upgrade'} to duplicate...`
                                "
                                :disabled="cloneLoading"
                                class="h-8 text-xs"
                                @input="searchCloneUpgrade(cloneSearchQuery)"
                            />
                            <div
                                v-if="cloneSearchResults.length"
                                class="absolute z-10 mt-1 max-h-48 w-full overflow-auto rounded-md border bg-popover p-1 shadow-md"
                            >
                                <button
                                    v-for="r in cloneSearchResults"
                                    :key="r.id"
                                    class="w-full rounded px-2 py-1.5 text-left text-sm hover:bg-accent"
                                    @click="cloneFromUpgrade(r.id)"
                                >
                                    {{ r.name }}
                                </button>
                            </div>
                        </div>
                        <p class="mt-1 text-2xs text-muted-foreground">
                            Fills in the text, abilities and actions from an official card. You can edit everything after.
                        </p>
                    </div>

                    <!-- Identity -->
                    <Card>
                        <CardContent class="space-y-4 p-4">
                            <h2 class="text-sm font-semibold">{{ domainLabel }} Identity</h2>
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
                                    <div
                                        v-if="showTypeSuggestions && typeSuggestions.length"
                                        class="absolute z-10 mt-1 max-h-48 w-full overflow-auto rounded-md border bg-popover p-1 shadow-md"
                                    >
                                        <button
                                            v-for="t in typeSuggestions"
                                            :key="t.value"
                                            class="flex w-full items-center justify-between rounded px-2 py-1.5 text-left text-sm hover:bg-accent"
                                            @mousedown.prevent="
                                                form.type = t.name;
                                                showTypeSuggestions = false;
                                            "
                                        >
                                            {{ t.name }}
                                            <Badge variant="outline" class="px-1 py-0 text-2xs">Official</Badge>
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
                                    <div
                                        v-if="showLimitationsSuggestions && limitationsSuggestions.length"
                                        class="absolute z-10 mt-1 max-h-48 w-full overflow-auto rounded-md border bg-popover p-1 shadow-md"
                                    >
                                        <button
                                            v-for="l in limitationsSuggestions"
                                            :key="l.value"
                                            class="flex w-full items-center justify-between rounded px-2 py-1.5 text-left text-sm hover:bg-accent"
                                            @mousedown.prevent="
                                                form.limitations = l.name;
                                                showLimitationsSuggestions = false;
                                            "
                                        >
                                            {{ l.name }}
                                            <Badge variant="outline" class="px-1 py-0 text-2xs">Official</Badge>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <!-- Crew-specific fields -->
                            <div v-if="isCrew">
                                <label class="mb-1 block text-xs text-muted-foreground">Master Name</label>
                                <SuggestInput
                                    v-model="form.master_name"
                                    :endpoint="route('api.card-creator.characters')"
                                    placeholder="e.g. Maxine Agassiz, The Renowned"
                                />
                            </div>
                            <div>
                                <label class="mb-1 block text-xs text-muted-foreground">Keyword (optional)</label>
                                <SuggestInput
                                    v-model="form.keyword_name"
                                    :endpoint="route('api.card-creator.keywords')"
                                    placeholder="e.g. Witchling"
                                />
                            </div>
                        </CardContent>
                    </Card>

                    <ContentBlocksPanel v-model="contentBlocks" :enums="enums" />

                    <!-- Back Face: Tokens & Markers (crew only). Not shown for a
                         synced campaign crew card — its back face's tokens/
                         markers are auto-calculated from the crew's live
                         arsenal (see the read-only section below), so editing
                         them here would silently do nothing on the real card. -->
                    <TokensMarkersPanel
                        v-if="isCrew && !props.upgrade?.is_campaign_crew_card"
                        v-model:tokens="backTokens"
                        v-model:markers="backMarkers"
                    />

                    <!-- Back Face: Tokens & Markers, read-only, for a synced
                         campaign crew card — auto-calculated from the crew's
                         current arsenal, not something this editor can change. -->
                    <Collapsible v-if="isCrew && props.upgrade?.is_campaign_crew_card" default-open>
                        <Card>
                            <CardContent class="p-4">
                                <CollapsibleTrigger class="flex w-full items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <h3 class="text-sm font-semibold">Back — Tokens & Markers</h3>
                                        <Badge v-if="props.crew_computed_tokens_markers?.length" variant="secondary" class="text-2xs">{{
                                            props.crew_computed_tokens_markers.length
                                        }}</Badge>
                                    </div>
                                    <ChevronDown class="size-4 text-muted-foreground transition-transform [[data-state=open]_&]:rotate-180" />
                                </CollapsibleTrigger>
                                <CollapsibleContent>
                                    <p class="mb-2 mt-3 text-xs text-muted-foreground">
                                        Automatically calculated from your crew's current arsenal — shown here for reference, not editable.
                                    </p>
                                    <div v-if="!props.crew_computed_tokens_markers?.length" class="text-xs text-muted-foreground">
                                        No tokens or markers currently held by this crew.
                                    </div>
                                    <div
                                        v-for="(item, idx) in props.crew_computed_tokens_markers"
                                        :key="item.type + '-' + item.name + '-' + idx"
                                        class="mb-2 rounded-lg border p-2"
                                    >
                                        <div class="flex items-center gap-2">
                                            <span class="text-xs font-medium">{{ item.name }}</span>
                                            <Badge variant="outline" class="px-1 py-0 text-2xs">{{
                                                item.type === 'token' ? 'Token' : 'Marker'
                                            }}</Badge>
                                        </div>
                                        <div v-if="item.description" class="text-xs text-muted-foreground">{{ item.description }}</div>
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
                                    <h2 class="text-sm font-semibold">Notes</h2>
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
                <div id="card-preview" class="scroll-mt-4 lg:col-span-1">
                    <div class="sticky top-4 space-y-3">
                        <div class="flex items-center justify-between">
                            <h2 class="text-sm font-semibold">Preview</h2>
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

                        <!-- Hidden export-only instance (dark theme, matching the live
                             preview) — exportImages() captures these instead of the live
                             preview above, which is width-clamped by this page's 1/3
                             column and can't reliably grow to fit dense content (see
                             UpgradeCardRenderer.vue's doc comment). Fixed off-screen,
                             unconstrained by any layout, sized via grow-to-fit measured
                             on demand right before export — see measureExportSize in
                             exportImages(). -->
                        <div
                            class="pointer-events-none fixed left-[-9999px] top-0"
                            :style="{ width: exportWidth + 'px', height: exportHeight + 'px' }"
                        >
                            <UpgradeFrontFace
                                ref="exportFrontRef"
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
                                :card-min-height="exportHeight"
                            />
                        </div>
                        <div
                            class="pointer-events-none fixed left-[-9999px] top-0"
                            :style="{ width: exportWidth + 'px', height: exportHeight + 'px' }"
                        >
                            <UpgradeBackFace
                                ref="exportBackRef"
                                :name="form.name || domainLabel + ' Name'"
                                :domain="domain"
                                :faction="form.faction !== 'none' ? form.faction : null"
                                :master-name="form.master_name || null"
                                :back-tokens="backTokens"
                                :back-markers="backMarkers"
                                :card-min-height="exportHeight"
                            />
                        </div>

                        <!-- Hidden print-only instance (light theme, see printMode) —
                             exportPdf() captures these instead of the live dark preview
                             above. Fixed off-screen position so this doesn't affect page
                             layout/scroll. Sized via the same grow-to-fit measurement
                             (printWidth/printHeight, measured on demand right before
                             export — see measurePrintSize in exportPdf()) rather than a
                             fixed 550x950 box, which clipped dense cards. -->
                        <div class="pointer-events-none fixed left-[-9999px] top-0" :style="{ width: printWidth + 'px', height: printHeight + 'px' }">
                            <UpgradeFrontFace
                                ref="printFrontRef"
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
                                :card-min-height="printHeight"
                                print-mode
                            />
                        </div>
                        <div class="pointer-events-none fixed left-[-9999px] top-0" :style="{ width: printWidth + 'px', height: printHeight + 'px' }">
                            <UpgradeBackFace
                                ref="printBackRef"
                                :name="form.name || domainLabel + ' Name'"
                                :domain="domain"
                                :faction="form.faction !== 'none' ? form.faction : null"
                                :master-name="form.master_name || null"
                                :back-tokens="backTokens"
                                :back-markers="backMarkers"
                                :card-min-height="printHeight"
                                print-mode
                            />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Save bar: sticky so Save stays in reach; shows the saved card image's state when editing. -->
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
                        <span class="truncate">Editing {{ form.name || domainLabel.toLowerCase() }}</span>
                        <span v-if="imagePending || rerendering" class="inline-flex items-center gap-1 text-foreground">
                            <Loader2 class="size-3 animate-spin" /> Rendering card image…
                        </span>
                        <template v-else-if="imageUrl">
                            <a :href="imageUrl" :download="`${form.name || 'card'}.png`" class="inline-flex items-center gap-1 hover:text-foreground">
                                <Download class="size-3" /> Card image
                            </a>
                            <Link
                                :href="route('tools.card_creator.upgrades.share', upgrade!.share_code)"
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
                <Button :disabled="saving || !form.name" size="sm" @click="save">
                    <Loader2 v-if="saving" class="mr-2 size-4 animate-spin" />
                    <Save v-else class="mr-2 size-4" />
                    {{ isEdit ? 'Save Changes' : `Create ${domainLabel}` }}
                </Button>
            </div>
        </div>

        <CardHistoryDialog
            v-if="isEdit && versions"
            v-model:open="historyOpen"
            :versions="versions"
            :dirty="dirty"
            :restore-url="(id: number) => route('tools.card_creator.upgrades.restore_version', [props.upgrade!.id, id])"
        />
    </div>
</template>
