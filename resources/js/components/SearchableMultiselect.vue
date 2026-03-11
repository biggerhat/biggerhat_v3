<script setup lang="ts">
import { Combobox, ComboboxAnchor, ComboboxEmpty, ComboboxInput, ComboboxItem, ComboboxList } from '@/components/ui/combobox';
import { useFilter } from 'reka-ui';
import { ChevronDown, X } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

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

const { contains } = useFilter({ sensitivity: 'base' });

const filteredOptions = computed(() => {
    const available = props.options.filter((o) => !modelValue.value.includes(String(o[props.optionValue])));
    if (!searchTerm.value) return available;
    return available.filter((o) => contains(String(o[props.optionLabel]), searchTerm.value));
});

const selectedLabels = computed(() => {
    return modelValue.value.map((val) => {
        const found = props.options.find((o) => String(o[props.optionValue]) === val);
        return { value: val, label: found ? String(found[props.optionLabel]) : val };
    });
});

const removeItem = (value: string) => {
    modelValue.value = modelValue.value.filter((v) => v !== value);
};

const clearAll = () => {
    modelValue.value = [];
    searchTerm.value = '';
};

watch(open, (isOpen) => {
    if (!isOpen) {
        searchTerm.value = '';
    }
});
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
                <button class="rounded-sm text-muted-foreground hover:text-foreground" @click="removeItem(item.value)">
                    <X class="h-3 w-3" />
                </button>
            </span>
        </div>

        <!-- Search input with dropdown -->
        <div class="flex items-center gap-1">
            <div class="min-w-0 flex-1">
                <Combobox v-model:open="open" :ignore-filter="true">
                    <ComboboxAnchor
                        class="flex h-8 w-full cursor-text items-center rounded-md border bg-transparent text-xs shadow-sm"
                        @click="open = true"
                    >
                        <ComboboxInput
                            v-model="searchTerm"
                            class="h-full flex-1 border-0 bg-transparent px-2 text-xs shadow-none outline-none ring-0 placeholder:text-muted-foreground focus-visible:ring-0"
                            :placeholder="props.placeholder"
                        />
                        <button class="px-2 text-muted-foreground" tabindex="-1" @click.stop="open = !open">
                            <ChevronDown class="h-3.5 w-3.5 transition-transform" :class="open ? 'rotate-180' : ''" />
                        </button>
                    </ComboboxAnchor>
                    <ComboboxList class="max-h-[200px] w-[--reka-popper-anchor-width] overflow-y-auto p-1">
                        <ComboboxEmpty>No results found</ComboboxEmpty>
                        <ComboboxItem
                            v-for="option in filteredOptions"
                            :key="String(option[props.optionValue])"
                            :value="String(option[props.optionValue])"
                            @select.prevent="
                                (ev) => {
                                    if (typeof ev.detail.value === 'string') {
                                        modelValue = [...modelValue, ev.detail.value];
                                        searchTerm = '';
                                    }
                                }
                            "
                        >
                            {{ option[props.optionLabel] }}
                        </ComboboxItem>
                    </ComboboxList>
                </Combobox>
            </div>
            <button
                v-if="modelValue.length"
                class="shrink-0 rounded-sm p-0.5 text-muted-foreground hover:text-foreground"
                @click.stop="clearAll"
            >
                <X class="h-3 w-3" />
            </button>
        </div>
    </div>
</template>
