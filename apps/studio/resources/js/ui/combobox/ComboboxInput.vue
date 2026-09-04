<script setup lang="ts">
import type { ComboboxInputProps } from "reka-ui"
import type { HTMLAttributes } from "vue"
import { reactiveOmit } from "@vueuse/core"
import { ComboboxInput } from "reka-ui"
import { cn } from "@/lib/utils"

const props = defineProps<ComboboxInputProps & { class?: HTMLAttributes["class"] }>()

const delegatedProps = reactiveOmit(props, "class")
</script>

<template>
  <!-- the same field shape Input draws, so a row of controls lines up -->
  <ComboboxInput
    data-slot="combobox-input"
    v-bind="delegatedProps"
    :class="cn(
      'w-full rounded-md border border-line-hover bg-background px-[11px] py-[7px]',
      'font-sans text-[13px] leading-[1.35] text-foreground outline-none',
      'placeholder:text-faint hover:border-faint focus-visible:border-ring',
      props.class,
    )"
  />
</template>
