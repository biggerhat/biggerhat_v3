<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { useConfirm } from '@/composables/useConfirm';
import { STAGE_DOT } from '@/lib/collectionStages';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { router } from '@inertiajs/vue3';
import { Plus, Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';

export interface CollectionModelCopy {
    id: number;
    stage: string;
    note: string | null;
}

/** The individual copies of one miniature: each has its own stage and a short note. */
const props = defineProps<{
    miniatureId: number;
    miniatureName: string;
    models: CollectionModelCopy[];
    stages: { value: string; label: string }[];
}>();

const confirm = useConfirm();
const busy = ref(false);
const opts = { preserveScroll: true, preserveState: true, onFinish: () => (busy.value = false) };

function setStage(model: CollectionModelCopy, stage: string) {
    if (stage === model.stage) return;
    busy.value = true;
    router.put(route('collection.models.update', model.id), { stage }, opts);
}
function saveNote(model: CollectionModelCopy, event: Event) {
    const note = (event.target as HTMLInputElement).value.trim();
    if (note === (model.note ?? '')) return;
    busy.value = true;
    router.put(route('collection.models.update', model.id), { note }, opts);
}
async function remove(model: CollectionModelCopy) {
    // A note is lost with the copy, and the last copy takes the miniature out of the collection: ask first. Any other copy is quick to add back.
    if (model.note || props.models.length === 1) {
        const ok = await confirm({
            title: props.models.length === 1 ? 'Remove the last model?' : 'Remove this model?',
            message: props.models.length === 1 ? `${props.miniatureName} leaves your collection.` : `The note on it is removed too: "${model.note}".`,
            confirmLabel: 'Remove',
            destructive: true,
        });
        if (!ok) return;
    }
    busy.value = true;
    router.delete(route('collection.models.destroy', model.id), opts);
}
function add() {
    busy.value = true;
    router.post(route('collection.models.store'), { miniature_id: props.miniatureId }, opts);
}
</script>

<template>
    <div class="mt-2 space-y-1.5 border-t border-border/50 pt-2" :data-testid="`collection-copies-${miniatureId}`">
        <div v-for="(model, i) in models" :key="model.id" class="flex flex-wrap items-center gap-2" data-testid="collection-model">
            <span class="w-14 shrink-0 text-xs text-muted-foreground">Model {{ i + 1 }}</span>
            <span class="size-2.5 shrink-0 rounded-full" :class="STAGE_DOT[model.stage] ?? STAGE_DOT.unbuilt" aria-hidden="true" />
            <Select :model-value="model.stage" :disabled="busy" @update:model-value="(v) => setStage(model, String(v))">
                <SelectTrigger
                    class="h-8 w-40 text-xs sm:w-44"
                    :aria-label="`Stage of ${miniatureName} ${i + 1}`"
                    data-testid="collection-model-stage"
                >
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem v-for="s in stages" :key="s.value" :value="s.value">{{ s.label }}</SelectItem>
                </SelectContent>
            </Select>
            <Input
                :default-value="model.note ?? ''"
                :aria-label="`Note for ${miniatureName} ${i + 1}`"
                maxlength="255"
                placeholder="Note (optional)"
                class="order-last h-8 w-full text-xs sm:order-none sm:w-auto sm:min-w-0 sm:flex-1 [@media(pointer:coarse)]:min-h-11"
                data-testid="collection-model-note"
                @blur="saveNote(model, $event)"
                @keydown.enter.prevent="($event.target as HTMLInputElement).blur()"
            />
            <Button
                variant="ghost"
                size="icon"
                class="size-8 shrink-0 text-muted-foreground hover:text-destructive"
                :disabled="busy"
                :aria-label="`Remove model ${i + 1}`"
                data-testid="collection-model-remove"
                @click="remove(model)"
            >
                <Trash2 class="size-3.5" />
            </Button>
        </div>
        <Button
            variant="outline"
            size="sm"
            class="h-8 gap-1 text-xs max-sm:min-h-11"
            :disabled="busy"
            data-testid="collection-model-add"
            @click="add"
        >
            <Plus class="size-3.5" /> Add a model
        </Button>
    </div>
</template>
