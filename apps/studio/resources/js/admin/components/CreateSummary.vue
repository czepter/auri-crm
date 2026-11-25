<script setup lang="ts">
defineProps<{ title: string; rows: { label: string; value: string | null | undefined }[] }>()
</script>

<template>
  <section class="surface-card p-5 ">
    <h2 class="m-0 mb-2 text-ui font-semibold">{{ title }}</h2>
    <dl class="m-0">
      <template v-for="row in rows" :key="row.label">
        <div class="flex justify-between gap-4 py-2 text-ui shadow-[inset_0_1px_0_var(--rule)] first:shadow-none">
          <dt class="flex-none text-ink-2">{{ row.label }}</dt>
          <dd class="m-0 min-w-0 truncate text-right font-semibold">{{ row.value || '—' }}</dd>
        </div>
      </template>
    </dl>
  </section>
</template>
