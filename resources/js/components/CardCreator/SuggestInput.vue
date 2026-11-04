<script setup lang="ts">
import { Input } from '@/components/ui/input';
import { ref } from 'vue';

/**
 * Text input that suggests official names while typing (the value stays free text, so custom names still work).
 * `endpoint` is an API URL that takes `?q=` and returns `[{ name }]`; a failed or superseded request shows nothing.
 */
const props = defineProps<{
    modelValue: string;
    endpoint: string;
    placeholder?: string;
}>();
const emit = defineEmits<{ 'update:modelValue': [value: string] }>();

const results = ref<string[]>([]);
let timer: ReturnType<typeof setTimeout> | undefined;
let latest = 0;

const onInput = (value: string) => {
    emit('update:modelValue', value);
    clearTimeout(timer);
    if (value.length < 2) {
        results.value = [];
        return;
    }
    timer = setTimeout(async () => {
        const id = ++latest;
        try {
            const res = await fetch(`${props.endpoint}?q=${encodeURIComponent(value)}`, { headers: { Accept: 'application/json' } });
            if (!res.ok || id !== latest) return;
            const rows: { name: string }[] = await res.json();
            results.value = [...new Set(rows.map((r) => r.name))].slice(0, 8);
        } catch {
            results.value = [];
        }
    }, 300);
};

const pick = (name: string) => {
    emit('update:modelValue', name);
    results.value = [];
};

// Delay so a click on a suggestion lands before the list disappears.
const hideSoon = () => setTimeout(() => (results.value = []), 150);
</script>

<template>
    <div class="relative">
        <Input
            :model-value="modelValue"
            :placeholder="placeholder"
            autocomplete="off"
            @update:model-value="(v) => onInput(String(v))"
            @blur="hideSoon"
        />
        <div v-if="results.length" class="absolute z-10 mt-1 w-full rounded-md border bg-popover p-1 shadow-md">
            <button
                v-for="r in results"
                :key="r"
                type="button"
                class="w-full rounded px-2 py-1.5 text-left text-sm hover:bg-accent"
                @mousedown.prevent="pick(r)"
            >
                {{ r }}
            </button>
        </div>
    </div>
</template>
