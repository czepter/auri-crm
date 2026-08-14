<script setup lang="ts">
import { computed } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { basisPointsToPercent, formatEuros } from '@/admin/lib/money'
import PortalFooter from '@/portal/components/PortalFooter.vue'
import PortalHeader from '@/portal/components/PortalHeader.vue'
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
function chipClass(status: string): string {
  return ({ sent: 'now', accepted: 'done' } as Record<string, string>)[status] ?? 'next'
}

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

  <main class="pwrap">
    <h1>{{ $t('portal.quoteTitle') }}</h1>

    <div v-if="quote" class="card static" style="margin-top: var(--s5)">
      <div class="cardhead">
        <span class="kick">{{ quote.number ? `${$t('quotes.title')} ${quote.number}` : $t('quotes.title') }}</span>
        <span :class="['chip', chipClass(quote.status)]">{{ quote.statusLabel }}</span>
      </div>

      <p v-if="quote.validUntil" class="hint" style="margin-bottom: var(--s4)">
        {{ $t('quotes.validUntil') }} {{ quote.validUntil }}
      </p>

      <table class="qt">
        <tr v-for="item in quote.items" :key="item.id" :class="{ incl: item.isIncluded }">
          <td>
            {{ item.title }}
            <div v-if="item.isIncluded || item.description" class="d">
              {{ item.isIncluded ? $t('quotes.included') : item.description }}
            </div>
          </td>
          <td>{{ item.isIncluded ? $t('quotes.includedPrice') : formatEuros(item.lineTotal) }}</td>
        </tr>
        <tr v-if="!quote.discountAmount" class="sum">
          <td>{{ $t('quotes.total') }}</td>
          <td>{{ formatEuros(quote.total) }}</td>
        </tr>
        <template v-else>
          <tr class="sum">
            <td>{{ $t('quotes.total') }}</td>
            <td>{{ formatEuros(quote.total) }}</td>
          </tr>
          <tr class="tax">
            <td>{{ $t('quotes.discount') }}</td>
            <td>−{{ formatEuros(quote.discountAmount) }}</td>
          </tr>
          <tr class="total">
            <td>{{ $t('quotes.net') }}</td>
            <td>{{ formatEuros(quote.net) }}</td>
          </tr>
        </template>

        <tr v-if="quote.vatMode === 'small_business'" class="tax">
          <td colspan="2">{{ quote.vatNote }}</td>
        </tr>
        <template v-else-if="quote.vatMode === 'standard'">
          <tr class="tax">
            <td>{{ $t('quotes.vat', { rate: basisPointsToPercent(quote.vatRate) }) }}</td>
            <td>{{ formatEuros(quote.vatAmount ?? 0) }}</td>
          </tr>
          <tr class="total">
            <td>{{ $t('quotes.total') }}</td>
            <td>{{ formatEuros(quote.net + (quote.vatAmount ?? 0)) }}</td>
          </tr>
        </template>
      </table>

      <p v-if="quote.status === 'accepted'" class="legal" style="margin-top: var(--s4)">
        {{ $t('portal.quoteAccepted', { date: quote.acceptedAt }) }}
      </p>

      <template v-else-if="quote.status === 'sent'">
        <p v-if="quote.expired" class="err" style="margin-top: var(--s4)">{{ $t('portal.quoteExpired') }}</p>
        <p v-if="rejection" class="err" style="margin-top: var(--s4)">{{ rejection }}</p>

        <!-- T-08: the button is the affordance — it never renders once the
             switch is off, matching the 404 the accept route itself gives. -->
        <Button
          v-if="canAccept"
          class="wide"
          type="button"
          :disabled="form.processing"
          style="margin-top: var(--s4)"
          @click="accept"
        >
          {{ $t('portal.quoteAccept') }}
        </Button>
      </template>
    </div>

    <p v-else class="hint">{{ $t('portal.quoteNone') }}</p>
  </main>

  <PortalFooter />
</template>
