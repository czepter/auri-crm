<script setup lang="ts">
/* tokens.css .card.static / .cardhead / .kick. */
defineProps<{ title: string }>()
</script>

<template>
  <div class="relative block cursor-default rounded-md border border-border p-2.5 text-inherit no-underline">
    <div class="mb-4 flex items-center justify-between">
      <span class="mb-[5px] text-[10px] leading-none font-semibold tracking-[0.09em] uppercase text-faint">{{ title }}</span>
    </div>
    <slot />
  </div>
</template>
