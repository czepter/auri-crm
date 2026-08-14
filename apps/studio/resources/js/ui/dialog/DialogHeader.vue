<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import { cn } from "@/lib/utils"

const props = defineProps<{ class?: HTMLAttributes["class"] }>()
</script>

<template>
  <!-- the hairline under the header is the same one .panel and .trow use;
       a dialog is a card that happens to float -->
  <div
    data-slot="dialog-header"
    :class="cn('flex flex-col gap-1 border-b border-border px-[var(--s5)] py-[var(--s4)] pr-[var(--s6)]', props.class)"
  >
    <slot />
  </div>
</template>
