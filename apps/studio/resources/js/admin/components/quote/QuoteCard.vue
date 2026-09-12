<script setup lang="ts">
defineProps<{ title: string; summary?: string }>()
const open = defineModel<boolean>('open', { required: true })
</script>

<template>
  <div class="rounded-[20px] border border-border bg-background">
    <button
      type="button"
      class="flex w-full cursor-pointer items-center gap-3 px-5 py-4 text-left"
      :aria-expanded="open"
      @click="open = !open"
    >
      <span class="flex-1 truncate text-[14px] font-semibold tracking-[-0.02em]">{{ title }}</span>
      <span v-if="!open" class="flex min-w-0 items-center gap-2 text-[12.5px] text-ink-2">
        <slot name="summary">{{ summary }}</slot>
      </span>
      <svg
        class="size-4 flex-none text-ink-2 transition-transform duration-150"
        :class="{ 'rotate-180': open }"
        viewBox="0 0 24 24"
      ><path d="M6 9l6 6 6-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" /></svg>
    </button>

    <div v-if="open" class="border-t border-rule px-5 py-4">
      <slot />
    </div>
  </div>
</template>
