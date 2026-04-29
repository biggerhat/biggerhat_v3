<script setup lang="ts">
import AllegianceLogo from '@/components/AllegianceLogo.vue';
import CompanyHiringPoolPane from '@/components/TOS/CompanyHiringPoolPane.vue';
import CompanyRosterPane from '@/components/TOS/CompanyRosterPane.vue';
import { Badge } from '@/components/ui/badge';
import Button from '@/components/ui/button/Button.vue';
import { Card, CardContent } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useConfirm } from '@/composables/useConfirm';
import { Head, Link, router } from '@inertiajs/vue3';
import { AlertTriangle, ArrowLeft, Package, Plus, Search, ShieldAlert, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const confirmDialog = useConfirm();

interface Allegiance {
    id: number;
    slug: string;
    name: string;
    type: string;
    color_slug: string | null;
}

interface SpecialRule {
    id: number;
    slug: string;
    name: string;
}

interface Sculpt {
    id: number;
    slug: string;
}

interface UnitMin {
    id: number;
    slug: string;
    name: string;
    title: string | null;
    scrip: number;
    restriction: string | null;
    combined_arms_child_id: number | null;
    special_unit_rules: SpecialRule[];
    sculpts?: Sculpt[];
    hire_category?: 'direct' | 'neutral';
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
}

interface CompanyUnit {
    id: number;
    is_commander: boolean;
    is_combined_arms_child: boolean;
    position: number;
    unit: UnitMin;
    assets: AssetMin[];
}

interface Company {
    id: number;
    slug: string;
    name: string;
    allegiance: Allegiance;
    notes: string | null;
    company_units: CompanyUnit[];
}

const props = defineProps<{
    company: Company;
    scrip_budget: number;
    scrip_spent: number;
    scrip_remaining: number;
    has_commander: boolean;
    hireable_units: UnitMin[];
    available_assets: AssetMin[];
}>();

// ── Hiring pool state ────────────────────────────────────────────────────
const filterText = ref('');
type PoolFilter = 'all' | 'direct' | 'neutral' | 'commander';
const poolFilter = ref<PoolFilter>('all');
type PoolSort = 'name' | 'scrip';
const poolSort = ref<PoolSort>('name');

const isCommanderEligible = (u: UnitMin) => u.special_unit_rules.some((r) => r.slug === 'commander');

const augmentedPool = computed(() => {
    const text = filterText.value.trim().toLowerCase();
    const filtered = props.hireable_units.filter((u) => {
        if (text && !u.name.toLowerCase().includes(text) && !(u.title?.toLowerCase().includes(text) ?? false)) {
            return false;
        }
        switch (poolFilter.value) {
            case 'direct':
                return u.hire_category === 'direct';
            case 'neutral':
                return u.hire_category === 'neutral';
            case 'commander':
                return isCommanderEligible(u);
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
    all: props.hireable_units.length,
    direct: props.hireable_units.filter((u) => u.hire_category === 'direct').length,
    neutral: props.hireable_units.filter((u) => u.hire_category === 'neutral').length,
    commander: props.hireable_units.filter(isCommanderEligible).length,
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

// ── Roster helpers — Commander pinned, then by position ──────────────────
const childByParentUnitId = computed(() => {
    const map = new Map<number, CompanyUnit>();
    for (const cu of props.company.company_units) {
        if (cu.is_combined_arms_child) {
            const parent = props.company.company_units.find(
                (p) => p.unit.combined_arms_child_id === cu.unit.id && !p.is_combined_arms_child,
            );
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

const accentBg = computed(() =>
    props.company.allegiance.color_slug ? `bg-${props.company.allegiance.color_slug}` : 'bg-primary/40',
);

// ── Hire / remove ────────────────────────────────────────────────────────
function hireUnit(unit: UnitMin, asCommander = false) {
    router.post(
        route('tos.companies.units.add', props.company.slug),
        { unit_id: unit.id, is_commander: asCommander },
        { preserveScroll: true },
    );
}

function removeUnit(cu: CompanyUnit) {
    router.post(route('tos.companies.units.remove', [props.company.slug, cu.id]), {}, { preserveScroll: true });
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
    const text = assetDialogFilter.value.trim().toLowerCase();
    if (!text) return props.available_assets;
    return props.available_assets.filter((a) => a.name.toLowerCase().includes(text));
});

function attachAsset(asset: AssetMin) {
    if (!assetDialogTarget.value) return;
    router.post(
        route('tos.companies.assets.attach', [props.company.slug, assetDialogTarget.value.id]),
        { asset_id: asset.id },
        {
            preserveScroll: true,
            onSuccess: () => {
                assetDialogOpen.value = false;
            },
        },
    );
}

function detachAsset(cu: CompanyUnit, asset: AssetMin) {
    router.post(route('tos.companies.assets.detach', [props.company.slug, cu.id, asset.slug]), {}, { preserveScroll: true });
}

function isAssetUnique(asset: AssetMin) {
    return (asset.limits ?? []).some((l) => l.limit_type === 'unique');
}

function slotLocations(asset: AssetMin): string[] {
    return (asset.limits ?? [])
        .filter((l) => l.limit_type === 'slot' && l.parameter_value)
        .map((l) => (l.parameter_value as string).toLowerCase());
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
            <Link
                :href="route('tos.companies.index')"
                class="inline-flex items-center gap-1 text-xs text-muted-foreground hover:text-foreground"
            >
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
                                <Link
                                    :href="route('tos.allegiances.view', company.allegiance.slug)"
                                    class="hover:text-foreground hover:underline"
                                >{{ company.allegiance.name }}</Link>
                                <span class="mx-1 opacity-50">·</span>
                                <span class="capitalize">{{ company.allegiance.type }}</span>
                            </p>
                        </div>
                        <Button
                            variant="ghost"
                            size="sm"
                            class="h-8 gap-1 text-xs text-rose-600 hover:bg-rose-500/10 hover:text-rose-700"
                            @click="deleteCompany"
                        >
                            <Trash2 class="size-3.5" />
                            <span class="hidden sm:inline">Delete</span>
                        </Button>
                    </div>

                    <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-2 text-xs sm:text-sm">
                        <div class="flex items-baseline gap-1">
                            <span class="text-muted-foreground">Scrip:</span>
                            <span
                                class="font-semibold tabular-nums"
                                :class="overBudget ? 'text-rose-600 dark:text-rose-400' : ''"
                            >{{ scrip_spent }} / {{ scrip_budget }}</span>
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
                            >{{ scrip_remaining }} remaining</Badge>
                            <Badge v-else variant="outline" class="text-[11px]">No Commander yet</Badge>
                        </div>
                    </div>

                    <div class="mt-2 h-2 w-full overflow-hidden rounded-full bg-muted">
                        <div
                            class="h-full transition-all duration-300 ease-out"
                            :class="budgetBarClass"
                            :style="{ width: `${budgetPercent}%` }"
                        />
                    </div>
                </CardContent>
            </Card>

            <!-- Validity: Commander missing -->
            <div
                v-if="!has_commander"
                class="flex items-start gap-2 rounded-md border border-amber-500/40 bg-amber-500/10 p-3 text-sm text-amber-800 dark:text-amber-300"
            >
                <ShieldAlert class="mt-0.5 size-4 shrink-0" />
                <div class="min-w-0">
                    <p class="font-medium">Commander not set</p>
                    <p class="text-xs opacity-90">
                        A Company needs exactly one Commander to provide its Scrip budget. Use the Hiring Pool below to add a Commander
                        unit (the crown icon adds them in the right role).
                    </p>
                </div>
            </div>

            <!-- ═══ Two-pane (lg+) / Tabs (mobile) ═══ -->
            <div class="hidden lg:grid lg:grid-cols-5 lg:gap-4">
                <div class="lg:col-span-3">
                    <CompanyRosterPane
                        :renderable-units="renderableUnits"
                        :child-by-parent="childByParentUnitId"
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
                        @hire="hireUnit"
                    />
                </TabsContent>
            </Tabs>
        </div>

        <!-- Asset attach dialog -->
        <Dialog v-model:open="assetDialogOpen">
            <DialogContent class="max-w-lg">
                <DialogHeader>
                    <DialogTitle>Attach Asset</DialogTitle>
                    <DialogDescription>
                        <span v-if="assetDialogTarget">
                            Pick an Asset to attach to <strong>{{ assetDialogTarget.unit.name }}</strong>.
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
                                    >In Company</Badge>
                                </div>
                                <div v-if="slotLocations(a).length" class="mt-0.5 flex gap-1">
                                    <span
                                        v-for="loc in slotLocations(a)"
                                        :key="loc"
                                        class="text-[9px] uppercase tracking-wider text-muted-foreground"
                                    >{{ loc }}</span>
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
    </div>
</template>
