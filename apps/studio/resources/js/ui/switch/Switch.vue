<script setup lang="ts">
import type { SwitchRootEmits, SwitchRootProps } from "reka-ui"
import type { HTMLAttributes } from "vue"
import { reactiveOmit } from "@vueuse/core"
import { SwitchRoot, SwitchThumb, useForwardPropsEmits } from "reka-ui"
import { cn } from "@/lib/utils"

const props = defineProps<SwitchRootProps & { class?: HTMLAttributes["class"] }>()
const emits = defineEmits<SwitchRootEmits>()

const delegatedProps = reactiveOmit(props, "class")
const forwarded = useForwardPropsEmits(delegatedProps, emits)
</script>

<template>
  <!-- On and off, not checked and unchecked: what this operates takes effect
       under the pointer, with no form to submit after it. Drawn from the same
       vocabulary as the checkbox — --ink as the on state, the hairline grey
       as the off one. -->
  <SwitchRoot
    data-slot="switch"
    v-bind="forwarded"
    :class="cn(
      'peer relative inline-flex h-6 w-11 shrink-0 cursor-pointer items-center rounded-full border-0 bg-input p-[2px]',
      'after:absolute after:-inset-y-[10px] after:inset-x-0',
      'transition-colors duration-[120ms] ease-out',
      'data-[state=checked]:bg-foreground',
      'disabled:cursor-not-allowed disabled:opacity-50',
      props.class,
    )"
  >
    <SwitchThumb
      class="pointer-events-none block size-5 rounded-full bg-background shadow-sm transition-transform duration-[120ms] ease-out data-[state=checked]:translate-x-5"
    />
  </SwitchRoot>
</template>
