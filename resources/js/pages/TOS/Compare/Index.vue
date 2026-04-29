<script setup lang="ts">
import EmptyState from '@/components/EmptyState.vue';
import PageBanner from '@/components/PageBanner.vue';
import SearchableSelect from '@/components/SearchableSelect.vue';
import FlipCard from '@/components/TOS/FlipCard.vue';
import UnitStatBlock from '@/components/TOS/UnitStatBlock.vue';
import TosText from '@/components/TosText.vue';
import { Badge } from '@/components/ui/badge';
import Button from '@/components/ui/button/Button.vue';
import { Card, CardContent } from '@/components/ui/card';
import type { TosSelectOption } from '@/types/tos';
import { Head, router } from '@inertiajs/vue3';
import { Scale, Swords, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface Side {
    id: number;
    side: string;
    speed: number;
    defense: number;
    willpower: number;
    armor: number;
    abilities: Array<{ id: number; name: string; body: string | null }>;
    actions: Array<{
        id: number;
        name: string;
        type_links: Array<{ id: number; type: string }>;
        av: number | null;
        av_target: string | null;
        av_suits: string | null;
        range: string | null;
        strength: number | null;
    }>;
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
    tactics: string | null;
    sides: Side[];
    sculpts: Sculpt[];
    allegiances: Array<{ id: number; slug: string; name: string }>;
    special_unit_rules: Array<{ id: number; slug: string; name: string }>;
}

const props = defineProps<{
    units: Unit[];
    selected_slugs: string[];
    max_units: number;
    sculpt_options: TosSelectOption[];
}>();

const pickerSlot = ref<string | null>(null);

const canAddMore = computed(() => props.units.length < props.max_units);

function pushUnit(slug: string | null) {
    if (!slug) return;
    if (props.selected_slugs.includes(slug)) {
        pickerSlot.value = null;
        return;
    }
    const next = [...props.selected_slugs, slug].slice(0, props.max_units);
    router.get(route('tos.compare'), { units: next.join(',') }, { preserveState: true, only: ['units', 'selected_slugs'] });
    pickerSlot.value = null;
}

function removeUnit(slug: string) {
    const next = props.selected_slugs.filter((s) => s !== slug);
    router.get(route('tos.compare'), { units: next.join(',') }, { preserveState: true, only: ['units', 'selected_slugs'] });
}

function clearAll() {
    router.get(route('tos.compare'), {}, { preserveState: true, only: ['units', 'selected_slugs'] });
}

// Sculpts already chosen — hidden from the picker so users can't double-add.
const pickerOptions = computed(() => props.sculpt_options.filter((o) => !props.selected_slugs.includes(o.value)));

function standardSide(u: Unit): Side | null {
    return u.sides.find((s) => s.side === 'standard') ?? null;
}

function glorySide(u: Unit): Side | null {
    return u.sides.find((s) => s.side === 'glory') ?? null;
}

// Pick the sculpt whose slug matches the URL — that's the variant the user
// asked to compare. Fall back to the first sculpt if no match (legacy URLs
// or unit-level slugs).
function activeSculpt(u: Unit): Sculpt | null {
    const matched = u.sculpts.find((s) => props.selected_slugs.includes(s.slug));
    return matched ?? u.sculpts[0] ?? null;
}
</script>

<template>
    <Head title="Compare Units — TOS" />
    <div class="relative pb-12">
        <div
            class="pointer-events-none absolute inset-x-0 top-0 h-64 opacity-[0.07] dark:opacity-[0.12]"
            :style="{ background: 'radial-gradient(ellipse at top, hsl(var(--primary)) 0%, transparent 70%)' }"
        />

        <PageBanner title="Compare Units" class="mb-2">
            <template #subtitle>
                <div class="my-auto px-2 py-0 text-xs text-muted-foreground md:py-2 md:text-sm md:text-foreground">
                    Side-by-side stats for up to {{ max_units }} units
                </div>
            </template>
        </PageBanner>

        <!-- Sticky picker — stays accessible while scrolling cards below -->
        <div class="sticky top-0 z-20 mb-3 border-b bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/80">
            <div class="container mx-auto flex flex-wrap items-center gap-2 py-2 sm:px-4">
                <div v-if="canAddMore" class="min-w-[260px] flex-1 sm:max-w-md">
                    <SearchableSelect
                        v-model="pickerSlot"
                        :options="pickerOptions"
                        placeholder="Add a unit to compare…"
                        @update:modelValue="pushUnit"
                    />
                </div>
                <span v-if="units.length" class="text-[11px] text-muted-foreground">
                    {{ units.length }} of {{ max_units }} selected
                </span>
                <Button v-if="units.length" variant="ghost" size="sm" class="ml-auto text-xs" @click="clearAll">
                    <X class="size-3" /> Clear all
                </Button>
            </div>
        </div>

        <div class="container mx-auto space-y-4 sm:px-4">

            <EmptyState
                v-if="!units.length"
                :icon="Scale"
                title="No units selected"
                description="Pick up to four units from the dropdown above. The URL is shareable — copy it to send your comparison."
            />

            <div
                v-else
                class="grid gap-4"
                :class="{
                    'sm:grid-cols-2': units.length === 2,
                    'sm:grid-cols-2 lg:grid-cols-3': units.length === 3,
                    'sm:grid-cols-2 lg:grid-cols-4': units.length >= 4,
                }"
            >
                <Card v-for="u in units" :key="u.id" class="overflow-hidden">
                    <CardContent class="space-y-3 p-4">
                        <!-- Card art — flips Standard ↔ Glory on click, mirroring
                             the unit detail page so the comparator feels like
                             stacking the actual cards next to each other. -->
                        <FlipCard
                            v-if="activeSculpt(u)"
                            :front-image="activeSculpt(u)!.front_image"
                            :back-image="activeSculpt(u)!.back_image"
                            :front-alt="`${u.name} (Standard)`"
                            :back-alt="`${u.name} (Glory)`"
                            :allegiance-slug="u.allegiances[0]?.slug ?? null"
                            :placeholder-icon="Swords"
                            :single-side="!activeSculpt(u)?.back_image"
                        />
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <h3 class="truncate text-sm font-semibold">{{ u.name }}</h3>
                                <p v-if="u.title" class="truncate text-[11px] italic text-muted-foreground">{{ u.title }}</p>
                            </div>
                            <button
                                type="button"
                                class="rounded p-1 text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                                aria-label="Remove from comparison"
                                @click="removeUnit(activeSculpt(u)?.slug ?? u.slug)"
                            >
                                <X class="size-3.5" />
                            </button>
                        </div>

                        <div class="flex flex-wrap gap-1 text-[10px]">
                            <Badge
                                v-if="u.special_unit_rules.some((r) => r.slug === 'commander')"
                                class="bg-emerald-500/10 text-emerald-700 dark:text-emerald-400"
                            >
                                +{{ u.scrip }} Scrip budget
                            </Badge>
                            <Badge v-else variant="outline">{{ u.scrip }} Scrip</Badge>
                            <Badge v-if="u.tactics" variant="outline">Tactics {{ u.tactics }}</Badge>
                            <Badge v-for="a in u.allegiances" :key="a.id" variant="outline" class="capitalize">{{ a.name }}</Badge>
                            <Badge v-for="r in u.special_unit_rules" :key="r.id" class="bg-secondary text-secondary-foreground">{{ r.name }}</Badge>
                        </div>

                        <div class="grid gap-2 sm:grid-cols-2">
                            <UnitStatBlock v-if="standardSide(u)" :side="standardSide(u)!" label="Standard" :active="true" />
                            <UnitStatBlock v-if="glorySide(u)" :side="glorySide(u)!" label="Glory" :active="true" />
                        </div>

                        <div v-if="standardSide(u)?.abilities.length || glorySide(u)?.abilities.length">
                            <p class="mb-1 text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">Abilities</p>
                            <ul class="space-y-1 text-[11px]">
                                <li v-for="a in standardSide(u)?.abilities ?? []" :key="`s-${a.id}`">
                                    <span class="font-medium">{{ a.name }}</span>
                                    <span v-if="a.body" class="ml-1 text-muted-foreground"><TosText :text="a.body" /></span>
                                </li>
                                <li
                                    v-for="a in glorySide(u)?.abilities ?? []"
                                    :key="`g-${a.id}`"
                                    class="text-muted-foreground"
                                >
                                    <span class="font-medium text-foreground">{{ a.name }}</span>
                                    <span class="ml-1 text-[9px] uppercase tracking-wider">(glory)</span>
                                    <span v-if="a.body" class="ml-1"><TosText :text="a.body" /></span>
                                </li>
                            </ul>
                        </div>

                        <div v-if="standardSide(u)?.actions.length || glorySide(u)?.actions.length">
                            <p class="mb-1 text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">Actions</p>
                            <ul class="space-y-1 text-[11px]">
                                <li v-for="ac in standardSide(u)?.actions ?? []" :key="`as-${ac.id}`" class="flex items-baseline justify-between gap-2">
                                    <span class="font-medium">{{ ac.name }}</span>
                                    <span class="text-[10px] text-muted-foreground">
                                        <template v-if="ac.av != null">AV {{ ac.av }}</template>
                                        <template v-if="ac.range"> · {{ ac.range }}</template>
                                        <template v-if="ac.strength != null"> · Str {{ ac.strength }}</template>
                                    </span>
                                </li>
                                <li
                                    v-for="ac in glorySide(u)?.actions ?? []"
                                    :key="`ag-${ac.id}`"
                                    class="flex items-baseline justify-between gap-2 text-muted-foreground"
                                >
                                    <span><span class="font-medium text-foreground">{{ ac.name }}</span> <span class="text-[9px] uppercase tracking-wider">(glory)</span></span>
                                    <span class="text-[10px]">
                                        <template v-if="ac.av != null">AV {{ ac.av }}</template>
                                        <template v-if="ac.range"> · {{ ac.range }}</template>
                                        <template v-if="ac.strength != null"> · Str {{ ac.strength }}</template>
                                    </span>
                                </li>
                            </ul>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </div>
</template>
