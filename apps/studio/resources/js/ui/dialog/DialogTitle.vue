<script setup lang="ts">
import type { DialogTitleProps } from "reka-ui"
import type { HTMLAttributes } from "vue"
import { reactiveOmit } from "@vueuse/core"
import { DialogTitle } from "reka-ui"
import { cn } from "@/lib/utils"

const props = defineProps<DialogTitleProps & { class?: HTMLAttributes["class"] }>()

const delegatedProps = reactiveOmit(props, "class")
</script>

<template>
  <!-- tokens.css sets h1 at 700/24px/-.04em; a dialog title is the same voice
       one step down, not a different one -->
  <DialogTitle
    data-slot="dialog-title"
    v-bind="delegatedProps"
    :class="cn('m-0 text-title font-bold leading-none tracking-[-0.03em]', props.class)"
  >
    <slot />
  </DialogTitle>
</template>
