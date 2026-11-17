<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { useToast } from '@/composables/useToast';
import type { TerrainPiece } from '@/lib/tabletop/geometry';
import type { LayoutSaveTarget, SavedLayout } from '@/lib/tabletop/layouts';
import type { RequestPayload } from '@inertiajs/core';
import { Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

/**
 * Keep the table on the board as one of the viewer's own tables: a name and who can see it. The sandbox keeps what is on screen; a game
 * board keeps the game's own table (which a watcher of an observable game may also do).
 */
const props = defineProps<{
    open: boolean;
    terrain: TerrainPiece[];
    tableImage: string | null;
    target: LayoutSaveTarget;
    signedIn: boolean;
}>();
const emit = defineEmits<{ 'update:open': [open: boolean] }>();

const toast = useToast();
const name = ref('');
const visibility = ref<SavedLayout['visibility']>('private');
const busy = ref(false);
const error = ref('');

// start each time from a clean form
watch(
    () => props.open,
    (open) => {
        if (!open) return;
        name.value = '';
        visibility.value = 'private';
        error.value = '';
    },
);

function save() {
    const title = name.value.trim();
    if (!title || !props.terrain.length || busy.value) return;
    busy.value = true;
    error.value = '';
    const done = {
        preserveScroll: true,
        preserveState: true,
        only: ['layouts'] as string[],
        onSuccess: () => {
            toast.success(`Saved "${title}"`, { description: 'You will find it under Tools > Tabletop.' });
            emit('update:open', false);
        },
        onError: (errors: Record<string, string>) => {
            error.value = Object.values(errors)[0] ?? 'That did not work';
        },
        onFinish: () => (busy.value = false),
    };
    const body = { name: title, visibility: visibility.value };
    if (props.target.kind === 'game') router.post(route('games.board.layout', props.target.uuid), body, done);
    else
        router.post(
            route('tools.tabletop.layouts.store'),
            { ...body, terrain: props.terrain, table_image: props.tableImage } as unknown as RequestPayload,
            done,
        );
}
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="sm:max-w-md">
            <div data-testid="save-dialog" class="contents">
                <DialogHeader>
                    <DialogTitle>Save this table</DialogTitle>
                    <DialogDescription>
                        {{
                            target.kind === 'game'
                                ? "Keeps this game's table (terrain and surface) as one of your own."
                                : 'Keeps the terrain and surface on the board as one of your own.'
                        }}
                    </DialogDescription>
                </DialogHeader>

                <p v-if="!signedIn" class="text-sm text-muted-foreground">
                    <Link :href="route('login')" class="underline">Sign in</Link> to keep tables of your own.
                </p>
                <form v-else class="space-y-3" @submit.prevent="save">
                    <label class="block text-sm">
                        <span class="mb-1 block font-medium">Name</span>
                        <input
                            v-model="name"
                            maxlength="80"
                            placeholder="e.g. City streets"
                            class="w-full rounded-md border bg-background px-2 py-1.5"
                            :aria-describedby="error ? 'save-table-error' : undefined"
                            data-testid="save-table-name"
                        />
                    </label>
                    <label class="block text-sm">
                        <span class="mb-1 block font-medium">Who can see it</span>
                        <select v-model="visibility" class="w-full rounded-md border bg-background px-2 py-1.5" data-testid="save-table-visibility">
                            <option value="private">Private (only you)</option>
                            <option value="link">Anyone with the link</option>
                            <option value="public">Public (listed in the gallery)</option>
                        </select>
                    </label>
                    <p v-if="!terrain.length" class="text-sm text-muted-foreground">There is no terrain on the table to save yet.</p>
                    <p v-if="error" id="save-table-error" class="text-sm text-destructive" role="alert" data-testid="save-table-error">{{ error }}</p>
                    <div class="flex justify-end gap-2">
                        <Button type="button" variant="outline" size="sm" @click="emit('update:open', false)">Cancel</Button>
                        <Button type="submit" size="sm" :disabled="busy || !name.trim() || !terrain.length" data-testid="save-table-confirm"
                            >Save table</Button
                        >
                    </div>
                </form>
            </div>
        </DialogContent>
    </Dialog>
</template>
