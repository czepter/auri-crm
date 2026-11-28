<script setup lang="ts">
defineProps<{
  label: string
  value: string
  sub?: string
  delta?: string
  /* Richtung statt Farbe am Aufrufer: eine Quote, die faellt, ist rot, auch
     wenn die Zahl daneben steigt. */
  tone?: 'up' | 'down' | 'flat'
}>()
</script>

<template>
  <div class="flex flex-col gap-3 surface-card px-6 py-5">
    <span class="text-small leading-none font-medium text-ink-2">{{ label }}</span>

    <div class="flex flex-col gap-1.5">
      <b class="text-[34px] leading-10 font-semibold tracking-[-0.03em] tabular-nums text-foreground">{{ value }}</b>
      <span v-if="sub" class="text-small leading-[1.35] text-muted-foreground">{{ sub }}</span>
    </div>

    <span
      v-if="delta"
      class="inline-flex items-center gap-1.5 self-start rounded-[var(--r-sm)] px-2 py-1 text-small leading-none font-semibold"
      :class="{
        'bg-ok-bg text-ok': tone === 'up',
        'bg-late-bg text-destructive': tone === 'down',
        'bg-muted text-muted-foreground': tone === undefined || tone === 'flat',
      }"
    >
      <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path v-if="tone === 'up'" d="M5 15l7-7 7 7" />
        <path v-else-if="tone === 'down'" d="M5 9l7 7 7-7" />
        <path v-else d="M5 12h14" />
      </svg>
      {{ delta }}
    </span>
  </div>
</template>
