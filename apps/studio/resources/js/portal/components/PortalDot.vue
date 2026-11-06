<script setup lang="ts">
/*
 * mockups/redesign/tokens.css `.dot`. Colour on this surface is a signal and
 * nothing else: warm means something is waiting on the client, green means
 * it is done, and the neutral grey is everything in between.
 *
 * The grey is measured against --sunk rather than against white, because the
 * rows it stands in sink on hover.
 */
withDefaults(defineProps<{ state?: 'open' | 'done' | 'neutral' | 'late' }>(), { state: 'neutral' })
</script>

<template>
  <span
    class="size-1.5 flex-none rounded-full"
    :class="{ open: 'bg-action', done: 'bg-ok', neutral: 'bg-mark', late: 'bg-destructive' }[state]"
  />
</template>
