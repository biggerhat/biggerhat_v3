<script setup lang="ts">
import EmptyState from '@/components/EmptyState.vue';
import FactionLogo from '@/components/FactionLogo.vue';
import PageBanner from '@/components/PageBanner.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useStaggeredEntry } from '@/composables/useStaggeredEntry';
import { Head, Link, router } from '@inertiajs/vue3';
import { BarChart3, BookOpen, Check, Copy, Globe, Grid2x2, Hammer, Library, Lock, Minus, Package, Paintbrush, Plus, Search, Trash2, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface CollectionItem {
    miniature_id: number;
    miniature_name: string;
    miniature_slug: string;
    front_image: string | null;
    character_id: number;
    character_name: string;
    character_slug: string;
    faction: string | null;
    station: string | null;
    keywords: string[];
    quantity: number;
    is_built: boolean;
    is_painted: boolean;
    standard_miniature_id: number | null;
}

interface FactionStat {
    faction: string;
    name: string;
    color: string;
    logo: string;
    total: number;
    owned: number;
    percent: number;
}

interface KeywordStat {
    name: string;
    slug: string;
    total: number;
    owned: number;
    percent: number;
}

interface OwnedPackage {
    id: number;
    name: string;
    slug: string;
    front_image: string | null;
    factions: Array<{ value: string; label: string; color: string; logo: string }>;
}

const props = defineProps<{
    collection: CollectionItem[];
    owned_packages: OwnedPackage[];
    faction_stats: FactionStat[];
    keyword_stats: KeywordStat[];
    totals: {
        characters: number;
        owned_characters: number;
        owned_miniatures: number;
        owned_packages: number;
        percent: number;
        built: number;
        painted: number;
        built_percent: number;
        painted_percent: number;
    };
    factions: Record<string, { slug: string; name: string; color: string; logo: string }>;
    is_owner: boolean;
    share_code: string;
    is_public: boolean;
    owner_name?: string;
}>();

const activeTab = ref('collection');

// ─── Sharing ───
const copied = ref(false);
const copyShareLink = async () => {
    try {
        const url = route('collection.share', { shareCode: props.share_code });
        await navigator.clipboard.writeText(url);
        copied.value = true;
        setTimeout(() => (copied.value = false), 2000);
    } catch {
        // Clipboard API may be blocked in insecure contexts
    }
};

const togglePublic = () => {
    router.post(route('collection.toggle_public'), {}, { preserveScroll: true, preserveState: true });
};

// ─── Filters ───
const filterText = ref('');
const filterFaction = ref<string | null>(null);
const filterStatus = ref<string | null>(null);
const hasActiveFilter = computed(() => !!filterText.value || !!filterFaction.value || !!filterStatus.value);

const clearFilters = () => {
    filterText.value = '';
    filterFaction.value = null;
    filterStatus.value = null;
};

// Filtered collection
const filteredCollection = computed(() => {
    let items = props.collection;
    if (filterText.value) {
        const search = filterText.value.toLowerCase();
        items = items.filter(
            (i) =>
                i.character_name?.toLowerCase().includes(search) ||
                i.miniature_name?.toLowerCase().includes(search) ||
                i.keywords.some((k) => k.toLowerCase().includes(search)),
        );
    }
    if (filterFaction.value) {
        items = items.filter((i) => i.faction === filterFaction.value);
    }
    if (filterStatus.value) {
        switch (filterStatus.value) {
            case 'unbuilt':
                items = items.filter((i) => !i.is_built);
                break;
            case 'built':
                items = items.filter((i) => i.is_built);
                break;
            case 'unpainted':
                items = items.filter((i) => !i.is_painted);
                break;
            case 'painted':
                items = items.filter((i) => i.is_painted);
                break;
        }
    }
    return items;
});

// Group by character
const groupedByCharacter = computed(() => {
    const map = new Map<number, { character_name: string; character_slug: string; faction: string | null; miniatures: CollectionItem[] }>();
    for (const item of filteredCollection.value) {
        if (!map.has(item.character_id)) {
            map.set(item.character_id, {
                character_name: item.character_name,
                character_slug: item.character_slug,
                faction: item.faction,
                miniatures: [],
            });
        }
        map.get(item.character_id)!.miniatures.push(item);
    }
    return [...map.values()].sort((a, b) => a.character_name.localeCompare(b.character_name));
});

// ─── Staggered entry ───
const groupCount = computed(() => groupedByCharacter.value.length);
const { delays } = useStaggeredEntry(groupCount);

const factionCount = computed(() => props.faction_stats.length);
const { delays: factionDelays } = useStaggeredEntry(factionCount);

const keywordCount = computed(() => sortedKeywordStats.value.length);
const { delays: keywordDelays } = useStaggeredEntry(keywordCount, 20, 400);

const packageCount = computed(() => props.owned_packages.length);
const { delays: packageDelays } = useStaggeredEntry(packageCount);

// ─── Mutations ───
const processing = ref(false);

const updateQuantity = (miniatureId: number, quantity: number) => {
    processing.value = true;
    router.post(
        route('collection.toggle'),
        { miniature_id: miniatureId, quantity: Math.max(0, quantity) },
        { preserveScroll: true, preserveState: true, onFinish: () => (processing.value = false) },
    );
};

const updateStatus = (miniatureId: number, field: 'is_built' | 'is_painted', value: boolean) => {
    processing.value = true;
    router.post(
        route('collection.update_status'),
        { miniature_id: miniatureId, [field]: value },
        { preserveScroll: true, preserveState: true, onFinish: () => (processing.value = false) },
    );
};

const removeMiniature = (miniatureId: number) => {
    processing.value = true;
    router.post(
        route('collection.remove'),
        { miniature_id: miniatureId },
        {
            preserveScroll: true,
            preserveState: true,
            onFinish: () => (processing.value = false),
        },
    );
};

const removePackage = (packageId: number) => {
    processing.value = true;
    router.post(
        route('collection.toggle_package'),
        { package_id: packageId },
        {
            preserveScroll: true,
            preserveState: true,
            onFinish: () => (processing.value = false),
        },
    );
};

// Sorted keyword stats
const sortedKeywordStats = computed(() => [...props.keyword_stats].sort((a, b) => b.percent - a.percent));

// Top factions by owned count
const topFactions = computed(() =>
    [...props.faction_stats].filter((f) => f.owned > 0).sort((a, b) => b.owned - a.owned || b.percent - a.percent).slice(0, 5),
);
</script>

<template>
    <Head :title="is_owner ? 'My Collection' : `${owner_name}'s Collection`" />

    <div class="relative">
        <div
            class="pointer-events-none absolute inset-x-0 top-0 h-64 opacity-[0.07] dark:opacity-[0.12]"
            :style="{ background: 'radial-gradient(ellipse at top, hsl(var(--primary)) 0%, transparent 70%)' }"
        />

        <PageBanner :title="is_owner ? 'My Collection' : `${owner_name}'s Collection`">
            <template #subtitle>
                <div class="flex flex-wrap items-center gap-3 px-2">
                    <span class="text-sm text-muted-foreground">
                        {{ is_owner ? 'Track your miniatures, packages, and collection progress.' : `Viewing ${owner_name}'s collection.` }}
                    </span>
                    <div v-if="is_owner" class="flex items-center gap-1.5">
                        <Button variant="ghost" size="sm" class="h-7 gap-1 text-xs" @click="togglePublic">
                            <Globe v-if="is_public" class="size-3.5" />
                            <Lock v-else class="size-3.5" />
                            {{ is_public ? 'Public' : 'Private' }}
                        </Button>
                        <Button
                            variant="ghost"
                            size="sm"
                            class="h-7 gap-1 text-xs"
                            :disabled="!is_public"
                            :title="!is_public ? 'Make public to share' : 'Copy share link'"
                            @click="copyShareLink"
                        >
                            <Check v-if="copied" class="size-3.5 text-green-500" />
                            <Copy v-else class="size-3.5" />
                            {{ copied ? 'Copied!' : 'Share' }}
                        </Button>
                    </div>
                </div>
            </template>
        </PageBanner>

        <div class="container mx-auto mt-6 px-4 lg:px-6">
            <!-- Overview stats -->
            <div class="mb-6 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <Card class="overflow-hidden">
                    <CardContent class="p-4 text-center">
                        <div class="text-2xl font-bold tracking-tight sm:text-3xl">{{ totals.owned_characters }}</div>
                        <div class="mt-0.5 text-xs text-muted-foreground">Characters ({{ totals.percent }}%)</div>
                        <div class="mt-2 h-2 w-full overflow-hidden rounded-full bg-muted">
                            <div class="h-full rounded-full bg-primary transition-all duration-500" :style="{ width: `${totals.percent}%` }" />
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent class="p-4 text-center">
                        <div class="text-2xl font-bold tracking-tight sm:text-3xl">{{ totals.owned_miniatures }}</div>
                        <div class="mt-0.5 text-xs text-muted-foreground">Total Miniatures</div>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent class="p-4 text-center">
                        <div class="text-2xl font-bold tracking-tight sm:text-3xl">{{ totals.owned_packages }}</div>
                        <div class="mt-0.5 text-xs text-muted-foreground">Packages</div>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent class="p-4 text-center">
                        <div class="text-2xl font-bold tracking-tight sm:text-3xl">{{ totals.characters }}</div>
                        <div class="mt-0.5 text-xs text-muted-foreground">Total Available</div>
                    </CardContent>
                </Card>
            </div>

            <!-- Tabs -->
            <Tabs v-model="activeTab">
                <TabsList class="mb-4">
                    <TabsTrigger value="collection">
                        <Library class="mr-1.5 size-4" />
                        <span class="hidden sm:inline">Collection</span>
                    </TabsTrigger>
                    <TabsTrigger value="packages">
                        <Package class="mr-1.5 size-4" />
                        <span class="hidden sm:inline">Packages</span>
                    </TabsTrigger>
                    <TabsTrigger value="factions">
                        <Grid2x2 class="mr-1.5 size-4" />
                        <span class="hidden sm:inline">Factions</span>
                    </TabsTrigger>
                    <TabsTrigger value="keywords">
                        <BookOpen class="mr-1.5 size-4" />
                        <span class="hidden sm:inline">Keywords</span>
                    </TabsTrigger>
                    <TabsTrigger value="stats">
                        <BarChart3 class="mr-1.5 size-4" />
                        <span class="hidden sm:inline">Stats</span>
                    </TabsTrigger>
                </TabsList>

                <!-- Collection Tab -->
                <TabsContent value="collection">
                    <!-- Filters -->
                    <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center">
                        <div class="relative min-w-0 flex-1">
                            <Search class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input v-model="filterText" placeholder="Search collection..." class="pl-9 pr-9" />
                            <button
                                v-if="filterText"
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                                @click="filterText = ''"
                            >
                                <X class="size-4" />
                            </button>
                        </div>
                        <div class="flex items-center gap-2">
                            <Select v-model="filterFaction">
                                <SelectTrigger class="w-full sm:w-40">
                                    <SelectValue placeholder="All Factions" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="(f, key) in factions" :key="key" :value="key">{{ f.name }}</SelectItem>
                                </SelectContent>
                            </Select>
                            <Select v-model="filterStatus">
                                <SelectTrigger class="w-full sm:w-40">
                                    <SelectValue placeholder="All Status" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="unbuilt">Unbuilt</SelectItem>
                                    <SelectItem value="built">Built</SelectItem>
                                    <SelectItem value="unpainted">Unpainted</SelectItem>
                                    <SelectItem value="painted">Painted</SelectItem>
                                </SelectContent>
                            </Select>
                            <Button v-if="hasActiveFilter" variant="ghost" size="sm" @click="clearFilters"> Clear </Button>
                        </div>
                    </div>

                    <div v-if="hasActiveFilter" class="mb-3 text-sm text-muted-foreground">
                        Showing {{ filteredCollection.length }} of {{ collection.length }} miniatures
                    </div>

                    <EmptyState
                        v-if="filteredCollection.length === 0 && hasActiveFilter"
                        title="No miniatures match your filter"
                        description="Try adjusting your search or faction filter."
                    />

                    <div v-else-if="filteredCollection.length === 0" class="flex flex-col items-center justify-center py-16 text-muted-foreground">
                        <Library class="mb-3 size-12 opacity-30" />
                        <p class="text-sm font-medium">{{ is_owner ? 'Your collection is empty' : 'This collection is empty' }}</p>
                        <p v-if="is_owner" class="mt-1 text-xs">Add miniatures from character or package pages to get started.</p>
                    </div>

                    <div v-else class="space-y-2">
                        <div
                            v-for="(group, index) in groupedByCharacter"
                            :key="group.character_slug"
                            class="animate-fade-in-up rounded-lg border bg-card opacity-0 transition-all duration-200 hover:shadow-md"
                            :style="delays[index]"
                        >
                            <div
                                v-for="(item, itemIndex) in group.miniatures"
                                :key="item.miniature_id"
                                class="px-3 py-2.5 sm:px-4"
                                :class="{ 'border-t border-border/50': itemIndex > 0 }"
                            >
                                <div class="flex items-center gap-2 sm:gap-3">
                                    <FactionLogo v-if="item.faction" :faction="item.faction" class-name="size-5 shrink-0" />
                                    <Link
                                        :href="
                                            route('characters.view', {
                                                character: item.character_slug,
                                                miniature: item.standard_miniature_id ?? item.miniature_id,
                                                slug: item.miniature_slug,
                                            })
                                        "
                                        class="min-w-0 flex-1"
                                    >
                                        <div class="truncate text-sm font-medium transition-colors hover:text-primary">{{ item.character_name }}</div>
                                        <div v-if="item.miniature_name !== item.character_name" class="truncate text-xs text-muted-foreground">
                                            {{ item.miniature_name }}
                                        </div>
                                    </Link>
                                    <!-- Status icons (view-only, non-owner) -->
                                    <template v-if="!is_owner">
                                        <div class="flex shrink-0 items-center gap-1">
                                            <Hammer
                                                v-if="item.is_built"
                                                class="size-3.5 text-amber-600 dark:text-amber-400"
                                                title="Built"
                                            />
                                            <Paintbrush
                                                v-if="item.is_painted"
                                                class="size-3.5 text-violet-600 dark:text-violet-400"
                                                title="Painted"
                                            />
                                        </div>
                                        <span class="text-sm tabular-nums text-muted-foreground">&times;{{ item.quantity }}</span>
                                    </template>
                                </div>
                                <!-- Owner controls: second row on mobile, inline on desktop -->
                                <div v-if="is_owner" class="mt-1.5 flex items-center gap-1 pl-7 sm:mt-0 sm:pl-0 sm:pt-0">
                                    <Button
                                        :variant="item.is_built ? 'default' : 'outline'"
                                        size="icon"
                                        class="size-7"
                                        :class="item.is_built ? 'bg-amber-600 text-white hover:bg-amber-700' : 'text-muted-foreground'"
                                        :disabled="processing"
                                        :title="item.is_built ? 'Mark as unbuilt' : 'Mark as built'"
                                        @click="updateStatus(item.miniature_id, 'is_built', !item.is_built)"
                                    >
                                        <Hammer class="size-3.5" />
                                    </Button>
                                    <Button
                                        :variant="item.is_painted ? 'default' : 'outline'"
                                        size="icon"
                                        class="size-7"
                                        :class="item.is_painted ? 'bg-violet-600 text-white hover:bg-violet-700' : 'text-muted-foreground'"
                                        :disabled="processing"
                                        :title="item.is_painted ? 'Mark as unpainted' : 'Mark as painted'"
                                        @click="updateStatus(item.miniature_id, 'is_painted', !item.is_painted)"
                                    >
                                        <Paintbrush class="size-3.5" />
                                    </Button>
                                    <div class="ml-1 flex shrink-0 items-center gap-1">
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            class="size-7"
                                            :disabled="processing"
                                            @click="updateQuantity(item.miniature_id, item.quantity - 1)"
                                        >
                                            <Minus class="size-3" />
                                        </Button>
                                        <span class="w-6 text-center text-sm font-medium tabular-nums">{{ item.quantity }}</span>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            class="size-7"
                                            :disabled="processing"
                                            @click="updateQuantity(item.miniature_id, item.quantity + 1)"
                                        >
                                            <Plus class="size-3" />
                                        </Button>
                                    </div>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        class="ml-auto size-7 text-destructive hover:text-destructive"
                                        :disabled="processing"
                                        @click="removeMiniature(item.miniature_id)"
                                    >
                                        <Trash2 class="size-3" />
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </div>
                </TabsContent>

                <!-- Packages Tab -->
                <TabsContent value="packages">
                    <div v-if="owned_packages.length === 0" class="flex flex-col items-center justify-center py-16 text-muted-foreground">
                        <Package class="mb-3 size-12 opacity-30" />
                        <p class="text-sm font-medium">{{ is_owner ? 'No packages tracked yet' : 'No packages in this collection' }}</p>
                        <p v-if="is_owner" class="mt-1 text-xs">Add packages from the package pages to get started.</p>
                    </div>
                    <div v-else class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <Card
                            v-for="(pkg, index) in owned_packages"
                            :key="pkg.id"
                            class="animate-fade-in-up opacity-0 transition-all duration-300 hover:-translate-y-0.5 hover:shadow-lg"
                            :style="packageDelays[index]"
                        >
                            <CardContent class="p-4">
                                <div class="flex items-start gap-3">
                                    <img
                                        v-if="pkg.front_image"
                                        :src="`/storage/${pkg.front_image}`"
                                        :alt="pkg.name"
                                        class="size-16 shrink-0 rounded-md object-cover"
                                    />
                                    <div class="min-w-0 flex-1">
                                        <Link
                                            :href="route('packages.view', pkg.slug)"
                                            class="text-sm font-semibold transition-colors hover:text-primary"
                                        >
                                            {{ pkg.name }}
                                        </Link>
                                        <div v-if="pkg.factions?.length" class="mt-1.5 flex flex-wrap gap-1">
                                            <Badge v-for="f in pkg.factions" :key="f.value" variant="secondary" class="gap-1 text-[10px]">
                                                <img :src="f.logo" :alt="f.label" class="size-3" />
                                                {{ f.label }}
                                            </Badge>
                                        </div>
                                    </div>
                                    <Button
                                        v-if="is_owner"
                                        variant="ghost"
                                        size="icon"
                                        class="size-7 shrink-0 text-destructive hover:text-destructive"
                                        :disabled="processing"
                                        @click="removePackage(pkg.id)"
                                    >
                                        <Trash2 class="size-3" />
                                    </Button>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </TabsContent>

                <!-- Factions Tab -->
                <TabsContent value="factions">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <Card
                            v-for="(stat, index) in faction_stats"
                            :key="stat.faction"
                            class="animate-fade-in-up opacity-0 transition-all duration-300 hover:-translate-y-0.5 hover:shadow-lg"
                            :style="factionDelays[index]"
                        >
                            <CardContent class="p-4">
                                <div class="mb-3 flex items-center gap-2.5">
                                    <img :src="stat.logo" :alt="stat.name" class="size-8" />
                                    <div>
                                        <div class="font-semibold">{{ stat.name }}</div>
                                        <div class="text-xs text-muted-foreground">{{ stat.owned }} / {{ stat.total }} characters</div>
                                    </div>
                                </div>
                                <div class="h-2 w-full overflow-hidden rounded-full bg-muted">
                                    <div
                                        class="h-full rounded-full transition-all duration-500"
                                        :style="{ width: `${stat.percent}%`, backgroundColor: `hsl(var(--${stat.color}))` }"
                                    />
                                </div>
                                <div class="mt-1.5 text-right text-xs font-semibold tabular-nums">{{ stat.percent }}%</div>
                            </CardContent>
                        </Card>
                    </div>
                </TabsContent>

                <!-- Keywords Tab -->
                <TabsContent value="keywords">
                    <div class="space-y-1.5">
                        <div
                            v-for="(stat, index) in sortedKeywordStats"
                            :key="stat.slug"
                            class="animate-fade-in-up rounded-lg border bg-card px-3 py-2.5 opacity-0 transition-all duration-200 hover:shadow-md sm:px-4"
                            :style="keywordDelays[index]"
                        >
                            <div class="flex items-center gap-2 sm:gap-3">
                                <Link :href="route('keywords.view', stat.slug)" class="min-w-0 flex-1">
                                    <span class="text-sm font-medium transition-colors hover:text-primary">{{ stat.name }}</span>
                                </Link>
                                <span class="shrink-0 text-xs tabular-nums text-muted-foreground">{{ stat.owned }}/{{ stat.total }}</span>
                                <Badge :variant="stat.percent === 100 ? 'default' : 'secondary'" class="w-14 shrink-0 justify-center text-[10px] tabular-nums">
                                    {{ stat.percent }}%
                                </Badge>
                            </div>
                            <div class="mt-1.5 h-1.5 w-full overflow-hidden rounded-full bg-muted">
                                <div class="h-full rounded-full bg-primary transition-all duration-500" :style="{ width: `${stat.percent}%` }" />
                            </div>
                        </div>
                    </div>
                </TabsContent>

                <!-- Stats Tab -->
                <TabsContent value="stats">
                    <!-- Built / Painted progress -->
                    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <Card>
                            <CardContent class="p-4">
                                <div class="mb-3 flex items-center gap-2.5">
                                    <div class="flex size-8 items-center justify-center rounded-md bg-amber-100 dark:bg-amber-900/30">
                                        <Hammer class="size-4 text-amber-600 dark:text-amber-400" />
                                    </div>
                                    <div>
                                        <div class="font-semibold">Built</div>
                                        <div class="text-xs text-muted-foreground">{{ totals.built }} of {{ collection.length }} miniatures</div>
                                    </div>
                                    <Badge variant="secondary" class="ml-auto tabular-nums">{{ totals.built_percent }}%</Badge>
                                </div>
                                <div class="h-2 w-full overflow-hidden rounded-full bg-muted">
                                    <div
                                        class="h-full rounded-full bg-amber-500 transition-all duration-500"
                                        :style="{ width: `${totals.built_percent}%` }"
                                    />
                                </div>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardContent class="p-4">
                                <div class="mb-3 flex items-center gap-2.5">
                                    <div class="flex size-8 items-center justify-center rounded-md bg-violet-100 dark:bg-violet-900/30">
                                        <Paintbrush class="size-4 text-violet-600 dark:text-violet-400" />
                                    </div>
                                    <div>
                                        <div class="font-semibold">Painted</div>
                                        <div class="text-xs text-muted-foreground">{{ totals.painted }} of {{ collection.length }} miniatures</div>
                                    </div>
                                    <Badge variant="secondary" class="ml-auto tabular-nums">{{ totals.painted_percent }}%</Badge>
                                </div>
                                <div class="h-2 w-full overflow-hidden rounded-full bg-muted">
                                    <div
                                        class="h-full rounded-full bg-violet-500 transition-all duration-500"
                                        :style="{ width: `${totals.painted_percent}%` }"
                                    />
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                        <!-- Top factions -->
                        <Card>
                            <CardHeader class="pb-3">
                                <CardTitle class="text-sm font-semibold uppercase tracking-wider text-muted-foreground">Top Factions</CardTitle>
                            </CardHeader>
                            <CardContent class="space-y-4">
                                <div v-for="stat in topFactions" :key="stat.faction" class="space-y-1.5">
                                    <div class="flex items-center gap-2 text-sm">
                                        <img :src="stat.logo" :alt="stat.name" class="size-5 shrink-0" />
                                        <span class="min-w-0 truncate font-medium">{{ stat.name }}</span>
                                        <span class="ml-auto shrink-0 tabular-nums text-muted-foreground">
                                            {{ stat.owned }}/{{ stat.total }}
                                            <span class="hidden sm:inline">({{ stat.percent }}%)</span>
                                        </span>
                                    </div>
                                    <div class="h-2 w-full overflow-hidden rounded-full bg-muted">
                                        <div
                                            class="h-full rounded-full transition-all duration-500"
                                            :style="{ width: `${stat.percent}%`, backgroundColor: `hsl(var(--${stat.color}))` }"
                                        />
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Top owned keywords -->
                        <Card>
                            <CardHeader class="pb-3">
                                <CardTitle class="text-sm font-semibold uppercase tracking-wider text-muted-foreground"
                                    >Most Complete Keywords</CardTitle
                                >
                            </CardHeader>
                            <CardContent class="space-y-2.5">
                                <div
                                    v-for="stat in sortedKeywordStats.slice(0, 10)"
                                    :key="stat.slug"
                                    class="flex items-center justify-between gap-2 text-sm"
                                >
                                    <Link :href="route('keywords.view', stat.slug)" class="min-w-0 truncate font-medium transition-colors hover:text-primary">
                                        {{ stat.name }}
                                    </Link>
                                    <div class="flex shrink-0 items-center gap-2">
                                        <span class="tabular-nums text-muted-foreground">{{ stat.owned }}/{{ stat.total }}</span>
                                        <Badge
                                            :variant="stat.percent === 100 ? 'default' : 'secondary'"
                                            class="w-14 justify-center text-[10px] tabular-nums"
                                        >
                                            {{ stat.percent }}%
                                        </Badge>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </TabsContent>
            </Tabs>
        </div>
    </div>
</template>
