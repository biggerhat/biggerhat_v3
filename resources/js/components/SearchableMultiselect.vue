<script setup lang="ts">
import { X } from 'lucide-vue-next';
import { computed, nextTick, ref } from 'vue';

interface Option {
    [key: string]: string | number;
}

interface Props {
    placeholder?: string;
    options: Option[];
    optionLabel?: string;
    optionValue?: string;
}

const modelValue = defineModel<string[]>({ default: [] });

const props = withDefaults(defineProps<Props>(), {
    placeholder: 'Search...',
    optionLabel: 'name',
    optionValue: 'value',
});

const searchTerm = ref('');
const open = ref(false);
const highlightIndex = ref(-1);
const inputRef = ref<HTMLInputElement | null>(null);
const listRef = ref<HTMLDivElement | null>(null);

const MAX_VISIBLE = 50;

const filteredOptions = computed(() => {
    const selected = new Set(modelValue.value);
    const available = props.options.filter((o) => !selected.has(String(o[props.optionValue])));
    if (!searchTerm.value) return available.slice(0, MAX_VISIBLE);
    const term = searchTerm.value.toLowerCase();
    return available.filter((o) => String(o[props.optionLabel]).toLowerCase().includes(term)).slice(0, MAX_VISIBLE);
});

const selectedLabels = computed(() => {
    const optMap = new Map(props.options.map((o) => [String(o[props.optionValue]), String(o[props.optionLabel])]));
    return modelValue.value.map((val) => ({ value: val, label: optMap.get(val) ?? val }));
});

const selectOption = (value: string) => {
    modelValue.value = [...modelValue.value, value];
    searchTerm.value = '';
    highlightIndex.value = -1;
    nextTick(() => inputRef.value?.focus());
};

const removeItem = (value: string) => {
    modelValue.value = modelValue.value.filter((v) => v !== value);
};

const clearAll = () => {
    modelValue.value = [];
    searchTerm.value = '';
};

const openDropdown = () => {
    open.value = true;
    highlightIndex.value = -1;
};

const closeDropdown = () => {
    open.value = false;
    highlightIndex.value = -1;
};

const onKeydown = (e: KeyboardEvent) => {
    if (!open.value && (e.key === 'ArrowDown' || e.key === 'Enter')) {
        openDropdown();
        e.preventDefault();
        return;
    }
    if (!open.value) return;

    if (e.key === 'ArrowDown') {
        e.preventDefault();
        highlightIndex.value = Math.min(highlightIndex.value + 1, filteredOptions.value.length - 1);
        scrollToHighlighted();
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        highlightIndex.value = Math.max(highlightIndex.value - 1, 0);
        scrollToHighlighted();
    } else if (e.key === 'Enter') {
        e.preventDefault();
        if (highlightIndex.value >= 0 && highlightIndex.value < filteredOptions.value.length) {
            selectOption(String(filteredOptions.value[highlightIndex.value][props.optionValue]));
        }
    } else if (e.key === 'Escape') {
        closeDropdown();
    }
};

const scrollToHighlighted = () => {
    nextTick(() => {
        const el = listRef.value?.children[highlightIndex.value] as HTMLElement | undefined;
        el?.scrollIntoView({ block: 'nearest' });
    });
};

const onBlur = (e: FocusEvent) => {
    const related = e.relatedTarget as HTMLElement | null;
    if (related?.closest('.multiselect-dropdown')) return;
    closeDropdown();
};
</script>

<template>
    <div class="space-y-2">
        <!-- Selected items as badges -->
        <div v-if="selectedLabels.length" class="flex flex-wrap gap-1.5">
            <span
                v-for="item in selectedLabels"
                :key="item.value"
                class="inline-flex items-center gap-1 rounded-md bg-secondary px-2 py-1 text-xs font-medium"
            >
                {{ item.label }}
                <button type="button" class="rounded-sm text-muted-foreground hover:text-foreground" @click="removeItem(item.value)">
                    <X class="h-3 w-3" />
                </button>
            </span>
        </div>

        <!-- Search input with dropdown -->
        <div class="relative flex items-center gap-1">
            <div class="min-w-0 flex-1">
                <input
                    ref="inputRef"
                    v-model="searchTerm"
                    type="text"
                    :placeholder="props.placeholder"
                    class="flex h-8 w-full rounded-md border bg-transparent px-2 text-xs shadow-sm outline-none placeholder:text-muted-foreground focus-visible:ring-1 focus-visible:ring-ring"
                    @focus="openDropdown"
                    @blur="onBlur"
                    @input="openDropdown"
                    @keydown="onKeydown"
                />

                <!-- Dropdown -->
                <div
                    v-if="open"
                    class="multiselect-dropdown absolute left-0 right-0 top-full z-50 mt-1 max-h-[200px] overflow-y-auto rounded-md border bg-popover p-1 shadow-md"
                    ref="listRef"
                    @mousedown.prevent
                >
                    <div v-if="!filteredOptions.length" class="px-2 py-1.5 text-center text-xs text-muted-foreground">No results found</div>
                    <button
                        v-for="(option, index) in filteredOptions"
                        :key="String(option[props.optionValue])"
                        type="button"
                        class="flex w-full cursor-pointer items-center rounded-sm px-2 py-1.5 text-left text-xs outline-none"
                        :class="index === highlightIndex ? 'bg-accent text-accent-foreground' : 'hover:bg-accent hover:text-accent-foreground'"
                        @click="selectOption(String(option[props.optionValue]))"
                        @mouseenter="highlightIndex = index"
                        tabindex="-1"
                    >
                        {{ option[props.optionLabel] }}
                    </button>
                </div>
            </div>
            <button
                v-if="modelValue.length"
                type="button"
                class="shrink-0 rounded-sm p-0.5 text-muted-foreground hover:text-foreground"
                @click.stop="clearAll"
            >
                <X class="h-3 w-3" />
            </button>
        </div>
    </div>
</template>
