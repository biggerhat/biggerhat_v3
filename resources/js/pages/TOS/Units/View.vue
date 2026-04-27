<script setup lang="ts">
import PageBanner from '@/components/PageBanner.vue';
import CardImage from '@/components/TOS/CardImage.vue';
import UnitCard from '@/components/TOS/UnitCard.vue';
import Button from '@/components/ui/button/Button.vue';
import { Card, CardContent } from '@/components/ui/card';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Download, Swords } from 'lucide-vue-next';

interface Sculpt {
    id: number;
    slug: string;
    name: string;
    front_image: string | null;
    back_image: string | null;
    combination_image: string | null;
    release_date: string | null;
    box_reference: string | null;
}

interface Unit {
    id: number;
    slug: string;
    name: string;
    title: string | null;
    scrip: number;
    tactics: string | null;
    description: string | null;
    sides: any[];
    allegiances: any[];
    special_unit_rules: any[];
    sculpts: Sculpt[];
    combined_arms_child: Unit | null;
}

defineProps<{
    unit: Unit;
    active_sculpt: Sculpt;
}>();
</script>

<template>
    <Head :title="`${unit.name} — TOS`" />
    <div class="relative">
        <div
            class="pointer-events-none absolute inset-x-0 top-0 h-64 opacity-[0.07] dark:opacity-[0.12]"
            :style="{ background: 'radial-gradient(ellipse at top, hsl(var(--primary)) 0%, transparent 70%)' }"
        />
        <PageBanner :title="unit.name" class="mb-2">
            <template #subtitle>
                <div class="my-auto flex items-center gap-2 px-2 py-0 text-xs text-muted-foreground md:py-2 md:text-sm md:text-foreground">
                    <span v-if="unit.title">{{ unit.title }}</span>
                </div>
            </template>
        </PageBanner>

        <div class="container mx-auto space-y-4 sm:px-4">
            <div class="flex items-center justify-between gap-2">
                <Link
                    :href="route('tos.units.index')"
                    class="inline-flex items-center gap-1 text-xs text-muted-foreground hover:text-foreground"
                >
                    <ArrowLeft class="size-3" /> All units
                </Link>
                <Button
                    v-if="active_sculpt.combination_image || active_sculpt.front_image"
                    variant="outline"
                    size="sm"
                    as="a"
                    :href="route('tos.units.pdf', active_sculpt.slug)"
                    target="_blank"
                    rel="noopener"
                    class="gap-1 text-xs"
                >
                    <Download class="size-3" /> Download PDF
                </Button>
            </div>

            <UnitCard :unit="unit" :active-sculpt="active_sculpt" />

            <div v-if="unit.sculpts.length > 1">
                <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Sculpts</p>
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <Link
                        v-for="s in unit.sculpts"
                        :key="s.id"
                        :href="route('tos.units.view', s.slug)"
                        class="block rounded-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                    >
                        <Card :class="['overflow-hidden transition', s.id === active_sculpt.id ? 'border-primary ring-2 ring-primary/40' : '']">
                            <CardImage
                                :src="s.combination_image ?? s.front_image"
                                :alt="s.name"
                                :allegiance-slug="unit.allegiances[0]?.slug ?? null"
                                :placeholder-icon="Swords"
                                aspect-class="aspect-[5/7]"
                                rounded-class=""
                            />
                            <CardContent class="p-2 text-xs">
                                <p class="font-medium">{{ s.name }}</p>
                                <p v-if="s.release_date" class="text-[10px] tabular-nums text-muted-foreground">{{ s.release_date }}</p>
                            </CardContent>
                        </Card>
                    </Link>
                </div>
            </div>

            <div v-if="unit.combined_arms_child" class="rounded-md border border-dashed p-4 text-xs text-muted-foreground">
                <p class="mb-1 font-semibold uppercase tracking-wider text-foreground">Combined Arms attaches</p>
                <Link :href="route('tos.units.view', unit.combined_arms_child.slug)" class="text-primary hover:underline">
                    {{ unit.combined_arms_child.name }}
                </Link>
            </div>
        </div>
    </div>
</template>
