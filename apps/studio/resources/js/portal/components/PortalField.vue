<script setup lang="ts">
import { useId } from "vue"
import type { HTMLAttributes } from "vue"
import { cn } from "@/lib/utils"

/*
 * mockups/redesign/portal-einstellungen.html `.fld`, as a component. Not a
 * variant of `ui/field`: that one is a column around a shadcn control and
 * leaves the control alone, this one restyles the control itself — the
 * portal's field is a hairline box on paper, sentence-case caption above it,
 * where the admin's is a sunk surface under a caption in caps.
 *
 * The control rules are descendant variants rather than classes on the
 * control, because the slot holds whatever the call site had: an Input, a
 * Textarea, a DatePicker, a native select.
 */
const props = defineProps<{
  label: string
  /** The mark a caption carries when its field may stay empty. */
  optional?: string
  hint?: string
  /** Shown between caption and control. The root stops being a label, so the control must bind the slot's controlId and describedBy. */
  help?: string
  /** The slot holds a composite control rather than one labelable element. */
  group?: boolean
  class?: HTMLAttributes["class"]
}>()

const CONTROL = [
  '[&_input]:w-full [&_input]:rounded-[var(--r-sm)] [&_input]:border [&_input]:border-input',
  '[&_input]:bg-background [&_input]:px-[13px] [&_input]:py-[11px] [&_input]:font-sans',
  '[&_input]:text-[14.5px] [&_input]:leading-[1.5] [&_input]:font-normal [&_input]:text-foreground',
  '[&_input:focus]:border-muted-foreground',
  '[&_textarea]:w-full [&_textarea]:rounded-[var(--r-sm)] [&_textarea]:border [&_textarea]:border-input',
  '[&_textarea]:bg-background [&_textarea]:px-[13px] [&_textarea]:py-[11px] [&_textarea]:font-sans',
  '[&_textarea]:text-[14.5px] [&_textarea]:leading-[1.6] [&_textarea]:text-foreground',
  '[&_textarea:focus]:border-muted-foreground',
  '[&_select]:w-full [&_select]:rounded-[var(--r-sm)] [&_select]:border [&_select]:border-input',
  '[&_select]:bg-background [&_select]:px-[13px] [&_select]:py-[11px] [&_select]:text-[14.5px]',
  // the room the calendar button needs, claimed at the same level as above
  '[&_[data-slot=date-picker]_input]:pr-9',
].join(' ')

const base = useId()
const controlId = base
const captionId = `${base}-caption`
const helpId = `${base}-help`
</script>

<template>
  <!-- leading-none on the wrapper, not only on the caption: a control is
       inline-level, so the label's own strut is part of the line box it sits
       in. At the inherited 15px/1.6 a textarea gains 5px of descender space
       it never had. -->
  <component :is="help || group ? 'div' : 'label'" :class="cn('block text-small leading-none', CONTROL, props.class)">
    <component
      :is="group ? 'span' : help ? 'label' : 'span'"
      :id="group ? captionId : undefined"
      :for="help && !group ? controlId : undefined"
      data-slot="caption"
      class="mb-[7px] block text-small leading-none font-normal text-muted-foreground"
    >
      {{ label }}
      <em v-if="optional" class="font-normal not-italic whitespace-nowrap text-muted-foreground">· {{ optional }}</em>
    </component>
    <p v-if="help" :id="helpId" class="-mt-[3px] mb-2.5 whitespace-pre-line text-[12.5px] leading-[1.45] text-muted-foreground">{{ help }}</p>
    <slot :control-id="controlId" :caption-id="captionId" :described-by="help ? helpId : undefined" />
    <em v-if="hint" class="mt-[7px] block text-[12.5px] leading-[1.45] font-normal not-italic text-muted-foreground">{{ hint }}</em>
  </component>
</template>
