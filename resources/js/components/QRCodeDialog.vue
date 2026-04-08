<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import QRCode from 'qrcode';
import { ref, watch } from 'vue';

const props = defineProps<{
    open: boolean;
    url: string;
    title?: string;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
}>();

const qrDataUrl = ref('');

watch(
    () => props.open,
    async (isOpen) => {
        if (isOpen && props.url) {
            qrDataUrl.value = await QRCode.toDataURL(props.url, {
                width: 300,
                margin: 2,
                color: { dark: '#000000', light: '#ffffff' },
            });
        }
    },
    { immediate: true },
);

const linkCopied = ref(false);
const copyLink = async () => {
    await navigator.clipboard.writeText(props.url);
    linkCopied.value = true;
    setTimeout(() => (linkCopied.value = false), 2000);
};
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="max-w-xs">
            <DialogHeader>
                <DialogTitle class="text-center">{{ title || 'Share Link' }}</DialogTitle>
            </DialogHeader>
            <div class="flex flex-col items-center gap-3">
                <img v-if="qrDataUrl" :src="qrDataUrl" alt="QR Code" class="rounded-lg" />
                <p class="break-all text-center text-xs text-muted-foreground">{{ url }}</p>
            </div>
            <DialogFooter class="gap-2 sm:gap-0">
                <Button variant="outline" class="w-full" @click="copyLink">
                    {{ linkCopied ? 'Copied!' : 'Copy Link' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
