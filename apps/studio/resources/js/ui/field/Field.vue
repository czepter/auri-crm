<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import { computed, ref, useId, watch } from "vue"
import { useI18n } from "vue-i18n"
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

// Live validation: :user-invalid turns on once the person has touched a
// control. The wording is ours; the browser's own sentences and bubbles stay off.
const { t } = useI18n()
const native = ref("")

function reason(control: HTMLInputElement) {
  const v = control.validity

  if (v.customError) return null

  if (v.valueMissing) return "valueMissing"
  if (v.typeMismatch) return control.type === "email" ? "email" : "url"
  if (v.patternMismatch) return "pattern"
  if (v.tooShort) return "tooShort"
  if (v.tooLong) return "tooLong"
  if (v.rangeUnderflow) return "tooSmall"
  if (v.rangeOverflow) return "tooBig"
  if (v.stepMismatch) return "step"

  return "badInput"
}

function checkNative(event: Event) {
  const control = event.type === "invalid"
    ? (event.target as HTMLInputElement)
    : (event.currentTarget as HTMLElement).querySelector<HTMLInputElement>(":user-invalid")
  const key = control ? reason(control) : null

  native.value = key ? t(`common.invalid.${key}`) : control?.validationMessage ?? ""
}

// The text outlives the error for the length of the collapse; clearing it at
// once would shrink the row while it is still closing.
const message = computed(() => props.error || native.value)
const shown = ref(message.value)
watch(message, (next) => next && (shown.value = next))

const fallback = useId()
const id = () => props.id ?? fallback
</script>

<template>
  <div
    :class="cn('flex min-w-0 flex-col gap-1.5', dense && 'gap-1', wide && 'col-span-full', props.class)"
    @input="checkNative"
    @focusout="checkNative"
    @invalid.capture.prevent="checkNative"
  >
    <Label v-if="label" :for="id()" :variant="dense ? 'micro' : 'field'">{{ label }}</Label>
    <slot :id="id()" />
    <span v-if="hint" class="text-small leading-[1.45] text-faint">{{ hint }}</span>
    <div
      aria-live="polite"
      class="grid transition-[grid-template-rows] duration-200 ease-[cubic-bezier(0.23,1,0.32,1)] motion-reduce:transition-none"
      :class="[message ? 'grid-rows-[1fr]' : 'grid-rows-[0fr]', dense ? '-mt-1' : '-mt-1.5']"
    >
      <div class="overflow-hidden">
        <p
          class="text-small leading-[1.45] text-destructive transition-[opacity,transform] duration-200 ease-[cubic-bezier(0.23,1,0.32,1)] motion-reduce:transition-none"
          :class="[message ? 'translate-y-0 opacity-100' : '-translate-y-0.5 opacity-0', dense ? 'pt-1' : 'pt-1.5']"
        >{{ shown }}</p>
      </div>
    </div>
  </div>
</template>
