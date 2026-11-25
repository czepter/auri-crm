<script setup lang="ts">
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import { ChevronRight } from 'lucide-vue-next'
import Card from '@/ui/page/Card.vue'
import { Button } from '@/ui/button'
import { formatEuros } from '@/lib/money'
import { dayWithWeekday as day } from '@/admin/lib/dates'
import { quoteTone } from '@/admin/lib/quoteDisplay'
import Chip from '@/ui/page/Chip.vue'

const props = defineProps<{
  projectId: string
  canManageQuotes: boolean
  quotes: App.Quotes.Data.QuoteData[]
  payments: { number: string | null; rows: { label: string; amount: number }[] } | null
}>()

const gross = (quote: App.Quotes.Data.QuoteData) => quote.net + (quote.vatMode === 'standard' ? quote.vatAmount : 0)

const hasAccepted = computed(() => props.quotes.some((quote) => quote.status === 'accepted'))

const total = computed(() => props.payments?.rows.reduce((sum, row) => sum + row.amount, 0) ?? 0)
</script>

<template>
  <div class="flex flex-col gap-3">
    <div v-if="canManageQuotes" class="flex">
      <Button :as="Link" class="ml-auto" :variant="hasAccepted ? 'outline' : 'default'" :href="`/angebote/neu?projekt=${projectId}`">
        <svg class="size-3" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14" /></svg>
        {{ $t('quotes.create') }}
      </Button>
    </div>

    <section v-if="quotes.length" class="surface-card py-1.5">
      <Link
        v-for="quote in quotes"
        :key="quote.id"
        :href="`/angebote/${quote.id}`"
        class="flex items-center gap-3 px-[18px] py-3 max-[640px]:gap-2 max-[640px]:px-3 text-small text-foreground no-underline border-t border-rule first:border-t-0 hover:bg-sunk"
      >
        <span class="w-24 flex-none font-semibold max-[640px]:w-auto">{{ quote.number ?? $t('quotes.draft') }}</span>
        <Chip :tone="quoteTone(quote.status)">{{ quote.statusLabel }}</Chip>
        <span class="min-w-0 flex-1 truncate text-ink-2">
          <template v-if="quote.sentAt">
            {{ day(quote.sentAt) }} · {{ quote.sentChannel === 'mail' ? $t('quotes.sentByMail') : $t('quotes.sentBySelf') }}
          </template>
          <template v-else-if="quote.validUntil">
            {{ $t('lead.validUntil', { date: day(quote.validUntil) }) }}
          </template>
        </span>
        <span class="w-24 flex-none text-right font-semibold tabular-nums max-[640px]:w-auto">{{ formatEuros(gross(quote)) }}</span>
        <ChevronRight class="size-[15px] shrink-0 text-ink-2" />
      </Link>
    </section>

    <Card v-if="payments?.rows.length" :title="$t('payments.title')" :summary="payments.number">
      <div
        v-for="(row, index) in payments.rows"
        :key="index"
        class="flex gap-3 py-[9px] text-small border-t border-rule first:border-t-0"
      >
        <span class="flex-1">{{ row.label }}</span>
        <span class="w-28 flex-none text-right font-semibold tabular-nums">{{ formatEuros(row.amount) }}</span>
      </div>
      <div class="flex items-baseline gap-3 border-t border-line-hover pt-2.5">
        <span class="flex-1 text-small font-semibold">{{ $t('quotes.total') }}</span>
        <span class="w-28 flex-none text-right text-body font-semibold tracking-[-0.018em] tabular-nums">{{ formatEuros(total) }}</span>
      </div>
    </Card>
  </div>
</template>
