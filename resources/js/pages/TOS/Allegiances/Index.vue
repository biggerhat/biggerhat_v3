<script setup lang="ts">
import AllegianceLogo from '@/components/AllegianceLogo.vue';
import CardSkeleton from '@/components/CardSkeleton.vue';
import EmptyState from '@/components/EmptyState.vue';
import ListSearchBar from '@/components/ListSearchBar.vue';
import PageBanner from '@/components/PageBanner.vue';
import TosText from '@/components/TosText.vue';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { useListFiltering } from '@/composables/useListFiltering';
import { Head, Link } from '@inertiajs/vue3';
import { Shield } from 'lucide-vue-next';

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

const props = defineProps<{
    allegiances: Allegiance[];
    name_search: string | null;
}>();

const { filterParams, activeFilterCount, filter, clear, handleNameKeydown, clearNameSearch, isLoading } = useListFiltering(
    { name_search: props.name_search as string | null },
    { routeName: 'tos.allegiances.index', filterKeys: [], only: ['allegiances', 'name_search'] },
);
</script>

<template>
    <Head title="TOS Allegiances" />
    <div class="relative">
        <div
            class="pointer-events-none absolute inset-x-0 top-0 h-64 opacity-[0.07] dark:opacity-[0.12]"
            :style="{ background: 'radial-gradient(ellipse at top, hsl(var(--primary)) 0%, transparent 70%)' }"
        />

        <PageBanner title="Allegiances" class="mb-2">
            <template #subtitle>
                <div class="my-auto px-2 py-0 text-xs text-muted-foreground md:py-2 md:text-sm md:text-foreground">
                    The major powers fighting in The Other Side
                </div>
            </template>
        </PageBanner>

        <ListSearchBar
            v-model:name-search="filterParams.name_search"
            :active-filter-count="activeFilterCount"
            placeholder="Search allegiances by name..."
            @name-keydown="handleNameKeydown"
            @clear-search="clearNameSearch"
            @filter="filter"
            @clear="clear"
        />

        <div class="container mx-auto sm:px-4">
            <div v-if="isLoading" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <CardSkeleton v-for="n in 6" :key="`skeleton-${n}`" />
            </div>
            <div v-else-if="allegiances.length" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <Link
                    v-for="a in allegiances"
                    :key="a.id"
                    :href="route('tos.allegiances.view', a.slug)"
                    class="block rounded-lg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                >
                    <Card class="h-full overflow-hidden transition-all duration-200 ease-out hover:-translate-y-0.5 hover:border-primary/30 hover:shadow-md">
                        <div :class="['h-1 w-full', a.color_slug ? `bg-${a.color_slug}` : 'bg-primary/40']" />
                        <CardContent class="p-4">
                            <div class="mb-2 flex items-start justify-between gap-2">
                                <div class="flex items-center gap-2">
                                    <AllegianceLogo :allegiance="a.slug" class-name="size-5 text-muted-foreground" />
                                    <span class="text-sm font-semibold">{{ a.name }}</span>
                                </div>
                                <Badge v-if="a.is_syndicate" variant="outline" class="text-[10px]">Syndicate</Badge>
                            </div>
                            <p class="text-[11px] capitalize text-muted-foreground">{{ a.type }}</p>
                            <p v-if="a.description" class="mt-2 line-clamp-3 text-xs text-muted-foreground"><TosText :text="a.description" /></p>
                        </CardContent>
                    </Card>
                </Link>
            </div>

            <EmptyState v-else :icon="Shield" title="No allegiances yet" description="Check back once data has been seeded." />
        </div>
    </div>
</template>
