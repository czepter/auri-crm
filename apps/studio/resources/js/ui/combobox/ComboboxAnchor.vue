<script setup lang="ts">
import type { ComboboxAnchorProps } from "reka-ui"
import type { HTMLAttributes } from "vue"
import { reactiveOmit } from "@vueuse/core"
import { ChevronDown } from "lucide-vue-next"
import { ComboboxAnchor, ComboboxTrigger, useForwardProps } from "reka-ui"
import { cn } from "@/lib/utils"

const props = defineProps<ComboboxAnchorProps & { class?: HTMLAttributes["class"] }>()

const delegatedProps = reactiveOmit(props, "class")
const forwarded = useForwardProps(delegatedProps)
</script>

<template>
  <ComboboxAnchor
    data-slot="combobox-anchor"
    v-bind="forwarded"
    :class="cn('relative w-full', props.class)"
  >
    <slot />
    <ComboboxTrigger class="absolute inset-y-0 right-0 flex w-8 cursor-pointer items-center justify-center text-muted-foreground" tabindex="-1">
      <ChevronDown class="size-[13px]" />
    </ComboboxTrigger>
  </ComboboxAnchor>
</template>
