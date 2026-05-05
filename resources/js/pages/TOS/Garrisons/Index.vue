<script setup lang="ts">
import AllegianceLogo from '@/components/AllegianceLogo.vue';
import EmptyState from '@/components/EmptyState.vue';
import PageBanner from '@/components/PageBanner.vue';
import { Badge } from '@/components/ui/badge';
import Button from '@/components/ui/button/Button.vue';
import { Card, CardContent } from '@/components/ui/card';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight, Crown, Plus, Shield } from 'lucide-vue-next';

interface Allegiance {
    id: number;
    slug: string;
    name: string;
    color_slug: string | null;
}

interface GarrisonUnitMin {
    id: number;
    is_commander: boolean;
}

type GarrisonFormat =
    | 'one_commander'
    | 'one_commander_plus_10'
    | 'two_commanders'
    | 'theater_of_war'
    | 'no_mans_land';

interface Garrison {
    id: number;
    slug: string;
    name: string;
    format: GarrisonFormat;
    allegiance: Allegiance;
    garrison_units: GarrisonUnitMin[];
    is_public: boolean;
    updated_at: string;
}

defineProps<{
    garrisons: Garrison[];
}>();

const formatLabel: Record<GarrisonFormat, string> = {
    one_commander: 'One Commander',
    one_commander_plus_10: 'One Cmdr +10',
    two_commanders: 'Two Commanders',
    theater_of_war: 'Theater of War',
    no_mans_land: "No Man's Land",
};

function relativeTime(iso: string): string {
    const updated = new Date(iso).getTime();
    const now = Date.now();
    const diff = Math.max(0, now - updated);
    const day = 86_400_000;
    if (diff < 60_000) return 'just now';
    if (diff < 3_600_000) return `${Math.floor(diff / 60_000)}m ago`;
    if (diff < day) return `${Math.floor(diff / 3_600_000)}h ago`;
    if (diff < day * 30) return `${Math.floor(diff / day)}d ago`;
    return new Date(iso).toLocaleDateString();
}
</script>

<template>
    <Head title="My Garrisons — TOS" />
    <div class="relative pb-12">
        <div
            class="pointer-events-none absolute inset-x-0 top-0 h-64 opacity-[0.07] dark:opacity-[0.12]"
            :style="{ background: 'radial-gradient(ellipse at top, hsl(var(--primary)) 0%, transparent 70%)' }"
        />

        <PageBanner title="My Garrisons" class="mb-2">
            <template #subtitle>
                <div class="my-auto px-2 py-0 text-xs text-muted-foreground md:py-2 md:text-sm md:text-foreground">
                    Tournament-level pools — Commanders, Units, Assets, Stratagems, Envoys. Format-validated for Fields of Glory.
                </div>
            </template>
        </PageBanner>

        <div class="container mx-auto space-y-4 sm:px-4">
            <div class="flex items-center justify-between gap-2">
                <p class="text-xs text-muted-foreground">
                    {{ garrisons.length }} {{ garrisons.length === 1 ? 'garrison' : 'garrisons' }}
                </p>
                <Button as="a" :href="route('tos.garrisons.create')" size="sm" class="gap-1.5">
                    <Plus class="size-4" /> New Garrison
                </Button>
            </div>

            <div v-if="garrisons.length" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <Link
                    v-for="g in garrisons"
                    :key="g.id"
                    :href="route('tos.garrisons.view', g.slug)"
                    class="group block rounded-xl focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                >
                    <Card class="h-full overflow-hidden transition-all duration-200 ease-out group-hover:-translate-y-1 group-hover:border-primary/40 group-hover:shadow-lg">
                        <div :class="['h-1 w-full', g.allegiance.color_slug ? `bg-${g.allegiance.color_slug}` : 'bg-primary/40']" />
                        <CardContent class="p-4">
                            <div class="flex items-start gap-3">
                                <div class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-muted/40 ring-1 ring-border/50 transition-transform group-hover:scale-105">
                                    <AllegianceLogo :allegiance="g.allegiance.slug" class-name="size-8" />
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-semibold leading-tight">{{ g.name }}</p>
                                    <p class="truncate text-[11px] text-muted-foreground">{{ g.allegiance.name }}</p>
                                </div>
                            </div>
                            <div class="mt-3 flex flex-wrap items-center gap-1.5">
                                <Badge variant="outline" class="text-[10px]">{{ formatLabel[g.format] }}</Badge>
                                <Badge variant="outline" class="text-[10px]">
                                    {{ g.garrison_units.length }} {{ g.garrison_units.length === 1 ? 'unit' : 'units' }}
                                </Badge>
                                <Badge
                                    v-if="g.garrison_units.some((u) => u.is_commander)"
                                    class="gap-1 bg-amber-500/10 text-[10px] text-amber-700 dark:text-amber-400"
                                >
                                    <Crown class="size-2.5" />
                                    {{ g.garrison_units.filter((u) => u.is_commander).length }}
                                </Badge>
                                <Badge
                                    v-if="g.is_public"
                                    class="bg-emerald-500/10 text-[10px] text-emerald-700 dark:text-emerald-400"
                                >Public</Badge>
                            </div>
                            <div class="mt-2 flex items-center justify-between text-[10px] text-muted-foreground">
                                <span>Updated {{ relativeTime(g.updated_at) }}</span>
                                <ArrowRight class="size-3 opacity-0 transition-opacity group-hover:opacity-100" />
                            </div>
                        </CardContent>
                    </Card>
                </Link>
            </div>
            <EmptyState
                v-else
                :icon="Shield"
                title="No Garrisons yet"
                description="Create one to lock in a tournament pool — pick a format, fill out Commanders, Units, Assets, Stratagems, and your Envoy slot."
            />
        </div>
    </div>
</template>
