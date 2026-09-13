<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import { cn } from "@/lib/utils"

const props = defineProps<{ class?: HTMLAttributes["class"] }>()
</script>

<template>
  <div
    data-slot="alert-dialog-body"
    :class="cn('flex flex-col gap-4 px-[var(--s5)] pb-[var(--s4)]', props.class)"
  >
    <slot />
  </div>
</template>
