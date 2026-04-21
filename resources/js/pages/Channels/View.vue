<script setup lang="ts">
import ClearableSelect from '@/components/ClearableSelect.vue';
import EmptyState from '@/components/EmptyState.vue';
import ListSearchBar from '@/components/ListSearchBar.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import { useListFiltering } from '@/composables/useListFiltering';
import { formatDateOnly } from '@/lib/utils';
import { type SharedData } from '@/types';
import { Head, router, usePage } from '@inertiajs/vue3';
import { ExternalLink } from 'lucide-vue-next';
import { computed } from 'vue';

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

interface Channel {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    image_url: string | null;
    users: Array<{ id: number; name: string }>;
}

const page = usePage<SharedData>();

const props = defineProps<{
    channel: Channel;
    transmissions: Transmission[];
    transmission_types: SelectOption[];
    content_types: SelectOption[];
    factions: SelectOption[];
    keywords: SelectOption[];
    characters: SelectOption[];
}>();

const factionInfo = computed(() => page.props.faction_info);
const channelIds = computed(() => page.props.auth.channel_ids ?? []);
const canManage = computed(() => {
    return channelIds.value.includes(props.channel.id) || (page.props.auth.permissions ?? []).includes('edit_channel');
});

const filterKeys = ['transmission_type', 'content_type', 'faction', 'keyword', 'character'] as const;

const { filterParams, activeFilterCount, filter, clear, handleNameKeydown, clearNameSearch, handleViewChange } = useListFiltering(
    {
        transmission_type: null as string | null,
        content_type: null as string | null,
        faction: null as string | null,
        keyword: null as string | null,
        character: null as string | null,
        name_search: null as string | null,
        page_view: null as string | null,
    },
    {
        routeName: 'channels.view',
        routeParams: props.channel.slug,
        filterKeys,
        only: ['transmissions'],
    },
);

const filterByTag = (key: string, value: string) => {
    (filterParams.value as Record<string, string | null>)[key] = value;
    filter();
};

const getFactionLabel = (slug: string) => factionInfo.value[slug]?.name ?? slug;
const getFactionLogo = (slug: string) => factionInfo.value[slug]?.logo ?? '';
const getFactionColor = (slug: string) => factionInfo.value[slug]?.color ?? '';
const formatContentType = (value: string) => value.replace(/_/g, ' ');
const formatDate = (dateStr: string) => formatDateOnly(dateStr, { year: 'numeric', month: 'short', day: 'numeric' });
</script>

<template>
    <Head :title="channel.name" />

    <div class="relative">
        <div
            class="pointer-events-none absolute inset-x-0 top-0 h-64 opacity-[0.07] dark:opacity-[0.12]"
            :style="{ background: 'radial-gradient(ellipse at top, hsl(var(--primary)) 0%, transparent 70%)' }"
        />

        <div class="container mx-auto flex items-center gap-6 pb-6 pt-10 sm:px-4">
            <img v-if="channel.image_url" :src="channel.image_url" :alt="channel.name" class="h-24 w-24 shrink-0 rounded-lg object-cover shadow-md" />
            <div>
                <h1 class="text-3xl font-bold tracking-tight">{{ channel.name }}</h1>
                <p v-if="channel.description" class="mt-1 max-w-2xl text-sm text-muted-foreground">{{ channel.description }}</p>
                <div class="mt-2 text-xs text-muted-foreground">
                    {{ transmissions.length }} {{ transmissions.length === 1 ? 'transmission' : 'transmissions' }}
                </div>
            </div>
        </div>

        <ListSearchBar
            v-model:name-search="filterParams.name_search"
            :page-view="filterParams.page_view"
            @update:page-view="handleViewChange"
            :active-filter-count="activeFilterCount"
            placeholder="Search transmissions by name..."
            has-filters
            @name-keydown="handleNameKeydown"
            @clear-search="clearNameSearch"
            @filter="filter"
            @clear="clear"
        >
            <template #filters>
                <div class="grid gap-4">
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
            </template>
        </ListSearchBar>

        <div class="container mx-auto sm:px-4">
            <div class="flex gap-6">
                <!-- Desktop sidebar filters -->
                <aside class="hidden w-72 shrink-0 md:block">
                    <div class="space-y-4 pr-2">
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
                        <Button v-if="canManage" class="w-full" @click="router.get(route('transmissions.create', channel.slug))">
                            Add Transmission
                        </Button>
                    </div>
                </aside>

                <!-- Main content -->
                <div class="min-w-0 flex-1">
                    <Button v-if="canManage" size="sm" class="mb-4 md:hidden" @click="router.get(route('transmissions.create', channel.slug))">
                        Add Transmission
                    </Button>
                    <div v-if="transmissions.length" class="space-y-4">
                        <Card v-for="transmission in transmissions" :key="transmission.id">
                            <CardHeader class="pb-2">
                                <div>
                                    <a
                                        :href="transmission.url"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="group/title flex items-center gap-1.5"
                                    >
                                        <h3 class="text-lg font-bold leading-tight group-hover/title:underline">{{ transmission.title }}</h3>
                                        <ExternalLink class="h-3.5 w-3.5 shrink-0 text-muted-foreground" />
                                    </a>
                                    <div v-if="transmission.release_date" class="mt-1 text-xs text-muted-foreground">
                                        {{ formatDate(transmission.release_date) }}
                                    </div>
                                </div>
                            </CardHeader>
                            <CardContent>
                                <p v-if="transmission.description" class="mb-3 text-sm text-muted-foreground">{{ transmission.description }}</p>
                                <div class="mb-2 flex items-center gap-2">
                                    <Badge
                                        variant="secondary"
                                        class="cursor-pointer capitalize hover:bg-secondary/80"
                                        @click="filterByTag('transmission_type', transmission.transmission_type)"
                                        >{{ transmission.transmission_type }}</Badge
                                    >
                                    <Badge
                                        variant="outline"
                                        class="cursor-pointer capitalize hover:bg-accent"
                                        @click="filterByTag('content_type', transmission.content_type)"
                                        >{{ formatContentType(transmission.content_type) }}</Badge
                                    >
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
                                            backgroundColor: character.faction_color ? `hsl(var(--${character.faction_color}))` : undefined,
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
                                <div v-if="canManage" class="mt-3 flex gap-2">
                                    <Button
                                        size="sm"
                                        variant="ghost"
                                        @click="router.get(route('transmissions.edit', { channel: channel.slug, transmission: transmission.slug }))"
                                    >
                                        Edit
                                    </Button>
                                    <Button
                                        size="sm"
                                        variant="ghost"
                                        class="text-destructive"
                                        @click="
                                            router.post(route('transmissions.delete', { channel: channel.slug, transmission: transmission.slug }))
                                        "
                                    >
                                        Delete
                                    </Button>
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    <EmptyState v-else title="No transmissions found" description="Try adjusting your filters or check back later for new content." />
                </div>
            </div>
        </div>
    </div>
</template>
