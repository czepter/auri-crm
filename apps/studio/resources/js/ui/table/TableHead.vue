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
    scope="col"
    data-slot="table-head"
    :class="
      cn(
        'px-6 first:pl-6 last:pr-6 max-[640px]:px-2.5 max-[640px]:first:pl-4 max-[640px]:last:pr-4 pb-2 text-left text-small leading-none font-medium text-ink-2',
        align === 'right' && 'text-right',
        props.class,
      )
    "
  >
    <slot />
  </th>
</template>
