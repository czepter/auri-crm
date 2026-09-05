<script setup lang="ts">
import type { AlertDialogActionProps } from "reka-ui"
import type { HTMLAttributes } from "vue"
import type { ButtonVariants } from "@/ui/button"
import { reactiveOmit } from "@vueuse/core"
import { AlertDialogAction } from "reka-ui"
import { cn } from "@/lib/utils"
import { buttonVariants } from "@/ui/button"

const props = defineProps<AlertDialogActionProps & {
  tone?: ButtonVariants["tone"]
  class?: HTMLAttributes["class"]
}>()

const delegatedProps = reactiveOmit(props, "class", "tone")
</script>

<template>
  <!-- ml-0: the default button carries .btn's margin-left:auto, which in a
       two-button footer would fling the confirm to the far edge -->
  <AlertDialogAction
    data-slot="alert-dialog-action"
    v-bind="delegatedProps"
    :class="cn(buttonVariants({ variant: 'default', tone }), 'ml-0', props.class)"
  >
    <slot />
  </AlertDialogAction>
</template>
