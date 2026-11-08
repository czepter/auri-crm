<script setup lang="ts">
import type { ContextMenuItemEmits, ContextMenuItemProps } from "reka-ui"
import type { HTMLAttributes } from "vue"
import { reactiveOmit } from "@vueuse/core"
import { ContextMenuItem, useForwardPropsEmits } from "reka-ui"
import { cn } from "@/lib/utils"

const props = defineProps<ContextMenuItemProps & { class?: HTMLAttributes["class"] }>()
const emits = defineEmits<ContextMenuItemEmits>()

const delegatedProps = reactiveOmit(props, "class")
const forwarded = useForwardPropsEmits(delegatedProps, emits)
</script>

<template>
  <ContextMenuItem
    data-slot="context-menu-item"
    v-bind="forwarded"
    :class="cn(
      'relative flex w-full cursor-pointer select-none items-center gap-2.25 rounded-[4px] px-2.5 py-[7px]',
      'text-ui leading-[1.35] text-foreground no-underline outline-none',
      'data-[highlighted]:bg-accent data-[highlighted]:text-accent-foreground',
      'data-[disabled]:pointer-events-none data-[disabled]:opacity-50',
      props.class,
    )"
  >
    <slot />
  </ContextMenuItem>
</template>
