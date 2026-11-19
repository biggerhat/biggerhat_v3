<script setup lang="ts">
import HelpDialog from '@/components/Help/HelpDialog.vue';
import { Button } from '@/components/ui/button';
import { CircleHelp } from 'lucide-vue-next';
import { ref } from 'vue';

/** A "Help" button that opens a feature's help in a dialog. Drop it into a feature's header: `<HelpButton topic="crew-builder" />`. */
withDefaults(defineProps<{ topic: string; section?: string; label?: string; size?: 'sm' | 'default' }>(), { label: 'Help', size: 'sm' });
const open = ref(false);
</script>

<template>
    <Button
        variant="ghost"
        :size="size"
        :aria-label="label ? undefined : 'Help'"
        :title="label ? undefined : 'Help'"
        :data-testid="`help-open-${topic}`"
        @click="open = true"
        ><CircleHelp class="size-4" :class="label ? 'mr-1.5' : ''" />{{ label }}</Button
    >
    <HelpDialog v-model:open="open" :topic="topic" :section="section" />
</template>
