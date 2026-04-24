<script setup lang="ts">
import CardSkeleton from '@/components/CardSkeleton.vue';
import EmptyState from '@/components/EmptyState.vue';
import ListSearchBar from '@/components/ListSearchBar.vue';
import PageBanner from '@/components/PageBanner.vue';
import CardImage from '@/components/TOS/CardImage.vue';
import TosText from '@/components/TosText.vue';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { useListFiltering } from '@/composables/useListFiltering';
import { Head, Link } from '@inertiajs/vue3';
import { BookOpen } from 'lucide-vue-next';

interface Ability {
    id: number;
    name: string;
    body: string | null;
}

interface AllegianceCard {
    id: number;
    slug: string;
    name: string;
    type: string;
    body: string | null;
    image_path: string | null;
    allegiance: { id: number; slug: string; name: string; color_slug: string | null };
    abilities: Ability[];
}

const props = defineProps<{
    cards: AllegianceCard[];
    name_search: string | null;
}>();

const { filterParams, activeFilterCount, filter, clear, handleNameKeydown, clearNameSearch, isLoading } = useListFiltering(
    { name_search: props.name_search as string | null },
    { routeName: 'tos.allegiance_cards.index', filterKeys: [], only: ['cards', 'name_search'] },
);
</script>

<template>
    <Head title="Allegiance Cards — TOS" />
    <div class="relative">
        <PageBanner title="Allegiance Cards" class="mb-2">
            <template #subtitle>
                <div class="my-auto px-2 py-0 text-xs text-muted-foreground md:py-2 md:text-sm md:text-foreground">
                    Company-level abilities set by your chosen Allegiance
                </div>
            </template>
        </PageBanner>

        <ListSearchBar
            v-model:name-search="filterParams.name_search"
            :active-filter-count="activeFilterCount"
            placeholder="Search allegiance cards..."
            @name-keydown="handleNameKeydown"
            @clear-search="clearNameSearch"
            @filter="filter"
            @clear="clear"
        />

        <div class="container mx-auto sm:px-4">
            <div v-if="isLoading" class="grid gap-4 grid-cols-2 md:grid-cols-3 lg:grid-cols-4">
                <CardSkeleton v-for="n in 8" :key="`skeleton-${n}`" />
            </div>
            <div v-else-if="cards.length" class="grid gap-4 grid-cols-2 md:grid-cols-3 lg:grid-cols-4">
                <Link
                    v-for="c in cards"
                    :key="c.id"
                    :href="route('tos.allegiance_cards.view', c.slug)"
                    class="block rounded-lg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                >
                    <Card class="h-full overflow-hidden transition-all duration-200 ease-out hover:-translate-y-0.5 hover:border-primary/30 hover:shadow-lg hover:shadow-black/10">
                        <CardImage
                            :src="c.image_path"
                            :alt="c.name"
                            :allegiance-slug="c.allegiance.slug"
                            :placeholder-icon="BookOpen"
                            rounded-class=""
                        />
                        <CardContent class="space-y-1.5 p-3">
                            <div class="flex items-center justify-between gap-2">
                                <span class="truncate text-sm font-semibold">{{ c.name }}</span>
                                <Badge variant="outline" class="shrink-0 text-[10px] capitalize">{{ c.type }}</Badge>
                            </div>
                            <p class="truncate text-[10px] text-muted-foreground">{{ c.allegiance.name }}</p>
                            <p v-if="c.body" class="line-clamp-2 text-xs text-muted-foreground"><TosText :text="c.body" /></p>
                            <p v-if="c.abilities.length" class="text-[10px] tabular-nums text-muted-foreground">
                                {{ c.abilities.length }} {{ c.abilities.length === 1 ? 'ability' : 'abilities' }}
                            </p>
                        </CardContent>
                    </Card>
                </Link>
            </div>
            <EmptyState v-else :icon="BookOpen" title="No allegiance cards yet" />
        </div>
    </div>
</template>
