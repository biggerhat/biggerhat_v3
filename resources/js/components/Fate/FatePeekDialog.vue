<script setup lang="ts">
import FateCardFace from '@/components/Fate/FateCardFace.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { cardLabel } from '@/lib/fate/fate';
import { ArrowDown, ArrowUp } from 'lucide-vue-next';
import { ref, watch } from 'vue';

/**
 * The cards just looked at on top of the deck, and what to do with each: back on top (in the order shown), to the bottom, to the
 * discard pile (face up) or to the hand. Nothing here is told to the other player except what reaches the discard pile.
 */
const props = defineProps<{ open: boolean; cards: number[]; busy?: boolean }>();
const emit = defineEmits<{
    'update:open': [open: boolean];
    arrange: [decisions: { card: number; to: string }[]];
}>();

type To = 'top' | 'bottom' | 'discard' | 'hand';
const rows = ref<{ card: number; to: To }[]>([]);
watch(
    () => [props.open, props.cards] as const,
    () => (rows.value = props.cards.map((card) => ({ card, to: 'top' as To }))),
    { immediate: true },
);
const swap = (i: number, j: number) => {
    const next = [...rows.value];
    [next[i], next[j]] = [next[j], next[i]];
    rows.value = next;
};
const destinations: { to: To; label: string }[] = [
    { to: 'top', label: 'Top' },
    { to: 'bottom', label: 'Bottom' },
    { to: 'discard', label: 'Discard' },
    { to: 'hand', label: 'Hand' },
];
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="max-w-md">
            <div data-testid="fate-peek-dialog" class="contents">
                <DialogHeader>
                    <DialogTitle>Top of the deck</DialogTitle>
                    <DialogDescription>
                        Only you see these. The first card listed ends up on top. Cards you discard go face up to the discard pile.
                    </DialogDescription>
                </DialogHeader>
                <ol class="space-y-2">
                    <li v-for="(r, i) in rows" :key="r.card" class="flex items-center gap-2" data-testid="fate-peek-row">
                        <span class="w-5 text-right text-xs text-muted-foreground">{{ i + 1 }}</span>
                        <FateCardFace :card="r.card" small />
                        <span class="min-w-0 flex-1 truncate text-sm">{{ cardLabel(r.card) }}</span>
                        <select
                            v-model="r.to"
                            class="rounded-md border bg-background px-1.5 py-1 text-sm"
                            :aria-label="`What to do with ${cardLabel(r.card)}`"
                        >
                            <option v-for="d in destinations" :key="d.to" :value="d.to">{{ d.label }}</option>
                        </select>
                        <Button size="sm" variant="ghost" class="px-1.5" :disabled="i === 0" aria-label="Move up" @click="swap(i, i - 1)"
                            ><ArrowUp class="size-4"
                        /></Button>
                        <Button
                            size="sm"
                            variant="ghost"
                            class="px-1.5"
                            :disabled="i === rows.length - 1"
                            aria-label="Move down"
                            @click="swap(i, i + 1)"
                            ><ArrowDown class="size-4"
                        /></Button>
                    </li>
                </ol>
                <DialogFooter>
                    <Button variant="outline" @click="emit('update:open', false)">Leave them as they were</Button>
                    <Button
                        :disabled="busy"
                        data-testid="fate-peek-apply"
                        @click="
                            emit(
                                'arrange',
                                rows.map((r) => ({ card: r.card, to: r.to })),
                            )
                        "
                        >Apply</Button
                    >
                </DialogFooter>
            </div>
        </DialogContent>
    </Dialog>
</template>
