<script setup lang="ts">
import CardSkeleton from '@/components/CardSkeleton.vue';
import ClearableSelect from '@/components/ClearableSelect.vue';
import EmptyState from '@/components/EmptyState.vue';
import InertiaPagination from '@/components/InertiaPagination.vue';
import ListSearchBar from '@/components/ListSearchBar.vue';
import PageBanner from '@/components/PageBanner.vue';
import TableSkeleton from '@/components/TableSkeleton.vue';
import UnitGridCard from '@/components/TOS/UnitGridCard.vue';
import Button from '@/components/ui/button/Button.vue';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useListFiltering } from '@/composables/useListFiltering';
import type { Paginator, TosSelectOption } from '@/types/tos';
import { Head, Link } from '@inertiajs/vue3';
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
    allegiance_filter: string | null;
    name_search: string | null;
    page_view: string;
    special_rules: TosSelectOption[];
    allegiances: TosSelectOption[];
}>();

const { filterParams, activeFilterCount, filter, clear, handleNameKeydown, clearNameSearch, handleViewChange, isLoading } = useListFiltering(
    {
        rule: props.rule_filter as string | null,
        allegiance: props.allegiance_filter as string | null,
        name_search: props.name_search as string | null,
        page_view: props.page_view as string | null,
    },
    {
        routeName: 'tos.units.index',
        filterKeys: ['rule', 'allegiance'],
        only: ['units', 'rule_filter', 'allegiance_filter', 'name_search', 'page_view'],
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
            <!-- Special-rule filter chips — segmented control aligned with the rest of TOS -->
            <div class="mb-2 flex flex-wrap items-center gap-1">
                <span class="mr-1 text-[11px] uppercase tracking-wider text-muted-foreground">Rule:</span>
                <Button :variant="!filterParams.rule ? 'default' : 'outline'" size="sm" class="h-6 px-2 text-[11px]" @click="setRule(null)"
                    >All</Button
                >
                <Button
                    v-for="r in special_rules"
                    :key="r.value"
                    :variant="filterParams.rule === r.value ? 'default' : 'outline'"
                    size="sm"
                    class="h-6 px-2 text-[11px]"
                    @click="setRule(r.value)"
                    >{{ r.name }}</Button
                >
            </div>
            <!-- Allegiance filter — a single-select dropdown rather than chips since
                 the allegiance list is longer than the rule list. Full AND/OR/exclude
                 allegiance filtering lives on Advanced Search for users who need it. -->
            <div class="mb-4 flex flex-wrap items-center gap-1">
                <span class="mr-1 text-[11px] uppercase tracking-wider text-muted-foreground">Allegiance:</span>
                <ClearableSelect
                    v-model="filterParams.allegiance"
                    :options="allegiances"
                    placeholder="All allegiances"
                    trigger-class="h-6 w-48 text-[11px]"
                    @update:model-value="filter"
                />
            </div>

            <div v-if="isLoading && filterParams.page_view === 'table'" class="overflow-auto">
                <TableSkeleton :rows="8" :cols="5" />
            </div>
            <div v-else-if="isLoading" class="grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-4">
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
                                <Link v-if="u.sculpts[0]" :href="route('tos.units.view', u.sculpts[0].slug)" class="hover:underline">
                                    {{ u.name }}
                                </Link>
                                <span v-else>{{ u.name }}</span>
                            </TableCell>
                            <TableCell class="text-xs italic text-muted-foreground">{{ u.title ?? '—' }}</TableCell>
                            <TableCell class="text-xs">
                                <span
                                    v-if="u.special_unit_rules.some((r) => r.slug === 'commander')"
                                    class="font-medium tabular-nums text-emerald-700 dark:text-emerald-400"
                                    title="Provides starting Scrip budget"
                                    >+{{ u.scrip }}</span
                                >
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

            <div v-else-if="units.data.length" class="grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-4">
                <UnitGridCard v-for="u in units.data" :key="u.id" :unit="u" />
            </div>
            <EmptyState v-else :icon="Swords" title="No units yet" description="Try clearing filters, or check back once units have been seeded." />

            <InertiaPagination
                v-if="!isLoading"
                :paginator="units"
                :only="['units', 'rule_filter', 'allegiance_filter', 'name_search', 'page_view']"
            />
        </div>
    </div>
</template>
