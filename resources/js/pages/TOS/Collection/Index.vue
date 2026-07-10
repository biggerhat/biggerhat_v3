<script setup lang="ts">
import AllegianceLogo from '@/components/AllegianceLogo.vue';
import EmptyState from '@/components/EmptyState.vue';
import PowerBarBubbles from '@/components/Game/PowerBarBubbles.vue';
import HeadingEyebrow from '@/components/HeadingEyebrow.vue';
import PageBanner from '@/components/PageBanner.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useStaggeredEntry } from '@/composables/useStaggeredEntry';
import { CARD_HOVER } from '@/lib/cardHover';
import { Head, Link, router } from '@inertiajs/vue3';
import {
    BarChart3,
    Check,
    CheckSquare,
    Copy,
    Globe,
    Grid2x2,
    Hammer,
    Library,
    Lock,
    Minus,
    Package,
    Paintbrush,
    Plus,
    Search,
    Trash2,
    X,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

interface CollectionItem {
    // Adjunct-limit Assets count as Units for collection purposes — `type`
    // says which table the row actually lives in, so mutations hit the
    // right endpoint with the right id.
    type: 'unit_sculpt' | 'asset';
    unit_sculpt_id: number | null;
    asset_id: number | null;
    sculpt_name: string;
    sculpt_slug: string;
    front_image: string | null;
    unit_id: number;
    unit_name: string;
    unit_slug: string;
    allegiances: Array<{ slug: string; name: string }>;
    quantity: number;
    // Squad units (rulebook Special Unit Rule, e.g. "Squad of 9") track
    // per-model progress via built_count/painted_count instead of the flat
    // is_built/is_painted toggle — squad_size is null for everything else.
    // is_built/is_painted are still always present, derived server-side
    // (fully built/painted for a squad row means the whole tracked box is).
    squad_size: number | null;
    built_count: number;
    painted_count: number;
    is_built: boolean;
    is_painted: boolean;
}

interface AllegianceStat {
    allegiance: string;
    name: string;
    color: string;
    logo: string;
    total: number;
    owned: number;
    percent: number;
}

interface OwnedPackage {
    id: number;
    name: string;
    slug: string;
    front_image: string | null;
}

const props = defineProps<{
    collection: CollectionItem[];
    owned_packages: OwnedPackage[] | null;
    allegiance_stats: AllegianceStat[];
    totals: {
        units: number;
        owned_units: number;
        owned_sculpts: number;
        owned_packages: number;
        percent: number;
        built: number;
        painted: number;
        built_percent: number;
        painted_percent: number;
    };
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
        const url = route('tos.collection.share', { shareCode: props.share_code });
        await navigator.clipboard.writeText(url);
        copied.value = true;
        setTimeout(() => (copied.value = false), 2000);
    } catch {
        // Clipboard API may be blocked in insecure contexts
    }
};

const togglePublic = () => {
    router.post(route('tos.collection.toggle_public'), {}, { preserveScroll: true, preserveState: true });
};

// ─── Filters ───
const filterText = ref('');
const filterAllegiance = ref<string | null>(null);
const filterStatus = ref<string | null>(null);
const hasActiveFilter = computed(() => !!filterText.value || !!filterAllegiance.value || !!filterStatus.value);

const clearFilters = () => {
    filterText.value = '';
    filterAllegiance.value = null;
    filterStatus.value = null;
};

const filteredCollection = computed(() => {
    let items = props.collection;
    if (filterText.value) {
        const search = filterText.value.toLowerCase();
        items = items.filter((i) => i.unit_name.toLowerCase().includes(search) || i.sculpt_name.toLowerCase().includes(search));
    }
    if (filterAllegiance.value) {
        items = items.filter((i) => i.allegiances.some((a) => a.slug === filterAllegiance.value));
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

const groupedByUnit = computed(() => {
    const map = new Map<number, { unit_name: string; unit_slug: string; allegiances: CollectionItem['allegiances']; sculpts: CollectionItem[] }>();
    for (const item of filteredCollection.value) {
        if (!map.has(item.unit_id)) {
            map.set(item.unit_id, { unit_name: item.unit_name, unit_slug: item.unit_slug, allegiances: item.allegiances, sculpts: [] });
        }
        map.get(item.unit_id)!.sculpts.push(item);
    }
    return [...map.values()].sort((a, b) => a.unit_name.localeCompare(b.unit_name));
});

const groupCount = computed(() => groupedByUnit.value.length);
const { delays } = useStaggeredEntry(groupCount);

const allegianceCount = computed(() => props.allegiance_stats.length);
const { delays: allegianceDelays } = useStaggeredEntry(allegianceCount);

const packageCount = computed(() => props.owned_packages?.length ?? 0);
const { delays: packageDelays } = useStaggeredEntry(packageCount);

// ─── Mutations ───
const processing = ref(false);

const updateQuantity = (item: CollectionItem, quantity: number) => {
    processing.value = true;
    const opts = { preserveScroll: true, preserveState: true, onFinish: () => (processing.value = false) };
    if (item.type === 'asset') {
        router.post(route('tos.collection.toggle_asset'), { asset_id: item.asset_id, quantity: Math.max(0, quantity) }, opts);
    } else {
        router.post(route('tos.collection.toggle'), { unit_sculpt_id: item.unit_sculpt_id, quantity: Math.max(0, quantity) }, opts);
    }
};

const updateStatus = (item: CollectionItem, field: 'is_built' | 'is_painted', value: boolean) => {
    processing.value = true;
    const opts = { preserveScroll: true, preserveState: true, onFinish: () => (processing.value = false) };
    if (item.type === 'asset') {
        router.post(route('tos.collection.update_asset_status'), { asset_id: item.asset_id, [field]: value }, opts);
    } else {
        router.post(route('tos.collection.update_status'), { unit_sculpt_id: item.unit_sculpt_id, [field]: value }, opts);
    }
};

// Squad units only (item.squad_size set) — per-model bubble progress,
// same endpoint as updateStatus but with a count instead of a boolean.
const updateSquadCount = (item: CollectionItem, field: 'built_count' | 'painted_count', count: number) => {
    processing.value = true;
    router.post(
        route('tos.collection.update_status'),
        { unit_sculpt_id: item.unit_sculpt_id, [field]: count },
        { preserveScroll: true, preserveState: true, onFinish: () => (processing.value = false) },
    );
};

const removeSculpt = (item: CollectionItem) => {
    processing.value = true;
    const opts = { preserveScroll: true, preserveState: true, onFinish: () => (processing.value = false) };
    if (item.type === 'asset') {
        router.post(route('tos.collection.toggle_asset'), { asset_id: item.asset_id, quantity: 0 }, opts);
    } else {
        router.post(route('tos.collection.toggle'), { unit_sculpt_id: item.unit_sculpt_id, quantity: 0 }, opts);
    }
};

const removePackage = (packageId: number) => {
    processing.value = true;
    router.post(
        route('collection.toggle_package'),
        { package_id: packageId },
        { preserveScroll: true, preserveState: true, onFinish: () => (processing.value = false) },
    );
};

// ─── Bulk select mode ───
const selectMode = ref(false);
const selectedIds = ref<Set<number>>(new Set());

const toggleSelected = (unitSculptId: number) => {
    const next = new Set(selectedIds.value);
    if (next.has(unitSculptId)) next.delete(unitSculptId);
    else next.add(unitSculptId);
    selectedIds.value = next;
};

// Bulk select/remove/mark stays Unit-Sculpt-only for now — Adjunct Assets
// support single-row add/toggle/status/remove, not the bulk endpoints.
const filteredSculptIds = computed(() => filteredCollection.value.filter((i) => i.type === 'unit_sculpt').map((i) => i.unit_sculpt_id as number));
const allFilteredSelected = computed(() => filteredSculptIds.value.length > 0 && filteredSculptIds.value.every((id) => selectedIds.value.has(id)));
const toggleSelectAll = () => {
    if (allFilteredSelected.value) {
        const next = new Set(selectedIds.value);
        filteredSculptIds.value.forEach((id) => next.delete(id));
        selectedIds.value = next;
    } else {
        const next = new Set(selectedIds.value);
        filteredSculptIds.value.forEach((id) => next.add(id));
        selectedIds.value = next;
    }
};

const exitSelectMode = () => {
    selectMode.value = false;
    selectedIds.value = new Set();
};
watch(selectMode, (active) => {
    if (!active) selectedIds.value = new Set();
});

const bulkRemove = () => {
    const ids = [...selectedIds.value];
    if (!ids.length) return;
    processing.value = true;
    router.post(
        route('tos.collection.remove_bulk'),
        { unit_sculpt_ids: ids },
        { preserveScroll: true, preserveState: true, onFinish: () => (processing.value = false), onSuccess: () => exitSelectMode() },
    );
};

const bulkMark = (field: 'is_built' | 'is_painted', value: boolean) => {
    const ids = [...selectedIds.value];
    if (!ids.length) return;
    processing.value = true;
    router.post(
        route('tos.collection.update_status_bulk'),
        { unit_sculpt_ids: ids, [field]: value },
        { preserveScroll: true, preserveState: true, onFinish: () => (processing.value = false), onSuccess: () => exitSelectMode() },
    );
};

const topAllegiances = computed(() =>
    [...props.allegiance_stats]
        .filter((a) => a.owned > 0)
        .sort((a, b) => b.owned - a.owned || b.percent - a.percent)
        .slice(0, 5),
);
</script>

<template>
    <Head :title="is_owner ? 'My TOS Collection' : `${owner_name}'s TOS Collection`" />

    <div class="relative">
        <div
            class="pointer-events-none absolute inset-x-0 top-0 h-64 opacity-[0.07] dark:opacity-[0.12]"
            :style="{ background: 'radial-gradient(ellipse at top, hsl(var(--primary)) 0%, transparent 70%)' }"
        />

        <PageBanner :title="is_owner ? 'My Collection' : `${owner_name}'s Collection`">
            <template #subtitle>
                <div class="flex flex-wrap items-center gap-3 px-2">
                    <span class="text-sm text-muted-foreground">
                        {{
                            is_owner
                                ? 'Unit sculpts and packages you already own — track assembly, paint, and play progress here.'
                                : `Viewing ${owner_name}'s TOS collection.`
                        }}
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

        <div class="container mx-auto mt-6 sm:px-4 lg:px-6" :class="{ 'pb-24': is_owner && selectMode && selectedIds.size > 0 }">
            <!-- Overview stats -->
            <div class="mb-6 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <Card class="overflow-hidden">
                    <CardContent class="p-4 text-center">
                        <div class="text-2xl font-bold tracking-tight sm:text-3xl">{{ totals.owned_units }}</div>
                        <div class="mt-0.5 text-xs text-muted-foreground">Units ({{ totals.percent }}%)</div>
                        <div class="mt-2 h-2 w-full overflow-hidden rounded-full bg-muted">
                            <div class="h-full rounded-full bg-primary transition-all duration-500" :style="{ width: `${totals.percent}%` }" />
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent class="p-4 text-center">
                        <div class="text-2xl font-bold tracking-tight sm:text-3xl">{{ totals.owned_sculpts }}</div>
                        <div class="mt-0.5 text-xs text-muted-foreground">Total Sculpts</div>
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
                        <div class="text-2xl font-bold tracking-tight sm:text-3xl">{{ totals.units }}</div>
                        <div class="mt-0.5 text-xs text-muted-foreground">Total Available</div>
                    </CardContent>
                </Card>
            </div>

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
                    <TabsTrigger value="allegiances">
                        <Grid2x2 class="mr-1.5 size-4" />
                        <span class="hidden sm:inline">Allegiances</span>
                    </TabsTrigger>
                    <TabsTrigger value="stats">
                        <BarChart3 class="mr-1.5 size-4" />
                        <span class="hidden sm:inline">Stats</span>
                    </TabsTrigger>
                </TabsList>

                <!-- Collection Tab -->
                <TabsContent value="collection">
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
                            <Select v-model="filterAllegiance">
                                <SelectTrigger class="w-full sm:w-40">
                                    <SelectValue placeholder="All Allegiances" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="a in allegiance_stats" :key="a.allegiance" :value="a.allegiance">{{ a.name }}</SelectItem>
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
                            <Button
                                v-if="is_owner && collection.length > 0"
                                :variant="selectMode ? 'default' : 'outline'"
                                size="sm"
                                class="gap-1.5"
                                :title="selectMode ? 'Exit select mode' : 'Enter select mode for bulk actions'"
                                @click="selectMode ? exitSelectMode() : (selectMode = true)"
                            >
                                <CheckSquare class="size-3.5" />
                                {{ selectMode ? 'Done' : 'Select' }}
                            </Button>
                        </div>
                    </div>

                    <div
                        v-if="is_owner && selectMode && filteredCollection.length > 0"
                        class="mb-2 flex items-center gap-3 rounded-md border bg-muted/40 px-3 py-2 text-xs"
                    >
                        <Checkbox
                            :checked="allFilteredSelected"
                            :title="allFilteredSelected ? 'Deselect visible' : 'Select all visible'"
                            @update:checked="toggleSelectAll"
                        />
                        <span class="text-muted-foreground">
                            <template v-if="selectedIds.size === 0">Select rows for bulk actions</template>
                            <template v-else>{{ selectedIds.size }} selected</template>
                        </span>
                    </div>

                    <div v-if="hasActiveFilter" class="mb-3 text-sm text-muted-foreground">
                        Showing {{ filteredCollection.length }} of {{ collection.length }} items
                    </div>

                    <EmptyState
                        v-if="filteredCollection.length === 0 && hasActiveFilter"
                        title="No items match your filter"
                        description="Try adjusting your search or allegiance filter."
                    />

                    <EmptyState
                        v-else-if="filteredCollection.length === 0"
                        :icon="Library"
                        :title="is_owner ? 'Your collection is empty' : 'This collection is empty'"
                        :description="is_owner ? 'Add unit sculpts or Adjunct assets from unit/asset pages to get started.' : ''"
                    />

                    <div v-else class="space-y-2">
                        <div
                            v-for="(group, index) in groupedByUnit"
                            :key="group.unit_slug"
                            class="animate-fade-in-up rounded-lg border bg-card opacity-0 transition-all duration-200 hover:shadow-md"
                            :style="delays[index]"
                        >
                            <div
                                v-for="(item, itemIndex) in group.sculpts"
                                :key="`${item.type}-${item.unit_sculpt_id ?? item.asset_id}`"
                                class="px-3 py-2.5 transition-colors sm:px-4"
                                :class="[
                                    { 'border-t border-border/50': itemIndex > 0 },
                                    selectMode && item.type === 'unit_sculpt' && selectedIds.has(item.unit_sculpt_id as number) ? 'bg-primary/5' : '',
                                    selectMode && item.type === 'unit_sculpt' ? 'cursor-pointer' : '',
                                ]"
                                @click="selectMode && item.type === 'unit_sculpt' ? toggleSelected(item.unit_sculpt_id as number) : null"
                            >
                                <div class="flex items-center gap-2 sm:gap-3">
                                    <Checkbox
                                        v-if="is_owner && selectMode && item.type === 'unit_sculpt'"
                                        :checked="selectedIds.has(item.unit_sculpt_id as number)"
                                        class="shrink-0"
                                        @update:checked="toggleSelected(item.unit_sculpt_id as number)"
                                        @click.stop
                                    />
                                    <AllegianceLogo v-if="item.allegiances[0]" :allegiance="item.allegiances[0].slug" class-name="size-5 shrink-0" />
                                    <Link
                                        v-if="!selectMode"
                                        :href="route(item.type === 'asset' ? 'tos.assets.view' : 'tos.units.view', item.sculpt_slug)"
                                        class="min-w-0 flex-1"
                                    >
                                        <div class="flex items-center gap-1.5">
                                            <div class="truncate text-sm font-medium transition-colors hover:text-primary">
                                                {{ item.unit_name }}
                                            </div>
                                            <Badge v-if="item.type === 'asset'" variant="outline" class="shrink-0 text-[9px]">Adjunct</Badge>
                                        </div>
                                        <div v-if="item.sculpt_name !== item.unit_name" class="truncate text-xs text-muted-foreground">
                                            {{ item.sculpt_name }}
                                        </div>
                                    </Link>
                                    <div v-else class="min-w-0 flex-1">
                                        <div class="flex items-center gap-1.5">
                                            <div class="truncate text-sm font-medium">{{ item.unit_name }}</div>
                                            <Badge v-if="item.type === 'asset'" variant="outline" class="shrink-0 text-[9px]">Adjunct</Badge>
                                        </div>
                                        <div v-if="item.sculpt_name !== item.unit_name" class="truncate text-xs text-muted-foreground">
                                            {{ item.sculpt_name }}
                                        </div>
                                    </div>
                                    <!-- Status icons (view-only, non-owner) -->
                                    <template v-if="!is_owner">
                                        <div v-if="item.squad_size" class="flex shrink-0 items-center gap-2 text-[11px] tabular-nums">
                                            <span class="flex items-center gap-0.5 text-amber-600 dark:text-amber-400">
                                                <Hammer class="size-3" />{{ item.built_count }}/{{ item.squad_size }}
                                            </span>
                                            <span class="flex items-center gap-0.5 text-violet-600 dark:text-violet-400">
                                                <Paintbrush class="size-3" />{{ item.painted_count }}/{{ item.squad_size }}
                                            </span>
                                        </div>
                                        <div v-else class="flex shrink-0 items-center gap-1">
                                            <Hammer v-if="item.is_built" class="size-3.5 text-amber-600 dark:text-amber-400" title="Built" />
                                            <Paintbrush
                                                v-if="item.is_painted"
                                                class="size-3.5 text-violet-600 dark:text-violet-400"
                                                title="Painted"
                                            />
                                        </div>
                                        <span class="text-sm tabular-nums text-muted-foreground">&times;{{ item.quantity }}</span>
                                    </template>
                                </div>
                                <div v-if="is_owner && !selectMode" class="mt-1.5 flex items-center gap-1 pl-7 sm:mt-0 sm:pl-0 sm:pt-0">
                                    <!-- Squad units track per-model progress via bubbles instead of a flat toggle. -->
                                    <div v-if="item.squad_size" class="flex flex-col gap-1">
                                        <div class="flex items-center gap-1.5">
                                            <Hammer class="size-3 shrink-0 text-amber-600 dark:text-amber-400" />
                                            <PowerBarBubbles
                                                :max="item.squad_size"
                                                :current="item.built_count"
                                                color="amber"
                                                label="Built"
                                                compact
                                                :readonly="processing"
                                                @update="(v) => updateSquadCount(item, 'built_count', v)"
                                            />
                                        </div>
                                        <div class="flex items-center gap-1.5">
                                            <Paintbrush class="size-3 shrink-0 text-violet-600 dark:text-violet-400" />
                                            <PowerBarBubbles
                                                :max="item.squad_size"
                                                :current="item.painted_count"
                                                color="violet"
                                                label="Painted"
                                                compact
                                                :readonly="processing"
                                                @update="(v) => updateSquadCount(item, 'painted_count', v)"
                                            />
                                        </div>
                                    </div>
                                    <template v-else>
                                        <Button
                                            :variant="item.is_built ? 'default' : 'outline'"
                                            size="icon"
                                            class="size-7"
                                            :class="item.is_built ? 'bg-amber-600 text-white hover:bg-amber-700' : 'text-muted-foreground'"
                                            :disabled="processing"
                                            :title="item.is_built ? 'Mark as unbuilt' : 'Mark as built'"
                                            @click="updateStatus(item, 'is_built', !item.is_built)"
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
                                            @click="updateStatus(item, 'is_painted', !item.is_painted)"
                                        >
                                            <Paintbrush class="size-3.5" />
                                        </Button>
                                    </template>
                                    <div class="ml-1 flex shrink-0 items-center gap-1">
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            class="size-7"
                                            :disabled="processing"
                                            @click="updateQuantity(item, item.quantity - 1)"
                                        >
                                            <Minus class="size-3" />
                                        </Button>
                                        <span class="w-6 text-center text-sm font-medium tabular-nums">{{ item.quantity }}</span>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            class="size-7"
                                            :disabled="processing"
                                            @click="updateQuantity(item, item.quantity + 1)"
                                        >
                                            <Plus class="size-3" />
                                        </Button>
                                    </div>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        class="ml-auto size-7 text-destructive hover:text-destructive"
                                        :disabled="processing"
                                        @click="removeSculpt(item)"
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
                    <div v-if="owned_packages === null" class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <Card v-for="i in 3" :key="i" class="animate-pulse">
                            <CardContent class="p-4">
                                <div class="flex items-start gap-3">
                                    <div class="size-16 shrink-0 rounded-md bg-muted" />
                                    <div class="min-w-0 flex-1 space-y-2">
                                        <div class="h-4 w-3/4 rounded bg-muted" />
                                        <div class="h-3 w-1/2 rounded bg-muted" />
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                    <EmptyState
                        v-else-if="owned_packages.length === 0"
                        :icon="Package"
                        :title="is_owner ? 'No packages tracked yet' : 'No packages in this collection'"
                        :description="is_owner ? 'Add packages from the TOS package pages to get started.' : ''"
                    />
                    <div v-else class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <Card
                            v-for="(pkg, index) in owned_packages"
                            :key="pkg.id"
                            :class="['animate-fade-in-up opacity-0', CARD_HOVER]"
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
                                            :href="route('tos.packages.view', pkg.slug)"
                                            class="text-sm font-semibold transition-colors hover:text-primary"
                                        >
                                            {{ pkg.name }}
                                        </Link>
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

                <!-- Allegiances Tab -->
                <TabsContent value="allegiances">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <Card
                            v-for="(stat, index) in allegiance_stats"
                            :key="stat.allegiance"
                            :class="['animate-fade-in-up opacity-0', CARD_HOVER]"
                            :style="allegianceDelays[index]"
                        >
                            <CardContent class="p-4">
                                <div class="mb-3 flex items-center gap-2.5">
                                    <img :src="stat.logo" :alt="stat.name" class="size-8" />
                                    <div>
                                        <div class="font-semibold">{{ stat.name }}</div>
                                        <div class="text-xs text-muted-foreground">{{ stat.owned }} / {{ stat.total }} units</div>
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

                <!-- Stats Tab -->
                <TabsContent value="stats">
                    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <Card>
                            <CardContent class="p-4">
                                <div class="mb-3 flex items-center gap-2.5">
                                    <div class="flex size-8 items-center justify-center rounded-md bg-amber-100 dark:bg-amber-900/30">
                                        <Hammer class="size-4 text-amber-600 dark:text-amber-400" />
                                    </div>
                                    <div>
                                        <div class="font-semibold">Built</div>
                                        <div class="text-xs text-muted-foreground">{{ totals.built }} of {{ collection.length }} sculpts</div>
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
                                        <div class="text-xs text-muted-foreground">{{ totals.painted }} of {{ collection.length }} sculpts</div>
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

                    <Card>
                        <CardHeader class="pb-3">
                            <HeadingEyebrow>Top Allegiances</HeadingEyebrow>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div v-if="topAllegiances.length === 0" class="text-sm text-muted-foreground">No units collected yet.</div>
                            <div v-for="stat in topAllegiances" :key="stat.allegiance" class="space-y-1.5">
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
                </TabsContent>
            </Tabs>
        </div>
    </div>

    <Transition
        enter-active-class="transition duration-150 ease-out"
        enter-from-class="translate-y-full opacity-0"
        enter-to-class="translate-y-0 opacity-100"
        leave-active-class="transition duration-150 ease-in"
        leave-from-class="translate-y-0 opacity-100"
        leave-to-class="translate-y-full opacity-0"
    >
        <div
            v-if="is_owner && selectMode && selectedIds.size > 0"
            class="fixed inset-x-0 bottom-0 z-40 border-t bg-background/95 px-3 py-3 shadow-[0_-8px_24px_rgba(0,0,0,0.12)] backdrop-blur sm:px-6"
        >
            <div class="container mx-auto flex flex-wrap items-center gap-2">
                <span class="mr-1 text-sm font-medium">{{ selectedIds.size }} selected</span>
                <Button size="sm" variant="outline" :disabled="processing" class="gap-1.5" @click="bulkMark('is_built', true)">
                    <Hammer class="size-3.5" /> <span class="hidden sm:inline">Mark</span> Built
                </Button>
                <Button size="sm" variant="outline" :disabled="processing" class="gap-1.5" @click="bulkMark('is_built', false)">
                    <Hammer class="size-3.5 text-muted-foreground" /> Unbuilt
                </Button>
                <Button size="sm" variant="outline" :disabled="processing" class="gap-1.5" @click="bulkMark('is_painted', true)">
                    <Paintbrush class="size-3.5" /> <span class="hidden sm:inline">Mark</span> Painted
                </Button>
                <Button size="sm" variant="outline" :disabled="processing" class="gap-1.5" @click="bulkMark('is_painted', false)">
                    <Paintbrush class="size-3.5 text-muted-foreground" /> Unpainted
                </Button>
                <Button size="sm" variant="destructive" :disabled="processing" class="ml-auto gap-1.5" @click="bulkRemove">
                    <Trash2 class="size-3.5" /> Remove {{ selectedIds.size }}
                </Button>
            </div>
        </div>
    </Transition>
</template>
