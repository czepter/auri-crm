<script setup lang="ts">
import type { SelectTriggerProps } from "reka-ui"
import type { HTMLAttributes } from "vue"
import type { InputVariants } from "@/ui/input"
import { reactiveOmit } from "@vueuse/core"
import { ChevronDown } from "lucide-vue-next"
import { SelectIcon, SelectTrigger, useForwardProps } from "reka-ui"
import { cn } from "@/lib/utils"
import { inputVariants } from "@/ui/input"

const props = defineProps<SelectTriggerProps & {
  size?: InputVariants["size"]
  class?: HTMLAttributes["class"]
}>()

const delegatedProps = reactiveOmit(props, "class", "size")
const forwarded = useForwardProps(delegatedProps)
</script>

<template>
  <!-- The trigger is the field: forms.css draws select.inp as .inp plus a
       chevron, so the padding and border come from inputVariants and the only
       thing added here is the mark itself. The chevron is a flex child rather
       than the rule's background-image — same position, but it can now take
       the icon system's stroke like every other icon on the screen. -->
  <SelectTrigger
    data-slot="select-trigger"
    v-bind="forwarded"
    :class="cn(
      inputVariants({ size }),
      'flex cursor-pointer items-center justify-between gap-2 text-left',
      'data-[placeholder]:text-faint data-[state=open]:border-ring',
      '[&>span]:min-w-0 [&>span]:truncate',
      props.class,
    )"
  >
    <slot />
    <SelectIcon as-child>
      <ChevronDown :class="cn('shrink-0 text-muted-foreground', size === 'sm' ? 'size-3' : 'size-[13px]')" />
    </SelectIcon>
  </SelectTrigger>
</template>
