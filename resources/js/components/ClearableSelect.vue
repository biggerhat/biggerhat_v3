<script setup lang="ts">
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { X } from 'lucide-vue-next';

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
    triggerClass: 'h-8 text-xs border-2 border-primary rounded',
});
</script>

<template>
    <div class="flex items-center gap-1">
        <div class="min-w-0 flex-1">
            <Select v-model="modelValue">
                <SelectTrigger :class="props.triggerClass">
                    <SelectValue :placeholder="props.placeholder" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem v-for="option in props.options" :value="String(option[props.optionValue])" :key="String(option[props.optionValue])">
                        {{ option[props.optionLabel] }}
                    </SelectItem>
                </SelectContent>
            </Select>
        </div>
        <button
            class="shrink-0 rounded-sm p-0.5 text-muted-foreground hover:text-foreground"
            :class="modelValue ? 'visible' : 'invisible'"
            @click.stop="modelValue = null"
        >
            <X class="h-3 w-3" />
        </button>
    </div>
</template>
