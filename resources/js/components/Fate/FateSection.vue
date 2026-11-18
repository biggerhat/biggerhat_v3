<script setup lang="ts">
import FatePanel from '@/components/Fate/FatePanel.vue';
import type { FateFeed } from '@/lib/fate/fate';
import { ChevronDown } from 'lucide-vue-next';
import { ref } from 'vue';

/**
 * The Fate deck as a shut-by-default section. Nothing is fetched (a game's deck) or built until it is first opened, so a table that
 * never uses it pays nothing, which matters on a phone. The feed's start() is what loads and listens.
 */
const props = defineProps<{
    feed: FateFeed & { start?: () => Promise<void> };
    names?: Record<number, string>;
    readOnly?: boolean;
    title?: string;
}>();

const opened = ref(false);
const open = ref(false);
function onToggle(event: Event) {
    open.value = (event.target as HTMLDetailsElement).open;
    if (open.value && !opened.value) {
        opened.value = true;
        void props.feed.start?.();
    }
}
</script>

<template>
    <details class="group rounded-md border" data-testid="fate-section" @toggle="onToggle">
        <summary class="flex cursor-pointer list-none items-center justify-between gap-2 p-3 text-sm [&::-webkit-details-marker]:hidden">
            <span class="font-semibold">{{ title ?? 'Fate deck' }}</span>
            <ChevronDown class="size-4 shrink-0 transition-transform group-open:rotate-180" />
        </summary>
        <div v-if="opened" class="border-t p-3">
            <FatePanel :feed="feed" :names="names" :read-only="readOnly" />
        </div>
    </details>
</template>
