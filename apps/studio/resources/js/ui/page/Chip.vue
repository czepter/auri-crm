<script setup lang="ts">
export type ChipTone = 'neutral' | 'info' | 'success' | 'warn' | 'danger'

withDefaults(defineProps<{ tone?: ChipTone }>(), { tone: 'neutral' })

const TONE: Record<ChipTone, string> = {
  neutral: 'bg-muted text-muted-foreground',
  info: 'bg-[#E4EDF8] text-[#1B4A80]',
  success: 'bg-ok-bg text-ok',
  warn: 'bg-due-bg text-due',
  danger: 'bg-late-bg text-destructive',
}
</script>

<template>
  <span :class="['inline-flex flex-none items-center gap-1.5 rounded-[var(--r-sm)] px-2 py-[3px] text-small leading-[1.5] font-medium', TONE[tone]]"><slot /></span>
</template>
