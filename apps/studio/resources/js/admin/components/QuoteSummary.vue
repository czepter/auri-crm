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
    <div v-if="superseded.length" class="mb-2.5 flex flex-wrap items-baseline gap-2">
      <span
        v-for="old in superseded"
        :key="old.id"
        class="rounded-[var(--r-pill)] bg-sunk px-[11px] py-[5px] text-small leading-[1.4] text-ink-2 line-through"
      >
        {{ old.number ?? $t('quotes.draft') }} {{ formatEurosRounded(old.net) }}
      </span>
    </div>

    <div>
      <LineItems :items="top.shown" />
      <div v-if="top.rest" class="py-2 text-small leading-[1.47] text-ink-2 shadow-[inset_0_1px_0_var(--rule)]">
        {{ $t('quotes.more', { count: top.rest }) }}
      </div>
    </div>

    <div class="flex items-baseline gap-3.5 pt-2.5 shadow-[inset_0_1px_0_var(--line-hover)]">
      <span class="min-w-0 flex-1 text-small leading-[1.47] font-semibold">{{ $t('quotes.total') }}</span>
      <span class="w-28 flex-none text-right text-body leading-[1.47] font-semibold tracking-[-0.018em] tabular-nums">
        {{ formatEurosRounded(quote.net) }}
      </span>
    </div>

    <p v-if="validUntilLabel" class="mt-[7px] text-small leading-[1.47] text-ink-2">{{ validUntilLabel }}</p>
  </div>
</template>
