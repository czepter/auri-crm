<script setup lang="ts">
import type { DropdownMenuItemProps } from "reka-ui"
import type { HTMLAttributes } from "vue"
import { reactiveOmit } from "@vueuse/core"
import { DropdownMenuItem, useForwardProps } from "reka-ui"
import { cn } from "@/lib/utils"

const props = defineProps<DropdownMenuItemProps & { class?: HTMLAttributes["class"] }>()

const delegatedProps = reactiveOmit(props, "class")
const forwarded = useForwardProps(delegatedProps)
</script>

<template>
  <DropdownMenuItem
    data-slot="dropdown-menu-item"
    v-bind="forwarded"
    :class="cn(
      'relative flex w-full cursor-pointer select-none items-center gap-2.25 rounded-[var(--r-sm)] px-2.5 py-[7px]',
      'text-[13px] leading-[1.35] text-foreground no-underline outline-none',
      'data-[highlighted]:bg-accent data-[highlighted]:text-accent-foreground',
      props.class,
    )"
  >
    <slot />
  </DropdownMenuItem>
</template>
