<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import { cn } from "@/lib/utils"

/*
 * portal.css `.field`, as a component. Not a variant of `ui/field`: that one
 * is a column around a shadcn control and leaves the control alone, this one
 * wraps its control in a <label> and restyles the input itself — full width,
 * 12px of padding, a 16px face. Folding the two together would mean choosing
 * whose input wins, and the portal's is the one eighteen screens were built
 * against.
 *
 * The input rules are descendant variants rather than classes on the control,
 * because `.field input` reached whatever the slot happened to hold — an
 * Input, a DatePicker, a native date field — and the call sites do not all
 * know which.
 */
const props = defineProps<{
  label: string
  /** The mark a caption carries when its field may stay empty. */
  optional?: string
  hint?: string
  class?: HTMLAttributes["class"]
}>()

const INPUT = [
  '[&_input]:w-full [&_input]:rounded-md [&_input]:border [&_input]:border-line-hover',
  '[&_input]:bg-background [&_input]:px-3.5 [&_input]:py-3 [&_input]:font-sans',
  '[&_input]:text-[16px] [&_input]:leading-[1.2] [&_input]:font-medium [&_input]:text-foreground',
  // the room the calendar button needs, claimed at the same level as above
  '[&_[data-slot=date-picker]_input]:pr-9',
].join(' ')

/* portal.css `.field em`, which is unlayered and therefore reached both marks
   a caption can carry: the hint under the control and the "optional" beside
   the label. Beside is what it was written for; block is what it does. */
const MARK = 'mt-[7px] block text-[12.5px] leading-[1.45] font-normal not-italic text-faint'
</script>

<template>
  <!-- text-[10px]/leading-none on the wrapper, not only on the caption: a
       control is inline-level, so the label's own strut is part of the line
       box it sits in. At the inherited 15px/1.55 a textarea gains 4px of
       descender space it never had. -->
  <label :class="cn('mb-5 block text-[10px] leading-none', INPUT, props.class)">
    <span class="mb-2 block text-[10px] leading-none font-semibold tracking-[0.09em] uppercase text-faint">
      {{ label }}
      <em v-if="optional" :class="[MARK, 'ml-1.5 tracking-[0.02em] normal-case']">· {{ optional }}</em>
    </span>
    <slot />
    <em v-if="hint" :class="MARK">{{ hint }}</em>
  </label>
</template>
