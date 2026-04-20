<script setup lang="ts">
import { Dialog, DialogContent, DialogTitle } from '@/components/ui/dialog';

defineProps<{
    open: boolean;
    src: string | null;
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
}>();
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="fullscreen-card-dialog max-h-[95dvh] max-w-[95vw] border-none bg-black/95 p-2 sm:max-w-fit sm:p-4">
            <DialogTitle class="sr-only">Card Preview</DialogTitle>
            <div class="flex items-center justify-center">
                <img v-if="src" :src="src" alt="Card" class="max-h-[90dvh] w-auto rounded-lg object-contain" />
            </div>
        </DialogContent>
    </Dialog>
</template>
