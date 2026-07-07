<script setup lang="ts">
import EmptyState from '@/components/EmptyState.vue';
import PageBanner from '@/components/PageBanner.vue';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Card, CardContent } from '@/components/ui/card';
import { useInitials } from '@/composables/useInitials';
import { useStaggeredEntry } from '@/composables/useStaggeredEntry';
import { Head } from '@inertiajs/vue3';
import { Heart } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface Supporter {
    id: number;
    name: string;
    supporter_since: string | null;
}

const props = defineProps<{
    supporters: Supporter[];
}>();

const { getInitials } = useInitials();
const { delays } = useStaggeredEntry(ref(props.supporters.length), 40);
const supporterCount = computed(() => props.supporters.length);
</script>

<template>
    <Head title="Our Supporters" />

    <div class="relative">
        <div
            class="pointer-events-none absolute inset-x-0 top-0 h-64 opacity-[0.07] dark:opacity-[0.12]"
            :style="{ background: 'radial-gradient(ellipse at top, hsl(var(--primary)) 0%, transparent 70%)' }"
        />

        <PageBanner title="Our Supporters" class="mb-2">
            <template #subtitle>
                <div class="my-auto px-2 py-0 text-xs text-muted-foreground md:py-2 md:text-sm md:text-foreground">
                    {{ supporterCount }} {{ supporterCount === 1 ? 'person keeps' : 'people keep' }} BiggerHat running
                </div>
            </template>
        </PageBanner>

        <div class="container mx-auto sm:px-4">
            <p class="mb-6 max-w-2xl text-sm text-muted-foreground">
                BiggerHat is free and ad-free, kept online by folks who chip in on
                <a href="https://ko-fi.com/biggerhat" target="_blank" rel="noopener" class="font-medium text-primary hover:underline">Ko-fi</a>. Thank
                you.
            </p>

            <template v-if="supporters.length">
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <Card v-for="(supporter, index) in supporters" :key="supporter.id" class="animate-fade-in-up opacity-0" :style="delays[index]">
                        <CardContent class="flex items-center gap-3 p-4">
                            <Avatar size="sm">
                                <AvatarFallback>{{ getInitials(supporter.name) }}</AvatarFallback>
                            </Avatar>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium">{{ supporter.name }}</p>
                                <p v-if="supporter.supporter_since" class="text-xs text-muted-foreground">
                                    Supporter since {{ supporter.supporter_since }}
                                </p>
                            </div>
                            <Heart class="size-4 shrink-0 fill-rose-500/20 text-rose-500" />
                        </CardContent>
                    </Card>
                </div>
            </template>

            <EmptyState
                v-else
                title="No public supporters yet"
                description="Supporters can choose to appear here from their account settings — check back soon."
            />
        </div>
    </div>
</template>
