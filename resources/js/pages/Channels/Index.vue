<script setup lang="ts">
import ClearableSelect from '@/components/ClearableSelect.vue';
import EmptyState from '@/components/EmptyState.vue';
import FilterPanel from '@/components/FilterPanel.vue';
import PageBanner from '@/components/PageBanner.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import { type SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ExternalLink } from 'lucide-vue-next';
import { cleanObject } from '@/composables/CleanObject';
import { computed, onMounted, ref } from 'vue';

interface SelectOption {
    name: string;
    value: string;
}

interface Transmission {
    id: number;
    title: string;
    slug: string;
    description: string | null;
    url: string;
    transmission_type: string;
    content_type: string;
    factions: string[] | null;
    release_date: string | null;
    channel: { id: number; name: string; slug: string; image_url: string | null };
    characters: Array<{
        id: number;
        display_name: string;
        slug: string;
        faction: string | null;
        faction_color: string | null;
        standard_miniatures: Array<{ id: number; slug: string }>;
    }>;
    keywords: Array<{ id: number; name: string; slug: string }>;
}

const page = usePage<SharedData>();

defineProps<{
    transmissions: Transmission[];
    channels: SelectOption[];
    transmission_types: SelectOption[];
    content_types: SelectOption[];
    factions: SelectOption[];
    keywords: SelectOption[];
    characters: SelectOption[];
}>();

const factionInfo = computed(() => page.props.faction_info);

const filterParams = ref({
    channel: null as string | null,
    transmission_type: null as string | null,
    content_type: null as string | null,
    faction: null as string | null,
    keyword: null as string | null,
    character: null as string | null,
});

const filterKeys = ['channel', 'transmission_type', 'content_type', 'faction', 'keyword', 'character'] as const;

const activeFilterCount = computed(() => {
    return filterKeys.filter((key) => filterParams.value[key] != null && filterParams.value[key] !== '').length;
});

const filter = () => {
    const params: Record<string, string | null> = { ...filterParams.value };
    router.get(route('channels.index'), cleanObject(params), {
        only: ['transmissions'],
        replace: true,
        preserveState: true,
    });
};

const clear = () => {
    for (const key of filterKeys) {
        filterParams.value[key] = null;
    }
    filter();
};

const filterByTag = (key: string, value: string) => {
    filterParams.value[key] = value;
    filter();
};

onMounted(() => {
    const urlParams = new URLSearchParams(window.location.search);
    filterParams.value.channel = urlParams.get('channel');
    filterParams.value.transmission_type = urlParams.get('transmission_type');
    filterParams.value.content_type = urlParams.get('content_type');
    filterParams.value.faction = urlParams.get('faction');
    filterParams.value.keyword = urlParams.get('keyword');
    filterParams.value.character = urlParams.get('character');
});

const getFactionLabel = (slug: string) => factionInfo.value[slug]?.name ?? slug;
const getFactionLogo = (slug: string) => factionInfo.value[slug]?.logo ?? '';
const getFactionColor = (slug: string) => factionInfo.value[slug]?.color ?? '';
const formatContentType = (value: string) => value.replace(/_/g, ' ');
const formatDate = (dateStr: string) => {
    return new Date(dateStr).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
};
</script>

<template>
    <Head title="Across the Aethervox" />

    <div class="relative">
        <div
            class="pointer-events-none absolute inset-x-0 top-0 h-64 opacity-[0.07] dark:opacity-[0.12]"
            :style="{ background: 'radial-gradient(ellipse at top, hsl(var(--primary)) 0%, transparent 70%)' }"
        />

        <PageBanner title="Across the Aethervox" class="mb-2">
            <template #subtitle>
                <div class="my-auto px-2 py-0 text-xs text-muted-foreground md:py-2 md:text-sm md:text-foreground">
                    {{ transmissions.length }} {{ transmissions.length === 1 ? 'transmission' : 'transmissions' }} found
                </div>
            </template>
        </PageBanner>

        <!-- Mobile filter trigger -->
        <div class="container mx-auto mb-2 flex items-center justify-end px-4 md:hidden">
            <FilterPanel :filter-count="activeFilterCount" @filter="filter" @clear="clear">
                <div class="grid gap-4">
                    <div class="space-y-2">
                        <label class="text-sm font-medium">Channel</label>
                        <ClearableSelect
                            v-model="filterParams.channel"
                            placeholder="All Channels"
                            :options="channels"
                            trigger-class="border-2 border-primary rounded"
                        />
                    </div>
                    <div class="space-y-2">
                        <label class="text-sm font-medium">Platform</label>
                        <ClearableSelect
                            v-model="filterParams.transmission_type"
                            placeholder="All Platforms"
                            :options="transmission_types"
                            trigger-class="border-2 border-primary rounded"
                        />
                    </div>
                    <div class="space-y-2">
                        <label class="text-sm font-medium">Content Type</label>
                        <ClearableSelect
                            v-model="filterParams.content_type"
                            placeholder="All Content"
                            :options="content_types"
                            trigger-class="border-2 border-primary rounded"
                        />
                    </div>
                    <div class="space-y-2">
                        <label class="text-sm font-medium">Faction</label>
                        <ClearableSelect
                            v-model="filterParams.faction"
                            placeholder="All Factions"
                            :options="factions"
                            trigger-class="border-2 border-primary rounded"
                        />
                    </div>
                    <div class="space-y-2">
                        <label class="text-sm font-medium">Keyword</label>
                        <ClearableSelect
                            v-model="filterParams.keyword"
                            placeholder="All Keywords"
                            :options="keywords"
                            trigger-class="border-2 border-primary rounded"
                        />
                    </div>
                    <div class="space-y-2">
                        <label class="text-sm font-medium">Character</label>
                        <ClearableSelect
                            v-model="filterParams.character"
                            placeholder="All Characters"
                            :options="characters"
                            trigger-class="border-2 border-primary rounded"
                        />
                    </div>
                </div>
            </FilterPanel>
        </div>

        <div class="container mx-auto px-4">
            <div class="flex gap-6">
                <!-- Desktop sidebar filters -->
                <aside class="hidden w-72 shrink-0 md:block">
                    <div class="space-y-4 pr-2">
                        <div class="space-y-1">
                            <label class="text-xs font-medium text-muted-foreground">Channel</label>
                            <ClearableSelect v-model="filterParams.channel" placeholder="All Channels" :options="channels" />
                        </div>
                        <div class="space-y-1">
                            <label class="text-xs font-medium text-muted-foreground">Platform</label>
                            <ClearableSelect v-model="filterParams.transmission_type" placeholder="All Platforms" :options="transmission_types" />
                        </div>
                        <div class="space-y-1">
                            <label class="text-xs font-medium text-muted-foreground">Content Type</label>
                            <ClearableSelect v-model="filterParams.content_type" placeholder="All Content" :options="content_types" />
                        </div>
                        <div class="space-y-1">
                            <label class="text-xs font-medium text-muted-foreground">Faction</label>
                            <ClearableSelect v-model="filterParams.faction" placeholder="All Factions" :options="factions" />
                        </div>
                        <div class="space-y-1">
                            <label class="text-xs font-medium text-muted-foreground">Keyword</label>
                            <ClearableSelect v-model="filterParams.keyword" placeholder="All Keywords" :options="keywords" />
                        </div>
                        <div class="space-y-1">
                            <label class="text-xs font-medium text-muted-foreground">Character</label>
                            <ClearableSelect v-model="filterParams.character" placeholder="All Characters" :options="characters" />
                        </div>
                        <div class="flex gap-2 pt-2">
                            <Button size="sm" @click="filter">Search</Button>
                            <Button size="sm" variant="outline" @click="clear">Clear</Button>
                        </div>
                    </div>
                </aside>

                <!-- Main content -->
                <div class="min-w-0 flex-1">
                    <div v-if="transmissions.length" class="space-y-4">
                        <Card v-for="transmission in transmissions" :key="transmission.id" class="flex flex-col sm:flex-row">
                            <div class="min-w-0 flex-1">
                                <CardHeader class="pb-2">
                                    <a
                                        :href="transmission.url"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="group/title flex items-center gap-1.5"
                                    >
                                        <h3 class="text-lg font-bold leading-tight group-hover/title:underline">{{ transmission.title }}</h3>
                                        <ExternalLink class="h-3.5 w-3.5 shrink-0 text-muted-foreground" />
                                    </a>
                                    <div class="mt-1 flex items-center gap-2 text-xs text-muted-foreground">
                                        <span v-if="transmission.release_date">{{ formatDate(transmission.release_date) }}</span>
                                        <Link
                                            v-if="transmission.channel"
                                            :href="route('channels.view', transmission.channel.slug)"
                                            class="font-medium hover:text-foreground sm:hidden"
                                        >
                                            {{ transmission.channel.name }}
                                        </Link>
                                    </div>
                                </CardHeader>
                                <CardContent>
                                    <p v-if="transmission.description" class="mb-3 text-sm text-muted-foreground">{{ transmission.description }}</p>
                                    <div class="mb-2 flex items-center gap-2">
                                        <Badge variant="secondary" class="cursor-pointer capitalize hover:bg-secondary/80" @click="filterByTag('transmission_type', transmission.transmission_type)">{{ transmission.transmission_type }}</Badge>
                                        <Badge variant="outline" class="cursor-pointer capitalize hover:bg-accent" @click="filterByTag('content_type', transmission.content_type)">{{ formatContentType(transmission.content_type) }}</Badge>
                                    </div>
                                    <div class="flex flex-wrap gap-1.5">
                                        <Badge
                                            v-for="faction in transmission.factions ?? []"
                                            :key="faction"
                                            class="flex cursor-pointer items-center gap-1 border-transparent text-xs text-white hover:opacity-80"
                                            :style="{
                                                backgroundColor: `hsl(var(--${getFactionColor(faction)}))`,
                                            }"
                                            @click="router.visit(route('factions.view', faction))"
                                        >
                                            <img :src="getFactionLogo(faction)" class="h-3 w-3" :alt="getFactionLabel(faction)" />
                                            {{ getFactionLabel(faction) }}
                                        </Badge>
                                        <Badge
                                            v-for="keyword in transmission.keywords"
                                            :key="'k-' + keyword.id"
                                            variant="outline"
                                            class="cursor-pointer text-xs hover:bg-accent"
                                            @click="router.visit(route('keywords.view', keyword.slug))"
                                        >
                                            {{ keyword.name }}
                                        </Badge>
                                        <Badge
                                            v-for="character in transmission.characters"
                                            :key="'c-' + character.id"
                                            class="cursor-pointer border-transparent text-xs text-white hover:opacity-80"
                                            :style="{
                                                backgroundColor: character.faction_color
                                                    ? `hsl(var(--${character.faction_color}))`
                                                    : undefined,
                                            }"
                                            @click="
                                                router.visit(
                                                    route('characters.view', {
                                                        character: character.slug,
                                                        miniature: character.standard_miniatures[0]?.id ?? 1,
                                                        slug: character.standard_miniatures[0]?.slug ?? 'view',
                                                    }),
                                                )
                                            "
                                        >
                                            {{ character.display_name }}
                                        </Badge>
                                    </div>
                                </CardContent>
                            </div>
                            <Link
                                v-if="transmission.channel"
                                :href="route('channels.view', transmission.channel.slug)"
                                class="hidden w-28 shrink-0 flex-col items-center justify-center gap-1 border-l px-3 text-center text-xs font-medium text-muted-foreground hover:bg-accent hover:text-foreground sm:flex"
                            >
                                <img
                                    v-if="transmission.channel.image_url"
                                    :src="transmission.channel.image_url"
                                    :alt="transmission.channel.name"
                                    class="h-10 w-10 rounded object-cover"
                                />
                                {{ transmission.channel.name }}
                            </Link>
                        </Card>
                    </div>

                    <EmptyState v-else title="No transmissions found" description="Try adjusting your filters or check back later for new content." />
                </div>
            </div>
        </div>
    </div>
</template>
