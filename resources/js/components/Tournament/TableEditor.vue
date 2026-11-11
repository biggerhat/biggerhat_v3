<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useConfirm } from '@/composables/useConfirm';
import { useToast } from '@/composables/useToast';
import { csrfToken } from '@/lib/utils';
import type { TableDetail } from '@/types/tournament';
import { ImagePlus, Loader2, Trash2 } from 'lucide-vue-next';
import { computed, onBeforeUnmount, ref, watch } from 'vue';

/** One table's name, terrain notes and photo/layout. Saves on its own so a long list never loses work. */
const props = defineProps<{ tournamentUuid: string; number: number; detail: TableDetail | null }>();
const emit = defineEmits<{ (e: 'saved'): void }>();

const toast = useToast();
const confirm = useConfirm();

const name = ref(props.detail?.name ?? '');
const description = ref(props.detail?.description ?? '');
const file = ref<File | null>(null);
const removeImage = ref(false);
const preview = ref<string | null>(null);
const saving = ref(false);
const error = ref<string | null>(null);
const input = ref<HTMLInputElement | null>(null);

watch(
    () => props.detail,
    (d) => {
        name.value = d?.name ?? '';
        description.value = d?.description ?? '';
        file.value = null;
        removeImage.value = false;
        clearPreview();
    },
);

const clearPreview = () => {
    if (preview.value) URL.revokeObjectURL(preview.value);
    preview.value = null;
};
onBeforeUnmount(clearPreview);

const shownImage = computed(() => preview.value ?? (removeImage.value ? null : (props.detail?.image_url ?? null)));
const dirty = computed(
    () =>
        name.value !== (props.detail?.name ?? '') ||
        description.value !== (props.detail?.description ?? '') ||
        file.value !== null ||
        removeImage.value,
);
const described = computed(() => !!(props.detail?.name || props.detail?.description || props.detail?.image_url));

const pick = (e: Event) => {
    const chosen = (e.target as HTMLInputElement).files?.[0] ?? null;
    if (!chosen) return;
    file.value = chosen;
    removeImage.value = false;
    clearPreview();
    preview.value = URL.createObjectURL(chosen);
};

const dropImage = () => {
    file.value = null;
    clearPreview();
    removeImage.value = true;
    if (input.value) input.value.value = '';
};

const send = async (init: RequestInit, url: string): Promise<boolean> => {
    saving.value = true;
    error.value = null;
    try {
        const res = await fetch(url, { ...init, headers: { 'X-CSRF-TOKEN': csrfToken(), Accept: 'application/json' } });
        if (!res.ok) {
            const body = await res.json().catch(() => ({}));
            error.value = body.errors ? (Object.values(body.errors).flat()[0] as string) : (body.message ?? 'Could not save this table.');
            return false;
        }
        return true;
    } catch {
        error.value = 'Network error. Please try again.';
        return false;
    } finally {
        saving.value = false;
    }
};

const save = async () => {
    const form = new FormData();
    form.append('name', name.value);
    form.append('description', description.value);
    if (file.value) form.append('image', file.value);
    if (removeImage.value) form.append('remove_image', '1');
    if (await send({ method: 'POST', body: form }, route('tournaments.tables.save', [props.tournamentUuid, props.number]))) {
        toast.success(`Table ${props.number} saved`);
        emit('saved');
    }
};

const clear = async () => {
    if (!(await confirm({ title: `Clear table ${props.number}?`, message: 'Its name, notes and photo are removed.', destructive: true }))) return;
    if (await send({ method: 'DELETE' }, route('tournaments.tables.destroy', [props.tournamentUuid, props.number]))) {
        toast.success(`Table ${props.number} cleared`);
        emit('saved');
    }
};
</script>

<template>
    <div class="space-y-3 rounded-md border p-3 sm:p-4" :data-table="number">
        <div class="flex items-center justify-between gap-2">
            <h4 class="text-sm font-semibold">Table {{ number }}</h4>
            <Button v-if="described" type="button" variant="ghost" size="sm" class="gap-1 text-xs text-muted-foreground" @click="clear">
                <Trash2 class="size-3.5" /> Clear
            </Button>
        </div>
        <div class="grid gap-3 sm:grid-cols-[1fr_12rem]">
            <div class="space-y-3">
                <div class="space-y-1.5">
                    <Label :for="`table-name-${number}`" class="text-xs">Name (optional)</Label>
                    <Input :id="`table-name-${number}`" v-model="name" maxlength="80" placeholder="The Mill" />
                </div>
                <div class="space-y-1.5">
                    <Label :for="`table-desc-${number}`" class="text-xs">Terrain and notes</Label>
                    <Textarea
                        :id="`table-desc-${number}`"
                        v-model="description"
                        rows="3"
                        maxlength="2000"
                        placeholder="Two LoS-blocking buildings in the centre, a river along the north edge (severe terrain)."
                    />
                </div>
            </div>
            <div class="space-y-1.5">
                <span class="block text-xs font-medium">Photo or layout</span>
                <div class="flex aspect-[4/3] items-center justify-center overflow-hidden rounded-md border border-dashed bg-muted/40">
                    <img v-if="shownImage" :src="shownImage" :alt="`Table ${number} terrain`" class="size-full object-cover" />
                    <ImagePlus v-else class="size-6 text-muted-foreground" aria-hidden="true" />
                </div>
                <input
                    ref="input"
                    type="file"
                    accept="image/png,image/jpeg,image/gif,image/webp"
                    class="sr-only"
                    :id="`table-image-${number}`"
                    @change="pick"
                />
                <div class="flex flex-wrap gap-2">
                    <Button as-child type="button" variant="outline" size="sm" class="text-xs">
                        <label :for="`table-image-${number}`" class="cursor-pointer">{{ shownImage ? 'Replace' : 'Add image' }}</label>
                    </Button>
                    <Button v-if="shownImage" type="button" variant="ghost" size="sm" class="text-xs" @click="dropImage">Remove</Button>
                </div>
            </div>
        </div>
        <p v-if="error" class="text-xs text-destructive" role="alert">{{ error }}</p>
        <div class="flex justify-end">
            <Button type="button" size="sm" :disabled="!dirty || saving" @click="save">
                <Loader2 v-if="saving" class="mr-1 size-3.5 animate-spin" /> Save table
            </Button>
        </div>
    </div>
</template>
