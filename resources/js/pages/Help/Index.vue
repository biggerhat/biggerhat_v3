<script setup lang="ts">
import PageBanner from '@/components/PageBanner.vue';
import { Card, CardContent } from '@/components/ui/card';
import { helpTopic } from '@/lib/help';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

/** The help index: one card per feature that has help. Which topics show comes from the server (App\Support\Help\HelpTopics). */
const props = defineProps<{ topics: string[] }>();
const items = computed(() => props.topics.map((slug) => helpTopic(slug)).filter((t) => !!t));
</script>

<template>
    <Head title="Help" />
    <PageBanner title="Help">
        <template #subtitle>How the tools on this site work.</template>
    </PageBanner>
    <div class="mx-auto grid max-w-4xl gap-4 p-4 sm:grid-cols-2" data-testid="help-index">
        <Link
            v-for="t in items"
            :key="t!.slug"
            :href="route('help.show', t!.slug)"
            class="block rounded-xl focus-visible:outline focus-visible:outline-2"
            :data-testid="`help-topic-${t!.slug}`"
        >
            <Card class="h-full transition-colors hover:bg-muted/40">
                <CardContent class="space-y-1 p-4">
                    <h2 class="font-semibold">{{ t!.title }}</h2>
                    <p class="text-sm text-muted-foreground">{{ t!.summary }}</p>
                </CardContent>
            </Card>
        </Link>
    </div>
</template>
