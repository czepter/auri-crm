<script setup lang="ts">
withDefaults(defineProps<{ tone?: 'success' | 'warn' | 'danger' | 'neutral' }>(), { tone: 'neutral' })
</script>

<template>
  <span
    class="inline-flex items-center rounded-[var(--r-pill)] px-2.5 py-1 text-[12.5px] leading-none font-semibold whitespace-nowrap"
    :class="{
      success: 'bg-ok-bg text-ok',
      warn: 'bg-due-bg text-due',
      danger: 'bg-destructive/10 text-destructive',
      neutral: 'bg-sunk text-muted-foreground',
    }[tone]"
  >
    <slot />
  </span>
</template>
