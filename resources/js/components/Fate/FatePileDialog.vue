<script setup lang="ts">
import FateCardFace from '@/components/Fate/FateCardFace.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { cardLabel } from '@/lib/fate/fate';
import { computed, ref, watch } from 'vue';

/**
 * A whole discard pile, face up, newest first. The pile is public, so anyone may look; the player it belongs to may also pick a card
 * and take it back into the hand, put it on the deck, or set it aside (removed from the game).
 */
const props = defineProps<{ open: boolean; title: string; cards: number[]; canAct: boolean; busy?: boolean }>();
const emit = defineEmits<{ 'update:open': [open: boolean]; act: [name: string, body: Record<string, unknown>] }>();

const picked = ref<number | null>(null);
watch(
    () => [props.open, props.cards],
    () => {
        if (picked.value !== null && !props.cards.includes(picked.value)) picked.value = null;
    },
);
const newestFirst = computed(() => [...props.cards].reverse());
const act = (name: string, body: Record<string, unknown>) => {
    emit('act', name, body);
    picked.value = null;
};
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="max-h-[85vh] max-w-md overflow-y-auto">
            <div data-testid="fate-pile-dialog" class="contents">
                <DialogHeader>
                    <DialogTitle>{{ title }}</DialogTitle>
                    <DialogDescription>
                        Face up, newest first.{{ canAct ? ' Pick a card to take it back, put it on the deck or set it aside.' : '' }}
                    </DialogDescription>
                </DialogHeader>
                <p v-if="!cards.length" class="text-sm text-muted-foreground">Nothing here.</p>
                <div v-else class="flex flex-wrap gap-1.5">
                    <component
                        :is="canAct ? 'button' : 'span'"
                        v-for="c in newestFirst"
                        :key="c"
                        :type="canAct ? 'button' : undefined"
                        class="rounded-md focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
                        data-testid="fate-pile-card"
                        :data-card="c"
                        @click="canAct && (picked = picked === c ? null : c)"
                    >
                        <FateCardFace :card="c" :note="canAct ? 'in the discard pile' : undefined" :highlight="picked === c" />
                    </component>
                </div>
                <div
                    v-if="picked !== null"
                    class="flex flex-wrap gap-1.5 rounded-md border border-primary/40 bg-primary/5 p-2"
                    data-testid="fate-pile-menu"
                >
                    <span class="w-full text-sm font-medium">{{ cardLabel(picked) }}</span>
                    <Button size="sm" :disabled="busy" data-testid="fate-pile-take" @click="act('draw_from_discard', { card: picked })"
                        >Take into hand</Button
                    >
                    <Button size="sm" variant="outline" :disabled="busy" @click="act('put', { card: picked, where: 'top' })">Top of deck</Button>
                    <Button size="sm" variant="outline" :disabled="busy" @click="act('put', { card: picked, where: 'bottom' })"
                        >Bottom of deck</Button
                    >
                    <Button size="sm" variant="outline" :disabled="busy" @click="act('remove', { cards: [picked] })">Remove from game</Button>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>
