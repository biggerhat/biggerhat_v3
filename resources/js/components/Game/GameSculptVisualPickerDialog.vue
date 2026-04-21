<script setup lang="ts">
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Check } from 'lucide-vue-next';

interface Miniature {
    id: number;
    display_name: string;
    front_image: string | null;
}

defineProps<{
    open: boolean;
    miniatures: Miniature[];
    /** Id of the currently-selected sculpt, highlighted with a ring + check. */
    selectedId: string | number | null;
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'select', miniatureId: number): void;
}>();
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <!-- Flex column so the sticky header and scrollable grid partition the
             available viewport height. Without `flex` + `min-h-0` on the grid
             wrapper, mobile viewports would let the grid grow past the dialog
             and the inner `overflow-y-auto` would never kick in. -->
        <DialogContent class="flex max-h-[90dvh] max-w-4xl flex-col overflow-hidden p-0 sm:p-0">
            <DialogHeader class="shrink-0 border-b p-4 pb-3">
                <DialogTitle>Choose Sculpt</DialogTitle>
                <DialogDescription>Browse all sculpt variants for this character. Click one to apply.</DialogDescription>
            </DialogHeader>
            <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain p-4">
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4">
                    <button
                        v-for="mini in miniatures"
                        :key="mini.id"
                        type="button"
                        class="group relative flex flex-col overflow-hidden rounded-lg border-2 bg-muted/30 text-left transition-all hover:shadow-md"
                        :class="
                            String(mini.id) === String(selectedId)
                                ? 'border-primary shadow-sm shadow-primary/20'
                                : 'border-transparent hover:border-primary/40'
                        "
                        :aria-pressed="String(mini.id) === String(selectedId)"
                        @click="emit('select', mini.id)"
                    >
                        <div class="relative aspect-[550/950] w-full overflow-hidden bg-black/5">
                            <img
                                v-if="mini.front_image"
                                :src="'/storage/' + mini.front_image"
                                :alt="mini.display_name"
                                class="h-full w-full object-cover"
                                loading="lazy"
                                decoding="async"
                            />
                            <div
                                v-else
                                class="flex h-full w-full items-center justify-center text-xs text-muted-foreground"
                            >
                                No image
                            </div>
                            <div
                                v-if="String(mini.id) === String(selectedId)"
                                class="absolute right-1.5 top-1.5 flex size-6 items-center justify-center rounded-full bg-primary text-primary-foreground shadow-md"
                                aria-hidden="true"
                            >
                                <Check class="size-3.5" />
                            </div>
                        </div>
                        <div
                            class="px-2 py-1.5 text-center text-[11px] font-medium leading-tight"
                            :class="String(mini.id) === String(selectedId) ? 'bg-primary/10 text-foreground' : 'bg-muted/40 text-muted-foreground'"
                        >
                            {{ mini.display_name }}
                        </div>
                    </button>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>
