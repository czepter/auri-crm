<script setup lang="ts">
/*
 * The document as the place it is written: studio and number at the head,
 * the content on the sheet itself. One frame for the invoice, the Gutschrift
 * and every other Beleg, so that what is edited reads like what is sent.
 */
defineProps<{ studio: string; kind: string; number?: string | null; date?: string | null }>()
</script>

<template>
  <section class="rounded-lg bg-muted p-6 max-[640px]:p-3">
    <div class="mx-auto flex max-w-[900px] flex-col gap-6 rounded-md bg-background p-12 shadow-sm max-[640px]:p-5">
      <header class="flex items-start justify-between gap-6">
        <div class="text-[18px] font-semibold">{{ studio }}</div>
        <div class="text-right">
          <div class="text-[20px] leading-7 font-semibold tracking-[-0.02em]">{{ kind }}<template v-if="number"> {{ number }}</template></div>
          <div v-if="date" class="text-small text-ink-2">{{ date }}</div>
        </div>
      </header>
      <slot />
    </div>
  </section>
</template>
