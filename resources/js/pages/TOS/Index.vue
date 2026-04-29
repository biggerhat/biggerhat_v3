<script setup lang="ts">
import AllegianceLogo from '@/components/AllegianceLogo.vue';
import { Badge } from '@/components/ui/badge';
import Button from '@/components/ui/button/Button.vue';
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowRight,
    BookOpen,
    Earth,
    Newspaper,
    Package,
    Scale,
    Search,
    Shield,
    Skull,
    Sparkles,
    Swords,
    Target,
    Users,
    Wand2,
    Zap,
} from 'lucide-vue-next';
import { computed } from 'vue';

interface Allegiance {
    id: number;
    slug: string;
    name: string;
    short_name: string | null;
    type: string;
    is_syndicate: boolean;
    logo_path: string | null;
    color_slug: string | null;
    unit_count?: number;
}

interface Stats {
    units: number;
    allegiances: number;
    syndicates: number;
    allegiance_cards: number;
    assets: number;
    stratagems: number;
    abilities: number;
    actions: number;
    triggers: number;
    special_rules: number;
}

const props = defineProps<{
    allegiances: Allegiance[];
    syndicates: Allegiance[];
    stats: Stats;
    type_pool_counts: { earth: number; malifaux: number };
}>();

// Database-tile catalog. Per-type accent classes match the existing TOS
// allegiance palette so the homepage feels like the rest of the section.
const browseTiles = computed(() => [
    {
        title: 'Units',
        href: route('tos.units.index'),
        icon: Swords,
        count: props.stats.units,
        description: 'Every unit card with both Standard and Glory sides.',
        accent: 'border-rose-500/30 bg-gradient-to-br from-rose-500/10 via-rose-500/5 to-transparent group-hover:border-rose-500/60',
        iconBg: 'bg-rose-500/15 text-rose-600 dark:text-rose-400 group-hover:bg-rose-500 group-hover:text-white',
    },
    {
        title: 'Allegiance Cards',
        href: route('tos.allegiance_cards.index'),
        icon: BookOpen,
        count: props.stats.allegiance_cards,
        description: 'Standard and Primary tiers — the core of every faction.',
        accent: 'border-amber-500/30 bg-gradient-to-br from-amber-500/10 via-amber-500/5 to-transparent group-hover:border-amber-500/60',
        iconBg: 'bg-amber-500/15 text-amber-600 dark:text-amber-400 group-hover:bg-amber-500 group-hover:text-white',
    },
    {
        title: 'Assets',
        href: route('tos.assets.index'),
        icon: Package,
        count: props.stats.assets,
        description: 'Vehicles, gear, and constructs — Restricted, Slot, Unique, Adjunct.',
        accent: 'border-cyan-500/30 bg-gradient-to-br from-cyan-500/10 via-cyan-500/5 to-transparent group-hover:border-cyan-500/60',
        iconBg: 'bg-cyan-500/15 text-cyan-600 dark:text-cyan-400 group-hover:bg-cyan-500 group-hover:text-white',
    },
    {
        title: 'Stratagems',
        href: route('tos.stratagems.index'),
        icon: Newspaper,
        count: props.stats.stratagems,
        description: 'Tactics-token-cost battlefield events.',
        accent: 'border-emerald-500/30 bg-gradient-to-br from-emerald-500/10 via-emerald-500/5 to-transparent group-hover:border-emerald-500/60',
        iconBg: 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 group-hover:bg-emerald-500 group-hover:text-white',
    },
]);

const referenceTiles = computed(() => [
    { title: 'Special Rules', href: route('tos.special_rules.index'), icon: Sparkles, count: props.stats.special_rules },
    { title: 'Abilities', href: route('tos.abilities.index'), icon: Zap, count: props.stats.abilities },
    { title: 'Actions', href: route('tos.actions.index'), icon: Wand2, count: props.stats.actions },
    { title: 'Triggers', href: route('tos.triggers.index'), icon: Target, count: props.stats.triggers },
]);

// Type-pool tiles — TOS' Earth / Malifaux duality is structural, so it gets
// dedicated entry points alongside the per-Allegiance grid.
const typePools = computed(() => [
    {
        slug: 'earth',
        label: 'Earth Side',
        description: 'King\'s Empire, Abyssinia, and the Earthside roster.',
        icon: Earth,
        count: props.type_pool_counts.earth,
        href: route('tos.allegiances.viewByType', 'earth'),
        accent: 'from-amber-500/15 via-amber-500/5 to-transparent border-amber-500/30',
        ring: 'ring-amber-500/40',
        iconBg: 'bg-amber-500/15 text-amber-600 dark:text-amber-400',
    },
    {
        slug: 'malifaux',
        label: 'Malifaux Side',
        description: 'Cult of the Burning Man, Gibbering Hordes, and the Breach.',
        icon: Skull,
        count: props.type_pool_counts.malifaux,
        href: route('tos.allegiances.viewByType', 'malifaux'),
        accent: 'from-purple-500/15 via-purple-500/5 to-transparent border-purple-500/30',
        ring: 'ring-purple-500/40',
        iconBg: 'bg-purple-500/15 text-purple-600 dark:text-purple-400',
    },
]);
</script>

<template>
    <Head title="The Other Side" />
    <div class="relative w-full max-w-full overflow-x-hidden">
        <!-- Background wash -->
        <div
            class="pointer-events-none absolute inset-x-0 top-0 h-[36rem] opacity-[0.07] dark:opacity-[0.14]"
            :style="{ background: 'radial-gradient(ellipse at top, hsl(var(--primary)) 0%, transparent 70%)' }"
        />

        <div class="container mx-auto flex w-full max-w-full flex-col gap-12 overflow-x-hidden px-3 pb-12 pt-6 sm:px-4 lg:gap-16">
            <!-- ═══ Hero ═══ -->
            <div class="animate-fade-in-up flex flex-col items-center pt-4 text-center sm:pt-8">
                <div class="relative mb-4 flex size-20 items-center justify-center rounded-2xl bg-gradient-to-br from-primary/20 to-primary/5 ring-1 ring-primary/30 sm:size-24">
                    <Swords class="size-10 text-primary sm:size-12" />
                    <div class="absolute -right-2 -top-2 flex size-7 items-center justify-center rounded-full bg-amber-500 text-[9px] font-bold text-white shadow-md">
                        β
                    </div>
                </div>
                <h1 class="text-balance text-2xl font-bold tracking-tight sm:text-3xl md:text-4xl">The Other Side</h1>
                <p class="mt-2 max-w-2xl text-balance text-sm text-muted-foreground sm:text-base">
                    Wyrd's mass-battle steampunk wargame database. Browse every Unit, Allegiance, Asset, and Stratagem — built from the
                    rulebook, ready for your next Company.
                </p>

                <div class="mt-5 flex flex-wrap items-center justify-center gap-2 sm:gap-3">
                    <Link :href="route('tos.search')">
                        <Button class="gap-2">
                            <Search class="size-4" />
                            <span>Advanced Search</span>
                        </Button>
                    </Link>
                    <Link :href="route('tos.units.index')">
                        <Button variant="outline" class="gap-2">
                            <Swords class="size-4" />
                            <span>Browse Units</span>
                        </Button>
                    </Link>
                    <Link :href="route('tos.compare')">
                        <Button variant="ghost" class="gap-2">
                            <Scale class="size-4" />
                            <span>Compare</span>
                        </Button>
                    </Link>
                </div>

                <div class="mt-5 flex flex-wrap items-center justify-center gap-x-3 gap-y-1 text-[11px] text-muted-foreground sm:gap-x-5 sm:text-xs">
                    <span><strong class="text-foreground">{{ stats.units }}</strong> units</span>
                    <span class="opacity-50">·</span>
                    <span><strong class="text-foreground">{{ stats.allegiances }}</strong> allegiances</span>
                    <span class="opacity-50">·</span>
                    <span><strong class="text-foreground">{{ stats.assets }}</strong> assets</span>
                    <span class="opacity-50">·</span>
                    <span><strong class="text-foreground">{{ stats.stratagems }}</strong> stratagems</span>
                </div>
            </div>

            <!-- ═══ Earth / Malifaux Type Pools ═══ -->
            <section class="animate-fade-in-up" style="animation-delay: 60ms">
                <div class="mb-3 flex items-center justify-between px-1">
                    <h2 class="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">Sides</h2>
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <Link
                        v-for="pool in typePools"
                        :key="pool.slug"
                        :href="pool.href"
                        :class="['group relative overflow-hidden rounded-xl border-2 bg-gradient-to-br p-5 transition-all duration-200 hover:-translate-y-0.5 hover:shadow-lg', pool.accent]"
                    >
                        <div class="flex items-start gap-3">
                            <div :class="['flex size-12 shrink-0 items-center justify-center rounded-xl transition-colors', pool.iconBg]">
                                <component :is="pool.icon" class="size-6" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-baseline justify-between gap-2">
                                    <h3 class="text-lg font-semibold leading-tight">{{ pool.label }}</h3>
                                    <span class="shrink-0 text-xs tabular-nums text-muted-foreground">{{ pool.count }} units</span>
                                </div>
                                <p class="mt-1 text-xs text-muted-foreground">{{ pool.description }}</p>
                                <div class="mt-2 inline-flex items-center gap-1 text-xs font-medium opacity-0 transition-opacity group-hover:opacity-100">
                                    Open roster <ArrowRight class="size-3" />
                                </div>
                            </div>
                        </div>
                    </Link>
                </div>
            </section>

            <!-- ═══ Allegiances grid ═══ -->
            <section class="animate-fade-in-up" style="animation-delay: 100ms">
                <div class="mb-3 flex items-center justify-between px-1">
                    <h2 class="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">Allegiances</h2>
                    <Link :href="route('tos.allegiances.index')" class="text-[11px] text-muted-foreground hover:text-foreground">
                        View all <ArrowRight class="ml-0.5 inline size-3" />
                    </Link>
                </div>
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <Link
                        v-for="a in allegiances"
                        :key="a.id"
                        :href="route('tos.allegiances.view', a.slug)"
                        class="group block rounded-xl focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                    >
                        <div class="relative h-full overflow-hidden rounded-xl border bg-card transition-all duration-200 group-hover:-translate-y-1 group-hover:border-primary/40 group-hover:shadow-lg">
                            <div :class="['h-1 w-full', a.color_slug ? `bg-${a.color_slug}` : 'bg-primary/40']" />
                            <div class="flex items-center gap-3 p-4">
                                <div class="flex size-14 shrink-0 items-center justify-center rounded-xl bg-muted/40 text-primary ring-1 ring-border/50 transition-transform group-hover:scale-105">
                                    <AllegianceLogo :allegiance="a.slug" class-name="size-9" />
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-semibold leading-tight">{{ a.name }}</p>
                                    <div class="mt-0.5 flex items-center gap-1.5 text-[11px] text-muted-foreground">
                                        <span class="capitalize">{{ a.type }}</span>
                                        <span class="opacity-50">·</span>
                                        <span class="tabular-nums">{{ a.unit_count ?? 0 }} units</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </Link>
                </div>
            </section>

            <!-- ═══ Syndicates ═══ -->
            <section v-if="syndicates.length" class="animate-fade-in-up" style="animation-delay: 140ms">
                <div class="mb-3 flex items-center justify-between px-1">
                    <h2 class="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">Syndicates</h2>
                    <span class="text-[11px] text-muted-foreground">Cross-allegiance groups within a type</span>
                </div>
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    <Link
                        v-for="s in syndicates"
                        :key="s.id"
                        :href="route('tos.allegiances.view', s.slug)"
                        class="group flex items-center gap-3 rounded-lg border bg-card/40 px-3 py-2.5 transition-all hover:-translate-y-0.5 hover:border-primary/40 hover:bg-card hover:shadow-md"
                    >
                        <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                            <Users class="size-5" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium leading-tight">{{ s.name }}</p>
                            <p class="text-[10px] capitalize text-muted-foreground">
                                {{ s.type }} · {{ s.unit_count ?? 0 }} units
                            </p>
                        </div>
                        <ArrowRight class="size-3.5 shrink-0 text-muted-foreground opacity-0 transition-opacity group-hover:opacity-100" />
                    </Link>
                </div>
            </section>

            <!-- ═══ Browse the Database (4 big tiles) ═══ -->
            <section class="animate-fade-in-up" style="animation-delay: 180ms">
                <div class="mb-3 flex items-center justify-between px-1">
                    <h2 class="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">Browse the database</h2>
                </div>
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <Link
                        v-for="tile in browseTiles"
                        :key="tile.title"
                        :href="tile.href"
                        :class="['group relative overflow-hidden rounded-xl border-2 p-5 transition-all duration-200 hover:-translate-y-0.5 hover:shadow-lg', tile.accent]"
                    >
                        <div class="flex flex-col gap-3">
                            <div :class="['flex size-12 items-center justify-center rounded-xl transition-colors', tile.iconBg]">
                                <component :is="tile.icon" class="size-6" />
                            </div>
                            <div class="min-w-0">
                                <div class="flex items-baseline justify-between gap-2">
                                    <h3 class="font-semibold leading-tight">{{ tile.title }}</h3>
                                    <Badge variant="outline" class="shrink-0 text-[10px] tabular-nums">{{ tile.count }}</Badge>
                                </div>
                                <p class="mt-1 text-xs text-muted-foreground">{{ tile.description }}</p>
                            </div>
                        </div>
                    </Link>
                </div>
            </section>

            <!-- ═══ Reference Library (compact tiles) ═══ -->
            <section class="animate-fade-in-up" style="animation-delay: 220ms">
                <div class="mb-3 flex items-center justify-between px-1">
                    <h2 class="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">Reference</h2>
                </div>
                <div class="grid grid-cols-2 gap-2 sm:grid-cols-4 sm:gap-3">
                    <Link
                        v-for="tile in referenceTiles"
                        :key="tile.title"
                        :href="tile.href"
                        class="group flex flex-col items-center gap-1.5 rounded-lg border border-transparent p-3 text-center transition-all duration-200 hover:-translate-y-0.5 hover:border-border hover:bg-muted hover:shadow-md sm:p-4"
                    >
                        <div class="flex size-10 items-center justify-center rounded-lg bg-muted/60 text-muted-foreground transition-colors group-hover:bg-primary/15 group-hover:text-primary">
                            <component :is="tile.icon" class="size-5" />
                        </div>
                        <span class="text-xs font-medium leading-tight">{{ tile.title }}</span>
                        <span class="text-[10px] tabular-nums text-muted-foreground">{{ tile.count }}</span>
                    </Link>
                </div>
            </section>

            <!-- ═══ Footer CTA ═══ -->
            <section class="animate-fade-in-up" style="animation-delay: 260ms">
                <div class="rounded-xl border-2 border-dashed bg-muted/20 p-6 text-center sm:p-8">
                    <Shield class="mx-auto mb-3 size-10 text-muted-foreground/60" />
                    <h3 class="text-base font-semibold">Build your Company</h3>
                    <p class="mx-auto mt-1 max-w-md text-xs text-muted-foreground sm:text-sm">
                        Hire a Commander, fill out the roster within Scrip, and attach Assets — full rule enforcement and per-user save.
                    </p>
                    <Link :href="route('tos.companies.index')" class="mt-4 inline-block">
                        <Button class="gap-2">
                            <Users class="size-4" />
                            <span>My Companies</span>
                            <ArrowRight class="size-3.5" />
                        </Button>
                    </Link>
                </div>
            </section>
        </div>
    </div>
</template>
