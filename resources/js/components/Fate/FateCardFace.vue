<script setup lang="ts">
import GameIcon from '@/components/GameIcon.vue';
import { RED_JOKER, cardFace, cardLabel, cardSuit, isJoker } from '@/lib/fate/fate';
import { computed } from 'vue';

/**
 * One Fate card, drawn from its number: its value and suit icon (an original rendering from the M4E-Symbols font, not Wyrd's art), a
 * Red or Black Joker, or a card back. A Red Joker shows the suit its owner chose once it has one.
 */
const props = withDefaults(
    defineProps<{
        card?: number;
        /** Face down: what an opponent's hand looks like. */
        back?: boolean;
        /** A Red Joker's chosen suit. */
        jokerSuit?: string | null;
        /** Ringed: the chosen result, or the card picked for a menu. */
        highlight?: boolean;
        /** Greyed: not a legal choice. */
        dim?: boolean;
        small?: boolean;
        /** Said instead of the card's name (a deck, a pile): the face is what a button around it is named from. */
        label?: string;
        /** What is said after the card's name: where it is, or what pressing it does. */
        note?: string;
        /** The hand's size: big enough to read and tap. */
        large?: boolean;
        /** Value and suit in the top corner, so a card with others stacked over it can still be read. */
        corner?: boolean;
    }>(),
    { card: undefined, jokerSuit: null },
);

const suit = computed(() => (props.card === undefined ? null : (cardSuit(props.card) ?? (props.card === RED_JOKER ? props.jokerSuit : null))));
const black = computed(() => props.card !== undefined && isJoker(props.card) && props.card !== RED_JOKER);
const spoken = computed(() => {
    const name =
        props.label ??
        (props.back || props.card === undefined ? 'Face-down Fate card' : cardLabel(props.card) + (props.jokerSuit ? ` as ${props.jokerSuit}` : ''));
    return props.note ? `${name}, ${props.note}` : name;
});
</script>

<template>
    <span
        class="relative inline-flex shrink-0 select-none flex-col rounded-md border font-semibold shadow-sm"
        :class="[
            corner ? 'items-start justify-start px-1.5 pt-1' : 'items-center justify-center',
            large ? 'h-[4.5rem] w-[3.25rem] text-lg' : small ? 'h-10 w-7 text-xs' : 'h-14 w-10 text-sm',
            back || card === undefined
                ? 'border-slate-500 bg-gradient-to-br from-slate-700 to-slate-900 text-slate-300'
                : black
                  ? 'border-slate-900 bg-slate-900 text-white'
                  : 'border-border bg-white text-slate-900 dark:bg-slate-100',
            highlight ? 'ring-2 ring-primary ring-offset-1 ring-offset-background' : '',
            dim ? 'opacity-40' : '',
        ]"
        role="img"
        :aria-label="spoken"
    >
        <template v-if="back || card === undefined">
            <span aria-hidden="true">♠</span>
        </template>
        <template v-else>
            <span class="leading-none" :class="card === RED_JOKER ? 'text-red-600' : ''" aria-hidden="true">{{ cardFace(card) }}</span>
            <span v-if="suit" aria-hidden="true" class="contents"><GameIcon :type="suit" :size-em="large ? 1.6 : small ? 1 : 1.3" /></span>
        </template>
    </span>
</template>
