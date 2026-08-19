<script setup lang="ts">
import { computed } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { basisPointsToPercent, formatEuros } from '@/admin/lib/money'
import PortalFooter from '@/portal/components/PortalFooter.vue'
import PortalHeader from '@/portal/components/PortalHeader.vue'
import PortalWrap from '@/portal/components/PortalWrap.vue'
import { Button } from '@/ui/button'

type QuoteItem = {
  id: string
  title: string
  description: string | null
  isIncluded: boolean
  lineTotal: number
}

type Quote = {
  id: string
  status: string
  statusLabel: string
  number: string | null
  validUntil: string | null
  total: number
  discountAmount: number
  net: number
  vatMode: string | null
  vatRate: number | null
  vatNote: string | null
  vatAmount: number | null
  items: QuoteItem[]
  // Display only — see App\Quotes\Actions\AcceptQuote for the re-check that
  // actually gates acceptance. This flag never reaches the accept request.
  expired: boolean
  acceptedAt: string | null
}

const props = defineProps<{
  contact: { name: string }
  showSwitcher: boolean
  project: { id: string; name: string }
  // T-08's "accept quotes online" switch (default off) — the affordance half
  // of the same rule EnsurePortalCapability enforces on the accept route
  // itself. Never hides an already-accepted quote, only the button that
  // would accept a new one.
  canAccept: boolean
  quote: Quote | null
}>()

// The mockup's own three chip states (tokens.css), the same mapping the
// admin quotes screen uses.
/*
 * tokens.css `.chip` and its four tones, with portal.css's own size step on
 * top. Inline rather than a component: the plan extracts at the third screen
 * that needs a pattern, and this is the second in the portal.
 */
const CHIP = 'flex-none rounded-[var(--r-sm)] px-[9px] py-1 text-[10.5px] leading-[1.5] font-semibold tracking-[0.04em] uppercase'

function chipTone(status: string): string {
  return (
    {
      sent: 'bg-due-bg text-due',
      accepted: 'bg-ok-bg text-ok',
    } as Record<string, string>
  )[status] ?? 'bg-muted text-muted-foreground'
}

const SUM = '[&>td]:border-t [&>td]:border-border [&>td]:pt-2.5! [&>td]:font-semibold'
const TAX = '[&>td]:py-0.5! [&>td]:text-muted-foreground'
const TOTAL = '[&>td]:pt-2! [&>td]:text-[14px]! [&>td]:font-bold'

const form = useForm({})

// AcceptQuote's own re-check rejects under a 'quote' key, not a field this
// empty form ever declares — see App\Quotes\Actions\AcceptQuote.
const rejection = computed(() => (form.errors as Record<string, string>).quote)

function accept() {
  form.post(`/projekte/${props.project.id}/angebot/annehmen`, { preserveScroll: true })
}
</script>

<template>
  <PortalHeader :contact="contact" :show-switcher="showSwitcher" />

  <PortalWrap>
    <h1>{{ $t('portal.quoteTitle') }}</h1>

    <div v-if="quote" class="relative mt-8 block cursor-default rounded-md border border-border p-3.5 text-inherit no-underline">
      <div class="mb-5 flex items-center justify-between">
        <span class="mb-[5px] text-[11px] leading-none font-semibold tracking-[0.1em] uppercase text-faint">{{ quote.number ? `${$t('quotes.title')} ${quote.number}` : $t('quotes.title') }}</span>
        <span :class="[CHIP, chipTone(quote.status)]">{{ quote.statusLabel }}</span>
      </div>

      <p v-if="quote.validUntil" class="mb-5 text-[11.5px] leading-[1.45] font-normal text-faint">
        {{ $t('quotes.validUntil') }} {{ quote.validUntil }}
      </p>

      <table
        class="w-full border-collapse [&_td]:py-2 [&_td]:align-top [&_td]:text-[13px] [&_td]:leading-[1.4] [&_td]:font-normal [&_td:last-child]:text-right [&_td:last-child]:tabular-nums [&_td:last-child]:whitespace-nowrap"
      >
        <tr v-for="item in quote.items" :key="item.id" :class="item.isIncluded && '[&>td]:text-muted-foreground [&>td:last-child]:text-faint'">
          <td>
            {{ item.title }}
            <div
              v-if="item.isIncluded || item.description"
              class="text-[11.5px] leading-[1.4] font-normal text-muted-foreground"
            >
              {{ item.isIncluded ? $t('quotes.included') : item.description }}
            </div>
          </td>
          <td>{{ item.isIncluded ? $t('quotes.includedPrice') : formatEuros(item.lineTotal) }}</td>
        </tr>
        <tr v-if="!quote.discountAmount" :class="SUM">
          <td>{{ $t('quotes.total') }}</td>
          <td>{{ formatEuros(quote.total) }}</td>
        </tr>
        <template v-else>
          <tr :class="SUM">
            <td>{{ $t('quotes.total') }}</td>
            <td>{{ formatEuros(quote.total) }}</td>
          </tr>
          <tr :class="TAX">
            <td>{{ $t('quotes.discount') }}</td>
            <td>−{{ formatEuros(quote.discountAmount) }}</td>
          </tr>
          <tr :class="TOTAL">
            <td>{{ $t('quotes.net') }}</td>
            <td>{{ formatEuros(quote.net) }}</td>
          </tr>
        </template>

        <tr v-if="quote.vatMode === 'small_business'" :class="TAX">
          <td colspan="2">{{ quote.vatNote }}</td>
        </tr>
        <template v-else-if="quote.vatMode === 'standard'">
          <tr :class="TAX">
            <td>{{ $t('quotes.vat', { rate: basisPointsToPercent(quote.vatRate) }) }}</td>
            <td>{{ formatEuros(quote.vatAmount ?? 0) }}</td>
          </tr>
          <tr :class="TOTAL">
            <td>{{ $t('quotes.total') }}</td>
            <td>{{ formatEuros(quote.net + (quote.vatAmount ?? 0)) }}</td>
          </tr>
        </template>
      </table>

      <p v-if="quote.status === 'accepted'" class="mt-5 text-[12.5px] leading-[1.6] font-normal text-muted-foreground">
        {{ $t('portal.quoteAccepted', { date: quote.acceptedAt }) }}
      </p>

      <template v-else-if="quote.status === 'sent'">
        <p v-if="quote.expired" class="mt-5 text-[11.5px] leading-[1.45] text-destructive">{{ $t('portal.quoteExpired') }}</p>
        <p v-if="rejection" class="mt-5 text-[11.5px] leading-[1.45] text-destructive">{{ rejection }}</p>

        <!-- T-08: the button is the affordance — it never renders once the
             switch is off, matching the 404 the accept route itself gives. -->
        <Button
          v-if="canAccept"
          class="wide mt-5"
          type="button"
          :disabled="form.processing"
          @click="accept"
        >
          {{ $t('portal.quoteAccept') }}
        </Button>
      </template>
    </div>

    <p v-else class="text-[11.5px] leading-[1.45] font-normal text-faint">{{ $t('portal.quoteNone') }}</p>
  </PortalWrap>

  <PortalFooter />
</template>
