<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { ChevronDown, ExternalLink, Newspaper, Radio } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface Article {
    id: number;
    title: string;
    slug: string;
    published_at: string | null;
    category: { id: number; name: string } | null;
}

interface TransmissionItem {
    id: number;
    title: string;
    slug: string;
    url: string | null;
    release_date: string | null;
    channel: { id: number; name: string; slug: string; image: string | null } | null;
}

interface PodLinkItem {
    id: number;
    name: string;
    slug: string;
    source: string;
    url: string;
}

const props = defineProps<{
    articles?: Article[];
    transmissions?: TransmissionItem[];
    podLinks?: PodLinkItem[];
}>();

const open = ref(false);

const totalCount = computed(() => (props.articles?.length ?? 0) + (props.transmissions?.length ?? 0) + (props.podLinks?.length ?? 0));

const hasContent = computed(() => totalCount.value > 0);

const formatDate = (d: string | null) => {
    if (!d) return '';
    return new Date(d).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
};

const sourceLabel = (source: string) => (source === 'forgefire' ? 'ForgeFire' : 'Wargame Vault');
</script>

<template>
    <div v-if="hasContent" class="container mx-auto mb-2 sm:px-4">
        <Collapsible v-model:open="open">
            <CollapsibleTrigger class="flex w-full items-center justify-between rounded-lg border bg-card px-4 py-2.5 text-sm font-medium transition-colors hover:bg-accent/50">
                <div class="flex items-center gap-2">
                    <span>Resources</span>
                    <Badge variant="secondary" class="px-1.5 py-0 text-[10px]">{{ totalCount }}</Badge>
                </div>
                <ChevronDown class="size-4 text-muted-foreground transition-transform" :class="{ 'rotate-180': open }" />
            </CollapsibleTrigger>
            <CollapsibleContent class="mt-1">
                <div class="rounded-lg border bg-card">
                    <div class="grid gap-px sm:grid-cols-2 lg:grid-cols-3">
                        <!-- Articles -->
                        <div v-if="articles?.length" class="p-3">
                            <div class="mb-2 flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                <Newspaper class="size-3.5" />
                                Articles
                            </div>
                            <div class="space-y-1">
                                <a
                                    v-for="article in articles"
                                    :key="article.id"
                                    :href="route('blog.view', { blogPost: article.slug })"
                                    class="flex items-start gap-2 rounded-md px-2 py-1.5 text-sm transition-colors hover:bg-accent"
                                >
                                    <div class="min-w-0 flex-1">
                                        <div class="font-medium leading-tight">{{ article.title }}</div>
                                        <div class="mt-0.5 flex items-center gap-1 text-[10px] text-muted-foreground">
                                            <span v-if="article.category">{{ article.category.name }}</span>
                                            <span v-if="article.category && article.published_at">&middot;</span>
                                            <span v-if="article.published_at">{{ formatDate(article.published_at) }}</span>
                                        </div>
                                    </div>
                                </a>
                            </div>
                        </div>

                        <!-- Transmissions -->
                        <div v-if="transmissions?.length" class="p-3">
                            <div class="mb-2 flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                <Radio class="size-3.5" />
                                Across the Aethervox
                            </div>
                            <div class="space-y-1">
                                <a
                                    v-for="t in transmissions"
                                    :key="t.id"
                                    :href="t.url ?? '#'"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="flex items-start gap-2 rounded-md px-2 py-1.5 text-sm transition-colors hover:bg-accent"
                                >
                                    <img v-if="t.channel?.image" :src="'/storage/' + t.channel.image" :alt="t.channel.name" class="mt-0.5 size-5 shrink-0 rounded" />
                                    <div class="min-w-0 flex-1">
                                        <div class="font-medium leading-tight">{{ t.title }}</div>
                                        <div class="mt-0.5 flex items-center gap-1 text-[10px] text-muted-foreground">
                                            <span v-if="t.channel">{{ t.channel.name }}</span>
                                            <span v-if="t.channel && t.release_date">&middot;</span>
                                            <span v-if="t.release_date">{{ formatDate(t.release_date) }}</span>
                                        </div>
                                    </div>
                                </a>
                            </div>
                        </div>

                        <!-- Print On Demand -->
                        <div v-if="podLinks?.length" class="p-3">
                            <div class="mb-2 flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                <ExternalLink class="size-3.5" />
                                Print On Demand
                            </div>
                            <div class="space-y-1">
                                <a
                                    v-for="pod in podLinks"
                                    :key="pod.id"
                                    :href="pod.url"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="flex items-center gap-2 rounded-md px-2 py-1.5 text-sm transition-colors hover:bg-accent"
                                >
                                    <div class="min-w-0 flex-1">
                                        <div class="font-medium leading-tight">{{ pod.name }}</div>
                                    </div>
                                    <Badge variant="outline" class="shrink-0 text-[9px]">{{ sourceLabel(pod.source) }}</Badge>
                                    <ExternalLink class="size-3 shrink-0 text-muted-foreground" />
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </CollapsibleContent>
        </Collapsible>
    </div>
</template>
