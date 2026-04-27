<script setup lang="ts">
import PageBanner from '@/components/PageBanner.vue';
import SearchableSelect from '@/components/SearchableSelect.vue';
import { Badge } from '@/components/ui/badge';
import Button from '@/components/ui/button/Button.vue';
import { Card, CardContent } from '@/components/ui/card';
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, Crown, Plus, Trash2, X } from 'lucide-vue-next';
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
    special_unit_rules: SpecialRule[];
    sculpts?: Sculpt[];
}

interface AssetMin {
    id: number;
    slug: string;
    name: string;
    scrip_cost: number;
    limits?: Array<{ id: number; limit_type: string; parameter_value: string | null }>;
    allegiances?: Array<{ id: number; slug: string; name: string }>;
}

interface CrewUnit {
    id: number;
    is_commander: boolean;
    position: number;
    unit: UnitMin;
    assets: AssetMin[];
}

interface Crew {
    id: number;
    slug: string;
    name: string;
    allegiance: Allegiance;
    notes: string | null;
    crew_units: CrewUnit[];
}

const props = defineProps<{
    crew: Crew;
    scrip_spent: number;
    hireable_units: UnitMin[];
    available_assets: AssetMin[];
}>();

const unitToAdd = ref<string | null>(null);
const addAsCommander = ref(false);
const assetSelections = ref<Record<number, string | null>>({});

const unitOptions = computed(() =>
    props.hireable_units.map((u) => ({ value: String(u.id), name: `${u.name}${u.title ? ` — ${u.title}` : ''} (${u.scrip}s)` })),
);

const assetOptions = computed(() => props.available_assets.map((a) => ({ value: String(a.id), name: `${a.name} (${a.scrip_cost}s)` })));

const hasCommander = computed(() => props.crew.crew_units.some((cu) => cu.is_commander));

function addUnit() {
    if (!unitToAdd.value) return;
    router.post(
        route('tos.crews.units.add', props.crew.slug),
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

function removeUnit(cu: CrewUnit) {
    router.post(route('tos.crews.units.remove', [props.crew.slug, cu.id]), {}, { preserveScroll: true });
}

function attachAsset(cu: CrewUnit) {
    const id = assetSelections.value[cu.id];
    if (!id) return;
    router.post(
        route('tos.crews.assets.attach', [props.crew.slug, cu.id]),
        { asset_id: Number(id) },
        {
            preserveScroll: true,
            onSuccess: () => {
                assetSelections.value[cu.id] = null;
            },
        },
    );
}

function detachAsset(cu: CrewUnit, asset: AssetMin) {
    router.post(route('tos.crews.assets.detach', [props.crew.slug, cu.id, asset.slug]), {}, { preserveScroll: true });
}

function deleteCrew() {
    if (!confirm(`Delete "${props.crew.name}"? This cannot be undone.`)) return;
    router.post(route('tos.crews.delete', props.crew.slug));
}
</script>

<template>
    <Head :title="`${crew.name} — TOS Crew`" />
    <div class="relative">
        <PageBanner :title="crew.name" class="mb-2">
            <template #subtitle>
                <div class="my-auto flex items-center gap-2 px-2 py-0 text-xs text-muted-foreground md:py-2 md:text-sm md:text-foreground">
                    <span>{{ crew.allegiance.name }}</span>
                    <span class="capitalize">· {{ crew.allegiance.type }}</span>
                </div>
            </template>
        </PageBanner>

        <div class="container mx-auto space-y-4 sm:px-4">
            <div class="flex items-center justify-between gap-2">
                <Link :href="route('tos.crews.index')" class="inline-flex items-center gap-1 text-xs text-muted-foreground hover:text-foreground">
                    <ArrowLeft class="size-3" /> All crews
                </Link>
                <div class="flex items-center gap-3">
                    <span class="text-xs text-muted-foreground">
                        Scrip spent:
                        <span :class="['ml-0.5 tabular-nums font-medium', scrip_spent < 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-foreground']">{{ scrip_spent }}</span>
                    </span>
                    <Button variant="ghost" size="sm" class="gap-1 text-xs text-rose-600 hover:bg-rose-500/10" @click="deleteCrew">
                        <Trash2 class="size-3" /> Delete
                    </Button>
                </div>
            </div>

            <Card>
                <CardContent class="space-y-3 p-4">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">Add a unit</p>
                    <div class="flex flex-wrap items-center gap-2">
                        <div class="min-w-[260px] flex-1">
                            <SearchableSelect v-model="unitToAdd" :options="unitOptions" placeholder="Choose a hireable unit…" />
                        </div>
                        <label v-if="!hasCommander" class="flex items-center gap-1 text-xs text-muted-foreground">
                            <input v-model="addAsCommander" type="checkbox" class="rounded" />
                            Add as Commander
                        </label>
                        <Button :disabled="!unitToAdd" size="sm" class="gap-1" @click="addUnit">
                            <Plus class="size-3" /> Add
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <div v-if="!crew.crew_units.length" class="rounded-md border border-dashed p-6 text-center text-xs text-muted-foreground">
                No units in this crew yet. Pick a Commander first to set the Scrip budget.
            </div>

            <Card v-for="cu in crew.crew_units" :key="cu.id" class="overflow-hidden">
                <CardContent class="space-y-2 p-4">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-baseline gap-2">
                                <Crown v-if="cu.is_commander" class="size-3 shrink-0 text-amber-500" />
                                <span class="text-sm font-semibold">{{ cu.unit.name }}</span>
                                <span v-if="cu.unit.title" class="text-[11px] italic text-muted-foreground">{{ cu.unit.title }}</span>
                                <Badge v-if="cu.is_commander" class="bg-amber-500/10 text-[9px] text-amber-700 dark:text-amber-400">Commander</Badge>
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

                    <div v-if="cu.assets.length" class="flex flex-wrap gap-1">
                        <Badge v-for="a in cu.assets" :key="a.id" variant="outline" class="text-[10px]">
                            {{ a.name }} ({{ a.scrip_cost }}s)
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
