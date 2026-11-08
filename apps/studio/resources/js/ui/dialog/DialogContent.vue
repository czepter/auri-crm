<script setup lang="ts">
import type { DialogContentEmits, DialogContentProps } from "reka-ui"
import type { HTMLAttributes } from "vue"
import { reactiveOmit } from "@vueuse/core"
import { X } from "lucide-vue-next"
import { DialogClose, DialogContent, DialogOverlay, DialogPortal, useForwardPropsEmits } from "reka-ui"
import { cn } from "@/lib/utils"
import { buttonVariants } from "@/ui/button"

const props = defineProps<DialogContentProps & {
  class?: HTMLAttributes["class"]
  /** hides the corner close button — for a dialog that must be answered, not dismissed */
  hideClose?: boolean
}>()

const emits = defineEmits<DialogContentEmits>()

const delegatedProps = reactiveOmit(props, "class", "hideClose")
const forwarded = useForwardPropsEmits(delegatedProps, emits)
</script>

<template>
  <DialogPortal>
    <!-- The scrim is --ink at 30%, not black: every neutral in this system is
         warmed toward the accent, and a cold scrim over a warm page reads as
         a different product's modal dropped on top. No blur — nothing else
         here blurs. -->
    <DialogOverlay
      data-slot="dialog-overlay"
      class="fixed inset-0 z-50 min-h-screen bg-[rgb(20_16_14_/_0.30)]"
    />
    <DialogContent
      data-slot="dialog-panel"
      v-bind="forwarded"
      :class="cn(
        'fixed left-1/2 top-1/2 z-50 flex w-[calc(100vw-2*var(--s5))] max-w-[560px] max-h-[85vh]',
        '-translate-x-1/2 -translate-y-1/2 flex-col',
        'rounded-md border border-line-hover bg-background font-sans text-foreground',
        'shadow-[0_24px_64px_rgb(20_16_14_/_0.16)] outline-none',
        props.class,
      )"
    >
      <slot />

      <DialogClose
        v-if="!hideClose"
        :class="cn(
          buttonVariants({ variant: 'ghost' }),
          'absolute right-[var(--s4)] top-[var(--s4)] p-0 [&_svg]:size-[15px] [&_svg]:opacity-100',
        )"
      >
        <X />
        <span class="sr-only">{{ $t('common.close') }}</span>
      </DialogClose>
    </DialogContent>
  </DialogPortal>
</template>
