<script setup lang="ts">
// Built on raw radix-vue primitives (not @/components/ui/dialog) because
// the project's DialogContent wrapper hard-codes a <DialogClose> X button
// that overlaps card art. Going one layer lower lets us suppress it.
import { FlipHorizontal, X } from 'lucide-vue-next';
import { DialogClose, DialogContent, DialogDescription, DialogOverlay, DialogPortal, DialogRoot, DialogTitle } from 'radix-vue';
import { computed, ref, watch } from 'vue';

const props = withDefaults(
    defineProps<{
        open: boolean;
        src: string | null;
        backSrc?: string | null;
        title?: string | null;
    }>(),
    { backSrc: null, title: null },
);

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
}>();

const showingBack = ref(false);

// Reset to front each time the dialog opens.
watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) showingBack.value = false;
    },
);

const hasFlip = computed(() => !!props.backSrc);
const currentSrc = computed(() => (showingBack.value && props.backSrc ? props.backSrc : props.src));
const sideLabel = computed(() => (showingBack.value ? 'Back' : 'Front'));
const headerTitle = computed(() => {
    if (!props.title) return sideLabel.value;
    return hasFlip.value ? `${props.title} · ${sideLabel.value}` : props.title;
});
</script>

<template>
    <DialogRoot :open="open" @update:open="emit('update:open', $event)">
        <DialogPortal>
            <DialogOverlay
                class="fixed inset-0 z-50 bg-black/85 data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0"
            />
            <!-- Fixed h/w gives the flex-1 image container a concrete area —
                 max-h/max-w would let the dialog auto-shrink to small images. -->
            <DialogContent
                class="fixed left-1/2 top-1/2 z-50 flex h-[95dvh] w-[95vw] max-w-[1400px] -translate-x-1/2 -translate-y-1/2 flex-col gap-2 outline-none data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0"
            >
                <div class="flex items-center justify-between gap-3 rounded-lg bg-background/95 px-3 py-2 shadow-lg backdrop-blur sm:px-4">
                    <DialogTitle class="min-w-0 truncate text-sm font-medium">{{ headerTitle }}</DialogTitle>
                    <DialogDescription class="sr-only">Fullscreen card preview.</DialogDescription>
                    <div class="flex shrink-0 items-center gap-1">
                        <button
                            v-if="hasFlip"
                            type="button"
                            class="inline-flex items-center gap-1.5 rounded-md border bg-background px-2.5 py-1 text-xs font-medium transition-colors hover:bg-muted"
                            :aria-label="showingBack ? 'Show front' : 'Show back'"
                            @click="showingBack = !showingBack"
                        >
                            <FlipHorizontal class="size-3.5" />
                            <span>{{ showingBack ? 'Front' : 'Back' }}</span>
                        </button>
                        <DialogClose
                            class="inline-flex size-8 items-center justify-center rounded-md text-foreground/80 transition-colors hover:bg-muted hover:text-foreground"
                            aria-label="Close"
                        >
                            <X class="size-4" />
                        </DialogClose>
                    </div>
                </div>

                <!-- h-full w-full + object-contain scales BOTH ways:
                     small images upscale to fill, large images downscale. -->
                <div
                    class="flex min-h-0 flex-1 items-center justify-center overflow-hidden"
                    :class="hasFlip ? 'cursor-pointer' : ''"
                    @click="hasFlip ? (showingBack = !showingBack) : undefined"
                >
                    <img
                        v-if="currentSrc"
                        :src="currentSrc"
                        :alt="headerTitle"
                        loading="lazy"
                        decoding="async"
                        class="h-full w-full rounded-lg object-contain"
                    />
                </div>
            </DialogContent>
        </DialogPortal>
    </DialogRoot>
</template>
