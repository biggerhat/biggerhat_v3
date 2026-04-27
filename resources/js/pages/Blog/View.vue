<script setup lang="ts">
import BlogContent from '@/components/blog/BlogContent.vue';
import JsonLd from '@/components/JsonLd.vue';
import SeoHead from '@/components/SeoHead.vue';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Separator } from '@/components/ui/separator';
import { getInitials } from '@/composables/useInitials';
import { Link } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';
import { computed } from 'vue';

interface BlogPost {
    id: number;
    title: string;
    slug: string;
    content: Record<string, unknown>;
    excerpt: string | null;
    featured_image: string | null;
    status: string;
    published_at: string | null;
    author: { name: string };
    category: { name: string; slug: string } | null;
    characters: Array<{ display_name: string; slug: string; faction: string; miniatures?: Array<{ id: number; slug: string }> }>;
    keywords: Array<{ name: string; slug: string }>;
    upgrades: Array<{ name: string; slug: string }>;
}

const props = defineProps<{
    post: BlogPost;
    isPreview?: boolean;
}>();

const formatDate = (dateStr: string | null) => {
    if (!dateStr) return 'Unpublished';
    return new Date(dateStr).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
};

const hasRelatedContent = () => {
    return props.post.characters.length > 0 || props.post.keywords.length > 0 || props.post.upgrades.length > 0;
};

const factionCssVar = (faction: string): string => {
    if (!faction) return 'primary';
    const map: Record<string, string> = { explorers_society: 'explorerssociety', ten_thunders: 'tenthunders' };
    return map[faction] ?? faction;
};

const seoDescription = computed(() => {
    if (props.post.excerpt) return props.post.excerpt;
    // Fallback: pull plain text out of the rich content tree.
    const collectText = (node: unknown): string => {
        if (typeof node !== 'object' || node === null) return '';
        const n = node as { text?: string; content?: unknown[] };
        if (typeof n.text === 'string') return n.text;
        if (Array.isArray(n.content)) return n.content.map(collectText).join(' ');
        return '';
    };
    return collectText(props.post.content).replace(/\s+/g, ' ').trim().slice(0, 280);
});
</script>

<template>
    <SeoHead
        :title="isPreview ? `Preview: ${post.title}` : post.title"
        :description="seoDescription"
        :image="post.featured_image"
        type="article"
    />
    <JsonLd
        v-if="!isPreview && post.published_at"
        head-key="blog-article"
        :data="{
            '@context': 'https://schema.org',
            '@type': 'BlogPosting',
            headline: post.title,
            description: seoDescription,
            image: post.featured_image
                ? (post.featured_image.startsWith('http') ? post.featured_image : `https://biggerhat.net/storage/${post.featured_image}`)
                : undefined,
            datePublished: post.published_at,
            author: { '@type': 'Person', name: post.author?.name ?? 'BiggerHat' },
            publisher: {
                '@type': 'Organization',
                name: 'BiggerHat',
                logo: { '@type': 'ImageObject', url: 'https://biggerhat.net/images/biggerhat-og.png' },
            },
        }"
    />

    <!-- Preview banner -->
    <div v-if="isPreview" class="border-b border-yellow-300 bg-yellow-50 px-4 py-3 dark:border-yellow-700 dark:bg-yellow-950">
        <div class="container mx-auto flex items-center justify-between sm:px-4">
            <div class="flex items-center gap-2 text-sm font-medium text-yellow-800 dark:text-yellow-200">
                <Badge variant="outline" class="border-yellow-400 text-yellow-800 dark:border-yellow-600 dark:text-yellow-200">Preview</Badge>
                This is a preview — not yet visible to the public.
            </div>
            <Link
                :href="route('admin.blog.posts.edit', post.slug)"
                class="text-sm font-medium text-yellow-800 underline hover:no-underline dark:text-yellow-200"
            >
                Back to Editor
            </Link>
        </div>
    </div>

    <div class="relative">
        <!-- Background gradient -->
        <div
            class="pointer-events-none absolute inset-x-0 top-0 h-64 opacity-[0.07] dark:opacity-[0.12]"
            :style="{ background: 'radial-gradient(ellipse at top, hsl(var(--primary)) 0%, transparent 70%)' }"
        />

        <div class="container mx-auto pb-8 pt-4 sm:px-4 lg:pb-16 lg:pt-6">
            <!-- Back link -->
            <Link
                :href="isPreview ? route('admin.blog.posts.edit', post.slug) : route('blog.index')"
                class="group mb-4 inline-flex items-center gap-1.5 text-sm text-muted-foreground transition-colors hover:text-foreground lg:mb-6"
            >
                <ArrowLeft class="h-4 w-4 transition-transform group-hover:-translate-x-1" />
                {{ isPreview ? 'Back to Editor' : 'Back to Articles' }}
            </Link>

            <!-- Hero image -->
            <div v-if="post.featured_image" class="mb-6 overflow-hidden rounded-xl lg:mb-8">
                <img
                    :src="`/storage/${post.featured_image}`"
                    :alt="post.title"
                    loading="lazy"
                    decoding="async"
                    class="max-h-64 w-full object-cover sm:max-h-80 lg:max-h-[420px]"
                />
            </div>

            <!-- Article header -->
            <div class="mx-auto mb-6 max-w-3xl lg:mb-8">
                <div class="mb-3 flex flex-wrap items-center gap-2">
                    <Badge v-if="post.category" variant="secondary">{{ post.category.name }}</Badge>
                    <span class="text-sm text-muted-foreground">{{ formatDate(post.published_at) }}</span>
                </div>
                <h1 class="mb-4 text-2xl font-bold leading-tight sm:text-3xl md:text-4xl">
                    {{ post.title }}
                </h1>
                <div class="flex items-center gap-3">
                    <Avatar size="sm">
                        <AvatarFallback>{{ getInitials(post.author.name) }}</AvatarFallback>
                    </Avatar>
                    <span class="text-sm font-medium">{{ post.author.name }}</span>
                </div>
            </div>

            <!-- Content -->
            <article class="mx-auto max-w-3xl">
                <BlogContent :content="post.content" />
            </article>

            <!-- Related Content -->
            <aside v-if="hasRelatedContent()" class="mx-auto mt-8 max-w-3xl lg:mt-12">
                <Separator label="Related Content" class="mb-6" />

                <div class="space-y-4 sm:space-y-6">
                    <!-- Characters -->
                    <div v-if="post.characters.length">
                        <h4 class="mb-1.5 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Characters</h4>
                        <div class="flex flex-wrap gap-1.5">
                            <Link
                                v-for="character in post.characters"
                                :key="`c-${character.slug}`"
                                :href="
                                    character.miniatures?.length
                                        ? route('characters.view', {
                                              character: character.slug,
                                              miniature: character.miniatures[0].id,
                                              slug: character.miniatures[0].slug,
                                          })
                                        : `/characters/${character.slug}`
                                "
                            >
                                <Badge
                                    class="cursor-pointer border-0 text-white transition-opacity hover:opacity-80"
                                    :style="character.faction ? { backgroundColor: `hsl(var(--${factionCssVar(character.faction)}))` } : {}"
                                    :variant="character.faction ? 'default' : 'outline'"
                                >
                                    {{ character.display_name }}
                                </Badge>
                            </Link>
                        </div>
                    </div>

                    <!-- Keywords -->
                    <div v-if="post.keywords.length">
                        <h4 class="mb-1.5 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Keywords</h4>
                        <div class="flex flex-wrap gap-1.5">
                            <Link v-for="keyword in post.keywords" :key="`k-${keyword.slug}`" :href="route('keywords.view', keyword.slug)">
                                <Badge variant="outline" class="cursor-pointer transition-colors hover:bg-accent">
                                    {{ keyword.name }}
                                </Badge>
                            </Link>
                        </div>
                    </div>

                    <!-- Upgrades -->
                    <div v-if="post.upgrades.length">
                        <h4 class="mb-1.5 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Upgrades</h4>
                        <div class="flex flex-wrap gap-1.5">
                            <Link v-for="upgrade in post.upgrades" :key="`u-${upgrade.slug}`" :href="route('upgrades.view', upgrade.slug)">
                                <Badge variant="outline" class="cursor-pointer transition-colors hover:bg-accent">
                                    {{ upgrade.name }}
                                </Badge>
                            </Link>
                        </div>
                    </div>
                </div>
            </aside>
        </div>
    </div>
</template>
