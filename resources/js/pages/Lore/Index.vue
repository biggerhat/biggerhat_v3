<script setup lang="ts">
import { useStaggeredEntry } from '@/composables/useStaggeredEntry';
import { Head, Link } from '@inertiajs/vue3';
import { BookOpen, ExternalLink } from 'lucide-vue-next';
import { computed } from 'vue';

import CardSkeleton from '@/components/CardSkeleton.vue';
import ClearableSelect from '@/components/ClearableSelect.vue';
import EmptyState from '@/components/EmptyState.vue';
import InertiaPagination from '@/components/InertiaPagination.vue';
import ListSearchBar from '@/components/ListSearchBar.vue';
import LoreCard from '@/components/LoreCard.vue';
import PageBanner from '@/components/PageBanner.vue';
import SearchableSelect from '@/components/SearchableSelect.vue';
import TableSkeleton from '@/components/TableSkeleton.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useListFiltering } from '@/composables/useListFiltering';

const props = defineProps<{
    lores: any;
    result_count: number;
    media_types: any[];
    lore_media: any[];
    characters: any[];
}>();

const filterKeys = ['name_search', 'media_type', 'lore_media', 'character'] as const;

const { filterParams, activeFilterCount, filter, clear, handleNameKeydown, clearNameSearch, handleViewChange, isLoading } = useListFiltering(
    {
        name_search: null as string | null,
        media_type: null as string | null,
        lore_media: null as string | null,
        character: null as string | null,
        page_view: null as string | null,
    },
    {
        routeName: 'lores.index',
        filterKeys,
        only: ['lores', 'result_count'],
    },
);

const loreCount = computed(() => props.lores?.data?.length ?? 0);
const { delays } = useStaggeredEntry(loreCount);
</script>

<template>
    <Head title="Lore" />
    <div class="relative">
        <div
            class="pointer-events-none absolute inset-x-0 top-0 h-64 opacity-[0.07] dark:opacity-[0.12]"
            :style="{ background: 'radial-gradient(ellipse at top, hsl(var(--primary)) 0%, transparent 70%)' }"
        />
        <PageBanner title="Lore Directory" class="mb-2">
            <template #subtitle>
                <div class="my-auto px-2 py-0 text-xs text-muted-foreground md:py-2 md:text-sm md:text-foreground">
                    {{ props.result_count }} {{ props.result_count === 1 ? 'story' : 'stories' }} found
                </div>
            </template>
        </PageBanner>

        <ListSearchBar
            v-model:name-search="filterParams.name_search"
            :page-view="filterParams.page_view"
            @update:page-view="handleViewChange"
            :active-filter-count="activeFilterCount"
            placeholder="Search lore by name..."
            has-filters
            @name-keydown="handleNameKeydown"
            @clear-search="clearNameSearch"
            @filter="filter"
            @clear="clear"
        >
            <template #filters>
                <div class="grid gap-4">
                    <div class="space-y-2">
                        <label class="text-sm font-medium">Character</label>
                        <SearchableSelect
                            v-model="filterParams.character"
                            placeholder="Any Character"
                            :options="props.characters"
                            trigger-class="border-2 border-primary rounded"
                        />
                    </div>
                    <div class="space-y-2">
                        <label class="text-sm font-medium">Media Type</label>
                        <ClearableSelect
                            v-model="filterParams.media_type"
                            placeholder="Any Type"
                            :options="props.media_types"
                            trigger-class="border-2 border-primary rounded"
                        />
                    </div>
                    <div class="space-y-2">
                        <label class="text-sm font-medium">Media Source</label>
                        <ClearableSelect
                            v-model="filterParams.lore_media"
                            placeholder="Any Source"
                            :options="props.lore_media"
                            trigger-class="border-2 border-primary rounded"
                        />
                    </div>
                </div>
            </template>
        </ListSearchBar>

        <!-- Main content area -->
        <div class="container mx-auto sm:px-4">
            <div class="flex gap-6">
                <!-- Desktop sidebar filters -->
                <aside class="hidden w-64 shrink-0 md:block">
                    <div class="space-y-3 pr-2">
                        <div class="space-y-1">
                            <label class="text-xs font-medium text-muted-foreground">Character</label>
                            <SearchableSelect v-model="filterParams.character" placeholder="Any Character" :options="props.characters" />
                        </div>
                        <div class="space-y-1">
                            <label class="text-xs font-medium text-muted-foreground">Media Type</label>
                            <ClearableSelect v-model="filterParams.media_type" placeholder="Any Type" :options="props.media_types" />
                        </div>
                        <div class="space-y-1">
                            <label class="text-xs font-medium text-muted-foreground">Media Source</label>
                            <ClearableSelect v-model="filterParams.lore_media" placeholder="Any Source" :options="props.lore_media" />
                        </div>
                        <div class="flex gap-2 pt-2">
                            <Button class="flex-1" @click="filter">Search</Button>
                            <Button variant="outline" class="flex-1" @click="clear">Clear</Button>
                        </div>
                    </div>
                </aside>

                <!-- Results area -->
                <div class="min-w-0 flex-1">
                    <!-- Loading states -->
                    <div v-if="isLoading && filterParams.page_view === 'table'" class="overflow-auto">
                        <TableSkeleton :rows="8" :cols="5" />
                    </div>
                    <div v-else-if="isLoading">
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            <CardSkeleton v-for="n in 6" :key="`skeleton-${n}`" />
                        </div>
                    </div>

                    <!-- Table view -->
                    <div v-else-if="filterParams.page_view === 'table'" class="overflow-auto">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Story</TableHead>
                                    <TableHead>Media Source</TableHead>
                                    <TableHead>Type</TableHead>
                                    <TableHead>Characters</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <template v-if="props.lores?.data?.length">
                                    <TableRow v-for="lore in props.lores.data" :key="lore.id">
                                        <TableCell class="font-medium">
                                            <span class="inline-flex items-center gap-1.5">
                                                <BookOpen class="h-3.5 w-3.5 shrink-0 text-muted-foreground" />
                                                {{ lore.name }}
                                            </span>
                                        </TableCell>
                                        <TableCell>
                                            <div v-if="lore.media?.length" class="space-y-0.5">
                                                <div v-for="media in lore.media" :key="media.name">
                                                    <a
                                                        v-if="media.link"
                                                        :href="media.link"
                                                        target="_blank"
                                                        class="inline-flex items-center gap-1 text-primary hover:underline"
                                                    >
                                                        {{ media.name }}
                                                        <ExternalLink class="h-3 w-3" />
                                                    </a>
                                                    <span v-else>{{ media.name }}</span>
                                                </div>
                                            </div>
                                            <span v-else class="text-muted-foreground">-</span>
                                        </TableCell>
                                        <TableCell>
                                            <div v-if="lore.media?.length" class="flex flex-wrap gap-1">
                                                <Badge v-for="media in lore.media" :key="media.name" variant="outline" class="text-xs capitalize">
                                                    {{ (media.type as string).replace(/_/g, ' ') }}
                                                </Badge>
                                            </div>
                                            <span v-else>-</span>
                                        </TableCell>
                                        <TableCell>
                                            <div class="flex flex-wrap gap-1">
                                                <Link
                                                    v-for="character in lore.characters"
                                                    :key="character.slug"
                                                    :href="
                                                        route('characters.view', {
                                                            character: character.slug,
                                                            miniature: character.standard_miniatures?.[0]?.id,
                                                            slug: character.standard_miniatures?.[0]?.slug ?? 'view',
                                                        })
                                                    "
                                                >
                                                    <Badge variant="secondary" class="cursor-pointer text-xs transition-colors hover:bg-accent">
                                                        {{ character.display_name }}
                                                    </Badge>
                                                </Link>
                                                <span v-if="!lore.characters?.length" class="text-sm text-muted-foreground">-</span>
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                </template>
                                <template v-else>
                                    <TableRow>
                                        <TableCell :colspan="4">
                                            <EmptyState />
                                        </TableCell>
                                    </TableRow>
                                </template>
                            </TableBody>
                        </Table>
                        <InertiaPagination :paginator="props.lores" :only="['lores', 'result_count']" />
                    </div>

                    <!-- Card view (default) -->
                    <div v-else>
                        <template v-if="props.lores?.data?.length">
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                <LoreCard
                                    v-for="(lore, index) in props.lores.data"
                                    :key="lore.id"
                                    :lore="lore"
                                    class="animate-fade-in-up opacity-0 transition-all duration-300 hover:-translate-y-1 hover:shadow-xl"
                                    :style="delays[index]"
                                />
                            </div>
                        </template>
                        <EmptyState v-else />
                        <InertiaPagination :paginator="props.lores" :only="['lores', 'result_count']" />
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
