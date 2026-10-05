<script setup lang="ts" generic="T extends LineItemRow">
import { formatEurosRounded } from '@/admin/lib/money'

export type LineItemRow = {
  id: string
  title: string
  description?: string | null
  quantity: number
  unitPrice: number | null
}

defineProps<{ items: T[] }>()
</script>

<template>
  <div>
    <div
      v-for="item in items"
      :key="item.id"
      class="flex items-baseline gap-3.5 py-2 shadow-[inset_0_1px_0_var(--rule)] first:shadow-none"
    >
      <span class="min-w-0 flex-1 text-ui leading-[1.47]">
        {{ item.title }}
        <span v-if="item.description" class="text-small text-ink-2"> · {{ item.description }}</span>
        <slot name="note" :item="item" />
      </span>
      <span class="w-14 flex-none text-right text-small leading-[1.47] tabular-nums text-ink-2">{{ item.quantity }}×</span>
      <!-- A position without a price is an included service, not a zero:
           unit_price is nullable in both package_items and quote_items for
           exactly that. -->
      <span class="w-28 flex-none text-right text-small leading-[1.47] tabular-nums">
        {{ item.unitPrice === null ? $t('packages.included') : formatEurosRounded(item.unitPrice) }}
        <slot name="priceNote" :item="item" />
      </span>
    </div>
  </div>
</template>
