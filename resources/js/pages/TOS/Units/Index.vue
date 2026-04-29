<script setup lang="ts">
import type { Paginator, TosSelectOption } from '@/types/tos';
import CardSkeleton from '@/components/CardSkeleton.vue';
import EmptyState from '@/components/EmptyState.vue';
import InertiaPagination from '@/components/InertiaPagination.vue';
import ListSearchBar from '@/components/ListSearchBar.vue';
import PageBanner from '@/components/PageBanner.vue';
import TableSkeleton from '@/components/TableSkeleton.vue';
import FlipCard from '@/components/TOS/FlipCard.vue';
import { Badge } from '@/components/ui/badge';
import Button from '@/components/ui/button/Button.vue';
import { Card, CardContent } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useListFiltering } from '@/composables/useListFiltering';
import { Head, Link, router } from '@inertiajs/vue3';
import { Swords } from 'lucide-vue-next';

interface SpecialRule {
    id: number;
    slug?: string;
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
    glory_tactics: string | null;
    sides: Side[];
    allegiances: Array<{ id: number; slug: string; name: string }>;
    special_unit_rules: SpecialRule[];
    sculpts: Sculpt[];
}

const props = defineProps<{
    units: Paginator<Unit>;
    rule_filter: string | null;
    name_search: string | null;
    page_view: string;
    special_rules: TosSelectOption[];
}>();

const { filterParams, activeFilterCount, filter, clear, handleNameKeydown, clearNameSearch, handleViewChange, isLoading } = useListFiltering(
    {
        rule: props.rule_filter as string | null,
        name_search: props.name_search as string | null,
        page_view: props.page_view as string | null,
    },
    {
        routeName: 'tos.units.index',
        filterKeys: ['rule'],
        only: ['units', 'rule_filter', 'name_search', 'page_view'],
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
                    {{ units.total }} {{ units.total === 1 ? 'unit' : 'units' }} found
                </div>
            </template>
        </PageBanner>

        <ListSearchBar
            v-model:name-search="filterParams.name_search"
            :page-view="filterParams.page_view"
            :active-filter-count="activeFilterCount"
            placeholder="Search units by name or title..."
            @update:page-view="handleViewChange"
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

            <div v-if="isLoading && filterParams.page_view === 'table'" class="overflow-auto">
                <TableSkeleton :rows="8" :cols="5" />
            </div>
            <div v-else-if="isLoading" class="grid gap-4 grid-cols-2 md:grid-cols-3 lg:grid-cols-4">
                <CardSkeleton v-for="n in 8" :key="`skeleton-${n}`" />
            </div>

            <div v-else-if="filterParams.page_view === 'table' && units.data.length" class="overflow-auto rounded-md border">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Name</TableHead>
                            <TableHead>Title</TableHead>
                            <TableHead>Scrip</TableHead>
                            <TableHead>Allegiances</TableHead>
                            <TableHead>Rules</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="u in units.data" :key="u.id" class="transition-colors hover:bg-muted/40">
                            <TableCell class="font-medium">
                                <Link
                                    v-if="u.sculpts[0]"
                                    :href="route('tos.units.view', u.sculpts[0].slug)"
                                    class="hover:underline"
                                >
                                    {{ u.name }}
                                </Link>
                                <span v-else>{{ u.name }}</span>
                            </TableCell>
                            <TableCell class="text-xs italic text-muted-foreground">{{ u.title ?? '—' }}</TableCell>
                            <TableCell class="text-xs">
                                <span
                                    v-if="u.special_unit_rules.some((r) => r.slug === 'commander')"
                                    class="tabular-nums font-medium text-emerald-700 dark:text-emerald-400"
                                    title="Provides starting Scrip budget"
                                >+{{ u.scrip }}</span>
                                <span v-else class="tabular-nums text-muted-foreground">{{ u.scrip }}</span>
                            </TableCell>
                            <TableCell class="text-xs text-muted-foreground">
                                {{ u.allegiances.map((a) => a.name).join(', ') || '—' }}
                            </TableCell>
                            <TableCell class="text-xs">
                                <span v-for="r in u.special_unit_rules" :key="r.id" class="mr-1">{{ r.name }}</span>
                                <span v-if="!u.special_unit_rules.length" class="text-muted-foreground">—</span>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>

            <div v-else-if="units.data.length" class="grid gap-4 grid-cols-2 md:grid-cols-3 lg:grid-cols-4">
                <Card
                    v-for="u in units.data"
                    :key="u.id"
                    class="h-full overflow-hidden transition-all duration-200 ease-out hover:-translate-y-0.5 hover:border-primary/30 hover:shadow-lg hover:shadow-black/10"
                >
                    <FlipCard
                        :front-image="u.sculpts[0]?.front_image"
                        :back-image="u.sculpts[0]?.back_image"
                        :front-alt="`${u.name} (standard)`"
                        :back-alt="`${u.name} (glory)`"
                        :allegiance-slug="u.allegiances[0]?.slug ?? null"
                        :placeholder-icon="Swords"
                        :single-side="!u.sculpts[0]?.back_image"
                    />
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
                            <Badge v-for="r in u.special_unit_rules" :key="r.id" variant="outline" class="text-[10px]">{{ r.name }}</Badge>
                        </div>
                        <div v-if="u.sculpts[0]" class="pt-1">
                            <Button
                                size="sm"
                                variant="link"
                                class="h-6 px-0 text-[11px]"
                                @click="router.get(route('tos.units.view', u.sculpts[0].slug))"
                            >
                                View Unit Page
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>
            <EmptyState v-else :icon="Swords" title="No units yet" description="Try clearing filters, or check back once units have been seeded." />

            <InertiaPagination v-if="!isLoading" :paginator="units" :only="['units', 'rule_filter', 'name_search', 'page_view']" />
        </div>
    </div>
</template>
