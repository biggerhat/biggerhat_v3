<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Check } from 'lucide-vue-next';

interface Replacement {
    id: number;
    display_name: string;
    front_image: string | null;
    count: number;
    selected: boolean;
}

const props = defineProps<{
    open: boolean;
    replacements: Replacement[];
    warnings: string[];
    hasSelected: boolean;
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'toggle', replacementId: number): void;
    (e: 'confirm'): void;
    (e: 'dismiss'): void;
}>();

const descriptionText = () =>
    props.replacements.length > 1
        ? 'Select which models to add to the crew.'
        : 'This model replaces into the following when killed.';
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="max-w-sm">
            <DialogHeader>
                <DialogTitle>Replace on Death</DialogTitle>
                <DialogDescription>{{ descriptionText() }}</DialogDescription>
            </DialogHeader>

            <div class="space-y-1.5">
                <button
                    v-for="replacement in replacements"
                    :key="replacement.id"
                    class="flex w-full items-center gap-3 rounded-lg border p-2 text-left transition-colors"
                    :class="replacement.selected ? 'border-primary bg-primary/5' : 'opacity-50'"
                    @click="emit('toggle', replacement.id)"
                >
                    <div
                        class="flex size-5 shrink-0 items-center justify-center rounded border"
                        :class="replacement.selected ? 'border-primary bg-primary text-primary-foreground' : 'border-muted-foreground/30'"
                    >
                        <Check v-if="replacement.selected" class="size-3" />
                    </div>
                    <img
                        v-if="replacement.front_image"
                        :src="replacement.front_image"
                        :alt="replacement.display_name"
                        class="size-10 shrink-0 rounded object-cover"
                    />
                    <div class="min-w-0 flex-1">
                        <div class="text-sm font-medium">{{ replacement.display_name }}</div>
                        <div v-if="replacement.count > 1" class="text-xs text-muted-foreground">&times;{{ replacement.count }}</div>
                    </div>
                </button>
            </div>

            <div v-if="warnings.length" class="space-y-1">
                <div
                    v-for="(warn, i) in warnings"
                    :key="i"
                    class="rounded-md border border-amber-500/30 bg-amber-500/5 px-3 py-1.5 text-xs text-amber-700 dark:text-amber-400"
                >
                    {{ warn }}
                </div>
                <p class="text-xs text-muted-foreground">Select a different option and try again, or close.</p>
            </div>

            <DialogFooter class="gap-2 sm:gap-0">
                <Button variant="outline" @click="emit('dismiss')">{{ warnings.length ? 'Close' : 'Skip All' }}</Button>
                <Button :disabled="!hasSelected" @click="emit('confirm')"> Add Selected </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
