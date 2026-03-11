<script setup lang="ts">
import { Combobox, ComboboxAnchor, ComboboxEmpty, ComboboxInput, ComboboxItem, ComboboxList } from '@/components/ui/combobox';
import { useFilter } from 'reka-ui';
import { ChevronDown, X } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

const modelValue = defineModel<string | null>();

interface Option {
    [key: string]: string | number;
}

interface Props {
    placeholder?: string;
    options: Option[];
    optionLabel?: string;
    optionValue?: string;
    triggerClass?: string;
}

const props = withDefaults(defineProps<Props>(), {
    placeholder: 'Select...',
    optionLabel: 'name',
    optionValue: 'value',
    triggerClass: '',
});

const searchTerm = ref('');
const open = ref(false);

const { contains } = useFilter({ sensitivity: 'base' });

const selectedLabel = computed(() => {
    if (!modelValue.value) return '';
    const found = props.options.find((o) => String(o[props.optionValue]) === modelValue.value);
    return found ? String(found[props.optionLabel]) : '';
});

const filteredOptions = computed(() => {
    if (!searchTerm.value) return props.options;
    return props.options.filter((o) => contains(String(o[props.optionLabel]), searchTerm.value));
});

const clear = () => {
    modelValue.value = null;
    searchTerm.value = '';
};

watch(open, (isOpen) => {
    if (!isOpen) {
        searchTerm.value = '';
    }
});
</script>

<template>
    <div class="flex items-center gap-1">
        <div class="min-w-0 flex-1">
            <Combobox v-model:open="open" :ignore-filter="true">
                <ComboboxAnchor
                    class="flex h-8 w-full cursor-text items-center rounded-md border-2 border-primary bg-transparent text-xs shadow-sm"
                    :class="props.triggerClass"
                    @click="open = true"
                >
                    <ComboboxInput
                        ref="inputRef"
                        v-model="searchTerm"
                        class="h-full flex-1 border-0 bg-transparent px-2 text-xs shadow-none outline-none ring-0 placeholder:text-muted-foreground focus-visible:ring-0"
                        :placeholder="selectedLabel || props.placeholder"
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
                                    modelValue = ev.detail.value;
                                    searchTerm = '';
                                    open = false;
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
            class="shrink-0 rounded-sm p-0.5 text-muted-foreground hover:text-foreground"
            :class="modelValue ? 'visible' : 'invisible'"
            @click.stop="clear"
        >
            <X class="h-3 w-3" />
        </button>
    </div>
</template>
