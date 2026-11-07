<script setup lang="ts">
defineProps<{ rect: { left: number; top: number; width: number; height: number } | null; animate: boolean }>()
</script>

<template>
  <span
    v-if="rect"
    aria-hidden="true"
    class="pointer-events-none absolute z-0 motion-reduce:transition-none"
    :class="animate && 'transition-[left,top,width,height] duration-200 ease-[cubic-bezier(0.23,1,0.32,1)]'"
    :style="{ left: `${rect.left}px`, top: `${rect.top}px`, width: `${rect.width}px`, height: `${rect.height}px` }"
  >
    <slot />
  </span>
</template>
