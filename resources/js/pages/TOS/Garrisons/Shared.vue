<script setup lang="ts">
import AllegianceLogo from '@/components/AllegianceLogo.vue';
import EmptyState from '@/components/EmptyState.vue';
import PageBanner from '@/components/PageBanner.vue';
import CardImage from '@/components/TOS/CardImage.vue';
import FlipCard from '@/components/TOS/FlipCard.vue';
import TosText from '@/components/TosText.vue';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { Head, Link } from '@inertiajs/vue3';
import { AlertTriangle, Crown, Globe, Newspaper, Package, ScrollText, Swords } from 'lucide-vue-next';
import { computed } from 'vue';

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

interface Asset {
    id: number;
    slug: string;
    name: string;
    scrip_cost: number;
    image_path: string | null;
    pivot: { quantity: number };
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
    user: { id: number; name: string };
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

const props = defineProps<{
    garrison: Garrison;
    format: FormatMeta;
    scrip_spent: number;
    scrip_remaining: number;
    violations: string[];
}>();

const commanderUnits = computed(() => props.garrison.garrison_units.filter((gu) => gu.is_commander));
const minionUnits = computed(() => props.garrison.garrison_units.filter((gu) => !gu.is_commander));

const accentBg = computed(() => (props.garrison.allegiance.color_slug ? `bg-${props.garrison.allegiance.color_slug}` : 'bg-primary/40'));

const overBudget = computed(() => props.scrip_remaining < 0);
const budgetPercent = computed(() => {
    if (props.format.scrip_budget <= 0) return 0;
    return Math.min(100, Math.round((props.scrip_spent / props.format.scrip_budget) * 100));
});
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

function activeSculpt(gu: GarrisonUnit): Sculpt | null {
    if (!gu.unit.sculpts?.length) return null;
    return gu.unit.sculpts.find((s) => s.id === gu.sculpt_id) ?? gu.unit.sculpts[0] ?? null;
}
</script>

<template>
    <Head :title="`${garrison.name} — Shared TOS Garrison`" />
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
                    <span class="text-muted-foreground/50">&middot;</span>
                    <span>by {{ garrison.user.name }}</span>
                </div>
            </template>
        </PageBanner>

        <div class="container mx-auto space-y-3 px-3 sm:px-4">
            <!-- Public-share banner -->
            <div
                class="inline-flex items-center gap-1.5 rounded-full bg-emerald-500/10 px-3 py-1 text-[11px] font-medium text-emerald-700 dark:text-emerald-400"
            >
                <Globe class="size-3" />
                Shared Garrison &middot; read-only
            </div>

            <!-- Header card -->
            <Card class="overflow-hidden">
                <div :class="['h-1 w-full', accentBg]" />
                <CardContent class="space-y-3 p-3 sm:p-4">
                    <div>
                        <p class="text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">Format</p>
                        <p class="text-sm font-semibold">{{ format.label }}</p>
                        <p class="mt-1 text-[11px] text-muted-foreground">{{ format.description }}</p>
                    </div>

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
                description="The owner hasn't filled out their pool yet."
            />

            <!-- Commanders -->
            <section v-if="commanderUnits.length">
                <header class="mb-3 flex items-baseline gap-2">
                    <Crown class="size-4 text-amber-500" aria-hidden="true" />
                    <h2 class="text-sm font-semibold uppercase tracking-wider text-muted-foreground">Commanders</h2>
                    <Badge variant="secondary" class="text-[10px]"> {{ commanderUnits.length }} / {{ format.max_commanders }} </Badge>
                </header>
                <div class="grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-4">
                    <Card v-for="gu in commanderUnits" :key="gu.id" class="h-full overflow-hidden border-amber-500/30">
                        <FlipCard
                            :front-image="activeSculpt(gu)?.front_image"
                            :back-image="activeSculpt(gu)?.back_image"
                            :front-alt="`${gu.unit.name} (standard)`"
                            :back-alt="`${gu.unit.name} (glory)`"
                            :allegiance-slug="garrison.allegiance.slug"
                            :placeholder-icon="Crown"
                            :single-side="!activeSculpt(gu)?.back_image"
                        />
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

            <!-- Units -->
            <section v-if="minionUnits.length">
                <header class="mb-3 flex items-baseline gap-2">
                    <Swords class="size-4 text-muted-foreground" aria-hidden="true" />
                    <h2 class="text-sm font-semibold uppercase tracking-wider text-muted-foreground">Units</h2>
                    <Badge variant="secondary" class="text-[10px]">{{ minionUnits.length }}</Badge>
                </header>
                <div class="grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-4">
                    <Card v-for="gu in minionUnits" :key="gu.id" class="h-full overflow-hidden">
                        <FlipCard
                            :front-image="activeSculpt(gu)?.front_image"
                            :back-image="activeSculpt(gu)?.back_image"
                            :front-alt="`${gu.unit.name} (standard)`"
                            :back-alt="`${gu.unit.name} (glory)`"
                            :allegiance-slug="garrison.allegiance.slug"
                            :placeholder-icon="Swords"
                            :single-side="!activeSculpt(gu)?.back_image"
                        />
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

            <!-- Assets -->
            <section v-if="garrison.assets.length">
                <header class="mb-3 flex items-baseline gap-2">
                    <Package class="size-4 text-muted-foreground" aria-hidden="true" />
                    <h2 class="text-sm font-semibold uppercase tracking-wider text-muted-foreground">Assets</h2>
                    <Badge variant="secondary" class="text-[10px]">
                        {{ garrison.assets.reduce((n, a) => n + a.pivot.quantity, 0) }}
                    </Badge>
                </header>
                <div class="grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-4">
                    <Link
                        v-for="a in garrison.assets"
                        :key="a.id"
                        :href="route('tos.assets.view', a.slug)"
                        class="block rounded-lg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                    >
                        <Card class="h-full overflow-hidden">
                            <CardImage
                                :src="a.image_path"
                                :alt="a.name"
                                :allegiance-slug="garrison.allegiance.slug"
                                :placeholder-icon="Package"
                                rounded-class=""
                            />
                            <CardContent class="space-y-1 p-3">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="truncate text-sm font-semibold">{{ a.name }}</span>
                                    <Badge variant="outline" class="shrink-0 text-[10px] tabular-nums">×{{ a.pivot.quantity }}</Badge>
                                </div>
                                <p class="text-[10px] tabular-nums text-muted-foreground">
                                    {{ a.scrip_cost }}s each &middot; {{ a.scrip_cost * a.pivot.quantity }}s total
                                </p>
                                <div v-if="a.limits.length" class="flex flex-wrap gap-1">
                                    <Badge v-for="l in a.limits" :key="l.id" variant="outline" class="text-[10px] capitalize">{{
                                        limitLabel(l)
                                    }}</Badge>
                                </div>
                            </CardContent>
                        </Card>
                    </Link>
                </div>
            </section>

            <!-- Stratagems -->
            <section v-if="garrison.stratagems.length">
                <header class="mb-3 flex items-baseline gap-2">
                    <Newspaper class="size-4 text-muted-foreground" aria-hidden="true" />
                    <h2 class="text-sm font-semibold uppercase tracking-wider text-muted-foreground">Stratagems</h2>
                    <Badge variant="secondary" class="text-[10px]"> {{ garrison.stratagems.length }} / {{ format.stratagem_count }} </Badge>
                </header>
                <div class="grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-4">
                    <Link
                        v-for="s in garrison.stratagems"
                        :key="s.id"
                        :href="route('tos.stratagems.view', s.slug)"
                        class="block rounded-lg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                    >
                        <Card class="h-full overflow-hidden">
                            <CardImage
                                :src="s.image_path"
                                :alt="s.name"
                                :allegiance-slug="s.allegiance?.slug ?? garrison.allegiance.slug"
                                :placeholder-icon="Newspaper"
                                rounded-class=""
                            />
                            <CardContent class="space-y-1 p-3">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="truncate text-sm font-semibold">{{ s.name }}</span>
                                    <Badge variant="outline" class="shrink-0 text-[10px] tabular-nums">{{ s.tactical_cost }}T</Badge>
                                </div>
                                <p class="truncate text-[10px] capitalize text-muted-foreground">{{ stratagemScopeLabel(s) }}</p>
                                <p v-if="s.effect" class="line-clamp-2 text-xs text-muted-foreground">
                                    <TosText :text="s.effect" />
                                </p>
                            </CardContent>
                        </Card>
                    </Link>
                </div>
            </section>

            <!-- Envoys -->
            <section v-if="garrison.envoys.length">
                <header class="mb-3 flex items-baseline gap-2">
                    <ScrollText class="size-4 text-muted-foreground" aria-hidden="true" />
                    <h2 class="text-sm font-semibold uppercase tracking-wider text-muted-foreground">Envoys</h2>
                    <Badge variant="secondary" class="text-[10px]"> {{ garrison.envoys.length }} / {{ format.envoy_count }} </Badge>
                </header>
                <div class="grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-4">
                    <Link
                        v-for="c in garrison.envoys"
                        :key="c.id"
                        :href="route('tos.allegiance_cards.view', c.slug)"
                        class="block rounded-lg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                    >
                        <Card class="h-full overflow-hidden">
                            <CardImage
                                :src="c.image_path"
                                :alt="c.name"
                                :allegiance-slug="c.allegiance?.slug ?? garrison.allegiance.slug"
                                :placeholder-icon="ScrollText"
                                rounded-class=""
                            />
                            <CardContent class="space-y-1 p-3">
                                <span class="block truncate text-sm font-semibold">{{ c.name }}</span>
                                <p v-if="c.allegiance" class="truncate text-[10px] text-muted-foreground">{{ c.allegiance.name }}</p>
                            </CardContent>
                        </Card>
                    </Link>
                </div>
            </section>
        </div>
    </div>
</template>
