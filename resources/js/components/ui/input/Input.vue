<script setup lang="ts">
import { useAutoLabel } from '@/composables/useAutoLabel';
import { cn } from '@/lib/utils';
import { useVModel } from '@vueuse/core';
import { ref, type HTMLAttributes } from 'vue';

const props = defineProps<{
    defaultValue?: string | number;
    modelValue?: string | number;
    class?: HTMLAttributes['class'];
}>();

const emits = defineEmits<{
    (e: 'update:modelValue', payload: string | number): void;
}>();

const modelValue = useVModel(props, 'modelValue', emits, {
    passive: true,
    defaultValue: props.defaultValue,
});

// Most call sites put a <label> next to the input without wiring them together; name the input from it.
const inputRef = ref<HTMLInputElement | null>(null);
const labelledBy = useAutoLabel(() => inputRef.value ?? undefined);
</script>

<template>
    <input
        ref="inputRef"
        v-model="modelValue"
        :aria-labelledby="labelledBy"
        :class="
            cn(
                'flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-base ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium file:text-foreground placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 md:text-sm',
                props.class,
            )
        "
    />
</template>
