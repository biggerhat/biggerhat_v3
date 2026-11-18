<script setup lang="ts">
import { Button } from '@/components/ui/button';
import type { ModelInfo, Token } from '@/lib/tabletop/geometry';
import { Check, ChevronDown, Circle, Heart, Minus, Plus } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

/**
 * What the game tracker keeps for one model: activation, health, tokens and a note. It owns no state: every change is emitted as a
 * patch and the page that holds the model applies it (the sandbox keeps it on the device, a game saves it with the game).
 */
const props = defineProps<{
    token: Token;
    /** The viewer may change this model. */
    canEdit: boolean;
    /** The sandbox has no card behind a model, so its maximum health is set here. */
    editableMax?: boolean;
    /** The crew list above already shows activation and health (the play screen's row), so only tokens and notes are shown. */
    crew?: boolean;
}>();

const emit = defineEmits<{ change: [patch: Partial<ModelInfo>] }>();

const info = computed<ModelInfo>(() => props.token.info ?? { tokens: [] });
const max = computed(() => info.value.maxHealth);
const health = computed(() => info.value.health ?? info.value.maxHealth ?? 0);

const setHealth = (n: number) => {
    if (!props.canEdit || max.value === undefined) return;
    emit('change', { health: Math.min(max.value, Math.max(0, n)) });
};
// A pip is a point of health: pressing the one you are on takes it off, like the tracker.
const pip = (i: number) => setHealth(health.value === i ? i - 1 : i);
const setMax = (n: number) => {
    const next = Math.min(40, Math.max(1, Math.round(n) || 1));
    emit('change', { maxHealth: next, health: Math.min(info.value.health ?? next, next) });
};

// The note is saved when you leave the box, not on every keystroke.
const note = ref(info.value.notes ?? '');
watch(
    () => info.value.notes,
    (next) => (note.value = next ?? ''),
);
const saveNote = () => {
    if (props.canEdit && note.value !== (info.value.notes ?? '')) emit('change', { notes: note.value });
};
</script>

<template>
    <div class="mt-3 space-y-3 border-t pt-3" data-testid="model-info">
        <div v-if="!crew" class="flex items-center justify-between gap-2">
            <span class="font-medium">Model</span>
            <button
                type="button"
                class="inline-flex items-center gap-1 rounded-md border px-2 py-1 text-xs disabled:opacity-60"
                :class="info.activated ? 'border-green-500/60 text-green-600 dark:text-green-400' : 'text-muted-foreground'"
                :disabled="!canEdit"
                :aria-pressed="!!info.activated"
                data-testid="model-activated"
                @click="emit('change', { activated: !info.activated })"
            >
                <Check v-if="info.activated" class="size-3.5" />
                <Circle v-else class="size-3.5" />
                {{ info.activated ? 'Activated' : 'Not activated' }}
            </button>
        </div>

        <div v-if="!crew" data-testid="model-health">
            <div v-if="max !== undefined" class="flex flex-wrap items-center gap-2">
                <Heart class="size-4 shrink-0 text-rose-500" />
                <Button
                    size="sm"
                    variant="outline"
                    aria-label="Lose 1 health"
                    :disabled="!canEdit || health <= 0"
                    data-testid="health-minus"
                    @click="setHealth(health - 1)"
                    ><Minus class="size-4"
                /></Button>
                <span class="min-w-14 text-center font-semibold" data-testid="health-value">{{ health }} / {{ max }}</span>
                <Button
                    size="sm"
                    variant="outline"
                    aria-label="Heal 1"
                    :disabled="!canEdit || health >= max"
                    data-testid="health-plus"
                    @click="setHealth(health + 1)"
                    ><Plus class="size-4"
                /></Button>
                <div class="flex w-full flex-wrap gap-1" aria-hidden="true">
                    <button
                        v-for="i in max"
                        :key="i"
                        type="button"
                        tabindex="-1"
                        class="size-3.5 rounded-sm border"
                        :class="i <= health ? 'border-rose-600 bg-rose-500' : 'border-muted-foreground/40'"
                        :disabled="!canEdit"
                        @click="pip(i)"
                    />
                </div>
            </div>
            <label v-else-if="editableMax && canEdit" class="flex items-center gap-2">
                <Heart class="size-4 shrink-0 text-rose-500" />
                <span class="text-muted-foreground">Health</span>
                <input
                    type="number"
                    min="1"
                    max="40"
                    class="w-20 rounded-md border bg-background px-2 py-1.5"
                    placeholder="Max"
                    aria-label="Maximum health"
                    data-testid="health-max"
                    @change="setMax(Number(($event.target as HTMLInputElement).value))"
                />
            </label>
            <span v-else class="text-muted-foreground">No health tracked.</span>
        </div>

        <details class="group" :open="note.length > 0" data-testid="model-notes-section">
            <summary class="flex cursor-pointer list-none items-center justify-between text-muted-foreground [&::-webkit-details-marker]:hidden">
                <span
                    >Notes <span class="text-xs">({{ note.length ? 'written' : 'none' }})</span></span
                >
                <ChevronDown class="size-4 transition-transform group-open:rotate-180" />
            </summary>
            <textarea
                v-model="note"
                rows="2"
                maxlength="500"
                class="mt-1 w-full rounded-md border bg-background px-2 py-1.5"
                :disabled="!canEdit"
                data-testid="model-notes"
                @blur="saveNote"
            />
        </details>
    </div>
</template>
