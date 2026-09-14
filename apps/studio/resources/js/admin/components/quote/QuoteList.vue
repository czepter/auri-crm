<script setup lang="ts">
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import { ChevronRight } from 'lucide-vue-next'
import Card from '@/ui/page/Card.vue'
import { Button } from '@/ui/button'
import { formatEuros } from '@/admin/lib/money'
import { dayWithWeekday as day } from '@/admin/lib/dates'
import { quoteChipClass } from '@/admin/lib/quoteDisplay'

const props = defineProps<{
  projectId: string
  canManageQuotes: boolean
  quotes: App.Quotes.Data.QuoteData[]
  payments: { number: string | null; rows: { label: string; amount: number }[] } | null
}>()

const gross = (quote: App.Quotes.Data.QuoteData) => quote.net + (quote.vatMode === 'standard' ? quote.vatAmount : 0)

const total = computed(() => props.payments?.rows.reduce((sum, row) => sum + row.amount, 0) ?? 0)
</script>

<template>
  <div class="flex flex-col gap-3">
    <div v-if="canManageQuotes" class="flex">
      <Button :as="Link" class="ml-auto" variant="pill" :href="`/angebote/neu?projekt=${projectId}`">
        <svg class="size-3" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14" /></svg>
        {{ $t('quotes.create') }}
      </Button>
    </div>

    <section v-if="quotes.length" class="rounded-md border border-rule bg-background py-1.5">
      <Link
        v-for="quote in quotes"
        :key="quote.id"
        :href="`/angebote/${quote.id}`"
        class="flex items-center gap-3 px-[18px] py-3 text-[13px] text-foreground no-underline shadow-[inset_0_1px_0_var(--rule)] first:shadow-none hover:bg-sunk"
      >
        <span class="w-24 flex-none font-semibold">{{ quote.number ?? $t('quotes.draft') }}</span>
        <span :class="['chip', quoteChipClass(quote.status)]">{{ quote.statusLabel }}</span>
        <span class="min-w-0 flex-1 truncate text-ink-2">
          <template v-if="quote.sentAt">
            {{ day(quote.sentAt) }} · {{ quote.sentChannel === 'mail' ? $t('quotes.sentByMail') : $t('quotes.sentBySelf') }}
          </template>
          <template v-else-if="quote.validUntil">
            {{ $t('lead.validUntil', { date: day(quote.validUntil) }) }}
          </template>
        </span>
        <span class="w-24 flex-none text-right font-semibold tabular-nums">{{ formatEuros(gross(quote)) }}</span>
        <ChevronRight class="size-[15px] shrink-0 text-ink-2" />
      </Link>
    </section>

    <Card v-if="payments?.rows.length" :title="$t('payments.title')" :summary="payments.number">
      <div
        v-for="(row, index) in payments.rows"
        :key="index"
        class="flex gap-3 py-[9px] text-[13px] shadow-[inset_0_1px_0_var(--rule)] first:shadow-none"
      >
        <span class="flex-1">{{ row.label }}</span>
        <span class="w-28 flex-none text-right font-semibold tabular-nums">{{ formatEuros(row.amount) }}</span>
      </div>
      <div class="flex items-baseline gap-3 pt-2.5 shadow-[inset_0_1px_0_#D8D8DC]">
        <span class="flex-1 text-[13px] font-semibold">{{ $t('quotes.total') }}</span>
        <span class="w-28 flex-none text-right text-[15px] font-semibold tracking-[-0.018em] tabular-nums">{{ formatEuros(total) }}</span>
      </div>
    </Card>
  </div>
</template>
