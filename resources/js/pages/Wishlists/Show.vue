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
import { Head, Link, router } from '@inertiajs/vue3';
import { Check, Copy, Globe, KeyRound, List, Lock, Package, Pencil, Plus, Search, Trash2, Users, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface FactionInfo {
    value: string;
    label: string;
    color: string;
    logo: string;
}

interface CharacterItem {
    item_id: number;
    id: number;
    name: string;
    slug: string;
    faction: string | null;
    faction_label: string | null;
    faction_color: string | null;
    faction_logo: string | null;
    station: string | null;
    station_label: string | null;
    front_image: string | null;
    standard_miniature_id: number | null;
    notes: string | null;
}

interface MiniatureItem {
    item_id: number;
    id: number;
    name: string;
    slug: string;
    character_name: string | null;
    character_slug: string | null;
    front_image: string | null;
    notes: string | null;
}

interface PackageItem {
    item_id: number;
    id: number;
    name: string;
    slug: string;
    front_image: string | null;
    factions: FactionInfo[];
    notes: string | null;
}

interface KeywordOption {
    id: number;
    name: string;
    slug: string;
}

interface SearchOption {
    id: number;
    name: string;
    faction?: string;
}

const props = defineProps<{
    wishlist: {
        id: number;
        name: string;
        share_code: string;
        is_public: boolean;
    };
    items: {
        characters: CharacterItem[];
        miniatures: MiniatureItem[];
        packages: PackageItem[];
    };
    keywords: KeywordOption[];
    searchable: {
        characters: SearchOption[];
        miniatures: SearchOption[];
        packages: SearchOption[];
    };
    is_owner: boolean;
    owner_name?: string;
}>();

const processing = ref(false);

// ─── Name editing ───
const editingName = ref(false);
const editName = ref(props.wishlist.name);

function saveName() {
    if (!editName.value.trim()) return;
    router.put(route('wishlists.update', props.wishlist.id), { name: editName.value.trim() }, {
        preserveScroll: true,
        onSuccess: () => (editingName.value = false),
    });
}

// ─── Sharing ───
const copied = ref(false);
const copyShareLink = async () => {
    try {
        const url = route('wishlists.share', { shareCode: props.wishlist.share_code });
        await navigator.clipboard.writeText(url);
        copied.value = true;
        setTimeout(() => (copied.value = false), 2000);
    } catch {
        // Clipboard API may be blocked
    }
};

const togglePublic = () => {
    router.post(route('wishlists.toggle_public', props.wishlist.id), {}, { preserveScroll: true, preserveState: true });
};

// ─── Add items ───
const addType = ref<string>('character');
const addSearch = ref('');
const selectedKeyword = ref<string | null>(null);

function addItem(type: string, id: number) {
    processing.value = true;
    router.post(
        route('wishlists.items.add', props.wishlist.id),
        { type, id },
        { preserveScroll: true, onFinish: () => (processing.value = false) },
    );
}

function addKeyword() {
    if (!selectedKeyword.value) return;
    processing.value = true;
    router.post(
        route('wishlists.add_keyword', props.wishlist.id),
        { keyword_id: Number(selectedKeyword.value) },
        {
            preserveScroll: true,
            onFinish: () => {
                processing.value = false;
                selectedKeyword.value = null;
            },
        },
    );
}

function removeItem(itemId: number) {
    processing.value = true;
    router.delete(route('wishlists.items.remove', { wishlist: props.wishlist.id, wishlistItem: itemId }), {
        preserveScroll: true,
        onFinish: () => (processing.value = false),
    });
}

// ─── Search results for adding ───
const searchResults = computed(() => {
    if (addSearch.value.length < 2) return [];
    const s = addSearch.value.toLowerCase();
    const type = addType.value as 'character' | 'miniature' | 'package';
    const pluralType = `${type}s` as 'characters' | 'miniatures' | 'packages';
    const existingIds = new Set(props.items[pluralType].map((i: any) => i.id));
    return props.searchable[pluralType]
        .filter((o: SearchOption) => {
            if (existingIds.has(o.id)) return false;
            return o.name.toLowerCase().includes(s) || o.faction?.toLowerCase().includes(s);
        })
        .slice(0, 20);
});

// ─── Filtering ───
const filterText = ref('');

const filteredCharacters = computed(() => {
    if (!filterText.value) return props.items.characters;
    const s = filterText.value.toLowerCase();
    return props.items.characters.filter((c) => c.name.toLowerCase().includes(s) || c.faction_label?.toLowerCase().includes(s));
});

const filteredMiniatures = computed(() => {
    if (!filterText.value) return props.items.miniatures;
    const s = filterText.value.toLowerCase();
    return props.items.miniatures.filter((m) => m.name.toLowerCase().includes(s) || m.character_name?.toLowerCase().includes(s));
});

const filteredPackages = computed(() => {
    if (!filterText.value) return props.items.packages;
    const s = filterText.value.toLowerCase();
    return props.items.packages.filter((p) => p.name.toLowerCase().includes(s));
});

const totalItems = computed(() => props.items.characters.length + props.items.miniatures.length + props.items.packages.length);
const hasItems = computed(() => totalItems.value > 0);

interface AllItem {
    item_id: number;
    id: number;
    type: 'character' | 'miniature' | 'package';
    name: string;
    slug: string;
    faction?: string | null;
    faction_label?: string | null;
    faction_logo?: string | null;
    station_label?: string | null;
    standard_miniature_id?: number | null;
    character_name?: string | null;
    character_slug?: string | null;
    front_image?: string | null;
    factions?: Array<{ value: string; label: string; color: string; logo: string }>;
}

const filteredAll = computed(() => {
    const items: AllItem[] = [];

    for (const c of filteredCharacters.value) {
        items.push({
            item_id: c.item_id,
            id: c.id,
            type: 'character',
            name: c.name,
            slug: c.slug,
            faction: c.faction,
            faction_label: c.faction_label,
            faction_logo: c.faction_logo,
            station_label: c.station_label,
            standard_miniature_id: c.standard_miniature_id,
            front_image: c.front_image,
        });
    }
    for (const p of filteredPackages.value) {
        items.push({
            item_id: p.item_id,
            id: p.id,
            type: 'package',
            name: p.name,
            slug: p.slug,
            front_image: p.front_image,
            factions: p.factions,
        });
    }
    for (const m of filteredMiniatures.value) {
        items.push({
            item_id: m.item_id,
            id: m.id,
            type: 'miniature',
            name: m.name,
            slug: m.slug,
            character_name: m.character_name,
            character_slug: m.character_slug,
            front_image: m.front_image,
        });
    }

    return items;
});

const typeBadgeClass: Record<string, string> = {
    character: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
    package: 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200',
    miniature: 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200',
};

function itemHref(item: AllItem): string {
    if (item.type === 'character' && item.standard_miniature_id) {
        return route('characters.view', { character: item.slug, miniature: item.standard_miniature_id, slug: item.slug });
    }
    if (item.type === 'package') {
        return route('packages.view', item.slug);
    }
    // miniatures don't have a dedicated page — link to their character
    if (item.type === 'miniature' && item.character_slug) {
        return route('characters.view', { character: item.character_slug, miniature: item.id, slug: item.slug });
    }
    return '#';
}

function characterHref(char: CharacterItem): string {
    if (char.standard_miniature_id) {
        return route('characters.view', { character: char.slug, miniature: char.standard_miniature_id, slug: char.slug });
    }
    return '#';
}

function miniatureHref(mini: MiniatureItem): string {
    if (mini.character_slug) {
        return route('characters.view', { character: mini.character_slug, miniature: mini.id, slug: mini.slug });
    }
    return '#';
}
</script>

<template>
    <Head :title="`${wishlist.name} — Wishlist`" />

    <div class="relative pb-12">
        <div
            class="pointer-events-none absolute inset-x-0 top-0 h-64 opacity-[0.07] dark:opacity-[0.12]"
            :style="{ background: 'radial-gradient(ellipse at top, hsl(var(--primary)) 0%, transparent 70%)' }"
        />

        <PageBanner :title="wishlist.name">
            <template #subtitle>
                <div class="flex flex-wrap items-center gap-3 px-2">
                    <span class="text-sm text-muted-foreground">
                        {{ is_owner ? `${totalItems} items in this wishlist` : `Viewing ${owner_name}'s wishlist` }}
                    </span>
                    <div v-if="is_owner" class="flex items-center gap-1.5">
                        <Button variant="ghost" size="sm" class="h-7 gap-1 text-xs" @click="editingName = !editingName">
                            <Pencil class="size-3.5" />
                            Rename
                        </Button>
                        <Button variant="ghost" size="sm" class="h-7 gap-1 text-xs" @click="togglePublic">
                            <Globe v-if="wishlist.is_public" class="size-3.5" />
                            <Lock v-else class="size-3.5" />
                            {{ wishlist.is_public ? 'Public' : 'Private' }}
                        </Button>
                        <Button
                            variant="ghost"
                            size="sm"
                            class="h-7 gap-1 text-xs"
                            :disabled="!wishlist.is_public"
                            :title="!wishlist.is_public ? 'Make public to share' : 'Copy share link'"
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
            <!-- Rename inline -->
            <div v-if="editingName && is_owner" class="mb-4 flex items-center gap-2">
                <Input v-model="editName" class="max-w-sm" @keydown.enter="saveName" @keydown.escape="editingName = false" />
                <Button size="sm" @click="saveName" :disabled="!editName.trim()">Save</Button>
                <Button variant="ghost" size="sm" @click="editingName = false">Cancel</Button>
            </div>

            <!-- Add items section -->
            <Card v-if="is_owner" class="mb-6">
                <CardHeader class="pb-3">
                    <CardTitle class="text-sm font-semibold uppercase tracking-wider text-muted-foreground">Add Items</CardTitle>
                </CardHeader>
                <CardContent class="space-y-4">
                    <!-- Add by keyword -->
                    <div>
                        <p class="mb-2 text-sm font-medium">Add by Keyword</p>
                        <p class="mb-2 text-xs text-muted-foreground">
                            Adds all characters in the keyword and their associated packages.
                        </p>
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                            <Select v-model="selectedKeyword">
                                <SelectTrigger class="w-full sm:max-w-sm">
                                    <SelectValue placeholder="Select a keyword..." />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="kw in keywords" :key="kw.id" :value="String(kw.id)">
                                        {{ kw.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <Button class="shrink-0" :disabled="!selectedKeyword || processing" @click="addKeyword">
                                <KeyRound class="mr-2 size-4" />
                                Add
                            </Button>
                        </div>
                    </div>

                    <!-- Add individual items -->
                    <div>
                        <p class="mb-2 text-sm font-medium">Add Individual Item</p>
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                            <Select v-model="addType">
                                <SelectTrigger class="w-full sm:w-36 sm:shrink-0">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="character">Character</SelectItem>
                                    <SelectItem value="miniature">Miniature</SelectItem>
                                    <SelectItem value="package">Package</SelectItem>
                                </SelectContent>
                            </Select>
                            <Input v-model="addSearch" :placeholder="`Search ${addType}s...`" class="w-full sm:max-w-sm" />
                        </div>
                        <div v-if="addSearch.length >= 2" class="mt-2 max-h-48 overflow-y-auto rounded-md border">
                            <div v-if="searchResults.length === 0" class="p-3 text-center text-sm text-muted-foreground">No results found.</div>
                            <button
                                v-for="result in searchResults"
                                :key="result.id"
                                class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm transition-colors hover:bg-accent"
                                :disabled="processing"
                                @click="addItem(addType, result.id)"
                            >
                                <Plus class="size-3.5 shrink-0 text-muted-foreground" />
                                <span>{{ result.name }}</span>
                                <span v-if="result.faction" class="text-xs text-muted-foreground">{{ result.faction }}</span>
                            </button>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Items display -->
            <EmptyState v-if="!hasItems" title="This wishlist is empty" description="Add characters, packages, or miniatures to get started." />

            <template v-else>
                <!-- Filter -->
                <div class="mb-4">
                    <div class="relative max-w-sm">
                        <Search class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input v-model="filterText" placeholder="Filter items..." class="pl-9 pr-9" />
                        <button
                            v-if="filterText"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                            @click="filterText = ''"
                        >
                            <X class="size-4" />
                        </button>
                    </div>
                </div>

                <Tabs default-value="all">
                    <TabsList class="mb-4">
                        <TabsTrigger value="all">
                            <List class="mr-1.5 size-4" />
                            All ({{ totalItems }})
                        </TabsTrigger>
                        <TabsTrigger value="characters">
                            <Users class="mr-1.5 size-4" />
                            Characters ({{ items.characters.length }})
                        </TabsTrigger>
                        <TabsTrigger value="packages">
                            <Package class="mr-1.5 size-4" />
                            Packages ({{ items.packages.length }})
                        </TabsTrigger>
                        <TabsTrigger v-if="items.miniatures.length" value="miniatures">
                            Miniatures ({{ items.miniatures.length }})
                        </TabsTrigger>
                    </TabsList>

                    <!-- All -->
                    <TabsContent value="all">
                        <div v-if="filteredAll.length === 0" class="py-8 text-center text-sm text-muted-foreground">No items found.</div>
                        <div v-else class="space-y-1.5">
                            <div
                                v-for="item in filteredAll"
                                :key="`${item.type}-${item.item_id}`"
                                class="flex items-center gap-3 rounded-lg border bg-card px-4 py-2.5 transition-all duration-200 hover:shadow-md"
                            >
                                <img
                                    v-if="item.front_image"
                                    :src="`/storage/${item.front_image}`"
                                    :alt="item.name"
                                    class="size-10 shrink-0 rounded object-cover"
                                    loading="lazy"
                                />
                                <FactionLogo v-else-if="item.faction" :faction="item.faction" class-name="size-5 shrink-0" />
                                <Link :href="itemHref(item)" class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2">
                                        <span class="truncate text-sm font-medium transition-colors hover:text-primary">{{ item.name }}</span>
                                        <span class="shrink-0 rounded px-1.5 py-0.5 text-[10px] font-medium capitalize" :class="typeBadgeClass[item.type]">
                                            {{ item.type }}
                                        </span>
                                    </div>
                                    <div class="flex items-center gap-2 text-xs text-muted-foreground">
                                        <span v-if="item.station_label">{{ item.station_label }}</span>
                                        <span v-if="item.faction_label">{{ item.faction_label }}</span>
                                        <span v-if="item.character_name">{{ item.character_name }}</span>
                                        <template v-if="item.factions?.length">
                                            <span v-for="f in item.factions" :key="f.value">{{ f.label }}</span>
                                        </template>
                                    </div>
                                </Link>
                                <Button
                                    v-if="is_owner"
                                    variant="ghost"
                                    size="icon"
                                    class="size-7 shrink-0 text-destructive hover:text-destructive"
                                    :disabled="processing"
                                    @click="removeItem(item.item_id)"
                                >
                                    <Trash2 class="size-3" />
                                </Button>
                            </div>
                        </div>
                    </TabsContent>

                    <!-- Characters -->
                    <TabsContent value="characters">
                        <div v-if="filteredCharacters.length === 0" class="py-8 text-center text-sm text-muted-foreground">No characters found.</div>
                        <div v-else class="space-y-1.5">
                            <div
                                v-for="char in filteredCharacters"
                                :key="char.item_id"
                                class="flex items-center gap-3 rounded-lg border bg-card px-4 py-2.5 transition-all duration-200 hover:shadow-md"
                            >
                                <img
                                    v-if="char.front_image"
                                    :src="`/storage/${char.front_image}`"
                                    :alt="char.name"
                                    class="size-10 shrink-0 rounded object-cover"
                                    loading="lazy"
                                />
                                <FactionLogo v-else-if="char.faction" :faction="char.faction" class-name="size-5 shrink-0" />
                                <Link :href="characterHref(char)" class="min-w-0 flex-1">
                                    <div class="truncate text-sm font-medium transition-colors hover:text-primary">{{ char.name }}</div>
                                    <div class="flex items-center gap-2 text-xs text-muted-foreground">
                                        <span v-if="char.station_label">{{ char.station_label }}</span>
                                        <span v-if="char.faction_label">{{ char.faction_label }}</span>
                                    </div>
                                </Link>
                                <Button
                                    v-if="is_owner"
                                    variant="ghost"
                                    size="icon"
                                    class="size-7 shrink-0 text-destructive hover:text-destructive"
                                    :disabled="processing"
                                    @click="removeItem(char.item_id)"
                                >
                                    <Trash2 class="size-3" />
                                </Button>
                            </div>
                        </div>
                    </TabsContent>

                    <!-- Packages -->
                    <TabsContent value="packages">
                        <div v-if="filteredPackages.length === 0" class="py-8 text-center text-sm text-muted-foreground">No packages found.</div>
                        <div v-else class="space-y-1.5">
                            <div
                                v-for="pkg in filteredPackages"
                                :key="pkg.item_id"
                                class="flex items-center gap-3 rounded-lg border bg-card px-4 py-2.5 transition-all duration-200 hover:shadow-md"
                            >
                                <img
                                    v-if="pkg.front_image"
                                    :src="`/storage/${pkg.front_image}`"
                                    :alt="pkg.name"
                                    class="size-10 shrink-0 rounded object-cover"
                                    loading="lazy"
                                />
                                <Link :href="route('packages.view', pkg.slug)" class="min-w-0 flex-1">
                                    <div class="truncate text-sm font-medium transition-colors hover:text-primary">{{ pkg.name }}</div>
                                    <div v-if="pkg.factions?.length" class="flex flex-wrap gap-1">
                                        <Badge v-for="f in pkg.factions" :key="f.value" variant="secondary" class="gap-1 text-[10px]">
                                            <img :src="f.logo" :alt="f.label" class="size-3" />
                                            {{ f.label }}
                                        </Badge>
                                    </div>
                                </Link>
                                <Button
                                    v-if="is_owner"
                                    variant="ghost"
                                    size="icon"
                                    class="size-7 shrink-0 text-destructive hover:text-destructive"
                                    :disabled="processing"
                                    @click="removeItem(pkg.item_id)"
                                >
                                    <Trash2 class="size-3" />
                                </Button>
                            </div>
                        </div>
                    </TabsContent>

                    <!-- Miniatures -->
                    <TabsContent value="miniatures">
                        <div v-if="filteredMiniatures.length === 0" class="py-8 text-center text-sm text-muted-foreground">No miniatures found.</div>
                        <div v-else class="space-y-1.5">
                            <div
                                v-for="mini in filteredMiniatures"
                                :key="mini.item_id"
                                class="flex items-center gap-3 rounded-lg border bg-card px-4 py-2.5 transition-all duration-200 hover:shadow-md"
                            >
                                <img
                                    v-if="mini.front_image"
                                    :src="`/storage/${mini.front_image}`"
                                    :alt="mini.name"
                                    class="size-10 shrink-0 rounded object-cover"
                                    loading="lazy"
                                />
                                <Link :href="miniatureHref(mini)" class="min-w-0 flex-1">
                                    <div class="truncate text-sm font-medium transition-colors hover:text-primary">{{ mini.name }}</div>
                                    <div v-if="mini.character_name" class="text-xs text-muted-foreground">{{ mini.character_name }}</div>
                                </Link>
                                <Button
                                    v-if="is_owner"
                                    variant="ghost"
                                    size="icon"
                                    class="size-7 shrink-0 text-destructive hover:text-destructive"
                                    :disabled="processing"
                                    @click="removeItem(mini.item_id)"
                                >
                                    <Trash2 class="size-3" />
                                </Button>
                            </div>
                        </div>
                    </TabsContent>
                </Tabs>
            </template>
        </div>
    </div>
</template>
