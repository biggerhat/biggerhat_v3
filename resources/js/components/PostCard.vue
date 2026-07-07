<script setup lang="ts">
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { getInitials } from '@/composables/useInitials';
import { CARD_HOVER, CARD_HOVER_PROMINENT } from '@/lib/cardHover';
import { Link } from '@inertiajs/vue3';
import { Newspaper } from 'lucide-vue-next';
import type { Component } from 'vue';

export interface Post {
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

withDefaults(
    defineProps<{
        post: Post;
        variant: 'featured' | 'grid';
        href: string;
        icon?: Component;
    }>(),
    { icon: () => Newspaper },
);

const formatDate = (dateStr: string) => new Date(dateStr).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
</script>

<template>
    <Link :href="href" class="group block">
        <Card v-if="variant === 'featured'" :class="['overflow-hidden md:grid md:grid-cols-5', CARD_HOVER_PROMINENT]">
            <div class="aspect-video overflow-hidden md:col-span-3 md:aspect-auto md:h-full">
                <img
                    v-if="post.featured_image"
                    :src="`/storage/${post.featured_image}`"
                    :alt="post.title"
                    loading="lazy"
                    decoding="async"
                    class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-[1.02]"
                />
                <div v-else class="flex h-full min-h-48 items-center justify-center bg-muted">
                    <component :is="icon" class="size-12 text-muted-foreground/20" />
                </div>
            </div>
            <div class="flex flex-col justify-center p-5 md:col-span-2 md:p-6">
                <div class="mb-2 flex flex-wrap items-center gap-2">
                    <Badge v-if="post.category" variant="secondary" class="text-[10px]">{{ post.category.name }}</Badge>
                    <span class="text-xs text-muted-foreground">{{ formatDate(post.published_at) }}</span>
                </div>
                <h2 class="mb-2 line-clamp-3 text-xl font-bold leading-tight group-hover:text-primary md:text-2xl">
                    {{ post.title }}
                </h2>
                <p v-if="post.excerpt" class="mb-3 line-clamp-2 text-sm text-muted-foreground">{{ post.excerpt }}</p>
                <div class="flex items-center gap-2 text-xs text-muted-foreground">
                    <Avatar size="sm" class="!h-5 !w-5 !text-[9px]">
                        <AvatarFallback>{{ getInitials(post.author.name) }}</AvatarFallback>
                    </Avatar>
                    <span class="font-medium text-foreground">{{ post.author.name }}</span>
                </div>
            </div>
        </Card>
        <Card v-else :class="['h-full overflow-hidden', CARD_HOVER]">
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
                    <component :is="icon" class="size-8 text-muted-foreground/20" />
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
</template>
