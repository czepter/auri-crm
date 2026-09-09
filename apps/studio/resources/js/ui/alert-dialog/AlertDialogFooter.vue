<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import { cn } from "@/lib/utils"

const props = defineProps<{ class?: HTMLAttributes["class"] }>()
</script>

<template>
  <div
    data-slot="alert-dialog-footer"
    :class="cn(
      'flex items-center gap-[var(--s4)] px-[var(--s5)] pb-[var(--s5)]',
      props.class,
    )"
  >
    <slot />
  </div>
</template>
