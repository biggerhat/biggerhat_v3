<script setup lang="ts">
import AllegianceLogo from '@/components/AllegianceLogo.vue';
import CompanyCommanderPicker from '@/components/TOS/CompanyCommanderPicker.vue';
import CompanyHiringPoolPane from '@/components/TOS/CompanyHiringPoolPane.vue';
import CompanyRosterPane from '@/components/TOS/CompanyRosterPane.vue';
import CompanyUnitDrawer from '@/components/TOS/CompanyUnitDrawer.vue';
import CardImage from '@/components/TOS/CardImage.vue';
import { Badge } from '@/components/ui/badge';
import Button from '@/components/ui/button/Button.vue';
import { Card, CardContent } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useConfirm } from '@/composables/useConfirm';
import { Head, Link, router } from '@inertiajs/vue3';
import { AlertTriangle, ArrowLeft, Globe, Lock, Package, Plus, Printer, Search, Share2, Shield, Trash2, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const confirmDialog = useConfirm();

interface AllegianceCardMin {
    id: number;
    slug: string;
    name: string;
    image_path: string | null;
}

interface StratagemMin {
    id: number;
    slug: string;
    name: string;
    tactical_cost: number;
    deck_source?: 'primary' | 'envoy';
}

interface Allegiance {
    id: number;
    slug: string;
    name: string;
    type: string;
    color_slug: string | null;
    allegiance_cards?: AllegianceCardMin[];
}

interface SpecialRule {
    id: number;
    slug: string;
    name: string;
}

interface Sculpt {
    id: number;
    slug: string;
    name: string | null;
    front_image: string | null;
    back_image: string | null;
    combination_image: string | null;
}

interface UnitMin {
    id: number;
    slug: string;
    name: string;
    title: string | null;
    scrip: number;
    restriction: string | null;
    description?: string | null;
    combined_arms_child_id: number | null;
    special_unit_rules: SpecialRule[];
    sculpts?: Sculpt[];
    hire_category?: 'direct' | 'envoy' | 'neutral';
}

interface AssetLimit {
    id: number;
    limit_type: string;
    parameter_value: string | null;
}

interface AssetMin {
    id: number;
    slug: string;
    name: string;
    scrip_cost: number;
    limits?: AssetLimit[];
    already_attached?: boolean;
    // Company-unit ids this Asset can legally attach to (server-computed).
    attachable_company_unit_ids?: number[];
}

interface CompanyUnit {
    id: number;
    is_commander: boolean;
    is_combined_arms_child: boolean;
    sculpt_id: number | null;
    position: number;
    unit: UnitMin;
    assets: AssetMin[];
}

interface Company {
    id: number;
    slug: string;
    share_code: string;
    is_public: boolean;
    name: string;
    allegiance: Allegiance;
    envoy_allegiance: Allegiance | null;
    notes: string | null;
    company_units: CompanyUnit[];
    stratagems: StratagemMin[];
    garrison_id: number | null;
    garrison: { id: number; slug: string; name: string; format: string } | null;
}

interface AvailableGarrison {
    id: number;
    slug: string;
    name: string;
    format: string;
    allegiance_id: number;
    updated_at: string;
}

const props = defineProps<{
    company: Company;
    scrip_budget: number;
    scrip_spent: number;
    scrip_remaining: number;
    has_commander: boolean;
    commander_count: number;
    max_commanders: number;
    envoy_scrip_spent: number;
    envoy_scrip_cap: number;
    available_stratagems: StratagemMin[];
    stratagem_deck_size: number;
    max_envoy_stratagems: number;
    commander_pool: UnitMin[];
    hireable_units: UnitMin[];
    available_assets: AssetMin[];
    available_garrisons: AvailableGarrison[];
}>();

// ── Hiring pool filter state ─────────────────────────────────────────────
const filterText = ref('');
type PoolFilter = 'all' | 'direct' | 'envoy' | 'neutral' | 'commander';
const poolFilter = ref<PoolFilter>('all');
type PoolSort = 'name' | 'scrip';
const poolSort = ref<PoolSort>('name');

const isCommanderEligible = (u: UnitMin) => u.special_unit_rules.some((r) => r.slug === 'commander');

// Commanders are hired through the dedicated picker, never as regular units —
// so they're stripped from the Step-2 hiring pool entirely.
const poolUnits = computed(() => props.hireable_units.filter((u) => !isCommanderEligible(u)));

// Whether the Company can still take another Commander (format-capped).
const canAddCommander = computed(() => props.commander_count < props.max_commanders);

const augmentedPool = computed(() => {
    const text = filterText.value.trim().toLowerCase();
    const filtered = poolUnits.value.filter((u) => {
        if (text && !u.name.toLowerCase().includes(text) && !(u.title?.toLowerCase().includes(text) ?? false)) {
            return false;
        }
        switch (poolFilter.value) {
            case 'direct':
                return u.hire_category === 'direct';
            case 'envoy':
                return u.hire_category === 'envoy';
            case 'neutral':
                return u.hire_category === 'neutral';
            default:
                return true;
        }
    });

    return [...filtered].sort((a, b) => {
        if (poolSort.value === 'scrip') return a.scrip - b.scrip || a.name.localeCompare(b.name);
        return a.name.localeCompare(b.name);
    });
});

const filterCounts = computed(() => ({
    all: poolUnits.value.length,
    direct: poolUnits.value.filter((u) => u.hire_category === 'direct').length,
    envoy: poolUnits.value.filter((u) => u.hire_category === 'envoy').length,
    neutral: poolUnits.value.filter((u) => u.hire_category === 'neutral').length,
    commander: 0,
}));

// ── Budget helpers ───────────────────────────────────────────────────────
const budgetPercent = computed(() => {
    if (props.scrip_budget <= 0) return 0;
    return Math.min(100, Math.round((props.scrip_spent / props.scrip_budget) * 100));
});

const overBudget = computed(() => props.scrip_remaining < 0);

const budgetBarClass = computed(() => {
    if (overBudget.value) return 'bg-rose-500';
    if (budgetPercent.value >= 90) return 'bg-amber-500';
    return 'bg-emerald-500';
});

// ── Roster helpers ───────────────────────────────────────────────────────
const childByParentUnitId = computed(() => {
    const map = new Map<number, CompanyUnit>();
    for (const cu of props.company.company_units) {
        if (cu.is_combined_arms_child) {
            const parent = props.company.company_units.find((p) => p.unit.combined_arms_child_id === cu.unit.id && !p.is_combined_arms_child);
            if (parent) map.set(parent.unit.id, cu);
        }
    }
    return map;
});

const renderableUnits = computed(() =>
    [...props.company.company_units]
        .filter((cu) => !cu.is_combined_arms_child)
        .sort((a, b) => {
            if (a.is_commander && !b.is_commander) return -1;
            if (!a.is_commander && b.is_commander) return 1;
            return a.position - b.position;
        }),
);

const totalModels = computed(() => props.company.company_units.length);

const accentBg = computed(() => (props.company.allegiance.color_slug ? `bg-${props.company.allegiance.color_slug}` : 'bg-primary/40'));

// Inertia partial-reload contract — keys whose values we want refreshed
// on every pool action. `hireable_units` / `available_assets` aren't in
// this list so the heavy controller queries are skipped server-side; the
// asset-attach flow opts those back in to refresh the `already_attached`
// annotation.
const reloadOnly = [
    'company',
    'scrip_budget',
    'scrip_spent',
    'scrip_remaining',
    'has_commander',
    'commander_count',
    'max_commanders',
    'envoy_scrip_spent',
    'envoy_scrip_cap',
];
const reloadOnlyWithAssets = [...reloadOnly, 'available_assets'];

// ── Hire / remove ────────────────────────────────────────────────────────
function hireUnit(unit: UnitMin, asCommander = false) {
    router.post(
        route('tos.companies.units.add', props.company.slug),
        { unit_id: unit.id, is_commander: asCommander },
        {
            only: reloadOnly,
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                drawerOpen.value = false;
            },
        },
    );
}

function removeUnit(cu: CompanyUnit) {
    router.post(
        route('tos.companies.units.remove', [props.company.slug, cu.id]),
        {},
        {
            only: reloadOnly,
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                drawerOpen.value = false;
            },
        },
    );
}

// ── Sculpt persistence ───────────────────────────────────────────────────
function updateSculpt(cu: CompanyUnit, sculptId: number) {
    router.post(
        route('tos.companies.units.sculpt', [props.company.slug, cu.id]),
        { sculpt_id: sculptId },
        { only: reloadOnly, preserveScroll: true, preserveState: true },
    );
}

// ── Drawer state — Malifaux Crew Builder pattern ─────────────────────────
const drawerOpen = ref(false);
const drawerMode = ref<'roster' | 'pool'>('pool');
const drawerCompanyUnit = ref<CompanyUnit | null>(null);
const drawerPoolUnit = ref<UnitMin | null>(null);

const drawerUnit = computed<UnitMin | null>(() => {
    if (drawerMode.value === 'roster') return drawerCompanyUnit.value?.unit ?? null;
    return drawerPoolUnit.value;
});

const drawerSelectedSculptId = computed<number | null>(() => (drawerMode.value === 'roster' ? (drawerCompanyUnit.value?.sculpt_id ?? null) : null));

const drawerIsCommander = computed(() => drawerMode.value === 'roster' && (drawerCompanyUnit.value?.is_commander ?? false));

const drawerUnaffordable = computed(() =>
    drawerMode.value === 'pool' && drawerPoolUnit.value ? drawerPoolUnit.value.scrip > props.scrip_remaining : false,
);

function openRosterDrawer(cu: CompanyUnit) {
    drawerMode.value = 'roster';
    drawerCompanyUnit.value = cu;
    drawerPoolUnit.value = null;
    drawerOpen.value = true;
}

function openPoolDrawer(u: UnitMin) {
    drawerMode.value = 'pool';
    drawerPoolUnit.value = u;
    drawerCompanyUnit.value = null;
    drawerOpen.value = true;
}

function handleDrawerSculptChange(sculptId: number) {
    if (drawerMode.value === 'roster' && drawerCompanyUnit.value) {
        updateSculpt(drawerCompanyUnit.value, sculptId);
    }
}

function handleDrawerHire(asCommander: boolean) {
    if (drawerMode.value === 'pool' && drawerPoolUnit.value) {
        hireUnit(drawerPoolUnit.value, asCommander);
    }
}

function handleDrawerRemove() {
    if (drawerMode.value === 'roster' && drawerCompanyUnit.value) {
        removeUnit(drawerCompanyUnit.value);
    }
}

// ── Asset attach dialog ──────────────────────────────────────────────────
const assetDialogOpen = ref(false);
const assetDialogTarget = ref<CompanyUnit | null>(null);
const assetDialogFilter = ref('');

function openAssetDialog(cu: CompanyUnit) {
    assetDialogTarget.value = cu;
    assetDialogFilter.value = '';
    assetDialogOpen.value = true;
}

const filteredAssets = computed(() => {
    const targetId = assetDialogTarget.value?.id ?? null;
    const text = assetDialogFilter.value.trim().toLowerCase();

    return props.available_assets.filter((a) => {
        // Only offer Assets the targeted unit can actually take.
        if (targetId !== null && a.attachable_company_unit_ids && !a.attachable_company_unit_ids.includes(targetId)) {
            return false;
        }
        return text === '' || a.name.toLowerCase().includes(text);
    });
});

function attachAsset(asset: AssetMin) {
    if (!assetDialogTarget.value) return;
    router.post(
        route('tos.companies.assets.attach', [props.company.slug, assetDialogTarget.value.id]),
        { asset_id: asset.id },
        {
            // Refresh `available_assets` so the picker's already_attached
            // annotation reflects the new attachment without a full
            // page navigation.
            only: reloadOnlyWithAssets,
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                assetDialogOpen.value = false;
            },
        },
    );
}

function detachAsset(cu: CompanyUnit, asset: AssetMin) {
    router.post(
        route('tos.companies.assets.detach', [props.company.slug, cu.id, asset.slug]),
        {},
        { only: reloadOnlyWithAssets, preserveScroll: true, preserveState: true },
    );
}

function isAssetUnique(asset: AssetMin) {
    return (asset.limits ?? []).some((l) => l.limit_type === 'unique');
}

function slotLocations(asset: AssetMin): string[] {
    return (asset.limits ?? []).filter((l) => l.limit_type === 'slot' && l.parameter_value).map((l) => (l.parameter_value as string).toLowerCase());
}

// ── Sharing ──────────────────────────────────────────────────────────────
const shareCopied = ref(false);

function togglePublic() {
    router.post(route('tos.companies.toggle_public', props.company.slug), {}, { only: reloadOnly, preserveScroll: true, preserveState: true });
}

// ── Garrison link ────────────────────────────────────────────────────────
// Setting `garrison_id = null` drops the restriction; the picker for the
// existing roster doesn't change retroactively (Builder simply unrestricts
// going forward — same as a casual Company).
function clearGarrison() {
    // Garrison swap re-scopes the picker pools — refresh both lists so
    // the user immediately sees the unrestricted hireable + available set.
    router.post(
        route('tos.companies.set_garrison', props.company.slug),
        { garrison_id: null },
        {
            only: [...reloadOnly, 'hireable_units', 'available_assets'],
            preserveScroll: true,
            preserveState: true,
        },
    );
}

// ── Garrison picker dialog ──────────────────────────────────────────────
const garrisonPickerOpen = ref(false);
function openGarrisonPicker() {
    garrisonPickerOpen.value = true;
}
function pickGarrison(garrison: AvailableGarrison) {
    router.post(
        route('tos.companies.set_garrison', props.company.slug),
        { garrison_id: garrison.id },
        {
            // Garrison swap re-scopes the picker pools — pull both fresh.
            only: [...reloadOnly, 'hireable_units', 'available_assets'],
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                garrisonPickerOpen.value = false;
            },
        },
    );
}

const garrisonFormatLabel: Record<string, string> = {
    one_commander: 'One Commander',
    one_commander_plus_10: 'One Cmdr +10',
    two_commanders: 'Two Commanders',
    theater_of_war: 'Theater of War',
    no_mans_land: "No Man's Land",
};

// ── Stratagem deck ───────────────────────────────────────────────────────
const stratagemFilter = ref('');
const deckIds = computed(() => new Set(props.company.stratagems.map((s) => s.id)));
const envoyStratagemIds = computed(() => new Set(props.available_stratagems.filter((s) => s.deck_source === 'envoy').map((s) => s.id)));
const deckEnvoyCount = computed(() => props.company.stratagems.filter((s) => envoyStratagemIds.value.has(s.id)).length);
const deckFull = computed(() => props.company.stratagems.length >= props.stratagem_deck_size);
const envoyDeckFull = computed(() => deckEnvoyCount.value >= props.max_envoy_stratagems);

const availableStratagemList = computed(() => {
    const text = stratagemFilter.value.trim().toLowerCase();
    return props.available_stratagems.filter((s) => !deckIds.value.has(s.id) && (!text || s.name.toLowerCase().includes(text)));
});

function stratagemDisabled(s: StratagemMin): boolean {
    return deckFull.value || (s.deck_source === 'envoy' && envoyDeckFull.value);
}

function addStratagem(s: StratagemMin) {
    if (stratagemDisabled(s)) return;
    router.post(route('tos.companies.stratagems.add', props.company.slug), { stratagem_id: s.id }, { preserveScroll: true, only: reloadOnly });
}

function removeStratagem(s: StratagemMin) {
    router.post(route('tos.companies.stratagems.remove', [props.company.slug, s.slug]), {}, { preserveScroll: true, only: reloadOnly });
}

async function copyShareLink() {
    const url = window.location.origin + route('tos.companies.shared', props.company.share_code, false);
    try {
        await navigator.clipboard.writeText(url);
        shareCopied.value = true;
        setTimeout(() => (shareCopied.value = false), 1800);
    } catch {
        // Fallback: prompt the user with the URL.
        window.prompt('Copy this share link:', url);
    }
}

function downloadPdf() {
    window.open(route('tos.companies.pdf', props.company.slug), '_blank');
}

// ── Delete ───────────────────────────────────────────────────────────────
async function deleteCompany() {
    if (
        !(await confirmDialog({
            title: 'Delete Company',
            message: `Delete "${props.company.name}"? This cannot be undone.`,
            confirmLabel: 'Delete',
            destructive: true,
        }))
    ) {
        return;
    }
    router.post(route('tos.companies.delete', props.company.slug));
}
</script>

<template>
    <Head :title="`${company.name} — TOS Company`" />
    <div class="relative pb-12">
        <div
            class="pointer-events-none absolute inset-x-0 top-0 h-64 opacity-[0.07] dark:opacity-[0.12]"
            :style="{ background: 'radial-gradient(ellipse at top, hsl(var(--primary)) 0%, transparent 70%)' }"
        />

        <div class="container mx-auto space-y-3 px-3 pt-4 sm:px-4">
            <Link :href="route('tos.companies.index')" class="inline-flex items-center gap-1 text-xs text-muted-foreground hover:text-foreground">
                <ArrowLeft class="size-3" /> All Companies
            </Link>

            <!-- ═══ Header card ═══ -->
            <Card class="overflow-hidden">
                <div :class="['h-1 w-full', accentBg]" />
                <CardContent class="p-3 sm:p-4">
                    <div class="flex items-start gap-3">
                        <div class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-muted/40 ring-1 ring-border/50 sm:size-14">
                            <AllegianceLogo :allegiance="company.allegiance.slug" class-name="size-8 sm:size-10" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <h1 class="truncate text-lg font-bold leading-tight sm:text-xl">{{ company.name }}</h1>
                            <p class="truncate text-xs text-muted-foreground">
                                <Link :href="route('tos.allegiances.view', company.allegiance.slug)" class="hover:text-foreground hover:underline">{{
                                    company.allegiance.name
                                }}</Link>
                                <span class="mx-1 opacity-50">·</span>
                                <span class="capitalize">{{ company.allegiance.type }}</span>
                            </p>
                            <div
                                v-if="company.garrison"
                                class="mt-1 inline-flex items-center gap-1.5 rounded-full bg-emerald-500/10 px-2 py-0.5 text-[10px] font-medium text-emerald-700 dark:text-emerald-400"
                            >
                                <Shield class="size-3" />
                                <span>Building from</span>
                                <Link :href="route('tos.garrisons.view', company.garrison.slug)" class="font-semibold hover:underline">{{
                                    company.garrison.name
                                }}</Link>
                                <button
                                    v-if="available_garrisons.length > 1"
                                    type="button"
                                    class="ml-0.5 rounded-full px-1 text-emerald-700/60 hover:text-emerald-700 dark:text-emerald-400/60 dark:hover:text-emerald-400"
                                    title="Switch to a different Garrison"
                                    @click="openGarrisonPicker"
                                >
                                    change
                                </button>
                                <button
                                    type="button"
                                    class="ml-0.5 rounded-full text-emerald-700/60 hover:text-emerald-700 dark:text-emerald-400/60 dark:hover:text-emerald-400"
                                    title="Drop the Garrison restriction"
                                    @click="clearGarrison"
                                >
                                    <X class="size-3" />
                                </button>
                            </div>
                            <button
                                v-else-if="available_garrisons.length"
                                type="button"
                                class="mt-1 inline-flex items-center gap-1.5 rounded-full border border-dashed border-emerald-500/40 px-2 py-0.5 text-[10px] font-medium text-emerald-700 hover:bg-emerald-500/5 dark:text-emerald-400"
                                title="Restrict the hiring pool to a tournament Garrison"
                                @click="openGarrisonPicker"
                            >
                                <Shield class="size-3" />
                                Build from a Garrison…
                            </button>
                        </div>
                        <div class="flex shrink-0 items-center gap-1">
                            <Button
                                variant="ghost"
                                size="sm"
                                class="h-8 gap-1 text-xs"
                                :class="
                                    company.is_public
                                        ? 'text-emerald-600 hover:bg-emerald-500/10 hover:text-emerald-700 dark:text-emerald-400'
                                        : 'text-muted-foreground hover:text-foreground'
                                "
                                :title="company.is_public ? 'Public — click to make private' : 'Private — click to make public'"
                                @click="togglePublic"
                            >
                                <Globe v-if="company.is_public" class="size-3.5" />
                                <Lock v-else class="size-3.5" />
                                <span class="hidden sm:inline">{{ company.is_public ? 'Public' : 'Private' }}</span>
                            </Button>
                            <Button
                                variant="ghost"
                                size="sm"
                                class="h-8 gap-1 text-xs"
                                :disabled="!company.is_public"
                                :title="company.is_public ? 'Copy share link' : 'Make public to share'"
                                @click="copyShareLink"
                            >
                                <Share2 class="size-3.5" />
                                <span class="hidden sm:inline">{{ shareCopied ? 'Copied!' : 'Share' }}</span>
                            </Button>
                            <Button variant="ghost" size="sm" class="h-8 gap-1 text-xs" title="Download PDF" @click="downloadPdf">
                                <Printer class="size-3.5" />
                                <span class="hidden sm:inline">PDF</span>
                            </Button>
                            <Button
                                variant="ghost"
                                size="sm"
                                class="h-8 gap-1 text-xs text-rose-600 hover:bg-rose-500/10 hover:text-rose-700"
                                @click="deleteCompany"
                            >
                                <Trash2 class="size-3.5" />
                            </Button>
                        </div>
                    </div>

                    <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-2 text-xs sm:text-sm">
                        <div class="flex items-baseline gap-1">
                            <span class="text-muted-foreground">Scrip:</span>
                            <span class="font-semibold tabular-nums" :class="overBudget ? 'text-rose-600 dark:text-rose-400' : ''"
                                >{{ scrip_spent }} / {{ scrip_budget }}</span
                            >
                        </div>
                        <div class="flex items-baseline gap-1">
                            <span class="text-muted-foreground">Models:</span>
                            <span class="font-semibold tabular-nums">{{ totalModels }}</span>
                        </div>
                        <div class="ml-auto">
                            <Badge
                                v-if="overBudget"
                                variant="outline"
                                class="border-rose-500/40 bg-rose-500/10 text-[11px] text-rose-700 dark:text-rose-400"
                            >
                                <AlertTriangle class="mr-1 size-3" /> {{ -scrip_remaining }} over budget
                            </Badge>
                            <Badge
                                v-else-if="scrip_budget > 0"
                                variant="outline"
                                class="border-emerald-500/40 bg-emerald-500/10 text-[11px] text-emerald-700 dark:text-emerald-400"
                                >{{ scrip_remaining }} remaining</Badge
                            >
                            <Badge v-else variant="outline" class="text-[11px]">Step 1 — pick a Commander</Badge>
                        </div>
                    </div>

                    <div class="mt-2 h-2 w-full overflow-hidden rounded-full bg-muted">
                        <div class="h-full transition-all duration-300 ease-out" :class="budgetBarClass" :style="{ width: `${budgetPercent}%` }" />
                    </div>

                    <p
                        v-if="company.envoy_allegiance"
                        class="mt-1.5 text-[11px]"
                        :class="envoy_scrip_spent > envoy_scrip_cap ? 'text-rose-600' : 'text-muted-foreground'"
                    >
                        Envoy spend: {{ envoy_scrip_spent }} / {{ envoy_scrip_cap }} Scrip (50% cap)
                    </p>
                </CardContent>
            </Card>

            <!-- ═══ Allegiance + Envoy cards ═══ -->
            <div v-if="company.allegiance.allegiance_cards?.length || company.envoy_allegiance?.allegiance_cards?.length" class="rounded-md border p-4">
                <p class="mb-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Allegiance Cards</p>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div v-if="company.allegiance.allegiance_cards?.length">
                        <p class="mb-1.5 text-[11px] font-semibold">{{ company.allegiance.name }} <span class="text-muted-foreground">— Primary</span></p>
                        <div class="grid grid-cols-2 gap-2">
                            <Link v-for="c in company.allegiance.allegiance_cards" :key="c.id" :href="route('tos.allegiance_cards.view', c.slug)">
                                <CardImage :src="c.image_path" :alt="c.name" :allegiance-slug="company.allegiance.slug" :placeholder-icon="Shield" aspect-class="aspect-[5/7]" />
                            </Link>
                        </div>
                    </div>
                    <div v-if="company.envoy_allegiance?.allegiance_cards?.length">
                        <p class="mb-1.5 text-[11px] font-semibold">
                            {{ company.envoy_allegiance.name }} <span class="text-muted-foreground">— Envoy</span>
                        </p>
                        <div class="grid grid-cols-2 gap-2">
                            <Link v-for="c in company.envoy_allegiance.allegiance_cards" :key="c.id" :href="route('tos.allegiance_cards.view', c.slug)">
                                <CardImage :src="c.image_path" :alt="c.name" :allegiance-slug="company.envoy_allegiance.slug" :placeholder-icon="Shield" aspect-class="aspect-[5/7]" />
                            </Link>
                        </div>
                        <p class="mt-1.5 text-[10px] italic text-muted-foreground">Standard effects only — “Primary Only” abilities don’t apply when taken as an Envoy.</p>
                    </div>
                </div>
            </div>

            <!-- ═══ Stratagem Deck ═══ -->
            <div class="rounded-md border p-4">
                <div class="mb-3 flex items-center justify-between gap-2">
                    <p class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Stratagem Deck</p>
                    <div class="flex items-center gap-2 text-[11px] text-muted-foreground">
                        <span :class="deckFull ? 'font-semibold text-emerald-600' : ''">{{ company.stratagems.length }} / {{ stratagem_deck_size }}</span>
                        <span v-if="company.envoy_allegiance">· {{ deckEnvoyCount }} / {{ max_envoy_stratagems }} Envoy</span>
                    </div>
                </div>

                <div v-if="company.stratagems.length" class="mb-3 flex flex-wrap gap-1.5">
                    <span
                        v-for="s in company.stratagems"
                        :key="s.id"
                        class="inline-flex items-center gap-1 rounded border bg-muted/40 px-2 py-1 text-[11px]"
                    >
                        <span :class="envoyStratagemIds.has(s.id) ? 'text-sky-600 dark:text-sky-400' : ''">{{ s.name }}</span>
                        <span class="text-muted-foreground">({{ s.tactical_cost }})</span>
                        <button type="button" class="text-muted-foreground hover:text-rose-600" aria-label="Remove" @click="removeStratagem(s)">
                            <X class="size-3" />
                        </button>
                    </span>
                </div>
                <p v-else class="mb-3 text-[11px] text-muted-foreground">No Stratagems selected yet.</p>

                <Input v-model="stratagemFilter" placeholder="Search Stratagems…" class="mb-2 h-8 text-xs" />
                <div class="max-h-48 space-y-1 overflow-y-auto">
                    <button
                        v-for="s in availableStratagemList"
                        :key="s.id"
                        type="button"
                        :disabled="stratagemDisabled(s)"
                        class="flex w-full items-center justify-between gap-2 rounded border px-2 py-1 text-left text-[11px] transition hover:border-primary/40 disabled:cursor-not-allowed disabled:opacity-40"
                        @click="addStratagem(s)"
                    >
                        <span class="flex items-center gap-1.5">
                            <Plus class="size-3 shrink-0" /> {{ s.name }}
                            <Badge v-if="s.deck_source === 'envoy'" variant="outline" class="border-sky-500/40 px-1 py-0 text-[9px] text-sky-600 dark:text-sky-400">Envoy</Badge>
                        </span>
                        <span class="text-muted-foreground">{{ s.tactical_cost }}</span>
                    </button>
                    <p v-if="!availableStratagemList.length" class="py-2 text-center text-[11px] text-muted-foreground">No Stratagems match.</p>
                </div>
            </div>

            <!-- ═══ Step 1: Commander picker (shown until the format's Commander slots are full) ═══ -->
            <CompanyCommanderPicker
                v-if="canAddCommander"
                :pool="commander_pool"
                :allegiance-slug="company.allegiance.slug"
                :allegiance-name="company.allegiance.name"
                :allegiance-color-slug="company.allegiance.color_slug"
                @preview="openPoolDrawer"
                @hire="(u) => hireUnit(u, true)"
            />

            <!-- ═══ Step 2: Roster + Hiring Pool (once at least one Commander is set) ═══ -->
            <template v-if="has_commander">
                <div class="hidden lg:grid lg:grid-cols-5 lg:gap-4">
                    <div class="lg:col-span-3">
                        <CompanyRosterPane
                            :renderable-units="renderableUnits"
                            :child-by-parent="childByParentUnitId"
                            :allegiance-bg="accentBg"
                            :allegiance-slug="company.allegiance.slug"
                            :allegiance-color-slug="company.allegiance.color_slug"
                            @preview="openRosterDrawer"
                            @remove="removeUnit"
                            @attach="openAssetDialog"
                            @detach="detachAsset"
                        />
                    </div>
                    <div class="lg:col-span-2">
                        <CompanyHiringPoolPane
                            v-model:filter-text="filterText"
                            v-model:pool-filter="poolFilter"
                            v-model:pool-sort="poolSort"
                            :pool="augmentedPool"
                            :counts="filterCounts"
                            :has-commander="has_commander"
                            :scrip-remaining="scrip_remaining"
                            :allegiance-slug="company.allegiance.slug"
                            :allegiance-color-slug="company.allegiance.color_slug"
                            @preview="openPoolDrawer"
                            @hire="hireUnit"
                        />
                    </div>
                </div>

                <Tabs default-value="roster" class="lg:hidden">
                    <TabsList class="grid w-full grid-cols-2">
                        <TabsTrigger value="roster">
                            Roster
                            <Badge v-if="renderableUnits.length" variant="secondary" class="ml-1.5 px-1.5 py-0 text-[10px]">
                                {{ renderableUnits.length }}
                            </Badge>
                        </TabsTrigger>
                        <TabsTrigger value="pool">
                            Hiring Pool
                            <Badge variant="secondary" class="ml-1.5 px-1.5 py-0 text-[10px]">{{ filterCounts.all }}</Badge>
                        </TabsTrigger>
                    </TabsList>
                    <TabsContent value="roster" class="mt-3">
                        <CompanyRosterPane
                            :renderable-units="renderableUnits"
                            :child-by-parent="childByParentUnitId"
                            :allegiance-bg="accentBg"
                            :allegiance-slug="company.allegiance.slug"
                            :allegiance-color-slug="company.allegiance.color_slug"
                            @preview="openRosterDrawer"
                            @remove="removeUnit"
                            @attach="openAssetDialog"
                            @detach="detachAsset"
                        />
                    </TabsContent>
                    <TabsContent value="pool" class="mt-3">
                        <CompanyHiringPoolPane
                            v-model:filter-text="filterText"
                            v-model:pool-filter="poolFilter"
                            v-model:pool-sort="poolSort"
                            :pool="augmentedPool"
                            :counts="filterCounts"
                            :has-commander="has_commander"
                            :scrip-remaining="scrip_remaining"
                            :allegiance-slug="company.allegiance.slug"
                            :allegiance-color-slug="company.allegiance.color_slug"
                            @preview="openPoolDrawer"
                            @hire="hireUnit"
                        />
                    </TabsContent>
                </Tabs>
            </template>
        </div>

        <!-- Unit drawer — flip card + sculpt picker, used by both roster and pool -->
        <CompanyUnitDrawer
            v-model:open="drawerOpen"
            :unit="drawerUnit"
            :mode="drawerMode"
            :selected-sculpt-id="drawerSelectedSculptId"
            :is-commander="drawerIsCommander"
            :has-commander="has_commander"
            :unaffordable="drawerUnaffordable"
            :allegiance-slug="company.allegiance.slug"
            :allegiance-color-slug="company.allegiance.color_slug"
            @sculpt-change="handleDrawerSculptChange"
            @hire="handleDrawerHire"
            @remove="handleDrawerRemove"
        />

        <!-- Asset attach dialog -->
        <Dialog v-model:open="assetDialogOpen">
            <DialogContent class="max-w-lg">
                <DialogHeader>
                    <DialogTitle>Attach Asset</DialogTitle>
                    <DialogDescription>
                        <span v-if="assetDialogTarget">
                            Pick an Asset to attach to <strong>{{ assetDialogTarget.unit.name }}</strong
                            >.
                        </span>
                    </DialogDescription>
                </DialogHeader>
                <div class="relative">
                    <Search class="absolute left-2.5 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input v-model="assetDialogFilter" placeholder="Search assets…" class="pl-9" />
                </div>
                <div class="-mx-2 max-h-[50vh] overflow-y-auto px-2">
                    <div v-if="!filteredAssets.length" class="py-6 text-center text-xs text-muted-foreground">No assets match.</div>
                    <div v-else class="space-y-1">
                        <button
                            v-for="a in filteredAssets"
                            :key="a.id"
                            type="button"
                            :disabled="isAssetUnique(a) && a.already_attached"
                            class="group flex w-full items-center gap-2 rounded-md border bg-card px-3 py-2 text-left text-xs transition-colors hover:border-primary/40 hover:bg-accent disabled:cursor-not-allowed disabled:opacity-50"
                            @click="attachAsset(a)"
                        >
                            <Package class="size-4 shrink-0 text-muted-foreground" />
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-1.5">
                                    <span class="truncate font-medium">{{ a.name }}</span>
                                    <Badge v-if="isAssetUnique(a)" variant="outline" class="px-1 py-0 text-[9px]">Unique</Badge>
                                    <Badge
                                        v-if="a.already_attached"
                                        variant="outline"
                                        class="border-amber-500/40 bg-amber-500/10 px-1 py-0 text-[9px] text-amber-700 dark:text-amber-400"
                                        >In Company</Badge
                                    >
                                </div>
                                <div v-if="slotLocations(a).length" class="mt-0.5 flex gap-1">
                                    <span
                                        v-for="loc in slotLocations(a)"
                                        :key="loc"
                                        class="text-[9px] uppercase tracking-wider text-muted-foreground"
                                        >{{ loc }}</span
                                    >
                                </div>
                            </div>
                            <span class="shrink-0 text-[11px] tabular-nums text-muted-foreground">{{ a.scrip_cost }}s</span>
                            <Plus class="size-4 shrink-0 text-muted-foreground transition-colors group-hover:text-primary" />
                        </button>
                    </div>
                </div>
                <DialogFooter>
                    <Button variant="ghost" @click="assetDialogOpen = false">Cancel</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Garrison picker — set or swap the Garrison this Company is built from. -->
        <Dialog v-model:open="garrisonPickerOpen">
            <DialogContent class="max-w-lg">
                <DialogHeader>
                    <DialogTitle>
                        <span v-if="company.garrison">Switch Garrison</span>
                        <span v-else>Build from a Garrison</span>
                    </DialogTitle>
                    <DialogDescription>
                        Restricts the hiring pool to one of your tournament Garrisons. Allegiance stays locked to {{ company.allegiance.name }}.
                    </DialogDescription>
                </DialogHeader>
                <div class="space-y-2 py-2">
                    <div v-if="!available_garrisons.length" class="rounded-md border border-dashed p-6 text-center text-xs text-muted-foreground">
                        No Garrisons of this Allegiance yet.
                        <Link :href="route('tos.garrisons.create')" class="underline hover:text-foreground">Create one →</Link>
                    </div>
                    <button
                        v-for="g in available_garrisons"
                        :key="g.id"
                        type="button"
                        :disabled="company.garrison?.id === g.id"
                        :class="[
                            'flex w-full items-center gap-3 rounded-lg border p-3 text-left text-xs transition-all',
                            company.garrison?.id === g.id
                                ? 'cursor-not-allowed border-primary bg-primary/5 opacity-60 ring-1 ring-primary/40'
                                : 'hover:-translate-y-0.5 hover:border-primary/30 hover:shadow-sm',
                        ]"
                        @click="pickGarrison(g)"
                    >
                        <div
                            class="flex size-9 shrink-0 items-center justify-center rounded-md bg-emerald-500/10 text-emerald-600 dark:text-emerald-400"
                        >
                            <Shield class="size-4" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-[12px] font-semibold">{{ g.name }}</p>
                            <p class="truncate text-[10px] text-muted-foreground">{{ garrisonFormatLabel[g.format] ?? g.format }}</p>
                        </div>
                        <span v-if="company.garrison?.id === g.id" class="text-[10px] font-medium text-primary">current</span>
                    </button>
                </div>
                <DialogFooter>
                    <Button variant="ghost" @click="garrisonPickerOpen = false">Cancel</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
