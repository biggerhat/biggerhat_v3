<script setup lang="ts">
import AllegianceLogo from '@/components/AllegianceLogo.vue';
import CompanyRosterPane from '@/components/TOS/CompanyRosterPane.vue';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { Head, Link } from '@inertiajs/vue3';
import { Globe } from 'lucide-vue-next';
import { computed } from 'vue';

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

interface Company {
    id: number;
    slug: string;
    share_code: string;
    name: string;
    allegiance: Allegiance;
    user: { id: number; name: string };
    notes: string | null;
    company_units: CompanyUnit[];
}

const props = defineProps<{
    company: Company;
    scrip_budget: number;
    scrip_spent: number;
    scrip_remaining: number;
}>();

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

const accentBg = computed(() =>
    props.company.allegiance.color_slug ? `bg-${props.company.allegiance.color_slug}` : 'bg-primary/40',
);

const overBudget = computed(() => props.scrip_remaining < 0);
const budgetPercent = computed(() => {
    if (props.scrip_budget <= 0) return 0;
    return Math.min(100, Math.round((props.scrip_spent / props.scrip_budget) * 100));
});
const budgetBarClass = computed(() => {
    if (overBudget.value) return 'bg-rose-500';
    if (budgetPercent.value >= 90) return 'bg-amber-500';
    return 'bg-emerald-500';
});

// Roster pane is read-only — its emits are noop'd here so users can preview
// units (drawer open) but not modify the shared Company. We pass a no-op
// child handler set; the pane's interactive controls (asset detach, remove)
// won't render meaningful results because the controller would 403, but
// the buttons exist; we hide them via passing a different wrapper if
// needed in a follow-up.
const noop = () => {};
</script>

<template>
    <Head :title="`${company.name} — Shared TOS Company`" />
    <div class="relative pb-12">
        <div
            class="pointer-events-none absolute inset-x-0 top-0 h-64 opacity-[0.07] dark:opacity-[0.12]"
            :style="{ background: 'radial-gradient(ellipse at top, hsl(var(--primary)) 0%, transparent 70%)' }"
        />

        <div class="container mx-auto space-y-3 px-3 pt-4 sm:px-4">
            <!-- Public-share banner -->
            <div class="inline-flex items-center gap-1.5 rounded-full bg-emerald-500/10 px-3 py-1 text-[11px] font-medium text-emerald-700 dark:text-emerald-400">
                <Globe class="size-3" />
                Shared Company · read-only
            </div>

            <!-- Header card -->
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
                                <span class="mx-1 opacity-50">·</span>
                                <span>by {{ company.user.name }}</span>
                            </p>
                        </div>
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
                            <span class="font-semibold tabular-nums">{{ company.company_units.length }}</span>
                        </div>
                        <div class="ml-auto">
                            <Badge
                                v-if="overBudget"
                                variant="outline"
                                class="border-rose-500/40 bg-rose-500/10 text-[11px] text-rose-700 dark:text-rose-400"
                            >{{ -scrip_remaining }} over budget</Badge>
                            <Badge
                                v-else
                                variant="outline"
                                class="border-emerald-500/40 bg-emerald-500/10 text-[11px] text-emerald-700 dark:text-emerald-400"
                            >{{ scrip_remaining }} remaining</Badge>
                        </div>
                    </div>

                    <div class="mt-2 h-2 w-full overflow-hidden rounded-full bg-muted">
                        <div class="h-full transition-all" :class="budgetBarClass" :style="{ width: `${budgetPercent}%` }" />
                    </div>
                </CardContent>
            </Card>

            <!-- Roster (read-only) -->
            <CompanyRosterPane
                :renderable-units="renderableUnits"
                :child-by-parent="childByParentUnitId"
                :allegiance-bg="accentBg"
                @preview="noop"
                @remove="noop"
                @attach="noop"
                @detach="noop"
            />

            <Card v-if="company.notes">
                <CardContent class="p-4">
                    <h2 class="mb-2 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">Notes</h2>
                    <p class="whitespace-pre-wrap text-sm">{{ company.notes }}</p>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
