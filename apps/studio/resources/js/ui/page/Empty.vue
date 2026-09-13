<script setup lang="ts">
defineProps<{ text: string }>()
</script>

<template>
  <div class="flex flex-col items-center gap-3 rounded-md border border-dashed border-border px-6 py-9">
    <span class="text-[12.5px] leading-[1.45] text-ink-2">{{ text }}</span>
    <slot />
  </div>
</template>
