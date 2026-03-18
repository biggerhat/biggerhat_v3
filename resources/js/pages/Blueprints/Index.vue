<script setup lang="ts">
import { useStaggeredEntry } from '@/composables/useStaggeredEntry';
import { Head, Link } from '@inertiajs/vue3';
import { FileImage } from 'lucide-vue-next';
import { computed } from 'vue';

import BlueprintCard from '@/components/BlueprintCard.vue';
import CardSkeleton from '@/components/CardSkeleton.vue';
import ClearableSelect from '@/components/ClearableSelect.vue';
import EmptyState from '@/components/EmptyState.vue';
import InertiaPagination from '@/components/InertiaPagination.vue';
import ListSearchBar from '@/components/ListSearchBar.vue';
import PageBanner from '@/components/PageBanner.vue';
import SearchableSelect from '@/components/SearchableSelect.vue';
import TableSkeleton from '@/components/TableSkeleton.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { imageSrc } from '@/composables/useBlueprintImages';
import { useListFiltering } from '@/composables/useListFiltering';

const props = defineProps<{
    blueprints: any;
    result_count: number;
    sculpt_versions: any[];
    characters: any[];
}>();

const filterKeys = ['name_search', 'sculpt_version', 'character'] as const;

const { filterParams, activeFilterCount, filter, clear, handleNameKeydown, clearNameSearch, handleViewChange, isLoading } = useListFiltering(
    {
        name_search: null as string | null,
        sculpt_version: null as string | null,
        character: null as string | null,
        page_view: null as string | null,
    },
    {
        routeName: 'blueprints.index',
        filterKeys,
        only: ['blueprints', 'result_count'],
    },
);

const blueprintCount = computed(() => props.blueprints?.data?.length ?? 0);
const { delays } = useStaggeredEntry(blueprintCount);

const formatVersion = (version: string) => {
    return version ? version.replace(/_/g, ' ') : '';
};
</script>

<template>
    <Head title="Build Instructions" />
    <div class="relative">
        <div
            class="pointer-events-none absolute inset-x-0 top-0 h-64 opacity-[0.07] dark:opacity-[0.12]"
            :style="{ background: 'radial-gradient(ellipse at top, hsl(var(--primary)) 0%, transparent 70%)' }"
        />
        <PageBanner title="Build Instructions" class="mb-2">
            <template #subtitle>
                <div class="my-auto px-2 py-0 text-xs text-muted-foreground md:py-2 md:text-sm md:text-foreground">
                    {{ props.result_count }} {{ props.result_count === 1 ? 'blueprint' : 'blueprints' }} found
                </div>
            </template>
        </PageBanner>

        <ListSearchBar
            v-model:name-search="filterParams.name_search"
            :page-view="filterParams.page_view"
            @update:page-view="handleViewChange"
            :active-filter-count="activeFilterCount"
            placeholder="Search blueprints by name..."
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
                        <label class="text-sm font-medium">Sculpt Version</label>
                        <ClearableSelect
                            v-model="filterParams.sculpt_version"
                            placeholder="Any Version"
                            :options="props.sculpt_versions"
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
                            <label class="text-xs font-medium text-muted-foreground">Sculpt Version</label>
                            <ClearableSelect v-model="filterParams.sculpt_version" placeholder="Any Version" :options="props.sculpt_versions" />
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
                        <TableSkeleton :rows="8" :cols="4" />
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
                                    <TableHead>Image</TableHead>
                                    <TableHead>Name</TableHead>
                                    <TableHead>Edition</TableHead>
                                    <TableHead>Characters</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <template v-if="props.blueprints?.data?.length">
                                    <TableRow v-for="bp in props.blueprints.data" :key="bp.id">
                                        <TableCell class="w-20">
                                            <img
                                                v-if="bp.image_path"
                                                :src="imageSrc(bp.image_path)"
                                                :alt="bp.name"
                                                loading="lazy"
                                                decoding="async"
                                                class="h-12 w-16 rounded object-contain"
                                            />
                                            <span v-else class="text-muted-foreground">-</span>
                                        </TableCell>
                                        <TableCell class="font-medium">
                                            <span class="inline-flex items-center gap-1.5">
                                                <FileImage class="h-3.5 w-3.5 shrink-0 text-muted-foreground" />
                                                {{ bp.name }}
                                            </span>
                                        </TableCell>
                                        <TableCell>
                                            <Badge variant="outline" class="text-xs capitalize">{{ formatVersion(bp.sculpt_version) }}</Badge>
                                        </TableCell>
                                        <TableCell>
                                            <div class="flex flex-wrap gap-1">
                                                <Link
                                                    v-for="character in bp.characters"
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
                                                <span v-if="!bp.characters?.length" class="text-sm text-muted-foreground">-</span>
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
                        <InertiaPagination :paginator="props.blueprints" :only="['blueprints', 'result_count']" />
                    </div>

                    <!-- Card view (default) -->
                    <div v-else>
                        <template v-if="props.blueprints?.data?.length">
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                <BlueprintCard
                                    v-for="(bp, index) in props.blueprints.data"
                                    :key="bp.id"
                                    :blueprint="bp"
                                    class="animate-fade-in-up opacity-0 transition-all duration-300 hover:-translate-y-1 hover:shadow-xl"
                                    :style="delays[index]"
                                />
                            </div>
                        </template>
                        <EmptyState v-else />
                        <InertiaPagination :paginator="props.blueprints" :only="['blueprints', 'result_count']" />
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
