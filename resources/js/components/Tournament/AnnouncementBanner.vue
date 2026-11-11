<script setup lang="ts">
import { Megaphone } from 'lucide-vue-next';
import { computed } from 'vue';

/**
 * The organizer's notice, shown above the pairings on the public page and (larger) on display mode.
 * A polite live region, so a screen reader hears a new announcement when the page refreshes.
 */
const props = defineProps<{ text: string; announcedAt?: string | null; large?: boolean }>();

const time = computed(() =>
    props.announcedAt ? new Date(props.announcedAt).toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' }) : null,
);
</script>

<template>
    <div
        role="status"
        aria-live="polite"
        class="flex items-start gap-3 rounded-lg border border-amber-500/50 bg-amber-500/10 text-amber-950 dark:text-amber-100"
        :class="large ? 'p-5 text-2xl sm:text-3xl' : 'p-3 text-sm'"
    >
        <Megaphone class="mt-0.5 shrink-0" :class="large ? 'size-8' : 'size-4'" aria-hidden="true" />
        <p class="min-w-0 flex-1 break-words font-medium">{{ text }}</p>
        <span v-if="time" class="shrink-0 opacity-70" :class="large ? 'text-lg' : 'text-xs'">{{ time }}</span>
    </div>
</template>
