<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { useConfirm } from '@/composables/useConfirm';
import { useToast } from '@/composables/useToast';
import type { TerrainPiece } from '@/lib/tabletop/geometry';
import type { LayoutSaveTarget, SavedLayout } from '@/lib/tabletop/layouts';
import type { RequestPayload } from '@inertiajs/core';
import { Link, router } from '@inertiajs/vue3';
import { Copy, Download, Link2, Save, Share2, Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';

const props = defineProps<{
    layouts: SavedLayout[];
    /** The table as it is now, to save. */
    terrain: TerrainPiece[];
    tableImage: string | null;
    target: LayoutSaveTarget;
    signedIn: boolean;
    /** Loading would change a table the viewer may not change. */
    readOnly?: boolean;
}>();
const emit = defineEmits<{ load: [layout: SavedLayout] }>();

const confirm = useConfirm();
const toast = useToast();
const name = ref('');
const busy = ref(false);

const refresh = { preserveScroll: true, preserveState: true, only: ['layouts'] as string[] };
const failed = (errors: Record<string, string>) => {
    toast.error(Object.values(errors)[0] ?? 'That did not work');
};

function saveNew() {
    const title = name.value.trim();
    if (!title || !props.terrain.length) return;
    busy.value = true;
    const done = {
        ...refresh,
        onSuccess: () => {
            name.value = '';
            toast.success(`Saved "${title}"`);
        },
        onError: failed,
        onFinish: () => (busy.value = false),
    };
    if (props.target.kind === 'game') router.post(route('games.board.layout', props.target.uuid), { name: title }, done);
    else
        router.post(
            route('tools.tabletop.layouts.store'),
            { name: title, terrain: props.terrain, table_image: props.tableImage } as unknown as RequestPayload,
            done,
        );
}

const update = (l: SavedLayout, data: RequestPayload, message?: string) =>
    router.put(route('tools.tabletop.layouts.update', l.uuid), data, {
        ...refresh,
        onSuccess: () => {
            if (message) toast.success(message);
        },
        onError: failed,
    });

async function overwrite(l: SavedLayout) {
    if (!props.terrain.length) return;
    if (!(await confirm({ title: `Replace "${l.name}"?`, message: 'It will hold the table as it is now.' }))) return;
    update(l, { terrain: props.terrain, table_image: props.tableImage } as unknown as RequestPayload, `Updated "${l.name}"`);
}

async function remove(l: SavedLayout) {
    if (!(await confirm({ title: `Delete "${l.name}"?`, message: 'Anyone it was shared with by link loses it too.', destructive: true }))) return;
    router.delete(route('tools.tabletop.layouts.destroy', l.uuid), {
        ...refresh,
        onSuccess: () => {
            toast.success('Deleted');
        },
    });
}

const linkOf = (l: SavedLayout) => `${window.location.origin}${route('tools.tabletop', { layout: l.uuid }, false)}`;
async function copyLink(l: SavedLayout) {
    try {
        await navigator.clipboard.writeText(linkOf(l));
        toast.success('Link copied');
    } catch {
        toast.info(linkOf(l));
    }
}

const rename = (l: SavedLayout, e: Event) => {
    const value = (e.target as HTMLInputElement).value.trim();
    if (value && value !== l.name) update(l, { name: value });
};
</script>

<template>
    <details class="rounded-md border p-3 text-sm" data-testid="layout-library">
        <summary class="cursor-pointer font-medium">
            Saved tables <span class="font-normal text-muted-foreground">({{ signedIn ? layouts.length : 'sign in to save' }})</span>
        </summary>

        <p v-if="!signedIn" class="mt-2 text-muted-foreground">
            <Link :href="route('login')" class="underline">Sign in</Link> to keep your own terrain setups, share them by link, and copy other
            people's.
        </p>
        <div v-else class="mt-3 space-y-3">
            <form class="flex flex-wrap items-end gap-2" @submit.prevent="saveNew">
                <label class="min-w-0 flex-1">
                    <span class="mb-1 block font-medium">{{
                        target.kind === 'game' ? "Save this game's table as" : 'Save the table on the board as'
                    }}</span>
                    <input
                        v-model="name"
                        maxlength="80"
                        placeholder="e.g. City streets"
                        class="w-full rounded-md border bg-background px-2 py-1.5"
                        data-testid="layout-name"
                    />
                </label>
                <Button type="submit" size="sm" data-testid="layout-save" :disabled="busy || !name.trim() || !terrain.length"
                    ><Save class="mr-1 size-4" />Save</Button
                >
            </form>
            <p v-if="!terrain.length" class="text-muted-foreground">There is no terrain on the table to save yet.</p>

            <ul v-if="layouts.length" class="space-y-2">
                <li v-for="l in layouts" :key="l.uuid" class="rounded-md border p-2" data-testid="layout-item" :data-uuid="l.uuid">
                    <div class="flex flex-wrap items-center gap-2">
                        <input
                            class="min-w-0 flex-1 rounded-md border bg-background px-2 py-1 font-medium"
                            maxlength="80"
                            aria-label="Layout name"
                            :value="l.name"
                            data-testid="layout-rename"
                            @change="rename(l, $event)"
                        />
                        <span class="text-muted-foreground"
                            >{{ l.pieces }} piece{{ l.pieces === 1 ? '' : 's' }}<template v-if="l.is_shared"> · shared</template></span
                        >
                    </div>
                    <div class="mt-2 flex flex-wrap gap-1">
                        <Button size="sm" variant="outline" data-testid="layout-load" :disabled="readOnly" @click="emit('load', l)"
                            ><Download class="mr-1 size-4" />Load</Button
                        >
                        <Button size="sm" variant="outline" data-testid="layout-overwrite" :disabled="!terrain.length" @click="overwrite(l)"
                            ><Save class="mr-1 size-4" />Replace with this table</Button
                        >
                        <Button
                            size="sm"
                            :variant="l.is_shared ? 'default' : 'outline'"
                            data-testid="layout-share"
                            :aria-pressed="l.is_shared"
                            @click="update(l, { is_shared: !l.is_shared }, l.is_shared ? 'No longer shared' : 'Anyone with the link can see it')"
                            ><Share2 class="mr-1 size-4" />{{ l.is_shared ? 'Shared' : 'Share' }}</Button
                        >
                        <Button v-if="l.is_shared" size="sm" variant="outline" data-testid="layout-link" @click="copyLink(l)"
                            ><Link2 class="mr-1 size-4" />Copy link</Button
                        >
                        <Button
                            size="sm"
                            variant="outline"
                            data-testid="layout-duplicate"
                            @click="router.post(route('tools.tabletop.layouts.copy', l.uuid), {}, { ...refresh, onError: failed })"
                            ><Copy class="mr-1 size-4" />Duplicate</Button
                        >
                        <Button size="sm" variant="ghost" aria-label="Delete this layout" data-testid="layout-delete" @click="remove(l)"
                            ><Trash2 class="size-4"
                        /></Button>
                    </div>
                </li>
            </ul>
            <p v-else class="text-muted-foreground">Nothing saved yet. Set the table up, give it a name and save it.</p>
        </div>
    </details>
</template>
