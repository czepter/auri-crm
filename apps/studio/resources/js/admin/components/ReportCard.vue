<script setup lang="ts">
defineProps<{ title: string; sub?: string }>()
</script>

<template>
  <div class="flex flex-col gap-4 rounded-[var(--r)] bg-paper shadow-card p-6">
    <div class="flex items-start justify-between gap-4">
      <div class="flex flex-col gap-1">
        <span class="text-[15px] leading-none font-semibold tracking-[-0.015em] text-foreground">{{ title }}</span>
        <span v-if="sub" class="text-small leading-[1.35] text-muted-foreground">{{ sub }}</span>
      </div>
      <slot name="head" />
    </div>
    <slot />
  </div>
</template>
