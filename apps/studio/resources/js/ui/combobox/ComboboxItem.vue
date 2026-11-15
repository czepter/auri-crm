<script setup lang="ts">
import type { ComboboxItemEmits, ComboboxItemProps } from "reka-ui"
import type { HTMLAttributes } from "vue"
import { reactiveOmit } from "@vueuse/core"
import { Check, Plus } from "lucide-vue-next"
import { ComboboxItem, ComboboxItemIndicator, useForwardPropsEmits } from "reka-ui"
import { cn } from "@/lib/utils"

const props = defineProps<ComboboxItemProps & { create?: boolean; class?: HTMLAttributes["class"] }>()
const emits = defineEmits<ComboboxItemEmits>()

const delegatedProps = reactiveOmit(props, "class", "create")
const forwarded = useForwardPropsEmits(delegatedProps, emits)
</script>

<template>
  <ComboboxItem
    data-slot="combobox-item"
    v-bind="forwarded"
    :class="cn(
      'relative flex cursor-pointer select-none items-center gap-2 rounded-[4px] py-[7px] pl-[11px] pr-8',
      'text-ui leading-[1.35] outline-none',
      'data-[highlighted]:bg-accent data-[highlighted]:text-accent-foreground',
      'data-[disabled]:pointer-events-none data-[disabled]:opacity-50',
      props.class,
    )"
  >
    <Plus v-if="create" class="size-[13px] shrink-0 text-muted-foreground" />
    <slot />
    <ComboboxItemIndicator class="absolute right-[11px] flex items-center justify-center">
      <Check class="size-[13px]" />
    </ComboboxItemIndicator>
  </ComboboxItem>
</template>
