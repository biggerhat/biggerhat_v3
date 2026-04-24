<script setup lang="ts">
import CardSkeleton from '@/components/CardSkeleton.vue';
import EmptyState from '@/components/EmptyState.vue';
import ListSearchBar from '@/components/ListSearchBar.vue';
import PageBanner from '@/components/PageBanner.vue';
import TosMarginCost from '@/components/TosMarginCost.vue';
import TosSuits from '@/components/TosSuits.vue';
import TosText from '@/components/TosText.vue';
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
    action: { id: number; name: string };
}

const props = defineProps<{
    triggers: Trigger[];
    name_search: string | null;
}>();

const { filterParams, activeFilterCount, filter, clear, handleNameKeydown, clearNameSearch, isLoading } = useListFiltering(
    { name_search: props.name_search as string | null },
    { routeName: 'tos.triggers.index', filterKeys: [], only: ['triggers', 'name_search'] },
);
</script>

<template>
    <Head title="TOS Triggers" />
    <div class="relative">
        <PageBanner title="Triggers" class="mb-2">
            <template #subtitle>
                <div class="my-auto px-2 py-0 text-xs text-muted-foreground md:py-2 md:text-sm md:text-foreground">
                    Suit-driven follow-ups attached to Actions
                </div>
            </template>
        </PageBanner>

        <ListSearchBar
            v-model:name-search="filterParams.name_search"
            :active-filter-count="activeFilterCount"
            placeholder="Search triggers by name..."
            @name-keydown="handleNameKeydown"
            @clear-search="clearNameSearch"
            @filter="filter"
            @clear="clear"
        />

        <div class="container mx-auto sm:px-4">
            <div v-if="isLoading" class="grid gap-3 sm:grid-cols-2">
                <CardSkeleton v-for="n in 6" :key="`skeleton-${n}`" />
            </div>
            <div v-else-if="triggers.length" class="grid gap-3 sm:grid-cols-2">
                <Card v-for="t in triggers" :key="t.id">
                    <CardContent class="p-4 text-sm">
                        <div class="mb-1 flex items-center justify-between gap-2">
                            <span class="font-semibold">{{ t.name }}</span>
                            <TosSuits v-if="t.suits" :suits="t.suits" />
                            <TosMarginCost v-else-if="t.margin_cost != null" :cost="t.margin_cost" />
                        </div>
                        <p class="text-[11px] text-muted-foreground">
                            on {{ t.action.name }}
                            <span v-if="t.timing === 'immediately'" class="ml-1 italic">(Immediately)</span>
                        </p>
                        <p v-if="t.body" class="mt-2 text-xs text-muted-foreground"><TosText :text="t.body" /></p>
                    </CardContent>
                </Card>
            </div>
            <EmptyState v-else :icon="Swords" title="No triggers yet" />
        </div>
    </div>
</template>
