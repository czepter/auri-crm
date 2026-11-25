<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import LineItems from '@/admin/components/quote/LineItems.vue'
import { formatEuros, formatEurosRounded, formatPercent } from '@/lib/money'
import { grossTotal, topLines } from '@/admin/lib/quoteSummary'

const props = defineProps<{
  quote: App.Quotes.Data.QuoteData
  superseded: { id: string; number: string | null; net: number }[]
  validUntilLabel: string | null
}>()

const top = computed(() => topLines(props.quote.items))

const { t } = useI18n()

const totals = computed(() => {
  const { net, vatAmount, vatMode, vatRate } = props.quote

  return vatMode === 'standard'
    ? [
        { label: t('quotes.net'), value: net, bold: false },
        { label: t('quotes.vat', { rate: formatPercent(vatRate) }), value: vatAmount, bold: false },
        { label: t('quotes.total'), value: grossTotal(props.quote), bold: true },
      ]
    : [{ label: t('quotes.total'), value: net, bold: true }]
})
</script>

<template>
  <div>
    <div v-if="superseded.length" class="mb-2.5 flex flex-wrap items-baseline gap-2">
      <span
        v-for="old in superseded"
        :key="old.id"
        class="rounded-[var(--r-sm)] bg-sunk px-[11px] py-[5px] text-small leading-[1.4] text-ink-2 line-through"
      >
        {{ old.number ?? $t('quotes.draft') }} {{ formatEurosRounded(old.net) }}
      </span>
    </div>

    <div>
      <LineItems :items="top.shown" />
      <div v-if="top.rest" class="py-2 text-small leading-[1.47] text-ink-2 border-t border-rule">
        {{ $t('quotes.more', { count: top.rest }) }}
      </div>
    </div>

    <div class="border-t border-line-hover pt-2.5">
      <div
        v-for="row in totals"
        :key="row.label"
        class="flex items-baseline gap-3.5 py-0.5"
        :class="row.bold ? 'font-semibold' : 'text-ink-2'"
      >
        <span class="min-w-0 flex-1 text-small leading-[1.47]">{{ row.label }}</span>
        <span
          class="w-28 flex-none text-right leading-[1.47] tabular-nums"
          :class="row.bold ? 'text-body tracking-[-0.018em]' : 'text-small'"
        >{{ formatEuros(row.value) }}</span>
      </div>
    </div>

    <p v-if="validUntilLabel" class="mt-[7px] text-small leading-[1.47] text-ink-2">{{ validUntilLabel }}</p>
  </div>
</template>
