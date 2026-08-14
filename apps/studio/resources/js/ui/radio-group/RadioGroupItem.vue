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
      'peer size-4 shrink-0 cursor-pointer rounded-full border border-line-hover bg-background',
      'transition-colors duration-[120ms] ease-out',
      'hover:border-faint',
      'focus-visible:border-ring focus-visible:outline-none',
      'data-[state=checked]:border-foreground',
      'disabled:cursor-not-allowed disabled:opacity-50',
      props.class,
    )"
  >
    <RadioGroupIndicator class="flex size-full items-center justify-center">
      <span class="block size-2 rounded-full bg-foreground" />
    </RadioGroupIndicator>
  </RadioGroupItem>
</template>
