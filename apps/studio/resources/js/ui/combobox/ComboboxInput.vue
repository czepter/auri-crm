<script setup lang="ts">
import type { ComboboxInputEmits, ComboboxInputProps } from "reka-ui"
import type { HTMLAttributes } from "vue"
import { reactiveOmit } from "@vueuse/core"
import { ComboboxInput, useForwardPropsEmits } from "reka-ui"
import { cn } from "@/lib/utils"

const props = defineProps<ComboboxInputProps & { class?: HTMLAttributes["class"] }>()
const emits = defineEmits<ComboboxInputEmits>()

const delegatedProps = reactiveOmit(props, "class")
const forwarded = useForwardPropsEmits(delegatedProps, emits)
</script>

<template>
  <!-- the same field shape Input draws, so a row of controls lines up -->
  <ComboboxInput
    data-slot="combobox-input"
    v-bind="forwarded"
    :class="cn(
      'w-full rounded-[6px] border border-line-hover bg-background px-[11px] py-[7px]',
      'font-sans text-ui leading-[1.35] text-foreground outline-none',
      'placeholder:text-faint hover:border-faint focus-visible:border-ring',
      props.class,
    )"
  />
</template>
