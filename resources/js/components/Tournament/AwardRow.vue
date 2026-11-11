<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import type { TournamentAward } from '@/types/tournament';
import { Loader2, Trash2 } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

/** One side award: its name, an optional note and who it went to. Saves (or creates) on its own. */
const props = defineProps<{
    award: TournamentAward | null;
    initialName?: string;
    players: { id: number; display_name: string }[];
    saving: boolean;
    error: string | null;
}>();
const emit = defineEmits<{
    (e: 'save', value: { name: string; player_id: number | null; note: string | null }): void;
    (e: 'delete'): void;
}>();

const NONE = '__none';
const name = ref(props.award?.name ?? props.initialName ?? '');
const note = ref(props.award?.note ?? '');
const recipient = ref(props.award?.player_id ? String(props.award.player_id) : NONE);

watch(
    () => props.award,
    (a) => {
        name.value = a?.name ?? '';
        note.value = a?.note ?? '';
        recipient.value = a?.player_id ? String(a.player_id) : NONE;
    },
);

const dirty = computed(
    () =>
        !props.award ||
        name.value !== props.award.name ||
        (note.value || null) !== props.award.note ||
        (recipient.value === NONE ? null : Number(recipient.value)) !== props.award.player_id,
);

const save = () => {
    emit('save', { name: name.value.trim(), player_id: recipient.value === NONE ? null : Number(recipient.value), note: note.value.trim() || null });
    if (!props.award) {
        name.value = '';
        note.value = '';
        recipient.value = NONE;
    }
};
</script>

<template>
    <div class="space-y-2 rounded-md border p-3" :data-award="award?.id ?? 'new'">
        <div class="grid gap-2 sm:grid-cols-[1fr_1fr]">
            <Input
                v-model="name"
                maxlength="80"
                placeholder="Award name (e.g. Best Painted)"
                :aria-label="award ? `Award name` : 'New award name'"
                @keydown.enter="name.trim() && save()"
            />
            <Select v-model="recipient">
                <SelectTrigger class="h-9 text-sm" aria-label="Awarded to"><SelectValue placeholder="Awarded to…" /></SelectTrigger>
                <SelectContent>
                    <SelectItem :value="NONE">Not awarded yet</SelectItem>
                    <SelectItem v-for="p in players" :key="p.id" :value="String(p.id)">{{ p.display_name }}</SelectItem>
                </SelectContent>
            </Select>
        </div>
        <Input
            v-model="note"
            maxlength="200"
            placeholder="Note (optional) — shown next to the winner"
            aria-label="Award note"
            @keydown.enter="name.trim() && save()"
        />
        <p v-if="error" class="text-xs text-destructive" role="alert">{{ error }}</p>
        <div class="flex justify-end gap-2">
            <Button v-if="award" type="button" variant="ghost" size="sm" class="gap-1 text-xs text-muted-foreground" @click="emit('delete')">
                <Trash2 class="size-3.5" /> Delete
            </Button>
            <Button type="button" size="sm" :disabled="!name.trim() || !dirty || saving" @click="save">
                <Loader2 v-if="saving" class="mr-1 size-3.5 animate-spin" /> {{ award ? 'Save award' : 'Add award' }}
            </Button>
        </div>
    </div>
</template>
