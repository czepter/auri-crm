<script setup lang="ts">
import { computed } from 'vue'
import { useForm, usePage } from '@inertiajs/vue3'
import { basisPointsToPercent, formatEuros } from '@/admin/lib/money'
import PortalDot from '@/portal/components/PortalDot.vue'
import PortalFooter from '@/portal/components/PortalFooter.vue'
import PortalHeader from '@/portal/components/PortalHeader.vue'
import PortalShell from '@/portal/components/PortalShell.vue'
import PortalWrap from '@/portal/components/PortalWrap.vue'
import QuoteSlider from '@/portal/components/QuoteSlider.vue'
import { splitStages } from '@/portal/lib/paymentStages'
import { Button } from '@/ui/button'
import { Checkbox } from '@/ui/checkbox'
import { Label } from '@/ui/label'

type QuoteItem = {
  id: string
  title: string
  description: string | null
  isIncluded: boolean
  netTotal: number
}

type Quote = {
  id: string
  // Q-08: null heisst klassisches Angebot — dann steht unten die Tabelle,
  // die vor dieser Runde hier stand, unveraendert.
  hourlyRate: number | null
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
  vatLines: { rate: number; net: number; vat: number }[]
  items: QuoteItem[]
  intro: string | null
  outro: string | null
  paymentStages: { id: string; percent: number; label: string; minimum: number }[]
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
  // Q-10: wie viele Schaufensterbilder es gibt. Null heisst kein Raster.
  showcaseCount: number
  // QuoteShareController's own accept/image routes, for a quote opened
  // through its share link rather than a portal session — same screen,
  // different targets. Both default to the session routes every logged-in
  // contact uses.
  acceptUrl?: string
  imageUrlBase?: string
}>()

const portalLegal = computed(() => usePage().props.portalLegal as { agbUrl: string | null; widerrufUrl: string | null } | undefined)
const agbUrl = computed(() => portalLegal.value?.agbUrl ?? null)
const widerrufUrl = computed(() => portalLegal.value?.widerrufUrl ?? null)

const form = useForm({ terms: false })

// AcceptQuote's own re-check rejects under a 'quote' key, not a field this
// form declares — see App\Quotes\Actions\AcceptQuote.
const rejection = computed(() => (form.errors as Record<string, string>).quote ?? (form.errors as Record<string, string>).terms)

function accept() {
  form.post(props.acceptUrl ?? `/projekte/${props.project.id}/angebot/annehmen`, { preserveScroll: true })
}


// The dot says the same thing the label does, one beat earlier. Warm means
// this one is waiting on the client.
const state = computed(() => (props.quote?.status === 'accepted' ? 'done' : 'open'))

// Choice mode already gets its own breakdown from QuoteSlider's own
// `stages`; this is the classic-table half of the same split, against the
// same gross total the table above already prints.
const stages = computed(() => {
  const quote = props.quote

  if (!quote) return []

  const gross = quote.net + (quote.vatMode === 'standard' ? (quote.vatAmount ?? 0) : 0)

  return splitStages(gross, quote.paymentStages)
})

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

      <!-- #480: Editor.js-HTML, beim Speichern bereinigt, Felder escaped (QuoteController). -->
      <div
        v-if="quote?.intro"
        class="animate-rise [animation-delay:190ms] motion-reduce:animate-none mt-8 max-w-[62ch] text-[15.5px] leading-[1.6] [&_p+*]:mt-3 [&_*+p]:mt-3 [&_ul]:list-disc [&_ul]:pl-5 [&_ol]:list-decimal [&_ol]:pl-5"
        v-html="quote.intro"
      />

      <QuoteSlider
        v-if="quote && quote.hourlyRate !== null"
        class="animate-rise [animation-delay:230ms] motion-reduce:animate-none"
        :project="project"
        :quote="(quote as unknown as App.Quotes.Data.QuoteData)"
        :can-accept="canAccept"
        :accept-url="acceptUrl"
        :image-url-base="imageUrlBase"
        :expired="quote.expired"
        :accepted-at="quote.acceptedAt"
        :showcase-count="showcaseCount"
      />

      <div v-else-if="quote" class="animate-rise [animation-delay:230ms] motion-reduce:animate-none mt-12 max-w-[680px] border-t border-border pt-8">
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
              <div v-if="item.description" class="text-[13px] leading-[1.45] text-muted-foreground">
                {{ item.description }}
              </div>
            </td>
            <td>{{ item.isIncluded ? $t('quotes.includedPrice') : formatEuros(item.netTotal) }}</td>
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
            <!-- #412: eine Zeile je Steuersatz (§ 14 Abs. 4 Nr. 7 UStG). -->
            <tr v-for="line in quote.vatLines" :key="line.rate" :class="[ROW, '[&>td]:py-1! [&>td]:text-muted-foreground']">
              <td>{{ $t('quotes.vat', { rate: basisPointsToPercent(line.rate) }) }}</td>
              <td>{{ formatEuros(line.vat) }}</td>
            </tr>
            <tr :class="[ROW, '[&>td]:pt-2! [&>td]:font-serif [&>td]:text-[22px]!']">
              <td>{{ $t('quotes.total') }}</td>
              <td>{{ formatEuros(quote.net + (quote.vatAmount ?? 0)) }}</td>
            </tr>
          </template>
        </table>

        <table v-if="stages.length" class="mt-8 w-full max-w-[420px] border-collapse">
          <tr :class="[ROW, '[&>td]:border-b-0']">
            <td colspan="2" class="text-[11px] leading-none font-semibold tracking-[0.17em] uppercase text-muted-foreground">
              {{ $t('quotes.payment') }}
            </td>
          </tr>
          <tr v-for="stage in stages" :key="stage.label" :class="[ROW, '[&>td]:text-muted-foreground']">
            <td>{{ stage.label }}</td>
            <td>{{ formatEuros(stage.amount) }}</td>
          </tr>
        </table>

        <div v-if="quote.outro" class="mt-8 max-w-[62ch] text-[15.5px] leading-[1.6] [&_p+*]:mt-3 [&_*+p]:mt-3 [&_ul]:list-disc [&_ul]:pl-5 [&_ol]:list-decimal [&_ol]:pl-5" v-html="quote.outro" />

        <p v-if="quote.status === 'accepted'" class="mt-8 text-[14.5px] text-muted-foreground">
          {{ $t('portal.quoteAccepted', { date: quote.acceptedAt }) }}
        </p>

        <template v-else-if="quote.status === 'sent'">
          <p v-if="quote.expired" class="mt-8 text-[14.5px] text-destructive">{{ $t('portal.quoteExpired') }}</p>
          <p v-if="rejection" class="mt-8 text-[14.5px] text-destructive">{{ rejection }}</p>

          <!-- T-08: the button is the affordance — it never renders once the
               switch is off, matching the 404 the accept route itself gives. -->
          <template v-if="canAccept">
            <div
              v-if="agbUrl || widerrufUrl"
              class="mt-8 flex items-start gap-3.5 text-[13.5px] leading-[1.5] [&_[data-slot=checkbox]]:mt-0.5 [&_[data-slot=checkbox]]:size-[17px] [&_[data-slot=checkbox]]:flex-none [&_[data-slot=checkbox][data-state=checked]]:border-action [&_[data-slot=checkbox][data-state=checked]]:bg-action"
            >
              <Checkbox id="quote-terms" v-model="form.terms" required />
              <Label variant="inline" for="quote-terms">
                <a v-if="agbUrl" :href="agbUrl" target="_blank" rel="noopener" class="underline">{{ $t('portal.footerAgb') }}</a>
                <template v-if="agbUrl && widerrufUrl"> {{ $t('portal.quoteTermsAnd') }} </template>
                <a v-if="widerrufUrl" :href="widerrufUrl" target="_blank" rel="noopener" class="underline">{{ $t('portal.footerWiderruf') }}</a>
                {{ $t('portal.quoteTerms') }}
              </Label>
            </div>

            <Button
              class="mt-8"
              variant="portal"
              type="button"
              :disabled="form.processing || ((agbUrl !== null || widerrufUrl !== null) && !form.terms)"
              @click="accept"
            >
              {{ $t('portal.quoteAccept') }}
            </Button>
          </template>
        </template>
      </div>

      <p v-else class="animate-rise [animation-delay:230ms] motion-reduce:animate-none mt-12 text-[15px] text-muted-foreground">{{ $t('portal.quoteNone') }}</p>
    </PortalWrap>

    <PortalFooter />
  </PortalShell>
</template>
