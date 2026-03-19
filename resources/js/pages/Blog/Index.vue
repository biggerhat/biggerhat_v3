<script setup lang="ts">
import ClearableSelect from '@/components/ClearableSelect.vue';
import EmptyState from '@/components/EmptyState.vue';
import InertiaPagination from '@/components/InertiaPagination.vue';
import PageBanner from '@/components/PageBanner.vue';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { getInitials } from '@/composables/useInitials';
import { useStaggeredEntry } from '@/composables/useStaggeredEntry';
import { type SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Newspaper } from 'lucide-vue-next';
import { computed } from 'vue';

interface BlogPost {
    id: number;
    title: string;
    slug: string;
    excerpt: string | null;
    featured_image: string | null;
    status: string;
    published_at: string;
    author: { name: string };
    category: { name: string; slug: string } | null;
}

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

const formatDate = (dateStr: string) => {
    return new Date(dateStr).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
};
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
                <Link v-if="featuredPost" :href="route('blog.view', featuredPost.slug)" class="animate-fade-in-up group mb-6 block opacity-0">
                    <Card class="overflow-hidden transition-all duration-300 hover:-translate-y-0.5 hover:shadow-xl md:grid md:grid-cols-5">
                        <div class="aspect-video overflow-hidden md:col-span-3 md:aspect-auto md:h-full">
                            <img
                                v-if="featuredPost.featured_image"
                                :src="`/storage/${featuredPost.featured_image}`"
                                :alt="featuredPost.title"
                                loading="lazy"
                                decoding="async"
                                class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-[1.02]"
                            />
                            <div v-else class="flex h-full min-h-48 items-center justify-center bg-muted">
                                <Newspaper class="size-12 text-muted-foreground/20" />
                            </div>
                        </div>
                        <div class="flex flex-col justify-center p-5 md:col-span-2 md:p-6">
                            <div class="mb-2 flex flex-wrap items-center gap-2">
                                <Badge v-if="featuredPost.category" variant="secondary" class="text-[10px]">{{ featuredPost.category.name }}</Badge>
                                <span class="text-xs text-muted-foreground">{{ formatDate(featuredPost.published_at) }}</span>
                            </div>
                            <h2 class="mb-2 line-clamp-3 text-xl font-bold leading-tight group-hover:text-primary md:text-2xl">
                                {{ featuredPost.title }}
                            </h2>
                            <p v-if="featuredPost.excerpt" class="mb-3 line-clamp-2 text-sm text-muted-foreground">{{ featuredPost.excerpt }}</p>
                            <div class="flex items-center gap-2 text-xs text-muted-foreground">
                                <Avatar size="sm" class="!h-5 !w-5 !text-[9px]">
                                    <AvatarFallback>{{ getInitials(featuredPost.author.name) }}</AvatarFallback>
                                </Avatar>
                                <span class="font-medium text-foreground">{{ featuredPost.author.name }}</span>
                            </div>
                        </div>
                    </Card>
                </Link>

                <!-- Grid -->
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <Link
                        v-for="(post, index) in gridPosts"
                        :key="post.id"
                        :href="route('blog.view', post.slug)"
                        class="animate-fade-in-up group block opacity-0"
                        :style="delays[index]"
                    >
                        <Card class="h-full overflow-hidden transition-all duration-300 hover:-translate-y-1 hover:shadow-xl">
                            <div class="aspect-video overflow-hidden">
                                <img
                                    v-if="post.featured_image"
                                    :src="`/storage/${post.featured_image}`"
                                    :alt="post.title"
                                    loading="lazy"
                                    decoding="async"
                                    class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-[1.02]"
                                />
                                <div v-else class="flex h-full items-center justify-center bg-muted">
                                    <Newspaper class="size-8 text-muted-foreground/20" />
                                </div>
                            </div>
                            <CardContent class="p-4">
                                <div class="mb-2 flex flex-wrap items-center gap-2">
                                    <Badge v-if="post.category" variant="secondary" class="text-[10px]">{{ post.category.name }}</Badge>
                                    <span class="text-[11px] text-muted-foreground">{{ formatDate(post.published_at) }}</span>
                                </div>
                                <h3 class="mb-1.5 line-clamp-2 text-sm font-bold leading-tight group-hover:text-primary sm:text-base">
                                    {{ post.title }}
                                </h3>
                                <p v-if="post.excerpt" class="mb-3 line-clamp-2 text-xs text-muted-foreground">{{ post.excerpt }}</p>
                                <div class="flex items-center gap-2 text-xs text-muted-foreground">
                                    <Avatar size="sm" class="!h-5 !w-5 !text-[9px]">
                                        <AvatarFallback>{{ getInitials(post.author.name) }}</AvatarFallback>
                                    </Avatar>
                                    <span>{{ post.author.name }}</span>
                                </div>
                            </CardContent>
                        </Card>
                    </Link>
                </div>
            </template>

            <EmptyState v-else title="No articles found" description="Try adjusting your filters or check back later for new content." />

            <InertiaPagination :paginator="posts" :only="['posts']" />
        </div>
    </div>
</template>
