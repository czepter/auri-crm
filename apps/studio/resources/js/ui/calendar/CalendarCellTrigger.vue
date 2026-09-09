<script lang="ts" setup>
import type { CalendarCellTriggerProps } from "reka-ui"
import type { HTMLAttributes } from "vue"
import { reactiveOmit } from "@vueuse/core"
import { CalendarCellTrigger, useForwardProps } from "reka-ui"
import { cn } from "@/lib/utils"
import { buttonVariants } from "@/ui/button"

const props = withDefaults(defineProps<CalendarCellTriggerProps & { class?: HTMLAttributes["class"] }>(), {
  as: "button",
})

const delegatedProps = reactiveOmit(props, "class")

const forwardedProps = useForwardProps(delegatedProps)
</script>

<template>
  <CalendarCellTrigger
    data-slot="calendar-cell-trigger"
    :class="cn(
      buttonVariants({ variant: 'ghost' }),
      'size-8 justify-center rounded-md p-0 text-[13px] font-normal text-foreground hover:bg-muted hover:text-foreground',
      // today is named, not filled: a fill here reads as a selection
      '[&[data-today]:not([data-selected])]:font-semibold [&[data-today]:not([data-selected])]:text-action',
      // Selected
      'data-[selected]:bg-action data-[selected]:font-semibold data-[selected]:text-action-foreground data-[selected]:hover:bg-action data-[selected]:hover:text-action-foreground',
      // Disabled
      'data-[disabled]:text-faint data-[disabled]:opacity-50',
      // Unavailable
      'data-[unavailable]:text-destructive data-[unavailable]:line-through',
      // Outside months
      'data-[outside-view]:text-faint',
      props.class,
    )"
    v-bind="forwardedProps"
  >
    <slot />
  </CalendarCellTrigger>
</template>
