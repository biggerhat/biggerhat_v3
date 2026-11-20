<script setup lang="ts">
import HelpContent from '@/components/Help/HelpContent.vue';
import PageBanner from '@/components/PageBanner.vue';
import { Button } from '@/components/ui/button';
import { helpTabs, helpTopic } from '@/lib/help';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';
import { computed } from 'vue';

/** One feature's help as a page: every section with a contents list, for reading in full, printing or sharing. */
const props = defineProps<{ slug: string }>();
const topic = computed(() => helpTopic(props.slug));
const tabs = computed(() => (topic.value ? helpTabs(topic.value) : []));
</script>

<template>
    <template v-if="topic">
        <Head :title="`${topic.title} help`" />
        <PageBanner :title="`${topic.title} help`">
            <template #subtitle>{{ topic.summary }}</template>
            <template #actions>
                <Button as-child variant="outline" size="sm">
                    <Link :href="route(topic.route)"><ArrowLeft class="mr-1.5 size-4" />Back to {{ topic.title }}</Link>
                </Button>
            </template>
        </PageBanner>
        <div class="mx-auto grid max-w-5xl gap-6 p-4 lg:grid-cols-[14rem_1fr]">
            <nav class="lg:sticky lg:top-4 lg:self-start" aria-label="On this page">
                <ul class="space-y-1 text-sm">
                    <li v-for="t in tabs" :key="t.id">
                        <a :href="`#help-${t.id}`" class="block min-h-8 rounded-md px-2.5 py-1 hover:bg-muted">{{ t.title }}</a>
                    </li>
                    <li class="pt-2">
                        <Link :href="route('help.index')" class="block min-h-8 rounded-md px-2.5 py-1 text-muted-foreground hover:bg-muted"
                            >All help</Link
                        >
                    </li>
                </ul>
            </nav>
            <HelpContent :topic="topic" :heading-level="2" />
        </div>
    </template>
</template>
