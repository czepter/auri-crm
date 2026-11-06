<script setup lang="ts">
import type { RadioGroupItemProps } from "reka-ui"
import type { HTMLAttributes } from "vue"
import { reactiveOmit } from "@vueuse/core"
import { RadioGroupIndicator, RadioGroupItem, useForwardProps } from "reka-ui"
import { cn } from "@/lib/utils"

const props = defineProps<RadioGroupItemProps & { class?: HTMLAttributes["class"] }>()

const delegatedProps = reactiveOmit(props, "class")
const forwarded = useForwardProps(delegatedProps)
</script>

<template>
  <RadioGroupItem
    data-slot="radio-group-item"
    v-bind="forwarded"
    :class="cn(
      'peer relative size-6 shrink-0 cursor-pointer rounded-full border border-input bg-background',
      'after:absolute after:-inset-[10px]',
      'transition-colors duration-[120ms] ease-out',
      'hover:border-foreground',
      'focus-visible:border-ring',
      'data-[state=checked]:border-foreground',
      'disabled:cursor-not-allowed disabled:opacity-50',
      props.class,
    )"
  >
    <RadioGroupIndicator class="flex size-full items-center justify-center">
      <span class="block size-3 rounded-full bg-foreground" />
    </RadioGroupIndicator>
  </RadioGroupItem>
</template>
