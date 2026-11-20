<script setup lang="ts">
import FateCardFace from '@/components/Fate/FateCardFace.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { computed, ref, watch } from 'vue';

/**
 * The start phase (CRG, Turn sequence): discard any number of cards from the hand, then draw until the hand is at its maximum. The cards
 * picked here are shown to everyone as they go to the discard pile; what is drawn is only the owner's to see.
 */
const props = defineProps<{ open: boolean; hand: number[]; handMax: number; busy?: boolean }>();
const emit = defineEmits<{ 'update:open': [open: boolean]; apply: [discard: number[]] }>();

const picked = ref<number[]>([]);
watch(
    () => props.open,
    (open) => {
        if (open) picked.value = [];
    },
);
const toggle = (card: number) => (picked.value = picked.value.includes(card) ? picked.value.filter((c) => c !== card) : [...picked.value, card]);
/** How many cards will be drawn: up to the maximum after the discards. */
const draws = computed(() => Math.max(0, props.handMax - (props.hand.length - picked.value.length)));
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="max-w-md">
            <div data-testid="fate-start-dialog" class="contents">
                <DialogHeader>
                    <DialogTitle>Start phase</DialogTitle>
                    <DialogDescription>
                        Tap the cards you want to discard, then draw up to {{ handMax }}. Discarded cards go face up to the discard pile.
                    </DialogDescription>
                </DialogHeader>
                <div class="flex flex-wrap gap-1.5">
                    <button
                        v-for="c in hand"
                        :key="c"
                        type="button"
                        class="rounded-md transition-transform focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
                        :class="picked.includes(c) ? '-translate-y-1.5' : ''"
                        :aria-pressed="picked.includes(c)"
                        data-testid="fate-start-card"
                        :data-card="c"
                        @click="toggle(c)"
                    >
                        <FateCardFace :card="c" :note="picked.includes(c) ? 'to discard' : 'in hand'" :highlight="picked.includes(c)" />
                    </button>
                    <span v-if="!hand.length" class="py-3 text-sm text-muted-foreground">No cards in hand.</span>
                </div>
                <p class="text-sm text-muted-foreground" data-testid="fate-start-summary">Discard {{ picked.length }}, draw {{ draws }}.</p>
                <DialogFooter>
                    <Button variant="outline" @click="emit('update:open', false)">Cancel</Button>
                    <Button :disabled="busy" data-testid="fate-start-apply" @click="emit('apply', picked)">Discard and draw</Button>
                </DialogFooter>
            </div>
        </DialogContent>
    </Dialog>
</template>
