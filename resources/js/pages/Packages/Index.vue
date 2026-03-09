<script setup lang="ts">
import CardSkeleton from '@/components/CardSkeleton.vue';
import ClearableSelect from '@/components/ClearableSelect.vue';
import EmptyState from '@/components/EmptyState.vue';
import FactionLogo from '@/components/FactionLogo.vue';
import FilterPanel from '@/components/FilterPanel.vue';
import InertiaPagination from '@/components/InertiaPagination.vue';
import PageBanner from '@/components/PageBanner.vue';
import TableSkeleton from '@/components/TableSkeleton.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { cleanObject } from '@/composables/CleanObject';
import { useStaggeredEntry } from '@/composables/useStaggeredEntry';
import { Head, Link, router } from '@inertiajs/vue3';
import { LayoutGrid, List, Search, X } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';

const props = defineProps<{
    packages: any;
    result_count: number;
    factions: Record<string, any>;
    categories: any[];
    sculpt_versions: any[];
}>();

const filterParams = ref({
    name_search: null as string | null,
    faction: null as string | null,
    category: null as string | null,
    sculpt_version: null as string | null,
    page_view: null as string | null,
});

const filterKeys = ['name_search', 'faction', 'category', 'sculpt_version'] as const;

const activeFilterCount = computed(() => {
    return filterKeys.filter((key) => filterParams.value[key] != null && filterParams.value[key] !== '').length;
});

const filter = () => {
    const params: Record<string, string | null> = { ...filterParams.value };
    for (const key in params) {
        if (params[key] === '') {
            params[key] = null;
        }
    }
    params.page = null;
    router.get(route('packages.index'), cleanObject(params), {
        only: ['packages', 'result_count'],
        replace: true,
        preserveState: true,
    });
};

const clear = () => {
    for (const key of filterKeys) {
        filterParams.value[key] = null;
    }
    filterParams.value.page_view = 'cards';
    filter();
};

const handleNameKeydown = (e: KeyboardEvent) => {
    if (e.key === 'Enter') {
        filter();
    }
};

const clearNameSearch = () => {
    filterParams.value.name_search = null;
    filter();
};

const handleViewChange = (value: string) => {
    filterParams.value.page_view = value;
    filter();
};

const toggleFaction = (faction: string) => {
    filterParams.value.faction = filterParams.value.faction === faction ? null : faction;
    filter();
};

const urlParams = new URLSearchParams(window.location.search);
onMounted(() => {
    filterParams.value.name_search = urlParams.get('name_search');
    filterParams.value.faction = urlParams.get('faction');
    filterParams.value.category = urlParams.get('category');
    filterParams.value.sculpt_version = urlParams.get('sculpt_version');
    filterParams.value.page_view = urlParams.get('page_view') ?? 'cards';
});

const packageCount = computed(() => props.packages?.data?.length ?? 0);
const { delays } = useStaggeredEntry(packageCount);

const isLoading = ref(false);
onMounted(() => {
    router.on('start', () => {
        isLoading.value = true;
    });
    router.on('finish', () => {
        isLoading.value = false;
    });
});

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

        <!-- Search bar -->
        <div class="container mx-auto mb-3 px-4">
            <div class="relative">
                <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                <Input
                    v-model="filterParams.name_search"
                    type="text"
                    placeholder="Search packages by name..."
                    class="border-2 border-primary pl-10 pr-10"
                    @keydown="handleNameKeydown"
                />
                <button
                    v-if="filterParams.name_search"
                    class="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                    @click="clearNameSearch"
                >
                    <X class="h-4 w-4" />
                </button>
            </div>
        </div>

        <!-- Faction logos row -->
        <div class="container mx-auto mb-2 px-4">
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

        <!-- Tabs + filter trigger -->
        <div class="container mx-auto mb-2 flex items-center justify-between px-4">
            <Tabs :model-value="filterParams.page_view" @update:model-value="handleViewChange">
                <TabsList>
                    <TabsTrigger value="cards">
                        <LayoutGrid class="h-4 w-4" />
                        <span class="hidden sm:inline">Cards</span>
                    </TabsTrigger>
                    <TabsTrigger value="table">
                        <List class="h-4 w-4" />
                        <span class="hidden sm:inline">Table</span>
                    </TabsTrigger>
                </TabsList>
            </Tabs>
            <div class="flex items-center gap-2">
                <Badge v-if="activeFilterCount > 0" variant="secondary" class="text-xs">
                    {{ activeFilterCount }} {{ activeFilterCount === 1 ? 'filter' : 'filters' }}
                </Badge>
                <!-- Mobile-only filter trigger -->
                <div class="md:hidden">
                    <FilterPanel :filter-count="activeFilterCount" @filter="filter" @clear="clear">
                        <div class="grid gap-4">
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
                    </FilterPanel>
                </div>
            </div>
        </div>

        <!-- Main content area -->
        <div class="container mx-auto px-4">
            <div class="flex gap-6">
                <!-- Desktop sidebar filters -->
                <aside class="hidden w-64 shrink-0 md:block">
                    <div class="space-y-3 pr-2">
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
                                            <Link :href="route('packages.view', { package: pkg.slug })" class="text-primary hover:underline">
                                                {{ pkg.name }}
                                            </Link>
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
                                            <img :src="`/storage/${pkg.front_image}`" :alt="pkg.name" class="aspect-[3/4] w-full object-cover" />
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
