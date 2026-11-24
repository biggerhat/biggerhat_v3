<script setup lang="ts">
import { Input } from '@/components/ui/input';
import { useLatestFetch } from '@/composables/useLatestFetch';
import { ref, watch } from 'vue';

/**
 * Search the models by name as you type, and pick one. Used by the Log a game form for a master and for the models in a crew. With
 * `free` the text typed is also kept as the answer when nothing is picked (a master the catalogue does not have, or one you only
 * remember by name).
 */
export interface FoundCharacter {
    id: number;
    name: string;
    faction: string | null;
    station: string | null;
    cost: number | null;
}

const props = defineProps<{
    id: string;
    label: string;
    /** Only masters. */
    masters?: boolean;
    faction?: string | null;
    /** What is in the box when the form opens. */
    modelValue?: string;
    /** Keep the typed text as the value even when nothing is picked. */
    free?: boolean;
    /** Clear the box after a pick (adding models one after another). */
    clearOnPick?: boolean;
}>();
const emit = defineEmits<{
    (e: 'pick', c: FoundCharacter): void;
    (e: 'update:modelValue', text: string): void;
}>();

const text = ref(props.modelValue ?? '');
const results = ref<FoundCharacter[]>([]);
const open = ref(false);
const fetchLatest = useLatestFetch();
let timer: ReturnType<typeof setTimeout> | undefined;

watch(
    () => props.modelValue,
    (v) => {
        if (v !== undefined && v !== text.value) text.value = v;
    },
);

function onInput() {
    if (props.free) emit('update:modelValue', text.value);
    clearTimeout(timer);
    if (text.value.trim().length < 2) {
        results.value = [];
        open.value = false;
        return;
    }
    timer = setTimeout(async () => {
        const q = new URLSearchParams({ q: text.value.trim() });
        if (props.masters) q.set('station', 'master');
        if (props.faction) q.set('faction', props.faction);
        const data = await fetchLatest<{ characters: FoundCharacter[] }>(props.id, route('games.logged.characters') + '?' + q.toString());
        if (data) {
            results.value = data.characters;
            open.value = data.characters.length > 0;
        }
    }, 250);
}
/** Closing waits a moment so a click on a result lands before the list goes. */
const closeSoon = () => setTimeout(() => (open.value = false), 150);
function pick(c: FoundCharacter) {
    emit('pick', c);
    text.value = props.clearOnPick ? '' : c.name;
    if (props.free) emit('update:modelValue', text.value);
    results.value = [];
    open.value = false;
}
</script>

<template>
    <div class="relative">
        <label :for="id" class="mb-1.5 block text-sm font-medium leading-none">{{ label }}</label>
        <Input
            :id="id"
            v-model="text"
            type="text"
            autocomplete="off"
            role="combobox"
            :aria-expanded="open"
            :aria-controls="`${id}-list`"
            @input="onInput"
            @keydown.escape="open = false"
            @blur="closeSoon"
        />
        <ul
            v-show="open"
            :id="`${id}-list`"
            role="listbox"
            class="absolute z-20 mt-1 max-h-56 w-full overflow-auto rounded-md border bg-popover p-1 text-sm shadow-md"
        >
            <li v-for="c in results" :key="c.id" role="option" :aria-selected="false">
                <button
                    type="button"
                    class="flex min-h-11 w-full items-center justify-between gap-2 rounded px-2 text-left hover:bg-accent sm:min-h-8"
                    @mousedown.prevent="pick(c)"
                >
                    <span class="truncate">{{ c.name }}</span>
                    <span class="shrink-0 text-xs text-muted-foreground"
                        >{{ c.station ?? '' }}<span v-if="c.cost != null"> · {{ c.cost }}ss</span></span
                    >
                </button>
            </li>
        </ul>
    </div>
</template>
