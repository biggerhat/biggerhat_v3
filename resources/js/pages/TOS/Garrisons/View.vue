<script setup lang="ts">
import AllegianceLogo from '@/components/AllegianceLogo.vue';
import EmptyState from '@/components/EmptyState.vue';
import PageBanner from '@/components/PageBanner.vue';
import CardImage from '@/components/TOS/CardImage.vue';
import FlipCard from '@/components/TOS/FlipCard.vue';
import GarrisonPicker, { type PickerKind } from '@/components/TOS/GarrisonPicker.vue';
import TosText from '@/components/TosText.vue';
import { Badge } from '@/components/ui/badge';
import Button from '@/components/ui/button/Button.vue';
import { Card, CardContent } from '@/components/ui/card';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import { useConfirm } from '@/composables/useConfirm';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowLeft,
    Crown,
    Download,
    Globe,
    Images,
    Lock,
    Minus,
    Newspaper,
    Package,
    Pencil,
    Plus,
    ScrollText,
    Swords,
    Trash2,
    UserMinus,
    X,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

type GarrisonFormat = 'one_commander' | 'one_commander_plus_10' | 'two_commanders' | 'theater_of_war' | 'no_mans_land';

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
    name: string | null;
    front_image: string | null;
    back_image: string | null;
    combination_image: string | null;
}

interface Unit {
    id: number;
    slug: string;
    name: string;
    title: string | null;
    scrip: number;
    restriction: string | null;
    special_unit_rules: SpecialRule[];
    sculpts: Sculpt[];
}

interface GarrisonUnit {
    id: number;
    is_commander: boolean;
    sculpt_id: number | null;
    position: number;
    unit: Unit;
}

interface AssetLimit {
    id: number;
    limit_type: string;
    parameter_type: string | null;
    parameter_value: string | null;
}

interface AssetPivot {
    quantity: number;
}

interface Asset {
    id: number;
    slug: string;
    name: string;
    scrip_cost: number;
    image_path: string | null;
    pivot: AssetPivot;
    limits: AssetLimit[];
}

interface Stratagem {
    id: number;
    slug: string;
    name: string;
    tactical_cost: number;
    effect: string | null;
    image_path: string | null;
    allegiance_id: number | null;
    allegiance_type: string | null;
    allegiance: { id: number; slug: string; name: string } | null;
}

interface EnvoyCard {
    id: number;
    slug: string;
    name: string;
    image_path: string | null;
    allegiance: { id: number; slug: string; name: string } | null;
}

interface Garrison {
    id: number;
    slug: string;
    name: string;
    format: GarrisonFormat;
    notes: string | null;
    is_public: boolean;
    share_code: string;
    allegiance: Allegiance;
    garrison_units: GarrisonUnit[];
    assets: Asset[];
    stratagems: Stratagem[];
    envoys: EnvoyCard[];
}

interface FormatMeta {
    value: GarrisonFormat;
    label: string;
    description: string;
    max_commanders: number;
    scrip_budget: number;
    stratagem_count: number;
    envoy_count: number;
}

interface HireableUnit {
    id: number;
    slug: string;
    name: string;
    title: string | null;
    scrip: number;
    restriction: string | null;
    special_unit_rules: SpecialRule[];
    sculpts: Sculpt[];
    allegiances: Array<{ id: number }>;
}

interface AvailableAsset {
    id: number;
    slug: string;
    name: string;
    scrip_cost: number;
    image_path: string | null;
    limits: AssetLimit[];
}

interface AvailableStratagem {
    id: number;
    slug: string;
    name: string;
    tactical_cost: number;
    effect: string | null;
    image_path: string | null;
    allegiance_id: number | null;
    allegiance_type: string | null;
    allegiance: { id: number; slug: string; name: string } | null;
}

interface AvailableEnvoy {
    id: number;
    slug: string;
    name: string;
    image_path: string | null;
}

interface FormatOption {
    name: string;
    value: GarrisonFormat;
    description: string;
}

const props = defineProps<{
    garrison: Garrison;
    format: FormatMeta;
    scrip_spent: number;
    scrip_remaining: number;
    violations: string[];
    hireable_units: HireableUnit[];
    available_assets: AvailableAsset[];
    available_stratagems: AvailableStratagem[];
    available_envoys: AvailableEnvoy[];
    format_options: FormatOption[];
}>();

type ResourceTab = 'all' | 'commanders' | 'units' | 'assets' | 'stratagems' | 'envoys';

const activeTab = ref<ResourceTab>('all');

const commanderUnits = computed(() => props.garrison.garrison_units.filter((gu) => gu.is_commander));
const minionUnits = computed(() => props.garrison.garrison_units.filter((gu) => !gu.is_commander));

const tabs: Array<{ key: ResourceTab; label: string; count: number; cap: number | null }> = [];
const tabsList = computed<typeof tabs>(() => [
    {
        key: 'all',
        label: 'All',
        count: props.garrison.garrison_units.length + props.garrison.assets.length + props.garrison.stratagems.length + props.garrison.envoys.length,
        cap: null,
    },
    { key: 'commanders', label: 'Commanders', count: commanderUnits.value.length, cap: props.format.max_commanders },
    { key: 'units', label: 'Units', count: minionUnits.value.length, cap: null },
    { key: 'assets', label: 'Assets', count: props.garrison.assets.reduce((n, a) => n + a.pivot.quantity, 0), cap: null },
    { key: 'stratagems', label: 'Stratagems', count: props.garrison.stratagems.length, cap: props.format.stratagem_count },
    { key: 'envoys', label: 'Envoys', count: props.garrison.envoys.length, cap: props.format.envoy_count },
]);

const showCommanders = computed(() => activeTab.value === 'all' || activeTab.value === 'commanders');
const showUnits = computed(() => activeTab.value === 'all' || activeTab.value === 'units');
const showAssets = computed(() => activeTab.value === 'all' || activeTab.value === 'assets');
const showStratagems = computed(() => activeTab.value === 'all' || activeTab.value === 'stratagems');
const showEnvoys = computed(() => activeTab.value === 'all' || activeTab.value === 'envoys');

const accentBg = computed(() => (props.garrison.allegiance.color_slug ? `bg-${props.garrison.allegiance.color_slug}` : 'bg-primary/40'));

const budgetPercent = computed(() => {
    if (props.format.scrip_budget <= 0) return 0;
    return Math.min(100, Math.round((props.scrip_spent / props.format.scrip_budget) * 100));
});
const overBudget = computed(() => props.scrip_remaining < 0);
const budgetBarClass = computed(() => {
    if (overBudget.value) return 'bg-rose-500';
    if (budgetPercent.value >= 90) return 'bg-amber-500';
    return 'bg-emerald-500';
});

const stratagemScopeLabel = (s: Stratagem): string => {
    if (s.allegiance) return s.allegiance.name;
    if (s.allegiance_type) return `Any ${s.allegiance_type} allegiance`;
    return 'Universal';
};

const limitLabel = (l: AssetLimit): string => {
    const head = l.limit_type.charAt(0).toUpperCase() + l.limit_type.slice(1);
    return l.parameter_value ? `${head} (${l.parameter_value})` : head;
};

const { confirm } = useConfirm();

function togglePublic() {
    router.post(
        route('tos.garrisons.toggle_public', props.garrison.slug),
        {},
        {
            preserveScroll: true,
        },
    );
}

async function deleteGarrison() {
    const ok = await confirm({
        title: `Delete "${props.garrison.name}"?`,
        message: 'This permanently removes the Garrison and everything in its pool. The action cannot be undone.',
        confirmLabel: 'Delete Garrison',
        confirmVariant: 'destructive',
    });
    if (!ok) return;
    router.post(route('tos.garrisons.delete', props.garrison.slug));
}

function activeSculpt(gu: GarrisonUnit): Sculpt | null {
    if (!gu.unit.sculpts?.length) return null;
    return gu.unit.sculpts.find((s) => s.id === gu.sculpt_id) ?? gu.unit.sculpts[0] ?? null;
}

void tabs;

// ── Picker state ──────────────────────────────────────────────────────
//
// The Sheet, search, and per-row layout live in `<GarrisonPicker>`. View
// only owns which kind is currently open and dispatches add events to
// the server. Cap / scrip / same-name disabled state is computed inside
// the component from `pool*` props.

const pickerOpen = ref<PickerKind>(null);
const openPicker = (kind: Exclude<PickerKind, null>) => {
    pickerOpen.value = kind;
};

// Cap-reached state at the top-level too — the View's "Add" buttons
// disable based on them before the picker even opens.
const stratagemCapReached = computed(() => props.garrison.stratagems.length >= props.format.stratagem_count);
const envoySlotFilled = computed(() => props.garrison.envoys.length >= props.format.envoy_count);

const reloadOnly = ['garrison', 'format', 'scrip_spent', 'scrip_remaining', 'violations'];
const visit = (url: string, data: Record<string, unknown> = {}) => {
    router.post(url, data, {
        only: reloadOnly,
        preserveScroll: true,
        preserveState: true,
    });
};

const addUnit = (unit: HireableUnit, asCommander: boolean) => {
    visit(route('tos.garrisons.units.add', props.garrison.slug), {
        unit_id: unit.id,
        is_commander: asCommander,
    });
};
const removeUnit = (gu: GarrisonUnit) => {
    visit(route('tos.garrisons.units.remove', [props.garrison.slug, gu.id]));
};

const stepAsset = (asset: AvailableAsset | Asset, delta: number) => {
    visit(route('tos.garrisons.assets.attach', props.garrison.slug), {
        asset_id: asset.id,
        delta,
    });
};

const pickStratagem = (s: AvailableStratagem) => {
    visit(route('tos.garrisons.stratagems.pick', props.garrison.slug), {
        stratagem_id: s.id,
    });
};
const unpickStratagem = (s: Stratagem) => {
    visit(route('tos.garrisons.stratagems.unpick', [props.garrison.slug, s.slug]));
};

const pickEnvoy = (c: AvailableEnvoy) => {
    visit(route('tos.garrisons.envoys.pick', props.garrison.slug), {
        allegiance_card_id: c.id,
    });
};
const unpickEnvoy = (c: EnvoyCard) => {
    visit(route('tos.garrisons.envoys.unpick', [props.garrison.slug, c.slug]));
};

// ── Edit dialog ───────────────────────────────────────────────────────
//
// Wires the existing tos.garrisons.update endpoint. Format swaps are
// allowed even if they make the current pool illegal — Garrison::violations()
// will flag the over-cap state on the next render and the user can prune.
// (Refusing the swap server-side would be more conservative but cuts off
// a legitimate workflow: reformat to a smaller event, then trim to fit.)

const editOpen = ref(false);
const editForm = useForm({
    name: props.garrison.name,
    format: props.garrison.format,
    notes: props.garrison.notes ?? '',
});

const openEdit = () => {
    editForm.name = props.garrison.name;
    editForm.format = props.garrison.format;
    editForm.notes = props.garrison.notes ?? '';
    editOpen.value = true;
};

const submitEdit = () => {
    editForm.post(route('tos.garrisons.update', props.garrison.slug), {
        preserveScroll: true,
        onSuccess: () => {
            editOpen.value = false;
        },
    });
};

const formatChanged = computed(() => editForm.format !== props.garrison.format);

// ── Sculpt picker ─────────────────────────────────────────────────────

const sculptDialogTarget = ref<GarrisonUnit | null>(null);
const sculptDialogOpen = computed({
    get: () => sculptDialogTarget.value !== null,
    set: (v: boolean) => {
        if (!v) sculptDialogTarget.value = null;
    },
});

const openSculptDialog = (gu: GarrisonUnit) => {
    sculptDialogTarget.value = gu;
};

const chooseSculpt = (gu: GarrisonUnit, sculptId: number | null) => {
    router.post(
        route('tos.garrisons.units.sculpt', [props.garrison.slug, gu.id]),
        {
            sculpt_id: sculptId,
        },
        {
            only: reloadOnly,
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                sculptDialogTarget.value = null;
            },
        },
    );
};
</script>

<template>
    <Head :title="`${garrison.name} — Garrison`" />
    <div class="relative pb-12">
        <div
            class="pointer-events-none absolute inset-x-0 top-0 h-64 opacity-[0.07] dark:opacity-[0.12]"
            :style="{ background: 'radial-gradient(ellipse at top, hsl(var(--primary)) 0%, transparent 70%)' }"
        />

        <PageBanner :title="garrison.name" class="mb-2">
            <template #logo>
                <div class="w-20 md:w-32">
                    <AllegianceLogo :allegiance="garrison.allegiance.slug" class-name="mx-auto my-auto h-16 w-16 md:h-20 md:w-20" />
                </div>
            </template>
            <template #subtitle>
                <div
                    class="my-auto flex flex-wrap items-center gap-x-1 px-2 py-0 text-xs text-muted-foreground md:py-2 md:text-sm md:text-foreground"
                >
                    <Link :href="route('tos.allegiances.view', garrison.allegiance.slug)" class="hover:text-foreground hover:underline">{{
                        garrison.allegiance.name
                    }}</Link>
                    <span class="text-muted-foreground/50">&middot;</span>
                    <span>{{ format.label }}</span>
                </div>
            </template>
        </PageBanner>

        <div class="container mx-auto space-y-3 sm:px-4">
            <Link :href="route('tos.garrisons.index')" class="inline-flex items-center gap-1 text-xs text-muted-foreground hover:text-foreground">
                <ArrowLeft class="size-3" /> All Garrisons
            </Link>

            <!-- Header card: format + budget + actions -->
            <Card class="overflow-hidden">
                <div :class="['h-1 w-full', accentBg]" />
                <CardContent class="space-y-3 p-3 sm:p-4">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0 flex-1">
                            <p class="text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">Format</p>
                            <p class="text-sm font-semibold">{{ format.label }}</p>
                            <p class="mt-1 text-[11px] text-muted-foreground">{{ format.description }}</p>
                        </div>
                        <div class="flex shrink-0 items-center gap-2">
                            <Button size="sm" variant="outline" class="gap-1.5" @click="togglePublic">
                                <component :is="garrison.is_public ? Globe : Lock" class="size-3.5" />
                                {{ garrison.is_public ? 'Public' : 'Private' }}
                            </Button>
                            <Button size="sm" variant="outline" class="gap-1.5" @click="openEdit"> <Pencil class="size-3.5" /> Edit </Button>
                            <Button
                                as="a"
                                :href="route('tos.companies.create', { garrison_id: garrison.id })"
                                size="sm"
                                variant="default"
                                class="gap-1.5"
                                title="Build a Company restricted to this Garrison's pool"
                            >
                                <Swords class="size-3.5" /> New Company
                            </Button>
                            <Button
                                as="a"
                                :href="route('tos.garrisons.pdf', garrison.slug)"
                                target="_blank"
                                size="sm"
                                variant="outline"
                                class="gap-1.5"
                            >
                                <Download class="size-3.5" /> PDF
                            </Button>
                            <Button
                                size="sm"
                                variant="ghost"
                                class="gap-1.5 text-rose-600 hover:bg-rose-500/10 hover:text-rose-700"
                                @click="deleteGarrison"
                            >
                                <Trash2 class="size-3.5" /> Delete
                            </Button>
                        </div>
                    </div>

                    <!-- Scrip meter -->
                    <div>
                        <div class="mb-1 flex items-baseline justify-between text-xs">
                            <span class="text-muted-foreground">Scrip pool</span>
                            <span class="font-semibold tabular-nums" :class="overBudget ? 'text-rose-600 dark:text-rose-400' : ''"
                                >{{ scrip_spent }} / {{ format.scrip_budget }}</span
                            >
                        </div>
                        <div class="h-2 w-full overflow-hidden rounded-full bg-muted">
                            <div class="h-full transition-all" :class="budgetBarClass" :style="{ width: `${budgetPercent}%` }" />
                        </div>
                    </div>

                    <!-- Public share link -->
                    <div
                        v-if="garrison.is_public"
                        class="flex flex-wrap items-center gap-2 rounded-md bg-emerald-500/10 p-2 text-[11px] text-emerald-700 dark:text-emerald-400"
                    >
                        <Globe class="size-3.5" />
                        <span>Shareable link:</span>
                        <code class="rounded bg-background/60 px-1.5 py-0.5 font-mono text-[10px]">{{
                            route('tos.garrisons.shared', garrison.share_code)
                        }}</code>
                    </div>

                    <!-- Notes -->
                    <p v-if="garrison.notes" class="rounded-md bg-muted/50 p-3 text-xs text-muted-foreground">{{ garrison.notes }}</p>
                </CardContent>
            </Card>

            <!-- Violations banner -->
            <div v-if="violations.length" class="rounded-md border border-rose-500/40 bg-rose-500/5 p-3">
                <div class="flex items-start gap-2">
                    <AlertTriangle class="mt-0.5 size-4 shrink-0 text-rose-600 dark:text-rose-400" />
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-semibold text-rose-700 dark:text-rose-400">
                            {{ violations.length }} rule {{ violations.length === 1 ? 'violation' : 'violations' }}
                        </p>
                        <ul class="mt-1 space-y-0.5 text-[12px] text-rose-700/90 dark:text-rose-400/90">
                            <li v-for="(v, i) in violations" :key="i">• {{ v }}</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Resource tabs -->
            <Tabs :model-value="activeTab" @update:model-value="(v) => (activeTab = v as ResourceTab)">
                <TabsList class="overflow-x-auto">
                    <TabsTrigger v-for="t in tabsList" :key="t.key" :value="t.key" class="gap-1.5">
                        {{ t.label }}
                        <Badge variant="secondary" class="px-1.5 py-0 text-[10px]">
                            {{ t.count }}<template v-if="t.cap !== null"> / {{ t.cap }}</template>
                        </Badge>
                    </TabsTrigger>
                </TabsList>
            </Tabs>

            <!-- Empty pool -->
            <EmptyState
                v-if="
                    garrison.garrison_units.length === 0 &&
                    garrison.assets.length === 0 &&
                    garrison.stratagems.length === 0 &&
                    garrison.envoys.length === 0
                "
                :icon="Swords"
                title="Garrison pool is empty"
                description="Phase 3 of the Garrison Builder lands the add-Unit / attach-Asset / pick-Stratagem / pick-Envoy controls. Until then this Garrison is just metadata."
            />

            <!-- Add buttons — visible on tabs that match a single resource, or on All -->
            <div class="flex flex-wrap gap-2">
                <Button
                    v-if="activeTab === 'all' || activeTab === 'commanders' || activeTab === 'units'"
                    size="sm"
                    variant="outline"
                    class="gap-1.5"
                    @click="openPicker('units')"
                    ><Plus class="size-3.5" /> Add Unit / Commander</Button
                >
                <Button v-if="activeTab === 'all' || activeTab === 'assets'" size="sm" variant="outline" class="gap-1.5" @click="openPicker('assets')"
                    ><Plus class="size-3.5" /> Add Asset</Button
                >
                <Button
                    v-if="activeTab === 'all' || activeTab === 'stratagems'"
                    size="sm"
                    variant="outline"
                    class="gap-1.5"
                    :disabled="stratagemCapReached"
                    @click="openPicker('stratagems')"
                    ><Plus class="size-3.5" /> Pick Stratagem</Button
                >
                <Button
                    v-if="(activeTab === 'all' || activeTab === 'envoys') && format.envoy_count > 0"
                    size="sm"
                    variant="outline"
                    class="gap-1.5"
                    :disabled="envoySlotFilled"
                    @click="openPicker('envoys')"
                    ><Plus class="size-3.5" /> Pick Envoy</Button
                >
            </div>

            <!-- ── Commanders ────────────────────────────────────────── -->
            <section v-if="showCommanders && commanderUnits.length">
                <header v-if="activeTab === 'all'" class="mb-3 flex items-baseline gap-2">
                    <Crown class="size-4 text-amber-500" aria-hidden="true" />
                    <h2 class="text-sm font-semibold uppercase tracking-wider text-muted-foreground">Commanders</h2>
                    <Badge variant="secondary" class="text-[10px]"> {{ commanderUnits.length }} / {{ format.max_commanders }} </Badge>
                </header>
                <div class="grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-4">
                    <Card v-for="gu in commanderUnits" :key="gu.id" class="group/card relative h-full overflow-hidden border-amber-500/30">
                        <FlipCard
                            :front-image="activeSculpt(gu)?.front_image"
                            :back-image="activeSculpt(gu)?.back_image"
                            :front-alt="`${gu.unit.name} (standard)`"
                            :back-alt="`${gu.unit.name} (glory)`"
                            :allegiance-slug="garrison.allegiance.slug"
                            :placeholder-icon="Crown"
                            :single-side="!activeSculpt(gu)?.back_image"
                        />
                        <button
                            type="button"
                            class="absolute right-1.5 top-1.5 hidden size-7 items-center justify-center rounded-full bg-black/60 text-white opacity-0 backdrop-blur-sm transition hover:bg-rose-500 group-hover/card:flex group-hover/card:opacity-100"
                            aria-label="Remove Commander"
                            @click="removeUnit(gu)"
                        >
                            <UserMinus class="size-3.5" />
                        </button>
                        <button
                            v-if="(gu.unit.sculpts?.length ?? 0) > 1"
                            type="button"
                            class="absolute left-1.5 top-1.5 hidden size-7 items-center justify-center rounded-full bg-black/60 text-white opacity-0 backdrop-blur-sm transition hover:bg-primary group-hover/card:flex group-hover/card:opacity-100"
                            aria-label="Change sculpt"
                            @click="openSculptDialog(gu)"
                        >
                            <Images class="size-3.5" />
                        </button>
                        <CardContent class="space-y-1 p-3">
                            <div class="flex items-center justify-between gap-2">
                                <span class="truncate text-sm font-semibold">{{ gu.unit.name }}</span>
                                <Crown class="size-3.5 shrink-0 text-amber-500" />
                            </div>
                            <p v-if="gu.unit.title" class="truncate text-[11px] italic text-muted-foreground">{{ gu.unit.title }}</p>
                        </CardContent>
                    </Card>
                </div>
            </section>

            <!-- ── Units ─────────────────────────────────────────────── -->
            <section v-if="showUnits && minionUnits.length">
                <header v-if="activeTab === 'all'" class="mb-3 flex items-baseline gap-2">
                    <Swords class="size-4 text-muted-foreground" aria-hidden="true" />
                    <h2 class="text-sm font-semibold uppercase tracking-wider text-muted-foreground">Units</h2>
                    <Badge variant="secondary" class="text-[10px]">{{ minionUnits.length }}</Badge>
                </header>
                <div class="grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-4">
                    <Card v-for="gu in minionUnits" :key="gu.id" class="group/card relative h-full overflow-hidden">
                        <FlipCard
                            :front-image="activeSculpt(gu)?.front_image"
                            :back-image="activeSculpt(gu)?.back_image"
                            :front-alt="`${gu.unit.name} (standard)`"
                            :back-alt="`${gu.unit.name} (glory)`"
                            :allegiance-slug="garrison.allegiance.slug"
                            :placeholder-icon="Swords"
                            :single-side="!activeSculpt(gu)?.back_image"
                        />
                        <button
                            type="button"
                            class="absolute right-1.5 top-1.5 hidden size-7 items-center justify-center rounded-full bg-black/60 text-white opacity-0 backdrop-blur-sm transition hover:bg-rose-500 group-hover/card:flex group-hover/card:opacity-100"
                            aria-label="Remove Unit"
                            @click="removeUnit(gu)"
                        >
                            <UserMinus class="size-3.5" />
                        </button>
                        <button
                            v-if="(gu.unit.sculpts?.length ?? 0) > 1"
                            type="button"
                            class="absolute left-1.5 top-1.5 hidden size-7 items-center justify-center rounded-full bg-black/60 text-white opacity-0 backdrop-blur-sm transition hover:bg-primary group-hover/card:flex group-hover/card:opacity-100"
                            aria-label="Change sculpt"
                            @click="openSculptDialog(gu)"
                        >
                            <Images class="size-3.5" />
                        </button>
                        <CardContent class="space-y-1 p-3">
                            <div class="flex items-center justify-between gap-2">
                                <span class="truncate text-sm font-semibold">{{ gu.unit.name }}</span>
                                <span class="shrink-0 text-[11px] tabular-nums text-muted-foreground">{{ gu.unit.scrip }}s</span>
                            </div>
                            <p v-if="gu.unit.title" class="truncate text-[11px] italic text-muted-foreground">{{ gu.unit.title }}</p>
                            <div class="flex flex-wrap gap-1">
                                <Badge v-if="gu.unit.restriction" variant="outline" class="text-[10px] capitalize">Neutral</Badge>
                                <Badge v-for="r in gu.unit.special_unit_rules" :key="r.id" variant="outline" class="text-[10px]">{{ r.name }}</Badge>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </section>

            <!-- ── Assets ────────────────────────────────────────────── -->
            <section v-if="showAssets && garrison.assets.length">
                <header v-if="activeTab === 'all'" class="mb-3 flex items-baseline gap-2">
                    <Package class="size-4 text-muted-foreground" aria-hidden="true" />
                    <h2 class="text-sm font-semibold uppercase tracking-wider text-muted-foreground">Assets</h2>
                    <Badge variant="secondary" class="text-[10px]">
                        {{ garrison.assets.reduce((n, a) => n + a.pivot.quantity, 0) }}
                    </Badge>
                </header>
                <div class="grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-4">
                    <Card v-for="a in garrison.assets" :key="a.id" class="group/card relative h-full overflow-hidden">
                        <Link :href="route('tos.assets.view', a.slug)" class="block focus-visible:outline-none">
                            <CardImage
                                :src="a.image_path"
                                :alt="a.name"
                                :allegiance-slug="garrison.allegiance.slug"
                                :placeholder-icon="Package"
                                rounded-class=""
                            />
                        </Link>
                        <CardContent class="space-y-1.5 p-3">
                            <div class="flex items-center justify-between gap-2">
                                <Link :href="route('tos.assets.view', a.slug)" class="truncate text-sm font-semibold hover:underline">{{
                                    a.name
                                }}</Link>
                                <Badge variant="outline" class="shrink-0 text-[10px] tabular-nums">×{{ a.pivot.quantity }}</Badge>
                            </div>
                            <p class="text-[10px] tabular-nums text-muted-foreground">
                                {{ a.scrip_cost }}s each &middot; {{ a.scrip_cost * a.pivot.quantity }}s total
                            </p>
                            <div v-if="a.limits.length" class="flex flex-wrap gap-1">
                                <Badge v-for="l in a.limits" :key="l.id" variant="outline" class="text-[10px] capitalize">{{ limitLabel(l) }}</Badge>
                            </div>
                            <div class="flex items-center justify-between gap-2 pt-1">
                                <div class="flex items-center gap-1">
                                    <Button size="icon" variant="outline" class="size-6" aria-label="Decrease quantity" @click="stepAsset(a, -1)"
                                        ><Minus class="size-3"
                                    /></Button>
                                    <Button size="icon" variant="outline" class="size-6" aria-label="Increase quantity" @click="stepAsset(a, 1)"
                                        ><Plus class="size-3"
                                    /></Button>
                                </div>
                                <Button
                                    size="icon"
                                    variant="ghost"
                                    class="size-6 text-muted-foreground hover:bg-rose-500/10 hover:text-rose-600"
                                    aria-label="Remove all"
                                    @click="stepAsset(a, -a.pivot.quantity)"
                                    ><Trash2 class="size-3"
                                /></Button>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </section>

            <!-- ── Stratagems ────────────────────────────────────────── -->
            <section v-if="showStratagems && garrison.stratagems.length">
                <header v-if="activeTab === 'all'" class="mb-3 flex items-baseline gap-2">
                    <Newspaper class="size-4 text-muted-foreground" aria-hidden="true" />
                    <h2 class="text-sm font-semibold uppercase tracking-wider text-muted-foreground">Stratagems</h2>
                    <Badge variant="secondary" class="text-[10px]"> {{ garrison.stratagems.length }} / {{ format.stratagem_count }} </Badge>
                </header>
                <div class="grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-4">
                    <Card v-for="s in garrison.stratagems" :key="s.id" class="group/card relative h-full overflow-hidden">
                        <Link :href="route('tos.stratagems.view', s.slug)" class="block focus-visible:outline-none">
                            <CardImage
                                :src="s.image_path"
                                :alt="s.name"
                                :allegiance-slug="s.allegiance?.slug ?? garrison.allegiance.slug"
                                :placeholder-icon="Newspaper"
                                rounded-class=""
                            />
                        </Link>
                        <button
                            type="button"
                            class="absolute right-1.5 top-1.5 hidden size-7 items-center justify-center rounded-full bg-black/60 text-white opacity-0 backdrop-blur-sm transition hover:bg-rose-500 group-hover/card:flex group-hover/card:opacity-100"
                            aria-label="Remove Stratagem"
                            @click="unpickStratagem(s)"
                        >
                            <X class="size-3.5" />
                        </button>
                        <CardContent class="space-y-1 p-3">
                            <div class="flex items-center justify-between gap-2">
                                <Link :href="route('tos.stratagems.view', s.slug)" class="truncate text-sm font-semibold hover:underline">{{
                                    s.name
                                }}</Link>
                                <Badge variant="outline" class="shrink-0 text-[10px] tabular-nums">{{ s.tactical_cost }}T</Badge>
                            </div>
                            <p class="truncate text-[10px] capitalize text-muted-foreground">{{ stratagemScopeLabel(s) }}</p>
                            <p v-if="s.effect" class="line-clamp-2 text-xs text-muted-foreground">
                                <TosText :text="s.effect" />
                            </p>
                        </CardContent>
                    </Card>
                </div>
            </section>

            <!-- ── Envoys ────────────────────────────────────────────── -->
            <section v-if="showEnvoys && garrison.envoys.length">
                <header v-if="activeTab === 'all'" class="mb-3 flex items-baseline gap-2">
                    <ScrollText class="size-4 text-muted-foreground" aria-hidden="true" />
                    <h2 class="text-sm font-semibold uppercase tracking-wider text-muted-foreground">Envoys</h2>
                    <Badge variant="secondary" class="text-[10px]"> {{ garrison.envoys.length }} / {{ format.envoy_count }} </Badge>
                </header>
                <div class="grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-4">
                    <Card v-for="c in garrison.envoys" :key="c.id" class="group/card relative h-full overflow-hidden">
                        <Link :href="route('tos.allegiance_cards.view', c.slug)" class="block focus-visible:outline-none">
                            <CardImage
                                :src="c.image_path"
                                :alt="c.name"
                                :allegiance-slug="c.allegiance?.slug ?? garrison.allegiance.slug"
                                :placeholder-icon="ScrollText"
                                rounded-class=""
                            />
                        </Link>
                        <button
                            type="button"
                            class="absolute right-1.5 top-1.5 hidden size-7 items-center justify-center rounded-full bg-black/60 text-white opacity-0 backdrop-blur-sm transition hover:bg-rose-500 group-hover/card:flex group-hover/card:opacity-100"
                            aria-label="Remove Envoy"
                            @click="unpickEnvoy(c)"
                        >
                            <X class="size-3.5" />
                        </button>
                        <CardContent class="space-y-1 p-3">
                            <Link :href="route('tos.allegiance_cards.view', c.slug)" class="block truncate text-sm font-semibold hover:underline">{{
                                c.name
                            }}</Link>
                            <p v-if="c.allegiance" class="truncate text-[10px] text-muted-foreground">{{ c.allegiance.name }}</p>
                        </CardContent>
                    </Card>
                </div>
            </section>

            <!-- ── Picker drawer ─────────────────────────────────────── -->
            <GarrisonPicker
                v-model:open="pickerOpen"
                :format="format"
                :scrip-remaining="scrip_remaining"
                :hireable-units="hireable_units"
                :available-assets="available_assets"
                :available-stratagems="available_stratagems"
                :available-envoys="available_envoys"
                :pool-units="garrison.garrison_units"
                :pool-assets="garrison.assets"
                :pool-stratagems="garrison.stratagems"
                :pool-envoys="garrison.envoys"
                @add-unit="addUnit"
                @step-asset="stepAsset"
                @pick-stratagem="pickStratagem"
                @pick-envoy="pickEnvoy"
            />

            <!-- ── Edit metadata Dialog ─────────────────────────────── -->
            <Dialog v-model:open="editOpen">
                <DialogContent class="max-w-lg">
                    <DialogHeader>
                        <DialogTitle>Edit Garrison</DialogTitle>
                    </DialogHeader>
                    <div class="space-y-4 py-2">
                        <div class="space-y-1.5">
                            <Label for="edit-name">Name</Label>
                            <Input id="edit-name" v-model="editForm.name" type="text" maxlength="120" required />
                            <p v-if="editForm.errors.name" class="text-[11px] text-rose-600">{{ editForm.errors.name }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label>Format</Label>
                            <div class="grid gap-2 sm:grid-cols-2">
                                <button
                                    v-for="f in format_options"
                                    :key="f.value"
                                    type="button"
                                    :class="[
                                        'rounded-lg border p-2 text-left text-xs transition-all',
                                        editForm.format === f.value
                                            ? 'border-primary bg-primary/5 ring-1 ring-primary/40'
                                            : 'hover:border-primary/30 hover:shadow-sm',
                                    ]"
                                    @click="editForm.format = f.value"
                                >
                                    <p class="text-[12px] font-semibold">{{ f.name }}</p>
                                    <p class="mt-0.5 text-[10px] leading-snug text-muted-foreground">{{ f.description }}</p>
                                </button>
                            </div>
                            <p v-if="editForm.errors.format" class="text-[11px] text-rose-600">{{ editForm.errors.format }}</p>
                            <p
                                v-if="formatChanged"
                                class="rounded-md border border-amber-500/40 bg-amber-500/5 p-2 text-[11px] text-amber-700 dark:text-amber-400"
                            >
                                Format swap may put the existing pool over the new caps. The Garrison stays editable — trim units, assets, stratagems,
                                or envoys as needed to clear the violations banner.
                            </p>
                        </div>

                        <div class="space-y-1.5">
                            <Label for="edit-notes">Notes</Label>
                            <Textarea id="edit-notes" v-model="editForm.notes" rows="3" placeholder="Tournament context, plan-of-attack…" />
                        </div>
                    </div>
                    <DialogFooter>
                        <Button variant="ghost" @click="editOpen = false">Cancel</Button>
                        <Button :disabled="editForm.processing || !editForm.name" @click="submitEdit">Save</Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <!-- ── Sculpt picker Dialog ─────────────────────────────── -->
            <Dialog v-model:open="sculptDialogOpen">
                <DialogContent class="max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>
                            <span v-if="sculptDialogTarget">Choose sculpt for {{ sculptDialogTarget.unit.name }}</span>
                            <span v-else>Choose sculpt</span>
                        </DialogTitle>
                    </DialogHeader>
                    <div v-if="sculptDialogTarget" class="grid gap-3 sm:grid-cols-2 md:grid-cols-3">
                        <button
                            v-for="s in sculptDialogTarget.unit.sculpts"
                            :key="s.id"
                            type="button"
                            :class="[
                                'overflow-hidden rounded-lg border-2 text-left transition-all',
                                sculptDialogTarget.sculpt_id === s.id
                                    ? 'border-primary ring-2 ring-primary/40'
                                    : 'border-transparent hover:border-primary/40',
                            ]"
                            @click="chooseSculpt(sculptDialogTarget, s.id)"
                        >
                            <FlipCard
                                :front-image="s.front_image"
                                :back-image="s.back_image"
                                :front-alt="s.name ?? sculptDialogTarget.unit.name"
                                :back-alt="`${s.name ?? sculptDialogTarget.unit.name} (glory)`"
                                :allegiance-slug="garrison.allegiance.slug"
                                :placeholder-icon="Swords"
                                :single-side="!s.back_image"
                            />
                            <p class="px-2 py-1.5 text-xs font-medium">
                                {{ s.name ?? `Sculpt #${s.id}` }}
                            </p>
                        </button>
                    </div>
                </DialogContent>
            </Dialog>
        </div>
    </div>
</template>
