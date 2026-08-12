<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import { cn } from "@/lib/utils"

const props = defineProps<{ class?: HTMLAttributes["class"] }>()
</script>

<template>
  <!-- The scrolling part. shadcn has no DialogBody, but the forms this app
       puts in a dialog include repeatable rows (quote items, instalment due
       dates) that can outgrow the viewport — without this the header and the
       save button scroll away with them. -->
  <div
    data-slot="dialog-body"
    :class="cn('min-h-0 flex-1 overflow-y-auto px-[var(--s5)] py-[var(--s4)]', props.class)"
  >
    <slot />
  </div>
</template>
