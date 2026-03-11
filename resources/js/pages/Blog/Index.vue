<script setup lang="ts">
import EmptyState from '@/components/EmptyState.vue';
import InertiaPagination from '@/components/InertiaPagination.vue';
import PageBanner from '@/components/PageBanner.vue';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardFooter, CardHeader } from '@/components/ui/card';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { getInitials } from '@/composables/useInitials';
import { useStaggeredEntry } from '@/composables/useStaggeredEntry';
import { type SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
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
    active_category: string | null;
    active_faction: string | null;
}>();

const factionInfo = computed(() => page.props.faction_info as Record<string, { name: string; slug: string; color: string; logo: string }>);

const isFirstPageNoFilters = computed(() => props.posts.current_page === 1 && !props.active_category && !props.active_faction);

const featuredPost = computed(() => (isFirstPageNoFilters.value && props.posts.data.length >= 2 ? props.posts.data[0] : null));

const gridPosts = computed(() => (featuredPost.value ? props.posts.data.slice(1) : props.posts.data));

const gridPostCount = computed(() => gridPosts.value.length);
const { delays } = useStaggeredEntry(gridPostCount);

const activeCategoryTab = computed(() => props.active_category ?? 'all');

const selectedFaction = computed(() => props.active_faction ?? 'all');

const filterByCategory = (categorySlug: string) => {
    const params: Record<string, string> = {};
    if (categorySlug !== 'all') params.category = categorySlug;
    if (props.active_faction) params.faction = props.active_faction;
    router.get(route('blog.index'), params, { only: ['posts', 'active_category', 'active_faction'], preserveState: true, replace: true });
};

const filterByFaction = (factionSlug: string) => {
    const params: Record<string, string> = {};
    if (props.active_category) params.category = props.active_category;
    if (factionSlug !== 'all') params.faction = factionSlug;
    router.get(route('blog.index'), params, { only: ['posts', 'active_category', 'active_faction'], preserveState: true, replace: true });
};

const formatDate = (dateStr: string) => {
    return new Date(dateStr).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
};
</script>

<template>
    <Head title="Articles" />

    <div class="relative">
        <!-- Gradient accent -->
        <div
            class="pointer-events-none absolute inset-x-0 top-0 h-64 opacity-[0.07] dark:opacity-[0.12]"
            :style="{ background: 'radial-gradient(ellipse at top, hsl(var(--primary)) 0%, transparent 70%)' }"
        />

        <PageBanner title="Articles">
            <template #subtitle>
                <div class="p-2 text-sm text-muted-foreground">{{ posts.total }} {{ posts.total === 1 ? 'Article' : 'Articles' }}</div>
            </template>
        </PageBanner>

        <div class="container mx-auto px-4">
            <!-- Filter bar -->
            <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <Tabs :model-value="activeCategoryTab" @update:model-value="filterByCategory($event as string)">
                    <TabsList class="flex-wrap">
                        <TabsTrigger value="all">All</TabsTrigger>
                        <TabsTrigger v-for="category in categories" :key="category.id" :value="category.slug">
                            {{ category.name }}
                        </TabsTrigger>
                    </TabsList>
                </Tabs>

                <Select :model-value="selectedFaction" @update:model-value="filterByFaction($event as string)">
                    <SelectTrigger class="w-full sm:w-48">
                        <SelectValue placeholder="All Factions" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">All Factions</SelectItem>
                        <SelectItem v-for="(faction, key) in factionInfo" :key="key" :value="faction.slug">
                            <span class="flex items-center gap-2">
                                <img :src="faction.logo" class="h-4 w-4" :alt="faction.name" />
                                {{ faction.name }}
                            </span>
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <template v-if="posts.data.length">
                <!-- Hero featured post -->
                <Link v-if="featuredPost" :href="route('blog.view', featuredPost.slug)" class="animate-fade-in-up group mb-8 block opacity-0">
                    <Card class="overflow-hidden transition-shadow duration-300 hover:shadow-xl md:grid md:grid-cols-2">
                        <div class="aspect-video overflow-hidden md:aspect-auto md:h-full">
                            <img
                                v-if="featuredPost.featured_image"
                                :src="`/storage/${featuredPost.featured_image}`"
                                :alt="featuredPost.title"
                                class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-105"
                            />
                            <div v-else class="flex h-full min-h-48 items-center justify-center bg-muted">
                                <span class="text-4xl text-muted-foreground/30">{{ getInitials(featuredPost.title) }}</span>
                            </div>
                        </div>
                        <div class="flex flex-col justify-center p-6 md:p-8">
                            <Badge v-if="featuredPost.category" variant="secondary" class="mb-3 w-fit">{{ featuredPost.category.name }}</Badge>
                            <h2 class="mb-3 text-2xl font-bold leading-tight md:text-3xl">{{ featuredPost.title }}</h2>
                            <p v-if="featuredPost.excerpt" class="mb-4 line-clamp-3 text-muted-foreground">{{ featuredPost.excerpt }}</p>
                            <div class="flex items-center gap-3">
                                <Avatar size="sm">
                                    <AvatarFallback>{{ getInitials(featuredPost.author.name) }}</AvatarFallback>
                                </Avatar>
                                <div class="text-sm">
                                    <div class="font-medium">{{ featuredPost.author.name }}</div>
                                    <div class="text-muted-foreground">{{ formatDate(featuredPost.published_at) }}</div>
                                </div>
                            </div>
                        </div>
                    </Card>
                </Link>

                <!-- Grid cards -->
                <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    <Link
                        v-for="(post, index) in gridPosts"
                        :key="post.id"
                        :href="route('blog.view', post.slug)"
                        class="animate-fade-in-up group block opacity-0"
                        :style="delays[index]"
                    >
                        <Card class="h-full transition-all duration-300 hover:-translate-y-1 hover:shadow-xl">
                            <div class="aspect-video overflow-hidden rounded-t-lg">
                                <img
                                    v-if="post.featured_image"
                                    :src="`/storage/${post.featured_image}`"
                                    :alt="post.title"
                                    class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-105"
                                />
                                <div v-else class="flex h-full items-center justify-center bg-muted">
                                    <span class="text-2xl text-muted-foreground/30">{{ getInitials(post.title) }}</span>
                                </div>
                            </div>
                            <CardHeader class="pb-2">
                                <Badge v-if="post.category" variant="secondary" class="mb-1 w-fit text-xs">{{ post.category.name }}</Badge>
                                <h3 class="line-clamp-2 text-lg font-bold leading-tight">{{ post.title }}</h3>
                            </CardHeader>
                            <CardContent v-if="post.excerpt" class="pb-2">
                                <p class="line-clamp-2 text-sm text-muted-foreground">{{ post.excerpt }}</p>
                            </CardContent>
                            <CardFooter class="mt-auto gap-2 text-xs text-muted-foreground">
                                <Avatar size="sm" class="!h-6 !w-6 !text-[10px]">
                                    <AvatarFallback>{{ getInitials(post.author.name) }}</AvatarFallback>
                                </Avatar>
                                <span>{{ post.author.name }}</span>
                                <span>&middot;</span>
                                <span>{{ formatDate(post.published_at) }}</span>
                            </CardFooter>
                        </Card>
                    </Link>
                </div>
            </template>

            <EmptyState v-else title="No articles found" description="Try adjusting your filters or check back later for new content." />

            <InertiaPagination :paginator="posts" :only="['posts']" />
        </div>
    </div>
</template>
