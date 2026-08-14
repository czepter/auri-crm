<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import { cn } from "@/lib/utils"

const props = defineProps<{ class?: HTMLAttributes["class"] }>()
</script>

<template>
  <!-- no hairline under this one: with a single question and two buttons the
       panel is one thought, and a rule across it would split it into two -->
  <div
    data-slot="alert-dialog-header"
    :class="cn('flex flex-col gap-[var(--s2)] px-[var(--s5)] pb-[var(--s4)] pt-[var(--s5)]', props.class)"
  >
    <slot />
  </div>
</template>
