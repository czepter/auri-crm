<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import { useVModel } from "@vueuse/core"
import { cn } from "@/lib/utils"

/*
 * ui/input's field with the shell taken off — InputGroup draws the border,
 * the background and the focus colour for the whole row. The horizontal
 * padding is asymmetric on purpose: an addon already spends 11px on its own
 * side, and a second 11px between `https://` and the host would read as two
 * values rather than one.
 */
const props = defineProps<{
  defaultValue?: string | number
  modelValue?: string | number
  class?: HTMLAttributes["class"]
}>()

const emits = defineEmits<{
  (e: "update:modelValue", payload: string | number): void
}>()

const modelValue = useVModel(props, "modelValue", emits, {
  passive: true,
  defaultValue: props.defaultValue,
})
</script>

<template>
  <input
    v-model="modelValue"
    data-slot="input-group-input"
    :class="cn(
      'w-full min-w-0 flex-1 appearance-none border-0 bg-transparent px-1.5 py-[9px] font-sans text-[13px] font-normal leading-[1.35] text-foreground',
      'placeholder:text-faint focus:outline-none',
      'disabled:cursor-not-allowed',
      'first:pl-[11px] last:pr-[11px]',
      props.class,
    )"
  >
</template>
