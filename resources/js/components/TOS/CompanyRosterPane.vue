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
    position: number;
    unit: UnitMin;
    assets: AssetMin[];
}

defineProps<{
    renderableUnits: CompanyUnit[];
    childByParent: Map<number, CompanyUnit>;
}>();

const emit = defineEmits<{
    (e: 'remove', cu: CompanyUnit): void;
    (e: 'attach', cu: CompanyUnit): void;
    (e: 'detach', cu: CompanyUnit, asset: AssetMin): void;
}>();

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
                description="Pick a Commander from the Hiring Pool to start your Company."
            />

            <div
                v-for="cu in renderableUnits"
                :key="cu.id"
                class="overflow-hidden rounded-lg border bg-card transition-colors hover:border-primary/30"
            >
                <!-- Unit row header -->
                <div class="flex items-center gap-2 px-3 py-2">
                    <TooltipProvider v-if="cu.is_commander">
                        <Tooltip>
                            <TooltipTrigger as-child>
                                <div class="flex size-8 shrink-0 items-center justify-center rounded-md bg-amber-500/15 text-amber-600 dark:text-amber-400">
                                    <Crown class="size-4" />
                                </div>
                            </TooltipTrigger>
                            <TooltipContent side="top">
                                <p class="text-xs">Commander — provides Scrip budget</p>
                            </TooltipContent>
                        </Tooltip>
                    </TooltipProvider>
                    <div v-else class="flex size-8 shrink-0 items-center justify-center rounded-md bg-muted/60 text-muted-foreground">
                        <Swords class="size-4" />
                    </div>

                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-baseline gap-x-1.5 gap-y-0.5">
                            <a
                                v-if="cu.unit.sculpts && cu.unit.sculpts[0]"
                                :href="route('tos.units.view', cu.unit.sculpts[0].slug)"
                                target="_blank"
                                class="truncate text-sm font-semibold hover:underline"
                            >{{ cu.unit.name }}</a>
                            <span v-else class="truncate text-sm font-semibold">{{ cu.unit.name }}</span>
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

                    <Button
                        variant="ghost"
                        size="icon"
                        class="size-7 shrink-0 text-muted-foreground hover:text-rose-600"
                        aria-label="Remove unit"
                        @click="emit('remove', cu)"
                    >
                        <UserMinus class="size-4" />
                    </Button>
                </div>

                <!-- Combined Arms child (auto-attached) -->
                <div
                    v-if="childByParent.get(cu.unit.id)"
                    class="mx-3 mb-2 flex items-center gap-2 rounded-md border-l-2 border-amber-500/50 bg-amber-500/5 px-2.5 py-1.5"
                >
                    <Lock class="size-3 shrink-0 text-amber-600 dark:text-amber-400" />
                    <span class="text-xs font-medium">{{ childByParent.get(cu.unit.id)?.unit.name }}</span>
                    <Badge class="bg-amber-500/10 px-1 py-0 text-[9px] text-amber-700 dark:text-amber-400">Combined Arms</Badge>
                    <span class="text-[10px] italic text-muted-foreground">auto-attached</span>
                </div>

                <!-- Asset row -->
                <div class="flex flex-wrap items-center gap-1 border-t bg-muted/20 px-3 py-1.5">
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
