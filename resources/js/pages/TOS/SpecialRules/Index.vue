<script setup lang="ts">
import CardSkeleton from '@/components/CardSkeleton.vue';
import EmptyState from '@/components/EmptyState.vue';
import ListSearchBar from '@/components/ListSearchBar.vue';
import PageBanner from '@/components/PageBanner.vue';
import TosText from '@/components/TosText.vue';
import { Card, CardContent } from '@/components/ui/card';
import { useListFiltering } from '@/composables/useListFiltering';
import { Head } from '@inertiajs/vue3';
import { BookOpen } from 'lucide-vue-next';

interface Rule {
    id: number;
    slug: string;
    name: string;
    description: string | null;
}

const props = defineProps<{
    rules: Rule[];
    name_search: string | null;
}>();

const { filterParams, activeFilterCount, filter, clear, handleNameKeydown, clearNameSearch, isLoading } = useListFiltering(
    { name_search: props.name_search as string | null },
    { routeName: 'tos.special_rules.index', filterKeys: [], only: ['rules', 'name_search'] },
);
</script>

<template>
    <Head title="Special Unit Rules — TOS" />
    <div class="relative">
        <PageBanner title="Special Unit Rules" class="mb-2">
            <template #subtitle>
                <div class="my-auto px-2 py-0 text-xs text-muted-foreground md:py-2 md:text-sm md:text-foreground">
                    The categorical tags that define how a Unit Card behaves in play
                </div>
            </template>
        </PageBanner>

        <ListSearchBar
            v-model:name-search="filterParams.name_search"
            :active-filter-count="activeFilterCount"
            placeholder="Search rules by name..."
            @name-keydown="handleNameKeydown"
            @clear-search="clearNameSearch"
            @filter="filter"
            @clear="clear"
        />

        <div class="container mx-auto sm:px-4">
            <div v-if="isLoading" class="grid gap-3 sm:grid-cols-2">
                <CardSkeleton v-for="n in 6" :key="`skeleton-${n}`" />
            </div>
            <div v-else-if="rules.length" class="grid gap-3 sm:grid-cols-2">
                <Card v-for="r in rules" :key="r.id">
                    <CardContent class="p-4">
                        <p class="mb-1 text-sm font-semibold">{{ r.name }}</p>
                        <p v-if="r.description" class="text-xs text-muted-foreground"><TosText :text="r.description" /></p>
                    </CardContent>
                </Card>
            </div>
            <EmptyState v-else :icon="BookOpen" title="No special rules yet" />
        </div>
    </div>
</template>
