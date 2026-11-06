<script setup lang="ts">
withDefaults(defineProps<{ state?: 'due' | 'late' }>(), { state: 'due' })
</script>

<template>
  <span
    class="inline-flex items-center rounded-[var(--r-pill)] px-2.5 py-1 text-[12.5px] leading-none font-semibold whitespace-nowrap"
    :class="state === 'late' ? 'bg-destructive/10 text-destructive' : 'bg-due-bg text-due'"
  >
    <slot />
  </span>
</template>
