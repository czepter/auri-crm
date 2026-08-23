<script setup lang="ts">
import { computed } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { basisPointsToPercent, formatEuros } from '@/admin/lib/money'
import PortalDot from '@/portal/components/PortalDot.vue'
import PortalFooter from '@/portal/components/PortalFooter.vue'
import PortalHeader from '@/portal/components/PortalHeader.vue'
import PortalShell from '@/portal/components/PortalShell.vue'
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

const form = useForm({})

// AcceptQuote's own re-check rejects under a 'quote' key, not a field this
// empty form ever declares — see App\Quotes\Actions\AcceptQuote.
const rejection = computed(() => (form.errors as Record<string, string>).quote)

function accept() {
  form.post(`/projekte/${props.project.id}/angebot/annehmen`, { preserveScroll: true })
}


// The dot says the same thing the label does, one beat earlier. Warm means
// this one is waiting on the client.
const state = computed(() => (props.quote?.status === 'accepted' ? 'done' : 'open'))

const ROW = '[&>td]:py-2 [&>td]:align-top [&>td]:text-[14.5px] [&>td]:leading-[1.5] [&>td:last-child]:text-right [&>td:last-child]:tabular-nums [&>td:last-child]:whitespace-nowrap'
</script>

<template>
  <PortalShell>
    <PortalHeader :switcher="{ label: project.name, toAll: showSwitcher }" />

    <PortalWrap class="pb-40">
      <section class="pt-[var(--band)]">
        <p class="animate-rise motion-reduce:animate-none mb-4 text-[11px] leading-none font-semibold tracking-[0.17em] uppercase text-muted-foreground">{{ project.name }}</p>
        <h1 class="animate-rise [animation-delay:140ms] motion-reduce:animate-none font-serif text-[clamp(36px,5vw,52px)] leading-[var(--display-leading)] font-normal tracking-[var(--display-tracking)]">
          {{ $t('portal.quoteTitle') }}
        </h1>
      </section>

      <div v-if="quote" class="animate-rise [animation-delay:230ms] motion-reduce:animate-none mt-12 max-w-[680px] border-t border-border pt-8">
        <!-- The heading above already says "Angebot"; only a number adds
             anything here. -->
        <div class="flex flex-wrap items-baseline justify-between gap-4">
          <span v-if="quote.number" class="text-[11px] leading-none font-semibold tracking-[0.17em] uppercase tabular-nums text-muted-foreground">
            {{ quote.number }}
          </span>
          <span class="ml-auto inline-flex items-center gap-2 text-[13px] text-muted-foreground">
            <PortalDot :state="state" />{{ quote.statusLabel }}
            <template v-if="quote.validUntil && quote.status === 'sent'">
              · {{ $t('quotes.validUntil') }} {{ quote.validUntil }}
            </template>
          </span>
        </div>

        <table class="mt-8 w-full border-collapse">
          <tr v-for="item in quote.items" :key="item.id" :class="[ROW, item.isIncluded && '[&>td]:text-muted-foreground']">
            <td>
              {{ item.title }}
              <div v-if="item.isIncluded || item.description" class="text-[13px] leading-[1.45] text-muted-foreground">
                {{ item.isIncluded ? $t('quotes.included') : item.description }}
              </div>
            </td>
            <td>{{ item.isIncluded ? $t('quotes.includedPrice') : formatEuros(item.lineTotal) }}</td>
          </tr>

          <tr :class="[ROW, '[&>td]:border-t [&>td]:border-border [&>td]:pt-3! [&>td]:font-semibold']">
            <td>{{ $t('quotes.total') }}</td>
            <td>{{ formatEuros(quote.total) }}</td>
          </tr>
          <template v-if="quote.discountAmount">
            <tr :class="[ROW, '[&>td]:py-1! [&>td]:text-muted-foreground']">
              <td>{{ $t('quotes.discount') }}</td>
              <td>−{{ formatEuros(quote.discountAmount) }}</td>
            </tr>
            <tr :class="[ROW, '[&>td]:pt-2! [&>td]:font-serif [&>td]:text-[22px]!']">
              <td>{{ $t('quotes.net') }}</td>
              <td>{{ formatEuros(quote.net) }}</td>
            </tr>
          </template>

          <tr v-if="quote.vatMode === 'small_business'" :class="[ROW, '[&>td]:py-1! [&>td]:text-[13px]! [&>td]:text-muted-foreground']">
            <td colspan="2">{{ quote.vatNote }}</td>
          </tr>
          <template v-else-if="quote.vatMode === 'standard'">
            <tr :class="[ROW, '[&>td]:py-1! [&>td]:text-muted-foreground']">
              <td>{{ $t('quotes.vat', { rate: basisPointsToPercent(quote.vatRate) }) }}</td>
              <td>{{ formatEuros(quote.vatAmount ?? 0) }}</td>
            </tr>
            <tr :class="[ROW, '[&>td]:pt-2! [&>td]:font-serif [&>td]:text-[22px]!']">
              <td>{{ $t('quotes.total') }}</td>
              <td>{{ formatEuros(quote.net + (quote.vatAmount ?? 0)) }}</td>
            </tr>
          </template>
        </table>

        <p v-if="quote.status === 'accepted'" class="mt-8 text-[14.5px] text-muted-foreground">
          {{ $t('portal.quoteAccepted', { date: quote.acceptedAt }) }}
        </p>

        <template v-else-if="quote.status === 'sent'">
          <p v-if="quote.expired" class="mt-8 text-[14.5px] text-destructive">{{ $t('portal.quoteExpired') }}</p>
          <p v-if="rejection" class="mt-8 text-[14.5px] text-destructive">{{ rejection }}</p>

          <!-- T-08: the button is the affordance — it never renders once the
               switch is off, matching the 404 the accept route itself gives. -->
          <Button
            v-if="canAccept"
            class="mt-8"
            variant="portal"
            type="button"
            :disabled="form.processing"
            @click="accept"
          >
            {{ $t('portal.quoteAccept') }}
          </Button>
        </template>
      </div>

      <p v-else class="animate-rise [animation-delay:230ms] motion-reduce:animate-none mt-12 text-[15px] text-muted-foreground">{{ $t('portal.quoteNone') }}</p>
    </PortalWrap>

    <PortalFooter />
  </PortalShell>
</template>
