<script setup lang="ts">
import InertiaPagination from '@/components/InertiaPagination.vue';
import PageBanner from '@/components/PageBanner.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardFooter, CardHeader } from '@/components/ui/card';
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

const filterByCategory = (categorySlug: string | null) => {
    const params: Record<string, string> = {};
    if (categorySlug) params.category = categorySlug;
    if (props.active_faction) params.faction = props.active_faction;
    router.get(route('blog.index'), params, { preserveState: true });
};

const filterByFaction = (factionSlug: string | null) => {
    const params: Record<string, string> = {};
    if (props.active_category) params.category = props.active_category;
    if (factionSlug) params.faction = factionSlug;
    router.get(route('blog.index'), params, { preserveState: true });
};

const formatDate = (dateStr: string) => {
    return new Date(dateStr).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
};
</script>

<template>
    <Head title="Blog" />
    <PageBanner title="Blog" />

    <div class="container mx-auto px-4">
        <!-- Filters -->
        <div class="mb-6 flex flex-wrap gap-2">
            <Button :variant="!active_category ? 'default' : 'outline'" size="sm" @click="filterByCategory(null)">All</Button>
            <Button
                v-for="category in categories"
                :key="category.id"
                :variant="active_category === category.slug ? 'default' : 'outline'"
                size="sm"
                @click="filterByCategory(category.slug)"
            >
                {{ category.name }}
            </Button>
        </div>

        <div class="mb-6 flex flex-wrap gap-2">
            <Button :variant="!active_faction ? 'default' : 'outline'" size="sm" @click="filterByFaction(null)">All Factions</Button>
            <Button
                v-for="(faction, key) in factionInfo"
                :key="key"
                :variant="active_faction === faction.slug ? 'default' : 'outline'"
                size="sm"
                @click="filterByFaction(faction.slug)"
            >
                <img :src="faction.logo" class="mr-1 h-4 w-4" :alt="faction.name" />
                {{ faction.name }}
            </Button>
        </div>

        <!-- Post Grid -->
        <div v-if="posts.data.length" class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
            <Link v-for="post in posts.data" :key="post.id" :href="route('blog.view', post.slug)" class="block">
                <Card class="h-full transition hover:shadow-lg">
                    <div v-if="post.featured_image" class="aspect-video overflow-hidden rounded-t-lg">
                        <img :src="`/storage/${post.featured_image}`" :alt="post.title" class="h-full w-full object-cover" />
                    </div>
                    <CardHeader>
                        <div class="flex items-center gap-2">
                            <Badge v-if="post.category" variant="secondary">{{ post.category.name }}</Badge>
                        </div>
                        <h3 class="text-lg font-bold leading-tight">{{ post.title }}</h3>
                    </CardHeader>
                    <CardContent v-if="post.excerpt">
                        <p class="line-clamp-3 text-sm text-muted-foreground">{{ post.excerpt }}</p>
                    </CardContent>
                    <CardFooter class="text-xs text-muted-foreground">
                        <span>{{ post.author.name }}</span>
                        <span class="mx-2">&middot;</span>
                        <span>{{ formatDate(post.published_at) }}</span>
                    </CardFooter>
                </Card>
            </Link>
        </div>
        <div v-else class="py-12 text-center text-muted-foreground">No blog posts found.</div>

        <InertiaPagination :paginator="posts" :only="['posts']" />
    </div>
</template>
