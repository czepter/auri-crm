<script setup lang="ts">
import type { PopoverContentEmits, PopoverContentProps } from "reka-ui"
import type { HTMLAttributes } from "vue"
import { reactiveOmit } from "@vueuse/core"
import {
  PopoverContent,
  PopoverPortal,
  useForwardPropsEmits,
} from "reka-ui"
import { cn } from "@/lib/utils"

defineOptions({
  inheritAttrs: false,
})

const props = withDefaults(
  defineProps<PopoverContentProps & { class?: HTMLAttributes["class"] }>(),
  {
    align: "center",
    sideOffset: 4,
  },
)
const emits = defineEmits<PopoverContentEmits>()

const delegatedProps = reactiveOmit(props, "class")

const forwarded = useForwardPropsEmits(delegatedProps, emits)
</script>

<template>
  <PopoverPortal>
    <!-- Panel styling is the dialog's, minus the scrim: same hairline border,
         same warm shadow, so the two floating layers are one surface. -->
    <PopoverContent
      data-slot="popover-panel"
      v-bind="{ ...$attrs, ...forwarded }"
      :class="
        cn(
          'z-50 w-auto max-w-(--reka-popover-content-available-width) rounded-md border border-line-hover bg-background p-3 font-sans text-foreground',
          'shadow-[0_24px_64px_rgb(20_16_14_/_0.16)] outline-none',
          props.class,
        )
      "
    >
      <slot />
    </PopoverContent>
  </PopoverPortal>
</template>
