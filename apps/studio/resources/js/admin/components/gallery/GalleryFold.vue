<script setup lang="ts">
defineProps<{ label: string; value: string; error?: boolean }>()
</script>

<template>
  <details :open="error" class="shadow-[inset_0_-1px_0_var(--rule)]">
    <summary class="flex cursor-pointer items-baseline gap-2 py-3 text-small">
      <span class="font-medium">{{ label }}</span>
      <span class="text-ink-2">·</span>
      <span class="min-w-0 truncate text-ink-2">{{ value }}</span>
    </summary>
    <div class="pb-4">
      <slot />
    </div>
  </details>
</template>
