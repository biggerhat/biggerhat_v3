<script setup lang="ts">
import AllegianceLogo from '@/components/AllegianceLogo.vue';
import EmptyState from '@/components/EmptyState.vue';
import PageBanner from '@/components/PageBanner.vue';
import FlipCard from '@/components/TOS/FlipCard.vue';
import TosText from '@/components/TosText.vue';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Swords } from 'lucide-vue-next';

interface Allegiance {
    id: number;
    slug: string;
    name: string;
    short_name: string | null;
    type: string;
    is_syndicate: boolean;
    description: string | null;
    logo_path: string | null;
    color_slug: string | null;
}

interface Sculpt {
    id: number;
    slug: string;
    name: string | null;
    front_image: string | null;
    back_image: string | null;
    combination_image: string | null;
}

interface SpecialRule {
    id: number;
    slug: string;
    name: string;
}

interface Unit {
    id: number;
    slug: string;
    name: string;
    title: string | null;
    scrip: number;
    restriction: string | null;
    sculpts: Sculpt[];
    special_unit_rules: SpecialRule[];
    allegiances: Array<{ id: number; slug: string }>;
}

defineProps<{
    allegiance: Allegiance;
    units: Unit[];
}>();
</script>

<template>
    <Head :title="allegiance.name" />
    <div class="relative">
        <div
            class="pointer-events-none absolute inset-x-0 top-0 h-64 opacity-[0.07] dark:opacity-[0.12]"
            :style="{ background: 'radial-gradient(ellipse at top, hsl(var(--primary)) 0%, transparent 70%)' }"
        />

        <PageBanner :title="allegiance.name" class="mb-2">
            <template #subtitle>
                <div class="my-auto flex items-center gap-2 px-2 py-0 text-xs text-muted-foreground md:py-2 md:text-sm md:text-foreground">
                    <span class="capitalize">{{ allegiance.type }}</span>
                    <Badge v-if="allegiance.is_syndicate" variant="outline" class="text-[10px]">Syndicate</Badge>
                </div>
            </template>
        </PageBanner>

        <div class="container mx-auto space-y-4 sm:px-4">
            <Link
                :href="route('tos.allegiances.index')"
                class="inline-flex items-center gap-1 text-xs text-muted-foreground hover:text-foreground"
            >
                <ArrowLeft class="size-3" /> All allegiances
            </Link>

            <Card class="overflow-hidden">
                <div :class="['h-1.5 w-full', allegiance.color_slug ? `bg-${allegiance.color_slug}` : 'bg-primary/40']" />
                <CardContent class="space-y-3 p-5">
                    <div class="flex items-center gap-3">
                        <div class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
                            <AllegianceLogo :allegiance="allegiance.slug" class-name="size-6" />
                        </div>
                        <div>
                            <h2 class="font-semibold">{{ allegiance.name }}</h2>
                            <p class="text-xs capitalize text-muted-foreground">
                                {{ allegiance.type }}{{ allegiance.is_syndicate ? ' syndicate' : '' }}
                            </p>
                        </div>
                    </div>
                    <p v-if="allegiance.description" class="text-sm text-muted-foreground"><TosText :text="allegiance.description" /></p>
                    <p v-else class="text-xs italic text-muted-foreground">No description set yet.</p>
                </CardContent>
            </Card>

            <div>
                <div class="mb-2 flex items-baseline justify-between gap-2">
                    <h3 class="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">Units</h3>
                    <span class="text-[11px] tabular-nums text-muted-foreground">
                        {{ units.length }} {{ units.length === 1 ? 'unit' : 'units' }}
                    </span>
                </div>

                <div v-if="units.length" class="grid gap-4 grid-cols-2 md:grid-cols-3 lg:grid-cols-4">
                    <Link
                        v-for="u in units"
                        :key="u.id"
                        :href="u.sculpts[0] ? route('tos.units.view', u.sculpts[0].slug) : '#'"
                        class="block rounded-lg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                    >
                        <Card class="h-full overflow-hidden transition-all duration-200 ease-out hover:-translate-y-0.5 hover:border-primary/30 hover:shadow-lg hover:shadow-black/10">
                            <div @click.stop @keydown.stop>
                                <FlipCard
                                    :front-image="u.sculpts[0]?.front_image"
                                    :back-image="u.sculpts[0]?.back_image"
                                    :front-alt="`${u.name} (standard)`"
                                    :back-alt="`${u.name} (glory)`"
                                    :allegiance-slug="allegiance.slug"
                                    :placeholder-icon="Swords"
                                    :single-side="!u.sculpts[0]?.back_image"
                                />
                            </div>
                            <CardContent class="space-y-1.5 p-3">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="truncate text-sm font-semibold">{{ u.name }}</span>
                                    <span
                                        v-if="u.special_unit_rules.some((r) => r.slug === 'commander')"
                                        class="shrink-0 text-[11px] tabular-nums font-medium text-emerald-700 dark:text-emerald-400"
                                        title="Provides starting Scrip budget"
                                    >+{{ u.scrip }}</span>
                                    <span v-else class="shrink-0 text-[11px] tabular-nums text-muted-foreground">{{ u.scrip }}</span>
                                </div>
                                <p v-if="u.title" class="truncate text-[11px] italic text-muted-foreground">{{ u.title }}</p>
                                <div class="flex flex-wrap gap-1">
                                    <Badge v-if="u.restriction" variant="outline" class="text-[10px] capitalize">Neutral</Badge>
                                    <Badge v-for="r in u.special_unit_rules" :key="r.id" variant="outline" class="text-[10px]">{{ r.name }}</Badge>
                                </div>
                            </CardContent>
                        </Card>
                    </Link>
                </div>
                <EmptyState v-else :icon="Swords" title="No units attached yet" description="Units will appear here once they're seeded or assigned to this allegiance." />
            </div>
        </div>
    </div>
</template>
