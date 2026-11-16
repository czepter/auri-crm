<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import { useVModel } from "@vueuse/core"
import { computed, useId, useTemplateRef } from "vue"
import { cn } from "@/lib/utils"
import { labelVariants } from "@/ui/label"
import { SlidingIndicator, useSlidingIndicator } from "@/ui/sliding-indicator"

/*
 * crm.css .choice — a handful of fixed values as buttons instead of a menu:
 * three occasions are hit faster than a dropdown is opened. The radio itself
 * stays in the markup and keeps the keyboard and the form value; only its box
 * is replaced by a segment in a rail with a sliding thumb.
 *
 * With a caption it is a fieldset with a legend rather than a div with a
 * label: a label belongs to one control, and there is no single control here
 * — the caption names the whole set, which is what a legend is for.
 */
const props = withDefaults(defineProps<{
  modelValue?: string | null
  options: { value: string; label: string }[]
  label?: string
  /** Zeichen statt Wort: quadratische Felder, die Beschriftung trägt das Eingabefeld. */
  icon?: boolean
  class?: HTMLAttributes["class"]
}>(), { icon: false })

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

const CONTAINER = "relative flex h-8 max-w-full overflow-x-auto whitespace-nowrap rounded-[var(--r-sm)] bg-sunk p-[3px] [scrollbar-width:none]"

const ITEM = "flex h-full cursor-pointer items-center rounded-[calc(var(--r-sm)-2px)] px-3 text-small font-medium text-ink-2 transition-colors duration-[120ms] ease-out peer-checked:text-foreground"

/* Ein Zeichen füllt keine Textzeile: es steht mittig in einem Feld, das so
   hoch ist wie breit, sonst liegt es in einer flachen Pille. */
const SEGMENT_THUMB = "peer-checked:bg-paper peer-checked:shadow-[0_1px_2px_rgba(24,24,27,.08)]"

const root = useTemplateRef<HTMLElement>("root")
const rail = useTemplateRef<HTMLElement>("rail")
const track = computed(() => (props.label ? rail.value : root.value))
const { rect, animate } = useSlidingIndicator(track, () => model.value, { selector: "label:has(:checked)" })

const ICON_ITEM
  = "flex size-[26px] cursor-pointer items-center justify-center rounded-[calc(var(--r-sm)-2px)] text-foreground transition-colors duration-[120ms] ease-out peer-checked:bg-paper peer-checked:shadow-[0_1px_2px_rgba(24,24,27,.08)] [&_svg]:size-[15px] [&_svg]:stroke-[1.75]"
</script>

<template>
  <component
    :is="label ? 'fieldset' : 'div'"
    ref="root"
    data-slot="choice-group"
    :class="cn(label ? 'flex min-w-0 flex-col' : CONTAINER, props.class)"
  >
    <!-- Der Abstand hängt am Rand statt am `gap` des fieldset: ein legend ist
         kein Flex-Item, das gap greift an ihm also nicht, und ein Browser, der
         es doch als eines führte, zählte beide zusammen. -->
    <legend v-if="label" :class="cn('mb-1.5', labelVariants())">{{ label }}</legend>
    <!-- Mit Beschriftung trägt diese Zeile den Container, ohne sie tut es das
         fieldset selbst und die Optionen fallen per `contents` hindurch. Der
         Rail stand vorher nie, wenn eine Beschriftung dabei
         war — die Optionen kamen dann als Spalte. -->
    <div ref="rail" :class="label ? cn(CONTAINER, 'self-start') : 'contents'">
      <SlidingIndicator
        :rect="rect"
        :animate="animate"
        class="rounded-[calc(var(--r-sm)-2px)] bg-paper shadow-[0_1px_2px_rgba(24,24,27,.08)]"
      />
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
        <span :class="[icon ? ICON_ITEM : ITEM, !icon && !rect && SEGMENT_THUMB, 'peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-ring']"><slot name="option" :option="option">{{ option.label }}</slot></span>
      </label>
    </div>
  </component>
</template>
