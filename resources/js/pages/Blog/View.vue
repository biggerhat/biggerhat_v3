<script setup lang="ts">
import BlogContent from '@/components/blog/BlogContent.vue';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Separator } from '@/components/ui/separator';
import { getInitials } from '@/composables/useInitials';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';

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
}

const props = defineProps<{
    post: BlogPost;
}>();

const formatDate = (dateStr: string) => {
    return new Date(dateStr).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
};

const hasRelatedContent = () => {
    return props.post.characters.length > 0 || props.post.keywords.length > 0 || props.post.upgrades.length > 0;
};
</script>

<template>
    <Head :title="post.title" />

    <!-- Hero image header -->
    <div v-if="post.featured_image" class="relative h-[300px] w-full overflow-hidden sm:h-[400px] lg:h-[480px]">
        <img :src="`/storage/${post.featured_image}`" :alt="post.title" class="h-full w-full object-cover" />
        <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/40 to-transparent" />
        <div class="container absolute inset-x-0 bottom-0 mx-auto px-4 pb-8 md:pb-12">
            <Badge v-if="post.category" variant="secondary" class="animate-fade-in-up mb-3 opacity-0">{{ post.category.name }}</Badge>
            <h1
                class="animate-fade-in-up mb-4 max-w-3xl text-3xl font-bold leading-tight text-white opacity-0 [animation-delay:100ms] md:text-4xl lg:text-5xl"
            >
                {{ post.title }}
            </h1>
            <div class="animate-fade-in-up flex items-center gap-3 opacity-0 [animation-delay:200ms]">
                <Avatar size="sm">
                    <AvatarFallback>{{ getInitials(post.author.name) }}</AvatarFallback>
                </Avatar>
                <div class="text-sm text-white/90">
                    <span class="font-medium">{{ post.author.name }}</span>
                    <span class="mx-2">&middot;</span>
                    <time>{{ formatDate(post.published_at) }}</time>
                </div>
            </div>
        </div>
    </div>

    <!-- No-image fallback header -->
    <div v-else class="relative">
        <div
            class="pointer-events-none absolute inset-x-0 top-0 h-64 opacity-[0.07] dark:opacity-[0.12]"
            :style="{ background: 'radial-gradient(ellipse at top, hsl(var(--primary)) 0%, transparent 70%)' }"
        />
        <div class="container mx-auto px-4 pb-6 pt-8 md:pt-12">
            <Badge v-if="post.category" variant="secondary" class="animate-fade-in-up mb-3 opacity-0">{{ post.category.name }}</Badge>
            <h1 class="animate-fade-in-up mb-4 max-w-3xl text-3xl font-bold leading-tight opacity-0 [animation-delay:100ms] md:text-4xl lg:text-5xl">
                {{ post.title }}
            </h1>
            <div class="animate-fade-in-up flex items-center gap-3 opacity-0 [animation-delay:200ms]">
                <Avatar size="sm">
                    <AvatarFallback>{{ getInitials(post.author.name) }}</AvatarFallback>
                </Avatar>
                <div class="text-sm text-muted-foreground">
                    <span class="font-medium text-foreground">{{ post.author.name }}</span>
                    <span class="mx-2">&middot;</span>
                    <time>{{ formatDate(post.published_at) }}</time>
                </div>
            </div>
        </div>
    </div>

    <article class="container mx-auto px-4 pb-16">
        <div class="mx-auto max-w-3xl">
            <!-- Back link -->
            <Link
                :href="route('blog.index')"
                class="group mb-8 inline-flex items-center gap-1.5 text-sm text-muted-foreground transition-colors hover:text-foreground"
            >
                <ArrowLeft class="h-4 w-4 transition-transform group-hover:-translate-x-1" />
                Back to Articles
            </Link>

            <!-- Content -->
            <BlogContent :content="post.content" />

            <!-- Related Content -->
            <aside v-if="hasRelatedContent()" class="mt-12">
                <Separator label="Related Content" class="mb-8" />

                <div class="space-y-6">
                    <!-- Characters -->
                    <div v-if="post.characters.length">
                        <h4 class="mb-2 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Characters</h4>
                        <div class="flex flex-wrap gap-2">
                            <Link
                                v-for="character in post.characters"
                                :key="`c-${character.slug}`"
                                :href="route('characters.view', { character: character.slug, miniature: 1, slug: 'view' })"
                            >
                                <Badge variant="outline" class="cursor-pointer transition-colors hover:bg-accent">
                                    {{ character.display_name }}
                                </Badge>
                            </Link>
                        </div>
                    </div>

                    <!-- Keywords -->
                    <div v-if="post.keywords.length">
                        <h4 class="mb-2 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Keywords</h4>
                        <div class="flex flex-wrap gap-2">
                            <Link v-for="keyword in post.keywords" :key="`k-${keyword.slug}`" :href="route('keywords.view', keyword.slug)">
                                <Badge variant="outline" class="cursor-pointer transition-colors hover:bg-accent">
                                    {{ keyword.name }}
                                </Badge>
                            </Link>
                        </div>
                    </div>

                    <!-- Upgrades -->
                    <div v-if="post.upgrades.length">
                        <h4 class="mb-2 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Upgrades</h4>
                        <div class="flex flex-wrap gap-2">
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
    </article>
</template>
