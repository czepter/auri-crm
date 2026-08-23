<script setup lang="ts">
import { computed } from 'vue'
import LineItems from '@/admin/components/LineItems.vue'
import { formatEurosRounded } from '@/admin/lib/money'
import { topLines } from '@/admin/lib/quoteSummary'

const props = defineProps<{
  quote: App.Quotes.Data.QuoteData
  superseded: { id: string; number: string | null; net: number }[]
  validUntilLabel: string | null
}>()

const top = computed(() => topLines(props.quote.items))
</script>

<template>
  <div>
    <div class="flex flex-wrap items-baseline gap-2">
      <!-- Ein Entwurf trägt keine Nummer: SendQuote vergibt sie erst beim Versenden. -->
      <span class="rounded-[var(--r-pill)] bg-sunk-2 px-[11px] py-[5px] text-[12px] leading-[1.4]">
        <b class="font-semibold">{{ quote.number ?? $t('quotes.draft') }}</b>
        {{ quote.statusLabel }}
      </span>
      <span
        v-for="old in superseded"
        :key="old.id"
        class="rounded-[var(--r-pill)] bg-sunk px-[11px] py-[5px] text-[12px] leading-[1.4] text-ink-2 line-through"
      >
        {{ old.number ?? $t('quotes.draft') }} {{ formatEurosRounded(old.net) }}
      </span>
    </div>

    <div class="mt-2.5">
      <LineItems :items="top.shown" />
      <div v-if="top.rest" class="py-2 text-[12.5px] leading-[1.47] text-ink-2 shadow-[inset_0_1px_0_var(--rule)]">
        {{ $t('quotes.more', { count: top.rest }) }}
      </div>
    </div>

    <div class="flex items-baseline gap-3.5 pt-2.5 shadow-[inset_0_1px_0_#D8D8DC]">
      <span class="min-w-0 flex-1 text-[13px] leading-[1.47] font-semibold">{{ $t('quotes.total') }}</span>
      <span class="w-28 flex-none text-right text-[15px] leading-[1.47] font-semibold tracking-[-0.018em] tabular-nums">
        {{ formatEurosRounded(quote.net) }}
      </span>
    </div>

    <p v-if="validUntilLabel" class="mt-[7px] text-[12px] leading-[1.47] text-ink-2">{{ validUntilLabel }}</p>
  </div>
</template>
