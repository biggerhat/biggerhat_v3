<script setup lang="ts">
import CardSkeleton from '@/components/CardSkeleton.vue';
import EmptyState from '@/components/EmptyState.vue';
import ListSearchBar from '@/components/ListSearchBar.vue';
import PageBanner from '@/components/PageBanner.vue';
import TosMarginCost from '@/components/TosMarginCost.vue';
import TosSuits from '@/components/TosSuits.vue';
import TosText from '@/components/TosText.vue';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { useListFiltering } from '@/composables/useListFiltering';
import { Head } from '@inertiajs/vue3';
import { Swords } from 'lucide-vue-next';

interface Trigger {
    id: number;
    name: string;
    suits: string | null;
    margin_cost: number | null;
    timing: string;
    body: string | null;
}

interface Action {
    id: number;
    slug: string;
    name: string;
    type_links: Array<{ id: number; type: string }>;
    av: number | null;
    av_target: string | null;
    av_suits: string | null;
    range: string | null;
    strength: number | null;
    is_piercing: boolean;
    is_accurate: boolean;
    is_area: boolean;
    usage_limit: string | null;
    body: string | null;
    triggers: Trigger[];
}

const props = defineProps<{
    actions: Action[];
    name_search: string | null;
}>();

const { filterParams, activeFilterCount, filter, clear, handleNameKeydown, clearNameSearch, isLoading } = useListFiltering(
    { name_search: props.name_search as string | null },
    { routeName: 'tos.actions.index', filterKeys: [], only: ['actions', 'name_search'] },
);
</script>

<template>
    <Head title="TOS Actions" />
    <div class="relative">
        <PageBanner title="Actions" class="mb-2">
            <template #subtitle>
                <div class="my-auto px-2 py-0 text-xs text-muted-foreground md:py-2 md:text-sm md:text-foreground">
                    Magic, Melee, Missile, and Morale Actions
                </div>
            </template>
        </PageBanner>

        <ListSearchBar
            v-model:name-search="filterParams.name_search"
            :active-filter-count="activeFilterCount"
            placeholder="Search actions by name..."
            @name-keydown="handleNameKeydown"
            @clear-search="clearNameSearch"
            @filter="filter"
            @clear="clear"
        />

        <div class="container mx-auto sm:px-4">
            <div v-if="isLoading" class="grid gap-3 sm:grid-cols-2">
                <CardSkeleton v-for="n in 6" :key="`skeleton-${n}`" />
            </div>
            <div v-else-if="actions.length" class="grid gap-3 sm:grid-cols-2">
                <Card v-for="a in actions" :key="a.id">
                    <CardContent class="p-4">
                        <div class="mb-1.5 flex items-center justify-between gap-2">
                            <span class="text-sm font-semibold">{{ a.name }}</span>
                            <div class="flex flex-wrap gap-1">
                                <Badge v-for="l in a.type_links" :key="l.id" variant="outline" class="text-[10px] capitalize">{{ l.type }}</Badge>
                            </div>
                        </div>
                        <p class="mb-1 text-[11px] text-muted-foreground">
                            <template v-if="a.av != null">{{ a.av }}<TosSuits v-if="a.av_suits" :suits="a.av_suits" /><template v-if="a.av_target"> v {{ a.av_target }}</template></template>
                            <template v-if="a.range"> · {{ a.range }}</template>
                            <template v-if="a.strength != null"> · Strength {{ a.strength }}</template>
                        </p>
                        <div v-if="a.is_piercing || a.is_accurate || a.is_area || a.usage_limit" class="mb-1 flex flex-wrap gap-1">
                            <Badge v-if="a.is_piercing" class="bg-amber-500/10 text-[9px] text-amber-700 dark:text-amber-400">Piercing</Badge>
                            <Badge v-if="a.is_accurate" class="bg-sky-500/10 text-[9px] text-sky-700 dark:text-sky-400">Accurate</Badge>
                            <Badge v-if="a.is_area" class="bg-rose-500/10 text-[9px] text-rose-700 dark:text-rose-400">Area</Badge>
                            <Badge v-if="a.usage_limit" variant="outline" class="text-[9px] capitalize">{{ a.usage_limit.replace(/_/g, ' ') }}</Badge>
                        </div>
                        <p v-if="a.body" class="text-xs text-muted-foreground"><TosText :text="a.body" /></p>
                        <ul v-if="a.triggers.length" class="mt-2 space-y-1 border-l-2 border-border pl-2 text-xs">
                            <li v-for="t in a.triggers" :key="t.id">
                                <TosSuits v-if="t.suits" :suits="t.suits" />
                                <TosMarginCost v-else-if="t.margin_cost != null" :cost="t.margin_cost" />
                                <span class="font-medium">{{ t.name }}</span>
                                <span v-if="t.timing === 'immediately'" class="ml-1 text-[10px] italic text-muted-foreground">(Immediately)</span>
                                <span v-if="t.body" class="text-muted-foreground"> — <TosText :text="t.body" /></span>
                            </li>
                        </ul>
                    </CardContent>
                </Card>
            </div>
            <EmptyState v-else :icon="Swords" title="No actions yet" />
        </div>
    </div>
</template>
