<script setup lang="ts">
import CharacterCardView from '@/components/CharacterCardView.vue';
import ClearableSelect from '@/components/ClearableSelect.vue';
import EmptyState from '@/components/EmptyState.vue';
import FactionLogo from '@/components/FactionLogo.vue';
import PageBanner from '@/components/PageBanner.vue';
import SearchableSelect from '@/components/SearchableSelect.vue';
import { Badge } from '@/components/ui/badge';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { Input } from '@/components/ui/input';
import { Head, Link } from '@inertiajs/vue3';
import { ChevronDown, Search, X } from 'lucide-vue-next';
import { computed, reactive, ref } from 'vue';

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
    keywords: string[];
    standard_miniature: BoxCharacterMiniature | null;
}

interface Box {
    name: string;
    slug: string;
    legacy_m3e_name: string | null;
    category: string | null;
    category_label: string | null;
    msrp: number | null;
    characters: BoxCharacter[];
}

const props = defineProps<{
    packages: Box[];
    factions: Record<string, { slug: string; name: string }>;
    categories: { name: string; value: string }[];
    keywords: { name: string; value: string }[];
}>();

const search = ref('');
const activeFaction = ref<string | null>(null);
const activeCategory = ref<string | null>(null);
const activeKeyword = ref<string | null>(null);
const openBoxes = reactive<Set<string>>(new Set());

const toggleFaction = (slug: string) => {
    activeFaction.value = activeFaction.value === slug ? null : slug;
};

const matchesSearch = (box: Box, term: string) => {
    if (box.name.toLowerCase().includes(term)) return true;
    if (box.legacy_m3e_name?.toLowerCase().includes(term)) return true;
    return box.characters.some((c) => c.display_name.toLowerCase().includes(term));
};

const filteredBoxes = computed(() => {
    const term = search.value.trim().toLowerCase();

    return props.packages.filter((box) => {
        if (activeFaction.value && !box.characters.some((c) => c.faction === activeFaction.value)) {
            return false;
        }
        if (activeCategory.value && box.category !== activeCategory.value) {
            return false;
        }
        if (activeKeyword.value && !box.characters.some((c) => c.keywords.includes(activeKeyword.value!))) {
            return false;
        }
        if (term && !matchesSearch(box, term)) {
            return false;
        }
        return true;
    });
});

const totalMsrpCents = computed(() => filteredBoxes.value.reduce((sum, box) => sum + (box.msrp ?? 0), 0));
const formatMsrp = (cents: number | null | undefined) => (cents ? `$${(cents / 100).toFixed(2)}` : null);

// A live search/filter naturally wants its matches visible — auto-expand
// every box while searching/filtering, but leave manual toggles alone when
// idle so the page doesn't dump 100+ open sections on first load.
const isFiltering = computed(
    () => search.value.trim().length > 0 || activeFaction.value !== null || activeCategory.value !== null || activeKeyword.value !== null,
);
const isOpen = (slug: string) => isFiltering.value || openBoxes.has(slug);
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
    activeFaction.value = null;
    activeCategory.value = null;
    activeKeyword.value = null;
};

const hasActiveFilters = computed(() => isFiltering.value);
</script>

<template>
    <Head title="Box Contents" />
    <div>
        <PageBanner title="Box Contents">
            <template #subtitle>
                <div class="my-auto flex flex-wrap items-center gap-x-3 gap-y-1 px-2 py-0 text-xs text-muted-foreground md:py-2 md:text-sm md:text-foreground">
                    <span>{{ filteredBoxes.length }} of {{ props.packages.length }} boxes</span>
                    <span v-if="totalMsrpCents > 0" class="font-medium">· {{ formatMsrp(totalMsrpCents) }} total MSRP</span>
                </div>
            </template>
        </PageBanner>

        <div class="container mx-auto mb-3 sm:px-4">
            <div class="relative">
                <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                <Input v-model="search" type="text" placeholder="Search box or model name..." class="border-2 border-primary pl-10 pr-10" />
                <button v-if="search" class="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground" @click="clearSearch">
                    <X class="h-4 w-4" />
                </button>
            </div>
        </div>

        <div class="container mx-auto mb-3 sm:px-4">
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
                <button v-if="hasActiveFilters" class="text-xs text-muted-foreground underline hover:text-foreground" @click="clearAllFilters">
                    Clear filters
                </button>
            </div>
        </div>

        <div class="container mx-auto mb-4 sm:px-4">
            <div class="flex flex-wrap items-center gap-2">
                <button
                    v-for="(faction, key) in factions"
                    :key="key"
                    @click="toggleFaction(faction.slug)"
                    class="rounded-full p-1 transition-all"
                    :class="activeFaction === faction.slug ? 'ring-2 ring-primary' : 'opacity-50 hover:opacity-100'"
                >
                    <FactionLogo :faction="faction.slug" class-name="h-6 w-6" />
                </button>
            </div>
        </div>

        <div class="container mx-auto sm:px-4">
            <EmptyState v-if="!filteredBoxes.length" description="Try a different search term or clear the active filters." />

            <div v-else class="space-y-2">
                <Collapsible v-for="box in filteredBoxes" :key="box.slug" :open="isOpen(box.slug)" @update:open="toggleBox(box.slug)">
                    <div class="rounded-lg border">
                        <CollapsibleTrigger class="flex w-full items-center justify-between gap-3 px-4 py-3 text-left hover:bg-muted/50">
                            <div class="flex min-w-0 items-center gap-2">
                                <Link
                                    :href="route('packages.view', { package: box.slug })"
                                    class="truncate font-semibold text-primary hover:underline"
                                    @click.stop
                                >
                                    {{ box.name }}
                                </Link>
                                <Badge v-if="box.category_label" variant="outline" class="shrink-0 text-xs">{{ box.category_label }}</Badge>
                                <span v-if="box.legacy_m3e_name" class="hidden shrink-0 text-xs text-muted-foreground sm:inline">
                                    (M3E: {{ box.legacy_m3e_name }})
                                </span>
                            </div>
                            <div class="flex shrink-0 items-center gap-2">
                                <span v-if="formatMsrp(box.msrp)" class="text-xs font-medium text-muted-foreground">{{ formatMsrp(box.msrp) }}</span>
                                <span class="text-xs text-muted-foreground">{{ box.characters.length }} models</span>
                                <ChevronDown class="h-4 w-4 shrink-0 transition-transform" :class="{ 'rotate-180': isOpen(box.slug) }" />
                            </div>
                        </CollapsibleTrigger>
                        <CollapsibleContent class="border-t px-4 py-4">
                            <div class="grid grid-cols-2 gap-4 md:grid-cols-4 lg:grid-cols-5">
                                <div v-for="character in box.characters" :key="character.slug" class="relative">
                                    <Badge v-if="character.quantity > 1" class="absolute right-1 top-1 z-10 text-xs">
                                        ×{{ character.quantity }}
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
