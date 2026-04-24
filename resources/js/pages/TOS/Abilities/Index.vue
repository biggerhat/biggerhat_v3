<script setup lang="ts">
import CardSkeleton from '@/components/CardSkeleton.vue';
import EmptyState from '@/components/EmptyState.vue';
import ListSearchBar from '@/components/ListSearchBar.vue';
import PageBanner from '@/components/PageBanner.vue';
import TosText from '@/components/TosText.vue';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { useListFiltering } from '@/composables/useListFiltering';
import { Head } from '@inertiajs/vue3';
import { Shield } from 'lucide-vue-next';

interface Ability {
    id: number;
    slug: string;
    name: string;
    body: string | null;
    is_general: boolean;
    allegiance: { id: number; name: string } | null;
}

const props = defineProps<{
    abilities: Ability[];
    name_search: string | null;
}>();

const { filterParams, activeFilterCount, filter, clear, handleNameKeydown, clearNameSearch, isLoading } = useListFiltering(
    { name_search: props.name_search as string | null },
    { routeName: 'tos.abilities.index', filterKeys: [], only: ['abilities', 'name_search'] },
);
</script>

<template>
    <Head title="TOS Abilities" />
    <div class="relative">
        <PageBanner title="Abilities" class="mb-2">
            <template #subtitle>
                <div class="my-auto px-2 py-0 text-xs text-muted-foreground md:py-2 md:text-sm md:text-foreground">
                    Reusable abilities referenced by Units, Allegiance Cards, Envoys, and Assets
                </div>
            </template>
        </PageBanner>

        <ListSearchBar
            v-model:name-search="filterParams.name_search"
            :active-filter-count="activeFilterCount"
            placeholder="Search abilities by name..."
            @name-keydown="handleNameKeydown"
            @clear-search="clearNameSearch"
            @filter="filter"
            @clear="clear"
        />

        <div class="container mx-auto sm:px-4">
            <div v-if="isLoading" class="grid gap-3 sm:grid-cols-2">
                <CardSkeleton v-for="n in 6" :key="`skeleton-${n}`" />
            </div>
            <div v-else-if="abilities.length" class="grid gap-3 sm:grid-cols-2">
                <Card v-for="a in abilities" :key="a.id">
                    <CardContent class="p-4">
                        <div class="mb-1.5 flex items-center justify-between gap-2">
                            <span class="text-sm font-semibold">{{ a.name }}</span>
                            <Badge v-if="a.is_general" variant="outline" class="text-[10px]">General</Badge>
                            <Badge v-else-if="a.allegiance" variant="outline" class="text-[10px]">{{ a.allegiance.name }}</Badge>
                        </div>
                        <p v-if="a.body" class="text-xs text-muted-foreground"><TosText :text="a.body" /></p>
                    </CardContent>
                </Card>
            </div>
            <EmptyState v-else :icon="Shield" title="No abilities yet" />
        </div>
    </div>
</template>
