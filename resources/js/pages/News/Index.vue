<script setup lang="ts">
import EmptyState from '@/components/EmptyState.vue';
import InertiaPagination from '@/components/InertiaPagination.vue';
import PageBanner from '@/components/PageBanner.vue';
import PostCard, { type Post } from '@/components/PostCard.vue';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useStaggeredEntry } from '@/composables/useStaggeredEntry';
import { Head, router } from '@inertiajs/vue3';
import { Megaphone } from 'lucide-vue-next';
import { computed } from 'vue';

type NewsPost = Post;

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
                <PostCard
                    v-if="featuredPost"
                    variant="featured"
                    :post="featuredPost"
                    :href="route('news.view', featuredPost.slug)"
                    :icon="Megaphone"
                    class="animate-fade-in-up mb-6 opacity-0"
                />

                <!-- Grid -->
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <PostCard
                        v-for="(post, index) in gridPosts"
                        :key="post.id"
                        variant="grid"
                        :post="post"
                        :href="route('news.view', post.slug)"
                        :icon="Megaphone"
                        class="animate-fade-in-up opacity-0"
                        :style="delays[index]"
                    />
                </div>
            </template>

            <EmptyState v-else title="No news yet" description="Check back later for site updates and announcements." />

            <InertiaPagination :paginator="posts" :only="['posts']" />
        </div>
    </div>
</template>
