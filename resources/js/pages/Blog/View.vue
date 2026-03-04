<script setup lang="ts">
import BlogContent from '@/components/blog/BlogContent.vue';
import PageBanner from '@/components/PageBanner.vue';
import { Badge } from '@/components/ui/badge';
import { Head, Link } from '@inertiajs/vue3';

interface BlogPost {
    id: number;
    title: string;
    slug: string;
    content: Record<string, unknown>;
    excerpt: string | null;
    featured_image: string | null;
    status: string;
    published_at: string;
    author: { name: string };
    category: { name: string; slug: string } | null;
    characters: Array<{ display_name: string; slug: string; faction: string }>;
    keywords: Array<{ name: string; slug: string }>;
    upgrades: Array<{ name: string; slug: string }>;
    actions: Array<{ name: string; slug: string }>;
    abilities: Array<{ name: string; slug: string }>;
    faction_tags: string[];
}

const props = defineProps<{
    post: BlogPost;
}>();

const formatDate = (dateStr: string) => {
    return new Date(dateStr).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
};

const hasRelatedContent = () => {
    return (
        props.post.characters.length > 0 ||
        props.post.keywords.length > 0 ||
        props.post.upgrades.length > 0 ||
        props.post.actions.length > 0 ||
        props.post.abilities.length > 0
    );
};
</script>

<template>
    <Head :title="post.title" />
    <PageBanner :title="post.title">
        <template #subtitle>
            <div class="flex items-center gap-2 p-2 text-sm text-muted-foreground">
                <span>{{ post.author.name }}</span>
                <span>&middot;</span>
                <span>{{ formatDate(post.published_at) }}</span>
                <Badge v-if="post.category" variant="secondary" class="ml-2">{{ post.category.name }}</Badge>
            </div>
        </template>
    </PageBanner>

    <div class="container mx-auto px-4">
        <div class="mx-auto max-w-3xl">
            <!-- Featured Image -->
            <div v-if="post.featured_image" class="mb-8 overflow-hidden rounded-lg">
                <img :src="`/storage/${post.featured_image}`" :alt="post.title" class="w-full object-cover" />
            </div>

            <!-- Content -->
            <BlogContent :content="post.content" />

            <!-- Related Content -->
            <div v-if="hasRelatedContent()" class="mt-12 border-t pt-8">
                <h3 class="mb-4 text-lg font-bold">Related Content</h3>
                <div class="flex flex-wrap gap-2">
                    <Link
                        v-for="character in post.characters"
                        :key="`c-${character.slug}`"
                        :href="route('characters.view', { character: character.slug, miniature: 1, slug: 'view' })"
                    >
                        <Badge variant="outline" class="cursor-pointer hover:bg-blue-100 dark:hover:bg-blue-900">
                            {{ character.display_name }}
                        </Badge>
                    </Link>
                    <Link v-for="keyword in post.keywords" :key="`k-${keyword.slug}`" :href="route('keywords.view', keyword.slug)">
                        <Badge variant="outline" class="cursor-pointer hover:bg-green-100 dark:hover:bg-green-900">
                            {{ keyword.name }}
                        </Badge>
                    </Link>
                    <Link v-for="upgrade in post.upgrades" :key="`u-${upgrade.slug}`" :href="route('upgrades.view', upgrade.slug)">
                        <Badge variant="outline" class="cursor-pointer hover:bg-orange-100 dark:hover:bg-orange-900">
                            {{ upgrade.name }}
                        </Badge>
                    </Link>
                    <Badge v-for="action in post.actions" :key="`a-${action.slug}`" variant="outline">
                        {{ action.name }}
                    </Badge>
                    <Badge v-for="ability in post.abilities" :key="`ab-${ability.slug}`" variant="outline">
                        {{ ability.name }}
                    </Badge>
                </div>
            </div>
        </div>
    </div>
</template>
