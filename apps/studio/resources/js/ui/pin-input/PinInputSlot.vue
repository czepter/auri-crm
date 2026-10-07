<script setup lang="ts">
import type { PinInputInputProps } from "reka-ui"
import type { HTMLAttributes } from "vue"
import { reactiveOmit } from "@vueuse/core"
import { PinInputInput, useForwardProps } from "reka-ui"
import { cn } from "@/lib/utils"

const props = defineProps<PinInputInputProps & { class?: HTMLAttributes["class"] }>()

const delegatedProps = reactiveOmit(props, "class")

const forwardedProps = useForwardProps(delegatedProps)
</script>

<template>
  <PinInputInput
    data-slot="pin-input-slot"
    v-bind="forwardedProps"
    inputmode="numeric"
    :class="cn(
      'relative flex size-[38px] items-center justify-center border-y border-r border-input bg-background text-center font-sans text-ui tabular-nums text-foreground outline-none',
      'transition-[border-color] duration-[120ms] ease-out first:rounded-l-[6px] first:border-l last:rounded-r-[6px]',
      'focus:z-10 focus:border-ring aria-invalid:border-destructive',
      'disabled:cursor-not-allowed disabled:opacity-50', props.class)"
  />
</template>
