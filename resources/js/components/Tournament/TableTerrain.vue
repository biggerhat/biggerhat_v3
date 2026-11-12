<script setup lang="ts">
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { tableLabel, type TableDetail } from '@/types/tournament';
import { ref } from 'vue';

/** One table's name, terrain notes and photo/layout; the photo opens full size. */
defineProps<{ detail: TableDetail; showTitle?: boolean }>();

const open = ref(false);
</script>

<template>
    <div class="flex gap-3">
        <button
            v-if="detail.image_url"
            type="button"
            class="size-20 shrink-0 overflow-hidden rounded-md border focus-visible:outline focus-visible:outline-2 focus-visible:outline-ring sm:size-24"
            :aria-label="`Open the photo for ${tableLabel(detail.number, { [detail.number]: detail })}`"
            @click="open = true"
        >
            <img
                :src="detail.image_url"
                :alt="`${tableLabel(detail.number, { [detail.number]: detail })} terrain`"
                class="size-full object-cover"
                loading="lazy"
            />
        </button>
        <div class="min-w-0 space-y-1 text-sm">
            <div v-if="showTitle !== false" class="font-medium">{{ tableLabel(detail.number, { [detail.number]: detail }) }}</div>
            <p v-if="detail.description" class="whitespace-pre-line text-muted-foreground">{{ detail.description }}</p>
        </div>
        <Dialog v-if="detail.image_url" v-model:open="open">
            <DialogContent class="max-w-3xl">
                <DialogHeader>
                    <DialogTitle>{{ tableLabel(detail.number, { [detail.number]: detail }) }}</DialogTitle>
                    <DialogDescription v-if="detail.description" class="whitespace-pre-line">{{ detail.description }}</DialogDescription>
                    <DialogDescription v-else class="sr-only">Terrain photo</DialogDescription>
                </DialogHeader>
                <img
                    :src="detail.image_url"
                    :alt="`${tableLabel(detail.number, { [detail.number]: detail })} terrain`"
                    class="max-h-[70vh] w-full rounded-md object-contain"
                />
            </DialogContent>
        </Dialog>
    </div>
</template>
