<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import { cn } from "@/lib/utils"

/*
 * The fixed part of the value. select-none and pointer-events-none on
 * purpose: it is not a control and it is not text to copy — clicking it puts
 * the caret in the field, which is what a user aiming at "https://|" means.
 */
const props = withDefaults(defineProps<{
  align?: "inline-start" | "inline-end"
  class?: HTMLAttributes["class"]
}>(), { align: "inline-start" })
</script>

<template>
  <span
    data-slot="input-group-addon"
    :data-align="align"
    :class="cn(
      'pointer-events-none flex select-none items-center py-[9px] font-sans text-[13px] leading-[1.35] text-faint',
      align === 'inline-start' ? 'pl-[11px]' : 'pr-[11px]',
      props.class,
    )"
  >
    <slot />
  </span>
</template>
