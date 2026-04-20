<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';

defineProps<{
    open: boolean;
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'confirm'): void;
}>();
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="max-w-sm">
            <DialogHeader>
                <DialogTitle>Complete Game</DialogTitle>
                <DialogDescription>
                    Are you sure you want to mark this game as complete? Final scores will be calculated and the game will end.
                </DialogDescription>
            </DialogHeader>
            <DialogFooter class="gap-2 sm:gap-0">
                <Button variant="outline" @click="emit('update:open', false)">Cancel</Button>
                <Button @click="emit('confirm')">Complete Game</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
