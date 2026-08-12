<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import { cn } from "@/lib/utils"

const props = defineProps<{ class?: HTMLAttributes["class"] }>()
</script>

<template>
  <!-- .formfoot's own shape — actions left, in reading order, never a pair of
       buttons shoved to the right corner. The primary is first because that
       is where every other form on this surface puts it.
       The ml-0 undoes .btn's margin-left:auto, which the default button
       variant still carries: left alone it flings the primary to the right
       edge and drags the cancel along behind it. -->
  <div
    data-slot="dialog-footer"
    :class="cn(
      'flex items-center gap-[var(--s4)] border-t border-border px-[var(--s5)] py-[var(--s4)]',
      '[&>[data-slot=button]]:ml-0',
      props.class,
    )"
  >
    <slot />
  </div>
</template>
