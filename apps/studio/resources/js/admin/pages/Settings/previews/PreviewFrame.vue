<script setup lang="ts">
defineProps<{ label: string }>()
</script>

<!--
  The right-hand column every settings pane that has something to show hangs
  its sample in. Below 1440px there is no room for a third column beside the
  app sidebar and the 620px form, so it drops out — the pane still works, it
  is the form that carries the setting.

  Everything a setting does not control renders as a grey bar inside these,
  never as invented sample text: the point is seeing your own data land.
-->
<template>
  <aside class="sticky top-6 w-[352px] flex-none max-[1440px]:hidden">
    <div class="mb-2.5 text-[12.5px] leading-none font-semibold tracking-[0.02em] uppercase text-faint">{{ label }}</div>
    <div class="rounded-lg bg-muted p-4">
      <slot />
    </div>
  </aside>
</template>
