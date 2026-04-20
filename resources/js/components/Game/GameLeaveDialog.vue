<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';

defineProps<{
    open: boolean;
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'stay'): void;
    (e: 'leave'): void;
}>();
</script>

<template>
    <Dialog
        :open="open"
        @update:open="
            (v) => {
                if (!v) emit('stay');
            }
        "
    >
        <DialogContent class="max-w-sm">
            <DialogHeader>
                <DialogTitle>Leave game?</DialogTitle>
                <DialogDescription> Your game is still in progress. If you leave, you can come back to it later from My Games. </DialogDescription>
            </DialogHeader>
            <DialogFooter class="flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <Button variant="outline" @click="emit('stay')">Stay</Button>
                <Button variant="destructive" @click="emit('leave')">Leave</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
