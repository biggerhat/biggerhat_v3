<script setup lang="ts">
import type { Paginator } from '@/types/tos';
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
    abilities: Paginator<Ability>;
    name_search: string | null;
    page_view: string;
}>();

const { filterParams, activeFilterCount, filter, clear, handleNameKeydown, clearNameSearch, handleViewChange, isLoading } = useListFiltering(
    {
        name_search: props.name_search as string | null,
        page_view: props.page_view as string | null,
    },
    { routeName: 'tos.abilities.index', filterKeys: [], only: ['abilities', 'name_search', 'page_view'] },
);
</script>

<template>
    <Head title="TOS Abilities" />
    <div class="relative">
        <PageBanner title="Abilities" class="mb-2">
            <template #subtitle>
                <div class="my-auto px-2 py-0 text-xs text-muted-foreground md:py-2 md:text-sm md:text-foreground">
                    {{ abilities.total }} {{ abilities.total === 1 ? 'ability' : 'abilities' }}
                </div>
            </template>
        </PageBanner>

        <ListSearchBar
            v-model:name-search="filterParams.name_search"
            :page-view="filterParams.page_view"
            :active-filter-count="activeFilterCount"
            placeholder="Search abilities by name..."
            @update:page-view="handleViewChange"
            @name-keydown="handleNameKeydown"
            @clear-search="clearNameSearch"
            @filter="filter"
            @clear="clear"
        />

        <div class="container mx-auto sm:px-4">
            <!-- Loading -->
            <div v-if="isLoading && filterParams.page_view === 'table'" class="overflow-auto">
                <TableSkeleton :rows="8" :cols="3" />
            </div>
            <div v-else-if="isLoading" class="grid gap-3 sm:grid-cols-2">
                <CardSkeleton v-for="n in 6" :key="`skeleton-${n}`" />
            </div>

            <!-- Table view -->
            <div v-else-if="filterParams.page_view === 'table' && abilities.data.length" class="overflow-auto rounded-md border">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Name</TableHead>
                            <TableHead>Scope</TableHead>
                            <TableHead>Body</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="a in abilities.data" :key="a.id">
                            <TableCell class="font-medium">{{ a.name }}</TableCell>
                            <TableCell class="text-xs">
                                <Badge v-if="a.is_general" variant="outline" class="text-[10px]">General</Badge>
                                <Badge v-else-if="a.allegiance" variant="outline" class="text-[10px]">{{ a.allegiance.name }}</Badge>
                                <span v-else class="text-muted-foreground">—</span>
                            </TableCell>
                            <TableCell class="max-w-md text-xs text-muted-foreground">
                                <TosText v-if="a.body" :text="a.body" />
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>

            <!-- Card view -->
            <div v-else-if="abilities.data.length" class="grid gap-3 sm:grid-cols-2">
                <Card v-for="a in abilities.data" :key="a.id">
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

            <InertiaPagination v-if="!isLoading" :paginator="abilities" :only="['abilities', 'name_search', 'page_view']" />
        </div>
    </div>
</template>
