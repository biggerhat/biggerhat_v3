<script setup lang="ts">
import CharacterCardView from '@/components/CharacterCardView.vue';
import ClearableSelect from '@/components/ClearableSelect.vue';
import EmptyState from '@/components/EmptyState.vue';
import FactionLogo from '@/components/FactionLogo.vue';
import FilterPanel from '@/components/FilterPanel.vue';
import PageBanner from '@/components/PageBanner.vue';
import SearchableSelect from '@/components/SearchableSelect.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import type { SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { refDebounced } from '@vueuse/core';
import { BookMarked, Check, ChevronDown, Heart, Link2, Loader2, Plus, Search, Sparkles, X } from 'lucide-vue-next';
import { computed, onMounted, reactive, ref, watch } from 'vue';

interface BoxCharacterMiniature {
    id: number;
    slug: string;
    display_name: string;
    front_image: string | null;
    back_image: string | null;
    character_id: number;
}

interface BoxCharacter {
    display_name: string;
    slug: string;
    faction: string;
    faction_label: string;
    faction_color: string;
    quantity: number;
    special_order: boolean;
    keywords: string[];
    standard_miniature: BoxCharacterMiniature | null;
}

interface Box {
    id: number;
    name: string;
    slug: string;
    legacy_m3e_name: string | null;
    category: string | null;
    category_label: string | null;
    msrp: number | null;
    released_at: string | null;
    is_auto_generated: boolean;
    is_standard_edition: boolean;
    characters: BoxCharacter[];
}

const props = defineProps<{
    packages: Box[];
    factions: Record<string, { slug: string; name: string }>;
    categories: { name: string; value: string }[];
    keywords: { name: string; value: string }[];
}>();

const page = usePage<SharedData>();
const isLoggedIn = computed(() => !!page.props.auth?.user);
const collectionPackageIds = computed(() => new Set(page.props.auth?.collection_package_ids ?? []));
const isCollected = (pkgId: number) => collectionPackageIds.value.has(pkgId);

const wishlistPackageIds = computed(() => {
    const ids = new Set<number>();
    for (const wl of Object.values(page.props.auth?.wishlist_items ?? {})) {
        for (const id of wl.packages) ids.add(id);
    }
    return ids;
});
const isWishlisted = (pkgId: number) => wishlistPackageIds.value.has(pkgId);

const wishlists = computed(() => page.props.auth?.wishlists ?? []);
const isOnWishlist = (wishlistId: number, packageId: number) =>
    (page.props.auth?.wishlist_items?.[wishlistId]?.packages ?? []).includes(packageId);

// Mirrors AddToWishlist.vue's addToWishlist — optimistic update against the
// same shared `wishlist_items` bucket shape, via router.post rather than raw
// fetch to match the rest of this page's Inertia-only mutation style.
const addPackageToWishlist = (wishlistId: number, packageId: number) => {
    const items = page.props.auth.wishlist_items;
    if (!items[wishlistId]) {
        items[wishlistId] = { characters: [], miniatures: [], packages: [], units: [], unit_sculpts: [] };
    }
    if (items[wishlistId].packages.includes(packageId)) return;

    items[wishlistId].packages.push(packageId);

    router.post(
        route('wishlists.items.add', wishlistId),
        { type: 'package', id: packageId },
        {
            preserveScroll: true,
            preserveState: true,
            only: ['auth'],
            onError: () => {
                const idx = items[wishlistId].packages.indexOf(packageId);
                if (idx !== -1) items[wishlistId].packages.splice(idx, 1);
            },
        },
    );
};

// Track per-package in-flight state so we can disable that row's Add button
// while the request is pending — prevents double-clicks creating dupes.
// Mirrors Packages/Index.vue's addPackageToCollection.
const addingPackageIds = ref<Set<number>>(new Set());
const addPackageToCollection = (packageId: number) => {
    if (addingPackageIds.value.has(packageId)) return;

    // Optimistic update with rollback on failure.
    const pkgIds = page.props.auth.collection_package_ids;
    const wasAbsent = !pkgIds.includes(packageId);
    if (wasAbsent) pkgIds.push(packageId);

    router.post(
        route('collection.add_package'),
        { package_id: packageId },
        {
            preserveScroll: true,
            preserveState: true,
            only: ['auth'],
            onStart: () => addingPackageIds.value.add(packageId),
            onError: () => {
                if (wasAbsent) {
                    page.props.auth.collection_package_ids = pkgIds.filter((id) => id !== packageId);
                }
            },
            onFinish: () => addingPackageIds.value.delete(packageId),
        },
    );
};

// Collection membership for individual models is tracked by miniature id
// (same source CharacterCardView.vue reads), not character id — a character
// with no standard_miniature can never be "owned" since there's nothing to
// check off, so it always counts as missing.
const collectionMiniatureIds = computed(() => new Set(page.props.auth?.collection_miniature_ids ?? []));
const isCharacterCollected = (character: BoxCharacter) =>
    character.standard_miniature != null && collectionMiniatureIds.value.has(character.standard_miniature.id);

const search = ref('');
// The input itself stays instant (bound directly to `search`); the actual
// (re)filtering of 400+ boxes reads from this debounced copy instead, same
// pattern as CrewBuilder/Index.vue and PDF/Index.vue for large client-side
// lists. `isPending` (below) exposes the in-flight window so there's a
// visible cue instead of the UI just going quiet for 200ms.
const debouncedSearch = refDebounced(search, 200);
const isPending = computed(() => search.value.trim() !== debouncedSearch.value.trim());

// Multiple factions can be active at once (e.g. "Guild or Neverborn") — a
// plain array rather than a Set so it serializes trivially to a comma-joined
// URL param.
const activeFactions = ref<string[]>([]);
const activeCategory = ref<string | null>(null);
const activeKeyword = ref<string | null>(null);
const standardOnly = ref(false);
const missingCharactersOnly = ref(false);
// Values are 'owned' | 'missing' — only meaningful when logged in, since
// collection state doesn't exist for guests. Kept as a plain string|null (not
// a union) so it binds cleanly to ClearableSelect's string-typed v-model.
const collectionFilter = ref<string | null>(null);
const collectionFilterOptions = [
    { name: 'In My Collection', value: 'owned' },
    { name: 'Not in My Collection', value: 'missing' },
];
const sortBy = ref('name');
const sortOptions = [
    { name: 'Name (A–Z)', value: 'name' },
    { name: 'MSRP: Low to High', value: 'msrp_asc' },
    { name: 'MSRP: High to Low', value: 'msrp_desc' },
    { name: 'Newest Release', value: 'released_desc' },
    { name: 'Oldest Release', value: 'released_asc' },
];
const openBoxes = reactive<Set<string>>(new Set());

const toggleFaction = (slug: string) => {
    const i = activeFactions.value.indexOf(slug);
    if (i >= 0) activeFactions.value.splice(i, 1);
    else activeFactions.value.push(slug);
};

const matchesSearch = (box: Box, term: string) => {
    if (box.name.toLowerCase().includes(term)) return true;
    if (box.legacy_m3e_name?.toLowerCase().includes(term)) return true;
    return box.characters.some((c) => c.display_name.toLowerCase().includes(term));
};

const filteredBoxes = computed(() => {
    const term = debouncedSearch.value.trim().toLowerCase();

    return props.packages.filter((box) => {
        if (activeFactions.value.length && !box.characters.some((c) => activeFactions.value.includes(c.faction))) {
            return false;
        }
        if (activeCategory.value && box.category !== activeCategory.value) {
            return false;
        }
        if (activeKeyword.value && !box.characters.some((c) => c.keywords.includes(activeKeyword.value!))) {
            return false;
        }
        if (standardOnly.value && !box.is_standard_edition) {
            return false;
        }
        if (collectionFilter.value === 'owned' && !isCollected(box.id)) {
            return false;
        }
        if (collectionFilter.value === 'missing' && isCollected(box.id)) {
            return false;
        }
        if (missingCharactersOnly.value && !box.characters.some((c) => !isCharacterCollected(c))) {
            return false;
        }
        if (term && !matchesSearch(box, term)) {
            return false;
        }
        return true;
    });
});

// Sorting is a separate pass over the already-filtered set — keeps the
// filter predicate above focused purely on inclusion/exclusion. Boxes
// missing the sorted-on field (no MSRP, no release date) sort to the end
// regardless of direction, rather than clustering at whichever end `null`
// happens to coerce to numerically.
const sortedBoxes = computed(() => {
    const boxes = [...filteredBoxes.value];

    const byNullableNumber = (getter: (b: Box) => number | null, direction: 1 | -1) => (a: Box, b: Box) => {
        const av = getter(a);
        const bv = getter(b);
        if (av == null && bv == null) return 0;
        if (av == null) return 1;
        if (bv == null) return -1;
        return (av - bv) * direction;
    };

    switch (sortBy.value) {
        case 'msrp_asc':
            return boxes.sort(byNullableNumber((b) => b.msrp, 1));
        case 'msrp_desc':
            return boxes.sort(byNullableNumber((b) => b.msrp, -1));
        case 'released_asc':
            return boxes.sort(byNullableNumber((b) => (b.released_at ? new Date(b.released_at).getTime() : null), 1));
        case 'released_desc':
            return boxes.sort(byNullableNumber((b) => (b.released_at ? new Date(b.released_at).getTime() : null), -1));
        default:
            return boxes.sort((a, b) => a.name.localeCompare(b.name));
    }
});

const totalMsrpCents = computed(() => filteredBoxes.value.reduce((sum, box) => sum + (box.msrp ?? 0), 0));
const formatMsrp = (cents: number | null | undefined) => (cents ? `$${(cents / 100).toFixed(2)}` : null);

// Quick-facts computed against the current filtered view (not the full
// catalog) so they double as live feedback on what a filter combination
// actually turned up — mirrors the stat-chip treatment on Factions/View.vue
// and Keywords/View.vue.
const uniqueCharacterCount = computed(() => new Set(filteredBoxes.value.flatMap((b) => b.characters.map((c) => c.slug))).size);
const uniqueKeywordCount = computed(() => new Set(filteredBoxes.value.flatMap((b) => b.characters.flatMap((c) => c.keywords))).size);
const uniqueFactionCount = computed(() => new Set(filteredBoxes.value.flatMap((b) => b.characters.map((c) => c.faction))).size);

const isFiltering = computed(
    () =>
        search.value.trim().length > 0 ||
        activeFactions.value.length > 0 ||
        activeCategory.value !== null ||
        activeKeyword.value !== null ||
        standardOnly.value ||
        missingCharactersOnly.value ||
        collectionFilter.value !== null,
);

// A free-text search naturally wants its matches visible — auto-expand so
// the matched model is right there instead of an extra click. Deliberately
// NOT extended to the other filters (category/keyword/faction/collection
// toggles etc.): with 400+ boxes in the catalog, any of those can still
// leave hundreds matching, and force-expanding all of them at once means
// mounting hundreds of CharacterCardView grids simultaneously — that mass
// mount, not the (cheap) array filter itself, was the actual source of the
// multi-second freeze on toggle. Capped to a small result count so a broad
// search term doesn't reintroduce the same problem.
const autoExpandSearchMatches = computed(() => debouncedSearch.value.trim().length > 0 && filteredBoxes.value.length <= 30);
const isOpen = (slug: string) => autoExpandSearchMatches.value || openBoxes.has(slug);
const toggleBox = (slug: string) => {
    if (openBoxes.has(slug)) {
        openBoxes.delete(slug);
    } else {
        openBoxes.add(slug);
    }
};

const clearSearch = () => {
    search.value = '';
};

const clearAllFilters = () => {
    search.value = '';
    activeFactions.value = [];
    activeCategory.value = null;
    activeKeyword.value = null;
    standardOnly.value = false;
    missingCharactersOnly.value = false;
    collectionFilter.value = null;
    sortBy.value = 'name';
};

const hasActiveFilters = computed(() => isFiltering.value);

// Drives the FilterPanel trigger's badge — counts filter *dimensions* set
// (not matched results), and deliberately excludes free-text search (that
// input stays visible outside the panel on every breakpoint) and sort
// (it reorders rather than narrows, so it doesn't read as a "filter").
const activeFilterCount = computed(() => {
    let count = 0;
    if (activeFactions.value.length) count++;
    if (activeCategory.value) count++;
    if (activeKeyword.value) count++;
    if (standardOnly.value) count++;
    if (missingCharactersOnly.value) count++;
    if (collectionFilter.value) count++;
    return count;
});

// Combine multiple filters into one click for common browsing intents.
// Presets always start from a clean slate (clearAllFilters) so applying one
// never mixes leftover state from whatever was set before.
interface Preset {
    label: string;
    apply: () => void;
}
const presets = computed<Preset[]>(() => {
    const list: Preset[] = [
        { label: 'Core Boxes Only', apply: () => (activeCategory.value = 'core_box') },
        { label: 'Standard Editions Only', apply: () => (standardOnly.value = true) },
    ];
    if (isLoggedIn.value) {
        list.push(
            { label: "Boxes I'm Missing", apply: () => (collectionFilter.value = 'missing') },
            { label: "Contains Characters I Don't Own", apply: () => (missingCharactersOnly.value = true) },
        );
    }
    return list;
});
const applyPreset = (preset: Preset) => {
    clearAllFilters();
    preset.apply();
};

// All filtering happens client-side against the already-loaded payload, so
// there's no need for a full Inertia visit to make a filtered view
// shareable — just keep the URL in sync via history.replaceState (mirrors
// the pattern in Tools/SchemePath.vue) so the address bar always reflects
// the current filters and can be copied/bookmarked directly.
const syncUrl = () => {
    const params = new URLSearchParams();
    if (debouncedSearch.value.trim()) params.set('search', debouncedSearch.value.trim());
    if (activeFactions.value.length) params.set('faction', activeFactions.value.join(','));
    if (activeCategory.value) params.set('category', activeCategory.value);
    if (activeKeyword.value) params.set('keyword', activeKeyword.value);
    if (standardOnly.value) params.set('standard', '1');
    if (missingCharactersOnly.value) params.set('missing_characters', '1');
    if (collectionFilter.value) params.set('collection', collectionFilter.value);
    if (sortBy.value !== 'name') params.set('sort', sortBy.value);
    const query = params.toString();
    window.history.replaceState({}, '', `${window.location.pathname}${query ? `?${query}` : ''}`);
};

watch(
    [debouncedSearch, activeFactions, activeCategory, activeKeyword, standardOnly, missingCharactersOnly, collectionFilter, sortBy],
    syncUrl,
    { deep: true },
);

onMounted(() => {
    const params = new URLSearchParams(window.location.search);

    const searchParam = params.get('search');
    if (searchParam) search.value = searchParam;

    // Validate against the actual option lists so a stale/malformed URL
    // (old bookmark, hand-edited link) degrades to "no filter" instead of
    // silently matching zero boxes.
    const validFactionSlugs = new Set(Object.values(props.factions).map((f) => f.slug));
    const factionParam = params.get('faction');
    if (factionParam) {
        activeFactions.value = factionParam.split(',').filter((slug) => validFactionSlugs.has(slug));
    }

    const categoryParam = params.get('category');
    if (categoryParam && props.categories.some((c) => c.value === categoryParam)) {
        activeCategory.value = categoryParam;
    }

    const keywordParam = params.get('keyword');
    if (keywordParam && props.keywords.some((k) => k.value === keywordParam)) {
        activeKeyword.value = keywordParam;
    }

    if (params.get('standard') === '1') {
        standardOnly.value = true;
    }

    if (isLoggedIn.value && params.get('missing_characters') === '1') {
        missingCharactersOnly.value = true;
    }

    const collectionParam = params.get('collection');
    if (isLoggedIn.value && (collectionParam === 'owned' || collectionParam === 'missing')) {
        collectionFilter.value = collectionParam;
    }

    const sortParam = params.get('sort');
    if (sortParam && sortOptions.some((o) => o.value === sortParam)) {
        sortBy.value = sortParam;
    }
});

const linkCopied = ref(false);
const copyLink = async () => {
    try {
        await navigator.clipboard.writeText(window.location.href);
        linkCopied.value = true;
        setTimeout(() => (linkCopied.value = false), 1500);
    } catch {
        // clipboard blocked — no-op
    }
};
</script>

<template>
    <Head title="Box Contents" />
    <div>
        <PageBanner title="Box Contents">
            <template #subtitle>
                <div class="my-auto flex flex-wrap items-center gap-x-1 px-2 py-0 text-xs text-muted-foreground md:py-2 md:text-sm md:text-foreground">
                    <span>{{ filteredBoxes.length }} of {{ props.packages.length }} boxes</span>
                    <template v-if="totalMsrpCents > 0">
                        <span class="text-muted-foreground/50">&middot;</span>
                        <span class="font-medium">{{ formatMsrp(totalMsrpCents) }} total MSRP</span>
                    </template>
                    <span class="text-muted-foreground/50">&middot;</span>
                    <span>{{ uniqueCharacterCount }} characters</span>
                    <span class="text-muted-foreground/50">&middot;</span>
                    <span>{{ uniqueKeywordCount }} keywords</span>
                    <span class="text-muted-foreground/50">&middot;</span>
                    <span>{{ uniqueFactionCount }} factions</span>
                </div>
            </template>
        </PageBanner>

        <div class="container mx-auto mb-3 sm:px-4">
            <div class="relative">
                <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                <Input v-model="search" type="text" placeholder="Search box or model name..." class="border-2 border-primary pl-10 pr-10" />
                <Loader2 v-if="isPending" class="absolute right-9 top-1/2 h-4 w-4 -translate-y-1/2 animate-spin text-muted-foreground" />
                <button v-if="search" class="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground" @click="clearSearch">
                    <X class="h-4 w-4" />
                </button>
            </div>
        </div>

        <!-- Desktop: every filter control visible inline, no extra clicks. -->
        <div class="container mx-auto mb-3 hidden sm:px-4 md:block">
            <div class="flex flex-wrap items-center gap-2">
                <ClearableSelect
                    v-model="activeCategory"
                    placeholder="Any Category"
                    :options="props.categories"
                    trigger-class="h-8 w-40 text-xs border-2 border-primary rounded"
                />
                <SearchableSelect
                    v-model="activeKeyword"
                    placeholder="Any Keyword"
                    :options="props.keywords"
                    trigger-class="h-8 w-44 text-xs border-2 border-primary rounded"
                />
                <ClearableSelect
                    v-if="isLoggedIn"
                    v-model="collectionFilter"
                    placeholder="Any Collection Status"
                    :options="collectionFilterOptions"
                    trigger-class="h-8 w-48 text-xs border-2 border-primary rounded"
                />
                <Select v-model="sortBy">
                    <SelectTrigger class="h-8 w-44 border-2 border-primary text-xs">
                        <SelectValue placeholder="Sort by" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem v-for="opt in sortOptions" :key="opt.value" :value="opt.value">{{ opt.name }}</SelectItem>
                    </SelectContent>
                </Select>
                <label class="flex h-8 cursor-pointer items-center gap-1.5 rounded border-2 border-primary px-2.5">
                    <Switch id="standard-only-toggle" v-model="standardOnly" class="h-4 w-7" />
                    <Label for="standard-only-toggle" class="cursor-pointer text-xs font-medium text-muted-foreground">
                        Standard editions only
                    </Label>
                </label>
                <label v-if="isLoggedIn" class="flex h-8 cursor-pointer items-center gap-1.5 rounded border-2 border-primary px-2.5">
                    <Switch id="missing-characters-toggle" v-model="missingCharactersOnly" class="h-4 w-7" />
                    <Label for="missing-characters-toggle" class="cursor-pointer text-xs font-medium text-muted-foreground">
                        Contains models I don't own
                    </Label>
                </label>
                <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                        <Button variant="outline" size="sm" class="h-8 gap-1 text-xs">
                            <Sparkles class="h-3.5 w-3.5" />
                            <span class="hidden sm:inline">Presets</span>
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="start">
                        <DropdownMenuItem v-for="preset in presets" :key="preset.label" @click="applyPreset(preset)">
                            {{ preset.label }}
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
                <button v-if="hasActiveFilters" class="text-xs text-muted-foreground underline hover:text-foreground" @click="clearAllFilters">
                    Clear filters
                </button>
                <button
                    v-if="hasActiveFilters"
                    class="flex items-center gap-1 text-xs text-muted-foreground hover:text-foreground"
                    @click="copyLink"
                >
                    <Check v-if="linkCopied" class="h-3 w-3" />
                    <Link2 v-else class="h-3 w-3" />
                    {{ linkCopied ? 'Copied!' : 'Copy link to this view' }}
                </button>
            </div>
        </div>

        <!-- Mobile: same filters, condensed behind a single Filters trigger
             (FilterPanel — a Sheet on tablet-width, a bottom Drawer on phone;
             see ListSearchBar.vue for the same pattern on other browse pages)
             instead of ~7 controls wrapping across several lines. -->
        <div class="container mx-auto mb-3 px-4 md:hidden">
            <div class="flex flex-wrap items-center gap-2">
                <FilterPanel :filter-count="activeFilterCount" @clear="clearAllFilters">
                    <div class="grid gap-4">
                        <div class="space-y-2">
                            <label class="text-sm font-medium">Category</label>
                            <ClearableSelect v-model="activeCategory" placeholder="Any Category" :options="props.categories" />
                        </div>
                        <div class="space-y-2">
                            <label class="text-sm font-medium">Keyword</label>
                            <SearchableSelect v-model="activeKeyword" placeholder="Any Keyword" :options="props.keywords" />
                        </div>
                        <div v-if="isLoggedIn" class="space-y-2">
                            <label class="text-sm font-medium">Collection Status</label>
                            <ClearableSelect v-model="collectionFilter" placeholder="Any Collection Status" :options="collectionFilterOptions" />
                        </div>
                        <div class="space-y-2">
                            <label class="text-sm font-medium">Sort By</label>
                            <Select v-model="sortBy">
                                <SelectTrigger><SelectValue placeholder="Sort by" /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="opt in sortOptions" :key="opt.value" :value="opt.value">{{ opt.name }}</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <label class="flex cursor-pointer items-center gap-2 text-sm">
                            <Switch id="standard-only-toggle-mobile" v-model="standardOnly" />
                            Standard editions only
                        </label>
                        <label v-if="isLoggedIn" class="flex cursor-pointer items-center gap-2 text-sm">
                            <Switch id="missing-characters-toggle-mobile" v-model="missingCharactersOnly" />
                            Contains models I don't own
                        </label>
                    </div>
                </FilterPanel>
                <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                        <Button variant="outline" size="sm" class="h-8 gap-1 text-xs">
                            <Sparkles class="h-3.5 w-3.5" />
                            Presets
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="start">
                        <DropdownMenuItem v-for="preset in presets" :key="preset.label" @click="applyPreset(preset)">
                            {{ preset.label }}
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
                <button v-if="hasActiveFilters" class="text-xs text-muted-foreground underline hover:text-foreground" @click="clearAllFilters">
                    Clear filters
                </button>
                <button
                    v-if="hasActiveFilters"
                    class="flex items-center gap-1 text-xs text-muted-foreground hover:text-foreground"
                    @click="copyLink"
                >
                    <Check v-if="linkCopied" class="h-3 w-3" />
                    <Link2 v-else class="h-3 w-3" />
                    {{ linkCopied ? 'Copied!' : 'Copy' }}
                </button>
            </div>
        </div>

        <div class="container mx-auto mb-4 sm:px-4">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <button
                    v-for="(faction, key) in factions"
                    :key="key"
                    @click="toggleFaction(faction.slug)"
                    class="rounded-full p-1 transition-all"
                    :class="activeFactions.includes(faction.slug) ? 'ring-2 ring-primary' : 'opacity-50 hover:opacity-100'"
                    :title="faction.name"
                >
                    <FactionLogo :faction="faction.slug" class-name="h-6 w-6" />
                </button>
            </div>
        </div>

        <div class="container mx-auto sm:px-4">
            <EmptyState v-if="!filteredBoxes.length" description="Try a different search term or clear the active filters." />

            <div v-else class="space-y-2">
                <Collapsible v-for="box in sortedBoxes" :key="box.slug" :open="isOpen(box.slug)" @update:open="toggleBox(box.slug)">
                    <div class="rounded-lg border">
                        <!-- Name is a plain link, sized to its own text (not
                             flex-1) so its clickable area never extends past
                             the visible words — clicking it navigates to the
                             package page and nothing else. `justify-between`
                             on its row pins the mobile chevron to the far
                             edge without stretching the link itself. It still
                             gets its own full-width line so a long name never
                             gets squeezed by badges/MSRP/model count, which
                             flow into a second, wrapping line instead of
                             competing for the same row.

                             The Add to Collection / Add to Wishlist buttons
                             are real buttons and must not nest inside another
                             button, so the toggle area uses two independent
                             CollapsibleTrigger instances (both bound to the
                             same Collapsible) bracketing the action buttons
                             rather than one trigger wrapping everything —
                             clicking a badge, the price, model count, or
                             chevron toggles the row; clicking an action
                             button only does its own thing. -->
                        <div class="flex w-full flex-col gap-1.5 px-4 pt-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between sm:gap-3">
                            <div class="flex min-w-0 items-center justify-between gap-2">
                                <Link
                                    :href="route('packages.view', { package: box.slug })"
                                    class="min-w-0 truncate font-semibold text-primary hover:underline"
                                >
                                    {{ box.name }}
                                </Link>
                                <ChevronDown class="h-4 w-4 shrink-0 transition-transform sm:hidden" :class="{ 'rotate-180': isOpen(box.slug) }" />
                            </div>
                            <div class="flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-muted-foreground">
                                <CollapsibleTrigger class="-mx-2 flex flex-wrap items-center gap-x-2 gap-y-1 rounded px-2 py-1 hover:bg-muted/50">
                                    <Badge v-if="box.category_label" variant="outline" class="shrink-0 text-xs">{{ box.category_label }}</Badge>
                                    <span v-if="box.legacy_m3e_name" class="hidden shrink-0 sm:inline">(M3E: {{ box.legacy_m3e_name }})</span>
                                    <span
                                        v-if="isCollected(box.id)"
                                        class="flex shrink-0 items-center gap-1"
                                        style="color: #059669"
                                        title="In Collection"
                                    >
                                        <BookMarked class="size-3.5" />
                                        <span class="hidden sm:inline">Collected</span>
                                    </span>
                                </CollapsibleTrigger>
                                <Button
                                    v-if="isLoggedIn && !isCollected(box.id)"
                                    variant="outline"
                                    size="sm"
                                    class="h-6 shrink-0 gap-1 px-2 text-[11px] text-muted-foreground hover:text-foreground"
                                    :disabled="addingPackageIds.has(box.id)"
                                    @click="addPackageToCollection(box.id)"
                                >
                                    <Plus class="size-3" />
                                    <span class="hidden sm:inline">Add to Collection</span>
                                </Button>
                                <DropdownMenu v-if="isLoggedIn">
                                    <DropdownMenuTrigger as-child>
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            class="h-6 shrink-0 gap-1 px-2 text-[11px]"
                                            :class="
                                                isWishlisted(box.id)
                                                    ? 'border-rose-500/40 text-rose-600 dark:text-rose-400'
                                                    : 'text-muted-foreground hover:text-foreground'
                                            "
                                        >
                                            <Heart class="size-3" :class="{ 'fill-current': isWishlisted(box.id) }" />
                                            <span class="hidden sm:inline">{{ isWishlisted(box.id) ? 'Wishlisted' : 'Add to Wishlist' }}</span>
                                        </Button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent align="start">
                                        <template v-if="wishlists.length">
                                            <DropdownMenuItem
                                                v-for="wl in wishlists"
                                                :key="wl.id"
                                                @click="addPackageToWishlist(wl.id, box.id)"
                                            >
                                                <Check v-if="isOnWishlist(wl.id, box.id)" class="mr-2 size-3.5" />
                                                {{ wl.name }}
                                            </DropdownMenuItem>
                                        </template>
                                        <DropdownMenuItem v-else as-child>
                                            <Link :href="route('wishlists.index')">Create a wishlist</Link>
                                        </DropdownMenuItem>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                                <CollapsibleTrigger class="-mx-2 flex flex-wrap items-center gap-x-2 gap-y-1 rounded px-2 py-1 hover:bg-muted/50">
                                    <span v-if="formatMsrp(box.msrp)" class="shrink-0 font-medium">{{ formatMsrp(box.msrp) }}</span>
                                    <span class="shrink-0">{{ box.characters.length }} {{ box.characters.length === 1 ? 'model' : 'models' }}</span>
                                    <ChevronDown class="h-4 w-4 shrink-0 transition-transform sm:ml-1" :class="{ 'rotate-180': isOpen(box.slug) }" />
                                </CollapsibleTrigger>
                            </div>
                        </div>
                        <CollapsibleContent class="border-t px-4 py-4">
                            <div class="grid grid-cols-2 gap-4 md:grid-cols-4 lg:grid-cols-5">
                                <div
                                    v-for="character in box.characters"
                                    :key="character.slug"
                                    class="relative rounded-lg"
                                    :class="{
                                        'ring-2 ring-amber-500 ring-offset-2 ring-offset-background':
                                            missingCharactersOnly && isLoggedIn && !isCharacterCollected(character),
                                    }"
                                >
                                    <Badge v-if="character.quantity > 1" class="absolute right-1 top-1 z-10 text-xs">
                                        ×{{ character.quantity }}
                                    </Badge>
                                    <Badge
                                        v-if="character.special_order"
                                        variant="outline"
                                        class="absolute left-1 top-1 z-10 border-amber-500/50 bg-background text-[10px] text-amber-600 dark:text-amber-400"
                                        title="Available for special order"
                                    >
                                        Special Order
                                    </Badge>
                                    <CharacterCardView
                                        v-if="character.standard_miniature"
                                        :miniature="character.standard_miniature"
                                        :character-slug="character.slug"
                                    />
                                    <div v-else class="flex h-full flex-col items-center justify-center gap-1 rounded-lg border border-dashed p-4 text-center">
                                        <FactionLogo :faction="character.faction" class-name="h-6 w-6" />
                                        <Link :href="route('characters.view', { character: character.slug, miniature: 1, slug: 'view' })" class="text-sm text-primary hover:underline">
                                            {{ character.display_name }}
                                        </Link>
                                    </div>
                                </div>
                            </div>
                        </CollapsibleContent>
                    </div>
                </Collapsible>
            </div>
        </div>
    </div>
</template>
