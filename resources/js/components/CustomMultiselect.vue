<script setup lang="ts">
import { Combobox, ComboboxAnchor, ComboboxEmpty, ComboboxGroup, ComboboxInput, ComboboxItem, ComboboxList } from '@/components/ui/combobox';
import { TagsInput, TagsInputInput, TagsInputItem, TagsInputItemDelete, TagsInputItemText } from '@/components/ui/tags-input';
import { useFilter } from 'reka-ui';
import { computed, ref } from 'vue';

const modelValue = defineModel();

const props = defineProps({
    comboTitle: {
        type: String,
        required: false,
        default() {
            return 'Select';
        },
    },
    choiceOptions: {
        type: [Object, Array],
        required: true,
        default() {
            return [];
        },
    },
});

const open = ref(false);
const searchTerm = ref('');

const { contains } = useFilter({ sensitivity: 'base' });
const filteredOptions = computed(() => {
    const options = props.choiceOptions.filter((i) => !modelValue.value.includes(i.name));
    return searchTerm.value ? options.filter((option) => contains(option.name, searchTerm.value)) : options;
});
</script>

<template>
    <Combobox v-model="modelValue" v-model:open="open" :ignore-filter="true">
        <ComboboxAnchor as-child>
            <TagsInput v-model="modelValue" class="w-full gap-2 px-2">
                <div class="flex flex-wrap items-center gap-2">
                    <TagsInputItem v-for="item in modelValue" :key="item" :value="item">
                        <TagsInputItemText />
                        <TagsInputItemDelete />
                    </TagsInputItem>
                </div>

                <ComboboxInput v-model="searchTerm" as-child>
                    <TagsInputInput
                        :placeholder="comboTitle"
                        class="h-auto w-full min-w-[200px] border-none p-0 focus-visible:ring-0"
                        @keydown.enter.prevent
                        @focus="open = true"
                    />
                </ComboboxInput>
            </TagsInput>

            <ComboboxList class="w-[--reka-popper-anchor-width]">
                <ComboboxEmpty />
                <ComboboxGroup>
                    <ComboboxItem
                        v-for="option in filteredOptions"
                        :key="option.value"
                        :value="option.name"
                        @select.prevent="
                            (ev) => {
                                if (typeof ev.detail.value === 'string') {
                                    searchTerm = '';
                                    modelValue.push(ev.detail.value);
                                }
                                if (filteredOptions.length === 0) {
                                    open = false;
                                }
                            }
                        "
                    >
                        {{ option.name }}
                    </ComboboxItem>
                </ComboboxGroup>
            </ComboboxList>
        </ComboboxAnchor>
    </Combobox>
</template>
