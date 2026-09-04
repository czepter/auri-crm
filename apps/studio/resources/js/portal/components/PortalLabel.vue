<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import { cn } from "@/lib/utils"

/*
 * mockups/redesign/tokens.css `.eyebrow`. Every section on this surface is
 * named by one, and the pair of it against a 46–86px serif is what carries
 * the hierarchy here — which is why there is no third grey to lean on.
 */
const props = defineProps<{ class?: HTMLAttributes["class"] }>()
</script>

<template>
  <p :class="cn('m-0 text-[11px] leading-none font-semibold tracking-[0.17em] uppercase text-muted-foreground', props.class)">
    <slot />
  </p>
</template>
