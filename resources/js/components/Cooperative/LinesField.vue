<script setup lang="ts">
import { Textarea } from '@/components/ui/textarea';
import { computed } from 'vue';

/** A list of strings edited as one item per line (blank lines dropped). */
const model = defineModel<string[] | null>({ required: true });
defineProps<{ rows?: number; placeholder?: string; id?: string }>();

const text = computed({
    get: () => (model.value ?? []).join('\n'),
    set: (value: string) =>
        (model.value = value
            .split('\n')
            .map((l) => l.trim())
            .filter(Boolean)),
});
</script>

<template>
    <Textarea :id="id" v-model="text" :rows="rows ?? 3" :placeholder="placeholder" />
</template>
