<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { _promptState, _resolvePrompt } from '@/composables/usePrompt';
import { computed, ref, watch } from 'vue';

const state = _promptState();
const value = ref('');

watch(
    () => state.value.open,
    (next) => {
        if (next) value.value = state.value.options.defaultValue ?? '';
    },
    { immediate: true },
);

const open = computed({
    get: () => state.value.open,
    set: (v) => {
        if (!v) _resolvePrompt(null);
    },
});

const onCancel = () => _resolvePrompt(null);
const onConfirm = () => _resolvePrompt(value.value);
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>{{ state.options.title ?? 'Enter a value' }}</DialogTitle>
                <DialogDescription v-if="state.options.message">{{ state.options.message }}</DialogDescription>
            </DialogHeader>
            <Input
                v-model="value"
                :placeholder="state.options.placeholder ?? ''"
                autofocus
                @keydown.enter="onConfirm"
            />
            <DialogFooter class="gap-2 sm:gap-0">
                <Button variant="ghost" @click="onCancel">{{ state.options.cancelLabel ?? 'Cancel' }}</Button>
                <Button @click="onConfirm">{{ state.options.confirmLabel ?? 'OK' }}</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
