<script setup lang="ts">
import UpgradeFlipCard from '@/components/UpgradeFlipCard.vue';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { computed } from 'vue';

interface CardEntry {
    id: string | number;
    title: string;
    subtitle?: string | null;
    front_image: string | null;
    back_image: string | null;
    /** Optional badge label (e.g. "Crew Upgrade", "Master", "Killed"). */
    badge?: string | null;
    /** Tone for the badge. */
    badgeTone?: 'amber' | 'red' | 'muted' | 'primary';
}

const props = defineProps<{
    open: boolean;
    title: string;
    /** First entry is rendered at the top of the grid; intended for the active
     *  crew upgrade card. The rest follow in member order. Empty entries (no
     *  front_image at all) are skipped — there's nothing to display. */
    entries: CardEntry[];
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
}>();

// Skip entries with no images at all — nothing to render. Wraps DialogContent's
// reactive prop so the grid responds to crew changes (kills, summons, etc).
const renderEntries = computed(() => props.entries.filter((e) => e.front_image || e.back_image));

const badgeClass = (tone?: 'amber' | 'red' | 'muted' | 'primary') => {
    switch (tone) {
        case 'amber':
            return 'border-amber-500/50 bg-amber-500/15 text-amber-700 dark:text-amber-300';
        case 'red':
            return 'border-red-500/50 bg-red-500/10 text-red-700 dark:text-red-300';
        case 'primary':
            return 'border-primary/50 bg-primary/10 text-primary';
        default:
            return 'border-border bg-muted text-muted-foreground';
    }
};
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <!-- DialogContent already renders its own DialogClose at top-right;
             pad the header on the right so the title doesn't collide with it. -->
        <DialogContent class="max-h-[95dvh] max-w-[95vw] overflow-hidden p-0 sm:max-w-[90vw] lg:max-w-[1400px]">
            <DialogHeader class="border-b px-4 py-3 pr-12">
                <DialogTitle class="truncate text-base">{{ title }}</DialogTitle>
            </DialogHeader>

            <div class="overflow-y-auto px-4 py-4" style="max-height: calc(95dvh - 60px)">
                <div v-if="renderEntries.length === 0" class="py-12 text-center text-sm text-muted-foreground">
                    No card images available for this crew.
                </div>
                <div
                    v-else
                    class="grid gap-4"
                    :class="{
                        'sm:grid-cols-2': renderEntries.length >= 2,
                        'lg:grid-cols-3': renderEntries.length >= 3,
                        'xl:grid-cols-4': renderEntries.length >= 4,
                    }"
                >
                    <div v-for="entry in renderEntries" :key="entry.id" class="space-y-1.5">
                        <div class="flex items-center justify-between gap-2">
                            <div class="min-w-0">
                                <div class="truncate text-sm font-semibold">{{ entry.title }}</div>
                                <div v-if="entry.subtitle" class="truncate text-[11px] italic text-muted-foreground">{{ entry.subtitle }}</div>
                            </div>
                            <span
                                v-if="entry.badge"
                                class="shrink-0 rounded border px-1.5 py-0 text-[9px] font-medium uppercase tracking-wider"
                                :class="badgeClass(entry.badgeTone)"
                            >{{ entry.badge }}</span>
                        </div>
                        <UpgradeFlipCard
                            v-if="entry.front_image"
                            :front-image="entry.front_image"
                            :back-image="entry.back_image"
                            :alt-text="entry.title"
                            :show-link="false"
                        />
                    </div>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>
