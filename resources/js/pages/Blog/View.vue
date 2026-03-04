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
            <div class="flex flex-wrap items-center gap-2 p-2 text-sm text-muted-foreground">
                <span>By {{ post.author.name }}</span>
                <span>&middot;</span>
                <time>{{ formatDate(post.published_at) }}</time>
                <Badge v-if="post.category" variant="secondary" class="ml-1">{{ post.category.name }}</Badge>
            </div>
        </template>
    </PageBanner>

    <article class="container mx-auto px-4 pb-16">
        <div class="mx-auto max-w-3xl">
            <!-- Featured Image -->
            <div v-if="post.featured_image" class="mb-8 overflow-hidden rounded-xl shadow-lg">
                <img :src="`/storage/${post.featured_image}`" :alt="post.title" class="w-full object-cover" />
            </div>

            <!-- Content -->
            <BlogContent :content="post.content" />

            <!-- Related Content -->
            <aside v-if="hasRelatedContent()" class="mt-12 rounded-lg border bg-card p-6">
                <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-muted-foreground">Related</h3>
                <div class="flex flex-wrap gap-2">
                    <Link
                        v-for="character in post.characters"
                        :key="`c-${character.slug}`"
                        :href="route('characters.view', { character: character.slug, miniature: 1, slug: 'view' })"
                    >
                        <Badge class="cursor-pointer bg-blue-100 text-blue-800 hover:bg-blue-200 dark:bg-blue-900 dark:text-blue-200 dark:hover:bg-blue-800">
                            {{ character.display_name }}
                        </Badge>
                    </Link>
                    <Link v-for="keyword in post.keywords" :key="`k-${keyword.slug}`" :href="route('keywords.view', keyword.slug)">
                        <Badge class="cursor-pointer bg-green-100 text-green-800 hover:bg-green-200 dark:bg-green-900 dark:text-green-200 dark:hover:bg-green-800">
                            {{ keyword.name }}
                        </Badge>
                    </Link>
                    <Link v-for="upgrade in post.upgrades" :key="`u-${upgrade.slug}`" :href="route('upgrades.view', upgrade.slug)">
                        <Badge class="cursor-pointer bg-orange-100 text-orange-800 hover:bg-orange-200 dark:bg-orange-900 dark:text-orange-200 dark:hover:bg-orange-800">
                            {{ upgrade.name }}
                        </Badge>
                    </Link>
                    <Badge
                        v-for="action in post.actions"
                        :key="`a-${action.slug}`"
                        class="bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200"
                    >
                        {{ action.name }}
                    </Badge>
                    <Badge
                        v-for="ability in post.abilities"
                        :key="`ab-${ability.slug}`"
                        class="bg-teal-100 text-teal-800 dark:bg-teal-900 dark:text-teal-200"
                    >
                        {{ ability.name }}
                    </Badge>
                </div>
            </aside>
        </div>
    </article>
</template>
