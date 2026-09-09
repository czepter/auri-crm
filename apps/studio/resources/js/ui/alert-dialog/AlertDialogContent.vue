<script setup lang="ts">
import type { AlertDialogContentEmits, AlertDialogContentProps } from "reka-ui"
import type { HTMLAttributes } from "vue"
import { reactiveOmit } from "@vueuse/core"
import { AlertDialogContent, AlertDialogOverlay, AlertDialogPortal, useForwardPropsEmits } from "reka-ui"
import { cn } from "@/lib/utils"

const props = defineProps<AlertDialogContentProps & { class?: HTMLAttributes["class"] }>()
const emits = defineEmits<AlertDialogContentEmits>()

const delegatedProps = reactiveOmit(props, "class")
const forwarded = useForwardPropsEmits(delegatedProps, emits)
</script>

<template>
  <AlertDialogPortal>
    <AlertDialogOverlay
      data-slot="alert-dialog-overlay"
      class="fixed inset-0 z-50 bg-[rgb(20_16_14_/_0.30)]"
    />
    <!-- Narrower than a form dialog and with no close affordance in the
         corner: this asks one question and both answers are buttons. Reka
         also blocks Escape-to-dismiss here, which is the point — the
         difference between this and Dialog is that it must be answered. -->
    <AlertDialogContent
      data-slot="alert-dialog-panel"
      v-bind="forwarded"
      :class="cn(
        'fixed left-1/2 top-1/2 z-50 flex w-[calc(100vw-2*var(--s5))] max-w-[416px]',
        '-translate-x-1/2 -translate-y-1/2 flex-col',
        'rounded-md border border-line-hover bg-background font-sans text-foreground',
        'shadow-[0_24px_64px_rgb(20_16_14_/_0.16)] outline-none',
        props.class,
      )"
    >
      <slot />
    </AlertDialogContent>
  </AlertDialogPortal>
</template>
