<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import { cn } from "@/lib/utils"

const props = defineProps<{ class?: HTMLAttributes["class"] }>()
</script>

<template>
  <table data-slot="table" :class="cn('mt-5 w-full border-collapse', props.class)">
    <slot />
  </table>
</template>
