<script setup lang="ts">
import type { CheckboxRootEmits, CheckboxRootProps } from "reka-ui"
import type { HTMLAttributes } from "vue"
import { reactiveOmit } from "@vueuse/core"
import { Check, Minus } from "lucide-vue-next"
import { CheckboxIndicator, CheckboxRoot, useForwardPropsEmits } from "reka-ui"
import { cn } from "@/lib/utils"

const props = defineProps<CheckboxRootProps & { class?: HTMLAttributes["class"] }>()
const emits = defineEmits<CheckboxRootEmits>()

const delegatedProps = reactiveOmit(props, "class")
const forwarded = useForwardPropsEmits(delegatedProps, emits)
</script>

<template>
  <!-- The one control the system never drew: every checkbox in the app was a
       bare <input type="checkbox"> in whatever the browser paints. The box is
       therefore built from the vocabulary the rest of the system uses — the
       hairline border a field takes on hover, --ink as the checked fill, the
       small radius the chips use. -->
  <CheckboxRoot
    data-slot="checkbox"
    v-bind="forwarded"
    :class="cn(
      'peer relative size-6 shrink-0 cursor-pointer rounded-[5px] border border-input bg-background',
      'after:absolute after:-inset-[10px]',
      'transition-colors duration-[120ms] ease-out',
      'hover:border-foreground',
      'focus-visible:border-ring',
      'data-[state=checked]:border-foreground data-[state=checked]:bg-foreground data-[state=checked]:text-background',
      'data-[state=indeterminate]:border-foreground data-[state=indeterminate]:bg-foreground data-[state=indeterminate]:text-background',
      'disabled:cursor-not-allowed disabled:opacity-50',
      props.class,
    )"
    v-slot="{ state }"
  >
    <CheckboxIndicator class="flex size-full items-center justify-center text-current">
      <Minus v-if="state === 'indeterminate'" class="size-4" />
      <Check v-else class="size-4" />
    </CheckboxIndicator>
  </CheckboxRoot>
</template>
