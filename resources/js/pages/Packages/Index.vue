<script setup lang="ts">
import CardSkeleton from '@/components/CardSkeleton.vue';
import ClearableSelect from '@/components/ClearableSelect.vue';
import EmptyState from '@/components/EmptyState.vue';
import FactionLogo from '@/components/FactionLogo.vue';
import InertiaPagination from '@/components/InertiaPagination.vue';
import ListSearchBar from '@/components/ListSearchBar.vue';
import PageBanner from '@/components/PageBanner.vue';
import SearchableSelect from '@/components/SearchableSelect.vue';
import TableSkeleton from '@/components/TableSkeleton.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useListFiltering } from '@/composables/useListFiltering';
import { useStaggeredEntry } from '@/composables/useStaggeredEntry';
import type { SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { BookMarked, Heart, Plus } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    packages: any;
    result_count: number;
    factions: Record<string, any>;
    categories: any[];
    sculpt_versions: any[];
    characters: any[];
}>();

const filterKeys = ['name_search', 'faction', 'category', 'sculpt_version', 'character'] as const;

const { filterParams, activeFilterCount, filter, clear, handleNameKeydown, clearNameSearch, handleViewChange, isLoading } = useListFiltering(
    {
        name_search: null as string | null,
        faction: null as string | null,
        category: null as string | null,
        sculpt_version: null as string | null,
        character: null as string | null,
        page_view: null as string | null,
    },
    {
        routeName: 'packages.index',
        filterKeys,
        only: ['packages', 'result_count'],
    },
);

const toggleFaction = (faction: string) => {
    filterParams.value.faction = filterParams.value.faction === faction ? null : faction;
    filter();
};

const packageCount = computed(() => props.packages?.data?.length ?? 0);
const { delays } = useStaggeredEntry(packageCount);

const page = usePage<SharedData>();

const collectionPackageIds = computed(() => new Set(page.props.auth?.collection_package_ids ?? []));
const wishlistPackageIds = computed(() => {
    const ids = new Set<number>();
    for (const wl of Object.values(page.props.auth?.wishlist_items ?? {})) {
        for (const id of wl.packages) ids.add(id);
    }
    return ids;
});

const isLoggedIn = computed(() => !!page.props.auth?.user);
const isCollected = (pkgId: number) => collectionPackageIds.value.has(pkgId);
const isWishlisted = (pkgId: number) => wishlistPackageIds.value.has(pkgId);

const addPackageToCollection = async (packageId: number) => {
    // Optimistically update shared auth data
    const pkgIds = page.props.auth.collection_package_ids;
    if (!pkgIds.includes(packageId)) pkgIds.push(packageId);

    await fetch(route('collection.add_package'), {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '',
        },
        body: JSON.stringify({ package_id: packageId }),
    });
};

const formatPrice = (cents: number | null) => {
    if (!cents) return '-';
    return `$${(cents / 100).toFixed(2)}`;
};
</script>

<template>
    <Head title="Packages" />
    <div class="relative">
        <div
            class="pointer-events-none absolute inset-x-0 top-0 h-64 opacity-[0.07] dark:opacity-[0.12]"
            :style="{ background: 'radial-gradient(ellipse at top, hsl(var(--primary)) 0%, transparent 70%)' }"
        />
        <PageBanner title="Package Directory" class="mb-2">
            <template #subtitle>
                <div class="my-auto px-2 py-0 text-xs text-muted-foreground md:py-2 md:text-sm md:text-foreground">
                    {{ props.result_count }} {{ props.result_count === 1 ? 'package' : 'packages' }} found
                </div>
            </template>
        </PageBanner>

        <ListSearchBar
            v-model:name-search="filterParams.name_search"
            :page-view="filterParams.page_view"
            @update:page-view="handleViewChange"
            :active-filter-count="activeFilterCount"
            placeholder="Search packages by name..."
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
                        <label class="text-sm font-medium">Category</label>
                        <ClearableSelect
                            v-model="filterParams.category"
                            placeholder="Any Category"
                            :options="props.categories"
                            trigger-class="border-2 border-primary rounded"
                        />
                    </div>
                    <div class="space-y-2">
                        <label class="text-sm font-medium">Edition</label>
                        <ClearableSelect
                            v-model="filterParams.sculpt_version"
                            placeholder="Any Edition"
                            :options="props.sculpt_versions"
                            trigger-class="border-2 border-primary rounded"
                        />
                    </div>
                </div>
            </template>
        </ListSearchBar>

        <!-- Faction logos row -->
        <div class="container mx-auto mb-2 sm:px-4">
            <div class="flex flex-wrap items-center gap-2">
                <button
                    v-for="(faction, key) in factions"
                    :key="key"
                    @click="toggleFaction(faction.slug)"
                    class="rounded-full p-1 transition-all"
                    :class="filterParams.faction === faction.slug ? 'ring-2 ring-primary' : 'opacity-50 hover:opacity-100'"
                >
                    <FactionLogo :faction="faction.slug" class-name="h-6 w-6" />
                </button>
            </div>
        </div>

        <!-- Main content area -->
        <div class="container mx-auto sm:px-4">
            <div class="flex gap-6">
                <!-- Desktop sidebar filters -->
                <aside class="hidden w-56 shrink-0 md:block lg:w-64">
                    <div class="space-y-3 pr-2">
                        <div class="space-y-1">
                            <label class="text-xs font-medium text-muted-foreground">Character</label>
                            <SearchableSelect v-model="filterParams.character" placeholder="Any Character" :options="props.characters" />
                        </div>
                        <div class="space-y-1">
                            <label class="text-xs font-medium text-muted-foreground">Category</label>
                            <ClearableSelect v-model="filterParams.category" placeholder="Any Category" :options="props.categories" />
                        </div>
                        <div class="space-y-1">
                            <label class="text-xs font-medium text-muted-foreground">Edition</label>
                            <ClearableSelect v-model="filterParams.sculpt_version" placeholder="Any Edition" :options="props.sculpt_versions" />
                        </div>

                        <!-- Action buttons -->
                        <div class="flex gap-2 pt-2">
                            <Button class="flex-1" @click="filter">Search</Button>
                            <Button variant="outline" class="flex-1" @click="clear">Clear</Button>
                        </div>
                    </div>
                </aside>

                <!-- Results area -->
                <div class="min-w-0 flex-1">
                    <div v-if="isLoading && filterParams.page_view === 'table'" class="overflow-auto">
                        <TableSkeleton :rows="8" :cols="5" />
                    </div>
                    <div v-else-if="isLoading">
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            <CardSkeleton v-for="n in 6" :key="`skeleton-${n}`" />
                        </div>
                    </div>
                    <div v-else-if="filterParams.page_view === 'table'" class="overflow-auto">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Package</TableHead>
                                    <TableHead>SKU</TableHead>
                                    <TableHead>Category</TableHead>
                                    <TableHead>Edition</TableHead>
                                    <TableHead>MSRP</TableHead>
                                    <TableHead>Characters</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <template v-if="props.packages?.data?.length">
                                    <TableRow v-for="pkg in props.packages.data" :key="pkg.id">
                                        <TableCell class="font-medium">
                                            <div class="flex items-center gap-1.5">
                                                <Link :href="route('packages.view', { package: pkg.slug })" class="text-primary hover:underline">
                                                    {{ pkg.name }}
                                                </Link>
                                                <BookMarked
                                                    v-if="isCollected(pkg.id)"
                                                    class="size-3.5 shrink-0"
                                                    style="color: #059669"
                                                    title="In Collection"
                                                />
                                                <button
                                                    v-else-if="isLoggedIn"
                                                    class="inline-flex size-5 items-center justify-center rounded text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                                                    title="Add to Collection"
                                                    @click="addPackageToCollection(pkg.id)"
                                                >
                                                    <Plus class="size-3.5" />
                                                </button>
                                                <Heart
                                                    v-if="isWishlisted(pkg.id)"
                                                    class="size-3.5 shrink-0 fill-current"
                                                    style="color: #f43f5e"
                                                    title="On Wishlist"
                                                />
                                            </div>
                                        </TableCell>
                                        <TableCell>{{ pkg.sku ?? '-' }}</TableCell>
                                        <TableCell>
                                            <Badge v-if="pkg.category_label" variant="outline" class="text-xs">
                                                {{ pkg.category_label }}
                                            </Badge>
                                            <span v-else>-</span>
                                        </TableCell>
                                        <TableCell>
                                            <Badge variant="secondary" class="text-xs">
                                                {{ pkg.sculpt_version_label }}
                                            </Badge>
                                        </TableCell>
                                        <TableCell>{{ formatPrice(pkg.msrp) }}</TableCell>
                                        <TableCell>{{ pkg.characters_count }}</TableCell>
                                    </TableRow>
                                </template>
                                <template v-else>
                                    <TableRow>
                                        <TableCell :colspan="6">
                                            <EmptyState />
                                        </TableCell>
                                    </TableRow>
                                </template>
                            </TableBody>
                        </Table>
                        <InertiaPagination :paginator="props.packages" :only="['packages', 'result_count']" />
                    </div>
                    <div v-else>
                        <template v-if="props.packages?.data?.length">
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                <Link
                                    v-for="(pkg, index) in props.packages.data"
                                    :key="pkg.slug"
                                    :href="route('packages.view', { package: pkg.slug })"
                                    class="animate-fade-in-up opacity-0"
                                    :style="delays[index]"
                                >
                                    <Card class="h-full transition-all duration-300 hover:-translate-y-1 hover:shadow-xl">
                                        <div v-if="pkg.front_image" class="overflow-hidden rounded-t-xl">
                                            <img
                                                :src="`/storage/${pkg.front_image}`"
                                                :alt="pkg.name"
                                                loading="lazy"
                                                decoding="async"
                                                class="aspect-[3/4] w-full object-cover"
                                            />
                                        </div>
                                        <CardHeader class="pb-2">
                                            <CardTitle class="text-base">{{ pkg.name }}</CardTitle>
                                        </CardHeader>
                                        <CardContent>
                                            <div class="flex flex-wrap items-center gap-2">
                                                <div v-if="pkg.factions?.length" class="flex gap-1">
                                                    <FactionLogo
                                                        v-for="faction in pkg.factions"
                                                        :key="faction.value"
                                                        :faction="faction.value"
                                                        class-name="h-5 w-5"
                                                    />
                                                </div>
                                                <Badge v-if="pkg.category_label" variant="outline" class="text-xs">
                                                    {{ pkg.category_label }}
                                                </Badge>
                                                <Badge v-if="pkg.sculpt_version_label" variant="secondary" class="text-xs">
                                                    {{ pkg.sculpt_version_label }}
                                                </Badge>
                                                <span class="ml-auto text-xs text-muted-foreground">{{ pkg.characters_count }} Characters</span>
                                            </div>
                                            <div v-if="isCollected(pkg.id) || isWishlisted(pkg.id)" class="mt-1.5 flex items-center gap-2">
                                                <span v-if="isCollected(pkg.id)" class="flex items-center gap-1 text-[11px]" style="color: #059669">
                                                    <BookMarked class="size-3" />
                                                    Collected
                                                </span>
                                                <span v-if="isWishlisted(pkg.id)" class="flex items-center gap-1 text-[11px]" style="color: #f43f5e">
                                                    <Heart class="size-3 fill-current" />
                                                    Wishlisted
                                                </span>
                                            </div>
                                            <button
                                                v-if="isLoggedIn && !isCollected(pkg.id)"
                                                class="mt-1.5 flex items-center gap-1 text-[11px] text-muted-foreground transition-colors hover:text-foreground"
                                                @click.prevent="addPackageToCollection(pkg.id)"
                                            >
                                                <Plus class="size-3" />
                                                Add to Collection
                                            </button>
                                        </CardContent>
                                    </Card>
                                </Link>
                            </div>
                        </template>
                        <EmptyState v-else />
                        <InertiaPagination :paginator="props.packages" :only="['packages', 'result_count']" />
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
