<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import type { TableCellVariants } from "."
import { cn } from "@/lib/utils"
import { tableCellVariants } from "."

const props = defineProps<{
  tone?: TableCellVariants["tone"]
  align?: TableCellVariants["align"]
  class?: HTMLAttributes["class"]
}>()
</script>

<template>
  <td
    data-slot="table-cell"
    :class="cn(tableCellVariants({ tone, align }), props.class)"
  >
    <slot />
  </td>
</template>
