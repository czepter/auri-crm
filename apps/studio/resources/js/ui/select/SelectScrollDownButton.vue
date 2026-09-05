<script setup lang="ts">
import type { SelectScrollDownButtonProps } from "reka-ui"
import type { HTMLAttributes } from "vue"
import { reactiveOmit } from "@vueuse/core"
import { ChevronDown } from "lucide-vue-next"
import { SelectScrollDownButton } from "reka-ui"
import { cn } from "@/lib/utils"

const props = defineProps<SelectScrollDownButtonProps & { class?: HTMLAttributes["class"] }>()

const delegatedProps = reactiveOmit(props, "class")
</script>

<template>
  <SelectScrollDownButton
    data-slot="select-scroll-down-button"
    v-bind="delegatedProps"
    :class="cn('flex cursor-default items-center justify-center py-1 text-muted-foreground', props.class)"
  >
    <slot>
      <ChevronDown class="size-[13px]" />
    </slot>
  </SelectScrollDownButton>
</template>
