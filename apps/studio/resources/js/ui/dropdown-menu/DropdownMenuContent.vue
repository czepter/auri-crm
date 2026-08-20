<script setup lang="ts">
import type { DropdownMenuContentEmits, DropdownMenuContentProps } from "reka-ui"
import type { HTMLAttributes } from "vue"
import { reactiveOmit } from "@vueuse/core"
import { DropdownMenuContent, DropdownMenuPortal, useForwardPropsEmits } from "reka-ui"
import { cn } from "@/lib/utils"

defineOptions({ inheritAttrs: false })

const props = withDefaults(
  defineProps<DropdownMenuContentProps & { class?: HTMLAttributes["class"] }>(),
  { align: "start", sideOffset: 6 },
)
const emits = defineEmits<DropdownMenuContentEmits>()

const delegatedProps = reactiveOmit(props, "class")

const forwarded = useForwardPropsEmits(delegatedProps, emits)
</script>

<template>
  <DropdownMenuPortal>
    <!-- The popover's panel, minus its padding: a menu pads its items. -->
    <DropdownMenuContent
      data-slot="dropdown-menu-panel"
      v-bind="{ ...$attrs, ...forwarded }"
      :class="
        cn(
          'z-50 min-w-[180px] rounded-md border border-line-hover bg-background p-1 font-sans text-foreground',
          'shadow-[0_24px_64px_rgb(20_16_14_/_0.16)] outline-none',
          props.class,
        )
      "
    >
      <slot />
    </DropdownMenuContent>
  </DropdownMenuPortal>
</template>
