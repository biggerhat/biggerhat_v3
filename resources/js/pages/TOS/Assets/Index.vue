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
import { Package } from 'lucide-vue-next';

interface Limit {
    id: number;
    limit_type: string;
    parameter_type: string | null;
    parameter_value: string | null;
}

interface Asset {
    id: number;
    slug: string;
    name: string;
    scrip_cost: number;
    body: string | null;
    image_path: string | null;
    allegiances: Array<{ id: number; slug: string; name: string }>;
    limits: Limit[];
}

const props = defineProps<{
    assets: Asset[];
    name_search: string | null;
}>();

const { filterParams, activeFilterCount, filter, clear, handleNameKeydown, clearNameSearch, isLoading } = useListFiltering(
    { name_search: props.name_search as string | null },
    { routeName: 'tos.assets.index', filterKeys: [], only: ['assets', 'name_search'] },
);

const limitLabel = (l: Limit): string => {
    const head = l.limit_type.charAt(0).toUpperCase() + l.limit_type.slice(1);
    if (!l.parameter_value) return head;
    return `${head} (${l.parameter_value})`;
};
</script>

<template>
    <Head title="TOS Assets" />
    <div class="relative">
        <PageBanner title="Assets" class="mb-2">
            <template #subtitle>
                <div class="my-auto px-2 py-0 text-xs text-muted-foreground md:py-2 md:text-sm md:text-foreground">
                    Equipment, mutations, and attachments bought for units during hiring
                </div>
            </template>
        </PageBanner>

        <ListSearchBar
            v-model:name-search="filterParams.name_search"
            :active-filter-count="activeFilterCount"
            placeholder="Search assets by name..."
            @name-keydown="handleNameKeydown"
            @clear-search="clearNameSearch"
            @filter="filter"
            @clear="clear"
        />

        <div class="container mx-auto sm:px-4">
            <div v-if="isLoading" class="grid gap-4 grid-cols-2 md:grid-cols-3 lg:grid-cols-4">
                <CardSkeleton v-for="n in 8" :key="`skeleton-${n}`" />
            </div>
            <div v-else-if="assets.length" class="grid gap-4 grid-cols-2 md:grid-cols-3 lg:grid-cols-4">
                <Link
                    v-for="a in assets"
                    :key="a.id"
                    :href="route('tos.assets.view', a.slug)"
                    class="block rounded-lg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                >
                    <Card class="h-full overflow-hidden transition-all duration-200 ease-out hover:-translate-y-0.5 hover:border-primary/30 hover:shadow-lg hover:shadow-black/10">
                        <CardImage
                            :src="a.image_path"
                            :alt="a.name"
                            :allegiance-slug="a.allegiances[0]?.slug ?? null"
                            :placeholder-icon="Package"
                            rounded-class=""
                        />
                        <CardContent class="space-y-1.5 p-3">
                            <div class="flex items-center justify-between gap-2">
                                <span class="truncate text-sm font-semibold">{{ a.name }}</span>
                                <span class="shrink-0 text-[11px] tabular-nums text-muted-foreground">{{ a.scrip_cost }}</span>
                            </div>
                            <div class="flex flex-wrap gap-1">
                                <Badge v-for="l in a.limits" :key="l.id" variant="outline" class="text-[10px] capitalize">{{ limitLabel(l) }}</Badge>
                            </div>
                            <p v-if="a.allegiances.length" class="truncate text-[10px] text-muted-foreground">
                                {{ a.allegiances.map((x) => x.name).join(', ') }}
                            </p>
                        </CardContent>
                    </Card>
                </Link>
            </div>
            <EmptyState v-else :icon="Package" title="No assets yet" />
        </div>
    </div>
</template>
