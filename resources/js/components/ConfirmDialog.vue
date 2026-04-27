<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { _confirmState, _resolveConfirm } from '@/composables/useConfirm';
import { computed } from 'vue';

const state = _confirmState();
const open = computed({
    get: () => state.value.open,
    set: (v) => {
        if (!v) _resolveConfirm(false);
    },
});

const onCancel = () => _resolveConfirm(false);
const onConfirm = () => _resolveConfirm(true);
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>{{ state.options.title ?? 'Are you sure?' }}</DialogTitle>
                <DialogDescription class="whitespace-pre-line">{{ state.options.message }}</DialogDescription>
            </DialogHeader>
            <DialogFooter class="gap-2 sm:gap-0">
                <Button variant="ghost" @click="onCancel">{{ state.options.cancelLabel ?? 'Cancel' }}</Button>
                <Button :variant="state.options.destructive ? 'destructive' : 'default'" @click="onConfirm">
                    {{ state.options.confirmLabel ?? 'Confirm' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
