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
  /** Zeichen statt Wort: quadratische Felder, die Beschriftung trägt das Eingabefeld. */
  icon?: boolean
  class?: HTMLAttributes["class"]
}>(), { variant: "default", icon: false })

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
  segmented: "flex max-w-full overflow-x-auto whitespace-nowrap rounded-lg bg-sunk p-[3px]",
}

const ITEM = {
  default:
    "inline-block cursor-pointer rounded-[var(--r-sm)] bg-sunk px-3 py-1.5 text-small text-foreground peer-checked:bg-foreground peer-checked:text-paper",
  segmented:
    "inline-block cursor-pointer rounded-[6px] px-3.5 py-1 text-small leading-[1.4] font-normal text-foreground transition-colors duration-[120ms] ease-out peer-checked:bg-paper peer-checked:font-medium peer-checked:shadow-[0_1px_2px_rgba(24,24,27,.08)]",
}

/* Ein Zeichen füllt keine Textzeile: es steht mittig in einem Feld, das so
   hoch ist wie breit, sonst liegt es in einer flachen Pille. */
const ICON_ITEM
  = "flex size-[26px] cursor-pointer items-center justify-center rounded-[6px] text-foreground transition-colors duration-[120ms] ease-out peer-checked:bg-paper [&_svg]:size-[15px] [&_svg]:stroke-[1.75]"
</script>

<template>
  <component
    :is="label ? 'fieldset' : 'div'"
    data-slot="choice-group"
    :class="cn(label ? 'flex min-w-0 flex-col' : CONTAINER[variant], props.class)"
  >
    <!-- Der Abstand hängt am Rand statt am `gap` des fieldset: ein legend ist
         kein Flex-Item, das gap greift an ihm also nicht, und ein Browser, der
         es doch als eines führte, zählte beide zusammen. -->
    <legend v-if="label" :class="cn('mb-1.5', labelVariants())">{{ label }}</legend>
    <!-- Mit Beschriftung trägt diese Zeile den Container, ohne sie tut es das
         fieldset selbst und die Optionen fallen per `contents` hindurch. Der
         Rail des `segmented` stand vorher nie, wenn eine Beschriftung dabei
         war — die Optionen kamen dann als Spalte. -->
    <div :class="label ? cn(CONTAINER[variant], variant === 'segmented' && 'self-start') : 'contents'">
      <label v-for="option in options" :key="option.value" class="relative">
        <input
          v-model="model"
          :aria-label="option.label"
          class="peer pointer-events-none absolute opacity-0"
          :name="name"
          type="radio"
          :value="option.value"
        >
        <!-- Der Slot ist für die Fälle, in denen ein Zeichen kürzer ist als
             sein Wort — die Beschriftung bleibt am Eingabefeld und damit für
             den Screenreader stehen. -->
        <span :class="icon ? ICON_ITEM : ITEM[variant]"><slot name="option" :option="option">{{ option.label }}</slot></span>
      </label>
    </div>
  </component>
</template>
