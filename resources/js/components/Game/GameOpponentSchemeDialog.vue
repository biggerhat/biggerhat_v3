<script setup lang="ts">
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';

type DialogMode = 'scored' | 'discard' | 'end-of-game';

interface SchemeOption {
    id: number;
    name: string;
}

defineProps<{
    open: boolean;
    mode: DialogMode;
    schemePool: SchemeOption[];
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'select', schemeId: number): void;
    (e: 'keep-hidden'): void;
    (e: 'cancel'): void;
}>();
</script>

<template>
    <Dialog
        :open="open"
        @update:open="
            (v) => {
                emit('update:open', v);
                if (!v) emit('cancel');
            }
        "
    >
        <DialogContent class="max-w-sm">
            <DialogHeader>
                <DialogTitle>
                    <template v-if="mode === 'scored'">Opponent Scored Scheme VP</template>
                    <template v-else-if="mode === 'end-of-game'">Opponent's Final Scheme</template>
                    <template v-else>Opponent's Scheme</template>
                </DialogTitle>
                <DialogDescription>
                    <template v-if="mode === 'scored'">Which scheme did they score on? Their next pool will derive from this scheme.</template>
                    <template v-else-if="mode === 'end-of-game'">Identify the opponent's scheme for final scoring, or keep hidden.</template>
                    <template v-else
                        >Hold scheme hidden, or select which scheme they're discarding. Their next pool will derive from the discarded scheme.</template
                    >
                </DialogDescription>
            </DialogHeader>

            <div class="space-y-1">
                <!-- Hold hidden (discard + end-of-game only) -->
                <button
                    v-if="mode === 'discard' || mode === 'end-of-game'"
                    class="flex w-full items-center justify-between rounded-md bg-primary/10 px-3 py-2.5 text-left text-sm font-medium hover:bg-primary/20"
                    @click="emit('keep-hidden')"
                >
                    Hold Scheme (Hidden)
                </button>
                <div
                    v-if="(mode === 'discard' || mode === 'end-of-game') && schemePool.length"
                    class="py-1 text-center text-[10px] text-muted-foreground"
                >
                    — or {{ mode === 'discard' ? 'discard' : 'reveal' }} —
                </div>

                <!-- Scheme options from current pool -->
                <button
                    v-for="scheme in schemePool"
                    :key="'opp-id-' + scheme.id"
                    class="flex w-full items-center justify-between rounded-md px-3 py-2 text-left text-sm hover:bg-accent"
                    @click="emit('select', scheme.id)"
                >
                    {{ scheme.name }}
                </button>
            </div>
        </DialogContent>
    </Dialog>
</template>
