<script setup lang="ts">
import CardSkeleton from '@/components/CardSkeleton.vue';
import EmptyState from '@/components/EmptyState.vue';
import ListSearchBar from '@/components/ListSearchBar.vue';
import PageBanner from '@/components/PageBanner.vue';
import CardImage from '@/components/TOS/CardImage.vue';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { useListFiltering } from '@/composables/useListFiltering';
import { Head, Link } from '@inertiajs/vue3';
import { Swords } from 'lucide-vue-next';

interface SelectOption {
    name: string;
    value: string;
}

interface SpecialRule {
    id: number;
    name: string;
}

interface Sculpt {
    id: number;
    slug: string;
    name: string;
    front_image: string | null;
    back_image: string | null;
    combination_image: string | null;
}

interface Side {
    side: string;
    speed: number;
    defense: number;
    willpower: number;
    armor: number;
}

interface Unit {
    id: number;
    slug: string;
    name: string;
    title: string | null;
    scrip: number;
    tactics: string | null;
    sides: Side[];
    allegiances: Array<{ id: number; slug: string; name: string }>;
    special_unit_rules: SpecialRule[];
    sculpts: Sculpt[];
}

const props = defineProps<{
    units: Unit[];
    rule_filter: string | null;
    name_search: string | null;
    special_rules: SelectOption[];
}>();

const { filterParams, activeFilterCount, filter, clear, handleNameKeydown, clearNameSearch, isLoading } = useListFiltering(
    {
        rule: props.rule_filter as string | null,
        name_search: props.name_search as string | null,
    },
    {
        routeName: 'tos.units.index',
        filterKeys: ['rule'],
        only: ['units', 'rule_filter', 'name_search'],
    },
);

function setRule(slug: string | null) {
    filterParams.value.rule = slug;
    filter();
}
</script>

<template>
    <Head :title="rule_filter ? `${rule_filter} units — TOS` : 'TOS Units'" />
    <div class="relative">
        <div
            class="pointer-events-none absolute inset-x-0 top-0 h-64 opacity-[0.07] dark:opacity-[0.12]"
            :style="{ background: 'radial-gradient(ellipse at top, hsl(var(--primary)) 0%, transparent 70%)' }"
        />
        <PageBanner :title="rule_filter ? `${rule_filter[0].toUpperCase()}${rule_filter.slice(1).replace('_', ' ')}s` : 'Units'" class="mb-2">
            <template #subtitle>
                <div class="my-auto px-2 py-0 text-xs text-muted-foreground md:py-2 md:text-sm md:text-foreground">
                    {{ units.length }} {{ units.length === 1 ? 'unit' : 'units' }} found
                </div>
            </template>
        </PageBanner>

        <ListSearchBar
            v-model:name-search="filterParams.name_search"
            :active-filter-count="activeFilterCount"
            placeholder="Search units by name or title..."
            @name-keydown="handleNameKeydown"
            @clear-search="clearNameSearch"
            @filter="filter"
            @clear="clear"
        />

        <div class="container mx-auto sm:px-4">
            <div class="mb-4 flex flex-wrap gap-2">
                <button
                    type="button"
                    :class="['rounded-md px-2.5 py-1 text-xs font-medium transition-colors', !filterParams.rule ? 'bg-primary text-primary-foreground' : 'bg-muted text-muted-foreground hover:bg-accent']"
                    @click="setRule(null)"
                >
                    All
                </button>
                <button
                    v-for="r in special_rules"
                    :key="r.value"
                    type="button"
                    :class="['rounded-md px-2.5 py-1 text-xs font-medium transition-colors', filterParams.rule === r.value ? 'bg-primary text-primary-foreground' : 'bg-muted text-muted-foreground hover:bg-accent']"
                    @click="setRule(r.value)"
                >
                    {{ r.name }}
                </button>
            </div>

            <div v-if="isLoading" class="grid gap-4 grid-cols-2 md:grid-cols-3 lg:grid-cols-4">
                <CardSkeleton v-for="n in 8" :key="`skeleton-${n}`" />
            </div>
            <div v-else-if="units.length" class="grid gap-4 grid-cols-2 md:grid-cols-3 lg:grid-cols-4">
                <Link
                    v-for="u in units"
                    :key="u.id"
                    :href="u.sculpts[0] ? route('tos.units.view', u.sculpts[0].slug) : '#'"
                    class="block rounded-lg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                >
                    <Card class="h-full overflow-hidden transition-all duration-200 ease-out hover:-translate-y-0.5 hover:border-primary/30 hover:shadow-lg hover:shadow-black/10">
                        <CardImage
                            :src="u.sculpts[0]?.combination_image ?? u.sculpts[0]?.front_image"
                            :alt="u.name"
                            :allegiance-slug="u.allegiances[0]?.slug ?? null"
                            :placeholder-icon="Swords"
                            rounded-class=""
                        />
                        <CardContent class="space-y-1.5 p-3">
                            <div class="flex items-center justify-between gap-2">
                                <span class="truncate text-sm font-semibold">{{ u.name }}</span>
                                <span class="shrink-0 text-[11px] tabular-nums text-muted-foreground">{{ u.scrip }}</span>
                            </div>
                            <p v-if="u.title" class="truncate text-[11px] italic text-muted-foreground">{{ u.title }}</p>
                            <div class="flex flex-wrap gap-1">
                                <Badge v-for="r in u.special_unit_rules" :key="r.id" variant="outline" class="text-[10px]">{{ r.name }}</Badge>
                            </div>
                        </CardContent>
                    </Card>
                </Link>
            </div>
            <EmptyState v-else :icon="Swords" title="No units yet" description="Try clearing filters, or check back once units have been seeded." />
        </div>
    </div>
</template>
