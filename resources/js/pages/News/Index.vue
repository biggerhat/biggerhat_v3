<script setup lang="ts">
import EmptyState from '@/components/EmptyState.vue';
import InertiaPagination from '@/components/InertiaPagination.vue';
import PageBanner from '@/components/PageBanner.vue';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { getInitials } from '@/composables/useInitials';
import { useStaggeredEntry } from '@/composables/useStaggeredEntry';
import { Head, Link, router } from '@inertiajs/vue3';
import { Megaphone } from 'lucide-vue-next';
import { computed } from 'vue';

interface NewsPost {
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

interface NewsCategory {
    id: number;
    name: string;
    slug: string;
}

interface Paginator {
    data: NewsPost[];
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

const props = defineProps<{
    posts: Paginator;
    categories: NewsCategory[];
    active_category: string | null;
}>();

const isFirstPageNoFilters = computed(() => props.posts.current_page === 1 && !props.active_category);

const featuredPost = computed(() => (isFirstPageNoFilters.value && props.posts.data.length >= 2 ? props.posts.data[0] : null));

const gridPosts = computed(() => (featuredPost.value ? props.posts.data.slice(1) : props.posts.data));

const gridPostCount = computed(() => gridPosts.value.length);
const { delays } = useStaggeredEntry(gridPostCount);

const activeCategoryTab = computed(() => props.active_category ?? 'all');

const applyCategoryFilter = (val: string) => {
    const params = val === 'all' ? {} : { category: val };
    router.get(route('news.index'), params, { only: ['posts', 'active_category'], preserveState: true, preserveScroll: true, replace: true });
};

const formatDate = (dateStr: string) => {
    return new Date(dateStr).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
};
</script>

<template>
    <Head title="Site News" />

    <div class="relative">
        <div
            class="pointer-events-none absolute inset-x-0 top-0 h-64 opacity-[0.07] dark:opacity-[0.12]"
            :style="{ background: 'radial-gradient(ellipse at top, hsl(var(--primary)) 0%, transparent 70%)' }"
        />

        <PageBanner title="Site News" class="mb-2">
            <template #subtitle>
                <div class="my-auto px-2 py-0 text-xs text-muted-foreground md:py-2 md:text-sm md:text-foreground">
                    {{ posts.total }} {{ posts.total === 1 ? 'update' : 'updates' }} found
                </div>
            </template>
        </PageBanner>

        <div class="container mx-auto sm:px-4">
            <!-- Category tabs -->
            <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <Tabs :model-value="activeCategoryTab" @update:model-value="applyCategoryFilter($event as string)">
                    <TabsList class="flex-wrap">
                        <TabsTrigger value="all">All</TabsTrigger>
                        <TabsTrigger v-for="category in categories" :key="category.id" :value="category.slug">
                            {{ category.name }}
                        </TabsTrigger>
                    </TabsList>
                </Tabs>
            </div>

            <template v-if="posts.data.length">
                <!-- Featured post (first page, no filters) -->
                <Link v-if="featuredPost" :href="route('news.view', featuredPost.slug)" class="animate-fade-in-up group mb-6 block opacity-0">
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
                                <Megaphone class="size-12 text-muted-foreground/20" />
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
                        :href="route('news.view', post.slug)"
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
                                    <Megaphone class="size-8 text-muted-foreground/20" />
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

            <EmptyState v-else title="No news yet" description="Check back later for site updates and announcements." />

            <InertiaPagination :paginator="posts" :only="['posts']" />
        </div>
    </div>
</template>
