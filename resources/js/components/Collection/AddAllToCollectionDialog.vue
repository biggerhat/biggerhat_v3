<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Library, Loader2 } from 'lucide-vue-next';

defineProps<{
    open: boolean;
    count: number;
    scope: string;
    submitting: boolean;
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
                <DialogTitle class="flex items-center gap-2">
                    <Library class="size-5 text-primary" />
                    Add all to collection?
                </DialogTitle>
                <DialogDescription>
                    Add {{ count }} {{ count === 1 ? 'character' : 'characters' }} from
                    <span class="font-medium text-foreground">{{ scope }}</span> to your collection. You can remove any of them later.
                </DialogDescription>
            </DialogHeader>
            <DialogFooter class="gap-2 sm:gap-0">
                <Button variant="outline" :disabled="submitting" @click="emit('update:open', false)">Cancel</Button>
                <Button :disabled="submitting" @click="emit('confirm')">
                    <Loader2 v-if="submitting" class="mr-2 size-4 animate-spin" />
                    Add {{ count }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
