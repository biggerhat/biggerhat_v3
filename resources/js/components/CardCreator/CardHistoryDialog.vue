<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { useConfirm } from '@/composables/useConfirm';
import { useToast } from '@/composables/useToast';
import { csrfToken } from '@/lib/utils';
import { History, Loader2 } from 'lucide-vue-next';
import { ref } from 'vue';

export interface CardVersion {
    id: number;
    created_at: string;
    label: string;
}

/**
 * Earlier saved states of a card with a Restore button. Restoring is a normal save on the server (so it can itself be
 * undone), then the editor is reloaded to show it. `dirty` warns that unsaved edits in the open editor will be lost.
 */
const props = defineProps<{ versions: CardVersion[]; restoreUrl: (versionId: number) => string; dirty: boolean }>();
const open = defineModel<boolean>('open', { required: true });

const confirm = useConfirm();
const toast = useToast();
const restoringId = ref<number | null>(null);

const when = (iso: string) => new Date(iso).toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' });

const restore = async (v: CardVersion) => {
    // The shared confirm dialog mounts below this one, so step aside while it is open and come back if cancelled.
    open.value = false;
    const ok = await confirm({
        title: 'Restore this version?',
        message: `Put the card back to "${v.label}" from ${when(v.created_at)}. ${props.dirty ? 'Your unsaved changes in the editor will be lost. ' : ''}The current version is kept in this history.`,
        confirmLabel: 'Restore',
    });
    if (!ok) {
        open.value = true;
        return;
    }
    restoringId.value = v.id;
    try {
        const res = await fetch(props.restoreUrl(v.id), { method: 'POST', headers: { 'X-CSRF-TOKEN': csrfToken(), Accept: 'application/json' } });
        const body = await res.json().catch(() => ({}));
        if (!res.ok || !body.redirect) {
            toast.error('Could not restore that version', { description: body.message ?? 'Please try again.' });
            return;
        }
        // A full load, so the editor starts from the restored card rather than the form state it had open.
        window.location.assign(body.redirect);
    } catch {
        toast.error('Could not restore that version', { description: 'Check your connection and try again.' });
    } finally {
        restoringId.value = null;
    }
};
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="max-w-lg">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2"><History class="size-4" /> Version history</DialogTitle>
                <DialogDescription>The last {{ 10 }} states of this card, from before each save.</DialogDescription>
            </DialogHeader>
            <p v-if="versions.length === 0" class="py-6 text-center text-sm text-muted-foreground">
                No earlier versions yet — they appear after you save changes.
            </p>
            <ul v-else class="max-h-[55vh] divide-y overflow-y-auto">
                <li v-for="v in versions" :key="v.id" class="flex items-center gap-3 py-2">
                    <div class="min-w-0 flex-1">
                        <div class="truncate text-sm font-medium">{{ v.label }}</div>
                        <div class="text-xs text-muted-foreground">{{ when(v.created_at) }}</div>
                    </div>
                    <Button size="sm" variant="outline" :disabled="restoringId !== null" @click="restore(v)">
                        <Loader2 v-if="restoringId === v.id" class="mr-1 size-3.5 animate-spin" /> Restore
                    </Button>
                </li>
            </ul>
        </DialogContent>
    </Dialog>
</template>
