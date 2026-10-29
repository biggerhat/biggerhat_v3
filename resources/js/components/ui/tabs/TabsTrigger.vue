<script setup lang="ts">
import { nextTick, onMounted, onUpdated, ref, type ComponentPublicInstance, type HTMLAttributes } from 'vue'
import { reactiveOmit } from '@vueuse/core'
import { TabsTrigger, type TabsTriggerProps, useForwardProps } from 'reka-ui'
import { cn } from '@/lib/utils'

const props = defineProps<TabsTriggerProps & { class?: HTMLAttributes['class'] }>()

const delegatedProps = reactiveOmit(props, 'class')

const forwardedProps = useForwardProps(delegatedProps)

// Reka points aria-controls at the panel's id even when that panel isn't mounted (inactive tabs are
// unmounted by default), which is an invalid ARIA reference. Drop the attribute when there's nothing to point at.
const triggerRef = ref<ComponentPublicInstance | null>(null)
function dropDanglingControls() {
  const el = triggerRef.value?.$el as HTMLElement | undefined
  const id = el?.getAttribute?.('aria-controls')
  if (el && id && !document.getElementById(id)) el.removeAttribute('aria-controls')
}
onMounted(() => nextTick(dropDanglingControls))
onUpdated(() => nextTick(dropDanglingControls))
</script>

<template>
  <TabsTrigger
    ref="triggerRef"
    v-bind="forwardedProps"
    :class="cn(
      'inline-flex items-center justify-center whitespace-nowrap rounded-md px-3 py-1 text-sm font-medium ring-offset-background transition-all focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50 data-[state=active]:bg-background data-[state=active]:text-foreground data-[state=active]:shadow',
      props.class,
    )"
  >
    <span class="inline-flex items-center gap-1.5 truncate">
      <slot />
    </span>
  </TabsTrigger>
</template>
