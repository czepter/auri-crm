<script setup lang="ts">
import type { SelectContentEmits, SelectContentProps } from "reka-ui"
import type { HTMLAttributes } from "vue"
import { reactiveOmit } from "@vueuse/core"
import { SelectContent, SelectPortal, SelectViewport, useForwardPropsEmits } from "reka-ui"
import { cn } from "@/lib/utils"
import SelectScrollDownButton from "./SelectScrollDownButton.vue"
import SelectScrollUpButton from "./SelectScrollUpButton.vue"

const props = withDefaults(
  defineProps<SelectContentProps & { class?: HTMLAttributes["class"] }>(),
  { position: "popper", sideOffset: 4 },
)

const emits = defineEmits<SelectContentEmits>()

const delegatedProps = reactiveOmit(props, "class")
const forwarded = useForwardPropsEmits(delegatedProps, emits)
</script>

<template>
  <SelectPortal>
    <SelectContent
      data-slot="select-content"
      v-bind="forwarded"
      :class="cn(
        'relative z-50 max-h-(--reka-select-content-available-height) overflow-hidden',
        'rounded-[6px] border border-line-hover bg-background p-1 font-sans text-foreground',
        position === 'popper' ? 'w-full min-w-(--reka-select-trigger-width)' : 'min-w-32',
        props.class,
      )"
    >
      <SelectScrollUpButton />
      <SelectViewport :class="cn(position === 'popper' && 'scroll-my-1')">
        <slot />
      </SelectViewport>
      <SelectScrollDownButton />
    </SelectContent>
  </SelectPortal>
</template>
