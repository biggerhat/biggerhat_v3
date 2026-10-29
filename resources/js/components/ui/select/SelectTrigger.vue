<script setup lang="ts">
import { cn } from '@/lib/utils'
import { ChevronDown } from 'lucide-vue-next'
import { SelectIcon, SelectTrigger, type SelectTriggerProps, useForwardProps } from 'reka-ui'
import { useAutoLabel } from '@/composables/useAutoLabel'
import { computed, ref, type ComponentPublicInstance, type HTMLAttributes } from 'vue'

const props = defineProps<SelectTriggerProps & { class?: HTMLAttributes['class'] }>()

const delegatedProps = computed(() => {
  const { class: _, ...delegated } = props

  return delegated
})

const forwardedProps = useForwardProps(delegatedProps.value)

// The trigger is role="combobox", which (unlike a plain button) takes its name only from
// aria-label / aria-labelledby / a <label for>, never from its text. Most call sites place a
// <Label> right before the Select without wiring them together, so name it from that label plus the
// current value (see useAutoLabel).
const triggerRef = ref<ComponentPublicInstance | null>(null)
const labelledBy = useAutoLabel(() => triggerRef.value?.$el as HTMLElement | undefined, { includeValue: true })
</script>

<template>
  <SelectTrigger
    ref="triggerRef"
    v-bind="forwardedProps"
    :aria-labelledby="labelledBy"
    :class="cn(
      'flex h-9 w-full items-center justify-between whitespace-nowrap rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm ring-offset-background data-[placeholder]:text-muted-foreground focus:outline-none focus:ring-1 focus:ring-ring disabled:cursor-not-allowed disabled:opacity-50 [&>span]:truncate text-start',
      props.class,
    )"
  >
    <slot />
    <SelectIcon as-child>
      <ChevronDown class="w-4 h-4 opacity-50 shrink-0" />
    </SelectIcon>
  </SelectTrigger>
</template>
