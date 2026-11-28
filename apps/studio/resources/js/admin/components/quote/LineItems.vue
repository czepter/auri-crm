<script setup lang="ts" generic="T extends LineItemRow">
import { formatEurosRounded } from '@/lib/money'

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
      class="flex items-baseline gap-3.5 py-2 border-t border-rule first:border-t-0"
    >
      <span class="min-w-0 flex-1 text-ui leading-[1.47]">
        {{ item.title }}
        <span v-if="item.description" class="text-small text-ink-2"> · {{ item.description }}</span>
        <slot name="note" :item="item" />
      </span>
      <span class="flex-none text-right text-small leading-[1.47] whitespace-nowrap tabular-nums text-ink-2">{{
        item.quantity > 1 && item.unitPrice !== null ? `${item.quantity} × ${formatEurosRounded(item.unitPrice)}` : ''
      }}</span>
      <!-- A position without a price is an included service, not a zero:
           unit_price is nullable in both package_items and quote_items for
           exactly that. -->
      <span class="w-28 flex-none text-right text-small leading-[1.47] tabular-nums">
        {{ item.unitPrice === null ? $t('packages.included') : formatEurosRounded(item.unitPrice * item.quantity) }}
        <slot name="priceNote" :item="item" />
      </span>
    </div>
  </div>
</template>
