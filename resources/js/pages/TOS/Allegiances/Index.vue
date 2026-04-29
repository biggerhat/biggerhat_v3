<script setup lang="ts">
import type { Paginator } from '@/types/tos';
import AllegianceLogo from '@/components/AllegianceLogo.vue';
import CardSkeleton from '@/components/CardSkeleton.vue';
import EmptyState from '@/components/EmptyState.vue';
import InertiaPagination from '@/components/InertiaPagination.vue';
import ListSearchBar from '@/components/ListSearchBar.vue';
import PageBanner from '@/components/PageBanner.vue';
import TableSkeleton from '@/components/TableSkeleton.vue';
import TosText from '@/components/TosText.vue';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
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
    allegiances: Paginator<Allegiance>;
    name_search: string | null;
    page_view: string;
}>();

const { filterParams, activeFilterCount, filter, clear, handleNameKeydown, clearNameSearch, handleViewChange, isLoading } = useListFiltering(
    {
        name_search: props.name_search as string | null,
        page_view: props.page_view as string | null,
    },
    { routeName: 'tos.allegiances.index', filterKeys: [], only: ['allegiances', 'name_search', 'page_view'] },
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
            :page-view="filterParams.page_view"
            :active-filter-count="activeFilterCount"
            placeholder="Search allegiances by name..."
            @update:page-view="handleViewChange"
            @name-keydown="handleNameKeydown"
            @clear-search="clearNameSearch"
            @filter="filter"
            @clear="clear"
        />

        <div class="container mx-auto sm:px-4">
            <div v-if="isLoading && filterParams.page_view === 'table'" class="overflow-auto">
                <TableSkeleton :rows="8" :cols="4" />
            </div>
            <div v-else-if="isLoading" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <CardSkeleton v-for="n in 6" :key="`skeleton-${n}`" />
            </div>

            <div v-else-if="filterParams.page_view === 'table' && allegiances.data.length" class="overflow-auto rounded-md border">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead class="w-12"></TableHead>
                            <TableHead>Name</TableHead>
                            <TableHead>Type</TableHead>
                            <TableHead>Description</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="a in allegiances.data" :key="a.id" class="transition-colors hover:bg-muted/40">
                            <TableCell>
                                <Link :href="route('tos.allegiances.view', a.slug)">
                                    <AllegianceLogo :allegiance="a.slug" class-name="size-8" />
                                </Link>
                            </TableCell>
                            <TableCell class="font-medium">
                                <Link :href="route('tos.allegiances.view', a.slug)" class="hover:underline">{{ a.name }}</Link>
                                <Badge v-if="a.is_syndicate" variant="outline" class="ml-2 text-[10px]">Syndicate</Badge>
                            </TableCell>
                            <TableCell class="text-xs capitalize">{{ a.type }}</TableCell>
                            <TableCell class="max-w-md text-xs text-muted-foreground line-clamp-2">
                                <TosText v-if="a.description" :text="a.description" />
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>

            <div v-else-if="allegiances.data.length" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <Link
                    v-for="a in allegiances.data"
                    :key="a.id"
                    :href="route('tos.allegiances.view', a.slug)"
                    class="block rounded-lg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                >
                    <Card class="group/card h-full overflow-hidden transition-all duration-200 ease-out hover:-translate-y-0.5 hover:border-primary/30 hover:shadow-md">
                        <div :class="['h-1 w-full', a.color_slug ? `bg-${a.color_slug}` : 'bg-primary/40']" />
                        <CardContent class="p-4">
                            <div class="mb-3 flex items-start gap-3">
                                <AllegianceLogo
                                    :allegiance="a.slug"
                                    class-name="size-14 shrink-0 transition-transform group-hover/card:scale-105 sm:size-16"
                                />
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-start justify-between gap-2">
                                        <span class="text-base font-semibold leading-tight">{{ a.name }}</span>
                                        <Badge v-if="a.is_syndicate" variant="outline" class="shrink-0 text-[10px]">Syndicate</Badge>
                                    </div>
                                    <p class="mt-0.5 text-[11px] capitalize text-muted-foreground">{{ a.type }}</p>
                                </div>
                            </div>
                            <p v-if="a.description" class="line-clamp-3 text-xs text-muted-foreground"><TosText :text="a.description" /></p>
                        </CardContent>
                    </Card>
                </Link>
            </div>

            <EmptyState v-else :icon="Shield" title="No allegiances yet" description="Check back once data has been seeded." />

            <InertiaPagination v-if="!isLoading" :paginator="allegiances" :only="['allegiances', 'name_search', 'page_view']" />
        </div>
    </div>
</template>
