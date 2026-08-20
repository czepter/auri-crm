<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import { useVModel } from "@vueuse/core"
import { cn } from "@/lib/utils"
import { labelVariants } from "@/ui/label"

/*
 * crm.css .choice — a handful of fixed values as buttons instead of a menu:
 * three occasions are hit faster than a dropdown is opened. The radio itself
 * stays in the markup and keeps the keyboard and the form value; only its box
 * is replaced by the pill beside it.
 *
 * With a caption it is a fieldset with a legend rather than a div with a
 * label: a label belongs to one control, and there is no single control here
 * — the caption names the whole set, which is what a legend is for.
 */
const props = defineProps<{
  modelValue?: string | null
  options: { value: string; label: string }[]
  label?: string
  class?: HTMLAttributes["class"]
}>()

const emits = defineEmits<{
  (e: "update:modelValue", payload: string): void
}>()

const model = useVModel(props, "modelValue", emits, { passive: true })
</script>

<template>
  <component
    :is="label ? 'fieldset' : 'div'"
    data-slot="choice-group"
    :class="cn(label ? 'flex min-w-0 flex-col gap-1.5' : 'flex flex-wrap gap-1.5', props.class)"
  >
    <legend v-if="label" :class="labelVariants()">{{ label }}</legend>
    <div :class="label && 'flex flex-wrap gap-1.5'">
      <label v-for="option in options" :key="option.value" class="relative">
        <input
          v-model="model"
          class="peer pointer-events-none absolute opacity-0"
          type="radio"
          :value="option.value"
        >
        <span
          class="inline-block cursor-pointer rounded-[var(--r-pill)] bg-sunk px-3 py-1.5 text-[12.5px] text-foreground peer-checked:bg-foreground peer-checked:text-paper"
        >{{ option.label }}</span>
      </label>
    </div>
  </component>
</template>
