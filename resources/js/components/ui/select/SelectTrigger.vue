<script setup lang="ts">
import { cn } from '@/lib/utils'
import { ChevronDown } from 'lucide-vue-next'
import { SelectIcon, SelectTrigger, type SelectTriggerProps, useForwardProps } from 'reka-ui'
import { computed, onMounted, ref, useAttrs, useId, type ComponentPublicInstance, type HTMLAttributes } from 'vue'

const props = defineProps<SelectTriggerProps & { class?: HTMLAttributes['class'] }>()

const delegatedProps = computed(() => {
  const { class: _, ...delegated } = props

  return delegated
})

const forwardedProps = useForwardProps(delegatedProps.value)

// The trigger is role="combobox", which (unlike a plain button) takes its name only from
// aria-label / aria-labelledby / a <label for>, never from its text. Most call sites place a
// <Label> right before the Select without wiring them together, so find that label once mounted
// and name the control "<label> <current value>". Explicit aria-label(ledby) or <label for> win.
const attrs = useAttrs()
const triggerRef = ref<ComponentPublicInstance | null>(null)
const autoId = useId()
const labelledBy = ref<string | undefined>()

const CONTROLS = 'button, input, select, textarea, [role="combobox"]'

function findLabel(el: HTMLElement): HTMLElement | null {
  let node: HTMLElement | null = el
  for (let depth = 0; depth < 3 && node; depth++) {
    const prev = node.previousElementSibling as HTMLElement | null
    if (prev) {
      const label = prev.matches('label') ? prev : prev.querySelector<HTMLElement>('label')
      if (label && !prev.querySelector(CONTROLS)) return label
    }
    node = node.parentElement
  }
  return null
}

onMounted(() => {
  if (attrs['aria-label'] || attrs['aria-labelledby']) return
  const el = triggerRef.value?.$el as HTMLElement | undefined
  if (!el) return
  if (el.id && document.querySelector(`label[for="${CSS.escape(el.id)}"]`)) return

  const ids: string[] = []
  const label = findLabel(el)
  if (label) {
    label.id ||= `${autoId}-label`
    ids.push(label.id)
  }
  // Name the control from its visible value too. The value lives in a child span: a combobox
  // can't take its name from its own content, but it can reference an element that has it.
  const value = el.querySelector<HTMLElement>(':scope > span')
  if (value) {
    value.id ||= `${autoId}-value`
    ids.push(value.id)
  }
  if (ids.length) labelledBy.value = ids.join(' ')
})
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
