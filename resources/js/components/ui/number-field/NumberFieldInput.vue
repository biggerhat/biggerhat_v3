<script setup lang="ts">
import type { ComponentPublicInstance, HTMLAttributes } from 'vue'
import { ref } from 'vue'
import { cn } from '@/lib/utils'
import { useAutoLabel } from '@/composables/useAutoLabel'
import { NumberFieldInput } from 'reka-ui'

const props = defineProps<{
  class?: HTMLAttributes['class']
}>()

// Most call sites put a <Label> next to the field without wiring it up; name the input from it.
const inputRef = ref<ComponentPublicInstance | null>(null)
const labelledBy = useAutoLabel(() => inputRef.value?.$el as HTMLElement | undefined)
</script>

<template>
  <NumberFieldInput
    ref="inputRef"
    data-slot="input"
    :aria-labelledby="labelledBy"
    :class="cn('flex h-9 w-full rounded-md border border-input bg-transparent py-1 text-sm text-center shadow-sm transition-colors placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50', props.class)"
  />
</template>
