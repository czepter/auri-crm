<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import { cn } from "@/lib/utils"

/*
 * portal.css `.pwrap` and `.pwrap.wide`, as a component. Six screens open
 * with the same <main>, and the only thing they disagree on is whether the
 * column is a reading width or a working one.
 */
const props = defineProps<{
  /** `.pwrap.wide` — 1060px, for the screens that put two columns side by side. */
  wide?: boolean
  class?: HTMLAttributes["class"]
}>()
</script>

<template>
  <main :class="cn('mx-auto px-8 pt-11 pb-16', wide ? 'max-w-[1060px]' : 'max-w-[740px]', props.class)">
    <slot />
  </main>
</template>
