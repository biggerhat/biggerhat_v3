<script setup lang="ts">
import TosText from '@/components/TosText.vue';
import { Badge } from '@/components/ui/badge';
import Button from '@/components/ui/button/Button.vue';
import { Input } from '@/components/ui/input';
import { Sheet, SheetContent, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { Crown, Plus, Search } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

/**
 * Garrison Builder picker drawer — one component covers all four picker
 * kinds (units / assets / stratagems / envoys). Parent passes the
 * pickable lists + Garrison context; this component handles search,
 * empty states, and emits Add events. Cap / scrip / same-name guards
 * are computed locally so each row knows whether its Add button is
 * disabled before the round trip.
 *
 * Server-side validation re-checks every action, so a stale UI won't
 * corrupt a saved Garrison — these checks just keep the UI honest.
 */

export type PickerKind = 'units' | 'assets' | 'stratagems' | 'envoys' | null;

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

interface AssetLimit {
    id: number;
    limit_type: string;
    parameter_type: string | null;
    parameter_value: string | null;
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

interface FormatMeta {
    max_commanders: number;
    scrip_budget: number;
    stratagem_count: number;
    envoy_count: number;
}

interface UnitInPool {
    id: number;
    is_commander: boolean;
    unit: { id: number; name: string };
}

interface AssetInPool {
    id: number;
    pivot: { quantity: number };
}

interface StratagemInPool {
    id: number;
}

interface EnvoyInPool {
    id: number;
}

const props = defineProps<{
    open: PickerKind;
    format: FormatMeta;
    scripRemaining: number;
    hireableUnits: HireableUnit[];
    availableAssets: AvailableAsset[];
    availableStratagems: AvailableStratagem[];
    availableEnvoys: AvailableEnvoy[];
    poolUnits: UnitInPool[];
    poolAssets: AssetInPool[];
    poolStratagems: StratagemInPool[];
    poolEnvoys: EnvoyInPool[];
}>();

const emit = defineEmits<{
    (e: 'update:open', v: PickerKind): void;
    (e: 'add-unit', unit: HireableUnit, asCommander: boolean): void;
    (e: 'step-asset', asset: AvailableAsset, delta: number): void;
    (e: 'pick-stratagem', stratagem: AvailableStratagem): void;
    (e: 'pick-envoy', envoy: AvailableEnvoy): void;
}>();

// Local search resets every time the picker opens to a new kind so the
// previous search doesn't leak across resource types.
const search = ref('');
watch(
    () => props.open,
    (next) => {
        if (next !== null) search.value = '';
    },
);

const matches = (haystack: string | null | undefined, needle: string): boolean => {
    if (!needle.trim()) return true;
    return (haystack ?? '').toLowerCase().includes(needle.toLowerCase());
};

const filteredUnits = computed(() => props.hireableUnits.filter((u) => matches(u.name, search.value) || matches(u.title, search.value)));
const filteredAssets = computed(() => props.availableAssets.filter((a) => matches(a.name, search.value)));
const filteredStratagems = computed(() => props.availableStratagems.filter((s) => matches(s.name, search.value) || matches(s.effect, search.value)));
const filteredEnvoys = computed(() => props.availableEnvoys.filter((c) => matches(c.name, search.value)));

const isCommanderEligible = (u: HireableUnit): boolean => u.special_unit_rules.some((r) => r.slug === 'commander');

const sameNameCount = (name: string): number => props.poolUnits.filter((gu) => gu.unit.name === name).length;

const isUnitInPool = (u: HireableUnit): boolean => sameNameCount(u.name) > 0;

const assetQuantity = (assetId: number): number => {
    const row = props.poolAssets.find((a) => a.id === assetId);
    return row ? row.pivot.quantity : 0;
};

const cmdrCapReached = computed(() => props.poolUnits.filter((gu) => gu.is_commander).length >= props.format.max_commanders);
const stratagemCapReached = computed(() => props.poolStratagems.length >= props.format.stratagem_count);
const envoySlotFilled = computed(() => props.poolEnvoys.length >= props.format.envoy_count);
const isStratagemPicked = (s: AvailableStratagem): boolean => props.poolStratagems.some((picked) => picked.id === s.id);
const isEnvoyPicked = (c: AvailableEnvoy): boolean => props.poolEnvoys.some((picked) => picked.id === c.id);

const titleFor: Record<Exclude<PickerKind, null>, string> = {
    units: 'Add Unit / Commander',
    assets: 'Add Asset',
    stratagems: 'Pick Stratagem',
    envoys: 'Pick Envoy',
};
</script>

<template>
    <Sheet
        :open="open !== null"
        @update:open="
            (v) => {
                if (!v) emit('update:open', null);
            }
        "
    >
        <SheetContent class="w-full max-w-md overflow-y-auto sm:max-w-lg">
            <SheetHeader>
                <SheetTitle>{{ open ? titleFor[open] : '' }}</SheetTitle>
            </SheetHeader>

            <div class="relative mt-4">
                <Search class="absolute left-2.5 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                <Input v-model="search" placeholder="Search…" class="pl-9" />
            </div>

            <!-- ── Units picker ── -->
            <div v-if="open === 'units'" class="mt-3 space-y-2">
                <div v-if="!filteredUnits.length" class="rounded-md border border-dashed p-6 text-center text-xs text-muted-foreground">
                    No matching Units in this Allegiance's hireable pool.
                </div>
                <div v-for="u in filteredUnits" :key="u.id" class="flex items-center gap-2 rounded-md border bg-card px-2 py-1.5">
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-1.5">
                            <Crown v-if="isCommanderEligible(u)" class="size-3 shrink-0 text-amber-500" aria-label="Commander-eligible" />
                            <span class="truncate text-xs font-medium">{{ u.name }}</span>
                            <Badge v-if="u.restriction" variant="outline" class="px-1 py-0 text-[9px]">Neutral</Badge>
                        </div>
                        <div v-if="u.title" class="truncate text-[10px] italic text-muted-foreground">{{ u.title }}</div>
                        <div class="mt-0.5 flex items-center gap-1.5 text-[10px] text-muted-foreground">
                            <span class="tabular-nums">{{ u.scrip }}s</span>
                            <span v-if="isUnitInPool(u)" class="text-amber-600 dark:text-amber-400">
                                in pool: {{ sameNameCount(u.name) }}/{{ format.max_commanders }}
                            </span>
                        </div>
                    </div>
                    <div class="flex shrink-0 items-center gap-1">
                        <Button
                            v-if="isCommanderEligible(u)"
                            size="icon"
                            variant="ghost"
                            class="size-7 text-amber-600 hover:bg-amber-500/10 hover:text-amber-700 dark:text-amber-400"
                            :disabled="cmdrCapReached"
                            title="Add as Commander"
                            @click="emit('add-unit', u, true)"
                            ><Crown class="size-3.5"
                        /></Button>
                        <Button
                            size="icon"
                            variant="ghost"
                            class="size-7 text-muted-foreground hover:bg-primary/10 hover:text-primary"
                            :disabled="u.scrip > scripRemaining"
                            :title="u.scrip > scripRemaining ? 'Over the Scrip pool' : 'Add to pool'"
                            @click="emit('add-unit', u, false)"
                            ><Plus class="size-3.5"
                        /></Button>
                    </div>
                </div>
            </div>

            <!-- ── Assets picker ── -->
            <div v-else-if="open === 'assets'" class="mt-3 space-y-2">
                <div v-if="!filteredAssets.length" class="rounded-md border border-dashed p-6 text-center text-xs text-muted-foreground">
                    No matching Assets available.
                </div>
                <div v-for="a in filteredAssets" :key="a.id" class="flex items-center gap-2 rounded-md border bg-card px-2 py-1.5">
                    <div class="min-w-0 flex-1">
                        <span class="truncate text-xs font-medium">{{ a.name }}</span>
                        <div class="mt-0.5 flex items-center gap-1.5 text-[10px] text-muted-foreground">
                            <span class="tabular-nums">{{ a.scrip_cost }}s</span>
                            <span v-if="assetQuantity(a.id) > 0">in pool: ×{{ assetQuantity(a.id) }}</span>
                        </div>
                    </div>
                    <Button
                        size="icon"
                        variant="ghost"
                        class="size-7 text-muted-foreground hover:bg-primary/10 hover:text-primary"
                        :disabled="a.scrip_cost > scripRemaining"
                        :title="a.scrip_cost > scripRemaining ? 'Over the Scrip pool' : 'Add'"
                        @click="emit('step-asset', a, 1)"
                        ><Plus class="size-3.5"
                    /></Button>
                </div>
            </div>

            <!-- ── Stratagems picker ── -->
            <div v-else-if="open === 'stratagems'" class="mt-3 space-y-2">
                <div v-if="!filteredStratagems.length" class="rounded-md border border-dashed p-6 text-center text-xs text-muted-foreground">
                    No matching Stratagems.
                </div>
                <div v-for="s in filteredStratagems" :key="s.id" class="flex items-center gap-2 rounded-md border bg-card px-2 py-1.5">
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-1.5">
                            <span class="truncate text-xs font-medium">{{ s.name }}</span>
                            <Badge variant="outline" class="px-1 py-0 text-[9px] tabular-nums">{{ s.tactical_cost }}T</Badge>
                        </div>
                        <p v-if="s.effect" class="line-clamp-2 text-[10px] text-muted-foreground">
                            <TosText :text="s.effect" />
                        </p>
                    </div>
                    <Button
                        size="icon"
                        variant="ghost"
                        class="size-7 text-muted-foreground hover:bg-primary/10 hover:text-primary"
                        :disabled="stratagemCapReached || isStratagemPicked(s)"
                        :title="isStratagemPicked(s) ? 'Already in deck' : stratagemCapReached ? 'Stratagem cap reached' : 'Pick'"
                        @click="emit('pick-stratagem', s)"
                        ><Plus class="size-3.5"
                    /></Button>
                </div>
            </div>

            <!-- ── Envoys picker ── -->
            <div v-else-if="open === 'envoys'" class="mt-3 space-y-2">
                <div v-if="!filteredEnvoys.length" class="rounded-md border border-dashed p-6 text-center text-xs text-muted-foreground">
                    No Allegiance Cards seeded for this Allegiance yet.
                </div>
                <div v-for="c in filteredEnvoys" :key="c.id" class="flex items-center gap-2 rounded-md border bg-card px-2 py-1.5">
                    <span class="min-w-0 flex-1 truncate text-xs font-medium">{{ c.name }}</span>
                    <Button
                        size="icon"
                        variant="ghost"
                        class="size-7 text-muted-foreground hover:bg-primary/10 hover:text-primary"
                        :disabled="envoySlotFilled || isEnvoyPicked(c)"
                        @click="emit('pick-envoy', c)"
                        ><Plus class="size-3.5"
                    /></Button>
                </div>
            </div>
        </SheetContent>
    </Sheet>
</template>
