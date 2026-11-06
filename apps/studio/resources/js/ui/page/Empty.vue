<script setup lang="ts">
defineProps<{ text?: string }>()
</script>

<template>
  <div class="mx-auto flex w-full max-w-[480px] flex-col items-center gap-3 px-6 py-16 text-center">
    <svg class="size-9 fill-none stroke-ink-2 stroke-[1.5] [stroke-linecap:round] [stroke-linejoin:round]" viewBox="0 0 24 24" aria-hidden="true">
      <path d="M3 13l3-8h12l3 8v6a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1z" />
      <path d="M3 13h5l1.5 2.5h5L16 13h5" />
    </svg>
    <span v-if="text" class="text-[16px] font-semibold leading-[1.3] text-foreground">{{ text }}</span>
    <div v-if="$slots.default" class="mt-1 flex flex-wrap items-center justify-center gap-3"><slot /></div>
  </div>
</template>
