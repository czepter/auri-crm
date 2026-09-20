<script setup lang="ts">
import { computed } from 'vue'
import type { PackageItemRow } from '@/admin/lib/packageItem'
import { lineTotal } from '@/admin/lib/packageItem'
import { formatEuros } from '@/admin/lib/money'

const props = defineProps<{ items: PackageItemRow[] }>()

const total = computed(() => props.items.reduce((sum, row) => sum + lineTotal(row), 0))
const selectableCount = computed(() => props.items.filter((row) => row.selectable).length)
</script>

<template>
  <div class="overflow-hidden rounded-[10px] border border-border">
    <div class="p-[26px]">
      <div class="text-[11px] leading-none font-semibold tracking-[0.11em] uppercase text-faint">{{ $t('packages.priceSummary') }}</div>
      <div class="mt-3.5 text-[42px] leading-[1.1] font-bold tracking-[-0.02em] tabular-nums">{{ formatEuros(total) }}</div>
      <div class="mt-2 text-[14px] text-ink-2">
        {{ $t('packages.itemCount', items.length) }}<template v-if="selectableCount">
          · {{ $t('packages.selectableCount', selectableCount) }}
        </template>
      </div>
    </div>

    <div v-if="items.length" class="flex flex-col gap-4.5 border-t border-border p-[26px]">
      <div v-for="(row, index) in items" :key="row.id ?? index" class="flex items-baseline gap-3.5">
        <span class="min-w-0 flex-1 truncate text-[15px] font-medium">{{ row.title || $t('packages.itemTitle') }}</span>
        <span class="flex-none text-[13.5px] tabular-nums text-faint">{{ row.quantity }} ×</span>
        <span class="min-w-[72px] flex-none text-right text-[15px] font-medium tabular-nums">{{ formatEuros(lineTotal(row)) }}</span>
      </div>
    </div>
  </div>
</template>
