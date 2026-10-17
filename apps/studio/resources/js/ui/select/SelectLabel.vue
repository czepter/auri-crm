<script setup lang="ts">
import type { SelectLabelProps } from "reka-ui"
import type { HTMLAttributes } from "vue"
import { reactiveOmit } from "@vueuse/core"
import { SelectLabel } from "reka-ui"
import { cn } from "@/lib/utils"

const props = defineProps<SelectLabelProps & { class?: HTMLAttributes["class"] }>()

const delegatedProps = reactiveOmit(props, "class")
</script>

<template>
  <!-- the group heading inside the listbox, set like a field caption -->
  <SelectLabel
    data-slot="select-label"
    v-bind="delegatedProps"
    :class="cn('px-[11px] py-[6px] text-small font-semibold leading-none text-faint', props.class)"
  >
    <slot />
  </SelectLabel>
</template>
