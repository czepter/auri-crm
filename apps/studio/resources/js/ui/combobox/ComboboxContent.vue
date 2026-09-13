<script setup lang="ts">
import type { ComboboxContentEmits, ComboboxContentProps } from "reka-ui"
import type { HTMLAttributes } from "vue"
import { reactiveOmit } from "@vueuse/core"
import { ComboboxContent, ComboboxPortal, useForwardPropsEmits } from "reka-ui"
import { cn } from "@/lib/utils"

const props = withDefaults(
  defineProps<ComboboxContentProps & { class?: HTMLAttributes["class"] }>(),
  { position: "popper", sideOffset: 4 },
)

const emits = defineEmits<ComboboxContentEmits>()

const delegatedProps = reactiveOmit(props, "class")
const forwarded = useForwardPropsEmits(delegatedProps, emits)
</script>

<template>
  <ComboboxPortal>
    <ComboboxContent
      data-slot="combobox-content"
      v-bind="forwarded"
      :class="cn(
        'relative z-50 max-h-72 min-w-(--reka-combobox-trigger-width) overflow-y-auto overflow-x-hidden',
        'rounded-[6px] border border-line-hover bg-background p-1 font-sans text-foreground',
        'shadow-[0_8px_24px_rgb(20_16_14_/_0.10)]',
        props.class,
      )"
    >
      <slot />
    </ComboboxContent>
  </ComboboxPortal>
</template>
