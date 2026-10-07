<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import { cn } from "@/lib/utils"

const props = defineProps<{
  align?: "left" | "right"
  class?: HTMLAttributes["class"]
}>()
</script>

<template>
  <th
    data-slot="table-head"
    :class="
      cn(
        'px-6 first:pl-8 last:pr-8 pb-2 text-left text-small leading-none font-semibold tracking-[0.06em] uppercase text-ink-2',
        align === 'right' && 'text-right',
        props.class,
      )
    "
  >
    <slot />
  </th>
</template>
