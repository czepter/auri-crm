<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import Panel from '@/admin/components/crm/Panel.vue'

/*
 * Where a case came from and what it became, in one list: the inquiry, the
 * quote, the contract. A row that has no document yet says so quietly and
 * offers the one thing that creates it.
 */
export type ChainRow = {
  key: string
  label: string
  title: string
  meta?: string | null
  amount?: string | null
  href?: string | null
  action?: string | null
}

defineProps<{ title: string; rows: ChainRow[] }>()
</script>

<template>
  <Panel :title="title">
    <div
      v-for="row in rows"
      :key="row.key"
      class="grid min-h-16 grid-cols-[88px_minmax(0,1fr)_auto_auto] items-center gap-4 border-t border-rule first:border-t-0 max-[640px]:grid-cols-[72px_minmax(0,1fr)_auto]"
    >
      <span class="text-small font-medium text-ink-2">{{ row.label }}</span>
      <div class="min-w-0">
        <b class="block truncate font-semibold">{{ row.title }}</b>
        <span v-if="row.meta" class="block truncate text-small text-ink-2">{{ row.meta }}</span>
      </div>
      <span v-if="row.amount" class="font-semibold tabular-nums max-[640px]:hidden">{{ row.amount }}</span>
      <span v-else class="max-[640px]:hidden" />
      <Link v-if="row.href && row.action" class="inline-flex min-h-10 items-center text-ui font-medium text-ring no-underline hover:underline" :href="row.href">{{ row.action }}</Link>
      <span v-else />
    </div>
  </Panel>
</template>
