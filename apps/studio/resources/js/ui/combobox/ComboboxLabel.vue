<script setup lang="ts">
import type { ComboboxLabelProps } from "reka-ui"
import type { HTMLAttributes } from "vue"
import { reactiveOmit } from "@vueuse/core"
import { ComboboxLabel } from "reka-ui"
import { cn } from "@/lib/utils"

const props = defineProps<ComboboxLabelProps & { class?: HTMLAttributes["class"] }>()

const delegatedProps = reactiveOmit(props, "class")
</script>

<template>
  <!-- same micro-label the select's group heading uses -->
  <ComboboxLabel
    data-slot="combobox-label"
    v-bind="delegatedProps"
    :class="cn('px-[11px] py-[6px] text-[10px] font-semibold uppercase leading-none tracking-[0.09em] text-faint', props.class)"
  >
    <slot />
  </ComboboxLabel>
</template>
