<script setup lang="ts">
import FateCardFace from '@/components/Fate/FateCardFace.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { SUITS, cardSuit } from '@/lib/fate/fate';
import { computed } from 'vue';

/** Looking through the deck for a card: listed by suit and number, never in deck order. Taking one shuffles the deck. */
const props = defineProps<{ open: boolean; cards: number[]; busy?: boolean }>();
const emit = defineEmits<{ 'update:open': [open: boolean]; take: [card: number] }>();

const groups = computed(() => [
    ...SUITS.map((suit) => ({ name: suit, cards: props.cards.filter((c) => cardSuit(c) === suit) })),
    { name: 'jokers', cards: props.cards.filter((c) => cardSuit(c) === null) },
]);
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="max-h-[85vh] max-w-lg overflow-y-auto">
            <div data-testid="fate-search-dialog" class="contents">
                <DialogHeader>
                    <DialogTitle>Search the deck</DialogTitle>
                    <DialogDescription
                        >Take one card into your hand. The deck is shuffled afterwards, so the other player learns only that you
                        searched.</DialogDescription
                    >
                </DialogHeader>
                <section v-for="g in groups" v-show="g.cards.length" :key="g.name" class="space-y-1">
                    <h3 class="text-xs font-medium capitalize text-muted-foreground">{{ g.name }}</h3>
                    <div class="flex flex-wrap gap-1">
                        <button
                            v-for="c in g.cards"
                            :key="c"
                            type="button"
                            :disabled="busy"
                            class="rounded-md focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
                            data-testid="fate-search-card"
                            :data-card="c"
                            @click="emit('take', c)"
                        >
                            <FateCardFace :card="c" small />
                        </button>
                    </div>
                </section>
                <DialogFooter><Button variant="outline" @click="emit('update:open', false)">Close</Button></DialogFooter>
            </div>
        </DialogContent>
    </Dialog>
</template>
