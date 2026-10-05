<script setup lang="ts">
import type { SelectItemProps } from "reka-ui"
import type { HTMLAttributes } from "vue"
import { reactiveOmit } from "@vueuse/core"
import { Check } from "lucide-vue-next"
import { SelectItem, SelectItemIndicator, SelectItemText, useForwardProps } from "reka-ui"
import { cn } from "@/lib/utils"

const props = defineProps<SelectItemProps & { class?: HTMLAttributes["class"] }>()

const delegatedProps = reactiveOmit(props, "class")
const forwarded = useForwardProps(delegatedProps)
</script>

<template>
  <SelectItem
    data-slot="select-item"
    v-bind="forwarded"
    :class="cn(
      'relative flex cursor-pointer select-none items-center gap-2 rounded-[4px] py-[7px] pl-[11px] pr-8',
      'text-ui leading-[1.35] outline-none',
      'data-[highlighted]:bg-accent data-[highlighted]:text-accent-foreground',
      'data-[disabled]:pointer-events-none data-[disabled]:opacity-50',
      props.class,
    )"
  >
    <SelectItemText>
      <slot />
    </SelectItemText>
    <SelectItemIndicator class="absolute right-[11px] flex items-center justify-center">
      <Check class="size-[13px]" />
    </SelectItemIndicator>
  </SelectItem>
</template>
