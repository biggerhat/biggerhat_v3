<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useTournament } from '@/composables/useTournament';
import { Loader2 } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

/** Post or clear the one-line announcement everyone watching the event sees. */
const props = defineProps<{ open: boolean; tournament: { uuid: string; announcement?: string | null } }>();
const emit = defineEmits<{ (e: 'update:open', value: boolean): void; (e: 'saved'): void }>();

const text = ref('');
const { submitting, doModalAction } = useTournament(computed(() => props.tournament as any));
const error = ref<string | null>(null);

watch(
    () => props.open,
    (open) => {
        if (open) {
            text.value = props.tournament.announcement ?? '';
            error.value = null;
        }
    },
);

const save = async (value: string) => {
    submitting.value = true;
    error.value = null;
    const { ok, error: message } = await doModalAction(route('tournaments.announcement', props.tournament.uuid), 'PUT', { announcement: value });
    submitting.value = false;
    if (!ok) {
        error.value = message;
        return;
    }
    emit('saved');
    emit('update:open', false);
};
</script>

<template>
    <Dialog :open="open" @update:open="(v) => emit('update:open', v)">
        <DialogContent class="max-w-md">
            <DialogHeader>
                <DialogTitle>Announcement</DialogTitle>
                <DialogDescription>Shown above the pairings for everyone following the event, and on display mode.</DialogDescription>
            </DialogHeader>
            <div class="space-y-1.5">
                <Label for="announcement-text" class="text-xs">Message</Label>
                <Textarea
                    id="announcement-text"
                    v-model="text"
                    rows="3"
                    maxlength="500"
                    placeholder="Lunch until 1:15 — round 3 starts at 1:30"
                    @keydown.meta.enter="save(text)"
                    @keydown.ctrl.enter="save(text)"
                />
                <p class="text-right text-2xs text-muted-foreground">{{ text.length }}/500</p>
                <p v-if="error" class="text-xs text-destructive" role="alert">{{ error }}</p>
            </div>
            <DialogFooter class="gap-2 sm:gap-0">
                <Button v-if="tournament.announcement" variant="outline" :disabled="submitting" @click="save('')">Clear</Button>
                <Button :disabled="submitting || !text.trim()" @click="save(text)">
                    <Loader2 v-if="submitting" class="mr-2 size-4 animate-spin" />
                    Post
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
