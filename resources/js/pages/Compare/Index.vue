<script setup lang="ts">
import CharacterCardView from '@/components/CharacterCardView.vue';
import FactionLogo from '@/components/FactionLogo.vue';
import GameIcon from '@/components/GameIcon.vue';
import PageBanner from '@/components/PageBanner.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Head, Link } from '@inertiajs/vue3';
import EmptyState from '@/components/EmptyState.vue';
import { Copy, Loader2, Search, Swords, X } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';

interface CompareCharacter {
    id: number;
    display_name: string;
    name: string;
    title: string | null;
    slug: string;
    faction: string;
    station: string | null;
    cost: number | null;
    health: number | null;
    defense: number | null;
    defense_suit: string | null;
    willpower: number | null;
    willpower_suit: string | null;
    speed: number | null;
    size: number | null;
    base: number | null;
    count: number | null;
    front_image: string | null;
    back_image: string | null;
    combination_image: string | null;
    keywords: { name: string; slug: string }[];
    characteristics: string[];
    actions: any[];
    abilities: any[];
}

interface SearchResult {
    id: number;
    display_name: string;
    slug: string;
    faction: string;
    station: string | null;
    front_image: string | null;
}

const characters = ref<CompareCharacter[]>([]);
const loading = ref(false);
const loadError = ref<string | null>(null);
const searchQuery = ref('');
const searchResults = ref<SearchResult[]>([]);
const searchLoading = ref(false);
const showResults = ref(false);
const highlightIndex = ref(-1);
const linkCopied = ref(false);
let debounceTimer: ReturnType<typeof setTimeout>;
// Track the active search request so a later keystroke can cancel an in-flight
// fetch — otherwise the slower response can overwrite fresher results.
let searchAbort: AbortController | null = null;

const slugs = computed(() => characters.value.map((c) => c.slug));
const canAdd = computed(() => characters.value.length < 4);

const statFields = [
    { key: 'cost', label: 'Cost', suffix: 'ss' },
    { key: 'health', label: 'Health' },
    { key: 'defense', label: 'Df', suitKey: 'defense_suit' },
    { key: 'willpower', label: 'Wp', suitKey: 'willpower_suit' },
    { key: 'speed', label: 'Spd' },
    { key: 'size', label: 'Sz' },
] as const;

const bestStat = (key: string): number | null => {
    const vals = characters.value.map((c) => (c as any)[key] as number | null).filter((v) => v != null) as number[];
    if (!vals.length) return null;
    return key === 'cost' ? Math.min(...vals) : Math.max(...vals);
};

const worstStat = (key: string): number | null => {
    const vals = characters.value.map((c) => (c as any)[key] as number | null).filter((v) => v != null) as number[];
    if (!vals.length) return null;
    return key === 'cost' ? Math.max(...vals) : Math.min(...vals);
};

const statClass = (key: string, value: number | null): string => {
    if (value == null || characters.value.length < 2) return '';
    const best = bestStat(key);
    const worst = worstStat(key);
    if (best === worst) return '';
    if (value === best) return 'text-green-600 dark:text-green-400';
    if (value === worst) return 'text-red-500 dark:text-red-400';
    return '';
};

const updateUrl = () => {
    const params = slugs.value.length ? `?characters=${slugs.value.join(',')}` : '';
    window.history.replaceState({}, '', route('tools.compare') + params);
};

const fetchCharacters = async (slugList: string[]) => {
    if (!slugList.length) return;
    loading.value = true;
    loadError.value = null;
    try {
        const res = await fetch(`/api/characters/compare?slugs=${slugList.join(',')}`);
        if (!res.ok) {
            loadError.value = 'Could not load comparison. Please try again.';
            return;
        }
        characters.value = await res.json();
    } catch {
        loadError.value = 'Network error. Please check your connection and try again.';
    } finally {
        loading.value = false;
    }
};

const addCharacter = async (slug: string) => {
    if (!canAdd.value || slugs.value.includes(slug)) return;
    showResults.value = false;
    searchQuery.value = '';
    searchResults.value = [];
    loading.value = true;
    loadError.value = null;
    try {
        const res = await fetch(`/api/characters/compare?slugs=${[...slugs.value, slug].join(',')}`);
        if (!res.ok) {
            loadError.value = 'Could not add character. Please try again.';
            return;
        }
        characters.value = await res.json();
        updateUrl();
    } catch {
        loadError.value = 'Network error while adding character.';
    } finally {
        loading.value = false;
    }
};

const removeCharacter = (slug: string) => {
    characters.value = characters.value.filter((c) => c.slug !== slug);
    updateUrl();
};

const copyLink = async () => {
    await navigator.clipboard.writeText(window.location.href);
    linkCopied.value = true;
    setTimeout(() => (linkCopied.value = false), 2000);
};

watch(searchQuery, (q) => {
    clearTimeout(debounceTimer);
    // Cancel any in-flight search — a later keystroke supersedes the earlier one.
    searchAbort?.abort();
    if (q.length < 2) {
        searchResults.value = [];
        showResults.value = false;
        return;
    }
    searchLoading.value = true;
    debounceTimer = setTimeout(async () => {
        searchAbort = new AbortController();
        try {
            const res = await fetch(`/api/characters/search?q=${encodeURIComponent(q)}`, { signal: searchAbort.signal });
            if (res.ok) {
                const data = await res.json();
                searchResults.value = (data.data ?? data).filter((r: any) => !slugs.value.includes(r.slug)).slice(0, 8);
                showResults.value = searchResults.value.length > 0;
                highlightIndex.value = -1;
            }
        } catch (e) {
            // Aborted fetches reject with a DOMException — ignore those; they're expected.
            if (!(e instanceof DOMException && e.name === 'AbortError')) {
                searchResults.value = [];
            }
        } finally {
            searchLoading.value = false;
        }
    }, 250);
});

const handleKeydown = (e: KeyboardEvent) => {
    if (!showResults.value) return;
    if (e.key === 'ArrowDown') {
        e.preventDefault();
        highlightIndex.value = Math.min(highlightIndex.value + 1, searchResults.value.length - 1);
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        highlightIndex.value = Math.max(highlightIndex.value - 1, -1);
    } else if (e.key === 'Enter' && highlightIndex.value >= 0) {
        e.preventDefault();
        addCharacter(searchResults.value[highlightIndex.value].slug);
    } else if (e.key === 'Escape') {
        showResults.value = false;
    }
};

onMounted(() => {
    const params = new URLSearchParams(window.location.search);
    const initial = params.get('characters');
    if (initial) {
        fetchCharacters(initial.split(',').filter(Boolean).slice(0, 4));
    }
});

const factionColor = (faction: string) => {
    const map: Record<string, string> = {
        arcanists: 'arcanists',
        bayou: 'bayou',
        guild: 'guild',
        explorers_society: 'explorerssociety',
        neverborn: 'neverborn',
        outcasts: 'outcasts',
        resurrectionists: 'resurrectionists',
        ten_thunders: 'tenthunders',
    };
    return map[faction] ?? faction;
};
</script>

<template>
    <Head title="Compare Characters" />
    <div class="relative">
        <div
            class="pointer-events-none absolute inset-x-0 top-0 h-64 opacity-[0.07] dark:opacity-[0.12]"
            :style="{ background: 'radial-gradient(ellipse at top, hsl(var(--primary)) 0%, transparent 70%)' }"
        />

        <PageBanner title="Compare Characters" class="mb-2">
            <template #subtitle>
                <div class="my-auto px-2 py-0 text-xs text-muted-foreground md:py-2 md:text-sm md:text-foreground">
                    Side-by-side comparison of up to 4 models
                </div>
            </template>
        </PageBanner>

        <div class="container mx-auto pb-8 sm:px-4">
            <!-- Search + actions -->
            <div class="mb-4 flex flex-wrap items-center gap-2">
                <div class="relative min-w-0 flex-1">
                    <Search class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        v-model="searchQuery"
                        :placeholder="canAdd ? 'Search for a character to add...' : 'Maximum 4 characters reached'"
                        :disabled="!canAdd"
                        class="pl-10"
                        @keydown="handleKeydown"
                        @blur="setTimeout(() => (showResults = false), 200)"
                    />
                    <div
                        v-if="showResults && searchResults.length"
                        class="absolute left-0 right-0 top-full z-50 mt-1 overflow-hidden rounded-md border bg-popover shadow-lg"
                    >
                        <button
                            v-for="(item, idx) in searchResults"
                            :key="item.id"
                            class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm transition-colors hover:bg-accent"
                            :class="{ 'bg-accent': idx === highlightIndex }"
                            @mousedown.prevent="addCharacter(item.slug)"
                        >
                            <img v-if="item.front_image" :src="item.front_image" :alt="item.display_name" class="size-8 rounded object-cover" />
                            <span class="flex-1 truncate font-medium">{{ item.display_name }}</span>
                            <Badge v-if="item.faction" variant="outline" class="shrink-0 text-[10px]">{{ item.faction.replace('_', ' ') }}</Badge>
                        </button>
                    </div>
                </div>
                <Button v-if="characters.length >= 2" variant="outline" size="sm" class="shrink-0 gap-1.5" @click="copyLink">
                    <Copy class="size-3.5" />
                    {{ linkCopied ? 'Copied!' : 'Share' }}
                </Button>
            </div>

            <!-- Error banner -->
            <div v-if="loadError" class="mb-4 rounded-lg border border-destructive/40 bg-destructive/10 px-4 py-3 text-sm text-destructive">
                <div class="flex items-start justify-between gap-2">
                    <span>{{ loadError }}</span>
                    <button class="shrink-0 text-xs hover:underline" @click="loadError = null">Dismiss</button>
                </div>
            </div>

            <!-- Loading -->
            <div v-if="loading && !characters.length" class="flex items-center justify-center py-16">
                <Loader2 class="size-6 animate-spin text-muted-foreground" />
            </div>

            <!-- Empty state -->
            <EmptyState
                v-else-if="!characters.length"
                :icon="Swords"
                title="No characters selected"
                description="Search above to add characters for comparison."
            />

            <!-- Comparison grid -->
            <div v-else>
                <div
                    class="grid gap-4"
                    :class="
                        characters.length === 1
                            ? 'max-w-sm grid-cols-1'
                            : characters.length === 2
                              ? 'grid-cols-2'
                              : characters.length === 3
                                ? 'grid-cols-2 lg:grid-cols-3'
                                : 'grid-cols-2 lg:grid-cols-4'
                    "
                >
                    <div v-for="char in characters" :key="char.slug" class="min-w-0">
                        <!-- Header: image + name -->
                        <Card class="mb-3 overflow-hidden">
                            <div class="relative" :style="{ borderTop: `3px solid hsl(var(--${factionColor(char.faction)}))` }">
                                <button
                                    class="absolute right-1 top-1 z-10 rounded-full bg-black/40 p-1 text-white/70 hover:bg-red-500/80 hover:text-white"
                                    aria-label="Remove"
                                    @click="removeCharacter(char.slug)"
                                >
                                    <X class="size-3" />
                                </button>
                                <!-- XL: combo image (front + back side by side) -->
                                <div v-if="char.combination_image" class="hidden bg-muted/30 p-2 xl:flex xl:justify-center">
                                    <img :src="'/storage/' + char.combination_image" :alt="char.display_name" class="w-full rounded object-contain" />
                                </div>
                                <!-- Smaller screens: flip card -->
                                <div v-if="char.front_image" class="bg-muted/30 p-2" :class="char.combination_image ? 'xl:hidden' : ''">
                                    <CharacterCardView
                                        :miniature="{
                                            id: char.id,
                                            display_name: char.display_name,
                                            slug: char.slug,
                                            front_image: char.front_image,
                                            back_image: char.back_image,
                                        }"
                                        :show-link="false"
                                        :show-collection="false"
                                    />
                                </div>
                            </div>
                            <CardContent class="p-3">
                                <div class="flex items-center gap-1.5">
                                    <FactionLogo :faction="char.faction" class-name="size-5 shrink-0" />
                                    <Link
                                        :href="
                                            route('characters.view', {
                                                character: char.slug,
                                                miniature: char.miniature_id,
                                                slug: char.miniature_slug ?? char.slug,
                                            })
                                        "
                                        class="min-w-0 truncate text-sm font-bold hover:text-primary hover:underline"
                                    >
                                        {{ char.display_name }}
                                    </Link>
                                </div>
                                <div class="mt-1 flex flex-wrap gap-1">
                                    <Badge v-if="char.station" variant="outline" class="text-[10px] capitalize">{{ char.station }}</Badge>
                                    <Badge v-if="char.base" variant="secondary" class="text-[10px]">{{ char.base }}mm</Badge>
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Stats -->
                        <Card class="mb-3">
                            <CardContent class="p-3">
                                <div class="grid grid-cols-3 gap-2 text-center">
                                    <div v-for="stat in statFields" :key="stat.key">
                                        <div class="text-[10px] font-medium uppercase tracking-wider text-muted-foreground">{{ stat.label }}</div>
                                        <div class="text-lg font-bold" :class="statClass(stat.key, (char as any)[stat.key])">
                                            <template v-if="(char as any)[stat.key] != null">
                                                {{ (char as any)[stat.key] }}{{ stat.suffix ?? '' }}
                                                <GameIcon
                                                    v-if="(stat as any).suitKey && (char as any)[(stat as any).suitKey]"
                                                    :type="(char as any)[(stat as any).suitKey]"
                                                    class-name="inline-block h-4 ml-0.5"
                                                />
                                            </template>
                                            <span v-else class="text-muted-foreground/30">-</span>
                                        </div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Combat Profile -->
                        <Card v-if="char.actions?.length" class="mb-3">
                            <CardContent class="p-3">
                                <div class="mb-1.5 text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">Combat Profile</div>
                                <div class="grid grid-cols-2 gap-x-3 gap-y-1 text-xs">
                                    <div class="flex justify-between">
                                        <span class="text-muted-foreground">Actions</span>
                                        <span class="font-bold">{{ char.actions.length }}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-muted-foreground">Signature</span>
                                        <span class="font-bold">{{ char.actions.filter((a: any) => a.is_signature).length }}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-muted-foreground">Triggers</span>
                                        <span class="font-bold">{{
                                            char.actions.reduce((sum: number, a: any) => sum + (a.triggers?.length ?? 0), 0)
                                        }}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-muted-foreground">Abilities</span>
                                        <span class="font-bold">{{ char.abilities?.length ?? 0 }}</span>
                                    </div>
                                    <div v-if="char.actions.some((a: any) => a.range)" class="flex justify-between">
                                        <span class="text-muted-foreground">Max Range</span>
                                        <span class="font-bold"
                                            >{{
                                                Math.max(...char.actions.filter((a: any) => a.range).map((a: any) => parseInt(a.range) || 0))
                                            }}"</span
                                        >
                                    </div>
                                    <div v-if="char.actions.some((a: any) => a.damage)" class="flex justify-between">
                                        <span class="text-muted-foreground">Max Damage</span>
                                        <span class="font-bold">{{
                                            char.actions
                                                .filter((a: any) => a.damage)
                                                .map((a: any) => a.damage)
                                                .sort()
                                                .pop()
                                        }}</span>
                                    </div>
                                </div>
                                <!-- Trigger suit breakdown -->
                                <div v-if="char.actions.some((a: any) => a.triggers?.length)" class="mt-2 border-t pt-2">
                                    <div class="mb-1 text-[10px] text-muted-foreground">Trigger Suits</div>
                                    <div class="flex flex-wrap gap-1.5">
                                        <template v-for="suit in ['ram', 'crow', 'tome', 'mask']" :key="suit">
                                            <span
                                                v-if="
                                                    char.actions.reduce(
                                                        (sum: number, a: any) =>
                                                            sum + (a.triggers?.filter((t: any) => t.suits?.toLowerCase().includes(suit)).length ?? 0),
                                                        0,
                                                    ) > 0
                                                "
                                                class="flex items-center gap-0.5 text-xs"
                                            >
                                                <GameIcon :type="suit" class-name="inline-block h-3.5" />
                                                <span class="font-bold">{{
                                                    char.actions.reduce(
                                                        (sum: number, a: any) =>
                                                            sum + (a.triggers?.filter((t: any) => t.suits?.toLowerCase().includes(suit)).length ?? 0),
                                                        0,
                                                    )
                                                }}</span>
                                            </span>
                                        </template>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Keywords -->
                        <Card v-if="char.keywords.length" class="mb-3">
                            <CardContent class="p-3">
                                <div class="mb-1.5 text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">Keywords</div>
                                <div class="flex flex-wrap gap-1">
                                    <Badge v-for="kw in char.keywords" :key="kw.slug" variant="secondary" class="text-[10px]">{{ kw.name }}</Badge>
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Characteristics -->
                        <Card v-if="char.characteristics.length" class="mb-3">
                            <CardContent class="p-3">
                                <div class="mb-1.5 text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">Characteristics</div>
                                <div class="flex flex-wrap gap-1">
                                    <Badge v-for="ch in char.characteristics" :key="ch" variant="outline" class="text-[10px] capitalize">{{
                                        ch
                                    }}</Badge>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
