<script setup lang="ts">
import EmptyState from '@/components/EmptyState.vue';
import FactionLogo from '@/components/FactionLogo.vue';
import PageBanner from '@/components/PageBanner.vue';
import { Badge } from '@/components/ui/badge';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { Input } from '@/components/ui/input';
import { Head, Link } from '@inertiajs/vue3';
import { ChevronDown, Search, X } from 'lucide-vue-next';
import { computed, reactive, ref } from 'vue';

interface BoxCharacter {
    display_name: string;
    slug: string;
    faction: string;
    faction_label: string;
    faction_color: string;
    quantity: number;
    standard_miniature: { id: number; slug: string } | null;
}

interface Box {
    name: string;
    slug: string;
    legacy_m3e_name: string | null;
    category: string | null;
    category_label: string | null;
    characters: BoxCharacter[];
}

const props = defineProps<{
    packages: Box[];
    factions: Record<string, { slug: string; name: string }>;
}>();

const search = ref('');
const activeFaction = ref<string | null>(null);
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
        if (term && !matchesSearch(box, term)) {
            return false;
        }
        return true;
    });
});

// A live search naturally wants its matches visible — auto-expand every box
// while searching/filtering, but leave manual toggles alone when idle so the
// page doesn't dump 100+ open sections on first load.
const isFiltering = computed(() => search.value.trim().length > 0 || activeFaction.value !== null);
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
</script>

<template>
    <Head title="Box Contents" />
    <div>
        <PageBanner title="Box Contents">
            <template #subtitle>
                <div class="my-auto px-2 py-0 text-xs text-muted-foreground md:py-2 md:text-sm md:text-foreground">
                    {{ filteredBoxes.length }} of {{ props.packages.length }} boxes — search by box or model name
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
            <EmptyState v-if="!filteredBoxes.length" description="Try a different search term or clear the faction filter." />

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
                                <span class="text-xs text-muted-foreground">{{ box.characters.length }} models</span>
                                <ChevronDown class="h-4 w-4 shrink-0 transition-transform" :class="{ 'rotate-180': isOpen(box.slug) }" />
                            </div>
                        </CollapsibleTrigger>
                        <CollapsibleContent class="border-t px-4 py-2">
                            <ul class="grid grid-cols-1 gap-x-4 gap-y-1.5 py-1.5 sm:grid-cols-2 lg:grid-cols-3">
                                <li v-for="character in box.characters" :key="character.slug" class="flex items-center gap-1.5 text-sm">
                                    <FactionLogo :faction="character.faction" class-name="h-4 w-4 shrink-0" />
                                    <Link
                                        :href="
                                            route('characters.view', {
                                                character: character.slug,
                                                miniature: character.standard_miniature?.id ?? 1,
                                                slug: character.standard_miniature?.slug ?? 'view',
                                            })
                                        "
                                        class="truncate text-primary hover:underline"
                                    >
                                        {{ character.display_name }}
                                    </Link>
                                    <span v-if="character.quantity > 1" class="shrink-0 text-xs text-muted-foreground">×{{ character.quantity }}</span>
                                </li>
                            </ul>
                        </CollapsibleContent>
                    </div>
                </Collapsible>
            </div>
        </div>
    </div>
</template>
