<script setup lang="ts">
import PageBanner from '@/components/PageBanner.vue';
import SearchableSelect from '@/components/SearchableSelect.vue';
import { Badge } from '@/components/ui/badge';
import Button from '@/components/ui/button/Button.vue';
import { Card, CardContent } from '@/components/ui/card';
import { Head, Link, router } from '@inertiajs/vue3';
import { AlertTriangle, ArrowLeft, Crown, Lock, Plus, ShieldAlert, Trash2, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';

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

const unitToAdd = ref<string | null>(null);
const addAsCommander = ref(false);
const assetSelections = ref<Record<number, string | null>>({});

const unitOptions = computed(() =>
    props.hireable_units.map((u) => {
        const cat = u.hire_category === 'neutral' ? '· Neutral' : '';
        const cost = `(${u.scrip}s)`;
        const label = `${u.name}${u.title ? ` — ${u.title}` : ''} ${cost} ${cat}`.trim();
        return { value: String(u.id), name: label };
    }),
);

const assetOptions = computed(() =>
    props.available_assets.map((a) => {
        const isUnique = (a.limits ?? []).some((l) => l.limit_type === 'unique');
        const taken = a.already_attached && isUnique ? ' · already in Company' : '';
        const tag = isUnique ? ' · Unique' : '';
        return { value: String(a.id), name: `${a.name} (${a.scrip_cost}s)${tag}${taken}` };
    }),
);

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

// Combined Arms parent → children pairing — used to nest the auto-attached
// child rows under their parent in the rendered list, mirroring how the
// rulebook prints the two cards side-by-side.
const childByParentUnitId = computed(() => {
    const map = new Map<number, CompanyUnit>();
    for (const cu of props.company.company_units) {
        if (cu.is_combined_arms_child) {
            const parent = props.company.company_units.find((p) => p.unit.combined_arms_child_id === cu.unit.id && !p.is_combined_arms_child);
            if (parent) {
                map.set(parent.unit.id, cu);
            }
        }
    }
    return map;
});

const renderableUnits = computed(() => props.company.company_units.filter((cu) => !cu.is_combined_arms_child));

function addUnit() {
    if (!unitToAdd.value) return;
    router.post(
        route('tos.companies.units.add', props.company.slug),
        { unit_id: Number(unitToAdd.value), is_commander: addAsCommander.value },
        {
            preserveScroll: true,
            onSuccess: () => {
                unitToAdd.value = null;
                addAsCommander.value = false;
            },
        },
    );
}

function removeUnit(cu: CompanyUnit) {
    router.post(route('tos.companies.units.remove', [props.company.slug, cu.id]), {}, { preserveScroll: true });
}

function attachAsset(cu: CompanyUnit) {
    const id = assetSelections.value[cu.id];
    if (!id) return;
    router.post(
        route('tos.companies.assets.attach', [props.company.slug, cu.id]),
        { asset_id: Number(id) },
        {
            preserveScroll: true,
            onSuccess: () => {
                assetSelections.value[cu.id] = null;
            },
        },
    );
}

function detachAsset(cu: CompanyUnit, asset: AssetMin) {
    router.post(route('tos.companies.assets.detach', [props.company.slug, cu.id, asset.slug]), {}, { preserveScroll: true });
}

function deleteCompany() {
    if (!confirm(`Delete "${props.company.name}"? This cannot be undone.`)) return;
    router.post(route('tos.companies.delete', props.company.slug));
}

function slotLocations(asset: AssetMin): string[] {
    return (asset.limits ?? [])
        .filter((l) => l.limit_type === 'slot' && l.parameter_value)
        .map((l) => (l.parameter_value as string).toLowerCase());
}
</script>

<template>
    <Head :title="`${company.name} — TOS Company`" />
    <div class="relative">
        <PageBanner :title="company.name" class="mb-2">
            <template #subtitle>
                <div class="my-auto flex items-center gap-2 px-2 py-0 text-xs text-muted-foreground md:py-2 md:text-sm md:text-foreground">
                    <span>{{ company.allegiance.name }}</span>
                    <span class="capitalize">· {{ company.allegiance.type }}</span>
                </div>
            </template>
        </PageBanner>

        <div class="container mx-auto space-y-4 sm:px-4">
            <div class="flex items-center justify-between gap-2">
                <Link :href="route('tos.companies.index')" class="inline-flex items-center gap-1 text-xs text-muted-foreground hover:text-foreground">
                    <ArrowLeft class="size-3" /> All Companies
                </Link>
                <Button variant="ghost" size="sm" class="gap-1 text-xs text-rose-600 hover:bg-rose-500/10" @click="deleteCompany">
                    <Trash2 class="size-3" /> Delete
                </Button>
            </div>

            <!-- Validity warnings — Commander missing is the showstopper -->
            <div
                v-if="!has_commander"
                class="flex items-start gap-2 rounded-md border border-amber-500/40 bg-amber-500/10 p-3 text-sm text-amber-800 dark:text-amber-300"
            >
                <ShieldAlert class="mt-0.5 size-4 shrink-0" />
                <div>
                    <p class="font-medium">Commander not set</p>
                    <p class="text-xs opacity-90">
                        A Company needs exactly one Commander to provide its Scrip budget. Add a Commander unit
                        below and toggle "Commander" before clicking Add.
                    </p>
                </div>
            </div>

            <!-- Budget bar — Malifaux Crew Builder parity. Shows budget,
                 spent, remaining, and a coloured progress bar. -->
            <Card>
                <CardContent class="p-4">
                    <div class="mb-2 flex flex-wrap items-baseline justify-between gap-2 text-sm">
                        <div class="flex items-baseline gap-3">
                            <span class="font-semibold">Scrip Budget</span>
                            <span class="tabular-nums text-muted-foreground">
                                <span class="font-medium text-foreground">{{ scrip_spent }}</span>
                                /
                                <span class="font-medium text-foreground">{{ scrip_budget }}</span>
                                spent
                            </span>
                        </div>
                        <Badge
                            :class="overBudget ? 'bg-rose-500/10 text-rose-700 dark:text-rose-400' : 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400'"
                            variant="outline"
                        >
                            <template v-if="overBudget">
                                <AlertTriangle class="mr-1 size-3" /> {{ -scrip_remaining }} over
                            </template>
                            <template v-else>{{ scrip_remaining }} remaining</template>
                        </Badge>
                    </div>
                    <div class="h-2 w-full overflow-hidden rounded-full bg-muted">
                        <div
                            class="h-full transition-all duration-300 ease-out"
                            :class="budgetBarClass"
                            :style="{ width: `${budgetPercent}%` }"
                        />
                    </div>
                    <p v-if="scrip_budget === 0" class="mt-2 text-[11px] italic text-muted-foreground">
                        Hire a Commander to set the Scrip budget.
                    </p>
                </CardContent>
            </Card>

            <!-- Unit picker -->
            <Card>
                <CardContent class="space-y-3 p-4">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">Add a unit</p>
                    <div class="flex flex-wrap items-center gap-2">
                        <div class="min-w-[260px] flex-1">
                            <SearchableSelect v-model="unitToAdd" :options="unitOptions" placeholder="Choose a hireable unit…" />
                        </div>
                        <label v-if="!has_commander" class="flex items-center gap-1 text-xs text-muted-foreground">
                            <input v-model="addAsCommander" type="checkbox" class="rounded" />
                            Add as Commander
                        </label>
                        <Button :disabled="!unitToAdd" size="sm" class="gap-1" @click="addUnit">
                            <Plus class="size-3" /> Add
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <div v-if="!renderableUnits.length" class="rounded-md border border-dashed p-6 text-center text-xs text-muted-foreground">
                No units in this Company yet. Pick a Commander first to set the Scrip budget.
            </div>

            <!-- Company unit list -->
            <Card v-for="cu in renderableUnits" :key="cu.id" class="overflow-hidden">
                <CardContent class="space-y-2 p-4">
                    <!-- Parent unit row -->
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-baseline gap-2">
                                <Crown v-if="cu.is_commander" class="size-3 shrink-0 text-amber-500" />
                                <span class="text-sm font-semibold">{{ cu.unit.name }}</span>
                                <span v-if="cu.unit.title" class="text-[11px] italic text-muted-foreground">{{ cu.unit.title }}</span>
                                <Badge v-if="cu.is_commander" class="bg-amber-500/10 text-[9px] text-amber-700 dark:text-amber-400">Commander</Badge>
                                <Badge v-if="cu.unit.restriction" variant="outline" class="text-[9px] capitalize">Neutral {{ cu.unit.restriction }}</Badge>
                                <Badge v-for="r in cu.unit.special_unit_rules" :key="r.id" variant="outline" class="text-[9px]">{{ r.name }}</Badge>
                            </div>
                            <p class="text-[11px] text-muted-foreground">
                                <span :class="cu.is_commander ? 'text-emerald-600 dark:text-emerald-400' : ''">
                                    {{ cu.is_commander ? `+${cu.unit.scrip}` : `${cu.unit.scrip}` }} Scrip
                                </span>
                            </p>
                        </div>
                        <button
                            type="button"
                            class="rounded p-1 text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                            aria-label="Remove unit"
                            @click="removeUnit(cu)"
                        >
                            <X class="size-3.5" />
                        </button>
                    </div>

                    <!-- Combined Arms child — rendered nested, can't be removed independently -->
                    <div
                        v-if="childByParentUnitId.get(cu.unit.id)"
                        class="ml-4 rounded border-l-2 border-amber-500/40 bg-amber-500/5 p-2"
                    >
                        <div class="flex items-center gap-2 text-xs">
                            <Lock class="size-3 text-amber-600 dark:text-amber-400" />
                            <span class="font-medium">{{ childByParentUnitId.get(cu.unit.id)?.unit.name }}</span>
                            <Badge class="bg-amber-500/10 text-[9px] text-amber-700 dark:text-amber-400">Combined Arms</Badge>
                            <span class="text-[10px] italic text-muted-foreground">Auto-attached with parent</span>
                        </div>
                    </div>

                    <!-- Attached assets -->
                    <div v-if="cu.assets.length" class="flex flex-wrap gap-1">
                        <Badge v-for="a in cu.assets" :key="a.id" variant="outline" class="text-[10px]">
                            {{ a.name }} ({{ a.scrip_cost }}s)
                            <span v-for="loc in slotLocations(a)" :key="loc" class="ml-1 text-[9px] uppercase tracking-wider text-muted-foreground">{{ loc }}</span>
                            <button type="button" class="ml-1 text-muted-foreground hover:text-rose-600" @click="detachAsset(cu, a)">×</button>
                        </Badge>
                    </div>

                    <div class="flex items-center gap-2">
                        <div class="min-w-[200px] flex-1">
                            <SearchableSelect v-model="assetSelections[cu.id]" :options="assetOptions" placeholder="Attach an Asset…" />
                        </div>
                        <Button :disabled="!assetSelections[cu.id]" size="sm" variant="outline" class="text-xs" @click="attachAsset(cu)">
                            Attach
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
