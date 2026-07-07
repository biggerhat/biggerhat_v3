<script setup lang="ts">
import ClearableSelect from '@/components/ClearableSelect.vue';
import EmptyState from '@/components/EmptyState.vue';
import InertiaPagination from '@/components/InertiaPagination.vue';
import PageBanner from '@/components/PageBanner.vue';
import PostCard, { type Post } from '@/components/PostCard.vue';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useStaggeredEntry } from '@/composables/useStaggeredEntry';
import { type SharedData } from '@/types';
import { Head, router, usePage } from '@inertiajs/vue3';
import { Newspaper } from 'lucide-vue-next';
import { computed } from 'vue';

type BlogPost = Post;

interface BlogCategory {
    id: number;
    name: string;
    slug: string;
}

interface Paginator {
    data: BlogPost[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    first_page_url: string;
    last_page_url: string;
    next_page_url: string | null;
    prev_page_url: string | null;
    path: string;
    from: number | null;
    to: number | null;
}

const page = usePage<SharedData>();

const props = defineProps<{
    posts: Paginator;
    categories: BlogCategory[];
    authors: { name: string; value: string }[];
    tagged_characters: { name: string; value: string }[];
    tagged_keywords: { name: string; value: string }[];
    active_category: string | null;
    active_faction: string | null;
    active_author: string | null;
    active_character: string | null;
    active_keyword: string | null;
}>();

const factionInfo = computed(() => page.props.faction_info);

const hasAnyFilter = computed(
    () => !!props.active_category || !!props.active_faction || !!props.active_author || !!props.active_character || !!props.active_keyword,
);

const isFirstPageNoFilters = computed(() => props.posts.current_page === 1 && !hasAnyFilter.value);

const featuredPost = computed(() => (isFirstPageNoFilters.value && props.posts.data.length >= 2 ? props.posts.data[0] : null));

const gridPosts = computed(() => (featuredPost.value ? props.posts.data.slice(1) : props.posts.data));

const gridPostCount = computed(() => gridPosts.value.length);
const { delays } = useStaggeredEntry(gridPostCount);

const activeCategoryTab = computed(() => props.active_category ?? 'all');
const selectedFaction = computed(() => props.active_faction ?? 'all');

const onlyKeys = ['posts', 'active_category', 'active_faction', 'active_author', 'active_character', 'active_keyword'];

const buildParams = (overrides: Record<string, string | null> = {}): Record<string, string> => {
    const base: Record<string, string | null> = {
        category: props.active_category,
        faction: props.active_faction,
        author: props.active_author,
        character: props.active_character,
        keyword: props.active_keyword,
        ...overrides,
    };
    const params: Record<string, string> = {};
    for (const [k, v] of Object.entries(base)) {
        if (v && v !== 'all') params[k] = v;
    }
    return params;
};

const applyFilter = (overrides: Record<string, string | null>) => {
    router.get(route('blog.index'), buildParams(overrides), { only: onlyKeys, preserveState: true, preserveScroll: true, replace: true });
};

const filterByCategory = (val: string) => applyFilter({ category: val === 'all' ? null : val });
const filterByFaction = (val: string) => applyFilter({ faction: val === 'all' ? null : val });
const filterByAuthor = (val: string | null) => applyFilter({ author: val });
const filterByCharacter = (val: string | null) => applyFilter({ character: val });
const filterByKeyword = (val: string | null) => applyFilter({ keyword: val });
</script>

<template>
    <Head title="Articles" />

    <div class="relative">
        <div
            class="pointer-events-none absolute inset-x-0 top-0 h-64 opacity-[0.07] dark:opacity-[0.12]"
            :style="{ background: 'radial-gradient(ellipse at top, hsl(var(--primary)) 0%, transparent 70%)' }"
        />

        <PageBanner title="Articles" class="mb-2">
            <template #subtitle>
                <div class="my-auto px-2 py-0 text-xs text-muted-foreground md:py-2 md:text-sm md:text-foreground">
                    {{ posts.total }} {{ posts.total === 1 ? 'article' : 'articles' }} found
                </div>
            </template>
        </PageBanner>

        <div class="container mx-auto sm:px-4">
            <!-- Category tabs -->
            <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <Tabs :model-value="activeCategoryTab" @update:model-value="filterByCategory($event as string)">
                    <TabsList class="flex-wrap">
                        <TabsTrigger value="all">All</TabsTrigger>
                        <TabsTrigger v-for="category in categories" :key="category.id" :value="category.slug">
                            {{ category.name }}
                        </TabsTrigger>
                    </TabsList>
                </Tabs>

                <Select :model-value="selectedFaction" @update:model-value="filterByFaction($event as string)">
                    <SelectTrigger class="w-full sm:w-44">
                        <SelectValue placeholder="All Factions" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">All Factions</SelectItem>
                        <SelectItem v-for="(faction, key) in factionInfo" :key="key" :value="faction.slug">
                            <span class="flex items-center gap-2">
                                <img :src="faction.logo" class="h-4 w-4" :alt="faction.name" loading="lazy" decoding="async" />
                                {{ faction.name }}
                            </span>
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <!-- Additional filters -->
            <div v-if="authors.length || tagged_characters.length || tagged_keywords.length" class="mb-6 flex flex-wrap gap-2">
                <ClearableSelect
                    v-if="authors.length"
                    :model-value="active_author"
                    placeholder="Author"
                    :options="authors"
                    class="w-full sm:w-40"
                    @update:model-value="filterByAuthor"
                />
                <ClearableSelect
                    v-if="tagged_characters.length"
                    :model-value="active_character"
                    placeholder="Character"
                    :options="tagged_characters"
                    class="w-full sm:w-48"
                    @update:model-value="filterByCharacter"
                />
                <ClearableSelect
                    v-if="tagged_keywords.length"
                    :model-value="active_keyword"
                    placeholder="Keyword"
                    :options="tagged_keywords"
                    class="w-full sm:w-40"
                    @update:model-value="filterByKeyword"
                />
            </div>

            <template v-if="posts.data.length">
                <!-- Featured post (first page, no filters) -->
                <PostCard
                    v-if="featuredPost"
                    variant="featured"
                    :post="featuredPost"
                    :href="route('blog.view', featuredPost.slug)"
                    :icon="Newspaper"
                    class="animate-fade-in-up mb-6 opacity-0"
                />

                <!-- Grid -->
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <PostCard
                        v-for="(post, index) in gridPosts"
                        :key="post.id"
                        variant="grid"
                        :post="post"
                        :href="route('blog.view', post.slug)"
                        :icon="Newspaper"
                        class="animate-fade-in-up opacity-0"
                        :style="delays[index]"
                    />
                </div>
            </template>

            <EmptyState v-else title="No articles found" description="Try adjusting your filters or check back later for new content." />

            <InertiaPagination :paginator="posts" :only="['posts']" />
        </div>
    </div>
</template>
