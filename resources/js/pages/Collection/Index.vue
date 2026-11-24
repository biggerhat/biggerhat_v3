<script setup lang="ts">
import EmptyState from '@/components/EmptyState.vue';
import FactionLogo from '@/components/FactionLogo.vue';
import HeadingEyebrow from '@/components/HeadingEyebrow.vue';
import HelpButton from '@/components/Help/HelpButton.vue';
import StagePips from '@/components/Collection/StagePips.vue';
import ModelList, { type CollectionModelCopy } from '@/components/Collection/ModelList.vue';
import PageBanner from '@/components/PageBanner.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useConfirm } from '@/composables/useConfirm';
import { factionSpine } from '@/composables/useFactionColor';
import { useStaggeredEntry } from '@/composables/useStaggeredEntry';
import { CARD_HOVER } from '@/lib/cardHover';
import type { SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    BarChart3,
    BookOpen,
    Check,
    CheckSquare,
    ChevronDown,
    Copy,
    Globe,
    Grid2x2,
    Hammer,
    Library,
    Lock,
    Minus,
    Package,
    Paintbrush,
    Swords,
    Plus,
    Search,
    Trash2,
    X,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

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
    /** How many of the copies are built / painted (a painted copy counts as built). */
    built_count: number;
    painted_count: number;
    models: CollectionModelCopy[];
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
    /** Models owned in this faction, and how many are built and painted. */
    models: number;
    built: number;
    painted: number;
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
    /** What the package holds against what the player has: models it holds, and how many of them are owned, built and painted. */
    models: number;
    owned: number;
    built: number;
    painted: number;
    contents: Array<{ slug: string; name: string; quantity: number; owned: number; built: number; painted: number }>;
}

const props = defineProps<{
    collection: CollectionItem[];
    // Deferred via Inertia::defer: missing (undefined) on the first paint and streamed in by a follow-up request. Test them with `!`, never
    // `=== null`, or a tab that is open on first load (?tab=packages) reads `.length` of undefined.
    owned_packages?: OwnedPackage[] | null;
    faction_stats: FactionStat[];
    stages: { value: string; label: string; count: number }[];
    keyword_stats?: KeywordStat[] | null;
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
    is_owner: boolean;
    share_code: string;
    is_public: boolean;
    owner_name?: string;
}>();

const page = usePage<SharedData>();
const factionInfo = computed(() => page.props.faction_info ?? {});

// The tab is kept in the address (?tab=packages) so a refresh or a shared link opens the same one.
const TABS = ['collection', 'packages', 'factions', 'keywords', 'stats'];
const tabFromUrl = (): string => {
    const tab = new URL(page.url, 'http://localhost').searchParams.get('tab');
    return tab && TABS.includes(tab) ? tab : 'collection';
};
const activeTab = ref(tabFromUrl());
watch(activeTab, (tab) => {
    if (typeof window === 'undefined') return;
    const url = new URL(window.location.href);
    if (tab === 'collection') url.searchParams.delete('tab');
    else url.searchParams.set('tab', tab);
    window.history.replaceState(window.history.state, '', url);
});

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

// Progress across the whole collection (models, not quantities), for the summary card and status chips.
const builtCount = computed(() => props.totals.built);
const paintedCount = computed(() => props.totals.painted);
const modelCount = computed(() => props.totals.owned_miniatures);
const pct = (n: number) => (modelCount.value ? Math.round((n / modelCount.value) * 100) : 0);

// A miniature with several copies can be partly built or painted, so the filters look at its copies: "Unbuilt" and "Unpainted" mean
// at least one copy still to do, "Painted" means every copy is.
const needsBuilding = (i: CollectionItem) => i.built_count < i.quantity;
const needsPainting = (i: CollectionItem) => i.painted_count < i.quantity;
const statusChips = computed(() => [
    { value: null, label: 'All', count: props.collection.length },
    { value: 'unbuilt', label: 'Unbuilt', count: props.collection.filter(needsBuilding).length },
    { value: 'unpainted', label: 'Unpainted', count: props.collection.filter(needsPainting).length },
    { value: 'painted', label: 'Painted', count: props.collection.filter((i) => !needsPainting(i)).length },
]);

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
                items = items.filter(needsBuilding);
                break;
            case 'built':
                items = items.filter((i) => !needsBuilding(i));
                break;
            case 'unpainted':
                items = items.filter(needsPainting);
                break;
            case 'painted':
                items = items.filter((i) => !needsPainting(i));
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

const packageCount = computed(() => props.owned_packages?.length ?? 0);
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

// The copies of a miniature, opened one row at a time.
const expanded = ref<Set<number>>(new Set());
const toggleExpanded = (miniatureId: number) => {
    const next = new Set(expanded.value);
    if (next.has(miniatureId)) next.delete(miniatureId);
    else next.add(miniatureId);
    expanded.value = next;
};
const progress = (item: CollectionItem) =>
    item.quantity > 1 ? `Built ${item.built_count}/${item.quantity} · Painted ${item.painted_count}/${item.quantity}` : '';

const confirm = useConfirm();
const removeMiniature = async (item: CollectionItem) => {
    const miniatureId = item.miniature_id;
    const notes = item.models.filter((m) => m.note).length;
    if (
        !(await confirm({
            title: `Remove ${item.miniature_name}?`,
            message: `${item.quantity === 1 ? 'Its model' : `All ${item.quantity} models`}${notes ? ` and ${notes === 1 ? 'a note' : `${notes} notes`}` : ''} will be removed from your collection.`,
            confirmLabel: 'Remove',
            destructive: true,
        }))
    )
        return;
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

// ─── Bulk select mode ───
// Discoverable rather than always-on: users opt into multi-select via the
// "Select" toggle, then a sticky action bar surfaces Remove / Mark Built /
// Mark Painted for the chosen rows. Selection is wiped when the user exits
// select mode or applies an action so the bar can't act on stale state.
const selectMode = ref(false);
const selectedIds = ref<Set<number>>(new Set());

const toggleSelected = (miniatureId: number) => {
    const next = new Set(selectedIds.value);
    if (next.has(miniatureId)) next.delete(miniatureId);
    else next.add(miniatureId);
    selectedIds.value = next;
};

const filteredMiniatureIds = computed(() => filteredCollection.value.map((i) => i.miniature_id));
const allFilteredSelected = computed(
    () => filteredMiniatureIds.value.length > 0 && filteredMiniatureIds.value.every((id) => selectedIds.value.has(id)),
);
const toggleSelectAll = () => {
    if (allFilteredSelected.value) {
        // Deselect only the rows currently visible — keeps off-screen picks
        // intact if the user is iterating through filters.
        const next = new Set(selectedIds.value);
        filteredMiniatureIds.value.forEach((id) => next.delete(id));
        selectedIds.value = next;
    } else {
        const next = new Set(selectedIds.value);
        filteredMiniatureIds.value.forEach((id) => next.add(id));
        selectedIds.value = next;
    }
};

const exitSelectMode = () => {
    selectMode.value = false;
    selectedIds.value = new Set();
};
// Clear selection when select mode flips off (covers the keyboard-escape /
// programmatic-toggle paths).
watch(selectMode, (active) => {
    if (!active) selectedIds.value = new Set();
});

const bulkRemove = async () => {
    const ids = [...selectedIds.value];
    if (!ids.length) return;
    if (
        !(await confirm({
            title: `Remove ${ids.length} ${ids.length === 1 ? 'miniature' : 'miniatures'}?`,
            message: 'All their models and notes are removed from your collection.',
            confirmLabel: 'Remove',
            destructive: true,
        }))
    )
        return;
    processing.value = true;
    router.post(
        route('collection.remove_bulk'),
        { miniature_ids: ids },
        {
            preserveScroll: true,
            preserveState: true,
            onFinish: () => {
                processing.value = false;
                exitSelectMode();
            },
        },
    );
};

const bulkStage = (stage: string) => {
    const ids = [...selectedIds.value];
    if (!ids.length || !stage) return;
    processing.value = true;
    router.post(
        route('collection.set_stage_bulk'),
        { miniature_ids: ids, stage },
        {
            preserveScroll: true,
            preserveState: true,
            onFinish: () => {
                processing.value = false;
                exitSelectMode();
            },
        },
    );
};

const bulkMark = (field: 'is_built' | 'is_painted', value: boolean) => {
    const ids = [...selectedIds.value];
    if (!ids.length) return;
    processing.value = true;
    router.post(
        route('collection.update_status_bulk'),
        { miniature_ids: ids, [field]: value },
        {
            preserveScroll: true,
            preserveState: true,
            onFinish: () => {
                processing.value = false;
                exitSelectMode();
            },
        },
    );
};

// Sorted keyword stats
const sortedKeywordStats = computed(() => [...(props.keyword_stats ?? [])].sort((a, b) => b.percent - a.percent));

// Top factions by owned count
const plural = (n: number, one: string, many = `${one}s`) => `${n} ${n === 1 ? one : many}`;

// Factions with nothing in the catalogue are left out; a click on one opens the Collection tab for it.
const visibleFactions = computed(() => props.faction_stats.filter((f) => f.total > 0));
const showFaction = (faction: string) => {
    clearFilters();
    filterFaction.value = faction;
    activeTab.value = 'collection';
};
const searchFor = (text: string) => {
    clearFilters();
    filterText.value = text;
    activeTab.value = 'collection';
};

// Keywords: most complete first, then the bigger ones; the ones not started stay hidden until asked for (or searched).
const keywordSearch = ref('');
const showAllKeywords = ref(false);
const rankedKeywords = computed(() =>
    [...(props.keyword_stats ?? [])].sort((a, b) => b.percent - a.percent || b.owned - a.owned || b.total - a.total || a.name.localeCompare(b.name)),
);
const notStartedKeywords = computed(() => rankedKeywords.value.filter((k) => k.owned === 0).length);
const listedKeywords = computed(() => {
    const q = keywordSearch.value.trim().toLowerCase();
    if (q) return rankedKeywords.value.filter((k) => k.name.toLowerCase().includes(q));
    return showAllKeywords.value ? rankedKeywords.value : rankedKeywords.value.filter((k) => k.owned > 0);
});
// Stats: a keyword with one character owned out of one is not the "most complete"; prefer keywords of some size when there are enough.
const topKeywords = computed(() => {
    const started = rankedKeywords.value.filter((k) => k.owned > 0);
    const sized = started.filter((k) => k.total >= 3);
    return (sized.length >= 3 ? sized : started).slice(0, 8);
});

// Stats: the models by stage, and what is next to paint or build.
const stageBars = computed(() => props.stages.filter((s) => s.count > 0));
const modelsIn = (i: CollectionItem, stages: string[]) => i.models.filter((m) => stages.includes(m.stage)).length;
const upNext = (stages: string[]) =>
    props.collection
        .map((i) => ({ i, n: modelsIn(i, stages) }))
        .filter((r) => r.n > 0)
        .sort((a, b) => b.n - a.n || a.i.character_name.localeCompare(b.i.character_name))
        .slice(0, 8);
const toPaint = computed(() => upNext(['built', 'primed']));
const toBuild = computed(() => upNext(['unbuilt']));
const itemName = (i: CollectionItem) => (i.miniature_name !== i.character_name ? `${i.character_name} (${i.miniature_name})` : i.character_name);
const STAGE_BAR: Record<string, string> = {
    unbuilt: 'bg-muted-foreground/40',
    built: 'bg-amber-600',
    primed: 'bg-sky-500',
    painted: 'bg-violet-600',
    based: 'bg-emerald-600',
};

// Packages: what is inside one, opened a card at a time; removing one keeps the models it added.
const openPackages = ref<Set<number>>(new Set());
const togglePackageOpen = (id: number) => {
    const next = new Set(openPackages.value);
    if (next.has(id)) next.delete(id);
    else next.add(id);
    openPackages.value = next;
};
const askRemovePackage = async (pkg: OwnedPackage) => {
    const ok = await confirm({
        title: `Remove ${pkg.name}?`,
        message: 'It leaves your list of packages. The miniatures it added stay in your collection.',
        confirmLabel: 'Remove',
        destructive: true,
    });
    if (ok) removePackage(pkg.id);
};
const isEmptyOwner = computed(() => props.is_owner && props.totals.owned_miniatures === 0 && props.totals.owned_packages === 0);

const topFactions = computed(() =>
    [...props.faction_stats]
        .filter((f) => f.owned > 0)
        .sort((a, b) => b.owned - a.owned || b.percent - a.percent)
        .slice(0, 5),
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
                        {{
                            is_owner
                                ? 'Miniatures and packages you already own — track assembly, paint, and play progress here.'
                                : `Viewing ${owner_name}'s collection.`
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
                            <Check v-if="copied" class="size-3.5 text-green-700 dark:text-green-400" />
                            <Copy v-else class="size-3.5" />
                            {{ copied ? 'Copied!' : 'Share' }}
                        </Button>
                        <Link :href="route('collection.playable')">
                            <Button variant="ghost" size="sm" class="h-7 gap-1 text-xs"><Swords class="size-3.5" />What can I play?</Button>
                        </Link>
                    </div>
                    <HelpButton topic="collection" />
                </div>
            </template>
        </PageBanner>

        <div class="container mx-auto mt-6 sm:px-4 lg:px-6" :class="{ 'pb-24': is_owner && selectMode && selectedIds.size > 0 }">
            <!-- Overview: one summary card instead of four tiles -->
            <Card v-if="isEmptyOwner" class="mb-6" data-testid="collection-welcome">
                <CardContent class="flex flex-col items-start gap-3 p-5 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="text-lg font-semibold">Start your collection</h2>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Add the miniatures you own from a character or package page, then mark each model as built, primed, painted or based.
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-2 sm:shrink-0 sm:flex-nowrap">
                        <Button as-child size="sm" class="max-sm:min-h-11"><Link :href="route('index')">Browse characters</Link></Button>
                        <Button as-child size="sm" variant="outline" class="max-sm:min-h-11"
                            ><Link :href="route('packages.index')">Browse packages</Link></Button
                        >
                    </div>
                </CardContent>
            </Card>
            <Card v-else class="mb-6">
                <CardContent class="grid gap-5 p-4 sm:p-5 lg:grid-cols-[1.2fr_1fr_1fr]">
                    <div>
                        <div class="flex items-baseline gap-2">
                            <span class="text-3xl font-bold tracking-tight">{{ totals.owned_characters }}</span>
                            <span class="text-sm text-muted-foreground">of {{ totals.characters }} characters ({{ totals.percent }}%)</span>
                        </div>
                        <div
                            class="mt-2 h-2 w-full overflow-hidden rounded-full bg-muted"
                            role="progressbar"
                            :aria-valuenow="totals.owned_characters"
                            :aria-valuemin="0"
                            :aria-valuemax="totals.characters"
                            aria-label="Characters collected"
                        >
                            <div class="h-full rounded-full bg-primary transition-all duration-500" :style="{ width: `${totals.percent}%` }" />
                        </div>
                        <div class="mt-2 text-xs text-muted-foreground">
                            {{ totals.owned_miniatures }} {{ totals.owned_miniatures === 1 ? 'model' : 'models' }} · {{ collection.length }}
                            {{ collection.length === 1 ? 'miniature' : 'miniatures' }} · {{ totals.owned_packages }}
                            {{ totals.owned_packages === 1 ? 'package' : 'packages' }}
                        </div>
                    </div>
                    <div>
                        <div class="flex items-center justify-between text-sm">
                            <span class="inline-flex items-center gap-1.5 font-medium"
                                ><Hammer class="size-4 text-amber-700 dark:text-amber-400" /> Built</span
                            >
                            <span class="tabular-nums text-muted-foreground">{{ builtCount }} / {{ modelCount }}</span>
                        </div>
                        <div
                            class="mt-2 h-2 w-full overflow-hidden rounded-full bg-muted"
                            role="progressbar"
                            :aria-valuenow="builtCount"
                            :aria-valuemin="0"
                            :aria-valuemax="modelCount"
                            aria-label="Models built"
                        >
                            <div class="h-full rounded-full bg-amber-600 transition-all duration-500" :style="{ width: `${pct(builtCount)}%` }" />
                        </div>
                    </div>
                    <div>
                        <div class="flex items-center justify-between text-sm">
                            <span class="inline-flex items-center gap-1.5 font-medium"
                                ><Paintbrush class="size-4 text-violet-700 dark:text-violet-400" /> Painted</span
                            >
                            <span class="tabular-nums text-muted-foreground">{{ paintedCount }} / {{ modelCount }}</span>
                        </div>
                        <div
                            class="mt-2 h-2 w-full overflow-hidden rounded-full bg-muted"
                            role="progressbar"
                            :aria-valuenow="paintedCount"
                            :aria-valuemin="0"
                            :aria-valuemax="modelCount"
                            aria-label="Models painted"
                        >
                            <div class="h-full rounded-full bg-violet-600 transition-all duration-500" :style="{ width: `${pct(paintedCount)}%` }" />
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Tabs -->
            <Tabs v-model="activeTab">
                <TabsList class="mb-4">
                    <TabsTrigger aria-label="Collection" value="collection">
                        <Library class="mr-1.5 size-4" />
                        <span class="hidden sm:inline">Collection</span>
                    </TabsTrigger>
                    <TabsTrigger aria-label="Packages" value="packages">
                        <Package class="mr-1.5 size-4" />
                        <span class="hidden sm:inline">Packages</span>
                    </TabsTrigger>
                    <TabsTrigger aria-label="Factions" value="factions">
                        <Grid2x2 class="mr-1.5 size-4" />
                        <span class="hidden sm:inline">Factions</span>
                    </TabsTrigger>
                    <TabsTrigger aria-label="Keywords" value="keywords">
                        <BookOpen class="mr-1.5 size-4" />
                        <span class="hidden sm:inline">Keywords</span>
                    </TabsTrigger>
                    <TabsTrigger aria-label="Stats" value="stats">
                        <BarChart3 class="mr-1.5 size-4" />
                        <span class="hidden sm:inline">Stats</span>
                    </TabsTrigger>
                </TabsList>

                <!-- Collection Tab -->
                <TabsContent value="collection">
                    <!-- One-click status filters (the Status select below still covers Built) -->
                    <div class="mb-3 flex flex-wrap gap-2" role="group" aria-label="Filter by build and paint status">
                        <button
                            v-for="chip in statusChips"
                            :key="chip.label"
                            type="button"
                            class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-xs font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                            :class="filterStatus === chip.value ? 'border-primary bg-primary text-primary-foreground' : 'hover:bg-accent'"
                            :aria-pressed="filterStatus === chip.value"
                            @click="filterStatus = chip.value"
                        >
                            {{ chip.label }}
                            <span class="tabular-nums" :class="filterStatus === chip.value ? 'opacity-80' : 'text-muted-foreground'">{{
                                chip.count
                            }}</span>
                        </button>
                        <span
                            class="self-center text-xs text-muted-foreground"
                            title="A miniature counts as Unbuilt or Unpainted while any model of it still is"
                            >Counts are miniatures</span
                        >
                    </div>

                    <!-- Filters -->
                    <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center">
                        <div class="relative min-w-0 flex-1">
                            <Search class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input v-model="filterText" placeholder="Search collection..." class="pl-9 pr-9" />
                            <button
                                aria-label="Clear filter"
                                v-if="filterText"
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                                @click="filterText = ''"
                            >
                                <X class="size-4" />
                            </button>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <Select v-model="filterFaction">
                                <SelectTrigger class="w-full sm:w-40">
                                    <SelectValue placeholder="All Factions" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="(f, key) in factionInfo" :key="key" :value="key">{{ f.name }}</SelectItem>
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

                    <!-- Bulk-select header (only visible in select mode with filtered rows) -->
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
                        Showing {{ filteredCollection.length }} of {{ collection.length }} miniatures
                    </div>

                    <EmptyState
                        v-if="filteredCollection.length === 0 && hasActiveFilter"
                        title="No miniatures match your filter"
                        description="Try adjusting your search or faction filter."
                    />

                    <EmptyState
                        v-else-if="filteredCollection.length === 0"
                        :icon="Library"
                        :title="is_owner ? 'Your collection is empty' : 'This collection is empty'"
                        :description="is_owner ? 'Add miniatures from character or package pages to get started.' : ''"
                    />

                    <div v-else class="xl:columns-2 xl:gap-2">
                        <div
                            v-for="(group, index) in groupedByCharacter"
                            :key="group.character_slug"
                            class="animate-fade-in-up mb-2 break-inside-avoid rounded-lg border bg-card opacity-0 transition-all duration-200 hover:shadow-md"
                            :class="factionSpine(group.faction)"
                            :style="delays[index]"
                        >
                            <div
                                v-for="(item, itemIndex) in group.miniatures"
                                :key="item.miniature_id"
                                class="px-3 py-2.5 transition-colors sm:px-4"
                                :class="[
                                    { 'border-t border-border/50': itemIndex > 0 },
                                    selectMode && selectedIds.has(item.miniature_id) ? 'bg-primary/5' : '',
                                    selectMode ? 'cursor-pointer' : '',
                                ]"
                                @click="selectMode ? toggleSelected(item.miniature_id) : null"
                            >
                                <div class="flex items-center gap-2 sm:gap-3">
                                    <Checkbox
                                        v-if="is_owner && selectMode"
                                        :checked="selectedIds.has(item.miniature_id)"
                                        class="shrink-0"
                                        @update:checked="toggleSelected(item.miniature_id)"
                                        @click.stop
                                    />
                                    <FactionLogo v-if="item.faction" :faction="item.faction" class-name="size-5 shrink-0" />
                                    <!-- In select mode the whole row drives selection; navigation is
                                         disabled so a tap doesn't accidentally leave the page mid-edit. -->
                                    <Link
                                        v-if="!selectMode"
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
                                        <div class="mt-0.5 flex items-center gap-2 text-xs text-muted-foreground">
                                            <StagePips :stages="item.models.map((m) => m.stage)" />
                                            <span v-if="progress(item)" class="truncate" data-testid="collection-progress">{{ progress(item) }}</span>
                                        </div>
                                    </Link>
                                    <div v-else class="min-w-0 flex-1">
                                        <div class="truncate text-sm font-medium">{{ item.character_name }}</div>
                                        <div v-if="item.miniature_name !== item.character_name" class="truncate text-xs text-muted-foreground">
                                            {{ item.miniature_name }}
                                        </div>
                                        <div class="mt-0.5 flex items-center gap-2 text-xs text-muted-foreground">
                                            <StagePips :stages="item.models.map((m) => m.stage)" />
                                            <span v-if="progress(item)" class="truncate">{{ progress(item) }}</span>
                                        </div>
                                    </div>
                                    <!-- Status icons (view-only, non-owner) -->
                                    <template v-if="!is_owner">
                                        <div class="flex shrink-0 items-center gap-1">
                                            <Hammer
                                                v-if="item.built_count > 0"
                                                class="size-3.5 text-amber-600 dark:text-amber-400"
                                                :title="item.is_built ? 'Built' : 'Partly built'"
                                            />
                                            <Paintbrush
                                                v-if="item.painted_count > 0"
                                                class="size-3.5 text-violet-600 dark:text-violet-400"
                                                :title="item.is_painted ? 'Painted' : 'Partly painted'"
                                            />
                                        </div>
                                        <span class="text-sm tabular-nums text-muted-foreground">&times;{{ item.quantity }}</span>
                                    </template>
                                </div>
                                <!-- Owner controls: second row on mobile, inline on desktop.
                                     Hidden in select mode — the row click drives selection instead. -->
                                <div v-if="is_owner && !selectMode" class="mt-2 flex flex-wrap items-center justify-between gap-x-3 gap-y-1.5 pl-7">
                                    <div class="flex items-center gap-1">
                                        <Button
                                            :variant="item.is_built ? 'default' : 'outline'"
                                            size="icon"
                                            class="size-7"
                                            :class="
                                                item.is_built
                                                    ? 'bg-amber-600 text-white hover:bg-amber-700'
                                                    : item.built_count > 0
                                                      ? 'border-amber-600 text-amber-700 dark:text-amber-400'
                                                      : 'text-muted-foreground'
                                            "
                                            :disabled="processing"
                                            :title="item.is_built ? 'Mark every model as unbuilt' : 'Mark every model as built'"
                                            @click="updateStatus(item.miniature_id, 'is_built', !item.is_built)"
                                        >
                                            <Hammer class="size-3.5" />
                                        </Button>
                                        <Button
                                            :variant="item.is_painted ? 'default' : 'outline'"
                                            size="icon"
                                            class="size-7"
                                            :class="
                                                item.is_painted
                                                    ? 'bg-violet-600 text-white hover:bg-violet-700'
                                                    : item.painted_count > 0
                                                      ? 'border-violet-600 text-violet-700 dark:text-violet-400'
                                                      : 'text-muted-foreground'
                                            "
                                            :disabled="processing"
                                            :title="item.is_painted ? 'Mark every model as unpainted' : 'Mark every model as painted'"
                                            @click="updateStatus(item.miniature_id, 'is_painted', !item.is_painted)"
                                        >
                                            <Paintbrush class="size-3.5" />
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            class="h-7 gap-1 px-2 text-xs"
                                            :aria-expanded="expanded.has(item.miniature_id)"
                                            :aria-label="`Models of ${item.miniature_name}`"
                                            data-testid="collection-models-toggle"
                                            @click="toggleExpanded(item.miniature_id)"
                                        >
                                            Models
                                            <ChevronDown
                                                class="size-3.5 transition-transform"
                                                :class="expanded.has(item.miniature_id) ? 'rotate-180' : ''"
                                            />
                                        </Button>
                                    </div>
                                    <div class="flex items-center gap-1">
                                        <div class="flex shrink-0 items-center gap-0.5">
                                            <Button
                                                aria-label="Decrease quantity"
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
                                                aria-label="Increase quantity"
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
                                            aria-label="Remove from collection"
                                            variant="ghost"
                                            size="icon"
                                            class="size-7 text-muted-foreground hover:text-destructive"
                                            :disabled="processing"
                                            @click="removeMiniature(item)"
                                        >
                                            <Trash2 class="size-3.5" />
                                        </Button>
                                    </div>
                                </div>
                                <ModelList
                                    v-if="is_owner && !selectMode && expanded.has(item.miniature_id)"
                                    :miniature-id="item.miniature_id"
                                    :miniature-name="item.miniature_name"
                                    :models="item.models"
                                    :stages="stages"
                                />
                            </div>
                        </div>
                    </div>
                </TabsContent>

                <!-- Packages Tab -->
                <TabsContent value="packages">
                    <div v-if="is_owner" class="mb-3 flex justify-end">
                        <Button as-child size="sm" variant="outline" class="gap-1.5 max-sm:min-h-11" data-testid="collection-browse-packages">
                            <Link :href="route('packages.index')"><Package class="size-3.5" /> Browse packages</Link>
                        </Button>
                    </div>
                    <!-- Deferred prop: show a quiet skeleton while the follow-up
                         request resolves so we don't flash "no packages" -->
                    <div v-if="!owned_packages" class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
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
                        :description="is_owner ? 'Add packages from the package pages to get started.' : ''"
                    />
                    <div v-else class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <Card
                            v-for="(pkg, index) in owned_packages"
                            :key="pkg.id"
                            :class="['animate-fade-in-up opacity-0', CARD_HOVER]"
                            :style="packageDelays[index]"
                            data-testid="collection-package"
                        >
                            <CardContent class="p-4">
                                <div class="flex items-start gap-3">
                                    <img
                                        v-if="pkg.front_image"
                                        :src="`/storage/${pkg.front_image}`"
                                        :alt="pkg.name"
                                        class="size-16 shrink-0 rounded-md object-cover"
                                    />
                                    <div
                                        v-else
                                        class="flex size-16 shrink-0 items-center justify-center rounded-md bg-muted text-muted-foreground"
                                        aria-hidden="true"
                                    >
                                        <Package class="size-6" />
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <Link
                                            :href="route('packages.view', pkg.slug)"
                                            class="text-sm font-semibold transition-colors hover:text-primary"
                                        >
                                            {{ pkg.name }}
                                        </Link>
                                        <div v-if="pkg.factions?.length" class="mt-1.5 flex flex-wrap gap-1">
                                            <Badge v-for="f in pkg.factions" :key="f.value" variant="secondary" class="gap-1 text-2xs">
                                                <img :src="f.logo" :alt="f.label" class="size-3" />
                                                {{ f.label }}
                                            </Badge>
                                        </div>
                                    </div>
                                    <Button
                                        v-if="is_owner"
                                        :aria-label="`Remove ${pkg.name} from your packages`"
                                        variant="ghost"
                                        size="icon"
                                        class="size-7 shrink-0 text-muted-foreground hover:text-destructive"
                                        :disabled="processing"
                                        data-testid="collection-package-remove"
                                        @click="askRemovePackage(pkg)"
                                    >
                                        <Trash2 class="size-3.5" />
                                    </Button>
                                </div>
                                <div v-if="pkg.models > 0" class="mt-3 space-y-1.5" data-testid="collection-package-progress">
                                    <div class="flex items-center justify-between gap-2 text-xs text-muted-foreground">
                                        <span>
                                            You have <span class="font-medium tabular-nums text-foreground">{{ pkg.owned }}</span> of
                                            {{ plural(pkg.models, 'model') }}
                                        </span>
                                        <span class="tabular-nums">{{ pkg.built }} built · {{ pkg.painted }} painted</span>
                                    </div>
                                    <div
                                        class="h-1.5 w-full overflow-hidden rounded-full bg-muted"
                                        role="progressbar"
                                        :aria-valuenow="pkg.owned"
                                        :aria-valuemin="0"
                                        :aria-valuemax="pkg.models"
                                        :aria-label="`${pkg.name}: models owned`"
                                    >
                                        <div
                                            class="h-full rounded-full bg-primary transition-all duration-500"
                                            :style="{ width: `${(pkg.owned / pkg.models) * 100}%` }"
                                        />
                                    </div>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        class="h-7 gap-1 px-1.5 text-xs"
                                        :aria-expanded="openPackages.has(pkg.id)"
                                        data-testid="collection-package-contents-toggle"
                                        @click="togglePackageOpen(pkg.id)"
                                    >
                                        What is inside
                                        <ChevronDown class="size-3.5 transition-transform" :class="openPackages.has(pkg.id) ? 'rotate-180' : ''" />
                                    </Button>
                                    <ul v-if="openPackages.has(pkg.id)" class="space-y-1 text-xs" data-testid="collection-package-contents">
                                        <li v-for="c in pkg.contents" :key="c.slug" class="flex items-center justify-between gap-2">
                                            <span class="min-w-0 truncate">{{ c.name }}</span>
                                            <span
                                                class="shrink-0 tabular-nums"
                                                :class="c.owned >= c.quantity ? 'text-foreground' : 'text-muted-foreground'"
                                            >
                                                {{ c.owned }}/{{ c.quantity }}
                                                <Check
                                                    v-if="c.owned >= c.quantity"
                                                    class="ml-0.5 inline size-3 text-green-700 dark:text-green-400"
                                                    aria-label="Complete"
                                                />
                                            </span>
                                        </li>
                                    </ul>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </TabsContent>

                <!-- Factions Tab -->
                <TabsContent value="factions">
                    <p class="mb-3 text-xs text-muted-foreground">Open a faction to see its models in your collection.</p>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <Card
                            v-for="(stat, index) in visibleFactions"
                            :key="stat.faction"
                            :class="['animate-fade-in-up opacity-0', CARD_HOVER]"
                            :style="factionDelays[index]"
                        >
                            <button
                                type="button"
                                class="block w-full rounded-lg p-4 text-left focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                data-testid="collection-faction"
                                @click="showFaction(stat.faction)"
                            >
                                <div class="mb-3 flex items-center gap-2.5">
                                    <img :src="stat.logo" :alt="stat.name" class="size-8" />
                                    <div>
                                        <div class="font-semibold">{{ stat.name }}</div>
                                        <div class="text-xs text-muted-foreground">{{ stat.owned }} / {{ plural(stat.total, 'character') }}</div>
                                    </div>
                                </div>
                                <div class="h-2 w-full overflow-hidden rounded-full bg-muted">
                                    <div
                                        class="h-full rounded-full transition-all duration-500"
                                        :style="{ width: `${stat.percent}%`, backgroundColor: `hsl(var(--${stat.color}))` }"
                                    />
                                </div>
                                <div class="mt-1.5 flex items-center justify-between gap-2 text-xs">
                                    <span v-if="stat.models > 0" class="tabular-nums text-muted-foreground"
                                        >{{ plural(stat.models, 'model') }} · {{ stat.built }} built · {{ stat.painted }} painted</span
                                    >
                                    <span v-else class="text-muted-foreground">None yet</span>
                                    <span class="font-semibold tabular-nums">{{ stat.percent }}%</span>
                                </div>
                            </button>
                        </Card>
                    </div>
                </TabsContent>

                <!-- Keywords Tab -->
                <TabsContent value="keywords">
                    <div class="mb-3 flex flex-col gap-2 sm:flex-row sm:items-center">
                        <div class="relative min-w-0 flex-1">
                            <Search class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                v-model="keywordSearch"
                                placeholder="Search keywords..."
                                aria-label="Search keywords"
                                class="pl-9"
                                data-testid="collection-keyword-search"
                            />
                        </div>
                        <Button
                            v-if="!keywordSearch && notStartedKeywords > 0"
                            variant="outline"
                            size="sm"
                            class="max-sm:min-h-11"
                            :aria-pressed="showAllKeywords"
                            data-testid="collection-keywords-all"
                            @click="showAllKeywords = !showAllKeywords"
                        >
                            {{ showAllKeywords ? 'Hide' : 'Show' }} {{ notStartedKeywords }} not started
                        </Button>
                    </div>
                    <!-- Deferred prop: light skeleton while keyword_stats streams in -->
                    <div v-if="!keyword_stats" class="space-y-1.5">
                        <div v-for="i in 8" :key="i" class="animate-pulse rounded-lg border bg-card px-3 py-2.5 sm:px-4">
                            <div class="flex items-center gap-2 sm:gap-3">
                                <div class="h-4 w-1/3 rounded bg-muted" />
                                <div class="ml-auto h-3 w-12 rounded bg-muted" />
                                <div class="h-5 w-14 rounded bg-muted" />
                            </div>
                            <div class="mt-1.5 h-1.5 w-full rounded-full bg-muted" />
                        </div>
                    </div>
                    <EmptyState
                        v-else-if="listedKeywords.length === 0"
                        :icon="Search"
                        :title="keywordSearch ? 'No keyword matches' : 'No keywords started yet'"
                        :description="keywordSearch ? 'Try a different word.' : 'A keyword appears here once you own a model that has it.'"
                    />
                    <div v-else class="space-y-1.5">
                        <div
                            v-for="(stat, index) in listedKeywords"
                            :key="stat.slug"
                            class="animate-fade-in-up rounded-lg border bg-card px-3 py-2.5 opacity-0 transition-all duration-200 hover:shadow-md sm:px-4"
                            :style="keywordDelays[Math.min(index, keywordDelays.length - 1)]"
                            data-testid="collection-keyword"
                        >
                            <div class="flex items-center gap-2 sm:gap-3">
                                <Link :href="route('keywords.view', stat.slug)" class="min-w-0 flex-1">
                                    <span class="text-sm font-medium transition-colors hover:text-primary">{{ stat.name }}</span>
                                </Link>
                                <span class="shrink-0 text-xs tabular-nums text-muted-foreground">{{ stat.owned }}/{{ stat.total }}</span>
                                <Badge
                                    :variant="stat.percent === 100 ? 'default' : 'secondary'"
                                    class="w-14 shrink-0 justify-center text-2xs tabular-nums"
                                >
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
                    <EmptyState
                        v-if="modelCount === 0"
                        :icon="Library"
                        :title="is_owner ? 'Nothing to show yet' : 'Nothing to show'"
                        :description="is_owner ? 'Stats appear once you add miniatures to your collection.' : ''"
                    />
                    <template v-else>
                        <!-- Every model by stage -->
                        <Card class="mb-6" data-testid="collection-stage-breakdown">
                            <CardHeader class="pb-3">
                                <HeadingEyebrow>Models by stage</HeadingEyebrow>
                            </CardHeader>
                            <CardContent class="space-y-3">
                                <div
                                    class="flex h-3 w-full overflow-hidden rounded-full bg-muted"
                                    role="img"
                                    :aria-label="`Models by stage: ${stageBars.map((st) => `${st.count} ${st.label}`).join(', ')}`"
                                >
                                    <div
                                        v-for="st in stageBars"
                                        :key="st.value"
                                        :class="STAGE_BAR[st.value]"
                                        :style="{ width: `${(st.count / modelCount) * 100}%` }"
                                    />
                                </div>
                                <ul class="grid grid-cols-2 gap-x-4 gap-y-1.5 text-sm sm:grid-cols-3 lg:grid-cols-5">
                                    <li v-for="st in stages" :key="st.value" class="flex items-center gap-2">
                                        <span
                                            class="size-2.5 shrink-0 rounded-full"
                                            :class="
                                                st.value === 'unbuilt'
                                                    ? 'bg-transparent ring-1 ring-inset ring-muted-foreground/70'
                                                    : STAGE_BAR[st.value]
                                            "
                                            aria-hidden="true"
                                        />
                                        <span class="min-w-0 truncate text-muted-foreground">{{ st.label }}</span>
                                        <span class="ml-auto font-medium tabular-nums">{{ st.count }}</span>
                                    </li>
                                </ul>
                                <p class="text-xs text-muted-foreground">
                                    {{ totals.built }} of {{ plural(modelCount, 'model') }} built ({{ totals.built_percent }}%) ·
                                    {{ totals.painted }} painted ({{ totals.painted_percent }}%)
                                </p>
                            </CardContent>
                        </Card>

                        <!-- What is next -->
                        <div class="mb-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
                            <Card data-testid="collection-next-paint">
                                <CardHeader class="pb-3">
                                    <HeadingEyebrow>Ready to paint</HeadingEyebrow>
                                </CardHeader>
                                <CardContent>
                                    <p v-if="toPaint.length === 0" class="text-sm text-muted-foreground">No built models waiting for paint.</p>
                                    <ul v-else class="space-y-1">
                                        <li v-for="r in toPaint" :key="r.i.miniature_id">
                                            <button
                                                type="button"
                                                class="flex w-full items-center justify-between gap-2 rounded px-1 py-1 text-left text-sm hover:bg-accent focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring max-sm:min-h-11"
                                                :title="`Show ${r.i.character_name} in the collection`"
                                                @click="searchFor(r.i.character_name)"
                                            >
                                                <span class="min-w-0 truncate">{{ itemName(r.i) }}</span>
                                                <span class="shrink-0 tabular-nums text-muted-foreground">&times;{{ r.n }}</span>
                                            </button>
                                        </li>
                                    </ul>
                                </CardContent>
                            </Card>
                            <Card data-testid="collection-next-build">
                                <CardHeader class="pb-3">
                                    <HeadingEyebrow>Still in the box</HeadingEyebrow>
                                </CardHeader>
                                <CardContent>
                                    <p v-if="toBuild.length === 0" class="text-sm text-muted-foreground">Everything is built.</p>
                                    <ul v-else class="space-y-1">
                                        <li v-for="r in toBuild" :key="r.i.miniature_id">
                                            <button
                                                type="button"
                                                class="flex w-full items-center justify-between gap-2 rounded px-1 py-1 text-left text-sm hover:bg-accent focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring max-sm:min-h-11"
                                                :title="`Show ${r.i.character_name} in the collection`"
                                                @click="searchFor(r.i.character_name)"
                                            >
                                                <span class="min-w-0 truncate">{{ itemName(r.i) }}</span>
                                                <span class="shrink-0 tabular-nums text-muted-foreground">&times;{{ r.n }}</span>
                                            </button>
                                        </li>
                                    </ul>
                                </CardContent>
                            </Card>
                        </div>

                        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                            <!-- Top factions -->
                            <Card>
                                <CardHeader class="pb-3">
                                    <HeadingEyebrow>Top Factions</HeadingEyebrow>
                                </CardHeader>
                                <CardContent class="space-y-4">
                                    <p v-if="topFactions.length === 0" class="text-sm text-muted-foreground">No faction has a character yet.</p>
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

                            <!-- Most complete keywords -->
                            <Card>
                                <CardHeader class="pb-3">
                                    <HeadingEyebrow>Most Complete Keywords</HeadingEyebrow>
                                </CardHeader>
                                <CardContent class="space-y-2.5">
                                    <p v-if="!keyword_stats" class="text-sm text-muted-foreground">Loading...</p>
                                    <p v-else-if="topKeywords.length === 0" class="text-sm text-muted-foreground">No keyword started yet.</p>
                                    <div v-for="stat in topKeywords" :key="stat.slug" class="flex items-center justify-between gap-2 text-sm">
                                        <Link
                                            :href="route('keywords.view', stat.slug)"
                                            class="min-w-0 truncate font-medium transition-colors hover:text-primary"
                                        >
                                            {{ stat.name }}
                                        </Link>
                                        <div class="flex shrink-0 items-center gap-2">
                                            <span class="tabular-nums text-muted-foreground">{{ stat.owned }}/{{ stat.total }}</span>
                                            <Badge
                                                :variant="stat.percent === 100 ? 'default' : 'secondary'"
                                                class="w-14 justify-center text-2xs tabular-nums"
                                            >
                                                {{ stat.percent }}%
                                            </Badge>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>
                    </template>
                </TabsContent>
            </Tabs>
        </div>
    </div>

    <!-- Sticky bulk-action bar: surfaces once the user has selected anything.
         Lives outside the container so it floats over the viewport bottom on
         mobile and desktop alike. -->
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
            class="fixed inset-x-0 bottom-[var(--tabbar-h,0px)] z-40 border-t bg-background/95 px-3 py-3 shadow-[0_-8px_24px_rgba(0,0,0,0.12)] backdrop-blur sm:px-6"
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
                <Select :model-value="''" @update:model-value="(v) => bulkStage(String(v))">
                    <SelectTrigger class="h-9 w-40" aria-label="Set every model to a stage" data-testid="collection-bulk-stage">
                        <SelectValue placeholder="Set stage..." />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem v-for="st in stages" :key="st.value" :value="st.value">{{ st.label }}</SelectItem>
                    </SelectContent>
                </Select>
                <Button size="sm" variant="destructive" :disabled="processing" class="ml-auto gap-1.5" @click="bulkRemove">
                    <Trash2 class="size-3.5" /> Remove {{ selectedIds.size }}
                </Button>
            </div>
        </div>
    </Transition>
</template>
