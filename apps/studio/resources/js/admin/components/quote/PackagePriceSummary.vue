<script setup lang="ts">
import { computed } from 'vue'
import type { PackageItemRow } from '@/admin/lib/packageItem'
import { lineTotal } from '@/admin/lib/packageItem'
import { formatEuros } from '@/lib/money'

const props = defineProps<{ items: PackageItemRow[] }>()

const total = computed(() => props.items.reduce((sum, row) => sum + lineTotal(row), 0))
const selectableCount = computed(() => props.items.filter((row) => row.selectable).length)
</script>

<template>
  <div class="overflow-hidden rounded-[var(--r)] bg-paper shadow-card">
    <div class="p-[26px]">
      <div class="text-small leading-none font-semibold text-faint">{{ $t('packages.priceSummary') }}</div>
      <div class="mt-3.5 text-[42px] leading-[1.1] font-bold tracking-[-0.02em] tabular-nums">{{ formatEuros(total) }}</div>
      <div class="mt-2 text-ui text-ink-2">
        {{ $t('packages.itemCount', items.length) }}<template v-if="selectableCount">
          · {{ $t('packages.selectableCount', selectableCount) }}
        </template>
      </div>
    </div>

    <div v-if="items.length" class="flex flex-col gap-4.5 border-t border-border p-[26px]">
      <div v-for="(row, index) in items" :key="row.id ?? index" class="flex items-baseline gap-3.5">
        <span class="min-w-0 flex-1 truncate text-body font-medium">{{ row.title || $t('packages.itemTitle') }}</span>
        <span class="flex-none text-ui tabular-nums text-faint">{{ row.quantity }} ×</span>
        <span class="min-w-[72px] flex-none text-right text-body font-medium tabular-nums">{{ formatEuros(lineTotal(row)) }}</span>
      </div>
    </div>
  </div>
</template>
