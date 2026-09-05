<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import { useVModel } from "@vueuse/core"
import { useId } from "vue"
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
 *
 * `segmented` is the same control drawn as a rail with a sliding thumb, for
 * the view switchers. A variant rather than its own component because the
 * decision is identical — one of N, spelled out rather than hidden in a menu
 * — and because the radios are what keep it from ever landing on no view at
 * all, which a group of buttons has to be told not to do.
 */
const props = withDefaults(defineProps<{
  modelValue?: string | null
  options: { value: string; label: string }[]
  label?: string
  variant?: "default" | "segmented"
  class?: HTMLAttributes["class"]
}>(), { variant: "default" })

const emits = defineEmits<{
  (e: "update:modelValue", payload: string): void
}>()

const model = useVModel(props, "modelValue", emits, { passive: true })

/*
 * Without a shared name the arrow keys walk every unnamed radio in the
 * document, so two groups on one screen behave as one ring — /galerien/:id
 * carries four.
 */
const name = useId()

const CONTAINER = {
  default: "flex flex-wrap gap-1.5",
  segmented: "flex rounded-lg bg-sunk-2 p-0.5",
}

const ITEM = {
  default:
    "inline-block cursor-pointer rounded-[var(--r-pill)] bg-sunk px-3 py-1.5 text-[12.5px] text-foreground peer-checked:bg-foreground peer-checked:text-paper",
  segmented:
    "inline-block cursor-pointer rounded-[6px] px-3.5 py-1 text-[12.5px] leading-[1.4] font-normal text-foreground transition-colors duration-[120ms] ease-out peer-checked:bg-paper peer-checked:font-medium",
}
</script>

<template>
  <component
    :is="label ? 'fieldset' : 'div'"
    data-slot="choice-group"
    :class="cn(label ? 'flex min-w-0 flex-col gap-1.5' : CONTAINER[variant], props.class)"
  >
    <legend v-if="label" :class="labelVariants()">{{ label }}</legend>
    <div :class="variant === 'segmented' ? 'contents' : label && 'flex flex-wrap gap-1.5'">
      <label v-for="option in options" :key="option.value" class="relative">
        <input
          v-model="model"
          class="peer pointer-events-none absolute opacity-0"
          :name="name"
          type="radio"
          :value="option.value"
        >
        <span :class="ITEM[variant]">{{ option.label }}</span>
      </label>
    </div>
  </component>
</template>
