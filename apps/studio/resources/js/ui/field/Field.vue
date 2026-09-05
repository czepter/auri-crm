<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import { useId } from "vue"
import { Label } from "@/ui/label"
import { cn } from "@/lib/utils"

/*
 * forms.css `.fld` and the caption, hint and error that sat under it, as a
 * component. 165 call sites across 37 files wrote the same four elements by
 * hand; that repetition is what the Tailwind-only migration would otherwise
 * have copied out 165 times
 * (docs/superpowers/specs/2026-08-19-tailwind-only-migration-design.md, §5).
 *
 * The id is handed to the slot rather than taken from the caller: a caption
 * and its control have to agree on one, and a form with nineteen fields is
 * nineteen chances to mistype it. Callers that already own an id — a test
 * selector, a label elsewhere on the page — pass it and keep it.
 */
const props = defineProps<{
  label?: string
  hint?: string
  error?: string
  /** `.fld.wide`: the field takes the whole row of a two-column grid. */
  wide?: boolean
  /** crm.css `.field`: the admin's tighter stack with the quieter caption. */
  dense?: boolean
  id?: string
  class?: HTMLAttributes["class"]
}>()

const fallback = useId()
const id = () => props.id ?? fallback
</script>

<template>
  <div :class="cn('flex min-w-0 flex-col gap-1.5', dense && 'gap-1', wide && 'col-span-full', props.class)">
    <Label v-if="label" :for="id()" :variant="dense ? 'micro' : 'field'">{{ label }}</Label>
    <slot :id="id()" />
    <span v-if="hint" class="text-[11.5px] leading-[1.45] text-faint">{{ hint }}</span>
    <p v-if="error" class="text-[11.5px] leading-[1.45] text-destructive">{{ error }}</p>
  </div>
</template>
