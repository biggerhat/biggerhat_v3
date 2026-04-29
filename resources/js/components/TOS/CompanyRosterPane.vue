<script setup lang="ts">
import EmptyState from '@/components/EmptyState.vue';
import { Badge } from '@/components/ui/badge';
import Button from '@/components/ui/button/Button.vue';
import { Card, CardContent } from '@/components/ui/card';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { Crown, Lock, Package, Plus, Swords, UserMinus, Users, X } from 'lucide-vue-next';

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
    combined_arms_child_id: number | null;
    special_unit_rules: SpecialRule[];
    sculpts?: Sculpt[];
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

defineProps<{
    renderableUnits: CompanyUnit[];
    childByParent: Map<number, CompanyUnit>;
    /** Tailwind background class for the allegiance, e.g. `bg-kingsempire`. */
    allegianceBg: string;
}>();

const emit = defineEmits<{
    (e: 'preview', cu: CompanyUnit): void;
    (e: 'remove', cu: CompanyUnit): void;
    (e: 'attach', cu: CompanyUnit): void;
    (e: 'detach', cu: CompanyUnit, asset: AssetMin): void;
}>();

function activeSculpt(cu: CompanyUnit): Sculpt | null {
    if (!cu.unit.sculpts?.length) return null;
    return cu.unit.sculpts.find((s) => s.id === cu.sculpt_id) ?? cu.unit.sculpts[0] ?? null;
}

function thumbSrc(cu: CompanyUnit): string | null {
    const s = activeSculpt(cu);
    return s?.combination_image ?? s?.front_image ?? null;
}

function slotLocations(asset: AssetMin): string[] {
    return (asset.limits ?? [])
        .filter((l) => l.limit_type === 'slot' && l.parameter_value)
        .map((l) => (l.parameter_value as string).toLowerCase());
}
</script>

<template>
    <Card class="overflow-hidden">
        <div class="flex items-center justify-between border-b px-3 py-2 sm:px-4">
            <h2 class="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">Roster</h2>
            <Badge variant="secondary" class="px-1.5 py-0 text-[10px]">
                {{ renderableUnits.length }} {{ renderableUnits.length === 1 ? 'unit' : 'units' }}
            </Badge>
        </div>
        <CardContent class="space-y-2 p-2 sm:p-3">
            <EmptyState
                v-if="!renderableUnits.length"
                :icon="Users"
                title="No units yet"
                description="Open the Hiring Pool to add Units to your Company."
            />

            <!--
                Each row is allegiance-tinted (Malifaux Crew Builder pattern).
                We render the colour as a thin left strip + a subtle 5%
                overlay so the row reads as part of the Allegiance without
                stomping on text contrast.
            -->
            <div
                v-for="cu in renderableUnits"
                :key="cu.id"
                class="group relative overflow-hidden rounded-lg border bg-card transition-all hover:-translate-y-0.5 hover:border-primary/30 hover:shadow-md"
            >
                <div :class="['absolute inset-y-0 left-0 w-1', allegianceBg]" />
                <div :class="['pointer-events-none absolute inset-0 opacity-[0.04] transition-opacity group-hover:opacity-[0.08]', allegianceBg]" />

                <!-- Header row — clickable opens drawer -->
                <button
                    type="button"
                    class="relative flex w-full items-center gap-2 px-3 py-2 text-left"
                    @click="emit('preview', cu)"
                >
                    <!-- Sculpt thumbnail or category icon -->
                    <div
                        v-if="thumbSrc(cu)"
                        class="relative size-12 shrink-0 overflow-hidden rounded-md ring-1 ring-border/60"
                    >
                        <img
                            :src="thumbSrc(cu) as string"
                            :alt="cu.unit.name"
                            class="h-full w-full object-cover"
                            loading="lazy"
                        />
                        <TooltipProvider v-if="cu.is_commander">
                            <Tooltip>
                                <TooltipTrigger as-child>
                                    <div class="absolute -bottom-1 -right-1 flex size-5 items-center justify-center rounded-full bg-amber-500 text-white ring-2 ring-card">
                                        <Crown class="size-3" />
                                    </div>
                                </TooltipTrigger>
                                <TooltipContent side="top">
                                    <p class="text-xs">Commander — provides Scrip budget</p>
                                </TooltipContent>
                            </Tooltip>
                        </TooltipProvider>
                    </div>
                    <div
                        v-else
                        :class="[
                            'flex size-12 shrink-0 items-center justify-center rounded-md',
                            cu.is_commander ? 'bg-amber-500/15 text-amber-600 dark:text-amber-400' : 'bg-muted/60 text-muted-foreground',
                        ]"
                    >
                        <Crown v-if="cu.is_commander" class="size-5" />
                        <Swords v-else class="size-5" />
                    </div>

                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-baseline gap-x-1.5 gap-y-0.5">
                            <span class="truncate text-sm font-semibold">{{ cu.unit.name }}</span>
                            <span v-if="cu.unit.title" class="truncate text-[11px] italic text-muted-foreground">{{ cu.unit.title }}</span>
                        </div>
                        <div class="mt-0.5 flex flex-wrap items-center gap-1 text-[10px]">
                            <span
                                :class="cu.is_commander ? 'font-medium text-emerald-700 dark:text-emerald-400' : 'tabular-nums text-muted-foreground'"
                            >{{ cu.is_commander ? '+' : '' }}{{ cu.unit.scrip }} Scrip</span>
                            <Badge
                                v-if="cu.is_commander"
                                class="bg-amber-500/10 px-1 py-0 text-[9px] text-amber-700 dark:text-amber-400"
                            >Commander</Badge>
                            <Badge
                                v-if="cu.unit.restriction"
                                variant="outline"
                                class="px-1 py-0 text-[9px] capitalize"
                            >Neutral · {{ cu.unit.restriction }}</Badge>
                            <Badge
                                v-for="r in cu.unit.special_unit_rules.filter((r) => r.slug !== 'commander')"
                                :key="r.id"
                                variant="outline"
                                class="px-1 py-0 text-[9px]"
                            >{{ r.name }}</Badge>
                        </div>
                    </div>

                    <!-- Stop click propagation so the drawer doesn't open when removing -->
                    <span
                        v-if="!cu.is_commander"
                        role="button"
                        tabindex="0"
                        class="flex size-7 shrink-0 cursor-pointer items-center justify-center rounded text-muted-foreground transition-colors hover:bg-rose-500/10 hover:text-rose-600"
                        aria-label="Remove unit"
                        @click.stop="emit('remove', cu)"
                        @keydown.enter.stop.prevent="emit('remove', cu)"
                    >
                        <UserMinus class="size-4" />
                    </span>
                </button>

                <!-- Combined Arms child (auto-attached) -->
                <div
                    v-if="childByParent.get(cu.unit.id)"
                    class="relative mx-3 mb-2 flex items-center gap-2 rounded-md border-l-2 border-amber-500/50 bg-amber-500/5 px-2.5 py-1.5"
                >
                    <Lock class="size-3 shrink-0 text-amber-600 dark:text-amber-400" />
                    <span class="text-xs font-medium">{{ childByParent.get(cu.unit.id)?.unit.name }}</span>
                    <Badge class="bg-amber-500/10 px-1 py-0 text-[9px] text-amber-700 dark:text-amber-400">Combined Arms</Badge>
                    <span class="text-[10px] italic text-muted-foreground">auto-attached</span>
                </div>

                <!-- Asset row -->
                <div class="relative flex flex-wrap items-center gap-1 border-t bg-background/40 px-3 py-1.5">
                    <Badge
                        v-for="a in cu.assets"
                        :key="a.id"
                        variant="outline"
                        class="gap-1 border-cyan-500/30 bg-cyan-500/5 px-1.5 py-0 text-[10px]"
                    >
                        <Package class="size-2.5 shrink-0 text-cyan-600 dark:text-cyan-400" />
                        <span>{{ a.name }}</span>
                        <span class="tabular-nums text-muted-foreground">({{ a.scrip_cost }}s)</span>
                        <span
                            v-for="loc in slotLocations(a)"
                            :key="loc"
                            class="text-[8px] uppercase tracking-wider text-muted-foreground"
                        >{{ loc }}</span>
                        <button
                            type="button"
                            class="ml-0.5 rounded text-muted-foreground transition-colors hover:text-rose-600"
                            aria-label="Detach asset"
                            @click.stop="emit('detach', cu, a)"
                        >
                            <X class="size-2.5" />
                        </button>
                    </Badge>
                    <Button
                        variant="ghost"
                        size="sm"
                        class="ml-auto h-6 gap-1 px-2 text-[11px] text-cyan-600 hover:bg-cyan-500/10 hover:text-cyan-700 dark:text-cyan-400"
                        @click="emit('attach', cu)"
                    >
                        <Plus class="size-3" /> Asset
                    </Button>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
