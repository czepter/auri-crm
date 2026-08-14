<script setup lang="ts">
import type { SelectScrollUpButtonProps } from "reka-ui"
import type { HTMLAttributes } from "vue"
import { reactiveOmit } from "@vueuse/core"
import { ChevronUp } from "lucide-vue-next"
import { SelectScrollUpButton } from "reka-ui"
import { cn } from "@/lib/utils"

const props = defineProps<SelectScrollUpButtonProps & { class?: HTMLAttributes["class"] }>()

const delegatedProps = reactiveOmit(props, "class")
</script>

<template>
  <SelectScrollUpButton
    data-slot="select-scroll-up-button"
    v-bind="delegatedProps"
    :class="cn('flex cursor-default items-center justify-center py-1 text-muted-foreground', props.class)"
  >
    <slot>
      <ChevronUp class="size-[13px]" />
    </slot>
  </SelectScrollUpButton>
</template>
